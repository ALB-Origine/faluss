<?php

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/plugins/faluss-identity/includes/class-faluss-identity-schema.php';
require_once dirname( __DIR__ ) . '/plugins/faluss-identity/includes/class-faluss-identity-registry.php';

function fi01_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$schema = Faluss_Identity_Schema::get_expected_schema();
fi01_assert( 6 === count( $schema ), 'FI-01 defines exactly six Identity tables.' );

foreach ( array( 'profiles', 'challenges', 'rate_limits', 'clients', 'auth_codes', 'audit' ) as $table ) {
    fi01_assert( isset( $schema[ $table ] ), 'Missing ' . $table . ' table definition.' );
    fi01_assert( isset( $schema[ $table ]['indexes']['PRIMARY'] ), 'Missing ' . $table . ' primary key.' );
}

fi01_assert( $schema['profiles']['indexes']['faluss_id_unique']['unique'], 'faluss_id must be unique.' );
fi01_assert( $schema['profiles']['indexes']['wp_user_id_unique']['unique'], 'wp_user_id must be unique.' );
fi01_assert( array( 'bucket_type', 'bucket_hash' ) === $schema['rate_limits']['indexes']['bucket_type_hash_unique']['columns'], 'Rate-limit unique index order changed.' );
fi01_assert( array( 'client_id', 'expires_at' ) === $schema['auth_codes']['indexes']['client_expires_at']['columns'], 'Auth-code index order changed.' );
fi01_assert( Faluss_Identity_Registry::is_valid_faluss_id( '550e8400-e29b-41d4-a716-446655440000' ), 'A UUID v4 must be accepted.' );
fi01_assert( ! Faluss_Identity_Registry::is_valid_faluss_id( '550e8400-e29b-11d4-a716-446655440000' ), 'A non-v4 UUID must be refused.' );

echo 'FI-01 schema contract: OK' . PHP_EOL;
