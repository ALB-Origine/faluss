<?php

/**
 * AN-01A documentary Analytics contract regression.
 *
 * These helpers exercise AN-01A cross-document invariants. They are test-only
 * and are not presented as a JSON Schema engine, Analytics runtime, producer,
 * consumer or WordPress integration.
 */

$an01a_assertions = 0;

function an01a_assert( $condition, $message ) {
    global $an01a_assertions;
    $an01a_assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function an01a_is_list( $value ) {
    return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
}

function an01a_exact_keys( $value, $expected ) {
    if ( ! is_array( $value ) || ( array() !== $value && an01a_is_list( $value ) ) ) {
        return false;
    }
    $actual = array_keys( $value );
    sort( $actual, SORT_STRING );
    sort( $expected, SORT_STRING );
    return $actual === $expected;
}

function an01a_read_json( $path ) {
    $document = json_decode( file_get_contents( $path ), true );
    an01a_assert( JSON_ERROR_NONE === json_last_error() && is_array( $document ), 'JSON must parse: ' . basename( $path ) );
    return $document;
}

function an01a_payload_is_valid( $payload, $schema ) {
    if ( ! is_array( $payload ) ) {
        return false;
    }
    if ( isset( $schema['required'] ) ) {
        return an01a_exact_keys( $payload, $schema['required'] )
            && in_array( $payload['surface_key'], $schema['properties']['surface_key']['enum'], true );
    }
    return array() === $payload && 0 === $schema['maxProperties'];
}

function an01a_opaque_reference( $owner_app_key, $object_type, $canonical_owner_object_identifier ) {
    return 'an01:' . $object_type . ':' . hash( 'sha256', "faluss-an01:v1\n" . $owner_app_key . "\n" . $object_type . "\n" . $canonical_owner_object_identifier );
}

function an01a_event_reference( $owner_app_key, $canonical_occurrence_identifier ) {
    return 'an01:event:' . hash( 'sha256', "faluss-an01:event:v1\n" . $owner_app_key . "\n" . $canonical_occurrence_identifier );
}

function an01a_contains_sensitive_data( $value ) {
    $forbidden_keys = array(
        'faluss_id', 'subject_faluss_id', 'actor_faluss_id', 'email', 'handle', 'url', 'domain', 'ip', 'ip_address',
        'user_agent', 'cookie', 'session', 'visitor_id', 'visitor_identity', 'raw_uuid', 'amount', 'balance', 'pf_class',
        'ledger', 'ledger_key', 'idempotency_key', 'write_uuid',
    );
    if ( is_string( $value ) ) {
        return 1 === preg_match( '/(?:https?:\/\/|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|(?:^|[^0-9])(?:[0-9]{1,3}\.){3}[0-9]{1,3}(?:$|[^0-9])|(?:^|\s)@[a-z0-9_]|\b(?:cookie|session|Mozilla\/)\b)/i', $value );
    }
    if ( ! is_array( $value ) ) {
        return false;
    }
    foreach ( $value as $key => $child ) {
        if ( is_string( $key ) && in_array( strtolower( $key ), $forbidden_keys, true ) ) {
            return true;
        }
        if ( an01a_contains_sensitive_data( $child ) ) {
            return true;
        }
    }
    return false;
}

function an01a_definitions() {
    return array(
        'faluss-hub.portal.viewed' => array( 'type' => 'faluss-hub.portal.viewed', 'node' => 'hub-node', 'app' => 'faluss-hub', 'engine' => 'faluss-portal', 'capability' => 'faluss-hub.events', 'document' => 'faluss-hub.portal-viewed', 'actor' => 'member', 'object' => null ),
        'faluss-hub.app.opened' => array( 'type' => 'faluss-hub.app.opened', 'node' => 'hub-node', 'app' => 'faluss-hub', 'engine' => 'faluss-portal', 'capability' => 'faluss-hub.events', 'document' => 'faluss-hub.app-opened', 'actor' => 'member', 'object' => 'app' ),
        'faluss-hub.daily-reward.claimed' => array( 'type' => 'faluss-hub.daily-reward.claimed', 'node' => 'hub-node', 'app' => 'faluss-hub', 'engine' => 'faluss-portal', 'capability' => 'faluss-hub.events', 'document' => 'faluss-hub.daily-reward-claimed', 'actor' => 'member', 'object' => null ),
        'faluss-me.card.viewed' => array( 'type' => 'faluss-me.card.viewed', 'node' => 'me-node', 'app' => 'faluss-me', 'engine' => 'faluss-link', 'capability' => 'faluss-me.events', 'document' => 'faluss-me.card-viewed', 'actor' => 'anonymous', 'object' => null ),
        'faluss-me.link.clicked' => array( 'type' => 'faluss-me.link.clicked', 'node' => 'me-node', 'app' => 'faluss-me', 'engine' => 'faluss-link', 'capability' => 'faluss-me.events', 'document' => 'faluss-me.link-clicked', 'actor' => 'anonymous', 'object' => 'link' ),
        'faluss-me.collection.opened' => array( 'type' => 'faluss-me.collection.opened', 'node' => 'me-node', 'app' => 'faluss-me', 'engine' => 'faluss-link', 'capability' => 'faluss-me.events', 'document' => 'faluss-me.collection-opened', 'actor' => 'anonymous', 'object' => 'collection' ),
    );
}

function an01a_event( $type, $definition, $subject_id, $payload, $canonical_object_identifier = null ) {
    $event = array(
        'contract_version'       => '1.0.0',
        'event_id'               => '70000000-0000-4000-8000-' . substr( hash( 'sha256', $type ), 0, 12 ),
        'event_type'             => $type,
        'event_version'          => '1.0.0',
        'source'                 => array(
            'node_id'         => $definition['node'],
            'app_key'         => $definition['app'],
            'owner'           => $definition['app'],
            'capability_key'  => $definition['capability'],
            'catalog_version' => '1.0.0',
        ),
        'source_event_reference' => an01a_event_reference( $definition['app'], $type . ':occurrence-1' ),
        'occurred_at'             => '2026-09-14T10:00:00Z',
        'produced_at'             => '2026-09-14T10:00:01Z',
        'subject_context'         => array( 'subject_type' => 'faluss_member', 'subject_faluss_id' => $subject_id ),
        'actor_context'           => 'member' === $definition['actor']
            ? array( 'actor_type' => 'member', 'actor_faluss_id' => $subject_id, 'anonymous_reference' => null, 'anonymous_scope' => null )
            : array( 'actor_type' => 'anonymous', 'actor_faluss_id' => null, 'anonymous_reference' => null, 'anonymous_scope' => null ),
        'object_context'          => null,
        'destinations'            => array( 'analytics.events' ),
        'payload_contract'        => array( 'document_type' => $definition['document'], 'contract_version' => '1.0.0' ),
        'payload'                 => $payload,
    );
    if ( null !== $definition['object'] ) {
        $event['object_context'] = array(
            'object_type'      => $definition['object'],
            'object_reference' => an01a_opaque_reference( $definition['app'], $definition['object'], $canonical_object_identifier ),
        );
    }
    return $event;
}

function an01a_event_is_valid( $event, $definition, $payload_schema, $canonical_object_identifier = null ) {
    $root_keys = array( 'contract_version', 'event_id', 'event_type', 'event_version', 'source', 'source_event_reference', 'occurred_at', 'produced_at', 'subject_context', 'actor_context', 'object_context', 'destinations', 'payload_contract', 'payload' );
    if ( ! an01a_exact_keys( $event, $root_keys ) || an01a_contains_sensitive_data( $event['payload'] ) ) {
        return false;
    }
    if ( '1.0.0' !== $event['contract_version'] || '1.0.0' !== $event['event_version'] || $definition['type'] !== $event['event_type'] || array( 'analytics.events' ) !== $event['destinations'] ) {
        return false;
    }
    if ( 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $event['event_id'] ) || 1 !== preg_match( '/^an01:event:[a-f0-9]{64}$/D', $event['source_event_reference'] ) ) {
        return false;
    }
    if ( 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $event['occurred_at'] ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $event['produced_at'] ) || strtotime( $event['occurred_at'] ) > strtotime( $event['produced_at'] ) ) {
        return false;
    }
    $expected_source = array( 'node_id' => $definition['node'], 'app_key' => $definition['app'], 'owner' => $definition['app'], 'capability_key' => $definition['capability'], 'catalog_version' => '1.0.0' );
    if ( $expected_source !== $event['source'] || array( 'document_type' => $definition['document'], 'contract_version' => '1.0.0' ) !== $event['payload_contract'] ) {
        return false;
    }
    if ( ! an01a_exact_keys( $event['subject_context'], array( 'subject_type', 'subject_faluss_id' ) ) || 'faluss_member' !== $event['subject_context']['subject_type'] || 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $event['subject_context']['subject_faluss_id'] ) ) {
        return false;
    }
    $actor = $event['actor_context'];
    if ( ! an01a_exact_keys( $actor, array( 'actor_type', 'actor_faluss_id', 'anonymous_reference', 'anonymous_scope' ) ) || $definition['actor'] !== $actor['actor_type'] ) {
        return false;
    }
    if ( 'member' === $definition['actor'] ) {
        if ( $event['subject_context']['subject_faluss_id'] !== $actor['actor_faluss_id'] || null !== $actor['anonymous_reference'] || null !== $actor['anonymous_scope'] ) {
            return false;
        }
    } elseif ( null !== $actor['actor_faluss_id'] || null !== $actor['anonymous_reference'] || null !== $actor['anonymous_scope'] ) {
        return false;
    }
    if ( null === $definition['object'] ) {
        if ( null !== $event['object_context'] ) {
            return false;
        }
    } else {
        $object = $event['object_context'];
        if ( ! an01a_exact_keys( $object, array( 'object_type', 'object_reference' ) ) || $definition['object'] !== $object['object_type'] || an01a_opaque_reference( $definition['app'], $definition['object'], $canonical_object_identifier ) !== $object['object_reference'] ) {
            return false;
        }
    }
    return an01a_payload_is_valid( $event['payload'], $payload_schema );
}

