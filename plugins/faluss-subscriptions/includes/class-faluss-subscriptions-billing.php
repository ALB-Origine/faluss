<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Private central billing façade. It is intentionally not attached to an AJAX,
 * shortcode or anonymous endpoint: SUB-01C will authenticate product callers.
 */
final class Faluss_Subscriptions_Billing {
    const PROVIDER = 'stripe';

    /** @return array<string,mixed>|WP_Error */
    public static function create_checkout( $faluss_id, $period ) {
        $faluss_id = self::faluss_id( $faluss_id );
        $period = is_string( $period ) ? $period : '';
        if ( ! $faluss_id || ! in_array( $period, array( 'monthly', 'annual' ), true ) || ! Faluss_Subscriptions_Schema::is_ready() ) {
            return self::error( 'checkout_invalid' );
        }
        $adapter = Faluss_Subscriptions_Stripe_Sdk::adapter();
        if ( is_wp_error( $adapter ) || ! Faluss_Subscriptions_Stripe_Config::tax_enabled() ) {
            return is_wp_error( $adapter ) ? $adapter : self::error( 'stripe_tax_not_enabled' );
        }
        $price = self::validated_price( $adapter, $period );
        if ( is_wp_error( $price ) ) { return $price; }
        if ( ! self::acquire_lock( $faluss_id ) ) { return self::error( 'checkout_busy' ); }
        try {
            if ( self::has_blocking_subscription( $faluss_id ) ) { return self::error( 'checkout_subscription_exists' ); }
            $trial = Faluss_Subscriptions_Repository::trial_for_faluss_id( $faluss_id );
            if ( is_array( $trial ) && 'eligible' !== (string) ( $trial['trial_state'] ?? '' ) ) { return self::error( 'checkout_trial_already_used' ); }
            $customer = self::ensure_customer( $adapter, $faluss_id );
            if ( is_wp_error( $customer ) ) { return $customer; }
            $opaque_state = self::random_state();
            $idempotency_key = self::random_state();
            if ( '' === $opaque_state || '' === $idempotency_key ) { return self::error( 'checkout_entropy_failed' ); }
            $checkout = Faluss_Subscriptions_Repository::create_checkout( array(
                'faluss_id' => $faluss_id, 'billing_interval' => $period,
                'provider_customer_reference' => $customer['provider_customer_reference'],
                'opaque_state' => $opaque_state, 'idempotency_key' => $idempotency_key,
            ) );
            if ( is_wp_error( $checkout ) ) { return $checkout; }
            $parameters = self::checkout_parameters( $checkout, $faluss_id, $period, $price['id'], $opaque_state );
            $session = $adapter->create_checkout_session( $parameters, $idempotency_key );
            if ( is_wp_error( $session ) || empty( $session['id'] ) || empty( $session['url'] ) || ! self::stripe_url( $session['url'] ) ) {
                return is_wp_error( $session ) ? $session : self::error( 'checkout_session_invalid' );
            }
            $saved = Faluss_Subscriptions_Repository::mark_checkout_provider_session( (int) $checkout['id'], (string) $session['id'], self::timestamp_to_utc( $session['expires_at'] ?? null ) );
            if ( is_wp_error( $saved ) ) { return $saved; }
            Faluss_Subscriptions_Audit::record( 0, 'stripe_checkout_created', $faluss_id, 'billing', array(), array( 'checkout_uuid' => $checkout['checkout_uuid'], 'period' => $period ), null );
            return array( 'url' => (string) $session['url'], 'checkout_uuid' => $checkout['checkout_uuid'], 'status' => 'pending' );
        } finally {
            self::release_lock( $faluss_id );
        }
    }

