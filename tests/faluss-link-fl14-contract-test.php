<?php

function fl14_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
$root = dirname( __DIR__ );
$identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );

fl14_assert( 1 === substr_count( $identity, 'name="viewport"' ) && false !== strpos( $identity, 'width=device-width, initial-scale=1, viewport-fit=cover' ), 'The public route must emit one enriched viewport declaration, not a competing second viewport.' );
foreach ( array( 'body.faluss-identity-public-route', 'html:has(body.faluss-identity-public-route)', 'background: var(--faluss-ink, #080808)', 'min-height: 100vh;', 'min-height: 100svh;' ) as $needle ) { fl14_assert( false !== strpos( $immersive, $needle ), 'The immersive shell must prevent a cream or white document gap: ' . $needle ); }
foreach ( array( 'body.faluss-identity-public-route > .faluss-identity-public-header-layer', 'body.faluss-identity-public-route > .elementor-location-header', 'position: absolute;', 'padding-top: env(safe-area-inset-top, 0px)', 'overflow: visible;', 'z-index: 1;' ) as $needle ) { fl14_assert( false !== strpos( $immersive, $needle ), 'The transparent header must remain over the hero with safe interactive content: ' . $needle ); }
foreach ( array( 'safe-area-inset-top', 'safe-area-inset-bottom', 'safe-area-inset-left', 'safe-area-inset-right', 'height: clamp(32rem, 92svh, 54rem)' ) as $needle ) { fl14_assert( false !== strpos( $immersive, $needle ), 'The immersive card must preserve edge-to-edge media and safe content spacing: ' . $needle ); }
fl14_assert( false === strpos( $immersive, '.faluss-link-studio' ) && false === strpos( $immersive, 'body:not(.faluss-identity-public-route)' ), 'FL-14 safe areas stay isolated from Studio and normal pages.' );

echo "FL-14 immersive safe-areas contract: OK\n";
