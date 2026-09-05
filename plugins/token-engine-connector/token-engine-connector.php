<?php
/**
 * Plugin Name: Token Engine Connector
 * Description: Private WordPress connector for a remote Token Engine core.
 * Version: 0.1.7
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TOKEN_ENGINE_CONNECTOR_FILE', __FILE__ );
define( 'TOKEN_ENGINE_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'TOKEN_ENGINE_CONNECTOR_VERSION', '0.1.7' );

require_once TOKEN_ENGINE_CONNECTOR_DIR . 'includes/class-token-engine-connector-crypto.php';
require_once TOKEN_ENGINE_CONNECTOR_DIR . 'includes/class-token-engine-connector-subject.php';
require_once TOKEN_ENGINE_CONNECTOR_DIR . 'includes/class-token-engine-connector-service.php';
require_once TOKEN_ENGINE_CONNECTOR_DIR . 'includes/class-token-engine-connector-upgrade.php';
require_once TOKEN_ENGINE_CONNECTOR_DIR . 'includes/class-token-engine-connector-admin.php';

register_activation_hook( __FILE__, array( 'Token_Engine_Connector_Upgrade', 'activate' ) );
add_action( 'plugins_loaded', array( 'Token_Engine_Connector_Upgrade', 'maybe_upgrade' ), 1 );
Token_Engine_Connector_Admin::boot();