    /** Retrieve and validate the configured Stripe Price immediately before Checkout. */
    public static function validated_price( $adapter, $period ) {
        $price_id = Faluss_Subscriptions_Stripe_Config::price_id( $period );
        $product_id = Faluss_Subscriptions_Stripe_Config::product_id();
        if ( is_wp_error( $price_id ) || is_wp_error( $product_id ) ) { return is_wp_error( $price_id ) ? $price_id : $product_id; }
        $price = $adapter->retrieve_price( $price_id );
        if ( is_wp_error( $price ) || ! is_array( $price ) ) { return is_wp_error( $price ) ? $price : self::error( 'stripe_price_unavailable' ); }
        $amount = 'monthly' === $period ? 999 : 9900;
        $product = self::id( $price['product'] ?? '' );
        $recurring = is_array( $price['recurring'] ?? null ) ? $price['recurring'] : array();
        $interval = 'monthly' === $period ? 'month' : 'year';
        if ( empty( $price['active'] ) || 'eur' !== strtolower( (string) ( $price['currency'] ?? '' ) ) || $amount !== (int) ( $price['unit_amount'] ?? -1 ) || $interval !== (string) ( $recurring['interval'] ?? '' ) || 1 !== (int) ( $recurring['interval_count'] ?? 1 ) || 'inclusive' !== (string) ( $price['tax_behavior'] ?? '' ) || $product !== $product_id || self::id( $price['id'] ?? '' ) !== $price_id ) {
            return self::error( 'stripe_price_catalogue_mismatch' );
        }
        return $price;
    }

    /** @return array<string,mixed>|WP_Error */
    public static function create_portal( $faluss_id ) {
        $faluss_id = self::faluss_id( $faluss_id );
        $adapter = Faluss_Subscriptions_Stripe_Sdk::adapter();
        $configuration = Faluss_Subscriptions_Stripe_Config::portal_configuration_id();
        if ( ! $faluss_id || is_wp_error( $adapter ) || is_wp_error( $configuration ) ) { return is_wp_error( $adapter ) ? $adapter : self::error( 'portal_unavailable' ); }
        $customer = Faluss_Subscriptions_Repository::customer_for_faluss_id( $faluss_id, self::PROVIDER );
        if ( ! is_array( $customer ) ) { return self::error( 'portal_customer_missing' ); }
        $session = $adapter->create_portal_session( array( 'customer' => $customer['provider_customer_reference'], 'configuration' => $configuration, 'return_url' => self::return_url( 'portal' ) ) );
        if ( is_wp_error( $session ) || empty( $session['url'] ) || ! self::stripe_url( $session['url'] ) ) { return is_wp_error( $session ) ? $session : self::error( 'portal_session_invalid' ); }
        return array( 'url' => (string) $session['url'] );
    }

    /** @return array<string,mixed>|WP_Error */
    public static function request_cancellation( $faluss_id, $subscription_reference, $cancel = true ) {
        $faluss_id = self::faluss_id( $faluss_id );
        $subscription_reference = self::id( $subscription_reference );
        $adapter = Faluss_Subscriptions_Stripe_Sdk::adapter();
        if ( ! $faluss_id || '' === $subscription_reference || is_wp_error( $adapter ) ) { return is_wp_error( $adapter ) ? $adapter : self::error( 'subscription_update_invalid' ); }
        $record = self::subscription_for_reference( $faluss_id, $subscription_reference );
        if ( ! is_array( $record ) ) { return self::error( 'subscription_not_owned' ); }
        $subscription = $adapter->cancel_at_period_end( $subscription_reference, $cancel );
        if ( is_wp_error( $subscription ) ) { return $subscription; }
        return self::apply_stripe_subscription( $subscription, 'customer.subscription.updated' );
    }

    /** @return array<string,mixed>|WP_Error */
    public static function resync( $subscription_reference ) {
        $subscription_reference = self::id( $subscription_reference );
        $adapter = Faluss_Subscriptions_Stripe_Sdk::adapter();
        if ( '' === $subscription_reference || is_wp_error( $adapter ) ) { return is_wp_error( $adapter ) ? $adapter : self::error( 'subscription_resync_invalid' ); }
        $subscription = $adapter->retrieve_subscription( $subscription_reference );
        return is_wp_error( $subscription ) ? $subscription : self::apply_stripe_subscription( $subscription, 'resync' );
    }

