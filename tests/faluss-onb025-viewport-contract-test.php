<?php

/** ONB-02.5 route viewport, scrolling and centered fallback contract. */

function onb025_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function onb025_slice( $source, $start, $end ) {
    $from = strpos( $source, $start );
    $to = false === $from ? false : strpos( $source, $end, $from );
    return false === $from || false === $to ? '' : substr( $source, $from, $to - $from );
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-onboarding.css' );
$card_css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-onboarding.js' );
$product_ui = file_get_contents( $root . '/docs/FALUSS_PRODUCT_UI.md' );

onb025_assert( false !== strpos( $bootstrap, 'Version: 0.3.9' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.9'" ), 'The current viewport assets need a renewable plugin version.' );

// Elementor Canvas must be normalized only on the configured onboarding URL.
foreach ( array( "add_filter( 'body_class', array( __CLASS__, 'onboarding_body_class' ), 99 )", 'faluss-link-onboarding-route', 'Faluss_Identity_Onboarding::onboarding_url()', "wp_parse_url( \$request_uri, PHP_URL_PATH )", 'untrailingslashit( $request_path ) !== untrailingslashit( $onboarding_path )', 'PHP_URL_QUERY', 'foreach ( $required_query as $key => $value )' ) as $needle ) {
    onb025_assert( false !== strpos( $link, $needle ), 'The route-scoped Canvas shell is incomplete: ' . $needle );
}
$route_method = onb025_slice( $link, 'private static function is_onboarding_surface_request()', '/** @param array<string,mixed> $state' );
onb025_assert( false === strpos( $route_method, '/start' ) && false === strpos( $route_method, '/commencer' ), 'The viewport route must follow the configured onboarding URL, never a hard-coded slug.' );
foreach ( array( 'body.faluss-link-onboarding-route', 'height: 100dvh', 'padding-top: env(safe-area-inset-top, 0px)', 'env(safe-area-inset-bottom, 0px)', '[data-faluss-onboarding-host]', ':has(.faluss-link-onboarding)', 'overflow: clip' ) as $needle ) {
    onb025_assert( false !== strpos( $css, $needle ), 'The route shell must own one safe, uninterrupted dynamic viewport: ' . $needle );
}
foreach ( array( 'prepareViewportHost', "document.documentElement.classList.add('faluss-link-onboarding-document')", "ancestor.setAttribute('data-faluss-onboarding-host', '')" ) as $needle ) {
    onb025_assert( false !== strpos( $script, $needle ), 'The actual WordPress/Elementor host chain must be identified at runtime: ' . $needle );
}
onb025_assert( false === strpos( $script, 'document.body.style.overflow' ) && false === strpos( $script, 'touchmove' ), 'The shell cannot emulate a viewport by locking Safari or the document from JavaScript.' );
onb025_assert( false === strpos( $css, 'margin: -8px' ) && false === strpos( $css, 'margin-top: -' ), 'Visible onboarding layout cannot be repaired with negative offset compensation.' );

// The live region cannot become a themed block that consumes the first grid row.
onb025_assert( false !== strpos( $link, 'class="faluss-link-onboarding__status" role="status" aria-live="polite"' ), 'The saved-state announcement needs a dedicated live-region class.' );
onb025_assert( false === strpos( $link, 'faluss-link-onboarding__status screen-reader-text' ), 'The live region cannot rely on a generic theme class whose cascade can expose a white ceiling.' );
onb025_assert( false !== strpos( $css, '.faluss-link-onboarding .faluss-link-onboarding__status.faluss-link-onboarding__status' ) && false !== strpos( $css, 'clip-path: inset(50%)' ), 'The dedicated live region must remain visually hidden under Elementor CSS.' );

// Name, preview, upload and long-list steps use explicit structures.
foreach ( array( 'data-onboarding-layout="<?php echo esc_attr( self::onboarding_layout( $step ) ); ?>"', "if ( 'name' === \$step ) { return 'name'; }", "if ( 'avatar' === \$step ) { return 'upload'; }", "array( 'socials', 'links' )", "return 'preview';" ) as $needle ) {
    onb025_assert( false !== strpos( $link, $needle ), 'A required step layout is not resolved explicitly: ' . $needle );
}
foreach ( array( '[data-onboarding-layout=name]', '[data-onboarding-layout=upload]', '[data-onboarding-layout=list]', 'align-content: start', 'faluss-link-onboarding__avatar-upload', 'background: #fff', 'height: min(355px, 100%)', 'grid-template-rows: auto minmax(0, 1fr) auto' ) as $needle ) {
    onb025_assert( false !== strpos( $css, $needle ), 'The Figma name/upload/list layout contract is incomplete: ' . $needle );
}
onb025_assert( false !== strpos( $css, '.faluss-link-onboarding__preview { position: absolute' ) && false !== strpos( $css, '.faluss-link-onboarding__form {' ) && false !== strpos( $css, 'border-radius: 50px 50px 0 0' ), 'Preview steps must keep the compact screen behind one opaque superposed panel.' );

// No short panel scrolls. Exactly the two member-data lists opt into scrolling.
onb025_assert( 1 === substr_count( $css, 'overflow-y: auto' ), 'Vertical scrolling must be declared once, on the reusable long-list region only.' );
onb025_assert( false !== strpos( $css, '.faluss-link-onboarding__scroll-region' ) && false !== strpos( $css, '.faluss-link-onboarding__panel {') && false !== strpos( $css, 'overflow: clip' ), 'Panels stay stable while an explicit list region may overflow.' );
onb025_assert( 2 === substr_count( $link, 'data-onboarding-scroll-region' ), 'Only Networks and Links may render a vertically scrollable content region.' );
onb025_assert( false !== strpos( $css, '.faluss-link-onboarding__actions { grid-row: 3' ), 'Continue and Skip must remain outside the list scroller in a stable footer row.' );

// The card renderer owns both immediate preview treatment and centered fallback.
foreach ( array( "'alignment' => 'center'", "'alignment' => self::align( \$theme['alignment'] ?? 'center' )", "private static function align( \$value ) { return in_array( \$value, array( 'left', 'center', 'right' ), true ) ? \$value : 'center'; }", "faluss-link-card--align-<?php echo esc_attr( \$presentation['alignment'] ); ?>" ) as $needle ) {
    onb025_assert( false !== strpos( $link, $needle ), 'A card without an explicit preference must resolve to the shared centered fallback: ' . $needle );
}
onb025_assert( false !== strpos( $link, "\$preferences['alignment'] = self::align( \$payload['alignment'] ?? \$preferences['alignment'] )" ) && false !== strpos( $link, '$legacy ? self::theme_setting_keys()' ), 'A historical explicit-left payload must remain an explicit presentation override.' );
$prefs_method = onb025_slice( $link, 'private static function prefs(', 'private static function name_color(' );
onb025_assert( false === strpos( $prefs_method, 'UPDATE ' ) && false === strpos( $prefs_method, '$wpdb->update' ), 'Reading the centered fallback cannot rewrite historical card rows.' );
foreach ( array( "nameTarget.classList.remove('faluss-link-card__name--strong', 'faluss-link-card__name--editorial')", "nameTarget.classList.add('faluss-link-card__name--' + treatment.value)", "faluss-link-card__name--<?php echo esc_attr( \$name_treatment['key'] ); ?>" ) as $needle ) {
    onb025_assert( false !== strpos( $script . $link, $needle ), 'Name treatment must propagate immediately and through the shared renderer: ' . $needle );
}
onb025_assert( false !== strpos( $card_css, '.faluss-link-card__name--strong' ) && false !== strpos( $card_css, '.faluss-link-card__name--editorial' ), 'Both stored name treatments need visible card presentation rules.' );

// Product controls preserve Figma geometry, readable states and motion preference.
foreach ( array( '.faluss-link-onboarding__symbol', 'border-radius: 50%', 'clip-path: circle(50% at 50% 50%)', '.faluss-link-onboarding__choice-panel.is-entering', 'transition: opacity .18s ease, transform .18s ease', 'button[aria-selected=true]', 'button:focus-visible', '@media (prefers-reduced-motion: reduce)' ) as $needle ) {
    onb025_assert( false !== strpos( $css, $needle ), 'The circular logo or segmented-control state contract is missing: ' . $needle );
}
onb025_assert( false !== strpos( $script, 'window.requestAnimationFrame' ) && false !== strpos( $script, "choicePanel.classList.add('is-entering')" ) && false !== strpos( $script, '!reduceMotion()' ), 'Segment content needs one short, reduced-motion-aware transition.' );

foreach ( array( 'ONB-02.5', 'Elementor Canvas', 'Réseaux et Liens', 'centré', 'préférence gauche explicite' ) as $needle ) {
    onb025_assert( false !== strpos( $product_ui, $needle ), 'The viewport/fallback decision must be documented: ' . $needle );
}

echo 'ONB-02.5 viewport and layout contract: OK' . PHP_EOL;
