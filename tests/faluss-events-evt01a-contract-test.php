<?php

/**
 * EVT-01A documentary-contract regression.
 *
 * The helpers below exercise the cross-field invariants required by EVT-01A.
 * They are deliberately test-only and are not presented as a complete JSON
 * Schema Draft 2020-12 implementation or as an events runtime.
 */

function evt01a_assert( $condition, $message ) {
    global $evt01a_assertions;
    $evt01a_assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function evt01a_is_list( $value ) {
    return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
}

function evt01a_exact_keys( $value, $expected ) {
    if ( ! is_array( $value ) || evt01a_is_list( $value ) ) {
        return false;
    }
    $actual = array_keys( $value );
    sort( $actual, SORT_STRING );
    sort( $expected, SORT_STRING );
    return $actual === $expected;
}

function evt01a_pattern( $value, $pattern ) {
    return is_string( $value ) && 1 === preg_match( '#' . $pattern . '#D', $value );
}

function evt01a_strict_utc( $value ) {
    if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])T(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]Z$/D', $value ) ) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new DateTimeZone( 'UTC' ) );
    $errors = DateTimeImmutable::getLastErrors();
    return false !== $date
        && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) )
        && $date->format( 'Y-m-d\TH:i:s\Z' ) === $value;
}

function evt01a_sensitive_string( $value ) {
    if ( ! is_string( $value ) ) {
        return false;
    }
    return 1 === preg_match( '/(?:https?:\/\/|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|(?:^|[^0-9])(?:[0-9]{1,3}\.){3}[0-9]{1,3}(?:$|[^0-9])|Mozilla\/|\b(?:cookie|session|bearer|stripe|password|secret)\b)/i', $value )
        || 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value );
}

function evt01a_opaque_reference( $value, $schema ) {
    return evt01a_pattern( $value, $schema['$defs']['opaqueReference']['pattern'] )
        && strlen( $value ) >= $schema['$defs']['opaqueReference']['minLength']
        && strlen( $value ) <= $schema['$defs']['opaqueReference']['maxLength']
        && ! evt01a_sensitive_string( $value );
}

function evt01a_payload_walk( $value, $depth, &$field_count, $limits, $forbidden_keys ) {
    if ( $depth > $limits['max_depth'] ) {
        return false;
    }
    if ( is_float( $value ) ) {
        return false;
    }
    if ( is_string( $value ) ) {
        return strlen( $value ) <= $limits['max_string_length'] && ! evt01a_sensitive_string( $value );
    }
    if ( is_int( $value ) || is_bool( $value ) || null === $value ) {
        return true;
    }
    if ( ! is_array( $value ) ) {
        return false;
    }
    if ( evt01a_is_list( $value ) ) {
        if ( count( $value ) > $limits['max_array_items'] ) {
            return false;
        }
        foreach ( $value as $child ) {
            if ( ! evt01a_payload_walk( $child, $depth + 1, $field_count, $limits, $forbidden_keys ) ) {
                return false;
            }
        }
        return true;
    }
    if ( count( $value ) > $limits['max_fields_per_object'] ) {
        return false;
    }
    foreach ( $value as $key => $child ) {
        if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $key ) || in_array( $key, $forbidden_keys, true ) ) {
            return false;
        }
        $field_count++;
        if ( $field_count > $limits['max_total_fields'] || ! evt01a_payload_walk( $child, $depth + 1, $field_count, $limits, $forbidden_keys ) ) {
            return false;
        }
    }
    return true;
}

function evt01a_payload_is_bounded( $payload, $schema ) {
    if ( ! is_array( $payload ) || evt01a_is_list( $payload ) ) {
        return false;
    }
    $encoded = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    if ( false === $encoded || strlen( $encoded ) > $schema['x-payload-validation']['limits']['max_total_bytes'] ) {
        return false;
    }
    $forbidden = $schema['$defs']['payloadPropertyName']['not']['enum'];
    $fields = 0;
    return evt01a_payload_walk( $payload, 0, $fields, $schema['x-payload-validation']['limits'], $forbidden );
}

function evt01a_catalog_has_forbidden_content( $value ) {
    if ( is_string( $value ) ) {
        return 1 === preg_match( '/(?:https?:\/\/|<\?php|<script|javascript:|\b(?:eval|function|callback|endpoint|private[_ -]?key|public[_ -]?key|secret|signature)\b)/i', $value );
    }
    if ( ! is_array( $value ) ) {
        return false;
    }
    $forbidden_keys = array( 'transport_url', 'endpoint', 'public_key', 'private_key', 'secret', 'signature', 'php_callback', 'class_name', 'function_name', 'script', 'html', 'css', 'asset', 'executable_content' );
    foreach ( $value as $key => $child ) {
        if ( is_string( $key ) && in_array( strtolower( $key ), $forbidden_keys, true ) ) {
            return true;
        }
        if ( evt01a_catalog_has_forbidden_content( $child ) ) {
            return true;
        }
    }
    return false;
}

function evt01a_collect_schema_refs( $value, &$refs ) {
    if ( ! is_array( $value ) ) {
        return;
    }
    foreach ( $value as $key => $child ) {
        if ( '$ref' === $key && is_string( $child ) ) {
            $refs[] = $child;
        }
        evt01a_collect_schema_refs( $child, $refs );
    }
}

function evt01a_local_refs_resolve( $schema ) {
    $refs = array();
    evt01a_collect_schema_refs( $schema, $refs );
    if ( empty( $refs ) ) {
        return false;
    }
    foreach ( $refs as $ref ) {
        if ( 0 !== strpos( $ref, '#/$defs/' ) ) {
            return false;
        }
        $name = substr( $ref, strlen( '#/$defs/' ) );
        if ( '' === $name || ! array_key_exists( $name, $schema['$defs'] ) ) {
            return false;
        }
    }
    return true;
}

function evt01a_valid_payload_contract( $contract, $schema, $app_key ) {
    return evt01a_exact_keys( $contract, array( 'document_type', 'contract_version' ) )
        && evt01a_pattern( $contract['document_type'], $schema['$defs']['documentType']['pattern'] )
        && 0 === strpos( $contract['document_type'], $app_key . '.' )
        && evt01a_pattern( $contract['contract_version'], $schema['$defs']['semanticVersion']['pattern'] );
}

