<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Specialized PHP runtime validator for the CAP v1 manifest contract. */
final class Faluss_Apps_Registry_Manifest_Validator {
    private const ROOT_KEYS = array( 'manifest_version', 'app_key', 'capability_namespace', 'owner', 'product_state', 'canonical_origins', 'public_presentation', 'official_asset', 'capabilities', 'compatibility' );
    private const INTERFACES = array( 'module_read_model', 'delegated_action', 'event_source', 'content_reference_source' );
    private const SLOTS = array( 'portal.apps.card_action', 'portal.analytics.dataset', 'master_profile.module', 'master_profile.footer_action', 'me.studio.tab', 'me.studio.block_source', 'me.public.tab', 'me.public.block', 'analytics.events', 'quests.events', 'progression.events' );
    private const EVENT_SLOTS = array( 'analytics.events', 'quests.events', 'progression.events' );

    public static function validate( $manifest, $payload_contract, $request_context ) {
        if ( ! self::valid_contract_and_context( $payload_contract, $request_context ) || ! self::exact_keys( $manifest, self::ROOT_KEYS ) || self::contains_forbidden_content( $manifest ) ) {
            return false;
        }
        if ( '1.0.0' !== $manifest['manifest_version'] || ! self::is_app_key( $manifest['app_key'] ) || $manifest['app_key'] !== $request_context['parameters']['app_key'] || ! self::is_app_key( $manifest['capability_namespace'] ) || $manifest['app_key'] !== $manifest['capability_namespace'] || ! in_array( $manifest['product_state'], array( 'planned', 'active', 'maintenance', 'retired' ), true ) ) {
            return false;
        }
        if ( ! self::valid_owner( $manifest['owner'], $manifest['capability_namespace'] ) || ! self::valid_origins( $manifest['canonical_origins'], $manifest['owner']['authority'] ) || ! self::valid_presentation( $manifest['public_presentation'] ) || ! self::valid_asset( $manifest['official_asset'], $manifest['capability_namespace'] ) || ! self::valid_capabilities( $manifest['capabilities'], $manifest['capability_namespace'] ) || ! self::valid_compatibility( $manifest['compatibility'], $manifest['capability_namespace'] ) ) {
            return false;
        }
        return true;
    }

    private static function valid_contract_and_context( $contract, $context ) {
        if ( ! self::exact_keys( $contract, array( 'document_type', 'contract_version' ) ) || 'faluss.app-capability-manifest' !== $contract['document_type'] || '1.0.0' !== $contract['contract_version'] || ! self::exact_keys( $context, array( 'operation', 'parameters', 'subject_context', 'sender', 'recipient' ) ) || 'manifest.read' !== $context['operation'] || null !== $context['subject_context'] ) {
            return false;
        }
        return self::exact_keys( $context['parameters'], array( 'app_key', 'requested_manifest_version' ) )
            && self::is_app_key( $context['parameters']['app_key'] )
            && '1.0.0' === $context['parameters']['requested_manifest_version']
            && is_array( $context['sender'] )
            && is_array( $context['recipient'] )
            && ( $context['recipient']['app_key'] ?? null ) === $context['parameters']['app_key'];
    }

