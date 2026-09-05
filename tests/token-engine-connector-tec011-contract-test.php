<?php

function tec011_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$subject = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-subject.php' );
$admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );

foreach ( array( 'core_site_url', 'normalise_site_url', 'REST_MODE_REWRITE', 'REST_MODE_QUERY', "add_query_arg( 'rest_route'", 'route_request', "'redirection' => 0", 'connector_core_redirect_rejected', 'connector_route_missing' ) as $needle ) {
    tec011_assert( false !== strpos( $service, $needle ), 'TEC-01.1 must accept a canonical HTTPS Core site URL and derive either REST route form safely: ' . $needle );
}
foreach ( array( 'core_connection_test', 'connector_core_url_invalid', 'connector_core_inaccessible', 'connector_project_inactive', 'connector_client_rejected', 'connector_secret_rejected', 'connector_permission_wallet_read_missing', 'connector_token_rejected', 'diagnostic_id' ) as $needle ) {
    tec011_assert( false !== strpos( $service . $admin, $needle ), 'TEC-01.1 must preserve the named Core diagnostic state: ' . $needle );
}
$core_start = strpos( $service, 'public static function core_connection_test()' );
$core_end = strpos( $service, 'public static function test_connection()', $core_start );
tec011_assert( false !== $core_start && false !== $core_end && false === strpos( substr( $service, $core_start, $core_end - $core_start ), 'current_subject' ), 'The Core connection test must not depend on a Faluss subject, session or balance.' );
foreach ( array( 'faluss_subject_diagnostic', 'token_engine_connector_test_subject', 'active_identity_profile', 'subject_fingerprint', "hash( 'sha256'", 'Diagnostic Sujet Faluss' ) as $needle ) {
    tec011_assert( false !== strpos( $service . $subject . $admin, $needle ), 'TEC-01.1 must keep a separate non-reversible Faluss subject diagnostic: ' . $needle );
}
tec011_assert( false === strpos( $admin, 'wp_remote_retrieve_body' ) && false === strpos( $admin, 'Authorization' ), 'Connector administration must not render a remote response body or authorization header.' );
tec011_assert( false === strpos( $admin, '$settings[\'secret_protected\']' ) && false !== strpos( $admin, '$settings[\'secret_configured\']' ), 'Connector administration must never render the stored secret.' );
foreach ( array( 'register_rest_route', 'add_shortcode', 'write_transaction', 'CREATE TABLE', '/credit', '/debit' ) as $forbidden ) {
    tec011_assert( false === strpos( $service . $subject . $admin, $forbidden ), 'TEC-01.1 must remain read-only and local-schema-free: ' . $forbidden );
}

echo "TEC-01.1 connector diagnostics contract: OK\n";
