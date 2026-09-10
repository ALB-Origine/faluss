<?php

define( 'ABSPATH', __DIR__ . '/' );

function pf01_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_User {
    public $roles = array();
    public $capabilities = array();
    public $display_name = '';
    public $user_nicename = '';
    public function __construct( $roles = array(), $capabilities = array() ) {
        $this->roles = $roles;
        $this->capabilities = $capabilities;
    }
}
$pf01_is_admin = false;
$pf01_is_logged_in = false;
$pf01_current_user = new WP_User();
function is_admin() { global $pf01_is_admin; return $pf01_is_admin; }
function is_user_logged_in() { global $pf01_is_logged_in; return $pf01_is_logged_in; }
function wp_get_current_user() { global $pf01_current_user; return $pf01_current_user; }
function user_can( $user, $capability ) { return ! empty( $user->capabilities[ $capability ] ); }
function home_url( $path = '/' ) { return 'https://faluss.com' . $path; }

final class Faluss_Identity_Client {
    public static $last_button_attributes = array();
    public static function button( $attributes, $inline ) {
        self::$last_button_attributes = $attributes;
        return false === $inline ? '<button>Continuer avec Faluss</button>' : '';
    }
}

final class Faluss_Subscriptions_Resolver {
    public static function resolve_for_faluss_id( $faluss_id ) {
        if ( '11111111-1111-4111-8111-111111111111' === $faluss_id ) {
            return array(
                'level' => 'pro',
                'state' => 'trialing',
                'expires_at' => '2026-10-01 12:00:00',
                'effective_sources' => array( array( 'source' => 'subscription', 'reference' => 'local-subject-a' ) ),
            );
        }
        return array( 'level' => 'free', 'state' => 'free', 'expires_at' => null, 'effective_sources' => array() );
    }
}

final class Faluss_Subscriptions_Catalog {
    public static function plans() {
        return array(
            'free' => array( 'public_name' => 'Faluss Gratuit', 'periods' => array() ),
            'pro'  => array( 'public_name' => 'Faluss Max', 'periods' => array( 'monthly' => array( 'amount_cents' => 999 ), 'annual' => array( 'amount_cents' => 9900 ) ) ),
        );
    }
}

final class Faluss_Subscriptions_Repository {
    public static function subscriptions_for_faluss_id( $faluss_id ) {
        return '11111111-1111-4111-8111-111111111111' === $faluss_id
            ? array( array( 'subscription_uuid' => 'local-subject-a', 'billing_interval' => 'monthly', 'provider_customer_reference' => 'cus_never_exposed' ) )
            : array();
    }
    public static function customer_for_faluss_id( $faluss_id, $provider ) {
        return '11111111-1111-4111-8111-111111111111' === $faluss_id && 'stripe' === $provider ? array( 'provider_customer_reference' => 'cus_never_exposed' ) : null;
    }
}

final class Faluss_Subscriptions_Stripe_Config {
    public static function portal_configuration_id() { return 'bpc_test_safe'; }
}

