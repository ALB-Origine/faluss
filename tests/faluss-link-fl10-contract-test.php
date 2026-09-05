<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value ) { return trim( (string) $value ); }
function esc_url( $value ) { return (string) $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_generate_uuid4() { return '99999999-9999-4999-8999-999999999999'; }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 17; }
function current_time() { return '2026-09-05 12:00:00'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
class WP_Post { public $post_author; public $post_mime_type; public function __construct( $author, $mime ) { $this->post_author = $author; $this->post_mime_type = $mime; } }
function get_post( $id ) { return 501 === (int) $id ? new WP_Post( 17, 'image/jpeg' ) : ( 502 === (int) $id ? new WP_Post( 17, 'image/webp' ) : new WP_Post( 18, 'image/jpeg' ) ); }
function wp_attachment_is_image( $id ) { return in_array( (int) $id, array( 501, 502 ), true ); }
function wp_get_attachment_image( $id, $size, $icon, $attributes ) { return '<img src="https://faluss.me/media/' . (int) $id . '.jpg" alt="' . esc_html( $attributes['alt'] ?? '' ) . '">'; }
function fl10_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
class FL10_WPDB {
    public $prefix = 'wp_';
    public $rows = array();
    public function prepare( $query ) { return $query; }
    public function get_results() { return $this->rows; }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$card_script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-card.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$docs = file_get_contents( $root . '/docs/FALUSS_LINK.md' );

foreach ( array( 'content_blocks', 'stored_blocks', 'hydrate_stored_block', 'normalise_blocks', 'public_block_markup', 'data-block-id', 'blockRenderers', 'blockData', 'media_teaser' ) as $needle ) { fl10_assert( false !== strpos( $link . $editor, $needle ), 'The normalized block rehydration path is incomplete: ' . $needle ); }
foreach ( array( 'studio_tab', 'faluss_studio_tab', 'data-fl-active-tab', 'aria-selected', 'aria-controls', 'role="tabpanel"', 'window.scrollTo({ top: 0, behavior: \'auto\' })' ) as $needle ) { fl10_assert( false !== strpos( $link . $editor, $needle ), 'Studio tab resume is not safe or accessible: ' . $needle ); }
foreach ( array( "'outline' => 'Icônes contour'", "'full' => 'Logos pleins'", 'social_variant', 'network_catalog_for_client', 'faluss-link-card__social--variant-' ) as $needle ) { fl10_assert( false !== strpos( $link . $editor . $css, $needle ), 'The normalized social resource variant is incomplete: ' . $needle ); }
foreach ( array( 'relativeLuminance', 'contrastRatio', 'titleThreshold = 4.5', 'editorialThreshold = 3', 'bestContrastColor', 'effectiveSurface', '--fl-title-color-resolved', '--fl-secondary-color-resolved' ) as $needle ) { fl10_assert( false !== strpos( $card_script . $css, $needle ), 'The contrast resolver does not use the effective surface: ' . $needle ); }
foreach ( array( 'faluss-link-card', 'FalussLinkCard', 'get_script_depends() { return array( \'faluss-link-card\', \'faluss-link-immersive\' ); }' ) as $needle ) { fl10_assert( false !== strpos( $link . $widgets . $card_script, $needle ), 'The public card contrast script is not explicitly available to Elementor: ' . $needle ); }
foreach ( array( 'fondation visuelle publique', 'thèmes de carte', 'paramètre local limité à cet onglet' ) as $needle ) { fl10_assert( false !== strpos( $docs, $needle ), 'FL-10 extension boundary or member behaviour is undocumented: ' . $needle ); }

global $wpdb; $wpdb = new FL10_WPDB();
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$normalise = new ReflectionMethod( 'Faluss_Link', 'normalise_blocks' );
$source = array(
    array( 'block_id' => '11111111-1111-4111-8111-111111111111', 'type' => 'section_title', 'value' => 'Le titre' ),
    array( 'block_id' => '22222222-2222-4222-8222-222222222222', 'type' => 'text', 'value' => 'Le texte' ),
    array( 'block_id' => '33333333-3333-4333-8333-333333333333', 'type' => 'link', 'label' => 'Le lien', 'url' => 'https://example.test/link' ),
    array( 'block_id' => '44444444-4444-4444-8444-444444444444', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => 'Le teaser', 'text' => 'Le descriptif' ),
    array( 'block_id' => '33333333-3333-4333-8333-333333333333', 'type' => 'link', 'label' => 'Doublon', 'url' => 'https://example.test/duplicate' ),
    array( 'block_id' => '55555555-5555-4555-8555-555555555555', 'type' => 'unknown', 'value' => 'Refusé' ),
);
$blocks = $normalise->invoke( null, $source );
fl10_assert( array( 'section_title', 'text', 'link', 'media_teaser' ) === array_column( $blocks, 'type' ), 'All four valid block types must keep their authored order and unknown types are refused.' );
fl10_assert( 4 === count( $blocks ) && 4 === count( array_unique( array_column( $blocks, 'block_id' ) ) ), 'Repeated block IDs must not duplicate a public or Studio block.' );
foreach ( $blocks as $block ) { $payload = $block; unset( $payload['block_id'], $payload['type'] ); $wpdb->rows[] = array( 'block_id' => $block['block_id'], 'block_type' => $block['type'], 'payload' => json_encode( $payload ) ); }
$stored = ( new ReflectionMethod( 'Faluss_Link', 'stored_blocks' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
fl10_assert( $blocks === $stored['blocks'], 'Studio and public rendering must rehydrate exactly the same block identifiers, data, and order.' );
$markup = ( new ReflectionMethod( 'Faluss_Link', 'public_blocks_markup' ) )->invoke( null, $stored['blocks'] );
foreach ( array( 'faluss-link-card__section-title', 'faluss-link-card__content-text', 'faluss-link-card__link', 'faluss-link-card__media-teaser', 'rel="noopener noreferrer nofollow"' ) as $needle ) { fl10_assert( false !== strpos( $markup, $needle ), 'A normalized public block is missing from the public renderer: ' . $needle ); }
$tab = new ReflectionMethod( 'Faluss_Link', 'studio_tab' );
fl10_assert( 'links' === $tab->invoke( null, 'links' ) && 'profile' === $tab->invoke( null, 'https://outside.test' ), 'Only local Studio tab names may survive the redirect.' );
$variant = new ReflectionMethod( 'Faluss_Link', 'social_variant' );
fl10_assert( 'outline' === $variant->invoke( null, 'invalid' ) && 'full' === $variant->invoke( null, 'full' ), 'Social resource variants are strictly allowlisted with an outline default.' );
echo "FL-10 contract: OK\n";
