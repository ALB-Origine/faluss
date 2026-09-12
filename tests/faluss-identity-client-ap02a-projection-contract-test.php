<?php

define( 'ABSPATH', __DIR__ . '/' );

function ap02a_client_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$ap02a_options = array(
    'faluss_identity_client_settings' => array(
        'enabled' => true,
        'authority' => 'https://faluss.me',
        'client_id' => 'faluss-hub',
        'return_urls' => array(),
    ),
);
$ap02a_user_meta = array();

function get_option( $key, $default = array() ) {
    global $ap02a_options;
    return $ap02a_options[ $key ] ?? $default;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_user_meta( $user_id, $key, $single ) {
    global $ap02a_user_meta;
    unset( $single );
    return $ap02a_user_meta[ (int) $user_id ][ $key ] ?? '';
}
function update_user_meta( $user_id, $key, $value ) {
    global $ap02a_user_meta;
    $ap02a_user_meta[ (int) $user_id ][ $key ] = $value;
    return true;
}
function delete_user_meta( $user_id, $key ) {
    global $ap02a_user_meta;
    unset( $ap02a_user_meta[ (int) $user_id ][ $key ] );
    return true;
}

final class Faluss_Identity_Client_Schema {
    public static function tables() { return array( 'links' => 'wp_faluss_identity_links' ); }
}
final class AP02A_Client_WPDB {
    public $last_arguments = array();
    public function prepare( $query, ...$arguments ) {
        $this->last_arguments = $arguments;
        return $query;
    }
    public function get_var( $query ) {
        unset( $query );
        return isset( $this->last_arguments[0] ) && '11111111-1111-4111-8111-111111111111' === $this->last_arguments[0] ? 42 : null;
    }
}
$wpdb = new AP02A_Client_WPDB();

require_once dirname( __DIR__ ) . '/plugins/faluss-identity-client/includes/class-faluss-identity-client.php';

$validate = new ReflectionMethod( 'Faluss_Identity_Client', 'validated_me_projection' );
$validate->setAccessible( true );
$synchronize = new ReflectionMethod( 'Faluss_Identity_Client', 'synchronize_member_app_projections' );
$synchronize->setAccessible( true );

$valid = array(
    'contract_version' => '1',
    'publication_status' => 'published',
    'canonical_url' => 'https://faluss.me/mon-faluss',
);
ap02a_client_assert( $valid === $validate->invoke( null, $valid ), 'The exact versioned Identity member route must be accepted.' );
foreach ( array(
    array( 'contract_version' => '1', 'publication_status' => 'draft', 'canonical_url' => 'https://faluss.me/mon-faluss' ),
    array( 'contract_version' => '1', 'publication_status' => 'published', 'canonical_url' => 'https://faluss.me/' ),
    array( 'contract_version' => '1', 'publication_status' => 'published', 'canonical_url' => 'https://attacker.invalid/mon-faluss' ),
    array( 'contract_version' => '1', 'publication_status' => 'published', 'canonical_url' => 'https://faluss.me/mon-faluss?published=1' ),
) as $invalid ) {
    ap02a_client_assert( null === $validate->invoke( null, $invalid ), 'Draft, generic, foreign or browser-augmented routes must fail closed.' );
}

ap02a_client_assert( true === $synchronize->invoke( null, 42, array( 'apps' => array( 'me' => $valid ) ) ), 'A valid server claim must be persisted for the linked local member.' );
ap02a_client_assert( $valid === Faluss_Identity_Client::member_app_projection( '11111111-1111-4111-8111-111111111111', 'me' ), 'Portal must be able to read the validated projection only through Identity Client.' );
ap02a_client_assert( null === Faluss_Identity_Client::member_app_projection( '11111111-1111-4111-8111-111111111111', 'hub' ), 'The projection reader must not invent another application.' );
ap02a_client_assert( true === $synchronize->invoke( null, 42, array() ) && null === Faluss_Identity_Client::member_app_projection( '11111111-1111-4111-8111-111111111111', 'me' ), 'An absent projection on a later SSO exchange must revoke the local read-model.' );

echo "AP-02A Identity Client projection contract: OK\n";
