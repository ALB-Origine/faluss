<?php

function tec017_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );
$plugin = file_get_contents( $root . '/plugins/token-engine-connector/token-engine-connector.php' );

foreach ( array( 'public static function daily_reward_diagnostic', "'reward_diagnostic'", 'connector/reward/diagnostic', 'normalise_daily_reward_response', 'daily_reward_error_result', "'granted'", "'already_claimed'", "'rule_unavailable'", "'permission_denied'", "'subject_unavailable'", "'configuration_invalid'", "'transient_error'" ) as $needle ) {
    tec017_assert( false !== strpos( $service, $needle ), 'TEC-01.7 must map the bounded daily reward state: ' . $needle );
}
foreach ( array( 'core_connection_test()', 'reward_claim_authorized', 'global_scope_accepted', 'subject_checked', 'subject_available', "route_request( 'reward_diagnostic'" ) as $needle ) {
    tec017_assert( false !== strpos( $service, $needle ), 'TEC-01.7 daily diagnostic must cover Core, permission, rule, scope and local subject readiness: ' . $needle );
}
$diagnostic_start = strpos( $service, 'public static function daily_reward_diagnostic' );
$request_start = strpos( $service, 'private static function daily_reward_request' );
$diagnostic = false !== $diagnostic_start && false !== $request_start ? substr( $service, $diagnostic_start, $request_start - $diagnostic_start ) : '';
tec017_assert( false === strpos( $diagnostic, "'subject_id' =>" ) && false === strpos( $diagnostic, 'claim_daily_reward' ), 'TEC-01.7 daily diagnostic must neither transmit a subject nor claim a reward.' );
foreach ( array( 'token_engine_connector_test_daily_reward', 'Diagnostiquer le gain quotidien', 'daily_reward_result', 'Aucun gain n’est réclamé', 'toutes les surfaces autorisées' ) as $needle ) {
    tec017_assert( false !== strpos( $admin, $needle ), 'TEC-01.7 administrator diagnostic is missing: ' . $needle );
}
tec017_assert( false === strpos( $plugin, 'register_rest_route' ) && false === strpos( $service, 'CREATE TABLE' ) && false === strpos( $service, 'INSERT INTO token_engine_ledger' ), 'TEC-01.7 must not add a public route, local table or ledger.' );
tec017_assert( false === strpos( $service, "'faluss_id' =>" ) && false === strpos( $service, "\$data['client_secret']" ), 'TEC-01.7 must not expose a Faluss ID or secret in a reward result.' );

echo "TEC-01.7 daily reward diagnosis and outcome mapping contract: OK\n";
