<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Faluss_Identity_Client_Plugin {

    public static function boot() {
        if ( self::is_identity_host() ) { return; }
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
        add_action( 'init', array( 'Faluss_Identity_Client', 'register' ) );
        add_action( 'wp_enqueue_scripts', array( 'Faluss_Identity_Client', 'assets' ) );
        add_action( 'elementor/widgets/register', array( __CLASS__, 'widget' ) );
        if ( is_admin() ) { Faluss_Identity_Client_Admin::register(); }
    }

    public static function activate() {
        if ( self::is_identity_host() ) { wp_die( 'Faluss Identity Client ne peut pas être activé sur faluss.me.' ); }
        if ( Faluss_Identity_Client_Schema::install_or_verify() ) { Faluss_Identity_Client::rewrite(); flush_rewrite_rules(); }
    }

    public static function deactivate() { flush_rewrite_rules(); }

    public static function load_textdomain() {
        load_plugin_textdomain( 'faluss-identity-client', false, dirname( plugin_basename( FALUSS_IDENTITY_CLIENT_FILE ) ) . '/languages' );
    }
    public static function widget($manager){if(!class_exists('Elementor\\Widget_Base')||!is_object($manager)||!method_exists($manager,'register'))return;require_once FALUSS_IDENTITY_CLIENT_DIR.'includes/class-faluss-identity-client-elementor-widget.php';$manager->register(new Faluss_Identity_Client_Elementor_Widget());}
    private static function is_identity_host(){ $p=wp_parse_url(home_url('/')); return is_array($p)&&isset($p['host'])&&in_array(strtolower($p['host']),array('faluss.me','www.faluss.me'),true); }
}
