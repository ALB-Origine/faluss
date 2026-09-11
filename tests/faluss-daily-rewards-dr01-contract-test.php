<?php

function dr01_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function dr01_status( $app_key, $reward_key, $owner, $status, $reward, $delegation ) {
    return array(
        'app_key'       => $app_key,
        'reward_key'    => $reward_key,
        'owner'         => $owner,
        'status'        => $status,
        'reward'        => $reward,
        'period'        => array(
            'type'         => 'daily',
            'timezone'     => 'Europe/Paris',
            'logical_date' => '2026-09-11',
        ),
        'delegation'    => $delegation,
        'freshness'     => array(
            'generated_at'    => '2026-09-11T10:00:00Z',
            'max_age_seconds' => 60,
            'stale_behavior'  => 'refresh_from_owner',
        ),
        'source'        => array(
            'type'           => 'owner_daily_reward_read_model',
            'engine'         => $owner,
            'read_model'     => $app_key . '-daily-reward',
            'source_version' => '1.0.0',
        ),
        'compatibility' => array(
            'minimum_consumer_version' => '1.0.0',
            'backward_compatible_with' => array( '1.0.0' ),
            'deprecated'               => false,
            'sunset_at'                => null,
            'replacement_reward_key'   => null,
        ),
    );
}

function dr01_contains_forbidden_data( $value, $forbidden ) {
    if ( ! is_array( $value ) ) {
        return false;
    }

    foreach ( $value as $key => $child ) {
        if ( is_string( $key ) ) {
            $normalized = strtolower( str_replace( '-', '_', $key ) );
            foreach ( $forbidden as $needle ) {
                if ( false !== strpos( $normalized, $needle ) ) {
                    return true;
                }
            }
        }
        if ( dr01_contains_forbidden_data( $child, $forbidden ) ) {
            return true;
        }
    }

    return false;
}

