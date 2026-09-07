<?php

/**
 * ONB-02.2 source contract for shared, non-persistent card previews.
 */

function onb022_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function onb022_method( $source, $start, $end ) {
    $from = strpos( $source, $start );
    $to = false === $from ? false : strpos( $source, $end, $from );
    return false === $from || false === $to ? '' : substr( $source, $from, $to - $from );
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-onboarding.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-onboarding.css' );
$card_css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$preview_resolver = onb022_method( $link, 'private static function onboarding_preview_state', 'private static function save_onboarding_step' );
$public_renderer = onb022_method( $link, 'private static function card_markup', 'private static function public_block_markup' );

onb022_assert( false !== strpos( $bootstrap, 'Version: 0.3.10' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.10'" ), 'The asset cache key must include the current Faluss Link frontend assets.' );
onb022_assert( false !== strpos( $link, "wp_ajax_faluss_link_onboarding_preview" ) && false === strpos( $link, "wp_ajax_nopriv_faluss_link_onboarding_preview" ), 'Only the authenticated member may resolve a draft preview.' );
onb022_assert( false !== strpos( $link, "self::ONBOARDING_SCRIPT, plugins_url( 'assets/js/faluss-link-onboarding.js'" ) && false !== strpos( $link, 'array( self::CARD_SCRIPT )' ), 'The wizard must load the shared card runtime explicitly.' );

onb022_assert( substr_count( $link, 'self::card_preview_markup(' ) >= 4, 'Studio, initial wizard, saved wizard and live draft must share one preview facade.' );
onb022_assert( false !== strpos( $link, 'return self::card_markup( $profile, $preferences, $alignment, true, $blocks, $demo_links, $context )' ), 'The preview facade must delegate to the canonical card renderer.' );
foreach ( array( 'display_name', 'faluss_identity_avatar_id', 'avatar_border', 'name_font', 'name_treatment', 'page_background', 'link_style', 'social_selected', 'social_urls', 'wizard_links', 'normalise_blocks', 'content_blocks' ) as $needle ) {
    onb022_assert( false !== strpos( $preview_resolver, $needle ), 'The draft resolver must carry the current wizard choice into the shared renderer: ' . $needle );
}
foreach ( array( 'save_preferences(', 'save_blocks(', 'save_studio_profile(', "publication_status' => 'published" ) as $needle ) {
    onb022_assert( false === strpos( $preview_resolver, $needle ), 'A live preview must never persist or publish: ' . $needle );
}

onb022_assert( false !== strpos( $link, 'class="faluss-link-onboarding__status" role="status" aria-live="polite"' ) && false !== strpos( $link, 'faluss-link-onboarding__error" role="alert" hidden' ), 'Success uses its own resilient live region while recoverable errors remain visible.' );
onb022_assert( false === strpos( $link, 'faluss-link-onboarding__notice' ) && false === strpos( $css, 'faluss-link-onboarding__notice' ) && false === strpos( $script, 'function notice(' ), 'The persistent success panel and its reserved layout must be removed.' );
onb022_assert( false !== strpos( $script, "announce(root, 'Étape enregistrée.')" ) && false === strpos( $script, "showError(root, 'Étape enregistrée.')" ), 'A saved step may only emit a non-visual live-region announcement.' );
foreach ( array( 'setPending(root, true)', "announce(root, 'Enregistrement en cours.')", 'button.disabled = value', "showError(root, 'Cette étape n’a pas pu être enregistrée." ) as $needle ) {
    onb022_assert( false !== strpos( $script, $needle ), 'Saving must expose a bounded loading/error state: ' . $needle );
}

onb022_assert( false !== strpos( $script, "card.style.setProperty('--fl-page-background', background.value)" ) && false !== strpos( $preview_resolver, "valid_hex( \$field( 'page_background'" ), 'The background draft must update immediately and reach the server renderer.' );
foreach ( array( 'faluss-link-card--links-solid', 'faluss-link-card--links-outline', "card.classList.add('faluss-link-card--links-' + linkStyle.value)" ) as $needle ) {
    onb022_assert( false !== strpos( $script, $needle ), 'Every allowed button shape must be visibly applied in the live card: ' . $needle );
}
onb022_assert( false !== strpos( $public_renderer, 'faluss-link-card--links-<?php echo esc_attr( $preferences[\'link_style\'] ); ?>' ), 'Saved button styles must use the same canonical public card class.' );
onb022_assert( false !== strpos( $script, 'scheduleSharedPreview' ) && false !== strpos( $script, "data.set('action', 'faluss_link_onboarding_preview')" ) && false !== strpos( $script, 'AbortController' ), 'All substantive drafts must refresh the normalized shared preview without stale responses.' );

foreach ( array( 'data-faluss-preview-only', 'faluss-link-card__link--skeleton', '$preview && $preview_demo_links && ! $has_link' ) as $needle ) {
    onb022_assert( false !== strpos( $public_renderer, $needle ), 'Preview-only link skeleton is not correctly gated: ' . $needle );
}
onb022_assert( false !== strpos( $link, '$markup = self::card_markup( $profile, $preferences, $alignment, false, $blocks );' ), 'The public route must call the canonical renderer without demo links.' );
onb022_assert( false === strpos( $preview_resolver, 'data-faluss-preview-only' ) && false === strpos( $preview_resolver, 'link--skeleton' ), 'Demo link markup must never enter normalized or stored block data.' );

foreach ( array( 'faluss-link-onboarding__device-viewport', 'width: 271px', 'height: 557px', 'border-radius: 50px 50px 0 0' ) as $needle ) {
    onb022_assert( false !== strpos( $css, $needle ), 'The Figma mobile preview and final card must stay layered, compact and legible: ' . $needle );
}
onb022_assert( false !== strpos( $link, 'faluss-link-card__preview-links' ), 'The shared renderer must retain preview-only link placeholders.' );
onb022_assert( false === strpos( $css, 'transform:scale(' ), 'The Figma shell must leave compact card density to the shared renderer.' );
onb022_assert( false !== strpos( $card_css, 'faluss-link-card--density-compact' ), 'The shared renderer must own the compact density context.' );
onb022_assert( false === strpos( $css, 'scale(.72)' ) && false === strpos( $css, 'max-height: 62%' ), 'The obsolete oversized mobile preview composition must not return.' );
onb022_assert( false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' ) && false === strpos( $script, 'document.body.style.overflow' ), 'Motion and Safari-safe viewport behavior must remain intact.' );
onb022_assert( false !== strpos( $link, 'wizard_links[<?php echo esc_attr( $field_key ); ?>][block_id]' ) && false !== strpos( $script, "wizard_links[' + id + '][block_id]" ), 'Real links must retain one grouped stable block payload in preview and persistence.' );

echo 'ONB-02.2 shared immersive preview contract: OK' . PHP_EOL;
