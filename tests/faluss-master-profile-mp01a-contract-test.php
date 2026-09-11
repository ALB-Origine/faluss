<?php

function mp01a_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function mp01a_base_module( $namespace, $owner, $status = 'available' ) {
    $empty = 'empty' === $status;

    return array(
        'namespace'         => $namespace,
        'contract_version'  => '1.0.0',
        'owner'             => array(
            'engine'    => $owner,
            'authority' => str_replace( 'faluss-', '', $owner ) . '-summary',
        ),
        'subject_faluss_id' => '11111111-1111-4111-8111-111111111111',
        'activation'        => array(
            'state'      => 'enabled',
            'changed_at' => '2026-09-11T10:00:00Z',
        ),
        'audiences'         => array( 'private' ),
        'projection'        => array(
            'status'  => $status,
            'payload' => $empty ? array() : array( 'fields' => array( 'label' => 'Résumé' ) ),
        ),
        'empty_state'       => array(
            'declared' => $empty,
            'reason'   => $empty ? 'no_data' : null,
        ),
        'freshness'         => array(
            'generated_at'    => '2026-09-11T10:00:00Z',
            'max_age_seconds' => 60,
            'stale_behavior'  => 'refresh_from_owner',
        ),
        'source'            => array(
            'type'           => 'owner_read_model',
            'engine'         => $owner,
            'read_model'     => str_replace( '.', '-', $namespace ),
            'source_version' => '1.0.0',
        ),
        'delegated_actions' => array(),
        'compatibility'     => array(
            'minimum_consumer_version' => '1.0.0',
            'backward_compatible_with' => array( '1.0.0' ),
            'deprecated'                 => false,
            'sunset_at'                  => null,
            'replacement_namespace'      => null,
        ),
    );
}

function mp01a_payload_contains_forbidden_data( $value, $forbidden ) {
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
        if ( mp01a_payload_contains_forbidden_data( $child, $forbidden ) ) {
            return true;
        }
    }

    return false;
}

function mp01a_module_is_valid( $module, $schema, &$error ) {
    foreach ( $schema['required'] as $required ) {
        if ( ! array_key_exists( $required, $module ) ) {
            $error = 'missing_' . $required;
            return false;
        }
    }

    $namespace_pattern = '~' . $schema['properties']['namespace']['pattern'] . '~D';
    if ( 1 !== preg_match( $namespace_pattern, $module['namespace'] ) ) {
        $error = 'invalid_namespace';
        return false;
    }

    $version_pattern = '~' . $schema['$defs']['semanticVersion']['pattern'] . '~D';
    if ( 1 !== preg_match( $version_pattern, $module['contract_version'] ) ) {
        $error = 'invalid_contract_version';
        return false;
    }

    $faluss_id_pattern = '~' . $schema['properties']['subject_faluss_id']['pattern'] . '~Di';
    if ( 1 !== preg_match( $faluss_id_pattern, $module['subject_faluss_id'] ) ) {
        $error = 'invalid_subject';
        return false;
    }

    $allowed_audiences = $schema['properties']['audiences']['items']['enum'];
    if ( empty( $module['audiences'] ) || array_diff( $module['audiences'], $allowed_audiences ) || count( $module['audiences'] ) !== count( array_unique( $module['audiences'] ) ) ) {
        $error = 'invalid_audience';
        return false;
    }

    if ( 'owner_read_model' !== ( $module['source']['type'] ?? '' ) || ( $module['owner']['engine'] ?? '' ) !== ( $module['source']['engine'] ?? '' ) ) {
        $error = 'invalid_source_owner';
        return false;
    }

    $is_empty = 'empty' === ( $module['projection']['status'] ?? '' );
    if ( $is_empty ) {
        if ( array() !== $module['projection']['payload'] || true !== ( $module['empty_state']['declared'] ?? null ) || ! is_string( $module['empty_state']['reason'] ?? null ) ) {
            $error = 'invalid_empty_state';
            return false;
        }
    } elseif ( 'available' !== ( $module['projection']['status'] ?? '' ) || empty( $module['projection']['payload'] ) || false !== ( $module['empty_state']['declared'] ?? null ) || null !== ( $module['empty_state']['reason'] ?? null ) ) {
        $error = 'invalid_available_state';
        return false;
    }

    if ( 'disabled' === ( $module['activation']['state'] ?? '' ) && ( ! $is_empty || ! empty( $module['delegated_actions'] ) ) ) {
        $error = 'disabled_module_not_empty';
        return false;
    }

    $family = explode( '.', $module['namespace'], 2 )[0] . '.';
    foreach ( ( $module['projection']['payload']['metrics'] ?? array() ) as $metric => $value ) {
        unset( $value );
        if ( 0 !== strpos( $metric, $family ) ) {
            $error = 'foreign_metric_namespace';
            return false;
        }
    }

    if ( in_array( 'public', $module['audiences'], true ) ) {
        if ( 'subscriptions.private' === $module['namespace'] || 'pf.summary' === $module['namespace'] ) {
            $error = 'private_module_public';
            return false;
        }
        if ( mp01a_payload_contains_forbidden_data( $module['projection']['payload'], $schema['x-public-forbidden-payload-data'] ) ) {
            $error = 'sensitive_public_payload';
            return false;
        }
    }

    foreach ( $module['delegated_actions'] as $action ) {
        if ( ( $action['owner'] ?? '' ) !== $module['owner']['engine'] || 'owner_deep_link' !== ( $action['delegation']['type'] ?? '' ) ) {
            $error = 'invalid_delegated_action';
            return false;
        }
    }

    $error = '';
    return true;
}

