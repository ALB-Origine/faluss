<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The local OAuth client never shares a WordPress cookie with Identity. Its
 * browser-bound state starts only from an explicit same-site POST action.
 */
final class Faluss_Identity_Client {

    const SETTINGS = 'faluss_identity_client_settings';
    const COOKIE = 'faluss_identity_client_state';
    const TTL = 600;
    const MEMBER_SESSION_TTL = 3600;
    const STYLE = 'faluss-identity-client-button';
    const START_ACTION = 'faluss_identity_client_start';
    const CONTINUE_ACTION = 'faluss_identity_client_continue';

    public static function register() {
        add_shortcode( 'faluss_identity_client_button', array( __CLASS__, 'shortcode' ) );
        add_action( 'admin_post_nopriv_' . self::START_ACTION, array( __CLASS__, 'start' ) );
        add_action( 'admin_post_' . self::START_ACTION, array( __CLASS__, 'start' ) );
        add_action( 'admin_post_nopriv_' . self::CONTINUE_ACTION, array( __CLASS__, 'continue_session' ) );
        add_action( 'admin_post_' . self::CONTINUE_ACTION, array( __CLASS__, 'continue_session' ) );
        add_action( 'template_redirect', array( __CLASS__, 'callback' ), 0 );
        add_action( 'init', array( __CLASS__, 'rewrite' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_filter( 'auth_cookie_expiration', array( __CLASS__, 'member_cookie_expiration' ), PHP_INT_MAX, 3 );
    }

    public static function query_vars( $vars ) {
        $vars[] = 'faluss_identity_client_callback';
        return $vars;
    }

    public static function rewrite() {
        add_rewrite_rule( '^faluss-identity/callback/?$', 'index.php?faluss_identity_client_callback=1', 'top' );
    }

    public static function assets() {
        wp_register_style( self::STYLE, plugins_url( 'assets/css/faluss-identity-client.css', FALUSS_IDENTITY_CLIENT_FILE ), array(), FALUSS_IDENTITY_CLIENT_VERSION );
    }

    public static function config() {
        return wp_parse_args( (array) get_option( self::SETTINGS, array() ), array(
            'enabled' => false,
            'authority' => 'https://faluss.me',
            'client_id' => '',
            'return_urls' => array(),
        ) );
    }

    public static function enabled() {
        return ! empty( self::config()['enabled'] );
    }

    public static function shortcode( $attributes = array() ) {
        return self::button( (array) $attributes );
    }

    /**
     * This form is the reusable, explicit SSO-continuation entry point. A
     * portal can render it only where a local Faluss member session is needed.
     */
    public static function button( $attributes = array(), $inline = true ) {
        if ( ! self::enabled() ) {
            return '';
        }
        if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
            self::assets();
        }
        wp_enqueue_style( self::STYLE );
        $attributes = wp_parse_args( $attributes, array(
            'label' => __( 'Continuer avec Faluss', 'faluss-identity-client' ),
            'redirect_url' => home_url( '/' ),
            'accent_color' => '#FF3D16',
            'text_color' => '#080808',
            'surface_color' => '#FFFFFF',
            'radius' => '999',
        ) );
        $redirect = self::allowed_return( $attributes['redirect_url'] );
        $style = $inline
            ? ' style="--fic-accent:' . esc_attr( self::hex( $attributes['accent_color'], '#FF3D16' ) ) . ';--fic-ink:' . esc_attr( self::hex( $attributes['text_color'], '#080808' ) ) . ';--fic-surface:' . esc_attr( self::hex( $attributes['surface_color'], '#FFFFFF' ) ) . ';--fic-radius:' . max( 0, min( 48, (int) $attributes['radius'] ) ) . 'px;"'
            : '';

        return '<div class="faluss-identity-client-button"' . $style . '><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr( self::CONTINUE_ACTION ) . '">'
            . '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '">'
            . wp_nonce_field( self::CONTINUE_ACTION, 'faluss_identity_client_nonce', false, false )
            . '<button class="faluss-identity-client-button__action" type="submit">' . esc_html( $attributes['label'] ) . '</button></form></div>';
    }

    /** Backward-compatible handler for existing embeds using the original action. */
    public static function start() {
        self::begin( self::START_ACTION );
    }

    /** Dedicated action for a session-required Faluss.com portal route. */
    public static function continue_session() {
        self::begin( self::CONTINUE_ACTION );
    }

    /**
     * Local member sessions short-circuit before an OAuth navigation. Any
     * anonymous session continues via the existing authorization-code flow.
     */
    private static function begin( $nonce_action ) {
        if ( ! self::enabled() || ! self::valid_post_nonce( $nonce_action ) ) {
            self::local_notice( 'invalid' );
        }
        $config = self::config();
        $redirect = self::allowed_return( isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '' );
        if ( '' === $config['client_id'] || ! self::authority( $config['authority'] ) ) {
            self::local_notice( 'invalid', $redirect );
        }
        if ( self::is_local_member_session() ) {
            self::redirect_to_local( $redirect );
        }

        $mode = is_user_logged_in() ? 'link' : 'login';
        $user_id = 'link' === $mode ? get_current_user_id() : null;
        try {
            $state = random_bytes( 32 );
            $verifier = random_bytes( 32 );
            $browser = random_bytes( 32 );
        } catch ( Exception $exception ) {
            self::local_notice( 'invalid', $redirect );
        }
        $browser_hash = self::hmac( $browser );
        if ( null === $browser_hash || ! self::store_state( hash( 'sha256', $state ), $browser_hash, $redirect, $mode, $user_id ) ) {
            self::local_notice( 'invalid', $redirect );
        }
        $cookie = self::base64url_encode( $state . $verifier . $browser );
        setcookie( self::COOKIE, $cookie, self::cookie_options( time() + self::TTL ) );
        $_COOKIE[ self::COOKIE ] = $cookie;

        $query = array(
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => self::callback_uri(),
            // A local link needs only the opaque Faluss ID. A login may need a
            // verified email solely to create its first local subscriber.
            'scope' => 'link' === $mode ? 'identity.basic' : 'identity.basic identity.email',
            'state' => self::base64url_encode( $state ),
            'code_challenge' => self::base64url_encode( hash( 'sha256', self::base64url_encode( $verifier ), true ) ),
            'code_challenge_method' => 'S256',
        );
        wp_redirect( add_query_arg( $query, rtrim( $config['authority'], '/' ) . '/oauth/authorize' ), 302, 'Faluss Identity Client' );
        exit;
    }

    public static function callback() {
        if ( '1' !== (string) get_query_var( 'faluss_identity_client_callback' ) ) {
            return;
        }
        if ( ! self::enabled() ) {
            self::local_notice( 'invalid' );
        }
        $state = isset( $_GET['state'] ) && is_string( $_GET['state'] ) ? wp_unslash( $_GET['state'] ) : '';
        $code = isset( $_GET['code'] ) && is_string( $_GET['code'] ) ? wp_unslash( $_GET['code'] ) : '';
        $row = self::consume_state( $state );
        self::clear_cookie();
        if ( null === $row || ! self::is_opaque( $code ) ) {
            self::local_notice( 'invalid' );
        }
        $claims = self::exchange( $code, $row['verifier'] );
        if ( null === $claims ) {
            self::local_notice( 'invalid', $row['redirect_url'] );
        }
        $user = self::resolve_user( $claims, $row );
        if ( ! $user instanceof WP_User ) {
            self::local_notice( 'link_required', $row['redirect_url'] );
        }
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, false, is_ssl() );
        do_action( 'wp_login', $user->user_login, $user );
        self::redirect_to_local( $row['redirect_url'] );
    }

