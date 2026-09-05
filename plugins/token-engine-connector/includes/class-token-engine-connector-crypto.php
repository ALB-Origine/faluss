<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Encrypts the connector secret at rest; it is never rendered back into administration. */
final class Token_Engine_Connector_Crypto {
    public static function encrypt( $secret ) {
        if ( ! is_string( $secret ) || '' === $secret ) { return new WP_Error( 'connector_secret_invalid' ); }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        try {
            if ( function_exists( 'sodium_crypto_secretbox' ) ) {
                $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
                return 'sodium:' . base64_encode( $nonce . sodium_crypto_secretbox( $secret, $nonce, $key ) );
            }
            if ( function_exists( 'openssl_encrypt' ) ) {
                $iv = random_bytes( 12 ); $tag = '';
                $ciphertext = openssl_encrypt( $secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
                return false === $ciphertext ? new WP_Error( 'connector_secret_protection_failed' ) : 'openssl:' . base64_encode( $iv . $tag . $ciphertext );
            }
        } catch ( Exception $exception ) {
            return new WP_Error( 'connector_secret_protection_failed' );
        }
        return new WP_Error( 'connector_secret_protection_unavailable' );
    }

    public static function decrypt( $stored ) {
        if ( ! is_string( $stored ) || '' === $stored ) { return new WP_Error( 'connector_secret_missing' ); }
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        if ( 0 === strpos( $stored, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
            $payload = base64_decode( substr( $stored, 7 ), true );
            if ( false === $payload || strlen( $payload ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) { return new WP_Error( 'connector_secret_unavailable' ); }
            $secret = sodium_crypto_secretbox_open( substr( $payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
            return false === $secret ? new WP_Error( 'connector_secret_unavailable' ) : $secret;
        }
        if ( 0 === strpos( $stored, 'openssl:' ) && function_exists( 'openssl_decrypt' ) ) {
            $payload = base64_decode( substr( $stored, 8 ), true );
            if ( false === $payload || strlen( $payload ) <= 28 ) { return new WP_Error( 'connector_secret_unavailable' ); }
            $secret = openssl_decrypt( substr( $payload, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $payload, 0, 12 ), substr( $payload, 12, 16 ) );
            return false === $secret ? new WP_Error( 'connector_secret_unavailable' ) : $secret;
        }
        return new WP_Error( 'connector_secret_unavailable' );
    }
}
