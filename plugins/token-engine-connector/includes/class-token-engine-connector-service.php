<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** PHP-only connector facade: configuration, separately testable core access and remote read-only balance. */
final class Token_Engine_Connector_Service {
    const OPTION = 'token_engine_connector_settings';
    const REST_SUFFIX = '/wp-json/token-engine/v1';

    public static function configuration() {
        $stored = get_option( self::OPTION, array() );
        $secret_state = self::secret_state( $stored );
        return array(
            'core_url' => self::core_url( $stored['core_url'] ?? '' ),
            'client_id' => self::client_id( $stored['client_id'] ?? '' ),
            'project_key' => self::project_key( $stored['project_key'] ?? '' ),
            'secret_configured' => 'saved' === $secret_state,
            'secret_state' => $secret_state,
        );
    }

    public static function is_configured() {
        $settings = self::configuration();
        return '' !== $settings['core_url'] && '' !== $settings['client_id'] && '' !== $settings['project_key'] && ! empty( $settings['secret_configured'] );
    }

    public static function save_configuration( $values ) {
        $current = get_option( self::OPTION, array() );
        $current = is_array( $current ) ? $current : array();
        $next = array(
            'core_url' => self::core_url( $values['core_url'] ?? '' ),
            'client_id' => self::client_id( $values['client_id'] ?? '' ),
            'project_key' => self::project_key( $values['project_key'] ?? '' ),
            'secret_protected' => is_string( $current['secret_protected'] ?? null ) ? $current['secret_protected'] : '',
        );
        $submitted_secret = isset( $values['client_secret'] ) ? trim( (string) wp_unslash( $values['client_secret'] ) ) : '';
        if ( '' !== $submitted_secret ) {
            $protected = Token_Engine_Connector_Crypto::encrypt( $submitted_secret );
            if ( is_wp_error( $protected ) ) { return self::error( 'connector_secret_protection_unavailable' ); }
            $verified_secret = Token_Engine_Connector_Crypto::decrypt( $protected );
            if ( is_wp_error( $verified_secret ) || ! is_string( $verified_secret ) || ! hash_equals( $submitted_secret, $verified_secret ) ) { return self::error( 'connector_secret_protection_unavailable' ); }
            $next['secret_protected'] = $protected;
        }
        if ( '' === $next['core_url'] ) { return self::error( 'connector_core_url_invalid' ); }
        if ( '' === $next['client_id'] ) { return self::error( 'connector_client_invalid' ); }
        if ( '' === $next['project_key'] ) { return self::error( 'connector_project_invalid' ); }
        if ( '' === $submitted_secret && 'saved' !== self::secret_state( $current ) ) { return self::error( 'connector_secret_required' ); }
        if ( '' === $next['secret_protected'] ) { return self::error( 'connector_secret_required' ); }
        update_option( self::OPTION, $next, false );
        $persisted = get_option( self::OPTION, array() );
        $persisted_secret = Token_Engine_Connector_Crypto::decrypt( is_array( $persisted ) ? ( $persisted['secret_protected'] ?? '' ) : '' );
        if ( is_wp_error( $persisted_secret ) || ! is_string( $persisted_secret ) || ( '' !== $submitted_secret && ! hash_equals( $submitted_secret, $persisted_secret ) ) ) {
            update_option( self::OPTION, $current, false );
            return self::error( 'connector_secret_persistence_failed' );
        }
        return self::configuration();
    }

    public static function current_subject_id() { return Token_Engine_Connector_Subject::current(); }
    public static function subject_diagnostic() { return Token_Engine_Connector_Subject::diagnostic(); }
    public static function faluss_subject_diagnostic() { return self::subject_diagnostic(); }

