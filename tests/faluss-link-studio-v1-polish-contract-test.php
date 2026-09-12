<?php

function studio_v1_polish_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );

studio_v1_polish_assert( false !== strpos( $bootstrap, 'Version: 0.3.18' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.18'" ), 'The Studio interaction patch must rotate every Faluss Link frontend asset URL.' );

foreach ( array(
    '.elementor-widget.elementor-element.elementor-widget-faluss_link_appearance > .elementor-widget-container',
    '.elementor-widget.elementor-element.elementor-widget-faluss_link_studio > .elementor-widget-container',
    '.faluss-link-studio[data-faluss-studio="v1"]',
    'max-inline-size: none',
    '--fl-studio-gutter:',
) as $needle ) {
    studio_v1_polish_assert( false !== strpos( $studio, $needle ), 'The historical Elementor mount or Studio root is not neutralized: ' . $needle );
}
studio_v1_polish_assert( false === strpos( $studio, '100vw' ), 'The full-width Studio must not introduce viewport-width horizontal overflow.' );
foreach ( array( '.faluss-link-studio__topbar', '.faluss-link-studio__identity', '.faluss-link-studio__context-tabs', '.faluss-link-studio__content', '.faluss-link-studio__create-view' ) as $selector ) {
    $start = strpos( $studio, $selector . ' {' );
    $end = false === $start ? false : strpos( $studio, '}', $start );
    $rule = false === $start || false === $end ? '' : substr( $studio, $start, $end - $start );
    studio_v1_polish_assert( false !== strpos( $rule, 'width: 100%') && false !== strpos( $rule, 'margin: 0'), 'Studio outer regions must share one full-width geometry: ' . $selector );
}
studio_v1_polish_assert( false !== strpos( $studio, '.faluss-link-studio[data-faluss-studio="v1"] .faluss-link-studio__dock-tabs button:focus' ), 'Dock focus styling must be isolated from Elementor.' );
studio_v1_polish_assert( false !== strpos( $studio, 'button:focus:not(:focus-visible)' ) && false !== strpos( $studio, 'button:focus-visible' ), 'Mouse focus contamination must be removed while retaining an accessible keyboard focus.' );

studio_v1_polish_assert( false !== strpos( $editor, 'var statusLifetime = 1800;' ), 'Success feedback must have a bounded 1.8 second lifetime.' );
foreach ( array( 'function clearStatus', "showStatus(studio, data.message || 'Studio enregistré.', false, true)", "notice.removeClass('is-visible')", "clearStatus(studio);" ) as $needle ) {
    studio_v1_polish_assert( false !== strpos( $editor, $needle ), 'Studio toast lifecycle is incomplete: ' . $needle );
}
studio_v1_polish_assert( false !== strpos( $editor, "showStatus(studio, 'Enregistrement…', false, false)" ) && false !== strpos( $editor, 'if (temporary !== false)' ), 'Only an active request may keep its in-progress toast visible.' );
studio_v1_polish_assert( false !== strpos( $link, 'class="faluss-link-studio__notice" role="status" aria-live="polite"></div>' ), 'The Studio must start with an empty polite live region.' );

studio_v1_polish_assert( false !== strpos( $card, '.faluss-link-card--links-solid .faluss-link-card__link{border-color:transparent;border-radius:1px' ), 'Visuel must resolve to the shared low-radius link presentation.' );
studio_v1_polish_assert( false !== strpos( $card, '.faluss-link-card--links-light .faluss-link-card__link{border:1px solid #fff;border-radius:100px' ), 'Minutieux must resolve to the shared outlined 100px pill presentation.' );
studio_v1_polish_assert( false !== strpos( $card, '.faluss-link-card--links-outline .faluss-link-card__link{border-color:transparent;border-radius:1px' ), 'Formel must retain its canonical rectangular presentation.' );
studio_v1_polish_assert( false !== strpos( $link, "const LINK_STYLES = array( 'solid' => 'Visuel', 'light' => 'Minutieux', 'outline' => 'Formel' )" ), 'Labels and canonical link_style values must keep one shared mapping.' );
studio_v1_polish_assert( false !== strpos( $studio, '.faluss-link-studio__style-preview--solid i { border-radius: 1px; }' ) && false !== strpos( $studio, '.faluss-link-studio__style-preview--light i { border: 1px solid #fff; border-radius: 100px; }' ), 'Studio choice previews must mirror the shared Visuel and Minutieux mapping.' );

foreach ( array(
    'data-faluss-studio-back-url=',
    'data-fl-studio-share',
    'data-public-url=',
    'function safeHistoryBack',
    'function sharePublicURL',
    'navigator.share',
    'navigator.clipboard.writeText',
    "if (!closeTransient(studio)) { safeHistoryBack(studio); }",
) as $needle ) {
    studio_v1_polish_assert( false !== strpos( $link . $editor, $needle ), 'Back or public sharing is incomplete: ' . $needle );
}
studio_v1_polish_assert( false === strpos( $link, 'faluss-link-studio__copy-id' ) && false === strpos( $editor, 'faluss-link-studio__copy-id' ), 'More must not expose the former private ID copy behavior.' );
studio_v1_polish_assert( false !== strpos( $link, "home_url( '/' . \$profile['public_slug'] )" ), 'Share must originate from the canonical public handle URL.' );
studio_v1_polish_assert( false !== strpos( $editor, '/\\/mon-faluss\\/?$/' ), 'Client sharing must reject the private Studio route.' );

echo "Faluss Link Studio V1 polish contract: OK\n";
