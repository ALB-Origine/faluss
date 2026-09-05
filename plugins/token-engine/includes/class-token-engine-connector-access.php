<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Private, read-only connector authentication for the generic Token Engine core. */
final class Token_Engine_Connector_Access {
    const PERMISSION_WALLET_READ = 'wallet.read';
    const TOKEN_TTL_SECONDS = 300;

    public static function boot() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( 'token-engine/v1', '/access-token', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'access_token_response' ),
            'permission_callback' => array( __CLASS__, 'https_only' ),
        ) );
        register_rest_route( 'token-engine/v1', '/diagnostic', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'diagnostic_response' ),
            'permission_callback' => array( __CLASS__, 'wallet_read_permission' ),
        ) );
        register_rest_route( 'token-engine/v1', '/balance', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'balance_response' ),
            'permission_callback' => array( __CLASS__, 'wallet_read_permission' ),
        ) );
    }

    /** Generates a public client ID and one-time secret for an active project. */
    public static function generate_credentials( $project_id ) {
        global $wpdb;
        if ( ! Token_Engine_Schema::is_ready() ) {
            return self::error( 'schema_not_ready' );
        }
        $project = self::project_by_id( absint( $project_id ) );
        if ( ! $project || empty( $project['active'] ) ) {
            return self::error( 'connector_project_inactive' );
        }
        try {
            $secret = self::random_value( 32 );
            $version = wp_generate_uuid4();
            $client_id = ! empty( $project['connector_client_id'] ) ? $project['connector_client_id'] : self::client_id();
        } catch ( Exception $exception ) {
            return self::error( 'connector_generation_failed' );
        }
        $updated = $wpdb->update(
            Token_Engine_Schema::projects_table(),
            array(
                'connector_client_id' => $client_id,
                'connector_secret_hash' => wp_hash_password( $secret ),
                'connector_secret_version' => $version,
                'connector_permissions' => wp_json_encode( array( self::PERMISSION_WALLET_READ ) ),
                'updated_at' => current_time( 'mysql', true ),
            ),
            array( 'id' => (int) $project['id'] ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        if ( false === $updated ) {
            return self::error( 'connector_generation_failed' );
        }
        return array( 'project_key' => $project['project_key'], 'client_id' => $client_id, 'secret' => $secret, 'permissions' => array( self::PERMISSION_WALLET_READ ) );
    }

    public static function project_has_credentials( $project ) {
        return is_array( $project ) && ! empty( $project['active'] ) && self::valid_client_id( $project['connector_client_id'] ?? '' ) && ! empty( $project['connector_secret_hash'] ) && self::valid_permissions( $project['connector_permissions'] ?? '' );
    }

    /** Issues an opaque, short-lived access token after client-secret proof on HTTPS. */
    public static function issue_token( $client_id, $secret ) {
        global $wpdb;
        if ( ! is_ssl() || ! Token_Engine_Schema::is_ready() ) {
            return self::error( 'connector_unavailable' );
        }
        $project = self::project_by_client_id( $client_id );
        if ( ! self::project_has_credentials( $project ) || ! is_string( $secret ) || ! wp_check_password( $secret, $project['connector_secret_hash'] ) ) {
            return self::error( 'connector_authentication_failed' );
        }
        try {
            $token = self::random_value( 32 );
        } catch ( Exception $exception ) {
            return self::error( 'connector_token_failed' );
        }
        $permissions = self::valid_permissions( $project['connector_permissions'] );
        $now = current_time( 'mysql', true );
        $expires = gmdate( 'Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS );
        $wpdb->query( 'DELETE FROM ' . Token_Engine_Schema::connector_tokens_table() . " WHERE expires_at < '" . esc_sql( $now ) . "'" );
        $inserted = $wpdb->insert(
            Token_Engine_Schema::connector_tokens_table(),
            array(
                'token_hash' => hash( 'sha256', $token ),
                'project_key' => $project['project_key'],
                'secret_version' => $project['connector_secret_version'],
                'permissions' => wp_json_encode( $permissions ),
                'expires_at' => $expires,
                'created_at' => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( false === $inserted ) {
            return self::error( 'connector_token_failed' );
        }
        return array( 'access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => self::TOKEN_TTL_SECONDS, 'permissions' => $permissions );
    }

    /** Returns the active project scoped by a valid bearer token and permission. */
    public static function authorize( $request, $permission = self::PERMISSION_WALLET_READ ) {
        global $wpdb;
        if ( ! is_ssl() || ! Token_Engine_Schema::is_ready() ) {
            return self::error( 'connector_unauthorized' );
        }
        $header = is_object( $request ) && method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'authorization' ) : '';
        if ( 1 !== preg_match( '/^Bearer ([A-Za-z0-9_-]{32,128})$/', trim( $header ), $matches ) ) {
            return self::error( 'connector_unauthorized' );
        }
        $sql = 'SELECT tokens.*, projects.active, projects.connector_secret_version, projects.connector_permissions FROM ' . Token_Engine_Schema::connector_tokens_table() . ' AS tokens INNER JOIN ' . Token_Engine_Schema::projects_table() . ' AS projects ON projects.project_key=tokens.project_key WHERE tokens.token_hash=%s AND tokens.expires_at > %s LIMIT 1';
        $entry = $wpdb->get_row( $wpdb->prepare( $sql, hash( 'sha256', $matches[1] ), current_time( 'mysql', true ) ), ARRAY_A );
        if ( ! is_array( $entry ) || empty( $entry['active'] ) || ! hash_equals( (string) $entry['connector_secret_version'], (string) $entry['secret_version'] ) ) {
            return self::error( 'connector_unauthorized' );
        }
        $permissions = self::valid_permissions( $entry['permissions'] );
        $project_permissions = self::valid_permissions( $entry['connector_permissions'] );
        if ( ! $permissions || ! $project_permissions || ! in_array( $permission, $permissions, true ) || ! in_array( $permission, $project_permissions, true ) ) {
            return self::error( 'connector_forbidden' );
        }
        return array( 'project_key' => $entry['project_key'], 'permissions' => $permissions, 'expires_at' => $entry['expires_at'] );
    }

    public static function https_only() {
        return is_ssl() ? true : self::error( 'https_required' );
    }

    public static function access_token_response( $request ) {
        $result = self::issue_token( $request->get_param( 'client_id' ), $request->get_param( 'client_secret' ) );
        nocache_headers();
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public static function wallet_read_permission( $request ) {
        $authorized = self::authorize( $request );
        return is_wp_error( $authorized ) ? $authorized : true;
    }

    public static function diagnostic_response( $request ) {
        $authorized = self::authorize( $request );
        if ( is_wp_error( $authorized ) ) { return $authorized; }
        return rest_ensure_response( array( 'connected' => true, 'project_key' => $authorized['project_key'], 'permissions' => $authorized['permissions'] ) );
    }

    public static function balance_response( $request ) {
        $authorized = self::authorize( $request );
        if ( is_wp_error( $authorized ) ) { return $authorized; }
        $subject = self::subject_id( $request->get_param( 'subject_id' ) );
        if ( '' === $subject ) { return self::error( 'invalid_subject' ); }
        return rest_ensure_response( array( 'project_key' => $authorized['project_key'], 'balance' => Token_Engine_Service::balance( $subject, $authorized['project_key'] ) ) );
    }

    private static function project_by_id( $id ) {
        global $wpdb;
        if ( ! $id ) { return null; }
        $project = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Token_Engine_Schema::projects_table() . ' WHERE id=%d', $id ), ARRAY_A );
        return is_array( $project ) ? $project : null;
    }

    private static function project_by_client_id( $client_id ) {
        global $wpdb;
        if ( ! self::valid_client_id( $client_id ) ) { return null; }
        $project = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Token_Engine_Schema::projects_table() . ' WHERE connector_client_id=%s LIMIT 1', $client_id ), ARRAY_A );
        return is_array( $project ) ? $project : null;
    }

    private static function client_id() { return 'tec_' . self::random_value( 18 ); }
    private static function random_value( $bytes ) { return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' ); }
    private static function valid_client_id( $value ) { return is_string( $value ) && 1 === preg_match( '/^tec_[A-Za-z0-9_-]{20,60}$/', $value ); }
    private static function subject_id( $value ) { $value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : ''; return function_exists( 'mb_substr' ) ? mb_substr( trim( $value ), 0, 191 ) : substr( trim( $value ), 0, 191 ); }
    private static function valid_permissions( $value ) { $permissions = is_array( $value ) ? $value : json_decode( (string) $value, true ); if ( ! is_array( $permissions ) || ! $permissions || array_diff( $permissions, array( self::PERMISSION_WALLET_READ ) ) || count( array_unique( $permissions ) ) !== count( $permissions ) ) { return array(); } return array_values( $permissions ); }
    private static function error( $code ) { return new WP_Error( $code, __( 'L’accès connecteur est refusé.', 'token-engine' ), array( 'status' => 403 ) ); }
}