    /** @return array<string,mixed>|WP_Error */
    public static function apply_stripe_subscription( $subscription, $event_type ) {
        if ( ! is_array( $subscription ) ) { return self::error( 'stripe_subscription_invalid' ); }
        $customer_reference = self::id( $subscription['customer'] ?? '' );
        $customer = Faluss_Subscriptions_Repository::customer_for_reference( $customer_reference, self::PROVIDER );
        $faluss_id = is_array( $customer ) ? self::faluss_id( $customer['faluss_id'] ?? '' ) : '';
        $metadata = is_array( $subscription['metadata'] ?? null ) ? $subscription['metadata'] : array();
        if ( ! $faluss_id || $faluss_id !== self::faluss_id( $metadata['faluss_id'] ?? '' ) ) { return self::error( 'stripe_subscription_identity_mismatch' ); }
        $period = self::subscription_period( $subscription );
        $adapter = Faluss_Subscriptions_Stripe_Sdk::adapter();
        if ( is_wp_error( $adapter ) ) { return $adapter; }
        $price = self::validated_subscription_price( $adapter, $subscription, $period );
        if ( is_wp_error( $price ) ) { return $price; }
        $normalized = self::normalise_state( $subscription );
        $trial_starts = self::timestamp_to_utc( $subscription['trial_start'] ?? null );
        $trial_ends = self::timestamp_to_utc( $subscription['trial_end'] ?? null );
        if ( 'trialing' === $normalized ) {
            $fingerprint = self::payment_fingerprint( $subscription );
            if ( '' === $fingerprint ) {
                $payment_method = $adapter->retrieve_payment_method( self::id( $subscription['default_payment_method'] ?? '' ) );
                if ( is_array( $payment_method ) ) { $fingerprint = self::id( $payment_method['card']['fingerprint'] ?? '' ); }
            }
            if ( ! self::valid_trial_window( $trial_starts, $trial_ends ) || '' === $fingerprint ) {
                $adapter->cancel_now( self::id( $subscription['id'] ?? '' ) );
                Faluss_Subscriptions_Audit::record( 0, 'stripe_trial_refused', $faluss_id, 'billing', array(), array( 'reason' => 'payment_or_window_invalid' ), null );
                return self::error( 'stripe_trial_verification_failed' );
            }
            $trial = Faluss_Subscriptions_Trials::activate_verified_trial( $faluss_id, self::id( $subscription['id'] ?? '' ), $fingerprint, $trial_starts );
            if ( is_wp_error( $trial ) ) {
                $adapter->cancel_now( self::id( $subscription['id'] ?? '' ) );
                Faluss_Subscriptions_Audit::record( 0, 'stripe_trial_refused', $faluss_id, 'billing', array(), array( 'reason' => 'trial_already_consumed' ), null );
                return $trial;
            }
        }
        $failed_at = in_array( $event_type, array( 'invoice.payment_failed', 'invoice.payment_action_required' ), true ) ? gmdate( 'Y-m-d H:i:s' ) : null;
        $record = Faluss_Subscriptions_Repository::upsert_provider_subscription( array(
            'faluss_id' => $faluss_id, 'provider' => self::PROVIDER, 'provider_customer_reference' => $customer_reference,
            'provider_subscription_reference' => self::id( $subscription['id'] ?? '' ), 'plan_key' => Faluss_Subscriptions_Catalog::PRO,
            'billing_interval' => $period, 'provider_status' => self::id( $subscription['status'] ?? '' ), 'normalized_state' => $normalized,
            'trial_starts_at' => $trial_starts, 'trial_ends_at' => $trial_ends,
            'period_starts_at' => self::timestamp_to_utc( $subscription['current_period_start'] ?? null ), 'period_ends_at' => self::timestamp_to_utc( $subscription['current_period_end'] ?? null ),
            'grace_started_at' => $failed_at, 'grace_ends_at' => $failed_at ? gmdate( 'Y-m-d H:i:s', strtotime( '+' . Faluss_Subscriptions_Catalog::GRACE_DAYS . ' days', strtotime( $failed_at ) ) ) : null,
            'cancel_at_period_end' => ! empty( $subscription['cancel_at_period_end'] ), 'cancelled_at' => self::timestamp_to_utc( $subscription['canceled_at'] ?? null ), 'ended_at' => self::timestamp_to_utc( $subscription['ended_at'] ?? null ),
        ) );
        if ( is_wp_error( $record ) ) { return $record; }
        Faluss_Subscriptions_Audit::record( 0, 'stripe_subscription_synced', $faluss_id, 'billing', array(), array( 'subscription_uuid' => $record['subscription_uuid'], 'state' => $normalized, 'event_type' => $event_type ), null );
        if ( 'trialing' === $normalized ) { Faluss_Subscriptions_Notifications::queue( $faluss_id, 'trial_started', self::id( $subscription['id'] ?? '' ), gmdate( 'Y-m-d H:i:s' ) ); }
        if ( 'canceling' === $normalized ) { Faluss_Subscriptions_Notifications::queue( $faluss_id, 'cancellation_recorded', self::id( $subscription['id'] ?? '' ), gmdate( 'Y-m-d H:i:s' ) ); }
        if ( 'expired' === $normalized ) { Faluss_Subscriptions_Notifications::queue( $faluss_id, 'rights_ended', self::id( $subscription['id'] ?? '' ), gmdate( 'Y-m-d H:i:s' ) ); }
        if ( in_array( $event_type, array( 'invoice.payment_failed', 'invoice.payment_action_required' ), true ) ) { Faluss_Subscriptions_Notifications::queue( $faluss_id, 'payment_problem', self::id( $subscription['id'] ?? '' ), gmdate( 'Y-m-d H:i:s' ) ); }
        if ( 'invoice.paid' === $event_type ) { Faluss_Subscriptions_Notifications::queue( $faluss_id, 'payment_confirmed', self::id( $subscription['id'] ?? '' ), gmdate( 'Y-m-d H:i:s' ) ); }
        return $record;
    }

