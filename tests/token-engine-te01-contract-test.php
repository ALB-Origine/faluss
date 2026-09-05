<?php

function te01_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: $message\n" );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/plugins/token-engine/token-engine.php' );
$schema = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-schema.php' );
$service = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-service.php' );
$admin = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-admin.php' );
$all = $plugin . $schema . $service . $admin;

foreach ( array( 'Token Engine', 'TOKEN_ENGINE_VERSION', 'register_activation_hook', "'Token_Engine_Schema', 'activate'", 'Token_Engine_Admin::boot' ) as $needle ) {
    te01_assert( false !== strpos( $plugin, $needle ), 'TE-01 bootstrap invariant is missing: ' . $needle );
}
foreach ( array( 'Faluss', 'ALB', 'Alternative LAB', 'WooCommerce', 'Elementor', 'add_rest_route', 'register_rest_route', 'wp_remote_', 'OAuth', 'Premium', 'daily reward', 'wallet public', 'shop' ) as $forbidden ) {
    te01_assert( false === stripos( $all, $forbidden ), 'TE-01 must remain a generic core without this dependency or product behavior: ' . $forbidden );
}

foreach ( array( "const VERSION = '1'", 'ENGINE=InnoDB', 'GET_LOCK', 'SHOW TABLE STATUS', 'SHOW FULL COLUMNS', 'SHOW INDEX', 'is_ready()', 'project_key_unique', 'rule_key_unique', 'transaction_uuid_unique', 'idempotency_key_unique', 'ledger_subject_project_date', 'ledger_project_rule_date', 'ledger_rule_date', 'ledger_created_at' ) as $needle ) {
    te01_assert( false !== strpos( $schema, $needle ), 'TE-01 schema invariant is missing: ' . $needle );
}
foreach ( array( 'DROP TABLE', 'ALTER TABLE', 'dbDelta', 'INSERT INTO', 'DELETE FROM' ) as $forbidden ) {
    te01_assert( false === stripos( $schema, $forbidden ), 'TE-01 schema must create or verify only, without destructive/default-data SQL: ' . $forbidden );
}
te01_assert( false !== strpos( $schema, 'count( $existing ) !== count( $tables )' ) && false !== strpos( $schema, 'verify_table' ) && false !== strpos( $schema, 'wp_die' ), 'TE-01 must refuse a partial or divergent schema rather than repairing it.' );
te01_assert( false !== strpos( $schema, "'unique' => true") && false !== strpos( $schema, "'Non_unique'") && false !== strpos( $schema, "'Column_name'" ), 'TE-01 must verify unique index semantics and ordered columns.' );

foreach ( array( 'unit_code', 'unit_singular', 'unit_plural', 'reference_timezone', 'configuration_is_valid()', 'unit_code_locked', 'has_transactions()', '/^[A-Z][A-Z0-9_-]{1,15}$/' ) as $needle ) {
    te01_assert( false !== strpos( $service, $needle ), 'TE-01 configuration invariant is missing: ' . $needle );
}
foreach ( array( "const SCOPES = array( 'global', 'project' )", "const TRIGGERS = array( 'event', 'claim' )", "const PERIODICITIES = array( 'none', 'once', 'daily', 'cooldown' )", "'project' === \$scope", "'global' === \$scope", 'invalid_cooldown' ) as $needle ) {
    te01_assert( false !== strpos( $service, $needle ), 'TE-01 rule validation invariant is missing: ' . $needle );
}
foreach ( array( "SUM(CASE WHEN direction=\\'credit\\' THEN amount ELSE -amount END)", 'START TRANSACTION', 'FOR UPDATE', "'debit' === \$transaction['direction']", 'insufficient_balance', 'idempotency_key', 'transaction_uuid', 'result_from_entry( $existing, true )', 'Token_Engine_Schema::is_ready()' ) as $needle ) {
    te01_assert( false !== strpos( $service, $needle ), 'TE-01 ledger/idempotence invariant is missing: ' . $needle );
}
te01_assert( false === stripos( $service, 'balance`' ), 'TE-01 must not store a mutable balance field.' );
foreach ( array( 'wp_schedule', 'wp_cron', 'cron', 'daily_reward', 'evaluate_eligibility' ) as $forbidden ) {
    te01_assert( false === stripos( $service, $forbidden ), 'TE-01 must not execute future rules or rewards automatically: ' . $forbidden );
}

foreach ( array( 'manage_options', 'token_engine_save_configuration', 'token_engine_create_project', 'token_engine_create_rule', 'token_engine_adjust', 'wp_nonce_field', 'wp_verify_nonce', 'wp_safe_redirect', "'operation_uuid'", "'idempotency_key' => wp_unslash( \$_POST['operation_uuid']", "'transaction_uuid' => wp_unslash( \$_POST['operation_uuid']", 'Solde projeté' ) as $needle ) {
    te01_assert( false !== strpos( $admin, $needle ), 'TE-01 native administration/manual idempotence invariant is missing: ' . $needle );
}
foreach ( array( 'Configuration', 'Projets', 'Règles', 'Ledger', 'Ajustement manuel' ) as $label ) {
    te01_assert( false !== strpos( $admin, $label ), 'TE-01 administration section is missing: ' . $label );
}

echo "TE-01 Token Engine contract: OK\n";
