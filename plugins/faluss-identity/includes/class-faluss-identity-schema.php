<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Faluss_Identity_Schema {

    const VERSION = '1';
    const OPTION_VERSION = 'faluss_identity_schema_version';
    const OPTION_DIAGNOSTIC = 'faluss_identity_schema_diagnostic';

    /**
     * The FI-01 schema contract contains structure only and no member data.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_expected_schema() {
        return array(
            'profiles' => array(
                'suffix' => 'faluss_identity_profiles',
                'columns' => array(
                    'id' => array( 'type' => 'bigint(20) unsigned', 'null' => false, 'auto_increment' => true ),
                    'faluss_id' => array( 'type' => 'char(36)', 'null' => false ),
                    'wp_user_id' => array( 'type' => 'bigint(20) unsigned', 'null' => false ),
                    'status' => array( 'type' => 'varchar(20)', 'null' => false ),
                    'consent_version' => array( 'type' => 'varchar(64)', 'null' => true ),
                    'created_at' => array( 'type' => 'datetime', 'null' => false ),
                    'updated_at' => array( 'type' => 'datetime', 'null' => true ),
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ),
                    'faluss_id_unique' => array( 'unique' => true, 'columns' => array( 'faluss_id' ) ),
                    'wp_user_id_unique' => array( 'unique' => true, 'columns' => array( 'wp_user_id' ) ),
                    'status_created_at' => array( 'unique' => false, 'columns' => array( 'status', 'created_at' ) ),
                ),
            ),
            'challenges' => array(
                'suffix' => 'faluss_identity_challenges',
                'columns' => array(
                    'id' => array( 'type' => 'bigint(20) unsigned', 'null' => false, 'auto_increment' => true ),
                    'challenge_hash' => array( 'type' => 'char(64)', 'null' => false ),
                    'otp_hash' => array( 'type' => 'char(64)', 'null' => true ),
                    'browser_fingerprint_hash' => array( 'type' => 'char(64)', 'null' => true ),
                    'attempt_count' => array( 'type' => 'tinyint(3) unsigned', 'null' => false ),
                    'status' => array( 'type' => 'varchar(20)', 'null' => false ),
                    'expires_at' => array( 'type' => 'datetime', 'null' => false ),
                    'consumed_at' => array( 'type' => 'datetime', 'null' => true ),
                    'created_at' => array( 'type' => 'datetime', 'null' => false ),
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ),
                    'challenge_hash_unique' => array( 'unique' => true, 'columns' => array( 'challenge_hash' ) ),
                    'status_expires_at' => array( 'unique' => false, 'columns' => array( 'status', 'expires_at' ) ),
                ),
            ),
            'rate_limits' => array(
                'suffix' => 'faluss_identity_rate_limits',
                'columns' => array(
                    'id' => array( 'type' => 'bigint(20) unsigned', 'null' => false, 'auto_increment' => true ),
                    'bucket_type' => array( 'type' => 'varchar(32)', 'null' => false ),
                    'bucket_hash' => array( 'type' => 'char(64)', 'null' => false ),
                    'attempt_count' => array( 'type' => 'int(10) unsigned', 'null' => false ),
                    'window_started_at' => array( 'type' => 'datetime', 'null' => false ),
                    'expires_at' => array( 'type' => 'datetime', 'null' => false ),
                    'updated_at' => array( 'type' => 'datetime', 'null' => false ),
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ),
                    'bucket_type_hash_unique' => array( 'unique' => true, 'columns' => array( 'bucket_type', 'bucket_hash' ) ),
                    'expires_at' => array( 'unique' => false, 'columns' => array( 'expires_at' ) ),
                ),
            ),
            'clients' => array(
                'suffix' => 'faluss_identity_clients',
                'columns' => array(
                    'id' => array( 'type' => 'bigint(20) unsigned', 'null' => false, 'auto_increment' => true ),
                    'client_id' => array( 'type' => 'varchar(191)', 'null' => false ),
                    'client_name' => array( 'type' => 'varchar(191)', 'null' => false ),
                    'status' => array( 'type' => 'varchar(20)', 'null' => false ),
                    'client_secret_hash' => array( 'type' => 'char(64)', 'null' => true ),
                    'allowed_scopes' => array( 'type' => 'varchar(255)', 'null' => false ),
                    'redirect_uris' => array( 'type' => 'longtext', 'null' => false ),
                    'created_at' => array( 'type' => 'datetime', 'null' => false ),
                    'updated_at' => array( 'type' => 'datetime', 'null' => true ),
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ),
                    'client_id_unique' => array( 'unique' => true, 'columns' => array( 'client_id' ) ),
                    'status' => array( 'unique' => false, 'columns' => array( 'status' ) ),
                ),
            ),
            'auth_codes' => array(
                'suffix' => 'faluss_identity_auth_codes',
                'columns' => array(
                    'id' => array( 'type' => 'bigint(20) unsigned', 'null' => false, 'auto_increment' => true ),
                    'code_hash' => array( 'type' => 'char(64)', 'null' => false ),
                    'faluss_id' => array( 'type' => 'char(36)', 'null' => false ),
                    'client_id' => array( 'type' => 'varchar(191)', 'null' => false ),
                    'redirect_uri' => array( 'type' => 'varchar(2048)', 'null' => false ),
                    'pkce_challenge' => array( 'type' => 'char(43)', 'null' => false ),
                    'scopes' => array( 'type' => 'varchar(255)', 'null' => false ),
                    'expires_at' => array( 'type' => 'datetime', 'null' => false ),
                    'consumed_at' => array( 'type' => 'datetime', 'null' => true ),
                    'created_at' => array( 'type' => 'datetime', 'null' => false ),
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ),
                    'code_hash_unique' => array( 'unique' => true, 'columns' => array( 'code_hash' ) ),
                    'client_expires_at' => array( 'unique' => false, 'columns' => array( 'client_id', 'expires_at' ) ),
                ),
            ),
            'audit' => array(
                'suffix' => 'faluss_identity_audit',
                'columns' => array(
                    'id' => array( 'type' => 'bigint(20) unsigned', 'null' => false, 'auto_increment' => true ),
                    'event_id' => array( 'type' => 'char(36)', 'null' => false ),
                    'event_type' => array( 'type' => 'varchar(64)', 'null' => false ),
                    'client_id' => array( 'type' => 'varchar(191)', 'null' => true ),
                    'occurred_at' => array( 'type' => 'datetime', 'null' => false ),
                    'expires_at' => array( 'type' => 'datetime', 'null' => false ),
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ),
                    'event_id_unique' => array( 'unique' => true, 'columns' => array( 'event_id' ) ),
                    'expires_at' => array( 'unique' => false, 'columns' => array( 'expires_at' ) ),
                ),
            ),
        );
    }

    /**
     * Creates a complete empty schema or verifies an existing complete schema.
     * A partial or incompatible schema is never repaired or deleted.
     *
     * @return bool
     */
    public static function install_or_verify() {
        $status = self::get_status();
        if ( 'fi_schema_ready' === $status['code'] ) {
            self::store_diagnostic( $status['code'] );
            return true;
        }
        if ( 'fi_schema_missing' !== $status['code'] ) {
            self::store_diagnostic( $status['code'] );
            return false;
        }
        if ( ! self::create_all_tables() ) {
            self::store_diagnostic( 'fi_schema_create_failed' );
            return false;
        }

        $status = self::get_status();
        self::store_diagnostic( $status['code'] );
        return 'fi_schema_ready' === $status['code'];
    }

    /**
     * @return array{ready: bool, code: string}
     */
    public static function get_status() {
        global $wpdb;

        if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) || ! method_exists( $wpdb, 'get_var' ) ) {
            return self::status( false, 'fi_schema_unverifiable' );
        }
        $tables = self::get_table_names();
        if ( empty( $tables ) ) {
            return self::status( false, 'fi_schema_prefix_invalid' );
        }

        $existing = 0;
        foreach ( $tables as $table ) {
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( null === $found && ! empty( $wpdb->last_error ) ) {
                return self::status( false, 'fi_schema_unverifiable' );
            }
            if ( $table === $found ) {
                ++$existing;
            }
        }
        if ( 0 === $existing ) {
            return self::status( false, 'fi_schema_missing' );
        }
        if ( count( $tables ) !== $existing ) {
            return self::status( false, 'fi_schema_partial' );
        }

        foreach ( self::get_expected_schema() as $key => $definition ) {
            $code = self::verify_table( $tables[ $key ], $definition );
            if ( null !== $code ) {
                return self::status( false, $code );
            }
        }
        return self::status( true, 'fi_schema_ready' );
    }

    private static function verify_table( $table, $definition ) {
        global $wpdb;

        $table_status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ), ARRAY_A );
        if ( ! is_array( $table_status ) || empty( $table_status['Engine'] ) ) {
            return 'fi_schema_unverifiable';
        }
        if ( 0 !== strcasecmp( 'InnoDB', $table_status['Engine'] ) ) {
            return 'fi_schema_unexpected';
        }

        $columns = $wpdb->get_results( 'SHOW FULL COLUMNS FROM ' . self::quote_identifier( $table ), ARRAY_A );
        if ( ! is_array( $columns ) || count( $columns ) !== count( $definition['columns'] ) ) {
            return 'fi_schema_unexpected';
        }
        $actual_columns = array();
        foreach ( $columns as $column ) {
            if ( empty( $column['Field'] ) ) {
                return 'fi_schema_unverifiable';
            }
            $actual_columns[ $column['Field'] ] = $column;
        }
        foreach ( $definition['columns'] as $name => $expected ) {
            if ( ! isset( $actual_columns[ $name ] ) ) {
                return 'fi_schema_unexpected';
            }
            $actual = $actual_columns[ $name ];
            $is_auto_increment = isset( $actual['Extra'] ) && false !== strpos( strtolower( $actual['Extra'] ), 'auto_increment' );
            if ( ! self::types_match( $expected['type'], $actual['Type'] ) || $expected['null'] !== ( 'YES' === $actual['Null'] ) || ( ! empty( $expected['auto_increment'] ) ) !== $is_auto_increment ) {
                return 'fi_schema_unexpected';
            }
        }

        $index_rows = $wpdb->get_results( 'SHOW INDEX FROM ' . self::quote_identifier( $table ), ARRAY_A );
        if ( ! is_array( $index_rows ) ) {
            return 'fi_schema_unverifiable';
        }
        $actual_indexes = array();
        foreach ( $index_rows as $index ) {
            if ( ! isset( $index['Key_name'], $index['Seq_in_index'], $index['Column_name'], $index['Non_unique'] ) ) {
                return 'fi_schema_unverifiable';
            }
            $name = $index['Key_name'];
            if ( ! isset( $actual_indexes[ $name ] ) ) {
                $actual_indexes[ $name ] = array( 'unique' => '0' === (string) $index['Non_unique'], 'columns' => array() );
            }
            $actual_indexes[ $name ]['columns'][ (int) $index['Seq_in_index'] ] = $index['Column_name'];
        }
        if ( count( $actual_indexes ) !== count( $definition['indexes'] ) ) {
            return 'fi_schema_unexpected';
        }
        foreach ( $definition['indexes'] as $name => $expected ) {
            if ( ! isset( $actual_indexes[ $name ] ) ) {
                return 'fi_schema_unexpected';
            }
            $actual = $actual_indexes[ $name ];
            ksort( $actual['columns'] );
            if ( $expected['unique'] !== $actual['unique'] || $expected['columns'] !== array_values( $actual['columns'] ) ) {
                return 'fi_schema_unexpected';
            }
        }
        return null;
    }

    private static function create_all_tables() {
        global $wpdb;

        $tables = self::get_table_names();
        if ( empty( $tables ) || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
            return false;
        }
        foreach ( self::get_expected_schema() as $key => $definition ) {
            $query = self::build_create_query( $tables[ $key ], $definition, $wpdb->get_charset_collate() );
            if ( false === $wpdb->query( $query ) ) {
                return false;
            }
        }
        return true;
    }

    private static function build_create_query( $table, $definition, $charset_collate ) {
        $lines = array();
        foreach ( $definition['columns'] as $name => $column ) {
            $line = self::quote_identifier( $name ) . ' ' . $column['type'] . ( $column['null'] ? ' NULL' : ' NOT NULL' );
            if ( ! empty( $column['auto_increment'] ) ) {
                $line .= ' AUTO_INCREMENT';
            }
            $lines[] = $line;
        }
        foreach ( $definition['indexes'] as $name => $index ) {
            $columns = implode( ', ', array_map( array( __CLASS__, 'quote_identifier' ), $index['columns'] ) );
            if ( 'PRIMARY' === $name ) {
                $lines[] = 'PRIMARY KEY (' . $columns . ')';
            } elseif ( $index['unique'] ) {
                $lines[] = 'UNIQUE KEY ' . self::quote_identifier( $name ) . ' (' . $columns . ')';
            } else {
                $lines[] = 'KEY ' . self::quote_identifier( $name ) . ' (' . $columns . ')';
            }
        }
        return 'CREATE TABLE ' . self::quote_identifier( $table ) . ' (' . implode( ', ', $lines ) . ') ENGINE=InnoDB ' . $charset_collate;
    }

    /**
     * @return array<string, string>
     */
    public static function get_table_names() {
        global $wpdb;

        if ( ! is_object( $wpdb ) || empty( $wpdb->prefix ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $wpdb->prefix ) ) {
            return array();
        }
        $tables = array();
        foreach ( self::get_expected_schema() as $key => $definition ) {
            $tables[ $key ] = $wpdb->prefix . $definition['suffix'];
        }
        return $tables;
    }

    private static function quote_identifier( $identifier ) {
        return chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $identifier ) . chr( 96 );
    }

    private static function types_match( $expected, $actual ) {
        $expected = strtolower( $expected );
        $actual = strtolower( $actual );
        if ( $expected === $actual ) {
            return true;
        }

        // MySQL 8 omits deprecated integer display widths; MariaDB may retain them.
        $integer_pattern = '/^(?:tinyint|smallint|mediumint|int|bigint)(?:\([0-9]+\))?(?: unsigned)?$/';
        if ( 1 === preg_match( $integer_pattern, $expected ) && 1 === preg_match( $integer_pattern, $actual ) ) {
            return preg_replace( '/\([0-9]+\)/', '', $expected ) === preg_replace( '/\([0-9]+\)/', '', $actual );
        }

        return false;
    }

    private static function store_diagnostic( $code ) {
        update_option( self::OPTION_DIAGNOSTIC, $code, false );
        if ( 'fi_schema_ready' === $code ) {
            update_option( self::OPTION_VERSION, self::VERSION, false );
        }
    }

    private static function status( $ready, $code ) {
        return array( 'ready' => $ready, 'code' => $code );
    }
}