    public static function normalise_state( $subscription ) {
        $status = self::id( is_array( $subscription ) ? ( $subscription['status'] ?? '' ) : '' );
        if ( 'trialing' === $status ) { return 'trialing'; }
        if ( 'active' === $status ) { return ! empty( $subscription['cancel_at_period_end'] ) ? 'canceling' : 'active'; }
        if ( 'past_due' === $status ) { return 'past_due'; }
        if ( in_array( $status, array( 'unpaid', 'paused' ), true ) ) { return 'suspended'; }
        return 'expired'; // canceled, incomplete and incomplete_expired never grant Pro.
    }

    private static function ensure_customer( $adapter, $faluss_id ) {
        $existing = Faluss_Subscriptions_Repository::customer_for_faluss_id( $faluss_id, self::PROVIDER );
        if ( is_array( $existing ) ) { return $existing; }
        $created = $adapter->create_customer( $faluss_id );
        if ( is_wp_error( $created ) || empty( $created['id'] ) ) { return is_wp_error( $created ) ? $created : self::error( 'stripe_customer_invalid' ); }
        return Faluss_Subscriptions_Repository::record_customer( $faluss_id, self::PROVIDER, self::id( $created['id'] ), Faluss_Subscriptions_Stripe_Config::mode() );
    }

    private static function checkout_parameters( $checkout, $faluss_id, $period, $price_id, $state ) {
        $base = self::return_url( 'checkout' );
        $token = $state;
        return array(
            'mode' => 'subscription', 'customer' => $checkout['provider_customer_reference'], 'line_items' => array( array( 'price' => $price_id, 'quantity' => 1 ) ),
            'payment_method_types' => array( 'card' ), 'payment_method_collection' => 'always', 'billing_address_collection' => 'required', 'customer_update' => array( 'address' => 'auto' ), 'automatic_tax' => array( 'enabled' => true ),
            'success_url' => add_query_arg( array( 'kind' => 'success', 'state' => $token, 'session_id' => '{CHECKOUT_SESSION_ID}' ), $base ),
            'cancel_url' => add_query_arg( array( 'kind' => 'cancel', 'state' => $token ), $base ),
            'client_reference_id' => $checkout['checkout_uuid'],
            'metadata' => array( 'faluss_billing_session' => $checkout['checkout_uuid'] ),
            'subscription_data' => array( 'trial_period_days' => Faluss_Subscriptions_Catalog::TRIAL_DAYS, 'trial_settings' => array( 'end_behavior' => array( 'missing_payment_method' => 'cancel' ) ), 'metadata' => array( 'faluss_id' => $faluss_id, 'faluss_billing_session' => $checkout['checkout_uuid'], 'billing_interval' => $period ) ),
        );
    }

