<?php

function fl181_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );

fl181_assert( false !== strpos( $identity, '<div class="faluss-identity-public-header-layer">' ) && false !== strpos( $identity, 'self::render_elementor_header();' ), 'The public route shell must emit the Elementor header inside its own route-only layer.' );
fl181_assert( strpos( $identity, '<div class="faluss-identity-public-header-layer">' ) > strpos( $identity, 'self::render_elementor_template( self::get_template_id() )' ), 'The out-of-flow header wrapper must follow the profile in DOM order so external off-canvas layers are not trapped below it.' );
foreach ( array(
    'body.faluss-identity-public-route > .faluss-identity-public-header-layer',
    'body.faluss-identity-public-route > .elementor-location-header',
    'body.faluss-identity-public-route > .faluss-identity-profile-page',
    'position: absolute;',
    'overflow: visible;',
) as $needle ) {
    fl181_assert( false !== strpos( $immersive, $needle ), 'FL-18.1 must retain the scoped public header/card planes: ' . $needle );
}

$header_plane_start = strpos( $immersive, 'body.faluss-identity-public-route > .faluss-identity-public-header-layer,' );
$header_plane_end = strpos( $immersive, '}', $header_plane_start );
$header_plane = substr( $immersive, $header_plane_start, $header_plane_end - $header_plane_start + 1 );
fl181_assert( false === strpos( $header_plane, 'pointer-events:' ) && false === strpos( $header_plane, 'isolation:' ) && false === strpos( $header_plane, 'z-index:' ), 'The transparent header wrapper must remain a native interactive plane without trapping external overlay stacks.' );
fl181_assert( false === strpos( $immersive, 'faluss-identity-public-header-layer > *' ), 'The public shell must not use a child pointer-events recovery rule.' );

$header_content_start = strpos( $immersive, 'body.faluss-identity-public-route > .faluss-identity-public-header-layer > header,' );
$header_content_end = strpos( $immersive, '}', $header_content_start );
$header_content = substr( $immersive, $header_content_start, $header_content_end - $header_content_start + 1 );
fl181_assert( false !== strpos( $header_content, 'overflow: visible;' ) && false === strpos( $header_content, 'pointer-events:' ) && false === strpos( $header_content, 'position:' ) && false === strpos( $header_content, 'z-index:' ), 'Elementor header controls and their sidebar must retain their own positioning and remain unclipped.' );

$profile_plane_start = strpos( $immersive, 'body.faluss-identity-public-route > .faluss-identity-profile-page {' );
$profile_plane_end = strpos( $immersive, '}', $profile_plane_start );
$profile_plane = substr( $immersive, $profile_plane_start, $profile_plane_end - $profile_plane_start + 1 );
fl181_assert( false !== strpos( $profile_plane, 'position: relative;' ) && false !== strpos( $profile_plane, 'overflow: visible;' ) && false === strpos( $profile_plane, 'pointer-events:' ) && false === strpos( $profile_plane, 'z-index:' ), 'The profile must remain independently interactive without creating a higher external overlay plane.' );

fl181_assert( false === strpos( $immersive, 'z-index: 9999' ) && false === strpos( $immersive, 'header{') && false === strpos( $immersive, 'footer{'), 'FL-18.1 must use a scoped layer relationship, not arbitrary z-index escalation or global header rules.' );

echo "FL-18.1 public Elementor header interaction contract: OK\n";
