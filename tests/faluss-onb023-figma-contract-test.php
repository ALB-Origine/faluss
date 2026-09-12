<?php

/**
 * ONB-02.3 Figma product UI contract. It intentionally verifies the scoped
 * WordPress implementation rather than rendering Figma's generated React.
 */

function onb023_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-onboarding.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-onboarding.css' );
$product_ui = file_get_contents( $root . '/docs/FALUSS_PRODUCT_UI.md' );
$skill = file_get_contents( $root . '/.codex/skills/faluss-ui/SKILL.md' );
$preview_resolver_start = strpos( $link, 'private static function onboarding_preview_state' );
$preview_resolver_end = strpos( $link, 'private static function save_onboarding_step', $preview_resolver_start );
$preview_resolver = false === $preview_resolver_start || false === $preview_resolver_end ? '' : substr( $link, $preview_resolver_start, $preview_resolver_end - $preview_resolver_start );

onb023_assert( false !== strpos( $bootstrap, 'Version: 0.3.16' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.16'" ), 'The current Faluss Link frontend assets require a fresh plugin version.' );
foreach ( array( '3695-2381', '3703-2466', '3703-2546', '3704-2648', '#ED4343', '#D43D3D', '271 × 557', 'FALUSS_PLUGIN_UI.md' ) as $needle ) {
    onb023_assert( false !== strpos( $product_ui, $needle ), 'The product contract must retain the exact Figma source/token mapping: ' . $needle );
}
onb023_assert( false !== strpos( $skill, 'FALUSS_PRODUCT_UI.md' ) && false !== strpos( $skill, 'FALUSS_PLUGIN_UI.md' ), 'The Faluss UI skill must route product UI and WordPress administration to separate contracts.' );

foreach ( array( 'faluss-onboarding-header-back.svg', 'faluss-onboarding-header-logo.png', 'faluss-onboarding-device.png', 'faluss-onboarding-upload-plus.svg' ) as $asset ) {
    $path = $root . '/plugins/faluss-link/assets/images/' . $asset;
    onb023_assert( is_file( $path ) && filesize( $path ) > 100, 'The exact local Figma asset must be packaged with Faluss Link: ' . $asset );
    onb023_assert( false !== strpos( $link, $asset ), 'The onboarding renderer must use the packaged Figma asset: ' . $asset );
}
onb023_assert( false === strpos( $link, 'figma.com/api/mcp/asset' ) && false === strpos( $css, 'figma.com/api/mcp/asset' ), 'No temporary Figma asset URL may be a production dependency.' );

foreach ( array( 'data-onboarding-preview-card', 'faluss-link-onboarding__device', 'width="271" height="557"', "'onboarding-preview'" ) as $needle ) {
    onb023_assert( false !== strpos( $link, $needle ), 'The compact device preview must retain the shared card renderer: ' . $needle );
}
foreach ( array( "root.querySelector('[data-onboarding-preview-card] .faluss-link-card')", "root.querySelector('[data-onboarding-preview-card]')", 'replaceChildren()', 'FalussLinkCard.initialize' ) as $needle ) {
    onb023_assert( false !== strpos( $script, $needle ), 'The live preview must replace only the shared card viewport: ' . $needle );
}
onb023_assert( false !== strpos( $preview_resolver, 'faluss_identity_avatar_id' ) && false !== strpos( $preview_resolver, 'avatar_border' ) && false !== strpos( $preview_resolver, 'page_background' ) && false !== strpos( $preview_resolver, 'name_font' ) && false !== strpos( $preview_resolver, 'social_selected' ) && false !== strpos( $preview_resolver, 'wizard_links' ), 'The draft resolver must carry each visual preference through the canonical renderer.' );
onb023_assert( false === strpos( $preview_resolver, 'save_preferences(' ) && false === strpos( $preview_resolver, 'save_blocks(' ) && false === strpos( $preview_resolver, "publication_status' => 'published" ), 'The compact preview and its skeleton remain non-persistent.' );

foreach ( array( '#000000', '#191919', '#737373', '#DDDDDD', '#FFFFFF', '#321752', '#350D0D', '#1A7061', '#A748B5', 'data-onboarding-background-choice', 'name="page_background" type="color"', 'faluss-link-onboarding__button-choice--outline', 'faluss-link-onboarding__button-choice--solid', 'faluss-link-onboarding__button-choice--light' ) as $needle ) {
    onb023_assert( false !== strpos( $link, $needle ), 'Figma backgrounds and all stored button variants must remain selectable: ' . $needle );
}
foreach ( array( 'syncBackgroundSwatches', 'syncChoiceSelections', "color.value = event.target.value", "card.style.setProperty('--fl-page-background', background.value)", 'faluss-link-card--avatar-yes', 'setChoicePanel', 'ArrowRight', 'aria-selected', 'prefers-reduced-motion' ) as $needle ) {
    onb023_assert( false !== strpos( $script . $css, $needle ), 'Figma controls must update their live card or remain keyboard/motion accessible: ' . $needle );
}
foreach ( array( 'width: 271px', 'height: 557px', 'border-radius: 50px 50px 0 0', 'min-height: 45px' ) as $needle ) {
    onb023_assert( false !== strpos( $css, $needle ), 'The local CSS must preserve the Figma dimension: ' . $needle );
}
onb023_assert( false === strpos( $css, 'transform:scale(' ), 'The Figma shell must not blur a separate static card through transform scale.' );
onb023_assert( false === strpos( $css, 'className=' ) && false === strpos( $css, 'rounded-[' ), 'The WordPress stylesheet must not retain generated React or Tailwind source.' );
onb023_assert( false === strpos( $css, ':root' ) && 0 === preg_match( '/(^|[}])\\s*(?:html|body)\\s*\\{/m', $css ), 'The product UI must remain scoped and cannot alter the page shell.' );
onb023_assert( false === strpos( $script, 'localStorage' ) && false === strpos( $script, 'sessionStorage' ), 'Resumption remains server-owned rather than browser-persisted.' );
onb023_assert( false !== strpos( $link, 'card_wizard_context()' ) && false !== strpos( $link, 'advance_card_wizard( $target_step )' ) && false !== strpos( $link, 'complete_card_wizard()' ), 'ONB-01 resumption, progress and explicit publication remain unchanged.' );

echo 'ONB-02.3 Figma product UI contract: OK' . PHP_EOL;
