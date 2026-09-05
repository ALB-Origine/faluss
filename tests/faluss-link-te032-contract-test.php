<?php

function flte032_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-reward.js' );

foreach ( array( "'available'", "'granted'", "'already_claimed'", "'rule_unavailable'", "'permission_denied'", "'subject_unavailable'", "'configuration_invalid'", "'transient_error'", 'daily_reward_public_message', 'wp_send_json_success( self::daily_reward_error_payload' ) as $needle ) {
    flte032_assert( false !== strpos( $link, $needle ), 'TE-03.2 Link must render a clear, bounded reward outcome: ' . $needle );
}
foreach ( array( "state === 'granted'", "state === 'already_claimed'", 'button.disabled = true', 'actionNode.replaceChildren()', 'updateBalance', "'granted', 'already_claimed'", 'permission_denied', 'transient_error' ) as $needle ) {
    flte032_assert( false !== strpos( $script, $needle ), 'TE-03.2 browser state handling is missing: ' . $needle );
}
flte032_assert( false === strpos( $link, 'wp_ajax_nopriv_faluss_link_daily_reward_claim' ) && false === strpos( $link, "'subject_id' =>" ) && false === strpos( $link, 'token_engine_ledger' ), 'TE-03.2 Link must not accept anonymous claims, subject input or own a ledger.' );
flte032_assert( false === strpos( $script, 'faluss_id' ) && false === strpos( $script, 'client_secret' ), 'TE-03.2 browser code must not receive a Faluss ID or Connector secret.' );

echo "Faluss Link TE-03.2 reward feedback contract: OK\n";
