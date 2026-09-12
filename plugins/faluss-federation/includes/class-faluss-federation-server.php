<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** The single private receiver. It reads the raw signed body exactly once. */
final class Faluss_Federation_Server {
    const NAMESPACE = 'faluss-federation/v1';
    const ROUTE = '/exchange';
    const MAX_BODY = 65536;

    public static function boot() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
        add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_pre_serialized' ), 10, 4 );
    }

    public static function register_route() {
        if ( ! Faluss_Federation_Crypto::transport_ready() ) {
            return;
        }
        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'handle' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public static function handle( $request ) {
        $started = microtime( true );
        if ( ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() || ! is_ssl() || ! empty( $request->get_query_params() ) || ! self::content_type_is_json( $request ) || ! self::content_encoding_is_identity( $request ) ) {
            return self::pre_auth_reject();
        }
        $raw_body = $request->get_body();
        if ( ! is_string( $raw_body ) || '' === $raw_body || strlen( $raw_body ) > self::MAX_BODY ) {
            return self::pre_auth_reject();
        }
        $headers = self::request_headers( $request );
        if ( is_wp_error( $headers ) ) {
            return self::pre_auth_reject();
        }
        $body_hash = hash( 'sha256', $raw_body );
        if ( ! hash_equals( $headers['X-Faluss-Federation-Content-SHA256'], $body_hash ) ) {
            return self::pre_auth_reject();
        }
        try {
            $message = json_decode( $raw_body, true, 16, JSON_THROW_ON_ERROR );
        } catch ( Exception $exception ) {
            return self::pre_auth_reject();
        }
        if ( ! self::valid_request( $message ) ) {
            return self::pre_auth_reject();
        }
        $identity = Faluss_Federation_Crypto::local_identity();
        if ( is_wp_error( $identity ) || $headers['X-Faluss-Federation-Key-Id'] !== $message['sender']['key_id'] ) {
            return self::pre_auth_reject();
        }
        $peer = Faluss_Federation_Policy::find_peer( $message['sender']['node_id'], $message['sender']['app_key'], $message['sender']['key_id'] );
        $canonical = Faluss_Federation_Crypto::request_canonical( $message, $raw_body );
        if ( is_wp_error( $peer ) || is_wp_error( $canonical ) || ! Faluss_Federation_Crypto::verify( $canonical, $headers['X-Faluss-Federation-Signature'], $peer['public_key'] ) || ! self::request_is_fresh( $message ) ) {
            return self::pre_auth_reject();
        }
        $allowed = Faluss_Federation_Policy::allow_incoming( $message, $peer, $identity );
        if ( is_wp_error( $allowed ) ) {
            return self::authenticated_response( self::failure_status( $allowed ), $message, $body_hash, $identity, $started );
        }
        $consumed = Faluss_Federation_Schema::consume_replay_and_limit( $message, $body_hash, self::rate_limit( $message['operation'] ) );
        if ( is_wp_error( $consumed ) ) {
            return self::authenticated_response( 'faluss_federation_rate_limited' === $consumed->get_error_code() ? 'temporarily_unavailable' : 'replay_rejected', $message, $body_hash, $identity, $started );
        }
        $result = Faluss_Federation_Providers::dispatch( $message, $identity );
        return self::authenticated_response( $result, $message, $body_hash, $identity, $started );
    }

    /** Emits pre-serialized Federation JSON only for the exact Federation route. */
    public static function serve_pre_serialized( $served, $result, $request, $server ) {
        if ( ! $request instanceof WP_REST_Request || '/' . self::NAMESPACE . self::ROUTE !== $request->get_route() || ! $result instanceof WP_REST_Response ) {
            return $served;
        }
        $data = $result->get_data();
        if ( ! is_array( $data ) || ! isset( $data['_faluss_federation_raw_json'] ) || ! is_string( $data['_faluss_federation_raw_json'] ) ) {
            return $served;
        }
        foreach ( $result->get_headers() as $name => $value ) {
            header( $name . ': ' . $value, true );
        }
        status_header( $result->get_status() );
        echo $data['_faluss_federation_raw_json']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- signed JSON bytes must not be re-encoded.
        return true;
    }

    private static function authenticated_response( $result, $request, $request_body_hash, $identity, $started ) {
        if ( is_string( $result ) ) {
            $result = self::failure( $result );
        }
        if ( ! is_array( $result ) || ! isset( $result['status'], $result['payload_contract'], $result['payload'], $result['error'] ) || ! self::valid_provider_result( $result, $request ) ) {
            $result = self::failure( 'incompatible' );
        }
        $status = $result['status'];
        $http_status = self::http_status( $status );
        $response = array(
            'protocol_version' => '1',
            'message_type' => 'response',
            'request_id' => $request['request_id'],
            'request_body_sha256' => $request_body_hash,
            'responder' => array( 'node_id' => $identity['node_id'], 'app_key' => $identity['app_key'], 'key_id' => $identity['key_id'] ),
            'recipient' => array( 'node_id' => $request['sender']['node_id'], 'app_key' => $request['sender']['app_key'] ),
            'generated_at' => gmdate( 'c' ),
            'expires_at' => gmdate( 'c', time() + 300 ),
            'status' => $status,
            'payload_contract' => $result['payload_contract'],
            'payload' => $result['payload'],
            'error' => $result['error'],
        );
        $raw = wp_json_encode( $response, JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $raw ) || strlen( $raw ) > self::MAX_BODY ) {
            $status = 'temporarily_unavailable';
            $http_status = self::http_status( $status );
            $response['status'] = $status;
            $response['payload_contract'] = null;
            $response['payload'] = array();
            $response['error'] = array( 'code' => $status, 'message' => 'Request could not be completed.' );
            $raw = wp_json_encode( $response, JSON_UNESCAPED_SLASHES );
        }
        $canonical = Faluss_Federation_Crypto::response_canonical( $response, $http_status, $raw );
        $signature = is_wp_error( $canonical ) ? new WP_Error( 'faluss_federation_fail_closed' ) : Faluss_Federation_Crypto::sign( $canonical );
        if ( ! is_string( $raw ) || is_wp_error( $signature ) ) {
            return self::pre_auth_reject();
        }
        Faluss_Federation_Schema::audit( array( 'request_id' => $request['request_id'], 'sender_node_id' => $request['sender']['node_id'], 'recipient_node_id' => $identity['node_id'], 'operation_name' => $request['operation'], 'capability_key' => $request['parameters']['capability_key'] ?? null, 'result_code' => $status, 'opaque_code' => 'receiver', 'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ) ) );
        $response_object = new WP_REST_Response( array( '_faluss_federation_raw_json' => $raw ), $http_status );
        $response_object->header( 'Content-Type', 'application/json' );
        $response_object->header( 'Cache-Control', 'private, no-store' );
        $response_object->header( 'X-Content-Type-Options', 'nosniff' );
        $response_object->header( 'X-Faluss-Federation-Key-Id', $identity['key_id'] );
        $response_object->header( 'X-Faluss-Federation-Content-SHA256', hash( 'sha256', $raw ) );
        $response_object->header( 'X-Faluss-Federation-Signature', $signature );
        return $response_object;
    }

    private static function pre_auth_reject() {
        return new WP_REST_Response( array( 'code' => 'invalid_request', 'message' => 'Request rejected.' ), 400 );
    }

    private static function request_headers( $request ) {
        $raw_headers = $request->get_headers();
        $wanted = array( 'x-faluss-federation-key-id' => 'X-Faluss-Federation-Key-Id', 'x-faluss-federation-content-sha256' => 'X-Faluss-Federation-Content-SHA256', 'x-faluss-federation-signature' => 'X-Faluss-Federation-Signature' );
        $out = array();
        foreach ( $wanted as $lower => $canonical ) {
            $values = $raw_headers[ $lower ] ?? null;
            if ( ! is_array( $values ) || 1 !== count( $values ) || ! is_string( $values[0] ) || '' === $values[0] ) {
                return new WP_Error( 'faluss_federation_invalid_headers' );
            }
            $out[ $canonical ] = $values[0];
        }
        return Faluss_Federation_Crypto::is_key_id( $out['X-Faluss-Federation-Key-Id'] ) && Faluss_Federation_Crypto::is_sha256( $out['X-Faluss-Federation-Content-SHA256'] ) && Faluss_Federation_Crypto::is_signature( $out['X-Faluss-Federation-Signature'] ) ? $out : new WP_Error( 'faluss_federation_invalid_headers' );
    }

    private static function content_type_is_json( $request ) {
        return 'application/json' === strtolower( trim( (string) $request->get_header( 'content-type' ) ) );
    }

    private static function content_encoding_is_identity( $request ) {
        $encoding = strtolower( trim( (string) $request->get_header( 'content-encoding' ) ) );
        return '' === $encoding || 'identity' === $encoding;
    }

    private static function valid_request( $request ) {
        $keys = array( 'protocol_version', 'message_type', 'request_id', 'operation', 'sender', 'recipient', 'issued_at', 'expires_at', 'nonce', 'subject_context', 'parameters' );
        if ( ! is_array( $request ) || array_diff( $keys, array_keys( $request ) ) || array_diff( array_keys( $request ), $keys ) || ! self::bounded_value( $request, 0 ) || '1' !== $request['protocol_version'] || 'request' !== $request['message_type'] || ! Faluss_Federation_Crypto::is_uuid( $request['request_id'] ) || ! in_array( $request['operation'], Faluss_Federation_Policy::operations(), true ) || ! Faluss_Federation_Crypto::is_nonce( $request['nonce'] ) || ! self::valid_date( $request['issued_at'] ) || ! self::valid_date( $request['expires_at'] ) || ! self::valid_sender( $request['sender'] ) || ! self::valid_recipient( $request['recipient'] ) ) {
            return false;
        }
        if ( 'diagnostic.read' === $request['operation'] ) {
            return null === $request['subject_context'] && array() === $request['parameters'];
        }
        if ( 'manifest.read' === $request['operation'] ) {
            return null === $request['subject_context'] && self::only_keys( $request['parameters'], array( 'app_key', 'requested_manifest_version' ) ) && isset( $request['parameters']['app_key'] ) && Faluss_Federation_Crypto::is_node( $request['parameters']['app_key'] ) && ( ! isset( $request['parameters']['requested_manifest_version'] ) || null === $request['parameters']['requested_manifest_version'] || Faluss_Federation_Crypto::is_semver( $request['parameters']['requested_manifest_version'] ) );
        }
        $parameters = $request['parameters'];
        $subject = $request['subject_context'];
        return self::only_keys( $parameters, array( 'owner_app_key', 'capability_key', 'document_type', 'contract_version', 'audience' ) ) && self::has_keys( $parameters, array( 'owner_app_key', 'capability_key', 'document_type', 'contract_version', 'audience' ) ) && Faluss_Federation_Crypto::is_node( $parameters['owner_app_key'] ) && is_string( $parameters['capability_key'] ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9_.-]{1,127})+$/D', $parameters['capability_key'] ) && is_string( $parameters['document_type'] ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9-]{1,63})+$/D', $parameters['document_type'] ) && Faluss_Federation_Crypto::is_semver( $parameters['contract_version'] ) && in_array( $parameters['audience'], Faluss_Federation_Policy::audiences(), true ) && ( null === $subject || ( self::only_keys( $subject, array( 'subject_faluss_id' ) ) && Faluss_Federation_Crypto::is_uuid( $subject['subject_faluss_id'] ?? '' ) ) );
    }

    private static function request_is_fresh( $request ) {
        $issued = strtotime( $request['issued_at'] );
        $expires = strtotime( $request['expires_at'] );
        $now = time();
        return false !== $issued && false !== $expires && $expires > $issued && $expires - $issued <= 300 && $issued <= $now + 60 && $expires >= $now - 60;
    }

    private static function valid_provider_result( $result, $request ) {
        $failures = array( 'not_available', 'not_authorized', 'incompatible', 'temporarily_unavailable', 'invalid_request', 'replay_rejected' );
        if ( 'success' === $result['status'] ) {
            return is_array( $result['payload_contract'] ) && is_array( $result['payload'] ) && ! empty( $result['payload'] ) && null === $result['error'] && self::bounded_value( $result['payload_contract'], 0 ) && self::bounded_value( $result['payload'], 0 );
        }
        if ( 'empty' === $result['status'] ) {
            return null === $result['payload_contract'] && array() === $result['payload'] && null === $result['error'];
        }
        return in_array( $result['status'], $failures, true ) && null === $result['payload_contract'] && array() === $result['payload'] && self::error_shape( $result['error'], $result['status'] );
    }

    private static function failure( $status ) { return array( 'status' => $status, 'payload_contract' => null, 'payload' => array(), 'error' => array( 'code' => $status, 'message' => 'Request could not be completed.' ) ); }
    private static function failure_status( $error ) { return 'faluss_federation_incompatible' === $error->get_error_code() ? 'incompatible' : ( 'faluss_federation_invalid_request' === $error->get_error_code() ? 'invalid_request' : 'not_authorized' ); }
    private static function http_status( $status ) { $map = array( 'success' => 200, 'empty' => 200, 'not_available' => 404, 'not_authorized' => 403, 'incompatible' => 409, 'temporarily_unavailable' => 503, 'invalid_request' => 400, 'replay_rejected' => 409 ); return $map[ $status ] ?? 400; }
    private static function rate_limit( $operation ) { return 'diagnostic.read' === $operation ? 30 : ( 'manifest.read' === $operation ? 60 : 600 ); }
    private static function valid_date( $value ) { return is_string( $value ) && strlen( $value ) <= 32 && 1 === preg_match( '/Z$/D', $value ) && false !== strtotime( $value ); }
    private static function valid_sender( $value ) { return self::only_keys( $value, array( 'node_id', 'app_key', 'key_id' ) ) && self::has_keys( $value, array( 'node_id', 'app_key', 'key_id' ) ) && Faluss_Federation_Crypto::is_node( $value['node_id'] ) && Faluss_Federation_Crypto::is_node( $value['app_key'] ) && Faluss_Federation_Crypto::is_key_id( $value['key_id'] ); }
    private static function valid_recipient( $value ) { return self::only_keys( $value, array( 'node_id', 'app_key' ) ) && self::has_keys( $value, array( 'node_id', 'app_key' ) ) && Faluss_Federation_Crypto::is_node( $value['node_id'] ) && Faluss_Federation_Crypto::is_node( $value['app_key'] ); }
    private static function only_keys( $value, $keys ) { return is_array( $value ) && ! array_diff( array_keys( $value ), $keys ); }
    private static function has_keys( $value, $keys ) { foreach ( $keys as $key ) { if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) { return false; } } return true; }
    private static function bounded_value( $value, $depth ) { if ( $depth > 16 ) { return false; } if ( is_string( $value ) ) { return strlen( $value ) <= 4096; } if ( ! is_array( $value ) ) { return is_null( $value ) || is_bool( $value ) || is_int( $value ) || is_float( $value ); } if ( count( $value ) > 128 ) { return false; } foreach ( $value as $child ) { if ( ! self::bounded_value( $child, $depth + 1 ) ) { return false; } } return true; }
    private static function error_shape( $error, $status ) { return is_array( $error ) && array( 'code', 'message' ) === array_keys( $error ) && $status === $error['code'] && is_string( $error['message'] ) && '' !== $error['message'] && strlen( $error['message'] ) <= 160 && false === strpos( $error['message'], "\r" ) && false === strpos( $error['message'], "\n" ) && 1 !== preg_match( '/faluss_id|secret|session|payment|@/i', $error['message'] ); }
}
