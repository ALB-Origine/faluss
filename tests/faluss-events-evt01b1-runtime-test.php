<?php

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }

final class WP_Error {
    private $code;
    public function __construct( $code ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
final class WP_REST_Response {
    private $data;
    private $status;
    private $headers = array();
    public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
    public function header( $name, $value ) { $this->headers[ $name ] = $value; }
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
    public function get_headers() { return $this->headers; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function wp_generate_uuid4() { return 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'; }
function absint( $value ) { return abs( (int) $value ); }
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['evt01b1_hooks'][ $hook ][ $priority ][] = $callback; }
function did_action( $hook ) { return (int) ( $GLOBALS['evt01b1_actions'][ $hook ] ?? 0 ); }

final class Faluss_Federation_Crypto {
    public static $identity = array( 'node_id' => 'me-node', 'app_key' => 'faluss-me', 'key_id' => 'me-key-0001' );
    public static function is_node( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $value ); }
    public static function is_semver( $value ) { return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*\.[0-9]+\.[0-9]+$/D', $value ); }
    public static function is_key_id( $value ) { return is_string( $value ) && strlen( $value ) >= 8; }
    public static function is_uuid( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value ); }
    public static function is_nonce( $value ) { return is_string( $value ) && 43 === strlen( $value ); }
    public static function is_utc_timestamp( $value ) { return is_string( $value ) && 1 === preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $value ); }
    public static function parse_utc_timestamp( $value ) { $time = strtotime( $value ); return false === $time ? new WP_Error( 'date' ) : $time; }
    public static function format_utc_timestamp( $value ) { return gmdate( 'Y-m-d\TH:i:s\Z', $value ); }
    public static function is_canonical_origin( $value ) { return is_string( $value ) && 0 === strpos( $value, 'https://' ); }
    public static function base64url_decode( $value, $length ) { unset( $value ); return str_repeat( 'x', $length ); }
    public static function response_canonical( $response, $status, $raw ) { unset( $response, $status, $raw ); return 'canonical'; }
    public static function sign( $canonical ) { unset( $canonical ); return str_repeat( 'A', 86 ); }
    public static function transport_ready() { return true; }
    public static function local_identity() { return self::$identity; }
}
final class Faluss_Federation_Schema {
    public static $audits = array();
    public static function is_ready() { return true; }
    public static function audit( $record ) { self::$audits[] = $record; return true; }
    public static function quote_identifier( $value ) { return '`' . $value . '`'; }
    public static function peers_table() { return 'wp_faluss_federation_peers'; }
}
final class Faluss_Federation_Client {
    public static $manifest_response;
    public static $catalog_response;
    public static $calls = array();
    public static function manifest_read( $node, $app, $version ) { self::$calls[] = array( 'manifest.read', $node, $app, $version ); return self::$manifest_response; }
    public static function event_catalog_read( $node, $app, $owner, $capability, $version ) { self::$calls[] = array( 'event_catalog.read', $node, $app, $owner, $capability, $version ); return self::$catalog_response; }
}

$evt01b1_assertions = 0;
function evt01b1_assert( $condition, $message ) {
    global $evt01b1_assertions;
    $evt01b1_assertions++;
    if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL ); exit( 1 ); }
}
function evt01b1_private( $class, $method, $arguments = array() ) { $reflection = new ReflectionMethod( $class, $method ); $reflection->setAccessible( true ); return $reflection->invokeArgs( null, $arguments ); }
function evt01b1_static_set( $class, $property, $value ) { $reflection = new ReflectionProperty( $class, $property ); $reflection->setAccessible( true ); $reflection->setValue( null, $value ); }

