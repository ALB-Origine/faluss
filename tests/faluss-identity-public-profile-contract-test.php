<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );

class WP_Post {}
function sanitize_title( $value ) { return trim( preg_replace( '/-+/', '-', preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ) ), '-' ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function esc_url_raw( $url, $protocols = array() ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function get_post_types( $args = array(), $output = 'names' ) { return array( 'post', 'page' ); }
function get_page_by_path( $slug, $output = OBJECT, $post_types = array() ) { return null; }

require_once dirname( __DIR__ ) . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php';

function fi03_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function fi03_private( $method, ...$arguments ) {
    $reflection = new ReflectionMethod( 'Faluss_Identity_Public_Profile', $method );
    $reflection->setAccessible( true );
    return $reflection->invoke( null, ...$arguments );
}

// Positive scenario: a readable stable handle and ordered HTTPS links form a public profile.
fi03_assert( 'alice-lab' === fi03_private( 'normalize_slug', 'Alice Lab' ), 'Readable identifiers normalize to a stable slug.' );
$links = fi03_private( 'sanitize_links', array(
    array( 'position' => 2, 'label' => 'Site', 'url' => 'https://example.test/' ),
    array( 'position' => 1, 'label' => 'Portfolio', 'url' => 'https://portfolio.example.test/' ),
) );
fi03_assert( is_array( $links ) && 'Portfolio' === $links[0]['label'] && 1 === $links[0]['position'], 'External links retain their explicit order.' );
fi03_assert( ! fi03_private( 'is_reserved_slug', 'alice-lab' ), 'A non-conflicting handle is allowed.' );

// Negative scenario: core routes, malformed handles and unsafe links cannot be published.
fi03_assert( '' === fi03_private( 'normalize_slug', 'x' ) && '' === fi03_private( 'normalize_slug', '!!!' ), 'Invalid handle shapes are rejected.' );
fi03_assert( fi03_private( 'is_reserved_slug', 'wp-json' ) && fi03_private( 'is_reserved_slug', 'login' ), 'Core and login routes are reserved.' );
fi03_assert( null === fi03_private( 'validate_external_url', 'javascript:alert(1)' ) && null === fi03_private( 'validate_external_url', 'http://example.test/' ), 'Only safe HTTPS external links are accepted.' );

$source = file_get_contents( dirname( __DIR__ ) . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
foreach ( array( 'faluss_id', 'public_slug', 'publication_status', 'FOR UPDATE', 'target="_blank"', 'noopener noreferrer nofollow', 'add_rewrite_rule', 'get_page_by_path' ) as $required ) {
    fi03_assert( false !== strpos( $source, $required ), 'Missing FI-03 invariant: ' . $required );
}
fi03_assert( false === strpos( $source, 'user_email' ), 'Public profiles never store or render e-mail data.' );

echo 'FI-03 public-profile contract: OK' . PHP_EOL;
