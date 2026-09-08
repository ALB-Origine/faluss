<?php

/**
 * Browser-shaped administration contract: inspect the full rendered member page,
 * submit its real forms to the registered admin-post action, then reread storage.
 */
final class Faluss_Subscriptions_Test_Redirect extends RuntimeException {
    public $location;
    public function __construct( $location ) { parent::__construct( 'redirect' ); $this->location = $location; }
}

$sub01a_hooks = array();
function add_action( $hook, $callback, $priority = 10 ) { global $sub01a_hooks; $sub01a_hooks[ $hook ][] = array( 'callback' => $callback, 'priority' => $priority ); }
function has_action( $hook, $callback = false ) { global $sub01a_hooks; foreach ( (array) ( $sub01a_hooks[ $hook ] ?? array() ) as $registered ) { if ( false === $callback || $registered['callback'] === $callback ) { return $registered['priority']; } } return false; }
function sub01a_admin_post_do_action( $hook ) { global $sub01a_hooks; foreach ( (array) ( $sub01a_hooks[ $hook ] ?? array() ) as $registered ) { call_user_func( $registered['callback'] ); } }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/faluss-subscriptions/'; }
function current_user_can( $capability ) { return 'manage_faluss_subscriptions' === $capability; }
function get_current_user_id() { return 91; }
function wp_verify_nonce( $nonce, $action ) { return 'test-nonce' === $nonce && 'faluss_subscriptions_admin' === $action ? 1 : false; }
function check_admin_referer( $action ) { if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', $action ) ) { throw new RuntimeException( 'invalid nonce' ); } return 1; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="test-nonce">'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_safe_redirect( $location ) { throw new Faluss_Subscriptions_Test_Redirect( $location ); }
function nocache_headers() {}
function wp_die( $message, $status = 0 ) { throw new RuntimeException( (string) $message . ':' . (int) $status ); }
function esc_html( $value ) { return (string) $value; }
function esc_html__( $value ) { return (string) $value; }
function esc_attr( $value ) { return (string) $value; }
function esc_attr__( $value ) { return (string) $value; }
function esc_url( $value ) { return (string) $value; }

require __DIR__ . '/faluss-subscriptions-sub01a-persistence-contract-test.php';
require_once $plugin . '/faluss-subscriptions.php';

function sub01a_admin_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
function sub01a_form_tags_are_separate( $html ) {
    preg_match_all( '/<\\/?form\\b[^>]*>/i', $html, $matches );
    $depth = 0;
    foreach ( $matches[0] as $tag ) {
        if ( 0 === strpos( strtolower( $tag ), '</form' ) ) { --$depth; }
        else { ++$depth; if ( $depth > 1 ) { return false; } }
        if ( $depth < 0 ) { return false; }
    }
    return 0 === $depth;
}
function sub01a_find_form( $html, $mutation ) {
    $pattern = '/<form\\b(?=[^>]*data-faluss-subscriptions-mutation="' . preg_quote( $mutation, '/' ) . '")[^>]*>.*?<\\/form>/si';
    return 1 === preg_match( $pattern, $html, $matches ) ? $matches[0] : '';
}
function sub01a_hidden_value( $html, $name ) {
    $pattern = '/<input\\b(?=[^>]*\\bname="' . preg_quote( $name, '/' ) . '")[^>]*\\bvalue="([^"]*)"[^>]*>/i';
    return 1 === preg_match( $pattern, $html, $matches ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
}
function sub01a_dispatch_form( $fields ) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $fields;
    try {
        sub01a_admin_post_do_action( 'admin_post_faluss_subscriptions_admin' );
    } catch ( Faluss_Subscriptions_Test_Redirect $redirect ) {
        return $redirect->location;
    }
    return '';
}

$member_id = '55555555-5555-4555-8555-555555555555';
$_GET = array( 'page' => 'faluss-subscriptions', 'tab' => 'member', 'faluss_id' => $member_id );
$_POST = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
Faluss_Subscriptions_Admin::render_page();
$member_html = ob_get_clean();

sub01a_admin_assert( false !== has_action( 'admin_post_faluss_subscriptions_admin', array( 'Faluss_Subscriptions_Admin', 'handle_post' ) ), 'The bootstrap must register the exact admin-post mutation hook.' );
sub01a_admin_assert( sub01a_form_tags_are_separate( $member_html ), 'The rendered member page must not contain nested or unclosed forms.' );
$grant_form = sub01a_find_form( $member_html, 'grant_pro' );
$trial_form = sub01a_find_form( $member_html, 'override_trial_eligibility' );
sub01a_admin_assert( '' !== $grant_form && '' !== $trial_form, 'The full member page must expose distinct grant and trial-override forms.' );
foreach ( array( $grant_form, $trial_form ) as $mutation_form ) {
    sub01a_admin_assert( false !== strpos( $mutation_form, 'method="post"' ) && false !== strpos( $mutation_form, 'action="https://example.test/wp-admin/admin-post.php"' ), 'Every mutation form must POST directly to admin-post.php.' );
    sub01a_admin_assert( 'faluss_subscriptions_admin' === sub01a_hidden_value( $mutation_form, 'action' ) && 'test-nonce' === sub01a_hidden_value( $mutation_form, '_wpnonce' ) && $member_id === sub01a_hidden_value( $mutation_form, 'faluss_id' ), 'Every mutation form must submit the exact WordPress action, nonce and Faluss ID.' );
    sub01a_admin_assert( false !== strpos( $mutation_form, 'type="submit"' ), 'Every mutation form must have an explicit submit button.' );
}
sub01a_admin_assert( false !== strpos( $grant_form, 'name="expires_at"' ) && false !== strpos( $grant_form, 'name="reason"' ) && '' !== sub01a_hidden_value( $grant_form, 'operation_reference' ), 'The grant form must carry expiration, reason and an operation reference.' );

$_GET = array( 'page' => 'faluss-subscriptions', 'tab' => 'diagnostics' );
ob_start();
Faluss_Subscriptions_Admin::render_page();
$diagnostics_html = ob_get_clean();
sub01a_admin_assert( false !== strpos( $diagnostics_html, 'Câblage administratif' ) && false !== strpos( $diagnostics_html, 'admin-post.php' ) && false !== strpos( $diagnostics_html, 'Autonomes' ) && false !== strpos( $diagnostics_html, 'Transaction disponible' ), 'Diagnostics must verify non-destructively the routed hook, endpoint, separated forms and transaction capability.' );

$grant_redirect = sub01a_dispatch_form(
    array(
        'action' => sub01a_hidden_value( $grant_form, 'action' ),
        'faluss_subscriptions_action' => sub01a_hidden_value( $grant_form, 'faluss_subscriptions_action' ),
        'return_tab' => sub01a_hidden_value( $grant_form, 'return_tab' ),
        'faluss_id' => sub01a_hidden_value( $grant_form, 'faluss_id' ),
        'operation_reference' => sub01a_hidden_value( $grant_form, 'operation_reference' ),
        '_wpnonce' => sub01a_hidden_value( $grant_form, '_wpnonce' ),
        'expires_at' => gmdate( 'Y-m-d\\TH:i', time() + DAY_IN_SECONDS ),
        'reason' => 'Attribution contractuelle',
    )
);
sub01a_admin_assert( false !== strpos( $grant_redirect, 'page=faluss-subscriptions&tab=member' ), 'A successful grant must PRG to the same member tab.' );
$persisted = Faluss_Subscriptions_Repository::entitlements_for_faluss_id( $member_id );
sub01a_admin_assert( 1 === count( $persisted ) && 'admin_grant' === $persisted[0]['source'] && 'active' === $persisted[0]['status'], 'The POST dispatched through the registered handler must persist the admin grant.' );
$decision = Faluss_Subscriptions_Resolver::resolve_for_faluss_id( $member_id );
sub01a_admin_assert( 'pro' === $decision['level'] && 'comped' === $decision['state'], 'An independent resolver reread must return comped after the real form POST.' );
$audit_actions = array_column( $wpdb->rows['audit'], 'action' );
sub01a_admin_assert( in_array( 'admin_grant_succeeded', $audit_actions, true ), 'The real form POST must write the admin_grant_succeeded audit trace.' );

$_GET = array( 'page' => 'faluss-subscriptions', 'tab' => 'member' );
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
Faluss_Subscriptions_Admin::render_page();
$success_html = ob_get_clean();
sub01a_admin_assert( false !== strpos( $success_html, 'Faluss Pro a été attribué jusqu’au' ), 'The redirected member page must show one visible success notice.' );
$revoke_form = sub01a_find_form( $success_html, 'revoke_grant' );
sub01a_admin_assert( '' !== $revoke_form && false !== strpos( $revoke_form, 'action="https://example.test/wp-admin/admin-post.php"' ) && false !== strpos( $revoke_form, 'type="submit"' ), 'The rendered revoke form must independently post to admin-post.php.' );
$revoke_redirect = sub01a_dispatch_form(
    array(
        'action' => sub01a_hidden_value( $revoke_form, 'action' ),
        'faluss_subscriptions_action' => sub01a_hidden_value( $revoke_form, 'faluss_subscriptions_action' ),
        'return_tab' => sub01a_hidden_value( $revoke_form, 'return_tab' ),
        'faluss_id' => sub01a_hidden_value( $revoke_form, 'faluss_id' ),
        'grant_id' => sub01a_hidden_value( $revoke_form, 'grant_id' ),
        '_wpnonce' => sub01a_hidden_value( $revoke_form, '_wpnonce' ),
        'reason' => 'Fin de l’attribution contractuelle',
    )
);
sub01a_admin_assert( false !== strpos( $revoke_redirect, 'page=faluss-subscriptions&tab=member' ), 'A revocation must PRG to the same member tab.' );
$after_revoke = Faluss_Subscriptions_Resolver::resolve_for_faluss_id( $member_id );
sub01a_admin_assert( 'free' === $after_revoke['level'], 'A revocation posted by its real form must return the member to free.' );
sub01a_admin_assert( in_array( 'admin_pro_revoked', array_column( $wpdb->rows['audit'], 'action' ), true ), 'The real revoke POST must be audited.' );

$invalid_expiration = sub01a_dispatch_form(
    array(
        'action' => sub01a_hidden_value( $grant_form, 'action' ),
        'faluss_subscriptions_action' => 'grant_pro',
        'return_tab' => 'member',
        'faluss_id' => $member_id,
        'operation_reference' => sub01a_hidden_value( $grant_form, 'operation_reference' ),
        '_wpnonce' => 'test-nonce',
        'expires_at' => gmdate( 'Y-m-d\\TH:i', time() - HOUR_IN_SECONDS ),
        'reason' => 'Date volontairement invalide',
    )
);
sub01a_admin_assert( false !== strpos( $invalid_expiration, 'page=faluss-subscriptions&tab=member' ) && in_array( 'admin_grant_invalid_expiration', array_column( $wpdb->rows['audit'], 'action' ), true ), 'A handler-reached invalid expiration must redirect safely and leave an explicit audit trace.' );
$invalid_nonce = sub01a_dispatch_form(
    array(
        'action' => sub01a_hidden_value( $grant_form, 'action' ),
        'faluss_subscriptions_action' => 'grant_pro',
        'return_tab' => 'member',
        'faluss_id' => $member_id,
        'operation_reference' => sub01a_hidden_value( $grant_form, 'operation_reference' ),
        '_wpnonce' => 'invalid-nonce',
        'expires_at' => gmdate( 'Y-m-d\\TH:i', time() + DAY_IN_SECONDS ),
        'reason' => 'Nonce volontairement invalide',
    )
);
sub01a_admin_assert( false !== strpos( $invalid_nonce, 'page=faluss-subscriptions&tab=member' ) && in_array( 'admin_grant_invalid_nonce', array_column( $wpdb->rows['audit'], 'action' ), true ), 'A handler-reached invalid nonce must redirect safely and leave an explicit audit trace.' );

echo "SUB-01A admin-post contract: OK\n";