function dr01_status_is_valid( $document, $schema, &$error ) {
    foreach ( $schema['required'] as $field ) {
        if ( ! array_key_exists( $field, $document ) ) {
            $error = 'missing_' . $field;
            return false;
        }
    }

    if ( array_diff( array_keys( $document ), array_keys( $schema['properties'] ) ) ) {
        $error = 'unknown_field';
        return false;
    }

    if ( ! is_string( $document['app_key'] ) || ! preg_match( '~' . $schema['properties']['app_key']['pattern'] . '~D', $document['app_key'] ) || ! is_string( $document['reward_key'] ) || ! preg_match( '~' . $schema['properties']['reward_key']['pattern'] . '~D', $document['reward_key'] ) || ! is_string( $document['owner'] ) || ! preg_match( '~' . $schema['$defs']['engineId']['pattern'] . '~D', $document['owner'] ) ) {
        $error = 'invalid_identity';
        return false;
    }

    if ( ! in_array( $document['status'], $schema['properties']['status']['enum'], true ) ) {
        $error = 'invalid_status';
        return false;
    }

    if ( 'daily' !== ( $document['period']['type'] ?? null ) || 'Europe/Paris' !== ( $document['period']['timezone'] ?? null ) || ! is_string( $document['period']['logical_date'] ?? null ) || ! preg_match( '~^[0-9]{4}-[0-9]{2}-[0-9]{2}$~D', $document['period']['logical_date'] ) ) {
        $error = 'invalid_period';
        return false;
    }

    if ( 'owner_daily_reward_read_model' !== ( $document['source']['type'] ?? null ) || ( $document['owner'] ?? null ) !== ( $document['source']['engine'] ?? null ) || ! is_string( $document['source']['read_model'] ?? null ) || ! preg_match( '~' . $schema['$defs']['semanticVersion']['pattern'] . '~D', $document['source']['source_version'] ?? '' ) ) {
        $error = 'invalid_source';
        return false;
    }

    if ( ! is_string( $document['freshness']['generated_at'] ?? null ) || ! is_int( $document['freshness']['max_age_seconds'] ?? null ) || 1 > $document['freshness']['max_age_seconds'] || ! in_array( $document['freshness']['stale_behavior'] ?? null, $schema['properties']['freshness']['properties']['stale_behavior']['enum'], true ) ) {
        $error = 'invalid_freshness';
        return false;
    }

    $version_pattern = '~' . $schema['$defs']['semanticVersion']['pattern'] . '~D';
    if ( ! preg_match( $version_pattern, $document['compatibility']['minimum_consumer_version'] ?? '' ) || ! is_array( $document['compatibility']['backward_compatible_with'] ?? null ) || ! is_bool( $document['compatibility']['deprecated'] ?? null ) || ! array_key_exists( 'sunset_at', $document['compatibility'] ) || ! array_key_exists( 'replacement_reward_key', $document['compatibility'] ) ) {
        $error = 'invalid_compatibility';
        return false;
    }

    $delegation = $document['delegation'];
    if ( ! is_array( $delegation ) || ! in_array( $delegation['type'] ?? null, $schema['properties']['delegation']['properties']['type']['enum'], true ) || ! array_key_exists( 'action_key', $delegation ) || ! array_key_exists( 'target', $delegation ) ) {
        $error = 'invalid_delegation';
        return false;
    }

    $reward = $document['reward'];
    if ( null !== $reward && ( ! is_array( $reward ) || ! is_int( $reward['amount_pf'] ?? null ) || 1 > $reward['amount_pf'] || ! in_array( $reward['economic_class'] ?? null, $schema['properties']['reward']['oneOf'][1]['properties']['economic_class']['enum'], true ) || ! is_string( $reward['label'] ?? null ) || '' === $reward['label'] ) ) {
        $error = 'invalid_reward';
        return false;
    }

    if ( 'claimable' === $document['status'] && ( null === $reward || ! in_array( $delegation['type'], array( 'owner_claim', 'owner_navigation' ), true ) ) ) {
        $error = 'claimable_not_delegated';
        return false;
    }

    if ( in_array( $document['status'], array( 'claimed', 'ineligible', 'unavailable', 'not_supported' ), true ) && ( 'none' !== $delegation['type'] || null !== $delegation['action_key'] || null !== $delegation['target'] ) ) {
        $error = 'non_claimable_mutation';
        return false;
    }

    if ( in_array( $document['status'], array( 'ineligible', 'unavailable', 'not_supported' ), true ) && null !== $reward ) {
        $error = 'unavailable_reward_announced';
        return false;
    }

    if ( 'hub' === $document['app_key'] && ( 'hub.daily_accrual' !== $document['reward_key'] || 'faluss-hub' !== $document['owner'] ) ) {
        $error = 'invalid_hub_identity';
        return false;
    }

    if ( 'me' === $document['app_key'] && ( 'me.profile_daily_claim' !== $document['reward_key'] || 'faluss-me' !== $document['owner'] ) ) {
        $error = 'invalid_me_identity';
        return false;
    }

    if ( dr01_contains_forbidden_data( $document, array( 'faluss_id', 'email', 'session', 'balance', 'payment', 'stripe', 'card', 'customer', 'checkout', 'price', 'invoice', 'secret', 'history', 'eligibility_detail' ) ) ) {
        $error = 'sensitive_status_data';
        return false;
    }

    $error = '';
    return true;
}

function dr01_daily_decision_key( $owner, $reward_key, $subject_faluss_id, $logical_date, $policy_version ) {
    return implode( '|', array( $owner, $reward_key, $subject_faluss_id, $logical_date, $policy_version ) );
}

function dr01_decisions_are_unique( $decisions ) {
    $seen = array();
    foreach ( $decisions as $decision ) {
        $key = dr01_daily_decision_key( $decision['owner'], $decision['reward_key'], $decision['subject_faluss_id'], $decision['logical_date'], $decision['policy_version'] );
        if ( isset( $seen[ $key ] ) ) {
            return false;
        }
        $seen[ $key ] = true;
    }
    return true;
}

function dr01_me_is_eligible( $identity_active, $published_card, $reserved_handle ) {
    return true === $identity_active && true === $published_card && true === $reserved_handle;
}