$root = dirname( __DIR__ );
$paths = array(
    'catalog_validator' => $root . '/plugins/faluss-events/includes/class-faluss-events-catalog-validator.php',
    'envelope_validator' => $root . '/plugins/faluss-events/includes/class-faluss-events-envelope-validator.php',
    'events' => $root . '/plugins/faluss-events/includes/class-faluss-events.php',
    'manifest_validator' => $root . '/plugins/faluss-apps-registry/includes/class-faluss-apps-registry-manifest-validator.php',
    'providers' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-providers.php',
    'policy' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-policy.php',
    'server' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-server.php',
    'client' => $root . '/plugins/faluss-federation/includes/class-faluss-federation-client.php',
);
foreach ( $paths as $path ) { evt01b1_assert( is_file( $path ), 'Required production artifact missing: ' . $path ); }
$events_bootstrap_path = $root . '/plugins/faluss-events/faluss-events.php';
$federation_bootstrap_path = $root . '/plugins/faluss-federation/faluss-federation.php';
foreach ( array( array( $events_bootstrap_path ), array( $events_bootstrap_path, $federation_bootstrap_path ), array( $federation_bootstrap_path, $events_bootstrap_path ) ) as $order ) {
    $snippet = "define('ABSPATH', __DIR__); function add_action(\$h,\$c,\$p=10){} function did_action(\$h){return 0;} function plugin_dir_path(\$f){return dirname(\$f).DIRECTORY_SEPARATOR;} function register_activation_hook(\$f,\$c){}";
    foreach ( $order as $bootstrap_path ) { $snippet .= ' require ' . var_export( $bootstrap_path, true ) . ';'; }
    $load_output = array(); $load_status = 0;
    exec( PHP_BINARY . ' -r ' . escapeshellarg( $snippet ) . ' 2>&1', $load_output, $load_status );
    evt01b1_assert( 0 === $load_status, 'Plugin loading must stay safe without dependencies and in both activation orders: ' . implode( ' then ', array_map( 'basename', $order ) ) . ' / ' . implode( ' | ', $load_output ) );
}
require_once $paths['manifest_validator'];
require_once $paths['catalog_validator'];
require_once $paths['envelope_validator'];
require_once $paths['providers'];
require_once $paths['policy'];
require_once $paths['server'];
require_once $paths['events'];

