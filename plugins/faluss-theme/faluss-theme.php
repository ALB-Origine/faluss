<?php
/** Plugin Name: Faluss Theme
 * Description: Tokens visuels centralisés pour les sites Faluss.
 * Version: 0.1.0
 */
if(!defined('ABSPATH'))exit;
define('FALUSS_THEME_FILE',__FILE__);define('FALUSS_THEME_DIR',plugin_dir_path(__FILE__));
require_once FALUSS_THEME_DIR.'includes/class-faluss-theme.php';
register_activation_hook(__FILE__,array('Faluss_Theme','activate'));
Faluss_Theme::boot();
