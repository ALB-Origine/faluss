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

studio_v1_regression_assert( false !== strpos( $bootstrap, 'Version: 0.3.11' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.11'" ), 'The Studio regression patch must rotate Faluss Link assets to 0.3.11.' );

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

foreach ( array( 'Faluss, c’est juste un écosystème complet.', 'Visitez nos autres produits !', 'Faluss Me', 'Vous êtes déjà ici', 'Mon Faluss', 'Ma Liste', 'https://www.faluss.com/', 'https://www.pro.faluss.com/', 'https://www.faluss.fans/', 'https://date.faluss.com/', 'M’y rendre', "home_url( '/mon-faluss/' )", "home_url( '/list/' )", 'Faluss_Identity_Navigation::actions()', 'assets/images/faluss-onboarding-header-logo.png', 'faluss-link-studio__ecosystem-lead', 'faluss-link-studio__ecosystem-products' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $link, $needle ), 'The More ecosystem screen must retain its exact product and identity contract: ' . $needle );
}
foreach ( array( '[data-fl-studio-ecosystem]:hover', '[data-fl-studio-ecosystem]:focus-visible', '[data-fl-studio-ecosystem]:active', 'color: #171717' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio, $needle ), 'The More control must remain dark and legible in every interaction state: ' . $needle );
}

foreach ( array( '--fl-studio-main-rhythm:', 'padding: clamp(1.1rem, 5vw, 2.25rem) var(--fl-studio-gutter) var(--fl-studio-main-rhythm)', 'padding: 0 var(--fl-studio-gutter) var(--fl-studio-main-rhythm)', '.faluss-link-studio__identity { min-height: 0; }' ) as $needle ) {
    studio_v1_regression_assert( false !== strpos( $studio, $needle ), 'Header and identity must share the compact Studio V1 rhythm: ' . $needle );
}

echo "Faluss Link Studio V1 regression contract: OK\n";
