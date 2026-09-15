<?php

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }

final class WP_Error {
    private $code;
    public function __construct( $code ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_parse_url( $value ) { return parse_url( $value ); }
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['an01b2_hooks'][ $hook ][ $priority ][] = $callback; }
function did_action( $hook ) { return (int) ( $GLOBALS['an01b2_actions'][ $hook ] ?? 0 ); }

final class Faluss_Federation_Crypto {
    public static $identity = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'origin' => 'https://faluss.com' );
    public static function local_identity() { return self::$identity; }
    public static function is_key_id( $value ) { return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/D', $value ); }
}

$an01b2_assertions = 0;
function an01b2_assert( $condition, $message ) {
    global $an01b2_assertions;
    $an01b2_assertions++;
    if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL ); exit( 1 ); }
}
function an01b2_static_get( $class, $property ) {
    $reflection = new ReflectionProperty( $class, $property );
    $reflection->setAccessible( true );
    return $reflection->getValue();
}
function an01b2_static_set( $class, $property, $value ) {
    $reflection = new ReflectionProperty( $class, $property );
    $reflection->setAccessible( true );
    $reflection->setValue( null, $value );
}
function an01b2_private( $class, $method, $arguments = array() ) {
    $reflection = new ReflectionMethod( $class, $method );
    $reflection->setAccessible( true );
    return $reflection->invokeArgs( null, $arguments );
}
function an01b2_reset( $owner_class ) {
    foreach ( array(
        'catalog_providers' => array(), 'payload_validators' => array(), 'provider_conflict' => false,
        'payload_validator_conflict' => false, 'federation_validator_registered' => false,
        'federation_provider_keys' => array(), 'federation_publish_registered' => false,
    ) as $property => $value ) {
        an01b2_static_set( 'Faluss_Events', $property, $value );
    }
    an01b2_static_set( $owner_class, 'registered', false );
    an01b2_static_set( $owner_class, 'conflict', false );
}
function an01b2_context( $node, $app, $capability ) {
    return array(
        'operation' => 'event_catalog.read',
        'parameters' => array( 'owner_app_key' => $app, 'capability_key' => $capability, 'catalog_version' => '1.0.0' ),
        'subject_context' => null,
        'sender' => array( 'node_id' => 'consumer-node', 'app_key' => 'consumer-app', 'key_id' => 'consumer-key-0001' ),
        'recipient' => array( 'node_id' => $node, 'app_key' => $app ),
    );
}
function an01b2_manifest_context( $node, $app ) {
    return array(
        'operation' => 'manifest.read',
        'parameters' => array( 'app_key' => $app, 'requested_manifest_version' => '1.0.0' ),
        'subject_context' => null,
        'sender' => array( 'node_id' => 'consumer-node', 'app_key' => 'consumer-app' ),
        'recipient' => array( 'node_id' => $node, 'app_key' => $app ),
    );
}
function an01b2_event( $type, $document, $actors, $presence, $object_types ) {
    return array(
        'event_type' => $type,
        'event_version' => '1.0.0',
        'payload_contract' => array( 'document_type' => $document, 'contract_version' => '1.0.0' ),
        'subject_policy' => 'required',
        'allowed_actor_types' => $actors,
        'object_policy' => array( 'presence' => $presence, 'allowed_types' => $object_types ),
        'allowed_destinations' => array( 'analytics.events' ),
        'max_delivery_delay_seconds' => 3600,
        'data_classification' => 'personal',
        'max_retention_seconds' => 7776000,
        'member_result_visibility' => 'own_subject_only',
        'lifecycle' => array( 'deprecated' => false, 'sunset_at' => null, 'replacement_event_type' => null ),
    );
}
function an01b2_capability( $key ) {
    return array(
        'capability_key' => $key,
        'interfaces' => array( 'event_source' ),
        'requested_bindings' => array( array( 'interface' => 'event_source', 'slot' => 'analytics.events' ) ),
        'read_model_contract' => null,
        'symbolic_actions' => array(),
        'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_capability_key' => null ),
    );
}

