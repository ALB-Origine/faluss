<?php

define( 'ABSPATH', __DIR__ . '/' );

function sub01a_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$plugin = $root . '/plugins/faluss-subscriptions';
$bootstrap = file_get_contents( $plugin . '/faluss-subscriptions.php' );
$schema = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-schema.php' );
$catalogue = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-catalog.php' );
$trials = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-trials.php' );
$entitlements = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-entitlements.php' );
$resolver_source = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-resolver.php' );
$repository = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-repository.php' );
$audit = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-audit.php' );
$admin = file_get_contents( $plugin . '/includes/class-faluss-subscriptions-admin.php' );
$documentation = file_get_contents( $root . '/docs/FALUSS_SUBSCRIPTIONS.md' );

sub01a_assert( false !== strpos( $bootstrap, 'Plugin Name: Faluss Subscriptions' ) && false !== strpos( $bootstrap, "FALUSS_SUBSCRIPTIONS_VERSION', '0.1.0'" ), 'SUB-01A requires a standalone Faluss Subscriptions plugin at 0.1.0.' );
sub01a_assert( false !== strpos( $schema, 'RENAME TABLE' ) && false !== strpos( $schema, 'temporary_tables' ) && false !== strpos( $schema, 'current_schema_ready' ) && false !== strpos( $schema, 'GET_LOCK' ), 'Installation must be atomic, verified, locked and replayable.' );
foreach ( array( 'faluss_subscriptions', 'faluss_subscription_trials', 'faluss_entitlements', 'faluss_subscription_events', 'faluss_subscription_audit', 'ENGINE=InnoDB' ) as $needle ) { sub01a_assert( false !== strpos( $schema, $needle ), 'Missing dedicated subscription schema invariant: ' . $needle ); }
sub01a_assert( false !== strpos( $schema, 'trial_faluss_unique' ) && false !== strpos( $schema, 'trial_payment_fingerprint_unique' ) && false !== strpos( $schema, 'trial_override_reference_unique' ), 'Trial identity, derived payment fingerprint and administrative override must be uniquely constrained.' );

$wpdb = (object) array( 'prefix' => 'wp_' );
require_once $plugin . '/includes/class-faluss-subscriptions-schema.php';
$plan = Faluss_Subscriptions_Schema::get_install_plan( '0123456789abcdef' );
sub01a_assert( is_array( $plan ) && 5 === count( $plan['final_tables'] ) && 5 === count( $plan['temporary_tables'] ), 'The initial migration must plan all five temporary tables.' );
foreach ( $plan['temporary_tables'] as $temporary ) { sub01a_assert( strlen( $temporary ) <= 64, 'Migration temporary table names must stay within MySQL limits.' ); }

require_once $plugin . '/includes/class-faluss-subscriptions-catalog.php';
require_once $plugin . '/includes/class-faluss-subscriptions-resolver.php';

$plans = Faluss_Subscriptions_Catalog::plans();
sub01a_assert( isset( $plans['free'], $plans['pro'] ) && 'Faluss Gratuit' === $plans['free']['public_name'] && 'Faluss Pro' === $plans['pro']['public_name'], 'Catalogue must expose free and pro canonical plans.' );
sub01a_assert( 999 === $plans['pro']['periods']['monthly']['amount_cents'] && 9900 === $plans['pro']['periods']['annual']['amount_cents'] && 'EUR' === $plans['pro']['currency'], 'Pro must retain EUR 999/9900 integer-cent prices.' );
sub01a_assert( 15 === $plans['pro']['trial_days'] && true === $plans['pro']['card_required'] && true === $plans['pro']['auto_renew'] && false === $plans['pro']['commercially_active'], 'The 15-day card-required trial must remain non-commercial before SUB-01B.' );
sub01a_assert( false !== strpos( $trials, 'trial_payment_proof_required' ) && false !== strpos( $trials, 'verification_reference' ) && false !== strpos( $trials, 'payment_fingerprint_hash' ), 'Trial activation must refuse absent server payment proof and persist only a derived fingerprint.' );
sub01a_assert( false !== strpos( $trials, 'START TRANSACTION' ) && false !== strpos( $trials, 'trial_locks' ) && false !== strpos( $trials, 'trial_already_used' ), 'Trial activation must serialize and reject double consumption.' );

