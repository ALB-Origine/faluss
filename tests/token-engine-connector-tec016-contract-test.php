<?php

function tec016_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );
$plugin = file_get_contents( $root . '/plugins/token-engine-connector/token-engine-connector.php' );

foreach ( array( 'public static function daily_reward_offer', 'daily_reward_token', "'reward_offer'", "'connector/reward/offer'", 'PERMISSION_REWARD_CLAIM', "'not_configured'", 'isset( $data[\'balance\'] )', 'route_request' ) as $needle ) {
    tec016_assert( false !== strpos( $service, $needle ), 'TEC-01.6 must request the safe Core-derived anonymous offer through its private facade: ' . $needle );
}
$offer_start = strpos( $service, 'public static function daily_reward_offer' );
$claim_start = strpos( $service, 'public static function claim_daily_reward_for_current_subject' );
$offer = false !== $offer_start && false !== $claim_start ? substr( $service, $offer_start, $claim_start - $offer_start ) : '';
tec016_assert( false === strpos( $offer, 'current_subject_id()' ) && false === strpos( $offer, "'subject_id'" ), 'TEC-01.6 anonymous offer must not resolve or transport a subject.' );
foreach ( array( 'reward.claim', 'absente : la réclamation publique reste indisponible', 'Permission reward.claim absente' ) as $needle ) {
    tec016_assert( false !== strpos( $admin, $needle ), 'TEC-01.6 diagnostics must explicitly identify an absent reward.claim permission: ' . $needle );
}
tec016_assert( false === strpos( $plugin, 'register_rest_route' ) && false === strpos( $service, 'CREATE TABLE' ) && false === strpos( $service, 'INSERT INTO token_engine_ledger' ), 'TEC-01.6 must keep no browser route, local table or local ledger.' );
tec016_assert( false === strpos( $service, "'faluss_id' =>" ) && false === strpos( $service, '$data[\'client_secret\']' ), 'TEC-01.6 must not expose a Faluss ID or secret in reward data.' );

echo "TEC-01.6 public offer and permission diagnostic contract: OK\n";
