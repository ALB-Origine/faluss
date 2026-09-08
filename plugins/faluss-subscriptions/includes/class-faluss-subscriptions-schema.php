<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Atomic, verified, additive installation of the subscription authority tables. */
final class Faluss_Subscriptions_Schema {
    const OPTION = 'faluss_subscriptions_schema_version';
    const VERSION = '1';

    public static function subscriptions_table() { return self::table( 'faluss_subscriptions' ); }
    public static function trials_table() { return self::table( 'faluss_subscription_trials' ); }
    public static function entitlements_table() { return self::table( 'faluss_entitlements' ); }
    public static function events_table() { return self::table( 'faluss_subscription_events' ); }
    public static function audit_table() { return self::table( 'faluss_subscription_audit' ); }

    public static function maybe_install() { return self::install(); }

    public static function activate() {
        if ( ! self::install() ) {
            wp_die( esc_html__( 'Le schéma Faluss Subscriptions ne peut pas être installé sans risque.', 'faluss-subscriptions' ) );
        }
    }

    /** Deactivation intentionally preserves subscriptions, audit records and diagnostic evidence. */
    public static function deactivate() {}

    public static function is_ready() {
        return self::VERSION === (string) get_option( self::OPTION ) && self::current_schema_ready();
    }

    /** Re-running installation is safe. Partial or divergent existing schemas fail closed. */
    public static function install() {
        global $wpdb;
        if ( ! self::valid_prefix() || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
            return false;
        }
        if ( self::current_schema_ready() ) {
            update_option( self::OPTION, self::VERSION, false );
            return true;
        }
        $lock = 'faluss_sub_schema_' . substr( hash( 'sha256', (string) $wpdb->prefix ), 0, 32 );
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $lock, 10 ) ) ) {
            return false;
        }
        try {
            if ( self::current_schema_ready() ) {
                update_option( self::OPTION, self::VERSION, false );
                return true;
            }
            if ( self::existing_tables( self::tables() ) ) {
                return false;
            }
            try {
                $suffix = bin2hex( random_bytes( 8 ) );
            } catch ( Exception $exception ) {
                return false;
            }
            $plan = self::get_install_plan( $suffix );
            if ( ! is_array( $plan ) || self::existing_tables( $plan['temporary_tables'] ) ) {
                return false;
            }
            foreach ( $plan['temporary_tables'] as $name => $table ) {
                if ( false === $wpdb->query( self::create_query( $name, $table ) ) || ! self::verify_table( $name, $table ) ) {
                    return false;
                }
            }
            if ( false === $wpdb->query( self::promotion_query( $plan ) ) || ! self::current_schema_ready() ) {
                return false;
            }
            update_option( self::OPTION, self::VERSION, false );
            return true;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    /** @return array<string,mixed>|null */
    public static function get_install_plan( $suffix ) {
        if ( ! self::valid_prefix() || ! is_string( $suffix ) || 1 !== preg_match( '/^[a-f0-9]{16,64}$/', $suffix ) ) {
            return null;
        }
        $temporary = array();
        foreach ( self::tables() as $name => $table ) {
            $temporary[ $name ] = $table . '__fs_' . substr( $suffix, 0, 12 );
            if ( strlen( $temporary[ $name ] ) > 64 ) {
                return null;
            }
        }
        return array( 'final_tables' => self::tables(), 'temporary_tables' => $temporary );
    }

    public static function get_expected_schema() { return self::expected_schema(); }

    /** @return array<string,mixed> */
    public static function get_status() {
        return array(
            'version' => self::VERSION,
            'stored_version' => get_option( self::OPTION ),
            'ready' => self::is_ready(),
            'tables' => self::tables(),
            'inspection' => self::inspect_tables(),
        );
    }

    /**
     * Safe schema evidence for the administration diagnostics. It intentionally
     * contains only structure metadata, never database errors or member data.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function inspect_tables() {
        $inspection = array();
        foreach ( self::tables() as $name => $table ) {
            $inspection[ $name ] = self::inspect_table( $name, $table );
        }
        return $inspection;
    }

    public static function quote_identifier( $identifier ) {
        return chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), (string) $identifier ) . chr( 96 );
    }

    private static function table( $suffix ) {
        global $wpdb;
        return self::valid_prefix() ? $wpdb->prefix . $suffix : '';
    }

    private static function valid_prefix() {
        global $wpdb;
        return is_object( $wpdb ) && isset( $wpdb->prefix ) && 1 === preg_match( '/^[A-Za-z0-9_]+$/', (string) $wpdb->prefix );
    }

    /** @return array<string,string> */
    private static function tables() {
        return array(
            'subscriptions' => self::subscriptions_table(),
            'trials' => self::trials_table(),
            'entitlements' => self::entitlements_table(),
            'events' => self::events_table(),
            'audit' => self::audit_table(),
        );
    }

    /** @return array<string,string> */
    private static function existing_tables( $tables ) {
        $existing = array();
        foreach ( $tables as $name => $table ) {
            if ( '' !== $table && self::table_exists( $table ) ) {
                $existing[ $name ] = $table;
            }
        }
        return $existing;
    }

    private static function table_exists( $table ) {
        global $wpdb;
        return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    private static function promotion_query( $plan ) {
        $renames = array();
        foreach ( $plan['temporary_tables'] as $name => $temporary ) {
            $renames[] = self::quote_identifier( $temporary ) . ' TO ' . self::quote_identifier( $plan['final_tables'][ $name ] );
        }
        return 'RENAME TABLE ' . implode( ',', $renames );
    }

    private static function create_query( $name, $table ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        if ( 'subscriptions' === $name ) {
            return 'CREATE TABLE ' . self::quote_identifier( $table ) . ' (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`subscription_uuid` char(36) NOT NULL,`faluss_id` char(36) NOT NULL,`provider` varchar(32) NOT NULL,`provider_customer_reference` varchar(191) NULL,`provider_subscription_reference` varchar(191) NULL,`plan_key` varchar(32) NOT NULL,`billing_interval` varchar(16) NULL,`provider_status` varchar(32) NULL,`normalized_state` varchar(16) NOT NULL,`trial_starts_at` datetime NULL,`trial_ends_at` datetime NULL,`period_starts_at` datetime NULL,`period_ends_at` datetime NULL,`grace_ends_at` datetime NULL,`cancel_at_period_end` tinyint(1) NOT NULL,`cancelled_at` datetime NULL,`ended_at` datetime NULL,`last_synced_at` datetime NULL,`version` bigint(20) unsigned NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `subscription_uuid_unique` (`subscription_uuid`),UNIQUE KEY `provider_subscription_unique` (`provider`,`provider_subscription_reference`),KEY `subscription_faluss_state` (`faluss_id`,`normalized_state`,`period_ends_at`),KEY `subscription_provider_status` (`provider`,`provider_status`),KEY `subscription_updated` (`updated_at`)) ENGINE=InnoDB ' . $charset;
        }
        if ( 'trials' === $name ) {
            return 'CREATE TABLE ' . self::quote_identifier( $table ) . ' (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`trial_uuid` char(36) NOT NULL,`faluss_id` char(36) NOT NULL,`eligibility_status` varchar(24) NOT NULL,`trial_state` varchar(16) NOT NULL,`activated_at` datetime NULL,`expires_at` datetime NULL,`consumed_at` datetime NULL,`revoked_at` datetime NULL,`verification_reference` varchar(191) NULL,`payment_fingerprint_hash` char(64) NULL,`admin_override_reason` varchar(191) NULL,`admin_override_reference` varchar(191) NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `trial_uuid_unique` (`trial_uuid`),UNIQUE KEY `trial_faluss_unique` (`faluss_id`),UNIQUE KEY `trial_payment_fingerprint_unique` (`payment_fingerprint_hash`),UNIQUE KEY `trial_override_reference_unique` (`admin_override_reference`),KEY `trial_state_expiry` (`trial_state`,`expires_at`),KEY `trial_eligibility` (`eligibility_status`,`trial_state`)) ENGINE=InnoDB ' . $charset;
        }
        if ( 'entitlements' === $name ) {
            return 'CREATE TABLE ' . self::quote_identifier( $table ) . ' (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`entitlement_uuid` char(36) NOT NULL,`faluss_id` char(36) NOT NULL,`entitlement_key` varchar(120) NOT NULL,`entitlement_value` varchar(64) NOT NULL,`source` varchar(24) NOT NULL,`source_reference` varchar(191) NOT NULL,`priority` smallint(5) unsigned NOT NULL,`starts_at` datetime NOT NULL,`expires_at` datetime NULL,`status` varchar(16) NOT NULL,`version` bigint(20) unsigned NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `entitlement_uuid_unique` (`entitlement_uuid`),UNIQUE KEY `entitlement_source_reference_unique` (`source_reference`),KEY `entitlement_faluss_key_state` (`faluss_id`,`entitlement_key`,`status`,`starts_at`),KEY `entitlement_expiry` (`expires_at`),KEY `entitlement_source` (`source`,`status`)) ENGINE=InnoDB ' . $charset;
        }
        if ( 'events' === $name ) {
            return 'CREATE TABLE ' . self::quote_identifier( $table ) . ' (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`provider` varchar(32) NOT NULL,`provider_event_id` varchar(191) NOT NULL,`event_type` varchar(80) NOT NULL,`processing_status` varchar(24) NOT NULL,`attempt_count` bigint(20) unsigned NOT NULL,`payload_hash` char(64) NOT NULL,`received_at` datetime NOT NULL,`processed_at` datetime NULL,`last_error` varchar(191) NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `provider_event_unique` (`provider`,`provider_event_id`),KEY `event_processing` (`processing_status`,`received_at`),KEY `event_provider_type` (`provider`,`event_type`)) ENGINE=InnoDB ' . $charset;
        }
        return 'CREATE TABLE ' . self::quote_identifier( $table ) . ' (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`audit_uuid` char(36) NOT NULL,`actor_user_id` bigint(20) unsigned NULL,`action` varchar(80) NOT NULL,`faluss_id` char(36) NULL,`source` varchar(32) NOT NULL,`previous_state` longtext NULL,`next_state` longtext NULL,`justification` varchar(191) NULL,`created_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `audit_uuid_unique` (`audit_uuid`),KEY `audit_faluss_created` (`faluss_id`,`created_at`),KEY `audit_action_created` (`action`,`created_at`),KEY `audit_actor_created` (`actor_user_id`,`created_at`)) ENGINE=InnoDB ' . $charset;
    }

    private static function current_schema_ready() {
        foreach ( self::tables() as $name => $table ) {
            if ( ! self::table_exists( $table ) || ! self::verify_table( $name, $table ) ) {
                return false;
            }
        }
        return true;
    }

    private static function verify_table( $name, $table ) {
        $inspection = self::inspect_table( $name, $table );
        return ! empty( $inspection['ready'] );
    }

    /** @return array<string,mixed> */
    private static function inspect_table( $name, $table ) {
        global $wpdb;
        $result = array(
            'table' => $table,
            'exists' => self::table_exists( $table ),
            'engine' => '',
            'columns_expected' => 0,
            'columns_actual' => 0,
            'indexes_expected' => 0,
            'indexes_actual' => 0,
            'ready' => false,
        );
        if ( empty( $result['exists'] ) ) {
            return $result;
        }
        $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ), ARRAY_A );
        $result['engine'] = is_array( $status ) ? (string) ( $status['Engine'] ?? '' ) : '';
        $expected = self::expected_schema()[ $name ] ?? null;
        $actual = $wpdb->get_results( 'SHOW FULL COLUMNS FROM ' . self::quote_identifier( $table ), ARRAY_A );
        $result['columns_expected'] = is_array( $expected ) ? count( $expected['columns'] ) : 0;
        $result['columns_actual'] = is_array( $actual ) ? count( $actual ) : 0;
        if ( ! is_array( $status ) || 0 !== strcasecmp( 'InnoDB', $result['engine'] ) || ! is_array( $expected ) || ! is_array( $actual ) || $result['columns_actual'] !== $result['columns_expected'] ) {
            return $result;
        }
        foreach ( $actual as $column ) {
            $field = $column['Field'] ?? '';
            if ( ! isset( $expected['columns'][ $field ] ) || ! self::types_match( (string) ( $column['Type'] ?? '' ), $expected['columns'][ $field ][0] ) || strtoupper( (string) ( $column['Null'] ?? '' ) ) !== $expected['columns'][ $field ][1] ) {
                return $result;
            }
        }
        $found = array();
        foreach ( (array) $wpdb->get_results( 'SHOW INDEX FROM ' . self::quote_identifier( $table ), ARRAY_A ) as $index ) {
            $key = $index['Key_name'] ?? '';
            if ( '' === $key ) { return $result; }
            $found[ $key ][] = $index;
        }
        $result['indexes_expected'] = count( $expected['indexes'] );
        $result['indexes_actual'] = count( $found );
        if ( $result['indexes_actual'] !== $result['indexes_expected'] || array_diff( array_keys( $expected['indexes'] ), array_keys( $found ) ) ) {
            return $result;
        }
        foreach ( $expected['indexes'] as $key => $definition ) {
            if ( count( $found[ $key ] ) !== count( $definition['columns'] ) ) { return $result; }
            usort( $found[ $key ], static function( $left, $right ) { return (int) $left['Seq_in_index'] <=> (int) $right['Seq_in_index']; } );
            foreach ( $found[ $key ] as $position => $index ) {
                if ( (int) $index['Non_unique'] !== ( $definition['unique'] ? 0 : 1 ) || $definition['columns'][ $position ] !== ( $index['Column_name'] ?? '' ) ) {
                    return $result;
                }
            }
        }
        $result['ready'] = true;
        return $result;
    }

    /** MySQL 8 omits legacy integer display widths from SHOW FULL COLUMNS. */
    private static function types_match( $actual, $expected ) {
        return self::normalise_type( $actual ) === self::normalise_type( $expected );
    }

    private static function normalise_type( $type ) {
        $type = strtolower( trim( (string) $type ) );
        return preg_replace( '/\\b(tinyint|smallint|mediumint|int|bigint)\\(\\d+\\)/', '$1', $type );
    }

    /** @return array<string,array<string,mixed>> */
    private static function expected_schema() {
        return array(
            'subscriptions' => array(
                'columns' => array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'subscription_uuid' => array( 'char(36)', 'NO' ), 'faluss_id' => array( 'char(36)', 'NO' ), 'provider' => array( 'varchar(32)', 'NO' ), 'provider_customer_reference' => array( 'varchar(191)', 'YES' ), 'provider_subscription_reference' => array( 'varchar(191)', 'YES' ), 'plan_key' => array( 'varchar(32)', 'NO' ), 'billing_interval' => array( 'varchar(16)', 'YES' ), 'provider_status' => array( 'varchar(32)', 'YES' ), 'normalized_state' => array( 'varchar(16)', 'NO' ), 'trial_starts_at' => array( 'datetime', 'YES' ), 'trial_ends_at' => array( 'datetime', 'YES' ), 'period_starts_at' => array( 'datetime', 'YES' ), 'period_ends_at' => array( 'datetime', 'YES' ), 'grace_ends_at' => array( 'datetime', 'YES' ), 'cancel_at_period_end' => array( 'tinyint(1)', 'NO' ), 'cancelled_at' => array( 'datetime', 'YES' ), 'ended_at' => array( 'datetime', 'YES' ), 'last_synced_at' => array( 'datetime', 'YES' ), 'version' => array( 'bigint(20) unsigned', 'NO' ), 'created_at' => array( 'datetime', 'NO' ), 'updated_at' => array( 'datetime', 'NO' ) ),
                'indexes' => array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'subscription_uuid_unique' => array( 'unique' => true, 'columns' => array( 'subscription_uuid' ) ), 'provider_subscription_unique' => array( 'unique' => true, 'columns' => array( 'provider', 'provider_subscription_reference' ) ), 'subscription_faluss_state' => array( 'unique' => false, 'columns' => array( 'faluss_id', 'normalized_state', 'period_ends_at' ) ), 'subscription_provider_status' => array( 'unique' => false, 'columns' => array( 'provider', 'provider_status' ) ), 'subscription_updated' => array( 'unique' => false, 'columns' => array( 'updated_at' ) ) ),
            ),
            'trials' => array(
                'columns' => array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'trial_uuid' => array( 'char(36)', 'NO' ), 'faluss_id' => array( 'char(36)', 'NO' ), 'eligibility_status' => array( 'varchar(24)', 'NO' ), 'trial_state' => array( 'varchar(16)', 'NO' ), 'activated_at' => array( 'datetime', 'YES' ), 'expires_at' => array( 'datetime', 'YES' ), 'consumed_at' => array( 'datetime', 'YES' ), 'revoked_at' => array( 'datetime', 'YES' ), 'verification_reference' => array( 'varchar(191)', 'YES' ), 'payment_fingerprint_hash' => array( 'char(64)', 'YES' ), 'admin_override_reason' => array( 'varchar(191)', 'YES' ), 'admin_override_reference' => array( 'varchar(191)', 'YES' ), 'created_at' => array( 'datetime', 'NO' ), 'updated_at' => array( 'datetime', 'NO' ) ),
                'indexes' => array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'trial_uuid_unique' => array( 'unique' => true, 'columns' => array( 'trial_uuid' ) ), 'trial_faluss_unique' => array( 'unique' => true, 'columns' => array( 'faluss_id' ) ), 'trial_payment_fingerprint_unique' => array( 'unique' => true, 'columns' => array( 'payment_fingerprint_hash' ) ), 'trial_override_reference_unique' => array( 'unique' => true, 'columns' => array( 'admin_override_reference' ) ), 'trial_state_expiry' => array( 'unique' => false, 'columns' => array( 'trial_state', 'expires_at' ) ), 'trial_eligibility' => array( 'unique' => false, 'columns' => array( 'eligibility_status', 'trial_state' ) ) ),
            ),
            'entitlements' => array(
                'columns' => array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'entitlement_uuid' => array( 'char(36)', 'NO' ), 'faluss_id' => array( 'char(36)', 'NO' ), 'entitlement_key' => array( 'varchar(120)', 'NO' ), 'entitlement_value' => array( 'varchar(64)', 'NO' ), 'source' => array( 'varchar(24)', 'NO' ), 'source_reference' => array( 'varchar(191)', 'NO' ), 'priority' => array( 'smallint(5) unsigned', 'NO' ), 'starts_at' => array( 'datetime', 'NO' ), 'expires_at' => array( 'datetime', 'YES' ), 'status' => array( 'varchar(16)', 'NO' ), 'version' => array( 'bigint(20) unsigned', 'NO' ), 'created_at' => array( 'datetime', 'NO' ), 'updated_at' => array( 'datetime', 'NO' ) ),
                'indexes' => array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'entitlement_uuid_unique' => array( 'unique' => true, 'columns' => array( 'entitlement_uuid' ) ), 'entitlement_source_reference_unique' => array( 'unique' => true, 'columns' => array( 'source_reference' ) ), 'entitlement_faluss_key_state' => array( 'unique' => false, 'columns' => array( 'faluss_id', 'entitlement_key', 'status', 'starts_at' ) ), 'entitlement_expiry' => array( 'unique' => false, 'columns' => array( 'expires_at' ) ), 'entitlement_source' => array( 'unique' => false, 'columns' => array( 'source', 'status' ) ) ),
            ),
            'events' => array(
                'columns' => array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'provider' => array( 'varchar(32)', 'NO' ), 'provider_event_id' => array( 'varchar(191)', 'NO' ), 'event_type' => array( 'varchar(80)', 'NO' ), 'processing_status' => array( 'varchar(24)', 'NO' ), 'attempt_count' => array( 'bigint(20) unsigned', 'NO' ), 'payload_hash' => array( 'char(64)', 'NO' ), 'received_at' => array( 'datetime', 'NO' ), 'processed_at' => array( 'datetime', 'YES' ), 'last_error' => array( 'varchar(191)', 'YES' ), 'created_at' => array( 'datetime', 'NO' ), 'updated_at' => array( 'datetime', 'NO' ) ),
                'indexes' => array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'provider_event_unique' => array( 'unique' => true, 'columns' => array( 'provider', 'provider_event_id' ) ), 'event_processing' => array( 'unique' => false, 'columns' => array( 'processing_status', 'received_at' ) ), 'event_provider_type' => array( 'unique' => false, 'columns' => array( 'provider', 'event_type' ) ) ),
            ),
            'audit' => array(
                'columns' => array( 'id' => array( 'bigint(20) unsigned', 'NO' ), 'audit_uuid' => array( 'char(36)', 'NO' ), 'actor_user_id' => array( 'bigint(20) unsigned', 'YES' ), 'action' => array( 'varchar(80)', 'NO' ), 'faluss_id' => array( 'char(36)', 'YES' ), 'source' => array( 'varchar(32)', 'NO' ), 'previous_state' => array( 'longtext', 'YES' ), 'next_state' => array( 'longtext', 'YES' ), 'justification' => array( 'varchar(191)', 'YES' ), 'created_at' => array( 'datetime', 'NO' ) ),
                'indexes' => array( 'PRIMARY' => array( 'unique' => true, 'columns' => array( 'id' ) ), 'audit_uuid_unique' => array( 'unique' => true, 'columns' => array( 'audit_uuid' ) ), 'audit_faluss_created' => array( 'unique' => false, 'columns' => array( 'faluss_id', 'created_at' ) ), 'audit_action_created' => array( 'unique' => false, 'columns' => array( 'action', 'created_at' ) ), 'audit_actor_created' => array( 'unique' => false, 'columns' => array( 'actor_user_id', 'created_at' ) ) ),
            ),
        );
    }
}
