<?php

function te032_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-service.php' );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );
$admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );
$admin_script = file_get_contents( $root . '/plugins/token-engine/assets/js/token-engine-admin.js' );

foreach ( array( "\$state['state'] = 'granted'", "'already_claimed'", "'rule_unavailable'", "'subject_unavailable'", "'configuration_invalid'", "'transient_error'" ) as $needle ) {
    te032_assert( false !== strpos( $service, $needle ), 'TE-03.2 Core outcome is missing: ' . $needle );
}
foreach ( array( "'global' === \$scope ) { \$project_id = 0;", "'project' === \$scope && ! self::project_by_id", "! empty( \$rule['project_id'] )", 'connector_daily_reward', "'emitter_project'" ) as $needle ) {
    te032_assert( false !== strpos( $service, $needle ), 'TE-03.2 must preserve a global NULL project rule while recording through the authorized client project: ' . $needle );
}
te032_assert( false !== strpos( $service, "array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )" ), 'TE-03.2 ledger insert must have exactly one format per supplied ledger column.' );
foreach ( array( 'GET_LOCK', 'reward.daily.', 'ledger_by_idempotency', 'daily_reward_diagnostic', 'global_scope_accepted', "'state' => \$global_scope_accepted ? 'ready' : 'rule_unavailable'" ) as $needle ) {
    te032_assert( false !== strpos( $service, $needle ), 'TE-03.2 must keep one atomic global gain and a non-mutating diagnostic: ' . $needle );
}
foreach ( array( "'reward_diagnostic' => array( 'path' => '/connector/reward/diagnostic'", 'daily_reward_diagnostic_response', 'daily_reward_diagnostic_payload', 'has_reward_claim_permission', "'permission_denied'" ) as $needle ) {
    te032_assert( false !== strpos( $access, $needle ), 'TE-03.2 Core access diagnostic/state contract is missing: ' . $needle );
}
te032_assert( false === strpos( $access, "'faluss_id' =>" ) && false === strpos( $access, "\$result['access_token']" ), 'TE-03.2 Core responses must not expose a subject or short token.' );
foreach ( array( 'data-token-engine-rule-form', 'data-token-engine-global-scope', 'Toutes les surfaces autorisées', 'data-token-engine-rule-project-select' ) as $needle ) {
    te032_assert( false !== strpos( $admin, $needle ), 'TE-03.2 rule administration must make global/project scope explicit: ' . $needle );
}
foreach ( array( 'updateRuleScope', 'select.disabled = global', 'select.required = ! global' ) as $needle ) {
    te032_assert( false !== strpos( $admin_script, $needle ), 'TE-03.2 rule scope UI must prevent project assignment for a global rule: ' . $needle );
}

echo "TE-03.2 global daily reward and ledger execution contract: OK\n";
