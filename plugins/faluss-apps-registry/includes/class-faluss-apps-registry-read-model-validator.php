<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Closed PHP validator for the apps.registry v1 member read-model. */
final class Faluss_Apps_Registry_Read_Model_Validator {
    private const ROOT_KEYS = array( 'contract_version', 'namespace', 'consumer', 'freshness', 'source', 'applications', 'compatibility' );
    private const INTERFACES = array( 'module_read_model', 'delegated_action', 'event_source', 'content_reference_source' );
    private const SLOTS = array( 'portal.apps.card_action', 'portal.analytics.dataset', 'master_profile.module', 'master_profile.footer_action', 'me.studio.tab', 'me.studio.block_source', 'me.public.tab', 'me.public.block', 'analytics.events', 'quests.events', 'progression.events' );
    private const EVENT_SLOTS = array( 'analytics.events', 'quests.events', 'progression.events' );

    public static function validate( $document ) {
        if ( ! self::exact_keys( $document, self::ROOT_KEYS ) || self::contains_sensitive_content( $document ) ) {
            return false;
        }
        if ( '1.0.0' !== $document['contract_version'] || 'apps.registry' !== $document['namespace'] || ! self::valid_consumer( $document['consumer'] ) || ! self::valid_freshness( $document['freshness'], true, true ) || ! self::valid_source( $document['source'] ) || ! self::valid_compatibility( $document['compatibility'], $document['consumer']['consumer_version'] ) ) {
            return false;
        }
        if ( ! self::is_list( $document['applications'] ) || count( $document['applications'] ) > 128 ) {
            return false;
        }
        $apps = array();
        foreach ( $document['applications'] as $application ) {
            if ( ! self::valid_application( $application, $document['consumer']['surface'], $document['consumer']['consumer_version'] ) || isset( $apps[ $application['app_key'] ] ) ) {
                return false;
            }
            $apps[ $application['app_key'] ] = true;
        }
        return true;
    }

    private static function valid_consumer( $consumer ) {
        return self::exact_keys( $consumer, array( 'surface', 'consumer_version' ) )
            && in_array( $consumer['surface'], array( 'portal', 'master_profile', 'me' ), true )
            && self::is_semver( $consumer['consumer_version'] );
    }

    private static function valid_source( $source ) {
        return self::exact_keys( $source, array( 'type', 'engine', 'read_model', 'source_version' ) )
            && 'registry_read_model' === $source['type']
            && 'faluss-apps-registry' === $source['engine']
            && 'apps-registry' === $source['read_model']
            && '1.0.0' === $source['source_version'];
    }

    private static function valid_application( $application, $surface, $consumer_version ) {
        if ( ! self::exact_keys( $application, array( 'app_key', 'availability', 'member_relationship', 'capabilities' ) ) || ! self::is_app_key( $application['app_key'] ) || ! in_array( $application['availability'], array( 'available', 'unavailable', 'retired' ), true ) || ! in_array( $application['member_relationship'], array( 'active', 'inactive', 'not_linked' ), true ) || ! self::is_list( $application['capabilities'] ) || count( $application['capabilities'] ) > 64 ) {
            return false;
        }
        $capabilities = array();
        foreach ( $application['capabilities'] as $capability ) {
            if ( ! self::valid_capability( $capability, $application, $surface, $consumer_version ) || isset( $capabilities[ $capability['capability_key'] ] ) ) {
                return false;
            }
            $capabilities[ $capability['capability_key'] ] = true;
        }
        return true;
    }

    private static function valid_capability( $capability, $application, $surface, $consumer_version ) {
        if ( ! self::exact_keys( $capability, array( 'capability_key', 'owner', 'interfaces', 'state', 'specialized_read_model', 'surface_compatibility', 'active_bindings', 'allowed_actions' ) ) || ! self::is_namespaced_key( $capability['capability_key'], $application['app_key'] ) || $application['app_key'] !== $capability['owner'] || ! self::valid_interfaces( $capability['interfaces'] ) || ! in_array( $capability['state'], array( 'enabled', 'disabled', 'temporarily_unavailable', 'not_supported' ), true ) || ! self::valid_specialized_read_model( $capability['specialized_read_model'], $capability['owner'] ) || ! self::valid_surface_compatibility( $capability['surface_compatibility'], $consumer_version ) ) {
            return false;
        }
        if ( ! self::valid_bindings( $capability['active_bindings'], $capability['interfaces'], $surface ) || ! self::valid_actions( $capability['allowed_actions'], $capability['owner'], $capability['interfaces'], $capability['active_bindings'] ) ) {
            return false;
        }
        $outputs_allowed = 'available' === $application['availability']
            && 'active' === $application['member_relationship']
            && 'enabled' === $capability['state']
            && 'available' === $capability['specialized_read_model']['status']
            && 'compatible' === $capability['surface_compatibility']['status'];
        return $outputs_allowed || ( array() === $capability['active_bindings'] && array() === $capability['allowed_actions'] );
    }

