<?php

function fl184_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }

$root = dirname( __DIR__ );
$identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
$immersive = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-immersive.css' );
$card = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$studio = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-studio.css' );
$discoveries = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-discoveries.css' );
$reward = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-reward.js' );
$immersive_js = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-immersive.js' );
$editor = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );

$template_call = strpos( $identity, 'self::render_elementor_template( self::get_template_id() )' );
$header_wrapper = strpos( $identity, '<div class="faluss-identity-public-header-layer">' );
fl184_assert( false !== $template_call && false !== $header_wrapper && $header_wrapper > $template_call, 'The real Elementor/The Plus header wrapper must be emitted after the immersive profile so its external overlay can manage its own top layer.' );
fl184_assert( false !== strpos( $identity, 'self::render_elementor_header();' ) && false !== strpos( $identity, "elementor_theme_do_location( 'header' )" ), 'The public shell must retain the actual Elementor Theme Builder header location.' );

$header_start = strpos( $immersive, 'body.faluss-identity-public-route > .faluss-identity-public-header-layer,' );
$header_end = strpos( $immersive, '}', $header_start );
$header = substr( $immersive, $header_start, $header_end - $header_start + 1 );
$profile_start = strpos( $immersive, 'body.faluss-identity-public-route > .faluss-identity-profile-page {' );
$profile_end = strpos( $immersive, '}', $profile_start );
$profile = substr( $immersive, $profile_start, $profile_end - $profile_start + 1 );
fl184_assert( false !== strpos( $header, 'position: absolute;' ) && false !== strpos( $header, 'overflow: visible;' ) && false === strpos( $header, 'z-index:' ) && false === strpos( $header, 'isolation:' ), 'The Faluss header wrapper must stay out of flow without creating a trapping context for The Plus Off Canvas.' );
fl184_assert( false !== strpos( $profile, 'position: relative;' ) && false !== strpos( $profile, 'overflow: visible;' ) && false === strpos( $profile, 'z-index:' ) && false === strpos( $profile, 'isolation:' ), 'The immersive shell must remain below external overlays without clipping them.' );
foreach ( array( 'clip-path:', 'contain:', 'pointer-events:' ) as $forbidden ) {
    fl184_assert( false === strpos( $immersive, $forbidden ), 'The public immersive stylesheet must not constrain an external panel: ' . $forbidden );
}

fl184_assert( false === strpos( $immersive_js, 'preventDefault' ) && false === strpos( $immersive_js, 'stopPropagation' ) && false !== strpos( $immersive_js, "addEventListener('scroll', requestUpdate, { passive: true })" ), 'The immersive depth script must only observe passive scroll and never intercept a The Plus header click.' );
fl184_assert( false === strpos( $reward . $editor, 'stopPropagation' ) && false === strpos( $reward . $editor, 'stopImmediatePropagation' ), 'Faluss Link must not stop a header or The Plus event from bubbling.' );
fl184_assert( false !== strpos( $reward, "event.target.closest('[data-faluss-reward-claim]')" ) && false !== strpos( $reward, 'component.contains(button)') && false !== strpos( $reward, 'event.preventDefault();' ), 'The reward listener may prevent only its own claim action after local containment is verified.' );
fl184_assert( false !== strpos( $editor, ".on('click.falussLink', '.faluss-link-studio") && false === strpos( $editor, ".on('click.falussLink', 'header") && false === strpos( $editor, ".on('click.falussLink', 'body"), 'Studio event delegation must stay within Faluss Link selectors and never bind a header-wide handler.' );

fl184_assert( false === strpos( $studio, 'faluss-identity-public-header-layer' ) && false === strpos( $discoveries, 'faluss-identity-public-header-layer' ), 'Studio and Mes découvertes must remain outside the public The Plus compatibility layer.' );
foreach ( array( '.faluss-link-card__social a', '.faluss-link-card__link', '.faluss-link-action', '.faluss-link-card__media-teaser' ) as $interactive ) {
    fl184_assert( false !== strpos( $card, $interactive ), 'The public card must remain interactive after the external panel closes: ' . $interactive );
}
fl184_assert( false !== strpos( $immersive, 'margin: 0;' ) && false !== strpos( $immersive, 'padding: 0;' ) && false !== strpos( $immersive, 'background: var(--faluss-ink, #080808)' ), 'The compatibility layer must retain a hero flush with the viewport and no white gap.' );

echo "FL-18.4 The Plus Popup Builder / Off Canvas contract: OK\n";
