<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'FALUSS_PORTAL_URL', 'https://faluss.com/wp-content/plugins/faluss-portal/' );
define( 'TOKEN_ENGINE_VERSION', '0.4.1' );

function ap02a1_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? (string) $value : ''; }
function wp_parse_url( $value ) { return parse_url( (string) $value ); }
function admin_url( $path = '' ) { return 'https://faluss.com/wp-admin/' . ltrim( $path, '/' ); }
function wp_create_nonce( $action ) { return hash( 'sha256', $action ); }

final class Token_Engine_Schema { const VERSION = '5'; }
final class Token_Engine_Points_Service {
    public static function daily_status( $faluss_id, $owner, $reward_key, $proof ) {
        unset( $faluss_id, $proof );
        return array(
            'state' => 'claimable', 'owner' => $owner, 'reward_key' => $reward_key,
            'amount_pf' => 20, 'economic_class' => 'earned', 'category' => 'daily_accrual',
            'logical_date' => '2026-09-12', 'entry_uuid' => null,
        );
    }
}
final class Faluss_Identity_Client {
    public static function member_app_projection( $faluss_id, $app_key ) {
        unset( $faluss_id );
        return 'me' === $app_key
            ? array( 'contract_version' => '1', 'publication_status' => 'published', 'canonical_url' => 'https://faluss.me/mon-faluss' )
            : null;
    }
}

$root = dirname( __DIR__ );
$plugin = $root . '/plugins/faluss-portal';
$portal = file_get_contents( $plugin . '/includes/class-faluss-portal.php' );
$bootstrap = file_get_contents( $plugin . '/faluss-portal.php' );
$css = file_get_contents( $plugin . '/assets/css/faluss-portal.css' );
$javascript = file_get_contents( $plugin . '/assets/js/faluss-portal.js' );

require_once $plugin . '/includes/class-faluss-portal.php';

$faluss_id = '11111111-1111-4111-8111-111111111111';
$panel_method = new ReflectionMethod( 'Faluss_Portal', 'apps_panel' );
$panel_method->setAccessible( true );
$render_daily = new ReflectionMethod( 'Faluss_Portal', 'render_hub_daily_action' );
$render_daily->setAccessible( true );

ob_start();
$panel_method->invoke( null, 'my-apps', $faluss_id );
$owned = ob_get_clean();
ob_start();
$panel_method->invoke( null, 'explore', $faluss_id );
$explore = ob_get_clean();

ap02a1_assert( 1 === substr_count( $portal, 'private static function render_app_card' ) && false !== strpos( $portal, "self::render_app_card( \$app, 'compact' )" ) && false !== strpos( $portal, "self::render_app_card( \$app, 'explore' )" ), 'Mes Apps and Explorer must retain one shared card renderer.' );
ap02a1_assert( 2 === substr_count( $owned, 'faluss-portal__app-open' ), 'Each owned Mes Apps card must expose exactly one common glass primitive.' );

preg_match( '/<article[^>]*data-faluss-app="hub".*?<\\/article>/s', $owned, $hub_card );
preg_match( '/<article[^>]*data-faluss-app="me".*?<\\/article>/s', $owned, $me_card );
ap02a1_assert( ! empty( $hub_card[0] ) && false !== strpos( $hub_card[0], 'assets/images/pf/faluss-pf-badge.png' ) && false !== strpos( $hub_card[0], '>20</span>' ), 'Claimable Hub must place only the supplied PF badge and 20 in the common visual primitive.' );
ap02a1_assert( false !== strpos( $hub_card[0], '<button class="faluss-portal__hub-daily-submit" type="submit" data-faluss-portal-hub-daily-submit aria-label="Gain quotidien : 20 Points Faluss"></button><span class="faluss-portal__app-open" aria-hidden="true">' ), 'Hub claimable must keep a transparent semantic submit overlay ahead of the common glass primitive.' );
ap02a1_assert( false === strpos( $hub_card[0], 'faluss-portal__hub-daily-button' ) && false === strpos( $hub_card[0], 'faluss-portal__hub-daily-claimed' ), 'Hub must not render a separately styled PF button or claimed surface.' );
ap02a1_assert( ! empty( $me_card[0] ) && false !== strpos( $me_card[0], 'faluss-portal__app-open' ) && false === strpos( $me_card[0], 'hub-daily' ) && false === strpos( $me_card[0], 'PF' ), 'Faluss Me must retain the same navigation primitive without a reward surface.' );

