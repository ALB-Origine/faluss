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
foreach ( array(
    'hub'  => array( '18px', '22px' ),
    'me'   => array( '12.43px', '23px' ),
    'date' => array( '23px', '23px' ),
    'pro'  => array( '21.28px', '23px' ),
) as $slug => $size ) {
    $mobile_rule = '/@media \(max-width:\s*720px\).*?\.faluss-portal__app-card\[data-faluss-app="' . preg_quote( $slug, '/' ) . '"\]\s*\{([^}]*)\}/s';
    ap01_assert( 1 === preg_match( $mobile_rule, $css, $mobile_match ) && false !== strpos( $mobile_match[1], '--faluss-app-symbol-width: ' . $size[0] ) && false !== strpos( $mobile_match[1], '--faluss-app-symbol-height: ' . $size[1] ), 'AP-01B must expose the exact mobile rendered-symbol dimensions for ' . $slug . '.' );
}
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-logo\s*\{[^}]*width:\s*var\(--faluss-app-symbol-width\);[^}]*height:\s*var\(--faluss-app-symbol-height\);[^}]*place-items:\s*center;[^}]*overflow:\s*hidden;[^}]*line-height:\s*0;/s', $css ), 'The logo column must expose the actual per-app visual bounds and remove image baseline drift.' );
ap01_assert( 1 === preg_match( '/\.faluss-portal__app-logo img\s*\{[^}]*display:\s*block;[^}]*width:\s*var\(--faluss-app-asset-width\);[^}]*height:\s*var\(--faluss-app-asset-height\);[^}]*max-width:\s*none;[^}]*max-height:\s*none;[^}]*object-fit:\s*contain;[^}]*line-height:\s*0;/s', $css ), 'No global image sizing may override the measured symbol asset dimensions.' );
ap01_assert( 1 === preg_match( '/data-faluss-app="me"\]\s*\{[^}]*--faluss-app-symbol-width:\s*12\.43px;[^}]*--faluss-app-symbol-height:\s*23px;[^}]*--faluss-app-asset-width:\s*42\.36px;[^}]*--faluss-app-asset-height:\s*42\.36px;/s', $css ), 'Faluss Me must correct only its own official asset padding inside the measured visible wrapper.' );
ap01_assert( 1 === preg_match( '/data-faluss-app="me"\]\s+\.faluss-portal__app-logo img\s*\{[^}]*position:\s*absolute;[^}]*inset-inline-start:\s*calc\(\(var\(--faluss-app-symbol-width\) - var\(--faluss-app-asset-width\)\) \/ 2\);[^}]*inset-block-start:\s*calc\(\(var\(--faluss-app-symbol-height\) - var\(--faluss-app-asset-height\)\) \/ 2\);/s', $css ), 'Faluss Me must center its padded official source locally rather than inherit the browser safe-alignment fallback.' );
ap01_assert( 0 === preg_match( '/\.faluss-portal__app-logo(?:\s+img)?\s*\{[^}]*transform:/s', $css ), 'AP-01B must not use a generic logo translation.' );
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
foreach ( $official_assets as $name => $source_asset ) {
    $portal_asset = $plugin . '/assets/images/apps/' . $name;
    ap01_assert( is_file( $portal_asset ) && hash_file( 'sha256', $source_asset ) === hash_file( 'sha256', $portal_asset ), 'AP-01B must embed the unchanged official source asset and correct only its rendered box: ' . $name );
}

ap01_assert( 0 === preg_match( '/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}/i', $explore_html . $published_owned_html ), 'No Faluss ID may be rendered in either Apps view.' );
foreach ( array( 'faluss_id', 'provider_', 'stripe', 'customer', 'subscription' ) as $sensitive ) {
    ap01_assert( false === stripos( $explore_html . $published_owned_html, $sensitive ), 'Apps markup must not expose technical account or billing data: ' . $sensitive );
}
foreach ( array( 'AP-01', 'AP-01B', 'Faluss Hub', 'Faluss Me', 'Bientôt disponible', 'Aucun appel', 'inter-domaine', 'preuve locale', '--faluss-app-symbol-width' ) as $needle ) {
    ap01_assert( false !== strpos( $documentation, $needle ), 'AP-01 documentation must capture its registry and ownership boundary: ' . $needle );
}

echo "AP-01 Apps Faluss contract: OK\n";
