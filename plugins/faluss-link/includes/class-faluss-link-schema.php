<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Faluss_Link_Schema {
    const OPTION = 'faluss_link_schema_version';
    const VERSION = '2';

    public static function table() { global $wpdb; return self::valid_prefix() ? $wpdb->prefix . 'faluss_link_cards' : ''; }
    public static function blocks_table() { global $wpdb; return self::valid_prefix() ? $wpdb->prefix . 'faluss_link_blocks' : ''; }
    public static function maybe_install() { return self::VERSION === get_option( self::OPTION ) || self::install(); }

    public static function install() {
        global $wpdb;
        $cards = self::table(); $blocks = self::blocks_table();
        if ( '' === $cards || '' === $blocks || ! method_exists( $wpdb, 'get_charset_collate' ) ) { return false; }
        $has_cards = self::exists( $cards ); $has_blocks = self::exists( $blocks );
        if ( $has_cards && ! self::verify_cards( $cards ) ) { return false; }
        if ( $has_blocks && ! self::verify_blocks( $blocks ) ) { return false; }
        /* A blocks table without the pre-existing cards table is a partial installation: never repair it. */
        if ( $has_blocks && ! $has_cards ) { return false; }
        if ( $has_cards && $has_blocks ) { update_option( self::OPTION, self::VERSION, false ); return true; }
        $lock = 'faluss_link_' . substr( hash( 'sha256', $wpdb->prefix ), 0, 32 );
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $lock, 10 ) ) ) { return false; }
        try {
            if ( self::exists( $blocks ) && ! self::exists( $cards ) ) { return false; }
            if ( ! self::exists( $cards ) && false === $wpdb->query( self::cards_query( $cards ) ) ) { return false; }
            if ( ! self::exists( $blocks ) && false === $wpdb->query( self::blocks_query( $blocks ) ) ) { return false; }
            if ( ! self::verify_cards( $cards ) || ! self::verify_blocks( $blocks ) ) { return false; }
            update_option( self::OPTION, self::VERSION, false );
            return true;
        } finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
    }

    private static function valid_prefix() { global $wpdb; return is_object( $wpdb ) && preg_match( '/^[A-Za-z0-9_]+$/', $wpdb->prefix ?? '' ); }
    private static function exists( $table ) { global $wpdb; return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); }
    private static function cards_query( $table ) { global $wpdb; return 'CREATE TABLE `' . $table . '` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`faluss_id` char(36) NOT NULL,`cover_attachment_id` bigint(20) unsigned NULL,`avatar_visible` tinyint(1) NOT NULL,`name_weight` varchar(20) NOT NULL,`name_treatment` varchar(20) NOT NULL,`available` tinyint(1) NOT NULL,`bio_mode` varchar(20) NOT NULL,`announcement` varchar(120) NULL,`announcement_variant` varchar(20) NOT NULL,`social_links` longtext NOT NULL,`social_layout` varchar(20) NOT NULL,`link_style` varchar(20) NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `faluss_id_unique` (`faluss_id`)) ENGINE=InnoDB ' . $wpdb->get_charset_collate(); }
    private static function blocks_query( $table ) { global $wpdb; return 'CREATE TABLE `' . $table . '` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`faluss_id` char(36) NOT NULL,`block_id` char(36) NOT NULL,`sort_order` smallint(5) unsigned NOT NULL,`block_type` varchar(20) NOT NULL,`payload` longtext NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `faluss_block_id` (`faluss_id`,`block_id`),UNIQUE KEY `faluss_block_order` (`faluss_id`,`sort_order`)) ENGINE=InnoDB ' . $wpdb->get_charset_collate(); }
    private static function verify_cards( $table ) { return self::verify_table( $table, array( 'id' => 'bigint(20) unsigned', 'faluss_id' => 'char(36)', 'cover_attachment_id' => 'bigint(20) unsigned', 'avatar_visible' => 'tinyint(1)', 'name_weight' => 'varchar(20)', 'name_treatment' => 'varchar(20)', 'available' => 'tinyint(1)', 'bio_mode' => 'varchar(20)', 'announcement' => 'varchar(120)', 'announcement_variant' => 'varchar(20)', 'social_links' => 'longtext', 'social_layout' => 'varchar(20)', 'link_style' => 'varchar(20)', 'created_at' => 'datetime', 'updated_at' => 'datetime' ), array( 'PRIMARY', 'faluss_id_unique' ) ); }
    private static function verify_blocks( $table ) { return self::verify_table( $table, array( 'id' => 'bigint(20) unsigned', 'faluss_id' => 'char(36)', 'block_id' => 'char(36)', 'sort_order' => 'smallint(5) unsigned', 'block_type' => 'varchar(20)', 'payload' => 'longtext', 'created_at' => 'datetime', 'updated_at' => 'datetime' ), array( 'PRIMARY', 'faluss_block_id', 'faluss_block_order' ) ); }
    private static function verify_table( $table, $needed, $indexes ) {
        global $wpdb;
        $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ), ARRAY_A );
        if ( ! is_array( $status ) || 0 !== strcasecmp( 'InnoDB', $status['Engine'] ?? '' ) ) { return false; }
        $columns = $wpdb->get_results( 'SHOW FULL COLUMNS FROM `' . $table . '`', ARRAY_A );
        if ( ! is_array( $columns ) || count( $columns ) !== count( $needed ) ) { return false; }
        foreach ( $columns as $column ) { if ( ! isset( $needed[ $column['Field'] ] ) || strtolower( $column['Type'] ) !== $needed[ $column['Field'] ] ) { return false; } }
        $found = array_unique( array_column( (array) $wpdb->get_results( 'SHOW INDEX FROM `' . $table . '`', ARRAY_A ), 'Key_name' ) );
        return count( $found ) === count( $indexes ) && ! array_diff( $indexes, $found );
    }
}