$root = dirname( __DIR__ );
$plugin = $root . '/plugins/faluss-portal';
$bootstrap = file_get_contents( $plugin . '/faluss-portal.php' );
$source = file_get_contents( $plugin . '/includes/class-faluss-portal.php' );
$css = file_get_contents( $plugin . '/assets/css/faluss-portal.css' );
$javascript = file_get_contents( $plugin . '/assets/js/faluss-portal.js' );
$documentation = file_get_contents( $root . '/docs/FALUSS_PORTAL.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );

foreach ( array( 'Plugin Name: Faluss Portal', "FALUSS_PORTAL_VERSION', '0.1.6'", 'class-faluss-portal.php' ) as $needle ) {
    pf01_assert( false !== strpos( $bootstrap, $needle ), 'PF-01 requires an isolated versioned Faluss Portal plugin: ' . $needle );
}
foreach ( array( "add_shortcode( self::SHORTCODE", "[faluss_portal]", 'Faluss_Identity_Client_Schema::tables()', 'WHERE wp_user_id = %d', "array( 'subscriber' )", 'Faluss_Identity_Client::button' ) as $needle ) {
    pf01_assert( false !== strpos( $source . $documentation, $needle ), 'PF-01 must start from the linked local SSO session and retain its shortcode/access fallback: ' . $needle );
}
foreach ( array( "add_filter( 'option_faluss_identity_client_settings'", "home_url( '/mon-faluss/' )", "'redirect_url' => self::portal_base_url()" ) as $needle ) {
    pf01_assert( false !== strpos( $source, $needle ), 'PF-01B must make the exact local portal return available to Identity Client without a persisted widget setting: ' . $needle );
}
pf01_assert( false === strpos( $source, "'redirect_url' => self::portal_url(" ), 'The SSO access form must not send a query-bearing portal route that Identity Client rejects in favour of home.' );
foreach ( array( "add_filter( 'show_admin_bar'", 'filter_member_admin_bar', "user_can( \$user, 'manage_options' )", "user_can( \$user, 'edit_posts' )" ) as $needle ) {
    pf01_assert( false !== strpos( $source, $needle ), 'PF-01B must hide the front-office admin bar only for non-privileged members: ' . $needle );
}
pf01_assert( false === strpos( $source, '$_GET[\'faluss_id\']' ) && false === strpos( $source, '$_POST[\'faluss_id\']' ), 'A browser-supplied Faluss ID must never select portal data.' );
pf01_assert( false === strpos( $source, 'CREATE TABLE' ) && false === strpos( $source, 'INSERT INTO' ) && false === strpos( $source, 'update_user_meta' ), 'PF-01 must not introduce a portal table, write an identity link or persist a universal profile.' );
pf01_assert( false === strpos( $source, 'wp_ajax_' ) && false === strpos( $source, 'register_rest_route' ), 'The member portal must not expose an anonymous browser data or billing route.' );
pf01_assert( false === strpos( $source, 'Token_Engine_Service::balance' ) && false === strpos( $source, 'Token_Engine_Schema' ) && false === strpos( $source, 'points_snapshot' ), 'PF-01 must not read, rename or display a historical Token Engine balance as PF.' );

require_once $plugin . '/includes/class-faluss-portal.php';
$return_settings = Faluss_Portal::include_portal_return( array( 'return_urls' => array( 'https://faluss.com/' ) ) );
$return_settings = Faluss_Portal::include_portal_return( $return_settings );
pf01_assert( in_array( 'https://faluss.com/mon-faluss/', $return_settings['return_urls'], true ), 'The portal must append the exact local SSO destination at read time.' );
pf01_assert( 1 === substr_count( implode( '|', $return_settings['return_urls'] ), 'https://faluss.com/mon-faluss/' ), 'The runtime SSO destination allowlist must remain deduplicated.' );
$access_method = new ReflectionMethod( 'Faluss_Portal', 'access_gate' );
$access_method->setAccessible( true );
$access_html = $access_method->invoke( null );
pf01_assert( 'https://faluss.com/mon-faluss/' === Faluss_Identity_Client::$last_button_attributes['redirect_url'], 'The internal access form must request the clean portal URL, never home or a query-bearing route.' );
pf01_assert( false !== strpos( $access_html, 'data-faluss-portal="access"' ), 'The access state must retain the full portal root marker.' );

$pf01_is_logged_in = true;
$pf01_current_user = new WP_User( array( 'subscriber' ) );
pf01_assert( false === Faluss_Portal::filter_member_admin_bar( true ), 'A standard front-office member must not receive the WordPress admin bar.' );
$pf01_current_user = new WP_User( array( 'administrator' ), array( 'manage_options' => true ) );
pf01_assert( true === Faluss_Portal::filter_member_admin_bar( true ), 'An administrator must retain the WordPress admin bar.' );
$pf01_current_user = new WP_User( array( 'editor' ), array( 'edit_posts' => true ) );
pf01_assert( true === Faluss_Portal::filter_member_admin_bar( true ), 'An editorial or management account must retain the WordPress admin bar.' );
$pf01_is_admin = true;
$pf01_current_user = new WP_User( array( 'subscriber' ) );
pf01_assert( true === Faluss_Portal::filter_member_admin_bar( true ), 'The plugin must never hide the admin bar policy inside wp-admin.' );
$pf01_is_admin = false;
$snapshot_method = new ReflectionMethod( 'Faluss_Portal', 'subscription_snapshot' );
$snapshot_method->setAccessible( true );
$trialing = $snapshot_method->invoke( null, '11111111-1111-4111-8111-111111111111' );
$free = $snapshot_method->invoke( null, '22222222-2222-4222-8222-222222222222' );
pf01_assert( true === $trialing['available'] && 'Faluss Max' === $trialing['offer_name'] && 'trialing' === $trialing['state'] && 'monthly' === $trialing['billing_interval'], 'A current canonical trialing decision must be projected faithfully for its own subject.' );
pf01_assert( true === $trialing['portal_available'] && ! array_key_exists( 'faluss_id', $trialing ) && ! array_key_exists( 'provider_customer_reference', $trialing ) && ! array_key_exists( 'provider_subscription_reference', $trialing ), 'The member snapshot must omit its Faluss ID and every Stripe reference.' );
pf01_assert( 'Faluss Gratuit' === $free['offer_name'] && 'free' === $free['state'] && false === $free['portal_available'], 'A second Faluss ID must receive its own free decision and no Customer Portal access.' );

foreach ( array( 'Faluss_Subscriptions_Billing::create_portal', 'wp_verify_nonce', 'self::PORTAL_NONCE', 'self::stripe_url', "add_action( 'template_redirect', array( __CLASS__, 'intercept_customer_portal_return' ), -1 )" ) as $needle ) {
    pf01_assert( false !== strpos( $source, $needle ), 'The Customer Portal action must stay server-side, nonce-protected and return to the portal safely: ' . $needle );
}
pf01_assert( false !== strpos( $source, 'Aucune facture locale disponible' ) && false !== strpos( $source, 'Aucune donnée consolidée' ) && false !== strpos( $source, 'Aucun avantage non configuré n’est supposé' ), 'Billing and Analytics must use explicit empty states instead of fictitious data.' );
foreach ( array( 'data-faluss-portal-master', 'data-faluss-portal-master-tab', 'data-faluss-portal-drawer', 'Points Faluss bientôt disponibles', 'profil universel', 'data-faluss-portal-profile-unavailable' ) as $needle ) {
    pf01_assert( false !== strpos( $source, $needle ), 'Master Profile must use the agreed preparatory surface without a second identity model: ' . $needle );
}
foreach ( array( "'apps'         => array( 'my-apps', 'explore' )", "'my-apps'       => 'Mes apps'", 'data-faluss-portal-sidebar-item', 'data-faluss-portal-sidebar-toggle', 'faluss-portal__master-header', 'faluss-portal__master-header-spacer' ) as $needle ) {
    pf01_assert( false !== strpos( $source, $needle ), 'PF-01B must expose the exact contextual navigation, collapsible shell and symmetric Master Profile header: ' . $needle );
}
foreach ( array( "'home'         => array( 'view', 'activity', 'discover' )", "'analytics'    => array( 'view', 'performance', 'revenue', 'sources' )", "'subscription' => array( 'offer', 'compare' )", "'billing'      => array( 'history', 'payment' )", "'settings'     => array( 'general', 'notifications', 'preferences' )", "'help'         => array( 'help', 'contact' )", 'data-faluss-portal-tabs' ) as $needle ) {
    pf01_assert( false !== strpos( $source, $needle ), 'Each section must own its exact contextual tabs and render them for no-reload switching: ' . $needle );
}
pf01_assert( 3 === substr_count( $source, 'data-faluss-portal-sidebar-item' ) && false === strpos( $source, "self::icon( 'home' )" ), 'The Faluss wordmark must be the only Home sidebar control, followed by Apps and the five-item section loop.' );
pf01_assert( false === strpos( $source, "number_format_i18n( \$points" ) && false === strpos( $source, "\$points['balance']" ), 'The PF placeholder must never contain a numeric balance before an official PF ledger exists.' );
foreach ( array( '--fp-sidebar-indicator-y', '--fp-tab-indicator-x', 'backdrop-filter', ':focus-visible', 'prefers-reduced-motion', 'aspect-ratio: 1', 'position: fixed', 'grid-template-columns: 30px minmax(0, 1fr) 30px', 'margin-top: auto' ) as $needle ) {
    pf01_assert( false !== strpos( $css, $needle ), 'The shell must retain visual indicators, footer glass, local focus control and reduced-motion support: ' . $needle );
}
pf01_assert( false !== strpos( $css, 'outline: 0 !important') && false !== strpos( $css, '-webkit-tap-highlight-color: transparent !important') && false !== strpos( $css, '.faluss-portal.faluss-portal .faluss-portal__sidebar-chevron-cell:focus-within') && false !== strpos( $css, '.faluss-portal.faluss-portal .faluss-portal__sidebar-avatar-cell:focus-within') && false !== strpos( $css, '.faluss-portal.faluss-portal .faluss-portal__master-chevron-cell:focus-within') && false === strpos( $css, 'outline: 2px') && false === strpos( $css, 'outline: 3px'), 'PF-01E must override browser, Safari and Elementor focus/tap frames on controls and their cells inside the portal.' );
pf01_assert( 0 === preg_match( '/faluss-portal__nav-link(?:\\:hover|\\.is-active)\\s*\\{[^}]*background/s', $css ), 'Sidebar state must move only the indicator and outline, never recolour an icon bubble.' );
pf01_assert( false === strpos( $source, 'faluss-portal__member-card' ) && false === strpos( $css, '.faluss-portal__member-card' ), 'PF-01C must remove the lower member card from markup and every viewport.' );
pf01_assert( false !== strpos( $source, 'data-faluss-portal-profile-open' ) && false !== strpos( $source, 'faluss-portal__sidebar-avatar-trigger' ), 'The independent sidebar avatar trigger must remain the sole Master Profile entry point.' );
foreach ( array( 'width: 30px', 'height: 30px', 'border-radius: 100px', 'data-chevron-direction="left"', 'faluss-portal__chevron-path--right' ) as $needle ) {
    pf01_assert( false !== strpos( $source . $css, $needle ), 'The two independent chevron controls must retain the exact non-rotating 30 px geometry: ' . $needle );
}
pf01_assert( false === strpos( $css, 'rotate(' ), 'Neither shell chevron may rotate when the sidebar changes state.' );
foreach ( array( 'faluss-portal__sidebar-chevron-cell', 'faluss-portal__sidebar-avatar-cell', 'faluss-portal__master-chevron-cell', 'data-faluss-portal-control="sidebar-chevron"', 'data-faluss-portal-control="master-chevron"', 'data-faluss-portal-control="sidebar-avatar"' ) as $needle ) {
    pf01_assert( false !== strpos( $source . $css, $needle ), 'Each PF-01E control must be centered by its own named layout cell: ' . $needle );
}
pf01_assert( false === strpos( $source . $css, 'faluss-portal__chevron-button' ) && false === strpos( $source . $css, 'faluss-portal__member-compact' ), 'The former shared chevron and navigation-bubble avatar templates must be absent.' );
pf01_assert( 1 === preg_match( '/\.faluss-portal__sidebar-chevron-cell\s*\{[^}]*display:\s*grid;[^}]*width:\s*44px;[^}]*height:\s*44px;[^}]*place-items:\s*center;/s', $css ), 'The independent sidebar chevron cell must own the local placement geometry.' );
pf01_assert( 1 === preg_match( '/\.faluss-portal__sidebar-avatar-cell\s*\{[^}]*display:\s*grid;[^}]*margin-top:\s*auto;[^}]*place-items:\s*center;/s', $css ), 'The independent bottom sidebar cell must push and center the avatar.' );
pf01_assert( 1 === preg_match( '/\.faluss-portal__master-chevron-cell\s*\{[^}]*display:\s*grid;[^}]*width:\s*30px;[^}]*height:\s*30px;[^}]*place-items:\s*center;[^}]*transform:\s*translateX\(-10px\);/s', $css ), 'PF-01F must move only the centered Master Profile return cell 10px left.' );
pf01_assert( 1 === substr_count( $css, 'transform: translateX(-10px);' ), 'PF-01F must apply its exact 10px offset once and only to the Master Profile return cell.' );
foreach ( array( 'faluss-portal__sidebar-chevron-control', 'faluss-portal__master-chevron-control', 'faluss-portal__sidebar-avatar-trigger' ) as $control ) {
    $control_rule = preg_match( '/\.' . preg_quote( $control, '/' ) . '\s*\{([^}]*)\}/s', $css, $control_match ) ? $control_match[1] : '';
    pf01_assert( false !== strpos( $control_rule, 'display: grid' ) && false !== strpos( $control_rule, 'place-items: center' ), 'Each independent control must center its own visual: ' . $control );
    pf01_assert( false !== strpos( $control_rule, 'border: 0 !important' ) && false !== strpos( $control_rule, 'outline: 0 !important' ) && false !== strpos( $control_rule, 'box-shadow: none !important' ) && false !== strpos( $control_rule, '-webkit-tap-highlight-color: transparent' ), 'Each independent control must defeat every injected frame: ' . $control );
    pf01_assert( false === strpos( $control_rule, 'position:' ) && false === strpos( $control_rule, 'translate' ) && false === strpos( $control_rule, 'left:' ) && false === strpos( $control_rule, 'bottom:' ), 'A control visual must not be offset inside its own cell: ' . $control );
}
foreach ( array( 'faluss-portal__sidebar-chevron-control', 'faluss-portal__master-chevron-control' ) as $control ) {
    $control_rule = preg_match( '/\.' . preg_quote( $control, '/' ) . '\s*\{([^}]*)\}/s', $css, $control_match ) ? $control_match[1] : '';
    pf01_assert( false !== strpos( $control_rule, 'width: 30px' ) && false !== strpos( $control_rule, 'height: 30px' ) && false !== strpos( $control_rule, 'background: transparent !important' ), 'Each chevron must have a transparent exact 30px hit area: ' . $control );
}
pf01_assert( false !== strpos( $css, '.faluss-portal__sidebar-avatar-trigger .faluss-portal__avatar--sidebar { width: 44px; height: 44px; flex-basis: 44px; }' ) && false !== strpos( $css, '.faluss-portal__sidebar-avatar-trigger .faluss-portal__avatar--sidebar { width: 42px; height: 42px; flex-basis: 42px; }' ), 'The avatar must match the mobile navigation bubble at both responsive breakpoints.' );
pf01_assert( 1 === preg_match( '/\.faluss-portal\s*\{[^}]*height:\s*100svh;[^}]*overflow:\s*hidden;/s', $css ) && 1 === preg_match( '/\.faluss-portal__main\s*\{[^}]*height:\s*100svh;[^}]*overflow-x:\s*hidden;[^}]*overflow-y:\s*auto;/s', $css ) && 1 === preg_match( '/\.faluss-portal__sidebar\s*\{[^}]*position:\s*sticky;[^}]*height:\s*100svh;[^}]*overflow:\s*hidden;/s', $css ), 'The viewport shell must keep the sticky sidebar fixed while only the gray panel scrolls vertically.' );
pf01_assert( false !== strpos( $css, '.faluss-portal__sidebar-avatar-trigger .faluss-portal__avatar--sidebar {' ) && false !== strpos( $css, 'width: 48px;' ) && false !== strpos( $css, 'height: 48px;' ), 'The desktop avatar must match the 48px desktop navigation bubbles.' );
pf01_assert( 1 === preg_match( '/\.faluss-portal__profile-edit\s*\{[^}]*width:\s*100%;[^}]*border:\s*0;[^}]*border-radius:\s*999px;[^}]*background:\s*#000;[^}]*color:\s*#fff;/s', $css ), 'The Master Profile edit CTA must use the full-width black Faluss primary treatment.' );
$tabs_rule = preg_match( '/\.faluss-portal__tabs\s*\{([^}]*)\}/s', $css, $tabs_match ) ? $tabs_match[1] : '';
pf01_assert( false !== strpos( $tabs_rule, 'width: calc(100% - var(--fp-context-edge) - var(--fp-context-edge))') && false !== strpos( $tabs_rule, 'margin-inline: auto'), 'The contextual bar must fill the gray content panel with symmetric local margins.' );
pf01_assert( false === strpos( $tabs_rule, '100vw') && false === strpos( $tabs_rule, '50vw') && false === strpos( $tabs_rule, '--fp-sidebar') && false === strpos( $tabs_rule, 'translateX'), 'The contextual bar must never be positioned from the viewport or sidebar.' );
pf01_assert( false === strpos( $source, 'faluss-portal__header' ) && 1 === preg_match( '/<main class="faluss-portal__main">.*?<\?php foreach \( self::TABS as \$section => \$tabs \) : \?>\s*<nav class="faluss-portal__tabs/s', $source ), 'Every contextual bar must be rendered directly under the gray main content panel.' );
pf01_assert( false !== strpos( $css, 'padding: max(50px, calc(env(safe-area-inset-top) + 42px)) 0'), 'The mobile contextual bar must clear the locally centered sidebar toggle cell without changing horizontal geometry.' );
pf01_assert( 1 === preg_match( '/\.faluss-portal__tab\s*\{[^}]*flex:\s*1 1 0;[^}]*min-width:\s*0;/s', $css ), 'All contextual labels must receive equal segments independent of label length.' );
pf01_assert( false !== strpos( $css, 'font-size: clamp(17px, calc(2vw + 2px), 24px)' ) && false !== strpos( $css, 'font-size: 12.5px' ), 'PF-01E must add exactly 2px to contextual labels without changing their segment geometry.' );
pf01_assert( false !== strpos( $css, '.faluss-portal :where(button, a)') && 0 === preg_match( '/\.faluss-portal a\s*\{[^}]*font:\s*inherit/s', $css ), 'The portal reset must not outrank contextual tab typography.' );
pf01_assert( false === strpos( $css, '.faluss-portal__tabs[data-faluss-portal-tabs="analytics"]') && false === strpos( $css, '.faluss-portal__tabs[data-faluss-portal-tabs="settings"]'), 'No label-length-specific contextual geometry may compress a section.' );
foreach ( array( 'tabGroup.clientWidth', 'tabLinks.length', 'activeIndex * segmentWidth' ) as $needle ) {
    pf01_assert( false !== strpos( $javascript, $needle ), 'The black indicator must use only the local equal-segment bar geometry: ' . $needle );
}
pf01_assert( false === strpos( $css, '--fp-sidebar-current-width') && false === strpos( $css, '--fp-header-safe'), 'PF-01D must remove the old viewport/sidebar header compensation variables.' );
foreach ( array( 'faluss-portal__master-tab-indicator', 'data-active-index="1"', 'data-active-index="2"', 'translate3d(100%', 'translate3d(200%' ) as $needle ) {
    pf01_assert( false !== strpos( $source . $css, $needle ), 'Master Profile tabs must share one transform-driven black indicator: ' . $needle );
}
pf01_assert( 1 === substr_count( $source, 'faluss-portal__master-tab-indicator' ), 'Only one Master Profile tab indicator may be rendered.' );
pf01_assert( false === strpos( $css, '.faluss-portal__master-tabs button.is-active { background: #000' ), 'Master Profile tabs must not toggle three independent active backgrounds.' );
foreach ( array( "dialog.classList.add('is-open')", "dialog.classList.add('is-closing')", 'transitionend', '110dvh', 'cubic-bezier(.55,.05,.85,.35)', 'cubic-bezier(.15,.85,.35,1)' ) as $needle ) {
    pf01_assert( false !== strpos( $javascript . $css, $needle ), 'Master Profile must enter from below Slow-to-Fast and leave below Fast-to-Slow: ' . $needle );
}
pf01_assert( false === strpos( $javascript, '.focus(' ) && false === strpos( $css, '.faluss-portal__master.is-entering' ), 'Profile and drawer opening must not force focus or use the old fade state.' );
pf01_assert( 0 === preg_match( '/faluss-portal__master[^\n{]*\{[^}]*opacity/s', $css ), 'Master Profile motion must not rely on opacity fading.' );
foreach ( array( 'history.pushState', 'popstate', 'requestAnimationFrame', 'showModal', 'closeProfile', 'setMasterTab', 'falussPortalSidebarCollapsed', 'aria-expanded', 'localStorage.setItem' ) as $needle ) {
    pf01_assert( false !== strpos( $javascript, $needle ), 'Navigation and Master Profile must be progressive, animated and history-aware: ' . $needle );
}
pf01_assert( false === strpos( $javascript, 'fetch(' ) && false === strpos( $javascript, 'Stripe' ), 'The browser must not read Stripe or make a direct portal data call.' );
foreach ( array( 'sources de vérité', 'Master Profile', 'Point Faluss', 'faluss_pf', 'ALB / Alternative LAB', 'crée aucune', 'trialing', 'Customer Portal' ) as $needle ) {
    pf01_assert( false !== strpos( $documentation, $needle ), 'PF-01 documentation is missing its architecture or data-boundary contract: ' . $needle );
}
foreach ( array( 'PF-01F', 'cellule du', 'chevron retour', '`10 px` vers la gauche', 'Aucune carte membre basse', 'composants distincts', '30 × 30 px', 'margin-top: auto', 'retrait symétrique de `18 px`', 'flex: 1 1 0', '100svh', 'seul défilement vertical', '`2 px`' ) as $needle ) {
    pf01_assert( false !== strpos( $documentation, $needle ), 'PF-01F documentation must capture the approved local-panel visual and interaction boundary: ' . $needle );
}
pf01_assert( false !== strpos( $architecture, 'Faluss Portal' ) && false !== strpos( $data_model, '## Faluss Portal' ), 'Architecture and data-model documentation must register the new read-only portal boundary.' );
pf01_assert( 0 === preg_match( '/(?:sk|pk)_(?:live|test)_[A-Za-z0-9]{10,}/', $source . $javascript . $css ), 'PF-01 must not contain a Stripe key.' );

echo "PF-01 Faluss Portal contract: OK\n";
