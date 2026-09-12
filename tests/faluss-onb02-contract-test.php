<?php

/**
 * ONB-02 is intentionally a source-level contract: no WordPress, media or
 * database is booted here. It protects the ownership boundary and the exact
 * resumable transitions that the runtime then executes transactionally.
 */

function onb02_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$identity = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-onboarding.php' );
$profile = file_get_contents( $root . '/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php' );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$schema = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link-schema.php' );
$script = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-onboarding.js' );
$css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-onboarding.css' );
$onb01 = file_get_contents( $root . '/plugins/faluss-identity/assets/js/faluss-identity-onboarding.js' );

foreach ( array( 'CARD_WIZARD_STEPS', "'wizard_name'", "'wizard_finish'", 'card_wizard_context', 'advance_card_wizard', 'complete_card_wizard', "'draft' ===", "'published' ===" ) as $needle ) {
    onb02_assert( false !== strpos( $identity, $needle ), 'Identity must distinguish a resumable draft from a published card: ' . $needle );
}
onb02_assert( false !== strpos( $identity, "'claimed', 'wizard_name'" ), 'A successful reserved handle must start the wizard at its first persisted step.' );
onb02_assert( false !== strpos( $identity, "'redirect' => self::onboarding_url()" ), 'The selected Elementor onboarding page remains the route after handle reservation.' );
onb02_assert( false !== strpos( $profile, 'faluss_identity_avatar_id' ) && false !== strpos( $profile, 'post_author' ) && false !== strpos( $profile, 'wp_attachment_is_image' ), 'Identity accepts a wizard avatar only after member ownership and image validation.' );

foreach ( array( 'render_onboarding_wizard', 'save_onboarding_wizard', 'finish_onboarding_wizard', 'upload_onboarding_avatar', 'save_onboarding_identity', 'save_preferences( $faluss_id, $post )', 'save_onboarding_links_transaction', 'persist_external_links_in_transaction', 'content_blocks( $faluss_id,', "'preview' =>", "'published'", 'complete_card_wizard', 'onboarding_social_url', 'onboarding_network_selection', 'onboarding_name_fonts', 'onboarding_no_cache', 'DONOTCACHEPAGE', 'litespeed_control_set_nocache', 'owned_image', 'wp_check_filetype_and_ext', "'image/'" ) as $needle ) {
    onb02_assert( false !== strpos( $link, $needle ), 'Wizard must write its data only through the canonical owner path: ' . $needle );
}
onb02_assert( false !== strpos( $link, "['publication_status'] = 'published'" ) || false !== strpos( $link, "'publication_status' => 'published'" ), 'Final publication is explicit rather than implied by a draft save.' );
onb02_assert( false !== strpos( $link, "'published' ===" ) && false !== strpos( $link, 'finish_onboarding_profile' ), 'A repeated final request must resolve the already-published profile idempotently.' );
onb02_assert( false === strpos( $link, 'CREATE TABLE' ) && false === strpos( $link, 'Faluss_Link_Onboarding_Schema' ), 'The wizard creates neither a parallel table nor a persistent onboarding record in Link.' );
onb02_assert( false !== strpos( $schema, 'faluss_link_cards' ) && false !== strpos( $schema, 'faluss_link_blocks' ), 'Existing Link card preferences and ordered blocks remain the only Link stores.' );
onb02_assert( false !== strpos( $link, "'https://www.instagram.com/'" ) && false !== strpos( $link, 'URL HTTPS' ) && false !== strpos( $link, 'socials( array( array( \'network\'' ), 'Social identifiers resolve only through recognised HTTPS network URLs.' );

