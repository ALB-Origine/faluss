<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Minimal no-cache technical responses. Browser parameters never grant entitlement. */
final class Faluss_Subscriptions_Returns {
    public static function boot() { add_action( 'template_redirect', array( __CLASS__, 'render' ), 0 ); }

    public static function render() {
        if ( empty( $_GET['faluss_subscriptions_return'] ) ) { return; }
        nocache_headers();
        $kind = sanitize_key( wp_unslash( $_GET['faluss_subscriptions_return'] ) );
        $state = isset( $_GET['state'] ) && is_string( $_GET['state'] ) ? wp_unslash( $_GET['state'] ) : '';
        $checkout = Faluss_Subscriptions_Repository::checkout_for_state( $state );
        $title = 'success' === $kind ? __( 'Stripe traite votre activation.', 'faluss-subscriptions' ) : ( 'cancel' === $kind ? __( 'Checkout a été annulé.', 'faluss-subscriptions' ) : __( 'Retour de gestion Faluss Pro.', 'faluss-subscriptions' ) );
        // Portal returns do not carry a subscription decision or grant token. A
        // Checkout response must still match its local opaque state.
        if ( 'portal' !== $kind && ! is_array( $checkout ) ) { $title = __( 'Cette confirmation n’est plus disponible.', 'faluss-subscriptions' ); }
        status_header( 200 );
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><meta http-equiv="Cache-Control" content="no-store"></head><body><main><h1>' . esc_html( $title ) . '</h1><p>' . esc_html__( 'Les droits sont déterminés exclusivement après vérification serveur et événements Stripe.', 'faluss-subscriptions' ) . '</p></main></body></html>';
        exit;
    }
}
