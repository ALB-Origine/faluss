<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** PHP-only connector facade: configuration, subject resolution, diagnostics and remote read-only balance. */
final class Token_Engine_Connector_Service {
    const OPTION = 'token_engine_connector_settings';

    public static function configuration() {
        $stored = get_option( self::OPTION, array() );
        return array(
            'core_url' => self::core_url( $stored['core_url'] ?? '' ),
            'client_id' => self::client_id( $stored['client_id'] ?? '' ),
            'project_key' => self::project_key( $stored['project_key'] ?? '' ),
            'secret_configured' => is_string( $stored['secret_protected'] ?? null ) && '' !== $stored['secret_protected'],
        );
    }

    public static function is_configured() {
        $settings = self::configuration();
        return '' !== $settings['core_url'] && '' !== $settings['client_id'] && '' !== $settings['project_key'] && ! empty( $settings['secret_configured'] );
    }

    public static function save_configuration( $values ) {
        $current = get_option( self::OPTION, array() );
        $next = array(
            'core_url' => self::core_url( $values['core_url'] ?? '' ),
            'client_id' => self::client_id( $values['client_id'] ?? '' ),
            'project_key' => self::project_key( $values['project_key'] ?? '' ),
            'secret_protected' => is_string( $current['secret_protected'] ?? null ) ? $current['secret_protected'] : '',
        );
        $submitted_secret = isset( $values['client_secret'] ) ? trim( (string) wp_unslash( $values['client_secret'] ) ) : '';
        if ( '' !== $submitted_secret ) {
            $protected = Token_Engine_Connector_Crypto::encrypt( $submitted_secret );
            if ( is_wp_error( $protected ) ) { return self::error( 'connector_secret_protection_failed' ); }
            $next['secret_protected'] = $protected;
        }
        if ( '' === $next['core_url'] || '' === $next['client_id'] || '' === $next['project_key'] || '' === $next['secret_protected'] ) {
            return self::error( 'connector_configuration_invalid' );
        }
        update_option( self::OPTION, $next, false );
        return self::configuration();
    }

    public static function current_subject_id() { return Token_Engine_Connector_Subject::current(); }
    public static function subject_diagnostic() { return Token_Engine_Connector_Subject::diagnostic(); }

    public static function test_connection() {
        $token = self::access_token();
        if ( is_wp_error( $token ) ) { return $token; }
        $response = wp_safe_remote_get( self::endpoint( '/wp-json/token-engine/v1/diagnostic' ), array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => true, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
        $data = self::response_data( $response );
        if ( is_wp_error( $data ) || empty( $data['connected'] ) || self::configuration()['project_key'] !== self::project_key( $data['project_key'] ?? '' ) ) { return self::error( 'connector_connection_failed' ); }
        return array( 'connected' => true, 'project_key' => self::project_key( $data['project_key'] ?? '' ), 'permissions' => is_array( $data['permissions'] ?? null ) ? $data['permissions'] : array() );
    }

    public static function balance_for_current_subject() {
        $subject = self::current_subject_id();
        if ( '' === $subject ) { return self::error( 'connector_subject_unavailable' ); }
        $token = self::access_token();
        if ( is_wp_error( $token ) ) { return $token; }
        $response = wp_safe_remote_post( self::endpoint( '/wp-json/token-engine/v1/balance' ), array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => true, 'headers' => array( 'Authorization' => 'Bearer ' . $token ), 'body' => array( 'subject_id' => $subject ) ) );
        $data = self::response_data( $response );
        if ( is_wp_error( $data ) || ! isset( $data['balance'] ) || self::configuration()['project_key'] !== self::project_key( $data['project_key'] ?? '' ) ) { return self::error( 'connector_balance_unavailable' ); }
        return array( 'project_key' => self::project_key( $data['project_key'] ?? '' ), 'balance' => max( 0, (int) $data['balance'] ) );
    }

    private static function access_token() {
        if ( ! self::is_configured() ) { return self::error( 'connector_configuration_invalid' ); }
        $stored = get_option( self::OPTION, array() );
        $secret = Token_Engine_Connector_Crypto::decrypt( $stored['secret_protected'] ?? '' );
        if ( is_wp_error( $secret ) ) { return self::error( 'connector_secret_unavailable' ); }
        $settings = self::configuration();
        $response = wp_safe_remote_post( self::endpoint( '/wp-json/token-engine/v1/access-token' ), array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => true, 'body' => array( 'client_id' => $settings['client_id'], 'client_secret' => $secret ) ) );
        $data = self::response_data( $response );
        if ( is_wp_error( $data ) || ! is_string( $data['access_token'] ?? null ) || '' === $data['access_token'] ) { return self::error( 'connector_authentication_failed' ); }
        return $data['access_token'];
    }

    private static function endpoint( $path ) { return rtrim( self::configuration()['core_url'], '/' ) . $path; }
    private static function response_data( $response ) {
        if ( is_wp_error( $response ) || 200 > (int) wp_remote_retrieve_response_code( $response ) || 299 < (int) wp_remote_retrieve_response_code( $response ) ) { return self::error( 'connector_remote_unavailable' ); }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $data ) ? $data : self::error( 'connector_remote_invalid' );
    }
    private static function core_url( $value ) {
        $value = is_string( $value ) ? esc_url_raw( trim( wp_unslash( $value ) ) ) : '';
        $parts = wp_parse_url( $value );
        if ( ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) ) { return ''; }
        return rtrim( $value, '/' );
    }
    private static function client_id( $value ) { $value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : ''; return 1 === preg_match( '/^tec_[A-Za-z0-9_-]{20,60}$/', $value ) ? $value : ''; }
    private static function project_key( $value ) { $value = is_string( $value ) ? strtolower( sanitize_text_field( wp_unslash( $value ) ) ) : ''; return 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{1,63}$/', $value ) ? $value : ''; }
    private static function error( $code ) { return new WP_Error( $code, __( 'Le connecteur ne peut pas terminer cette opération.', 'token-engine-connector' ) ); }
}
