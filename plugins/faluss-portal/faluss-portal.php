<?php
/**
 * Plugin Name: Faluss Portal
 * Description: Portail membre privé Faluss.com, alimenté par la session SSO locale.
 * Version: 0.1.24
 * Requires PHP: 8.2
 * Text Domain: faluss-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_PORTAL_FILE', __FILE__ );
define( 'FALUSS_PORTAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'FALUSS_PORTAL_URL', plugin_dir_url( __FILE__ ) );
define( 'FALUSS_PORTAL_VERSION', '0.1.24' );

require_once FALUSS_PORTAL_DIR . 'includes/class-faluss-portal.php';
require_once FALUSS_PORTAL_DIR . 'includes/class-faluss-portal-manifest.php';
require_once FALUSS_PORTAL_DIR . 'includes/class-faluss-portal-events-catalog.php';
require_once FALUSS_PORTAL_DIR . 'includes/class-faluss-portal-events-runtime.php';
require_once FALUSS_PORTAL_DIR . 'includes/class-faluss-portal-apps-registry-adapter.php';

Faluss_Portal::boot();
Faluss_Portal_Manifest::boot();
Faluss_Portal_Events_Catalog::boot();
Faluss_Portal_Events_Runtime::boot();
Faluss_Portal_Apps_Registry_Adapter::boot();
