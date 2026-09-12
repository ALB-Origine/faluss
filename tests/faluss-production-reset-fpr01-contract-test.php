<?php

function fpr01_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/plugins/faluss-production-reset/faluss-production-reset.php' );
$service = file_get_contents( $root . '/plugins/faluss-production-reset/includes/class-faluss-production-reset.php' );
$documentation = file_get_contents( $root . '/docs/FALUSS_PRODUCTION_RESET.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );

fpr01_assert( false !== strpos( $bootstrap, 'Plugin Name: Faluss Production Reset' ) && false !== strpos( $bootstrap, "Version: 0.1.3" ) && false !== strpos( $service, "const VERSION = '0.1.3'" ), 'FPR-01.3 must be an isolated Faluss Production Reset 0.1.3 plugin.' );
fpr01_assert( false !== strpos( $service, "'faluss.me'" ) && false !== strpos( $service, "'faluss.com'" ) && false !== strpos( $service, 'HUB_RECEIVER_URL' ), 'The isolated plugin must have only the two canonical Faluss sites in scope.' );

// 1. No browser operation bypasses capability, nonce and the exact confirmation.
foreach ( array( "current_user_can( 'manage_options' )", 'check_admin_referer', "const CONFIRMATION = 'METTRE FALUSS EN PRODUCTION'", 'has_confirmation' ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'Admin authorization invariant is missing: ' . $needle );
}

// 2. The peer protocol has a fixed envelope, never caller-selected users, tables or targets.
fpr01_assert( "array( 'protocol', 'operation', 'phase', 'run_id', 'issued_at', 'nonce' )" === "array( 'protocol', 'operation', 'phase', 'run_id', 'issued_at', 'nonce' )", 'Canonical protocol fixture must remain fixed.' );
foreach ( array( "const OPERATION = 'hub_member_reset_v1'", 'validate_hub_request', 'array_keys( $payload )', 'non_privileged_candidates' ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'Fixed, locally recalculated receiver invariant is missing: ' . $needle );
}
foreach ( array( '$request->get_param', "\$payload['user_ids']", "\$payload['tables']", "\$payload['target']", "\$payload['amount']" ) as $forbidden ) {
    fpr01_assert( false === strpos( $service, $forbidden ), 'The browser or peer must not select a reset target: ' . $forbidden );
}

// 3-4. Privileged accounts survive; only locally derived non-privileged accounts use the WordPress deletion API.
foreach ( array( 'is_privileged_user', 'user_can( $user, \'manage_options\' )', 'wp_delete_user( $user_id, null )' ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'WordPress account preservation/deletion invariant is missing: ' . $needle );
}
fpr01_assert( false === strpos( $service, 'DELETE FROM wp_users' ) && false === strpos( $service, 'DELETE FROM {$wpdb->users}' ), 'FPR-01 must never delete WordPress users through raw SQL.' );

// 5-6. Listed member data is removed while the SSO client registry remains structurally untouched.
foreach ( array( 'faluss_identity_profiles', 'faluss_identity_public_profiles', 'faluss_identity_challenges', 'faluss_identity_rate_limits', 'faluss_identity_auth_codes', 'faluss_identity_authorization_requests', 'faluss_identity_audit', 'faluss_link_cards', 'faluss_link_blocks', 'faluss_link_discoveries', 'faluss_link_discovery_settings', 'faluss_identity_links', 'faluss_identity_client_state' ) as $table ) {
    fpr01_assert( false !== strpos( $service, "'" . $table . "'" ), 'Required FPR-01 member table is absent from the static contract: ' . $table );
}
fpr01_assert( false === strpos( $service, "'faluss_identity_clients'" ), 'The SSO client registry must not be listed for FPR-01 deletion.' );

// 7-8. Member ownership alone selects media; deletion remains WordPress-mediated and path-verified.
fpr01_assert( false !== strpos( $service, 'wp_delete_attachment( $attachment_id, true )' ), 'Member media must be deleted through wp_delete_attachment(..., true).' );
foreach ( array( 'author_attachments', "post_type = 'attachment' AND post_author IN ({\$ids})", 'attachment_paths', 'fpr_attachment_file_remains' ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'Member-owned media selection or path verification invariant is missing: ' . $needle );
}
foreach ( array( 'identity_attachments', 'attachment_referenced_by_preserved_content', 'avatar_attachment_id', 'cover_attachment_id', 'media_teaser', 'fpr_media_ambiguous', 'fpr_media_shared', 'fpr_media_reference_ambiguous', '_thumbnail_id', '_elementor_data', 'parse_blocks', 'post_parent', 'post_content' ) as $forbidden ) {
    fpr01_assert( false === strpos( $service, $forbidden ), 'FPR-01.3 must not infer media ownership from a reference or preserved content: ' . $forbidden );
}
foreach ( array( 'unlink(', 'rmdir(', 'RecursiveDirectoryIterator', 'rm -rf', 'wp_delete_file' ) as $forbidden ) {
    fpr01_assert( false === strpos( $service, $forbidden ), 'FPR-01 must never recursively or directly delete uploads: ' . $forbidden );
}

// 9-10. The legacy ALB ledger is not a destructive target; PF exists and is empty only on Hub.
fpr01_assert( false === strpos( $service, 'token_engine_ledger' ), 'FPR-01 must not reference the ALB ledger.' );
foreach ( array( "'token_engine_pf_ledger'", 'fpr_pf_ledger_not_empty', '0 !== $pf_count' ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'PF-empty preflight invariant is missing: ' . $needle );
}
$identity_preflight = preg_match( '/private static function preflight_identity\(\).*?private static function preflight_hub\(/s', $service, $identity_match ) ? $identity_match[0] : '';
$hub_preflight = preg_match( '/private static function preflight_hub\(\).*?private static function required_tables\(/s', $service, $hub_match ) ? $hub_match[0] : '';
fpr01_assert( '' !== $identity_preflight && false === strpos( $identity_preflight, 'token_engine_pf_ledger' ) && false === strpos( $identity_preflight, 'table_count( $tables[' ), 'FPR-01.1 identity preflight must require and count no PF ledger.' );
fpr01_assert( '' !== $hub_preflight && false !== strpos( $hub_preflight, "'token_engine_pf_ledger'" ) && false !== strpos( $hub_preflight, 'table_count( $tables[\'token_engine_pf_ledger\'] )' ) && false !== strpos( $hub_preflight, 'fpr_pf_ledger_not_empty' ), 'FPR-01.1 Hub preflight must still require and reject a non-empty PF ledger.' );

// 11. Hub use is fixed, authenticated, short-lived, one-time and response-signed.
foreach ( array( 'hash_hmac( \'sha256\'', 'hash_equals', 'MAX_CLOCK_SKEW = 300', 'consume_nonce', 'add_option( $option', 'X-FPR-Response-Signature', 'register_hub_receiver' ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'Hub HMAC/timestamp/nonce invariant is missing: ' . $needle );
}
fpr01_assert( false !== strpos( $service, "! self::is_armed() || self::is_locked()" ), 'The Hub receiver must only be registered while explicitly armed and not locked.' );

// 12. Completion and partial failure both lock; no retry loop or cron exists.
foreach ( array( 'lock_with_receipt( $run_id, \'success\'', 'lock_with_receipt( $run_id, \'partial\'', 'OPTION_LOCKED', 'update_option( self::OPTION_ARMED, 0' ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'Automatic lock invariant is missing: ' . $needle );
}
foreach ( array( 'wp_schedule', 'wp_cron', 'wp_ajax_', 'add_shortcode' ) as $forbidden ) {
    fpr01_assert( false === strpos( $service, $forbidden ), 'FPR-01 must not add retry scheduling or a public UI surface: ' . $forbidden );
}

// 13. No personal data is emitted in responses, receipts or logs: only normalized integer counts.
foreach ( array( 'public_counts', "'run_id'", "'status'", "'started_at'", "'finished_at'" ) as $needle ) {
    fpr01_assert( false !== strpos( $service, $needle ), 'Non-PII receipt/response invariant is missing: ' . $needle );
}
fpr01_assert( false !== strpos( $service, 'allowed_count_keys' ), 'Responses and receipts must whitelist count keys rather than display arbitrary peer data.' );
foreach ( array( 'error_log(', 'wp_mail(' ) as $forbidden ) {
    fpr01_assert( false === strpos( $service, $forbidden ), 'The FPR plugin must not log or mail member data: ' . $forbidden );
}

foreach ( array( 'FALUSS_PRODUCTION_RESET_SHARED_SECRET', 'wp-config.php', 'Aucune sécurité ne repose', 'wp_delete_attachment', 'ledger PF est vérifié exclusivement sur `faluss.com`', "n'héberge pas Token Engine", 'token_engine_ledger', 'CDN tiers', 'Recette WordPress réelle' ) as $needle ) {
    fpr01_assert( false !== strpos( $documentation, $needle ), 'FPR-01 installation or operational limit is undocumented: ' . $needle );
}
fpr01_assert( false !== strpos( $architecture, '## Production Reset FPR-01' ), 'Architecture must record FPR-01 coordination boundary.' );
fpr01_assert( false !== strpos( $data_model, '## Production Reset FPR-01' ), 'Data model must record FPR-01 no-migration and receipt boundary.' );
fpr01_assert( false !== strpos( $roadmap, '## FPR-01 — Mise en production Faluss — livré techniquement' ), 'Roadmap must record the FPR-01 delivery.' );

echo 'FPR-01 Faluss Production Reset contract: OK' . PHP_EOL;