$now = '2026-09-08 12:00:00';
$free = Faluss_Subscriptions_Resolver::resolve_records( array(), array(), array(), $now );
sub01a_assert( 'free' === $free['level'] && false === $free['entitlements']['faluss.pro'], 'Absent subscription or entitlement must resolve to Gratuit.' );
$trial = Faluss_Subscriptions_Resolver::resolve_records( array(), array( array( 'trial_state' => 'trialing', 'expires_at' => '2026-09-23 12:00:00', 'trial_uuid' => 'trial-a' ) ), array(), $now );
sub01a_assert( 'pro' === $trial['level'] && 'trialing' === $trial['state'], 'A valid trial must resolve to Pro.' );
$trial_expired = Faluss_Subscriptions_Resolver::resolve_records( array(), array( array( 'trial_state' => 'trialing', 'expires_at' => $now, 'trial_uuid' => 'trial-b' ) ), array(), $now );
sub01a_assert( 'free' === $trial_expired['level'] && 'trial_expired' === $trial_expired['reason'], 'An exact trial expiration must resolve to Gratuit without cron.' );
$active = Faluss_Subscriptions_Resolver::resolve_records( array( array( 'normalized_state' => 'active', 'period_ends_at' => '2026-10-08 12:00:00', 'subscription_uuid' => 'sub-a' ) ), array(), array(), $now );
sub01a_assert( 'pro' === $active['level'] && 'active' === $active['state'], 'An active subscription must resolve to Pro.' );
$canceling = Faluss_Subscriptions_Resolver::resolve_records( array( array( 'normalized_state' => 'canceling', 'period_ends_at' => '2026-10-08 12:00:00', 'subscription_uuid' => 'sub-b' ) ), array(), array(), $now );
sub01a_assert( 'pro' === $canceling['level'] && 'canceling' === $canceling['state'], 'Cancellation at period end must preserve Pro until the term.' );
$grace_record = array( 'normalized_state' => 'past_due', 'period_ends_at' => '2026-09-08 12:00:00', 'grace_ends_at' => '2026-10-01 12:00:00', 'subscription_uuid' => 'sub-c' );
$grace = Faluss_Subscriptions_Resolver::resolve_records( array( $grace_record ), array(), array(), $now );
$after_grace = Faluss_Subscriptions_Resolver::resolve_records( array( $grace_record ), array(), array(), '2026-09-15 12:00:00' );
sub01a_assert( 'pro' === $grace['level'] && 'past_due' === $grace['state'] && 'free' === $after_grace['level'], 'Past due must preserve Pro for no more than the central seven-day grace period.' );
$revoked = Faluss_Subscriptions_Resolver::resolve_records( array( array( 'normalized_state' => 'active', 'period_ends_at' => '2026-10-08 12:00:00', 'subscription_uuid' => 'sub-d' ) ), array(), array( array( 'entitlement_key' => 'faluss.pro', 'entitlement_value' => 'revoked', 'source' => 'compliance_override', 'status' => 'active', 'starts_at' => '2026-09-01 00:00:00', 'expires_at' => null ) ), $now );
sub01a_assert( 'free' === $revoked['level'] && 'revoked' === $revoked['state'] && 'compliance_override' === $revoked['reason'], 'Compliance revocation must win over positive sources.' );
$admin_pro = Faluss_Subscriptions_Resolver::resolve_records( array(), array(), array( array( 'entitlement_key' => 'faluss.pro', 'entitlement_value' => 'pro', 'source' => 'admin_grant', 'priority' => 300, 'status' => 'active', 'starts_at' => '2026-09-01 00:00:00', 'expires_at' => '2026-10-01 00:00:00', 'entitlement_uuid' => 'grant-a' ) ), $now );
$admin_expired = Faluss_Subscriptions_Resolver::resolve_records( array(), array(), array( array( 'entitlement_key' => 'faluss.pro', 'entitlement_value' => 'pro', 'source' => 'admin_grant', 'priority' => 300, 'status' => 'active', 'starts_at' => '2026-09-01 00:00:00', 'expires_at' => $now, 'entitlement_uuid' => 'grant-b' ) ), $now );
sub01a_assert( 'pro' === $admin_pro['level'] && 'comped' === $admin_pro['state'] && 'free' === $admin_expired['level'], 'Temporary administrative Pro must apply and expire deterministically.' );

sub01a_assert( false !== strpos( $schema, 'provider_event_unique' ) && false !== strpos( $repository, "'idempotent' => true" ) && false !== strpos( $repository, 'payload_hash' ), 'Future provider events must be idempotent and retain only a payload hash.' );
sub01a_assert( false !== strpos( $audit, 'clean_state' ) && false !== strpos( $audit, 'payment|payload|fingerprint' ) && false !== strpos( $entitlements, 'admin_pro_granted' ) && false !== strpos( $entitlements, 'admin_pro_revoked' ), 'Sensitive mutations must be audited with sanitized states.' );
foreach ( array( "CAPABILITY = 'manage_faluss_subscriptions'", 'current_user_can', 'check_admin_referer', "'POST'", 'wp_safe_redirect' ) as $needle ) { sub01a_assert( false !== strpos( $admin, $needle ), 'Administration must retain capability/nonce/POST controls: ' . $needle ); }

$all_source = ''; foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin ) ) as $file ) { if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) { $all_source .= file_get_contents( $file->getPathname() ); } }
sub01a_assert( false === strpos( $all_source, 'register_rest_route' ) && false === strpos( $all_source, 'wp_ajax_' ) && false === strpos( $all_source, 'add_shortcode' ), 'SUB-01A must expose no client trial activation endpoint.' );
sub01a_assert( 0 === preg_match( '/(?:price|prod)_[A-Za-z0-9]+/', $all_source ) && 0 === preg_match( '/(?:sk|pk)_(?:live|test)_[A-Za-z0-9]+/', $all_source ), 'SUB-01A must not hard-code a payment-provider identifier or secret.' );
sub01a_assert( false !== strpos( $documentation, 'SUB-01B' ) && false !== strpos( $documentation, 'Token Engine' ) && false !== strpos( $documentation, 'FL-21' ), 'Documentation must preserve boundaries and future lots.' );

echo "SUB-01A subscriptions contract: OK\n";