function evt01a_definition_is_valid( $definition, $catalog, $schema ) {
    $required = $schema['$defs']['eventDefinition']['required'];
    if ( ! evt01a_exact_keys( $definition, $required ) ) {
        return false;
    }
    if ( ! evt01a_pattern( $definition['event_type'], $schema['$defs']['namespacedKey']['pattern'] ) || 0 !== strpos( $definition['event_type'], $catalog['app_key'] . '.' ) ) {
        return false;
    }
    if ( ! evt01a_pattern( $definition['event_version'], $schema['$defs']['semanticVersion']['pattern'] ) || ! evt01a_valid_payload_contract( $definition['payload_contract'], $schema, $catalog['app_key'] ) ) {
        return false;
    }
    if ( ! in_array( $definition['subject_policy'], $schema['$defs']['subjectPolicy']['enum'], true ) ) {
        return false;
    }
    $actors = $definition['allowed_actor_types'];
    if ( ! evt01a_is_list( $actors ) || empty( $actors ) || count( $actors ) !== count( array_unique( $actors, SORT_STRING ) ) || array_diff( $actors, $schema['$defs']['actorType']['enum'] ) ) {
        return false;
    }
    $object = $definition['object_policy'];
    if ( ! evt01a_exact_keys( $object, array( 'presence', 'allowed_types' ) ) || ! in_array( $object['presence'], array( 'required', 'optional', 'forbidden' ), true ) || ! evt01a_is_list( $object['allowed_types'] ) || count( $object['allowed_types'] ) !== count( array_unique( $object['allowed_types'], SORT_STRING ) ) ) {
        return false;
    }
    if ( ( 'forbidden' === $object['presence'] ) !== empty( $object['allowed_types'] ) ) {
        return false;
    }
    foreach ( $object['allowed_types'] as $object_type ) {
        if ( ! evt01a_pattern( $object_type, $schema['$defs']['objectType']['pattern'] ) ) {
            return false;
        }
    }
    $destinations = $definition['allowed_destinations'];
    if ( ! evt01a_is_list( $destinations ) || empty( $destinations ) || count( $destinations ) !== count( array_unique( $destinations, SORT_STRING ) ) || array_diff( $destinations, $schema['$defs']['destination']['enum'] ) ) {
        return false;
    }
    if ( ! is_int( $definition['max_delivery_delay_seconds'] ) || $definition['max_delivery_delay_seconds'] < 1 || $definition['max_delivery_delay_seconds'] > 604800 ) {
        return false;
    }
    if ( ! in_array( $definition['data_classification'], array( 'operational', 'pseudonymous', 'personal' ), true ) || ! is_int( $definition['max_retention_seconds'] ) || $definition['max_retention_seconds'] < 0 || $definition['max_retention_seconds'] > 31536000 || ! in_array( $definition['member_result_visibility'], array( 'aggregate_only', 'own_subject_only', 'never' ), true ) ) {
        return false;
    }
    $lifecycle = $definition['lifecycle'];
    if ( ! evt01a_exact_keys( $lifecycle, array( 'deprecated', 'sunset_at', 'replacement_event_type' ) ) || ! is_bool( $lifecycle['deprecated'] ) || ( null !== $lifecycle['sunset_at'] && ! evt01a_strict_utc( $lifecycle['sunset_at'] ) ) ) {
        return false;
    }
    return null === $lifecycle['replacement_event_type']
        || ( evt01a_pattern( $lifecycle['replacement_event_type'], $schema['$defs']['namespacedKey']['pattern'] ) && 0 === strpos( $lifecycle['replacement_event_type'], $catalog['app_key'] . '.' ) );
}

function evt01a_catalog_is_valid( $catalog, $schema ) {
    if ( ! evt01a_exact_keys( $catalog, $schema['required'] ) || evt01a_catalog_has_forbidden_content( $catalog ) ) {
        return false;
    }
    if ( '1.0.0' !== $catalog['contract_version'] || 'faluss.event-source-catalog' !== $catalog['document_type'] || ! evt01a_pattern( $catalog['catalog_version'], $schema['$defs']['semanticVersion']['pattern'] ) ) {
        return false;
    }
    foreach ( array( 'node_id' => 'nodeId', 'app_key' => 'appKey', 'owner' => 'appKey', 'owner_engine' => 'engineId', 'capability_key' => 'namespacedKey' ) as $field => $definition ) {
        if ( ! evt01a_pattern( $catalog[ $field ], $schema['$defs'][ $definition ]['pattern'] ) ) {
            return false;
        }
    }
    if ( $catalog['owner'] !== $catalog['app_key'] || 0 !== strpos( $catalog['capability_key'], $catalog['app_key'] . '.' ) || 'event_source' !== $catalog['capability_interface'] ) {
        return false;
    }
    $compatibility = $catalog['compatibility'];
    if ( ! evt01a_exact_keys( $compatibility, $schema['$defs']['catalogCompatibility']['required'] ) || ! evt01a_pattern( $compatibility['minimum_runtime_version'], $schema['$defs']['semanticVersion']['pattern'] ) || ! evt01a_is_list( $compatibility['compatible_with'] ) || empty( $compatibility['compatible_with'] ) || count( $compatibility['compatible_with'] ) !== count( array_unique( $compatibility['compatible_with'], SORT_STRING ) ) || ! is_bool( $compatibility['deprecated'] ) || ( null !== $compatibility['sunset_at'] && ! evt01a_strict_utc( $compatibility['sunset_at'] ) ) || ( null !== $compatibility['replacement_catalog_version'] && ! evt01a_pattern( $compatibility['replacement_catalog_version'], $schema['$defs']['semanticVersion']['pattern'] ) ) ) {
        return false;
    }
    foreach ( $compatibility['compatible_with'] as $version ) {
        if ( ! evt01a_pattern( $version, $schema['$defs']['semanticVersion']['pattern'] ) ) {
            return false;
        }
    }
    if ( ! evt01a_is_list( $catalog['event_types'] ) || empty( $catalog['event_types'] ) || count( $catalog['event_types'] ) > 64 ) {
        return false;
    }
    $types = array();
    $pairs = array();
    foreach ( $catalog['event_types'] as $definition ) {
        if ( ! evt01a_definition_is_valid( $definition, $catalog, $schema ) ) {
            return false;
        }
        $type = $definition['event_type'];
        $pair = $type . "\x1F" . $definition['event_version'];
        if ( isset( $types[ $type ] ) || isset( $pairs[ $pair ] ) ) {
            return false;
        }
        $types[ $type ] = true;
        $pairs[ $pair ] = true;
    }
    return true;
}

