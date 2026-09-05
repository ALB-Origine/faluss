<?php

function tec015_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$plugin = file_get_contents( $root . '/plugins/token-engine-connector/token-engine-connector.php' );

foreach ( array( 'daily_reward_status_for_current_subject', 'claim_daily_reward_for_current_subject', 'daily_reward_request', 'current_subject_id()', "PERMISSION_REWARD_CLAIM = 'reward.claim'", "'reward_status' => 'connector/reward/status'", "'reward_claim' => 'connector/reward/claim'", '$subject' ) as $needle ) {
    tec015_assert( false !== strpos( $service, $needle ), 'TEC-01.5 must send status and claim only for the active server-resolved subject: ' . $needle );
}
foreach ( array( 'connector_permission_reward_claim_missing', 'connector_subject_unavailable', 'wp_safe_remote_post', 'access_token', 'REST_MODE_REWRITE', 'REST_MODE_QUERY' ) as $needle ) {
    tec015_assert( false !== strpos( $service, $needle ), 'TEC-01.5 must fail closed through the private Core route: ' . $needle );
}
tec015_assert( false === strpos( $plugin, 'register_rest_route' ) && false === strpos( $service, 'CREATE TABLE' ) && false === strpos( $service, 'INSERT INTO token_engine_ledger' ), 'TEC-01.5 must not add a browser endpoint, local ledger or local reward rule.' );
tec015_assert( false === strpos( $service, "'faluss_id' =>" ) && false === strpos( $service, '$data[\'client_secret\']' ), 'TEC-01.5 must not expose a Faluss ID or secret in its returned reward data.' );

echo "TEC-01.5 private daily reward contract: OK\n";
