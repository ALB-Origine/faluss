<?php
/**
 * Plugin Name: Faluss Portal
 * Description: Portail membre privé Faluss.com, alimenté par la session SSO locale.
 * Version: 0.1.12
 * Requires PHP: 8.2
 * Text Domain: faluss-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_PORTAL_FILE', __FILE__ );
define( 'FALUSS_PORTAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'FALUSS_PORTAL_URL', plugin_dir_url( __FILE__ ) );
define( 'FALUSS_PORTAL_VERSION', '0.1.12' );

require_once FALUSS_PORTAL_DIR . 'includes/class-faluss-portal.php';

Faluss_Portal::boot();
