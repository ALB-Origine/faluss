<?php

function pf02b1_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function pf02b1_balance( $entries ) {
    $originals = array();
    foreach ( $entries as $entry ) {
        $originals[ $entry['entry_uuid'] ] = $entry;
    }
    $balance = 0;
    foreach ( $entries as $entry ) {
        if ( 'credit' === $entry['direction'] ) {
            $balance += $entry['amount_pf'];
        } elseif ( 'debit' === $entry['direction'] ) {
            $balance -= $entry['amount_pf'];
        } elseif ( 'compensation' === $entry['direction'] ) {
            $balance += 'debit' === $originals[ $entry['compensates_entry_uuid'] ]['direction'] ? $entry['amount_pf'] : -$entry['amount_pf'];
        }
    }
    return $balance;
}

function pf02b1_append( &$entries, $entry, &$error ) {
    foreach ( $entries as $existing ) {
        if ( $existing['idempotency_key'] === $entry['idempotency_key'] ) {
            $error = 'idempotent';
            return $existing;
        }
    }
    if ( 'compensation' === $entry['direction'] ) {
        foreach ( $entries as $existing ) {
            if ( 'compensation' === $existing['direction'] && $existing['compensates_entry_uuid'] === $entry['compensates_entry_uuid'] ) {
                $error = 'pf_already_compensated';
                return false;
            }
        }
    }
    $entries[] = $entry;
    $error = '';
    return $entry;
}

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/plugins/token-engine/token-engine.php' );
$schema = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-schema.php' );
$points = file_get_contents( $root . '/plugins/token-engine/includes/class-token-engine-points-service.php' );
$token_doc = file_get_contents( $root . '/docs/TOKEN_ENGINE.md' );
$pf_doc = file_get_contents( $root . '/docs/POINTS_FALUSS_CONTRACT.md' );

pf02b1_assert( false !== strpos( $plugin, 'Version: 0.4.1' ) && false !== strpos( $plugin, "TOKEN_ENGINE_VERSION', '0.4.1" ), 'PF-02B.1 requires Token Engine 0.4.1.' );
foreach ( array( "const VERSION = '5'", "const V4_VERSION = '4'", 'migrate_v4_to_v5', 'pf_compensates_entry_unique', 'UNIQUE KEY `pf_compensates_entry_unique` (`compensates_entry_uuid`)', 'WHERE `compensates_entry_uuid` IS NOT NULL', 'GROUP BY `compensates_entry_uuid`', 'HAVING COUNT(*) > 1', 'DROP INDEX `pf_compensates_entry`' ) as $needle ) {
    pf02b1_assert( false !== strpos( $schema, $needle ), 'PF-02B.1 schema invariant is missing: ' . $needle );
}
$migration_start = strpos( $schema, 'private static function migrate_v4_to_v5()' );
$migration_end = strpos( $schema, 'private static function current_schema_ready()', $migration_start );
$migration = false !== $migration_start && false !== $migration_end ? substr( $schema, $migration_start, $migration_end - $migration_start ) : '';
pf02b1_assert( '' !== $migration && false !== strpos( $migration, 'self::pf_ledger_table()' ) && false === strpos( $migration, 'self::ledger_table()' ) && false === strpos( $migration, 'create_query(' ), 'The 4-to-5 migration must alter only the PF ledger index.' );
foreach ( array( "return self::error( 'pf_already_compensated'", 'entry_by_compensated_uuid', 'SELECT * FROM `', 'WHERE compensates_entry_uuid=%s', 'FOR UPDATE' ) as $needle ) {
    pf02b1_assert( false !== strpos( $points, $needle ), 'PF-02B.1 must check compensation uniqueness in the transactional facade: ' . $needle );
}
pf02b1_assert( false !== strpos( $token_doc, "qu'une seule fois") && false !== strpos( $pf_doc, 'une seule fois'), 'PF-02B.1 documentation must state one compensation per original entry.' );

$entries = array();
$credit = array( 'entry_uuid' => '10000000-0000-4000-8000-000000000001', 'amount_pf' => 100, 'direction' => 'credit', 'idempotency_key' => 'pf.test.credit.0001', 'compensates_entry_uuid' => null );
$debit = array( 'entry_uuid' => '10000000-0000-4000-8000-000000000002', 'amount_pf' => 40, 'direction' => 'debit', 'idempotency_key' => 'pf.test.debit.0001', 'compensates_entry_uuid' => null );
$first_compensation = array( 'entry_uuid' => '10000000-0000-4000-8000-000000000003', 'amount_pf' => 40, 'direction' => 'compensation', 'idempotency_key' => 'pf.test.compensation.first', 'compensates_entry_uuid' => $debit['entry_uuid'], 'source_event_reference' => 'compensation.source.first' );
$second_compensation = array( 'entry_uuid' => '10000000-0000-4000-8000-000000000004', 'amount_pf' => 40, 'direction' => 'compensation', 'idempotency_key' => 'pf.test.compensation.second', 'compensates_entry_uuid' => $debit['entry_uuid'], 'source_event_reference' => 'compensation.source.other' );

pf02b1_assert( is_array( pf02b1_append( $entries, $credit, $error ) ) && is_array( pf02b1_append( $entries, $debit, $error ) ) && 60 === pf02b1_balance( $entries ), 'An earned credit followed by a same-class debit must leave the expected balance.' );
pf02b1_assert( is_array( pf02b1_append( $entries, $first_compensation, $error ) ) && 100 === pf02b1_balance( $entries ), 'The first full same-class compensation of the debit must be valid and append-only.' );
$balance_after_first = pf02b1_balance( $entries );
pf02b1_assert( is_array( pf02b1_append( $entries, $first_compensation, $error ) ) && 'idempotent' === $error && 3 === count( $entries ) && $balance_after_first === pf02b1_balance( $entries ), 'An identical compensation retry must return the committed result without a new line.' );
pf02b1_assert( false === pf02b1_append( $entries, $second_compensation, $error ) && 'pf_already_compensated' === $error && $balance_after_first === pf02b1_balance( $entries ) && 3 === count( $entries ), 'A second compensation with another source reference must be refused without changing the balance.' );

echo 'PF-02B.1 PF compensation uniqueness contract: OK' . PHP_EOL;