$root = dirname( __DIR__ );
$paths = array(
    'manifest_validator' => $root . '/plugins/faluss-apps-registry/includes/class-faluss-apps-registry-manifest-validator.php',
    'catalog_validator' => $root . '/plugins/faluss-events/includes/class-faluss-events-catalog-validator.php',
    'events' => $root . '/plugins/faluss-events/includes/class-faluss-events.php',
    'portal_manifest' => $root . '/plugins/faluss-portal/includes/class-faluss-portal-manifest.php',
    'portal_catalog' => $root . '/plugins/faluss-portal/includes/class-faluss-portal-events-catalog.php',
    'link_manifest' => $root . '/plugins/faluss-link/includes/class-faluss-link-manifest.php',
    'link_catalog' => $root . '/plugins/faluss-link/includes/class-faluss-link-events-catalog.php',
);
foreach ( $paths as $path ) { an01b2_assert( is_file( $path ), 'Required production artifact missing: ' . $path ); }
foreach ( array( 'Faluss_Portal_Events_Catalog' => $paths['portal_catalog'], 'Faluss_Link_Events_Catalog' => $paths['link_catalog'] ) as $class => $path ) {
    $snippet = "define('ABSPATH', __DIR__); class WP_Error { public function __construct( \$code ) {} } function add_action( \$hook, \$callback, \$priority = 10 ) {} function did_action( \$hook ) { return 1; } function is_wp_error( \$value ) { return \$value instanceof WP_Error; } require " . var_export( $path, true ) . "; " . $class . "::boot(); \$registered = " . $class . "::register_provider(); \$provided = " . $class . "::provide(array()); exit(false === \$registered && \$provided instanceof WP_Error ? 0 : 1);";
    $output = array(); $status = 1;
    exec( PHP_BINARY . ' -r ' . escapeshellarg( $snippet ) . ' 2>&1', $output, $status );
    an01b2_assert( 0 === $status, $class . ' must remain non-fatal when Events and Federation are unavailable: ' . implode( ' | ', $output ) );
}
foreach ( $paths as $path ) { require_once $path; }

$portal_bootstrap = file_get_contents( $root . '/plugins/faluss-portal/faluss-portal.php' );
$link_bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
an01b2_assert( false !== strpos( $portal_bootstrap, 'Version: 0.1.23' ) && false !== strpos( $portal_bootstrap, "FALUSS_PORTAL_VERSION', '0.1.23'" ), 'Portal version must be exactly 0.1.23.' );
an01b2_assert( false !== strpos( $link_bootstrap, 'Version: 0.3.20' ) && false !== strpos( $link_bootstrap, "FALUSS_LINK_VERSION','0.3.20'" ), 'Link version must be exactly 0.3.20.' );

$hub_manifest = Faluss_Portal_Manifest::manifest();
$me_manifest = Faluss_Link_Manifest::manifest();
$manifest_contract = array( 'document_type' => 'faluss.app-capability-manifest', 'contract_version' => '1.0.0' );
an01b2_assert( '1.0.0' === $hub_manifest['manifest_version'] && '1.0.0' === $me_manifest['manifest_version'], 'Both CAP manifest versions must remain 1.0.0.' );
an01b2_assert( true === Faluss_Apps_Registry_Manifest_Validator::validate( $hub_manifest, $manifest_contract, an01b2_manifest_context( 'hub-node', 'faluss-hub' ) ), 'Hub manifest must pass the production CAP validator.' );
an01b2_assert( true === Faluss_Apps_Registry_Manifest_Validator::validate( $me_manifest, $manifest_contract, an01b2_manifest_context( 'me-node', 'faluss-me' ) ), 'Me manifest must pass the production CAP validator.' );
an01b2_assert( 2 === count( $hub_manifest['capabilities'] ), 'Hub manifest must expose exactly two capabilities.' );
$daily_reward = array(
    'capability_key' => 'faluss-hub.daily-reward',
    'interfaces' => array( 'delegated_action' ),
    'requested_bindings' => array( array( 'interface' => 'delegated_action', 'slot' => 'portal.apps.card_action' ) ),
    'read_model_contract' => array( 'document_type' => 'daily-reward.status', 'contract_version' => '1.0.0' ),
    'symbolic_actions' => array( array( 'action_key' => 'faluss-hub.daily-reward.claim', 'kind' => 'delegated_action' ) ),
    'compatibility' => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null, 'replacement_capability_key' => null ),
);
an01b2_assert( $daily_reward === $hub_manifest['capabilities'][0], 'Historical Hub Daily Reward capability must be preserved byte-for-byte at the value level.' );
an01b2_assert( an01b2_capability( 'faluss-hub.events' ) === $hub_manifest['capabilities'][1], 'Hub event_source capability and analytics.events binding must be exact.' );
an01b2_assert( array( an01b2_capability( 'faluss-me.events' ) ) === $me_manifest['capabilities'], 'Me manifest must expose only its exact event_source capability.' );
$manifest_json = json_encode( array( $hub_manifest, $me_manifest ) );
an01b2_assert( false === strpos( $manifest_json, 'quests.events' ) && false === strpos( $manifest_json, 'progression.events' ), 'No Quests or Progression binding may be declared.' );

