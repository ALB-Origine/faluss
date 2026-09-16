<?php

$cap01b2_assertions = 0;

function cap01b2_assert( $condition, $message ) {
    global $cap01b2_assertions;
    $cap01b2_assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$source_root = getenv( 'FALUSS_CAP01B2_SOURCE_ROOT' );
$root = is_string( $source_root ) && '' !== $source_root ? rtrim( $source_root, '/\\' ) : dirname( __DIR__ );
$paths = array(
    'registry_bootstrap' => $root . '/plugins/faluss-apps-registry/faluss-apps-registry.php',
    'registry' => $root . '/plugins/faluss-apps-registry/includes/class-faluss-apps-registry.php',
    'manifest_validator' => $root . '/plugins/faluss-apps-registry/includes/class-faluss-apps-registry-manifest-validator.php',
    'read_model_validator' => $root . '/plugins/faluss-apps-registry/includes/class-faluss-apps-registry-read-model-validator.php',
    'portal_manifest' => $root . '/plugins/faluss-portal/includes/class-faluss-portal-manifest.php',
    'me_manifest' => $root . '/plugins/faluss-link/includes/class-faluss-link-manifest.php',
    'portal' => $root . '/plugins/faluss-portal/includes/class-faluss-portal.php',
    'portal_adapter' => $root . '/plugins/faluss-portal/includes/class-faluss-portal-apps-registry-adapter.php',
    'portal_bootstrap' => $root . '/plugins/faluss-portal/faluss-portal.php',
    'identity_adapter' => $root . '/plugins/faluss-identity-client/includes/class-faluss-identity-client-apps-registry-adapter.php',
    'identity_bootstrap' => $root . '/plugins/faluss-identity-client/faluss-identity-client.php',
    'federation_bootstrap' => $root . '/plugins/faluss-federation/faluss-federation.php',
    'link_bootstrap' => $root . '/plugins/faluss-link/faluss-link.php',
    'identity_bootstrap_owner' => $root . '/plugins/faluss-identity/faluss-identity.php',
);

if ( in_array( '--expect-base-blocker', $argv, true ) ) {
    cap01b2_assert( ! is_file( $paths['read_model_validator'] ) && ! is_file( $paths['portal_adapter'] ) && ! is_file( $paths['identity_adapter'] ), 'Base d0f1cd0 must not contain the CAP-01B.2 resolver, validator or adapters.' );
    cap01b2_assert( false !== strpos( file_get_contents( $paths['registry_bootstrap'] ), 'Version: 0.1.0' ), 'Base must retain Apps Registry 0.1.0.' );
    cap01b2_assert( false !== strpos( file_get_contents( $paths['portal'] ), "'available'   => true" ) && false !== strpos( file_get_contents( $paths['portal'] ), 'self::faluss_me_projection' ), 'Base must still make Hub and Me decisions in the hard-coded Portal registry.' );
    echo 'CAP-01B.2 base d0f1cd0 gap reproduced (' . $cap01b2_assertions . ' assertions)' . PHP_EOL;
    exit( 0 );
}

foreach ( $paths as $name => $path ) {
    cap01b2_assert( is_file( $path ), 'Required CAP-01B.2 production artifact is missing: ' . $name );
}

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
if ( ! defined( 'FALUSS_PORTAL_URL' ) ) { define( 'FALUSS_PORTAL_URL', 'https://faluss.com/wp-content/plugins/faluss-portal/' ); }
if ( ! defined( 'TOKEN_ENGINE_VERSION' ) ) { define( 'TOKEN_ENGINE_VERSION', '0.4.1' ); }

final class WP_Error {
    private $code;
    public function __construct( $code ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_parse_url( $value ) { return parse_url( (string) $value ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? (string) $value : ''; }
function admin_url( $path = '' ) { return 'https://faluss.com/wp-admin/' . ltrim( $path, '/' ); }
function wp_create_nonce( $action ) { return hash( 'sha256', $action ); }
function add_action() {}
function did_action() { return 0; }
function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/\\' ) . '/'; }

$GLOBALS['cap01b2_transients'] = array();
$GLOBALS['cap01b2_transient_ttls'] = array();
function get_transient( $key ) { return $GLOBALS['cap01b2_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration ) { $GLOBALS['cap01b2_transients'][ $key ] = $value; $GLOBALS['cap01b2_transient_ttls'][ $key ] = $expiration; return true; }
function delete_transient( $key ) { unset( $GLOBALS['cap01b2_transients'][ $key ], $GLOBALS['cap01b2_transient_ttls'][ $key ] ); return true; }

final class Faluss_Federation_Crypto {
    public static $identity = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'origin' => 'https://faluss.com', 'key_id' => 'hub-key-0001' );
    public static function local_identity() { return self::$identity; }
    public static function transport_ready() { return true; }
}

final class Faluss_Federation_Providers {
    public static function register_manifest_contract_validator() { return true; }
}

final class Faluss_Federation_Policy {
    public static $key_id = 'me-key-0001';
    public static function find_outbound_peer( $node_id, $app_key ) {
        return 'me-node' === $node_id && 'faluss-me' === $app_key
            ? array( 'peer_node_id' => $node_id, 'peer_app_key' => $app_key, 'key_id' => self::$key_id )
            : new WP_Error( 'unknown_peer' );
    }
}

final class Faluss_Federation_Client {
    public static $calls = 0;
    public static $fail = false;
    public static $manifest = array();
    public static function manifest_read( $node_id, $app_key, $version ) {
        self::$calls++;
        if ( self::$fail || 'me-node' !== $node_id || 'faluss-me' !== $app_key || '1.0.0' !== $version ) {
            return new WP_Error( 'transport_failed' );
        }
        return array(
            'status' => 'success',
            'payload_contract' => array( 'document_type' => 'faluss.app-capability-manifest', 'contract_version' => '1.0.0' ),
            'payload' => self::$manifest,
            'responder' => array( 'node_id' => 'me-node', 'app_key' => 'faluss-me', 'key_id' => Faluss_Federation_Policy::$key_id ),
            'generated_at' => gmdate( 'Y-m-d\\TH:i:s\\Z' ),
            'expires_at' => gmdate( 'Y-m-d\\TH:i:s\\Z', time() + 120 ),
            'error' => null,
        );
    }
}

final class Faluss_Identity_Client {
    public static $calls = 0;
    public static $projection = null;
    public static function member_app_projection( $faluss_id, $app_key ) {
        self::$calls++;
        unset( $faluss_id );
        return 'me' === $app_key ? self::$projection : new WP_Error( 'projection_unavailable' );
    }
}
final class Faluss_Identity_Client_Schema {
    public static function tables() { return array( 'links' => 'wp_faluss_identity_links' ); }
}
$wpdb = new class {
    public $last_error = '';
    public function prepare( $query ) { return $query; }
    public function get_var( $query ) { unset( $query ); return null; }
};

final class Token_Engine_Schema { const VERSION = '5'; }
final class Token_Engine_Points_Service {
    public static $calls = 0;
    public static $state = 'claimable';
    public static function daily_status( $faluss_id, $owner, $reward_key, $proof ) {
        self::$calls++;
        unset( $faluss_id, $proof );
        if ( in_array( self::$state, array( 'unavailable', 'not_supported', 'ineligible' ), true ) ) {
            return array( 'state' => self::$state );
        }
        return array( 'state' => self::$state, 'owner' => $owner, 'reward_key' => $reward_key, 'amount_pf' => 20, 'economic_class' => 'earned', 'category' => 'daily_accrual', 'logical_date' => gmdate( 'Y-m-d' ), 'entry_uuid' => null );
    }
}

require_once $paths['manifest_validator'];
require_once $paths['read_model_validator'];
require_once $paths['registry'];
require_once $paths['portal_manifest'];
require_once $paths['me_manifest'];
require_once $paths['portal'];
require_once $paths['portal_adapter'];
require_once $paths['identity_adapter'];

Faluss_Federation_Client::$manifest = Faluss_Link_Manifest::manifest();
$faluss_id = '11111111-1111-4111-8111-111111111111';
$valid_projection = array( 'contract_version' => '1', 'publication_status' => 'published', 'canonical_url' => 'https://faluss.me/mon-faluss' );

function cap01b2_static_set( $class, $property, $value ) {
    $reflection = new ReflectionProperty( $class, $property );
    $reflection->setAccessible( true );
    $reflection->setValue( null, $value );
}

function cap01b2_static_get( $class, $property ) {
    $reflection = new ReflectionProperty( $class, $property );
    $reflection->setAccessible( true );
    return $reflection->getValue();
}

function cap01b2_reset_runtime() {
    cap01b2_static_set( 'Faluss_Apps_Registry', 'sources', array() );
    cap01b2_static_set( 'Faluss_Apps_Registry', 'source_conflict', false );
    cap01b2_static_set( 'Faluss_Portal_Apps_Registry_Adapter', 'relationship_cache', array() );
    cap01b2_static_set( 'Faluss_Portal_Apps_Registry_Adapter', 'capability_cache', array() );
    cap01b2_static_set( 'Faluss_Identity_Client_Apps_Registry_Adapter', 'relationship_cache', array() );
    $GLOBALS['cap01b2_transients'] = array();
    $GLOBALS['cap01b2_transient_ttls'] = array();
    Faluss_Federation_Client::$calls = 0;
    Faluss_Federation_Client::$fail = false;
    Faluss_Federation_Policy::$key_id = 'me-key-0001';
    Faluss_Identity_Client::$calls = 0;
    Faluss_Identity_Client::$projection = $GLOBALS['cap01b2_valid_projection'];
    Token_Engine_Points_Service::$calls = 0;
    Token_Engine_Points_Service::$state = 'claimable';
}

$GLOBALS['cap01b2_valid_projection'] = $valid_projection;
cap01b2_reset_runtime();
cap01b2_assert( true === Faluss_Portal_Apps_Registry_Adapter::register_source(), 'Portal adapter must register the Hub local source.' );
cap01b2_assert( true === Faluss_Identity_Client_Apps_Registry_Adapter::register_source(), 'Identity Client adapter must register the Me federated source.' );
$sources = cap01b2_static_get( 'Faluss_Apps_Registry', 'sources' );
cap01b2_assert( array( 'faluss-hub', 'faluss-me' ) === array_keys( $sources ), 'The two exact application owners must be registered.' );
cap01b2_assert( is_wp_error( Faluss_Apps_Registry::register_source( $sources['faluss-hub'] ) ), 'An app_key collision must be refused.' );
cap01b2_assert( is_wp_error( Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' ) ), 'A collision must make the whole registry unavailable.' );
cap01b2_static_set( 'Faluss_Apps_Registry', 'source_conflict', false );
$invalid_descriptor = $sources['faluss-hub']; $invalid_descriptor['source_type'] = 'browser';
cap01b2_assert( is_wp_error( Faluss_Apps_Registry::register_source( $invalid_descriptor ) ), 'An unknown source type must be refused.' );
$invalid_descriptor = $sources['faluss-hub']; $invalid_descriptor['manifest_resolver'] = 'not_a_callback';
cap01b2_assert( is_wp_error( Faluss_Apps_Registry::register_source( $invalid_descriptor ) ), 'A non-callable owner resolver must be refused.' );
$invalid_descriptor = $sources['faluss-me']; $invalid_descriptor['url'] = 'https://attacker.invalid';
cap01b2_assert( is_wp_error( Faluss_Apps_Registry::register_source( $invalid_descriptor ) ), 'A federated source descriptor must never accept a URL.' );

cap01b2_reset_runtime();
Faluss_Portal_Apps_Registry_Adapter::register_source();
Faluss_Identity_Client_Apps_Registry_Adapter::register_source();

Faluss_Identity_Client::$calls = 0; Token_Engine_Points_Service::$calls = 0; Faluss_Federation_Client::$calls = 0;
cap01b2_assert( is_wp_error( Faluss_Apps_Registry::read_for_member( 'invalid', 'portal', '1.0.0' ) ), 'An invalid UUID must be rejected before any source read.' );
cap01b2_assert( 0 === Faluss_Identity_Client::$calls && 0 === Token_Engine_Points_Service::$calls && 0 === Faluss_Federation_Client::$calls, 'Invalid input must perform no local or network read.' );
Faluss_Federation_Crypto::$identity = array( 'node_id' => 'me-node', 'app_key' => 'faluss-me', 'origin' => 'https://faluss.me', 'key_id' => 'me-key-0001' );
cap01b2_assert( is_wp_error( Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' ) ), 'The member resolver must be inactive outside hub-node/faluss-hub/faluss.com.' );
Faluss_Federation_Crypto::$identity = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'origin' => 'https://faluss.com', 'key_id' => 'hub-key-0001' );

$document = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
cap01b2_assert( is_array( $document ) && Faluss_Apps_Registry_Read_Model_Validator::validate( $document ), 'The complete production registry document must validate.' );
cap01b2_assert( array( 'faluss-hub', 'faluss-me' ) === array_column( $document['applications'], 'app_key' ), 'Application resolution must be deterministic.' );
cap01b2_assert( 1 === Faluss_Federation_Client::$calls && 1 === Faluss_Identity_Client::$calls && 1 === Token_Engine_Points_Service::$calls, 'First resolution must use each owner exactly once.' );
$apps = array_column( $document['applications'], null, 'app_key' );
$hub_capability = $apps['faluss-hub']['capabilities'][0];
cap01b2_assert( 'available' === $apps['faluss-hub']['availability'] && 'active' === $apps['faluss-hub']['member_relationship'], 'Hub must be available and linked for the proved member.' );
cap01b2_assert( 'available' === $apps['faluss-me']['availability'] && 'active' === $apps['faluss-me']['member_relationship'] && array() === $apps['faluss-me']['capabilities'], 'Published Me must be active without invented capabilities.' );
cap01b2_assert( 'enabled' === $hub_capability['state'] && 'available' === $hub_capability['specialized_read_model']['status'], 'Claimable Hub capability must retain the owner result.' );
cap01b2_assert( array( array( 'slot' => 'portal.apps.card_action', 'interface' => 'delegated_action', 'binding_state' => 'active' ) ) === $hub_capability['active_bindings'], 'Claimable Hub must activate only the requested Portal binding.' );
cap01b2_assert( 'faluss-hub.daily-reward.claim' === $hub_capability['allowed_actions'][0]['action_key'] && 'faluss-hub' === $hub_capability['allowed_actions'][0]['owner'] && 'owner_delegated_action' === $hub_capability['allowed_actions'][0]['delegation']['type'] && 'faluss-hub.daily-reward.claim' === $hub_capability['allowed_actions'][0]['delegation']['target'], 'Claimable Hub must expose only the owner-delegated symbolic action.' );
cap01b2_assert( array( 'engine' => 'faluss-hub', 'read_model' => 'hub-daily-reward', 'source_version' => '1.0.0' ) === $hub_capability['specialized_read_model']['source'], 'Daily Reward source must remain exact.' );
cap01b2_assert( false === strpos( json_encode( $document ), $faluss_id ), 'The final read-model must never contain the Faluss ID.' );

$cache_key = array_key_first( $GLOBALS['cap01b2_transients'] );
$saved_cache = $GLOBALS['cap01b2_transients'][ $cache_key ];
cap01b2_assert( is_string( $cache_key ) && false === strpos( $cache_key, $faluss_id ) && false === strpos( serialize( $saved_cache ), $faluss_id ), 'The public manifest cache must contain no member identifier.' );
cap01b2_assert( $GLOBALS['cap01b2_transient_ttls'][ $cache_key ] <= 300 && $GLOBALS['cap01b2_transient_ttls'][ $cache_key ] > 0, 'The manifest cache TTL must be positive and bounded to 300 seconds.' );
$cache_keys = array_keys( $saved_cache );
sort( $cache_keys, SORT_STRING );
cap01b2_assert( array( 'cached_at', 'contract_version', 'document_type', 'expires_at', 'manifest', 'manifest_version', 'responder_key_id' ) === $cache_keys, 'The cache must contain only the verified public manifest and bounded validation metadata.' );
$again = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
cap01b2_assert( is_array( $again ) && 1 === Faluss_Federation_Client::$calls && 1 === Faluss_Identity_Client::$calls && 1 === Token_Engine_Points_Service::$calls, 'A second resolution in the same request must use the valid public cache and request-local owner caches.' );

$tampered = $saved_cache; $tampered['manifest']['app_key'] = 'faluss-other'; $GLOBALS['cap01b2_transients'][ $cache_key ] = $tampered; Faluss_Federation_Client::$fail = true;
$without_me = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
cap01b2_assert( array( 'faluss-hub' ) === array_column( $without_me['applications'], 'app_key' ), 'A tampered cache plus refresh failure must omit only Me and never serve stale data.' );
$GLOBALS['cap01b2_transients'][ $cache_key ] = $saved_cache; $GLOBALS['cap01b2_transients'][ $cache_key ]['expires_at'] = gmdate( 'Y-m-d\\TH:i:s\\Z', time() - 1 );
$without_me = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
cap01b2_assert( array( 'faluss-hub' ) === array_column( $without_me['applications'], 'app_key' ), 'An expired cache must not be served when refresh fails.' );
$GLOBALS['cap01b2_transients'][ $cache_key ] = $saved_cache; Faluss_Federation_Policy::$key_id = 'rotated-key-0002';
$without_me = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
cap01b2_assert( array( 'faluss-hub' ) === array_column( $without_me['applications'], 'app_key' ), 'A cache bound to another active key must be invalidated.' );
Faluss_Federation_Policy::$key_id = 'me-key-0001'; Faluss_Federation_Client::$fail = false; $GLOBALS['cap01b2_transients'] = array();

cap01b2_static_set( 'Faluss_Identity_Client_Apps_Registry_Adapter', 'relationship_cache', array() ); Faluss_Identity_Client::$projection = null;
$not_linked = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
$not_linked_apps = array_column( $not_linked['applications'], null, 'app_key' );
cap01b2_assert( 'not_linked' === $not_linked_apps['faluss-me']['member_relationship'], 'A confirmed absent Me projection must resolve to not_linked.' );
cap01b2_static_set( 'Faluss_Identity_Client_Apps_Registry_Adapter', 'relationship_cache', array() ); Faluss_Identity_Client::$projection = new WP_Error( 'projection_unavailable' );
$authority_failure = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
cap01b2_assert( array( 'faluss-hub' ) === array_column( $authority_failure['applications'], 'app_key' ), 'An unavailable relation authority must omit Me rather than fabricate not_linked.' );
$saved_wpdb = $wpdb; $wpdb = null; Faluss_Identity_Client::$projection = $valid_projection; cap01b2_static_set( 'Faluss_Identity_Client_Apps_Registry_Adapter', 'relationship_cache', array() );
$missing_authority = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
cap01b2_assert( array( 'faluss-hub' ) === array_column( $missing_authority['applications'], 'app_key' ), 'A missing local projection authority must omit Me instead of treating the outage as not_linked.' );
$wpdb = $saved_wpdb;

Faluss_Identity_Client::$projection = $valid_projection;
cap01b2_static_set( 'Faluss_Identity_Client_Apps_Registry_Adapter', 'relationship_cache', array() );
Token_Engine_Points_Service::$state = 'claimed'; cap01b2_static_set( 'Faluss_Portal_Apps_Registry_Adapter', 'capability_cache', array() );
$claimed_document = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
$claimed_capability = array_column( $claimed_document['applications'], null, 'app_key' )['faluss-hub']['capabilities'][0];
cap01b2_assert( 1 === count( $claimed_capability['active_bindings'] ) && array() === $claimed_capability['allowed_actions'], 'Claimed Hub must keep its binding without another action.' );
Token_Engine_Points_Service::$state = 'unavailable'; cap01b2_static_set( 'Faluss_Portal_Apps_Registry_Adapter', 'capability_cache', array() );
$unavailable_document = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
$unavailable_capability = array_column( $unavailable_document['applications'], null, 'app_key' )['faluss-hub']['capabilities'][0];
cap01b2_assert( 'temporarily_unavailable' === $unavailable_capability['state'] && 'unavailable' === $unavailable_capability['specialized_read_model']['status'] && array() === $unavailable_capability['active_bindings'] && array() === $unavailable_capability['allowed_actions'], 'Unavailable Core PF must withdraw only the Hub daily outputs.' );
Token_Engine_Points_Service::$state = 'not_supported'; cap01b2_static_set( 'Faluss_Portal_Apps_Registry_Adapter', 'capability_cache', array() );
$unsupported_document = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
$unsupported_capability = array_column( $unsupported_document['applications'], null, 'app_key' )['faluss-hub']['capabilities'][0];
cap01b2_assert( 'not_supported' === $unsupported_capability['state'] && 'not_supported' === $unsupported_capability['specialized_read_model']['status'] && array() === $unsupported_capability['active_bindings'], 'Unsupported Core PF must remain explicitly unsupported without outputs.' );

cap01b2_reset_runtime(); Faluss_Portal_Apps_Registry_Adapter::register_source();
$hub_source = cap01b2_static_get( 'Faluss_Apps_Registry', 'sources' )['faluss-hub'];
$incompatible_manifest = Faluss_Portal_Manifest::manifest(); $incompatible_manifest['capabilities'][0]['compatibility']['compatible_with'] = array( '2.0.0' );
$hub_source['manifest_resolver'] = function() use ( $incompatible_manifest ) { return $incompatible_manifest; };
cap01b2_static_set( 'Faluss_Apps_Registry', 'sources', array() ); Faluss_Apps_Registry::register_source( $hub_source );
$incompatible_document = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
$incompatible_capability = $incompatible_document['applications'][0]['capabilities'][0];
cap01b2_assert( 'incompatible' === $incompatible_capability['surface_compatibility']['status'] && array() === $incompatible_capability['active_bindings'] && array() === $incompatible_capability['allowed_actions'], 'An incompatible capability must expose no active output.' );
$retired_manifest = Faluss_Portal_Manifest::manifest(); $retired_manifest['product_state'] = 'retired';
$hub_source['manifest_resolver'] = function() use ( $retired_manifest ) { return $retired_manifest; };
cap01b2_static_set( 'Faluss_Apps_Registry', 'sources', array() ); Faluss_Apps_Registry::register_source( $hub_source ); cap01b2_static_set( 'Faluss_Portal_Apps_Registry_Adapter', 'capability_cache', array() );
$retired_document = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
$retired_app = $retired_document['applications'][0];
cap01b2_assert( 'retired' === $retired_app['availability'] && array() === $retired_app['capabilities'][0]['active_bindings'] && array() === $retired_app['capabilities'][0]['allowed_actions'], 'A retired application must expose no active output.' );

$invalid_document = $document; $invalid_document['applications'][] = $invalid_document['applications'][0];
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'Duplicate applications must be refused.' );
$invalid_document = $document; $invalid_document['faluss_id'] = $faluss_id;
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'Sensitive member data must be refused recursively.' );
$invalid_document = $document; $invalid_document['applications'][0]['member_relationship'] = 'not_linked';
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'A not-linked application must not retain active outputs.' );
$invalid_document = $document; $invalid_document['applications'][0]['capabilities'][0]['allowed_actions'][0]['owner'] = 'faluss-me';
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'Action ownership divergence must be refused.' );
$invalid_document = $document; $invalid_document['freshness']['generated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', time() - 61 );
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'A stale global read-model must be refused.' );
$invalid_document = $document; $invalid_document['applications'][0]['capabilities'][0]['specialized_read_model']['freshness']['generated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', time() - 61 );
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'A stale available specialized read-model must be refused.' );
$invalid_document = $document; $invalid_document['extra'] = true;
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'A read-model with an additional root key must be refused.' );
$invalid_document = $document; $invalid_document['applications'][0]['capabilities'][0]['surface_compatibility']['consumer_version'] = '2.0.0';
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'Capability compatibility must remain bound to the root consumer version.' );
$invalid_document = $document; $invalid_document['applications'][0]['capabilities'][0]['active_bindings'][0]['slot'] = 'master_profile.module';
cap01b2_assert( ! Faluss_Apps_Registry_Read_Model_Validator::validate( $invalid_document ), 'An active binding outside the declared consumer surface must be refused.' );

