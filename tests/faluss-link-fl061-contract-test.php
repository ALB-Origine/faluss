<?php
function fl061_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$widgets = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-widgets.php' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$depth = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-immersive.js' );

foreach ( array( "'page_background' => '#FFFDF5'", "'page_background' => \$page_background ? \$page_background : '#FFFDF5'", "--fl-page-background:%s", 'sanitize_hex_color', 'render_card' ) as $needle ) { fl061_assert( false !== strpos( $link, $needle ), 'Member page background is not validated, persisted, and emitted: ' . $needle ); }
foreach ( array( '--fl-page: var(--fl-page-background, var(--fl-canvas));', 'background: var(--fl-page);', 'faluss-link-card__body', 'var(--fl-page) 100%', 'faluss-link-card__links' ) as $needle ) { fl061_assert( false !== strpos( $immersive, $needle ), 'The immersive profile surface does not retain the selected member background: ' . $needle ); }
fl061_assert( false === strpos( $immersive, "\nbody {" ), 'The immersive member background must not depend on the WordPress body.' );
foreach ( array( 'faluss-link-studio__preview .faluss-link-card', 'faluss-link-studio__preview .faluss-link-card__body', 'var(--fl-page-background,var(--fl-canvas))', '--fl-page-background' ) as $needle ) { fl061_assert( false !== strpos( $studio . $editor, $needle ), 'Studio preview does not immediately mirror the member background: ' . $needle ); }
foreach ( array( "'page_background'", "--fl-page-background:{{VALUE}} !important;" ) as $needle ) { fl061_assert( false !== strpos( $widgets, $needle ), 'An explicit Elementor page background cannot override the member preference: ' . $needle ); }
fl061_assert( false === strpos( $widgets, "'default' => '#FFFDF5'" ), 'An empty Elementor color control must not overwrite the member preference.' );
foreach ( array( 'wp_register_script( self::IMMERSIVE_SCRIPT', "wp_enqueue_script( self::IMMERSIVE_SCRIPT )", 'get_script_depends() { return array( \'faluss-link-card\', \'faluss-link-immersive\' ); }' ) as $needle ) { fl061_assert( false !== strpos( $link . $widgets, $needle ), 'The immersive script dependency is not declared for the card widget: ' . $needle ); }
foreach ( array( '.faluss-link-card--presentation-immersive', 'falussLinkImmersiveReady', "addEventListener('scroll', requestUpdate, { passive: true })", 'requestAnimationFrame', 'prefers-reduced-motion: reduce', '--fl-immersive-depth', '--fl-immersive-panel-depth' ) as $needle ) { fl061_assert( false !== strpos( $depth . $immersive, $needle ), 'Depth is not scoped, passive, RAF-driven, or motion-safe: ' . $needle ); }
fl061_assert( false === strpos( $depth, "querySelectorAll('.faluss-link-card')" ), 'Depth must not target compact cards or Studio previews.' );
fl061_assert( false === strpos( $link, 'passwordless' ) && false === strpos( $link, 'client_secret' ) && false === strpos( $link, 'subscription' ), 'FL-06.1 must not introduce SSO, passwordless, or business data.' );
echo "FL-06.1 contract: OK\n";
