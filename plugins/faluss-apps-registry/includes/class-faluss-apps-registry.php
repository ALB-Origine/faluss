<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Trusted owner registry and member-scoped apps.registry resolver. */
final class Faluss_Apps_Registry {
    const DOCUMENT_TYPE = 'faluss.app-capability-manifest';
    const CONTRACT_VERSION = '1.0.0';
    const READ_MODEL_VERSION = '1.0.0';

    private static $sources = array();
    private static $source_conflict = false;

    public static function boot() {
        add_action( 'plugins_loaded', array( __CLASS__, 'register_federation_validator' ), 20 );
        if ( did_action( 'plugins_loaded' ) ) {
            self::register_federation_validator();
        }
    }

    /** @return true|false|WP_Error */
    public static function register_federation_validator() {
        if ( ! class_exists( 'Faluss_Federation_Providers' ) || ! class_exists( 'Faluss_Federation_Crypto' ) || ! method_exists( 'Faluss_Federation_Providers', 'register_manifest_contract_validator' ) || ! Faluss_Federation_Crypto::transport_ready() ) {
            return false;
        }
        return Faluss_Federation_Providers::register_manifest_contract_validator( self::DOCUMENT_TYPE, self::CONTRACT_VERSION, array( 'Faluss_Apps_Registry_Manifest_Validator', 'validate' ) );
    }

    public static function manifest_contract() {
        return array( 'document_type' => self::DOCUMENT_TYPE, 'contract_version' => self::CONTRACT_VERSION );
    }

    public static function validate_manifest( $manifest, $payload_contract, $request_context ) {
        return Faluss_Apps_Registry_Manifest_Validator::validate( $manifest, $payload_contract, $request_context );
    }

    /** Register one closed, server-owned application source. */
    public static function register_source( $descriptor ) {
        if ( ! self::valid_source_descriptor( $descriptor ) ) {
            self::$source_conflict = true;
            return self::failure();
        }
        $app_key = $descriptor['app_key'];
        if ( isset( self::$sources[ $app_key ] ) ) {
            self::$source_conflict = true;
            return self::failure();
        }
        self::$sources[ $app_key ] = $descriptor;
        return true;
    }