$hub_catalog = Faluss_Portal_Events_Catalog::catalog();
$me_catalog = Faluss_Link_Events_Catalog::catalog();
$hub_expected_events = array(
    an01b2_event( 'faluss-hub.portal.viewed', 'faluss-hub.portal-viewed', array( 'member' ), 'forbidden', array() ),
    an01b2_event( 'faluss-hub.app.opened', 'faluss-hub.app-opened', array( 'member' ), 'required', array( 'app' ) ),
    an01b2_event( 'faluss-hub.daily-reward.claimed', 'faluss-hub.daily-reward-claimed', array( 'member' ), 'forbidden', array() ),
);
$me_expected_events = array(
    an01b2_event( 'faluss-me.card.viewed', 'faluss-me.card-viewed', array( 'anonymous' ), 'forbidden', array() ),
    an01b2_event( 'faluss-me.link.clicked', 'faluss-me.link-clicked', array( 'anonymous' ), 'required', array( 'link' ) ),
    an01b2_event( 'faluss-me.collection.opened', 'faluss-me.collection-opened', array( 'anonymous' ), 'required', array( 'collection' ) ),
);
an01b2_assert( $hub_expected_events === $hub_catalog['event_types'], 'Hub catalog must expose exactly the three AN-01 Hub events.' );
an01b2_assert( $me_expected_events === $me_catalog['event_types'], 'Me catalog must expose exactly the three AN-01 Me events.' );
an01b2_assert( 'faluss-portal' === $hub_catalog['owner_engine'] && 'faluss-link' === $me_catalog['owner_engine'], 'Catalog owner engines must be exact.' );
an01b2_assert( Faluss_Events_Catalog_Validator::validate( $hub_catalog ) && Faluss_Events_Catalog_Validator::validate( $me_catalog ), 'Both owner catalogs must pass the production Events catalog validator.' );
an01b2_assert( Faluss_Events_Catalog_Validator::validate_against_manifest( $hub_catalog, $hub_manifest, $manifest_contract, an01b2_manifest_context( 'hub-node', 'faluss-hub' ) ), 'Hub catalog must cross-validate against the production Hub manifest.' );
an01b2_assert( Faluss_Events_Catalog_Validator::validate_against_manifest( $me_catalog, $me_manifest, $manifest_contract, an01b2_manifest_context( 'me-node', 'faluss-me' ) ), 'Me catalog must cross-validate against the production Me manifest.' );

foreach ( array(
    array( 'event_types', 'event_type', 'faluss-hub.unknown' ),
    array( 'owner', null, 'other-app' ), array( 'owner_engine', null, 'other-engine' ),
    array( 'node_id', null, 'other-node' ), array( 'capability_key', null, 'faluss-hub.other' ),
    array( 'contract_version', null, '2.0.0' ),
) as $mutation ) {
    $invalid = $hub_catalog;
    if ( 'event_types' === $mutation[0] ) { $invalid['event_types'][0][ $mutation[1] ] = $mutation[2]; } else { $invalid[ $mutation[0] ] = $mutation[2]; }
    an01b2_assert( ! an01b2_private( 'Faluss_Portal_Events_Catalog', 'valid_catalog', array( $invalid ) ), 'Hub owner provider must close on divergent ' . $mutation[0] . '.' );
}
$bad_destination = $hub_catalog;
$bad_destination['event_types'][0]['allowed_destinations'] = array( 'quests.events' );
an01b2_assert( ! an01b2_private( 'Faluss_Portal_Events_Catalog', 'valid_catalog', array( $bad_destination ) ), 'A divergent destination must be refused.' );
$bad_subject = $hub_catalog;
$bad_subject['event_types'][0]['subject_policy'] = 'optional';
an01b2_assert( ! an01b2_private( 'Faluss_Portal_Events_Catalog', 'valid_catalog', array( $bad_subject ) ), 'A divergent subject context policy must be refused.' );
$bad_binding = $hub_manifest;
$bad_binding['capabilities'][1]['requested_bindings'][0]['slot'] = 'quests.events';
an01b2_assert( ! Faluss_Events_Catalog_Validator::validate_against_manifest( $hub_catalog, $bad_binding, $manifest_contract, an01b2_manifest_context( 'hub-node', 'faluss-hub' ) ), 'A divergent manifest binding must be refused by production cross-validation.' );