cap01b2_reset_runtime(); Faluss_Portal_Apps_Registry_Adapter::register_source(); Faluss_Identity_Client_Apps_Registry_Adapter::register_source();
$portal_document = Faluss_Apps_Registry::read_for_member( $faluss_id, 'portal', '1.0.0' );
$registry_method = new ReflectionMethod( 'Faluss_Portal', 'app_registry' ); $registry_method->setAccessible( true );
$panel_method = new ReflectionMethod( 'Faluss_Portal', 'apps_panel' ); $panel_method->setAccessible( true );
$catalog = $registry_method->invoke( null, $faluss_id, $portal_document );
cap01b2_assert( array( 'hub', 'me', 'date', 'fans', 'pro' ) === array_column( $catalog, 'slug' ), 'Explorer presentation catalog must retain its five-card order.' );
$catalog_by_slug = array_column( $catalog, null, 'slug' );
cap01b2_assert( true === $catalog_by_slug['hub']['available'] && true === $catalog_by_slug['hub']['owned'] && true === $catalog_by_slug['hub']['active'], 'Portal Hub decisions must come from apps.registry.' );
cap01b2_assert( true === $catalog_by_slug['me']['available'] && true === $catalog_by_slug['me']['owned'] && 'https://faluss.me/mon-faluss' === $catalog_by_slug['me']['url'], 'Portal Me relation and destination must reuse the AP-02A adapter projection.' );
foreach ( array( 'date', 'fans', 'pro' ) as $slug ) { cap01b2_assert( false === $catalog_by_slug[ $slug ]['available'] && ! in_array( 'faluss-' . $slug, array_column( $portal_document['applications'], 'app_key' ), true ), 'Future catalog card must stay outside apps.registry: ' . $slug ); }
ob_start(); $panel_method->invoke( null, 'my-apps', $faluss_id, $portal_document ); $owned_html = ob_get_clean();
ob_start(); $panel_method->invoke( null, 'explore', $faluss_id, $portal_document ); $explore_html = ob_get_clean();
cap01b2_assert( '633a63073722f0a3ea87214aced960595a8947b3faeb3d39e0174eee034010a4' === hash( 'sha256', $owned_html ), 'Equivalent Hub + Me claimable state must preserve Mes Apps HTML byte-for-byte.' );
cap01b2_assert( '2ed5dba1a2b518ab32ed036cd5f2742c3e0ce99ed94c4a9699b3f09be168339e' === hash( 'sha256', $explore_html ), 'Equivalent runtime state must preserve Explorer HTML byte-for-byte.' );
cap01b2_assert( 2 === substr_count( $owned_html, 'data-faluss-app-card' ) && false !== strpos( $owned_html, 'href="https://faluss.me/mon-faluss"' ), 'Mes Apps must contain Hub then linked Me at its canonical destination.' );