function evt01b1_definition( $app, $type, $payload_type, $destinations = array( 'analytics.events' ) ) {
    return array(
        'event_type' => $app . '.' . $type,
        'event_version' => '1.0.0',
        'payload_contract' => array( 'document_type' => $app . '.' . $payload_type, 'contract_version' => '1.0.0' ),
        'subject_policy' => 'required',
        'allowed_actor_types' => array( 'member' ),
        'object_policy' => array( 'presence' => 'forbidden', 'allowed_types' => array() ),
        'allowed_destinations' => $destinations,
        'max_delivery_delay_seconds' => 3600,
        'data_classification' => 'pseudonymous',
        'max_retention_seconds' => 2592000,
        'member_result_visibility' => 'own_subject_only',
        'lifecycle' => array( 'deprecated' => false, 'sunset_at' => null, 'replacement_event_type' => null ),
    );
}
function evt01b1_catalog( $app, $node, $engine, $definition ) {
    return array(
        'contract_version' => '1.0.0',
        'document_type' => 'faluss.event-source-catalog',
        'catalog_version' => '1.0.0',
        'node_id' => $node,
        'app_key' => $app,
        'owner' => $app,
        'owner_engine' => $engine,
        'capability_key' => $app . '.events',
        'capability_interface' => 'event_source',
        'event_types' => array( $definition ),
        'compatibility' => array( 'minimum_runtime_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_catalog_version' => null ),
    );
}
function evt01b1_manifest( $app, $authority, $destinations ) {
    $bindings = array();
    foreach ( $destinations as $destination ) { $bindings[] = array( 'interface' => 'event_source', 'slot' => $destination ); }
    return array(
        'manifest_version' => '1.0.0',
        'app_key' => $app,
        'capability_namespace' => $app,
        'owner' => array( 'engine' => $app, 'authority' => $authority ),
        'product_state' => 'active',
        'canonical_origins' => array( 'https://' . $authority ),
        'public_presentation' => array( 'display_name' => 'Future source', 'summary' => 'Future event source used only by the executable contract test.' ),
        'official_asset' => null,
        'capabilities' => array( array(
            'capability_key' => $app . '.events',
            'interfaces' => array( 'event_source' ),
            'requested_bindings' => $bindings,
            'read_model_contract' => null,
            'symbolic_actions' => array(),
            'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_capability_key' => null ),
        ) ),
        'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_capability_key' => null ),
    );
}
function evt01b1_manifest_context( $app, $node ) {
    return array( 'operation' => 'manifest.read', 'parameters' => array( 'app_key' => $app, 'requested_manifest_version' => '1.0.0' ), 'subject_context' => null, 'sender' => array( 'node_id' => 'consumer-node', 'app_key' => 'consumer-app' ), 'recipient' => array( 'node_id' => $node, 'app_key' => $app ) );
}
function evt01b1_catalog_request( $app, $node, $capability = null ) {
    return array(
        'protocol_version' => '1', 'message_type' => 'request', 'request_id' => '11111111-1111-4111-8111-111111111111', 'operation' => 'event_catalog.read',
        'sender' => array( 'node_id' => 'consumer-node', 'app_key' => 'consumer-app', 'key_id' => 'consumer-key-0001' ),
        'recipient' => array( 'node_id' => $node, 'app_key' => $app ), 'issued_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 1 ), 'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() + 299 ), 'nonce' => str_repeat( 'A', 43 ), 'subject_context' => null,
        'parameters' => array( 'owner_app_key' => $app, 'capability_key' => null === $capability ? $app . '.events' : $capability, 'catalog_version' => '1.0.0' ),
    );
}
function evt01b1_event( $catalog, $definition ) {
    $subject = '11111111-1111-4111-8111-111111111111';
    return array(
        'contract_version' => '1.0.0', 'event_id' => '22222222-2222-4222-8222-222222222222', 'event_type' => $definition['event_type'], 'event_version' => $definition['event_version'],
        'source' => array( 'node_id' => $catalog['node_id'], 'app_key' => $catalog['app_key'], 'owner' => $catalog['owner'], 'capability_key' => $catalog['capability_key'], 'catalog_version' => $catalog['catalog_version'] ),
        'source_event_reference' => 'event-ref-0001', 'occurred_at' => '2026-09-13T10:00:00Z', 'produced_at' => '2026-09-13T10:00:01Z',
        'subject_context' => array( 'subject_type' => 'faluss_member', 'subject_faluss_id' => $subject ),
        'actor_context' => array( 'actor_type' => 'member', 'actor_faluss_id' => $subject, 'anonymous_reference' => null, 'anonymous_scope' => null ),
        'object_context' => null, 'destinations' => array( 'analytics.events' ), 'payload_contract' => $definition['payload_contract'], 'payload' => array( 'surface' => 'apps' ),
    );
}
function evt01b1_provider_result( $contract, $payload ) { return array( 'payload_contract' => $contract, 'payload' => $payload ); }
function evt01b1_remote_response( $node, $app, $contract, $payload ) {
    return array( 'status' => 'success', 'payload_contract' => $contract, 'payload' => $payload, 'error' => null, 'responder' => array( 'node_id' => $node, 'app_key' => $app, 'key_id' => 'remote-key-0001' ), 'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() + 300 ) );
}

$hub_definition = evt01b1_definition( 'faluss-hub', 'portal.viewed', 'portal-viewed', array( 'analytics.events', 'quests.events' ) );
$me_definition = evt01b1_definition( 'faluss-me', 'card.viewed', 'card-viewed' );
$hub_catalog = evt01b1_catalog( 'faluss-hub', 'hub-node', 'faluss-portal', $hub_definition );
$me_catalog = evt01b1_catalog( 'faluss-me', 'me-node', 'faluss-link', $me_definition );
$hub_manifest = evt01b1_manifest( 'faluss-hub', 'faluss.com', array( 'analytics.events', 'quests.events' ) );
$me_manifest = evt01b1_manifest( 'faluss-me', 'faluss.me', array( 'analytics.events' ) );
$cap_contract = array( 'document_type' => 'faluss.app-capability-manifest', 'contract_version' => '1.0.0' );
$catalog_contract = array( 'document_type' => 'faluss.event-source-catalog', 'contract_version' => '1.0.0' );

evt01b1_assert( Faluss_Events_Catalog_Validator::validate( $hub_catalog ), 'Future Hub catalog must pass the production validator.' );
evt01b1_assert( Faluss_Events_Catalog_Validator::validate( $me_catalog ), 'Future Me catalog must pass the production validator.' );
evt01b1_assert( Faluss_Events_Catalog_Validator::validate_against_manifest( $hub_catalog, $hub_manifest, $cap_contract, evt01b1_manifest_context( 'faluss-hub', 'hub-node' ) ), 'Exact valid CAP/EVT pairing must pass.' );
$invalid_manifest = $hub_manifest; $invalid_manifest['capabilities'] = array();
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate_against_manifest( $hub_catalog, $invalid_manifest, $cap_contract, evt01b1_manifest_context( 'faluss-hub', 'hub-node' ) ), 'Missing exact CAP capability must fail.' );
$invalid_manifest = $hub_manifest; $invalid_manifest['capabilities'][0]['interfaces'] = array( 'delegated_action' ); $invalid_manifest['capabilities'][0]['requested_bindings'] = array();
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate_against_manifest( $hub_catalog, $invalid_manifest, $cap_contract, evt01b1_manifest_context( 'faluss-hub', 'hub-node' ) ), 'Capability without event_source must fail.' );
$invalid_manifest = $hub_manifest; array_pop( $invalid_manifest['capabilities'][0]['requested_bindings'] );
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate_against_manifest( $hub_catalog, $invalid_manifest, $cap_contract, evt01b1_manifest_context( 'faluss-hub', 'hub-node' ) ), 'Every catalog destination needs an exact CAP event binding.' );
$invalid = $hub_catalog; $invalid['owner'] = 'faluss-me';
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate( $invalid ), 'Owner mismatch must fail.' );
$invalid = $hub_catalog; $invalid['event_types'][0]['event_type'] = 'faluss-me.portal.viewed';
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate( $invalid ), 'Foreign event namespace must fail.' );
$invalid = $hub_catalog; $invalid['endpoint'] = 'https://example.invalid';
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate( $invalid ), 'Additional forbidden catalog content must fail.' );
$invalid = $hub_catalog; $invalid['event_types'][] = $hub_definition;
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate( $invalid ), 'Duplicate event type and version must fail.' );
$deprecated = $hub_catalog; $deprecated['compatibility']['deprecated'] = true;
evt01b1_assert( Faluss_Events_Catalog_Validator::validate( $deprecated ) && ! Faluss_Events_Catalog_Validator::validate_against_manifest( $deprecated, $hub_manifest, $cap_contract, evt01b1_manifest_context( 'faluss-hub', 'hub-node' ) ), 'A well-formed but deprecated catalog must be cross-incompatible.' );
$invalid = $hub_catalog; $invalid['compatibility']['compatible_with'] = array();
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate( $invalid ), 'Invalid catalog compatibility must fail.' );

