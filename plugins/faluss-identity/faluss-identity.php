<?php
/**
 * Plugin Name: Faluss Identity
 * Description: Autorité d'identité passwordless et SSO de l'écosystème Faluss.
 * Version: 0.4.10
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Text Domain: faluss-identity
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_IDENTITY_VERSION', '0.4.10' );
define( 'FALUSS_IDENTITY_FILE', __FILE__ );
define( 'FALUSS_IDENTITY_DIR', plugin_dir_path( __FILE__ ) );

require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-plugin.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-schema.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-registry.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-front-preferences.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-navigation.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-passwordless.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-public-profile.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-onboarding.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-authorization.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-sso-clients-admin.php';
require_once FALUSS_IDENTITY_DIR . 'includes/class-faluss-identity-admin-diagnostic.php';

register_activation_hook( __FILE__, array( 'Faluss_Identity_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Faluss_Identity_Plugin', 'deactivate' ) );

Faluss_Identity_Plugin::boot();
