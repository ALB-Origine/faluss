<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Persistence boundary. No browser or public route calls this class in SUB-01A. */
final class Faluss_Subscriptions_Repository {
    /** @return array<int,array<string,mixed>> */
    public static function subscriptions_for_faluss_id( $faluss_id ) {
        return self::rows_for_faluss_id( Faluss_Subscriptions_Schema::subscriptions_table(), $faluss_id, 'updated_at DESC,id DESC' );
    }

    /** @return array<string,mixed>|null */
    public static function trial_for_faluss_id( $faluss_id ) {
        global $wpdb;
        if ( ! self::valid_faluss_id( $faluss_id ) ) { return null; }
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::trials_table() ) . ' WHERE faluss_id=%s LIMIT 1', strtolower( $faluss_id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function entitlements_for_faluss_id( $faluss_id ) {
        return self::rows_for_faluss_id( Faluss_Subscriptions_Schema::entitlements_table(), $faluss_id, 'priority DESC,starts_at DESC,id DESC' );
    }

    /** @return array<int,array<string,mixed>> */
    public static function events( $limit = 100, $only_errors = false ) {
        global $wpdb;
        $limit = max( 1, min( 100, (int) $limit ) );
        $table = Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::events_table() );
        $where = $only_errors ? " WHERE processing_status='failed'" : '';
        return (array) $wpdb->get_results( 'SELECT * FROM ' . $table . $where . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A );
    }

