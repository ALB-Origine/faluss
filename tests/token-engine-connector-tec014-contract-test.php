<?php

function tec014_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/plugins/token-engine-connector/token-engine-connector.php' );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$upgrade = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-upgrade.php' );
$admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );
$docs = file_get_contents( $root . '/docs/TOKEN_ENGINE_CONNECTOR.md' );

foreach ( array( "'core_site_url'", 'normalise_site_url', 'raw_https_url', 'isset( $parts[\'query\'] )', 'wp-json|index', 'https://', 'REST_MODE_REWRITE', 'REST_MODE_QUERY' ) as $needle ) {
    tec014_assert( false !== strpos( $service, $needle ), 'TEC-01.4 must validate an HTTPS site URL, including www or a WordPress subdirectory, while rejecting REST endpoints and query input: ' . $needle );
}
foreach ( array( 'route_request', 'rest_modes', "'token' => 'connector/token'", "'diagnostic' => 'connector/diagnostic'", "'balance' => 'connector/balance'", "add_query_arg( 'rest_route'", 'wp_safe_remote_post', 'wp_safe_remote_get', "'connector_route_missing'" ) as $needle ) {
    tec014_assert( false !== strpos( $service, $needle ), 'TEC-01.4 must try rewritten REST routes and fall back to rest_route without a double namespace: ' . $needle );
}
foreach ( array( 'migrate_legacy_site_url', '/wp-json/token-engine/v1', 'index\\\\.php', "'rest_route'", "'/token-engine/v1/'", 'core_site_url', 'secret_protected', 'client_id', 'project_key' ) as $needle ) {
    tec014_assert( false !== strpos( $service . $upgrade, $needle ), 'TEC-01.4 must migrate each recognized legacy REST base while preserving client, project and protected secret state: ' . $needle );
}
foreach ( array( 'class-token-engine-connector-upgrade.php', 'register_activation_hook', 'maybe_upgrade', 'URL du site Core', 'Mécanisme REST détecté', 'name="core_site_url"' ) as $needle ) {
    tec014_assert( false !== strpos( $plugin . $admin, $needle ), 'TEC-01.4 must activate the additive migration and expose the site URL-only diagnostic: ' . $needle );
}
foreach ( array( 'URL HTTPS du site Core', 'rest_route', 'aucune régénération n’est requise' ) as $needle ) {
    tec014_assert( false !== strpos( $docs, $needle ), 'TEC-01.4 documentation must preserve credentials during permalink-independent migration: ' . $needle );
}
tec014_assert( false === strpos( $admin, '$settings[\'secret_protected\']' ) && false === strpos( $admin, 'access_token' ), 'TEC-01.4 must not render a protected secret or short token.' );

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $root . '/' ); }
if ( ! function_exists( 'esc_url_raw' ) ) { function esc_url_raw( $value ) { return $value; } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $value ) { return $value; } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $value ) { return $value; } }
if ( ! function_exists( 'wp_parse_url' ) ) { function wp_parse_url( $value ) { return parse_url( $value ); } }
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; } }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $key, $value, $url ) { return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( $value ); } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { global $tec014_options; return array_key_exists( $key, $tec014_options ) ? $tec014_options[ $key ] : $default; } }
if ( ! function_exists( 'update_option' ) ) { function update_option( $key, $value ) { global $tec014_options; $tec014_options[ $key ] = $value; return true; } }
if ( ! class_exists( 'WP_Error' ) ) { class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $value ) { return $value instanceof WP_Error; } }
if ( ! function_exists( '__' ) ) { function __( $value ) { return $value; } }
if ( ! function_exists( 'wp_generate_uuid4' ) ) { function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000000'; } }
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) { function wp_remote_retrieve_response_code( $response ) { return $response['code']; } }
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) { function wp_remote_retrieve_body( $response ) { return $response['body']; } }
if ( ! function_exists( 'wp_safe_remote_post' ) ) { function wp_safe_remote_post( $url ) { global $tec014_remote_mode, $tec014_remote_urls; $tec014_remote_urls[] = $url; if ( 'query' === $tec014_remote_mode && str_contains( $url, '/wp-json/' ) ) { return array( 'code' => 404, 'body' => '{"code":"rest_no_route"}' ); } return array( 'code' => 200, 'body' => '{"protocol_version":"1","access_token":"test"}' ); } }
if ( ! function_exists( 'wp_safe_remote_get' ) ) { function wp_safe_remote_get( $url ) { return wp_safe_remote_post( $url ); } }

require_once $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php';
require_once $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-upgrade.php';