$submit_rule = preg_match( '/\\.faluss-portal__hub-daily-submit\\s*\\{([^}]*)\\}/s', $css, $submit_match ) ? $submit_match[1] : '';
ap02a1_assert( false !== strpos( $submit_rule, 'all: unset' ) && false !== strpos( $submit_rule, 'position: absolute' ) && false !== strpos( $submit_rule, 'inset: 0' ), 'The Hub submit control must be a transparent overlay, not a visual component.' );
foreach ( array( 'border:', 'border-radius:', 'background:', 'box-shadow:', 'color:', 'width:', 'height:', 'min-width:', 'min-height:', 'transition:', 'animation:', 'transform:', 'outline:' ) as $forbidden ) {
    ap02a1_assert( false === strpos( $submit_rule, $forbidden ), 'The Hub submit overlay must not own visual geometry or color: ' . $forbidden );
}
ap02a1_assert( false === strpos( $css . $javascript . $portal, 'faluss-portal__hub-daily-button' ) && false === strpos( $css . $javascript . $portal, 'faluss-portal__hub-daily-claimed' ), 'The former divergent Hub visual classes must be absent.' );
ap02a1_assert( 1 === preg_match_all( '/(?:^|\\R)\\.faluss-portal__app-open\\s*\\{/m', $css ) && 1 === preg_match( '/(?:^|\\R)\\.faluss-portal__app-open\\s*\\{[^}]*width:\\s*var\\(--faluss-app-open-size\\);[^}]*height:\\s*var\\(--faluss-app-open-size\\);[^}]*border-radius:\\s*50%;[^}]*background:[^}]*backdrop-filter:\\s*blur\\(14px\\)/s', $css ), 'The circular glass geometry, border, glass and blur must be owned once by the common primitive.' );
ap02a1_assert( false !== strpos( $css, '.faluss-portal__hub-daily-form:focus-within .faluss-portal__app-open') && false !== strpos( $css, '.faluss-portal__hub-daily-submit:focus:not(:focus-visible) + .faluss-portal__app-open') && false === strpos( $css, 'hub-daily-submit:focus-visible'), 'Keyboard focus must be circular, translucent and suppressed after pointer focus.' );
ap02a1_assert( false === strpos( $submit_rule, '#FF') && false === strpos( $submit_rule, '#ff'), 'The semantic submit overlay must not introduce a pink focus or accent treatment.' );

preg_match( '/<article[^>]*data-faluss-app="me".*?<\\/article>/s', $explore, $me_explore_card );
preg_match( '/<span class="faluss-portal__app-logo"[^>]*><img src="([^"]+)"/', $me_card[0] ?? '', $me_owned_logo );
preg_match( '/<span class="faluss-portal__app-logo"[^>]*><img src="([^"]+)"/', $me_explore_card[0] ?? '', $me_explore_logo );
ap02a1_assert( ! empty( $me_owned_logo[1] ) && $me_owned_logo[1] === ( $me_explore_logo[1] ?? '' ) && false !== strpos( $me_owned_logo[1], 'assets/images/apps/faluss-me.png' ), 'Mes Apps and Explorer must render the identical Faluss Me asset.' );
$me_logo_rule = preg_match( '/\\.faluss-portal__app-card\\[data-faluss-app="me"\\]\\s+\\.faluss-portal__app-logo img\\s*\\{([^}]*)\\}/s', $css, $me_logo_match ) ? $me_logo_match[1] : '';
ap02a1_assert( false !== strpos( $me_logo_rule, 'transform: scale(2.61)') && false !== strpos( $me_logo_rule, 'filter: invert(1)') && false !== strpos( $me_logo_rule, 'mix-blend-mode: screen') && false === strpos( $me_logo_rule, 'background'), 'The one shared Faluss Me treatment must preserve its transparent blend and never paint a rectangle.' );
ap02a1_assert( 0 === preg_match( '/app-card--compact[^\\n{]*data-faluss-app="me"[^\\n{]*app-logo|app-card--compact[^\\n{]*app-logo[^\\n{]*data-faluss-app="me"/s', $css ), 'No compact-only Faluss Me logo treatment may diverge from Explorer.' );
ap02a1_assert( 'a25533ca502e4e6286cb58c858de8d7a4de5d25b18a5d91894b31c46ff1f1955' === hash_file( 'sha256', $plugin . '/assets/images/pf/faluss-pf-badge.png' ), 'The official PF badge must remain byte-for-byte unchanged.' );

ob_start();
$render_daily->invoke( null, array( 'status' => 'claimed' ) );
$claimed = ob_get_clean();
ap02a1_assert( false === strpos( $claimed, '<form' ) && false === strpos( $claimed, '<button' ) && false !== strpos( $claimed, 'faluss-portal__app-open' ) && false !== strpos( $claimed, '>20</span>' ), 'The claimed state must retain the common visual primitive without a second claim control.' );
foreach ( array( 'method="post"', 'faluss_portal_hub_daily_nonce', 'data-faluss-portal-hub-daily-reward', 'window.fetch(form.action', 'method: \'POST\'' ) as $needle ) {
    ap02a1_assert( false !== strpos( $portal . $javascript . ( $hub_card[0] ?? '' ), $needle ), 'The fixed POST and nonce Hub claim contract must remain unchanged: ' . $needle );
}
foreach ( array( 'amount_pf', 'economic_class', 'logical_date', 'reward_key', 'idempotency' ) as $field ) {
    ap02a1_assert( false === strpos( $hub_card[0], $field ), 'No economic decision field may be supplied by the Hub browser form: ' . $field );
}
ap02a1_assert( false !== strpos( $bootstrap, "FALUSS_PORTAL_VERSION', '0.1.18'" ) && false === strpos( $portal, 'token_engine_pf_ledger' ) && false === strpos( $portal, 'dbDelta' ) && false === strpos( $portal, 'CREATE TABLE' ), 'AP-02A.1 must only version Portal and must not add a ledger, migration or table.' );

echo "AP-02A.1 Mes Apps visual primitive contract: OK\n";
