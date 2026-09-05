<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Resolves a generic subject without ever treating the local WordPress account ID as a central subject. */
final class Token_Engine_Connector_Subject {
    public static function current() {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) { return ''; }
        $subject = '';
        if ( class_exists( 'Faluss_Identity_Registry' ) && method_exists( 'Faluss_Identity_Registry', 'get_active_for_wp_user' ) ) {
            $subject = Faluss_Identity_Registry::get_active_for_wp_user( $user->ID );
        }
        $subject = apply_filters( 'token_engine_connector_subject_id', $subject, $user );
        return self::normalise( $subject );
    }

    public static function diagnostic() {
        $user = wp_get_current_user();
        $adapter_available = class_exists( 'Faluss_Identity_Registry' ) && method_exists( 'Faluss_Identity_Registry', 'get_active_for_wp_user' );
        $identity_subject = '';
        if ( $adapter_available && $user && $user->exists() ) {
            $identity_subject = Faluss_Identity_Registry::get_active_for_wp_user( $user->ID );
        }
        $subject = self::current();
        return array(
            'signed_in' => (bool) ( $user && $user->exists() ),
            'identity_adapter_available' => $adapter_available,
            'active_identity_profile' => '' !== self::normalise( $identity_subject ),
            'subject_available' => '' !== $subject,
            /* A short digest is operationally useful without exposing a Faluss ID. */
            'subject_fingerprint' => '' !== $subject ? substr( hash( 'sha256', $subject ), 0, 12 ) : '',
        );
    }

    private static function normalise( $value ) {
        $value = is_string( $value ) ? sanitize_text_field( $value ) : '';
        $value = trim( $value );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 191 ) : substr( $value, 0, 191 );
    }
}
