<?php
/**
 * Private WP-CLI consistency check for one Faluss Me aggregate.
 *
 * Usage (after a database backup):
 * FALUSS_LINK_CHECK_ID='<private uuid>' wp eval-file docs/FL-HOTFIX-01-READONLY-CHECK.php
 *
 * The output deliberately contains no Faluss ID, e-mail, block UUID, label,
 * URL, or preference value. This file performs SELECT queries only.
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "Run this read-only check with WP-CLI.\n" );
    return;
}

global $wpdb;
$faluss_id = strtolower( trim( (string) getenv( 'FALUSS_LINK_CHECK_ID' ) ) );
if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $faluss_id ) ) {
    WP_CLI::error( 'FALUSS_LINK_CHECK_ID must contain one private valid UUID.' );
}

$blocks_table = $wpdb->prefix . 'faluss_link_blocks';
$cards_table = $wpdb->prefix . 'faluss_link_cards';
$profiles_table = $wpdb->prefix . 'faluss_identity_public_profiles';

$blocks = $wpdb->get_results(
    $wpdb->prepare( 'SELECT sort_order,block_type,payload FROM `' . esc_sql( $blocks_table ) . '` WHERE faluss_id=%s ORDER BY sort_order ASC,id ASC', $faluss_id ),
    ARRAY_A
);
$profile = $wpdb->get_row(
    $wpdb->prepare( 'SELECT external_links FROM `' . esc_sql( $profiles_table ) . '` WHERE faluss_id=%s', $faluss_id ),
    ARRAY_A
);
$card = $wpdb->get_row(
    $wpdb->prepare( 'SELECT cover_attachment_id,avatar_visible,name_weight,name_treatment,available,bio_mode,announcement,announcement_variant,social_links,social_layout,link_style,created_at,updated_at FROM `' . esc_sql( $cards_table ) . '` WHERE faluss_id=%s', $faluss_id ),
    ARRAY_A
);

$type_counts = array();
$derived_links = array();
$orders = array();
foreach ( (array) $blocks as $row ) {
    $type = (string) ( $row['block_type'] ?? '' );
    $type_counts[ $type ] = ( $type_counts[ $type ] ?? 0 ) + 1;
    $orders[] = (int) ( $row['sort_order'] ?? 0 );
    if ( 'link' !== $type ) { continue; }
    $payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
    if ( ! is_array( $payload ) ) { $payload = array(); }
    $derived_links[] = array(
        'label' => (string) ( $payload['label'] ?? '' ),
        'url' => (string) ( $payload['url'] ?? '' ),
        'position' => count( $derived_links ) + 1,
    );
}
ksort( $type_counts );

$identity_links = is_array( $profile ) ? json_decode( (string) ( $profile['external_links'] ?? '' ), true ) : null;
$identity_links = is_array( $identity_links ) ? array_values( $identity_links ) : array();
$expected_orders = $orders ? range( 1, count( $orders ) ) : array();
$encode = static function( $value ) { return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); };
$fingerprint = static function( $value ) use ( $encode ) { return hash( 'sha256', $encode( $value ) ); };

$report = array(
    'blocks' => array(
        'row_count' => count( $blocks ),
        'type_counts' => $type_counts,
        'sort_order_contiguous' => $orders === $expected_orders,
        'aggregate_sha256' => $fingerprint( $blocks ),
    ),
    'identity_projection' => array(
        'row_exists' => is_array( $profile ),
        'link_count' => count( $identity_links ),
        'matches_canonical_blocks' => $encode( $identity_links ) === $encode( $derived_links ),
        'aggregate_sha256' => $fingerprint( $identity_links ),
    ),
    'card_preferences' => array(
        'row_exists' => is_array( $card ),
        'aggregate_sha256' => $fingerprint( is_array( $card ) ? $card : array() ),
    ),
);

WP_CLI::line( $encode( $report ) );
