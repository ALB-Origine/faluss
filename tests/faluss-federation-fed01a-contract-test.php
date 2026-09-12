<?php

$fed01a_assertions = 0;

function fed01a_assert( $condition, $message ) {
    global $fed01a_assertions;
    $fed01a_assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function fed01a_only_keys( $value, $keys ) {
    return is_array( $value ) && array() === array_diff( array_keys( $value ), $keys );
}

function fed01a_has_keys( $value, $keys ) {
    if ( ! is_array( $value ) ) {
        return false;
    }
    foreach ( $keys as $key ) {
        if ( ! array_key_exists( $key, $value ) ) {
            return false;
        }
    }
    return true;
}

function fed01a_has_remote_ref( $value ) {
    if ( ! is_array( $value ) ) {
        return false;
    }
    if ( isset( $value['$ref'] ) && ( ! is_string( $value['$ref'] ) || 0 !== strpos( $value['$ref'], '#/' ) ) ) {
        return true;
    }
    foreach ( $value as $child ) {
        if ( fed01a_has_remote_ref( $child ) ) {
            return true;
        }
    }
    return false;
}

function fed01a_is_uuid_v4( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value );
}

function fed01a_is_key( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $value );
}

function fed01a_is_semver( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*\.[0-9]+\.[0-9]+$/D', $value );
}

function fed01a_is_hash( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
}

function fed01a_is_nonce( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43,128}$/D', $value );
}

function fed01a_is_date( $value ) {
    return is_string( $value ) && 1 === preg_match( '/Z$/D', $value ) && false !== strtotime( $value );
}

function fed01a_contains_forbidden( $value ) {
    $forbidden = array( 'email', 'password', 'secret', 'session', 'wp_user_id', 'payment', 'ledger', 'table', 'sql', 'php_path' );
    if ( ! is_array( $value ) ) {
        return false;
    }
    foreach ( $value as $key => $child ) {
        if ( is_string( $key ) && in_array( strtolower( str_replace( '-', '_', $key ) ), $forbidden, true ) ) {
            return true;
        }
        if ( fed01a_contains_forbidden( $child ) ) {
            return true;
        }
    }
    return false;
}

function fed01a_canonical_request( $request, $method, $path, $raw_body ) {
    return implode( "\n", array(
        $request['protocol_version'],
        $method,
        $path,
        $request['sender']['node_id'],
        $request['recipient']['node_id'],
        $request['sender']['key_id'],
        $request['issued_at'],
        $request['expires_at'],
        $request['nonce'],
        hash( 'sha256', $raw_body ),
    ) );
}

function fed01a_canonical_response( $response, $http_status, $raw_body ) {
    return implode( "\n", array(
        $response['protocol_version'],
        (string) $http_status,
        $response['request_id'],
        $response['request_body_sha256'],
        $response['responder']['node_id'],
        $response['recipient']['node_id'],
        $response['responder']['key_id'],
        $response['generated_at'],
        $response['expires_at'],
        hash( 'sha256', $raw_body ),
    ) );
}

function fed01a_endpoint_is_valid( $method, $url, $redirect_count ) {
    if ( 'POST' !== $method || 0 !== $redirect_count ) {
        return false;
    }
    $parts = parse_url( $url );
    return is_array( $parts )
        && 'https' === ( $parts['scheme'] ?? null )
        && isset( $parts['host'] )
        && ! isset( $parts['port'] )
        && ! isset( $parts['query'] )
        && ! isset( $parts['fragment'] )
        && ! isset( $parts['user'] )
        && ! isset( $parts['pass'] )
        && '/wp-json/faluss-federation/v1/exchange' === ( $parts['path'] ?? null );
}

