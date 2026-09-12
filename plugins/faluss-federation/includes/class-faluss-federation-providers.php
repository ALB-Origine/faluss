<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Closed provider registry. FED-01B ships only the diagnostic producer. */
final class Faluss_Federation_Providers {
    private static $manifest_providers = array();
    private static $read_model_providers = array();

    public static function boot() {}

    /** Administrative capability state; registration remains closed to trusted PHP. */
    public static function operation_availability() {
        return array(
            'diagnostic.read' => 'integrated',
            'manifest.read' => empty( self::$manifest_providers ) ? 'not_available' : 'registered',
            'read_model.read' => empty( self::$read_model_providers ) ? 'not_available' : 'registered',
        );
    }

    /** Trusted server PHP may register one exact manifest producer and validator. */
    public static function register_manifest_provider( $app_key, $provider, $validator ) {
        if ( ! Faluss_Federation_Crypto::is_node( $app_key ) || ! is_callable( $provider ) || ! is_callable( $validator ) || isset( self::$manifest_providers[ $app_key ] ) ) {
            return new WP_Error( 'faluss_federation_provider_refused' );
        }
        self::$manifest_providers[ $app_key ] = array( 'provider' => $provider, 'validator' => $validator );
        return true;
    }

    /** Descriptor is an exact tuple, never a wildcard or arbitrary RPC method. */
    public static function register_read_model_provider( $descriptor, $provider, $validator ) {
        if ( ! is_array( $descriptor ) || ! is_callable( $provider ) || ! is_callable( $validator ) || ! self::valid_descriptor( $descriptor ) ) {
            return new WP_Error( 'faluss_federation_provider_refused' );
        }
        $key = self::descriptor_key( $descriptor );
        if ( isset( self::$read_model_providers[ $key ] ) ) {
            return new WP_Error( 'faluss_federation_provider_refused' );
        }
        self::$read_model_providers[ $key ] = array( 'descriptor' => $descriptor, 'provider' => $provider, 'validator' => $validator );
        return true;
    }

    public static function descriptor_allows( $request ) {
        if ( ! is_array( $request ) || 'read_model.read' !== ( $request['operation'] ?? null ) ) {
            return false;
        }
        $key = self::descriptor_key_from_parameters( $request['parameters'] ?? array() );
        if ( null === $key || empty( self::$read_model_providers[ $key ] ) ) {
            return false;
        }
        $descriptor = self::$read_model_providers[ $key ]['descriptor'];
        return in_array( $request['parameters']['audience'] ?? '', $descriptor['audiences'], true );
    }

    /** Whether a trusted producer exists for the exact read-model descriptor. */
    public static function has_read_model_provider( $request ) {
        if ( ! is_array( $request ) || 'read_model.read' !== ( $request['operation'] ?? null ) ) {
            return false;
        }
        $key = self::descriptor_key_from_parameters( $request['parameters'] ?? array() );
        return null !== $key && ! empty( self::$read_model_providers[ $key ] );
    }

    /** @return array<string,mixed> */
    public static function dispatch( $request, $identity ) {
        if ( ! is_array( $request ) || ! is_array( $identity ) ) {
            return self::failure( 'invalid_request' );
        }
        if ( 'diagnostic.read' === $request['operation'] ) {
            return array(
                'status' => 'success',
                'payload_contract' => array( 'document_type' => 'federation.diagnostic', 'contract_version' => '1.0.0' ),
                'payload' => array( 'protocol_version' => '1', 'node_id' => $identity['node_id'], 'app_key' => $identity['app_key'], 'key_id' => $identity['key_id'], 'clock_utc' => gmdate( 'c' ), 'operations' => array( 'diagnostic.read' ) ),
                'error' => null,
            );
        }
        if ( 'manifest.read' === $request['operation'] ) {
            $app_key = $request['parameters']['app_key'] ?? '';
            if ( empty( self::$manifest_providers[ $app_key ] ) ) {
                return self::failure( 'not_available' );
            }
            return self::dispatch_manifest( self::$manifest_providers[ $app_key ], $request );
        }
        if ( 'read_model.read' === $request['operation'] ) {
            $key = self::descriptor_key_from_parameters( $request['parameters'] ?? array() );
            if ( null === $key || empty( self::$read_model_providers[ $key ] ) ) {
                return self::failure( 'not_available' );
            }
            return self::dispatch_read_model( self::$read_model_providers[ $key ], $request );
        }
        return self::failure( 'invalid_request' );
    }

    public static function validate_received_payload( $response, $request ) {
        if ( ! is_array( $response ) || ! is_array( $request ) || 'success' !== ( $response['status'] ?? null ) || ! is_array( $response['payload_contract'] ?? null ) || ! is_array( $response['payload'] ?? null ) ) {
            return false;
        }
        if ( 'diagnostic.read' === ( $request['operation'] ?? null ) ) {
            return self::valid_diagnostic( $response['payload_contract'], $response['payload'] );
        }
        if ( 'manifest.read' === ( $request['operation'] ?? null ) ) {
            $entry = self::$manifest_providers[ $request['parameters']['app_key'] ?? '' ] ?? null;
            return is_array( $entry ) && call_user_func( $entry['validator'], $response['payload'], $response['payload_contract'] );
        }
        $key = self::descriptor_key_from_parameters( $request['parameters'] ?? array() );
        $entry = null !== $key ? ( self::$read_model_providers[ $key ] ?? null ) : null;
        return is_array( $entry ) && call_user_func( $entry['validator'], $response['payload'], $response['payload_contract'] );
    }

