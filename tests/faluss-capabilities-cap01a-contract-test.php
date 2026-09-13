<?php

function cap01a_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function cap01a_schema_has_remote_ref( $value ) {
    if ( ! is_array( $value ) ) {
        return false;
    }

    if ( isset( $value['$ref'] ) && ( ! is_string( $value['$ref'] ) || 0 !== strpos( $value['$ref'], '#/' ) ) ) {
        return true;
    }

    foreach ( $value as $child ) {
        if ( cap01a_schema_has_remote_ref( $child ) ) {
            return true;
        }
    }

    return false;
}

function cap01a_has_only_keys( $value, $allowed ) {
    if ( ! is_array( $value ) ) {
        return false;
    }

    return array() === array_diff( array_keys( $value ), $allowed );
}

function cap01a_has_required_keys( $value, $required ) {
    if ( ! is_array( $value ) ) {
        return false;
    }

    foreach ( $required as $key ) {
        if ( ! array_key_exists( $key, $value ) ) {
            return false;
        }
    }

    return true;
}

function cap01a_collection_is_unique_by( $items, $fields ) {
    if ( ! is_array( $items ) || ! is_array( $fields ) || empty( $fields ) ) {
        return false;
    }

    $seen = array();
    foreach ( $items as $item ) {
        if ( ! is_array( $item ) ) {
            return false;
        }

        $parts = array();
        foreach ( $fields as $field ) {
            if ( ! array_key_exists( $field, $item ) ) {
                return false;
            }
            $parts[] = json_encode( $item[ $field ] );
        }

        $key = implode( "\x1F", $parts );
        if ( isset( $seen[ $key ] ) ) {
            return false;
        }
        $seen[ $key ] = true;
    }

    return true;
}

function cap01a_is_semver( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*\\.[0-9]+\\.[0-9]+$/D', $value );
}

function cap01a_is_app_key( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $value );
}

function cap01a_is_capability_key( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\\.[a-z][a-z0-9_.-]{1,127})+$/D', $value );
}

function cap01a_is_https_origin( $value ) {
    return is_string( $value ) && 1 === preg_match( '/^https:\\/\\/[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?(?::[0-9]{1,5})?$/D', $value );
}

function cap01a_contains_forbidden_data( $value ) {
    $forbidden = array(
        'faluss_id',
        'wp_user_id',
        'email',
        'session',
        'subscription',
        'entitlement',
        'balance',
        'payment',
        'ledger',
        'history',
    );

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

        if ( cap01a_contains_forbidden_data( $child ) ) {
            return true;
        }
    }

    return false;
}

function cap01a_freshness_is_valid( $freshness ) {
    return cap01a_has_only_keys( $freshness, array( 'generated_at', 'max_age_seconds', 'stale_behavior' ) )
        && cap01a_has_required_keys( $freshness, array( 'generated_at', 'max_age_seconds', 'stale_behavior' ) )
        && is_string( $freshness['generated_at'] )
        && is_int( $freshness['max_age_seconds'] )
        && $freshness['max_age_seconds'] >= 1
        && $freshness['max_age_seconds'] <= 86400
        && in_array( $freshness['stale_behavior'], array( 'omit', 'refresh_from_owner' ), true );
}

function cap01a_compatibility_is_valid( $compatibility, $manifest = false ) {
    $required = array( 'minimum_consumer_version', 'compatible_with', 'deprecated', 'sunset_at' );
    $allowed = $required;

    if ( $manifest ) {
        $required[] = 'replacement_capability_key';
        $allowed[] = 'replacement_capability_key';
    }

    if ( ! cap01a_has_only_keys( $compatibility, $allowed )
        || ! cap01a_has_required_keys( $compatibility, $required )
        || ! cap01a_is_semver( $compatibility['minimum_consumer_version'] )
        || ! is_array( $compatibility['compatible_with'] )
        || count( $compatibility['compatible_with'] ) !== count( array_unique( $compatibility['compatible_with'] ) )
        || ! is_bool( $compatibility['deprecated'] )
        || ( null !== $compatibility['sunset_at'] && ! is_string( $compatibility['sunset_at'] ) ) ) {
        return false;
    }

    foreach ( $compatibility['compatible_with'] as $version ) {
        if ( ! cap01a_is_semver( $version ) ) {
            return false;
        }
    }

    return ! $manifest || null === $compatibility['replacement_capability_key'] || cap01a_is_capability_key( $compatibility['replacement_capability_key'] );
}

