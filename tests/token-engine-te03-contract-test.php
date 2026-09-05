<?php

function te03_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-service.php' );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );
$admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );

foreach ( array( "DAILY_REWARD_RULE_KEY = 'daily_reward'", 'daily_reward_status', 'claim_daily_reward', '$rule[\'scope\']', '$rule[\'trigger_type\']', '$rule[\'periodicity\']', 'reference_timezone', 'window_start_utc', 'next_available_at' ) as $needle ) {
    te03_assert( false !== strpos( $service, $needle ), 'TE-03 must keep an active global daily rule and calculate its Core-timezone window: ' . $needle );
}
foreach ( array( 'GET_LOCK', 'token_engine_reward_', 'RELEASE_LOCK', "'idempotency_key' => 'reward.daily.'", 'write_transaction', "'direction' => 'credit'", "'emitter_project'" ) as $needle ) {
    te03_assert( false !== strpos( $service, $needle ), 'TE-03 must serialize cross-surface claims and append only one idempotent Core ledger credit: ' . $needle );
}
te03_assert( false === strpos( $service, "'amount' => 100" ) && false === strpos( $service, "'unit' => 'ALB'" ) && false === stripos( $service, 'woocommerce' ), 'TE-03 Core must not hardcode an amount, unit or commerce dependency.' );
foreach ( array( "PERMISSION_REWARD_CLAIM = 'reward.claim'", "'reward_status' => array( 'path' => '/connector/reward/status'", "'reward_claim' => array( 'path' => '/connector/reward/claim'", 'daily_reward_status_response', 'daily_reward_claim_response', 'daily_reward_payload', 'connector_permission_reward_claim_missing' ) as $needle ) {
    te03_assert( false !== strpos( $access, $needle ), 'TE-03 must expose separately authorized status and claim routes: ' . $needle );
}
foreach ( array( 'reward.claim', 'wallet.read', 'Enregistrer les permissions' ) as $needle ) {
    te03_assert( false !== strpos( $admin, $needle ), 'TE-03 Core administration must make the reward permission explicit without secret rotation: ' . $needle );
}
te03_assert( false === strpos( $access, "'faluss_id' =>" ) && false === strpos( $access, '$result[\'access_token\']' ), 'TE-03 reward responses must not disclose a subject or token.' );

echo "TE-03 Core daily reward contract: OK\n";
