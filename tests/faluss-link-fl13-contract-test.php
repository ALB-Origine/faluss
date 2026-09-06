<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) ); }
function sanitize_title( $value ) { return trim( strtolower( preg_replace( '/[^a-z0-9-]/', '', (string) $value ) ) ); }
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
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { return '99999999-9999-4999-8999-999999999999'; }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 17; }
function current_time() { return '2026-09-05 12:00:00'; }
function is_admin() { return false; }
function is_user_logged_in() { return false; }
function get_query_var( $key ) { return ''; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value ); }

$fl13_filters = array(); $fl13_actions = array(); $fl13_asset_sizes = array();
function add_filter( $hook, $callback, $priority = 10 ) { global $fl13_filters; $fl13_filters[ $hook ][] = $callback; }
function do_action( $hook ) { global $fl13_actions; $fl13_actions[] = $hook; }
function nocache_headers() { global $fl13_actions; $fl13_actions[] = 'nocache_headers'; }
function wp_attachment_is_image( $id ) { return in_array( (int) $id, array( 501, 502 ), true ); }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { global $fl13_asset_sizes; $fl13_asset_sizes[] = $size; return wp_attachment_is_image( $id ) ? 'https://faluss.me/media/' . (int) $id . '-' . $size . '.png' : ''; }
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attributes = array() ) { return '<img src="' . esc_attr( wp_get_attachment_image_url( $id, $size ) ) . '" alt="' . esc_attr( $attributes['alt'] ?? '' ) . '">'; }
function wp_get_attachment_image_srcset( $id, $size = 'thumbnail' ) { return wp_attachment_is_image( $id ) ? 'https://faluss.me/media/' . (int) $id . '-small.png 320w, https://faluss.me/media/' . (int) $id . '-full.png 1024w' : ''; }
function wp_get_attachment_image_sizes( $id, $size = 'thumbnail' ) { return wp_attachment_is_image( $id ) ? '(max-width: 480px) 24px, 24px' : ''; }
function get_post_modified_time( $format, $gmt, $id ) { return 1700000000 + (int) $id; }
class WP_Post { public $post_author = 17; public $post_mime_type = 'image/jpeg'; }
function get_post( $id ) { return wp_attachment_is_image( $id ) ? new WP_Post() : null; }

function fl13_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
class FL13_Request { public $query_vars; public function __construct( $query_vars ) { $this->query_vars = $query_vars; } }
class FL13_WPDB {
    public $prefix = 'wp_'; public $card = null;
    public function prepare( $query ) { return $query; }
    public function get_row() { return $this->card; }
    public function get_results() { return array(); }
    public function update( $table, $values ) { $this->card = array_merge( (array) $this->card, $values ); return 1; }
    public function query() { return 1; }
}
$fl13_options = array();
function get_option( $key, $default = false ) { global $fl13_options; return $fl13_options[ $key ] ?? $default; }
function update_option( $key, $value ) { global $fl13_options; $fl13_options[ $key ] = $value; return true; }

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );

foreach ( array( "add_action( 'parse_request'", 'DONOTCACHEPAGE', 'litespeed_control_set_nocache', 'public_profile_no_cache_headers', "'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0'" ) as $needle ) { fl13_assert( false !== strpos( $link, $needle ), 'Public profile cache exclusion is incomplete: ' . $needle ); }
foreach ( array( 'wp_get_attachment_image_url( $attachment_id, \'full\' )', 'wp_get_attachment_image_srcset', 'wp_get_attachment_image_sizes', 'get_post_modified_time', 'versioned_attachment_srcset', 'social_image_markup' ) as $needle ) { fl13_assert( false !== strpos( $link, $needle ), 'Managed social assets must use original responsive versioned media: ' . $needle ); }
foreach ( array( 'validAsset', 'asset.srcset', 'asset.sizes', 'networkAsset' ) as $needle ) { fl13_assert( false !== strpos( $editor, $needle ), 'Studio preview must use the same managed social asset payload: ' . $needle ); }
fl13_assert( false !== strpos( $css, '.faluss-link-card--align-center .faluss-link-card__avatar' ) && false === strpos( $immersive, '.faluss-link-card--presentation-immersive.faluss-link-card--align-center .faluss-link-card__avatar' ), 'The shared card alignment class must center the avatar in Studio and public output.' );
foreach ( array( 'body.faluss-identity-public-route > .faluss-identity-public-header-layer', 'position: absolute;', 'z-index: 1;', 'body.faluss-identity-public-route > .faluss-identity-profile-page', 'margin: 0;' ) as $needle ) { fl13_assert( false !== strpos( $immersive, $needle ), 'The isolated public shell must overlay the header without a pre-hero gap: ' . $needle ); }
fl13_assert( false === strpos( $editor, 'function svg(' ) && false === strpos( $editor, 'social_appearance' ), 'Studio must not restore a CSS-recoloured or generic social fallback.' );

