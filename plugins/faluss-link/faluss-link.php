<?php
/** Plugin Name: Faluss Link
 * Description: Carte publique Faluss.me construite sur le profil Faluss Identity.
 * Version: 0.1.13
 * Requires PHP: 8.2
 */
if(!defined('ABSPATH'))exit;
define('FALUSS_LINK_FILE',__FILE__);define('FALUSS_LINK_DIR',plugin_dir_path(__FILE__));define('FALUSS_LINK_VERSION','0.1.13');
require_once FALUSS_LINK_DIR.'includes/class-faluss-link-schema.php';
require_once FALUSS_LINK_DIR.'includes/class-faluss-link-admin.php';
require_once FALUSS_LINK_DIR.'includes/class-faluss-link.php';
register_activation_hook(__FILE__,array('Faluss_Link','activate'));
Faluss_Link::boot();
Faluss_Link_Admin::boot();
