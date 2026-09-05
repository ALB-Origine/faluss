<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** PHP-only connector facade: a canonical Core site URL and narrow remote actions. */
final class Token_Engine_Connector_Service {
    const OPTION = 'token_engine_connector_settings';
    const PROTOCOL_VERSION = '1';
    const CORE_ENGINE = 'token-engine';
    const REST_MODE_REWRITE = 'rewrite';
    const REST_MODE_QUERY = 'query';
    const REST_NAMESPACE = 'token-engine/v1';
    const PERMISSION_WALLET_READ = 'wallet.read';
    const PERMISSION_REWARD_CLAIM = 'reward.claim';

    public static function configuration() {
        $stored = get_option( self::OPTION, array() );
        $stored = is_array( $stored ) ? $stored : array();
        $secret_state = self::secret_state( $stored );
        $site_url = array_key_exists( 'core_site_url', $stored ) ? self::normalise_site_url( $stored['core_site_url'] ) : self::migrate_legacy_site_url( $stored['core_url'] ?? '' );
        return array(
            'core_site_url' => $site_url,
            'client_id' => self::client_id( $stored['client_id'] ?? '' ),
            'project_key' => self::project_key( $stored['project_key'] ?? '' ),
            'secret_configured' => 'saved' === $secret_state,
            'secret_state' => $secret_state,
        );
    }

    public static function is_configured() {
        $settings = self::configuration();
        return '' !== $settings['core_site_url'] && '' !== $settings['client_id'] && '' !== $settings['project_key'] && ! empty( $settings['secret_configured'] );
    }

