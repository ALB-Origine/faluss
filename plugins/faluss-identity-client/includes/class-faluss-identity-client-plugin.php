<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Faluss_Identity_Client_Plugin {

    public static function boot() {
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
    }

    public static function activate() {
        // FI-05 adds verified local-link migrations here.
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'faluss-identity-client', false, dirname( plugin_basename( FALUSS_IDENTITY_CLIENT_FILE ) ) . '/languages' );
    }
}
