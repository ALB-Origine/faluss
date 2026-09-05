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
function selected() {}
function checked() {}
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { return '99999999-9999-4999-8999-999999999999'; }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 17; }
function current_time() { return '2026-09-05 12:00:00'; }
function is_admin() { return false; }
function wp_attachment_is_image( $id ) { return in_array( (int) $id, array( 501, 502 ), true ); }
function wp_get_attachment_image_url( $id, $size = 'medium' ) { return wp_attachment_is_image( $id ) ? 'https://faluss.me/media/' . (int) $id . '-' . $size . '.png' : ''; }
function wp_get_attachment_image( $id, $size, $icon, $attributes ) { return '<img src="' . esc_attr( wp_get_attachment_image_url( $id, $size ) ) . '" alt="' . esc_attr( $attributes['alt'] ?? '' ) . '">'; }
function get_post() { return null; }
function fl15_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$fl15_options = array();
function get_option( $key, $default = false ) { global $fl15_options; return $fl15_options[ $key ] ?? $default; }
function update_option( $key, $value ) { global $fl15_options; $fl15_options[ $key ] = $value; return true; }

class FL15_WPDB {
    public $prefix = 'wp_'; public $card;
    public function prepare( $query ) { return $query; }
    public function get_row() { return $this->card; }
    public function get_results() { return array(); }
    public function update( $table, $values ) { $this->card = array_merge( (array) $this->card, $values ); return 1; }
    public function query() { return 1; }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' ) . file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
foreach ( array( 'system_card_theme', 'catalog_theme', 'class_exists( \'Faluss_Catalog_Themes\' )', 'selected_theme', 'theme_overrides', 'theme_picker', 'catalog_themes_for_client' ) as $needle ) { fl15_assert( false !== strpos( $link, $needle ), 'FL-15 theme fallback or persistence invariant missing: ' . $needle ); }
foreach ( array( 'applyTheme', 'markThemeOverride', 'faluss-link-theme-picker__theme', 'theme_overrides' ) as $needle ) { fl15_assert( false !== strpos( $editor . $css, $needle ), 'Studio theme selection must update the live card and bounded overrides: ' . $needle ); }
foreach ( array( 'faluss-link-card--links-light', '--fl-page-background:{{VALUE}} !important;', '--fl-name-color:{{VALUE}} !important;' ) as $needle ) { fl15_assert( false !== strpos( $css . $widgets, $needle ), 'Link styles and explicit Elementor local priority are incomplete: ' . $needle ); }
fl15_assert( false === strpos( $link, 'subscription' ) && false === strpos( $link, 'payment' ) && false === strpos( $link, 'premium' ), 'Faluss Link must not invent economic or entitlement logic for themes.' );

global $wpdb, $fl15_options;
require_once $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$fl15_options[ Faluss_Catalog_Themes::OPTION ] = array(
    'night' => array( 'name' => 'Nuit', 'slug' => 'night', 'active' => 1, 'sort_order' => 2, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#101010', 'hero_transition_color' => '#222222', 'name_color' => '#FFFFFF', 'alignment' => 'center', 'social_variant' => 'full', 'link_style' => 'dark' ),
    'bright' => array( 'name' => 'Clair', 'slug' => 'bright', 'active' => 1, 'sort_order' => 3, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#FFFFFF', 'hero_transition_color' => '#FFFDF5', 'name_color' => '#000000', 'alignment' => 'left', 'social_variant' => 'outline', 'link_style' => 'light' ),
    'line' => array( 'name' => 'Ligne', 'slug' => 'line', 'active' => 1, 'sort_order' => 4, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#FFFFFF', 'hero_transition_color' => '#FFFDF5', 'name_color' => '#000000', 'alignment' => 'left', 'social_variant' => 'outline', 'link_style' => 'outline' ),
);
$wpdb = new FL15_WPDB();
$wpdb->card = array( 'faluss_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'social_links' => json_encode( array( 'networks' => array(), 'selected_theme' => 'night', 'theme_overrides' => array() ) ) );
$prefs_method = new ReflectionMethod( 'Faluss_Link', 'prefs' );
$prefs = $prefs_method->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
fl15_assert( 'night' === $prefs['selected_theme'] && '#101010' === $prefs['page_background'] && '#222222' === $prefs['hero_transition_color'] && '#FFFFFF' === $prefs['name_color'] && 'center' === $prefs['alignment'] && 'full' === $prefs['social_variant'] && 'solid' === $prefs['link_style'], 'The cascade must apply the selected catalogue theme after the system fallback.' );

$save = new ReflectionMethod( 'Faluss_Link', 'save_preferences' );
fl15_assert( $save->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', array( 'selected_theme' => 'night', 'theme_overrides' => json_encode( array( 'name_color', 'link_style' ) ), 'name_color' => '#82206B', 'link_style' => 'light', 'social_networks' => array() ) ), 'A selected theme and bounded personal overrides must persist.' );
$prefs = $prefs_method->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
fl15_assert( 'night' === $prefs['theme_reference'] && '#101010' === $prefs['page_background'] && '#82206B' === $prefs['name_color'] && 'light' === $prefs['link_style'], 'Personal color and link changes must override the theme while its other values remain active.' );

$card = new ReflectionMethod( 'Faluss_Link', 'card_markup' );
$profile = array( 'avatar_attachment_id' => 0, 'display_name' => 'Origin', 'public_slug' => 'origin', 'bio' => '' );
$light_markup = $card->invoke( null, $profile, $prefs, $prefs['alignment'], false, array() );
fl15_assert( false !== strpos( $light_markup, 'data-faluss-card-theme="night"' ) && false !== strpos( $light_markup, 'faluss-link-card--links-light' ), 'The public card must render the persisted theme identity and the light link style.' );
foreach ( array( 'bright', 'line' ) as $slug ) { $wpdb->card['social_links'] = json_encode( array( 'networks' => array(), 'selected_theme' => $slug, 'theme_overrides' => array() ) ); $resolved = $prefs_method->invoke( null, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' ); $markup = $card->invoke( null, $profile, $resolved, $resolved['alignment'], false, array() ); fl15_assert( false !== strpos( $markup, 'faluss-link-card--links-' . ( 'bright' === $slug ? 'light' : 'outline' ) ), 'All three catalogue button styles must render through the shared card.' ); }

echo "FL-15 contract: OK\n";
