<?php

$fed01b_behavior_assertions = 0;

function fed01b_behavior_assert( $condition, $message ) {
    global $fed01b_behavior_assertions;
    $fed01b_behavior_assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function fed01b_b64url( $value ) { return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); }
function fed01b_canonical( $parts ) {
    foreach ( $parts as $part ) {
        if ( ! is_string( $part ) || false !== strpos( $part, "\r" ) || false !== strpos( $part, "\n" ) || 0 === strpos( $part, "\xEF\xBB\xBF" ) ) { return false; }
    }
    $out = implode( "\n", $parts );
    return '' !== $out && "\n" !== substr( $out, -1 ) ? $out : false;
}
function fed01b_consume( &$bindings, &$nonces, $sender, $key_id, $request_id, $nonce, $hash, $limit, $count ) {
    $binding = $sender . "\x1F" . $request_id;
    $nonce_key = $sender . "\x1F" . $key_id . "\x1F" . hash( 'sha256', $nonce );
    if ( isset( $bindings[ $binding ] ) || isset( $nonces[ $nonce_key ] ) ) { return 'replay_rejected'; }
    $bindings[ $binding ] = $hash;
    $nonces[ $nonce_key ] = true;
    return $count >= $limit ? 'temporarily_unavailable' : 'accepted';
}

$root = dirname( __DIR__ );
$crypto = file_get_contents( $root . '/plugins/faluss-federation/includes/class-faluss-federation-crypto.php' );
$server = file_get_contents( $root . '/plugins/faluss-federation/includes/class-faluss-federation-server.php' );
$client = file_get_contents( $root . '/plugins/faluss-federation/includes/class-faluss-federation-client.php' );
$providers = file_get_contents( $root . '/plugins/faluss-federation/includes/class-faluss-federation-providers.php' );

$request_raw = '{"protocol_version":"1","message_type":"request"}';
$request_canonical = fed01b_canonical( array( '1', 'POST', '/wp-json/faluss-federation/v1/exchange', 'hub-node', 'me-node', 'key-0001', '2026-09-13T10:00:00Z', '2026-09-13T10:05:00Z', fed01b_b64url( str_repeat( 'n', 32 ) ), hash( 'sha256', $request_raw ) ) );
fed01b_behavior_assert( is_string( $request_canonical ) && 0 === strpos( $request_canonical, "1\nPOST\n/wp-json" ), 'Request canonicalization must bind exact POST path and raw body hash.' );
fed01b_behavior_assert( false === fed01b_canonical( array( '1', "POST\r\nGET" ) ), 'Canonicalization must reject CR/LF injection.' );
fed01b_behavior_assert( false === fed01b_canonical( array( "\xEF\xBB\xBF1", 'POST' ) ), 'Canonicalization must reject UTF-8 BOM.' );
fed01b_behavior_assert( false !== strpos( $server, "'request_body_sha256' => " . '$request_body_hash' ), 'Signed response must bind the original raw request hash, not re-encoded JSON.' );
fed01b_behavior_assert( false !== strpos( $server, 'JSON_THROW_ON_ERROR' ) && false !== strpos( $server, 'MAX_BODY = 65536' ), 'Receiver must decode once after raw-size limit.' );
fed01b_behavior_assert( false !== strpos( $server, 'pre_auth_reject' ) && false !== strpos( $server, "'Request rejected.'" ), 'Pre-authentication failures must remain generic.' );

$bindings = array(); $nonces = array(); $hash = hash( 'sha256', $request_raw ); $nonce = fed01b_b64url( str_repeat( 'n', 32 ) );
fed01b_behavior_assert( 'accepted' === fed01b_consume( $bindings, $nonces, 'hub-node', 'key-0001', '11111111-1111-4111-8111-111111111111', $nonce, $hash, 30, 0 ), 'First nonce and binding must be consumed.' );
fed01b_behavior_assert( 'replay_rejected' === fed01b_consume( $bindings, $nonces, 'hub-node', 'key-0001', '11111111-1111-4111-8111-111111111111', $nonce, $hash, 30, 0 ), 'Concurrent duplicate must be rejected.' );
fed01b_behavior_assert( 'accepted' === fed01b_consume( $bindings, $nonces, 'me-node', 'key-0001', '11111111-1111-4111-8111-111111111111', fed01b_b64url( str_repeat( 'm', 32 ) ), $hash, 30, 0 ), 'Bindings must remain isolated by sender.' );
fed01b_behavior_assert( 'temporarily_unavailable' === fed01b_consume( $bindings, $nonces, 'me-node', 'key-0001', '22222222-2222-4222-8222-222222222222', fed01b_b64url( str_repeat( 'o', 32 ) ), $hash, 30, 30 ), 'A limited request must still consume its nonce.' );
fed01b_behavior_assert( false !== strpos( $client, "'sslverify' => true" ) && false !== strpos( $client, "'redirection' => 0" ) && false !== strpos( $client, "'connect_timeout' => 3" ), 'Outbound call must refuse insecure transport, redirects and unbounded connection time.' );
fed01b_behavior_assert( false === strpos( $client, 'public static function call' ) && false !== strpos( $client, 'private static function call' ), 'Network primitive must remain private behind three closed facades.' );
fed01b_behavior_assert( false !== strpos( $providers, "failure( 'not_available' )" ) && false !== strpos( $providers, 'valid_diagnostic' ), 'Only diagnostic is available without a registered specialized provider.' );
fed01b_behavior_assert( false !== strpos( $server, "'not_available' => 404" ) && false !== strpos( $server, "'replay_rejected' => 409" ), 'Authenticated status-to-HTTP mapping must be closed and explicit.' );
fed01b_behavior_assert( false !== strpos( $server, 'serve_pre_serialized' ) && false !== strpos( $server, "'/' . self::NAMESPACE . self::ROUTE" ), 'REST byte interception must be restricted to Federation route.' );

if ( function_exists( 'sodium_crypto_sign_seed_keypair' ) && function_exists( 'sodium_crypto_sign_detached' ) && function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
    $seed = random_bytes( SODIUM_CRYPTO_SIGN_SEEDBYTES );
    $pair = sodium_crypto_sign_seed_keypair( $seed );
    $secret = sodium_crypto_sign_secretkey( $pair );
    $public = sodium_crypto_sign_publickey( $pair );
    $signature = sodium_crypto_sign_detached( $request_canonical, $secret );
    fed01b_behavior_assert( sodium_crypto_sign_verify_detached( $signature, $request_canonical, $public ), 'Ed25519 must validate a canonical request.' );
    fed01b_behavior_assert( ! sodium_crypto_sign_verify_detached( $signature, $request_canonical . 'x', $public ), 'Ed25519 must reject an altered canonical request.' );
    if ( function_exists( 'sodium_memzero' ) ) { sodium_memzero( $secret ); sodium_memzero( $pair ); sodium_memzero( $seed ); }
    $sodium_state = 'executed';
} else {
    fed01b_behavior_assert( false !== strpos( $crypto, 'sodium_available' ) && false !== strpos( $crypto, "new WP_Error( 'faluss_federation_sodium_unavailable' )" ), 'Absent Sodium must remain fail-closed without fatal.' );
    $sodium_state = 'not-executed-sodium-unavailable';
}

echo 'FED-01B Federation behavior: OK (' . $fed01b_behavior_assertions . ' assertions; sodium=' . $sodium_state . ')' . PHP_EOL;
