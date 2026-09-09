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

foreach ( array( 'Plugin Name: Faluss Portal', "FALUSS_PORTAL_VERSION', '0.1.0'", 'class-faluss-portal.php' ) as $needle ) {
    pf01_assert( false !== strpos( $bootstrap, $needle ), 'PF-01 requires an isolated versioned Faluss Portal plugin: ' . $needle );
}
foreach ( array( "add_shortcode( self::SHORTCODE", "[faluss_portal]", 'Faluss_Identity_Client_Schema::tables()', 'WHERE wp_user_id = %d', "array( 'subscriber' )", 'Faluss_Identity_Client::button' ) as $needle ) {
    pf01_assert( false !== strpos( $source . $documentation, $needle ), 'PF-01 must start from the linked local SSO session and retain its shortcode/access fallback: ' . $needle );
}
pf01_assert( false === strpos( $source, '$_GET[\'faluss_id\']' ) && false === strpos( $source, '$_POST[\'faluss_id\']' ), 'A browser-supplied Faluss ID must never select portal data.' );
pf01_assert( false === strpos( $source, 'CREATE TABLE' ) && false === strpos( $source, 'INSERT INTO' ) && false === strpos( $source, 'update_user_meta' ), 'PF-01 must not introduce a portal table, write an identity link or persist a universal profile.' );
pf01_assert( false === strpos( $source, 'wp_ajax_' ) && false === strpos( $source, 'register_rest_route' ), 'The member portal must not expose an anonymous browser data or billing route.' );

require_once $plugin . '/includes/class-faluss-portal.php';
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
foreach ( array( 'data-faluss-portal-master', 'data-faluss-portal-master-tab', 'data-faluss-portal-drawer', 'Points Faluss', 'profil universel', 'data-faluss-portal-profile-unavailable' ) as $needle ) {
    pf01_assert( false !== strpos( $source, $needle ), 'Master Profile must use the agreed preparatory surface without a second identity model: ' . $needle );
}
foreach ( array( '--fp-sidebar-indicator-y', '--fp-tab-indicator-x', 'backdrop-filter', ':focus-visible', 'prefers-reduced-motion' ) as $needle ) {
    pf01_assert( false !== strpos( $css, $needle ), 'The shell must retain visual indicators, glass, keyboard focus and reduced-motion support: ' . $needle );
}
pf01_assert( 0 === preg_match( '/faluss-portal__nav-link(?:\\:hover|\\.is-active)\\s*\\{[^}]*background/s', $css ), 'Sidebar state must move only the indicator and outline, never recolour an icon bubble.' );
foreach ( array( 'history.pushState', 'popstate', 'requestAnimationFrame', 'showModal', 'closeProfile', 'setMasterTab' ) as $needle ) {
    pf01_assert( false !== strpos( $javascript, $needle ), 'Navigation and Master Profile must be progressive, animated and history-aware: ' . $needle );
}
pf01_assert( false === strpos( $javascript, 'fetch(' ) && false === strpos( $javascript, 'Stripe' ), 'The browser must not read Stripe or make a direct portal data call.' );
foreach ( array( 'sources de vérité', 'Master Profile', 'Point Faluss', 'crée aucune', 'trialing', 'Customer Portal' ) as $needle ) {
    pf01_assert( false !== strpos( $documentation, $needle ), 'PF-01 documentation is missing its architecture or data-boundary contract: ' . $needle );
}
pf01_assert( false !== strpos( $architecture, 'Faluss Portal' ) && false !== strpos( $data_model, '## Faluss Portal' ), 'Architecture and data-model documentation must register the new read-only portal boundary.' );
pf01_assert( 0 === preg_match( '/(?:sk|pk)_(?:live|test)_[A-Za-z0-9]{10,}/', $source . $javascript . $css ), 'PF-01 must not contain a Stripe key.' );

echo "PF-01 Faluss Portal contract: OK\n";
