<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? strtoupper( (string) $value ) : null; }
function esc_url_raw( $value ) { return trim( (string) $value ); }
function esc_url( $value ) { return (string) $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function __( $value ) { return (string) $value; }
function esc_html_e( $value ) { echo esc_html( $value ); }
function esc_attr_e( $value ) { echo esc_attr( $value ); }
function wp_generate_uuid4() { return '99999999-9999-4999-8999-999999999999'; }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 17; }
function current_time() { return '2026-09-05 12:00:00'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_attachment_is_image( $id ) { return in_array( (int) $id, array( 501, 502 ), true ); }
function wp_get_attachment_image_url( $id ) { return wp_attachment_is_image( $id ) ? 'https://faluss.me/media/' . (int) $id . '.png' : ''; }
function wp_get_attachment_image( $id, $size, $icon, $attributes ) { return '<img class="' . esc_attr( $attributes['class'] ?? '' ) . '" src="' . esc_attr( wp_get_attachment_image_url( $id ) ) . '" alt="' . esc_attr( $attributes['alt'] ?? '' ) . '">'; }
class WP_Post { public $post_author = 17; public $post_mime_type = 'image/jpeg'; }
function get_post( $id ) { return wp_attachment_is_image( $id ) ? new WP_Post() : null; }
function fl12_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$fl12_options = array();
function get_option( $key, $default = false ) { global $fl12_options; return $fl12_options[ $key ] ?? $default; }
function update_option( $key, $value ) { global $fl12_options; $fl12_options[ $key ] = $value; return true; }
class FL12_WPDB {
    public $prefix = 'wp_';
    public $rows = array();
    public $card = null;
    public function prepare( $query ) { return $query; }
    public function get_results() { return $this->rows; }
    public function get_row() { return $this->card; }
    public function update( $table, $values ) { $this->card = array_merge( (array) $this->card, $values ); return 1; }
    public function query() { return 1; }
}
function fl12_luminance( $hex ) { $hex = ltrim( $hex, '#' ); $values = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ); $linear = array_map( static function( $value ) { $value /= 255; return $value <= .03928 ? $value / 12.92 : pow( ( $value + .055 ) / 1.055, 2.4 ); }, $values ); return $linear[0] * .2126 + $linear[1] * .7152 + $linear[2] * .0722; }
function fl12_contrast( $first, $second ) { $first = fl12_luminance( $first ); $second = fl12_luminance( $second ); return ( max( $first, $second ) + .05 ) / ( min( $first, $second ) + .05 ); }

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$admin = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-admin.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$card_script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-card.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );
$docs = file_get_contents( $root . '/docs/FALUSS_LINK.md' );

foreach ( array( 'editorialThreshold = 3', 'titleThreshold = 4.5', 'surfaceFor', '--fl-secondary-color-resolved', '#6F6A63' ) as $needle ) { fl12_assert( false !== strpos( $card_script . $css . $docs, $needle ), 'Editorial text must resolve from its own surface: ' . $needle ); }
fl12_assert( fl12_contrast( '#6F6A63', '#000000' ) >= 3 && fl12_contrast( '#6F6A63', '#FFFFFF' ) >= 4.5, 'Faluss editorial grey remains readable on both black and white surfaces before a fallback.' );
foreach ( array( 'position:absolute', 'linear-gradient(180deg,rgba(0,0,0,0) 0%,rgba(0,0,0,.9) 100%)', '.faluss-link-card__media-teaser-copy h3,.faluss-link-card__media-teaser-copy p{color:#FFF}', 'media-teaser--image-only' ) as $needle ) { fl12_assert( false !== strpos( $css, $needle ), 'Teaser image-only and black overlay treatment is incomplete: ' . $needle ); }
foreach ( array( 'outline_icon', 'full_logo', 'faluss-link-network-asset-picker', 'maybe_migrate_legacy_assets', 'social_variant', "'outline' => 'Icônes contour'", "'full' => 'Logos pleins'", 'social_asset_markup', 'networkAsset', 'faluss-link-card__network-asset' ) as $needle ) { fl12_assert( false !== strpos( $link . $admin . $editor . $css, $needle ), 'Managed social resource support is incomplete: ' . $needle ); }
fl12_assert( false === strpos( $editor, 'social_appearance' ) && false === strpos( $editor, 'function svg(' ), 'Studio must no longer restore the retired recolouring system.' );
fl12_assert( false !== strpos( $link, 'data-faluss-social-variant' ) && false !== strpos( $editor, 'data-faluss-social-variant' ) && false !== strpos( $editor . $css, 'faluss-link-card__social--inline' ) && false !== strpos( $css, 'width:24px;height:24px' ), 'Public cards, Studio preview, bubbles, inline mode, and mobile share the same stable managed image variant.' );

