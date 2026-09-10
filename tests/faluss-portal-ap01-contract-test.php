<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'FALUSS_PORTAL_URL', 'https://faluss.com/wp-content/plugins/faluss-portal/' );

function ap01_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? (string) $value : ''; }
function wp_parse_url( $value ) { return parse_url( (string) $value ); }

final class Faluss_Identity_Public_Profile {
    public static $profile = array();
    public static function studio_profile( $faluss_id ) {
        unset( $faluss_id );
        return self::$profile;
    }
}

$root = dirname( __DIR__ );
$plugin = $root . '/plugins/faluss-portal';
$source = file_get_contents( $plugin . '/includes/class-faluss-portal.php' );
$css = file_get_contents( $plugin . '/assets/css/faluss-portal.css' );
$javascript = file_get_contents( $plugin . '/assets/js/faluss-portal.js' );
$documentation = file_get_contents( $root . '/docs/FALUSS_PORTAL.md' );

require_once $plugin . '/includes/class-faluss-portal.php';

$faluss_id = '11111111-1111-4111-8111-111111111111';
$registry_method = new ReflectionMethod( 'Faluss_Portal', 'app_registry' );
$registry_method->setAccessible( true );
$panel_method = new ReflectionMethod( 'Faluss_Portal', 'apps_panel' );
$panel_method->setAccessible( true );

Faluss_Identity_Public_Profile::$profile = array(
    'faluss_id' => $faluss_id,
    'public_slug' => 'membre-test',
    'publication_status' => 'draft',
);
$registry = $registry_method->invoke( null, $faluss_id );
ap01_assert( array( 'hub', 'me', 'date', 'fans', 'pro' ) === array_column( $registry, 'slug' ), 'AP-01 must keep one ordered five-application registry.' );
$by_slug = array_column( $registry, null, 'slug' );
ap01_assert( true === $by_slug['hub']['available'] && true === $by_slug['hub']['owned'] && true === $by_slug['hub']['active'], 'A linked portal member must own the active Faluss Hub application.' );
$invalid_registry = array_column( $registry_method->invoke( null, 'not-a-faluss-id' ), null, 'slug' );
ap01_assert( false === $invalid_registry['hub']['owned'], 'Hub ownership must retain the validated linked-member precondition.' );
ap01_assert( false === $by_slug['me']['owned'], 'A draft Faluss.me card must never count as an owned application.' );
foreach ( array( 'date', 'fans', 'pro' ) as $slug ) {
    ap01_assert( false === $by_slug[ $slug ]['available'] && false === $by_slug[ $slug ]['owned'] && '' === $by_slug[ $slug ]['url'], 'An unavailable application must have neither ownership nor a production destination: ' . $slug );
}

ob_start();
$panel_method->invoke( null, 'my-apps', $faluss_id );
$draft_owned_html = ob_get_clean();
ap01_assert( 1 === substr_count( $draft_owned_html, 'data-faluss-app-card' ), 'Mes apps must render only the actually owned Hub when no published Faluss.me proof exists.' );
ap01_assert( false !== strpos( $draft_owned_html, 'data-faluss-app="hub"' ) && false === strpos( $draft_owned_html, 'data-faluss-app="me"' ), 'Mes apps must not infer Faluss.me ownership from the SSO link alone.' );

Faluss_Identity_Public_Profile::$profile['publication_status'] = 'published';
$registry = $registry_method->invoke( null, $faluss_id );
$by_slug = array_column( $registry, null, 'slug' );
ap01_assert( true === $by_slug['me']['owned'], 'A matching locally verified published card may add Faluss Me to Mes apps.' );
ob_start();
$panel_method->invoke( null, 'my-apps', $faluss_id );
$published_owned_html = ob_get_clean();
ap01_assert( 2 === substr_count( $published_owned_html, 'data-faluss-app-card' ), 'Mes apps must add exactly Faluss Me once a canonical published-card proof exists.' );
ap01_assert( false !== strpos( $published_owned_html, 'https://faluss.com/' ) && false !== strpos( $published_owned_html, 'https://www.faluss.me/' ), 'Owned available apps must use only their validated official destinations.' );
ap01_assert( 2 === substr_count( $published_owned_html, 'rel="noopener noreferrer"' ), 'Every compact application destination must be isolated from its opener.' );

