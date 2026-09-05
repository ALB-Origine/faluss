<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Additive settings migration; it never touches local credentials or the Core. */
final class Token_Engine_Connector_Upgrade {
    const OPTION = 'token_engine_connector_settings';
    const VERSION_OPTION = 'token_engine_connector_settings_version';
    const VERSION = '2';

    public static function activate() {
        self::maybe_upgrade();
    }

    public static function maybe_upgrade() {
        $stored = get_option( self::OPTION, array() );
        $stored = is_array( $stored ) ? $stored : array();

        if ( ! array_key_exists( 'core_site_url', $stored ) && isset( $stored['core_url'] ) ) {
            $site_url = Token_Engine_Connector_Service::migrate_legacy_site_url( $stored['core_url'] );
            if ( '' !== $site_url ) {
                $stored['core_site_url'] = $site_url;
                unset( $stored['core_url'] );
                update_option( self::OPTION, $stored, false );
            }
        }

        if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) {
            update_option( self::VERSION_OPTION, self::VERSION, false );
        }
    }
}
