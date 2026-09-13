<?php

$cap01b1_assertions = 0;

function cap01b1_assert( $condition, $message ) {
    global $cap01b1_assertions;
    $cap01b1_assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$source_root = getenv( 'FALUSS_CAP01B1_SOURCE_ROOT' );
$root = is_string( $source_root ) && '' !== $source_root ? rtrim( $source_root, '/\\' ) : dirname( __DIR__ );
$paths = array(
    'registry' => $root . '/plugins/faluss-apps-registry/faluss-apps-registry.php',
    'registry_class' => $root . '/plugins/faluss-apps-registry/includes/class-faluss-apps-registry.php',
    'validator' => $root . '/plugins/faluss-apps-registry/includes/class-faluss-apps-registry-manifest-validator.php',
    'hub' => $root . '/plugins/faluss-portal/includes/class-faluss-portal-manifest.php',
    'me' => $root . '/plugins/faluss-link/includes/class-faluss-link-manifest.php',
    'providers' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-providers.php',
    'policy' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-policy.php',
    'client' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-client.php',
    'server' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-server.php',
    'admin' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-admin.php',
    'federation_bootstrap' => $root . '/plugins/faluss-federation/faluss-federation.php',
    'portal_bootstrap' => $root . '/plugins/faluss-portal/faluss-portal.php',
    'link_bootstrap' => $root . '/plugins/faluss-link/faluss-link.php',
);

if ( in_array( '--expect-base-blocker', $argv, true ) ) {
    cap01b1_assert( ! is_file( $paths['registry'] ), 'Base a931bef must not contain the Apps Registry runtime plugin.' );
    cap01b1_assert( ! is_file( $paths['hub'] ) && ! is_file( $paths['me'] ), 'Base a931bef must not contain Hub or Me manifest producers.' );
    $base_providers = file_get_contents( $paths['providers'] );
    cap01b1_assert( false === strpos( $base_providers, 'register_manifest_contract_validator' ), 'Base a931bef must still couple remote validation to a local provider.' );
    echo 'CAP-01B.1 base a931bef gap reproduced (' . $cap01b1_assertions . ' assertions)' . PHP_EOL;
    exit( 0 );
}

foreach ( $paths as $name => $path ) {
    cap01b1_assert( is_file( $path ), 'Required CAP-01B.1 production artifact is missing: ' . $name );
}

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }

class WP_Error {
    private $code;
    public function __construct( $code ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}

class WP_REST_Response {
    private $data;
    private $status;
    private $headers = array();
    public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
    public function get_headers() { return $this->headers; }
    public function header( $name, $value ) { $this->headers[ $name ] = $value; }
}

$GLOBALS['cap01b1_actions'] = array();
$GLOBALS['cap01b1_did_plugins_loaded'] = 0;
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['cap01b1_actions'][] = array( $hook, $callback, $priority ); }
function did_action( $hook ) { return 'plugins_loaded' === $hook ? $GLOBALS['cap01b1_did_plugins_loaded'] : 0; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return is_string( $value ) ? preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) : ''; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( (string) $value ) : ''; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { return '11111111-1111-4111-8111-111111111111'; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/\\' ) . '/'; }
function plugin_dir_url() { return 'https://example.test/plugins/'; }
function register_activation_hook() {}
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function esc_html__( $value ) { return $value; }
function __( $value ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return (string) $value; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '">'; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_remote_retrieve_headers( $response ) { return $response['headers'] ?? array(); }

final class Faluss_Federation_Crypto {
    const PATH = '/wp-json/faluss-federation/v1/receive';
    public static $identity = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'key_id' => 'hub-key-0001' );
    public static $ready = true;
    public static function transport_ready() { return self::$ready; }
    public static function local_identity() { return self::$identity; }
    public static function is_node( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $value ); }
    public static function is_semver( $value ) { return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*\.[0-9]+\.[0-9]+$/D', $value ); }
    public static function is_key_id( $value ) { return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/D', $value ); }
    public static function is_uuid( $value ) { return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value ); }
    public static function is_sha256( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value ); }
    public static function is_nonce( $value ) { return is_string( $value ) && strlen( $value ) >= 40; }
    public static function is_signature( $value ) { return is_string( $value ) && 86 === strlen( $value ); }
    public static function is_canonical_origin( $value ) { $parts = is_string( $value ) ? parse_url( $value ) : false; return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? null ) && isset( $parts['host'] ) && ! isset( $parts['port'], $parts['path'], $parts['query'], $parts['fragment'], $parts['user'], $parts['pass'] ) && $value === 'https://' . $parts['host']; }
    public static function base64url_decode( $value, $length ) { return 'valid-public-key' === $value && 32 === $length ? str_repeat( 'k', 32 ) : new WP_Error( 'invalid_key' ); }
    public static function format_utc_timestamp( $timestamp ) { return gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ); }
    public static function parse_utc_timestamp( $value ) { $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new DateTimeZone( 'UTC' ) ); return $date && $date->format( 'Y-m-d\TH:i:s\Z' ) === $value ? $date->getTimestamp() : new WP_Error( 'invalid_time' ); }
    public static function is_utc_timestamp( $value ) { return ! is_wp_error( self::parse_utc_timestamp( $value ) ); }
    public static function response_canonical( $response, $status, $raw ) { return $status . "\n" . $raw; }
    public static function sign( $canonical ) { return rtrim( strtr( base64_encode( hash( 'sha512', $canonical, true ) ), '+/', '-_' ), '=' ); }
    public static function verify( $canonical, $signature ) { return is_string( $signature ) && hash_equals( self::sign( $canonical ), $signature ); }
}

