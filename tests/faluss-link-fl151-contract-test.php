<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

function add_action() {}
function add_shortcode() {}
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
function wp_attachment_is_image() { return false; }
function wp_get_attachment_image_url() { return ''; }
function wp_get_attachment_image() { return ''; }
function get_post() { return null; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function fl151_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$fl151_options = array();
function get_option( $key, $default = false ) { global $fl151_options; return $fl151_options[ $key ] ?? $default; }
function update_option( $key, $value ) { global $fl151_options; $fl151_options[ $key ] = $value; return true; }

class FL151_WPDB {
    public $prefix = 'wp_';
    public $card = array();
    public $rows = array();
    public $updates = 0;
    public function prepare( $query ) { return $query; }
    public function get_row() { return $this->card; }
    public function get_results() { return $this->rows; }
    public function update( $table, $values, $where = array() ) {
        ++$this->updates;
        if ( isset( $where['faluss_id'] ) && ( $this->card['faluss_id'] ?? '' ) === $where['faluss_id'] ) {
            $this->card = array_merge( $this->card, $values );
        }
        foreach ( $this->rows as $index => $row ) {
            if ( ( $row['faluss_id'] ?? '' ) === ( $where['faluss_id'] ?? '' ) ) {
                $this->rows[ $index ] = array_merge( $row, $values );
            }
        }
        return 1;
    }
    public function query() { return 1; }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
foreach ( array( 'resolve_card_presentation', 'get_active_theme', 'migrate_deactivated_theme_references', 'migrate_inactive_catalog_theme_references', 'faluss_catalog_theme_deactivated' ) as $needle ) {
    fl151_assert( false !== strpos( $link, $needle ), 'FL-15.1 must use a single active theme resolver and targeted migration: ' . $needle );
}
fl151_assert( false === strpos( $editor, "selected !== 'faluss-default'" ) && false !== strpos( $editor, 'setThemeOverrides(studio, [])' ), 'The default theme must reset old overrides and accept later member overrides.' );
fl151_assert( false !== strpos( $widgets, '--fl-name-color:{{VALUE}} !important;' ), 'Explicit Elementor name color must retain local final priority.' );

global $wpdb, $fl151_options;
require_once $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';

$fl151_options[ Faluss_Catalog_Themes::OPTION ] = array(
    'night' => array( 'name' => 'Nuit', 'slug' => 'night', 'active' => 1, 'sort_order' => 2, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#101010', 'hero_transition_color' => '#222222', 'name_color' => '#FFFFFF', 'alignment' => 'center', 'social_variant' => 'full', 'link_style' => 'dark' ),
    'other' => array( 'name' => 'Autre', 'slug' => 'other', 'active' => 1, 'sort_order' => 3, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#EEEEEE', 'hero_transition_color' => '#DDDDDD', 'name_color' => '#000000', 'alignment' => 'left', 'social_variant' => 'outline', 'link_style' => 'light' ),
);
$wpdb = new FL151_WPDB();
$id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$wpdb->card = array( 'faluss_id' => $id, 'available' => 1, 'social_links' => json_encode( array( 'networks' => array(), 'selected_theme' => 'night', 'theme_overrides' => array( 'name_color' ), 'name_color' => '#82206B' ) ) );
$prefs_method = new ReflectionMethod( 'Faluss_Link', 'prefs' );
$save = new ReflectionMethod( 'Faluss_Link', 'save_preferences' );
$picker = new ReflectionMethod( 'Faluss_Link', 'theme_picker' );
$card = new ReflectionMethod( 'Faluss_Link', 'card_markup' );

$prefs = $prefs_method->invoke( null, $id );
fl151_assert( 'night' === $prefs['selected_theme'] && '#101010' === $prefs['page_background'] && '#82206B' === $prefs['name_color'] && 'center' === $prefs['alignment'], 'An active scoped theme must be the base while a persisted member override stays effective.' );

$fl151_options[ Faluss_Catalog_Themes::OPTION ]['night']['active'] = 0;
$inactive = $prefs_method->invoke( null, $id );
fl151_assert( 'faluss-default' === $inactive['selected_theme'] && '#FFFDF5' === $inactive['page_background'] && '#82206B' === $inactive['name_color'], 'Inactive themes must resolve to the default while preserving explicit personal overrides.' );
$public_markup = $card->invoke( null, array( 'avatar_attachment_id' => 0, 'display_name' => 'Origin', 'public_slug' => 'origin', 'bio' => '' ), $inactive, $inactive['alignment'], false, array() );
fl151_assert( false !== strpos( $public_markup, 'data-faluss-card-theme="faluss-default"' ) && false === strpos( $public_markup, 'data-faluss-card-theme="night"' ), 'The public card must never emit an inactive theme.' );
ob_start(); $picker->invoke( null, $inactive ); $picker_markup = ob_get_clean();
fl151_assert( false !== strpos( $picker_markup, 'name="selected_theme" value="faluss-default"' ) && false !== strpos( $picker_markup, 'data-faluss-theme="faluss-default" aria-pressed="true"' ), 'Studio hydration must select Faluss default after a theme is inactive.' );

$wpdb->rows = array(
    array( 'faluss_id' => $id, 'social_links' => $wpdb->card['social_links'] ),
    array( 'faluss_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'social_links' => json_encode( array( 'selected_theme' => 'other', 'theme_overrides' => array() ) ) ),
);
fl151_assert( Faluss_Link::migrate_deactivated_theme_references( 'night' ), 'The precise deactivation migration must succeed.' );
$migrated = json_decode( $wpdb->card['social_links'], true );
fl151_assert( 'faluss-default' === $migrated['selected_theme'] && array( 'name_color' ) === $migrated['theme_overrides'], 'Only the exact inactive theme reference is replaced; member overrides remain.' );
$first_update_count = $wpdb->updates;
fl151_assert( Faluss_Link::migrate_deactivated_theme_references( 'night' ) && $first_update_count === $wpdb->updates, 'A repeated deactivation migration must be idempotent.' );
$fl151_options[ Faluss_Catalog_Themes::OPTION ]['night']['active'] = 1;
$after_reactivation = $prefs_method->invoke( null, $id );
fl151_assert( 'faluss-default' === $after_reactivation['selected_theme'], 'Reactivating a theme must not silently reapply it after migration.' );

fl151_assert( $save->invoke( null, $id, array( 'selected_theme' => 'faluss-default', 'theme_overrides' => json_encode( array() ), 'social_networks' => array() ) ), 'Selecting the default theme must save a fresh base.' );
$reset = json_decode( $wpdb->card['social_links'], true );
fl151_assert( 'faluss-default' === $reset['selected_theme'] && array() === $reset['theme_overrides'] && 1 === (int) $wpdb->card['available'], 'Theme selection resets only visual overrides and leaves non-visual card data intact.' );
fl151_assert( $save->invoke( null, $id, array( 'selected_theme' => 'faluss-default', 'theme_overrides' => json_encode( array( 'page_background' ) ), 'page_background' => '#112233', 'social_networks' => array() ) ), 'A later individual setting must save after the default is selected.' );
$reloaded = $prefs_method->invoke( null, $id );
fl151_assert( 'faluss-default' === $reloaded['selected_theme'] && '#112233' === $reloaded['page_background'] && in_array( 'page_background', $reloaded['theme_overrides'], true ), 'The Studio and public resolver must reload the saved default-theme override.' );

echo "FL-15.1 contract: OK\n";