function mp01a_public_projection_allowed( $ghost_until, $now ) {
    return null === $ghost_until || strtotime( $ghost_until ) <= strtotime( $now );
}

$root = dirname( __DIR__ );
$contract = file_get_contents( $root . '/docs/MASTER_PROFILE_CONTRACT.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );
$schema_json = file_get_contents( $root . '/contracts/master-profile-module.schema.json' );
$schema = json_decode( $schema_json, true );

mp01a_assert( is_array( $schema ) && JSON_ERROR_NONE === json_last_error(), 'The MP-01A module schema must be valid JSON.' );
mp01a_assert( 'https://json-schema.org/draft/2020-12/schema' === $schema['$schema'], 'The module schema must declare JSON Schema 2020-12.' );
mp01a_assert( false === $schema['additionalProperties'], 'The generic module envelope must reject undeclared top-level fields.' );

$required_fields = array(
    'namespace',
    'contract_version',
    'owner',
    'subject_faluss_id',
    'activation',
    'audiences',
    'projection',
    'empty_state',
    'freshness',
    'source',
    'delegated_actions',
    'compatibility',
);
mp01a_assert( $required_fields === $schema['required'], 'The v1 envelope must require every federation, visibility, freshness and compatibility field.' );
mp01a_assert( array( 'private', 'members', 'public' ) === $schema['properties']['audiences']['items']['enum'], 'Module audiences must be limited to private, members and public.' );
mp01a_assert( 'owner_read_model' === $schema['properties']['source']['properties']['type']['const'], 'Every module source must be an owner-provided read-model.' );
mp01a_assert( array( 'omit', 'refresh_from_owner' ) === $schema['properties']['freshness']['properties']['stale_behavior']['enum'], 'Stale data must be omitted or refreshed from its owner.' );
mp01a_assert( array( 'available', 'empty' ) === $schema['properties']['projection']['properties']['status']['enum'], 'A module must distinguish an available payload from an explicit empty state.' );

// Positive scenario: a complete owner read-model with a concrete namespace is accepted.
$identity = mp01a_base_module( 'identity.core', 'faluss-identity' );
$identity['audiences'] = array( 'private', 'members', 'public' );
$identity['projection']['payload'] = array(
    'fields' => array(
        'display_name' => 'Alice',
        'handle'       => '@alice',
        'avatar_url'   => 'https://www.faluss.me/media/alice.jpg',
    ),
);
mp01a_assert( mp01a_module_is_valid( $identity, $schema, $error ), 'A minimal owner-filtered public identity projection must be valid: ' . $error );

// Positive scenario: an owner may explicitly declare that an enabled module has no data.
$date_empty = mp01a_base_module( 'date.summary', 'faluss-date', 'empty' );
mp01a_assert( mp01a_module_is_valid( $date_empty, $schema, $error ), 'An explicit empty module with an empty payload must be valid: ' . $error );

// Negative scenario: a wildcard family cannot collide with a concrete module namespace.
$wildcard = mp01a_base_module( 'date.*', 'faluss-date' );
mp01a_assert( ! mp01a_module_is_valid( $wildcard, $schema, $error ) && 'invalid_namespace' === $error, 'A wildcard namespace must be rejected in a module document.' );

// Negative scenario: no audience outside the closed three-value vocabulary is accepted.
$partner = mp01a_base_module( 'fans.creator', 'faluss-fans' );
$partner['audiences'] = array( 'partners' );
mp01a_assert( ! mp01a_module_is_valid( $partner, $schema, $error ) && 'invalid_audience' === $error, 'An undeclared audience must be rejected.' );

// Negative scenario: every derived engine keeps metrics inside its own namespace.
foreach ( array( 'date.summary' => 'faluss-date', 'fans.creator' => 'faluss-fans', 'hof.score' => 'faluss-hof' ) as $namespace => $owner ) {
    $derived = mp01a_base_module( $namespace, $owner );
    $derived['projection']['payload']['metrics'] = array( 'progression.score' => 12 );
    mp01a_assert( ! mp01a_module_is_valid( $derived, $schema, $error ) && 'foreign_metric_namespace' === $error, 'A derived module must never become a source for progression.*: ' . $namespace );
}

// Negative scenario: subscriptions, e-mail, payment and technical identifiers never become public payloads.
$sensitive_payloads = array(
    'subscriptions.private' => array( 'fields' => array( 'plan' => 'max' ) ),
    'identity.core'         => array( 'fields' => array( 'email' => 'alice@example.test' ) ),
    'fans.creator'          => array( 'fields' => array( 'payment_status' => 'paid' ) ),
    'hof.score'             => array( 'fields' => array( 'faluss_id' => '11111111-1111-4111-8111-111111111111' ) ),
    'cosmetics.equipped'    => array( 'fields' => array( 'stripe_customer' => 'cus_example' ) ),
);
foreach ( $sensitive_payloads as $namespace => $payload ) {
    $module = mp01a_base_module( $namespace, 'faluss-' . explode( '.', $namespace, 2 )[0] );
    $module['audiences'] = array( 'public' );
    $module['projection']['payload'] = $payload;
    mp01a_assert( ! mp01a_module_is_valid( $module, $schema, $error ), 'Sensitive or private account data must be rejected from a public projection: ' . $namespace );
}

// Negative scenario: ghost mode wins before every public module decision.
mp01a_assert( ! mp01a_public_projection_allowed( '2026-09-12T10:00:00Z', '2026-09-11T10:00:00Z' ), 'A future ghost_until must suppress every public projection.' );
mp01a_assert( mp01a_public_projection_allowed( '2026-09-10T10:00:00Z', '2026-09-11T10:00:00Z' ), 'Expired ghost mode must restore the previously stored visibility decision.' );

// Negative scenario: an absent module remains absent and is never replaced with a made-up zero.
$modules = array( 'identity.core' => $identity );
$absent_hof = $modules['hof.score'] ?? null;
mp01a_assert( null === $absent_hof, 'An absent module must remain absent rather than becoming a synthetic score.' );

foreach ( array( 'identity.core', 'apps.registry', 'subscriptions.private', 'pf.summary', 'progression.global', 'cosmetics.equipped', 'date.*', 'fans.creator', 'hof.score' ) as $namespace ) {
    mp01a_assert( false !== strpos( $contract, '`' . $namespace . '`' ), 'The reference module matrix is missing: ' . $namespace );
}
foreach ( array( 'progression.*', 'date', 'date.*', 'fans.*', 'hof.*', 'pont de politique futur', 'ne fusionne jamais' ) as $needle ) {
    mp01a_assert( false !== strpos( $contract, $needle ), 'The score ownership boundary is missing: ' . $needle );
}
foreach ( array( 'ghost_until', 'prioritaire', 'visibilités précédentes', 'résultat extérieur indiscernable', 'ni date, durée, cause' ) as $needle ) {
    mp01a_assert( false !== strpos( $contract, $needle ), 'The non-disclosing ghost-mode contract is missing: ' . $needle );
}
foreach ( array( 'module absent', 'absence de donnée', 'valeur est inventée', 'payload = {}' ) as $needle ) {
    mp01a_assert( false !== strpos( $contract, $needle ), 'The absence-versus-empty rule is missing: ' . $needle );
}
foreach ( array( 'abonnement', 'e-mail', 'paiement', '`faluss_id`', 'Stripe' ) as $needle ) {
    mp01a_assert( false !== strpos( $contract, $needle ), 'The public sensitive-data exclusion is missing: ' . $needle );
}
foreach ( array( 'version sémantique', 'version majeure', 'dépréciation', 'sunset_at', 'replacement_namespace' ) as $needle ) {
    mp01a_assert( false !== strpos( $contract, $needle ), 'The compatibility and deprecation policy is missing: ' . $needle );
}

mp01a_assert( false !== strpos( $architecture, '## Master Profile fédéré MP-01A' ) && false !== strpos( $architecture, 'projection fédérée, jamais une source métier' ), 'Architecture must register the federated non-authoritative boundary.' );
mp01a_assert( false !== strpos( $data_model, '## Master Profile MP-01A' ) && false !== strpos( $data_model, "n'ajoute aucune table" ), 'Data model must state that MP-01A adds no table or copied member data.' );
mp01a_assert( false !== strpos( $roadmap, '## MP-01A — Universal Profile Contract — livré' ) && false !== strpos( $roadmap, 'Aucun plugin' ), 'Roadmap must register the delivered documentary lot and its runtime exclusion.' );

$expected_scope = array(
    'contracts/master-profile-module.schema.json',
    'docs/ARCHITECTURE.md',
    'docs/DATA_MODEL.md',
    'docs/MASTER_PROFILE_CONTRACT.md',
    'docs/ROADMAP.md',
    'tests/faluss-master-profile-mp01a-contract-test.php',
);
$scope = $schema['x-mp01a-scope'];
mp01a_assert( 'documentary-contract-only' === $scope['nature'], 'MP-01A must remain a documentary and contract-only lot.' );
mp01a_assert( false === $scope['wordpress_runtime_changes'] && false === $scope['migrations'] && false === $scope['routes'] && false === $scope['installable_artifact'], 'MP-01A must declare no WordPress runtime, migration, route or installable artifact.' );
mp01a_assert( $expected_scope === $scope['allowed_changed_paths'], 'The MP-01A scope manifest must list only the six authorized artifacts.' );
foreach ( $scope['allowed_changed_paths'] as $path ) {
    mp01a_assert( 0 !== strpos( $path, 'plugins/' ) && '.zip' !== strtolower( substr( $path, -4 ) ), 'The MP-01A scope must exclude plugin runtime and ZIP files: ' . $path );
}

echo 'MP-01A Universal Profile contract: OK' . PHP_EOL;