$not_linked_portal = $portal_document; $not_linked_portal['applications'][1]['member_relationship'] = 'not_linked';
$not_linked_portal['applications'][1]['capabilities'] = array();
ob_start(); $panel_method->invoke( null, 'my-apps', $faluss_id, $not_linked_portal ); $hub_only_html = ob_get_clean();
cap01b2_assert( 1 === substr_count( $hub_only_html, 'data-faluss-app-card' ) && false === strpos( $hub_only_html, 'data-faluss-app="me"' ), 'A not-linked Me member must see only Hub in Mes Apps.' );
$failed_me_portal = $portal_document; array_pop( $failed_me_portal['applications'] );
$failed_catalog = $registry_method->invoke( null, $faluss_id, $failed_me_portal );
cap01b2_assert( true === $failed_catalog[0]['owned'] && false === $failed_catalog[1]['available'], 'A missing Me source must never remove Hub or reactivate Me.' );
$no_binding = $portal_document; $no_binding['applications'][0]['capabilities'][0]['active_bindings'] = array(); $no_binding['applications'][0]['capabilities'][0]['allowed_actions'] = array();
ob_start(); $panel_method->invoke( null, 'my-apps', $faluss_id, $no_binding ); $no_daily_html = ob_get_clean();
cap01b2_assert( false === strpos( $no_daily_html, 'faluss-portal__hub-daily-form' ) && false === strpos( $no_daily_html, 'assets/images/pf/faluss-pf-badge.png' ), 'Without the exact binding Portal must render only normal navigation.' );
ob_start(); $panel_method->invoke( null, 'my-apps', $faluss_id, $claimed_document ); $claimed_html = ob_get_clean();
cap01b2_assert( false === strpos( $claimed_html, '<form') && false !== strpos( $claimed_html, 'faluss-portal__app-open--reward' ), 'Claimed binding must preserve the non-interactive pill.' );