evt01b1_assert( ! Faluss_Events::validate_event( evt01b1_event( $hub_catalog, $hub_definition ), $hub_catalog ), 'Missing specialized validator must fail closed.' );
evt01b1_assert( true === Faluss_Events::register_payload_validator( 'faluss-hub.portal-viewed', '1.0.0', function ( $payload ) { return array( 'surface' => 'apps' ) === $payload; } ), 'Trusted specialized payload validator must register.' );
$event = evt01b1_event( $hub_catalog, $hub_definition );
evt01b1_assert( Faluss_Events::validate_event( $event, $hub_catalog ), 'Exact envelope, accepted catalog and trusted validator must pass.' );
evt01b1_assert( ! Faluss_Events_Envelope_Validator::validate( $event, null, function () { return true; } ), 'Envelope without an accepted catalog must fail.' );
$invalid = $event; $invalid['payload']['surface'] = 'other';
evt01b1_assert( ! Faluss_Events::validate_event( $invalid, $hub_catalog ), 'Specialized payload failure must reject the envelope.' );
$invalid = $event; $invalid['payload'] = array( 'email' => 'member@example.test' );
evt01b1_assert( ! Faluss_Events::validate_event( $invalid, $hub_catalog ), 'Sensitive payload data must fail generically.' );
$invalid = $event; $invalid['source_event_reference'] = '33333333-3333-4333-8333-333333333333';
evt01b1_assert( ! Faluss_Events::validate_event( $invalid, $hub_catalog ), 'Sensitive/linkable UUID references must fail.' );

