<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Contract-only runtime boundary. CAP-01B.2 will own registry resolution. */
final class Faluss_Apps_Registry {
    const DOCUMENT_TYPE = 'faluss.app-capability-manifest';
    const CONTRACT_VERSION = '1.0.0';

    public static function boot() {
        add_action( 'plugins_loaded', array( __CLASS__, 'register_federation_validator' ), 20 );
        if ( did_action( 'plugins_loaded' ) ) {
            self::register_federation_validator();
        }
    }

    /** @return true|false|WP_Error */
    public static function register_federation_validator() {
        if ( ! class_exists( 'Faluss_Federation_Providers' ) || ! class_exists( 'Faluss_Federation_Crypto' ) || ! method_exists( 'Faluss_Federation_Providers', 'register_manifest_contract_validator' ) || ! Faluss_Federation_Crypto::transport_ready() ) {
            return false;
        }
        return Faluss_Federation_Providers::register_manifest_contract_validator( self::DOCUMENT_TYPE, self::CONTRACT_VERSION, array( 'Faluss_Apps_Registry_Manifest_Validator', 'validate' ) );
    }

    public static function manifest_contract() {
        return array( 'document_type' => self::DOCUMENT_TYPE, 'contract_version' => self::CONTRACT_VERSION );
    }

    public static function validate_manifest( $manifest, $payload_contract, $request_context ) {
        return Faluss_Apps_Registry_Manifest_Validator::validate( $manifest, $payload_contract, $request_context );
    }
}