    public static function save_configuration( $values ) {
        $current = get_option( self::OPTION, array() );
        $current = is_array( $current ) ? $current : array();
        $next = array(
            'core_site_url' => self::normalise_site_url( $values['core_site_url'] ?? '' ),
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
        if ( '' === $next['core_site_url'] ) { return self::error( 'connector_core_url_invalid' ); }
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

    /** Converts only recognized TE-02.3 REST bases to the canonical Core site URL. */
    public static function migrate_legacy_site_url( $value ) {
        $value = self::raw_https_url( $value, true );
        if ( '' === $value ) { return ''; }
        $parts = wp_parse_url( $value );
        if ( ! is_array( $parts ) ) { return ''; }
        $path = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
        $query = (string) ( $parts['query'] ?? '' );

        if ( 1 === preg_match( '#^(.*)/wp-json/token-engine/v1$#', $path, $matches ) && '' === $query ) {
            return self::site_url_from_parts( $parts, $matches[1] );
        }

        parse_str( $query, $query_values );
        if ( 1 === preg_match( '#^(.*)/index\\.php$#', $path, $matches ) && 1 === count( $query_values ) && '/token-engine/v1/' === (string) ( $query_values['rest_route'] ?? '' ) ) {
            return self::site_url_from_parts( $parts, $matches[1] );
        }

        return self::normalise_site_url( $value );
    }

    public static function current_subject_id() { return Token_Engine_Connector_Subject::current(); }
    public static function subject_diagnostic() { return Token_Engine_Connector_Subject::diagnostic(); }
    public static function faluss_subject_diagnostic() { return self::subject_diagnostic(); }

    /** Tests URL, REST routing, protocol and credentials. It never resolves a local subject. */
    public static function core_connection_test() {
        if ( ! self::is_configured() ) { return self::error( self::configuration_error_code(), '', self::configuration_stage() ); }
        $token = self::access_token();
        if ( is_wp_error( $token ) ) { return $token; }
        $diagnostic = self::route_request( 'diagnostic', 'GET', array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
        ), $token['rest_mode'] );
        if ( is_wp_error( $diagnostic ) ) { return $diagnostic; }
        $data = $diagnostic['data'];
        if ( self::CORE_ENGINE !== (string) ( $data['engine'] ?? '' ) ) { return self::error( 'connector_core_unidentified', self::diagnostic_from( $data, $token ), 'core' ); }
        if ( self::PROTOCOL_VERSION !== (string) ( $data['protocol_version'] ?? '' ) ) { return self::error( 'connector_protocol_incompatible', self::diagnostic_from( $data, $token ), 'protocol' ); }
        if ( empty( $data['connected'] ) ) { return self::error( 'connector_core_rejected', self::diagnostic_from( $data, $token ), 'core' ); }
        if ( self::configuration()['project_key'] !== self::project_key( $data['project_key'] ?? '' ) ) { return self::error( 'connector_project_rejected', self::diagnostic_from( $data, $token ), 'credentials' ); }
        $permissions = self::permissions( $data['permissions'] ?? array() );
        if ( ! in_array( 'wallet.read', $permissions, true ) ) { return self::error( 'connector_permission_wallet_read_missing', self::diagnostic_from( $data, $token ), 'permission' ); }
        return array(
            'connected' => true,
            'project_key' => self::project_key( $data['project_key'] ?? '' ),
            'permissions' => $permissions,
            'protocol_version' => self::PROTOCOL_VERSION,
            'rest_mode' => $diagnostic['rest_mode'],
            'steps' => self::successful_steps( $diagnostic['rest_mode'] ),
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
        $balance = self::route_request( 'balance', 'POST', array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
            'body' => array( 'subject_id' => $subject ),
        ), $token['rest_mode'] );
        if ( is_wp_error( $balance ) ) { return $balance; }
        $data = $balance['data'];
        if ( ! isset( $data['balance'] ) || self::configuration()['project_key'] !== self::project_key( $data['project_key'] ?? '' ) ) { return self::error( 'connector_balance_unavailable', self::diagnostic_from( $data, $token ), 'route' ); }
        return array( 'project_key' => self::project_key( $data['project_key'] ?? '' ), 'balance' => max( 0, (int) $data['balance'] ) );
    }

    /** Reads only the active local subject's global daily-reward status. */
    public static function daily_reward_status_for_current_subject() {
        return self::daily_reward_request( 'reward_status' );
    }

    /** Claims only the active local subject's global daily reward through the Core. */
    public static function claim_daily_reward_for_current_subject() {
        return self::daily_reward_request( 'reward_claim' );
    }

    private static function daily_reward_request( $route ) {
        $subject = self::current_subject_id();
        if ( '' === $subject ) { return self::error( 'connector_subject_unavailable', '', 'subject' ); }
        $token = self::access_token();
        if ( is_wp_error( $token ) ) { return $token; }
        $permissions = self::permissions( $token['permissions'] ?? array() );
        if ( ! in_array( self::PERMISSION_REWARD_CLAIM, $permissions, true ) ) {
            return self::error( 'connector_permission_reward_claim_missing', self::diagnostic_from( $token ), 'permission' );
        }
        $response = self::route_request( $route, 'POST', array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
            'body' => array( 'subject_id' => $subject ),
        ), $token['rest_mode'] );
        if ( is_wp_error( $response ) ) { return $response; }
        $data = $response['data'];
        if ( self::configuration()['project_key'] !== self::project_key( $data['project_key'] ?? '' ) ) {
            return self::error( 'connector_reward_unavailable', self::diagnostic_from( $data, $token ), 'route' );
        }
        $state = is_string( $data['state'] ?? null ) ? $data['state'] : '';
        if ( ! in_array( $state, array( 'eligible', 'claimed', 'unavailable' ), true ) ) {
            return self::error( 'connector_reward_unavailable', self::diagnostic_from( $data, $token ), 'route' );
        }
        if ( 'unavailable' === $state ) { return array( 'state' => 'unavailable' ); }
        $amount = is_numeric( $data['amount'] ?? null ) ? (int) $data['amount'] : 0;
        $unit = is_string( $data['unit'] ?? null ) ? sanitize_text_field( $data['unit'] ) : '';
        if ( $amount < 1 || '' === $unit ) {
            return self::error( 'connector_reward_unavailable', self::diagnostic_from( $data, $token ), 'route' );
        }
        return array(
            'state' => $state,
            'amount' => $amount,
            'unit' => $unit,
            'balance' => max( 0, (int) ( $data['balance'] ?? 0 ) ),
            'next_available_at' => self::iso_datetime( $data['next_available_at'] ?? '' ),
            'claimed_now' => ! empty( $data['claimed_now'] ),
        );
    }

