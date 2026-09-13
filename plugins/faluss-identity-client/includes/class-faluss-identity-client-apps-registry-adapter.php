<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** AP-02A relation adapter for the federated Faluss Me application source. */
final class Faluss_Identity_Client_Apps_Registry_Adapter {
    private static $relationship_cache = array();

    public static function boot() {
        add_action( 'plugins_loaded', array( __CLASS__, 'register_source' ), 40 );
        if ( did_action( 'plugins_loaded' ) ) {
            self::register_source();
        }
    }

    /** @return true|false|WP_Error */
    public static function register_source() {
        if ( ! class_exists( 'Faluss_Apps_Registry' ) || ! method_exists( 'Faluss_Apps_Registry', 'register_source' ) || ! class_exists( 'Faluss_Identity_Client' ) ) {
            return false;
        }
        return Faluss_Apps_Registry::register_source( array(
            'app_key' => 'faluss-me',
            'source_type' => 'federated_peer',
            'document_type' => 'faluss.app-capability-manifest',
            'contract_version' => '1.0.0',
            'requested_manifest_version' => '1.0.0',
            'owner' => 'faluss-me',
            'relationship_resolver' => array( __CLASS__, 'relationship' ),
            'capability_resolver' => null,
            'peer_node_id' => 'me-node',
            'peer_app_key' => 'faluss-me',
        ) );
    }

    /** @return string|WP_Error */
    public static function relationship( $faluss_id ) {
        if ( ! self::is_uuid_v4( $faluss_id ) || ! self::authority_available() ) {
            return new WP_Error( 'faluss_apps_registry_unavailable' );
        }
        if ( ! array_key_exists( $faluss_id, self::$relationship_cache ) ) {
            try {
                global $wpdb;
                $projection = Faluss_Identity_Client::member_app_projection( $faluss_id, 'me' );
                self::$relationship_cache[ $faluss_id ] = ! empty( $wpdb->last_error ) ? new WP_Error( 'faluss_apps_registry_unavailable' ) : $projection;
            } catch ( Throwable $exception ) {
                unset( $exception );
                self::$relationship_cache[ $faluss_id ] = new WP_Error( 'faluss_apps_registry_unavailable' );
            }
        }
        $projection = self::$relationship_cache[ $faluss_id ];
        if ( is_wp_error( $projection ) ) {
            return $projection;
        }
        if ( null === $projection ) {
            return 'not_linked';
        }
        return self::valid_projection( $projection ) ? 'active' : new WP_Error( 'faluss_apps_registry_unavailable' );
    }

    /** Reuse the exact projection validated during relation resolution. */
    public static function canonical_destination( $faluss_id ) {
        $relationship = self::relationship( $faluss_id );
        if ( 'active' !== $relationship ) {
            return null;
        }
        return self::$relationship_cache[ $faluss_id ]['canonical_url'];
    }

    private static function valid_projection( $projection ) {
        if ( ! is_array( $projection ) || array( 'contract_version', 'publication_status', 'canonical_url' ) !== array_keys( $projection ) || '1' !== $projection['contract_version'] || 'published' !== $projection['publication_status'] || ! is_string( $projection['canonical_url'] ) || ! in_array( $projection['canonical_url'], array( 'https://faluss.me/mon-faluss', 'https://www.faluss.me/mon-faluss' ), true ) ) {
            return false;
        }
        $parts = wp_parse_url( $projection['canonical_url'] );
        return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? null ) && in_array( strtolower( $parts['host'] ?? '' ), array( 'faluss.me', 'www.faluss.me' ), true ) && '/mon-faluss' === ( $parts['path'] ?? null ) && ! isset( $parts['query'], $parts['fragment'], $parts['user'], $parts['pass'], $parts['port'] );
    }

    private static function authority_available() {
        global $wpdb;
        if ( ! class_exists( 'Faluss_Identity_Client' ) || ! method_exists( 'Faluss_Identity_Client', 'member_app_projection' ) || ! class_exists( 'Faluss_Identity_Client_Schema' ) || ! method_exists( 'Faluss_Identity_Client_Schema', 'tables' ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
            return false;
        }
        $tables = Faluss_Identity_Client_Schema::tables();
        return is_array( $tables ) && is_string( $tables['links'] ?? null ) && '' !== $tables['links'];
    }

    private static function is_uuid_v4( $value ) {
        return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value );
    }
}
