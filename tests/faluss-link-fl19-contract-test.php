<?php

define( 'ABSPATH', __DIR__ . '/' );

function fl19_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function add_action() {}
function add_shortcode() {}
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) ); }
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
function disabled() {}
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { return '77777777-7777-4777-8777-777777777777'; }
function absint( $value ) { return abs( (int) $value ); }
function wp_attachment_is_image() { return true; }
function wp_get_attachment_image( $id, $size, $icon, $attributes ) { return '<img src="https://faluss.me/media/' . (int) $id . '.jpg" alt="' . esc_attr( $attributes['alt'] ?? '' ) . '">'; }
function is_user_logged_in() { global $fl19_signed_in; return $fl19_signed_in; }
function get_current_user_id() { return 19; }
function wp_parse_url( $value ) { return parse_url( $value ); }
function home_url( $path = '/' ) { return 'https://faluss.me' . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) ); }
function wp_validate_redirect( $url, $fallback = '' ) { return 0 === strpos( $url, 'https://faluss.me/' ) ? $url : $fallback; }
function add_query_arg( $key, $value, $url ) { return $url . '?redirect_to=' . rawurlencode( $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

class WP_Error {}
class Faluss_Identity_Schema { public static function get_status() { return array( 'ready' => true ); } }
class Faluss_Identity_Registry { public static function get_active_for_wp_user() { global $fl19_subject; return $fl19_subject; } }
class Token_Engine_Connector_Service {
    public static $decision = false;
    public static function entitlement_definitions() { return array( array( 'code' => 'theme.editorial', 'label' => 'Éditorial', 'type' => 'theme' ) ); }
    public static function subject_has_entitlement() { return self::$decision; }
}

$fl19_signed_in = false;
$fl19_subject = '';
$_SERVER['REQUEST_URI'] = '/origin?ignored=1';
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );

foreach ( array( 'TEASER_ACCESS_MODES', 'teaser_access_decision', 'Token_Engine_Connector_Service::subject_has_entitlement', 'teaser_entitlement_choices', 'teaser_login_url', 'faluss-link-card__media-teaser--locked' ) as $needle ) {
    fl19_assert( false !== strpos( $source, $needle ), 'FL-19 server rendering must retain the access decision path: ' . $needle );
}
foreach ( array( 'filter:blur(12px)', 'faluss-link-card__media-teaser-lock', 'faluss-link-card__media-teaser-unlock', 'min-height:44px' ) as $needle ) {
    fl19_assert( false !== strpos( $css, $needle ), 'FL-19 must render a mobile-safe editorial visual lock: ' . $needle );
}
foreach ( array( 'teaserAccessModes', 'teaserRights', 'teaserAccessFields', 'data-fl-block-field\': \'access_mode\'', 'data-fl-entitlement-unavailable' ) as $needle ) {
    fl19_assert( false !== strpos( $editor, $needle ), 'Studio must expose only the supported teaser access controls: ' . $needle );
}

require_once $root . '/plugins/faluss-link/includes/class-faluss-link.php';
$normalise = new ReflectionMethod( 'Faluss_Link', 'normalise_block' );
$decision = new ReflectionMethod( 'Faluss_Link', 'teaser_access_decision' );
$markup = new ReflectionMethod( 'Faluss_Link', 'public_block_markup' );

$historical = $normalise->invoke( null, array( 'block_id' => '11111111-1111-4111-8111-111111111111', 'type' => 'media_teaser', 'attachment_id' => 9, 'title' => 'Historique', 'text' => '' ), true, false );
fl19_assert( 'public' === $historical['access_mode'] && '' === $historical['entitlement_code'], 'A historical teaser must remain public without a migration.' );
fl19_assert( ! empty( $decision->invoke( null, $historical, 'owner-uuid', false )['visible'] ), 'A public teaser is visible to an anonymous visitor.' );

$member = $normalise->invoke( null, array( 'block_id' => '22222222-2222-4222-8222-222222222222', 'type' => 'media_teaser', 'attachment_id' => 9, 'title' => 'Secret copy', 'access_mode' => 'member' ), true, false );
$member_anon = $decision->invoke( null, $member, 'owner-uuid', false );
fl19_assert( empty( $member_anon['visible'] ) && ! empty( $member_anon['login_required'] ), 'Anonymous visitors must be locked from a member teaser with a local-login CTA.' );
$member_markup = $markup->invoke( null, $member, array( 'faluss_id' => 'owner-uuid' ), false );
fl19_assert( false !== strpos( $member_markup, 'faluss-link-card__media-teaser--locked' ) && false !== strpos( $member_markup, 'Créer mon Faluss pour débloquer' ) && false !== strpos( $member_markup, 'https://faluss.me/login/?redirect_to=https%3A%2F%2Ffaluss.me%2Forigin' ), 'The anonymous member lock must render blur, a safe local return, and no external redirect.' );
fl19_assert( false === strpos( $member_markup, 'Secret copy' ) && false === strpos( $member_markup, 'owner-uuid' ), 'Locked markup must not expose teaser copy or a Faluss ID.' );

$fl19_signed_in = true;
$fl19_subject = 'active-member';
fl19_assert( ! empty( $decision->invoke( null, $member, 'owner-uuid', false )['visible'] ), 'An active Faluss member must unlock a member teaser server-side.' );

$right = $normalise->invoke( null, array( 'block_id' => '33333333-3333-4333-8333-333333333333', 'type' => 'media_teaser', 'attachment_id' => 9, 'title' => 'Right copy', 'access_mode' => 'entitlement', 'entitlement_code' => 'theme.editorial' ), true, false );
Token_Engine_Connector_Service::$decision = false;
fl19_assert( empty( $decision->invoke( null, $right, 'owner-uuid', false )['visible'] ), 'Absent, revoked, expired, or unavailable entitlement decisions fail closed.' );
Token_Engine_Connector_Service::$decision = new WP_Error();
fl19_assert( empty( $decision->invoke( null, $right, 'owner-uuid', false )['visible'] ), 'A Connector or Core read error must also fail closed.' );
$_GET['entitlement_code'] = 'theme.editorial';
fl19_assert( empty( $decision->invoke( null, $right, 'owner-uuid', false )['visible'] ), 'A browser query cannot unlock a teaser.' );
Token_Engine_Connector_Service::$decision = true;
fl19_assert( ! empty( $decision->invoke( null, $right, 'owner-uuid', false )['visible'] ), 'Only an affirmative central entitlement decision unlocks the teaser.' );
fl19_assert( ! empty( $decision->invoke( null, $right, 'owner-uuid', true )['visible'] ), 'The owner Studio preview remains editable and never locks itself.' );

echo "FL-19 teaser visual-access contract: OK\n";
