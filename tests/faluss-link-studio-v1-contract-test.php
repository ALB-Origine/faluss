<?php

define( 'ABSPATH', __DIR__ . '/' );

function studio_v1_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );

foreach ( array(
    'data-fl-main-panel="links"',
    'data-fl-main-panel="style"',
    'data-fl-context-tab="all"',
    'data-fl-context-tab="collections"',
    'data-fl-context-tab="appearance"',
    'data-fl-context-tab="header"',
    'data-fl-context-tab="link-style"',
    'data-fl-create',
    'data-fl-preview-toggle',
    'data-fl-block-store',
    'aria-disabled="true"',
) as $needle ) {
    studio_v1_assert( false !== strpos( $link, $needle ), 'Studio shell or navigation is incomplete: ' . $needle );
}

studio_v1_assert( false !== strpos( $link, "esc_html_e( 'Shop', 'faluss-link' )" ) && false !== strpos( $link, "esc_html_e( 'Profil', 'faluss-link' )" ), 'Shop and Profil must remain visible but inert.' );
studio_v1_assert( false === strpos( $link, '>Sets<' ) && false === strpos( $link, '>Set<' ), 'Member-facing Studio markup must use Collections.' );
studio_v1_assert( false === strpos( $link, 'data-fl-new-collection-url' ), 'A collection must not expose or persist a URL.' );
studio_v1_assert( false !== strpos( $link, 'Collections are a Studio projection of the existing ordered block stream' ), 'Collections must project the canonical block stream.' );
studio_v1_assert( false !== strpos( $link, "'section_title'" ) && false !== strpos( $link, "'link'" ), 'Collections and links must reuse canonical block types.' );
studio_v1_assert( 1 === substr_count( $link, "add_action( 'admin_post_faluss_link_save_studio'" ), 'Studio V1 must keep one server mutation endpoint.' );
studio_v1_assert( false !== strpos( $link, "wp_verify_nonce" ) && false !== strpos( $link, "Faluss_Identity_Registry::get_active_for_wp_user" ), 'Studio mutations must retain nonce and owner guards.' );

foreach ( array(
    "window.fetch(form.attr('action')",
    'new FormData()',
    "data.append('mutation', task.mutation)",
    "data.append('aggregate_version'",
    'credentials: \'same-origin\'',
    'function enqueueStudioMutation',
    'function hydrateCanonicalBlocks',
    'collections_html',
    'preview_html',
    'safeURL(url)',
) as $needle ) {
    studio_v1_assert( false !== strpos( $editor, $needle ), 'Studio mutation or single-card interaction is incomplete: ' . $needle );
}
studio_v1_assert( false === strpos( $editor, 'new FormData(form[0])' ) && false === strpos( $editor, 'function saveStudio' ), 'Studio must never post the complete browser form.' );

studio_v1_assert( false === strpos( $editor, 'stopPropagation' ), 'Studio must not capture unrelated clicks.' );
studio_v1_assert( false === strpos( $editor, 'localStorage' ) && false === strpos( $editor, 'sessionStorage' ), 'Studio state must remain server-canonical.' );

foreach ( array(
    'min-height: 100dvh',
    'position: fixed',
    'safe-area-inset-bottom',
    'faluss-link-studio__dock-indicator',
    'faluss-link-studio__context-indicator',
    'transform: translateX',
    'prefers-reduced-motion',
    'object-fit: cover',
) as $needle ) {
    studio_v1_assert( false !== strpos( $studio, $needle ), 'Studio V1 responsive shell is incomplete: ' . $needle );
}

studio_v1_assert( false === strpos( $studio, '!important' ), 'Studio V1 must not fight Elementor with !important.' );
studio_v1_assert( false === strpos( $studio, '.faluss-link-studio__content { overflow') && false === strpos( $studio, '.faluss-link-studio__main { overflow'), 'Studio V1 must keep one document scroll.' );
studio_v1_assert( false !== strpos( $link, 'card_preview_markup' ) && false !== strpos( $link, "'studio-preview'" ), 'Preview must reuse the shared card renderer.' );
foreach ( array( '.faluss-link-card--links-outline .faluss-link-card__link', '.faluss-link-card--links-solid .faluss-link-card__link', '.faluss-link-card--links-light .faluss-link-card__link' ) as $needle ) {
    studio_v1_assert( false !== strpos( $card, $needle ), 'Design V1 must retain the shared public button variants: ' . $needle );
}

studio_v1_assert( false === strpos( $link, '15 jours offerts' ) && false === strpos( $link, 'Temps limité' ), 'No fictitious subscription banner may be rendered; the Ecosystem product card may name Faluss Pro.' );

require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$project = new ReflectionMethod( 'Faluss_Link', 'studio_collections' );
$collections = $project->invoke( null, array(
    array( 'block_id' => '11111111-1111-4111-8111-111111111111', 'type' => 'link', 'label' => 'Hors collection', 'url' => 'https://example.test/free' ),
    array( 'block_id' => '22222222-2222-4222-8222-222222222222', 'type' => 'section_title', 'value' => 'Favoris' ),
    array( 'block_id' => '33333333-3333-4333-8333-333333333333', 'type' => 'text', 'value' => 'Ma sélection' ),
    array( 'block_id' => '44444444-4444-4444-8444-444444444444', 'type' => 'link', 'label' => 'Faluss', 'url' => 'https://faluss.me' ),
) );
studio_v1_assert( 1 === count( $collections ), 'Only canonical section blocks may create collections.' );
$collection = reset( $collections );
studio_v1_assert( 'Favoris' === $collection['name'] && 'Ma sélection' === $collection['description'], 'Collection name and description must project canonical blocks.' );
studio_v1_assert( 1 === count( $collection['links'] ) && 'Faluss' === $collection['links'][0]['label'], 'Collection counters and contents must use real canonical links.' );

echo "Faluss Link Studio V1 contract: OK\n";
