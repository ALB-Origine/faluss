<?php

function ec02_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: $message\n" );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$schema = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-schema.php' );
$rights = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-entitlements.php' );
$access = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-connector-access.php' );
$connector = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-service.php' );
$connector_admin = file_get_contents( $root . '/plugins/token-engine-connector/includes/class-token-engine-connector-admin.php' );
$catalog = file_get_contents( $root . '/plugins/faluss-catalog/includes/class-faluss-catalog-themes.php' );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );

foreach ( array( "const VERSION = '4'", "const V3_VERSION = '3'", 'token_engine_entitlement_definitions', 'token_engine_entitlement_grants', 'ENGINE=InnoDB', 'entitlement_code_unique', 'grant_operation_unique', 'migrate_v2_to_v3', 'current_schema_ready' ) as $needle ) {
    ec02_assert( false !== strpos( $schema, $needle ), 'EC-02 requires an additive, strict InnoDB entitlement schema: ' . $needle );
}
foreach ( array( 'TYPE_THEME', 'create_definition', 'create_manual_grant', 'revoke_grant', 'GET_LOCK', 'active_or_scheduled_grant', 'operation_reference', 'revoked_at', 'ends_at', 'subject_has_entitlement' ) as $needle ) {
    ec02_assert( false !== strpos( $rights, $needle ), 'EC-02 requires generic definition, idempotent grant, expiry and revocation support: ' . $needle );
}
ec02_assert( false === stripos( $rights, 'ALB' ) && false === stripos( $rights, 'woocommerce' ) && false === stripos( $rights, 'faluss_id' ), 'Core entitlement service must remain generic and identity-agnostic.' );

foreach ( array( 'PERMISSION_ENTITLEMENTS_READ', 'connector/entitlements/definitions', 'connector/entitlement', 'entitlements_read_permission', 'entitlement_definitions_response', 'entitlement_response' ) as $needle ) {
    ec02_assert( false !== strpos( $access, $needle ), 'EC-02 Core access contract is missing: ' . $needle );
}
foreach ( array( 'entitlement_definitions()', 'subject_has_entitlement', 'entitlements_diagnostic', 'PERMISSION_ENTITLEMENTS_READ', 'connector_permission_entitlements_read_missing' ) as $needle ) {
    ec02_assert( false !== strpos( $connector, $needle ), 'EC-02 Connector must read and diagnose rights centrally: ' . $needle );
}
ec02_assert( false === strpos( $connector, 'CREATE TABLE' ) && false === strpos( $connector, 'INSERT INTO' ), 'Connector must not create a local entitlement registry or grant.' );
foreach ( array( 'token_engine_connector_test_entitlements', 'Diagnostic des droits', 'Aucun secret, jeton', 'entitlements.read' ) as $needle ) {
    ec02_assert( false !== strpos( $connector_admin, $needle ), 'Connector requires a non-mutating entitlement diagnostic: ' . $needle );
}

foreach ( array( "'entitlement_code'", 'connector_theme_entitlements', 'validated_entitlement', 'Inclus — aucun droit requis', 'Aucun code libre' ) as $needle ) {
    ec02_assert( false !== strpos( $catalog, $needle ), 'Catalogue must offer only Core-compatible or included theme rights: ' . $needle );
}
foreach ( array( 'theme_available_to_subject', 'subject_has_entitlement', 'theme_locked', 'prefs( $faluss_id, false )', 'Droit requis', 'catalog_themes_for_client( self::current_faluss_id() )' ) as $needle ) {
    ec02_assert( false !== strpos( $link, $needle ), 'Link must validate locked themes on the server: ' . $needle );
}
ec02_assert( false === strpos( $link, 'CREATE TABLE token_engine_entitlement' ) && false === strpos( $link, 'INSERT INTO token_engine_entitlement' ), 'Faluss Link must not keep an entitlement table or grant locally.' );
ec02_assert( false !== strpos( $studio, 'preset.locked') && false === strpos( $studio, 'faluss-link-studio__copy-id' ), 'Studio must prevent locked theme selection without exposing a private identity copy action.' );

echo "EC-02 centralized entitlements and lockable Faluss themes contract: OK\n";
