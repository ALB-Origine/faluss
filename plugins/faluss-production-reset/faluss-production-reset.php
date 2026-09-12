<?php
/**
 * Plugin Name: Faluss Production Reset
 * Description: One-time, administrator-operated production reset coordinator for faluss.me and faluss.com.
 * Version: 0.1.3
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Faluss
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_PRODUCTION_RESET_VERSION', '0.1.3' );
define( 'FALUSS_PRODUCTION_RESET_DIR', plugin_dir_path( __FILE__ ) );

require_once FALUSS_PRODUCTION_RESET_DIR . 'includes/class-faluss-production-reset.php';

Faluss_Production_Reset::boot();