    private static function valid_owner( $owner, $namespace ) {
        return self::exact_keys( $owner, array( 'engine', 'authority' ) )
            && $namespace === $owner['engine']
            && is_string( $owner['authority'] )
            && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9-]{1,63})*$/D', $owner['authority'] );
    }

    private static function valid_origins( $origins, $authority ) {
        if ( ! self::is_list( $origins ) || empty( $origins ) || count( $origins ) > 16 || count( $origins ) !== count( array_unique( $origins, SORT_STRING ) ) ) {
            return false;
        }
        foreach ( $origins as $origin ) {
            $parts = is_string( $origin ) ? wp_parse_url( $origin ) : false;
            $port = is_array( $parts ) && isset( $parts['port'] ) ? $parts['port'] : null;
            $canonical = is_array( $parts ) && isset( $parts['host'] ) ? 'https://' . $parts['host'] . ( null === $port ? '' : ':' . $port ) : '';
            if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? null ) || ! isset( $parts['host'] ) || ( null !== $port && ( ! is_int( $port ) || $port < 1 || $port > 65535 ) ) || isset( $parts['path'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || $origin !== $canonical || $authority !== strtolower( $parts['host'] ) ) {
                return false;
            }
        }
        return true;
    }

    private static function valid_presentation( $presentation ) {
        return self::exact_keys( $presentation, array( 'display_name', 'summary' ) )
            && self::bounded_text( $presentation['display_name'], 80 )
            && self::bounded_text( $presentation['summary'], 280 );
    }

    private static function valid_asset( $asset, $namespace ) {
        if ( null === $asset ) {
            return true;
        }
        if ( ! self::exact_keys( $asset, array( 'asset_key', 'mime_type', 'sha256', 'intrinsic_dimensions', 'distribution' ) ) || ! self::is_namespaced_key( $asset['asset_key'], $namespace ) || ! in_array( $asset['mime_type'], array( 'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp' ), true ) || ! is_string( $asset['sha256'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $asset['sha256'] ) || ! self::exact_keys( $asset['intrinsic_dimensions'], array( 'width', 'height' ) ) || ! in_array( $asset['distribution'], array( 'owner_hosted', 'immutable_embedded' ), true ) ) {
            return false;
        }
        $width = $asset['intrinsic_dimensions']['width'];
        $height = $asset['intrinsic_dimensions']['height'];
        return is_int( $width ) && is_int( $height ) && $width >= 1 && $width <= 32768 && $height >= 1 && $height <= 32768;
    }

    private static function valid_capabilities( $capabilities, $namespace ) {
        if ( ! self::is_list( $capabilities ) || count( $capabilities ) > 64 ) {
            return false;
        }
        $seen = array();
        foreach ( $capabilities as $capability ) {
            if ( ! self::exact_keys( $capability, array( 'capability_key', 'interfaces', 'requested_bindings', 'read_model_contract', 'symbolic_actions', 'compatibility' ) ) || ! self::is_namespaced_key( $capability['capability_key'], $namespace ) || isset( $seen[ $capability['capability_key'] ] ) || ! self::valid_interfaces( $capability['interfaces'] ) || ! self::valid_read_model_contract( $capability['read_model_contract'], $capability['interfaces'] ) || ! self::valid_bindings( $capability['requested_bindings'], $capability['interfaces'] ) || ! self::valid_actions( $capability['symbolic_actions'], $capability['interfaces'], $namespace ) || ! self::valid_compatibility( $capability['compatibility'], $namespace ) ) {
                return false;
            }
            $seen[ $capability['capability_key'] ] = true;
        }
        return true;
    }

    private static function valid_interfaces( $interfaces ) {
        return self::is_list( $interfaces ) && count( $interfaces ) >= 1 && count( $interfaces ) <= 4 && count( $interfaces ) === count( array_unique( $interfaces, SORT_STRING ) ) && ! array_diff( $interfaces, self::INTERFACES );
    }

    private static function valid_read_model_contract( $contract, $interfaces ) {
        if ( null === $contract ) {
            return ! in_array( 'module_read_model', $interfaces, true );
        }
        return self::exact_keys( $contract, array( 'document_type', 'contract_version' ) )
            && is_string( $contract['document_type'] )
            && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9-]{1,63})+$/D', $contract['document_type'] )
            && self::is_semver( $contract['contract_version'] );
    }

    private static function valid_bindings( $bindings, $interfaces ) {
        if ( ! self::is_list( $bindings ) || count( $bindings ) > 16 ) {
            return false;
        }
        $seen = array();
        foreach ( $bindings as $binding ) {
            if ( ! self::exact_keys( $binding, array( 'interface', 'slot' ) ) || ! in_array( $binding['interface'], $interfaces, true ) || ! in_array( $binding['slot'], self::SLOTS, true ) ) {
                return false;
            }
            $event_slot = in_array( $binding['slot'], self::EVENT_SLOTS, true );
            if ( $event_slot !== ( 'event_source' === $binding['interface'] ) ) {
                return false;
            }
            $key = $binding['slot'] . "\x1F" . $binding['interface'];
            if ( isset( $seen[ $key ] ) ) {
                return false;
            }
            $seen[ $key ] = true;
        }
        return true;
    }

    private static function valid_actions( $actions, $interfaces, $namespace ) {
        if ( ! self::is_list( $actions ) || count( $actions ) > 16 || ( ! empty( $actions ) && ! in_array( 'delegated_action', $interfaces, true ) ) ) {
            return false;
        }
        $seen = array();
        foreach ( $actions as $action ) {
            if ( ! self::exact_keys( $action, array( 'action_key', 'kind' ) ) || ! self::is_namespaced_key( $action['action_key'], $namespace ) || 'delegated_action' !== $action['kind'] || isset( $seen[ $action['action_key'] ] ) ) {
                return false;
            }
            $seen[ $action['action_key'] ] = true;
        }
        return true;
    }

    private static function valid_compatibility( $compatibility, $namespace ) {
        if ( ! self::exact_keys( $compatibility, array( 'minimum_consumer_version', 'compatible_with', 'deprecated', 'sunset_at', 'replacement_capability_key' ) ) || ! self::is_semver( $compatibility['minimum_consumer_version'] ) || ! self::is_list( $compatibility['compatible_with'] ) || count( $compatibility['compatible_with'] ) > 32 || count( $compatibility['compatible_with'] ) !== count( array_unique( $compatibility['compatible_with'], SORT_STRING ) ) || ! is_bool( $compatibility['deprecated'] ) ) {
            return false;
        }
        foreach ( $compatibility['compatible_with'] as $version ) {
            if ( ! self::is_semver( $version ) ) {
                return false;
            }
        }
        if ( null !== $compatibility['sunset_at'] && ! self::is_datetime( $compatibility['sunset_at'] ) ) {
            return false;
        }
        if ( null !== $compatibility['replacement_capability_key'] && ! self::is_namespaced_key( $compatibility['replacement_capability_key'], $namespace ) ) {
            return false;
        }
        return $compatibility['deprecated'] || ( null === $compatibility['sunset_at'] && null === $compatibility['replacement_capability_key'] );
    }

    private static function contains_forbidden_content( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $child ) {
                if ( is_string( $key ) && 1 === preg_match( '/faluss[_-]?id|wp[_-]?user[_-]?id|e[_-]?mail|email|login|session|cookie|balance|solde|payment|paiement|stripe|history|historique|subscription|abonnement|private[_-]?content|contenu[_-]?prive|callback|callable|executable|javascript|php|html|css|sql/i', $key ) ) {
                    return true;
                }
                if ( self::contains_forbidden_content( $child ) ) {
                    return true;
                }
            }
            return false;
        }
        if ( ! is_string( $value ) ) {
            return false;
        }
        return 1 === preg_match( '/faluss[_-]?id|wp[_-]?user[_-]?id|e-?mail|login|session|cookie|balance|solde|payment|paiement|stripe|history|historique|subscription|abonnement/i', $value )
            || 1 === preg_match( '/<\?(?:php)?|<(?:script|iframe|style)\b|javascript:|data:text\/html|on[a-z]+\s*=|\b(?:SELECT|INSERT|UPDATE|DELETE|DROP)\s+(?:FROM|INTO|TABLE|SET|WHERE)\b/i', $value );
    }

    private static function exact_keys( $value, $keys ) {
        if ( ! is_array( $value ) ) {
            return false;
        }
        $actual = array_keys( $value );
        sort( $actual, SORT_STRING );
        $expected = $keys;
        sort( $expected, SORT_STRING );
        return $actual === $expected;
    }

    private static function is_list( $value ) {
        return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
    }

    private static function is_app_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $value ); }
    private static function is_semver( $value ) { return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*\.[0-9]+\.[0-9]+$/D', $value ); }
    private static function is_namespaced_key( $value, $namespace ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9_.-]{1,127})+$/D', $value ) && 0 === strpos( $value, $namespace . '.' ); }
    private static function bounded_text( $value, $maximum ) { return is_string( $value ) && '' !== trim( $value ) && false === strpos( $value, "\0" ) && 1 === preg_match( '//u', $value ) && preg_match_all( '/./us', $value, $matches ) <= $maximum; }
    private static function is_datetime( $value ) {
        if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2})$/D', $value ) ) {
            return false;
        }
        $normalized = 'Z' === substr( $value, -1 ) ? substr( $value, 0, -1 ) . '+00:00' : $value;
        $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:sP', $normalized );
        $errors = DateTimeImmutable::getLastErrors();
        return false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $normalized === $date->format( 'Y-m-d\TH:i:sP' );
    }
}