function evt01a_subject_is_valid( $subject, $policy, $schema ) {
    if ( null === $subject ) {
        return 'required' !== $policy;
    }
    if ( 'forbidden' === $policy || ! evt01a_exact_keys( $subject, array( 'subject_type', 'subject_faluss_id' ) ) || 'faluss_member' !== $subject['subject_type'] ) {
        return false;
    }
    return evt01a_pattern( $subject['subject_faluss_id'], $schema['$defs']['uuidV4']['pattern'] );
}

function evt01a_actor_is_valid( $actor, $allowed_types, $schema ) {
    if ( ! evt01a_exact_keys( $actor, array( 'actor_type', 'actor_faluss_id', 'anonymous_reference', 'anonymous_scope' ) ) || ! in_array( $actor['actor_type'], $allowed_types, true ) ) {
        return false;
    }
    if ( 'member' === $actor['actor_type'] ) {
        return evt01a_pattern( $actor['actor_faluss_id'], $schema['$defs']['uuidV4']['pattern'] ) && null === $actor['anonymous_reference'] && null === $actor['anonymous_scope'];
    }
    if ( 'system' === $actor['actor_type'] ) {
        return null === $actor['actor_faluss_id'] && null === $actor['anonymous_reference'] && null === $actor['anonymous_scope'];
    }
    if ( 'anonymous' !== $actor['actor_type'] || null !== $actor['actor_faluss_id'] ) {
        return false;
    }
    if ( null === $actor['anonymous_reference'] ) {
        return null === $actor['anonymous_scope'];
    }
    return evt01a_opaque_reference( $actor['anonymous_reference'], $schema ) && in_array( $actor['anonymous_scope'], array( 'request', 'daily' ), true );
}

function evt01a_object_is_valid( $object, $policy, $schema ) {
    if ( null === $object ) {
        return 'required' !== $policy['presence'];
    }
    if ( 'forbidden' === $policy['presence'] || ! evt01a_exact_keys( $object, array( 'object_type', 'object_reference' ) ) || ! in_array( $object['object_type'], $policy['allowed_types'], true ) ) {
        return false;
    }
    return evt01a_pattern( $object['object_type'], $schema['$defs']['objectContext']['oneOf'][1]['properties']['object_type']['pattern'] ) && evt01a_opaque_reference( $object['object_reference'], $schema );
}

function evt01a_payload_validators() {
    return array(
        'faluss-hub.portal-viewed@1.0.0' => function ( $payload ) {
            return evt01a_exact_keys( $payload, array( 'surface' ) ) && in_array( $payload['surface'], array( 'member_portal', 'apps' ), true );
        },
        'faluss-hub.app-opened@1.0.0' => function ( $payload ) {
            return evt01a_exact_keys( $payload, array( 'target_app_key' ) ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $payload['target_app_key'] );
        },
        'faluss-hub.daily-reward-claimed@1.0.0' => function ( $payload ) {
            return evt01a_exact_keys( $payload, array( 'claim_state' ) ) && 'committed' === $payload['claim_state'];
        },
        'faluss-me.card-viewed@1.0.0' => function ( $payload ) {
            return evt01a_exact_keys( $payload, array( 'view_kind' ) ) && 'public_card' === $payload['view_kind'];
        },
        'faluss-me.link-clicked@1.0.0' => function ( $payload ) {
            return evt01a_exact_keys( $payload, array( 'interaction' ) ) && 'link_click' === $payload['interaction'];
        },
        'faluss-me.collection-opened@1.0.0' => function ( $payload ) {
            return evt01a_exact_keys( $payload, array( 'interaction' ) ) && 'collection_open' === $payload['interaction'];
        },
    );
}

function evt01a_event_is_valid( $event, $catalog, $envelope_schema, $catalog_schema, $payload_validators ) {
    if ( ! evt01a_exact_keys( $event, $envelope_schema['required'] ) || ! evt01a_catalog_is_valid( $catalog, $catalog_schema ) ) {
        return false;
    }
    if ( '1.0.0' !== $event['contract_version'] || ! evt01a_pattern( $event['event_id'], $envelope_schema['$defs']['uuidV4']['pattern'] ) || ! evt01a_pattern( $event['event_type'], $envelope_schema['$defs']['namespacedKey']['pattern'] ) || ! evt01a_pattern( $event['event_version'], $envelope_schema['$defs']['semanticVersion']['pattern'] ) || ! evt01a_opaque_reference( $event['source_event_reference'], $envelope_schema ) ) {
        return false;
    }
    $source = $event['source'];
    if ( ! evt01a_exact_keys( $source, array( 'node_id', 'app_key', 'owner', 'capability_key', 'catalog_version' ) ) || $source['node_id'] !== $catalog['node_id'] || $source['app_key'] !== $catalog['app_key'] || $source['owner'] !== $catalog['owner'] || $source['capability_key'] !== $catalog['capability_key'] || $source['catalog_version'] !== $catalog['catalog_version'] || $source['owner'] !== $source['app_key'] || 0 !== strpos( $event['event_type'], $source['app_key'] . '.' ) || 0 !== strpos( $source['capability_key'], $source['app_key'] . '.' ) ) {
        return false;
    }
    $definition = null;
    foreach ( $catalog['event_types'] as $candidate ) {
        if ( $candidate['event_type'] === $event['event_type'] && $candidate['event_version'] === $event['event_version'] ) {
            $definition = $candidate;
            break;
        }
    }
    if ( null === $definition || ! evt01a_strict_utc( $event['occurred_at'] ) || ! evt01a_strict_utc( $event['produced_at'] ) ) {
        return false;
    }
    $occurred = strtotime( $event['occurred_at'] );
    $produced = strtotime( $event['produced_at'] );
    if ( $produced < $occurred || ( $produced - $occurred ) > $definition['max_delivery_delay_seconds'] ) {
        return false;
    }
    if ( ! evt01a_subject_is_valid( $event['subject_context'], $definition['subject_policy'], $envelope_schema ) || ! evt01a_actor_is_valid( $event['actor_context'], $definition['allowed_actor_types'], $envelope_schema ) || ! evt01a_object_is_valid( $event['object_context'], $definition['object_policy'], $envelope_schema ) ) {
        return false;
    }
    if ( ! evt01a_is_list( $event['destinations'] ) || empty( $event['destinations'] ) || count( $event['destinations'] ) !== count( array_unique( $event['destinations'], SORT_STRING ) ) || array_diff( $event['destinations'], $definition['allowed_destinations'] ) ) {
        return false;
    }
    if ( ! evt01a_exact_keys( $event['payload_contract'], array( 'document_type', 'contract_version' ) ) || $event['payload_contract'] !== $definition['payload_contract'] || ! evt01a_payload_is_bounded( $event['payload'], $envelope_schema ) ) {
        return false;
    }
    $validator_key = $event['payload_contract']['document_type'] . '@' . $event['payload_contract']['contract_version'];
    return isset( $payload_validators[ $validator_key ] ) && true === $payload_validators[ $validator_key ]( $event['payload'] );
}

