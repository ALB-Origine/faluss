<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'FALUSS_PORTAL_URL', 'https://faluss.com/wp-content/plugins/faluss-portal/' );
define( 'TOKEN_ENGINE_VERSION', '0.4.1' );

function ap02a_assert( $condition, $message ) {
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
    public static $projection = null;
    public static function member_app_projection( $faluss_id, $app_key ) {
        unset( $faluss_id );
        return 'me' === $app_key ? self::$projection : null;
    }
}

$root = dirname( __DIR__ );
$portal_dir = $root . '/plugins/faluss-portal';
$portal = file_get_contents( $portal_dir . '/includes/class-faluss-portal.php' );
$portal_bootstrap = file_get_contents( $portal_dir . '/faluss-portal.php' );
$css = file_get_contents( $portal_dir . '/assets/css/faluss-portal.css' );
$javascript = file_get_contents( $portal_dir . '/assets/js/faluss-portal.js' );
$identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
$authorization = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-authorization.php' );
$identity_bootstrap = file_get_contents( $root . '/plugins/faluss-identity/faluss-identity.php' );
$client = file_get_contents( $root . '/plugins/faluss-identity-client/includes/class-faluss-identity-client.php' );
$client_bootstrap = file_get_contents( $root . '/plugins/faluss-identity-client/faluss-identity-client.php' );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$link_bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$documentation = file_get_contents( $root . '/docs/FALUSS_PORTAL.md' );

require_once $portal_dir . '/includes/class-faluss-portal.php';

$faluss_id = '11111111-1111-4111-8111-111111111111';
$registry_method = new ReflectionMethod( 'Faluss_Portal', 'app_registry' );
$registry_method->setAccessible( true );
$panel_method = new ReflectionMethod( 'Faluss_Portal', 'apps_panel' );
$panel_method->setAccessible( true );
$render_daily = new ReflectionMethod( 'Faluss_Portal', 'render_hub_daily_action' );
$render_daily->setAccessible( true );

Faluss_Identity_Client::$projection = null;
$without_projection = array_column( $registry_method->invoke( null, $faluss_id ), null, 'slug' );
ap02a_assert( false === $without_projection['me']['owned'], 'An absent Identity projection must not fabricate Faluss Me ownership.' );

Faluss_Identity_Client::$projection = array(
    'contract_version' => '1',
    'publication_status' => 'published',
    'canonical_url' => 'https://faluss.me/mon-faluss',
);
$registry = array_column( $registry_method->invoke( null, $faluss_id ), null, 'slug' );
ap02a_assert( 'https://faluss.com/mon-faluss' === $registry['hub']['url'], 'Faluss Hub must use its canonical member destination.' );
ap02a_assert( true === $registry['me']['owned'] && 'https://faluss.me/mon-faluss' === $registry['me']['url'], 'A published Identity projection must activate Faluss Me at its canonical member destination.' );

ob_start();
$panel_method->invoke( null, 'my-apps', $faluss_id );
$owned = ob_get_clean();
ap02a_assert( 2 === substr_count( $owned, 'data-faluss-app-card' ), 'Hub and published Faluss Me must appear immediately in Mes Apps.' );
ap02a_assert( 2 === substr_count( $owned, 'faluss-portal__app-card-access' ) && 2 === substr_count( $owned, 'faluss-portal__app-open' ), 'Every Mes Apps card must use the same card access and integrated glass action zone.' );
ap02a_assert( false !== strpos( $owned, 'href="https://faluss.com/mon-faluss"' ) && false !== strpos( $owned, 'href="https://faluss.me/mon-faluss"' ), 'Mes Apps must not fall back to either marketing home when member routes exist.' );

preg_match( '/<article[^>]*data-faluss-app="hub".*?<\/article>/s', $owned, $hub_card );
preg_match( '/<article[^>]*data-faluss-app="me".*?<\/article>/s', $owned, $me_card );
ap02a_assert( ! empty( $hub_card[0] ) && false !== strpos( $hub_card[0], 'assets/images/pf/faluss-pf-badge.png' ) && false !== strpos( $hub_card[0], '>20</span>' ), 'Claimable Hub must show only the official PF badge and amount 20 in the integrated zone.' );
foreach ( array( 'Récupérer', 'Claim', '+20', '>0 PF<', '>75 PF<' ) as $forbidden ) {
    ap02a_assert( false === strpos( $hub_card[0], $forbidden ), 'Hub must not render a competing claim label or false amount: ' . $forbidden );
}
ap02a_assert( ! empty( $me_card[0] ) && false === strpos( $me_card[0], 'PF' ) && false === strpos( $me_card[0], 'hub-daily' ) && false === strpos( $me_card[0], '<form' ), 'Faluss Me must retain only its glass navigation action without a fictitious reward.' );

