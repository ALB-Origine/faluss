<?php

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
    private $code;

    public function __construct( $code = '' ) {
        $this->code = $code;
    }

    public function get_error_code() {
        return $this->code;
    }
}

function is_wp_error( $value ) {
    return $value instanceof WP_Error;
}

function absint( $value ) {
    return abs( (int) $value );
}

function wp_normalize_path( $path ) {
    return str_replace( '\\', '/', $path );
}

function path_join( $base, $path ) {
    return rtrim( $base, '/\\' ) . '/' . ltrim( $path, '/\\' );
}

function trailingslashit( $value ) {
    return rtrim( $value, '/\\' ) . '/';
}

function wp_upload_dir() {
    return array(
        'basedir' => 'C:/fpr-uploads',
        'error'   => '',
    );
}

$fpr013_posts = array(
    101 => array( 'post_type' => 'attachment', 'post_author' => 7, 'post_parent' => 900, 'post_content' => '<!-- wp:image {"id":101} /-->' ),
    102 => array( 'post_type' => 'attachment', 'post_author' => 7, 'post_parent' => 0, 'post_content' => '' ),
    201 => array( 'post_type' => 'attachment', 'post_author' => 1, 'post_parent' => 0, 'post_content' => '' ),
);
$fpr013_deleted = array();

function get_post( $attachment_id ) {
    global $fpr013_posts;
    if ( ! isset( $fpr013_posts[ $attachment_id ] ) ) {
        return null;
    }
    return (object) $fpr013_posts[ $attachment_id ];
}

function get_attached_file( $attachment_id ) {
    return 'C:/fpr-uploads/2026/09/member-' . absint( $attachment_id ) . '.jpg';
}

function wp_get_attachment_metadata( $attachment_id ) {
    return array(
        'file'  => '2026/09/member-' . absint( $attachment_id ) . '.jpg',
        'sizes' => array(
            'medium' => array( 'file' => 'member-' . absint( $attachment_id ) . '-300x300.jpg' ),
            'large'  => array( 'file' => 'member-' . absint( $attachment_id ) . '-1024x1024.jpg' ),
        ),
    );
}

function wp_delete_attachment( $attachment_id, $force_delete ) {
    global $fpr013_deleted, $fpr013_posts;
    $fpr013_deleted[] = array( absint( $attachment_id ), $force_delete );
    unset( $fpr013_posts[ $attachment_id ] );
    return true;
}

class FPR013_WPDB {
    public $posts = 'wp_posts';
    public $last_query = '';

    public function get_col( $query ) {
        global $fpr013_posts;
        $this->last_query = $query;
        if ( ! preg_match( '/post_author IN \\(([^)]*)\\)/', $query, $match ) ) {
            return null;
        }
        $authors = array_map( 'absint', explode( ',', $match[1] ) );
        $ids = array();
        foreach ( $fpr013_posts as $post_id => $post ) {
            if ( 'attachment' === $post['post_type'] && in_array( absint( $post['post_author'] ), $authors, true ) ) {
                $ids[] = $post_id;
            }
        }
        return $ids;
    }
}

function fpr013_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function fpr013_call( $method ) {
    $reflection = new ReflectionMethod( 'Faluss_Production_Reset', $method );
    $reflection->setAccessible( true );
    return $reflection->invokeArgs( null, array_slice( func_get_args(), 1 ) );
}

$root = dirname( __DIR__ );
$service_path = $root . '/plugins/faluss-production-reset/includes/class-faluss-production-reset.php';
$service = file_get_contents( $service_path );

require_once $service_path;

global $wpdb;
$wpdb = new FPR013_WPDB();

// Every attachment authored by the member is selected, including an unreferenced one.
// The administrative post_parent and Gutenberg-shaped content do not block ownership.
$selected = fpr013_call( 'author_attachments', array( 7 ) );
fpr013_assert( array( 101, 102 ) === $selected, 'All and only member-authored attachments must be selected regardless of preserved-content references.' );
fpr013_assert( false !== strpos( $wpdb->last_query, "post_type = 'attachment' AND post_author IN (7)" ), 'The attachment query must use only WordPress attachment type and candidate authorship.' );
fpr013_assert( ! in_array( 201, $selected, true ), 'An administrator-authored attachment must never be selected for a member reset.' );

// Member data references are deleted with their tables; they never select or veto media.
foreach ( array( 'identity_attachments', 'avatar_attachment_id', 'cover_attachment_id', 'media_teaser', 'attachment_referenced_by_preserved_content', '_thumbnail_id', '_elementor_data', 'parse_blocks', 'post_parent', 'post_content', '$wpdb->postmeta', 'fpr_media_ambiguous', 'fpr_media_shared', 'fpr_media_reference_ambiguous' ) as $forbidden ) {
    fpr013_assert( false === strpos( $service, $forbidden ), 'No reference-derived media selection or veto may remain: ' . $forbidden );
}
fpr013_assert( 1 === substr_count( $service, 'LIKE' ) && false !== strpos( $service, 'SHOW TABLES LIKE %s' ), 'The only remaining LIKE must be the required-table existence check, never a media-reference scan.' );

// The same authorship selection is used on Identity and Hub.
$identity_preflight = preg_match( '/private static function preflight_identity\(\).*?private static function preflight_hub\(/s', $service, $identity_match ) ? $identity_match[0] : '';
$hub_preflight = preg_match( '/private static function preflight_hub\(\).*?private static function required_tables\(/s', $service, $hub_match ) ? $hub_match[0] : '';
fpr013_assert( false !== strpos( $identity_preflight, 'author_attachments( $candidate_ids )' ), 'faluss.me must select media exclusively from candidate authors.' );
fpr013_assert( false !== strpos( $hub_preflight, 'author_attachments( $candidate_ids )' ), 'faluss.com must select media exclusively from candidate authors.' );

// Paths remain preflighted and WordPress performs the force deletion.
$paths = fpr013_call( 'attachment_paths', 101 );
fpr013_assert( is_array( $paths ) && 3 === count( $paths ), 'The original file and every declared WordPress size must remain verifiable.' );
$deleted = fpr013_call( 'delete_member_attachments', $selected );
fpr013_assert( 2 === $deleted, 'Every selected member attachment must be deleted.' );
foreach ( $fpr013_deleted as $call ) {
    fpr013_assert( true === $call[1], 'wp_delete_attachment must always receive force_delete=true.' );
}
fpr013_assert( isset( $fpr013_posts[201] ), 'The administrator attachment must remain after member-media deletion.' );

// Existing account, PF/ALB and upgrade boundaries remain explicit.
foreach ( array( "user_can( \$user, 'manage_options' )", 'wp_delete_attachment( $attachment_id, true )', "'token_engine_pf_ledger'", 'fpr_pf_ledger_not_empty' ) as $needle ) {
    fpr013_assert( false !== strpos( $service, $needle ), 'An existing FPR safety invariant is missing: ' . $needle );
}
fpr013_assert( false === strpos( $service, 'token_engine_ledger' ), 'The ALB ledger must remain untouched.' );
fpr013_assert( false === strpos( $service, 'CREATE TABLE' ) && false === strpos( $service, 'dbDelta' ) && false === strpos( $service, 'migrate_' ), 'FPR-01.3 must add no table or migration.' );

echo 'FPR-01.3 member-ownership media reset contract: OK' . PHP_EOL;