final class Faluss_Federation_Schema {
    public static $audits = array();
    public static function is_ready() { return true; }
    public static function peers_table() { return 'wp_faluss_federation_peers'; }
    public static function quote_identifier( $identifier ) { return '`' . $identifier . '`'; }
    public static function audit( $data ) { self::$audits[] = $data; return true; }
}

final class CAP01B1_WPDB {
    public $last_error = '';
    public $rows = array();
    public function __construct( $rows ) { $this->rows = array_values( $rows ); }
    public function prepare( $query ) {
        $args = func_get_args(); array_shift( $args );
        foreach ( $args as $arg ) {
            if ( false !== strpos( $query, '%d' ) ) { $query = preg_replace( '/%d/', (string) (int) $arg, $query, 1 ); }
            else { $query = preg_replace( '/%s/', "'" . str_replace( "'", "''", (string) $arg ) . "'", $query, 1 ); }
        }
        return $query;
    }
    public function get_results( $query ) {
        if ( 1 === preg_match( '/WHERE id = ([0-9]+)/', $query, $match ) ) {
            return array_values( array_filter( $this->rows, static function ( $row ) use ( $match ) { return (int) $row['id'] === (int) $match[1]; } ) );
        }
        return $this->rows;
    }
    public function get_row( $query ) { return empty( $this->rows ) ? null : $this->rows[0]; }
}

function cap01b1_peer_row( $app_key = 'faluss-me', $node_id = 'me-node' ) {
    return array(
        'id' => 7,
        'peer_node_id' => $node_id,
        'peer_app_key' => $app_key,
        'canonical_origin' => 'faluss-me' === $app_key ? 'https://faluss.me' : 'https://faluss.com',
        'key_id' => 'remote-key-0001',
        'public_key' => 'valid-public-key',
        'key_state' => 'active',
        'valid_from' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
        'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
        'operations_json' => '["diagnostic.read","manifest.read"]',
        'owner_apps_json' => '["' . $app_key . '"]',
        'capabilities_json' => '[]',
        'audiences_json' => '[]',
    );
}