evt01b1_assert( true === Faluss_Events::register_federation_integration() && true === Faluss_Events::register_federation_integration(), 'Repeated integration must be idempotent without duplicate validator conflict.' );
$request = evt01b1_catalog_request( 'faluss-hub', 'hub-node' );
$catalog_context = array( 'operation' => $request['operation'], 'parameters' => $request['parameters'], 'subject_context' => $request['subject_context'], 'sender' => $request['sender'], 'recipient' => $request['recipient'] );
evt01b1_assert( Faluss_Events_Catalog_Validator::validate_federation_payload( $hub_catalog, $catalog_contract, $catalog_context ), 'Catalog matching the recipient node and app must pass Federation payload validation.' );
$wrong_node_catalog = $hub_catalog; $wrong_node_catalog['node_id'] = 'other-node';
evt01b1_assert( ! Faluss_Events_Catalog_Validator::validate_federation_payload( $wrong_node_catalog, $catalog_contract, $catalog_context ), 'Catalog node must match the exact Federation recipient node.' );
evt01b1_assert( evt01b1_private( 'Faluss_Federation_Server', 'valid_request', array( $request ) ), 'Production server must accept exactly the three event catalog parameters.' );
$invalid = $request; $invalid['subject_context'] = array( 'subject_faluss_id' => '11111111-1111-4111-8111-111111111111' );
evt01b1_assert( ! evt01b1_private( 'Faluss_Federation_Server', 'valid_request', array( $invalid ) ), 'event_catalog.read subject must be null.' );
$invalid = $request; $invalid['parameters']['audience'] = 'private';
evt01b1_assert( ! evt01b1_private( 'Faluss_Federation_Server', 'valid_request', array( $invalid ) ), 'No fourth event catalog parameter or audience is accepted.' );
$invalid = $request; $invalid['operation'] = 'event.publish';
evt01b1_assert( ! evt01b1_private( 'Faluss_Federation_Server', 'valid_request', array( $invalid ) ), 'event.publish with catalog-read parameters must remain invalid.' );
$catalog_response = array( 'status' => 'success', 'payload_contract' => $catalog_contract, 'payload' => $hub_catalog, 'error' => null );
$manifest_request = $request; $manifest_request['operation'] = 'manifest.read'; $manifest_request['parameters'] = array( 'app_key' => 'faluss-hub', 'requested_manifest_version' => '1.0.0' );
evt01b1_assert( ! Faluss_Federation_Providers::validate_received_payload( $catalog_response, $manifest_request ), 'manifest.read cannot tunnel an EVT catalog.' );
$read_request = $request; $read_request['operation'] = 'read_model.read'; $read_request['parameters'] = array( 'owner_app_key' => 'faluss-hub', 'capability_key' => 'faluss-hub.events', 'document_type' => 'faluss.event-source-catalog', 'contract_version' => '1.0.0', 'audience' => 'private' );
evt01b1_assert( ! Faluss_Federation_Providers::validate_received_payload( $catalog_response, $read_request ), 'read_model.read cannot tunnel an EVT catalog.' );

$peer = array( 'id' => 1, 'peer_node_id' => 'consumer-node', 'peer_app_key' => 'consumer-app', 'canonical_origin' => 'https://consumer.test', 'key_id' => 'consumer-key-0001', 'public_key' => str_repeat( 'A', 43 ), 'key_state' => 'active', 'valid_from' => gmdate( 'Y-m-d H:i:s', time() - 60 ), 'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 3600 ), 'operations' => array( 'event_catalog.read' ), 'owner_apps' => array( 'faluss-hub' ), 'capabilities' => array( 'faluss-hub.events' ), 'audiences' => array() );
$identity = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'key_id' => 'hub-key-0001' );
evt01b1_assert( true === Faluss_Federation_Policy::allow_incoming( $request, $peer, $identity ), 'Exact operation, owner and capability policy must authorize.' );
$invalid = $request; $invalid['sender']['node_id'] = 'other-node';
evt01b1_assert( is_wp_error( Faluss_Federation_Policy::allow_incoming( $invalid, $peer, $identity ) ), 'Sender must match the exact stored peer.' );
$invalid_peer = $peer; $invalid_peer['operations'] = array( 'diagnostic.read' );
evt01b1_assert( is_wp_error( Faluss_Federation_Policy::allow_incoming( $request, $invalid_peer, $identity ) ), 'Policy without event_catalog.read must refuse.' );
$invalid_peer = $peer; $invalid_peer['owner_apps'] = array();
evt01b1_assert( is_wp_error( Faluss_Federation_Policy::allow_incoming( $request, $invalid_peer, $identity ) ), 'Policy without exact owner must refuse.' );
$invalid_peer = $peer; $invalid_peer['capabilities'] = array();
evt01b1_assert( is_wp_error( Faluss_Federation_Policy::allow_incoming( $request, $invalid_peer, $identity ) ), 'Policy without exact capability must refuse.' );
evt01b1_assert( 'not_available' === Faluss_Federation_Providers::dispatch( $request, $identity )['status'], 'Absent provider must return signed-capable not_available.' );