ob_start();
$panel_method->invoke( null, 'explore', $faluss_id );
$explore = ob_get_clean();
ap02a_assert( false !== strpos( $explore, 'href="https://faluss.me/mon-faluss"' ) && false === strpos( $explore, 'href="https://www.faluss.me/"' ), 'Explorer must reuse the known Faluss Me member destination.' );

Faluss_Identity_Client::$projection['canonical_url'] = 'https://attacker.invalid/mon-faluss';
$invalid = array_column( $registry_method->invoke( null, $faluss_id ), null, 'slug' );
ap02a_assert( false === $invalid['me']['owned'], 'A projection outside the exact Faluss Me authority must fail closed.' );

ob_start();
$render_daily->invoke( null, array( 'status' => 'claimed' ) );
$claimed = ob_get_clean();
ap02a_assert( false === strpos( $claimed, '<button' ) && false === strpos( $claimed, '<form' ) && false !== strpos( $claimed, 'faluss-portal__app-open' ) && false !== strpos( $claimed, '>20</span>' ), 'Claimed Hub must keep the same visual zone with no second action.' );

foreach ( array( '0.1.18', '0.4.15', '0.5.2', '0.3.17' ) as $version ) {
    ap02a_assert( false !== strpos( $portal_bootstrap . $identity_bootstrap . $client_bootstrap . $link_bootstrap, $version ), 'Every modified plugin must expose its AP-02A patch version: ' . $version );
}
foreach ( array( 'member_app_projection', "'publication_status' => 'published'", "home_url( '/mon-faluss' )" ) as $needle ) {
    ap02a_assert( false !== strpos( $identity . $authorization, $needle ), 'Identity must issue the minimal published-card projection during SSO: ' . $needle );
}
foreach ( array( 'MEMBER_APPS_META', 'synchronize_member_app_projections', 'validated_me_projection', 'update_user_meta', 'get_user_meta' ) as $needle ) {
    ap02a_assert( false !== strpos( $client, $needle ), 'Identity Client must validate and retain the server-issued projection: ' . $needle );
}
ap02a_assert( false === strpos( $client, "\$_POST['apps']" ) && false === strpos( $client, "\$_GET['apps']" ), 'The browser must never supply the application projection.' );
ap02a_assert( false !== strpos( $link, 'href="https://faluss.com/mon-faluss"' ) && false === strpos( $link, 'href="https://www.faluss.com/"' ), 'Faluss Me must route its Hub card to the Hub member portal, never the marketing home.' );
ap02a_assert( false === strpos( $portal . $client . $identity . $authorization, 'register_rest_route' ) && false === strpos( $portal . $client . $identity . $authorization, 'CREATE TABLE' ), 'AP-02A must add no REST route, table or migration.' );
ap02a_assert( false === strpos( $portal, '$_GET[\'faluss_id\']' ) && false === strpos( $portal, '$_POST[\'faluss_id\']' ), 'Portal must not accept a Faluss ID from the browser.' );
foreach ( array( 'amount_pf', 'economic_class', 'logical_date', 'reward_key', 'idempotency' ) as $field ) {
    ap02a_assert( false === strpos( $hub_card[0], $field ), 'The Hub browser action must carry no economic decision field: ' . $field );
}
ap02a_assert( false !== strpos( $css, 'color-mix(in srgb, var(--faluss-app-accent)' ) && false !== strpos( $css, 'backdrop-filter: blur(14px)' ), 'The common compact action must retain the historical circular glass treatment.' );
ap02a_assert( false === strpos( $javascript, 'Récupérer' ) && false === strpos( $javascript, '+20 PF' ), 'The dynamic claimed transition must not restore a textual CTA.' );
ap02a_assert( 'a25533ca502e4e6286cb58c858de8d7a4de5d25b18a5d91894b31c46ff1f1955' === hash_file( 'sha256', $portal_dir . '/assets/images/pf/faluss-pf-badge.png' ), 'The supplied official PF badge must remain byte-for-byte unchanged.' );
foreach ( array( 'AP-02A', 'projection SSO', 'https://faluss.com/mon-faluss', 'https://faluss.me/mon-faluss', 'badge PF', '`20`' ) as $needle ) {
    ap02a_assert( false !== strpos( $documentation, $needle ), 'AP-02A documentation is incomplete: ' . $needle );
}

echo "AP-02A Mes Apps and PF action contract: OK\n";