foreach ( array(
    array( 'Faluss_Portal_Events_Catalog', 'hub-node', 'faluss-hub', 'https://faluss.com', 'faluss-hub.events', $hub_catalog ),
    array( 'Faluss_Link_Events_Catalog', 'me-node', 'faluss-me', 'https://faluss.me', 'faluss-me.events', $me_catalog ),
) as $owner ) {
    list( $class, $node, $app, $origin, $capability, $catalog ) = $owner;
    an01b2_reset( $class );
    Faluss_Federation_Crypto::$identity = array( 'node_id' => $node, 'app_key' => $app, 'origin' => 'https://wrong.example' );
    an01b2_assert( false === $class::register_provider() && array() === an01b2_static_get( 'Faluss_Events', 'catalog_providers' ), $class . ' must refuse a divergent local identity without registration.' );
    Faluss_Federation_Crypto::$identity = array( 'node_id' => $node, 'app_key' => $app, 'origin' => $origin );
    an01b2_assert( true === $class::register_provider() && true === $class::register_provider(), $class . ' registration must be idempotent.' );
    an01b2_assert( 1 === count( an01b2_static_get( 'Faluss_Events', 'catalog_providers' ) ), $class . ' repeated hooks must register only one provider.' );
    an01b2_assert( $catalog === Faluss_Events::resolve_local_catalog( $node, $app, $capability, '1.0.0' ), $class . ' catalog must resolve through the existing Events API.' );
    $context = an01b2_context( $node, $app, $capability );
    $events_result = Faluss_Events::provide_catalog_for_federation( $context );
    an01b2_assert( ! is_wp_error( $events_result ) && $catalog === $events_result['payload'], $class . ' must pass the complete Events provider adapter with a real Federation sender key_id.' );
    $provided = $class::provide( $context );
    an01b2_assert( array( 'payload_contract', 'payload' ) === array_keys( $provided ) && $catalog === $provided['payload'], $class . ' must return only payload_contract and payload.' );
    $local_context = $context; unset( $local_context['sender']['key_id'] );
    an01b2_assert( ! is_wp_error( $class::provide( $local_context ) ), $class . ' must also accept the exact synthetic context used by resolve_local_catalog().' );
    foreach ( array( 'missing', 'extra', 'operation', 'owner', 'capability', 'version', 'recipient', 'subject' ) as $case ) {
        $invalid = $context;
        if ( 'missing' === $case ) { unset( $invalid['sender'] ); }
        if ( 'extra' === $case ) { $invalid['extra'] = true; }
        if ( 'operation' === $case ) { $invalid['operation'] = 'manifest.read'; }
        if ( 'owner' === $case ) { $invalid['parameters']['owner_app_key'] = 'other-app'; }
        if ( 'capability' === $case ) { $invalid['parameters']['capability_key'] = $app . '.other'; }
        if ( 'version' === $case ) { $invalid['parameters']['catalog_version'] = '2.0.0'; }
        if ( 'recipient' === $case ) { $invalid['recipient']['node_id'] = 'other-node'; }
        if ( 'subject' === $case ) { $invalid['subject_context'] = array(); }
        an01b2_assert( is_wp_error( $class::provide( $invalid ) ), $class . ' must refuse divergent context: ' . $case . '.' );
    }
    $invalid_sender = $context; $invalid_sender['sender']['unexpected'] = true;
    an01b2_assert( is_wp_error( $class::provide( $invalid_sender ) ), $class . ' must refuse an ambiguous Federation sender.' );
    $invalid_key = $context; $invalid_key['sender']['key_id'] = 'bad';
    an01b2_assert( is_wp_error( $class::provide( $invalid_key ) ), $class . ' must refuse an invalid Federation key_id.' );

    an01b2_reset( $class );
    an01b2_assert( true === Faluss_Events::register_catalog_provider( $class::descriptor() ), 'Collision precondition must register the tuple once.' );
    an01b2_assert( false === $class::register_provider() && true === an01b2_static_get( $class, 'conflict' ) && true === an01b2_static_get( 'Faluss_Events', 'provider_conflict' ), $class . ' collision must fail closed.' );
}

$runtime_files = array( $paths['portal_catalog'], $paths['link_catalog'], $root . '/plugins/faluss-portal/faluss-portal.php', $root . '/plugins/faluss-link/faluss-link.php' );
$runtime_source = '';
foreach ( $runtime_files as $file ) { $runtime_source .= file_get_contents( $file ); }
foreach ( array( 'accept_local_event', 'accept_registered_local_catalog', 'refresh_remote_catalog', 'register_route', 'Faluss_Federation_Policy', 'wp_remote_get', 'wp_remote_post' ) as $forbidden ) {
    an01b2_assert( false === strpos( $runtime_source, $forbidden ), 'This lot must not invoke forbidden runtime mechanism: ' . $forbidden );
}
an01b2_assert( false === strpos( $runtime_source, 'register_payload_validator' ), 'Portal and Link must not register payload validators in this lot.' );

$protected = array( 'plugins/faluss-events', 'plugins/faluss-federation', 'plugins/faluss-analytics', 'plugins/faluss-apps-registry', 'contracts' );
foreach ( $protected as $path ) {
    $output = array(); $status = 0;
    exec( 'git -C ' . escapeshellarg( $root ) . ' diff --quiet a20f831c97e8e153658dabcbc31a2934fe3ad46d -- ' . escapeshellarg( $path ) . ' 2>&1', $output, $status );
    an01b2_assert( 0 === $status, 'Protected tree must remain byte-for-byte identical to the base: ' . $path );
}

echo 'AN-01B.2 owner catalog contracts: ' . $an01b2_assertions . '/' . $an01b2_assertions . " assertions passed.\n";
