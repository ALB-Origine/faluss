<?php
/** ONB-02.4 shared card presentation and Figma shell contract. */

function onb024_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$card_css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$onboarding_css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-onboarding.css' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-onboarding.js' );
$product_ui = file_get_contents( $root . '/docs/FALUSS_PRODUCT_UI.md' );

foreach ( array( 'private static function card_presentation(', 'private static function card_markup_from_presentation(', 'private static function card_preview_markup(', "'public', 'studio-preview', 'onboarding-preview'", "'density' => 'onboarding-preview' === \$context ? 'compact' : 'standard'" ) as $needle ) {
    onb024_assert( false !== strpos( $link, $needle ), 'One shared presentation facade must serve public, Studio and onboarding: ' . $needle );
}
foreach ( array( "'page_background'", "'avatar_border'", "'name_font'", "'name_treatment'", "'alignment'", "'link_style'", "'social_links'" ) as $needle ) {
    onb024_assert( false !== strpos( $link, $needle ), 'The shared presentation must carry resolved member preference: ' . $needle );
}
onb024_assert( false !== strpos( $link, 'private static function onboarding_theme_overrides(' ) && false !== strpos( $link, "\$preferences['theme_overrides'] = self::onboarding_theme_overrides" ), 'An arbitrary onboarding preference combination must resolve above a theme without a wizard-only card style.' );
onb024_assert( false !== strpos( $link, 'data-faluss-card-context="<?php echo esc_attr( $presentation[\'context\'] ); ?>"' ), 'The renderer must identify its shared presentation context.' );
onb024_assert( false !== strpos( $link, 'data-faluss-card-density="<?php echo esc_attr( $presentation[\'density\'] ); ?>"' ), 'Onboarding must request density rather than a separate card.' );
onb024_assert( false !== strpos( $link, "--fl-canvas:%s" ), 'The member page background must be applied on the shared card surface.' );

foreach ( array( 'private static function preview_social_markup(', "array( 'instagram', 'tiktok', 'x' )", 'data-faluss-preview-only', 'faluss-link-card__link--skeleton', 'preview_demo_links' ) as $needle ) {
    onb024_assert( false !== strpos( $link, $needle ), 'Preview-only social/link skeletons must remain in the shared renderer: ' . $needle );
}
$preview_start = strpos( $link, 'private static function onboarding_preview_state' );
$preview_end = strpos( $link, 'private static function save_onboarding_step', $preview_start );
$preview = false === $preview_start || false === $preview_end ? '' : substr( $link, $preview_start, $preview_end - $preview_start );
onb024_assert( false === strpos( $preview, 'save_preferences(' ) && false === strpos( $preview, 'save_blocks(' ) && false === strpos( $preview, "publication_status' => 'published" ), 'Preview skeletons and drafts must not persist or publish.' );

foreach ( array( 'faluss-link-card--density-compact', '.faluss-link-card--align-center .faluss-link-card__body', '.faluss-link-card--align-center .faluss-link-card__avatar', '.faluss-link-card__social--inline .faluss-link-card__social-demo', '--fl-canvas', '.faluss-link-card--links-outline .faluss-link-card__link', 'border-radius:1px', '.faluss-link-card--links-solid .faluss-link-card__link', '.faluss-link-card--links-light .faluss-link-card__link', 'border:1px solid #fff', 'color-mix(in srgb,var(--fl-action) 35%,transparent)' ) as $needle ) {
    onb024_assert( false !== strpos( $card_css, $needle ), 'Shared card CSS must propagate appearance and exact button variants: ' . $needle );
}
onb024_assert( false === strpos( $onboarding_css, 'transform:scale(' ), 'The Figma shell cannot scale a second static card.' );
foreach ( array( 'height:100dvh', 'padding-top:env(safe-area-inset-top,0px)', 'faluss-link-onboarding[data-current-step=avatar] .faluss-link-onboarding__preview', 'faluss-link-onboarding[data-current-step=socials] .faluss-link-onboarding__preview', 'faluss-link-onboarding[data-current-step=links] .faluss-link-onboarding__preview', 'overflow-y:auto', 'button:focus-visible', 'button[aria-selected=true]' ) as $needle ) {
    onb024_assert( false !== strpos( $onboarding_css, $needle ), 'The shell must preserve safe area, preview/no-preview layouts, constrained scrolling and readable focus: ' . $needle );
}
foreach ( array( "card.style.setProperty('--fl-canvas', background.value)", 'FalussLinkCard.initialize', 'replacePreview', 'scheduleSharedPreview' ) as $needle ) {
    onb024_assert( false !== strpos( $script, $needle ), 'The live draft preview must update the shared card rather than a static mock: ' . $needle );
}
foreach ( array( 'preview shell Figma', 'présentation de carte partagée', 'densité', 'Instagram, TikTok, X', 'ONB-02.4' ) as $needle ) {
    onb024_assert( false !== strpos( $product_ui, $needle ), 'The product UI contract must preserve the shell/presentation boundary: ' . $needle );
}

echo 'ONB-02.4 shared presentation contract: OK' . PHP_EOL;
