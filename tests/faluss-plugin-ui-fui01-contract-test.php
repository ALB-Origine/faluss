<?php

function fui01_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$contract = file_get_contents( $root . '/docs/FALUSS_PLUGIN_UI.md' );
$engine_admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );
$engine_css = file_get_contents( $root . '/plugins/token-engine/assets/css/token-engine-admin.css' );
$connector_admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );
$connector_css = file_get_contents( $root . '/plugins/token-engine-connector/assets/css/token-engine-connector-admin.css' );

foreach ( array( 'Faluss', 'by Alternative LAB', '#FFFDF5', '#FFFFFF', '#080808', '#6F6A63', '#FF3D16', 'rgba(8,8,8,.12)', '0 12px 30px rgba(8,8,8,.06)', 'Outfit', 'sidebar', 'server-rendered', 'aria-current', 'sidebar native WordPress' ) as $needle ) { fui01_assert( false !== strpos( $contract, $needle ), 'FUI-01 contract invariant is missing: ' . $needle ); }
foreach ( array( 'token-engine-admin__sidebar', 'token-engine-admin__workspace', 'Faluss', 'by Alternative LAB', 'Configuration', 'Projets', 'Règles', 'Ledger', 'Ajustement manuel', 'wp_enqueue_style' ) as $needle ) { fui01_assert( false !== strpos( $engine_admin, $needle ), 'Token Engine must apply the FUI-01 shell and five panels: ' . $needle ); }
foreach ( array( '.token-engine-admin', '.token-engine-connector-admin' ) as $scope ) { fui01_assert( false !== strpos( $engine_css . $connector_css, $scope ), 'FUI-01 styles must be component-scoped: ' . $scope ); }
foreach ( array( ':root', 'body {' ) as $forbidden ) { fui01_assert( false === strpos( $engine_css . $connector_css, $forbidden ), 'FUI-01 styles must not alter global administration CSS: ' . $forbidden ); }
fui01_assert( false !== strpos( $connector_admin, 'token-engine-connector-admin__sidebar' ) && false !== strpos( $connector_admin, 'by Alternative LAB' ), 'The Connector must embed its own FUI-01-compatible admin shell.' );

echo "FUI-01 Faluss plugin admin UI contract: OK\n";