function an01a_summary_is_valid( $summary, $schema ) {
    if ( ! an01a_exact_keys( $summary, $schema['required'] ) || an01a_contains_sensitive_data( $summary ) ) {
        return false;
    }
    if ( 'analytics.summary' !== $summary['document_type'] || '1.0.0' !== $summary['contract_version'] || ! in_array( $summary['status'], $schema['properties']['status']['enum'], true ) ) {
        return false;
    }
    foreach ( array( 'requested_period', 'produced_period' ) as $period_key ) {
        $period = $summary[ $period_key ];
        if ( ! an01a_exact_keys( $period, array( 'from', 'to' ) ) || strtotime( $period['from'] ) > strtotime( $period['to'] ) ) {
            return false;
        }
    }
    if ( array( 'raw_views' => 'qualified_event_count', 'unique_visitors' => 'not_supported' ) !== $summary['measurement_semantics'] ) {
        return false;
    }
    $allowed_metrics = array_keys( $schema['$defs']['metricTotals']['properties'] );
    if ( ! is_array( $summary['totals'] ) || array_diff( array_keys( $summary['totals'] ), $allowed_metrics ) ) {
        return false;
    }
    foreach ( $summary['totals'] as $value ) {
        if ( ! is_int( $value ) || $value < 0 ) {
            return false;
        }
    }
    if ( 'ready' === $summary['status'] ) {
        if ( empty( $summary['totals'] ) ) {
            return false;
        }
    } elseif ( array() !== $summary['totals'] || array() !== $summary['daily_series'] || array( 'by_event_type' => array(), 'by_object_reference' => array() ) !== $summary['breakdowns'] ) {
        return false;
    }
    $unique = $summary['unique_visitors'];
    if ( array( 'status' => 'not_supported', 'total' => null, 'daily_series' => array() ) !== $unique ) {
        return false;
    }
    if ( $summary['source'] !== array( 'owner' => 'faluss-analytics', 'node_id' => 'hub-node', 'hosting_app' => 'faluss-hub', 'destination' => 'analytics.events', 'consumer_key' => 'faluss-analytics.aggregate-v1' ) ) {
        return false;
    }
    if ( ! an01a_exact_keys( $summary['breakdowns'], array( 'by_event_type', 'by_object_reference' ) ) || count( $summary['daily_series'] ) > 800 || count( $summary['breakdowns']['by_object_reference'] ) > 200 ) {
        return false;
    }
    foreach ( $summary['breakdowns']['by_object_reference'] as $item ) {
        if ( ! an01a_exact_keys( $item, array( 'object_type', 'object_reference', 'total' ) ) || ! in_array( $item['object_type'], array( 'app', 'link', 'collection' ), true ) || 1 !== preg_match( '/^an01:' . preg_quote( $item['object_type'], '/' ) . ':[a-f0-9]{64}$/D', $item['object_reference'] ) ) {
            return false;
        }
    }
    return an01a_exact_keys( $summary['freshness'], array( 'generated_at', 'max_age_seconds', 'stale_behavior' ) )
        && an01a_exact_keys( $summary['compatibility'], array( 'minimum_consumer_version', 'compatible_with', 'deprecated', 'sunset_at' ) );
}

