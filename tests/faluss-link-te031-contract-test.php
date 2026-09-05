<?php

function flte031_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-reward.css' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-reward.js' );

foreach ( array( 'Token_Engine_Connector_Service::daily_reward_offer()', 'Token_Engine_Connector_Service::daily_reward_status_for_current_subject()', 'Token_Engine_Connector_Service::claim_daily_reward_for_current_subject()', 'daily_reward_error_payload', 'connector_permission_reward_claim_missing', 'connector_subject_unavailable', 'not_configured', 'check_ajax_referer', 'wp_send_json_success', 'wp_send_json_error' ) as $needle ) {
    flte031_assert( false !== strpos( $link, $needle ), 'TE-03.1 Link must carry the complete protected reward path: ' . $needle );
}
foreach ( array( 'Réclamer mes %1$s %2$s', 'et débloquer le teaser gratuitement', 'data-faluss-reward-ajax-url', 'data-faluss-reward-nonce', 'faluss-link-reward__feedback' ) as $needle ) {
    flte031_assert( false !== strpos( $link, $needle ), 'TE-03.1 must render an explicit anonymous invitation and safe claim feedback: ' . $needle );
}
foreach ( array( 'login_label', 'login_microcopy', 'get_script_depends', "'faluss-link-reward'" ) as $needle ) {
    flte031_assert( false !== strpos( $widgets, $needle ), 'TE-03.1 Elementor must expose the anonymous label, microcopy and reward script: ' . $needle );
}
foreach ( array( 'requestFailure', 'button.disabled = true', 'renderTerminalState', 'updateBalance', 'Gain attribué', "credentials: 'same-origin'", 'elementorFrontend.hooks.addAction', 'falussLinkRewardElementorBound', 'falussRewardReady' ) as $needle ) {
    flte031_assert( false !== strpos( $script, $needle ), 'TE-03.1 browser interaction must bind idempotently and never fail silently: ' . $needle );
}
foreach ( array( 'faluss-link-reward__action', 'faluss-link-reward__feedback', '--faluss-action', '--faluss-action-hover', '--faluss-action-active', 'prefers-reduced-motion' ) as $needle ) {
    flte031_assert( false !== strpos( $css, $needle ), 'TE-03.1 reward feedback must remain scoped and consume Faluss tokens: ' . $needle );
}
flte031_assert( false === strpos( $link, 'wp_ajax_nopriv_faluss_link_daily_reward_claim' ) && false === strpos( $link, "'subject_id' =>" ) && false === strpos( $link, 'token_engine_ledger' ), 'TE-03.1 Link must not accept anonymous claims, subject input or own a ledger.' );
flte031_assert( false === strpos( $script, 'faluss_id' ) && false === strpos( $script, 'client_secret' ), 'TE-03.1 browser code must not receive a Faluss ID or Connector secret.' );

echo "Faluss Link TE-03.1 public claim interaction contract: OK\n";