    /** Tests only transport, credentials, project and permission. It never resolves a local subject. */
    public static function core_connection_test() {
        $token = self::access_token();
        if ( is_wp_error( $token ) ) { return $token; }
        $response = wp_safe_remote_get( self::endpoint( 'diagnostic' ), array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
        ) );
        $data = self::response_data( $response );
        if ( is_wp_error( $data ) ) { return $data; }
        if ( empty( $data['connected'] ) ) { return self::error( 'connector_core_rejected', self::diagnostic_from( $data, $token ) ); }
        if ( self::configuration()['project_key'] !== self::project_key( $data['project_key'] ?? '' ) ) { return self::error( 'connector_project_rejected', self::diagnostic_from( $data, $token ) ); }
        $permissions = self::permissions( $data['permissions'] ?? array() );
        if ( ! in_array( 'wallet.read', $permissions, true ) ) { return self::error( 'connector_permission_wallet_read_missing', self::diagnostic_from( $data, $token ) ); }
        return array(
            'connected' => true,
            'project_key' => self::project_key( $data['project_key'] ?? '' ),
            'permissions' => $permissions,
            'diagnostic_id' => self::diagnostic_from( $data, $token ),
        );
    }

    /** Backward-compatible internal name; it deliberately remains subject-independent. */
    public static function test_connection() { return self::core_connection_test(); }

    public static function balance_for_current_subject() {
        $subject = self::current_subject_id();
        if ( '' === $subject ) { return self::error( 'connector_subject_unavailable' ); }
        $token = self::access_token();
        if ( is_wp_error( $token ) ) { return $token; }
        $response = wp_safe_remote_post( self::endpoint( 'balance' ), array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
            'body' => array( 'subject_id' => $subject ),
        ) );
        $data = self::response_data( $response );
        if ( is_wp_error( $data ) || ! isset( $data['balance'] ) || self::configuration()['project_key'] !== self::project_key( $data['project_key'] ?? '' ) ) { return self::error( 'connector_balance_unavailable', is_array( $data ) ? self::diagnostic_from( $data, $token ) : '' ); }
        return array( 'project_key' => self::project_key( $data['project_key'] ?? '' ), 'balance' => max( 0, (int) $data['balance'] ) );
    }

    private static function access_token() {
        if ( ! self::is_configured() ) { return self::error( self::configuration_error_code() ); }
        $stored = get_option( self::OPTION, array() );
        $secret = Token_Engine_Connector_Crypto::decrypt( $stored['secret_protected'] ?? '' );
        if ( is_wp_error( $secret ) ) { return self::error( 'connector_secret_unavailable' ); }
        $settings = self::configuration();
        $response = wp_safe_remote_post( self::endpoint( 'access-token' ), array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'body' => array( 'client_id' => $settings['client_id'], 'client_secret' => $secret ),
        ) );
        $data = self::response_data( $response );
        if ( is_wp_error( $data ) ) { return $data; }
        if ( ! is_string( $data['access_token'] ?? null ) || '' === $data['access_token'] ) { return self::error( 'connector_token_rejected', self::diagnostic_from( $data ) ); }
        return array( 'access_token' => $data['access_token'], 'diagnostic_id' => self::diagnostic_from( $data ) );
    }

    private static function endpoint( $route ) {
        return trailingslashit( self::configuration()['core_url'] ) . ltrim( $route, '/' );
    }

    /** Parses only non-sensitive, expected error metadata; response bodies are never shown in admin. */
    private static function response_data( $response ) {
        if ( is_wp_error( $response ) ) { return self::error( 'connector_core_inaccessible' ); }
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( 300 <= $status && 399 >= $status ) { return self::error( 'connector_core_redirect_rejected' ); }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 404 === $status || ( is_array( $data ) && 'rest_no_route' === ( $data['code'] ?? '' ) ) ) { return self::error( 'connector_route_missing', self::diagnostic_from( $data ) ); }
        if ( 200 > $status || 299 < $status ) {
            $code = is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '';
            $safe = array( 'https_required', 'connector_project_inactive', 'connector_client_rejected', 'connector_secret_rejected', 'connector_permission_wallet_read_missing', 'connector_token_rejected', 'connector_credentials_missing', 'schema_not_ready' );
            return self::error( in_array( $code, $safe, true ) ? $code : 'connector_core_rejected', self::diagnostic_from( $data ) );
        }
        return is_array( $data ) ? $data : self::error( 'connector_core_invalid_response' );
    }

    /** Accepts the exact copyable endpoint and keeps any valid WordPress subdirectory. */
    private static function core_url( $value ) {
        $value = is_string( $value ) ? esc_url_raw( trim( wp_unslash( $value ) ) ) : '';
        $parts = wp_parse_url( $value );
        if ( ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) ) { return ''; }
        $value = rtrim( $value, '/' );
        $suffix = self::REST_SUFFIX;
        if ( str_ends_with( strtolower( $value ), $suffix ) ) { return $value . '/'; }
        if ( str_ends_with( strtolower( $value ), '/wp-json' ) ) { return $value . '/token-engine/v1/'; }
        return $value . $suffix . '/';
    }
    private static function client_id( $value ) { $value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : ''; return 1 === preg_match( '/^tec_[A-Za-z0-9_-]{20,60}$/', $value ) ? $value : ''; }
    private static function project_key( $value ) { $value = is_string( $value ) ? strtolower( sanitize_text_field( wp_unslash( $value ) ) ) : ''; return 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{1,63}$/', $value ) ? $value : ''; }
    private static function permissions( $permissions ) { return is_array( $permissions ) && in_array( 'wallet.read', $permissions, true ) ? array( 'wallet.read' ) : array(); }
    private static function diagnostic_from( $data, $fallback = array() ) { $id = is_array( $data ) ? (string) ( $data['diagnostic_id'] ?? ( $data['data']['diagnostic_id'] ?? '' ) ) : ''; if ( '' === $id && is_array( $fallback ) ) { $id = (string) ( $fallback['diagnostic_id'] ?? '' ); } return 1 === preg_match( '/^[a-f0-9-]{16,64}$/i', $id ) ? $id : wp_generate_uuid4(); }
    private static function secret_state( $stored ) { if ( ! is_array( $stored ) || ! is_string( $stored['secret_protected'] ?? null ) || '' === $stored['secret_protected'] ) { return 'required'; } $secret = Token_Engine_Connector_Crypto::decrypt( $stored['secret_protected'] ); return is_wp_error( $secret ) || ! is_string( $secret ) || '' === $secret ? 'required' : 'saved'; }
    private static function configuration_error_code() { $settings = self::configuration(); if ( '' === $settings['core_url'] ) { return 'connector_core_url_invalid'; } if ( '' === $settings['client_id'] ) { return 'connector_client_invalid'; } if ( '' === $settings['project_key'] ) { return 'connector_project_invalid'; } return 'connector_secret_required'; }
    private static function error( $code, $diagnostic_id = '' ) { return new WP_Error( $code, __( 'Le connecteur ne peut pas terminer cette opération.', 'token-engine-connector' ), array( 'diagnostic_id' => '' !== $diagnostic_id ? $diagnostic_id : wp_generate_uuid4() ) ); }
}
