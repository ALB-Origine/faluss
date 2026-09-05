<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Encrypts the connector secret at rest; it is never rendered back into administration. */
final class Token_Engine_Connector_Crypto {
    const V2_SODIUM = 'tecv2:sodium:';
    const V2_OPENSSL = 'tecv2:openssl:';

    /** Returns whether this PHP instance can protect and recover a secret safely. */
    public static function is_available() {
        return self::sodium_available() || self::openssl_available();
    }

    public static function encrypt( $secret ) {
        if ( ! is_string( $secret ) || '' === $secret || strlen( $secret ) > 512 || ! self::is_available() ) {
            return new WP_Error( 'connector_secret_protection_unavailable' );
        }
        try {
            if ( self::sodium_available() ) {
                $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
                return self::V2_SODIUM . base64_encode( $nonce . sodium_crypto_secretbox( $secret, $nonce, self::key_v2() ) );
            }
            $iv_length = openssl_cipher_iv_length( 'aes-256-gcm' );
            $iv = random_bytes( $iv_length );
            $tag = '';
            $ciphertext = openssl_encrypt( $secret, 'aes-256-gcm', self::key_v2(), OPENSSL_RAW_DATA, $iv, $tag );
            if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
                return new WP_Error( 'connector_secret_protection_unavailable' );
            }
            return self::V2_OPENSSL . base64_encode( $iv . $tag . $ciphertext );
        } catch ( Exception $exception ) {
            return new WP_Error( 'connector_secret_protection_unavailable' );
        }
    }

    public static function decrypt( $stored ) {
        if ( ! is_string( $stored ) || '' === $stored ) {
            return new WP_Error( 'connector_secret_missing' );
        }
        try {
            if ( 0 === strpos( $stored, self::V2_SODIUM ) ) {
                return self::decrypt_sodium( substr( $stored, strlen( self::V2_SODIUM ) ), self::key_v2() );
            }
            if ( 0 === strpos( $stored, self::V2_OPENSSL ) ) {
                return self::decrypt_openssl( substr( $stored, strlen( self::V2_OPENSSL ) ), self::key_v2() );
            }
            /* TE-02.1 values remain readable and are replaced on the next secret save. */
            if ( 0 === strpos( $stored, 'sodium:' ) ) {
                return self::decrypt_sodium( substr( $stored, 7 ), self::legacy_key() );
            }
            if ( 0 === strpos( $stored, 'openssl:' ) ) {
                return self::decrypt_openssl( substr( $stored, 8 ), self::legacy_key() );
            }
        } catch ( Exception $exception ) {
            return new WP_Error( 'connector_secret_unavailable' );
        }
        return new WP_Error( 'connector_secret_unavailable' );
    }

    private static function decrypt_sodium( $encoded, $key ) {
        if ( ! self::sodium_available() ) {
            return new WP_Error( 'connector_secret_unavailable' );
        }
        $payload = base64_decode( $encoded, true );
        if ( false === $payload || strlen( $payload ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
            return new WP_Error( 'connector_secret_unavailable' );
        }
        $secret = sodium_crypto_secretbox_open( substr( $payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
        return false === $secret ? new WP_Error( 'connector_secret_unavailable' ) : $secret;
    }

    private static function decrypt_openssl( $encoded, $key ) {
        if ( ! self::openssl_available() ) {
            return new WP_Error( 'connector_secret_unavailable' );
        }
        $payload = base64_decode( $encoded, true );
        $iv_length = openssl_cipher_iv_length( 'aes-256-gcm' );
        if ( false === $payload || strlen( $payload ) <= $iv_length + 16 ) {
            return new WP_Error( 'connector_secret_unavailable' );
        }
        $secret = openssl_decrypt( substr( $payload, $iv_length + 16 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $payload, 0, $iv_length ), substr( $payload, $iv_length, 16 ) );
        return false === $secret ? new WP_Error( 'connector_secret_unavailable' ) : $secret;
    }

    private static function sodium_available() {
        return defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) && function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'sodium_crypto_secretbox_open' );
    }

    private static function openssl_available() {
        return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) && function_exists( 'openssl_cipher_iv_length' ) && 0 < (int) openssl_cipher_iv_length( 'aes-256-gcm' );
    }

    private static function key_v2() { return hash_hkdf( 'sha256', wp_salt( 'auth' ), 32, 'token-engine-connector-secret-v2' ); }
    private static function legacy_key() { return hash( 'sha256', wp_salt( 'auth' ), true ); }
}
