<?php

function pf02a_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function pf02a_entry( $category, $economic_class, $direction, $amount_pf, $suffix = 'a1b2c3d4e5f60708' ) {
    return array(
        'entry_id'               => '11111111-1111-4111-8111-111111111111',
        'subject_faluss_id'      => '22222222-2222-4222-8222-222222222222',
        'amount_pf'              => $amount_pf,
        'direction'              => $direction,
        'economic_class'         => $economic_class,
        'category'               => $category,
        'category_version'       => '1.0.0',
        'source_owner'           => 'faluss-pf-policy',
        'source_event_reference' => 'event-' . $suffix,
        'idempotency_key'        => 'pf-idempotency-' . $suffix,
        'policy_version'         => '1.0.0',
        'occurred_at'            => '2026-09-11T10:00:00Z',
        'compensates_entry_id'   => null,
        'administrative_reason'  => null,
        'write_origin'           => 'server',
        'metadata'               => array(),
    );
}

function pf02a_contains_forbidden_metadata( $value, $needles ) {
    if ( ! is_array( $value ) ) {
        return false;
    }

    foreach ( $value as $key => $child ) {
        if ( is_string( $key ) ) {
            $normalized = strtolower( str_replace( '-', '_', $key ) );
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $normalized, $needle ) ) {
                    return true;
                }
            }
        }
        if ( pf02a_contains_forbidden_metadata( $child, $needles ) ) {
            return true;
        }
    }

    return false;
}

function pf02a_entry_is_valid( $entry, $schema, &$error ) {
    foreach ( $schema['required'] as $field ) {
        if ( ! array_key_exists( $field, $entry ) ) {
            $error = 'missing_' . $field;
            return false;
        }
    }

    if ( array_diff( array_keys( $entry ), array_keys( $schema['properties'] ) ) ) {
        $error = 'unknown_field';
        return false;
    }

    $uuid_pattern = '~' . $schema['$defs']['uuidV4']['pattern'] . '~Di';
    if ( 1 !== preg_match( $uuid_pattern, $entry['entry_id'] ) || 1 !== preg_match( $uuid_pattern, $entry['subject_faluss_id'] ) ) {
        $error = 'invalid_uuid';
        return false;
    }

    if ( ! is_int( $entry['amount_pf'] ) || 1 > $entry['amount_pf'] ) {
        $error = 'invalid_amount';
        return false;
    }

    foreach ( array( 'direction', 'economic_class', 'category' ) as $field ) {
        if ( ! in_array( $entry[ $field ], $schema['properties'][ $field ]['enum'], true ) ) {
            $error = 'invalid_' . $field;
            return false;
        }
    }

    if ( 'server' !== $entry['write_origin'] ) {
        $error = 'non_server_write';
        return false;
    }

    if ( ! is_string( $entry['source_owner'] ) || ! preg_match( '~' . $schema['properties']['source_owner']['pattern'] . '~D', $entry['source_owner'] ) || in_array( $entry['source_owner'], array( 'master-profile', 'browser', 'elementor', 'javascript', 'url' ), true ) ) {
        $error = 'invalid_source_owner';
        return false;
    }

    if ( ! is_string( $entry['source_event_reference'] ) || ! preg_match( '~' . $schema['properties']['source_event_reference']['pattern'] . '~D', $entry['source_event_reference'] ) || preg_match( '~(?:^|[._-])alb(?:$|[._-])~', $entry['source_event_reference'] ) || false !== strpos( $entry['source_event_reference'], '://' ) ) {
        $error = 'invalid_source_reference';
        return false;
    }

    if ( ! is_string( $entry['idempotency_key'] ) || ! preg_match( '~' . $schema['properties']['idempotency_key']['pattern'] . '~D', $entry['idempotency_key'] ) ) {
        $error = 'invalid_idempotency_key';
        return false;
    }

    $version_pattern = '~' . $schema['$defs']['semanticVersion']['pattern'] . '~D';
    if ( 1 !== preg_match( $version_pattern, $entry['category_version'] ) || 1 !== preg_match( $version_pattern, $entry['policy_version'] ) ) {
        $error = 'invalid_version';
        return false;
    }

    if ( 'compensation' === $entry['direction'] ) {
        if ( ! is_string( $entry['compensates_entry_id'] ) || 1 !== preg_match( $uuid_pattern, $entry['compensates_entry_id'] ) ) {
            $error = 'missing_compensated_entry';
            return false;
        }
    } elseif ( null !== $entry['compensates_entry_id'] ) {
        $error = 'unexpected_compensated_entry';
        return false;
    }

    $expected_categories = array(
        'profile_daily_claim' => array( 'credit', 'earned' ),
        'daily_accrual'       => array( 'credit', 'earned' ),
        'pf_pack_purchase'    => array( 'credit', 'funded' ),
        'pf_pack_bonus'       => array( 'credit', 'promotional' ),
        'fans_support'        => array( 'debit', 'funded' ),
        'cosmetic_redemption' => array( 'debit', null ),
        'reversal'            => array( 'compensation', null ),
    );
    if ( isset( $expected_categories[ $entry['category'] ] ) ) {
        list( $direction, $economic_class ) = $expected_categories[ $entry['category'] ];
        if ( $direction !== $entry['direction'] || ( null !== $economic_class && $economic_class !== $entry['economic_class'] ) ) {
            $error = 'category_class_or_direction_mismatch';
            return false;
        }
    }

    if ( 'manual_adjustment' === $entry['category'] && ( ! is_string( $entry['administrative_reason'] ) || 3 > strlen( $entry['administrative_reason'] ) ) ) {
        $error = 'manual_reason_required';
        return false;
    }

    if ( ! is_array( $entry['metadata'] ) || pf02a_contains_forbidden_metadata( $entry['metadata'], array( 'email', 'card', 'stripe', 'payment', 'secret', 'password', 'token', 'customer', 'checkout', 'invoice', 'wp_user', 'faluss_id' ) ) ) {
        $error = 'invalid_metadata';
        return false;
    }

    $error = '';
    return true;
}

