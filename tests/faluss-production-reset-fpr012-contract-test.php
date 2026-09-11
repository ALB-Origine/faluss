<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

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

function untrailingslashit( $value ) {
    return rtrim( $value, '/' );
}

function wp_upload_dir() {
    return array( 'baseurl' => 'https://faluss.me/wp-content/uploads', 'error' => '' );
}

function wp_get_attachment_url( $attachment_id ) {
    unset( $attachment_id );
    return 'https://faluss.me/wp-content/uploads/2026/09/pf.png';
}

function wp_get_attachment_metadata( $attachment_id ) {
    unset( $attachment_id );
    return array(
        'file'  => '2026/09/pf.png',
        'sizes' => array(
            'medium' => array( 'file' => 'pf-300x300.png' ),
        ),
    );
}

class FPR012_WPDB {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $last_error = '';
    public $thumbnail = null;
    public $preserved_posts = array();
    public $elementor_payloads = array();

    public function prepare( $query ) {
        return $query;
    }

    public function get_var( $query ) {
        unset( $query );
        return $this->thumbnail;
    }

    public function get_results( $query, $output ) {
        unset( $query, $output );
        return $this->preserved_posts;
    }

    public function get_col( $query ) {
        unset( $query );
        return $this->elementor_payloads;
    }
}

$fpr012_blocks = array();

function parse_blocks( $content ) {
    global $fpr012_blocks;
    return $fpr012_blocks[ $content ] ?? array();
}

function fpr012_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function fpr012_call( $method ) {
    $reflection = new ReflectionMethod( 'Faluss_Production_Reset', $method );
    $reflection->setAccessible( true );
    return $reflection->invokeArgs( null, array_slice( func_get_args(), 1 ) );
}

$root = dirname( __DIR__ );
$service_path = $root . '/plugins/faluss-production-reset/includes/class-faluss-production-reset.php';
$service = file_get_contents( $service_path );

require_once $service_path;

$attachment_id = 42;
$original_url = 'https://faluss.me/wp-content/uploads/2026/09/pf.png';
$size_url = 'https://faluss.me/wp-content/uploads/2026/09/pf-300x300.png';
$urls = array( $original_url, $size_url );

global $wpdb;
$wpdb = new FPR012_WPDB();

// An ID-shaped substring in normal content is not a media reference.
$plain = fpr012_call( 'content_references_attachment', '1420 202642 setting_42', $attachment_id, $urls );
fpr012_assert( false === $plain, 'An incidental numeric substring in preserved content must not block media deletion.' );

// Gutenberg only blocks exact, structurally parsed image and gallery references.
$image_content = '<!-- wp:image -->';
$fpr012_blocks[ $image_content ] = array(
    array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 42 ), 'innerBlocks' => array() ),
);
fpr012_assert( true === fpr012_call( 'content_references_attachment', $image_content, $attachment_id, $urls ), 'A Gutenberg image with the exact attachment ID must block.' );

$gallery_content = '<!-- wp:gallery -->';
$fpr012_blocks[ $gallery_content ] = array(
    array( 'blockName' => 'core/gallery', 'attrs' => array( 'ids' => array( 7, 42 ) ), 'innerBlocks' => array() ),
);
fpr012_assert( true === fpr012_call( 'content_references_attachment', $gallery_content, $attachment_id, $urls ), 'A Gutenberg gallery with the exact attachment ID must block.' );

$other_image_content = '<!-- wp:image other -->';
$fpr012_blocks[ $other_image_content ] = array(
    array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 1420 ), 'innerBlocks' => array() ),
);
fpr012_assert( false === fpr012_call( 'content_references_attachment', $other_image_content, $attachment_id, $urls ), 'A Gutenberg image with another ID must not block.' );

$substring_image_content = '<!-- wp:image substring -->';
$fpr012_blocks[ $substring_image_content ] = array(
    array( 'blockName' => 'core/image', 'attrs' => array( 'setting_42' => '202642' ), 'innerBlocks' => array() ),
);
fpr012_assert( false === fpr012_call( 'content_references_attachment', $substring_image_content, $attachment_id, $urls ), 'A Gutenberg numeric substring must not be treated as an image reference.' );