    /** @param int $expiration @param int $user_id @param bool $remember */
    public static function member_cookie_expiration( $expiration, $user_id, $remember ) {
        unset( $remember );
        return self::is_linked_normal_member( $user_id ) ? self::MEMBER_SESSION_TTL : $expiration;
    }

    private static function is_local_member_session() {
        return is_user_logged_in() && self::is_linked_normal_member( get_current_user_id() );
    }

    private static function is_linked_normal_member( $user_id ) {
        $user = get_userdata( (int) $user_id );
        if ( ! $user instanceof WP_User || array( 'subscriber' ) !== array_values( (array) $user->roles ) ) {
            return false;
        }
        global $wpdb;
        $tables = Faluss_Identity_Client_Schema::tables();
        if ( empty( $tables['links'] ) ) {
            return false;
        }
        return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT faluss_id FROM ' . self::quote_identifier( $tables['links'] ) . ' WHERE wp_user_id = %d', $user->ID ) );
    }

    private static function valid_post_nonce( $action ) {
        return isset( $_POST['faluss_identity_client_nonce'] )
            && is_string( $_POST['faluss_identity_client_nonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faluss_identity_client_nonce'] ) ), $action );
    }

    private static function consume_state( $state ) {
        $raw = self::cookie_state();
        if ( null === $raw || ! self::is_opaque( $state ) || ! hash_equals( self::base64url_encode( $raw['state'] ), $state ) ) {
            return null;
        }
        global $wpdb;
        $tables = Faluss_Identity_Client_Schema::tables();
        $browser_hash = self::hmac( $raw['browser'] );
        if ( empty( $tables['states'] ) || null === $browser_hash || false === $wpdb->query( 'START TRANSACTION' ) ) {
            return null;
        }
        try {
            $row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, redirect_url, flow_mode, wp_user_id, expires_at, consumed_at FROM ' . self::quote_identifier( $tables['states'] ) . ' WHERE state_hash = %s AND browser_hash = %s FOR UPDATE', hash( 'sha256', $raw['state'] ), $browser_hash ), ARRAY_A );
            if ( ! is_array( $row ) || null !== $row['consumed_at'] || $row['expires_at'] <= gmdate( 'Y-m-d H:i:s' ) || 1 !== $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_identifier( $tables['states'] ) . ' SET consumed_at = %s WHERE id = %d AND consumed_at IS NULL', gmdate( 'Y-m-d H:i:s' ), $row['id'] ) ) || false === $wpdb->query( 'COMMIT' ) ) {
                $wpdb->query( 'ROLLBACK' );
                return null;
            }
            $row['verifier'] = self::base64url_encode( $raw['verifier'] );
            return $row;
        } catch ( Exception $exception ) {
            $wpdb->query( 'ROLLBACK' );
            return null;
        }
    }

    private static function exchange( $code, $verifier ) {
        $config = self::config();
        $body = array(
            'grant_type' => 'authorization_code',
            'client_id' => $config['client_id'],
            'redirect_uri' => self::callback_uri(),
            'code' => $code,
            'code_verifier' => $verifier,
        );
        $secret = defined( 'FALUSS_IDENTITY_CLIENT_SECRET' ) ? constant( 'FALUSS_IDENTITY_CLIENT_SECRET' ) : '';
        if ( is_string( $secret ) && '' !== $secret ) {
            $body['client_secret'] = $secret;
        }
        $response = wp_remote_post( rtrim( $config['authority'], '/' ) . '/oauth/token', array(
            'timeout' => 15,
            'redirection' => 0,
            'headers' => array( 'Accept' => 'application/json' ),
            'body' => $body,
        ) );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }
        $claims = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $claims ) || ! self::is_uuid( isset( $claims['faluss_id'] ) ? $claims['faluss_id'] : '' ) || false === strpos( ' ' . ( isset( $claims['scope'] ) ? $claims['scope'] : '' ) . ' ', ' identity.basic ' ) ) {
            return null;
        }
        if ( isset( $claims['email'] ) && ! is_email( $claims['email'] ) ) {
            return null;
        }
        return $claims;
    }

    private static function resolve_user( $claims, $state ) {
        global $wpdb;
        $tables = Faluss_Identity_Client_Schema::tables();
        $linked = $wpdb->get_var( $wpdb->prepare( 'SELECT wp_user_id FROM ' . self::quote_identifier( $tables['links'] ) . ' WHERE faluss_id = %s', $claims['faluss_id'] ) );
        if ( 'link' === $state['flow_mode'] ) {
            if ( $linked || ! is_user_logged_in() || (int) $state['wp_user_id'] !== get_current_user_id() ) {
                return null;
            }
            return self::link( (int) $state['wp_user_id'], $claims['faluss_id'] ) ? get_user_by( 'id', (int) $state['wp_user_id'] ) : null;
        }
        if ( $linked ) {
            $user = get_user_by( 'id', (int) $linked );
            return $user instanceof WP_User ? $user : null;
        }
        if ( empty( $claims['email'] ) ) {
            return null;
        }
        $existing = get_user_by( 'email', $claims['email'] );
        if ( $existing instanceof WP_User || ! get_role( 'subscriber' ) instanceof WP_Role ) {
            return null;
        }
        try {
            $user_id = wp_insert_user( array(
                'user_login' => 'faluss_' . bin2hex( random_bytes( 10 ) ),
                'user_pass' => bin2hex( random_bytes( 32 ) ),
                'user_email' => $claims['email'],
                'role' => 'subscriber',
            ) );
        } catch ( Exception $exception ) {
            return null;
        }
        if ( is_wp_error( $user_id ) ) {
            return null;
        }
        return self::link( (int) $user_id, $claims['faluss_id'] ) ? get_user_by( 'id', (int) $user_id ) : null;
    }

    private static function link( $user_id, $faluss_id ) {
        global $wpdb;
        $tables = Faluss_Identity_Client_Schema::tables();
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return false;
        }
        try {
            $existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::quote_identifier( $tables['links'] ) . ' WHERE wp_user_id = %d OR faluss_id = %s FOR UPDATE', $user_id, $faluss_id ) );
            if ( $existing || 1 !== $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::quote_identifier( $tables['links'] ) . ' (wp_user_id, faluss_id, created_at, last_proved_at) VALUES (%d, %s, %s, %s)', $user_id, $faluss_id, gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s' ) ) ) || false === $wpdb->query( 'COMMIT' ) ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }
            return true;
        } catch ( Exception $exception ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
    }

    private static function store_state( $state_hash, $browser_hash, $url, $mode, $user_id ) {
        global $wpdb;
        $tables = Faluss_Identity_Client_Schema::tables();
        return ! empty( $tables['states'] ) && 1 === $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::quote_identifier( $tables['states'] ) . ' (state_hash, browser_hash, redirect_url, flow_mode, wp_user_id, expires_at, created_at) VALUES (%s, %s, %s, %s, %d, %s, %s)', $state_hash, $browser_hash, $url, $mode, $user_id, gmdate( 'Y-m-d H:i:s', time() + self::TTL ), gmdate( 'Y-m-d H:i:s' ) ) );
    }

    private static function cookie_state() {
        if ( empty( $_COOKIE[ self::COOKIE ] ) || ! is_string( $_COOKIE[ self::COOKIE ] ) ) {
            return null;
        }
        $raw = base64_decode( strtr( $_COOKIE[ self::COOKIE ], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $_COOKIE[ self::COOKIE ] ) % 4 ) % 4 ), true );
        return is_string( $raw ) && 96 === strlen( $raw ) ? array( 'state' => substr( $raw, 0, 32 ), 'verifier' => substr( $raw, 32, 32 ), 'browser' => substr( $raw, 64, 32 ) ) : null;
    }

    private static function allowed_return( $url ) {
        $home = home_url( '/' );
        $allowed = array_unique( array_merge( array( $home ), (array) self::config()['return_urls'] ) );
        return is_string( $url ) && in_array( $url, $allowed, true ) && self::is_local_url( $url ) ? $url : $home;
    }

    private static function is_local_url( $url ) {
        $candidate = wp_parse_url( $url );
        $home = wp_parse_url( home_url( '/' ) );
        if ( ! is_array( $candidate ) || ! is_array( $home ) || ! isset( $candidate['scheme'], $candidate['host'], $home['scheme'], $home['host'] ) || isset( $candidate['user'] ) || isset( $candidate['pass'] ) ) {
            return false;
        }
        $candidate_port = isset( $candidate['port'] ) ? (int) $candidate['port'] : ( 'https' === strtolower( $candidate['scheme'] ) ? 443 : 80 );
        $home_port = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === strtolower( $home['scheme'] ) ? 443 : 80 );
        return 0 === strcasecmp( $candidate['scheme'], $home['scheme'] )
            && 0 === strcasecmp( $candidate['host'], $home['host'] )
            && $candidate_port === $home_port;
    }

    private static function authority( $authority ) {
        return 'https://faluss.me' === rtrim( (string) $authority, '/' );
    }

    private static function callback_uri() {
        return home_url( '/faluss-identity/callback' );
    }

    private static function hmac( $value ) {
        return function_exists( 'wp_salt' ) ? hash_hmac( 'sha256', $value, wp_salt( 'faluss_identity_client_state' ) ) : null;
    }

    private static function base64url_encode( $value ) {
        return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
    }

    private static function is_opaque( $value ) {
        return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $value );
    }

    private static function is_uuid( $value ) {
        return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value );
    }

    private static function hex( $value, $fallback ) {
        return is_string( $value ) && 1 === preg_match( '/^#[A-Fa-f0-9]{6}$/D', $value ) ? $value : $fallback;
    }

    private static function cookie_options( $expires ) {
        return array( 'expires' => $expires, 'path' => '/', 'domain' => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' );
    }

    private static function clear_cookie() {
        setcookie( self::COOKIE, '', self::cookie_options( time() - 3600 ) );
        unset( $_COOKIE[ self::COOKIE ] );
    }

    private static function redirect_to_local( $url ) {
        wp_safe_redirect( self::allowed_return( $url ) );
        exit;
    }

    private static function local_notice( $notice = 'invalid', $url = null ) {
        wp_safe_redirect( add_query_arg( 'faluss_identity_client_notice', sanitize_key( $notice ), self::allowed_return( $url ) ) );
        exit;
    }

    private static function quote_identifier( $identifier ) {
        return chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $identifier ) . chr( 96 );
    }

    public static function callback_url() {
        return self::callback_uri();
    }
}