function pf02a_batch_has_unique_idempotency( $entries ) {
    $seen = array();
    foreach ( $entries as $entry ) {
        $key = $entry['idempotency_key'] ?? '';
        if ( isset( $seen[ $key ] ) ) {
            return false;
        }
        $seen[ $key ] = true;
    }
    return true;
}

function pf02a_compensation_preserves_class( $original, $compensation ) {
    return 'compensation' === ( $compensation['direction'] ?? '' )
        && ( $original['economic_class'] ?? '' ) === ( $compensation['economic_class'] ?? '' )
        && ( $original['entry_id'] ?? '' ) === ( $compensation['compensates_entry_id'] ?? '' );
}

function pf02a_monetizable_support_is_allowed( $supporter_faluss_id, $creator_faluss_id, $entry ) {
    return $supporter_faluss_id !== $creator_faluss_id
        && 'fans_support' === ( $entry['category'] ?? '' )
        && 'funded' === ( $entry['economic_class'] ?? '' )
        && 'debit' === ( $entry['direction'] ?? '' );
}

$root = dirname( __DIR__ );
$contract = file_get_contents( $root . '/docs/POINTS_FALUSS_CONTRACT.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );
$schema_json = file_get_contents( $root . '/contracts/faluss-pf-ledger-entry.schema.json' );
$schema = json_decode( $schema_json, true );

pf02a_assert( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'The PF-02A ledger schema must be valid JSON.' );
pf02a_assert( 'https://json-schema.org/draft/2020-12/schema' === $schema['$schema'], 'The PF ledger schema must declare JSON Schema 2020-12.' );
pf02a_assert( false === $schema['additionalProperties'], 'The ledger entry must reject undeclared fields.' );

$required = array(
    'entry_id',
    'subject_faluss_id',
    'amount_pf',
    'direction',
    'economic_class',
    'category',
    'category_version',
    'source_owner',
    'source_event_reference',
    'idempotency_key',
    'policy_version',
    'occurred_at',
    'compensates_entry_id',
    'administrative_reason',
    'write_origin',
    'metadata',
);
pf02a_assert( $required === $schema['required'], 'PF ledger entries must require every identity, economic, policy and compensation field.' );
pf02a_assert( array( 'earned', 'funded', 'promotional' ) === $schema['properties']['economic_class']['enum'], 'PF economic classes must be closed.' );
pf02a_assert( array( 'credit', 'debit', 'compensation' ) === $schema['properties']['direction']['enum'], 'PF directions must be explicit and closed.' );
pf02a_assert( 'server' === $schema['properties']['write_origin']['const'], 'Only a server-side future engine may write PF entries.' );

// Positive scenario: the reserved 75 PF earned profile claim has one opaque server event and one idempotency key.
$profile_claim = pf02a_entry( 'profile_daily_claim', 'earned', 'credit', 75, 'profile75-4d9e2ee6e8c34e1' );
$profile_claim['source_owner'] = 'faluss-identity';
pf02a_assert( pf02a_entry_is_valid( $profile_claim, $schema, $error ), 'The reserved 75 PF earned profile claim must be structurally valid: ' . $error );

// Positive scenario: the reserved 20 PF earned recurring accrual is a distinct idempotent future consequence.
$daily_accrual = pf02a_entry( 'daily_accrual', 'earned', 'credit', 20, 'accrual20-a4f0bb5f5b104c2d' );
pf02a_assert( pf02a_entry_is_valid( $daily_accrual, $schema, $error ), 'The reserved 20 PF earned daily accrual must be structurally valid: ' . $error );
pf02a_assert( pf02a_batch_has_unique_idempotency( array( $profile_claim, $daily_accrual ) ), 'Distinct daily sources must retain distinct idempotency keys.' );

// Positive scenario: a 6,000 PF pack retains its 5,000 funded and 1,000 promotional rights separately.
$pack_funded = pf02a_entry( 'pf_pack_purchase', 'funded', 'credit', 5000, 'pack-funded-c9a3a052aa4e42f1' );
$pack_bonus = pf02a_entry( 'pf_pack_bonus', 'promotional', 'credit', 1000, 'pack-bonus-b3d7ed7b423c4ecb' );
pf02a_assert( pf02a_entry_is_valid( $pack_funded, $schema, $error ) && pf02a_entry_is_valid( $pack_bonus, $schema, $error ) && 6000 === $pack_funded['amount_pf'] + $pack_bonus['amount_pf'], 'A future pack must preserve the funded/promotional split rather than merge economic rights.' );

// Positive scenario: a refund compensation is a separate, linked funded entry.
$refund = pf02a_entry( 'reversal', 'funded', 'compensation', 5000, 'refund-5c8f29fbf2384e9d' );
$refund['compensates_entry_id'] = $pack_funded['entry_id'];
pf02a_assert( pf02a_entry_is_valid( $refund, $schema, $error ) && pf02a_compensation_preserves_class( $pack_funded, $refund ), 'A refund must be an append-only same-class compensation linked to the original funded entry.' );

// Negative scenario: an ALB reference can never become a PF source.
$alb_conversion = $pack_funded;
$alb_conversion['source_event_reference'] = 'alb-ledger-1234567890';
pf02a_assert( ! pf02a_entry_is_valid( $alb_conversion, $schema, $error ) && 'invalid_source_reference' === $error, 'An ALB ledger reference must not convert into a PF entry.' );

// Negative scenario: compensation cannot reclassify earned or promotional PF into funded PF.
$earned_reclassification = pf02a_entry( 'reversal', 'funded', 'compensation', 75, 'reclass-earned-1a2b3c4d5e6f7788' );
$earned_reclassification['compensates_entry_id'] = $profile_claim['entry_id'];
pf02a_assert( ! pf02a_compensation_preserves_class( $profile_claim, $earned_reclassification ), 'A compensation must not reclassify earned PF as funded PF.' );
$promotional_reclassification = pf02a_entry( 'reversal', 'funded', 'compensation', 1000, 'reclass-promo-1a2b3c4d5e6f7788' );
$promotional_reclassification['compensates_entry_id'] = $pack_bonus['entry_id'];
pf02a_assert( ! pf02a_compensation_preserves_class( $pack_bonus, $promotional_reclassification ), 'A compensation must not reclassify promotional PF as funded PF.' );

// Negative scenario: only funded PF can finance a future monetizable creator support.
$earned_support = pf02a_entry( 'fans_support', 'earned', 'debit', 20, 'earned-support-5a7d8e9f0b1c2d3e' );
pf02a_assert( ! pf02a_entry_is_valid( $earned_support, $schema, $error ) && 'category_class_or_direction_mismatch' === $error, 'Earned PF must never finance a monetizable Fans support.' );
$promotional_support = pf02a_entry( 'fans_support', 'promotional', 'debit', 20, 'promo-support-5a7d8e9f0b1c2d3e' );
pf02a_assert( ! pf02a_entry_is_valid( $promotional_support, $schema, $error ) && 'category_class_or_direction_mismatch' === $error, 'Promotional PF must never finance a monetizable Fans support.' );
$funded_support = pf02a_entry( 'fans_support', 'funded', 'debit', 20, 'funded-support-5a7d8e9f0b1c2d3' );
pf02a_assert( pf02a_monetizable_support_is_allowed( $funded_support['subject_faluss_id'], '33333333-3333-4333-8333-333333333333', $funded_support ), 'A future monetizable support requires a distinct creator and funded PF.' );
pf02a_assert( ! pf02a_monetizable_support_is_allowed( $funded_support['subject_faluss_id'], $funded_support['subject_faluss_id'], $funded_support ), 'A self-transaction must produce neither creator revenue nor Hall of Fame input.' );

// Negative scenario: retries cannot create a second economic consequence.
$duplicate = $daily_accrual;
pf02a_assert( ! pf02a_batch_has_unique_idempotency( array( $daily_accrual, $duplicate ) ), 'The same idempotency key must not yield two PF entries.' );

// Negative scenario: no browser or Master Profile write can enter the ledger.
$browser_write = $profile_claim;
$browser_write['write_origin'] = 'browser';
pf02a_assert( ! pf02a_entry_is_valid( $browser_write, $schema, $error ) && 'non_server_write' === $error, 'A browser, Elementor, URL or public page cannot credit PF.' );
$master_profile_write = $profile_claim;
$master_profile_write['source_owner'] = 'master-profile';
pf02a_assert( ! pf02a_entry_is_valid( $master_profile_write, $schema, $error ) && 'invalid_source_owner' === $error, 'Master Profile must not become a PF event owner.' );

foreach ( array( 'append_only', 'idempotency_key_unique', 'balance_is_derived', 'transfers_between_members', 'reclassify_earned_or_promotional_to_funded', 'master_profile_writes', 'browser_writes', 'hall_of_fame_from_pf_balance', 'faluss_plus_direct_pf' ) as $invariant ) {
    pf02a_assert( array_key_exists( $invariant, $schema['x-ledger-invariants'] ), 'The machine-readable ledger invariants must include: ' . $invariant );
}
pf02a_assert( true === $schema['x-ledger-invariants']['append_only'] && true === $schema['x-ledger-invariants']['idempotency_key_unique'] && true === $schema['x-ledger-invariants']['balance_is_derived'], 'PF must remain append-only, idempotent and ledger-derived.' );
pf02a_assert( false === $schema['x-ledger-invariants']['transfers_between_members'] && false === $schema['x-ledger-invariants']['reclassify_earned_or_promotional_to_funded'], 'PF must prohibit transfers and economic-class reclassification.' );
pf02a_assert( false === $schema['x-ledger-invariants']['hall_of_fame_from_pf_balance'] && false === $schema['x-ledger-invariants']['faluss_plus_direct_pf'], 'PF balance and Faluss Plus must not create Hall of Fame or PF gains.' );

foreach ( array( 'profile_daily_claim', 'daily_accrual', 'pf_pack_purchase', 'pf_pack_bonus', 'fans_support', 'cosmetic_redemption', 'manual_adjustment', 'reversal' ) as $category ) {
    pf02a_assert( in_array( $category, $schema['properties']['category']['enum'], true ) && false !== strpos( $contract, '`' . $category . '`' ), 'The reserved PF category is missing from schema or contract: ' . $category );
}
foreach ( array( '`75 PF`', '`20 PF`', '72/71/72/71/72/71/71', 'jours manqués ne sont pas rattrapés', 'bloqué', "n'a été trouvé dans ce périmètre" ) as $needle ) {
    pf02a_assert( false !== strpos( $contract, $needle ), 'The ALB inspection evidence or PF-02B blocker is missing: ' . $needle );
}
foreach ( array( 'Hall of Fame ne lit jamais le ledger PF', 'n\'est ni `progression.*`, ni `fans.*`, ni `hof.*`', 'sans gain direct de PF', 'soutien créateur monétisable', 'auto-transaction' ) as $needle ) {
    pf02a_assert( false !== strpos( $contract, $needle ), 'A required PF/Fans/HOF/Progression boundary is missing: ' . $needle );
}

pf02a_assert( false !== strpos( $architecture, '### Points Faluss PF-02A' ) && false !== strpos( $architecture, 'classe économique fermée' ), 'Architecture must register the closed PF class boundary.' );
pf02a_assert( false !== strpos( $data_model, '## Points Faluss PF-02A' ) && false !== strpos( $data_model, "n'ajoute aucune table" ), 'Data model must state that PF-02A creates no storage or member balance.' );
pf02a_assert( false !== strpos( $roadmap, '## PF-02A — Contrat Points Faluss et droits économiques' ) && false !== strpos( $roadmap, '## SUB-03 — Faluss Plus' ) && false !== strpos( $roadmap, '3,99 € / mois' ), 'Roadmap must register PF-02A and the Faluss Plus product prerequisite.' );

$expected_scope = array(
    'contracts/faluss-pf-ledger-entry.schema.json',
    'docs/ARCHITECTURE.md',
    'docs/DATA_MODEL.md',
    'docs/POINTS_FALUSS_CONTRACT.md',
    'docs/ROADMAP.md',
    'tests/faluss-pf-pf02a-contract-test.php',
);
$scope = $schema['x-pf02a-scope'];
pf02a_assert( 'documentary-contract-only' === $scope['nature'], 'PF-02A must remain documentary and contract-only.' );
pf02a_assert( false === $scope['wordpress_runtime_changes'] && false === $scope['migrations'] && false === $scope['routes'] && false === $scope['installable_artifact'], 'PF-02A must declare no WordPress runtime, migration, route or installable artifact.' );
pf02a_assert( $expected_scope === $scope['allowed_changed_paths'], 'The PF-02A scope manifest must list exactly the six authorized files.' );
foreach ( $scope['allowed_changed_paths'] as $path ) {
    pf02a_assert( 0 !== strpos( $path, 'plugins/' ) && '.zip' !== strtolower( substr( $path, -4 ) ), 'PF-02A scope must exclude plugin runtime and ZIP paths: ' . $path );
}

echo 'PF-02A Points Faluss contract: OK' . PHP_EOL;
