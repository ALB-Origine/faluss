<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Resolves a generic subject without ever treating the local WordPress account ID as a central subject. */
final class Token_Engine_Connector_Subject {
    public static function current() {
        $user = self::current_user_after_plugins_loaded();
        if ( null === $user ) { return ''; }
        $identity = self::identity_resolution( $user );
        return self::filtered_subject( $identity['faluss_id'], $user );
    }

    public static function diagnostic() {
        $user = self::current_user_after_plugins_loaded();
        $identity = null === $user ? self::empty_resolution() : self::identity_resolution( $user );
        $subject = null === $user ? '' : self::filtered_subject( $identity['faluss_id'], $user );
        return array(
            'signed_in' => null !== $user,
            'identity_adapter_available' => ! empty( $identity['identity_detected'] ),
            'active_identity_profile' => ! empty( $identity['active'] ),
            'subject_available' => '' !== $subject,
            /* A short digest is operationally useful without exposing a Faluss ID. */
            'subject_fingerprint' => '' !== $subject ? substr( hash( 'sha256', $subject ), 0, 12 ) : '',
        );
    }

    /** Public registry first; strict, read-only schema fallback only when that API is unavailable. */
    private static function identity_resolution( $user ) {
        if ( class_exists( 'Faluss_Identity_Registry' ) && method_exists( 'Faluss_Identity_Registry', 'get_active_for_wp_user' ) ) {
            $faluss_id = Faluss_Identity_Registry::get_active_for_wp_user( $user->ID );
            $active = self::valid_faluss_id( $faluss_id );
            return array( 'identity_detected' => true, 'active' => $active, 'faluss_id' => $active ? self::normalise( $faluss_id ) : '' );
        }
        return self::read_only_schema_resolution( $user->ID );
    }

    /** This fallback mirrors the current Faluss Identity active-profile read without changing it. */
    private static function read_only_schema_resolution( $wp_user_id ) {
        global $wpdb;
        if ( ! class_exists( 'Faluss_Identity_Schema' ) || ! method_exists( 'Faluss_Identity_Schema', 'get_status' ) || ! method_exists( 'Faluss_Identity_Schema', 'get_table_names' ) ) {
            return self::empty_resolution();
        }
        $status = Faluss_Identity_Schema::get_status();
        $tables = Faluss_Identity_Schema::get_table_names();
        $table = is_array( $tables ) ? (string) ( $tables['profiles'] ?? '' ) : '';
        if ( empty( $status['ready'] ) || '' === $table || 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $table ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
            return array( 'identity_detected' => true, 'active' => false, 'faluss_id' => '' );
        }
        $faluss_id = $wpdb->get_var( $wpdb->prepare( 'SELECT faluss_id FROM `' . $table . '` WHERE wp_user_id = %d AND status = %s', (int) $wp_user_id, 'active' ) );
        $active = self::valid_faluss_id( $faluss_id );
        return array( 'identity_detected' => true, 'active' => $active, 'faluss_id' => $active ? self::normalise( $faluss_id ) : '' );
    }

    private static function current_user_after_plugins_loaded() {
        if ( ! did_action( 'plugins_loaded' ) ) { return null; }
        $user = wp_get_current_user();
        return $user && $user->exists() ? $user : null;
    }

    private static function filtered_subject( $subject, $user ) {
        return self::normalise( apply_filters( 'token_engine_connector_subject_id', $subject, $user ) );
    }

    private static function valid_faluss_id( $value ) {
        if ( class_exists( 'Faluss_Identity_Registry' ) && method_exists( 'Faluss_Identity_Registry', 'is_valid_faluss_id' ) ) {
            return Faluss_Identity_Registry::is_valid_faluss_id( $value );
        }
        return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value );
    }

    private static function empty_resolution() { return array( 'identity_detected' => false, 'active' => false, 'faluss_id' => '' ); }
    private static function normalise( $value ) {
        $value = is_string( $value ) ? sanitize_text_field( $value ) : '';
        $value = trim( $value );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 191 ) : substr( $value, 0, 191 );
    }
}
