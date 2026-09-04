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
function wp_generate_uuid4() { return '99999999-9999-4999-8999-999999999999'; }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 17; }
function current_time() { return '2026-09-05 12:00:00'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
class WP_Post { public $post_author; public $post_mime_type; public function __construct( $author, $mime ) { $this->post_author = $author; $this->post_mime_type = $mime; } }
function get_post( $id ) { return 501 === (int) $id ? new WP_Post( 17, 'image/jpeg' ) : ( 502 === (int) $id ? new WP_Post( 18, 'image/jpeg' ) : new WP_Post( 17, 'application/pdf' ) ); }
function wp_attachment_is_image( $id ) { return in_array( (int) $id, array( 501, 502 ), true ); }
function wp_get_attachment_image( $id, $size, $icon, $attributes ) { return '<img src="https://faluss.me/media/' . (int) $id . '.jpg" alt="' . esc_html( $attributes['alt'] ?? '' ) . '">'; }
function fl09_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
class FL09_WPDB {
    public $prefix = 'wp_';
    public $rows = array();
    public function prepare( $query ) { return $query; }
    public function get_results() { return $this->rows; }
    public function query() { return 1; }
    public function delete() { $this->rows = array(); return 1; }
    public function insert( $table, $data ) { $this->rows[] = array( 'block_id' => $data['block_id'], 'block_type' => $data['block_type'], 'payload' => $data['payload'] ); return 1; }
}
$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$docs = file_get_contents( $root . '/docs/FALUSS_LINK.md' );

foreach ( array( "'media_teaser' => 'Teaser média'", 'upload_teaser', 'upload_member_image', 'faluss_link_upload_teaser', 'teaserNonce', 'media_attachment_id', 'owned_image', 'hydrate_stored_block', 'valid_block_id' ) as $needle ) { fl09_assert( false !== strpos( $link, $needle ), 'Missing strict teaser or canonical hydration invariant: ' . $needle ); }
foreach ( array( 'media_teaser', 'faluss-link-content-block__select-teaser', 'faluss-link-content-block__remove-teaser', 'attachment_id', 'uploadTeaser', 'faluss_link_upload_teaser' ) as $needle ) { fl09_assert( false !== strpos( $editor, $needle ), 'The member teaser composer or live preview is incomplete: ' . $needle ); }
foreach ( array( 'faluss-link-card__media-teaser', 'aspect-ratio:16/10', 'faluss-link-content-block__media-preview', 'faluss-link-card--align-center .faluss-link-card__media-teaser-copy' ) as $needle ) { fl09_assert( false !== strpos( $css, $needle ), 'Teaser styling is not responsive, scoped, or alignment-aware: ' . $needle ); }
foreach ( array( 'fondation visuelle publique', 'vente en euros ou en ALB', 'ne possédera jamais ces règles d’accès' ) as $needle ) { fl09_assert( false !== strpos( $docs, $needle ), 'Teaser extension boundary is not documented: ' . $needle ); }

global $wpdb; $wpdb = new FL09_WPDB();
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$normalise = new ReflectionMethod( 'Faluss_Link', 'normalise_blocks' );
$source = array(
    array( 'block_id' => '11111111-1111-4111-8111-111111111111', 'type' => 'section_title', 'value' => 'Éditorial' ),
    array( 'block_id' => '22222222-2222-4222-8222-222222222222', 'type' => 'text', 'value' => 'Texte public' ),
    array( 'block_id' => '33333333-3333-4333-8333-333333333333', 'type' => 'link', 'label' => 'Découvrir', 'url' => 'https://example.test' ),
    array( 'block_id' => '44444444-4444-4444-8444-444444444444', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => 'Aperçu', 'text' => 'Une image publique.' ),
    array( 'block_id' => '55555555-5555-4555-8555-555555555555', 'type' => 'media_teaser', 'attachment_id' => 502, 'title' => 'Refusé' ),
    array( 'block_id' => '66666666-6666-4666-8666-666666666666', 'type' => 'media_teaser', 'attachment_id' => 503, 'title' => 'Refusé' ),
    array( 'type' => 'premium_lock', 'value' => 'Refusé' ),
);
$blocks = $normalise->invoke( null, $source );
fl09_assert( array( 'section_title', 'text', 'link', 'media_teaser' ) === array_column( $blocks, 'type' ), 'Only the four valid FL-09 types may be persisted in their authored order.' );
fl09_assert( 501 === $blocks[3]['attachment_id'] && 'Aperçu' === $blocks[3]['title'], 'A member-owned image teaser must retain its strict payload.' );
fl09_assert( 4 === count( array_unique( array_column( $blocks, 'block_id' ) ) ), 'Valid blocks retain stable unique identifiers.' );

$wpdb->rows = array();
foreach ( $blocks as $block ) { $payload = $block; unset( $payload['block_id'], $payload['type'] ); $wpdb->rows[] = array( 'block_id' => $block['block_id'], 'block_type' => $block['type'], 'payload' => json_encode( $payload ) ); }
$stored = ( new ReflectionMethod( 'Faluss_Link', 'stored_blocks' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
fl09_assert( array_column( $blocks, 'block_id' ) === array_column( $stored['blocks'], 'block_id' ), 'Studio and public rendering rehydrate the same stable block identities and order.' );
fl09_assert( $blocks === $stored['blocks'], 'Stored blocks must preserve the complete normalized payload of all four types.' );
$reordered = $normalise->invoke( null, array( $source[3], $source[0], $source[2] ) );
fl09_assert( array( $source[3]['block_id'], $source[0]['block_id'], $source[2]['block_id'] ) === array_column( $reordered, 'block_id' ), 'Reordering or deleting blocks retains remaining IDs without duplication.' );
$saved = ( new ReflectionMethod( 'Faluss_Link', 'save_blocks' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $reordered );
$after_save = ( new ReflectionMethod( 'Faluss_Link', 'stored_blocks' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
fl09_assert( $saved && array_column( $reordered, 'block_id' ) === array_column( $after_save['blocks'], 'block_id' ) && 3 === count( $after_save['blocks'] ), 'Saving a modified, deleted, and reordered composition must update the same public blocks without duplicates.' );
$markup = ( new ReflectionMethod( 'Faluss_Link', 'public_blocks_markup' ) )->invoke( null, $stored['blocks'] );
foreach ( array( 'faluss-link-card__section-title', 'faluss-link-card__content-text', 'faluss-link-card__link', 'faluss-link-card__media-teaser', 'rel="noopener noreferrer nofollow"' ) as $needle ) { fl09_assert( false !== strpos( $markup, $needle ), 'Public normalized block rendering is incomplete: ' . $needle ); }
fl09_assert( false === strpos( $link, 'premium_lock' ) && false === strpos( $link, 'paywall' ) && false === strpos( $link, 'checkout' ), 'FL-09 must not implement premium, payment, or access mechanics.' );
echo "FL-09 contract: OK\n";
