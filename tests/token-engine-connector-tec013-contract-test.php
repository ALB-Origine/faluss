<?php

function tec013_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );
$docs = file_get_contents( $root . '/docs/TOKEN_ENGINE_CONNECTOR.md' );

foreach ( array( "const REST_SUFFIX = '/wp-json/token-engine/v1'", "'#(?:^|/)wp-json/token-engine/v1$#'", "'https'", 'isset( $parts[\'query\'] )', 'isset( $parts[\'fragment\'] )', 'isset( $parts[\'user\'] )', 'rtrim( $value, \'/\' ) . \'/\'', "'token' => 'connector/token'", "'diagnostic' => 'connector/diagnostic'", "'balance' => 'connector/balance'" ) as $needle ) {
    tec013_assert( false !== strpos( $service, $needle ), 'TEC-01.3 must accept only the complete canonical HTTPS REST base: ' . $needle );
}
tec013_assert( false === strpos( $service, 'return $value . $suffix' ) && false === strpos( $service, "'/wp-json' ) { return" ), 'TEC-01.3 must not reinterpret a REST root as an origin or append a second REST path.' );
foreach ( array( "const PROTOCOL_VERSION = '1'", "const CORE_ENGINE = 'token-engine'", 'connector_core_unidentified', 'connector_protocol_incompatible', 'successful_steps', 'connector/token', 'connector/diagnostic', "'redirection' => 0" ) as $needle ) {
    tec013_assert( false !== strpos( $service . $admin, $needle ), 'TEC-01.3 requires an exact protocol-compatible Core test: ' . $needle );
}
foreach ( array( 'URL REST', 'Route Core', 'Core Token Engine', 'Protocole', 'Credentials', 'Permission wallet.read', 'Jeton court', 'core_valid_key', 'subject_core_required' ) as $needle ) {
    tec013_assert( false !== strpos( $admin, $needle ), 'TEC-01.3 must expose distinct, non-sensitive diagnostic steps and gate the subject diagnostic: ' . $needle );
}
tec013_assert( false === strpos( $admin, 'wp_remote_retrieve_body' ) && false === strpos( $admin, '$settings[\'secret_protected\']' ), 'TEC-01.3 must not render a remote body or stored secret.' );
foreach ( array( 'register_rest_route', 'add_shortcode', 'write_transaction', '/credit', '/debit', 'Faluss_Identity' ) as $forbidden ) {
    tec013_assert( false === strpos( $service . $admin, $forbidden ), 'TEC-01.3 must preserve the read-only, subject-independent Core boundary: ' . $forbidden );
}
foreach ( array( 'connector/token', 'version de protocole', 'n’ajoute jamais un second chemin REST' ) as $needle ) {
    tec013_assert( false !== strpos( $docs, $needle ), 'TEC-01.3 documentation must match the strict Connector contract: ' . $needle );
}

echo "TEC-01.3 canonical Connector REST contract: OK\n";