function cap01a_manifest_is_valid( $manifest, &$error ) {
    $required = array(
        'manifest_version',
        'app_key',
        'capability_namespace',
        'owner',
        'product_state',
        'canonical_origins',
        'public_presentation',
        'official_asset',
        'capabilities',
        'compatibility',
    );

    if ( ! cap01a_has_only_keys( $manifest, $required ) || ! cap01a_has_required_keys( $manifest, $required ) ) {
        $error = 'invalid_manifest_fields';
        return false;
    }

    if ( ! cap01a_is_semver( $manifest['manifest_version'] )
        || ! cap01a_is_app_key( $manifest['app_key'] )
        || ! cap01a_is_app_key( $manifest['capability_namespace'] )
        || ! in_array( $manifest['product_state'], array( 'planned', 'active', 'maintenance', 'retired' ), true ) ) {
        $error = 'invalid_manifest_identity';
        return false;
    }

    $owner = $manifest['owner'];
    if ( ! cap01a_has_only_keys( $owner, array( 'engine', 'authority' ) )
        || ! cap01a_has_required_keys( $owner, array( 'engine', 'authority' ) )
        || ! cap01a_is_app_key( $owner['engine'] )
        || ! is_string( $owner['authority'] )
        || 1 !== preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\\.[a-z][a-z0-9-]{1,63})*$/D', $owner['authority'] ) ) {
        $error = 'invalid_owner';
        return false;
    }

    if ( empty( $manifest['canonical_origins'] ) || count( $manifest['canonical_origins'] ) > 16 || count( $manifest['canonical_origins'] ) !== count( array_unique( $manifest['canonical_origins'] ) ) ) {
        $error = 'invalid_origins';
        return false;
    }
    foreach ( $manifest['canonical_origins'] as $origin ) {
        if ( ! cap01a_is_https_origin( $origin ) ) {
            $error = 'invalid_origin';
            return false;
        }
    }

    $presentation = $manifest['public_presentation'];
    if ( ! cap01a_has_only_keys( $presentation, array( 'display_name', 'summary' ) )
        || ! cap01a_has_required_keys( $presentation, array( 'display_name', 'summary' ) )
        || ! is_string( $presentation['display_name'] )
        || ! is_string( $presentation['summary'] )
        || '' === $presentation['display_name']
        || '' === $presentation['summary']
        || strlen( $presentation['display_name'] ) > 80
        || strlen( $presentation['summary'] ) > 280 ) {
        $error = 'invalid_presentation';
        return false;
    }

    if ( null !== $manifest['official_asset'] ) {
        $asset = $manifest['official_asset'];
        if ( ! cap01a_has_only_keys( $asset, array( 'asset_key', 'mime_type', 'sha256', 'intrinsic_dimensions', 'distribution' ) )
            || ! cap01a_has_required_keys( $asset, array( 'asset_key', 'mime_type', 'sha256', 'intrinsic_dimensions', 'distribution' ) )
            || ! cap01a_is_capability_key( $asset['asset_key'] )
            || ! in_array( $asset['mime_type'], array( 'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp' ), true )
            || ! is_string( $asset['sha256'] )
            || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $asset['sha256'] )
            || ! cap01a_has_only_keys( $asset['intrinsic_dimensions'], array( 'width', 'height' ) )
            || ! cap01a_has_required_keys( $asset['intrinsic_dimensions'], array( 'width', 'height' ) )
            || ! is_int( $asset['intrinsic_dimensions']['width'] )
            || ! is_int( $asset['intrinsic_dimensions']['height'] )
            || $asset['intrinsic_dimensions']['width'] < 1
            || $asset['intrinsic_dimensions']['height'] < 1
            || ! in_array( $asset['distribution'], array( 'owner_hosted', 'immutable_embedded' ), true ) ) {
            $error = 'invalid_official_asset';
            return false;
        }
    }

    $interfaces = array( 'module_read_model', 'delegated_action', 'event_source', 'content_reference_source' );
    $slots = array(
        'portal.apps.card_action',
        'portal.analytics.dataset',
        'master_profile.module',
        'master_profile.footer_action',
        'me.studio.tab',
        'me.studio.block_source',
        'me.public.tab',
        'me.public.block',
        'analytics.events',
        'quests.events',
        'progression.events',
    );
    $event_slots = array( 'analytics.events', 'quests.events', 'progression.events' );

    if ( ! is_array( $manifest['capabilities'] ) || count( $manifest['capabilities'] ) > 64 ) {
        $error = 'invalid_capabilities';
        return false;
    }
    if ( ! cap01a_collection_is_unique_by( $manifest['capabilities'], array( 'capability_key' ) ) ) {
        $error = 'duplicate_manifest_capability_key';
        return false;
    }

    foreach ( $manifest['capabilities'] as $capability ) {
        $required_capability = array( 'capability_key', 'interfaces', 'requested_bindings', 'read_model_contract', 'symbolic_actions', 'compatibility' );
        if ( ! cap01a_has_only_keys( $capability, $required_capability )
            || ! cap01a_has_required_keys( $capability, $required_capability )
            || ! cap01a_is_capability_key( $capability['capability_key'] )
            || 0 !== strpos( $capability['capability_key'], $manifest['capability_namespace'] . '.' )
            || ! is_array( $capability['interfaces'] )
            || empty( $capability['interfaces'] )
            || count( $capability['interfaces'] ) !== count( array_unique( $capability['interfaces'] ) )
            || array_diff( $capability['interfaces'], $interfaces )
            || ! cap01a_compatibility_is_valid( $capability['compatibility'], true ) ) {
            $error = 'invalid_capability';
            return false;
        }
        if ( ! cap01a_collection_is_unique_by( $capability['requested_bindings'], array( 'slot', 'interface' ) )
            || ! cap01a_collection_is_unique_by( $capability['symbolic_actions'], array( 'action_key' ) ) ) {
            $error = 'duplicate_manifest_capability_member';
            return false;
        }

        if ( in_array( 'module_read_model', $capability['interfaces'], true ) && ! is_array( $capability['read_model_contract'] ) ) {
            $error = 'missing_typed_read_model';
            return false;
        }
        if ( is_array( $capability['read_model_contract'] )
            && ( ! cap01a_has_only_keys( $capability['read_model_contract'], array( 'document_type', 'contract_version' ) )
                || ! cap01a_has_required_keys( $capability['read_model_contract'], array( 'document_type', 'contract_version' ) )
                || ! is_string( $capability['read_model_contract']['document_type'] )
                || 1 !== preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\\.[a-z][a-z0-9-]{1,63})+$/D', $capability['read_model_contract']['document_type'] )
                || ! cap01a_is_semver( $capability['read_model_contract']['contract_version'] ) ) ) {
            $error = 'invalid_typed_read_model';
            return false;
        }

        foreach ( $capability['requested_bindings'] as $binding ) {
            if ( ! cap01a_has_only_keys( $binding, array( 'interface', 'slot' ) )
                || ! cap01a_has_required_keys( $binding, array( 'interface', 'slot' ) )
                || ! in_array( $binding['interface'], $capability['interfaces'], true )
                || ! in_array( $binding['slot'], $slots, true )
                || ( in_array( $binding['slot'], $event_slots, true ) && 'event_source' !== $binding['interface'] )
                || ( ! in_array( $binding['slot'], $event_slots, true ) && 'event_source' === $binding['interface'] ) ) {
                $error = 'invalid_requested_binding';
                return false;
            }
        }

        foreach ( $capability['symbolic_actions'] as $action ) {
            if ( ! cap01a_has_only_keys( $action, array( 'action_key', 'kind' ) )
                || ! cap01a_has_required_keys( $action, array( 'action_key', 'kind' ) )
                || ! cap01a_is_capability_key( $action['action_key'] )
                || 0 !== strpos( $action['action_key'], $manifest['capability_namespace'] . '.' )
                || 'delegated_action' !== $action['kind']
                || ! in_array( 'delegated_action', $capability['interfaces'], true ) ) {
                $error = 'invalid_symbolic_action';
                return false;
            }
        }
    }

    if ( ! cap01a_compatibility_is_valid( $manifest['compatibility'], true ) || cap01a_contains_forbidden_data( $manifest ) ) {
        $error = 'forbidden_manifest_data';
        return false;
    }

    $error = '';
    return true;
}