$portal_source = file_get_contents( $paths['portal'] );
cap01b2_assert( 1 === substr_count( $portal_source, 'Faluss_Apps_Registry::read_for_member' ), 'Portal must request exactly one registry document through the fixed internal facade.' );
cap01b2_assert( false !== strpos( $portal_source, 'self::render_panels( $route, $snapshot, $member, $apps_registry )' ) && false !== strpos( $portal_source, 'self::apps_panel( $tab, $member[\'faluss_id\'], $apps_registry )' ), 'The single Portal snapshot must be passed to both Apps panels without another registry read.' );
cap01b2_assert( false === strpos( $portal_source, 'Faluss_Identity_Client::member_app_projection' ) && false === strpos( $portal_source, "'available'   => true" ), 'Portal must no longer retain its old Hub/Me runtime decisions.' );
function cap01b2_method_hash( $source, $start, $end ) { $a = strpos( $source, $start ); $b = strpos( $source, $end, $a + 1 ); return false === $a || false === $b ? '' : hash( 'sha256', substr( $source, $a, $b - $a ) ); }
cap01b2_assert( '6332bc5a6395983a7cc742bb2127688bec9dcc0235636b353310db10542aba9d' === cap01b2_method_hash( $portal_source, '    private static function render_app_card', "    /**\n     * Renders the only PF action" ), 'render_app_card() must remain byte-for-byte unchanged.' );
cap01b2_assert( '5095cab76bbc94e3711fa96bbef99aac946879941512f0ac5e00d4981d3e5e2b' === cap01b2_method_hash( $portal_source, '    private static function render_hub_daily_action', '    /** The supplied PF badge' ), 'render_hub_daily_action() must remain byte-for-byte unchanged.' );
cap01b2_assert( '4d5cbd9c717c967478514b814b15c6294030b89e34e016b9009f9ff1686b3d64' === cap01b2_method_hash( $portal_source, '    private static function hub_pf_badge', "    /**\n     * Authenticated fixed-intent claim endpoint" ), 'The PF SVG/image helper boundary must remain byte-for-byte unchanged.' );
cap01b2_assert( '5ea20e08569d7b87f80010b352ece8291f1336d114ac56961b22651bc3618f88' === cap01b2_method_hash( $portal_source, '    private static function icon(', "\n}" ), 'Portal SVG helpers must remain byte-for-byte unchanged.' );
$asset_hashes = array(
    'assets/css/faluss-portal.css' => 'e229f44faad1d390b0af5a9aea606bcc878cf013c9650f0733288bf9dcbcab02',
    'assets/images/apps/faluss-date.png' => '21ee86bbe26342a2ca4a6f979a0052b3cf24a9ff0fd15c1c54c3638e8c113ed8',
    'assets/images/apps/faluss-hub.png' => '5a534c15e5254c6982df0739152c3784dbb2edd7c7972976e32e306fc55b017b',
    'assets/images/apps/faluss-me.png' => '1541ef775c32d229c11ec79a579ef9d371cf8f23a77c0c4b1920fda5bdb6d64a',
    'assets/images/apps/faluss-pro.png' => '33c828517cb2b9de0599ea3df6d0c735266bcbbc63212c743d1ef4e81f2467ff',
    'assets/images/pf/faluss-pf-badge.png' => 'a25533ca502e4e6286cb58c858de8d7a4de5d25b18a5d91894b31c46ff1f1955',
    'assets/js/faluss-portal.js' => '91de8ff3b1f77827c2318283d63b5aaa2500e19a52abdfe989360b29a76bdf62',
);
foreach ( $asset_hashes as $relative => $hash ) { cap01b2_assert( $hash === hash_file( 'sha256', $root . '/plugins/faluss-portal/' . $relative ), 'Portal asset must remain byte-for-byte unchanged: ' . $relative ); }

