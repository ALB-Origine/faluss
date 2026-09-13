<?php

function studio_v1_wiring_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
$card_script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-card.js' );
$editor_script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );

studio_v1_wiring_assert( false !== strpos( $bootstrap, 'Version: 0.3.19' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.19'" ), 'Faluss Link must retain the Studio interaction patch under version 0.3.19.' );
studio_v1_wiring_assert( false !== strpos( $link, "add_shortcode( 'faluss_link_appearance', array( __CLASS__, 'appearance_shortcode' ) )" ), 'The historical shortcode must remain registered for existing Elementor content.' );
studio_v1_wiring_assert( false !== strpos( $link, "add_shortcode( 'faluss_link_studio', array( __CLASS__, 'studio_shortcode' ) )" ), 'The canonical Studio shortcode must remain registered.' );
studio_v1_wiring_assert( false !== strpos( $link, 'public static function appearance_shortcode() { return self::studio_shortcode(); }' ), 'The historical shortcode must mount the canonical Studio renderer.' );
studio_v1_wiring_assert( false !== strpos( $link, 'public static function studio_shortcode() { return self::render_studio(); }' ), 'The canonical shortcode must mount Studio V1.' );
studio_v1_wiring_assert( false === strpos( $widgets, 'render() { echo Faluss_Link::render_editor(); }' ), 'No registered Elementor widget may leave the legacy editor as the active surface.' );
studio_v1_wiring_assert( 2 === substr_count( $widgets, 'render() { echo Faluss_Link::studio_shortcode(); }' ), 'Both existing Elementor widget IDs must mount the same Studio V1 callback.' );
studio_v1_wiring_assert( false !== strpos( $link, 'data-faluss-studio="v1"' ), 'The rendered HTML must expose an explicit Studio V1 root marker.' );

foreach ( array(
    "const STUDIO_STYLE = 'faluss-link-studio'",
    "const SCRIPT = 'faluss-link-editor'",
    "plugins_url( 'assets/css/faluss-link-studio.css'",
    "plugins_url( 'assets/js/faluss-link-editor.js'",
    'FALUSS_LINK_VERSION',
    'wp_enqueue_style( self::STUDIO_STYLE )',
    'wp_enqueue_script( self::SCRIPT )',
) as $needle ) {
    studio_v1_wiring_assert( false !== strpos( $link, $needle ), 'Studio asset registration or enqueue is missing: ' . $needle );
}

foreach ( array( 'Faluss_Link_Appearance_Widget', 'Faluss_Link_Studio_Widget', "'faluss-link-immersive'", "'faluss-link-studio'", "'faluss-link-editor'" ) as $needle ) {
    studio_v1_wiring_assert( false !== strpos( $widgets, $needle ), 'Elementor Studio dependency is missing: ' . $needle );
}
studio_v1_wiring_assert( false !== strpos( $card_script, 'frontend/element_ready/faluss_link_appearance.default' ), 'The shared card runtime must initialize historical Elementor placements.' );
studio_v1_wiring_assert( false !== strpos( $editor_script, 'frontend/element_ready/faluss_link_appearance.default' ), 'The Studio editor runtime must initialize historical Elementor placements.' );

$render_start = strpos( $link, 'public static function render_studio()' );
$render_end = strpos( $link, 'public static function save()', $render_start );
$render = substr( $link, $render_start, $render_end - $render_start );
studio_v1_wiring_assert( false !== strpos( $render, 'self::editor_assets();' ), 'Studio V1 must enqueue its assets from the renderer, including shortcode contexts.' );
studio_v1_wiring_assert( false === strpos( $render, 'is_page(' ), 'Studio rendering must not depend on a fragile page slug or Elementor route condition.' );

echo "Faluss Link Studio V1 wiring contract: OK\n";
