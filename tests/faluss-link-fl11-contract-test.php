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
function __( $value ) { return (string) $value; }
function esc_html_e( $value ) { echo esc_html( $value ); }
function esc_attr_e( $value ) { echo esc_attr( $value ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? strtoupper( (string) $value ) : null; }
function wp_generate_uuid4() { return '99999999-9999-4999-8999-999999999999'; }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 17; }
function current_time() { return '2026-09-05 12:00:00'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
class WP_Post { public $post_author = 17; public $post_mime_type = 'image/jpeg'; }
function get_post( $id ) { return 501 === (int) $id ? new WP_Post() : null; }
function wp_attachment_is_image( $id ) { return 501 === (int) $id; }
function wp_get_attachment_image( $id, $size, $icon, $attributes ) { return '<img src="https://faluss.me/media/' . (int) $id . '.jpg" alt="' . esc_attr( $attributes['alt'] ?? '' ) . '">'; }
function fl11_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
class FL11_WPDB {
    public $prefix = 'wp_';
    public $rows = array();
    public $card = null;
    public function prepare( $query ) { return $query; }
    public function get_results() { return $this->rows; }
    public function get_row() { return $this->card; }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$card_script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-card.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );
$docs = file_get_contents( $root . '/docs/FALUSS_LINK.md' );

foreach ( array( 'surfaceFor', 'opaqueCssColor', 'contrastRatio(preferred, surface) >= threshold', '.faluss-link-card__media-teaser-copy h3', '.faluss-link-card__media-teaser-copy p', '--fl-title-color-resolved', '--fl-secondary-color-resolved' ) as $needle ) {
    fl11_assert( false !== strpos( $card_script . $css, $needle ), 'Contrast must be resolved on each editorial surface: ' . $needle );
}
fl11_assert( false === strpos( $card_script, "readable(node, '--fl-secondary-color', effectiveSurface(card))" ), 'Secondary editorial text must not be forced from the card-wide surface.' );

foreach ( array( "'automatic' => 'Automatique'", "'black' => 'Noir'", "'white' => 'Blanc'", "'name' => 'Couleur du nom'", "'official' => 'Couleurs officielles'", "'full' => 'Logos complets'", "'social_appearance' => \$social_appearance", "data-faluss-social-appearance", 'faluss-link-card__social--appearance-' ) as $needle ) {
    fl11_assert( false !== strpos( $link . $editor . $css, $needle ), 'A persisted social appearance mode is incomplete: ' . $needle );
}
fl11_assert( false !== strpos( $editor, 'function updateSocials' ) && false !== strpos( $editor, "studio.find('[name=\"social_appearance\"]')" ), 'Studio must apply the selected social appearance immediately to its live preview.' );

foreach ( array( "'landscape' => 'Paysage'", "'portrait' => 'Portrait'", "'square' => 'Carré'", 'teaser_format', 'media-teaser--image-only', 'media-teaser--format-', 'teaserFormatField', 'ensureTeaserFormats' ) as $needle ) {
    fl11_assert( false !== strpos( $link . $editor . $css, $needle ), 'The media teaser format or image-only state is missing: ' . $needle );
}
foreach ( array( 'Paysage', 'Portrait', 'Carré', 'politique d’accès réelle' ) as $needle ) { fl11_assert( false !== strpos( $docs, $needle ), 'The three public teaser formats and their access boundary must be documented: ' . $needle ); }

global $wpdb; $wpdb = new FL11_WPDB();
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$normalise = new ReflectionMethod( 'Faluss_Link', 'normalise_blocks' );
$source = array(
    array( 'block_id' => '11111111-1111-4111-8111-111111111111', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => '', 'text' => '', 'format' => 'landscape' ),
    array( 'block_id' => '22222222-2222-4222-8222-222222222222', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => 'Portrait', 'text' => 'Texte', 'format' => 'portrait' ),
    array( 'block_id' => '33333333-3333-4333-8333-333333333333', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => 'Carré', 'text' => '', 'format' => 'square' ),
    array( 'block_id' => '44444444-4444-4444-8444-444444444444', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => 'Repli', 'text' => '', 'format' => 'invalid' ),
);
$blocks = $normalise->invoke( null, $source );
fl11_assert( array( 'landscape', 'portrait', 'square', 'landscape' ) === array_column( $blocks, 'format' ), 'Only the three allowlisted teaser formats persist, with a landscape fallback.' );
foreach ( $blocks as $block ) { $payload = $block; unset( $payload['block_id'], $payload['type'] ); $wpdb->rows[] = array( 'block_id' => $block['block_id'], 'block_type' => $block['type'], 'payload' => json_encode( $payload ) ); }
$stored = ( new ReflectionMethod( 'Faluss_Link', 'stored_blocks' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
fl11_assert( $blocks === $stored['blocks'], 'Studio and public card must rehydrate the same normalized teaser order and formats.' );
$markup = ( new ReflectionMethod( 'Faluss_Link', 'public_blocks_markup' ) )->invoke( null, $stored['blocks'] );
fl11_assert( false !== strpos( $markup, 'faluss-link-card__media-teaser--format-landscape faluss-link-card__media-teaser--image-only' ), 'A teaser without copy must render image-only without a content surface.' );
fl11_assert( 0 === substr_count( $markup, 'faluss-link-card__media-teaser-copy' ) || false !== strpos( $markup, '>Portrait<' ), 'Only authored teaser copy may create an editorial teaser surface.' );
foreach ( array( 'media-teaser--format-portrait', 'media-teaser--format-square' ) as $needle ) { fl11_assert( false !== strpos( $markup, $needle ), 'The public teaser must expose its selected image geometry: ' . $needle ); }

$appearance = new ReflectionMethod( 'Faluss_Link', 'social_appearance' );
foreach ( array( 'automatic', 'black', 'white', 'name', 'official', 'full' ) as $mode ) {
    fl11_assert( $mode === $appearance->invoke( null, $mode ), 'The saved social mode must be allowlisted: ' . $mode );
    $wpdb->card = array( 'faluss_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'social_links' => json_encode( array( 'networks' => array(), 'social_appearance' => $mode ) ) );
    $prefs = ( new ReflectionMethod( 'Faluss_Link', 'prefs' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
    fl11_assert( $mode === $prefs['social_appearance'], 'The saved social mode must survive preference reloading: ' . $mode );
    $prefs['social_links'] = json_encode( array( 'networks' => array( array( 'network' => 'instagram', 'url' => 'https://instagram.com/faluss' ) ) ) );
    $profile = array( 'avatar_attachment_id' => 0, 'display_name' => 'Faluss', 'public_slug' => 'faluss', 'bio' => 'Une bio' );
    $card = ( new ReflectionMethod( 'Faluss_Link', 'card_markup' ) )->invoke( null, $profile, $prefs, 'left', false, array() );
    fl11_assert( false !== strpos( $card, 'faluss-link-card__social--appearance-' . $mode ) && false !== strpos( $card, 'data-faluss-social-appearance="' . $mode . '"' ), 'The public card must emit the saved social mode: ' . $mode );
}
fl11_assert( 'automatic' === $appearance->invoke( null, 'untrusted' ), 'Unknown social appearance modes must fail closed.' );
echo "FL-11 contract: OK\n";
