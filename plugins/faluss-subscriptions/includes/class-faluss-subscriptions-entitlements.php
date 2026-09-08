<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Central rights registry. It records level decisions but grants no product-local feature in SUB-01A. */
final class Faluss_Subscriptions_Entitlements {
    const ADMIN_PRIORITY = 300;

    /** @return array<string,mixed>|WP_Error */
    public static function grant_temporary_pro( $faluss_id, $expires_at, $reason, $actor_user_id, $operation_reference ) {
        global $wpdb;
        $faluss_id = self::faluss_id( $faluss_id );
        $expires_at = self::future_utc( $expires_at );
        $reason = self::bounded( $reason, 191 );
        $operation_reference = self::bounded( $operation_reference, 191 );
        if ( ! Faluss_Subscriptions_Schema::is_ready() || ! $faluss_id || ! $expires_at || '' === $reason || '' === $operation_reference ) {
            return self::error( 'admin_grant_invalid' );
        }
        $table = Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::entitlements_table() );
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE source_reference=%s LIMIT 1', $operation_reference ), ARRAY_A );
        if ( is_array( $existing ) ) {
            return (string) $existing['faluss_id'] === $faluss_id ? $existing : self::error( 'admin_grant_reference_conflict' );
        }
        $now = gmdate( 'Y-m-d H:i:s' );
        $record = array(
            'entitlement_uuid' => wp_generate_uuid4(), 'faluss_id' => $faluss_id, 'entitlement_key' => Faluss_Subscriptions_Catalog::PRO_ENTITLEMENT,
            'entitlement_value' => 'pro', 'source' => 'admin_grant', 'source_reference' => $operation_reference, 'priority' => self::ADMIN_PRIORITY,
            'starts_at' => $now, 'expires_at' => $expires_at, 'status' => 'active', 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        );
        $written = $wpdb->insert( Faluss_Subscriptions_Schema::entitlements_table(), $record );
        if ( false === $written ) {
            $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE source_reference=%s LIMIT 1', $operation_reference ), ARRAY_A );
            return is_array( $existing ) && (string) $existing['faluss_id'] === $faluss_id ? $existing : self::error( 'admin_grant_failed' );
        }
        $record['id'] = (int) $wpdb->insert_id;
        Faluss_Subscriptions_Audit::record( $actor_user_id, 'admin_pro_granted', $faluss_id, 'admin_grant', array(), self::audit_state( $record ), $reason );
        return $record;
    }

    /** @return true|WP_Error */
    public static function revoke_admin_grant( $grant_id, $reason, $actor_user_id ) {
        global $wpdb;
        $grant_id = absint( $grant_id );
        $reason = self::bounded( $reason, 191 );
        if ( ! Faluss_Subscriptions_Schema::is_ready() || ! $grant_id || '' === $reason ) { return self::error( 'admin_grant_revoke_invalid' ); }
        $table = Faluss_Subscriptions_Schema::quote_identifier( Faluss_Subscriptions_Schema::entitlements_table() );
        $grant = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id=%d AND source=%s LIMIT 1', $grant_id, 'admin_grant' ), ARRAY_A );
        if ( ! is_array( $grant ) ) { return self::error( 'admin_grant_missing' ); }
        if ( 'revoked' === $grant['status'] ) { return true; }
        $now = gmdate( 'Y-m-d H:i:s' );
        $updated = $wpdb->update( Faluss_Subscriptions_Schema::entitlements_table(), array( 'status' => 'revoked', 'version' => (int) $grant['version'] + 1, 'updated_at' => $now ), array( 'id' => $grant_id, 'version' => (int) $grant['version'] ) );
        if ( false === $updated || 0 === $updated ) { return self::error( 'admin_grant_revoke_conflict' ); }
        $after = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id=%d', $grant_id ), ARRAY_A );
        Faluss_Subscriptions_Audit::record( $actor_user_id, 'admin_pro_revoked', $grant['faluss_id'], 'admin_grant', self::audit_state( $grant ), self::audit_state( $after ), $reason );
        return true;
    }

    private static function future_utc( $value ) { if ( ! is_string( $value ) || '' === trim( $value ) ) { return null; } try { $date = ( new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) ); return $date->getTimestamp() > time() ? $date->format( 'Y-m-d H:i:s' ) : null; } catch ( Exception $exception ) { return null; } }
    private static function faluss_id( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value ) ? strtolower( $value ) : ''; }
    private static function bounded( $value, $length ) { $value = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : ''; return function_exists( 'mb_substr' ) ? mb_substr( trim( $value ), 0, $length ) : substr( trim( $value ), 0, $length ); }
    private static function audit_state( $record ) { return is_array( $record ) ? array_intersect_key( $record, array_flip( array( 'id', 'entitlement_key', 'entitlement_value', 'source', 'priority', 'starts_at', 'expires_at', 'status', 'version' ) ) ) : array(); }
    private static function error( $code ) { return new WP_Error( $code, __( 'L’attribution administrative ne peut pas être modifiée.', 'faluss-subscriptions' ) ); }
}
