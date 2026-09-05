<?php

function te031_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-service.php' );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );

$offer_start = strpos( $service, 'public static function daily_reward_offer' );
$claim_start = strpos( $service, 'public static function claim_daily_reward' );
$offer = false !== $offer_start && false !== $claim_start ? substr( $service, $offer_start, $claim_start - $offer_start ) : '';
foreach ( array( 'daily_reward_offer', 'daily_reward_context', "'state' => 'available'", "'amount'", "'unit'" ) as $needle ) {
    te031_assert( false !== strpos( $service, $needle ), 'TE-03.1 must derive the anonymous offer only from the configured Core rule: ' . $needle );
}
te031_assert( false === strpos( $offer, 'subject_id' ) && false === strpos( $offer, 'write_transaction' ) && false === strpos( $offer, 'daily_reward_state' ), 'TE-03.1 offer must neither resolve a subject nor write/read a subject ledger state.' );
foreach ( array( "'reward_offer' => array( 'path' => '/connector/reward/offer'", 'daily_reward_offer_response', 'daily_reward_offer_payload', 'reward_claim_permission', 'register_rest_route' ) as $needle ) {
    te031_assert( false !== strpos( $access, $needle ), 'TE-03.1 must expose the offer through the bounded private Core contract: ' . $needle );
}
te031_assert( 9 === substr_count( $access, 'register_rest_route' ), 'EC-02 retains the daily reward routes alongside the private entitlement routes.' );
te031_assert( false === strpos( $access, "'faluss_id' =>" ) && false === strpos( $access, '$result[\'access_token\']' ), 'TE-03.1 Core responses must not disclose a Faluss ID or access token.' );

echo "TE-03.1 Core public-offer and atomic claim contract: OK\n";
