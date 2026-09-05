<?php

function tec01_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/plugins/token-engine-connector/token-engine-connector.php' );
$crypto = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-crypto.php' );
$subject = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-subject.php' );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );
$css = file_get_contents( $root . '/plugins/token-engine-connector/assets/css/token-engine-connector-admin.css' );
$all = $plugin . $crypto . $subject . $service . $admin;

foreach ( array( 'Token Engine Connector', 'Token_Engine_Connector_Admin::boot', 'class-token-engine-connector-service.php', 'class-token-engine-connector-subject.php' ) as $needle ) { tec01_assert( false !== strpos( $plugin, $needle ), 'TEC-01 bootstrap invariant is missing: ' . $needle ); }
foreach ( array( 'sodium_crypto_secretbox', 'aes-256-gcm', 'wp_salt', 'secret_protected' ) as $needle ) { tec01_assert( false !== strpos( $crypto . $service, $needle ), 'TEC-01 must protect the local connector secret: ' . $needle ); }
foreach ( array( 'https', 'wp_parse_url', 'client_id', 'project_key', 'wp_safe_remote_post', 'wp_safe_remote_get', "'redirection' => 0", 'Authorization', 'Bearer ' ) as $needle ) { tec01_assert( false !== strpos( $service, $needle ), 'TEC-01 private remote-read invariant is missing: ' . $needle ); }
tec01_assert( false === strpos( $admin, "\$settings['secret_protected']" ) && false !== strpos( $admin, "\$settings['secret_configured']" ), 'TEC-01 must indicate a configured secret without rendering it again.' );
foreach ( array( 'token_engine_connector_subject_id', 'Faluss_Identity_Registry', 'get_active_for_wp_user', 'apply_filters' ) as $needle ) { tec01_assert( false !== strpos( $subject, $needle ), 'TEC-01 optional Identity/generic subject invariant is missing: ' . $needle ); }
foreach ( array( 'get_or_create_for_wp_user', 'activate_for_wp_user', '$subject = $user->ID', 'CREATE TABLE', 'wpdb->insert', 'write_transaction', 'add_shortcode', 'register_rest_route', 'credit', 'debit' ) as $forbidden ) { tec01_assert( false === strpos( $all, $forbidden ), 'TEC-01 must not create identity, a local economy, frontend surface or ledger write: ' . $forbidden ); }
tec01_assert( false !== strpos( $css, '.token-engine-connector-admin' ), 'TEC-01 must namespace its administration styles.' );
foreach ( array( ':root', 'body {' ) as $forbidden ) { tec01_assert( false === strpos( $css, $forbidden ), 'TEC-01 administration styles must remain scoped: ' . $forbidden ); }
tec01_assert( false !== strpos( $admin, 'manage_options' ) && false !== strpos( $admin, 'wp_nonce_field' ) && false !== strpos( $admin, 'wp_safe_redirect' ) && false !== strpos( $admin, 'Tester la connexion' ), 'TEC-01 administration must remain capability- and nonce-protected with a safe diagnostic.' );

echo "TEC-01 Token Engine Connector contract: OK\n";