function fed01a_request_is_valid( $request, $headers, $raw_body, $now, &$error ) {
    $root = array( 'protocol_version', 'message_type', 'request_id', 'operation', 'sender', 'recipient', 'issued_at', 'expires_at', 'nonce', 'subject_context', 'parameters' );
    if ( ! fed01a_only_keys( $request, $root ) || ! fed01a_has_keys( $request, $root ) || fed01a_contains_forbidden( $request ) ) {
        $error = 'invalid_request_shape';
        return false;
    }
    if ( '1' !== $request['protocol_version'] || 'request' !== $request['message_type'] || ! fed01a_is_uuid_v4( $request['request_id'] ) || ! in_array( $request['operation'], array( 'diagnostic.read', 'manifest.read', 'read_model.read' ), true ) || ! fed01a_is_date( $request['issued_at'] ) || ! fed01a_is_date( $request['expires_at'] ) || ! fed01a_is_nonce( $request['nonce'] ) ) {
        $error = 'invalid_request_identity';
        return false;
    }
    if ( ! fed01a_only_keys( $request['sender'], array( 'node_id', 'app_key', 'key_id' ) ) || ! fed01a_has_keys( $request['sender'], array( 'node_id', 'app_key', 'key_id' ) ) || ! fed01a_is_key( $request['sender']['node_id'] ) || ! fed01a_is_key( $request['sender']['app_key'] ) || ! is_string( $request['sender']['key_id'] ) || ! fed01a_only_keys( $request['recipient'], array( 'node_id', 'app_key' ) ) || ! fed01a_has_keys( $request['recipient'], array( 'node_id', 'app_key' ) ) || ! fed01a_is_key( $request['recipient']['node_id'] ) || ! fed01a_is_key( $request['recipient']['app_key'] ) ) {
        $error = 'invalid_nodes';
        return false;
    }
    $issued = strtotime( $request['issued_at'] );
    $expires = strtotime( $request['expires_at'] );
    if ( $expires <= $issued || $expires - $issued > 300 || $issued > $now + 60 || $expires < $now - 60 ) {
        $error = 'invalid_time';
        return false;
    }
    if ( ! fed01a_has_keys( $headers, array( 'key_id', 'content_sha256', 'signature' ) ) || $headers['key_id'] !== $request['sender']['key_id'] || ! fed01a_is_hash( $headers['content_sha256'] ) || ! hash_equals( $headers['content_sha256'], hash( 'sha256', $raw_body ) ) || ! is_string( $headers['signature'] ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{86}$/D', $headers['signature'] ) ) {
        $error = 'invalid_signature_headers';
        return false;
    }
    if ( 'diagnostic.read' === $request['operation'] ) {
        $valid = null === $request['subject_context'] && array() === $request['parameters'];
    } elseif ( 'manifest.read' === $request['operation'] ) {
        $valid = null === $request['subject_context'] && fed01a_only_keys( $request['parameters'], array( 'app_key', 'requested_manifest_version' ) ) && fed01a_has_keys( $request['parameters'], array( 'app_key' ) ) && fed01a_is_key( $request['parameters']['app_key'] ) && ( ! array_key_exists( 'requested_manifest_version', $request['parameters'] ) || null === $request['parameters']['requested_manifest_version'] || fed01a_is_semver( $request['parameters']['requested_manifest_version'] ) );
    } else {
        $parameters = $request['parameters'];
        $valid = fed01a_only_keys( $parameters, array( 'owner_app_key', 'capability_key', 'document_type', 'contract_version', 'audience' ) ) && fed01a_has_keys( $parameters, array( 'owner_app_key', 'capability_key', 'document_type', 'contract_version', 'audience' ) ) && fed01a_is_key( $parameters['owner_app_key'] ) && is_string( $parameters['capability_key'] ) && 1 === preg_match( '/^[a-z][a-z0-9-]+(?:\.[a-z][a-z0-9_.-]+)+$/D', $parameters['capability_key'] ) && is_string( $parameters['document_type'] ) && 1 === preg_match( '/^[a-z][a-z0-9-]+(?:\.[a-z][a-z0-9-]+)+$/D', $parameters['document_type'] ) && fed01a_is_semver( $parameters['contract_version'] ) && in_array( $parameters['audience'], array( 'private', 'members', 'public' ), true ) && ( null === $request['subject_context'] || ( fed01a_only_keys( $request['subject_context'], array( 'subject_faluss_id', 'requested_audience' ) ) && fed01a_has_keys( $request['subject_context'], array( 'subject_faluss_id', 'requested_audience' ) ) && fed01a_is_uuid_v4( $request['subject_context']['subject_faluss_id'] ) && in_array( $request['subject_context']['requested_audience'], array( 'private', 'members', 'public' ), true ) ) );
    }
    if ( ! $valid ) {
        $error = 'invalid_operation_parameters';
        return false;
    }
    return true;
}

function fed01a_consume_nonce( &$seen, &$request_hashes, $request, $body_hash ) {
    $nonce_key = $request['sender']['node_id'] . "\x1F" . $request['sender']['key_id'] . "\x1F" . $request['nonce'];
    if ( isset( $seen[ $nonce_key ] ) || ( isset( $request_hashes[ $request['request_id'] ] ) && ! hash_equals( $request_hashes[ $request['request_id'] ], $body_hash ) ) ) {
        return false;
    }
    $seen[ $nonce_key ] = true;
    $request_hashes[ $request['request_id'] ] = $body_hash;
    return true;
}

function fed01a_policy_is_valid( $policy ) {
    foreach ( array( 'node_id', 'app_key', 'key_id', 'operations', 'owner_apps', 'capabilities', 'audiences', 'key_state', 'valid_until' ) as $field ) {
        if ( ! array_key_exists( $field, $policy ) ) {
            return false;
        }
    }
    if ( ! fed01a_is_key( $policy['node_id'] ) || ! fed01a_is_key( $policy['app_key'] ) || ! is_string( $policy['key_id'] ) || ! in_array( $policy['key_state'], array( 'active', 'rotating' ), true ) || ! fed01a_is_date( $policy['valid_until'] ) ) {
        return false;
    }
    foreach ( array( 'operations', 'owner_apps', 'capabilities', 'audiences' ) as $field ) {
        if ( ! is_array( $policy[ $field ] ) || empty( $policy[ $field ] ) || in_array( '*', $policy[ $field ], true ) ) {
            return false;
        }
    }
    return true;
}

function fed01a_policy_allows( $policy, $request, $now ) {
    return fed01a_policy_is_valid( $policy )
        && $policy['valid_until'] >= gmdate( 'c', $now )
        && $policy['node_id'] === $request['sender']['node_id']
        && $policy['app_key'] === $request['sender']['app_key']
        && $policy['key_id'] === $request['sender']['key_id']
        && in_array( $request['operation'], $policy['operations'], true )
        && ( 'read_model.read' !== $request['operation'] || ( in_array( $request['parameters']['owner_app_key'], $policy['owner_apps'], true ) && in_array( $request['parameters']['capability_key'], $policy['capabilities'], true ) && in_array( $request['parameters']['audience'], $policy['audiences'], true ) ) );
}

function fed01a_rotation_is_valid( $keys ) {
    if ( ! is_array( $keys ) || empty( $keys ) || count( $keys ) > 2 ) {
        return false;
    }
    $active = 0;
    foreach ( $keys as $key ) {
        if ( ! is_array( $key ) || ! isset( $key['key_id'], $key['state'] ) || ! is_string( $key['key_id'] ) || ! in_array( $key['state'], array( 'active', 'rotating' ), true ) ) {
            return false;
        }
        if ( 'active' === $key['state'] ) {
            $active++;
        }
    }
    return 1 === $active;
}

function fed01a_signature_header_is_valid( $key_id, $signature ) {
    return is_string( $key_id ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/D', $key_id )
        && is_string( $signature ) && 1 === preg_match( '/^[A-Za-z0-9_-]{86}$/D', $signature );
}

function fed01a_response_is_valid( $response, $request, $request_hash, &$error ) {
    $root = array( 'protocol_version', 'message_type', 'request_id', 'request_body_sha256', 'responder', 'recipient', 'generated_at', 'expires_at', 'status', 'payload_contract', 'payload', 'error' );
    $statuses = array( 'success', 'empty', 'not_available', 'not_authorized', 'incompatible', 'temporarily_unavailable', 'invalid_request', 'replay_rejected' );
    if ( ! fed01a_only_keys( $response, $root ) || ! fed01a_has_keys( $response, $root ) || fed01a_contains_forbidden( $response ) || '1' !== $response['protocol_version'] || 'response' !== $response['message_type'] || ! in_array( $response['status'], $statuses, true ) || $response['request_id'] !== $request['request_id'] || ! hash_equals( $response['request_body_sha256'], $request_hash ) || ! fed01a_is_date( $response['generated_at'] ) || ! fed01a_is_date( $response['expires_at'] ) || strtotime( $response['expires_at'] ) <= strtotime( $response['generated_at'] ) || strtotime( $response['expires_at'] ) - strtotime( $response['generated_at'] ) > 300 ) {
        $error = 'invalid_response_envelope';
        return false;
    }
    if ( ! fed01a_only_keys( $response['responder'], array( 'node_id', 'app_key', 'key_id' ) ) || ! fed01a_has_keys( $response['responder'], array( 'node_id', 'app_key', 'key_id' ) ) || ! fed01a_only_keys( $response['recipient'], array( 'node_id', 'app_key' ) ) || $response['recipient']['node_id'] !== $request['sender']['node_id'] || $response['recipient']['app_key'] !== $request['sender']['app_key'] ) {
        $error = 'invalid_response_routing';
        return false;
    }
    if ( 'success' === $response['status'] ) {
        $valid = is_array( $response['payload_contract'] ) && fed01a_only_keys( $response['payload_contract'], array( 'document_type', 'contract_version' ) ) && fed01a_has_keys( $response['payload_contract'], array( 'document_type', 'contract_version' ) ) && is_string( $response['payload_contract']['document_type'] ) && fed01a_is_semver( $response['payload_contract']['contract_version'] ) && is_array( $response['payload'] ) && ! empty( $response['payload'] ) && null === $response['error'];
    } elseif ( 'empty' === $response['status'] ) {
        $valid = null === $response['payload_contract'] && array() === $response['payload'] && null === $response['error'];
    } else {
        $valid = null === $response['payload_contract'] && array() === $response['payload'];
    }
    if ( ! $valid ) {
        $error = 'invalid_response_payload';
        return false;
    }
    return true;
}

function fed01a_specialized_payload_is_valid( $payload ) {
    return fed01a_only_keys( $payload, array( 'state' ) ) && 'available' === ( $payload['state'] ?? null );
}

$root = dirname( __DIR__ );
$request_schema = json_decode( file_get_contents( $root . '/contracts/faluss-federation-request.schema.json' ), true );
$response_schema = json_decode( file_get_contents( $root . '/contracts/faluss-federation-response.schema.json' ), true );
$contract = file_get_contents( $root . '/docs/FALUSS_FEDERATION_CONTRACT.md' );

fed01a_assert( is_array( $request_schema ) && is_array( $response_schema ), 'both Federation schemas parse as JSON' );
fed01a_assert( ! fed01a_has_remote_ref( $request_schema ) && ! fed01a_has_remote_ref( $response_schema ), 'schemas use only local references' );
fed01a_assert( false === $request_schema['additionalProperties'] && false === $response_schema['additionalProperties'], 'envelopes are closed' );
fed01a_assert( 3 === count( $request_schema['allOf'] ) && 3 === count( $response_schema['allOf'] ), 'operation and status branches are explicit' );
fed01a_assert( 'Ed25519' === $request_schema['x-fed01a-transport']['signature_algorithm'] && 'fail_closed' === $request_schema['x-fed01a-transport']['sodium_unavailable'], 'request schema fixes Ed25519 and Sodium fail-closed behavior' );
fed01a_assert( 'Ed25519' === $response_schema['x-fed01a-transport']['signature_algorithm'] && 'private, no-store' === $response_schema['x-fed01a-transport']['required_response_headers']['Cache-Control'], 'response schema fixes Ed25519 and private no-store responses' );
fed01a_assert( 9 === count( $request_schema['x-fed01a-scope'] ) && $request_schema['x-fed01a-scope'] === $response_schema['x-fed01a-scope'], 'both schemas declare the exact FED-01A scope' );
fed01a_assert( in_array( 'direct_table_access', $request_schema['x-fed01a-semantic-invariants']['prohibited'], true ) && in_array( 'event.publish', $request_schema['x-fed01a-semantic-invariants']['prohibited'], true ) && in_array( 'unsigned_fallback', $request_schema['x-fed01a-semantic-invariants']['prohibited'], true ), 'schema machine-readable invariants prohibit direct access, events and crypto fallback' );
foreach ( array( 'navigateur', 'HMAC, RSA, crypto maison', 'FPR, Identity ou Token Connector', 'Aucun wildcard global', 'Aucune réponse de diagnostic', 'CAP-01B', 'mode fantôme' ) as $required_contract_text ) {
    fed01a_assert( false !== strpos( $contract, $required_contract_text ), 'contract documents required boundary: ' . $required_contract_text );
}

$now = strtotime( '2030-01-01T00:00:00Z' );
$request = array(
    'protocol_version' => '1',
    'message_type' => 'request',
    'request_id' => '11111111-1111-4111-8111-111111111111',
    'operation' => 'diagnostic.read',
    'sender' => array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'key_id' => 'hub-ed25519-202601' ),
    'recipient' => array( 'node_id' => 'me-node', 'app_key' => 'faluss-me' ),
    'issued_at' => '2029-12-31T23:59:00Z',
    'expires_at' => '2030-01-01T00:04:00Z',
    'nonce' => str_repeat( 'A', 43 ),
    'subject_context' => null,
    'parameters' => array(),
);
$raw_request = json_encode( $request, JSON_UNESCAPED_SLASHES );
$headers = array( 'key_id' => $request['sender']['key_id'], 'content_sha256' => hash( 'sha256', $raw_request ), 'signature' => str_repeat( 'A', 86 ) );
$error = null;
fed01a_assert( fed01a_endpoint_is_valid( 'POST', 'https://faluss.me/wp-json/faluss-federation/v1/exchange', 0 ), 'canonical POST endpoint is accepted' );
foreach ( array( array( 'GET', 'https://faluss.me/wp-json/faluss-federation/v1/exchange', 0 ), array( 'POST', 'http://faluss.me/wp-json/faluss-federation/v1/exchange', 0 ), array( 'POST', 'https://faluss.me:8443/wp-json/faluss-federation/v1/exchange', 0 ), array( 'POST', 'https://faluss.me/wp-json/faluss-federation/v1/exchange?a=1', 0 ), array( 'POST', 'https://faluss.me/wp-json/faluss-federation/v1/exchange#x', 0 ), array( 'POST', 'https://user@faluss.me/wp-json/faluss-federation/v1/exchange', 0 ), array( 'POST', 'https://faluss.me/wp-json/faluss-federation/v1/exchange', 1 ) ) as $invalid_endpoint ) {
    fed01a_assert( ! fed01a_endpoint_is_valid( $invalid_endpoint[0], $invalid_endpoint[1], $invalid_endpoint[2] ), 'non-canonical endpoint is rejected' );
}
fed01a_assert( fed01a_request_is_valid( $request, $headers, $raw_request, $now, $error ), 'signed diagnostic request without subject is structurally valid' );
fed01a_assert( false !== strpos( fed01a_canonical_request( $request, 'POST', '/wp-json/faluss-federation/v1/exchange', $raw_request ), "\n" . hash( 'sha256', $raw_request ) ), 'canonical request binds the exact raw-body hash' );

$manifest_request = $request;
$manifest_request['request_id'] = '22222222-2222-4222-8222-222222222222';
$manifest_request['operation'] = 'manifest.read';
$manifest_request['parameters'] = array( 'app_key' => 'faluss-me', 'requested_manifest_version' => null );
$manifest_raw = json_encode( $manifest_request, JSON_UNESCAPED_SLASHES );
$manifest_headers = array( 'key_id' => $manifest_request['sender']['key_id'], 'content_sha256' => hash( 'sha256', $manifest_raw ), 'signature' => str_repeat( 'B', 86 ) );
fed01a_assert( fed01a_request_is_valid( $manifest_request, $manifest_headers, $manifest_raw, $now, $error ), 'signed manifest request has no member context' );
$manifest_without_version = $manifest_request;
unset( $manifest_without_version['parameters']['requested_manifest_version'] );
$manifest_without_version_raw = json_encode( $manifest_without_version, JSON_UNESCAPED_SLASHES );
$manifest_without_version_headers = array( 'key_id' => $manifest_without_version['sender']['key_id'], 'content_sha256' => hash( 'sha256', $manifest_without_version_raw ), 'signature' => str_repeat( 'B', 86 ) );
fed01a_assert( fed01a_request_is_valid( $manifest_without_version, $manifest_without_version_headers, $manifest_without_version_raw, $now, $error ), 'manifest version is genuinely optional' );

$read_request = $request;
$read_request['request_id'] = '33333333-3333-4333-8333-333333333333';
$read_request['operation'] = 'read_model.read';
$read_request['subject_context'] = array( 'subject_faluss_id' => '44444444-4444-4444-8444-444444444444', 'requested_audience' => 'private' );
$read_request['parameters'] = array( 'owner_app_key' => 'faluss-me', 'capability_key' => 'faluss-me.profile.summary', 'document_type' => 'profile.summary', 'contract_version' => '1.0.0', 'audience' => 'private' );
$read_raw = json_encode( $read_request, JSON_UNESCAPED_SLASHES );
$read_headers = array( 'key_id' => $read_request['sender']['key_id'], 'content_sha256' => hash( 'sha256', $read_raw ), 'signature' => str_repeat( 'C', 86 ) );
fed01a_assert( fed01a_request_is_valid( $read_request, $read_headers, $read_raw, $now, $error ), 'private read-model request has exact context and fields' );

$policy = array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub', 'key_id' => 'hub-ed25519-202601', 'operations' => array( 'read_model.read' ), 'owner_apps' => array( 'faluss-me' ), 'capabilities' => array( 'faluss-me.profile.summary' ), 'audiences' => array( 'private' ), 'key_state' => 'rotating', 'valid_until' => '2030-01-01T01:00:00Z' );
fed01a_assert( fed01a_policy_allows( $policy, $read_request, $now ), 'bounded rotating key accepts exact private permission' );
$wildcard_policy = $policy;
$wildcard_policy['capabilities'] = array( '*' );
fed01a_assert( ! fed01a_policy_is_valid( $wildcard_policy ), 'permission wildcard is rejected' );
$wrong_audience = $read_request;
$wrong_audience['parameters']['audience'] = 'public';
fed01a_assert( ! fed01a_policy_allows( $policy, $wrong_audience, $now ), 'unauthorized audience is rejected' );
$revoked_policy = $policy;
$revoked_policy['key_state'] = 'revoked';
fed01a_assert( ! fed01a_policy_is_valid( $revoked_policy ), 'revoked key is rejected immediately' );
$expired_policy = $policy;
$expired_policy['valid_until'] = '2029-12-31T23:00:00Z';
fed01a_assert( ! fed01a_policy_allows( $expired_policy, $read_request, $now ), 'expired key policy is rejected' );
$unknown_key_policy = $policy;
$unknown_key_policy['key_id'] = 'unknown-ed25519-202601';
fed01a_assert( ! fed01a_policy_allows( $unknown_key_policy, $read_request, $now ), 'unknown key policy cannot authorize the sender' );
fed01a_assert( fed01a_rotation_is_valid( array( array( 'key_id' => 'hub-ed25519-202512', 'state' => 'rotating' ), array( 'key_id' => 'hub-ed25519-202601', 'state' => 'active' ) ) ), 'bounded old and new key rotation is accepted' );
fed01a_assert( ! fed01a_rotation_is_valid( array( array( 'key_id' => 'hub-ed25519-202511', 'state' => 'rotating' ), array( 'key_id' => 'hub-ed25519-202512', 'state' => 'rotating' ), array( 'key_id' => 'hub-ed25519-202601', 'state' => 'active' ) ) ), 'rotation beyond old plus new is rejected' );

$seen = array();
$request_hashes = array();
fed01a_assert( fed01a_consume_nonce( $seen, $request_hashes, $read_request, hash( 'sha256', $read_raw ) ), 'nonce is consumed once after verification boundary' );
fed01a_assert( ! fed01a_consume_nonce( $seen, $request_hashes, $read_request, hash( 'sha256', $read_raw ) ), 'replayed nonce is rejected' );
$same_request_id_other_body = $read_request;
$same_request_id_other_body['nonce'] = str_repeat( 'D', 43 );
fed01a_assert( ! fed01a_consume_nonce( $seen, $request_hashes, $same_request_id_other_body, str_repeat( 'd', 64 ) ), 'request id cannot bind a different body hash' );

$invalid = $request;
$invalid['parameters'] = array( 'email' => 'not-allowed@example.test' );
$invalid_raw = json_encode( $invalid );
$invalid_headers = $headers;
$invalid_headers['content_sha256'] = hash( 'sha256', $invalid_raw );
fed01a_assert( ! fed01a_request_is_valid( $invalid, $invalid_headers, $invalid_raw, $now, $error ), 'personal data and invalid diagnostic parameters are rejected' );
$invalid = $request;
$invalid['expires_at'] = '2030-01-01T00:10:00Z';
$invalid_raw = json_encode( $invalid );
$invalid_headers = $headers;
$invalid_headers['content_sha256'] = hash( 'sha256', $invalid_raw );
fed01a_assert( ! fed01a_request_is_valid( $invalid, $invalid_headers, $invalid_raw, $now, $error ), 'request longer than five minutes is rejected' );
$invalid = $request;
$invalid['issued_at'] = '2030-01-01T00:02:00Z';
$invalid['expires_at'] = '2030-01-01T00:03:00Z';
$invalid_raw = json_encode( $invalid );
$invalid_headers = $headers;
$invalid_headers['content_sha256'] = hash( 'sha256', $invalid_raw );
fed01a_assert( ! fed01a_request_is_valid( $invalid, $invalid_headers, $invalid_raw, $now, $error ), 'future request beyond clock skew is rejected' );
$altered_headers = $headers;
$altered_headers['content_sha256'] = str_repeat( '0', 64 );
fed01a_assert( ! fed01a_request_is_valid( $request, $altered_headers, $raw_request, $now, $error ), 'altered body hash is rejected' );
$altered_headers = $headers;
$altered_headers['key_id'] = 'other-ed25519-202601';
fed01a_assert( ! fed01a_request_is_valid( $request, $altered_headers, $raw_request, $now, $error ), 'header and sender key mismatch is rejected' );
$altered_headers = $headers;
$altered_headers['signature'] = '';
fed01a_assert( ! fed01a_request_is_valid( $request, $altered_headers, $raw_request, $now, $error ), 'unsigned request is rejected' );
$invalid = $request;
$invalid['operation'] = 'event.publish';
$invalid_raw = json_encode( $invalid );
$invalid_headers = $headers;
$invalid_headers['content_sha256'] = hash( 'sha256', $invalid_raw );
fed01a_assert( ! fed01a_request_is_valid( $invalid, $invalid_headers, $invalid_raw, $now, $error ), 'prohibited event publish is rejected' );
$invalid = $manifest_request;
$invalid['parameters']['asset_bytes'] = 'binary-not-allowed';
$invalid_raw = json_encode( $invalid );
$invalid_headers = $manifest_headers;
$invalid_headers['content_sha256'] = hash( 'sha256', $invalid_raw );
fed01a_assert( ! fed01a_request_is_valid( $invalid, $invalid_headers, $invalid_raw, $now, $error ), 'manifest request cannot ask for binary assets' );

$response = array(
    'protocol_version' => '1',
    'message_type' => 'response',
    'request_id' => $read_request['request_id'],
    'request_body_sha256' => hash( 'sha256', $read_raw ),
    'responder' => array( 'node_id' => 'me-node', 'app_key' => 'faluss-me', 'key_id' => 'me-ed25519-202601' ),
    'recipient' => array( 'node_id' => 'hub-node', 'app_key' => 'faluss-hub' ),
    'generated_at' => '2030-01-01T00:00:00Z',
    'expires_at' => '2030-01-01T00:05:00Z',
    'status' => 'success',
    'payload_contract' => array( 'document_type' => 'profile.summary', 'contract_version' => '1.0.0' ),
    'payload' => array( 'state' => 'available' ),
    'error' => null,
);
fed01a_assert( fed01a_response_is_valid( $response, $read_request, hash( 'sha256', $read_raw ), $error ), 'success response is bound to request and hash' );
fed01a_assert( fed01a_specialized_payload_is_valid( $response['payload'] ), 'signed payload still passes its specialised contract' );
fed01a_assert( fed01a_signature_header_is_valid( $response['responder']['key_id'], str_repeat( 'D', 86 ) ), 'response signature header has the required base64url shape' );
fed01a_assert( ! fed01a_signature_header_is_valid( $response['responder']['key_id'], '' ), 'unsigned response is rejected' );
fed01a_assert( false !== strpos( fed01a_canonical_response( $response, 200, json_encode( $response ) ), "\n" . hash( 'sha256', json_encode( $response ) ) ), 'canonical response binds exact raw response hash' );
$empty_response = $response;
$empty_response['status'] = 'empty';
$empty_response['payload_contract'] = null;
$empty_response['payload'] = array();
fed01a_assert( fed01a_response_is_valid( $empty_response, $read_request, hash( 'sha256', $read_raw ), $error ), 'empty response contains no invented payload' );
$invalid_response = $response;
$invalid_response['request_id'] = '55555555-5555-4555-8555-555555555555';
fed01a_assert( ! fed01a_response_is_valid( $invalid_response, $read_request, hash( 'sha256', $read_raw ), $error ), 'response for another request is rejected' );
$invalid_response = $response;
$invalid_response['recipient']['node_id'] = 'other-node';
fed01a_assert( ! fed01a_response_is_valid( $invalid_response, $read_request, hash( 'sha256', $read_raw ), $error ), 'response for another recipient is rejected' );
$invalid_response = $response;
$invalid_response['payload'] = array( 'state' => 'unexpected' );
fed01a_assert( ! fed01a_specialized_payload_is_valid( $invalid_response['payload'] ), 'invalid specialised payload is rejected despite envelope shape' );

if ( function_exists( 'sodium_crypto_sign_keypair' ) ) {
    $keypair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey( $keypair );
    $public = sodium_crypto_sign_publickey( $keypair );
    $message = fed01a_canonical_request( $request, 'POST', '/wp-json/faluss-federation/v1/exchange', $raw_request );
    $signature = sodium_crypto_sign_detached( $message, $secret );
    fed01a_assert( sodium_crypto_sign_verify_detached( $signature, $message, $public ), 'ephemeral Ed25519 signature verifies' );
    fed01a_assert( ! sodium_crypto_sign_verify_detached( $signature, $message . 'x', $public ), 'altered signed message is rejected' );
} else {
    fed01a_assert( 'fail_closed' === $request_schema['x-fed01a-transport']['sodium_unavailable'], 'Sodium absence has only fail-closed behavior' );
    fwrite( STDOUT, 'Sodium unavailable: Ed25519 runtime test skipped; fail-closed contract asserted.' . PHP_EOL );
}

fwrite( STDOUT, 'FED-01A contract tests: OK (' . $fed01a_assertions . ' assertions).' . PHP_EOL );