function cap01a_registry_is_valid( $registry, &$error ) {
    $required = array( 'contract_version', 'namespace', 'consumer', 'freshness', 'source', 'applications', 'compatibility' );
    if ( ! cap01a_has_only_keys( $registry, $required ) || ! cap01a_has_required_keys( $registry, $required ) ) {
        $error = 'invalid_registry_fields';
        return false;
    }

    if ( ! cap01a_is_semver( $registry['contract_version'] )
        || 'apps.registry' !== $registry['namespace']
        || ! cap01a_has_only_keys( $registry['consumer'], array( 'surface', 'consumer_version' ) )
        || ! cap01a_has_required_keys( $registry['consumer'], array( 'surface', 'consumer_version' ) )
        || ! in_array( $registry['consumer']['surface'], array( 'portal', 'master_profile', 'me' ), true )
        || ! cap01a_is_semver( $registry['consumer']['consumer_version'] )
        || ! cap01a_freshness_is_valid( $registry['freshness'] )
        || ! cap01a_has_only_keys( $registry['source'], array( 'type', 'engine', 'read_model', 'source_version' ) )
        || ! cap01a_has_required_keys( $registry['source'], array( 'type', 'engine', 'read_model', 'source_version' ) )
        || 'registry_read_model' !== $registry['source']['type']
        || 'faluss-apps-registry' !== $registry['source']['engine']
        || 'apps-registry' !== $registry['source']['read_model']
        || ! cap01a_is_semver( $registry['source']['source_version'] )
        || ! cap01a_compatibility_is_valid( $registry['compatibility'] )
        || ! is_array( $registry['applications'] )
        || cap01a_contains_forbidden_data( $registry ) ) {
        $error = 'invalid_registry_envelope';
        return false;
    }

    $interfaces = array( 'module_read_model', 'delegated_action', 'event_source', 'content_reference_source' );
    $event_slots = array( 'analytics.events', 'quests.events', 'progression.events' );
    $slots = array(
        'portal.apps.card_action',
        'portal.analytics.dataset',
        'master_profile.module',
        'master_profile.footer_action',
        'me.studio.tab',
        'me.studio.block_source',
        'me.public.tab',
        'me.public.block',
        'analytics.events',
        'quests.events',
        'progression.events',
    );
    if ( ! cap01a_collection_is_unique_by( $registry['applications'], array( 'app_key' ) ) ) {
        $error = 'duplicate_registry_app_key';
        return false;
    }

    foreach ( $registry['applications'] as $application ) {
        $application_fields = array( 'app_key', 'availability', 'member_relationship', 'capabilities' );
        if ( ! cap01a_has_only_keys( $application, $application_fields )
            || ! cap01a_has_required_keys( $application, $application_fields )
            || ! cap01a_is_app_key( $application['app_key'] )
            || ! in_array( $application['availability'], array( 'available', 'unavailable', 'retired' ), true )
            || ! in_array( $application['member_relationship'], array( 'active', 'inactive', 'not_linked' ), true )
            || ! is_array( $application['capabilities'] ) ) {
            $error = 'invalid_application';
            return false;
        }
        if ( ! cap01a_collection_is_unique_by( $application['capabilities'], array( 'capability_key' ) ) ) {
            $error = 'duplicate_registry_capability_key';
            return false;
        }

        foreach ( $application['capabilities'] as $capability ) {
            $capability_fields = array( 'capability_key', 'owner', 'interfaces', 'state', 'specialized_read_model', 'surface_compatibility', 'active_bindings', 'allowed_actions' );
            if ( ! cap01a_has_only_keys( $capability, $capability_fields )
                || ! cap01a_has_required_keys( $capability, $capability_fields )
                || ! cap01a_is_capability_key( $capability['capability_key'] )
                || 0 !== strpos( $capability['capability_key'], $application['app_key'] . '.' )
                || ! cap01a_is_app_key( $capability['owner'] )
                || ! is_array( $capability['interfaces'] )
                || empty( $capability['interfaces'] )
                || array_diff( $capability['interfaces'], $interfaces )
                || ! in_array( $capability['state'], array( 'enabled', 'disabled', 'temporarily_unavailable', 'not_supported' ), true )
                || ! is_array( $capability['active_bindings'] )
                || ! is_array( $capability['allowed_actions'] ) ) {
                $error = 'invalid_registry_capability';
                return false;
            }
            if ( ! cap01a_collection_is_unique_by( $capability['active_bindings'], array( 'slot', 'interface' ) )
                || ! cap01a_collection_is_unique_by( $capability['allowed_actions'], array( 'action_key' ) ) ) {
                $error = 'duplicate_registry_capability_member';
                return false;
            }

            $read_model = $capability['specialized_read_model'];
            if ( ! cap01a_has_only_keys( $read_model, array( 'status', 'source', 'freshness' ) )
                || ! cap01a_has_required_keys( $read_model, array( 'status', 'source', 'freshness' ) )
                || ! in_array( $read_model['status'], array( 'available', 'unavailable', 'expired', 'not_supported' ), true )
                || ! cap01a_has_only_keys( $read_model['source'], array( 'engine', 'read_model', 'source_version' ) )
                || ! cap01a_has_required_keys( $read_model['source'], array( 'engine', 'read_model', 'source_version' ) )
                || $capability['owner'] !== $read_model['source']['engine']
                || ! is_string( $read_model['source']['read_model'] )
                || 1 !== preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\\.[a-z][a-z0-9-]{1,63})*$/D', $read_model['source']['read_model'] )
                || ! cap01a_is_semver( $read_model['source']['source_version'] )
                || ! cap01a_freshness_is_valid( $read_model['freshness'] )
                || ! cap01a_has_only_keys( $capability['surface_compatibility'], array( 'status', 'consumer_version' ) )
                || ! cap01a_has_required_keys( $capability['surface_compatibility'], array( 'status', 'consumer_version' ) )
                || ! in_array( $capability['surface_compatibility']['status'], array( 'compatible', 'incompatible', 'unsupported' ), true )
                || ! cap01a_is_semver( $capability['surface_compatibility']['consumer_version'] ) ) {
                $error = 'invalid_specialized_read_model';
                return false;
            }

            $can_bind = 'available' === $application['availability']
                && 'active' === $application['member_relationship']
                && 'enabled' === $capability['state']
                && 'available' === $read_model['status']
                && 'compatible' === $capability['surface_compatibility']['status'];

            if ( ! $can_bind && ( ! empty( $capability['active_bindings'] ) || ! empty( $capability['allowed_actions'] ) ) ) {
                $error = 'inactive_capability_has_binding_or_action';
                return false;
            }

            foreach ( $capability['active_bindings'] as $binding ) {
                if ( ! cap01a_has_only_keys( $binding, array( 'slot', 'interface', 'binding_state' ) )
                    || ! cap01a_has_required_keys( $binding, array( 'slot', 'interface', 'binding_state' ) )
                    || ! in_array( $binding['slot'], $slots, true )
                    || ! in_array( $binding['interface'], $capability['interfaces'], true )
                    || ( in_array( $binding['slot'], $event_slots, true ) && 'event_source' !== $binding['interface'] )
                    || ( ! in_array( $binding['slot'], $event_slots, true ) && 'event_source' === $binding['interface'] )
                    || 'active' !== $binding['binding_state'] ) {
                    $error = 'invalid_active_binding';
                    return false;
                }
            }

            foreach ( $capability['allowed_actions'] as $action ) {
                if ( ! cap01a_has_only_keys( $action, array( 'action_key', 'owner', 'delegation' ) )
                    || ! cap01a_has_required_keys( $action, array( 'action_key', 'owner', 'delegation' ) )
                    || ! cap01a_is_capability_key( $action['action_key'] )
                    || $capability['owner'] !== $action['owner']
                    || ! cap01a_has_only_keys( $action['delegation'], array( 'type', 'target' ) )
                    || ! cap01a_has_required_keys( $action['delegation'], array( 'type', 'target' ) )
                    || 'owner_delegated_action' !== $action['delegation']['type']
                    || ! cap01a_is_capability_key( $action['delegation']['target'] ) ) {
                    $error = 'invalid_allowed_action';
                    return false;
                }
            }
        }
    }

    $error = '';
    return true;
}