ob_start();
$panel_method->invoke( null, 'explore', $faluss_id );
$explore_html = ob_get_clean();
ap01_assert( 5 === substr_count( $explore_html, 'data-faluss-app-card' ), 'Explorer must render all five registry entries through the shared card.' );
ap01_assert( 3 === substr_count( $explore_html, '>Bientôt disponible</span>' ), 'Exactly the three unavailable applications must expose a non-interactive coming-soon state.' );
ap01_assert( 1 === substr_count( $explore_html, '>Visiter</a>' ) && 1 === substr_count( $explore_html, 'href="https://www.faluss.me/"' ), 'Only the available non-active Faluss Me card may render the official Explorer visit link.' );
ap01_assert( false !== strpos( $explore_html, 'data-faluss-app-current' ) && false !== strpos( $explore_html, 'Impossible d’ouvrir, vous y êtes déjà' ), 'The active Hub card must expose its local temporary already-here interaction.' );
preg_match( '/<article[^>]*data-faluss-app="hub".*?<\/article>/s', $explore_html, $hub_card );
ap01_assert( ! empty( $hub_card[0] ) && false === strpos( $hub_card[0], 'href=' ), 'The active Explorer application must never navigate.' );
foreach ( array( 'date', 'fans', 'pro' ) as $slug ) {
    preg_match( '/<article[^>]*data-faluss-app="' . preg_quote( $slug, '/' ) . '".*?<\/article>/s', $explore_html, $card );
    ap01_assert( ! empty( $card[0] ) && false === strpos( $card[0], '<a ' ), 'An unavailable application must never contain a link: ' . $slug );
}
preg_match( '/<article[^>]*data-faluss-app="fans".*?<\/article>/s', $explore_html, $fans_card );
ap01_assert( ! empty( $fans_card[0] ) && false === strpos( $fans_card[0], '<img ' ), 'Faluss Fans must reserve the common logo slot without inventing an asset.' );

foreach ( array( '#000000', '#EE4A4A', '#8649EF', '#51EEB7', '#EF8851', '--faluss-app-accent:', 'faluss-portal__app-card--compact', 'faluss-portal__app-card--explore' ) as $needle ) {
    ap01_assert( false !== strpos( $source . $explore_html . $published_owned_html, $needle ), 'Both AP-01 views must consume one registry and the same app card variables: ' . $needle );
}
foreach ( array( "self::render_app_card( \$app, 'compact' )", "self::render_app_card( \$app, 'explore' )", 'private static function app_registry', 'private static function validated_app_url' ) as $needle ) {
    ap01_assert( false !== strpos( $source, $needle ), 'AP-01 must keep one renderer, one registry and one URL allowlist: ' . $needle );
}
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-card\s*\{[^}]*linear-gradient\([^}]*linear-gradient\(/s', $css ), 'The canonical card must stack a dark glass gradient above the accent-white-accent gradient.' );
ap01_assert( false !== strpos( $css, 'var(--faluss-app-accent)' ) && false !== strpos( $css, 'overflow: hidden' ), 'The shared card must remain variable-driven and horizontally bounded.' );
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-card\s*\{[^}]*--faluss-app-logo-size:\s*58px;[^}]*--faluss-app-head-size:\s*72px;[^}]*--faluss-app-open-size:\s*72px;/s', $css ), 'AP-01A must define one canonical desktop logo, header and action geometry for every application.' );
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-card\s*\{[^}]*--faluss-app-logo-size:\s*46px;[^}]*--faluss-app-head-size:\s*52px;[^}]*--faluss-app-open-size:\s*52px;/s', $css ), 'AP-01A must resize the same shared geometry at the mobile breakpoint.' );
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-logo\s*\{[^}]*width:\s*var\(--faluss-app-logo-size\);[^}]*height:\s*var\(--faluss-app-logo-size\);/s', $css ) && 1 === preg_match( '/\.faluss-portal__app-logo img\s*\{[^}]*width:\s*100%;[^}]*height:\s*100%;[^}]*object-fit:\s*contain;/s', $css ), 'Every logo, including the empty Fans slot, must use one square wrapper and contain sizing without per-app dimensions.' );
ap01_assert( 0 === preg_match( '/data-faluss-app="(?:hub|me|date|fans|pro)"[^\{]*\.faluss-portal__app-logo\s*\{/s', $css ), 'No application may receive a logo-specific sizing correction.' );
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-head\s*\{[^}]*min-height:\s*var\(--faluss-app-head-size\);[^}]*align-items:\s*center;/s', $css ) && 1 === preg_match( '/\.faluss-portal__app-card--compact\s*\{[^}]*display:\s*grid;[^}]*align-content:\s*center;/s', $css ), 'The single shared header must center logo, identity and compact action vertically in both card variants.' );
ap01_assert( 1 === substr_count( $source, '<div class="faluss-portal__app-head">' ), 'Mes apps and Explorer must keep one canonical header emitted by the shared renderer.' );
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-action\s*\{[^}]*display:\s*inline-grid;[^}]*place-items:\s*center;[^}]*border:\s*0 !important;[^}]*outline:\s*0 !important;[^}]*border-radius:\s*100px !important;[^}]*box-shadow:\s*none !important;/s', $css ), 'The real Hub action must remain a centered pill immune to Elementor and browser frames.' );
ap01_assert( false !== strpos( $css, '.faluss-portal__app-action::before' ) && false !== strpos( $css, '.faluss-portal__app-action::after { border-radius: 100px !important; }' ), 'The action pseudo-elements must inherit the same fully rounded geometry.' );
ap01_assert( false !== strpos( $javascript, '[data-faluss-app-current]' ) && false !== strpos( $javascript, '2800' ) && false !== strpos( $javascript, 'message.hidden = true' ), 'The active-app message must be local and automatically disappear within three seconds.' );
ap01_assert( false === strpos( $javascript, 'fetch(' ) && false === strpos( $javascript, 'XMLHttpRequest' ), 'Apps Faluss must make no browser request to infer ownership.' );
ap01_assert( 1 === preg_match( '/private static function app_registry.*?private static function home_panel/s', $source, $apps_source ) && false === strpos( $apps_source[0], 'wp_remote_' ), 'The Apps registry and renderer must make no server-side cross-domain request.' );