$malformed_gutenberg = '<!-- wp:image malformed -->';
$fpr012_blocks[ $malformed_gutenberg ] = array(
    array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array() ),
);
$gutenberg_error = fpr012_call( 'content_references_attachment', $malformed_gutenberg, $attachment_id, $urls );
fpr012_assert( is_wp_error( $gutenberg_error ) && 'fpr_media_reference_ambiguous' === $gutenberg_error->get_error_code(), 'A pertinent malformed Gutenberg payload must fail closed with a generic error.' );

// Exact WordPress original and declared-size URLs in HTML block; surrounding text does not.
fpr012_assert( true === fpr012_call( 'html_references_attachment', '<img src="' . $original_url . '" />', $urls ), 'The exact original attachment URL in HTML must block.' );
fpr012_assert( true === fpr012_call( 'html_references_attachment', '<img srcset="' . $size_url . ' 300w" />', $urls ), 'The exact declared attachment-size URL in HTML must block.' );
fpr012_assert( false === fpr012_call( 'html_references_attachment', '<p>' . $original_url . '-copy</p>', $urls ), 'A URL-shaped text substring outside an HTML media attribute must not block.' );

// Elementor requires a valid image object binding the exact ID and URL.
$valid_elementor = '[{"settings":{"image":{"id":42,"url":"' . $original_url . '"}}}]';
fpr012_assert( true === fpr012_call( 'elementor_references_attachment', $valid_elementor, $attachment_id, $urls ), 'A valid Elementor image binding the exact ID and URL must block.' );
$invalid_elementor = '[{"settings":{"image":{"id":42,"url":"' . $original_url . '"}}]';
$elementor_error = fpr012_call( 'elementor_references_attachment', $invalid_elementor, $attachment_id, $urls );
fpr012_assert( is_wp_error( $elementor_error ) && 'fpr_media_reference_ambiguous' === $elementor_error->get_error_code(), 'A pertinent malformed Elementor payload must fail closed with a generic error.' );
fpr012_assert( false === fpr012_call( 'elementor_references_attachment', '[{"settings_42":"1420"}]', $attachment_id, $urls ), 'Arbitrary Elementor data containing an ID-shaped substring must not block.' );

// The SQL only examines preserved authors and recognized metadata, never broad ID LIKE searches.
foreach ( array( 'p.post_author NOT IN ({$excluded})', "pm.meta_key = '_thumbnail_id' AND pm.meta_value = %s", "pm.meta_key = '_elementor_data'" ) as $needle ) {
    fpr012_assert( false !== strpos( $service, $needle ), 'The preserved-content scope or recognized metadata guard is missing: ' . $needle );
}
foreach ( array( 'post_content LIKE', 'pm.meta_value LIKE', 'esc_like( (string) $attachment_id )' ) as $forbidden ) {
    fpr012_assert( false === strpos( $service, $forbidden ), 'No generic numeric media reference search may remain: ' . $forbidden );
}
fpr012_assert( false !== strpos( $service, "get_post_field( 'post_author', \$attachment->post_parent )" ), 'A member attachment with a preserved post_parent must still block.' );
fpr012_assert( false !== strpos( $service, "new WP_Error( 'fpr_media_reference_ambiguous', 'Une référence média conservée n’est pas vérifiable ; le preflight est bloqué.' )" ), 'An ambiguous media reference must use the generic, non-sensitive error.' );

// The recognized thumbnail is exact: no match is safe, a matching preserved thumbnail blocks.
$wpdb->thumbnail = null;
$wpdb->preserved_posts = array( array( 'ID' => 8, 'post_content' => 'setting_42 1420' ) );
$wpdb->elementor_payloads = array( '[{"setting_42":"1420"}]' );
fpr012_assert( false === fpr012_call( 'attachment_referenced_by_preserved_content', $attachment_id, array( 99 ) ), 'A preserved content or arbitrary metadata substring must not be treated as a reference.' );
$wpdb->thumbnail = 8;
fpr012_assert( true === fpr012_call( 'attachment_referenced_by_preserved_content', $attachment_id, array( 99 ) ), 'A preserved _thumbnail_id exactly matching the attachment must block.' );

echo 'FPR-01.2 exact preserved-media reference contract: OK' . PHP_EOL;