function cap01a_freshness() {
    return array(
        'generated_at'    => '2026-09-12T10:00:00Z',
        'max_age_seconds' => 60,
        'stale_behavior'  => 'omit',
    );
}

function cap01a_compatibility( $manifest = false ) {
    $compatibility = array(
        'minimum_consumer_version' => '1.0.0',
        'compatible_with'          => array( '1.0.0' ),
        'deprecated'               => false,
        'sunset_at'                => null,
    );

    if ( $manifest ) {
        $compatibility['replacement_capability_key'] = null;
    }

    return $compatibility;
}

function cap01a_registry_capability( $app_key, $owner, $capability_key, $interface, $slot ) {
    return array(
        'capability_key'       => $capability_key,
        'owner'                => $owner,
        'interfaces'           => array( $interface ),
        'state'                => 'enabled',
        'specialized_read_model' => array(
            'status' => 'available',
            'source' => array(
                'engine'         => $owner,
                'read_model'     => str_replace( array( '.', '_' ), '-', $capability_key ),
                'source_version' => '1.0.0',
            ),
            'freshness' => cap01a_freshness(),
        ),
        'surface_compatibility' => array(
            'status'           => 'compatible',
            'consumer_version' => '1.0.0',
        ),
        'active_bindings' => array(
            array(
                'slot'          => $slot,
                'interface'     => $interface,
                'binding_state' => 'active',
            ),
        ),
        'allowed_actions' => array(),
    );
}

function cap01a_registry_document( $application ) {
    return array(
        'contract_version' => '1.0.0',
        'namespace'        => 'apps.registry',
        'consumer'         => array(
            'surface'          => 'portal',
            'consumer_version' => '1.0.0',
        ),
        'freshness'        => cap01a_freshness(),
        'source'           => array(
            'type'           => 'registry_read_model',
            'engine'         => 'faluss-apps-registry',
            'read_model'     => 'apps-registry',
            'source_version' => '1.0.0',
        ),
        'applications'     => array( $application ),
        'compatibility'    => cap01a_compatibility(),
    );
}

$root = dirname( __DIR__ );
$contract = file_get_contents( $root . '/docs/FALUSS_CAPABILITIES_CONTRACT.md' );
$master_profile = file_get_contents( $root . '/docs/MASTER_PROFILE_CONTRACT.md' );
$portal = file_get_contents( $root . '/docs/FALUSS_PORTAL.md' );
$architecture = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$data_model = file_get_contents( $root . '/docs/DATA_MODEL.md' );
$roadmap = file_get_contents( $root . '/docs/ROADMAP.md' );
$manifest_schema = json_decode( file_get_contents( $root . '/contracts/faluss-app-capability-manifest.schema.json' ), true );
$registry_schema = json_decode( file_get_contents( $root . '/contracts/faluss-apps-registry-read-model.schema.json' ), true );

cap01a_assert( is_array( $manifest_schema ) && JSON_ERROR_NONE === json_last_error(), 'The application manifest schema must parse as JSON.' );
cap01a_assert( is_array( $registry_schema ), 'The apps.registry schema must parse as JSON.' );
foreach ( array( $manifest_schema, $registry_schema ) as $schema ) {
    cap01a_assert( 'https://json-schema.org/draft/2020-12/schema' === $schema['$schema'], 'Every CAP-01A schema must declare JSON Schema Draft 2020-12.' );
    cap01a_assert( false === $schema['additionalProperties'], 'Every CAP-01A document envelope must reject undeclared fields.' );
}
cap01a_assert( ! cap01a_schema_has_remote_ref( $manifest_schema ) && ! cap01a_schema_has_remote_ref( $registry_schema ), 'CAP-01A schemas must not use a remote reference.' );
cap01a_assert(
    array(
        'manifest_version',
        'app_key',
        'capability_namespace',
        'owner',
        'product_state',
        'canonical_origins',
        'public_presentation',
        'official_asset',
        'capabilities',
        'compatibility',
    ) === $manifest_schema['required'],
    'The manifest must require its version, ownership, origins, capability and compatibility fields.'
);
cap01a_assert(
    array(
        'portal.apps.card_action',
        'portal.analytics.dataset',
        'master_profile.module',
        'master_profile.footer_action',
        'me.studio.tab',
        'me.studio.block_source',
        'me.public.tab',
        'me.public.block',
        'analytics.events',
        'quests.events',
        'progression.events',
    ) === $manifest_schema['$defs']['slot']['enum'],
    'The manifest must retain the exact closed v1 slot vocabulary.'
);
cap01a_assert(
    array( 'available', 'unavailable', 'retired' ) === $registry_schema['$defs']['application']['properties']['availability']['enum']
    && array( 'active', 'inactive', 'not_linked' ) === $registry_schema['$defs']['application']['properties']['member_relationship']['enum']
    && array( 'enabled', 'disabled', 'temporarily_unavailable', 'not_supported' ) === $registry_schema['$defs']['capability']['properties']['state']['enum'],
    'The registry must retain the closed application, relation and capability states.'
);

