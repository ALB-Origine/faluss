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
final class Faluss_Identity_Client_Apps_Registry_Adapter {
    public static function canonical_destination( $faluss_id ) {
        unset( $faluss_id );
        return 'https://faluss.me/mon-faluss';
    }
}

function ap02a1_registry_document() {
    return array( 'applications' => array(
        array(
            'app_key' => 'faluss-hub', 'availability' => 'available', 'member_relationship' => 'active',
            'capabilities' => array( array(
                'capability_key' => 'faluss-hub.daily-reward', 'state' => 'enabled',
                'specialized_read_model' => array( 'status' => 'available' ),
                'active_bindings' => array( array( 'slot' => 'portal.apps.card_action', 'interface' => 'delegated_action', 'binding_state' => 'active' ) ),
                'allowed_actions' => array( array( 'action_key' => 'faluss-hub.daily-reward.claim', 'owner' => 'faluss-hub', 'delegation' => array( 'type' => 'owner_delegated_action', 'target' => 'faluss-hub.daily-reward.claim' ) ) ),
            ) ),
        ),
        array( 'app_key' => 'faluss-me', 'availability' => 'available', 'member_relationship' => 'active', 'capabilities' => array() ),
    ) );
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
$panel_method->invoke( null, 'my-apps', $faluss_id, ap02a1_registry_document() );
$owned = ob_get_clean();
ob_start();
$panel_method->invoke( null, 'explore', $faluss_id, ap02a1_registry_document() );
$explore = ob_get_clean();

ap02a1_assert( 1 === substr_count( $portal, 'private static function render_app_card' ) && false !== strpos( $portal, "self::render_app_card( \$app, 'compact' )" ) && false !== strpos( $portal, "self::render_app_card( \$app, 'explore' )" ), 'Mes Apps and Explorer must retain one shared card renderer.' );
ap02a1_assert( 2 === preg_match_all( '/class="[^"]*faluss-portal__app-open(?:\s|")/', $owned ), 'Each owned Mes Apps card must expose exactly one common glass primitive.' );

preg_match( '/<article[^>]*data-faluss-app="hub".*?<\\/article>/s', $owned, $hub_card );
preg_match( '/<article[^>]*data-faluss-app="me".*?<\\/article>/s', $owned, $me_card );
ap02a1_assert( ! empty( $hub_card[0] ) && false !== strpos( $hub_card[0], 'assets/images/pf/faluss-pf-badge.png' ) && false !== strpos( $hub_card[0], '>20</span>' ), 'Claimable Hub must place only the supplied PF badge and 20 in the common visual primitive.' );
ap02a1_assert( 1 === preg_match( '/<button class="faluss-portal__app-open faluss-portal__app-open--reward"[^>]*data-faluss-portal-hub-daily-submit[^>]*>.*?data-faluss-portal-pf-badge.*?<span[^>]*>20<\/span><\/button>/s', $hub_card[0] ), 'The visible reward pill itself must be the submit button containing the PF badge and amount.' );
ap02a1_assert( false === strpos( $hub_card[0], 'faluss-portal__hub-daily-submit' ) && 0 === preg_match( '/<button[^>]*>\s*<\/button>/', $hub_card[0] ), 'Hub claimable must contain no empty button or transparent interactive overlay.' );
ap02a1_assert( false === strpos( $hub_card[0], 'faluss-portal__hub-daily-button' ) && false === strpos( $hub_card[0], 'faluss-portal__hub-daily-claimed' ), 'Hub must not render a separately styled PF button or claimed surface.' );
ap02a1_assert( ! empty( $me_card[0] ) && false !== strpos( $me_card[0], 'faluss-portal__app-open' ) && false === strpos( $me_card[0], 'hub-daily' ) && false === strpos( $me_card[0], 'PF' ), 'Faluss Me must retain the same navigation primitive without a reward surface.' );

$form_rule = preg_match( '/\\.faluss-portal__hub-daily-form\\s*\\{([^}]*)\\}/s', $css, $form_match ) ? $form_match[1] : '';
$head_rule = preg_match( '/\\.faluss-portal__app-card--compact \\.faluss-portal__app-head\\s*\\{([^}]*)\\}/s', $css, $head_match ) ? $head_match[1] : '';
$action_rule = preg_match( '/\\.faluss-portal__hub-daily-action\\s*\\{([^}]*)\\}/s', $css, $action_match ) ? $action_match[1] : '';
$unavailable_rule = preg_match( '/\\.faluss-portal__hub-daily-action\\[data-faluss-portal-hub-daily-state="unavailable"\\]\\s*\\{([^}]*)\\}/s', $css, $unavailable_match ) ? $unavailable_match[1] : '';
$reward_rule = preg_match( '/\\.faluss-portal \\.faluss-portal__app-open--reward,\\s*\\.elementor.*?\\{([^}]*)\\}/s', $css, $reward_match ) ? $reward_match[1] : '';
$button_rule = preg_match( '/\\.faluss-portal button\\.faluss-portal__app-open--reward,\\s*\\.elementor.*?\\{([^}]*)\\}/s', $css, $button_match ) ? $button_match[1] : '';
ap02a1_assert( false === strpos( $form_rule . $button_rule, 'position: absolute' ) && false === strpos( $form_rule . $button_rule, 'inset: 0' ) && false === strpos( $css, '.faluss-portal__hub-daily-submit' ), 'The claim control must have no absolute full-card overlay.' );
ap02a1_assert( false !== strpos( $head_rule, 'z-index: auto' ) && false !== strpos( $head_rule, 'pointer-events: auto' ), 'The compact header must not create a WebKit hit-test barrier above the card link.' );
foreach ( array( 'position: relative', 'z-index: 3', 'pointer-events: auto' ) as $needle ) {
    ap02a1_assert( false !== strpos( $action_rule, $needle ), 'The daily action must own its explicit hit-test layer: ' . $needle );
}
ap02a1_assert( false !== strpos( $unavailable_rule, 'pointer-events: none' ), 'An unavailable daily action must leave the normal Hub card link reachable.' );
foreach ( array( 'position: relative', 'z-index: 4', 'pointer-events: auto' ) as $needle ) {
    ap02a1_assert( false !== strpos( $form_rule, $needle ), 'The daily form must own its explicit hit-test layer: ' . $needle );
}
foreach ( array( 'width: max-content', 'height: var(--faluss-app-open-size)', 'min-width: clamp(96px, 24vw, 112px)', 'gap: 8px', 'border-radius: 999px', 'white-space: nowrap' ) as $needle ) {
    ap02a1_assert( false !== strpos( $reward_rule, $needle ), 'The reward modifier must own compact responsive pill geometry: ' . $needle );
}
foreach ( array( 'appearance: none', 'position: relative', 'z-index: 5', 'margin: 0', 'border: 0 !important', 'outline: 0 !important', 'cursor: pointer', 'pointer-events: auto !important', 'touch-action: manipulation' ) as $needle ) {
    ap02a1_assert( false !== strpos( $button_rule, $needle ), 'The visible submit pill must neutralize Elementor button chrome: ' . $needle );
}
ap02a1_assert( false === strpos( $css . $javascript . $portal, 'faluss-portal__hub-daily-button' ) && false === strpos( $css . $javascript . $portal, 'faluss-portal__hub-daily-claimed' ), 'The former divergent Hub visual classes must be absent.' );
ap02a1_assert( 1 === preg_match_all( '/(?:^|\\R)\\.faluss-portal__app-open\\s*\\{/m', $css ) && 1 === preg_match( '/(?:^|\\R)\\.faluss-portal__app-open\\s*\\{[^}]*width:\\s*var\\(--faluss-app-open-size\\);[^}]*height:\\s*var\\(--faluss-app-open-size\\);[^}]*border-radius:\\s*50%;[^}]*background:[^}]*backdrop-filter:\\s*blur\\(14px\\)/s', $css ), 'The circular glass geometry, border, glass and blur must be owned once by the common primitive.' );
ap02a1_assert( false !== strpos( $css, 'button.faluss-portal__app-open--reward:focus-visible' ) && false !== strpos( $css, '0 0 0 2px rgba(255,255,255,.78)' ) && false === stripos( $button_rule, 'pink' ), 'Keyboard focus must use only a white pill-shaped ring with no pink treatment.' );

preg_match( '/<article[^>]*data-faluss-app="me".*?<\\/article>/s', $explore, $me_explore_card );
preg_match( '/<span class="faluss-portal__app-logo"[^>]*><img src="([^"]+)"/', $me_card[0] ?? '', $me_owned_logo );
preg_match( '/<span class="faluss-portal__app-logo"[^>]*><img src="([^"]+)"/', $me_explore_card[0] ?? '', $me_explore_logo );
ap02a1_assert( ! empty( $me_owned_logo[1] ) && $me_owned_logo[1] === ( $me_explore_logo[1] ?? '' ) && false !== strpos( $me_owned_logo[1], 'assets/images/apps/faluss-me.png' ), 'Mes Apps and Explorer must render the identical Faluss Me asset.' );
$me_logo_rule = preg_match( '/\\.faluss-portal__app-card\\[data-faluss-app="me"\\]\\s+\\.faluss-portal__app-logo img\\s*\\{([^}]*)\\}/s', $css, $me_logo_match ) ? $me_logo_match[1] : '';
ap02a1_assert( false !== strpos( $me_logo_rule, 'object-fit: contain') && false === strpos( $me_logo_rule, 'scale(2.61)') && false === strpos( $me_logo_rule, 'filter:') && false === strpos( $me_logo_rule, 'mix-blend-mode:') && false === strpos( $me_logo_rule, 'background'), 'The shared transparent Faluss Me treatment must need no scale, filter, blend mode or painted rectangle.' );
ap02a1_assert( 0 === preg_match( '/app-card--compact[^\\n{]*data-faluss-app="me"[^\\n{]*app-logo|app-card--compact[^\\n{]*app-logo[^\\n{]*data-faluss-app="me"/s', $css ), 'No compact-only Faluss Me logo treatment may diverge from Explorer.' );
ap02a1_assert( 'a25533ca502e4e6286cb58c858de8d7a4de5d25b18a5d91894b31c46ff1f1955' === hash_file( 'sha256', $plugin . '/assets/images/pf/faluss-pf-badge.png' ), 'The official PF badge must remain byte-for-byte unchanged.' );
ap02a1_assert( '1541ef775c32d229c11ec79a579ef9d371cf8f23a77c0c4b1920fda5bdb6d64a' === hash_file( 'sha256', $plugin . '/assets/images/apps/faluss-me.png' ), 'The official Faluss Me logo must remain byte-for-byte unchanged.' );

ob_start();
$render_daily->invoke( null, array( 'status' => 'claimed' ) );
$claimed = ob_get_clean();
ap02a1_assert( false === strpos( $claimed, '<form' ) && false === strpos( $claimed, '<button' ) && false !== strpos( $claimed, 'faluss-portal__app-open--reward' ) && false !== strpos( $claimed, '>20</span>' ), 'The claimed state must retain the identical reward pill without a second claim control.' );
foreach ( array( 'method="post"', 'faluss_portal_hub_daily_nonce', 'data-faluss-portal-hub-daily-reward', "window.fetch(form.getAttribute('action')", 'method: \'POST\'' ) as $needle ) {
    ap02a1_assert( false !== strpos( $portal . $javascript . ( $hub_card[0] ?? '' ), $needle ), 'The fixed POST and nonce Hub claim contract must remain unchanged: ' . $needle );
}
foreach ( array( 'amount_pf', 'economic_class', 'logical_date', 'reward_key', 'idempotency' ) as $field ) {
    ap02a1_assert( false === strpos( $hub_card[0], $field ), 'No economic decision field may be supplied by the Hub browser form: ' . $field );
}
ap02a1_assert( false !== strpos( $bootstrap, "FALUSS_PORTAL_VERSION', '0.1.22'" ) && false === strpos( $portal, 'token_engine_pf_ledger' ) && false === strpos( $portal, 'dbDelta' ) && false === strpos( $portal, 'CREATE TABLE' ), 'DR-02A.1 must remain present without adding a ledger, migration or table.' );

echo "AP-02A.2 Mes Apps visual primitive contract: OK\n";