function an01a_summary( $status ) {
    $ready = 'ready' === $status;
    return array(
        'document_type'        => 'analytics.summary',
        'contract_version'     => '1.0.0',
        'status'               => $status,
        'requested_period'     => array( 'from' => '2026-09-01T00:00:00Z', 'to' => '2026-09-14T23:59:59Z' ),
        'produced_period'      => array( 'from' => '2026-09-01T00:00:00Z', 'to' => '2026-09-14T10:05:00Z' ),
        'measurement_semantics' => array( 'raw_views' => 'qualified_event_count', 'unique_visitors' => 'not_supported' ),
        'totals'               => $ready ? array( 'me.card.raw_views' => 12, 'me.link.clicks' => 4 ) : array(),
        'daily_series'         => $ready ? array( array( 'date' => '2026-09-14', 'totals' => array( 'me.card.raw_views' => 2 ) ) ) : array(),
        'breakdowns'           => $ready ? array(
            'by_event_type'       => array( array( 'event_type' => 'faluss-me.card.viewed', 'total' => 12 ) ),
            'by_object_reference' => array( array( 'object_type' => 'link', 'object_reference' => an01a_opaque_reference( 'faluss-me', 'link', 'internal-link-7' ), 'total' => 4 ) ),
        ) : array( 'by_event_type' => array(), 'by_object_reference' => array() ),
        'unique_visitors'      => array( 'status' => 'not_supported', 'total' => null, 'daily_series' => array() ),
        'freshness'            => array( 'generated_at' => '2026-09-14T10:05:00Z', 'max_age_seconds' => 300, 'stale_behavior' => 'refresh_from_owner' ),
        'source'               => array( 'owner' => 'faluss-analytics', 'node_id' => 'hub-node', 'hosting_app' => 'faluss-hub', 'destination' => 'analytics.events', 'consumer_key' => 'faluss-analytics.aggregate-v1' ),
        'compatibility'        => array( 'minimum_consumer_version' => '1.0.0', 'compatible_with' => array( '1.0.0' ), 'deprecated' => false, 'sunset_at' => null ),
    );
}

