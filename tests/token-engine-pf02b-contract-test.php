<?php

function pf02b_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function pf02b_uuid( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
}

function pf02b_daily_idempotency( $owner, $reward_key, $faluss_id, $logical_date, $policy_version ) {
    return 'pf.daily.' . substr( hash( 'sha256', $owner . '|' . $reward_key . '|' . $faluss_id . '|' . $logical_date . '|' . $policy_version ), 0, 48 );
}

function pf02b_model_balance( $entries, $faluss_id, $economic_class ) {
    $by_uuid = array();
    foreach ( $entries as $entry ) {
        $by_uuid[ $entry['entry_uuid'] ] = $entry;
    }
    $balance = 0;
    foreach ( $entries as $entry ) {
        if ( $faluss_id !== $entry['faluss_id'] || $economic_class !== $entry['economic_class'] ) {
            continue;
        }
        if ( 'credit' === $entry['direction'] ) {
            $balance += $entry['amount_pf'];
        } elseif ( 'debit' === $entry['direction'] ) {
            $balance -= $entry['amount_pf'];
        } elseif ( 'compensation' === $entry['direction'] && isset( $by_uuid[ $entry['compensates_entry_uuid'] ] ) ) {
            $balance += 'credit' === $by_uuid[ $entry['compensates_entry_uuid'] ]['direction'] ? -$entry['amount_pf'] : $entry['amount_pf'];
        }
    }
    return $balance;
}

function pf02b_model_write( &$entries, $entry, &$error ) {
    if ( ! pf02b_uuid( $entry['entry_uuid'] ?? '' ) || ! pf02b_uuid( $entry['faluss_id'] ?? '' ) || ! is_int( $entry['amount_pf'] ?? null ) || 1 > $entry['amount_pf'] || ! in_array( $entry['direction'] ?? '', array( 'credit', 'debit', 'compensation' ), true ) || ! in_array( $entry['economic_class'] ?? '', array( 'earned', 'funded', 'promotional' ), true ) ) {
        $error = 'invalid_entry';
        return false;
    }
    foreach ( $entries as $existing ) {
        if ( $existing['idempotency_key'] === $entry['idempotency_key'] ) {
            $error = 'idempotent';
            return $existing;
        }
    }
    $original = null;
    if ( 'compensation' === $entry['direction'] ) {
        foreach ( $entries as $candidate ) {
            if ( $candidate['entry_uuid'] === ( $entry['compensates_entry_uuid'] ?? '' ) ) {
                $original = $candidate;
                break;
            }
        }
        if ( ! $original || 'compensation' === $original['direction'] || $original['faluss_id'] !== $entry['faluss_id'] || $original['economic_class'] !== $entry['economic_class'] ) {
            $error = 'invalid_compensation';
            return false;
        }
    } elseif ( null !== ( $entry['compensates_entry_uuid'] ?? null ) ) {
        $error = 'invalid_compensation_link';
        return false;
    }
    $effect = 'credit' === $entry['direction'] ? $entry['amount_pf'] : ( 'debit' === $entry['direction'] ? -$entry['amount_pf'] : ( 'credit' === $original['direction'] ? -$entry['amount_pf'] : $entry['amount_pf'] ) );
    if ( 0 > $effect + pf02b_model_balance( $entries, $entry['faluss_id'], $entry['economic_class'] ) ) {
        $error = 'insufficient_class_balance';
        return false;
    }
    $entries[] = $entry;
    $error = '';
    return $entry;
}

