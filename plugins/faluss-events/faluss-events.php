<?php
/**
 * Plugin Name: Faluss Events
 * Description: Coeur persistant et validateurs fermes des contrats d'evenements Faluss.
 * Version: 0.3.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_EVENTS_VERSION', '0.3.0' );
define( 'FALUSS_EVENTS_SCHEMA_VERSION', '1' );

require_once __DIR__ . '/includes/class-faluss-events-catalog-validator.php';
require_once __DIR__ . '/includes/class-faluss-events-envelope-validator.php';
require_once __DIR__ . '/includes/class-faluss-events-canonicalizer.php';
require_once __DIR__ . '/includes/class-faluss-events.php';
require_once __DIR__ . '/includes/class-faluss-events-schema.php';
require_once __DIR__ . '/includes/class-faluss-events-engine.php';
require_once __DIR__ . '/includes/class-faluss-events-workers.php';

register_activation_hook( __FILE__, array( 'Faluss_Events_Schema', 'activate' ) );
register_activation_hook( __FILE__, array( 'Faluss_Events_Workers', 'activate' ) );
if ( function_exists( 'register_deactivation_hook' ) ) {
    register_deactivation_hook( __FILE__, array( 'Faluss_Events_Workers', 'deactivate' ) );
}
add_action( 'plugins_loaded', array( 'Faluss_Events_Schema', 'maybe_upgrade' ), 5 );

Faluss_Events::boot();
Faluss_Events_Workers::boot();
