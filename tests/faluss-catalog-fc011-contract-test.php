<?php

define( 'ABSPATH', __DIR__ . '/' );

$fc011_options = array();
$fc011_actions = array();
function get_option( $key, $default = false ) { global $fc011_options; return $fc011_options[ $key ] ?? $default; }
function update_option( $key, $value ) { global $fc011_options; $fc011_options[ $key ] = $value; return true; }
function add_option( $key, $value ) { global $fc011_options; $fc011_options[ $key ] = $value; return true; }
function sanitize_title( $value ) { return trim( strtolower( preg_replace( '/[^a-z0-9-]/', '', (string) $value ) ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? strtoupper( (string) $value ) : null; }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function wp_attachment_is_image() { return false; }
function do_action( $hook, ...$arguments ) { global $fc011_actions; $fc011_actions[] = array( $hook, $arguments ); }
function fc011_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php' );
foreach ( array( 'get_active_theme', 'faluss_catalog_theme_deactivated', 'was_active', 'empty( $theme[\'active\'] )' ) as $needle ) {
    fc011_assert( false !== strpos( $source, $needle ), 'FC-01.1 must expose a strict active resolver and deactivation handoff: ' . $needle );
}
require_once $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php';

$fc011_options[ Faluss_Catalog_Themes::OPTION ] = array(
    'active-link' => array( 'name' => 'Actif', 'slug' => 'active-link', 'active' => 1, 'sort_order' => 2, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#112233', 'hero_transition_color' => '#445566', 'name_color' => '#FFFFFF', 'alignment' => 'center', 'social_variant' => 'full', 'link_style' => 'light' ),
    'inactive-link' => array( 'name' => 'Inactif', 'slug' => 'inactive-link', 'active' => 0, 'sort_order' => 3, 'preview_attachment_id' => 0, 'scope' => 'faluss-link', 'page_background' => '#112233', 'hero_transition_color' => '#445566', 'name_color' => '#FFFFFF', 'alignment' => 'center', 'social_variant' => 'full', 'link_style' => 'light' ),
);

fc011_assert( is_array( Faluss_Catalog_Themes::get_active_theme( 'active-link' ) ), 'An active Faluss Link theme must be resolved.' );
fc011_assert( false === Faluss_Catalog_Themes::get_active_theme( 'inactive-link' ), 'An inactive theme must never be returned as an effective theme.' );
fc011_assert( false === Faluss_Catalog_Themes::get_active_theme( 'active-link', 'other-product' ), 'A theme must not cross the Faluss Link scope.' );
fc011_assert( false === Faluss_Catalog_Themes::get_active_theme( 'unknown' ), 'An unknown or absent theme must fail closed.' );

echo "FC-01.1 contract: OK\n";