function evt01a_definition( $event_type, $payload_document_type, $subject_policy, $actors, $object_presence, $object_types, $destinations, $classification, $visibility, $delay = 3600 ) {
    return array(
        'event_type'                => $event_type,
        'event_version'             => '1.0.0',
        'payload_contract'          => array( 'document_type' => $payload_document_type, 'contract_version' => '1.0.0' ),
        'subject_policy'            => $subject_policy,
        'allowed_actor_types'       => $actors,
        'object_policy'             => array( 'presence' => $object_presence, 'allowed_types' => $object_types ),
        'allowed_destinations'      => $destinations,
        'max_delivery_delay_seconds' => $delay,
        'data_classification'       => $classification,
        'max_retention_seconds'     => 2592000,
        'member_result_visibility' => $visibility,
        'lifecycle'                 => array( 'deprecated' => false, 'sunset_at' => null, 'replacement_event_type' => null ),
    );
}

function evt01a_catalog( $app_key, $node_id, $engine, $definitions ) {
    return array(
        'contract_version'    => '1.0.0',
        'document_type'       => 'faluss.event-source-catalog',
        'catalog_version'     => '1.0.0',
        'node_id'             => $node_id,
        'app_key'             => $app_key,
        'owner'               => $app_key,
        'owner_engine'        => $engine,
        'capability_key'      => $app_key . '.events',
        'capability_interface' => 'event_source',
        'event_types'         => $definitions,
        'compatibility'       => array(
            'minimum_runtime_version'     => '1.0.0',
            'compatible_with'             => array( '1.0.0' ),
            'deprecated'                 => false,
            'sunset_at'                  => null,
            'replacement_catalog_version' => null,
        ),
    );
}

function evt01a_base_event( $catalog, $definition, $event_id, $reference, $subject_id ) {
    return array(
        'contract_version'       => '1.0.0',
        'event_id'               => $event_id,
        'event_type'             => $definition['event_type'],
        'event_version'          => $definition['event_version'],
        'source'                 => array(
            'node_id'         => $catalog['node_id'],
            'app_key'         => $catalog['app_key'],
            'owner'           => $catalog['owner'],
            'capability_key'  => $catalog['capability_key'],
            'catalog_version' => $catalog['catalog_version'],
        ),
        'source_event_reference' => $reference,
        'occurred_at'            => '2026-09-12T10:00:00Z',
        'produced_at'            => '2026-09-12T10:00:01Z',
        'subject_context'        => array( 'subject_type' => 'faluss_member', 'subject_faluss_id' => $subject_id ),
        'actor_context'          => array( 'actor_type' => 'member', 'actor_faluss_id' => $subject_id, 'anonymous_reference' => null, 'anonymous_scope' => null ),
        'object_context'         => null,
        'destinations'           => array( 'analytics.events' ),
        'payload_contract'       => $definition['payload_contract'],
        'payload'                => array(),
    );
}

function evt01a_canonical_test_value( $value ) {
    if ( ! is_array( $value ) ) {
        return $value;
    }
    if ( evt01a_is_list( $value ) ) {
        return array_map( 'evt01a_canonical_test_value', $value );
    }
    ksort( $value, SORT_STRING );
    foreach ( $value as $key => $child ) {
        $value[ $key ] = evt01a_canonical_test_value( $child );
    }
    return $value;
}