    /** @return array<string,mixed>|WP_Error */
    public static function read_for_member( $faluss_id, $surface, $consumer_version ) {
        if ( self::$source_conflict || ! self::is_uuid_v4( $faluss_id ) || ! in_array( $surface, array( 'portal', 'master_profile', 'me' ), true ) || '1.0.0' !== $consumer_version || ! self::is_hub_authority() ) {
            return self::failure();
        }

        $sources = self::$sources;
        ksort( $sources, SORT_STRING );
        $applications = array();
        foreach ( $sources as $source ) {
            $application = self::resolve_application( $source, $faluss_id, $surface, $consumer_version );
            if ( is_array( $application ) ) {
                $applications[] = $application;
            }
        }

        $document = array(
            'contract_version' => self::READ_MODEL_VERSION,
            'namespace' => 'apps.registry',
            'consumer' => array( 'surface' => $surface, 'consumer_version' => $consumer_version ),
            'freshness' => array( 'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'max_age_seconds' => 60, 'stale_behavior' => 'omit' ),
            'source' => array( 'type' => 'registry_read_model', 'engine' => 'faluss-apps-registry', 'read_model' => 'apps-registry', 'source_version' => self::READ_MODEL_VERSION ),
            'applications' => $applications,
            'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null ),
        );
        return Faluss_Apps_Registry_Read_Model_Validator::validate( $document ) ? $document : self::failure();
    }

    private static function resolve_application( $source, $faluss_id, $surface, $consumer_version ) {
        $manifest = 'local_owner' === $source['source_type'] ? self::local_manifest( $source ) : self::federated_manifest( $source );
        if ( ! is_array( $manifest ) ) {
            return null;
        }
        try {
            $relationship = call_user_func( $source['relationship_resolver'], $faluss_id );
        } catch ( Throwable $exception ) {
            unset( $exception );
            return null;
        }
        if ( is_wp_error( $relationship ) || ! in_array( $relationship, array( 'active', 'inactive', 'not_linked' ), true ) ) {
            return null;
        }

        $availability = 'active' === $manifest['product_state'] ? 'available' : ( 'retired' === $manifest['product_state'] ? 'retired' : 'unavailable' );
        $manifest_compatible = self::compatible( $manifest['compatibility'], $consumer_version );
        $capabilities = array();
        foreach ( $manifest['capabilities'] as $declared ) {
            $resolved = self::resolve_capability( $source, $declared, $faluss_id, $surface, $consumer_version, $availability, $relationship, $manifest_compatible );
            if ( is_array( $resolved ) ) {
                $capabilities[] = $resolved;
            }
        }
        usort( $capabilities, function( $left, $right ) { return strcmp( $left['capability_key'], $right['capability_key'] ); } );
        return array( 'app_key' => $source['app_key'], 'availability' => $availability, 'member_relationship' => $relationship, 'capabilities' => $capabilities );
    }

    private static function resolve_capability( $source, $declared, $faluss_id, $surface, $consumer_version, $availability, $relationship, $manifest_compatible ) {
        if ( null === $source['capability_resolver'] ) {
            return null;
        }
        try {
            $owner_result = call_user_func( $source['capability_resolver'], $faluss_id, $declared['capability_key'] );
        } catch ( Throwable $exception ) {
            unset( $exception );
            return null;
        }
        if ( is_wp_error( $owner_result ) || ! self::valid_owner_capability_result( $owner_result, $declared['capability_key'], $source['owner'] ) ) {
            return null;
        }

        $surface_status = $manifest_compatible && self::compatible( $declared['compatibility'], $consumer_version ) ? 'compatible' : 'incompatible';
        $active = 'available' === $availability && 'active' === $relationship && 'enabled' === $owner_result['state'] && 'available' === $owner_result['specialized_read_model']['status'] && 'compatible' === $surface_status && self::fresh_now( $owner_result['specialized_read_model']['freshness'] );
        $bindings = array();
        if ( $active ) {
            foreach ( $declared['requested_bindings'] as $binding ) {
                if ( self::surface_accepts_slot( $surface, $binding['slot'] ) && in_array( $binding['interface'], $declared['interfaces'], true ) ) {
                    $bindings[] = array( 'slot' => $binding['slot'], 'interface' => $binding['interface'], 'binding_state' => 'active' );
                }
            }
        }
        usort( $bindings, function( $left, $right ) { return strcmp( $left['slot'] . "\x1F" . $left['interface'], $right['slot'] . "\x1F" . $right['interface'] ); } );

        $actions = array();
        $has_delegated_binding = false;
        foreach ( $bindings as $binding ) {
            $has_delegated_binding = $has_delegated_binding || 'delegated_action' === $binding['interface'];
        }
        if ( $active && $has_delegated_binding ) {
            $declared_actions = array_column( $declared['symbolic_actions'], null, 'action_key' );
            foreach ( $owner_result['allowed_action_keys'] as $action_key ) {
                if ( isset( $declared_actions[ $action_key ] ) && 'delegated_action' === $declared_actions[ $action_key ]['kind'] && in_array( 'delegated_action', $declared['interfaces'], true ) ) {
                    $actions[] = array( 'action_key' => $action_key, 'owner' => $source['owner'], 'delegation' => array( 'type' => 'owner_delegated_action', 'target' => $action_key ) );
                }
            }
        }
        usort( $actions, function( $left, $right ) { return strcmp( $left['action_key'], $right['action_key'] ); } );
        return array(
            'capability_key' => $declared['capability_key'],
            'owner' => $source['owner'],
            'interfaces' => array_values( $declared['interfaces'] ),
            'state' => $owner_result['state'],
            'specialized_read_model' => $owner_result['specialized_read_model'],
            'surface_compatibility' => array( 'status' => $surface_status, 'consumer_version' => $consumer_version ),
            'active_bindings' => $bindings,
            'allowed_actions' => $actions,
        );
    }

    private static function local_manifest( $source ) {
        try {
            $manifest = call_user_func( $source['manifest_resolver'] );
        } catch ( Throwable $exception ) {
            unset( $exception );
            return null;
        }
        return self::manifest_is_valid( $manifest, $source ) ? $manifest : null;
    }

    private static function federated_manifest( $source ) {
        if ( ! class_exists( 'Faluss_Federation_Client' ) || ! class_exists( 'Faluss_Federation_Policy' ) || ! method_exists( 'Faluss_Federation_Client', 'manifest_read' ) || ! method_exists( 'Faluss_Federation_Policy', 'find_outbound_peer' ) ) {
            return null;
        }
        $peer = Faluss_Federation_Policy::find_outbound_peer( $source['peer_node_id'], $source['peer_app_key'] );
        if ( is_wp_error( $peer ) || ! is_string( $peer['key_id'] ?? null ) || '' === $peer['key_id'] ) {
            return null;
        }
        $cache_key = self::manifest_cache_key( $source, $peer );
        $cached = get_transient( $cache_key );
        if ( self::cached_manifest_is_valid( $cached, $source, $peer ) ) {
            return $cached['manifest'];
        }
        if ( false !== $cached ) {
            delete_transient( $cache_key );
        }

        $response = Faluss_Federation_Client::manifest_read( $source['peer_node_id'], $source['peer_app_key'], $source['requested_manifest_version'] );
        if ( ! self::federated_response_is_valid( $response, $source, $peer ) ) {
            return null;
        }
        $expires = self::timestamp( $response['expires_at'] );
        $ttl = min( 300, $expires - time() );
        if ( $ttl < 1 ) {
            return null;
        }
        $value = array(
            'cached_at' => time(),
            'contract_version' => $source['contract_version'],
            'document_type' => $source['document_type'],
            'expires_at' => $response['expires_at'],
            'manifest' => $response['payload'],
            'manifest_version' => $source['requested_manifest_version'],
            'responder_key_id' => $response['responder']['key_id'],
        );
        set_transient( $cache_key, $value, $ttl );
        return $response['payload'];
    }

    private static function federated_response_is_valid( $response, $source, $peer ) {
        if ( is_wp_error( $response ) || ! is_array( $response ) || 'success' !== ( $response['status'] ?? null ) || ! array_key_exists( 'error', $response ) || null !== $response['error'] || ! self::exact_keys( $response['payload_contract'] ?? null, array( 'document_type', 'contract_version' ) ) || $source['document_type'] !== $response['payload_contract']['document_type'] || $source['contract_version'] !== $response['payload_contract']['contract_version'] || ! is_array( $response['payload'] ?? null ) || ! is_array( $response['responder'] ?? null ) ) {
            return false;
        }
        if ( $source['peer_node_id'] !== ( $response['responder']['node_id'] ?? null ) || $source['peer_app_key'] !== ( $response['responder']['app_key'] ?? null ) || $peer['key_id'] !== ( $response['responder']['key_id'] ?? null ) ) {
            return false;
        }
        $generated = self::timestamp( $response['generated_at'] ?? null );
        $expires = self::timestamp( $response['expires_at'] ?? null );
        return false !== $generated && false !== $expires && $generated <= time() + 60 && $expires > time() && $expires > $generated && $expires - $generated <= 300 && self::manifest_is_valid( $response['payload'], $source );
    }

    private static function cached_manifest_is_valid( $cached, $source, $peer ) {
        if ( ! self::exact_keys( $cached, array( 'cached_at', 'contract_version', 'document_type', 'expires_at', 'manifest', 'manifest_version', 'responder_key_id' ) ) || ! is_int( $cached['cached_at'] ) || $cached['cached_at'] > time() + 60 || $cached['cached_at'] + 300 < time() || $cached['document_type'] !== $source['document_type'] || $cached['contract_version'] !== $source['contract_version'] || $cached['manifest_version'] !== $source['requested_manifest_version'] || $cached['responder_key_id'] !== $peer['key_id'] ) {
            return false;
        }
        $expires = self::timestamp( $cached['expires_at'] );
        return false !== $expires && $expires > time() && self::manifest_is_valid( $cached['manifest'], $source );
    }

    private static function manifest_is_valid( $manifest, $source ) {
        $context = array(
            'operation' => 'manifest.read',
            'parameters' => array( 'app_key' => $source['app_key'], 'requested_manifest_version' => $source['requested_manifest_version'] ),
            'subject_context' => null,
            'sender' => array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub' ),
            'recipient' => array( 'node_id' => 'owner-node', 'app_key' => $source['app_key'] ),
        );
        return true === self::validate_manifest( $manifest, array( 'document_type' => $source['document_type'], 'contract_version' => $source['contract_version'] ), $context );
    }

    private static function valid_source_descriptor( $source ) {
        if ( ! is_array( $source ) || ! in_array( $source['source_type'] ?? null, array( 'local_owner', 'federated_peer' ), true ) ) {
            return false;
        }
        $common = array( 'app_key', 'source_type', 'document_type', 'contract_version', 'requested_manifest_version', 'owner', 'relationship_resolver', 'capability_resolver' );
        $expected = 'local_owner' === $source['source_type'] ? array_merge( $common, array( 'manifest_resolver' ) ) : array_merge( $common, array( 'peer_node_id', 'peer_app_key' ) );
        if ( ! self::exact_keys( $source, $expected ) || ! self::is_app_key( $source['app_key'] ) || $source['owner'] !== $source['app_key'] || self::DOCUMENT_TYPE !== $source['document_type'] || self::CONTRACT_VERSION !== $source['contract_version'] || self::CONTRACT_VERSION !== $source['requested_manifest_version'] || ! is_callable( $source['relationship_resolver'] ) || ( null !== $source['capability_resolver'] && ! is_callable( $source['capability_resolver'] ) ) ) {
            return false;
        }
        if ( 'local_owner' === $source['source_type'] ) {
            return is_callable( $source['manifest_resolver'] );
        }
        return self::is_app_key( $source['peer_node_id'] ) && $source['app_key'] === $source['peer_app_key'];
    }

    private static function valid_owner_capability_result( $result, $capability_key, $owner ) {
        if ( ! self::exact_keys( $result, array( 'capability_key', 'state', 'specialized_read_model', 'allowed_action_keys' ) ) || $capability_key !== $result['capability_key'] || ! in_array( $result['state'], array( 'enabled', 'disabled', 'temporarily_unavailable', 'not_supported' ), true ) || ! self::is_list( $result['allowed_action_keys'] ) || count( $result['allowed_action_keys'] ) !== count( array_unique( $result['allowed_action_keys'], SORT_STRING ) ) ) {
            return false;
        }
        foreach ( $result['allowed_action_keys'] as $action_key ) {
            if ( ! self::is_namespaced_key( $action_key, $owner ) ) {
                return false;
            }
        }
        $model = $result['specialized_read_model'];
        return self::exact_keys( $model, array( 'status', 'source', 'freshness' ) )
            && in_array( $model['status'], array( 'available', 'unavailable', 'expired', 'not_supported' ), true )
            && self::exact_keys( $model['source'], array( 'engine', 'read_model', 'source_version' ) )
            && $owner === $model['source']['engine']
            && is_string( $model['source']['read_model'] )
            && self::is_semver( $model['source']['source_version'] )
            && self::freshness_shape( $model['freshness'] )
            && ( 'enabled' === $result['state'] || array() === $result['allowed_action_keys'] );
    }

    private static function compatible( $compatibility, $version ) {
        if ( ! is_array( $compatibility ) || ! is_string( $compatibility['minimum_consumer_version'] ?? null ) || ! is_array( $compatibility['compatible_with'] ?? null ) || version_compare( $version, $compatibility['minimum_consumer_version'], '<' ) || ! in_array( $version, $compatibility['compatible_with'], true ) ) {
            return false;
        }
        $sunset = $compatibility['sunset_at'] ?? null;
        return null === $sunset || ( false !== self::timestamp( $sunset ) && self::timestamp( $sunset ) > time() );
    }

    private static function surface_accepts_slot( $surface, $slot ) {
        $slots = array(
            'portal' => array( 'portal.apps.card_action', 'portal.analytics.dataset' ),
            'master_profile' => array( 'master_profile.module', 'master_profile.footer_action' ),
            'me' => array( 'me.studio.tab', 'me.studio.block_source', 'me.public.tab', 'me.public.block' ),
        );
        return isset( $slots[ $surface ] ) && in_array( $slot, $slots[ $surface ], true );
    }

    private static function freshness_shape( $freshness ) {
        return self::exact_keys( $freshness, array( 'generated_at', 'max_age_seconds', 'stale_behavior' ) ) && false !== self::timestamp( $freshness['generated_at'] ) && is_int( $freshness['max_age_seconds'] ) && $freshness['max_age_seconds'] >= 1 && $freshness['max_age_seconds'] <= 86400 && in_array( $freshness['stale_behavior'], array( 'omit', 'refresh_from_owner' ), true );
    }

    private static function fresh_now( $freshness ) {
        if ( ! self::freshness_shape( $freshness ) ) {
            return false;
        }
        $generated = self::timestamp( $freshness['generated_at'] );
        return $generated <= time() + 60 && $generated + $freshness['max_age_seconds'] >= time();
    }

    private static function manifest_cache_key( $source, $peer ) {
        $peer_binding = array( $peer['peer_node_id'] ?? '', $peer['peer_app_key'] ?? '', $peer['key_id'] ?? '', $peer['canonical_origin'] ?? '', $peer['key_state'] ?? '', $peer['valid_from'] ?? '', $peer['valid_until'] ?? '' );
        return '_faluss_apps_registry_manifest_v1_' . hash( 'sha256', $source['peer_node_id'] . "\x1F" . $source['peer_app_key'] . "\x1F" . $source['requested_manifest_version'] . "\x1F" . $source['contract_version'] . "\x1F" . implode( "\x1F", $peer_binding ) );
    }

    private static function is_hub_authority() {
        if ( ! class_exists( 'Faluss_Federation_Crypto' ) || ! method_exists( 'Faluss_Federation_Crypto', 'local_identity' ) ) {
            return false;
        }
        $identity = Faluss_Federation_Crypto::local_identity();
        return ! is_wp_error( $identity ) && 'hub-node' === ( $identity['node_id'] ?? null ) && 'faluss-hub' === ( $identity['app_key'] ?? null ) && 'https://faluss.com' === ( $identity['origin'] ?? null );
    }

    private static function exact_keys( $value, $keys ) {
        if ( ! is_array( $value ) ) {
            return false;
        }
        $actual = array_keys( $value );
        sort( $actual, SORT_STRING );
        sort( $keys, SORT_STRING );
        return $actual === $keys;
    }

    private static function is_list( $value ) { return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) ); }
    private static function is_app_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $value ); }
    private static function is_semver( $value ) { return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*\.[0-9]+\.[0-9]+$/D', $value ); }
    private static function is_namespaced_key( $value, $namespace ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9_.-]{1,127})+$/D', $value ) && 0 === strpos( $value, $namespace . '.' ); }
    private static function is_uuid_v4( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value ); }
    private static function timestamp( $value ) {
        if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2})$/D', $value ) ) {
            return false;
        }
        $normalized = 'Z' === substr( $value, -1 ) ? substr( $value, 0, -1 ) . '+00:00' : $value;
        $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:sP', $normalized );
        $errors = DateTimeImmutable::getLastErrors();
        return false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $normalized === $date->format( 'Y-m-d\TH:i:sP' ) ? $date->getTimestamp() : false;
    }
    private static function failure() { return new WP_Error( 'faluss_apps_registry_unavailable' ); }
}
