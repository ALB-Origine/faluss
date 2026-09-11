<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'FALUSS_PORTAL_URL', 'https://faluss.com/wp-content/plugins/faluss-portal/' );
define( 'TOKEN_ENGINE_VERSION', '0.4.1' );

function dr02a_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_parse_url( $value ) { return parse_url( (string) $value ); }
function admin_url( $path = '' ) { return 'https://faluss.com/wp-admin/' . ltrim( $path, '/' ); }
function wp_create_nonce( $action ) { return 'nonce-for-' . $action; }

final class Token_Engine_Schema {
    const VERSION = '5';
}

final class Token_Engine_Points_Service {
    public static $status_calls = 0;
    public static $claim_calls = 0;
    public static $next_status = array();
    public static $next_claim = array();

    public static function daily_status( $faluss_id, $owner, $reward_key, $proof ) {
        self::$status_calls++;
        return self::$next_status;
    }

    public static function claim_hub_daily( $faluss_id, $proof ) {
        self::$claim_calls++;
        return self::$next_claim;
    }
}

$root = dirname( __DIR__ );
$plugin = $root . '/plugins/faluss-portal';
$portal = file_get_contents( $plugin . '/includes/class-faluss-portal.php' );
$javascript = file_get_contents( $plugin . '/assets/js/faluss-portal.js' );
$css = file_get_contents( $plugin . '/assets/css/faluss-portal.css' );
$bootstrap = file_get_contents( $plugin . '/faluss-portal.php' );
$token_bootstrap = file_get_contents( $root . '/plugins/token-engine/token-engine.php' );
$token_schema = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-schema.php' );
$badge = $plugin . '/assets/images/pf/faluss-pf-badge.png';

foreach ( array( "FALUSS_PORTAL_VERSION', '0.1.16'", "TOKEN_ENGINE_VERSION', '0.4.1'", "const VERSION = '5'" ) as $needle ) {
    dr02a_assert( false !== strpos( $bootstrap . $token_bootstrap . $token_schema, $needle ), 'DR-02A must consume Portal 0.1.16 with Token Engine 0.4.1 schema 5: ' . $needle );
}
dr02a_assert( false === strpos( $portal, 'token_engine_ledger' ) && false === strpos( $portal, 'token_engine_pf_ledger' ) && false === strpos( $portal, 'dbDelta' ) && false === strpos( $portal, 'CREATE TABLE' ), 'Portal must not alter, query or create either Token Engine ledger/table.' );
foreach ( array( 'register_rest_route', 'wp_ajax_nopriv_', 'wp_schedule', 'wp_cron', 'token-engine-connector', 'Token_Engine_Connector' ) as $forbidden ) {
    dr02a_assert( false === strpos( $portal, $forbidden ), 'DR-02A must add no REST, anonymous AJAX, cron or Connector surface: ' . $forbidden );
}
dr02a_assert( false !== strpos( $portal, "add_action( 'wp_ajax_' . self::HUB_DAILY_ACTION" ) && false !== strpos( $portal, "Token_Engine_Points_Service::daily_status" ) && false !== strpos( $portal, "Token_Engine_Points_Service::claim_hub_daily" ), 'Portal must use only the authenticated Hub adapter and Core daily primitives.' );
foreach ( array( 'Token_Engine_Points_Service::balances_by_class', 'Token_Engine_Points_Service::claim_me_profile_daily', 'Token_Engine_Points_Service::write_entry', 'Token_Engine_Schema::pf_ledger_table' ) as $forbidden ) {
    dr02a_assert( false === strpos( $portal, $forbidden ), 'Portal must not read a balance, invoke Me, or write a PF ledger: ' . $forbidden );
}

require_once $plugin . '/includes/class-faluss-portal.php';

$read = new ReflectionMethod( 'Faluss_Portal', 'hub_daily_read_model' );
$read->setAccessible( true );
$claim = new ReflectionMethod( 'Faluss_Portal', 'hub_daily_claim' );
$claim->setAccessible( true );
$render = new ReflectionMethod( 'Faluss_Portal', 'render_hub_daily_action' );
$render->setAccessible( true );
$faluss_id = '11111111-1111-4111-8111-111111111111';
$core_decision = array(
    'state'          => 'claimable',
    'owner'          => 'faluss-hub',
    'reward_key'     => 'hub.daily_accrual',
    'amount_pf'      => 20,
    'economic_class' => 'earned',
    'category'       => 'daily_accrual',
    'logical_date'   => '2026-09-12',
);
Token_Engine_Points_Service::$next_status = $core_decision;
$document = $read->invoke( null, $faluss_id );
dr02a_assert( 'claimable' === $document['status'] && 'hub' === $document['app_key'] && 'faluss-hub' === $document['owner'] && 'hub.daily_accrual' === $document['reward_key'], 'Hub claimable document must preserve the fixed owner and reward key returned by Core.' );
dr02a_assert( 20 === $document['reward']['amount_pf'] && 'earned' === $document['reward']['economic_class'] && 'daily' === $document['period']['type'] && 'Europe/Paris' === $document['period']['timezone'] && '2026-09-12' === $document['period']['logical_date'], 'Hub claimable document must expose only the canonical 20 PF earned daily decision in Europe/Paris.' );
dr02a_assert( 'owner_claim' === $document['delegation']['type'] && 'claim-hub-daily' === $document['delegation']['action_key'] && 'hub.daily_accrual' === $document['delegation']['target'], 'Only a claimable Hub document may delegate the fixed owner claim.' );
dr02a_assert( false === strpos( json_encode( $document ), $faluss_id ), 'The read-model must never expose the technical Faluss ID.' );

