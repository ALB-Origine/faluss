<?php

function studio_v1_regression_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$root      = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$link      = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor    = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$studio    = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );

studio_v1_regression_assert( false !== strpos( $bootstrap, 'Version: 0.3.14' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.14'" ), 'The Studio regression patch must rotate Faluss Link assets to 0.3.14.' );

foreach ( array( '@media (max-width: 767px)', '.faluss-link-studio input,', '.faluss-link-studio select,', '.faluss-link-studio textarea { font-size: 16px; }' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio, $needle ), 'Studio editing controls must use a 16px mobile font without disabling browser zoom: ' . $needle );
}
studio_v1_regression_assert( false === strpos( $studio, 'user-scalable' ), 'The Studio must never disable browser zoom to avoid iOS field zoom.' );

foreach ( array( 'self::content_blocks( $faluss_id, array(), false )', "\$payload['blocks']", "\$payload['links_html']", 'function studio_links_panel_html', 'function hydrateCanonicalBlocks', 'Array.isArray(payload.blocks)', "[data-fl-main-panel=\"links\"] [data-fl-section-panel=\"all\"]", "saveStudio(studio, false, function (payload)", "restoreStudioState(studio, { tab: 'links', section: 'all', collection: '' }, true)" ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $link . $editor, $needle ), 'A new link must hydrate from the canonical successful save response: ' . $needle );
}
$create_link_start = strpos( $editor, "[data-fl-create-link-submit]" );
$create_link_end   = false === $create_link_start ? false : strpos( $editor, "[data-fl-create-collection-submit]", $create_link_start );
$create_link       = false === $create_link_start || false === $create_link_end ? '' : substr( $editor, $create_link_start, $create_link_end - $create_link_start );
studio_v1_regression_assert( false === strpos( $create_link, 'window.location.reload()' ), 'Creating a link must not need a hard reload before the visible list is current.' );

foreach ( array( 'flex: 0 0 36px', 'width: 36px; height: 36px', 'width: 16px; height: 16px', 'align-items: center', 'data-fl-open-social-manager', 'function openSocialManager', "activate(studio, 'style', false)", "activateSection(studio, 'header', false)" ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio . $link . $editor, $needle ), 'The social shortcut must be compact, aligned, and retain the Header destination: ' . $needle );
}

foreach ( array( '.faluss-link-theme-picker__rail { display: flex', 'overflow-x: auto', 'scroll-snap-type: x proximity', 'flex: 0 0 min(18rem, calc(100% - 4rem))', 'height: 5rem', 'text-overflow: ellipsis', 'white-space: nowrap', '.faluss-link-studio__design-content { overflow-x: clip' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio, $needle ), 'Only the themes rail may scroll horizontally while preserving readable cards: ' . $needle );
}

foreach ( array( 'Faluss, c’est juste un écosystème complet.', 'Visitez nos autres produits !', 'Faluss Me', 'Vous êtes déjà ici', 'Mon Faluss', 'Ma Liste', 'https://faluss.com/mon-faluss', 'https://www.pro.faluss.com/', 'https://www.faluss.fans/', 'https://date.faluss.com/', 'M’y rendre', "home_url( '/mon-faluss/' )", "home_url( '/list/' )", 'Faluss_Identity_Navigation::actions()', 'assets/images/faluss-onboarding-header-logo.png', 'faluss-link-studio__ecosystem-lead', 'faluss-link-studio__ecosystem-products' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $link, $needle ), 'The More ecosystem screen must retain its exact product and identity contract: ' . $needle );
}
foreach ( array( '[data-fl-studio-ecosystem]:hover', '[data-fl-studio-ecosystem]:focus-visible', '[data-fl-studio-ecosystem]:active', 'color: #171717' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio, $needle ), 'The More control must remain dark and legible in every interaction state: ' . $needle );
}