function an01a_policy_is_valid( $policy ) {
    return an01a_exact_keys( $policy, array( 'operation', 'capability' ) )
        && in_array( $policy['operation'], array( 'event.publish', 'event_catalog.read' ), true )
        && 'faluss-me.events' === $policy['capability']
        && false === strpos( $policy['capability'], '*' );
}

$root = dirname( __DIR__ );
$base = 'f978a0b28094776f9ff1852d44a93dfcf871e8d4';
$payload_files = array(
    'faluss-hub.portal.viewed'       => 'contracts/faluss-hub-portal-viewed.schema.json',
    'faluss-hub.app.opened'          => 'contracts/faluss-hub-app-opened.schema.json',
    'faluss-hub.daily-reward.claimed' => 'contracts/faluss-hub-daily-reward-claimed.schema.json',
    'faluss-me.card.viewed'          => 'contracts/faluss-me-card-viewed.schema.json',
    'faluss-me.link.clicked'         => 'contracts/faluss-me-link-clicked.schema.json',
    'faluss-me.collection.opened'    => 'contracts/faluss-me-collection-opened.schema.json',
);
$document_types = array(
    'faluss-hub.portal.viewed'        => 'faluss-hub.portal-viewed',
    'faluss-hub.app.opened'           => 'faluss-hub.app-opened',
    'faluss-hub.daily-reward.claimed' => 'faluss-hub.daily-reward-claimed',
    'faluss-me.card.viewed'           => 'faluss-me.card-viewed',
    'faluss-me.link.clicked'          => 'faluss-me.link-clicked',
    'faluss-me.collection.opened'     => 'faluss-me.collection-opened',
);
$schemas = array();
foreach ( $payload_files as $event_type => $path ) {
    $schema = an01a_read_json( $root . '/' . $path );
    $schemas[ $event_type ] = $schema;
    an01a_assert( 'https://json-schema.org/draft/2020-12/schema' === $schema['$schema'] && isset( $schema['$id'], $schema['title'] ), 'Payload schema must be autonomous Draft 2020-12: ' . $event_type );
    an01a_assert( $document_types[ $event_type ] === $schema['x-document-type'] && '1.0.0' === $schema['x-contract-version'] && false === $schema['additionalProperties'], 'Payload metadata and closure must be exact: ' . $event_type );
}
$summary_schema = an01a_read_json( $root . '/contracts/faluss-analytics-summary.schema.json' );

