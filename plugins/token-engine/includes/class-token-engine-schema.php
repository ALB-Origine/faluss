<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Versioned, additive schema installer for the generic engine and private connector access. */
final class Token_Engine_Schema {
    const OPTION = 'token_engine_schema_version';
    const VERSION = '2';
    const LEGACY_VERSION = '1';

    public static function projects_table() { return self::table( 'token_engine_projects' ); }
    public static function rules_table() { return self::table( 'token_engine_rules' ); }
    public static function ledger_table() { return self::table( 'token_engine_ledger' ); }
    public static function connector_tokens_table() { return self::table( 'token_engine_connector_tokens' ); }

    public static function maybe_install() { return self::install(); }

    /** Activation fails closed when an existing schema is incomplete or divergent. */
    public static function activate() {
        if ( ! self::install() ) {
            wp_die( esc_html__( 'Le schéma Token Engine ne peut pas être installé sans risque.', 'token-engine' ) );
        }
    }

    public static function is_ready() {
        return self::VERSION === get_option( self::OPTION ) && self::current_schema_ready();
    }

    public static function install() {
        global $wpdb;
        if ( ! self::valid_prefix() || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
            return false;
        }
        if ( self::current_schema_ready() ) {
            update_option( self::OPTION, self::VERSION, false );
            return true;
        }
        $lock = 'token_engine_' . substr( hash( 'sha256', (string) $wpdb->prefix ), 0, 32 );
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $lock, 10 ) ) ) {
            return false;
        }
        try {
            if ( self::current_schema_ready() ) {
                update_option( self::OPTION, self::VERSION, false );
                return true;
            }
            if ( self::legacy_schema_ready() ) {
                if ( ! self::migrate_v1_to_v2() || ! self::current_schema_ready() ) {
                    return false;
                }
                update_option( self::OPTION, self::VERSION, false );
                return true;
            }
            if ( self::existing_tables( self::tables() ) ) {
                return false;
            }
            foreach ( self::tables() as $name => $table ) {
                if ( false === $wpdb->query( self::create_query( $name, $table, self::VERSION ) ) ) {
                    return false;
                }
            }
            if ( ! self::current_schema_ready() ) {
                return false;
            }
            update_option( self::OPTION, self::VERSION, false );
            return true;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    private static function migrate_v1_to_v2() {
        global $wpdb;
        $projects = self::projects_table();
        $alter = 'ALTER TABLE `' . $projects . '` ADD COLUMN `connector_client_id` varchar(64) NULL AFTER `active`, ADD COLUMN `connector_secret_hash` varchar(255) NULL AFTER `connector_client_id`, ADD COLUMN `connector_secret_version` char(36) NULL AFTER `connector_secret_hash`, ADD COLUMN `connector_permissions` varchar(191) NULL AFTER `connector_secret_version`, ADD UNIQUE KEY `connector_client_id_unique` (`connector_client_id`)';
        if ( false === $wpdb->query( $alter ) ) {
            return false;
        }
        return false !== $wpdb->query( self::create_query( 'connector_tokens', self::connector_tokens_table(), self::VERSION ) );
    }

    private static function current_schema_ready() {
        $tables = self::tables();
        $existing = self::existing_tables( $tables );
        if ( count( $existing ) !== count( $tables ) ) {
            return false;
        }
        foreach ( $existing as $name => $table ) {
            if ( ! self::verify_table( $name, $table, self::VERSION ) ) {
                return false;
            }
        }
        return true;
    }

    private static function legacy_schema_ready() {
        if ( self::LEGACY_VERSION !== get_option( self::OPTION ) ) {
            return false;
        }
        $tables = self::legacy_tables();
        $existing = self::existing_tables( $tables );
        if ( count( $existing ) !== count( $tables ) ) {
            return false;
        }
        foreach ( $existing as $name => $table ) {
            if ( ! self::verify_table( $name, $table, self::LEGACY_VERSION ) ) {
                return false;
            }
        }
        return true;
    }

    private static function tables() {
        return array(
            'projects' => self::projects_table(),
            'rules' => self::rules_table(),
            'ledger' => self::ledger_table(),
            'connector_tokens' => self::connector_tokens_table(),
        );
    }

    private static function legacy_tables() {
        return array( 'projects' => self::projects_table(), 'rules' => self::rules_table(), 'ledger' => self::ledger_table() );
    }

    private static function table( $suffix ) {
        global $wpdb;
        return self::valid_prefix() ? $wpdb->prefix . $suffix : '';
    }

    private static function valid_prefix() {
        global $wpdb;
        return is_object( $wpdb ) && preg_match( '/^[A-Za-z0-9_]+$/', $wpdb->prefix ?? '' );
    }

    private static function existing_tables( $tables ) {
        $existing = array();
        foreach ( $tables as $name => $table ) {
            if ( '' !== $table && self::exists( $table ) ) {
                $existing[ $name ] = $table;
            }
        }
        return $existing;
    }

    private static function exists( $table ) {
        global $wpdb;
        return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    private static function create_query( $name, $table, $version ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        if ( 'projects' === $name ) {
            $connector_columns = self::VERSION === $version ? ',`connector_client_id` varchar(64) NULL,`connector_secret_hash` varchar(255) NULL,`connector_secret_version` char(36) NULL,`connector_permissions` varchar(191) NULL,UNIQUE KEY `connector_client_id_unique` (`connector_client_id`)' : '';
            return 'CREATE TABLE `' . $table . '` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`project_key` varchar(64) NOT NULL,`name` varchar(120) NOT NULL,`active` tinyint(1) NOT NULL' . $connector_columns . ',`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `project_key_unique` (`project_key`),KEY `project_active` (`active`)) ENGINE=InnoDB ' . $charset;
        }
        if ( 'rules' === $name ) {
            return 'CREATE TABLE `' . $table . '` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`rule_key` varchar(96) NOT NULL,`project_id` bigint(20) unsigned NULL,`scope` varchar(20) NOT NULL,`trigger_type` varchar(20) NOT NULL,`periodicity` varchar(20) NOT NULL,`cooldown_seconds` int(10) unsigned NULL,`amount` bigint(20) unsigned NOT NULL,`active` tinyint(1) NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `rule_key_unique` (`rule_key`),KEY `rule_project` (`project_id`),KEY `rule_scope_active` (`scope`,`active`)) ENGINE=InnoDB ' . $charset;
        }
        if ( 'ledger' === $name ) {
            return 'CREATE TABLE `' . $table . '` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`transaction_uuid` char(36) NOT NULL,`subject_id` varchar(191) NOT NULL,`project_key` varchar(64) NOT NULL,`rule_key` varchar(96) NULL,`direction` varchar(10) NOT NULL,`amount` bigint(20) unsigned NOT NULL,`idempotency_key` varchar(191) NOT NULL,`source_reference` varchar(191) NULL,`metadata` longtext NULL,`created_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `transaction_uuid_unique` (`transaction_uuid`),UNIQUE KEY `idempotency_key_unique` (`idempotency_key`),KEY `ledger_subject_project_date` (`subject_id`,`project_key`,`created_at`),KEY `ledger_project_rule_date` (`project_key`,`rule_key`,`created_at`),KEY `ledger_rule_date` (`rule_key`,`created_at`),KEY `ledger_created_at` (`created_at`)) ENGINE=InnoDB ' . $charset;
        }
        return 'CREATE TABLE `' . $table . '` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`token_hash` char(64) NOT NULL,`project_key` varchar(64) NOT NULL,`secret_version` char(36) NOT NULL,`permissions` varchar(191) NOT NULL,`expires_at` datetime NOT NULL,`created_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `token_hash_unique` (`token_hash`),KEY `token_project_expires` (`project_key`,`expires_at`),KEY `token_expires` (`expires_at`)) ENGINE=InnoDB ' . $charset;
    }

    private static function verify_table( $name, $table, $version ) {
        global $wpdb;
        $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ), ARRAY_A );
        if ( ! is_array( $status ) || 0 !== strcasecmp( 'InnoDB', $status['Engine'] ?? '' ) ) {
            return false;
        }
        $columns = self::columns( $name, $version );
        $actual = $wpdb->get_results( 'SHOW FULL COLUMNS FROM `' . $table . '`', ARRAY_A );
        if ( ! is_array( $actual ) || count( $actual ) !== count( $columns ) ) {
            return false;
        }
        foreach ( $actual as $column ) {
            $field = $column['Field'] ?? '';
            if ( ! isset( $columns[ $field ] ) || strtolower( $column['Type'] ?? '' ) !== $columns[ $field ][0] || strtoupper( $column['Null'] ?? '' ) !== $columns[ $field ][1] ) {
                return false;
            }
        }
        $found = array();
        foreach ( (array) $wpdb->get_results( 'SHOW INDEX FROM `' . $table . '`', ARRAY_A ) as $index ) {
            $key = $index['Key_name'] ?? '';
            if ( '' === $key ) { return false; }
            $found[ $key ][] = $index;
        }
        $needed = self::indexes( $name, $version );
        if ( count( $found ) !== count( $needed ) || array_diff( array_keys( $needed ), array_keys( $found ) ) ) {
            return false;
        }
        foreach ( $needed as $key => $expected ) {
            if ( count( $found[ $key ] ) !== count( $expected['columns'] ) ) { return false; }
            usort( $found[ $key ], static function( $left, $right ) { return (int) $left['Seq_in_index'] <=> (int) $right['Seq_in_index']; } );
            foreach ( $found[ $key ] as $position => $index ) {
                if ( (int) $index['Non_unique'] !== ( $expected['unique'] ? 0 : 1 ) || $expected['columns'][ $position ] !== ( $index['Column_name'] ?? '' ) ) { return false; }
            }
        }
        return true;
    }

    private static function columns( $name, $version ) {
        if ( 'projects' === $name ) {
            $columns = array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'project_key' => array( 'varchar(64)', 'NO' ), 'name' => array( 'varchar(120)', 'NO' ), 'active' => array( 'tinyint(1)', 'NO' ) );
            if ( self::VERSION === $version ) {
                $columns += array( 'connector_client_id' => array( 'varchar(64)', 'YES' ), 'connector_secret_hash' => array( 'varchar(255)', 'YES' ), 'connector_secret_version' => array( 'char(36)', 'YES' ), 'connector_permissions' => array( 'varchar(191)', 'YES' ) );
            }
            return $columns + array( 'created_at' => array( 'datetime', 'NO' ), 'updated_at' => array( 'datetime', 'NO' ) );
        }
        if ( 'rules' === $name ) {
            return array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'rule_key' => array( 'varchar(96)', 'NO' ), 'project_id' => array( 'bigint(20) unsigned', 'YES' ), 'scope' => array( 'varchar(20)', 'NO' ), 'trigger_type' => array( 'varchar(20)', 'NO' ), 'periodicity' => array( 'varchar(20)', 'NO' ), 'cooldown_seconds' => array( 'int(10) unsigned', 'YES' ), 'amount' => array( 'bigint(20) unsigned', 'NO' ), 'active' => array( 'tinyint(1)', 'NO' ), 'created_at' => array( 'datetime', 'NO' ), 'updated_at' => array( 'datetime', 'NO' ) );
        }
        if ( 'ledger' === $name ) {
            return array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'transaction_uuid' => array( 'char(36)', 'NO' ), 'subject_id' => array( 'varchar(191)', 'NO' ), 'project_key' => array( 'varchar(64)', 'NO' ), 'rule_key' => array( 'varchar(96)', 'YES' ), 'direction' => array( 'varchar(10)', 'NO' ), 'amount' => array( 'bigint(20) unsigned', 'NO' ), 'idempotency_key' => array( 'varchar(191)', 'NO' ), 'source_reference' => array( 'varchar(191)', 'YES' ), 'metadata' => array( 'longtext', 'YES' ), 'created_at' => array( 'datetime', 'NO' ) );
        }
        return array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'token_hash' => array( 'char(64)', 'NO' ), 'project_key' => array( 'varchar(64)', 'NO' ), 'secret_version' => array( 'char(36)', 'NO' ), 'permissions' => array( 'varchar(191)', 'NO' ), 'expires_at' => array( 'datetime', 'NO' ), 'created_at' => array( 'datetime', 'NO' ) );
    }

    private static function indexes( $name, $version ) {
        if ( 'projects' === $name ) {
            $indexes = array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'project_key_unique' => array( 'unique' => true, 'columns' => array( 'project_key' ) ), 'project_active' => array( 'unique' => false, 'columns' => array( 'active' ) ) );
            if ( self::VERSION === $version ) { $indexes['connector_client_id_unique'] = array( 'unique' => true, 'columns' => array( 'connector_client_id' ) ); }
            return $indexes;
        }
        if ( 'rules' === $name ) { return array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'rule_key_unique' => array( 'unique' => true, 'columns' => array( 'rule_key' ) ), 'rule_project' => array( 'unique' => false, 'columns' => array( 'project_id' ) ), 'rule_scope_active' => array( 'unique' => false, 'columns' => array( 'scope', 'active' ) ) ); }
        if ( 'ledger' === $name ) { return array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'transaction_uuid_unique' => array( 'unique' => true, 'columns' => array( 'transaction_uuid' ) ), 'idempotency_key_unique' => array( 'unique' => true, 'columns' => array( 'idempotency_key' ) ), 'ledger_subject_project_date' => array( 'unique' => false, 'columns' => array( 'subject_id', 'project_key', 'created_at' ) ), 'ledger_project_rule_date' => array( 'unique' => false, 'columns' => array( 'project_key', 'rule_key', 'created_at' ) ), 'ledger_rule_date' => array( 'unique' => false, 'columns' => array( 'rule_key', 'created_at' ) ), 'ledger_created_at' => array( 'unique' => false, 'columns' => array( 'created_at' ) ) ); }
        return array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'token_hash_unique' => array( 'unique' => true, 'columns' => array( 'token_hash' ) ), 'token_project_expires' => array( 'unique' => false, 'columns' => array( 'project_key', 'expires_at' ) ), 'token_expires' => array( 'unique' => false, 'columns' => array( 'expires_at' ) ) );
    }
}