foreach ( array( 'WeakSet', 'FormData', 'faluss_link_onboarding_save', 'faluss_link_onboarding_finish', 'faluss_link_onboarding_upload_avatar', 'credentials: \'same-origin\'', 'data-onboarding-back', 'data-onboarding-skip', 'replacePreview' ) as $needle ) {
    onb02_assert( false !== strpos( $script, $needle ), 'The mobile wizard needs the scoped, idempotent interaction contract: ' . $needle );
}
onb02_assert( false === strpos( $script, 'localStorage' ) && false === strpos( $script, 'sessionStorage' ), 'The client does not keep resumable onboarding state locally.' );
onb02_assert( false === strpos( $link, '<ol class="faluss-link-onboarding__progress"' ) && false === strpos( $link, 'data-onboarding-progress="' ), 'The vertical/listing step summary must be absent from markup, not visually hidden.' );
foreach ( array( 'role="progressbar"', 'aria-valuenow=', 'data-onboarding-progress-bar', 'data-onboarding-progress-text', 'data-onboarding-panels', 'role="group"', 'aria-hidden="', ' hidden inert', 'data-current-step=', '--flo-progress-scale:' ) as $needle ) {
    onb02_assert( false !== strpos( $link, $needle ), 'The server-rendered wizard must expose one resumed, accessible step and real progress: ' . $needle );
}
onb02_assert( 7 === substr_count( $link, 'data-onboarding-panel="' ), 'The seven product decisions remain distinct panels.' );
onb02_assert( 7 === substr_count( $link, 'data-onboarding-panel="' ) && 7 <= substr_count( $link, ' hidden inert' ) && 7 === substr_count( $link, 'aria-hidden="<?php echo' ), 'Every non-current server wizard panel is removed from focus and the accessibility tree; nested Figma controls may add their own inert panel.' );
onb02_assert( false !== strpos( $link, '$step = self::onboarding_step( $context[\'step\'] ?? \'\' )' ) && false !== strpos( $link, '$step_index = array_search( $step, $step_keys, true )' ), 'The visible panel and gauge must come from the persisted server step.' );
foreach ( array( 'setPanelAvailability', 'target.hidden = !active', "removeAttribute('inert')", "setAttribute('inert', '')", "setAttribute('aria-hidden'", 'window.requestAnimationFrame', "focus({ preventScroll: true })", "form.addEventListener('submit'", "saveCurrent(root, 'backward')", "direction: direction === 'backward'", 'replacePreview' ) as $needle ) {
    onb02_assert( false !== strpos( $script, $needle ), 'Dynamic transitions must save first and keep only one panel focusable: ' . $needle );
}
foreach ( array( '$target_step', 'onboarding_previous_step', "'backward' === \$direction", 'advance_card_wizard( $target_step )', "'step' => \$target_step" ) as $needle ) {
    onb02_assert( false !== strpos( $link, $needle ), 'Forward, skip and back navigation must persist the exact resumable server step: ' . $needle );
}
onb02_assert( false === strpos( $script, 'document.body.style.overflow' ) && false === strpos( $script, 'touchmove' ), 'The wizard must not lock global scrolling or Safari touch navigation.' );
foreach ( array( '#ed4343', '#f4f4f4', '#1e1e1e', 'Outfit', 'prefers-reduced-motion', '.faluss-link-onboarding', 'transform .23s ease', 'opacity .23s ease', 'scaleX(var(--flo-progress-scale))', '.faluss-link-onboarding__panel[hidden]', 'height: 100dvh' ) as $needle ) {
    onb02_assert( false !== strpos( $css, $needle ), 'The wizard must remain scoped to the Faluss UI foundation: ' . $needle );
}
onb02_assert( false !== strpos( $css, 'grid-template-rows: auto minmax(0, 1fr) auto' ) && false !== strpos( $css, 'overflow-y: auto' ), 'The active decision owns the useful viewport instead of creating a document-sized stack.' );

// ONB-01 remains a choice/handle flow: business returns are still typed and
// no-card never enters the wizard.
foreach ( array( "'unlock_teaser'", "'claim_reward'", "'no_card'", "'create_card'" ) as $needle ) {
    onb02_assert( false !== strpos( $identity, $needle ), 'ONB-01 business and no-card paths remain intact: ' . $needle );
}
onb02_assert( false !== strpos( $onb01, 'faluss_identity_onboarding_reserve_slug' ), 'The existing ONB-01 handle reservation remains its own scoped interaction.' );

echo 'ONB-02 canonical creation-wizard contract: OK' . PHP_EOL;