// No Draft 2020-12 validator is bundled. These assertions prove the JSON Schema
// branches are structurally present; dynamic comparisons are tested separately.
$no_active_outputs = $registry_schema['$defs']['noActiveOutputs']['properties'];
$application_branches = $registry_schema['$defs']['application']['allOf'];
$capability_branches = $registry_schema['$defs']['capability']['allOf'];
cap01a_assert(
    0 === $no_active_outputs['active_bindings']['maxItems']
    && 0 === $no_active_outputs['allowed_actions']['maxItems']
    && array( 'unavailable', 'retired' ) === $application_branches[0]['if']['properties']['availability']['enum']
    && array( 'inactive', 'not_linked' ) === $application_branches[1]['if']['properties']['member_relationship']['enum']
    && '#/$defs/noActiveOutputs' === $application_branches[0]['then']['properties']['capabilities']['items']['allOf'][1]['$ref']
    && '#/$defs/noActiveOutputs' === $application_branches[1]['then']['properties']['capabilities']['items']['allOf'][1]['$ref'],
    'The application schema must structurally empty outputs for unavailable/retired and inactive/not-linked applications.'
);
cap01a_assert(
    array( 'disabled', 'temporarily_unavailable', 'not_supported' ) === $capability_branches[0]['if']['properties']['state']['enum']
    && 0 === $capability_branches[0]['then']['properties']['active_bindings']['maxItems']
    && 0 === $capability_branches[0]['then']['properties']['allowed_actions']['maxItems']
    && array( 'unavailable', 'expired', 'not_supported' ) === $capability_branches[1]['if']['properties']['specialized_read_model']['properties']['status']['enum']
    && 0 === $capability_branches[1]['then']['properties']['active_bindings']['maxItems']
    && 0 === $capability_branches[1]['then']['properties']['allowed_actions']['maxItems']
    && array( 'incompatible', 'unsupported' ) === $capability_branches[2]['if']['properties']['surface_compatibility']['properties']['status']['enum']
    && 0 === $capability_branches[2]['then']['properties']['active_bindings']['maxItems']
    && 0 === $capability_branches[2]['then']['properties']['allowed_actions']['maxItems'],
    'The capability schema must structurally empty bindings and actions for every unusable state.'
);
foreach ( array(
    $manifest_schema['$defs']['requestedBinding']['allOf'],
    $registry_schema['$defs']['activeBinding']['allOf'],
) as $binding_branches ) {
    cap01a_assert(
        'event_source' === $binding_branches[0]['if']['properties']['interface']['const']
        && array( 'analytics.events', 'quests.events', 'progression.events' ) === $binding_branches[0]['then']['properties']['slot']['enum']
        && array( 'analytics.events', 'quests.events', 'progression.events' ) === $binding_branches[1]['if']['properties']['slot']['enum']
        && 'event_source' === $binding_branches[1]['then']['properties']['interface']['const'],
        'The schema must reserve event consumers for event_source and forbid event_source on surface slots.'
    );
}
cap01a_assert(
    'module_read_model' === $manifest_schema['$defs']['capability']['allOf'][0]['if']['properties']['interfaces']['contains']['const']
    && 'object' === $manifest_schema['$defs']['capability']['allOf'][0]['then']['properties']['read_model_contract']['type']
    && 'delegated_action' === $manifest_schema['$defs']['capability']['allOf'][1]['then']['properties']['interfaces']['contains']['const'],
    'The manifest schema must require a typed read-model and delegated interface when those structures are declared.'
);
foreach ( array( $manifest_schema['x-semantic-invariants'], $registry_schema['x-semantic-invariants'] ) as $semantic_invariants ) {
    cap01a_assert(
        true === $semantic_invariants['server_validation_required']
        && 'reject' === $semantic_invariants['on_violation'],
        'Every dynamic CAP-01A.1 semantic invariant must require fail-closed server validation.'
    );
}
foreach ( array(
    'capability_key_unique',
    'requested_binding_slot_interface_unique',
    'symbolic_action_key_unique',
    'capability_namespace_ownership',
    'binding_interface_declared_by_capability',
) as $invariant ) {
    cap01a_assert( isset( $manifest_schema['x-semantic-invariants']['manifest'][ $invariant ] ), 'The manifest semantic invariant is missing: ' . $invariant );
}
foreach ( array(
    'app_key_unique',
    'capability_key_unique',
    'active_binding_slot_interface_unique',
    'allowed_action_key_unique',
    'capability_namespace_ownership',
    'active_binding_interface_declared_by_capability',
    'specialized_read_model_source_owner_match',
    'allowed_action_owner_match',
) as $invariant ) {
    cap01a_assert( isset( $registry_schema['x-semantic-invariants']['registry'][ $invariant ] ), 'The registry semantic invariant is missing: ' . $invariant );
}
cap01a_assert(
    false === strpos( json_encode( $manifest_schema ), '"$data"' )
    && false === strpos( json_encode( $registry_schema ), '"$data"' ),
    'CAP-01A.1 must not introduce a non-standard data-reference validator extension.'
);

$expected_scope = array(
    'docs/FALUSS_CAPABILITIES_CONTRACT.md',
    'contracts/faluss-app-capability-manifest.schema.json',
    'contracts/faluss-apps-registry-read-model.schema.json',
    'tests/faluss-capabilities-cap01a-contract-test.php',
    'docs/MASTER_PROFILE_CONTRACT.md',
    'docs/FALUSS_PORTAL.md',
    'docs/ARCHITECTURE.md',
    'docs/DATA_MODEL.md',
    'docs/ROADMAP.md',
);
$scope = $manifest_schema['x-cap01a-scope'];
cap01a_assert( 'documentary-contract-only' === $scope['nature'], 'CAP-01A must remain documentary and contract-only.' );
cap01a_assert( false === $scope['wordpress_runtime_changes'] && false === $scope['transports'] && false === $scope['migrations'] && false === $scope['routes'] && false === $scope['portal_ui_changes'] && false === $scope['installable_artifact'], 'CAP-01A must declare no runtime, transport, migration, route, UI or installable artifact.' );
cap01a_assert( $expected_scope === $scope['allowed_changed_paths'], 'CAP-01A must list exactly its nine authorized files.' );