    private static function valid_interfaces( $interfaces ) {
        return self::is_list( $interfaces ) && count( $interfaces ) >= 1 && count( $interfaces ) <= 4 && count( $interfaces ) === count( array_unique( $interfaces, SORT_STRING ) ) && ! array_diff( $interfaces, self::INTERFACES );
    }

    private static function valid_specialized_read_model( $model, $owner ) {
        return self::exact_keys( $model, array( 'status', 'source', 'freshness' ) )
            && in_array( $model['status'], array( 'available', 'unavailable', 'expired', 'not_supported' ), true )
            && self::exact_keys( $model['source'], array( 'engine', 'read_model', 'source_version' ) )
            && $owner === $model['source']['engine']
            && self::is_app_key( $model['source']['engine'] )
            && is_string( $model['source']['read_model'] )
            && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9-]{1,63})*$/D', $model['source']['read_model'] )
            && self::is_semver( $model['source']['source_version'] )
            && self::valid_freshness( $model['freshness'], 'available' === $model['status'] );
    }

    private static function valid_surface_compatibility( $compatibility, $consumer_version ) {
        return self::exact_keys( $compatibility, array( 'status', 'consumer_version' ) )
            && in_array( $compatibility['status'], array( 'compatible', 'incompatible', 'unsupported' ), true )
            && $consumer_version === $compatibility['consumer_version'];
    }

    private static function valid_bindings( $bindings, $interfaces, $surface ) {
        if ( ! self::is_list( $bindings ) || count( $bindings ) > 16 ) {
            return false;
        }
        $seen = array();
        foreach ( $bindings as $binding ) {
            if ( ! self::exact_keys( $binding, array( 'slot', 'interface', 'binding_state' ) ) || 'active' !== $binding['binding_state'] || ! in_array( $binding['interface'], $interfaces, true ) || ! in_array( $binding['slot'], self::SLOTS, true ) || ! self::surface_accepts_slot( $surface, $binding['slot'] ) ) {
                return false;
            }
            if ( in_array( $binding['slot'], self::EVENT_SLOTS, true ) !== ( 'event_source' === $binding['interface'] ) ) {
                return false;
            }
            $key = $binding['slot'] . "\x1F" . $binding['interface'];
            if ( isset( $seen[ $key ] ) ) {
                return false;
            }
            $seen[ $key ] = true;
        }
        return true;
    }

    private static function valid_actions( $actions, $owner, $interfaces, $bindings ) {
        if ( ! self::is_list( $actions ) || count( $actions ) > 16 ) {
            return false;
        }
        if ( ! empty( $actions ) ) {
            $delegated_binding = false;
            foreach ( $bindings as $binding ) {
                $delegated_binding = $delegated_binding || 'delegated_action' === $binding['interface'];
            }
            if ( ! in_array( 'delegated_action', $interfaces, true ) || ! $delegated_binding ) {
                return false;
            }
        }
        $seen = array();
        foreach ( $actions as $action ) {
            if ( ! self::exact_keys( $action, array( 'action_key', 'owner', 'delegation' ) ) || ! self::is_namespaced_key( $action['action_key'], $owner ) || $owner !== $action['owner'] || ! self::exact_keys( $action['delegation'], array( 'type', 'target' ) ) || 'owner_delegated_action' !== $action['delegation']['type'] || $action['action_key'] !== $action['delegation']['target'] || isset( $seen[ $action['action_key'] ] ) ) {
                return false;
            }
            $seen[ $action['action_key'] ] = true;
        }
        return true;
    }

    private static function valid_freshness( $freshness, $must_be_current, $strict_utc = false ) {
        if ( ! self::exact_keys( $freshness, array( 'generated_at', 'max_age_seconds', 'stale_behavior' ) ) || ! self::is_datetime( $freshness['generated_at'] ) || ! is_int( $freshness['max_age_seconds'] ) || $freshness['max_age_seconds'] < 1 || $freshness['max_age_seconds'] > 86400 || ! in_array( $freshness['stale_behavior'], array( 'omit', 'refresh_from_owner' ), true ) ) {
            return false;
        }
        if ( $strict_utc && 'Z' !== substr( $freshness['generated_at'], -1 ) ) {
            return false;
        }
        $generated = self::timestamp( $freshness['generated_at'] );
        return false !== $generated && ( ! $must_be_current || ( $generated <= time() + 60 && $generated + $freshness['max_age_seconds'] >= time() ) );
    }

    private static function valid_compatibility( $compatibility, $consumer_version ) {
        if ( ! self::exact_keys( $compatibility, array( 'minimum_consumer_version', 'compatible_with', 'deprecated', 'sunset_at' ) ) || ! self::is_semver( $compatibility['minimum_consumer_version'] ) || ! self::is_list( $compatibility['compatible_with'] ) || count( $compatibility['compatible_with'] ) > 32 || count( $compatibility['compatible_with'] ) !== count( array_unique( $compatibility['compatible_with'], SORT_STRING ) ) || ! is_bool( $compatibility['deprecated'] ) || ( null !== $compatibility['sunset_at'] && ! self::is_datetime( $compatibility['sunset_at'] ) ) ) {
            return false;
        }
        foreach ( $compatibility['compatible_with'] as $version ) {
            if ( ! self::is_semver( $version ) ) {
                return false;
            }
        }
        return version_compare( $consumer_version, $compatibility['minimum_consumer_version'], '>=' ) && in_array( $consumer_version, $compatibility['compatible_with'], true ) && ( null === $compatibility['sunset_at'] || self::timestamp( $compatibility['sunset_at'] ) > time() );
    }

    private static function contains_sensitive_content( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $child ) {
                if ( is_string( $key ) && 1 === preg_match( '/faluss[_-]?id|wp[_-]?user[_-]?id|e[_-]?mail|email|login|session|cookie|balance|solde|payment|paiement|stripe|history|historique|profile[_-]?url|member[_-]?content|raw[_-]?manifest|callback|callable|executable/i', $key ) ) {
                    return true;
                }
                if ( self::contains_sensitive_content( $child ) ) {
                    return true;
                }
            }
            return false;
        }
        return is_object( $value ) || is_resource( $value ) || ( is_string( $value ) && ( 1 === preg_match( '/<\?(?:php)?|<(?:script|iframe|style)\b|javascript:|data:text\/html|on[a-z]+\s*=/i', $value ) ) );
    }

    private static function surface_accepts_slot( $surface, $slot ) {
        $slots = array(
            'portal' => array( 'portal.apps.card_action', 'portal.analytics.dataset' ),
            'master_profile' => array( 'master_profile.module', 'master_profile.footer_action' ),
            'me' => array( 'me.studio.tab', 'me.studio.block_source', 'me.public.tab', 'me.public.block' ),
        );
        return isset( $slots[ $surface ] ) && in_array( $slot, $slots[ $surface ], true );
    }

    private static function exact_keys( $value, $keys ) {
        if ( ! is_array( $value ) ) {
            return false;
        }
        $actual = array_keys( $value );
        sort( $actual, SORT_STRING );
        $expected = $keys;
        sort( $expected, SORT_STRING );
        return $actual === $expected;
    }

    private static function is_list( $value ) { return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) ); }
    private static function is_app_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}$/D', $value ); }
    private static function is_semver( $value ) { return is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*\.[0-9]+\.[0-9]+$/D', $value ); }
    private static function is_namespaced_key( $value, $namespace ) { return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,63}(?:\.[a-z][a-z0-9_.-]{1,127})+$/D', $value ) && 0 === strpos( $value, $namespace . '.' ); }

    private static function is_datetime( $value ) {
        return false !== self::timestamp( $value );
    }

    private static function timestamp( $value ) {
        if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2})$/D', $value ) ) {
            return false;
        }
        $normalized = 'Z' === substr( $value, -1 ) ? substr( $value, 0, -1 ) . '+00:00' : $value;
        $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:sP', $normalized );
        $errors = DateTimeImmutable::getLastErrors();
        return false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $normalized === $date->format( 'Y-m-d\TH:i:sP' ) ? $date->getTimestamp() : false;
    }
}
