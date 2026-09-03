<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FI-02 central passwordless ceremony.
 *
 * The only browser-held state is an HttpOnly pair of independent 256-bit
 * secrets. Database rows keep one-way derivatives only. No request parameter,
 * redirect, audit record or error message contains an email, OTP or secret.
 */
final class Faluss_Identity_Passwordless {

    const COOKIE_NAME = 'faluss_identity_challenge';
    const COOKIE_TTL = 600;
    const MAX_OTP_ATTEMPTS = 5;
    const REQUEST_LIMIT = 5;
    const REQUEST_WINDOW = 900;
    const NOTICE_KEY = 'faluss_identity_notice';
    const STYLE_HANDLE = 'faluss-identity-passwordless';

    public static function register() {
        add_shortcode( 'faluss_identity_login', array( __CLASS__, 'shortcode' ) );
        add_action( 'admin_post_nopriv_faluss_identity_request_code', array( __CLASS__, 'handle_request_code' ) );
        add_action( 'admin_post_faluss_identity_request_code', array( __CLASS__, 'handle_request_code' ) );
        add_action( 'admin_post_nopriv_faluss_identity_verify_code', array( __CLASS__, 'handle_verify_code' ) );
        add_action( 'admin_post_faluss_identity_verify_code', array( __CLASS__, 'handle_verify_code' ) );
    }

    public static function register_assets() {
        wp_register_style(
            self::STYLE_HANDLE,
            plugins_url( 'assets/css/faluss-identity-passwordless.css', FALUSS_IDENTITY_FILE ),
            array(),
            FALUSS_IDENTITY_VERSION
        );
    }

    public static function shortcode( $attributes = array() ) {
        return self::render_form( (array) $attributes );
    }

    /**
     * @param array<string, string> $settings Presentation-only customizations.
     * @return string
     */
    public static function render_form( $settings = array() ) {
        if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
            self::register_assets();
        }
        wp_enqueue_style( self::STYLE_HANDLE );

        $defaults = array(
            'heading'       => __( 'Bienvenue sur Faluss', 'faluss-identity' ),
            'intro'         => __( 'Entrez votre adresse e-mail pour recevoir un code de connexion.', 'faluss-identity' ),
            'accent_color'  => '#9b5cff',
            'surface_color' => '#171225',
            'text_color'    => '#f7f3ff',
            'radius'        => '18',
        );
        $settings = wp_parse_args( $settings, $defaults );
        $style = sprintf(
            '--faluss-identity-accent:%1$s;--faluss-identity-surface:%2$s;--faluss-identity-text:%3$s;--faluss-identity-radius:%4$dpx;',
            esc_attr( self::sanitize_hex_color( $settings['accent_color'], $defaults['accent_color'] ) ),
            esc_attr( self::sanitize_hex_color( $settings['surface_color'], $defaults['surface_color'] ) ),
            esc_attr( self::sanitize_hex_color( $settings['text_color'], $defaults['text_color'] ) ),
            max( 0, min( 48, (int) $settings['radius'] ) )
        );