$descriptor = array(
    'owner_app_key' => 'faluss-hub', 'capability_key' => 'faluss-hub.events', 'catalog_version' => '1.0.0',
    'catalog_provider' => function () use ( $catalog_contract, $hub_catalog ) { return evt01b1_provider_result( $catalog_contract, $hub_catalog ); },
    'cap_manifest_provider' => function () use ( $cap_contract, $hub_manifest ) { return evt01b1_provider_result( $cap_contract, $hub_manifest ); },
);
$refused_descriptor = $descriptor; $refused_descriptor['extra'] = true;
evt01b1_assert( is_wp_error( Faluss_Events::register_catalog_provider( $refused_descriptor ) ), 'Additional descriptor keys must be refused.' );
$refused_descriptor = $descriptor; $refused_descriptor['owner_app_key'] = '*';
evt01b1_assert( is_wp_error( Faluss_Events::register_catalog_provider( $refused_descriptor ) ), 'Descriptor wildcards must be refused.' );
$refused_descriptor = $descriptor; $refused_descriptor['catalog_provider'] = 'untrusted_missing_callback';
evt01b1_assert( is_wp_error( Faluss_Events::register_catalog_provider( $refused_descriptor ) ), 'Invalid callbacks must be refused.' );
evt01b1_assert( true === Faluss_Events::register_catalog_provider( $descriptor ), 'Exact trusted local provider descriptor must register.' );
$provided = Faluss_Federation_Providers::dispatch( $request, $identity );
evt01b1_assert( 'success' === $provided['status'] && $catalog_contract === $provided['payload_contract'] && $hub_catalog === $provided['payload'] && null === $provided['error'], 'Provider must return only the validated catalog under the exact contract.' );
Faluss_Federation_Schema::$audits = array();
$signed = evt01b1_private( 'Faluss_Federation_Server', 'authenticated_response', array( $provided, $request, str_repeat( 'b', 64 ), $identity, microtime( true ) ) );
evt01b1_assert( 200 === $signed->get_status() && 86 === strlen( $signed->get_headers()['X-Faluss-Federation-Signature'] ?? '' ), 'Valid provider result must traverse the production signed response path.' );
$audit = Faluss_Federation_Schema::$audits[0] ?? array();
evt01b1_assert( array( 'request_id', 'sender_node_id', 'recipient_node_id', 'operation_name', 'capability_key', 'result_code', 'opaque_code', 'duration_ms' ) === array_keys( $audit ) && 'event_catalog.read' === ( $audit['operation_name'] ?? null ) && 1 !== preg_match( '/manifest|destination|signature|faluss_id|https?:\/\//i', wp_json_encode( array_values( $audit ) ) ), 'Audit must remain minimal and contain no document, destination, key material, Faluss ID, payload or URL.' );

$wrong_node_entry = $descriptor; $wrong_node_entry['catalog_version'] = '1.0.1'; $local_wrong_node_catalog = $wrong_node_catalog; $local_wrong_node_catalog['catalog_version'] = '1.0.1';
$wrong_node_entry['catalog_provider'] = function () use ( $catalog_contract, $local_wrong_node_catalog ) { return evt01b1_provider_result( $catalog_contract, $local_wrong_node_catalog ); };
evt01b1_assert( true === Faluss_Events::register_catalog_provider( $wrong_node_entry ), 'Wrong-node provider descriptor itself may register as trusted code.' );
$wrong_node_request = $request; $wrong_node_request['parameters']['catalog_version'] = '1.0.1';
evt01b1_assert( 'incompatible' === Faluss_Federation_Providers::dispatch( $wrong_node_request, $identity )['status'], 'Local provider catalog bound to another node must map to incompatible.' );

$invalid_entry = $descriptor; $invalid_entry['catalog_version'] = '1.0.2'; $invalid_catalog = $hub_catalog; $invalid_catalog['catalog_version'] = '1.0.2'; $invalid_catalog['extra'] = true;
$invalid_entry['catalog_provider'] = function () use ( $catalog_contract, $invalid_catalog ) { return evt01b1_provider_result( $catalog_contract, $invalid_catalog ); };
evt01b1_assert( true === Faluss_Events::register_catalog_provider( $invalid_entry ), 'Separate invalid-result provider may register as trusted code.' );
$invalid_request = $request; $invalid_request['parameters']['catalog_version'] = '1.0.2';
evt01b1_assert( 'incompatible' === Faluss_Federation_Providers::dispatch( $invalid_request, $identity )['status'], 'Invalid catalog must map to incompatible.' );
$throwing_entry = $descriptor; $throwing_entry['catalog_version'] = '1.0.3'; $throwing_entry['catalog_provider'] = function () { throw new RuntimeException( 'temporary owner failure' ); };
evt01b1_assert( true === Faluss_Events::register_catalog_provider( $throwing_entry ), 'Throwing provider descriptor itself may register.' );
$throwing_request = $request; $throwing_request['parameters']['catalog_version'] = '1.0.3';
evt01b1_assert( 'temporarily_unavailable' === Faluss_Federation_Providers::dispatch( $throwing_request, $identity )['status'], 'Provider exception must map to temporarily_unavailable.' );

