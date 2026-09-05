<?php

function flte03_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-reward.css' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-reward.js' );

foreach ( array( "add_shortcode( 'faluss_link_daily_reward'", "add_action( 'wp_ajax_faluss_link_daily_reward_claim'", 'render_daily_reward', 'claim_daily_reward', 'check_ajax_referer', 'Token_Engine_Connector_Service::daily_reward_status_for_current_subject()', 'Token_Engine_Connector_Service::claim_daily_reward_for_current_subject()', 'daily_reward_login_url', "home_url( '/login/' )", "'redirect_to'" ) as $needle ) {
    flte03_assert( false !== strpos( $link, $needle ), 'TE-03 Link must render a protected, local-return reward flow: ' . $needle );
}
flte03_assert( false === strpos( $link, 'wp_ajax_nopriv_faluss_link_daily_reward_claim' ) && false === strpos( $link, "'subject_id' =>" ) && false === strpos( $link, 'token_engine_ledger' ), 'TE-03 Link must not expose subject input, anonymous claims or a local ledger.' );
foreach ( array( 'Faluss_Link_Daily_Reward_Widget', 'Récompense quotidienne Faluss', 'get_style_depends', 'faluss-link-reward', 'show_balance', 'hide_unavailable', 'presentation' ) as $needle ) {
    flte03_assert( false !== strpos( $widgets, $needle ), 'TE-03 must provide one configurable Elementor reward widget: ' . $needle );
}
foreach ( array( '--faluss-action', '--faluss-action-hover', '--faluss-action-active', 'Outfit', 'prefers-reduced-motion', '.faluss-link-reward' ) as $needle ) {
    flte03_assert( false !== strpos( $css, $needle ), 'TE-03 reward styles must stay scoped and consume Faluss tokens: ' . $needle );
}
foreach ( array( 'fetch', 'credentials: \'same-origin\'', 'button.disabled = true', 'falussRewardReady', 'replaceChildren', 'faluss-link-reward__balance', 'toLocaleString' ) as $needle ) {
    flte03_assert( false !== strpos( $script, $needle ), 'TE-03 must use an idempotent local AJAX interaction without a full reload: ' . $needle );
}
flte03_assert( false === strpos( $script, 'faluss_id' ) && false === strpos( $script, 'client_secret' ), 'TE-03 browser code must not receive a Faluss ID or connector secret.' );

echo "Faluss Link TE-03 reward contract: OK\n";
