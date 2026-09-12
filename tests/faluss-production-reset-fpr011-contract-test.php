<?php

define( 'ABSPATH', __DIR__ . '/' );

function fpr011_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$fpr011_options = array();
$fpr011_hooks = array();

function get_option( $name, $default = false ) {
    global $fpr011_options;
    return array_key_exists( $name, $fpr011_options ) ? $fpr011_options[ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
    global $fpr011_options;
    unset( $autoload );
    $fpr011_options[ $name ] = $value;
    return true;
}

function add_action( $hook, $callback ) {
    global $fpr011_hooks;
    $fpr011_hooks[] = array( $hook, $callback );
}

$root = dirname( __DIR__ );
$service_path = $root . '/plugins/faluss-production-reset/includes/class-faluss-production-reset.php';
$service = file_get_contents( $service_path );

require_once $service_path;

$armed = new ReflectionMethod( 'Faluss_Production_Reset', 'is_armed' );
$armed->setAccessible( true );

// A 0.1.2 armament is invalidated while loading 0.1.3; boot only registers handlers.
$fpr011_options = array(
    Faluss_Production_Reset::OPTION_ARMED => 1,
    Faluss_Production_Reset::OPTION_ARMED_VERSION => '0.1.2',
    Faluss_Production_Reset::OPTION_LOCKED => 0,
);
$fpr011_hooks = array();
Faluss_Production_Reset::boot();
fpr011_assert( 0 === $fpr011_options[ Faluss_Production_Reset::OPTION_ARMED ] && '0.1.3' === $fpr011_options[ Faluss_Production_Reset::OPTION_ARMED_VERSION ] && false === $armed->invoke( null ), 'An armament created under 0.1.2 must be invalidated and require explicit re-arm under 0.1.3.' );
fpr011_assert( 5 === count( $fpr011_hooks ), 'Boot may only register the existing five handlers; it must not launch a reset or preflight.' );

// A prior lock is not cleared or weakened by the required armament invalidation.
$fpr011_options = array(
    Faluss_Production_Reset::OPTION_ARMED => 1,
    Faluss_Production_Reset::OPTION_ARMED_VERSION => '0.1.2',
    Faluss_Production_Reset::OPTION_LOCKED => 1,
);
Faluss_Production_Reset::boot();
fpr011_assert( 0 === $fpr011_options[ Faluss_Production_Reset::OPTION_ARMED ] && 1 === $fpr011_options[ Faluss_Production_Reset::OPTION_LOCKED ], 'A previously locked installation remains locked while its legacy armament is invalidated.' );

// Only an explicit current-version armament may be considered armed.
$fpr011_options = array(
    Faluss_Production_Reset::OPTION_ARMED => 1,
    Faluss_Production_Reset::OPTION_ARMED_VERSION => '0.1.3',
    Faluss_Production_Reset::OPTION_LOCKED => 0,
);
fpr011_assert( true === $armed->invoke( null ), 'A current-version armament is the only armament accepted after the upgrade guard.' );

$identity_preflight = preg_match( '/private static function preflight_identity\(\).*?private static function preflight_hub\(/s', $service, $identity_match ) ? $identity_match[0] : '';
$hub_preflight = preg_match( '/private static function preflight_hub\(\).*?private static function required_tables\(/s', $service, $hub_match ) ? $hub_match[0] : '';
fpr011_assert( '' !== $identity_preflight && false === strpos( $identity_preflight, 'token_engine_pf_ledger' ) && false === strpos( $identity_preflight, 'table_count(' ), 'faluss.me preflight must not create, require, read or count a PF ledger.' );
fpr011_assert( '' !== $hub_preflight && false !== strpos( $hub_preflight, "'token_engine_pf_ledger'" ) && false !== strpos( $hub_preflight, 'table_count( $tables[\'token_engine_pf_ledger\'] )' ) && false !== strpos( $hub_preflight, 'fpr_pf_ledger_not_empty' ), 'faluss.com preflight must still require a present, strictly empty PF ledger.' );

$boot = preg_match( '/public static function boot\(\).*?public static function admin_menu\(/s', $service, $boot_match ) ? $boot_match[0] : '';
fpr011_assert( false !== strpos( $boot, 'invalidate_legacy_armament' ) && false === strpos( $boot, 'preflight_identity' ) && false === strpos( $boot, 'preflight_hub' ) && false === strpos( $boot, 'perform_identity_reset' ) && false === strpos( $boot, 'perform_hub_reset' ), 'The update guard must disarm only and must never start a reset automatically.' );
fpr011_assert( false === strpos( $service, 'CREATE TABLE' ) && false === strpos( $service, 'dbDelta' ) && false === strpos( $service, 'migrate_' ), 'FPR-01.3 must add no table or migration.' );

echo 'FPR-01.3 armament invalidation contract: OK' . PHP_EOL;
