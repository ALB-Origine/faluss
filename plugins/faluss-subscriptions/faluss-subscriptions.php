<?php
/**
 * Plugin Name: Faluss Subscriptions
 * Description: Autorité centrale des abonnements, essais et droits Faluss.
 * Version: 0.1.0
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_SUBSCRIPTIONS_FILE', __FILE__ );
define( 'FALUSS_SUBSCRIPTIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FALUSS_SUBSCRIPTIONS_URL', plugin_dir_url( __FILE__ ) );
define( 'FALUSS_SUBSCRIPTIONS_VERSION', '0.1.0' );

require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-schema.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-catalog.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-audit.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-repository.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-trials.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-entitlements.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-resolver.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-diagnostics.php';
require_once FALUSS_SUBSCRIPTIONS_DIR . 'includes/class-faluss-subscriptions-admin.php';

register_activation_hook( __FILE__, array( 'Faluss_Subscriptions_Schema', 'activate' ) );
register_activation_hook( __FILE__, array( 'Faluss_Subscriptions_Admin', 'grant_capability' ) );
register_deactivation_hook( __FILE__, array( 'Faluss_Subscriptions_Schema', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Faluss_Subscriptions_Schema', 'maybe_install' ), 1 );
Faluss_Subscriptions_Admin::boot();