Faluss_Federation_Client::$manifest_response = evt01b1_remote_response( 'hub-node', 'faluss-hub', $cap_contract, $hub_manifest );
Faluss_Federation_Client::$catalog_response = evt01b1_remote_response( 'hub-node', 'faluss-hub', $catalog_contract, $hub_catalog );
Faluss_Federation_Client::$calls = array();
$remote = Faluss_Events::read_remote_catalog( 'hub-node', 'faluss-hub', 'faluss-hub', 'faluss-hub.events', '1.0.0' );
evt01b1_assert( $hub_catalog === $remote && array( 'manifest.read', 'event_catalog.read' ) === array_column( Faluss_Federation_Client::$calls, 0 ), 'Remote facade must read and validate the signed manifest before the signed catalog, with no fallback.' );
$remote_wrong_node_catalog = $hub_catalog; $remote_wrong_node_catalog['node_id'] = 'other-node';
Faluss_Federation_Client::$catalog_response = evt01b1_remote_response( 'hub-node', 'faluss-hub', $catalog_contract, $remote_wrong_node_catalog );
Faluss_Federation_Client::$calls = array();
$remote_wrong_node = Faluss_Events::read_remote_catalog( 'hub-node', 'faluss-hub', 'faluss-hub', 'faluss-hub.events', '1.0.0' );
evt01b1_assert( is_wp_error( $remote_wrong_node ) && 'faluss_events_unavailable' === $remote_wrong_node->get_error_code(), 'Signed-simulated response with the expected responder but a foreign catalog node must fail generically.' );
evt01b1_assert( array( 'manifest.read', 'event_catalog.read' ) === array_column( Faluss_Federation_Client::$calls, 0 ) && 2 === count( Faluss_Federation_Client::$calls ), 'Wrong-node remote catalog must use no fallback, cache or second catalog read.' );
$wrong_contract = Faluss_Federation_Client::$catalog_response; $wrong_contract['payload_contract']['document_type'] = 'faluss.other'; Faluss_Federation_Client::$catalog_response = $wrong_contract;
evt01b1_assert( is_wp_error( Faluss_Events::read_remote_catalog( 'hub-node', 'faluss-hub', 'faluss-hub', 'faluss-hub.events', '1.0.0' ) ), 'Wrong catalog response contract must fail.' );
Faluss_Federation_Client::$catalog_response = evt01b1_remote_response( 'other-node', 'faluss-hub', $catalog_contract, $hub_catalog );
evt01b1_assert( is_wp_error( Faluss_Events::read_remote_catalog( 'hub-node', 'faluss-hub', 'faluss-hub', 'faluss-hub.events', '1.0.0' ) ), 'Response bound to another peer must fail.' );

$client_source = file_get_contents( $paths['client'] );
$server_source = file_get_contents( $paths['server'] );
$events_source = file_get_contents( $paths['events'] );
evt01b1_assert( false !== strpos( $client_source, 'public static function event_catalog_read' ) && false !== strpos( $client_source, "'owner_app_key' => \$owner_app_key, 'capability_key' => \$capability_key, 'catalog_version' => \$catalog_version" ), 'Production Federation client exposes the exact closed facade.' );
evt01b1_assert( false !== strpos( $client_source, "\$response['responder']['node_id'] !== \$peer['peer_node_id']" ) && false !== strpos( $client_source, "find_peer( \$peer['peer_node_id'], \$peer['peer_app_key'], \$response['responder']['key_id'] )" ), 'Production client still binds response to exact peer and registered key.' );
evt01b1_assert( false !== strpos( $server_source, 'consume_replay_and_limit' ) && false !== strpos( $server_source, 'rate_limit( $message[\'operation\'] )' ), 'Existing transactional anti-replay and rate limiting remain on the dispatch path.' );
evt01b1_assert( 1 !== preg_match( '/wp_remote_|curl_|file_get_contents\s*\(\s*[\'\"]https?:/i', file_get_contents( $root . '/plugins/faluss-events/faluss-events.php' ) . $events_source ), 'Plugin loading performs no network request.' );
foreach ( array( 'dbDelta', 'register_rest_route', 'add_shortcode', 'setcookie' ) as $forbidden ) {
    evt01b1_assert( false === stripos( file_get_contents( $root . '/plugins/faluss-events/faluss-events.php' ) . $events_source . file_get_contents( $paths['catalog_validator'] ) . file_get_contents( $paths['envelope_validator'] ), $forbidden ), 'Faluss Events must not add forbidden transport or browser behavior: ' . $forbidden );
}
evt01b1_assert( false === strpos( $events_source, 'faluss-hub' ) && false === strpos( $events_source, 'faluss-me' ), 'Plugin must ship no real Hub or Me provider/catalog.' );

