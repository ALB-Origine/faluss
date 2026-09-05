<?php

function te02_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$schema = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-schema.php' );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );
$admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );
$plugin = file_get_contents( $root . '/plugins/token-engine/token-engine.php' );

foreach ( array( 'connector_client_id', 'connector_secret_hash', 'connector_secret_version', 'connector_permissions', 'connector_client_id_unique', 'token_engine_connector_tokens', 'token_hash_unique', 'token_project_expires', 'migrate_v1_to_v2', "const VERSION = '2'", "const LEGACY_VERSION = '1'", 'ALTER TABLE' ) as $needle ) {
    te02_assert( false !== strpos( $schema, $needle ), 'TE-02 requires the additive connector schema invariant: ' . $needle );
}
foreach ( array( 'wp_hash_password', 'wp_check_password', 'random_bytes', 'TOKEN_TTL_SECONDS = 300', 'secret_version', 'hash_equals', "'wallet.read'", 'connector_project_inactive', 'is_ssl()', 'nocache_headers', 'Bearer ', 'token-engine/v1', '/connector/token', '/connector/diagnostic', '/connector/balance' ) as $needle ) {
    te02_assert( false !== strpos( $access, $needle ), 'TE-02 connector access invariant is missing: ' . $needle );
}
te02_assert( 3 === substr_count( $access, 'register_rest_route' ), 'TE-02 exposes exactly the three private, versioned connector routes.' );
foreach ( array( '__return_true', 'write_transaction', '/credit', '/debit', '/rules', '/adjust' ) as $forbidden ) {
    te02_assert( false === strpos( $access, $forbidden ), 'TE-02 must not expose a public or remote ledger-writing route: ' . $forbidden );
}
te02_assert( false !== strpos( $access, 'project_has_credentials' ) && false !== strpos( $access, 'empty( $project[\'active\'] )' ) && false !== strpos( $access, 'valid_permissions' ), 'Inactive or uncredentialed projects must be refused.' );
te02_assert( false !== strpos( $access, 'connector_secret_version' ) && false !== strpos( $access, "\$entry['secret_version']" ), 'Secret regeneration must invalidate previously issued access tokens.' );
te02_assert( false !== strpos( $admin, 'token_engine_generate_project_credentials' ) && false !== strpos( $admin, 'token-engine-one-time-secret' ) && false !== strpos( $admin, 'Seule une empreinte vérifiable est conservée' ), 'The project admin must reveal a generated secret only in its one-time confirmation response.' );
te02_assert( false === strpos( $admin, 'set_transient( self::adjustment_transient_key(), $result' ), 'The generated project secret must never be persisted by the admin response.' );
te02_assert( false !== strpos( $plugin, 'Token_Engine_Connector_Access::boot' ), 'The Core must register the private connector access layer.' );

echo "TE-02 Token Engine connector access contract: OK\n";