$official_assets = array(
    'faluss-hub.png'  => $root . '/plugins/faluss-link/assets/images/studio-ecosystem/faluss-studio-hub.png',
    'faluss-me.png'   => $root . '/plugins/faluss-link/assets/images/faluss-onboarding-header-logo.png',
    'faluss-date.png' => $root . '/plugins/faluss-link/assets/images/studio-ecosystem/faluss-studio-date.png',
    'faluss-pro.png'  => $root . '/plugins/faluss-link/assets/images/studio-ecosystem/faluss-studio-pro.png',
);
$normalized_asset_hashes = array(
    'faluss-hub.png'  => 'b299ea3d026f092f964ad5cc0344669d7c06ef4c1c2f30b9c311efebfa408a14',
    'faluss-date.png' => 'c4bcd913e796a06c6e0fb4ec83d01a27a3c0cb3c886449c36b4f636a8d5f1071',
    'faluss-pro.png'  => 'a76465d852729d63cf75bac2d3ffeb6b2890d7be238440d1924ae8fc10e80385',
);
foreach ( $official_assets as $name => $source_asset ) {
    $portal_asset = $plugin . '/assets/images/apps/' . $name;
    ap01_assert( is_file( $portal_asset ) && is_file( $source_asset ), 'The installable Portal ZIP must embed an official application asset: ' . $name );
    if ( 'faluss-me.png' === $name ) {
        ap01_assert( hash_file( 'sha256', $source_asset ) === hash_file( 'sha256', $portal_asset ), 'The Faluss Me reference asset must remain unchanged.' );
        continue;
    }
    $dimensions = getimagesize( $portal_asset );
    ap01_assert( is_array( $dimensions ) && 239 === $dimensions[0] && 239 === $dimensions[1], 'Each non-reference logo must use the same transparent canonical canvas: ' . $name );
    ap01_assert( $normalized_asset_hashes[ $name ] === hash_file( 'sha256', $portal_asset ), 'The padded asset must preserve the reviewed official artwork and canonical canvas: ' . $name );
}

ap01_assert( 0 === preg_match( '/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}/i', $explore_html . $published_owned_html ), 'No Faluss ID may be rendered in either Apps view.' );
foreach ( array( 'faluss_id', 'provider_', 'stripe', 'customer', 'subscription' ) as $sensitive ) {
    ap01_assert( false === stripos( $explore_html . $published_owned_html, $sensitive ), 'Apps markup must not expose technical account or billing data: ' . $sensitive );
}
foreach ( array( 'AP-01', 'AP-01A', 'Faluss Hub', 'Faluss Me', 'Bientôt disponible', 'Aucun appel', 'inter-domaine', 'preuve locale', '--faluss-app-logo-size' ) as $needle ) {
    ap01_assert( false !== strpos( $documentation, $needle ), 'AP-01 documentation must capture its registry and ownership boundary: ' . $needle );
}

echo "AP-01 Apps Faluss contract: OK\n";