$hub_manifest = array(
    'manifest_version'     => '1.0.0',
    'app_key'              => 'hub',
    'capability_namespace' => 'hub',
    'owner'                => array(
        'engine'    => 'faluss-hub',
        'authority' => 'hub.rewards',
    ),
    'product_state'        => 'active',
    'canonical_origins'    => array( 'https://faluss.com' ),
    'public_presentation'  => array(
        'display_name' => 'Faluss Hub',
        'summary'      => 'Surface membre propriétaire de son Daily Reward.',
    ),
    'official_asset'       => array(
        'asset_key'            => 'hub.logo',
        'mime_type'            => 'image/png',
        'sha256'               => str_repeat( 'a', 64 ),
        'intrinsic_dimensions' => array( 'width' => 24, 'height' => 24 ),
        'distribution'         => 'immutable_embedded',
    ),
    'capabilities'         => array(
        array(
            'capability_key'    => 'hub.daily_reward',
            'interfaces'        => array( 'delegated_action' ),
            'requested_bindings' => array(
                array( 'interface' => 'delegated_action', 'slot' => 'portal.apps.card_action' ),
            ),
            'read_model_contract' => null,
            'symbolic_actions'  => array(
                array( 'action_key' => 'hub.claim_daily_reward', 'kind' => 'delegated_action' ),
            ),
            'compatibility'     => cap01a_compatibility( true ),
        ),
    ),
    'compatibility'        => cap01a_compatibility( true ),
);
cap01a_assert( cap01a_manifest_is_valid( $hub_manifest, $error ), 'A Hub manifest with a delegated Daily Reward and official-asset metadata must be valid: ' . $error );

$hub_capability = cap01a_registry_capability( 'hub', 'faluss-hub', 'hub.daily_reward', 'delegated_action', 'portal.apps.card_action' );
$hub_capability['allowed_actions'][] = array(
    'action_key' => 'hub.claim_daily_reward',
    'owner'      => 'faluss-hub',
    'delegation' => array(
        'type'   => 'owner_delegated_action',
        'target' => 'hub.claim_daily_reward',
    ),
);
$hub_registry = cap01a_registry_document( array(
    'app_key'             => 'hub',
    'availability'        => 'available',
    'member_relationship' => 'active',
    'capabilities'        => array( $hub_capability ),
) );
cap01a_assert( cap01a_registry_is_valid( $hub_registry, $error ), 'An active Hub capability with one delegated active binding must be valid: ' . $error );

$me_not_linked = cap01a_registry_capability( 'me', 'faluss-me', 'me.profile', 'module_read_model', 'master_profile.module' );
$me_not_linked['state'] = 'not_supported';
$me_not_linked['specialized_read_model']['status'] = 'not_supported';
$me_not_linked['active_bindings'] = array();
$me_registry = cap01a_registry_document( array(
    'app_key'             => 'me',
    'availability'        => 'available',
    'member_relationship' => 'not_linked',
    'capabilities'        => array( $me_not_linked ),
) );
cap01a_assert( cap01a_registry_is_valid( $me_registry, $error ), 'An available but not-linked Faluss Me application must carry no active binding: ' . $error );

$me_active = cap01a_registry_capability( 'me', 'faluss-me', 'me.profile', 'module_read_model', 'master_profile.module' );
$me_active_registry = cap01a_registry_document( array(
    'app_key'             => 'me',
    'availability'        => 'available',
    'member_relationship' => 'active',
    'capabilities'        => array( $me_active ),
) );
cap01a_assert( cap01a_registry_is_valid( $me_active_registry, $error ), 'An active Faluss Me read-model compatible with Master Profile must be valid: ' . $error );

foreach ( array(
    array( 'fans', 'faluss-fans', 'fans.content.teaser_source', 'content_reference_source', 'me.studio.block_source' ),
    array( 'shop', 'faluss-shop', 'shop.public_catalog', 'module_read_model', 'me.public.tab' ),
    array( 'cosmetics', 'faluss-cosmetics', 'cosmetics.equipped', 'module_read_model', 'master_profile.module' ),
) as $scenario ) {
    $capability = cap01a_registry_capability( $scenario[0], $scenario[1], $scenario[2], $scenario[3], $scenario[4] );
    $registry = cap01a_registry_document( array(
        'app_key'             => $scenario[0],
        'availability'        => 'available',
        'member_relationship' => 'active',
        'capabilities'        => array( $capability ),
    ) );
    cap01a_assert( cap01a_registry_is_valid( $registry, $error ), 'A valid owner-filtered contextual capability must be accepted: ' . $scenario[2] . ' / ' . $error );
}

$multi_capability = cap01a_registry_document( array(
    'app_key'             => 'hub',
    'availability'        => 'available',
    'member_relationship' => 'active',
    'capabilities'        => array(
        cap01a_registry_capability( 'hub', 'faluss-hub', 'hub.daily_reward', 'delegated_action', 'portal.apps.card_action' ),
        cap01a_registry_capability( 'hub', 'faluss-hub', 'hub.analytics_source', 'event_source', 'analytics.events' ),
    ),
) );
cap01a_assert( cap01a_registry_is_valid( $multi_capability, $error ), 'One application may declare several distinct capabilities: ' . $error );

$disabled = cap01a_registry_capability( 'hub', 'faluss-hub', 'hub.daily_reward', 'delegated_action', 'portal.apps.card_action' );
$disabled['state'] = 'disabled';
$disabled['active_bindings'] = array();
$disabled['specialized_read_model']['status'] = 'unavailable';
$disabled_registry = cap01a_registry_document( array(
    'app_key'             => 'hub',
    'availability'        => 'available',
    'member_relationship' => 'active',
    'capabilities'        => array( $disabled ),
) );
cap01a_assert( cap01a_registry_is_valid( $disabled_registry, $error ), 'A disabled capability must be preserved as state without an active binding: ' . $error );

$foreign_namespace = $hub_manifest;
$foreign_namespace['capabilities'][0]['capability_key'] = 'fans.content.teaser_source';
cap01a_assert( ! cap01a_manifest_is_valid( $foreign_namespace, $error ), 'An application must not declare another engine namespace.' );

$manifest_with_identity = $hub_manifest;
$manifest_with_identity['faluss_id'] = '11111111-1111-4111-8111-111111111111';
cap01a_assert( ! cap01a_manifest_is_valid( $manifest_with_identity, $error ), 'A manifest must reject a Faluss ID or any undeclared sensitive field.' );

$unknown_slot = $hub_manifest;
$unknown_slot['capabilities'][0]['requested_bindings'][0]['slot'] = 'portal.*';
cap01a_assert( ! cap01a_manifest_is_valid( $unknown_slot, $error ), 'A wildcard or unknown slot must be rejected.' );

$inactive_with_action = $disabled_registry;
$inactive_with_action['applications'][0]['capabilities'][0]['allowed_actions'][] = array(
    'action_key' => 'hub.claim_daily_reward',
    'owner'      => 'faluss-hub',
    'delegation' => array( 'type' => 'owner_delegated_action', 'target' => 'hub.claim_daily_reward' ),
);
cap01a_assert( ! cap01a_registry_is_valid( $inactive_with_action, $error ), 'A disabled capability must not expose an authorized mutating action.' );