$new_runtime_source = file_get_contents( $paths['registry'] ) . file_get_contents( $paths['read_model_validator'] ) . file_get_contents( $paths['portal_adapter'] ) . file_get_contents( $paths['identity_adapter'] );
foreach ( array( 'register_rest_route', 'wp_ajax_', 'admin_post_', 'add_shortcode', 'dbDelta', 'CREATE TABLE', 'wp_schedule' ) as $forbidden ) { cap01b2_assert( false === strpos( $new_runtime_source, $forbidden ), 'CAP-01B.2 must add no endpoint, shortcode, table, migration or cron: ' . $forbidden ); }
cap01b2_assert( false !== strpos( file_get_contents( $paths['registry_bootstrap'] ), 'Version: 0.2.0' ) && false !== strpos( file_get_contents( $paths['identity_bootstrap'] ), 'Version: 0.5.3' ) && false !== strpos( file_get_contents( $paths['portal_bootstrap'] ), 'Version: 0.1.24' ), 'Apps Registry and Identity Client remain fixed while Portal advances to AN-01B.2.' );
cap01b2_assert( false !== strpos( file_get_contents( $paths['federation_bootstrap'] ), 'Version: 0.3.0' ) && false !== strpos( file_get_contents( $paths['link_bootstrap'] ), 'Version: 0.3.21' ) && false !== strpos( file_get_contents( $paths['identity_bootstrap_owner'] ), 'Version: 0.4.15' ), 'Federation and Identity remain fixed while Link advances to AN-01B.2.' );

echo 'CAP-01B.2 runtime registry: OK (' . $cap01b2_assertions . ' assertions; production registry/adapters/Portal)' . PHP_EOL;
