<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Faluss_Identity_Plugin {

    public static function boot() {
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );

        if ( is_admin() ) {
            add_action( 'admin_notices', array( 'Faluss_Identity_Admin_Diagnostic', 'render' ) );
        }
    }

    public static function activate() {
        Faluss_Identity_Schema::install_or_verify();
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'faluss-identity', false, dirname( plugin_basename( FALUSS_IDENTITY_FILE ) ) . '/languages' );
    }
}
