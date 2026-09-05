<?php
/**
 * Plugin Name: Token Engine
 * Description: Generic, append-only unit ledger core for WordPress.
 * Version: 0.2.1
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TOKEN_ENGINE_FILE', __FILE__ );
define( 'TOKEN_ENGINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TOKEN_ENGINE_VERSION', '0.2.1' );

require_once TOKEN_ENGINE_DIR . 'includes/class-token-engine-schema.php';
require_once TOKEN_ENGINE_DIR . 'includes/class-token-engine-service.php';
require_once TOKEN_ENGINE_DIR . 'includes/class-token-engine-connector-access.php';
require_once TOKEN_ENGINE_DIR . 'includes/class-token-engine-admin.php';

register_activation_hook( __FILE__, array( 'Token_Engine_Schema', 'activate' ) );
add_action( 'plugins_loaded', array( 'Token_Engine_Schema', 'maybe_install' ), 1 );
Token_Engine_Connector_Access::boot();
Token_Engine_Admin::boot();