        $notice = isset( $_GET[ self::NOTICE_KEY ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_KEY ] ) ) : '';
        $has_challenge = self::read_cookie_state() !== null;

        ob_start();
        ?>
        <section class="faluss-identity-login" style="<?php echo $style; ?>">
            <div class="faluss-identity-login__glow" aria-hidden="true"></div>
            <div class="faluss-identity-login__content">
                <p class="faluss-identity-login__eyebrow">FALUSS IDENTITY</p>
                <h2><?php echo esc_html( $settings['heading'] ); ?></h2>
                <p class="faluss-identity-login__intro"><?php echo esc_html( $settings['intro'] ); ?></p>
                <?php self::render_notice( $notice ); ?>
                <?php if ( is_user_logged_in() ) : ?>
                    <p class="faluss-identity-login__notice faluss-identity-login__notice--success"><?php esc_html_e( 'Votre session Faluss est active.', 'faluss-identity' ); ?></p>
                <?php elseif ( $has_challenge ) : ?>
                    <form class="faluss-identity-login__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
                        <input type="hidden" name="action" value="faluss_identity_verify_code">
                        <?php wp_nonce_field( 'faluss_identity_verify_code', 'faluss_identity_nonce' ); ?>
                        <label for="faluss-identity-otp"><?php esc_html_e( 'Code à 6 chiffres', 'faluss-identity' ); ?></label>
                        <input id="faluss-identity-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                        <button type="submit"><?php esc_html_e( 'Vérifier et continuer', 'faluss-identity' ); ?></button>
                    </form>
                    <form class="faluss-identity-login__secondary" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="faluss_identity_request_code">
                        <?php wp_nonce_field( 'faluss_identity_request_code', 'faluss_identity_nonce' ); ?>
                        <button type="submit" class="faluss-identity-login__link"><?php esc_html_e( 'Recevoir un nouveau code', 'faluss-identity' ); ?></button>
                    </form>
                <?php else : ?>
                    <form class="faluss-identity-login__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="faluss_identity_request_code">
                        <?php wp_nonce_field( 'faluss_identity_request_code', 'faluss_identity_nonce' ); ?>
                        <label for="faluss-identity-email"><?php esc_html_e( 'Adresse e-mail', 'faluss-identity' ); ?></label>
                        <input id="faluss-identity-email" name="email" type="email" autocomplete="email" maxlength="320" required>
                        <button type="submit"><?php esc_html_e( 'Recevoir mon code', 'faluss-identity' ); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_request_code() {
        if ( ! self::valid_nonce( 'faluss_identity_request_code' ) ) {
            self::redirect_with_notice( 'invalid' );
        }

        $email = isset( $_POST['email'] ) ? self::normalize_email( wp_unslash( $_POST['email'] ) ) : null;
        if ( null === $email ) {
            $email = self::email_for_current_challenge();
        }
        if ( null === $email || ! Faluss_Identity_Schema::get_status()['ready'] ) {
            if ( null === $email ) {
                self::clear_cookie();
            }
            self::redirect_with_notice( 'sent' );
        }

        self::issue_challenge( $email, self::client_ip() );
        self::redirect_with_notice( 'sent' );
    }

    public static function handle_verify_code() {
        if ( ! self::valid_nonce( 'faluss_identity_verify_code' ) ) {
            self::redirect_with_notice( 'invalid' );
        }

        $otp = isset( $_POST['otp'] ) ? (string) wp_unslash( $_POST['otp'] ) : '';
        $state = self::read_cookie_state();
        if ( ! self::is_valid_otp( $otp ) || null === $state ) {
            self::redirect_with_notice( 'invalid' );
        }

        $email = self::consume_valid_otp( $state, $otp );
        if ( null === $email ) {
            self::redirect_with_notice( 'invalid' );
        }

        $user = self::establish_local_identity( $email );
        if ( ! $user instanceof WP_User || self::is_privileged_user( $user ) ) {
            self::clear_cookie();
            self::redirect_with_notice( 'invalid' );
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, false, is_ssl() );
        do_action( 'wp_login', $user->user_login, $user );
        self::clear_cookie();
        self::record_audit( 'passwordless_session_opened' );
        self::redirect_with_notice( 'authenticated' );
    }

    /**
     * Generates, stores and mails one passwordless ceremony. Delivery failures
     * are intentionally indistinguishable from all other request outcomes.
     */
    private static function issue_challenge( $email, $ip ) {
        try {
            $challenge = random_bytes( 32 );
            $browser_secret = random_bytes( 32 );
            $otp = sprintf( '%06d', random_int( 0, 999999 ) );
        } catch ( Exception $exception ) {
            return false;
        }

        $challenge_hash = hash( 'sha256', $challenge );
        $browser_hash = self::secret_hash( $browser_secret, 'browser' );
        $email_hash = self::secret_hash( $email, 'email' );
        $ip_hash = self::secret_hash( $ip, 'ip' );
        if ( null === $browser_hash || null === $email_hash || null === $ip_hash ) {
            return false;
        }

        if ( ! self::reserve_request_limits( $email_hash, $ip_hash ) ) {
            return false;
        }

        // A replacement invalidates the prior code before a new one is sent.
        self::invalidate_current_challenge();

        global $wpdb;
        $tables = Faluss_Identity_Schema::get_table_names();
        if ( empty( $tables['challenges'] ) ) {
            return false;
        }
        $now = current_time( 'mysql', true );
        $expires = gmdate( 'Y-m-d H:i:s', time() + self::COOKIE_TTL );
        $inserted = $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . self::quote_identifier( $tables['challenges'] ) . ' (challenge_hash, otp_hash, browser_fingerprint_hash, email, email_hash, attempt_count, status, expires_at, created_at) VALUES (%s, %s, %s, %s, %s, %d, %s, %s, %s)',
                $challenge_hash,
                wp_hash_password( $otp ),
                $browser_hash,
                $email,
                $email_hash,
                0,
                'pending',
                $expires,
                $now
            )
        );
        if ( 1 !== $inserted ) {
            return false;
        }

        $sent = wp_mail(
            $email,
            __( 'Votre code de connexion Faluss', 'faluss-identity' ),
            sprintf( __( "Votre code Faluss est : %s\n\nIl expire dans 10 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.", 'faluss-identity' ), $otp )
        );
        if ( ! $sent ) {
            $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_identifier( $tables['challenges'] ) . ' SET status = %s WHERE challenge_hash = %s AND status = %s', 'delivery_failed', $challenge_hash, 'pending' ) );
            return false;
        }

        self::write_cookie_state( $challenge, $browser_secret );
        self::record_audit( 'passwordless_code_requested' );
        return true;
    }

    /**
     * Atomically records every failed OTP and consumes the one successful OTP.
     * @return string|null Verified email only for the immediate local session.
     */
    private static function consume_valid_otp( $state, $otp ) {
        global $wpdb;
        $tables = Faluss_Identity_Schema::get_table_names();
        $browser_hash = self::secret_hash( $state['browser_secret'], 'browser' );
        if ( empty( $tables['challenges'] ) || null === $browser_hash ) {
            return null;
        }

        $challenge_hash = hash( 'sha256', $state['challenge'] );
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return null;
        }
        try {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT id, email, otp_hash, attempt_count, status, expires_at FROM ' . self::quote_identifier( $tables['challenges'] ) . ' WHERE challenge_hash = %s AND browser_fingerprint_hash = %s FOR UPDATE',
                    $challenge_hash,
                    $browser_hash
                ),
                ARRAY_A
            );
            $valid = is_array( $row )
                && 'pending' === $row['status']
                && self::is_future_utc( $row['expires_at'] )
                && (int) $row['attempt_count'] < self::MAX_OTP_ATTEMPTS
                && wp_check_password( $otp, $row['otp_hash'] );

            if ( ! $valid ) {
                if ( is_array( $row ) && 'pending' === $row['status'] && (int) $row['attempt_count'] < self::MAX_OTP_ATTEMPTS ) {
                    $attempts = (int) $row['attempt_count'] + 1;
                    $status = $attempts >= self::MAX_OTP_ATTEMPTS ? 'locked' : 'pending';
                    $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_identifier( $tables['challenges'] ) . ' SET attempt_count = %d, status = %s WHERE id = %d', $attempts, $status, (int) $row['id'] ) );
                }
                $wpdb->query( 'COMMIT' );
                self::record_audit( 'passwordless_code_rejected' );
                return null;
            }

            $consumed = $wpdb->query(
                $wpdb->prepare(
                    'UPDATE ' . self::quote_identifier( $tables['challenges'] ) . ' SET status = %s, consumed_at = %s WHERE id = %d AND status = %s AND consumed_at IS NULL',
                    'consumed',
                    current_time( 'mysql', true ),
                    (int) $row['id'],
                    'pending'
                )
            );
            if ( 1 !== $consumed || false === $wpdb->query( 'COMMIT' ) ) {
                $wpdb->query( 'ROLLBACK' );
                return null;
            }
            return self::normalize_email( $row['email'] );
        } catch ( Exception $exception ) {
            $wpdb->query( 'ROLLBACK' );
            return null;
        }
    }

    /** Rate limits email and IP in a single InnoDB transaction. */
    private static function reserve_request_limits( $email_hash, $ip_hash ) {
        global $wpdb;
        $tables = Faluss_Identity_Schema::get_table_names();
        if ( empty( $tables['rate_limits'] ) ) {
            return false;
        }
        for ( $retry = 0; $retry < 2; ++$retry ) {
            if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
                return false;
            }
            try {
                $email_ok = self::reserve_rate_limit( $tables['rate_limits'], 'email', $email_hash );
                $ip_ok = $email_ok && self::reserve_rate_limit( $tables['rate_limits'], 'ip', $ip_hash );
                if ( $email_ok && $ip_ok && false !== $wpdb->query( 'COMMIT' ) ) {
                    return true;
                }
                $wpdb->query( 'ROLLBACK' );
                if ( ! $email_ok || ! $ip_ok ) {
                    return false;
                }
            } catch ( Exception $exception ) {
                $wpdb->query( 'ROLLBACK' );
            }
        }
        return false;
    }

    private static function reserve_rate_limit( $table, $bucket_type, $bucket_hash ) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, attempt_count, expires_at FROM ' . self::quote_identifier( $table ) . ' WHERE bucket_type = %s AND bucket_hash = %s FOR UPDATE',
                $bucket_type,
                $bucket_hash
            ),
            ARRAY_A
        );
        $now = current_time( 'mysql', true );
        $expires = gmdate( 'Y-m-d H:i:s', time() + self::REQUEST_WINDOW );
        if ( ! is_array( $row ) ) {
            return 1 === $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::quote_identifier( $table ) . ' (bucket_type, bucket_hash, attempt_count, window_started_at, expires_at, updated_at) VALUES (%s, %s, %d, %s, %s, %s)', $bucket_type, $bucket_hash, 1, $now, $expires, $now ) );
        }
        if ( ! self::is_future_utc( $row['expires_at'] ) ) {
            return 1 === $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_identifier( $table ) . ' SET attempt_count = %d, window_started_at = %s, expires_at = %s, updated_at = %s WHERE id = %d', 1, $now, $expires, $now, (int) $row['id'] ) );
        }
        if ( (int) $row['attempt_count'] >= self::REQUEST_LIMIT ) {
            return false;
        }
        return 1 === $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_identifier( $table ) . ' SET attempt_count = attempt_count + 1, updated_at = %s WHERE id = %d AND attempt_count < %d', $now, (int) $row['id'], self::REQUEST_LIMIT ) );
    }

    private static function find_or_create_safe_user( $email ) {
        $existing = get_user_by( 'email', $email );
        if ( $existing instanceof WP_User ) {
            return $existing;
        }

        $role = self::safe_default_role();
        if ( null === $role ) {
            return null;
        }
        for ( $attempt = 0; $attempt < 3; ++$attempt ) {
            try {
                $login = 'faluss_' . bin2hex( random_bytes( 10 ) );
                $password = bin2hex( random_bytes( 32 ) );
            } catch ( Exception $exception ) {
                return null;
            }
            $user_id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $password, 'user_email' => $email, 'role' => $role ) );
            if ( ! is_wp_error( $user_id ) ) {
                return get_user_by( 'id', (int) $user_id );
            }
            $existing = get_user_by( 'email', $email );
            if ( $existing instanceof WP_User ) {
                return $existing;
            }
        }
        return null;
    }

    /**
     * WordPress user creation/recovery and active Faluss profile creation share
     * one database transaction. The OTP has already been consumed at this
     * point, so every error fails closed without creating a session.
     */
    private static function establish_local_identity( $email ) {
        global $wpdb;

        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return null;
        }
        try {
            $user = self::find_or_create_safe_user( $email );
            $identity = $user instanceof WP_User && ! self::is_privileged_user( $user )
                ? Faluss_Identity_Registry::activate_for_wp_user( $user->ID )
                : null;
            if ( ! $user instanceof WP_User || null === $identity || false === $wpdb->query( 'COMMIT' ) ) {
                $wpdb->query( 'ROLLBACK' );
                return null;
            }
            return $user;
        } catch ( Exception $exception ) {
            $wpdb->query( 'ROLLBACK' );
            return null;
        }
    }

    private static function safe_default_role() {
        $role = (string) get_option( 'default_role', 'subscriber' );
        if ( '' === $role || self::role_is_privileged( $role ) ) {
            return null;
        }
        return $role;
    }

    private static function is_privileged_user( $user ) {
        foreach ( array( 'manage_options', 'edit_users', 'promote_users', 'delete_users' ) as $capability ) {
            if ( user_can( $user, $capability ) ) {
                return true;
            }
        }
        return false;
    }

    private static function role_is_privileged( $role_name ) {
        $role = get_role( $role_name );
        if ( ! $role instanceof WP_Role ) {
            return true;
        }
        foreach ( array( 'manage_options', 'edit_users', 'promote_users', 'delete_users' ) as $capability ) {
            if ( ! empty( $role->capabilities[ $capability ] ) ) {
                return true;
            }
        }
        return false;
    }

    private static function write_cookie_state( $challenge, $browser_secret ) {
        $value = self::base64url_encode( $challenge . $browser_secret );
        $options = self::cookie_options( time() + self::COOKIE_TTL );
        setcookie( self::COOKIE_NAME, $value, $options );
        $_COOKIE[ self::COOKIE_NAME ] = $value;
    }

    private static function read_cookie_state() {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) || ! is_string( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }
        $decoded = self::base64url_decode( $_COOKIE[ self::COOKIE_NAME ] );
        if ( ! is_string( $decoded ) || 64 !== strlen( $decoded ) ) {
            return null;
        }
        return array( 'challenge' => substr( $decoded, 0, 32 ), 'browser_secret' => substr( $decoded, 32, 32 ) );
    }

    /** Returns no data to the browser; used only to rotate a live ceremony. */
    private static function email_for_current_challenge() {
        global $wpdb;
        $state = self::read_cookie_state();
        $tables = Faluss_Identity_Schema::get_table_names();
        $browser_hash = null === $state ? null : self::secret_hash( $state['browser_secret'], 'browser' );
        if ( null === $state || null === $browser_hash || empty( $tables['challenges'] ) ) {
            return null;
        }
        $email = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT email FROM ' . self::quote_identifier( $tables['challenges'] ) . ' WHERE challenge_hash = %s AND browser_fingerprint_hash = %s AND status = %s AND expires_at > %s',
                hash( 'sha256', $state['challenge'] ),
                $browser_hash,
                'pending',
                gmdate( 'Y-m-d H:i:s' )
            )
        );
        return self::normalize_email( $email );
    }

    private static function invalidate_current_challenge() {
        global $wpdb;
        $state = self::read_cookie_state();
        $tables = Faluss_Identity_Schema::get_table_names();
        $browser_hash = null === $state ? null : self::secret_hash( $state['browser_secret'], 'browser' );
        if ( null === $state || null === $browser_hash || empty( $tables['challenges'] ) ) {
            return;
        }
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::quote_identifier( $tables['challenges'] ) . ' SET status = %s WHERE challenge_hash = %s AND browser_fingerprint_hash = %s AND status = %s',
                'replaced',
                hash( 'sha256', $state['challenge'] ),
                $browser_hash,
                'pending'
            )
        );
    }

    private static function clear_cookie() {
        setcookie( self::COOKIE_NAME, '', self::cookie_options( time() - 3600 ) );
        unset( $_COOKIE[ self::COOKIE_NAME ] );
    }

    private static function cookie_options( $expires ) {
        return array(
            'expires' => $expires,
            'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
            'domain' => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        );
    }

    private static function valid_nonce( $action ) {
        return isset( $_POST['faluss_identity_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faluss_identity_nonce'] ) ), $action );
    }

    private static function normalize_email( $email ) {
        $email = strtolower( trim( (string) $email ) );
        return is_email( $email ) ? $email : null;
    }

    private static function is_valid_otp( $otp ) {
        return is_string( $otp ) && 1 === preg_match( '/^[0-9]{6}$/D', $otp );
    }

    private static function secret_hash( $value, $context ) {
        if ( ! function_exists( 'wp_salt' ) ) {
            return null;
        }
        $salt = wp_salt( 'faluss_identity_' . $context );
        return is_string( $salt ) && '' !== $salt ? hash_hmac( 'sha256', $value, $salt ) : null;
    }

    private static function client_ip() {
        return isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    private static function is_future_utc( $datetime ) {
        return is_string( $datetime ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $datetime ) && $datetime > gmdate( 'Y-m-d H:i:s' );
    }

    private static function base64url_encode( $value ) {
        return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
    }

    private static function base64url_decode( $value ) {
        if ( ! is_string( $value ) || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/D', $value ) ) {
            return null;
        }
        $decoded = base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
        return false === $decoded ? null : $decoded;
    }

    private static function sanitize_hex_color( $color, $fallback ) {
        $color = is_string( $color ) ? $color : '';
        return preg_match( '/^#[a-fA-F0-9]{6}$/D', $color ) ? $color : $fallback;
    }

    private static function redirect_with_notice( $notice ) {
        $url = wp_validate_redirect( wp_get_referer(), home_url( '/' ) );
        wp_safe_redirect( add_query_arg( self::NOTICE_KEY, sanitize_key( $notice ), $url ) );
        exit;
    }

    private static function render_notice( $notice ) {
        $messages = array(
            'sent'          => __( 'Si cette adresse peut recevoir un code, celui-ci vient d’être envoyé. Vérifiez aussi vos indésirables.', 'faluss-identity' ),
            'invalid'       => __( 'Nous ne pouvons pas valider ce code. Demandez-en un nouveau et réessayez.', 'faluss-identity' ),
            'authenticated' => __( 'Votre identité a été vérifiée.', 'faluss-identity' ),
        );
        if ( isset( $messages[ $notice ] ) ) {
            $modifier = 'authenticated' === $notice ? ' faluss-identity-login__notice--success' : '';
            echo '<p class="faluss-identity-login__notice' . esc_attr( $modifier ) . '">' . esc_html( $messages[ $notice ] ) . '</p>';
        }
    }

    private static function record_audit( $event_type ) {
        global $wpdb;
        $tables = Faluss_Identity_Schema::get_table_names();
        if ( empty( $tables['audit'] ) ) {
            return;
        }
        try {
            $bytes = random_bytes( 16 );
        } catch ( Exception $exception ) {
            return;
        }
        $bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
        $bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
        $hex = bin2hex( $bytes );
        $event_id = substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20, 12 );
        $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::quote_identifier( $tables['audit'] ) . ' (event_id, event_type, occurred_at, expires_at) VALUES (%s, %s, %s, %s)', $event_id, $event_type, current_time( 'mysql', true ), gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ) ) );
    }

    private static function quote_identifier( $identifier ) {
        return chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $identifier ) . chr( 96 );
    }
}