function cap01b1_contract() { return array( 'document_type' => 'faluss.app-capability-manifest', 'contract_version' => '1.0.0' ); }
function cap01b1_context( $app_key ) {
    return array(
        'operation' => 'manifest.read',
        'parameters' => array( 'app_key' => $app_key, 'requested_manifest_version' => '1.0.0' ),
        'subject_context' => null,
        'sender' => array( 'node_id' => 'remote-node', 'app_key' => 'remote-app', 'key_id' => 'remote-key-0001' ),
        'recipient' => array( 'node_id' => 'local-node', 'app_key' => $app_key ),
    );
}
function cap01b1_request( $app_key, $responder_node ) {
    $context = cap01b1_context( $app_key );
    return array_merge( array(
        'protocol_version' => '1',
        'message_type' => 'request',
        'request_id' => '11111111-1111-4111-8111-111111111111',
        'issued_at' => Faluss_Federation_Crypto::format_utc_timestamp( time() - 1 ),
        'expires_at' => Faluss_Federation_Crypto::format_utc_timestamp( time() + 299 ),
        'nonce' => str_repeat( 'n', 43 ),
    ), $context, array( 'recipient' => array( 'node_id' => $responder_node, 'app_key' => $app_key ) ) );
}
function cap01b1_private( $class, $method, $arguments = array() ) { $reflection = new ReflectionMethod( $class, $method ); $reflection->setAccessible( true ); return $reflection->invokeArgs( null, $arguments ); }
function cap01b1_reset_providers() {
    $reflection = new ReflectionClass( 'Faluss_Federation_Providers' );
    foreach ( array( 'manifest_providers', 'manifest_contract_validators', 'read_model_providers' ) as $name ) {
        $property = $reflection->getProperty( $name ); $property->setAccessible( true ); $property->setValue( null, array() );
    }
}
function cap01b1_http_response( $response ) {
    return array( 'response' => array( 'code' => $response->get_status() ), 'headers' => $response->get_headers(), 'body' => $response->get_data()['_faluss_federation_raw_json'] );
}

require_once $paths['validator'];
require_once $paths['registry_class'];
require_once $paths['hub'];
require_once $paths['me'];

cap01b1_assert( false === Faluss_Apps_Registry::register_federation_validator(), 'Apps Registry must stay inactive without Federation.' );
cap01b1_assert( false === Faluss_Portal_Manifest::register_provider(), 'Portal manifest integration must stay inactive without Federation.' );
cap01b1_assert( false === Faluss_Link_Manifest::register_provider(), 'Link manifest integration must stay inactive without Federation.' );

require_once $paths['policy'];
require_once $paths['providers'];
require_once $paths['server'];
require_once $paths['client'];
require_once $paths['admin'];

function cap01b1_hub_expected() {
    return array(
        'manifest_version' => '1.0.0', 'app_key' => 'faluss-hub', 'capability_namespace' => 'faluss-hub',
        'owner' => array( 'engine' => 'faluss-hub', 'authority' => 'faluss.com' ), 'product_state' => 'active',
        'canonical_origins' => array( 'https://faluss.com' ),
        'public_presentation' => array( 'display_name' => 'Faluss Hub', 'summary' => 'Le portail central permettant au membre de retrouver ses applications et les services Faluss qui lui sont accessibles.' ),
        'official_asset' => null,
        'capabilities' => array( array(
            'capability_key' => 'faluss-hub.daily-reward', 'interfaces' => array( 'delegated_action' ),
            'requested_bindings' => array( array( 'interface' => 'delegated_action', 'slot' => 'portal.apps.card_action' ) ),
            'read_model_contract' => array( 'document_type' => 'daily-reward.status', 'contract_version' => '1.0.0' ),
            'symbolic_actions' => array( array( 'action_key' => 'faluss-hub.daily-reward.claim', 'kind' => 'delegated_action' ) ),
            'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_capability_key' => null ),
        ) ),
        'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_capability_key' => null ),
    );
}
function cap01b1_me_expected() {
    return array(
        'manifest_version' => '1.0.0', 'app_key' => 'faluss-me', 'capability_namespace' => 'faluss-me',
        'owner' => array( 'engine' => 'faluss-me', 'authority' => 'faluss.me' ), 'product_state' => 'active',
        'canonical_origins' => array( 'https://faluss.me' ),
        'public_presentation' => array( 'display_name' => 'Faluss Me', 'summary' => 'La carte publique personnalisable permettant au membre de présenter son identité, ses liens et les services Faluss qu’il choisit d’exposer.' ),
        'official_asset' => null, 'capabilities' => array(),
        'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_capability_key' => null ),
    );
}