    private static function dispatch_manifest( $entry, $request ) {
        try {
            $result = call_user_func( $entry['provider'], self::safe_context( $request ) );
        } catch ( Exception $exception ) {
            return self::failure( 'temporarily_unavailable' );
        }
        if ( ! is_array( $result ) || ! isset( $result['payload'], $result['payload_contract'] ) || ! is_array( $result['payload'] ) || ! is_array( $result['payload_contract'] ) || ! call_user_func( $entry['validator'], $result['payload'], $result['payload_contract'] ) ) {
            return self::failure( 'incompatible' );
        }
        $requested = $request['parameters']['requested_manifest_version'] ?? null;
        if ( null !== $requested && $requested !== ( $result['payload_contract']['contract_version'] ?? null ) ) {
            return self::failure( 'incompatible' );
        }
        return array( 'status' => 'success', 'payload_contract' => $result['payload_contract'], 'payload' => $result['payload'], 'error' => null );
    }

    private static function dispatch_read_model( $entry, $request ) {
        try {
            $result = call_user_func( $entry['provider'], self::safe_context( $request ) );
        } catch ( Exception $exception ) {
            return self::failure( 'temporarily_unavailable' );
        }
        $parameters = $request['parameters'];
        if ( ! is_array( $result ) || ! isset( $result['payload'], $result['payload_contract'] ) || ! is_array( $result['payload'] ) || ! is_array( $result['payload_contract'] ) || $parameters['document_type'] !== ( $result['payload_contract']['document_type'] ?? null ) || $parameters['contract_version'] !== ( $result['payload_contract']['contract_version'] ?? null ) || ! call_user_func( $entry['validator'], $result['payload'], $result['payload_contract'] ) ) {
            return self::failure( 'incompatible' );
        }
        return array( 'status' => 'success', 'payload_contract' => $result['payload_contract'], 'payload' => $result['payload'], 'error' => null );
    }

    private static function safe_context( $request ) {
        return array( 'operation' => $request['operation'], 'parameters' => $request['parameters'], 'subject_context' => $request['subject_context'], 'sender' => $request['sender'], 'recipient' => $request['recipient'] );
    }

    private static function valid_diagnostic( $contract, $payload ) {
        return is_array( $contract ) && 'federation.diagnostic' === ( $contract['document_type'] ?? null ) && '1.0.0' === ( $contract['contract_version'] ?? null ) && is_array( $payload ) && array( 'protocol_version', 'node_id', 'app_key', 'key_id', 'clock_utc', 'operations' ) === array_keys( $payload ) && '1' === $payload['protocol_version'] && Faluss_Federation_Crypto::is_node( $payload['node_id'] ) && Faluss_Federation_Crypto::is_node( $payload['app_key'] ) && Faluss_Federation_Crypto::is_key_id( $payload['key_id'] ) && is_string( $payload['clock_utc'] ) && in_array( 'diagnostic.read', $payload['operations'], true );
    }

    private static function failure( $status ) {
        return array( 'status' => $status, 'payload_contract' => null, 'payload' => array(), 'error' => array( 'code' => $status, 'message' => 'Request could not be completed.' ) );
    }

    private static function valid_descriptor( $descriptor ) {
        $required = array( 'owner_app_key', 'capability_key', 'document_type', 'contract_version', 'audiences' );
        if ( array_diff( $required, array_keys( $descriptor ) ) || array_diff( array_keys( $descriptor ), $required ) || ! Faluss_Federation_Crypto::is_node( $descriptor['owner_app_key'] ) || ! is_string( $descriptor['capability_key'] ) || ! is_string( $descriptor['document_type'] ) || ! Faluss_Federation_Crypto::is_semver( $descriptor['contract_version'] ) || ! is_array( $descriptor['audiences'] ) || empty( $descriptor['audiences'] ) ) {
            return false;
        }
        foreach ( $descriptor['audiences'] as $audience ) {
            if ( ! in_array( $audience, Faluss_Federation_Policy::audiences(), true ) ) {
                return false;
            }
        }
        return 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9_.-]{1,127})+$/D', $descriptor['capability_key'] ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9-]{1,63})+$/D', $descriptor['document_type'] );
    }

    private static function descriptor_key( $descriptor ) {
        return implode( "\x1F", array( $descriptor['owner_app_key'], $descriptor['capability_key'], $descriptor['document_type'], $descriptor['contract_version'] ) );
    }

    private static function descriptor_key_from_parameters( $parameters ) {
        if ( ! is_array( $parameters ) || ! isset( $parameters['owner_app_key'], $parameters['capability_key'], $parameters['document_type'], $parameters['contract_version'] ) ) {
            return null;
        }
        return implode( "\x1F", array( $parameters['owner_app_key'], $parameters['capability_key'], $parameters['document_type'], $parameters['contract_version'] ) );
    }
}
