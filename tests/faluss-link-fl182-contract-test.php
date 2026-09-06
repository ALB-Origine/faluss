<?php

function fl182_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$discoveries = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-discoveries.css' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );

fl182_assert( false !== strpos( $identity, '<div class="faluss-identity-public-header-layer">' ) && false !== strpos( $identity, '<main class="faluss-identity-profile-page' ), 'The route-only public shell must retain separate header and profile siblings.' );
fl182_assert( false !== strpos( $immersive, "body.faluss-identity-public-route > .faluss-identity-public-header-layer,\nbody.faluss-identity-public-route > .elementor-location-header" ) && false !== strpos( $immersive, 'position: absolute;') && false !== strpos( $immersive, 'z-index: 1;'), 'The header plane must overlay the immersive route without changing Elementor itself.' );
fl182_assert( false !== strpos( $immersive, 'body.faluss-identity-public-route > .faluss-identity-profile-page') && false !== strpos( $immersive, 'z-index: 0;') && false !== strpos( $immersive, 'overflow: visible;'), 'The card plane must remain below the header and never clip an Elementor sidebar.' );
fl182_assert( false === strpos( $immersive, 'pointer-events:' ), 'No public immersive shell, header layer, hero, or card container may suppress pointer interaction.' );

foreach ( array( 'html', 'body', '#page', '#content', 'main', '.elementor-location-header' ) as $global ) {
    fl182_assert( false === strpos( $immersive, $global . '{pointer-events:none' ), 'No global WordPress or Elementor container may receive pointer-events: none: ' . $global );
}
fl182_assert( false === strpos( $studio, 'faluss-identity-public-header-layer' ) && false === strpos( $discoveries, 'faluss-identity-public-header-layer' ), 'Studio and Mes découvertes must not receive public-header interaction rules.' );
foreach ( array( '.faluss-link-card__social a', '.faluss-link-card__link', '.faluss-link-action', '.faluss-link-studio__tabs button' ) as $interactive ) {
    fl182_assert( false !== strpos( $card . $studio, $interactive ), 'The public card and Studio must retain their own interactive controls: ' . $interactive );
}
fl182_assert( false !== strpos( $card, '.faluss-link-card__cover::after') && false !== strpos( $card, 'pointer-events:none'), 'Only a local decorative cover pseudo-element may ignore pointer events.' );
fl182_assert( false !== strpos( $immersive, 'margin: 0;') && false !== strpos( $immersive, 'padding: 0;') && false !== strpos( $immersive, 'background: var(--faluss-ink, #080808)'), 'The public hero must stay flush with the viewport without a white flow band.' );

echo "FL-18.2 interaction and public header contract: OK\n";
