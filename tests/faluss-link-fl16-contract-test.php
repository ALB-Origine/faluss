<?php

function fl16_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );
$depth = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-immersive.js' );

foreach ( array( 'body.faluss-identity-public-route', 'min-height:100vh;', 'min-height:100svh;', 'height: clamp(32rem, 92svh, 54rem)', 'safe-area-inset-top', 'safe-area-inset-bottom' ) as $needle ) {
    fl16_assert( false !== strpos( $immersive, $needle ), 'FL-16 needs a public-route-only stable viewport and safe-area strategy: ' . $needle );
}
fl16_assert( false === stripos( $immersive, 'dvh' ), 'FL-16 must not use Safari dynamic viewport units during scroll.' );
fl16_assert( false === stripos( $immersive, 'background-attachment: fixed' ), 'FL-16 must not use fixed backgrounds on mobile.' );
foreach ( array( "classList.contains('faluss-identity-public-route')", "addEventListener('scroll', requestUpdate, { passive: true })", 'requestAnimationFrame(update)', '--fl-immersive-depth', '--fl-immersive-panel-depth', 'prefers-reduced-motion: reduce' ) as $needle ) {
    fl16_assert( false !== strpos( $depth, $needle ), 'FL-16 depth must be route-scoped, passive, RAF-driven and motion-safe: ' . $needle );
}
foreach ( array( "addEventListener('resize'", 'visualViewport', '.style.height', '.style.minHeight', '.style.top', '.style.margin', '.style.padding', '.style.inset' ) as $forbidden ) {
    fl16_assert( false === strpos( $depth, $forbidden ), 'FL-16 must not mutate layout from viewport or scroll events: ' . $forbidden );
}
fl16_assert( 1 === substr_count( $depth, "addEventListener('scroll', requestUpdate, { passive: true })" ) && false !== strpos( $depth, 'falussLinkImmersiveReady' ), 'FL-16 must bind a single passive scroll listener and initialize each card once.' );
foreach ( array( 'transform: translate3d(0, var(--fl-immersive-depth, 0px), 0)', 'transform: translate3d(0, var(--fl-immersive-panel-depth, 0px), 0)' ) as $needle ) {
    fl16_assert( false !== strpos( $immersive, $needle ), 'FL-16 depth variables must only drive transforms: ' . $needle );
}

echo "FL-16 contract: OK\n";
