<?php

define( 'ABSPATH', __DIR__ . '/' );

$fc01_options = array();
function get_option( $key, $default = false ) { global $fc01_options; return $fc01_options[ $key ] ?? $default; }
function update_option( $key, $value ) { global $fc01_options; $fc01_options[ $key ] = $value; return true; }
function add_option( $key, $value ) { global $fc01_options; $fc01_options[ $key ] = $value; return true; }
function sanitize_title( $value ) { return trim( strtolower( preg_replace( '/[^a-z0-9-]/', '', (string) $value ) ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? strtoupper( (string) $value ) : null; }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function wp_attachment_is_image( $id ) { return 88 === (int) $id; }
function fc01_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php' );
foreach ( array( 'Faluss par défaut', 'SYSTEM_SLUG', 'manage_options', 'faluss_catalog_nonce', 'wp_verify_nonce', 'wp_attachment_is_image', 'LINK_SCOPE', 'page_background', 'hero_transition_color', 'name_color', 'social_variant', 'link_style' ) as $needle ) { fc01_assert( false !== strpos( $source, $needle ), 'FC-01 catalogue invariant absent: ' . $needle ); }
fc01_assert( false === strpos( $source, 'wp_insert_post' ) && false === strpos( $source, 'register_post_type' ), 'FC-01 themes must remain structured records, not arbitrary posts or CSS payloads.' );

require_once $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php';
$default = Faluss_Catalog_Themes::system_theme();
fc01_assert( 'faluss-default' === $default['slug'] && ! empty( $default['system'] ) && ! empty( $default['active'] ) && '#FFFDF5' === $default['page_background'] && '#000000' === $default['name_color'], 'The immutable Faluss default theme must match the card fallback.' );

$validate = new ReflectionMethod( 'Faluss_Catalog_Themes', 'validated_input' );
$validate->setAccessible( true );
$valid = $validate->invoke( null, array( 'name' => 'Éditorial', 'active' => '1', 'sort_order' => 4, 'preview_attachment_id' => 88, 'page_background' => '#112233', 'hero_transition_color' => '#445566', 'name_color' => '#BE79FF', 'alignment' => 'center', 'social_variant' => 'full', 'link_style' => 'light' ), 'editorial' );
fc01_assert( is_array( $valid ) && 'editorial' === $valid['slug'] && 88 === $valid['preview_attachment_id'] && 'faluss-link' === $valid['scope'], 'A complete, scoped, image-backed theme must validate.' );
fc01_assert( false === $validate->invoke( null, array( 'name' => 'Invalide', 'page_background' => 'red', 'hero_transition_color' => '#445566', 'name_color' => '#123456', 'alignment' => 'right', 'social_variant' => 'css', 'link_style' => 'gradient' ), 'invalide' ), 'Free colors and enum values must be refused.' );

$fc01_options[ Faluss_Catalog_Themes::OPTION ] = array(
    'editorial' => $valid,
    'archive' => array_merge( $valid, array( 'name' => 'Archive', 'slug' => 'archive', 'active' => 0, 'sort_order' => 2 ) ),
);
$all = Faluss_Catalog_Themes::all_for_scope();
$active = Faluss_Catalog_Themes::active_for_scope();
fc01_assert( isset( $all['faluss-default'], $all['editorial'], $all['archive'] ) && 'faluss-link' === $all['editorial']['scope'] && 'faluss-link' === $all['archive']['scope'], 'Every FC-01 record remains isolated to the Faluss Link scope.' );
fc01_assert( isset( $active['faluss-default'], $active['editorial'] ) && ! isset( $active['archive'] ), 'Inactive themes remain stored but are not selectable.' );
fc01_assert( is_array( Faluss_Catalog_Themes::get_theme( 'archive' ) ) && ! Faluss_Catalog_Themes::get_theme( 'missing' ), 'An inactive selected theme remains resolvable while an unknown slug is refused.' );

echo "FC-01 catalog contract: OK\n";
