<?php
/**
 * Plugin Name: Faluss Events
 * Description: Validateurs fermes des contrats d'evenements et adaptateur de catalogues Federation.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_EVENTS_VERSION', '0.1.0' );

require_once __DIR__ . '/includes/class-faluss-events-catalog-validator.php';
require_once __DIR__ . '/includes/class-faluss-events-envelope-validator.php';
require_once __DIR__ . '/includes/class-faluss-events.php';

Faluss_Events::boot();