function evt01a_test_hash( $event ) {
    return hash( 'sha256', json_encode( evt01a_canonical_test_value( $event ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

function evt01a_business_identity( $event ) {
    return implode( "\x1F", array( $event['source']['node_id'], $event['source']['app_key'], $event['event_type'], $event['event_version'], $event['source_event_reference'] ) );
}

function evt01a_source_reference_identity( $event ) {
    return implode( "\x1F", array( $event['source']['node_id'], $event['source']['app_key'], $event['source_event_reference'] ) );
}

function evt01a_accept_for_test( &$store, $event ) {
    $identity = evt01a_business_identity( $event );
    $reference_identity = evt01a_source_reference_identity( $event );
    $hash = evt01a_test_hash( $event );
    if ( isset( $store['by_event_id'][ $event['event_id'] ] ) && $store['by_event_id'][ $event['event_id'] ] !== $identity ) {
        return 'conflict';
    }
    if ( isset( $store['by_source_reference'][ $reference_identity ] ) && $store['by_source_reference'][ $reference_identity ] !== $identity ) {
        return 'conflict';
    }
    if ( isset( $store['events'][ $identity ] ) ) {
        $existing = $store['events'][ $identity ];
        return $existing['event_id'] === $event['event_id'] && $existing['hash'] === $hash ? 'existing' : 'conflict';
    }
    $store['events'][ $identity ] = array( 'event_id' => $event['event_id'], 'hash' => $hash, 'event' => $event );
    $store['by_event_id'][ $event['event_id'] ] = $identity;
    $store['by_source_reference'][ $reference_identity ] = $identity;
    return 'accepted';
}

function evt01a_delete_for_test( &$store, $event ) {
    unset( $store );
    unset( $event );
    return false;
}

function evt01a_deliver_for_test( &$deliveries, $event, $destination, $consumer ) {
    $key = $event['event_id'] . "\x1F" . $destination . "\x1F" . $consumer;
    if ( isset( $deliveries[ $key ] ) ) {
        return 'existing';
    }
    $deliveries[ $key ] = true;
    return 'processed';
}

$evt01a_assertions = 0;
$root = dirname( __DIR__ );
$envelope_json = file_get_contents( $root . '/contracts/faluss-event-envelope.schema.json' );
$catalog_json = file_get_contents( $root . '/contracts/faluss-event-source-catalog.schema.json' );
$envelope_schema = json_decode( $envelope_json, true );
$catalog_schema = json_decode( $catalog_json, true );

evt01a_assert( is_array( $envelope_schema ) && JSON_ERROR_NONE === json_last_error(), 'Envelope schema must be valid JSON.' );
evt01a_assert( is_array( $catalog_schema ), 'Catalog schema must be valid JSON.' );
foreach ( array( $envelope_schema, $catalog_schema ) as $schema ) {
    evt01a_assert( 'https://json-schema.org/draft/2020-12/schema' === $schema['$schema'], 'Every EVT-01A schema must declare Draft 2020-12.' );
    evt01a_assert( 'object' === $schema['type'] && false === $schema['additionalProperties'], 'Every EVT-01A root must be a closed object.' );
    evt01a_assert( 'documentary-contract-only' === $schema['x-evt01a-scope']['nature'], 'Scope must remain documentary-contract-only.' );
    foreach ( array( 'wordpress_runtime_changes', 'transport', 'tables_or_migrations', 'endpoints', 'real_events', 'assets_or_ui_changes' ) as $flag ) {
        evt01a_assert( false === $schema['x-evt01a-scope'][ $flag ], 'Scope flag must stay false: ' . $flag );
    }
    evt01a_assert( evt01a_local_refs_resolve( $schema ), 'Every schema reference must resolve to a local Draft 2020-12 definition.' );
}

$expected_envelope_fields = array( 'contract_version', 'event_id', 'event_type', 'event_version', 'source', 'source_event_reference', 'occurred_at', 'produced_at', 'subject_context', 'actor_context', 'object_context', 'destinations', 'payload_contract', 'payload' );
evt01a_assert( $expected_envelope_fields === $envelope_schema['required'], 'Envelope must require exactly the fourteen normative root fields.' );
evt01a_assert( 3 === count( $envelope_schema['$defs']['actorContext']['allOf'] ) && isset( $envelope_schema['$defs']['actorContext']['allOf'][0]['if'], $envelope_schema['$defs']['actorContext']['allOf'][0]['then'] ), 'Actor states must be represented by Draft 2020-12 conditional branches.' );
evt01a_assert( array( 'analytics.events', 'quests.events', 'progression.events' ) === $envelope_schema['$defs']['destination']['enum'], 'Destinations must be the three closed EVT consumers.' );
evt01a_assert( true === $envelope_schema['x-payload-validation']['specialized_validator_required'] && 'reject' === $envelope_schema['x-payload-validation']['on_missing_validator'], 'An exact specialized payload validator must be mandatory.' );
evt01a_assert( 'RFC8785-JCS-SHA256' === $envelope_schema['x-idempotency']['retry_comparison'] && 'at-least-once' === $envelope_schema['x-delivery-semantics']['producer_delivery'] && 'exactly-once-by-idempotence' === $envelope_schema['x-delivery-semantics']['consumer_effect'], 'Schema must expose canonical retry and delivery semantics.' );
evt01a_assert( 'event_source' === $catalog_schema['properties']['capability_interface']['const'], 'Catalog capability interface must be event_source.' );
evt01a_assert( isset( $catalog_schema['$defs']['objectPolicy']['allOf'][0]['if'], $catalog_schema['$defs']['objectPolicy']['allOf'][0]['then'], $catalog_schema['$defs']['objectPolicy']['allOf'][0]['else'] ), 'Object presence policy must use a closed Draft 2020-12 conditional branch.' );
evt01a_assert( array( 'operational', 'pseudonymous', 'personal' ) === $catalog_schema['$defs']['eventDefinition']['properties']['data_classification']['enum'], 'Catalog classifications must be closed.' );
evt01a_assert( array( 'aggregate_only', 'own_subject_only', 'never' ) === $catalog_schema['$defs']['eventDefinition']['properties']['member_result_visibility']['enum'], 'Member result visibility must be closed.' );

$allowed_paths = array(
    'docs/FALUSS_EVENTS_CONTRACT.md',
    'contracts/faluss-event-envelope.schema.json',
    'contracts/faluss-event-source-catalog.schema.json',
    'tests/faluss-events-evt01a-contract-test.php',
    'docs/FALUSS_CAPABILITIES_CONTRACT.md',
    'docs/FALUSS_FEDERATION_CONTRACT.md',
    'docs/FALUSS_PORTAL.md',
    'docs/ARCHITECTURE.md',
    'docs/DATA_MODEL.md',
    'docs/ROADMAP.md',
);
evt01a_assert( $allowed_paths === $envelope_schema['x-evt01a-scope']['allowed_changed_paths'], 'Envelope scope must list exactly the ten authorized files.' );
evt01a_assert( $allowed_paths === $catalog_schema['x-evt01a-scope']['allowed_changed_paths'], 'Catalog scope must list exactly the ten authorized files.' );
foreach ( $allowed_paths as $path ) {
    evt01a_assert( 0 !== strpos( $path, 'plugins/' ) && '.zip' !== strtolower( substr( $path, -4 ) ), 'EVT-01A scope must contain no plugin or ZIP: ' . $path );
}

$hub_definitions = array(
    evt01a_definition( 'faluss-hub.portal.viewed', 'faluss-hub.portal-viewed', 'required', array( 'member' ), 'forbidden', array(), array( 'analytics.events', 'quests.events' ), 'personal', 'own_subject_only' ),
    evt01a_definition( 'faluss-hub.app.opened', 'faluss-hub.app-opened', 'required', array( 'member' ), 'required', array( 'application' ), array( 'analytics.events', 'quests.events' ), 'pseudonymous', 'own_subject_only' ),
    evt01a_definition( 'faluss-hub.daily-reward.claimed', 'faluss-hub.daily-reward-claimed', 'required', array( 'member', 'system' ), 'forbidden', array(), array( 'analytics.events', 'quests.events', 'progression.events' ), 'operational', 'never' ),
);
$me_definitions = array(
    evt01a_definition( 'faluss-me.card.viewed', 'faluss-me.card-viewed', 'required', array( 'member', 'anonymous' ), 'optional', array( 'card' ), array( 'analytics.events' ), 'pseudonymous', 'aggregate_only' ),
    evt01a_definition( 'faluss-me.link.clicked', 'faluss-me.link-clicked', 'required', array( 'member', 'anonymous' ), 'required', array( 'link' ), array( 'analytics.events' ), 'pseudonymous', 'aggregate_only' ),
    evt01a_definition( 'faluss-me.collection.opened', 'faluss-me.collection-opened', 'required', array( 'member', 'anonymous' ), 'required', array( 'collection' ), array( 'analytics.events' ), 'pseudonymous', 'aggregate_only' ),
);
$hub_catalog = evt01a_catalog( 'faluss-hub', 'hub-node', 'faluss-portal', $hub_definitions );
$me_catalog = evt01a_catalog( 'faluss-me', 'me-node', 'faluss-link', $me_definitions );
$validators = evt01a_payload_validators();
$member_id = '11111111-1111-4111-8111-111111111111';

// Valid 8 and 9: coherent future Hub source and distinct multi-type Me source.
evt01a_assert( evt01a_catalog_is_valid( $hub_catalog, $catalog_schema ), 'A coherent Hub event_source catalog must be valid.' );
evt01a_assert( evt01a_catalog_is_valid( $me_catalog, $catalog_schema ) && 3 === count( $me_catalog['event_types'] ), 'A Me catalog with several distinct event types must be valid.' );

// Valid 1: Hub member event to Analytics.
$hub_view = evt01a_base_event( $hub_catalog, $hub_definitions[0], '10000000-0000-4000-8000-000000000001', 'hubview_ref_0001', $member_id );
$hub_view['payload'] = array( 'surface' => 'member_portal' );
evt01a_assert( evt01a_event_is_valid( $hub_view, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'A Hub member event to Analytics must be valid.' );

// Valid 2: Me card view with anonymous actor and no unique reference.
$me_anonymous = evt01a_base_event( $me_catalog, $me_definitions[0], '20000000-0000-4000-8000-000000000001', 'cardview_ref_0001', $member_id );
$me_anonymous['actor_context'] = array( 'actor_type' => 'anonymous', 'actor_faluss_id' => null, 'anonymous_reference' => null, 'anonymous_scope' => null );
$me_anonymous['payload'] = array( 'view_kind' => 'public_card' );
evt01a_assert( evt01a_event_is_valid( $me_anonymous, $me_catalog, $envelope_schema, $catalog_schema, $validators ), 'An anonymous card view without a unique reference must be valid.' );

// Valid 3: Me card view with a server-produced daily anonymous reference.
$me_daily = $me_anonymous;
$me_daily['event_id'] = '20000000-0000-4000-8000-000000000002';
$me_daily['source_event_reference'] = 'cardview_ref_0002';
$me_daily['actor_context']['anonymous_reference'] = 'anonref_daily_0002';
$me_daily['actor_context']['anonymous_scope'] = 'daily';
evt01a_assert( evt01a_event_is_valid( $me_daily, $me_catalog, $envelope_schema, $catalog_schema, $validators ), 'A daily opaque anonymous reference must be valid.' );

// Valid 4: link click carries only an opaque object reference.
$me_link = evt01a_base_event( $me_catalog, $me_definitions[1], '20000000-0000-4000-8000-000000000003', 'linkclick_ref_003', $member_id );
$me_link['object_context'] = array( 'object_type' => 'link', 'object_reference' => 'linkref_opaque_003' );
$me_link['payload'] = array( 'interaction' => 'link_click' );
evt01a_assert( evt01a_event_is_valid( $me_link, $me_catalog, $envelope_schema, $catalog_schema, $validators ), 'A Me link click with only an opaque object reference must be valid.' );

// Valid 5: one event can address Analytics and Quests without duplication.
$multi_destination = $hub_view;
$multi_destination['event_id'] = '10000000-0000-4000-8000-000000000005';
$multi_destination['source_event_reference'] = 'hubview_ref_0005';
$multi_destination['destinations'] = array( 'analytics.events', 'quests.events' );
evt01a_assert( evt01a_event_is_valid( $multi_destination, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'One event may request Analytics and Quests.' );

// Valid 6: a system actor carries no member or anonymous reference.
$system_event = evt01a_base_event( $hub_catalog, $hub_definitions[2], '10000000-0000-4000-8000-000000000006', 'dailyclaim_ref_006', $member_id );
$system_event['actor_context'] = array( 'actor_type' => 'system', 'actor_faluss_id' => null, 'anonymous_reference' => null, 'anonymous_scope' => null );
$system_event['payload'] = array( 'claim_state' => 'committed' );
evt01a_assert( evt01a_event_is_valid( $system_event, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'A system actor with all references null must be valid.' );

// Valid 10: delayed production remains valid inside the catalog delay.
$delayed = $hub_view;
$delayed['event_id'] = '10000000-0000-4000-8000-000000000010';
$delayed['source_event_reference'] = 'hubview_ref_0010';
$delayed['produced_at'] = '2026-09-12T10:59:59Z';
evt01a_assert( evt01a_event_is_valid( $delayed, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'A delayed event inside max_delivery_delay_seconds must retain its original occurred_at.' );

// Valid 7 and invalid retry conflicts: an immutable occurrence is deduplicated.
$store = array( 'events' => array(), 'by_event_id' => array(), 'by_source_reference' => array() );
evt01a_assert( 'accepted' === evt01a_accept_for_test( $store, $hub_view ), 'First occurrence must be accepted once.' );
evt01a_assert( 'existing' === evt01a_accept_for_test( $store, $hub_view ) && 1 === count( $store['events'] ), 'Strictly identical retry must return the existing occurrence.' );
$changed_retry = $hub_view;
$changed_retry['payload']['surface'] = 'apps';
evt01a_assert( evt01a_event_is_valid( $changed_retry, $hub_catalog, $envelope_schema, $catalog_schema, $validators ) && 'conflict' === evt01a_accept_for_test( $store, $changed_retry ), 'Same business reference with different content must conflict closed.' );
$new_id_retry = $hub_view;
$new_id_retry['event_id'] = '10000000-0000-4000-8000-000000000011';
evt01a_assert( 'conflict' === evt01a_accept_for_test( $store, $new_id_retry ), 'Same business reference with a new event_id must conflict closed.' );
$new_version_retry = $hub_view;
$new_version_retry['event_id'] = '10000000-0000-4000-8000-000000000012';
$new_version_retry['event_version'] = '2.0.0';
evt01a_assert( 'conflict' === evt01a_accept_for_test( $store, $new_version_retry ), 'Same source reference with another event version must conflict closed.' );
$reordered_event = array_reverse( $hub_view, true );
evt01a_assert( evt01a_test_hash( $hub_view ) === evt01a_test_hash( $reordered_event ), 'Test canonicalization must ignore object member order.' );
$reordered_destinations = $multi_destination;
$reordered_destinations['destinations'] = array_reverse( $multi_destination['destinations'] );
evt01a_assert( evt01a_test_hash( $multi_destination ) !== evt01a_test_hash( $reordered_destinations ), 'Canonical comparison must preserve array order.' );

// Invalid 1 to 6: identity, ownership, interface and destinations are closed.
$invalid = $hub_view;
$invalid['event_id'] = 'not-a-v4';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Non-v4 event_id must be rejected.' );
$invalid = $hub_view;
$invalid['event_type'] = 'faluss-me.portal.viewed';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Event type outside the owner namespace must be rejected.' );
$invalid = $hub_view;
$invalid['source']['owner'] = 'faluss-me';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Owner different from app_key must be rejected.' );
$invalid_catalog = $hub_catalog;
$invalid_catalog['capability_interface'] = 'module_read_model';
evt01a_assert( ! evt01a_catalog_is_valid( $invalid_catalog, $catalog_schema ) && ! evt01a_event_is_valid( $hub_view, $invalid_catalog, $envelope_schema, $catalog_schema, $validators ), 'Capability without event_source must reject catalog and event.' );
$invalid = $hub_view;
$invalid['destinations'] = array( '*' );
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Wildcard destination must be rejected.' );
$invalid = $hub_view;
$invalid['destinations'] = array( 'progression.events' );
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Destination absent from the event catalog definition must be rejected.' );

// Invalid 7: both duplicate event_type and duplicate type/version are closed.
$duplicate_catalog = $hub_catalog;
$duplicate = $hub_definitions[0];
$duplicate['event_version'] = '2.0.0';
$duplicate_catalog['event_types'][] = $duplicate;
evt01a_assert( ! evt01a_catalog_is_valid( $duplicate_catalog, $catalog_schema ), 'Duplicate event_type with another version must be rejected.' );
$duplicate_catalog = $hub_catalog;
$duplicate_catalog['event_types'][] = $hub_definitions[0];
evt01a_assert( ! evt01a_catalog_is_valid( $duplicate_catalog, $catalog_schema ), 'Duplicate event_type and version must be rejected.' );

// Invalid 8 and 9: exact specialized payload contract and closed payload.
$invalid = $hub_view;
$invalid['payload_contract']['document_type'] = 'faluss-hub.unknown-payload';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Missing exact specialized payload validator must be rejected.' );
$invalid = $hub_view;
$invalid['payload']['metadata'] = array( 'anything' => true );
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Arbitrary metadata and extra payload fields must be rejected.' );

// Invalid 12 to 14: time is strict, ordered and server-owned.
$invalid = $hub_view;
$invalid['produced_at'] = '2026-09-12T09:59:59Z';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'produced_at before occurred_at must be rejected.' );
$invalid = $hub_view;
$invalid['produced_at'] = '2026-09-12T11:00:01Z';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Delivery beyond the catalog maximum delay must be rejected.' );
foreach ( array( '2026-09-12T12:00:00+02:00', '2026-09-12T10:00:00.000Z', '2026-9-12T10:00:00Z', '2026-02-30T10:00:00Z' ) as $invalid_date ) {
    $invalid = $hub_view;
    $invalid['occurred_at'] = $invalid_date;
    evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Offset, fraction, normalizable or impossible date must be rejected: ' . $invalid_date );
}
$invalid = $hub_view;
$invalid['browser_timestamp'] = '2026-09-12T10:00:00Z';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Browser or WordPress timezone timestamp input must not enter the envelope.' );
$invalid = $hub_view;
$invalid['source_event_reference'] = 'example.test';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Opaque source reference must not contain a raw domain.' );

// Invalid 15 and 16: identities and sensitive values stay out of payloads.
$invalid = $hub_view;
$invalid['payload'] = array( 'surface' => 'member_portal', 'faluss_id' => $member_id );
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'faluss_id outside reserved contexts must be rejected.' );
foreach ( array( 'email' => 'member@example.test', 'login' => 'member-login', 'name' => 'Member Name', 'pseudonym' => 'member-pseudonym', 'handle' => '@member', 'url' => 'https://example.test/path', 'domain' => 'example.test', 'ip' => '192.0.2.1', 'user_agent' => 'Mozilla/5.0', 'cookie' => 'session=value', 'session' => 'bearer token' ) as $key => $value ) {
    evt01a_assert( ! evt01a_payload_is_bounded( array( $key => $value ), $envelope_schema ), 'Sensitive payload field must be rejected: ' . $key );
}

// Invalid 17 to 20: actor and object state combinations are exact.
$invalid = $me_daily;
$invalid['actor_context']['anonymous_scope'] = null;
evt01a_assert( ! evt01a_event_is_valid( $invalid, $me_catalog, $envelope_schema, $catalog_schema, $validators ), 'Anonymous reference without scope must be rejected.' );
$invalid = $me_anonymous;
$invalid['actor_context']['anonymous_scope'] = 'daily';
evt01a_assert( ! evt01a_event_is_valid( $invalid, $me_catalog, $envelope_schema, $catalog_schema, $validators ), 'Anonymous scope without reference must be rejected.' );
$invalid = $me_anonymous;
$invalid['actor_context']['actor_faluss_id'] = $member_id;
evt01a_assert( ! evt01a_event_is_valid( $invalid, $me_catalog, $envelope_schema, $catalog_schema, $validators ), 'Anonymous actor with faluss_id must be rejected.' );
$invalid = $hub_view;
$invalid['actor_context']['actor_faluss_id'] = null;
evt01a_assert( ! evt01a_event_is_valid( $invalid, $hub_catalog, $envelope_schema, $catalog_schema, $validators ), 'Member actor without faluss_id must be rejected.' );
foreach ( array( 'object_url' => 'https://example.test', 'object_slug' => 'public-slug', 'object_title' => 'Public title' ) as $key => $value ) {
    $invalid = $me_link;
    $invalid['object_context'][ $key ] = $value;
    evt01a_assert( ! evt01a_event_is_valid( $invalid, $me_catalog, $envelope_schema, $catalog_schema, $validators ), 'Object URL, slug or title must be rejected: ' . $key );
}

// Invalid 21: every generic payload limit is fail-closed.
evt01a_assert( ! evt01a_payload_is_bounded( array( 'values' => range( 1, 33 ) ), $envelope_schema ), 'Oversized payload array must be rejected.' );
evt01a_assert( ! evt01a_payload_is_bounded( array( 'message' => str_repeat( 'x', 257 ) ), $envelope_schema ), 'Oversized payload string must be rejected.' );
evt01a_assert( ! evt01a_payload_is_bounded( array( 'level' => array( 'a' => array( 'b' => array( 'c' => array( 'd' => array( 'e' => 1 ) ) ) ) ) ), $envelope_schema ), 'Payload beyond maximum depth must be rejected.' );
evt01a_assert( ! evt01a_payload_is_bounded( array( 'ratio' => 1.5 ), $envelope_schema ), 'Non-contractual floating-point payload must be rejected.' );
$oversized_payload = array();
for ( $i = 0; $i < 32; $i++ ) {
    $oversized_payload[ 'field_' . $i ] = str_repeat( 'x', 256 );
}
evt01a_assert( ! evt01a_payload_is_bounded( $oversized_payload, $envelope_schema ), 'Payload beyond the total byte limit must be rejected.' );
$too_many_fields = array();
foreach ( array( 'group_a', 'group_b', 'group_c', 'group_d' ) as $group ) {
    $too_many_fields[ $group ] = array();
    for ( $i = 0; $i < 32; $i++ ) {
        $too_many_fields[ $group ][ 'field_' . $i ] = 1;
    }
}
evt01a_assert( ! evt01a_payload_is_bounded( $too_many_fields, $envelope_schema ), 'Payload beyond the total field limit must be rejected.' );

// Invalid 22 and 23: catalogs contain no executable/transport data and events are not commands.
$invalid_catalog = $hub_catalog;
$invalid_catalog['transport_url'] = 'https://example.test/events';
evt01a_assert( ! evt01a_catalog_is_valid( $invalid_catalog, $catalog_schema ), 'Catalog transport URL or extra executable field must be rejected.' );
foreach ( array( 'transport_url' => 'https://example.test/events', 'public_key' => 'public-material', 'php_callback' => 'owner_callback', 'script' => 'javascript:run()', 'asset' => 'logo.png' ) as $key => $value ) {
    evt01a_assert( evt01a_catalog_has_forbidden_content( array( $key => $value ) ), 'Catalog must reject URL, key, callback, code or asset content: ' . $key );
}
foreach ( array( 'command' => 'credit', 'pf_balance' => 20, 'entitlement' => 'pro', 'reward' => 'grant', 'stripe_payload' => 'stripe' ) as $key => $value ) {
    evt01a_assert( ! evt01a_payload_is_bounded( array( $key => $value ), $envelope_schema ), 'Event must not become PF, payment, entitlement or reward command: ' . $key );
}

// Invalid 24, 27 and 28: accepted facts are immutable and effects are unique.
evt01a_assert( false === evt01a_delete_for_test( $store, $hub_view ) && 1 === count( $store['events'] ), 'Accepted event cannot be deleted as a correction.' );
evt01a_assert( 'conflict' === evt01a_accept_for_test( $store, $changed_retry ) && 1 === count( $store['events'] ), 'Accepted event cannot be mutated.' );
$multi_store = array( 'events' => array(), 'by_event_id' => array(), 'by_source_reference' => array() );
evt01a_assert( 'accepted' === evt01a_accept_for_test( $multi_store, $multi_destination ) && 1 === count( $multi_store['events'] ), 'Several destinations must still create one original event.' );
$deliveries = array();
evt01a_assert( 'processed' === evt01a_deliver_for_test( $deliveries, $multi_destination, 'analytics.events', 'analytics-v1' ), 'First consumer delivery must be processed.' );
evt01a_assert( 'existing' === evt01a_deliver_for_test( $deliveries, $multi_destination, 'analytics.events', 'analytics-v1' ) && 1 === count( $deliveries ), 'Retry or concurrent worker must not create a second consumer effect.' );

// Invalid 25 and 26 plus documentary coordination checks.
$events_contract = file_get_contents( $root . '/docs/FALUSS_EVENTS_CONTRACT.md' );
$cap_contract = file_get_contents( $root . '/docs/FALUSS_CAPABILITIES_CONTRACT.md' );
$fed_contract = file_get_contents( $root . '/docs/FALUSS_FEDERATION_CONTRACT.md' );
$portal_contract = file_get_contents( $root . '/docs/FALUSS_PORTAL.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );
$federation_request = json_decode( file_get_contents( $root . '/contracts/faluss-federation-request.schema.json' ), true );

foreach ( array( 'faluss-hub.portal.viewed', 'faluss-hub.app.opened', 'faluss-hub.daily-reward.claimed', 'faluss-me.card.viewed', 'faluss-me.link.clicked', 'faluss-me.collection.opened' ) as $reserved_type ) {
    evt01a_assert( false !== strpos( $events_contract, '`' . $reserved_type . '`' ), 'Reserved event type must be documented without production: ' . $reserved_type );
}
foreach ( array( 'fait métier déjà survenu', 'jamais une commande', 'au moins une fois', 'exactement une fois par idempotence', 'RFC 8785/JCS', 'append-only', 'cookie, fingerprint', 'Aucun événement réel' ) as $needle ) {
    evt01a_assert( false !== strpos( $events_contract, $needle ), 'Common event boundary is missing: ' . $needle );
}
evt01a_assert( false !== strpos( $events_contract, "n'apparaît jamais dans le" ) && false !== strpos( $events_contract, 'payload, une référence' ) && false !== strpos( $events_contract, 'sortie membre' ), 'Raw event identifiers and Faluss IDs must never be exposed to the browser or member output.' );
evt01a_assert( false !== strpos( $cap_contract, '### Coordination EVT-01A' ) && false !== strpos( $cap_contract, 'CAP-01B.2 ne produit ni n\'active aucun' ), 'CAP must require catalog, active binding and consumer policy without activating events.' );
evt01a_assert( false !== strpos( $fed_contract, '`event.publish` reste interdit' ) && false !== strpos( $fed_contract, '`diagnostic.read`, `manifest.read` et `read_model.read`' ), 'Federation must keep event.publish and operation tunneling forbidden.' );
evt01a_assert( array( 'diagnostic.read', 'manifest.read', 'read_model.read' ) === $federation_request['properties']['operation']['enum'], 'Existing Federation schema must remain closed to the three read operations.' );
evt01a_assert( false !== strpos( $portal_contract, '## Frontière événements EVT-01A' ) && false !== strpos( $portal_contract, 'ne produit et ne consomme encore aucun événement' ), 'Portal must remain free of event production and tracking.' );
evt01a_assert( false !== strpos( $architecture, '## Contrat commun des événements EVT-01A' ), 'Architecture must record the future four-responsibility topology.' );
evt01a_assert( false !== strpos( $data_model, '## Événements EVT-01A' ) && false !== strpos( $data_model, "n'ajoute aucune table" ), 'Data model must record zero EVT-01A persistence.' );
evt01a_assert( false !== strpos( $roadmap, '## EVT-01A — Contrat commun des événements — livré contractuellement' ), 'Roadmap must register EVT-01A as contract-only.' );

fwrite( STDOUT, 'EVT-01A common event contract: OK (' . $evt01a_assertions . ' assertions; test-only semantic helpers, not a full Draft 2020-12 engine).' . PHP_EOL );