$scope = array(
    'docs/FALUSS_ANALYTICS_CONTRACT.md',
    'contracts/faluss-hub-portal-viewed.schema.json',
    'contracts/faluss-hub-app-opened.schema.json',
    'contracts/faluss-hub-daily-reward-claimed.schema.json',
    'contracts/faluss-me-card-viewed.schema.json',
    'contracts/faluss-me-link-clicked.schema.json',
    'contracts/faluss-me-collection-opened.schema.json',
    'contracts/faluss-analytics-summary.schema.json',
    'tests/faluss-analytics-an01a-contract-test.php',
    'docs/FALUSS_EVENTS_CONTRACT.md',
    'docs/FALUSS_CAPABILITIES_CONTRACT.md',
    'docs/FALUSS_FEDERATION_CONTRACT.md',
    'docs/MASTER_PROFILE_CONTRACT.md',
    'docs/ARCHITECTURE.md',
    'docs/DATA_MODEL.md',
    'docs/ROADMAP.md',
);
foreach ( array_merge( array_values( $schemas ), array( $summary_schema ) ) as $schema ) {
    an01a_assert( 16 === $schema['x-an01a-scope']['changed_path_count'] && $scope === $schema['x-an01a-scope']['allowed_changed_paths'], 'Every AN-01A schema must expose the exact sixteen-file scope.' );
    an01a_assert( false === $schema['x-an01a-scope']['wordpress_runtime_changes'] && false === $schema['x-an01a-scope']['real_events_or_tracking'], 'Scope must remain documentary with no runtime or event.' );
}

// The mandatory base fails because none of the seven specialized schemas exists.
foreach ( array_merge( array_values( $payload_files ), array( 'contracts/faluss-analytics-summary.schema.json' ) ) as $path ) {
    $base_content = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' show ' . escapeshellarg( $base . ':' . $path ) . ' 2>NUL' );
    an01a_assert( null === $base_content || '' === $base_content, 'Mandatory base must fail by lacking AN-01A schema: ' . $path );
}

// Six valid payloads and full semantic envelopes.
$definitions = an01a_definitions();
$subject_id = '11111111-1111-4111-8111-111111111111';
foreach ( $definitions as $type => $definition ) {
    $payload = 'faluss-hub.portal.viewed' === $type ? array( 'surface_key' => 'apps' ) : array();
    $canonical_object_identifier = null === $definition['object'] ? null : 'owner-internal-' . $definition['object'] . '-7';
    $event = an01a_event( $type, $definition, $subject_id, $payload, $canonical_object_identifier );
    an01a_assert( an01a_payload_is_valid( $payload, $schemas[ $type ] ), 'Valid closed payload must pass: ' . $type );
    an01a_assert( an01a_event_is_valid( $event, $definition, $schemas[ $type ], $canonical_object_identifier ), 'Valid AN-01A event semantics must pass: ' . $type );
}

$hub_view = an01a_event( 'faluss-hub.portal.viewed', $definitions['faluss-hub.portal.viewed'], $subject_id, array( 'surface_key' => 'hub' ) );
$extra = $hub_view;
$extra['payload']['extra'] = true;
an01a_assert( ! an01a_event_is_valid( $extra, $definitions['faluss-hub.portal.viewed'], $schemas['faluss-hub.portal.viewed'] ), 'Any additional payload property must be rejected.' );
foreach ( array( 'url' => 'https://example.test', 'handle' => '@visitor', 'email' => 'visitor@example.test', 'ip' => '192.0.2.8', 'cookie' => 'visitor=1', 'session' => 'session-1', 'raw_uuid' => '22222222-2222-4222-8222-222222222222' ) as $key => $value ) {
    $invalid = $hub_view;
    $invalid['payload'] = array( 'surface_key' => 'hub', $key => $value );
    an01a_assert( ! an01a_event_is_valid( $invalid, $definitions['faluss-hub.portal.viewed'], $schemas['faluss-hub.portal.viewed'] ), 'Sensitive or raw payload data must be rejected: ' . $key );
}

