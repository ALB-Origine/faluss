<?php
if(!defined('ABSPATH'))exit;
final class Faluss_Theme {
 const OPTION='faluss_theme_tokens';
 public static function defaults(){return array('canvas'=>'#FFFDF5','surface'=>'#FFFFFF','ink'=>'#080808','muted'=>'#6F6A63','accent'=>'#FF3D16','action'=>'#080808','action_text'=>'#FFFFFF','action_hover'=>'#28231F','action_active'=>'#000000','border'=>'rgba(8, 8, 8, 0.12)','card_radius'=>'24px','control_radius'=>'8px','pill_radius'=>'999px','shadow'=>'0 12px 30px rgba(8, 8, 8, 0.06)');}
 public static function boot(){if(!self::is_faluss_site())return;add_action('wp_enqueue_scripts',array(__CLASS__,'tokens'));if(is_admin()){add_action('admin_menu',array(__CLASS__,'menu'));add_action('admin_init',array(__CLASS__,'settings'));}}
 public static function activate(){if(!self::is_faluss_site())wp_die(esc_html__('Faluss Theme est réservé aux sites Faluss.','faluss-theme'));}
 private static function is_faluss_site(){$host=(string)wp_parse_url(home_url('/'),PHP_URL_HOST);return 1===preg_match('/(^|\\.)faluss\\.(me|com)$/i',$host);}
 public static function get(){return wp_parse_args((array)get_option(self::OPTION,array()),self::defaults());}
 public static function tokens(){ $t=self::get();$css=':root{';foreach($t as $k=>$v)$css.='--faluss-'.$k.':'.$v.';';$css.='}';wp_register_style('faluss-theme-tokens',false,array(),null);wp_enqueue_style('faluss-theme-tokens');wp_add_inline_style('faluss-theme-tokens',$css);}
 public static function menu(){add_options_page('Faluss Theme','Faluss Theme','manage_options','faluss-theme',array(__CLASS__,'page'));}
 public static function settings(){register_setting('faluss_theme',self::OPTION,array('sanitize_callback'=>array(__CLASS__,'sanitize')));}
 public static function sanitize($v){$d=self::defaults();$out=array();foreach($d as $k=>$fallback){$x=isset($v[$k])?(string)$v[$k]:$fallback;if(in_array($k,array('border','shadow'),true)){$out[$k]=$x===$fallback?$x:$fallback;}elseif(false!==strpos($k,'radius')){$out[$k]=preg_match('/^[0-9]{1,3}px$/',$x)?$x:$fallback;}else{$out[$k]=preg_match('/^#[A-Fa-f0-9]{6}$/',$x)?$x:$fallback;}}return $out;}
 public static function page(){if(!current_user_can('manage_options'))return;$t=self::get();?><div class="wrap"><h1>Faluss Theme</h1><form method="post" action="options.php"><?php settings_fields('faluss_theme');foreach(self::defaults() as $k=>$d):?><p><label><?php echo esc_html($k); ?><br><input name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr($t[$k]); ?>"></label></p><?php endforeach;submit_button();?></form><form method="post" action="options.php"><?php settings_fields('faluss_theme');foreach(self::defaults() as $k=>$v):?><input type="hidden" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr($v); ?>"><?php endforeach;?><button class="button" type="submit">Restaurer les valeurs canoniques</button></form></div><?php }
}
