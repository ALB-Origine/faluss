<?php

function fl183_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$discoveries = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-discoveries.css' );

fl183_assert( false !== strpos( $identity, '<div class="faluss-identity-public-header-layer">' ) && false !== strpos( $identity, 'self::render_elementor_header();' ) && false !== strpos( $identity, '<main class="faluss-identity-profile-page' ), 'The public route must keep the actual Elementor header wrapper and immersive profile as sibling shell planes.' );
foreach ( array(
    'body.faluss-identity-public-route > .faluss-identity-public-header-layer,',
    'body.faluss-identity-public-route > .elementor-location-header {',
    'body.faluss-identity-public-route > .faluss-identity-public-header-layer > header,',
    'body.faluss-identity-public-route > .faluss-identity-public-header-layer > .elementor-location-header,',
    'body.faluss-identity-public-route > .faluss-identity-public-header-layer > [class*="elementor-location-header"] {',
    'isolation: isolate;',
    'overflow: visible;',
    'z-index: 1;',
) as $needle ) {
    fl183_assert( false !== strpos( $immersive, $needle ), 'The real Elementor header wrapper must be an interactive upper plane: ' . $needle );
}

$profile_start = strpos( $immersive, 'body.faluss-identity-public-route > .faluss-identity-profile-page {' );
$profile_end = strpos( $immersive, '}', $profile_start );
$profile = substr( $immersive, $profile_start, $profile_end - $profile_start + 1 );
fl183_assert( false !== strpos( $profile, 'position: relative;' ) && false !== strpos( $profile, 'z-index: 0;' ) && false !== strpos( $profile, 'overflow: visible;' ), 'The immersive profile shell must remain in its lower unclipped plane.' );
fl183_assert( false === strpos( $immersive, 'pointer-events:' ), 'No route shell, card, or Elementor header container may suppress pointer interaction.' );
fl183_assert( false === strpos( $immersive, 'z-index: 9999' ) && false === strpos( $immersive, 'header{') && false === strpos( $immersive, 'footer{'), 'The fix must remain route-scoped and must not restyle global Elementor chrome.' );
fl183_assert( false === strpos( $studio, 'faluss-identity-public-header-layer' ) && false === strpos( $discoveries, 'faluss-identity-public-header-layer' ), 'Studio and Mes découvertes must not receive public-header stacking rules.' );
foreach ( array( '.faluss-link-card__social a', '.faluss-link-card__link', '.faluss-link-action', '.faluss-link-card__media-teaser' ) as $interactive ) {
    fl183_assert( false !== strpos( $card, $interactive ), 'Public card interaction must remain present below the header plane: ' . $interactive );
}
fl183_assert( false !== strpos( $immersive, 'margin: 0;' ) && false !== strpos( $immersive, 'padding: 0;' ) && false !== strpos( $immersive, 'background: var(--faluss-ink, #080808)' ), 'The header overlay must not restore a white band or top flow gap.' );

echo "FL-18.3 Elementor header interaction layer contract: OK\n";
