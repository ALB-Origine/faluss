<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Faluss_Identity_Plugin {

    public static function boot() {
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
    }

    public static function activate() {
        // FI-01 adds verified migrations here. Activation must not create partial schema.
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'faluss-identity', false, dirname( plugin_basename( FALUSS_IDENTITY_FILE ) ) . '/languages' );
    }
}