    private static function has_blocking_subscription( $faluss_id ) {
        foreach ( Faluss_Subscriptions_Repository::subscriptions_for_faluss_id( $faluss_id ) as $subscription ) {
            if ( in_array( $subscription['normalized_state'] ?? '', array( 'trialing', 'active', 'canceling', 'past_due' ), true ) ) { return true; }
        }
        return false;
    }

    private static function subscription_for_reference( $faluss_id, $reference ) { foreach ( Faluss_Subscriptions_Repository::subscriptions_for_faluss_id( $faluss_id ) as $subscription ) { if ( self::PROVIDER === ( $subscription['provider'] ?? '' ) && $reference === ( $subscription['provider_subscription_reference'] ?? '' ) ) { return $subscription; } } return null; }
    private static function subscription_period( $subscription ) { $items = $subscription['items']['data'] ?? array(); $price = is_array( $items ) && ! empty( $items[0]['price'] ) ? $items[0]['price'] : array(); return 'year' === ( $price['recurring']['interval'] ?? '' ) ? 'annual' : 'monthly'; }
    /** Revalidate the configured Price before an event can affect a central right. */
    private static function validated_subscription_price( $adapter, $subscription, $period ) {
        $items = $subscription['items']['data'] ?? array();
        $embedded = is_array( $items ) && ! empty( $items[0]['price'] ) ? Faluss_Subscriptions_Stripe_Adapter::normalise( $items[0]['price'] ) : array();
        $configured = Faluss_Subscriptions_Stripe_Config::price_id( $period );
        if ( is_wp_error( $configured ) || self::id( $embedded['id'] ?? '' ) !== $configured ) { return self::error( 'stripe_subscription_price_mismatch' ); }
        $current = self::validated_price( $adapter, $period );
        return is_wp_error( $current ) ? $current : $embedded;
    }
    private static function payment_fingerprint( $subscription ) { $value = $subscription['default_payment_method'] ?? ''; if ( is_array( $value ) ) { return self::id( $value['card']['fingerprint'] ?? '' ); } return ''; }
    private static function valid_trial_window( $start, $end ) { return $start && $end && 1296000 === strtotime( $end ) - strtotime( $start ); }
    private static function return_url( $kind ) { return add_query_arg( array( 'faluss_subscriptions_return' => sanitize_key( $kind ) ), home_url( '/' ) ); }
    private static function stripe_url( $url ) { $parts = wp_parse_url( (string) $url ); return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && isset( $parts['host'] ) && 1 === preg_match( '/(^|\\.)stripe\.com$/', strtolower( $parts['host'] ) ); }
    private static function id( $value ) { if ( is_array( $value ) ) { $value = $value['id'] ?? ''; } return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_]+$/', $value ) ? $value : ''; }
    private static function faluss_id( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value ) ? strtolower( $value ) : ''; }
    private static function timestamp_to_utc( $value ) { if ( is_numeric( $value ) && (int) $value > 0 ) { return gmdate( 'Y-m-d H:i:s', (int) $value ); } return is_string( $value ) && '' !== $value ? $value : null; }
    private static function random_state() { try { return bin2hex( random_bytes( 32 ) ); } catch ( Exception $exception ) { return ''; } }
    private static function acquire_lock( $faluss_id ) { global $wpdb; return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', 'faluss_sub_billing_' . substr( hash( 'sha256', $faluss_id ), 0, 32 ), 10 ) ); }
    private static function release_lock( $faluss_id ) { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'faluss_sub_billing_' . substr( hash( 'sha256', $faluss_id ), 0, 32 ) ) ); }
    private static function error( $code ) { return new WP_Error( $code, __( 'La facturation Faluss Max ne peut pas être traitée.', 'faluss-subscriptions' ) ); }
}