$schema = json_decode( file_get_contents( $root . '/contracts/faluss-federation-request.schema.json' ), true );
evt01b1_assert( array( 'diagnostic.read', 'manifest.read', 'read_model.read', 'event_catalog.read', 'event.publish' ) === $schema['properties']['operation']['enum'] && 5 === count( $schema['allOf'] ), 'Request schema must retain the exact event catalog branch alongside event publish.' );
$branch = $schema['allOf'][3]['then']['properties'];
evt01b1_assert( null === $branch['subject_context']['const'] && array( 'owner_app_key', 'capability_key', 'catalog_version' ) === $branch['parameters']['required'] && false === $branch['parameters']['additionalProperties'], 'Schema branch requires null subject and exactly three parameters.' );
$bootstrap = file_get_contents( $root . '/plugins/faluss-federation/faluss-federation.php' );
$events_bootstrap = file_get_contents( $root . '/plugins/faluss-events/faluss-events.php' );
evt01b1_assert( false !== strpos( $bootstrap, 'Version: 0.3.0' ) && false !== strpos( $bootstrap, "FALUSS_FEDERATION_SCHEMA_VERSION', '1'" ) && false !== strpos( $events_bootstrap, 'Version: 0.3.0' ) && false !== strpos( $events_bootstrap, "FALUSS_EVENTS_SCHEMA_VERSION', '1'" ), 'Versions must be Events 0.3.0/schema 1 and Federation 0.3.0/schema 1.' );
Faluss_Events::boot();
evt01b1_assert( isset( $GLOBALS['evt01b1_hooks']['faluss_federation_ready'][20] ) && isset( $GLOBALS['evt01b1_hooks']['plugins_loaded'][40] ), 'Both early and late activation orders retain one deterministic integration callback.' );

$duplicate = Faluss_Events::register_catalog_provider( $descriptor );
evt01b1_assert( is_wp_error( $duplicate ) && 'temporarily_unavailable' === Faluss_Federation_Providers::dispatch( $request, $identity )['status'], 'Duplicate local descriptor makes the catalog registry globally unavailable.' );

$base_output = array(); $base_status = 0;
exec( 'git -C ' . escapeshellarg( $root ) . ' cat-file -e 427299df526d11ab5b24ac83ddfd46839027669c:plugins/faluss-events/faluss-events.php 2>&1', $base_output, $base_status );
evt01b1_assert( 0 !== $base_status, 'Base 427299df must fail this regression because the Faluss Events runtime is absent.' );
$base_source_lines = array(); $base_source_status = 0;
exec( 'git -C ' . escapeshellarg( $root ) . ' show 638c62f3089a3f089bc3dc88c4f72f83afb15dc0:plugins/faluss-events/includes/class-faluss-events-catalog-validator.php 2>&1', $base_source_lines, $base_source_status );
$base_source = implode( "\n", $base_source_lines );
$base_source = preg_replace( '/^<\?php\s*/', '', $base_source, 1 );
$base_source = str_replace( 'final class Faluss_Events_Catalog_Validator', 'final class EVT01B11_Base_Catalog_Validator', $base_source );
evt01b1_assert( 0 === $base_source_status && is_string( $base_source ) && false !== strpos( $base_source, 'EVT01B11_Base_Catalog_Validator' ), 'Required EVT-01B.1 base validator must be loadable for behavioral regression proof.' );
eval( $base_source );
evt01b1_assert( EVT01B11_Base_Catalog_Validator::validate_federation_payload( $wrong_node_catalog, $catalog_contract, $catalog_context ), 'Base 638c62f must fail this regression precisely by accepting a catalog whose node differs from the recipient node.' );

echo 'EVT-01B.1/EVT-01B.1.1 events runtime: OK (' . $evt01b1_assertions . ' assertions; production validators/providers/policy/server)' . PHP_EOL;
