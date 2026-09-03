<?php

define( 'ABSPATH', __DIR__ . '/' );

function wp_salt( $scheme = '' ) { return 'test-salt-' . $scheme; }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function home_url( $path = '/' ) {
    if ( '' === $path || '/' === $path ) { return 'https://faluss.me/'; }
    if ( '?' === $path[0] ) { return 'https://faluss.me/' . $path; }
    return 'https://faluss.me' . ( '/' === $path[0] ? $path : '/' . $path );
}
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_validate_redirect( $url, $fallback = '' ) { return $url ?: $fallback; }

require_once dirname( __DIR__ ) . '/plugins/faluss-identity/includes/class-faluss-identity-passwordless.php';

function fi02_passwordless_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function fi02_passwordless_private( $method, ...$arguments ) {
    $reflection = new ReflectionMethod( 'Faluss_Identity_Passwordless', $method );
    $reflection->setAccessible( true );
    return $reflection->invoke( null, ...$arguments );
}

// Positive scenario: an e-mail and an exact six-digit OTP can enter a ceremony.
fi02_passwordless_assert( 'member@example.test' === fi02_passwordless_private( 'normalize_email', ' Member@Example.Test ' ), 'Email normalization is canonical.' );
fi02_passwordless_assert( fi02_passwordless_private( 'is_valid_otp', '004281' ), 'A six-digit code with a leading zero is accepted.' );
$encoded = fi02_passwordless_private( 'base64url_encode', random_bytes( 64 ) );
fi02_passwordless_assert( is_array( fi02_passwordless_private( 'read_cookie_state' ) ) === false, 'No cookie never creates an implicit ceremony.' );
fi02_passwordless_assert( 64 === strlen( fi02_passwordless_private( 'base64url_decode', $encoded ) ), 'The cookie payload preserves both 256-bit secrets.' );

// Negative scenarios: malformed values cannot broaden proof, correlate buckets, or decode a cookie.
fi02_passwordless_assert( null === fi02_passwordless_private( 'normalize_email', 'not-an-email' ), 'Invalid email is rejected.' );
fi02_passwordless_assert( ! fi02_passwordless_private( 'is_valid_otp', '4281' ) && ! fi02_passwordless_private( 'is_valid_otp', '4281ab' ), 'Only exact numeric OTPs are accepted.' );
fi02_passwordless_assert( null === fi02_passwordless_private( 'base64url_decode', 'not/a-cookie' ), 'Cookie decoding rejects non-base64url input.' );
fi02_passwordless_assert( fi02_passwordless_private( 'secret_hash', 'same-value', 'email' ) !== fi02_passwordless_private( 'secret_hash', 'same-value', 'ip' ), 'Rate-limit buckets are domain separated.' );

// FI-02 corrections: new users always receive the least-privileged role and
// each post retains an exact local redirect, never an external URL.
fi02_passwordless_assert( 'https://faluss.me/espace-membre' === fi02_passwordless_private( 'local_redirect', '/espace-membre' ), 'A local path is retained.' );
fi02_passwordless_assert( 'https://faluss.me/retour?state=ok' === fi02_passwordless_private( 'local_redirect', 'https://faluss.me/retour?state=ok' ), 'A same-origin absolute URL is retained.' );
fi02_passwordless_assert( 'https://faluss.me/' === fi02_passwordless_private( 'local_redirect', 'https://attacker.example/collect' ), 'An external redirect falls back to the local home page.' );
fi02_passwordless_assert( 'https://faluss.me/' === fi02_passwordless_private( 'local_redirect', '//attacker.example/collect' ), 'A protocol-relative external redirect falls back to the local home page.' );

$source = file_get_contents( dirname( __DIR__ ) . '/plugins/faluss-identity/includes/class-faluss-identity-passwordless.php' );
foreach ( array( 'wp_hash_password', 'wp_check_password', 'START TRANSACTION', 'FOR UPDATE', "'secure' => true", "'httponly' => true", "'samesite' => 'Lax'", 'wp_set_auth_cookie' ) as $required ) {
    fi02_passwordless_assert( false !== strpos( $source, $required ), 'Missing FI-02 security primitive: ' . $required );
}
fi02_passwordless_assert( false === strpos( $source, '$_REQUEST' ), 'Passwordless endpoints do not accept merged request input.' );
fi02_passwordless_assert( false !== strpos( $source, "'role' => 'subscriber'" ), 'New passwordless accounts explicitly receive subscriber.' );
fi02_passwordless_assert( false === strpos( $source, "get_option( 'default_role'" ), 'New passwordless accounts do not inherit default_role.' );
fi02_passwordless_assert( strpos( $source, "get_user_by( 'email'" ) < strpos( $source, 'wp_insert_user' ), 'Existing accounts are returned before any role-bearing insert.' );
fi02_passwordless_assert( 3 === substr_count( $source, 'name="redirect_to"' ), 'Every passwordless form carries the redirect target.' );
foreach ( array( '#FFFDF5', '#FFFFFF', '#000000', '#FF3D16', 'Outfit', 'border-radius: 999px' ) as $required ) {
    fi02_passwordless_assert( false !== strpos( $source . file_get_contents( dirname( __DIR__ ) . '/plugins/faluss-identity/assets/css/faluss-identity-passwordless.css' ), $required ), 'Missing Faluss.me design token: ' . $required );
}
$widget_source = file_get_contents( dirname( __DIR__ ) . '/plugins/faluss-identity/includes/class-faluss-identity-elementor-widget.php' );
foreach ( array( "'redirect_url'", 'Group_Control_Typography', 'Group_Control_Border', 'Group_Control_Box_Shadow', "'button_hover'", '#FFFDF5', '#FF3D16' ) as $required ) {
    fi02_passwordless_assert( false !== strpos( $widget_source, $required ), 'Missing Elementor FI-02 control: ' . $required );
}

echo 'FI-02 passwordless contract: OK' . PHP_EOL;