    private static function access_token() {
        if ( ! self::is_configured() ) { return self::error( self::configuration_error_code(), '', self::configuration_stage() ); }
        $stored = get_option( self::OPTION, array() );
        $secret = Token_Engine_Connector_Crypto::decrypt( $stored['secret_protected'] ?? '' );
        if ( is_wp_error( $secret ) ) { return self::error( 'connector_secret_unavailable', '', 'credentials' ); }
        $settings = self::configuration();
        $token = self::route_request( 'token', 'POST', array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'body' => array( 'client_id' => $settings['client_id'], 'client_secret' => $secret ),
        ) );
        if ( is_wp_error( $token ) ) { return $token; }
        $data = $token['data'];
        if ( self::PROTOCOL_VERSION !== (string) ( $data['protocol_version'] ?? '' ) ) { return self::error( 'connector_protocol_incompatible', self::diagnostic_from( $data ), 'protocol' ); }
        if ( ! is_string( $data['access_token'] ?? null ) || '' === $data['access_token'] ) { return self::error( 'connector_token_rejected', self::diagnostic_from( $data ), 'token' ); }
        return array( 'access_token' => $data['access_token'], 'permissions' => self::permissions( $data['permissions'] ?? array() ), 'rest_mode' => $token['rest_mode'], 'diagnostic_id' => self::diagnostic_from( $data ) );
    }

    /** Tries the pretty REST route first and falls back when it is absent or not a REST response. */
    private static function route_request( $route, $method, $args, $known_mode = '' ) {
        $last_error = self::error( 'connector_route_missing', '', 'route' );
        foreach ( self::rest_modes( $known_mode ) as $rest_mode ) {
            $response = self::remote_request( $method, self::endpoint( $route, $rest_mode ), $args );
            $data = self::response_data( $response, 'route' );
            if ( ! is_wp_error( $data ) ) {
                return array( 'data' => $data, 'rest_mode' => $rest_mode );
            }
            $last_error = $data;
            if ( ! in_array( $data->get_error_code(), array( 'connector_route_missing', 'connector_core_invalid_response' ), true ) || '' !== $known_mode ) {
                return $data;
            }
        }
        return $last_error;
    }

    private static function remote_request( $method, $url, $args ) {
        return 'POST' === $method ? wp_safe_remote_post( $url, $args ) : wp_safe_remote_get( $url, $args );
    }

    /** Builds route URLs from the canonical site URL without concatenating onto a query string. */
    private static function endpoint( $route, $rest_mode ) {
        $routes = array( 'token' => 'connector/token', 'diagnostic' => 'connector/diagnostic', 'balance' => 'connector/balance', 'reward_status' => 'connector/reward/status', 'reward_claim' => 'connector/reward/claim' );
        $route_path = $routes[ $route ] ?? '';
        $site_url = self::configuration()['core_site_url'];
        if ( self::REST_MODE_QUERY === $rest_mode ) {
            return add_query_arg( 'rest_route', '/' . self::REST_NAMESPACE . '/' . $route_path, trailingslashit( $site_url ) . 'index.php' );
        }
        return trailingslashit( $site_url ) . 'wp-json/' . self::REST_NAMESPACE . '/' . $route_path;
    }

    private static function rest_modes( $known_mode ) {
        if ( in_array( $known_mode, array( self::REST_MODE_REWRITE, self::REST_MODE_QUERY ), true ) ) {
            return array( $known_mode );
        }
        return array( self::REST_MODE_REWRITE, self::REST_MODE_QUERY );
    }

    /** Parses only non-sensitive, expected error metadata; response bodies are never shown in admin. */
    private static function response_data( $response, $default_stage ) {
        if ( is_wp_error( $response ) ) { return self::error( 'connector_core_inaccessible', '', $default_stage ); }
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( 300 <= $status && 399 >= $status ) { return self::error( 'connector_core_redirect_rejected', '', $default_stage ); }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 404 === $status || ( is_array( $data ) && 'rest_no_route' === ( $data['code'] ?? '' ) ) ) { return self::error( 'connector_route_missing', self::diagnostic_from( $data ), 'route' ); }
        if ( 200 > $status || 299 < $status ) {
            $code = is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '';
            $safe = array( 'https_required', 'connector_project_inactive', 'connector_client_rejected', 'connector_secret_rejected', 'connector_permissions_missing', 'connector_permission_wallet_read_missing', 'connector_permission_reward_claim_missing', 'connector_token_rejected', 'connector_credentials_missing', 'schema_not_ready', 'reward_busy' );
            $code = in_array( $code, $safe, true ) ? $code : 'connector_core_rejected';
            return self::error( $code, self::diagnostic_from( $data ), self::stage_for_code( $code, $default_stage ) );
        }
        return is_array( $data ) ? $data : self::error( 'connector_core_invalid_response', '', $default_stage );
    }

    /** Accepts only a canonical HTTPS WordPress site URL, never a REST endpoint. */
    private static function normalise_site_url( $value ) {
        $value = self::raw_https_url( $value );
        if ( '' === $value ) { return ''; }
        $parts = wp_parse_url( $value );
        if ( ! is_array( $parts ) ) { return ''; }
        $path = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
        if ( 1 === preg_match( '#(?:^|/)(?:wp-json|index\\.php)(?:/|$)#i', $path ) ) { return ''; }
        return self::site_url_from_parts( $parts, $path );
    }

    private static function raw_https_url( $value, $allow_query = false ) {
        $value = is_string( $value ) ? esc_url_raw( trim( wp_unslash( $value ) ) ) : '';
        $parts = wp_parse_url( $value );
        if ( ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || ( ! $allow_query && isset( $parts['query'] ) ) || ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) ) { return ''; }
        return $value;
    }

    private static function site_url_from_parts( $parts, $path ) {
        $site_url = 'https://' . $parts['host'];
        if ( isset( $parts['port'] ) && 443 === (int) $parts['port'] ) { $site_url .= ':443'; }
        $path = '/' . ltrim( (string) $path, '/' );
        return rtrim( $site_url . ( '/' === $path ? '' : $path ), '/' );
    }

    private static function client_id( $value ) { $value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : ''; return 1 === preg_match( '/^tec_[A-Za-z0-9_-]{20,60}$/', $value ) ? $value : ''; }
    private static function project_key( $value ) { $value = is_string( $value ) ? strtolower( sanitize_text_field( wp_unslash( $value ) ) ) : ''; return 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{1,63}$/', $value ) ? $value : ''; }
    private static function permissions( $permissions ) {
        if ( ! is_array( $permissions ) ) { return array(); }
        $normalised = array();
        foreach ( array( self::PERMISSION_WALLET_READ, self::PERMISSION_REWARD_CLAIM ) as $permission ) {
            if ( in_array( $permission, $permissions, true ) ) { $normalised[] = $permission; }
        }
        return $normalised;
    }
    private static function rest_mode_label( $rest_mode ) { return self::REST_MODE_QUERY === $rest_mode ? 'rest_route' : 'wp-json'; }
    private static function successful_steps( $rest_mode ) { return array( 'url' => 'valid', 'rest' => self::rest_mode_label( $rest_mode ), 'route' => 'reachable', 'core' => 'identified', 'protocol' => 'compatible', 'credentials' => 'accepted', 'permission' => 'wallet.read', 'token' => 'received' ); }
    private static function stage_for_code( $code, $fallback ) { $stages = array( 'connector_client_rejected' => 'credentials', 'connector_secret_rejected' => 'credentials', 'connector_credentials_missing' => 'credentials', 'connector_project_inactive' => 'credentials', 'connector_permissions_missing' => 'permission', 'connector_permission_wallet_read_missing' => 'permission', 'connector_permission_reward_claim_missing' => 'permission', 'connector_token_rejected' => 'token' ); return $stages[ $code ] ?? $fallback; }
    private static function iso_datetime( $value ) { $value = is_string( $value ) ? trim( $value ) : ''; return '' !== $value && false !== strtotime( $value ) ? $value : ''; }
    private static function diagnostic_from( $data, $fallback = array() ) { $id = is_array( $data ) ? (string) ( $data['diagnostic_id'] ?? ( $data['data']['diagnostic_id'] ?? '' ) ) : ''; if ( '' === $id && is_array( $fallback ) ) { $id = (string) ( $fallback['diagnostic_id'] ?? '' ); } return 1 === preg_match( '/^[a-f0-9-]{16,64}$/i', $id ) ? $id : wp_generate_uuid4(); }
    private static function secret_state( $stored ) { if ( ! is_array( $stored ) || ! is_string( $stored['secret_protected'] ?? null ) || '' === $stored['secret_protected'] ) { return 'required'; } $secret = Token_Engine_Connector_Crypto::decrypt( $stored['secret_protected'] ); return is_wp_error( $secret ) || ! is_string( $secret ) || '' === $secret ? 'required' : 'saved'; }
    private static function configuration_error_code() { $settings = self::configuration(); if ( '' === $settings['core_site_url'] ) { return 'connector_core_url_invalid'; } if ( '' === $settings['client_id'] ) { return 'connector_client_invalid'; } if ( '' === $settings['project_key'] ) { return 'connector_project_invalid'; } return 'connector_secret_required'; }
    private static function configuration_stage() { $code = self::configuration_error_code(); return 'connector_core_url_invalid' === $code ? 'url' : 'credentials'; }
    private static function error( $code, $diagnostic_id = '', $stage = '' ) { return new WP_Error( $code, __( 'Le connecteur ne peut pas terminer cette opération.', 'token-engine-connector' ), array( 'diagnostic_id' => '' !== $diagnostic_id ? $diagnostic_id : wp_generate_uuid4(), 'stage' => $stage ) ); }
}
