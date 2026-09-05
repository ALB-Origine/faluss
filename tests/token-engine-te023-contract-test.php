<?php

function te023_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );
$admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );
$docs = file_get_contents( $root . '/docs/TOKEN_ENGINE.md' );

foreach ( array( 'rest_contract', "rest_url( 'token-engine/v1/' )", "'namespace' => 'token-engine/v1'", "'protocol_version' => self::PROTOCOL_VERSION", "'/connector/token'", "'/connector/diagnostic'", "'/connector/balance'", "'method' => 'POST'", "'method' => 'GET'" ) as $needle ) {
    te023_assert( false !== strpos( $access, $needle ), 'TE-02.3 requires one canonical WordPress REST contract: ' . $needle );
}
foreach ( array( 'rest_base_url', 'core_site_url', 'Token_Engine_Connector_Access::core_site_url()', 'protocol_version', "'engine' => 'token-engine'" ) as $needle ) {
    te023_assert( false !== strpos( $access . $admin, $needle ), 'TE-02.3 must expose a technical REST base, a copyable site URL and compatible protocol result: ' . $needle );
}
te023_assert( 5 === substr_count( $access, 'register_rest_route' ), 'TE-02.3 plus TE-03 must retain a bounded private connector route set.' );
te023_assert( 1 === substr_count( $access, '=> rest_url(' ), 'TE-02.3 must have one Core-owned WordPress REST base source.' );
foreach ( array( '/access-token', "'/diagnostic'", "'/balance'", '/credit', '/debit', 'write_transaction' ) as $forbidden ) {
    te023_assert( false === strpos( $access, $forbidden ), 'TE-02.3 must not retain a divergent or ledger-writing route: ' . $forbidden );
}
foreach ( array( 'rest_contract()', 'connector/token', 'connector/diagnostic', 'connector/balance', 'protocole' ) as $needle ) {
    te023_assert( false !== strpos( $docs, $needle ), 'TE-02.3 documentation must describe the same Core contract: ' . $needle );
}

echo "TE-02.3 canonical Core REST contract: OK\n";
