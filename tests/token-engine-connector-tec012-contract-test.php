<?php

function tec012_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$crypto = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-crypto.php' );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$subject = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-subject.php' );
$admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );

foreach ( array( 'is_available', 'hash_hkdf', 'wp_salt', 'sodium_crypto_secretbox', 'aes-256-gcm', 'tecv2:sodium:', 'tecv2:openssl:', 'legacy_key' ) as $needle ) {
    tec012_assert( false !== strpos( $crypto, $needle ), 'TEC-01.2 requires protected, backward-readable secret storage: ' . $needle );
}
foreach ( array( 'Token_Engine_Connector_Crypto::encrypt', 'Token_Engine_Connector_Crypto::decrypt', 'hash_equals( $submitted_secret, $verified_secret )', 'connector_secret_persistence_failed', 'update_option( self::OPTION, $current, false )', "'saved' !== self::secret_state( \$current )" ) as $needle ) {
    tec012_assert( false !== strpos( $service, $needle ), 'TEC-01.2 must verify a saved secret and preserve an existing secret on an empty resave: ' . $needle );
}
foreach ( array( "'secret_state' => \$secret_state", "'saved' === \$secret_state", "return 'required'", 'connector_secret_protection_unavailable', 'connector_secret_required' ) as $needle ) {
    tec012_assert( false !== strpos( $service . $admin, $needle ), 'TEC-01.2 requires an explicit protected-secret state: ' . $needle );
}
tec012_assert( false !== strpos( $admin, 'Secret enregistré.') && false !== strpos( $admin, 'Secret requis.' ), 'Connector administration must render only the saved/required secret state.' );
tec012_assert( false === strpos( $admin, "echo esc_attr( \$settings['secret_protected']" ) && false === strpos( $admin, 'wp_remote_retrieve_body' ), 'Connector administration and diagnostics must not render a secret or remote body.' );
foreach ( array( 'Faluss_Identity_Registry', 'get_active_for_wp_user', 'Faluss_Identity_Schema', 'get_table_names', 'WHERE wp_user_id = %d AND status = %s', "'active'", 'did_action( \'plugins_loaded\' )', 'token_engine_connector_subject_id' ) as $needle ) {
    tec012_assert( false !== strpos( $subject, $needle ), 'TEC-01.2 must resolve an active Faluss profile via the real API/schema before the generic filter: ' . $needle );
}
foreach ( array( 'get_or_create_for_wp_user', 'activate_for_wp_user', 'INSERT ', 'UPDATE ', 'DELETE ', '$subject = $user->ID' ) as $forbidden ) {
    tec012_assert( false === strpos( $subject, $forbidden ), 'TEC-01.2 subject resolution must stay read-only and must not use a WordPress user ID as the subject: ' . $forbidden );
}
foreach ( array( 'active_identity_profile', 'subject_available', 'subject_fingerprint', "hash( 'sha256'" ) as $needle ) {
    tec012_assert( false !== strpos( $subject . $admin, $needle ), 'TEC-01.2 must diagnose an active profile without exposing a Faluss ID: ' . $needle );
}

echo "TEC-01.2 protected secret and Faluss subject contract: OK\n";
