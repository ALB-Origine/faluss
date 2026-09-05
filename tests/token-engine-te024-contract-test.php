<?php

function te024_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );
$admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );
$docs = file_get_contents( $root . '/docs/TOKEN_ENGINE.md' );

foreach ( array( 'core_site_url', "home_url( '/' )", "'site_url' => self::core_site_url()", "'base_url' => rest_url( 'token-engine/v1/' )" ) as $needle ) {
    te024_assert( false !== strpos( $access, $needle ), 'TE-02.4 must keep the canonical site URL separate from the WordPress-derived REST base: ' . $needle );
}
foreach ( array( 'URL du site Core', 'Token_Engine_Connector_Access::core_site_url()', 'forme REST est détectée automatiquement' ) as $needle ) {
    te024_assert( false !== strpos( $admin, $needle ), 'TE-02.4 Core administration must copy only the site URL: ' . $needle );
}
te024_assert( false === strpos( $admin, 'URL REST exacte du Core' ) && false === strpos( $admin, 'Token_Engine_Connector_Access::rest_base_url()' ), 'TE-02.4 must not expose a REST base as Connector configuration.' );
foreach ( array( 'URL du site Core', 'index.php?rest_route=', 'n’est jamais une valeur de configuration Connector' ) as $needle ) {
    te024_assert( false !== strpos( $docs, $needle ), 'TE-02.4 documentation must distinguish the site URL from the REST mechanism: ' . $needle );
}

echo "TE-02.4 Core site URL contract: OK\n";