$me_card = an01a_event( 'faluss-me.card.viewed', $definitions['faluss-me.card.viewed'], $subject_id, array() );
$invalid = $me_card;
$invalid['actor_context'] = array( 'actor_type' => 'member', 'actor_faluss_id' => $subject_id, 'anonymous_reference' => null, 'anonymous_scope' => null );
an01a_assert( ! an01a_event_is_valid( $invalid, $definitions['faluss-me.card.viewed'], $schemas['faluss-me.card.viewed'] ), 'Faluss Me member actor must be rejected.' );
$invalid = $me_card;
$invalid['subject_context'] = null;
an01a_assert( ! an01a_event_is_valid( $invalid, $definitions['faluss-me.card.viewed'], $schemas['faluss-me.card.viewed'] ), 'Faluss Me card view without its owner subject must be rejected.' );
foreach ( array( 'quests.events', 'progression.events' ) as $destination ) {
    $invalid = $hub_view;
    $invalid['destinations'] = array( $destination );
    an01a_assert( ! an01a_event_is_valid( $invalid, $definitions['faluss-hub.portal.viewed'], $schemas['faluss-hub.portal.viewed'] ), 'Inactive AN-01 destination must be rejected: ' . $destination );
}

$reward = an01a_event( 'faluss-hub.daily-reward.claimed', $definitions['faluss-hub.daily-reward.claimed'], $subject_id, array() );
$reward_retry = an01a_event( 'faluss-hub.daily-reward.claimed', $definitions['faluss-hub.daily-reward.claimed'], $subject_id, array() );
an01a_assert( $reward === $reward_retry, 'A retry of the same committed PF occurrence must preserve the event instead of creating a second fact.' );
foreach ( array( 'amount' => 20, 'ledger_key' => 'pf-entry-7' ) as $key => $value ) {
    $invalid = $reward;
    $invalid['payload'][ $key ] = $value;
    an01a_assert( ! an01a_event_is_valid( $invalid, $definitions['faluss-hub.daily-reward.claimed'], $schemas['faluss-hub.daily-reward.claimed'] ), 'Daily reward economic detail must be rejected: ' . $key );
}

$link_definition = $definitions['faluss-me.link.clicked'];
$link_event = an01a_event( 'faluss-me.link.clicked', $link_definition, $subject_id, array(), 'internal-link-7' );
$invalid = $link_event;
$invalid['object_context']['object_reference'] = '22222222-2222-4222-8222-222222222222';
an01a_assert( ! an01a_event_is_valid( $invalid, $link_definition, $schemas['faluss-me.link.clicked'], 'internal-link-7' ), 'Raw object UUID must be rejected.' );
$invalid = $link_event;
$invalid['object_context']['object_reference'] = an01a_opaque_reference( 'faluss-me', 'link', 'random-request-value' );
an01a_assert( ! an01a_event_is_valid( $invalid, $link_definition, $schemas['faluss-me.link.clicked'], 'internal-link-7' ), 'Unstable object reference must be rejected.' );
an01a_assert( $link_event['object_context']['object_reference'] === an01a_opaque_reference( 'faluss-me', 'link', 'internal-link-7' ), 'Object reference derivation must be stable and owner-namespaced.' );

// Empty and ready aggregate read-models, with v1 uniques closed unavailable.
$empty = an01a_summary( 'empty' );
$ready = an01a_summary( 'ready' );
an01a_assert( an01a_summary_is_valid( $empty, $summary_schema ), 'Empty Analytics summary must be valid.' );
an01a_assert( an01a_summary_is_valid( $ready, $summary_schema ), 'Ready Analytics summary must be valid.' );
$invalid = $ready;
$invalid['faluss_id'] = $subject_id;
an01a_assert( ! an01a_summary_is_valid( $invalid, $summary_schema ), 'Faluss ID must stay outside Analytics summary.' );
$invalid = $ready;
$invalid['visitor_identity'] = 'visitor-7';
an01a_assert( ! an01a_summary_is_valid( $invalid, $summary_schema ), 'Visitor identity must stay outside Analytics summary.' );
$invalid = $ready;
$invalid['unique_visitors'] = array( 'status' => 'ready', 'total' => 3, 'daily_series' => array( 3 ) );
an01a_assert( ! an01a_summary_is_valid( $invalid, $summary_schema ), 'Unique visitors must remain not_supported in v1.' );

