<?php

function te021_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );
$admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );
$script = file_get_contents( $root . '/plugins/token-engine/assets/js/token-engine-admin.js' );

foreach ( array( "rest_url( 'token-engine/v1/' )", 'rest_base_url', 'token-engine-core-url-', 'data-copy-target', 'token-engine-copy', 'wallet.read accordée' ) as $needle ) {
    te021_assert( false !== strpos( $access . $admin, $needle ), 'TE-02.1 must expose the exact copyable Core REST URL and permission state: ' . $needle );
}
te021_assert( false !== strpos( $script, 'navigator.clipboard' ) && false !== strpos( $script, 'document.execCommand' ), 'TE-02.1 must provide a functional non-sensitive copy control.' );
foreach ( array( 'update_project_permissions', 'token_engine_update_project_permissions', 'normalise_permissions_allow_empty', 'connector_permissions' ) as $needle ) {
    te021_assert( false !== strpos( $access . $admin, $needle ), 'TE-02.1 must support explicit wallet.read permission management: ' . $needle );
}
te021_assert( false !== strpos( $access, '$first_credentials ? array( self::PERMISSION_WALLET_READ ) : self::stored_permissions' ), 'Only first credentials may default to wallet.read.' );
te021_assert( false !== strpos( $access, 'has_credential_record' ) && false !== strpos( $access, 'stored_permissions( $project[\'connector_permissions\'] ?? \'\' )' ), 'Secret regeneration must retain a revoked or historical permission state.' );
foreach ( array( 'connector_permission_wallet_read_missing', 'connector_client_rejected', 'connector_secret_rejected', 'connector_token_rejected', 'connector_project_inactive', 'diagnostic_id' ) as $needle ) {
    te021_assert( false !== strpos( $access, $needle ), 'TE-02.1 requires a precise, correlatable Core refusal: ' . $needle );
}
te021_assert( false === strpos( $access, "'connector_permissions' => wp_json_encode( array( self::PERMISSION_WALLET_READ ) )" ), 'Regeneration must not overwrite a revoked permission.' );
te021_assert( 3 === substr_count( $access, 'register_rest_route' ), 'TE-02.1 must keep exactly the three read-only Core routes.' );
foreach ( array( '/credit', '/debit', 'write_transaction', 'register_rest_field' ) as $forbidden ) {
    te021_assert( false === strpos( $access, $forbidden ), 'TE-02.1 must not add remote ledger mutation: ' . $forbidden );
}
te021_assert( false !== strpos( $admin, 'without modifying the permissions' ) || false !== strpos( $admin, 'sans modifier les permissions' ), 'The one-time secret response must explain that rotation preserves permissions.' );

echo "TE-02.1 Core connector diagnostic contract: OK\n";