tec014_assert( 'https://faluss.com' === Token_Engine_Connector_Service::migrate_legacy_site_url( 'https://faluss.com/wp-json/token-engine/v1/' ), 'TE-02.4 must migrate a root rewritten REST base.' );
tec014_assert( 'https://www.faluss.com/subdir' === Token_Engine_Connector_Service::migrate_legacy_site_url( 'https://www.faluss.com/subdir/wp-json/token-engine/v1/' ), 'TE-02.4 must preserve a WordPress subdirectory in a rewritten REST base.' );
tec014_assert( 'https://faluss.com' === Token_Engine_Connector_Service::migrate_legacy_site_url( 'https://faluss.com/index.php?rest_route=/token-engine/v1/' ), 'TE-02.4 must migrate the simple-permalink REST base.' );

$normalise = new ReflectionMethod( 'Token_Engine_Connector_Service', 'normalise_site_url' );
tec014_assert( 'https://www.faluss.com' === $normalise->invoke( null, 'https://www.faluss.com' ), 'TE-02.4 must accept a canonical HTTPS www site URL.' );
tec014_assert( 'https://faluss.com/subdir' === $normalise->invoke( null, 'https://faluss.com/subdir/' ), 'TE-02.4 must accept a canonical HTTPS site URL with subdirectory.' );
foreach ( array( 'http://faluss.com', 'https://faluss.com/wp-json/token-engine/v1/', 'https://faluss.com/?rest_route=/token-engine/v1/' ) as $invalid_url ) {
    tec014_assert( '' === $normalise->invoke( null, $invalid_url ), 'TE-02.4 must reject non-HTTPS, REST endpoint and query-string configuration input.' );
}

$tec014_options = array( 'token_engine_connector_settings' => array( 'core_site_url' => 'https://www.faluss.com', 'secret_protected' => '' ) );
$endpoint = new ReflectionMethod( 'Token_Engine_Connector_Service', 'endpoint' );
tec014_assert( 'https://www.faluss.com/wp-json/token-engine/v1/connector/token' === $endpoint->invoke( null, 'token', 'rewrite' ), 'TE-02.4 must construct the rewritten token route exactly once.' );
tec014_assert( 'https://www.faluss.com/index.php?rest_route=%2Ftoken-engine%2Fv1%2Fconnector%2Fdiagnostic' === $endpoint->invoke( null, 'diagnostic', 'query' ), 'TE-02.4 must construct the simple-permalink diagnostic route through rest_route.' );
tec014_assert( 'https://www.faluss.com/index.php?rest_route=%2Ftoken-engine%2Fv1%2Fconnector%2Fbalance' === $endpoint->invoke( null, 'balance', 'query' ), 'TE-02.4 must construct the simple-permalink balance route through rest_route.' );

$route_request = new ReflectionMethod( 'Token_Engine_Connector_Service', 'route_request' );
$tec014_remote_mode = 'query';
$tec014_remote_urls = array();
$query_result = $route_request->invoke( null, 'token', 'POST', array(), '' );
tec014_assert( 'query' === $query_result['rest_mode'] && 2 === count( $tec014_remote_urls ) && str_contains( $tec014_remote_urls[1], 'rest_route=%2Ftoken-engine%2Fv1%2Fconnector%2Ftoken' ), 'TE-02.4 must fall back from a missing rewritten route to the WordPress simple-permalink route.' );
$tec014_remote_mode = 'rewrite';
$tec014_remote_urls = array();
$rewrite_result = $route_request->invoke( null, 'token', 'POST', array(), '' );
tec014_assert( 'rewrite' === $rewrite_result['rest_mode'] && 1 === count( $tec014_remote_urls ) && str_contains( $tec014_remote_urls[0], '/wp-json/token-engine/v1/connector/token' ), 'TE-02.4 must retain the rewritten route when it is available.' );

$tec014_options = array( 'token_engine_connector_settings' => array( 'core_url' => 'https://www.faluss.com/subdir/wp-json/token-engine/v1/', 'client_id' => 'tec_existing', 'project_key' => 'faluss-link', 'secret_protected' => 'opaque-protected-secret' ) );
Token_Engine_Connector_Upgrade::maybe_upgrade();
$migrated = $tec014_options['token_engine_connector_settings'];
tec014_assert( 'https://www.faluss.com/subdir' === $migrated['core_site_url'] && ! isset( $migrated['core_url'] ), 'TE-02.4 upgrade must replace a recognized REST base with the Core site URL.' );
tec014_assert( 'tec_existing' === $migrated['client_id'] && 'faluss-link' === $migrated['project_key'] && 'opaque-protected-secret' === $migrated['secret_protected'], 'TE-02.4 upgrade must preserve the existing client, project and protected secret.' );

echo "TEC-01.4 Core site URL and REST discovery contract: OK\n";