$missing_source = $hub_registry;
unset( $missing_source['applications'][0]['capabilities'][0]['specialized_read_model']['source'] );
cap01a_assert( ! cap01a_registry_is_valid( $missing_source, $error ), 'A capability without an owner source and version must be rejected.' );

$not_linked_binding = $me_active_registry;
$not_linked_binding['applications'][0]['member_relationship'] = 'not_linked';
cap01a_assert( ! cap01a_registry_is_valid( $not_linked_binding, $error ), 'An available application must not be considered active for a member without a relation.' );

$expired_binding = $me_active_registry;
$expired_binding['applications'][0]['capabilities'][0]['specialized_read_model']['status'] = 'expired';
cap01a_assert( ! cap01a_registry_is_valid( $expired_binding, $error ), 'An expired specialized read-model must not render as an active binding.' );

$browser_decision = $hub_registry;
$browser_decision['applications'][0]['capabilities'][0]['browser_eligibility'] = 'enabled';
cap01a_assert( ! cap01a_registry_is_valid( $browser_decision, $error ), 'A browser must not supply an eligibility or activation decision.' );

// The structural branches above are inspected separately. These documents
// exercise the corresponding fail-closed server validation.
$unavailable_with_binding = $hub_registry;
$unavailable_with_binding['applications'][0]['availability'] = 'unavailable';
cap01a_assert( ! cap01a_registry_is_valid( $unavailable_with_binding, $error ), 'An unavailable application must reject an active binding.' );

$retired_with_action = $hub_registry;
$retired_with_action['applications'][0]['availability'] = 'retired';
$retired_with_action['applications'][0]['capabilities'][0]['active_bindings'] = array();
cap01a_assert( ! cap01a_registry_is_valid( $retired_with_action, $error ), 'A retired application must reject an allowed action.' );

$inactive_with_binding = $hub_registry;
$inactive_with_binding['applications'][0]['member_relationship'] = 'inactive';
$inactive_with_binding['applications'][0]['capabilities'][0]['allowed_actions'] = array();
cap01a_assert( ! cap01a_registry_is_valid( $inactive_with_binding, $error ), 'An inactive member relation must reject an active binding.' );

$not_linked_with_action = $hub_registry;
$not_linked_with_action['applications'][0]['member_relationship'] = 'not_linked';
$not_linked_with_action['applications'][0]['capabilities'][0]['active_bindings'] = array();
cap01a_assert( ! cap01a_registry_is_valid( $not_linked_with_action, $error ), 'A not-linked member relation must reject an allowed action.' );

$expired_with_action = $hub_registry;
$expired_with_action['applications'][0]['capabilities'][0]['specialized_read_model']['status'] = 'expired';
$expired_with_action['applications'][0]['capabilities'][0]['active_bindings'] = array();
cap01a_assert( ! cap01a_registry_is_valid( $expired_with_action, $error ), 'An expired specialized read-model must reject an allowed action.' );

$unavailable_with_action = $hub_registry;
$unavailable_with_action['applications'][0]['capabilities'][0]['specialized_read_model']['status'] = 'unavailable';
$unavailable_with_action['applications'][0]['capabilities'][0]['active_bindings'] = array();
cap01a_assert( ! cap01a_registry_is_valid( $unavailable_with_action, $error ), 'An unavailable specialized read-model must reject an allowed action.' );

$incompatible_with_action = $hub_registry;
$incompatible_with_action['applications'][0]['capabilities'][0]['surface_compatibility']['status'] = 'incompatible';
$incompatible_with_action['applications'][0]['capabilities'][0]['active_bindings'] = array();
cap01a_assert( ! cap01a_registry_is_valid( $incompatible_with_action, $error ), 'An incompatible surface must reject an allowed action.' );

$event_source_surface_slot = $multi_capability;
$event_source_surface_slot['applications'][0]['capabilities'][1]['active_bindings'][0]['slot'] = 'me.public.tab';
cap01a_assert( ! cap01a_registry_is_valid( $event_source_surface_slot, $error ), 'An event source must not target a surface slot.' );

$read_model_event_slot = $me_active_registry;
$read_model_event_slot['applications'][0]['capabilities'][0]['active_bindings'][0]['slot'] = 'analytics.events';
cap01a_assert( ! cap01a_registry_is_valid( $read_model_event_slot, $error ), 'A module read-model must not target an event consumer.' );

$undeclared_binding_interface = $hub_registry;
$undeclared_binding_interface['applications'][0]['capabilities'][0]['active_bindings'][0]['interface'] = 'module_read_model';
cap01a_assert( ! cap01a_registry_is_valid( $undeclared_binding_interface, $error ), 'A binding interface absent from its capability must be rejected semantically.' );

$duplicate_app_key = $hub_registry;
$duplicate_application = $duplicate_app_key['applications'][0];
$duplicate_application['capabilities'][0]['capability_key'] = 'hub.secondary';
$duplicate_app_key['applications'][] = $duplicate_application;
cap01a_assert( ! cap01a_registry_is_valid( $duplicate_app_key, $error ), 'Two applications with the same app_key must be rejected semantically.' );

$duplicate_manifest_capability_key = $hub_manifest;
$duplicate_manifest_capability = $duplicate_manifest_capability_key['capabilities'][0];
$duplicate_manifest_capability['requested_bindings'] = array();
$duplicate_manifest_capability_key['capabilities'][] = $duplicate_manifest_capability;
cap01a_assert( ! cap01a_manifest_is_valid( $duplicate_manifest_capability_key, $error ), 'Two different manifest capabilities with the same capability_key must be rejected semantically.' );

$duplicate_registry_capability_key = $hub_registry;
$duplicate_registry_capability = $duplicate_registry_capability_key['applications'][0]['capabilities'][0];
$duplicate_registry_capability['active_bindings'] = array();
$duplicate_registry_capability_key['applications'][0]['capabilities'][] = $duplicate_registry_capability;
cap01a_assert( ! cap01a_registry_is_valid( $duplicate_registry_capability_key, $error ), 'Two different read-model capabilities with the same capability_key must be rejected semantically.' );

$duplicate_binding_pair = $hub_registry;
$duplicate_binding_pair['applications'][0]['capabilities'][0]['active_bindings'][] = $duplicate_binding_pair['applications'][0]['capabilities'][0]['active_bindings'][0];
cap01a_assert( ! cap01a_registry_is_valid( $duplicate_binding_pair, $error ), 'Two bindings sharing the same slot/interface pair must be rejected semantically.' );
cap01a_assert(
    ! cap01a_collection_is_unique_by(
        array(
            array( 'slot' => 'portal.apps.card_action', 'interface' => 'delegated_action', 'non_key_variant' => 'first' ),
            array( 'slot' => 'portal.apps.card_action', 'interface' => 'delegated_action', 'non_key_variant' => 'second' ),
        ),
        array( 'slot', 'interface' )
    ),
    'Semantic binding-pair uniqueness must reject different records sharing the same business pair.'
);

