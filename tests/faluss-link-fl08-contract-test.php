<?php
define( 'ABSPATH', __DIR__ . '/' );
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value ) { return trim( (string) $value ); }
function wp_generate_uuid4() { return '99999999-9999-4999-8999-999999999999'; }
function fl08_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
$root = dirname( __DIR__ );
$schema = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php' );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$js = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$docs = file_get_contents( $root . '/docs/FALUSS_LINK.md' );

foreach ( array( 'faluss_link_blocks', 'block_id', 'sort_order', 'block_type', 'payload', 'faluss_block_id', 'faluss_block_order', 'ENGINE=InnoDB', 'GET_LOCK', 'verify_blocks', 'partial installation: never repair it' ) as $needle ) { fl08_assert( false !== strpos( $schema, $needle ), 'Missing strict FL-08 blocks storage invariant: ' . $needle ); }
foreach ( array( "'section_title' => 'Titre de section'", "'text' => 'Texte'", "'link' => 'Lien'", 'normalise_blocks', 'normalise_block', 'block_url', 'array_slice', 'content_blocks', 'migrate_legacy_links', 'legacy_block_id', 'save_blocks' ) as $needle ) { fl08_assert( false !== strpos( $link, $needle ), 'Missing FL-08 block type, validation, or migration invariant: ' . $needle ); }
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$normalise = new ReflectionMethod( 'Faluss_Link', 'normalise_blocks' );
$blocks = $normalise->invoke( null, array(
    array( 'block_id' => '11111111-1111-4111-8111-111111111111', 'type' => 'section_title', 'value' => 'À découvrir' ),
    array( 'block_id' => '22222222-2222-4222-8222-222222222222', 'type' => 'text', 'value' => "Un texte\néditorial." ),
    array( 'block_id' => '33333333-3333-4333-8333-333333333333', 'type' => 'link', 'label' => 'Mon lien', 'url' => 'https://example.test/bonjour' ),
    array( 'type' => 'link', 'label' => 'Refusé', 'url' => 'http://example.test' ),
    array( 'type' => 'shop', 'value' => 'Refusé' ),
    array( 'type' => 'text', 'value' => '' ),
) );
fl08_assert( 3 === count( $blocks ), 'Only valid v1 blocks may be stored.' );
fl08_assert( array( 'section_title', 'text', 'link' ) === array_column( $blocks, 'type' ), 'Validated blocks must retain their authored order.' );
fl08_assert( 'https://example.test/bonjour' === $blocks[2]['url'], 'A valid HTTPS link must retain its URL.' );
foreach ( array( 'faluss-link-content-composer', 'content_blocks', 'data-fl-block-action="up"', 'data-fl-block-action="down"', 'data-fl-block-action="remove"', 'Ajouter un élément' ) as $needle ) { fl08_assert( false !== strpos( $link, $needle ), 'Composer action or semantic is missing: ' . $needle ); }
$composer = substr( $link, strpos( $link, 'private static function content_composer' ), strpos( $link, 'private static function page_background_field' ) - strpos( $link, 'private static function content_composer' ) );
fl08_assert( false === strpos( $composer, 'position' ), 'The member composer must not expose technical positions.' );
foreach ( array( 'function contentBlock', 'renumberBlocks', 'data-fl-block-action', 'content_blocks[', 'function updateLinks', 'faluss-link-card__section-title', 'faluss-link-card__content-text' ) as $needle ) { fl08_assert( false !== strpos( $js, $needle ), 'Composer updates, reorders, or preview rendering are incomplete: ' . $needle ); }
foreach ( array( 'public_blocks_markup', 'faluss-link-card__section-title', 'faluss-link-card__content-text', 'rel="noopener noreferrer nofollow"', 'esc_html', 'esc_url' ) as $needle ) { fl08_assert( false !== strpos( $link, $needle ), 'Public v1 block rendering is incomplete or unsafe: ' . $needle ); }
foreach ( array( 'faluss-link-card__content-blocks', 'faluss-link-card__section-title', 'faluss-link-content-composer', 'faluss-link-card--align-center', '--fl-page-background', 'faluss-link-card__social' ) as $needle ) { fl08_assert( false !== strpos( $css, $needle ), 'Block rendering regresses card style, alignment, colors, or socials: ' . $needle ); }
foreach ( array( 'pro.faluss', 'contenu verrouillé', 'récompense quotidienne', 'paiement, de token ou d’abonnement' ) as $needle ) { fl08_assert( false !== strpos( $docs, $needle ), 'Future block boundary is not documented: ' . $needle ); }
fl08_assert( false === strpos( $link, 'passwordless' ) && false === strpos( $link, 'client_secret' ) && false === strpos( $link, 'subscription' ), 'FL-08 must not introduce authentication or business engines.' );
echo "FL-08 contract: OK\n";
