<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Local trust store and exact operation policy. Remote manifests never create trust. */
final class Faluss_Federation_Policy {
    public static function operations() { return array( 'diagnostic.read', 'manifest.read', 'read_model.read' ); }
    public static function audiences() { return array( 'private', 'members', 'public' ); }

    public static function has_usable_peer() {
        global $wpdb;
        if ( ! Faluss_Federation_Schema::is_ready() ) {
            return false;
        }
        $rows = $wpdb->get_results( 'SELECT * FROM ' . Faluss_Federation_Schema::quote_identifier( Faluss_Federation_Schema::peers_table() ) . ' WHERE key_state IN (\'active\',\'rotating\')', ARRAY_A );
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            if ( ! is_wp_error( self::normalize_peer( $row ) ) ) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed>|WP_Error */
    public static function find_peer( $node_id, $app_key, $key_id ) {
        global $wpdb;
        if ( ! Faluss_Federation_Schema::is_ready() || ! Faluss_Federation_Crypto::is_node( $node_id ) || ! Faluss_Federation_Crypto::is_node( $app_key ) || ! Faluss_Federation_Crypto::is_key_id( $key_id ) ) {
            return new WP_Error( 'faluss_federation_unknown_peer' );
        }
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Federation_Schema::quote_identifier( Faluss_Federation_Schema::peers_table() ) . ' WHERE peer_node_id = %s AND peer_app_key = %s AND key_id = %s LIMIT 1', $node_id, $app_key, $key_id ), ARRAY_A );
        return is_array( $row ) ? self::normalize_peer( $row ) : new WP_Error( 'faluss_federation_unknown_peer' );
    }

    /** @return array<string,mixed>|WP_Error */
    public static function find_outbound_peer( $node_id, $app_key ) {
        global $wpdb;
        if ( ! Faluss_Federation_Schema::is_ready() || ! Faluss_Federation_Crypto::is_node( $node_id ) || ! Faluss_Federation_Crypto::is_node( $app_key ) ) {
            return new WP_Error( 'faluss_federation_unknown_peer' );
        }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Faluss_Federation_Schema::quote_identifier( Faluss_Federation_Schema::peers_table() ) . ' WHERE peer_node_id = %s AND peer_app_key = %s ORDER BY CASE key_state WHEN \"active\" THEN 0 WHEN \"rotating\" THEN 1 ELSE 2 END, id DESC', $node_id, $app_key ), ARRAY_A );
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $peer = self::normalize_peer( $row );
            if ( ! is_wp_error( $peer ) ) {
                return $peer;
            }
        }
        return new WP_Error( 'faluss_federation_unknown_peer' );
    }

    /** @return true|WP_Error */
    public static function allow_incoming( $request, $peer, $identity ) {
        if ( ! is_array( $request ) || ! is_array( $peer ) || ! is_array( $identity ) || ! self::peer_is_usable( $peer ) || ( $request['recipient']['node_id'] ?? null ) !== $identity['node_id'] || ( $request['recipient']['app_key'] ?? null ) !== $identity['app_key'] || ! in_array( $request['operation'] ?? '', $peer['operations'], true ) ) {
            return new WP_Error( 'faluss_federation_not_authorized' );
        }
        if ( 'diagnostic.read' === $request['operation'] ) {
            return null === $request['subject_context'] && array() === $request['parameters'] ? true : new WP_Error( 'faluss_federation_invalid_request' );
        }
        if ( 'manifest.read' === $request['operation'] ) {
            $app = $request['parameters']['app_key'] ?? null;
            return null === $request['subject_context'] && is_string( $app ) && $app === $request['recipient']['app_key'] && in_array( $app, $peer['owner_apps'], true ) ? true : new WP_Error( 'faluss_federation_not_authorized' );
        }
        $parameters = $request['parameters'];
        if ( ( $parameters['owner_app_key'] ?? null ) !== $request['recipient']['app_key'] || ! in_array( $parameters['owner_app_key'] ?? '', $peer['owner_apps'], true ) || ! in_array( $parameters['capability_key'] ?? '', $peer['capabilities'], true ) || ! in_array( $parameters['audience'] ?? '', $peer['audiences'], true ) ) {
            return new WP_Error( 'faluss_federation_not_authorized' );
        }
        /* A missing local producer is a signed not_available response, not a policy oracle. */
        return Faluss_Federation_Providers::has_read_model_provider( $request ) && ! Faluss_Federation_Providers::descriptor_allows( $request ) ? new WP_Error( 'faluss_federation_incompatible' ) : true;
    }

    /** @return true|WP_Error */
    public static function create_peer( $input ) {
        $peer = self::validate_peer_input( $input );
        if ( is_wp_error( $peer ) || ! Faluss_Federation_Schema::is_ready() ) {
            return is_wp_error( $peer ) ? $peer : new WP_Error( 'faluss_federation_fail_closed' );
        }
        return self::create_peer_transaction( $peer );
    }

    /** @return true|WP_Error */
    private static function create_peer_transaction( $peer ) {
        global $wpdb;
        $started = $wpdb->query( 'START TRANSACTION' );
        if ( false === $started || self::database_has_error() ) {
            self::rollback_safely();
            return new WP_Error( 'faluss_federation_fail_closed' );
        }
        try {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,key_state FROM ' . Faluss_Federation_Schema::quote_identifier( Faluss_Federation_Schema::peers_table() ) . ' WHERE peer_node_id = %s AND peer_app_key = %s FOR UPDATE', $peer['peer_node_id'], $peer['peer_app_key'] ), ARRAY_A );
            if ( ! is_array( $rows ) || self::database_has_error() ) {
                self::rollback_safely();
                return new WP_Error( 'faluss_federation_fail_closed' );
            }
            $states = array();
            $active_id = 0;
            foreach ( $rows as $row ) {
                $states[] = $row['key_state'];
                if ( 'active' === $row['key_state'] ) {
                    $active_id = (int) $row['id'];
                }
            }
            if ( 'active' !== $peer['key_state'] || in_array( 'rotating', $states, true ) || count( array_intersect( $states, array( 'active', 'rotating' ) ) ) >= 2 ) {
                return self::rollback_safely() ? new WP_Error( 'faluss_federation_rotation_refused' ) : new WP_Error( 'faluss_federation_fail_closed' );
            }
            if ( $active_id > 0 ) {
                $rotated = $wpdb->update( Faluss_Federation_Schema::peers_table(), array( 'key_state' => 'rotating', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $active_id ), array( '%s', '%s' ), array( '%d' ) );
                if ( 1 !== $rotated || self::database_has_error() ) {
                    self::rollback_safely();
                    return new WP_Error( 'faluss_federation_fail_closed' );
                }
            }
            $now = gmdate( 'Y-m-d H:i:s' );
            $written = $wpdb->insert( Faluss_Federation_Schema::peers_table(), array( 'peer_node_id' => $peer['peer_node_id'], 'peer_app_key' => $peer['peer_app_key'], 'canonical_origin' => $peer['canonical_origin'], 'key_id' => $peer['key_id'], 'public_key' => $peer['public_key'], 'key_state' => $peer['key_state'], 'valid_from' => $peer['valid_from'], 'valid_until' => $peer['valid_until'], 'operations_json' => wp_json_encode( $peer['operations'] ), 'owner_apps_json' => wp_json_encode( $peer['owner_apps'] ), 'capabilities_json' => wp_json_encode( $peer['capabilities'] ), 'audiences_json' => wp_json_encode( $peer['audiences'] ), 'created_at' => $now, 'updated_at' => $now, 'revoked_at' => null ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
            if ( false === $written || self::database_has_error() ) {
                self::rollback_safely();
                return new WP_Error( 'faluss_federation_fail_closed' );
            }
            $committed = $wpdb->query( 'COMMIT' );
            if ( false === $committed || self::database_has_error() ) {
                self::rollback_safely();
                return new WP_Error( 'faluss_federation_fail_closed' );
            }
            Faluss_Federation_Schema::audit( array( 'result_code' => 'peer_registered', 'opaque_code' => 'admin' ) );
            return true;
        } catch ( Throwable $exception ) {
            self::rollback_safely();
            return new WP_Error( 'faluss_federation_fail_closed' );
        }
    }

    /** @return true|WP_Error */
    public static function revoke_peer( $peer_id ) {
        if ( ! Faluss_Federation_Schema::is_ready() || ! is_int( $peer_id ) || $peer_id < 1 ) {
            return new WP_Error( 'faluss_federation_fail_closed' );
        }
        return self::revoke_peer_transaction( $peer_id );
    }

    /** @return true|WP_Error */
    private static function revoke_peer_transaction( $peer_id ) {
        global $wpdb;
        $started = $wpdb->query( 'START TRANSACTION' );
        if ( false === $started || self::database_has_error() ) {
            self::rollback_safely();
            return new WP_Error( 'faluss_federation_fail_closed' );
        }
        try {
            $state = $wpdb->get_var( $wpdb->prepare( 'SELECT key_state FROM ' . Faluss_Federation_Schema::quote_identifier( Faluss_Federation_Schema::peers_table() ) . ' WHERE id = %d FOR UPDATE', $peer_id ) );
            if ( self::database_has_error() ) {
                self::rollback_safely();
                return new WP_Error( 'faluss_federation_fail_closed' );
            }
            if ( ! in_array( $state, array( 'active', 'rotating' ), true ) ) {
                return self::rollback_safely() ? new WP_Error( 'faluss_federation_revoke_refused' ) : new WP_Error( 'faluss_federation_fail_closed' );
            }
            $written = $wpdb->update( Faluss_Federation_Schema::peers_table(), array( 'key_state' => 'revoked', 'revoked_at' => gmdate( 'Y-m-d H:i:s' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $peer_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
            if ( 1 !== $written || self::database_has_error() ) {
                self::rollback_safely();
                return new WP_Error( 'faluss_federation_fail_closed' );
            }
            $committed = $wpdb->query( 'COMMIT' );
            if ( false === $committed || self::database_has_error() ) {
                self::rollback_safely();
                return new WP_Error( 'faluss_federation_fail_closed' );
            }
            Faluss_Federation_Schema::audit( array( 'result_code' => 'peer_revoked', 'opaque_code' => 'admin' ) );
            return true;
        } catch ( Throwable $exception ) {
            self::rollback_safely();
            return new WP_Error( 'faluss_federation_fail_closed' );
        }
    }

    private static function rollback_safely() {
        global $wpdb;
        $rolled_back = $wpdb->query( 'ROLLBACK' );
        return false !== $rolled_back && ! self::database_has_error();
    }

    private static function database_has_error() {
        global $wpdb;
        return ! isset( $wpdb->last_error ) || ! is_string( $wpdb->last_error ) || '' !== $wpdb->last_error;
    }

    public static function state_counts() {
        global $wpdb;
        $counts = array( 'active' => 0, 'rotating' => 0, 'revoked' => 0, 'expired' => 0 );
        if ( ! Faluss_Federation_Schema::is_ready() ) {
            return $counts;
        }
        $rows = $wpdb->get_results( 'SELECT key_state,valid_until FROM ' . Faluss_Federation_Schema::quote_identifier( Faluss_Federation_Schema::peers_table() ), ARRAY_A );
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $state = $row['key_state'];
            $until = strtotime( str_replace( ' ', 'T', (string) $row['valid_until'] ) . 'Z' );
            if ( in_array( $state, array( 'active', 'rotating' ), true ) && false !== $until && $until < time() ) {
                $state = 'expired';
            }
            if ( isset( $counts[ $state ] ) ) {
                $counts[ $state ]++;
            }
        }
        return $counts;
    }

    /** Public metadata only, used by the private administrator to choose a revocation target. */
    public static function peer_summaries() {
        global $wpdb;
        if ( ! Faluss_Federation_Schema::is_ready() ) {
            return array();
        }
        $rows = $wpdb->get_results( 'SELECT id,peer_node_id,peer_app_key,canonical_origin,key_id,key_state,valid_from,valid_until FROM ' . Faluss_Federation_Schema::quote_identifier( Faluss_Federation_Schema::peers_table() ) . ' ORDER BY id DESC', ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    private static function normalize_peer( $row ) {
        if ( ! is_array( $row ) ) {
            return new WP_Error( 'faluss_federation_unknown_peer' );
        }
        $peer = array( 'id' => absint( $row['id'] ?? 0 ), 'peer_node_id' => $row['peer_node_id'] ?? '', 'peer_app_key' => $row['peer_app_key'] ?? '', 'canonical_origin' => $row['canonical_origin'] ?? '', 'key_id' => $row['key_id'] ?? '', 'public_key' => $row['public_key'] ?? '', 'key_state' => $row['key_state'] ?? '', 'valid_from' => $row['valid_from'] ?? '', 'valid_until' => $row['valid_until'] ?? '', 'operations' => self::decode_list( $row['operations_json'] ?? '', 'operation', 3 ), 'owner_apps' => self::decode_list( $row['owner_apps_json'] ?? '', 'node', 32 ), 'capabilities' => self::decode_list( $row['capabilities_json'] ?? '', 'capability', 128 ), 'audiences' => self::decode_list( $row['audiences_json'] ?? '', 'audience', 3 ) );
        return self::peer_is_usable( $peer ) ? $peer : new WP_Error( 'faluss_federation_unknown_peer' );
    }

    private static function peer_is_usable( $peer ) {
        if ( ! is_array( $peer ) || ! Faluss_Federation_Crypto::is_node( $peer['peer_node_id'] ?? '' ) || ! Faluss_Federation_Crypto::is_node( $peer['peer_app_key'] ?? '' ) || ! Faluss_Federation_Crypto::is_key_id( $peer['key_id'] ?? '' ) || ! Faluss_Federation_Crypto::is_canonical_origin( $peer['canonical_origin'] ?? '' ) || ! in_array( $peer['key_state'] ?? '', array( 'active', 'rotating' ), true ) || is_wp_error( Faluss_Federation_Crypto::base64url_decode( $peer['public_key'] ?? '', 32 ) ) ) {
            return false;
        }
        $from = strtotime( str_replace( ' ', 'T', $peer['valid_from'] ) . 'Z' );
        $until = strtotime( str_replace( ' ', 'T', $peer['valid_until'] ) . 'Z' );
        return false !== $from && false !== $until && $until > $from && $from <= time() && $until >= time() && ! empty( $peer['operations'] );
    }

    private static function validate_peer_input( $input ) {
        if ( ! is_array( $input ) ) {
            return new WP_Error( 'faluss_federation_invalid_peer' );
        }
        $allowed = array( 'peer_node_id', 'peer_app_key', 'canonical_origin', 'key_id', 'public_key', 'key_state', 'valid_from', 'valid_until', 'operations', 'owner_apps', 'capabilities', 'audiences' );
        if ( array_diff( array_keys( $input ), $allowed ) || array_diff( $allowed, array_keys( $input ) ) ) {
            return new WP_Error( 'faluss_federation_invalid_peer' );
        }
        $peer = array( 'peer_node_id' => $input['peer_node_id'], 'peer_app_key' => $input['peer_app_key'], 'canonical_origin' => $input['canonical_origin'], 'key_id' => $input['key_id'], 'public_key' => $input['public_key'], 'key_state' => $input['key_state'], 'valid_from' => $input['valid_from'], 'valid_until' => $input['valid_until'], 'operations' => self::normalize_list( $input['operations'], 'operation', 3 ), 'owner_apps' => self::normalize_list( $input['owner_apps'], 'node', 32 ), 'capabilities' => self::normalize_list( $input['capabilities'], 'capability', 128 ), 'audiences' => self::normalize_list( $input['audiences'], 'audience', 3 ) );
        $from = strtotime( str_replace( ' ', 'T', $peer['valid_from'] ) . 'Z' );
        $until = strtotime( str_replace( ' ', 'T', $peer['valid_until'] ) . 'Z' );
        if ( ! Faluss_Federation_Crypto::is_node( $peer['peer_node_id'] ) || ! Faluss_Federation_Crypto::is_node( $peer['peer_app_key'] ) || ! Faluss_Federation_Crypto::is_canonical_origin( $peer['canonical_origin'] ) || ! Faluss_Federation_Crypto::is_key_id( $peer['key_id'] ) || is_wp_error( Faluss_Federation_Crypto::base64url_decode( $peer['public_key'], 32 ) ) || 'active' !== $peer['key_state'] || false === $from || false === $until || $until <= $from || in_array( false, $peer, true ) ) {
            return new WP_Error( 'faluss_federation_invalid_peer' );
        }
        return $peer;
    }

    private static function decode_list( $json, $kind, $maximum ) {
        $value = json_decode( $json, true );
        return JSON_ERROR_NONE === json_last_error() ? self::normalize_list( $value, $kind, $maximum ) : false;
    }

    private static function normalize_list( $values, $kind, $maximum ) {
        if ( ! is_array( $values ) || count( $values ) > $maximum || count( $values ) !== count( array_unique( $values, SORT_STRING ) ) || ( 'operation' === $kind && empty( $values ) ) ) {
            return false;
        }
        foreach ( $values as $value ) {
            $valid = 'operation' === $kind ? in_array( $value, self::operations(), true ) : ( 'audience' === $kind ? in_array( $value, self::audiences(), true ) : ( 'node' === $kind ? Faluss_Federation_Crypto::is_node( $value ) : ( is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9_.-]{1,127})+$/D', $value ) ) ) );
            if ( ! $valid || '*' === $value ) {
                return false;
            }
        }
        return array_values( $values );
    }
}
