<?php
/**
 * Plugin Name: Faluss Apps Registry
 * Description: Runtime validation primitives for Faluss application capability manifests.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Faluss
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_APPS_REGISTRY_VERSION', '0.2.0' );
define( 'FALUSS_APPS_REGISTRY_DIR', plugin_dir_path( __FILE__ ) );

require_once FALUSS_APPS_REGISTRY_DIR . 'includes/class-faluss-apps-registry-manifest-validator.php';
require_once FALUSS_APPS_REGISTRY_DIR . 'includes/class-faluss-apps-registry-read-model-validator.php';
require_once FALUSS_APPS_REGISTRY_DIR . 'includes/class-faluss-apps-registry.php';

Faluss_Apps_Registry::boot();