$calls_before_invalid = Token_Engine_Points_Service::$status_calls;
$invalid_document = $read->invoke( null, 'not-a-faluss-id' );
dr02a_assert( 'ineligible' === $invalid_document['status'] && $calls_before_invalid === Token_Engine_Points_Service::$status_calls, 'An invalid or absent canonical subject must be ineligible without invoking Core.' );

Token_Engine_Points_Service::$next_claim = $core_decision + array( 'claimed_now' => true );
Token_Engine_Points_Service::$next_claim['state'] = 'claimed';
$claimed = $claim->invoke( null, $faluss_id );
dr02a_assert( 'claimed' === $claimed['status'] && 'none' === $claimed['delegation']['type'] && null === $claimed['delegation']['action_key'] && null === $claimed['delegation']['target'] && 1 === Token_Engine_Points_Service::$claim_calls, 'A committed Core claim must produce claimed with no second mutable delegation.' );

foreach ( array( 'ineligible', 'unavailable', 'not_supported' ) as $state ) {
    Token_Engine_Points_Service::$next_status = array( 'state' => $state );
    $negative = $read->invoke( null, $faluss_id );
    dr02a_assert( array( 'status' => $state ) === $negative, 'A non-claimable Core state must stay minimal and must not fabricate a reward document: ' . $state );
    ob_start();
    $render->invoke( null, $negative );
    $negative_markup = ob_get_clean();
    dr02a_assert( false === strpos( $negative_markup, '<button' ) && false === strpos( $negative_markup, '<form' ), 'A non-claimable state must not render a false claim action: ' . $state );
}

ob_start();
$render->invoke( null, $document );
$claimable_markup = ob_get_clean();
dr02a_assert( false !== strpos( $claimable_markup, 'method="post"' ) && false !== strpos( $claimable_markup, 'faluss_portal_claim_hub_daily' ) && false !== strpos( $claimable_markup, 'Récupérer +20 PF' ), 'The Hub card must render its single explicit POST claim action.' );
foreach ( array( $faluss_id, 'amount_pf', 'economic_class', 'logical_date', 'reward_key', 'idempotency', 'date=', 'owner=' ) as $forbidden ) {
    dr02a_assert( false === strpos( $claimable_markup, $forbidden ), 'The browser claim markup must carry no subject or economic parameter: ' . $forbidden );
}
ob_start();
$render->invoke( null, $claimed );
$claimed_markup = ob_get_clean();
dr02a_assert( false === strpos( $claimed_markup, '<form' ) && false !== strpos( $claimed_markup, 'Récupéré aujourd’hui' ), 'The claimed Hub card must replace its action without offering a second claim.' );

foreach ( array( '$_GET', 'faluss_id', 'amount_pf', 'economic_class', 'logical_date', 'reward_key', 'idempotency' ) as $forbidden ) {
    dr02a_assert( false === strpos( $javascript, $forbidden ), 'The browser code must not send or hold a PF business parameter: ' . $forbidden );
}
dr02a_assert( false !== strpos( $javascript, "window.fetch(form.action" ) && false !== strpos( $javascript, "method: 'POST'" ) && false !== strpos( $javascript, 'new FormData(form)' ) && false !== strpos( $javascript, "credentials: 'same-origin'") && false !== strpos( $javascript, "cache: 'no-store'") && false !== strpos( $javascript, "dailyReward.status !== 'claimed'") && false !== strpos( $javascript, 'button.disabled = true' ) && false !== strpos( $javascript, 'action.replaceChildren(claimed)' ), 'The Hub action must be nonce-form POST only, disable double-clicks and render claimed only after a real Core result.' );
dr02a_assert( false === strpos( $javascript, 'setInterval' ) && false === strpos( $javascript, 'location.reload' ) && false === strpos( $javascript, 'window.location.assign' ), 'Daily reward UI must not poll, reload or navigate to perform a claim.' );

$handler = '';
preg_match( '/public static function claim_hub_daily\(\).*?\/\*\* @return array<string,mixed> \*\//s', $portal, $handler_match );
$handler = $handler_match[0] ?? '';
dr02a_assert( false !== strpos( $handler, "'POST'") && false !== strpos( $handler, 'wp_verify_nonce' ) && false !== strpos( $handler, 'is_user_logged_in' ) && false !== strpos( $handler, 'self::current_member()' ) && false !== strpos( $handler, 'self::hub_daily_claim' ) && false === strpos( $handler, '$_GET' ), 'The authenticated handler must check POST, nonce and current active member before the fixed Core claim.' );
dr02a_assert( false !== strpos( $portal, "Cache-Control: private, no-store, max-age=0, must-revalidate" ) && false !== strpos( $portal, 'wp_send_json_success' ), 'The claim response must be a no-store minimal read-model response.' );

dr02a_assert( is_file( $badge ) && 'a25533ca502e4e6286cb58c858de8d7a4de5d25b18a5d91894b31c46ff1f1955' === hash_file( 'sha256', $badge ), 'The supplied official PF badge must be embedded unchanged.' );
dr02a_assert( false !== strpos( $portal, 'assets/images/pf/faluss-pf-badge.png' ) && false !== strpos( $css, 'faluss-portal__hub-daily-badge' ) && false !== strpos( $portal, 'faluss-portal__app-card-access' ), 'Only the Hub daily-action zone may render the supplied PF badge while preserving the normal card access.' );

echo "DR-02A Faluss Hub daily reward contract: OK\n";
