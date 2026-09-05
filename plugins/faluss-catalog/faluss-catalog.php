<?php
/**
 * Plugin Name: Catalogue Faluss
 * Description: Catalogue central des thèmes de cartes Faluss.
 * Version: 0.1.1
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FALUSS_CATALOG_FILE', __FILE__ );
define( 'FALUSS_CATALOG_DIR', plugin_dir_path( __FILE__ ) );
define( 'FALUSS_CATALOG_VERSION', '0.1.1' );

require_once FALUSS_CATALOG_DIR . 'includes/class-faluss-catalog-themes.php';

register_activation_hook( __FILE__, array( 'Faluss_Catalog_Themes', 'activate' ) );
Faluss_Catalog_Themes::boot();