function pf02b_entry( $entry_uuid, $faluss_id, $amount, $direction, $economic_class, $category, $idempotency_key, $compensates = null ) {
    return array(
        'entry_uuid'             => $entry_uuid,
        'faluss_id'              => $faluss_id,
        'amount_pf'              => $amount,
        'direction'              => $direction,
        'economic_class'         => $economic_class,
        'category'               => $category,
        'idempotency_key'        => $idempotency_key,
        'compensates_entry_uuid' => $compensates,
    );
}

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/plugins/token-engine/token-engine.php' );
$schema = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-schema.php' );
$points = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-points-service.php' );
$generic_service = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-service.php' );
$connector = file_get_contents( $root . '/plugins/token-engine-connector/token-engine-connector.php' );
$portal = file_get_contents( $root . '/plugins/faluss-portal/includes/class-faluss-portal.php' );
$pf_contract = file_get_contents( $root . '/docs/POINTS_FALUSS_CONTRACT.md' );
$daily_contract = file_get_contents( $root . '/docs/DAILY_REWARDS_CONTRACT.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );

pf02b_assert( false !== strpos( $plugin, 'Version: 0.4.1' ) && false !== strpos( $plugin, "TOKEN_ENGINE_VERSION', '0.4.1" ) && false !== strpos( $plugin, 'class-token-engine-points-service.php' ), 'PF-02B must load the isolated Points service in Token Engine 0.4.1.' );
foreach ( array( "const VERSION = '5'", "const V4_VERSION = '4'", "const V3_VERSION = '3'", 'pf_ledger_table()', 'migrate_v3_to_v4', 'migrate_v4_to_v5', 'v3_schema_ready', 'v4_schema_ready', 'token_engine_pf_ledger', 'ENGINE=InnoDB', 'pf_entry_uuid_unique', 'pf_idempotency_key_unique', 'pf_subject_class_date', 'pf_subject_category_date', 'pf_source_category_date', 'pf_compensates_entry_unique' ) as $needle ) {
    pf02b_assert( false !== strpos( $schema, $needle ), 'PF-02B schema invariant is missing: ' . $needle );
}
$migration_start = strpos( $schema, 'private static function migrate_v3_to_v4()' );
$migration_end = strpos( $schema, 'private static function migrate_v4_to_v5()', $migration_start );
$migration = false !== $migration_start && false !== $migration_end ? substr( $schema, $migration_start, $migration_end - $migration_start ) : '';
pf02b_assert( '' !== $migration && false === strpos( $migration, 'ALTER TABLE' ) && false === strpos( $migration, 'self::ledger_table()' ) && false !== strpos( $migration, "create_query( 'pf_ledger'" ), 'The 3-to-4 migration must add only the PF table and leave token_engine_ledger untouched.' );
foreach ( array( '`entry_uuid` char(36)', '`faluss_id` char(36)', '`amount_pf` bigint(20) unsigned', '`direction` varchar(12)', '`economic_class` varchar(20)', '`category` varchar(64)', '`category_version` varchar(32)', '`source_owner` varchar(64)', '`source_event_reference` varchar(191)', '`idempotency_key` varchar(191)', '`policy_version` varchar(32)', '`occurred_at` datetime', '`compensates_entry_uuid` char(36) NULL', '`administrative_reason` varchar(191) NULL', '`metadata` longtext', '`created_at` datetime' ) as $needle ) {
    pf02b_assert( false !== strpos( $schema, $needle ), 'The PF table must include its required immutable field: ' . $needle );
}

foreach ( array( 'balances_by_class', 'daily_status', 'claim_hub_daily', 'claim_me_profile_daily', 'validate_future_entry', 'compensate_entry', 'START TRANSACTION', 'FOR UPDATE', 'GET_LOCK', 'idempotency_key', 'DateTimeImmutable', "DateTimeZone( 'Europe/Paris' )", 'pf_insufficient_class_balance', 'pf_feature_not_enabled', 'compensates_entry_uuid' ) as $needle ) {
    pf02b_assert( false !== strpos( $points, $needle ), 'The internal PF service invariant is missing: ' . $needle );
}
foreach ( array( 'register_rest_route', 'wp_ajax_', 'add_shortcode', 'wp_schedule', 'wp_cron', '$_POST', '$_GET', 'wp_user_id', 'Token_Engine_Service::balance', 'Token_Engine_Schema::ledger_table' ) as $forbidden ) {
    pf02b_assert( false === strpos( $points, $forbidden ), 'PF-02B must not expose a browser/runtime surface or access the generic ALB ledger: ' . $forbidden );
}
pf02b_assert( false === strpos( $generic_service, 'Token_Engine_Points_Service' ) && false === strpos( $connector, 'Token_Engine_Points_Service' ), 'The generic service and Connector must never wire themselves to the PF ledger.' );
pf02b_assert( false !== strpos( $portal, 'Token_Engine_Points_Service::daily_status' ) && false !== strpos( $portal, 'Token_Engine_Points_Service::claim_hub_daily' ) && false === strpos( $portal, 'Token_Engine_Points_Service::balances_by_class' ) && false === strpos( $portal, 'Token_Engine_Points_Service::claim_me_profile_daily' ) && false === strpos( $portal, 'Token_Engine_Schema::pf_ledger_table' ), 'DR-02A Portal may use only the fixed Hub daily adapter and must not read balances, invoke Me or access a PF table.' );

$faluss_id = '11111111-1111-4111-8111-111111111111';
$hub_key = pf02b_daily_idempotency( 'faluss-hub', 'hub.daily_accrual', $faluss_id, '2026-09-11', '1.0.0' );
$me_key = pf02b_daily_idempotency( 'faluss-me', 'me.profile_daily_claim', $faluss_id, '2026-09-11', '1.0.0' );
pf02b_assert( $hub_key !== $me_key, 'Hub and Me must derive distinct Core idempotency keys for the same member and day.' );

$entries = array();
$hub = pf02b_entry( '22222222-2222-4222-8222-222222222222', $faluss_id, 20, 'credit', 'earned', 'daily_accrual', $hub_key );
$first_hub = pf02b_model_write( $entries, $hub, $error );
pf02b_assert( is_array( $first_hub ) && '' === $error, 'Hub must append one 20 PF earned credit.' );
$second_hub = pf02b_model_write( $entries, $hub, $error );
pf02b_assert( is_array( $second_hub ) && 'idempotent' === $error && 1 === count( $entries ), 'A retry or simulated concurrent Hub claim must return the existing result without a second line.' );
$me = pf02b_entry( '33333333-3333-4333-8333-333333333333', $faluss_id, 75, 'credit', 'earned', 'profile_daily_claim', $me_key );
pf02b_assert( is_array( pf02b_model_write( $entries, $me, $error ) ) && 95 === pf02b_model_balance( $entries, $faluss_id, 'earned' ), 'Hub 20 PF and Me 75 PF must remain cumulable at exactly 95 earned PF.' );
pf02b_assert( ! pf02b_uuid( 'not-a-faluss-id' ) && ! pf02b_model_write( $entries, pf02b_entry( 'not-a-uuid', $faluss_id, 1, 'credit', 'earned', 'daily_accrual', 'pf.daily.invalid-entry' ), $error ), 'PF entries require UUID v4 values for both entry and subject.' );
pf02b_assert( ! pf02b_model_write( $entries, pf02b_entry( '44444444-4444-4444-8444-444444444444', $faluss_id, 0, 'credit', 'earned', 'daily_accrual', 'pf.daily.zero-amount' ), $error ), 'PF amounts must be strictly positive integers.' );
$debit = pf02b_entry( '55555555-5555-4555-8555-555555555555', $faluss_id, 96, 'debit', 'earned', 'cosmetic_redemption', 'pf.future.negative-balance' );
pf02b_assert( ! pf02b_model_write( $entries, $debit, $error ) && 'insufficient_class_balance' === $error, 'No debit may make an economic class negative.' );
$wrong_class_compensation = pf02b_entry( '66666666-6666-4666-8666-666666666666', $faluss_id, 20, 'compensation', 'funded', 'reversal', 'pf.compensation.wrong-class', $hub['entry_uuid'] );
pf02b_assert( ! pf02b_model_write( $entries, $wrong_class_compensation, $error ) && 'invalid_compensation' === $error, 'A compensation must be linked to an original entry of the same economic class.' );
$valid_compensation = pf02b_entry( '77777777-7777-4777-8777-777777777777', $faluss_id, 20, 'compensation', 'earned', 'reversal', 'pf.compensation.hub-reversal', $hub['entry_uuid'] );
pf02b_assert( is_array( pf02b_model_write( $entries, $valid_compensation, $error ) ) && 75 === pf02b_model_balance( $entries, $faluss_id, 'earned' ), 'A valid compensation must append a linked same-class correction instead of editing the Hub entry.' );

foreach ( array( 'faluss-hub', 'hub.daily_accrual', 'daily_accrual', '20', 'faluss-me', 'me.profile_daily_claim', 'profile_daily_claim', '75', 'Europe/Paris', 'published_card', 'reserved_handle' ) as $needle ) {
    pf02b_assert( false !== strpos( $points, $needle ), 'The two fixed daily rewards or their server proof are missing: ' . $needle );
}
foreach ( array( 'PF-02B — Core réel isolé', 'token_engine_pf_ledger', 'ledger ALB existant', "Aucune ligne PF n'est", "créée à l'installation" ) as $needle ) {
    pf02b_assert( false !== strpos( $pf_contract, $needle ), 'PF documentation must state the actual isolated Core boundary: ' . $needle );
}
pf02b_assert( false !== strpos( $daily_contract, 'aucun adaptateur Hub ou Faluss Me') && false !== strpos( $daily_contract, 'rattrapage' ) && false !== strpos( $architecture, '### Core PF-02B' ) && false !== strpos( $architecture, '### Daily Reward Hub DR-02A' ) && false !== strpos( $data_model, '## Points Faluss PF-02B' ) && false !== strpos( $data_model, "DR-02A n'ajoute toujours ni table" ) && false !== strpos( $roadmap, '## PF-02B — Core réel du ledger Points Faluss' ) && false !== strpos( $roadmap, '## DR-02A — Gain quotidien Faluss Hub réel' ), 'Architecture, data model, roadmap and DR-01 must retain the isolated Core contract while documenting the sole Hub adapter.' );

echo 'PF-02B Token Engine Points ledger contract: OK' . PHP_EOL;