$root = dirname( __DIR__ );
$contract = file_get_contents( $root . '/docs/DAILY_REWARDS_CONTRACT.md' );
$pf_contract = file_get_contents( $root . '/docs/POINTS_FALUSS_CONTRACT.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );
$schema_json = file_get_contents( $root . '/contracts/faluss-daily-reward.schema.json' );
$schema = json_decode( $schema_json, true );

dr01_assert( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'The DR-01 status schema must be valid JSON.' );
dr01_assert( 'https://json-schema.org/draft/2020-12/schema' === $schema['$schema'], 'The DR-01 schema must declare JSON Schema 2020-12.' );
dr01_assert( false === $schema['additionalProperties'], 'The daily reward status must reject undeclared top-level data.' );
dr01_assert( array( 'claimable', 'claimed', 'ineligible', 'unavailable', 'not_supported' ) === $schema['properties']['status']['enum'], 'Daily reward statuses must be closed.' );
dr01_assert( 'daily' === $schema['x-daily-reward-invariants']['period_type'] && 'Europe/Paris' === $schema['x-daily-reward-invariants']['period_timezone'], 'Daily rewards must use the immutable Europe/Paris daily period.' );
dr01_assert( false === $schema['x-daily-reward-invariants']['wordpress_timezone_mutable'] && false === $schema['x-daily-reward-invariants']['missed_day_catchup'], 'WordPress timezone changes and missed-day catch-up must be prohibited.' );
dr01_assert( true === $schema['x-daily-reward-invariants']['hub_and_me_cumulative_same_day'] && 95 === $schema['x-daily-reward-invariants']['maximum_daily_earned_pf'], 'Hub and Me must be cumulable only up to 95 earned PF per day.' );
dr01_assert( false === $schema['x-daily-reward-invariants']['portal_writes_rewards'] && false === $schema['x-daily-reward-invariants']['browser_or_url_writes_rewards'] && false === $schema['x-daily-reward-invariants']['duplicate_idempotent_pf_write'], 'Portal, browser, URL and retries must not write a second PF consequence.' );

$hub_claimable = dr01_status(
    'hub',
    'hub.daily_accrual',
    'faluss-hub',
    'claimable',
    array( 'amount_pf' => 20, 'economic_class' => 'earned', 'label' => 'Récupérer +20 PF' ),
    array( 'type' => 'owner_claim', 'action_key' => 'claim-daily-reward', 'target' => null )
);
dr01_assert( dr01_status_is_valid( $hub_claimable, $schema, $error ), 'A claimable Hub 20 PF earned reward delegated to its owner must be valid: ' . $error );

$hub_claimed = $hub_claimable;
$hub_claimed['status'] = 'claimed';
$hub_claimed['delegation'] = array( 'type' => 'none', 'action_key' => null, 'target' => null );
dr01_assert( dr01_status_is_valid( $hub_claimed, $schema, $error ), 'A claimed Hub reward must have no mutating delegation: ' . $error );

$me_claimable = dr01_status(
    'me',
    'me.profile_daily_claim',
    'faluss-me',
    'claimable',
    array( 'amount_pf' => 75, 'economic_class' => 'earned', 'label' => 'Récupérer +75 PF' ),
    array( 'type' => 'owner_navigation', 'action_key' => 'open-published-card', 'target' => 'faluss-me.published-card' )
);
dr01_assert( dr01_status_is_valid( $me_claimable, $schema, $error ), 'A claimable Me 75 PF earned reward must navigate to its owner: ' . $error );
dr01_assert( dr01_me_is_eligible( true, true, true ), 'An active identity with a published card and reserved handle is eligible for the Me reward.' );
dr01_assert( ! dr01_me_is_eligible( true, false, true ) && ! dr01_me_is_eligible( true, true, false ), 'A Me claim without a published card or reserved handle must be ineligible.' );
dr01_assert( 95 === $hub_claimable['reward']['amount_pf'] + $me_claimable['reward']['amount_pf'], 'Distinct Hub and Me rewards may total exactly 95 earned PF on the same day.' );

$future = dr01_status(
    'date',
    'date.daily-exploration',
    'faluss-date',
    'claimable',
    array( 'amount_pf' => 5, 'economic_class' => 'earned', 'label' => 'Reward Date' ),
    array( 'type' => 'owner_claim', 'action_key' => 'claim-daily-reward', 'target' => null )
);
dr01_assert( dr01_status_is_valid( $future, $schema, $error ), 'A future derived reward with distinct app, owner, key and source must remain valid: ' . $error );

$decision = array( 'owner' => 'faluss-hub', 'reward_key' => 'hub.daily_accrual', 'subject_faluss_id' => '11111111-1111-4111-8111-111111111111', 'logical_date' => '2026-09-11', 'policy_version' => '1.0.0' );
dr01_assert( ! dr01_decisions_are_unique( array( $decision, $decision ) ), 'Two Hub gains for the same member, day and policy must be rejected as duplicate consequences.' );
$me_decision = $decision;
$me_decision['owner'] = 'faluss-me';
$me_decision['reward_key'] = 'me.profile_daily_claim';
dr01_assert( dr01_decisions_are_unique( array( $decision, $me_decision ) ), 'Different owner reward keys may coexist on the same logical day without merging.' );

$bad_claimed = $hub_claimed;
$bad_claimed['delegation'] = array( 'type' => 'owner_claim', 'action_key' => 'claim-daily-reward', 'target' => null );
dr01_assert( ! dr01_status_is_valid( $bad_claimed, $schema, $error ) && 'non_claimable_mutation' === $error, 'A claimed status must never carry a claim delegation.' );
$bad_timezone = $hub_claimable;
$bad_timezone['period']['timezone'] = 'America/Montreal';
dr01_assert( ! dr01_status_is_valid( $bad_timezone, $schema, $error ) && 'invalid_period' === $error, 'A mutable or browser timezone must not define the daily period.' );
$bad_portal = $me_claimable;
$bad_portal['owner'] = 'faluss-portal';
$bad_portal['source']['engine'] = 'faluss-portal';
dr01_assert( ! dr01_status_is_valid( $bad_portal, $schema, $error ) && 'invalid_me_identity' === $error, 'Portal must never become the Faluss Me reward owner.' );
$bad_sensitive = $hub_claimable;
$bad_sensitive['source']['faluss_id'] = '11111111-1111-4111-8111-111111111111';
dr01_assert( ! dr01_status_is_valid( $bad_sensitive, $schema, $error ) && 'sensitive_status_data' === $error, 'A status document must not expose a Faluss ID, balance, e-mail or payment data.' );
$bad_unavailable = $hub_claimed;
$bad_unavailable['status'] = 'unavailable';
dr01_assert( ! dr01_status_is_valid( $bad_unavailable, $schema, $error ) && 'unavailable_reward_announced' === $error, 'Unavailable or ineligible apps must not announce a fake reward.' );

foreach ( array( 'hub.daily_accrual', 'me.profile_daily_claim', '`20 PF`', '`75 PF`', '`95 PF earned`', '`Europe/Paris`', 'owner_navigation', 'owner_claim', 'reward non déclaré', 'ne simule jamais un succès' ) as $needle ) {
    dr01_assert( false !== strpos( $contract, $needle ), 'The DR-01 contract is missing a required rule: ' . $needle );
}
foreach ( array( 'DR-01', 'hub.daily_accrual', 'me.profile_daily_claim', 'PF-02B est autorisé', '`95 PF', 'Europe/Paris' ) as $needle ) {
    dr01_assert( false !== strpos( $pf_contract, $needle ), 'PF-02A must incorporate the decided DR-01 daily rules: ' . $needle );
}
dr01_assert( false !== strpos( $architecture, '### Daily Rewards DR-01' ) && false !== strpos( $architecture, 'Hub/Portal n\'écrit' ), 'Architecture must preserve owner-only daily reward decisions.' );
dr01_assert( false !== strpos( $data_model, '## Daily Rewards DR-01' ) && false !== strpos( $data_model, 'n\'ajoute aucune table' ), 'Data model must state that DR-01 creates no storage.' );
dr01_assert( false !== strpos( $roadmap, '## DR-01 — Contrat fédéré des Daily Rewards Faluss' ) && false !== strpos( $roadmap, 'sans rattrapage' ), 'Roadmap must record the delivered documentary DR-01 lot.' );

$expected_scope = array(
    'contracts/faluss-daily-reward.schema.json',
    'docs/ARCHITECTURE.md',
    'docs/DAILY_REWARDS_CONTRACT.md',
    'docs/DATA_MODEL.md',
    'docs/POINTS_FALUSS_CONTRACT.md',
    'docs/ROADMAP.md',
    'tests/faluss-daily-rewards-dr01-contract-test.php',
    'tests/faluss-pf-pf02a-contract-test.php',
);
$scope = $schema['x-dr01-scope'];
dr01_assert( 'documentary-contract-only' === $scope['nature'], 'DR-01 must remain documentary and contract-only.' );
dr01_assert( false === $scope['wordpress_runtime_changes'] && false === $scope['migrations'] && false === $scope['routes'] && false === $scope['portal_ui_changes'] && false === $scope['installable_artifact'], 'DR-01 must declare no runtime, migration, route, Portal UI or installable artifact.' );
dr01_assert( $expected_scope === $scope['allowed_changed_paths'], 'The DR-01 scope manifest must list exactly the eight authorized files.' );
foreach ( $scope['allowed_changed_paths'] as $path ) {
    dr01_assert( 0 !== strpos( $path, 'plugins/' ) && '.zip' !== strtolower( substr( $path, -4 ) ), 'DR-01 scope must exclude plugin runtime and ZIP files: ' . $path );
}

echo 'DR-01 Daily Rewards contract: OK' . PHP_EOL;