$hub_manifest = Faluss_Portal_Manifest::manifest();
$me_manifest = Faluss_Link_Manifest::manifest();
cap01b1_assert( cap01b1_hub_expected() === $hub_manifest, 'Portal must expose the exact Faluss Hub manifest.' );
cap01b1_assert( cap01b1_me_expected() === $me_manifest, 'Link must expose the exact Faluss Me manifest.' );
cap01b1_assert( null === $hub_manifest['official_asset'] && null === $me_manifest['official_asset'], 'Both official assets must remain null.' );
cap01b1_assert( Faluss_Apps_Registry_Manifest_Validator::validate( $hub_manifest, cap01b1_contract(), cap01b1_context( 'faluss-hub' ) ), 'Exact Hub manifest must validate at runtime.' );
cap01b1_assert( Faluss_Apps_Registry_Manifest_Validator::validate( $me_manifest, cap01b1_contract(), cap01b1_context( 'faluss-me' ) ), 'Exact Me manifest must validate at runtime.' );
$reordered_contract = array( 'contract_version' => '1.0.0', 'document_type' => 'faluss.app-capability-manifest' );
cap01b1_assert( Faluss_Apps_Registry_Manifest_Validator::validate( $me_manifest, $reordered_contract, cap01b1_context( 'faluss-me' ) ), 'JSON object member order must not affect validation.' );
$ported_origin = $hub_manifest; $ported_origin['canonical_origins'][0] = 'https://faluss.com:8443';
cap01b1_assert( Faluss_Apps_Registry_Manifest_Validator::validate( $ported_origin, cap01b1_contract(), cap01b1_context( 'faluss-hub' ) ), 'A canonical HTTPS origin with a schema-valid port must remain valid.' );
$deprecated = $hub_manifest; $deprecated['compatibility']['deprecated'] = true; $deprecated['compatibility']['sunset_at'] = '2030-01-01T00:00:00Z';
cap01b1_assert( Faluss_Apps_Registry_Manifest_Validator::validate( $deprecated, cap01b1_contract(), cap01b1_context( 'faluss-hub' ) ), 'A valid deprecation timestamp must be accepted.' );
$deprecated['compatibility']['sunset_at'] = '2030-02-30T00:00:00Z';
cap01b1_assert( ! Faluss_Apps_Registry_Manifest_Validator::validate( $deprecated, cap01b1_contract(), cap01b1_context( 'faluss-hub' ) ), 'An impossible deprecation timestamp must be rejected.' );