    /**
     * Idempotence foundation for SUB-01B. Only a payload hash is stored; raw provider data never is.
     * @return array<string,mixed>|WP_Error
     */
    public static function record_event( $provider, $provider_event_id, $event_type, $payload_hash ) {
        global $wpdb;
        if ( ! Faluss_Subscriptions_Schema::is_ready() || ! self::valid_provider( $provider ) || ! self::bounded( $provider_event_id, 191 ) || ! self::bounded( $event_type, 80 ) || ! self::valid_hash( $payload_hash ) ) {
            return self::error( 'event_invalid' );
        }
        $provider = strtolower( self::bounded( $provider, 32 ) );
        $provider_event_id = self::bounded( $provider_event_id, 191 );
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::events_table() ) . ' WHERE provider=%s AND provider_event_id=%s LIMIT 1', $provider, $provider_event_id ), ARRAY_A );
        if ( is_array( $existing ) ) {
            return array( 'event' => $existing, 'idempotent' => true );
        }
        $now = gmdate( 'Y-m-d H:i:s' );
        $written = $wpdb->insert(
            Faluss_Subscriptions_Schema::events_table(),
            array( 'provider' => $provider, 'provider_event_id' => $provider_event_id, 'event_type' => self::bounded( $event_type, 80 ), 'processing_status' => 'received', 'attempt_count' => 0, 'payload_hash' => strtolower( $payload_hash ), 'received_at' => $now, 'created_at' => $now, 'updated_at' => $now ),
            array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );
        if ( false === $written ) {
            $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::events_table() ) . ' WHERE provider=%s AND provider_event_id=%s LIMIT 1', $provider, $provider_event_id ), ARRAY_A );
            return is_array( $existing ) ? array( 'event' => $existing, 'idempotent' => true ) : self::error( 'event_record_failed' );
        }
        $event = array( 'id' => (int) $wpdb->insert_id, 'provider' => $provider, 'provider_event_id' => $provider_event_id, 'processing_status' => 'received' );
        Faluss_Subscriptions_Audit::record( 0, 'provider_event_recorded', null, 'provider', array(), array( 'provider' => $provider, 'event_type' => self::bounded( $event_type, 80 ), 'processing_status' => 'received' ), null );
        return array( 'event' => $event, 'idempotent' => false );
    }

    /** Future trusted payment adapter only; administration must never call this. */
    public static function upsert_provider_subscription( $record ) {
        global $wpdb;
        if ( ! Faluss_Subscriptions_Schema::is_ready() || ! is_array( $record ) || ! self::valid_faluss_id( $record['faluss_id'] ?? '' ) || ! self::valid_provider( $record['provider'] ?? '' ) || ! Faluss_Subscriptions_Catalog::valid_plan( $record['plan_key'] ?? '' ) || ! in_array( $record['normalized_state'] ?? '', Faluss_Subscriptions_Catalog::normalized_states(), true ) ) {
            return self::error( 'subscription_invalid' );
        }
        $interval = $record['billing_interval'] ?? null;
        if ( Faluss_Subscriptions_Catalog::PRO === $record['plan_key'] && ! Faluss_Subscriptions_Catalog::valid_period( $record['plan_key'], $interval ) ) {
            return self::error( 'subscription_interval_invalid' );
        }
        $provider = strtolower( self::bounded( $record['provider'], 32 ) );
        $reference = self::bounded( $record['provider_subscription_reference'] ?? '', 191 );
        if ( '' === $reference ) { return self::error( 'subscription_reference_missing' ); }
        $now = gmdate( 'Y-m-d H:i:s' );
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::subscriptions_table() ) . ' WHERE provider=%s AND provider_subscription_reference=%s LIMIT 1', $provider, $reference ), ARRAY_A );
        $period_ends_at = self::utc_or_null( $record['period_ends_at'] ?? null );
        $grace_ends_at = 'past_due' === $record['normalized_state'] ? self::capped_grace_end( $period_ends_at, self::utc_or_null( $record['grace_ends_at'] ?? null ) ) : self::utc_or_null( $record['grace_ends_at'] ?? null );
        if ( 'past_due' === $record['normalized_state'] && ! $grace_ends_at ) { return self::error( 'subscription_grace_invalid' ); }
        $data = array(
            'faluss_id' => strtolower( $record['faluss_id'] ), 'provider' => $provider,
            'provider_customer_reference' => self::nullable( $record['provider_customer_reference'] ?? null, 191 ), 'provider_subscription_reference' => $reference,
            'plan_key' => $record['plan_key'], 'billing_interval' => $interval, 'provider_status' => self::nullable( $record['provider_status'] ?? null, 32 ),
            'normalized_state' => $record['normalized_state'], 'trial_starts_at' => self::utc_or_null( $record['trial_starts_at'] ?? null ), 'trial_ends_at' => self::utc_or_null( $record['trial_ends_at'] ?? null ),
            'period_starts_at' => self::utc_or_null( $record['period_starts_at'] ?? null ), 'period_ends_at' => $period_ends_at, 'grace_ends_at' => $grace_ends_at,
            'cancel_at_period_end' => empty( $record['cancel_at_period_end'] ) ? 0 : 1, 'cancelled_at' => self::utc_or_null( $record['cancelled_at'] ?? null ), 'ended_at' => self::utc_or_null( $record['ended_at'] ?? null ), 'last_synced_at' => $now, 'updated_at' => $now,
        );
        if ( is_array( $existing ) ) {
            $data['version'] = (int) $existing['version'] + 1;
            $written = $wpdb->update( Faluss_Subscriptions_Schema::subscriptions_table(), $data, array( 'id' => (int) $existing['id'], 'version' => (int) $existing['version'] ) );
            if ( false === $written || 0 === $written ) { return self::error( 'subscription_conflict' ); }
            $saved = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::subscriptions_table() ) . ' WHERE id=%d', (int) $existing['id'] ), ARRAY_A );
            Faluss_Subscriptions_Audit::record( 0, 'provider_subscription_updated', $record['faluss_id'], 'subscription', self::subscription_audit_state( $existing ), self::subscription_audit_state( $saved ), null );
            return $saved;
        }
        $data = array_merge( array( 'subscription_uuid' => wp_generate_uuid4(), 'version' => 1, 'created_at' => $now ), $data );
        $written = $wpdb->insert( Faluss_Subscriptions_Schema::subscriptions_table(), $data );
        if ( false === $written ) { return self::error( 'subscription_record_failed' ); }
        $saved = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::subscriptions_table() ) . ' WHERE id=%d', (int) $wpdb->insert_id ), ARRAY_A );
        Faluss_Subscriptions_Audit::record( 0, 'provider_subscription_recorded', $record['faluss_id'], 'subscription', array(), self::subscription_audit_state( $saved ), null );
        return $saved;
    }

    private static function rows_for_faluss_id( $table, $faluss_id, $order ) {
        global $wpdb;
        if ( ! self::valid_faluss_id( $faluss_id ) ) { return array(); }
        return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Subscriptions_Schema::quote_identifier( $table ) . ' WHERE faluss_id=%s ORDER BY ' . $order, strtolower( $faluss_id ) ), ARRAY_A );
    }
    private static function valid_faluss_id( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value ); }
    private static function valid_provider( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9_-]{1,31}$/i', $value ); }
    private static function valid_hash( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/i', $value ); }
    private static function bounded( $value, $length ) { $value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : ''; return function_exists( 'mb_substr' ) ? mb_substr( trim( $value ), 0, $length ) : substr( trim( $value ), 0, $length ); }
    private static function nullable( $value, $length ) { $value = self::bounded( $value, $length ); return '' === $value ? null : $value; }
    /** The grace period is a server rule, not an arbitrary provider value. */
    private static function capped_grace_end( $period_ends_at, $requested_grace_end ) {
        if ( ! $period_ends_at ) { return null; }
        $maximum = gmdate( 'Y-m-d H:i:s', strtotime( '+' . Faluss_Subscriptions_Catalog::GRACE_DAYS . ' days', strtotime( $period_ends_at ) ) );
        return $requested_grace_end && strcmp( $requested_grace_end, $maximum ) < 0 ? $requested_grace_end : $maximum;
    }
    private static function subscription_audit_state( $record ) { return is_array( $record ) ? array_intersect_key( $record, array_flip( array( 'subscription_uuid', 'plan_key', 'billing_interval', 'normalized_state', 'trial_starts_at', 'trial_ends_at', 'period_starts_at', 'period_ends_at', 'grace_ends_at', 'cancel_at_period_end', 'cancelled_at', 'ended_at', 'version' ) ) ) : array(); }
    private static function utc_or_null( $value ) { if ( ! is_string( $value ) || '' === trim( $value ) ) { return null; } try { return ( new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ); } catch ( Exception $exception ) { return null; } }
    private static function error( $code ) { return new WP_Error( $code, __( 'L’enregistrement central ne peut pas être modifié.', 'faluss-subscriptions' ) ); }
}
