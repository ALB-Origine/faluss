<?php

function te022_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );

foreach ( array( 'PERMISSION_WALLET_READ', 'project_connection_status', 'connector_permission_wallet_read_missing', 'connector_secret_version', 'diagnostic_id' ) as $needle ) {
    te022_assert( false !== strpos( $access, $needle ), 'TE-02.2 Core continuity invariant is missing: ' . $needle );
}
foreach ( array( 'Faluss_Identity', 'get_or_create_for_wp_user', 'write_transaction', '/credit', '/debit' ) as $forbidden ) {
    te022_assert( false === strpos( $access, $forbidden ), 'TE-02.2 must not couple the Core to Identity or remote ledger writes: ' . $forbidden );
}

echo "TE-02.2 Core continuity contract: OK\n";