foreach ( array( "screen.find('h2[tabindex]').removeAttr('tabindex')", "name !== 'create-link' && name !== 'create-collection'", 'input:not([type="hidden"])', "name + '\"] textarea'", 'if (target.length) { target.trigger(\'focus\'); }' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $editor, $needle ), 'The Ecosystem heading must remain static while real create fields retain managed focus: ' . $needle );
}
studio_v1_regression_assert( false === strpos( $editor, "name + '\"] h2," ), 'The screen switcher must never select an Ecosystem heading as a focus target.' );
foreach ( array( 'expandedLink:', 'collectionEditorOpen:', 'scrollY:', 'function restoreEditorState', 'activateSection(studio, state.section, false)', 'window.scrollTo(0, Math.max(0, state.scrollY))', "if (screen === 'ecosystem') { restoreStudioState(studio, studio.data('falussLinkEcosystemReturn'), true); return true; }" ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $editor, $needle ), 'Back from Ecosystem must restore the saved Studio root, editing state and scroll without focusing a context tab: ' . $needle );
}

foreach ( array( '.faluss-link-studio__round-action:active', '.faluss-link-studio__round-action:focus', '.faluss-link-studio__round-action:focus-visible', '.faluss-link-studio__round-action > span { color: currentColor; }', '.faluss-link-studio__round-action:focus:not(:focus-visible) { outline: 0; }' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio, $needle ), 'Back, More and Share must retain dark icon-button states while keyboard focus stays visible: ' . $needle );
}

foreach ( array( 'align-items: flex-start', 'text-align: left', 'object-fit: contain', 'object-position: left center', 'faluss-link-studio__ecosystem-symbol--faluss-me', 'faluss-link-studio__ecosystem-placeholder' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio . $link, $needle ), 'Every Ecosystem product card must share the active card alignment without recoloring derived logos: ' . $needle );
}
foreach ( array(
    'assets/images/studio-ecosystem/faluss-studio-hub.png',
    'assets/images/studio-ecosystem/faluss-studio-pro.png',
    'assets/images/studio-ecosystem/faluss-studio-date.png',
    'https://faluss.com/mon-faluss',
    'https://www.pro.faluss.com/',
    'https://date.faluss.com/',
) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $link, $needle ), 'The official derivative-logo mapping is incomplete: ' . $needle );
}
foreach ( array( 'faluss-studio-hub.png', 'faluss-studio-pro.png', 'faluss-studio-date.png' ) as $asset ) {
    $path = $root . '/plugins/faluss-link/assets/images/studio-ecosystem/' . $asset;
    studio_v1_regression_assert( is_file( $path ) && filesize( $path ) > 0, 'The official derivative-logo asset must be packaged locally: ' . $asset );
}
$create_views_start = strpos( $link, 'private static function studio_create_views()' );
$create_views_end   = false === $create_views_start ? false : strpos( $link, 'private static function studio_ecosystem_smart_action()', $create_views_start );
$create_views       = false === $create_views_start || false === $create_views_end ? '' : substr( $link, $create_views_start, $create_views_end - $create_views_start );
studio_v1_regression_assert( 1 === substr_count( $create_views, 'faluss-onboarding-header-logo.png' ), 'The Faluss Me asset must remain exclusive to the active Faluss Me Ecosystem card.' );
$derivative_mappings = array(
    'https://faluss.com/mon-faluss' => '$hub_symbol_url',
    'https://www.pro.faluss.com/' => '$pro_symbol_url',
    'https://date.faluss.com/'    => '$date_symbol_url',
);
foreach ( $derivative_mappings as $url => $symbol ) {
    studio_v1_regression_assert( 1 === preg_match( '/href="' . preg_quote( $url, '/' ) . '".*?esc_url\( ' . preg_quote( $symbol, '/' ) . ' \)/', $create_views ), 'Each official derivative asset must stay attached to its product URL: ' . $url );
}
studio_v1_regression_assert( 1 === preg_match( '/href="https:\/\/www\.faluss\.fans\/".*?faluss-link-studio__ecosystem-placeholder/', $create_views ), 'Faluss Fans must retain a neutral temporary placeholder rather than borrowing another product logo.' );

foreach ( array( '--fl-studio-main-rhythm:', 'padding: clamp(1.1rem, 5vw, 2.25rem) var(--fl-studio-gutter) var(--fl-studio-main-rhythm)', 'padding: 0 var(--fl-studio-gutter) var(--fl-studio-main-rhythm)', '.faluss-link-studio__identity { min-height: 0; }' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio, $needle ), 'Header and identity must share the compact Studio V1 rhythm: ' . $needle );
}

echo "Faluss Link Studio V1 regression contract: OK\n";