$duplicate_symbolic_action_key = $hub_manifest;
$duplicate_symbolic_action = $duplicate_symbolic_action_key['capabilities'][0]['symbolic_actions'][0];
$duplicate_symbolic_action_key['capabilities'][0]['symbolic_actions'][] = $duplicate_symbolic_action;
cap01a_assert( ! cap01a_manifest_is_valid( $duplicate_symbolic_action_key, $error ), 'Two symbolic actions with the same action_key must be rejected semantically.' );

$duplicate_allowed_action_key = $hub_registry;
$duplicate_allowed_action = $duplicate_allowed_action_key['applications'][0]['capabilities'][0]['allowed_actions'][0];
$duplicate_allowed_action['delegation']['target'] = 'hub.other_daily_reward';
$duplicate_allowed_action_key['applications'][0]['capabilities'][0]['allowed_actions'][] = $duplicate_allowed_action;
cap01a_assert( ! cap01a_registry_is_valid( $duplicate_allowed_action_key, $error ), 'Two distinct allowed actions with the same action_key must be rejected semantically.' );

$manifest_event_source_surface_slot = $hub_manifest;
$manifest_event_source_surface_slot['capabilities'][0]['interfaces'] = array( 'event_source' );
$manifest_event_source_surface_slot['capabilities'][0]['requested_bindings'][0] = array( 'interface' => 'event_source', 'slot' => 'me.public.tab' );
$manifest_event_source_surface_slot['capabilities'][0]['symbolic_actions'] = array();
cap01a_assert( ! cap01a_manifest_is_valid( $manifest_event_source_surface_slot, $error ), 'A requested event source must not target a surface slot.' );

$manifest_read_model_event_slot = $hub_manifest;
$manifest_read_model_event_slot['capabilities'][0]['interfaces'] = array( 'module_read_model' );
$manifest_read_model_event_slot['capabilities'][0]['requested_bindings'][0] = array( 'interface' => 'module_read_model', 'slot' => 'analytics.events' );
$manifest_read_model_event_slot['capabilities'][0]['read_model_contract'] = array( 'document_type' => 'hub.module', 'contract_version' => '1.0.0' );
$manifest_read_model_event_slot['capabilities'][0]['symbolic_actions'] = array();
cap01a_assert( ! cap01a_manifest_is_valid( $manifest_read_model_event_slot, $error ), 'A requested module read-model must not target an event consumer.' );

$manifest_undeclared_binding_interface = $hub_manifest;
$manifest_undeclared_binding_interface['capabilities'][0]['requested_bindings'][0]['interface'] = 'module_read_model';
cap01a_assert( ! cap01a_manifest_is_valid( $manifest_undeclared_binding_interface, $error ), 'A requested binding interface absent from its capability must be rejected semantically.' );

$missing_typed_read_model = $hub_manifest;
$missing_typed_read_model['capabilities'][0]['interfaces'] = array( 'module_read_model' );
$missing_typed_read_model['capabilities'][0]['requested_bindings'][0] = array( 'interface' => 'module_read_model', 'slot' => 'me.public.tab' );
$missing_typed_read_model['capabilities'][0]['symbolic_actions'] = array();
cap01a_assert( ! cap01a_manifest_is_valid( $missing_typed_read_model, $error ), 'A module_read_model interface must require a typed read-model contract.' );

$symbolic_action_without_interface = $hub_manifest;
$symbolic_action_without_interface['capabilities'][0]['interfaces'] = array( 'content_reference_source' );
$symbolic_action_without_interface['capabilities'][0]['requested_bindings'][0] = array( 'interface' => 'content_reference_source', 'slot' => 'me.studio.block_source' );
cap01a_assert( ! cap01a_manifest_is_valid( $symbolic_action_without_interface, $error ), 'A symbolic action must require the delegated_action interface.' );

$source_owner_mismatch = $hub_registry;
$source_owner_mismatch['applications'][0]['capabilities'][0]['specialized_read_model']['source']['engine'] = 'faluss-other';
cap01a_assert( ! cap01a_registry_is_valid( $source_owner_mismatch, $error ), 'A specialized read-model source must match the capability owner.' );

$allowed_action_owner_mismatch = $hub_registry;
$allowed_action_owner_mismatch['applications'][0]['capabilities'][0]['allowed_actions'][0]['owner'] = 'faluss-other';
cap01a_assert( ! cap01a_registry_is_valid( $allowed_action_owner_mismatch, $error ), 'An allowed action must match the capability owner.' );

foreach ( array(
    'faluss-apps-registry',
    'apps.registry',
    'module_read_model',
    'delegated_action',
    'content_reference_source',
    'portal.apps.card_action',
    'me.studio.block_source',
    'me.public.tab',
    'binding active',
    'Aucun navigateur',
    'FED-01',
    'CAP-01B',
    'DR-02A.2',
    'CAP-01A.1',
    'x-semantic-invariants',
    'validateur JSON Schema Draft 2020-12',
) as $needle ) {
    cap01a_assert( false !== strpos( $contract, $needle ), 'The CAP-01A contract is missing a required boundary: ' . $needle );
}
cap01a_assert( false !== strpos( $master_profile, 'CAP-01A' ) && false !== strpos( $master_profile, 'binding actif' ), 'MP-01A must consume apps.registry without becoming its owner.' );
cap01a_assert( false !== strpos( $portal, 'Depuis CAP-01B.2' ) && false !== strpos( $portal, 'catalogue AP-01 reste uniquement propriétaire' ) && false !== strpos( $portal, '`apps.registry`' ), 'Portal must document the runtime/presentation ownership split without weakening CAP-01A.' );
cap01a_assert( false !== strpos( $architecture, 'CAP-01A' ) && false !== strpos( $architecture, 'faluss-apps-registry' ), 'Architecture must register the future registry authority.' );
cap01a_assert( false !== strpos( $data_model, 'CAP-01A' ) && false !== strpos( $data_model, 'n\'ajoute aucune table' ), 'Data model must state that CAP-01A adds no table or member data.' );
cap01a_assert( false !== strpos( $roadmap, '## CAP-01A' ) && false !== strpos( $roadmap, 'FED-01' ) && false !== strpos( $roadmap, 'MP-01B' ), 'Roadmap must preserve the ordered CAP-01 future path.' );

echo 'CAP-01A Faluss application capabilities contract: OK' . PHP_EOL;