global $wpdb, $fl13_options, $fl13_filters, $fl13_actions, $fl13_asset_sizes;
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-admin.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';

Faluss_Link::exclude_public_profile_from_cache( new FL13_Request( array() ) );
fl13_assert( empty( $fl13_filters ) && empty( $fl13_actions ) && ! defined( 'DONOTCACHEPAGE' ), 'Non-profile requests must not receive Faluss public cache exclusions.' );
Faluss_Link::exclude_public_profile_from_cache( new FL13_Request( array( 'faluss_public_profile' => 'origin' ) ) );
fl13_assert( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE && ! empty( $fl13_filters['wp_headers'] ) && in_array( 'litespeed_control_set_nocache', $fl13_actions, true ), 'Only a Faluss public profile request must enable WordPress and LiteSpeed no-cache handling.' );
$headers = call_user_func( $fl13_filters['wp_headers'][0], array( 'X-Existing' => 'keep' ) );
fl13_assert( 'no-store, no-cache, must-revalidate, max-age=0' === $headers['Cache-Control'] && 'no-cache' === $headers['X-LiteSpeed-Cache-Control'], 'Public profile responses must emit strict no-cache headers.' );

$fl13_options[ Faluss_Link_Admin::OPTION ] = array( 'instagram' => array( 'label' => 'Instagram', 'active' => 1, 'outline_icon' => 501, 'full_logo' => 502 ) );
$assets = ( new ReflectionMethod( 'Faluss_Link', 'network_catalog_for_client' ) )->invoke( null );
fl13_assert( false === in_array( 'thumbnail', $fl13_asset_sizes, true ) && false !== strpos( $assets['instagram']['full']['src'], '502-full.png?ver=1700000502' ) && false !== strpos( $assets['instagram']['full']['srcset'], '?ver=1700000502' ) && '' !== $assets['instagram']['full']['sizes'], 'Social resources must use versioned full originals with responsive WordPress metadata.' );

$wpdb = new FL13_WPDB();
$wpdb->card = array( 'faluss_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'social_links' => json_encode( array( 'networks' => array( array( 'network' => 'instagram', 'url' => 'https://instagram.com/faluss' ) ), 'social_variant' => 'full' ) ) );
$prefs = ( new ReflectionMethod( 'Faluss_Link', 'prefs' ) )->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
$profile = array( 'avatar_attachment_id' => 501, 'display_name' => 'Origin', 'public_slug' => 'origin', 'bio' => 'Bio' );
$card_markup = new ReflectionMethod( 'Faluss_Link', 'card_markup' );
$anonymous_full = $card_markup->invoke( null, $profile, $prefs, 'center', false, array() );
fl13_assert( 'full' === $prefs['social_variant'] && false !== strpos( $anonymous_full, 'faluss-link-card--align-center' ) && false !== strpos( $anonymous_full, 'faluss-link-card__network-asset' ) && false !== strpos( $anonymous_full, '502-full.png?ver=1700000502' ) && false !== strpos( $anonymous_full, 'srcset=' ) && false !== strpos( $anonymous_full, 'alt="Instagram"' ), 'An anonymous public card must render its persisted full social variant and responsive original asset.' );
$anonymous_left = $card_markup->invoke( null, $profile, $prefs, 'left', true, array() );
fl13_assert( false !== strpos( $anonymous_left, 'faluss-link-card--align-left' ) && false !== strpos( $anonymous_left, 'faluss-link-card__avatar' ), 'Studio and public markup must share left alignment and avatar composition classes.' );
$fl13_options[ Faluss_Link_Admin::OPTION ]['instagram']['full_logo'] = 0;
$fallback = $card_markup->invoke( null, $profile, $prefs, 'center', false, array() );
fl13_assert( false !== strpos( $fallback, '501-full.png?ver=1700000501' ), 'A missing selected asset must fall back to the other managed original.' );
$fl13_options[ Faluss_Link_Admin::OPTION ]['instagram']['outline_icon'] = 0;
$hidden = $card_markup->invoke( null, $profile, $prefs, 'center', false, array() );
fl13_assert( false === strpos( $hidden, 'data-faluss-network="instagram"' ) && false === strpos( $hidden, '<img class="faluss-link-card__network-asset" src=""' ), 'A network without a valid asset must be hidden without a broken image or CSS fallback.' );

echo "FL-13 contract: OK\n";