an01a_assert( an01a_policy_is_valid( array( 'operation' => 'event.publish', 'capability' => 'faluss-me.events' ) ), 'Exact future Me publish policy must be valid.' );
an01a_assert( an01a_policy_is_valid( array( 'operation' => 'event_catalog.read', 'capability' => 'faluss-me.events' ) ), 'Exact future Me catalog policy must be valid.' );
an01a_assert( ! an01a_policy_is_valid( array( 'operation' => 'event.publish', 'capability' => '*' ) ), 'Federation policy wildcard must be rejected.' );

$analytics_contract = file_get_contents( $root . '/docs/FALUSS_ANALYTICS_CONTRACT.md' );
$events_contract = file_get_contents( $root . '/docs/FALUSS_EVENTS_CONTRACT.md' );
$cap_contract = file_get_contents( $root . '/docs/FALUSS_CAPABILITIES_CONTRACT.md' );
$fed_contract = file_get_contents( $root . '/docs/FALUSS_FEDERATION_CONTRACT.md' );
$master_contract = file_get_contents( $root . '/docs/MASTER_PROFILE_CONTRACT.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );

foreach ( array( 'faluss-portal', 'Faluss Events', 'Federation transporte seulement', 'faluss-analytics', 'jamais owner des événements sources' ) as $needle ) {
    an01a_assert( false !== strpos( $analytics_contract, $needle ), 'Producer, Events, Federation and Analytics boundaries must stay distinct: ' . $needle );
}
foreach ( array( '90 jours maximum', 'au moins 30 jours', '25 mois maximum', 'Aucune donnée Analytics n\'est publique', 'base légale', 'information utilisateur' ) as $needle ) {
    an01a_assert( false !== strpos( $analytics_contract, $needle ), 'Privacy or retention contract is missing: ' . $needle );
}
an01a_assert( false !== strpos( $events_contract, 'AN-01A matérialise uniquement les six contrats' ) && false !== strpos( $cap_contract, '### Coordination AN-01A' ), 'EVT and CAP must reserve the exact sources without activation.' );
an01a_assert( false !== strpos( $fed_contract, '### Politique future AN-01A' ) && false !== strpos( $fed_contract, '`owner_apps` et `audiences` ne participent jamais' ), 'Federation must document exact future policy without wildcard authorization.' );
an01a_assert( false !== strpos( $master_contract, '| `analytics.summary` | Faluss Analytics |' ) && false !== strpos( $master_contract, "n'active pas le" ), 'Master Profile must reserve but not activate analytics.summary.' );
an01a_assert( false !== strpos( $architecture, '## Analytics AN-01A' ) && false !== strpos( $data_model, '## Analytics AN-01A' ) && false !== strpos( $roadmap, '## AN-01A — Contrat Analytics et premiers événements — livré contractuellement' ), 'Architecture, data model and roadmap must register documentary AN-01A.' );

$tracked = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' diff --name-only ' . escapeshellarg( $base) . ' --' );
$untracked = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' ls-files --others --exclude-standard' );
$changed = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $tracked . "\n" . (string) $untracked ) ) );
$changed = array_values( array_unique( array_map( function ( $path ) { return str_replace( '\\', '/', $path ); }, $changed ) ) );
$expected = $scope;
sort( $changed, SORT_STRING );
sort( $expected, SORT_STRING );
an01a_assert( 16 === count( $changed ) && $expected === $changed, 'AN-01A must change exactly the sixteen authorized files.' );
foreach ( $changed as $path ) {
    an01a_assert( 0 !== strpos( $path, 'plugins/' ), 'AN-01A must not modify a plugin: ' . $path );
}
foreach ( array( 'register_rest_route', 'CREATE TABLE', 'setcookie(', 'wp_schedule_event', 'Faluss_Events::register_event', 'add_action(' ) as $runtime_marker ) {
    an01a_assert( false === strpos( $analytics_contract, $runtime_marker ), 'Documentary contract must not contain runtime implementation marker: ' . $runtime_marker );
}
an01a_assert( false !== strpos( $analytics_contract, 'ne crée aucun plugin, ZIP, table' ) && false !== strpos( $analytics_contract, 'Aucune donnée réelle n\'est créée' ), 'Contract must explicitly confirm absence of plugin, table, route, cookie and real event.' );

fwrite( STDOUT, 'AN-01A Analytics documentary contract: OK (' . $an01a_assertions . ' assertions; no runtime or real event).' . PHP_EOL );
