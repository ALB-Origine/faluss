<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

function fl18_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function add_action() {}
function add_shortcode() {}
function is_user_logged_in() { global $fl18_logged_in; return $fl18_logged_in; }
function get_current_user_id() { return 71; }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) ); }
function sanitize_title( $value ) { return trim( strtolower( preg_replace( '/[^a-z0-9-]/', '', (string) $value ) ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? strtoupper( (string) $value ) : null; }
function absint( $value ) { return abs( (int) $value ); }
function current_time() { return '2026-09-06 12:00:00'; }
function get_option( $key, $default = false ) { return 'date_format' === $key ? 'j F Y' : $default; }
function update_option() { return true; }
function esc_url_raw( $value ) { return trim( (string) $value ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, (array) $value ); }
function __($value) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function esc_html_e( $value ) { echo esc_html( $value ); }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function home_url( $path = '' ) { return 'https://faluss.test' . $path; }
function admin_url( $path = '' ) { return 'https://faluss.test/wp-admin/' . $path; }
function wp_nonce_field() {}
function checked() {}
function wp_attachment_is_image( $id ) { return $id > 0; }
function wp_get_attachment_image( $id, $size, $icon = false, $attributes = array() ) { return '<img src="https://faluss.test/media/' . (int) $id . '.jpg" alt="">'; }
function wp_style_is() { return true; }
function wp_enqueue_style() {}
function wp_register_style() {}
function wp_register_script() {}
function wp_localize_script() {}

class Faluss_Identity_Schema {
    public static function get_status() { return array( 'ready' => true ); }
    public static function get_public_profiles_table() { return 'wp_faluss_identity_public_profiles'; }
}
class Faluss_Identity_Registry {
    public static $id = '11111111-1111-4111-8111-111111111111';
    public static function get_active_for_wp_user() { return self::$id; }
}

class FL18_WPDB {
    public $prefix = 'wp_';
    public $discoveries = array();
    public $settings = array();
    public $public_profiles = array();
    private $next_id = 1;
    public function prepare( $query, ...$args ) {
        foreach ( $args as $arg ) {
            $query = preg_replace_callback( '/%[ds]/', static function( $match ) use ( $arg ) { return '%d' === $match[0] ? (string) (int) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'"; }, $query, 1 );
        }
        return $query;
    }
    public function query( $query ) {
        if ( in_array( $query, array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK' ), true ) ) { return 1; }
        if ( false !== strpos( $query, 'INSERT INTO wp_faluss_link_discoveries' ) ) {
            preg_match( "/VALUES \('([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\)/", $query, $matches );
            if ( count( $matches ) !== 6 ) { return false; }
            $key = $matches[1] . '|' . $matches[2];
            if ( isset( $this->discoveries[ $key ] ) ) { $this->discoveries[ $key ]['last_seen_at'] = $matches[4]; ++$this->discoveries[ $key ]['view_count']; return 2; }
            $this->discoveries[ $key ] = array( 'id' => $this->next_id++, 'viewer_faluss_id' => $matches[1], 'discovered_faluss_id' => $matches[2], 'first_seen_at' => $matches[3], 'last_seen_at' => $matches[4], 'view_count' => (int) $matches[5] );
            return 1;
        }
        if ( false !== strpos( $query, 'INSERT INTO wp_faluss_link_discovery_settings' ) ) {
            preg_match( "/VALUES \('([^']+)'\s*,\s*(\d+)\s*,\s*'[^']+'\)/", $query, $matches );
            if ( count( $matches ) !== 3 ) { return false; }
            $this->settings[ $matches[1] ] = (int) $matches[2]; return 1;
        }
        if ( false !== strpos( $query, 'DELETE FROM wp_faluss_link_discoveries WHERE viewer_faluss_id=' ) ) {
            preg_match( "/viewer_faluss_id='([^']+)'/", $query, $matches ); $viewer = $matches[1] ?? '';
            $records = array_filter( $this->discoveries, static function( $row ) use ( $viewer ) { return $viewer === $row['viewer_faluss_id']; } );
            uasort( $records, static function( $left, $right ) { return $right['id'] <=> $left['id']; } );
            $retain = array_slice( array_keys( $records ), 0, 250 );
            foreach ( array_keys( $records ) as $key ) { if ( ! in_array( $key, $retain, true ) ) { unset( $this->discoveries[ $key ] ); } }
            return 1;
        }
        return 1;
    }
    public function get_var( $query ) {
        if ( false !== strpos( $query, 'SELECT recording_enabled' ) ) { preg_match( "/viewer_faluss_id='([^']+)'/", $query, $matches ); return $this->settings[ $matches[1] ?? '' ] ?? null; }
        return null;
    }
    public function get_results( $query ) {
        if ( false === strpos( $query, 'FROM wp_faluss_link_discoveries d' ) ) { return array(); }
        preg_match( "/d\.viewer_faluss_id='([^']+)'/", $query, $matches ); $viewer = $matches[1] ?? '';
        $rows = array();
        foreach ( $this->discoveries as $row ) {
            if ( $viewer !== $row['viewer_faluss_id'] || empty( $this->public_profiles[ $row['discovered_faluss_id'] ] ) ) { continue; }
            $profile = $this->public_profiles[ $row['discovered_faluss_id'] ];
            if ( 'published' !== $profile['publication_status'] ) { continue; }
            $rows[] = array( 'id' => $row['id'], 'last_seen_at' => $row['last_seen_at'], 'public_slug' => $profile['public_slug'], 'display_name' => $profile['display_name'], 'avatar_attachment_id' => $profile['avatar_attachment_id'] );
        }
        usort( $rows, static function( $left, $right ) { return $right['id'] <=> $left['id']; } );
        return $rows;
    }
    public function delete( $table, $where ) {
        $deleted = 0;
        foreach ( array_keys( $this->discoveries ) as $key ) {
            $row = $this->discoveries[ $key ];
            if ( ( ! isset( $where['id'] ) || (int) $where['id'] === (int) $row['id'] ) && ( ! isset( $where['viewer_faluss_id'] ) || $where['viewer_faluss_id'] === $row['viewer_faluss_id'] ) ) { unset( $this->discoveries[ $key ] ); ++$deleted; }
        }
        return $deleted;
    }
}

$root = dirname( __DIR__ );
$link_source = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$schema_source = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php' );
$widget_source = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
$studio_source = substr( $link_source, strpos( $link_source, 'public static function render_studio' ), strpos( $link_source, 'public static function save()', strpos( $link_source, 'public static function render_studio' ) ) - strpos( $link_source, 'public static function render_studio' ) );

foreach ( array( "const VERSION = '3'", 'faluss_link_discoveries', 'faluss_link_discovery_settings', 'ENGINE=InnoDB', 'faluss_discovery_pair', 'faluss_discovery_recent', 'GET_LOCK', 'SHOW TABLE STATUS', 'SHOW FULL COLUMNS', 'SHOW INDEX', 'array_fill_keys', "'NO'" ) as $needle ) {
    fl18_assert( false !== strpos( $schema_source, $needle ), 'FL-18 schema must be additive, strict and indexed: ' . $needle );
}
foreach ( array( 'record_discovery_for_current_visitor( $profile[\'faluss_id\'] )', 'START TRANSACTION', 'ON DUPLICATE KEY UPDATE', 'view_count=view_count+1', 'LIMIT 250', 'hash_equals', 'discovery_recording_enabled', 'p.publication_status=%s' ) as $needle ) {
    fl18_assert( false !== strpos( $link_source, $needle ), 'FL-18 must record only server-resolved eligible public profiles: ' . $needle );
}
foreach ( array( 'faluss_link_save_discovery_settings', 'faluss_link_delete_discovery', 'faluss_link_clear_discoveries', 'wp_nonce_field', 'viewer_faluss_id' ) as $needle ) {
    fl18_assert( false !== strpos( $link_source, $needle ), 'FL-18 private controls must be persisted and scoped: ' . $needle );
}
fl18_assert( false === stripos( $studio_source, 'discover' ), 'Mes découvertes must not add a setting or history to Studio Faluss.' );
foreach ( array( 'Faluss_Link_Discoveries_Widget', 'Mes découvertes Faluss', 'Nombre maximal affiché par page', 'Liste', 'Grille', 'faluss-link-discoveries' ) as $needle ) {
    fl18_assert( false !== strpos( $widget_source, $needle ), 'FL-18 Elementor widget is missing the member-library control: ' . $needle );
}

global $wpdb, $fl18_logged_in;
$wpdb = new FL18_WPDB();
$fl18_logged_in = false;
require_once $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php';
require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';

$record = new ReflectionMethod( 'Faluss_Link', 'record_discovery_for_current_visitor' );
$enabled = new ReflectionMethod( 'Faluss_Link', 'discovery_recording_enabled' );
$set_enabled = new ReflectionMethod( 'Faluss_Link', 'set_discovery_recording_enabled' );
$delete = new ReflectionMethod( 'Faluss_Link', 'delete_discovery_for_viewer' );
$clear = new ReflectionMethod( 'Faluss_Link', 'clear_discoveries_for_viewer' );
$list = new ReflectionMethod( 'Faluss_Link', 'discoveries_for_viewer' );
$viewer = Faluss_Identity_Registry::$id;
$target = '22222222-2222-4222-8222-222222222222';
$second_viewer = '33333333-3333-4333-8333-333333333333';
$second_target = '44444444-4444-4444-8444-444444444444';

fl18_assert( '' === Faluss_Link::render_discoveries(), 'The private widget must render no data for an anonymous visitor.' );
fl18_assert( false === $record->invoke( null, $target ) && 0 === count( $wpdb->discoveries ), 'An anonymous visitor must never create a discovery.' );
$fl18_logged_in = true; Faluss_Identity_Registry::$id = '';
fl18_assert( false === $record->invoke( null, $target ) && 0 === count( $wpdb->discoveries ), 'A member without an active Faluss profile must not create a discovery.' );
Faluss_Identity_Registry::$id = $viewer;
fl18_assert( false === $record->invoke( null, $viewer ) && 0 === count( $wpdb->discoveries ), 'Consulting one’s own profile must not create a discovery.' );
fl18_assert( true === $record->invoke( null, $target ) && 1 === count( $wpdb->discoveries ), 'The first eligible discovery must create one entry.' );
fl18_assert( true === $record->invoke( null, $target ) && 1 === count( $wpdb->discoveries ) && 2 === reset( $wpdb->discoveries )['view_count'], 'A revisit must update the unique existing entry without duplication.' );
fl18_assert( true === $set_enabled->invoke( null, $viewer, false ) && false === $enabled->invoke( null, $viewer ), 'The private recording setting must persist as disabled.' );
fl18_assert( false === $record->invoke( null, $second_target ) && 1 === count( $wpdb->discoveries ), 'A disabled setting must prevent future writes without deleting history.' );
fl18_assert( true === $set_enabled->invoke( null, $viewer, true ), 'The private recording setting must be re-enableable.' );
for ( $i = 0; $i < 251; ++$i ) { $suffix = str_pad( dechex( $i ), 12, '0', STR_PAD_LEFT ); $record->invoke( null, '55555555-5555-4555-8555-' . $suffix ); }
fl18_assert( 250 === count( array_filter( $wpdb->discoveries, static function( $row ) use ( $viewer ) { return $row['viewer_faluss_id'] === $viewer; } ) ), 'The oldest discoveries must be deterministically pruned at 250 per member.' );
Faluss_Identity_Registry::$id = $second_viewer;
fl18_assert( true === $record->invoke( null, $second_target ), 'A second active member must have an isolated discovery library.' );
$wpdb->public_profiles[ $second_target ] = array( 'publication_status' => 'published', 'public_slug' => 'autre-membre', 'display_name' => 'Autre membre', 'avatar_attachment_id' => 3 );
$rows = $list->invoke( null, $second_viewer, 24 );
fl18_assert( 1 === count( $rows ) && 'autre-membre' === $rows[0]['public_slug'] && ! isset( $rows[0]['discovered_faluss_id'] ), 'The private widget list must hydrate only current published profile data without exposing Faluss IDs.' );
$_GET = array();
$library_markup = Faluss_Link::render_discoveries( array( 'title' => 'Mes découvertes', 'empty_label' => 'Rien ici' ) );
fl18_assert( false !== strpos( $library_markup, '@autre-membre' ) && false === strpos( $library_markup, $second_target ) && false === strpos( $library_markup, 'view_count' ), 'The private widget must render available profiles without exposing Faluss IDs or raw consultation counts.' );
$wpdb->public_profiles[ $second_target ]['publication_status'] = 'draft';
fl18_assert( 0 === count( $list->invoke( null, $second_viewer, 24 ) ), 'A profile that becomes unpublished must be omitted cleanly from the library.' );
$wpdb->public_profiles[ $second_target ]['publication_status'] = 'published';
$entry_id = $rows[0]['id'];
Faluss_Identity_Registry::$id = $viewer;
fl18_assert( true === $clear->invoke( null, $viewer ) && 0 === count( array_filter( $wpdb->discoveries, static function( $row ) use ( $viewer ) { return $row['viewer_faluss_id'] === $viewer; } ) ) && 1 === count( $list->invoke( null, $second_viewer, 24 ) ), 'Clear all must remove only the current member’s history.' );
fl18_assert( true === $delete->invoke( null, $second_viewer, $entry_id ) && 0 === count( $list->invoke( null, $second_viewer, 24 ) ), 'An individual deletion must be limited to the current viewer entry.' );

echo "FL-18 Mes découvertes private library contract: OK\n";
