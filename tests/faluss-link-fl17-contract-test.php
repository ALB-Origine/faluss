<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

function fl17_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
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
function esc_textarea( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function __( $value ) { return (string) $value; }
function esc_html_e( $value ) { echo esc_html( $value ); }
function esc_attr_e( $value ) { echo esc_attr( $value ); }
function selected() {}
function checked() {}
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { return '77777777-7777-4777-8777-777777777777'; }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return 17; }
function current_time() { return '2026-09-06 12:00:00'; }
function is_admin() { return false; }
function wp_attachment_is_image() { return false; }
function wp_get_attachment_image_url() { return ''; }
function wp_get_attachment_image() { return ''; }
function get_post() { return null; }
function wp_parse_url( $url ) { return parse_url( $url ); }

class Token_Engine_Connector_Service {
    public static $allowed = false;
    public static function subject_has_entitlement( $subject_id, $code ) { return self::$allowed && 'theme.premium' === $code; }
}

$fl17_options = array();
function get_option( $key, $default = false ) { global $fl17_options; return $fl17_options[ $key ] ?? $default; }
function update_option( $key, $value ) { global $fl17_options; $fl17_options[ $key ] = $value; return true; }

class FL17_WPDB {
    public $prefix = 'wp_';
    public $card = array();
    public function prepare( $query ) { return $query; }
    public function get_row() { return $this->card; }
    public function get_results() { return array(); }
    public function update( $table, $values ) { $this->card = array_merge( $this->card, $values ); return 1; }
    public function query() { return 1; }
}

$root = dirname( __DIR__ );
$link_source = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$editor_source = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$studio_css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );

foreach ( array( 'stored_overrides', 'theme_setting_keys()', 'theme_locked', 'theme_reference' ) as $needle ) {
    fl17_assert( false !== strpos( $link_source, $needle ), 'FL-17 must retain the selected theme and stored member preferences during an entitlement fallback: ' . $needle );
}
foreach ( array( 'data-fl-preview', 'aria-expanded="false"', 'Mettre à jour', 'data-fl-dirty-count', 'role="switch"', 'data-fl-publication-state', 'faluss-link-color-field__value' ) as $needle ) {
    fl17_assert( false !== strpos( $link_source, $needle ), 'FL-17 Studio markup is missing the compact control: ' . $needle );
}
foreach ( array( 'formState', 'dirtyCount', 'togglePreview', 'scrollIntoView', 'falussLinkInitialState', 'updatePublication', 'updateColorFields', 'prefers-reduced-motion: reduce' ) as $needle ) {
    fl17_assert( false !== strpos( $editor_source, $needle ), 'FL-17 Studio behavior is missing: ' . $needle );
}
foreach ( array( 'position:fixed', 'safe-area-inset-bottom', 'padding-bottom:calc(6.25rem', 'border-radius:100%', '-webkit-line-clamp:2', 'faluss-link-publication__switch' ) as $needle ) {
    fl17_assert( false !== strpos( $studio_css, $needle ), 'FL-17 Studio mobile layout or accessible control is missing: ' . $needle );
}
fl17_assert( false !== strpos( $widgets, '--fl-page-background:{{VALUE}} !important;' ) && false !== strpos( $widgets, '--fl-name-color:{{VALUE}} !important;' ), 'Explicit Elementor page and name overrides must remain final presentation layers.' );

global $wpdb, $fl17_options;
require_once $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';

$fl17_options[ Faluss_Catalog_Themes::OPTION ] = array(
    'premium-theme' => array( 'name' => 'Un thème Faluss vraiment très long pour vérifier un rendu compact', 'slug' => 'premium-theme', 'active' => 1, 'sort_order' => 2, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#101010', 'hero_transition_color' => '#202020', 'name_color' => '#FFFFFF', 'alignment' => 'left', 'social_variant' => 'outline', 'link_style' => 'solid', 'entitlement_code' => 'theme.premium' ),
);
$wpdb = new FL17_WPDB();
$id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$original_payload = array( 'networks' => array(), 'selected_theme' => 'premium-theme', 'theme_overrides' => array( 'page_background', 'hero_transition_color', 'name_color', 'alignment', 'social_variant', 'link_style' ), 'page_background' => '#112233', 'hero_transition_color' => '#445566', 'name_color' => '#BE79FF', 'alignment' => 'center', 'social_variant' => 'full', 'link_style' => 'light' );
$wpdb->card = array( 'faluss_id' => $id, 'available' => 1, 'link_style' => 'light', 'social_links' => json_encode( $original_payload ) );
$original_json = $wpdb->card['social_links'];

$prefs = new ReflectionMethod( 'Faluss_Link', 'prefs' );
$save = new ReflectionMethod( 'Faluss_Link', 'save_preferences' );
$card = new ReflectionMethod( 'Faluss_Link', 'card_markup' );
$picker = new ReflectionMethod( 'Faluss_Link', 'theme_picker' );

Token_Engine_Connector_Service::$allowed = false;
$fallback = $prefs->invoke( null, $id );
fl17_assert( 'premium-theme' === $fallback['theme_reference'] && 'faluss-default' === $fallback['selected_theme'] && 1 === (int) $fallback['theme_locked'], 'A missing central right must fail closed to the effective default without dropping the stored selection.' );
fl17_assert( '#112233' === $fallback['page_background'] && '#445566' === $fallback['hero_transition_color'] && '#BE79FF' === $fallback['name_color'] && 'center' === $fallback['alignment'] && 'full' === $fallback['social_variant'] && 'light' === $fallback['link_style'], 'The entitlement fallback must visibly retain the saved member presentation values.' );
fl17_assert( $original_json === $wpdb->card['social_links'], 'Resolving a missing right must not mutate member preferences.' );
$markup = $card->invoke( null, array( 'avatar_attachment_id' => 0, 'display_name' => 'Origin', 'public_slug' => 'origin', 'bio' => '' ), $fallback, $fallback['alignment'], false, array() );
fl17_assert( false !== strpos( $markup, 'data-faluss-card-theme="faluss-default"' ) && false === strpos( $markup, 'data-faluss-card-theme="premium-theme"' ), 'The public card must not render a locked theme.' );
ob_start(); $picker->invoke( null, $fallback ); $picker_markup = ob_get_clean();
fl17_assert( false !== strpos( $picker_markup, 'data-faluss-theme="premium-theme" aria-pressed="true" disabled aria-disabled="true"' ), 'The Studio must retain the prior theme reference as a clearly locked choice.' );

fl17_assert( $save->invoke( null, $id, array( 'selected_theme' => 'premium-theme', 'theme_overrides' => json_encode( $original_payload['theme_overrides'] ), 'social_networks' => array() ) ), 'Saving the Studio while a right is unavailable must still preserve the selected theme reference.' );
$after_save = json_decode( $wpdb->card['social_links'], true );
fl17_assert( 'premium-theme' === $after_save['selected_theme'] && $original_payload['theme_overrides'] === $after_save['theme_overrides'], 'The temporary fallback must not mutate the saved personal override list after a Studio save.' );

Token_Engine_Connector_Service::$allowed = true;
$restored = $prefs->invoke( null, $id );
fl17_assert( 'premium-theme' === $restored['theme_reference'] && 'premium-theme' === $restored['selected_theme'] && empty( $restored['theme_locked'] ), 'A restored central right must re-enable the previously selected theme without rebuilding the Studio.' );
fl17_assert( '#112233' === $restored['page_background'], 'A restored right must preserve the saved explicit member layer above the selected theme.' );

echo "FL-17 / EC-02.1 Studio compact and non-destructive entitlement fallback contract: OK\n";