global $wpdb, $fl12_options;
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-admin.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$fl12_options[ Faluss_Link_Admin::OPTION ] = array( 'instagram' => array( 'label' => 'Instagram', 'active' => 1, 'icon' => 501 ) );
$catalog = Faluss_Link_Admin::catalog();
fl12_assert( 501 === $catalog['instagram']['outline_icon'] && 0 === $catalog['instagram']['full_logo'], 'A legacy social icon must remain available as the outline resource.' );
$migrate = new ReflectionMethod( 'Faluss_Link_Admin', 'maybe_migrate_legacy_assets' );
$migrate->setAccessible( true ); $migrate->invoke( null );
fl12_assert( 501 === $fl12_options[ Faluss_Link_Admin::OPTION ]['instagram']['outline_icon'] && 0 === $fl12_options[ Faluss_Link_Admin::OPTION ]['instagram']['full_logo'], 'The social resource migration must be additive and idempotent.' );

$wpdb = new FL12_WPDB();
$wpdb->card = array( 'faluss_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'social_links' => json_encode( array( 'networks' => array( array( 'network' => 'instagram', 'url' => 'https://instagram.com/faluss' ) ) ) ) );
$save = new ReflectionMethod( 'Faluss_Link', 'save_preferences' );
fl12_assert( $save->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', array( 'social_variant' => 'full', 'social_networks' => array( array( 'network' => 'instagram', 'url' => 'https://instagram.com/faluss' ) ) ) ), 'The selected social variant must save with valid existing social URLs.' );
$prefs = ( new ReflectionMethod( 'Faluss_Link', 'prefs' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
fl12_assert( 'full' === $prefs['social_variant'], 'The selected social variant must restore after saving.' );

$fl12_options[ Faluss_Link_Admin::OPTION ]['instagram']['full_logo'] = 502;
$profile = array( 'avatar_attachment_id' => 0, 'display_name' => 'Faluss', 'public_slug' => 'faluss', 'bio' => 'Faluss' );
$card = ( new ReflectionMethod( 'Faluss_Link', 'card_markup' ) )->invoke( null, $profile, $prefs, 'left', false, array() );
fl12_assert( false !== strpos( $card, 'faluss-link-card__social--variant-full' ) && false !== strpos( $card, 'media/502.png' ) && false !== strpos( $card, 'alt="Instagram"' ), 'Public cards must render the selected full logo asset with stable accessible markup.' );
$prefs['social_variant'] = 'outline';
$card = ( new ReflectionMethod( 'Faluss_Link', 'card_markup' ) )->invoke( null, $profile, $prefs, 'left', false, array() );
fl12_assert( false !== strpos( $card, 'faluss-link-card__social--variant-outline' ) && false !== strpos( $card, 'media/501.png' ), 'Public cards must render the outline asset for the outline variant.' );
$prefs['social_variant'] = 'full';
$fl12_options[ Faluss_Link_Admin::OPTION ]['instagram']['full_logo'] = 0;
$card = ( new ReflectionMethod( 'Faluss_Link', 'card_markup' ) )->invoke( null, $profile, $prefs, 'left', false, array() );
fl12_assert( false !== strpos( $card, 'media/501.png' ), 'A missing selected resource must fall back to the other valid managed asset.' );
$fl12_options[ Faluss_Link_Admin::OPTION ]['instagram']['outline_icon'] = 0;
$fl12_options[ Faluss_Link_Admin::OPTION ]['instagram']['full_logo'] = 0;
$card = ( new ReflectionMethod( 'Faluss_Link', 'card_markup' ) )->invoke( null, $profile, $prefs, 'left', false, array() );
fl12_assert( false === strpos( $card, 'data-faluss-network="instagram"' ), 'Missing resources must hide only that network instead of rendering a broken image.' );

$normalise = new ReflectionMethod( 'Faluss_Link', 'normalise_blocks' );
$teasers = $normalise->invoke( null, array(
    array( 'block_id' => '11111111-1111-4111-8111-111111111111', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => '', 'text' => '' ),
    array( 'block_id' => '22222222-2222-4222-8222-222222222222', 'type' => 'media_teaser', 'attachment_id' => 501, 'title' => 'Titre', 'text' => 'Description' ),
) );
$teaser_markup = ( new ReflectionMethod( 'Faluss_Link', 'public_blocks_markup' ) )->invoke( null, $teasers );
fl12_assert( false !== strpos( $teaser_markup, 'media-teaser--image-only' ) && 1 === substr_count( $teaser_markup, 'faluss-link-card__media-teaser-copy' ), 'Empty teasers must remain image-only while authored copy creates exactly one overlay.' );
echo "FL-12 contract: OK\n";
