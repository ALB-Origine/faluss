<?php
/**
 * Plugin Name: Faluss Identity Client
 * Description: Client SSO local pour une application de l'écosystème Faluss.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Text Domain: faluss-identity-client
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_IDENTITY_CLIENT_VERSION', '0.1.0' );
define( 'FALUSS_IDENTITY_CLIENT_FILE', __FILE__ );
define( 'FALUSS_IDENTITY_CLIENT_DIR', plugin_dir_path( __FILE__ ) );

require_once FALUSS_IDENTITY_CLIENT_DIR . 'includes/class-faluss-identity-client-plugin.php';

register_activation_hook( __FILE__, array( 'Faluss_Identity_Client_Plugin', 'activate' ) );

Faluss_Identity_Client_Plugin::boot();