$invalid = array();
$case = $hub_manifest; $case['app_key'] = 'faluss-me'; $invalid['wrong app_key'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['manifest_version'] = '1.0.1'; $invalid['wrong manifest version'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['canonical_origins'][0] = 'http://faluss.com'; $invalid['non-HTTPS origin'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['canonical_origins'][0] = 'https://other.example'; $invalid['divergent origin'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capability_namespace'] = 'faluss-other'; $invalid['foreign namespace'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['capability_key'] = 'faluss-other.daily-reward'; $invalid['foreign capability'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['symbolic_actions'][0]['action_key'] = 'faluss-other.claim'; $invalid['foreign action'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['requested_bindings'][0]['interface'] = 'module_read_model'; $invalid['undeclared binding interface'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['interfaces'] = array( 'event_source' ); $case['capabilities'][0]['requested_bindings'][0] = array( 'interface' => 'event_source', 'slot' => 'portal.apps.card_action' ); $case['capabilities'][0]['symbolic_actions'] = array(); $invalid['event source surface slot'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][] = $case['capabilities'][0]; $invalid['duplicate capability'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['requested_bindings'][] = $case['capabilities'][0]['requested_bindings'][0]; $invalid['duplicate binding'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['symbolic_actions'][] = $case['capabilities'][0]['symbolic_actions'][0]; $invalid['duplicate action'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['unexpected'] = true; $invalid['extra root field'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['public_presentation']['faluss_id'] = '11111111-1111-4111-8111-111111111111'; $invalid['deep member field'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['read_model_contract']['document_type'] = 'member.email'; $invalid['deep sensitive value'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['public_presentation']['summary'] = '<script>alert(1)</script>'; $invalid['executable content'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$case = $hub_manifest; $case['capabilities'][0]['symbolic_actions'][0]['target_url'] = 'https://faluss.com/action'; $invalid['action URL'] = array( $case, cap01b1_context( 'faluss-hub' ) );
$context = cap01b1_context( 'faluss-hub' ); $context['parameters']['requested_manifest_version'] = '2.0.0'; $invalid['wrong requested version'] = array( $hub_manifest, $context );
foreach ( $invalid as $label => $fixture ) {
    cap01b1_assert( ! Faluss_Apps_Registry_Manifest_Validator::validate( $fixture[0], cap01b1_contract(), $fixture[1] ), 'Runtime validator must reject ' . $label . '.' );
}

$asset_manifest = $me_manifest;
$asset_manifest['official_asset'] = array( 'asset_key' => 'faluss-me.logo', 'mime_type' => 'image/png', 'sha256' => str_repeat( 'a', 64 ), 'intrinsic_dimensions' => array( 'width' => 24, 'height' => 24 ), 'distribution' => 'immutable_embedded' );
cap01b1_assert( Faluss_Apps_Registry_Manifest_Validator::validate( $asset_manifest, cap01b1_contract(), cap01b1_context( 'faluss-me' ) ), 'Validator must cover a structurally valid future official asset without creating one.' );
$asset_manifest['official_asset']['intrinsic_dimensions']['width'] = 0;
cap01b1_assert( ! Faluss_Apps_Registry_Manifest_Validator::validate( $asset_manifest, cap01b1_contract(), cap01b1_context( 'faluss-me' ) ), 'Invalid official asset dimensions must be rejected.' );

cap01b1_reset_providers();
cap01b1_assert( true === Faluss_Apps_Registry::register_federation_validator(), 'Registry must register the exact CAP v1 validator when Federation is ready.' );
cap01b1_assert( 'not_available' === Faluss_Federation_Providers::operation_availability()['manifest.read'], 'A validator alone must never become a manifest producer.' );
$missing = Faluss_Federation_Providers::dispatch( cap01b1_context( 'faluss-hub' ), Faluss_Federation_Crypto::$identity );
cap01b1_assert( 'not_available' === $missing['status'], 'Missing local provider must remain not_available.' );
cap01b1_assert( is_wp_error( Faluss_Apps_Registry::register_federation_validator() ), 'Duplicate validator registration must fail closed.' );

cap01b1_reset_providers();
Faluss_Federation_Providers::register_manifest_provider( 'faluss-hub', array( 'Faluss_Portal_Manifest', 'provide' ) );
cap01b1_assert( 'incompatible' === Faluss_Federation_Providers::dispatch( cap01b1_context( 'faluss-hub' ), Faluss_Federation_Crypto::$identity )['status'], 'A producer without its specialized contract validator must fail closed.' );

cap01b1_reset_providers();
Faluss_Apps_Registry::register_federation_validator();
Faluss_Federation_Crypto::$identity = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'key_id' => 'hub-key-0001' );
cap01b1_assert( true === Faluss_Portal_Manifest::register_provider(), 'Hub provider must register on the Hub identity.' );
cap01b1_assert( false === Faluss_Link_Manifest::register_provider(), 'Me provider must not register on the Hub identity.' );
$hub_result = Faluss_Federation_Providers::dispatch( cap01b1_context( 'faluss-hub' ), Faluss_Federation_Crypto::$identity );
cap01b1_assert( 'success' === $hub_result['status'] && $hub_manifest === $hub_result['payload'], 'Hub node must produce only the exact Hub manifest.' );
cap01b1_assert( 'not_available' === Faluss_Federation_Providers::dispatch( cap01b1_context( 'faluss-me' ), Faluss_Federation_Crypto::$identity )['status'], 'Hub node must not produce the Me manifest.' );
cap01b1_assert( is_wp_error( Faluss_Portal_Manifest::register_provider() ), 'Same Hub provider must not register twice.' );

cap01b1_reset_providers();
Faluss_Apps_Registry::register_federation_validator();
Faluss_Federation_Crypto::$identity = array( 'node_id' => 'me-node', 'app_key' => 'faluss-me', 'key_id' => 'me-key-0001' );
cap01b1_assert( true === Faluss_Link_Manifest::register_provider(), 'Me provider must register on the Me identity.' );
cap01b1_assert( false === Faluss_Portal_Manifest::register_provider(), 'Hub provider must not register on the Me identity.' );
$me_result = Faluss_Federation_Providers::dispatch( cap01b1_context( 'faluss-me' ), Faluss_Federation_Crypto::$identity );
cap01b1_assert( 'success' === $me_result['status'] && $me_manifest === $me_result['payload'], 'Me node must produce only the exact Me manifest.' );

cap01b1_reset_providers();
Faluss_Apps_Registry::register_federation_validator();
$remote_response = array( 'status' => 'success', 'payload_contract' => cap01b1_contract(), 'payload' => $me_manifest, 'error' => null );
cap01b1_assert( Faluss_Federation_Providers::validate_received_payload( $remote_response, cap01b1_context( 'faluss-me' ) ), 'Remote manifest validation must not require a local provider for the requested app.' );
$remote_response['payload']['app_key'] = 'faluss-hub';
cap01b1_assert( ! Faluss_Federation_Providers::validate_received_payload( $remote_response, cap01b1_context( 'faluss-me' ) ), 'Remote manifest with the wrong app_key must be refused.' );

$GLOBALS['cap01b1_actions'] = array();
Faluss_Apps_Registry::boot(); Faluss_Portal_Manifest::boot(); Faluss_Link_Manifest::boot();
$priorities = array();
foreach ( $GLOBALS['cap01b1_actions'] as $action ) { if ( 'plugins_loaded' === $action[0] ) { $priorities[] = $action[2]; } }
cap01b1_assert( array( 20, 30, 30 ) === $priorities, 'Load order must deterministically register CAP validator before owner providers.' );
Faluss_Federation_Crypto::$ready = false;
cap01b1_reset_providers();
cap01b1_assert( false === Faluss_Apps_Registry::register_federation_validator(), 'Registry must remain inactive while Federation is not ready.' );
Faluss_Federation_Crypto::$ready = true;

global $wpdb;
$wpdb = new CAP01B1_WPDB( array( cap01b1_peer_row() ) );
$peer = Faluss_Federation_Policy::find_outbound_peer_by_id( 7 );
cap01b1_assert( is_array( $peer ) && 'faluss-me' === $peer['peer_app_key'] && in_array( 'manifest.read', $peer['operations'], true ), 'Administrative manifest test must resolve one usable authorized peer by server-side ID.' );
ob_start(); cap01b1_private( 'Faluss_Federation_Admin', 'render_peer_list' ); $html = ob_get_clean();
$position = strpos( $html, 'value="test_manifest"' );
$start = false === $position ? false : strrpos( substr( $html, 0, $position ), '<form' );
$end = false === $position ? false : strpos( $html, '</form>', $position );
$form = false !== $start && false !== $end ? substr( $html, $start, $end - $start + 7 ) : '';
cap01b1_assert( '' !== $form && false !== strpos( $form, 'method="post"' ) && false !== strpos( $form, 'faluss_federation_test_manifest' ) && false !== strpos( $form, 'name="peer_id" value="7"' ), 'Authorized peer must expose a dedicated read-only manifest POST form.' );
foreach ( array( 'peer_node_id', 'peer_app_key', 'canonical_origin', 'requested_manifest_version', 'manifest_version', 'url' ) as $forbidden ) {
    cap01b1_assert( false === strpos( $form, 'name="' . $forbidden . '"' ), 'Manifest test browser form must not submit ' . $forbidden . '.' );
}
$_POST = array( 'action' => 'faluss_federation_manage', '_wpnonce' => 'nonce', 'faluss_federation_action' => 'test_manifest', 'peer_id' => '7' );
cap01b1_assert( true === cap01b1_private( 'Faluss_Federation_Admin', 'manifest_test_post_is_exact' ), 'Exact manifest-test POST metadata must be accepted.' );
$_POST['canonical_origin'] = 'https://attacker.example';
cap01b1_assert( false === cap01b1_private( 'Faluss_Federation_Admin', 'manifest_test_post_is_exact' ), 'Arbitrary browser destination must be refused.' );
$admin_source = file_get_contents( $paths['admin'] );
cap01b1_assert( false !== strpos( $admin_source, 'find_outbound_peer_by_id' ) && false !== strpos( $admin_source, "manifest_read( \$peer['peer_node_id'], \$peer['peer_app_key'], '1.0.0' )" ), 'Admin must resolve destination and fixed manifest version from server-side data.' );
cap01b1_assert( false === strpos( $admin_source, 'update_option' ) && false === strpos( $admin_source, 'set_transient' ), 'Manifest test must never store a payload.' );
$manifest_message = cap01b1_private( 'Faluss_Federation_Admin', 'manifest_test_message', array( array( 'status' => 'success', 'payload_contract' => $reordered_contract, 'payload' => $me_manifest, 'error' => null ), $peer ) );
cap01b1_assert( 'Manifeste distant vérifié. app_key : faluss-me ; manifest_version : 1.0.0 ; product_state : active ; capacités : 0.' === $manifest_message, 'Admin success output must contain only the four approved manifest summary values.' );

cap01b1_reset_providers(); Faluss_Apps_Registry::register_federation_validator();
Faluss_Federation_Crypto::$identity = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'key_id' => 'hub-key-0001' );
Faluss_Portal_Manifest::register_provider();
$request = cap01b1_request( 'faluss-hub', 'hub-node' );
$raw_request = wp_json_encode( $request, JSON_UNESCAPED_SLASHES );
$signed = cap01b1_private( 'Faluss_Federation_Server', 'authenticated_response', array( $hub_result, $request, hash( 'sha256', $raw_request ), Faluss_Federation_Crypto::$identity, microtime( true ) ) );
$wpdb = new CAP01B1_WPDB( array( cap01b1_peer_row( 'faluss-hub', 'hub-node' ) ) );
$validated = cap01b1_private( 'Faluss_Federation_Client', 'validate_response', array( cap01b1_http_response( $signed ), $request, $raw_request, Faluss_Federation_Policy::find_peer( 'hub-node', 'faluss-hub', 'remote-key-0001' ) ) );
cap01b1_assert( is_array( $validated ) && 'success' === $validated['status'], 'Intact signed manifest response must validate end-to-end.' );
$tampered_http = cap01b1_http_response( $signed );
$tampered = json_decode( $tampered_http['body'], true ); $tampered['payload']['product_state'] = 'retired';
$tampered_http['body'] = wp_json_encode( $tampered, JSON_UNESCAPED_SLASHES );
$tampered_http['headers']['X-Faluss-Federation-Content-SHA256'] = hash( 'sha256', $tampered_http['body'] );
cap01b1_assert( is_wp_error( cap01b1_private( 'Faluss_Federation_Client', 'validate_response', array( $tampered_http, $request, $raw_request, Faluss_Federation_Policy::find_peer( 'hub-node', 'faluss-hub', 'remote-key-0001' ) ) ) ), 'Altered signed response must be refused even when its content hash is recomputed.' );

cap01b1_reset_providers(); Faluss_Apps_Registry::register_federation_validator();
$missing_result = Faluss_Federation_Providers::dispatch( cap01b1_context( 'faluss-hub' ), Faluss_Federation_Crypto::$identity );
$missing_signed = cap01b1_private( 'Faluss_Federation_Server', 'authenticated_response', array( $missing_result, $request, hash( 'sha256', $raw_request ), Faluss_Federation_Crypto::$identity, microtime( true ) ) );
cap01b1_assert( 404 === $missing_signed->get_status() && 86 === strlen( $missing_signed->get_headers()['X-Faluss-Federation-Signature'] ?? '' ), 'Provider absence must remain a signed not_available response.' );

$bootstrap_sources = array_map( 'file_get_contents', array( $paths['federation_bootstrap'], $paths['registry'], $paths['portal_bootstrap'], $paths['link_bootstrap'] ) );
cap01b1_assert( false !== strpos( $bootstrap_sources[0], 'Version: 0.2.0' ) && false !== strpos( $bootstrap_sources[0], "FALUSS_FEDERATION_SCHEMA_VERSION', '1'" ), 'Federation must be 0.2.0 with schema 1.' );
cap01b1_assert( false !== strpos( $bootstrap_sources[1], 'Version: 0.2.0' ), 'Apps Registry must retain CAP-01B.1 under version 0.2.0.' );
cap01b1_assert( false !== strpos( $bootstrap_sources[2], 'Version: 0.1.22' ) && false !== strpos( $bootstrap_sources[3], 'Version: 0.3.19' ), 'Portal and Link versions must be 0.1.22 and 0.3.19.' );
$registry_runtime = $bootstrap_sources[1] . file_get_contents( $paths['registry_class'] ) . file_get_contents( $paths['validator'] );
foreach ( array( 'CREATE TABLE', 'dbDelta', 'register_rest_route', 'add_shortcode', 'update_option', 'wp_insert', 'wp_update' ) as $forbidden ) {
    cap01b1_assert( false === stripos( $registry_runtime, $forbidden ), 'Apps Registry CAP-01B.1 must not add storage, routes, UI or mutations: ' . $forbidden );
}

echo 'CAP-01B.1 runtime manifests: OK (' . $cap01b1_assertions . ' assertions; production validator/providers/manifests/admin/client/server)' . PHP_EOL;
