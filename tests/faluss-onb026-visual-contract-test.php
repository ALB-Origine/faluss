<?php

/** ONB-02.6 shared-card visual finishing contract. */

function onb026_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$root = dirname( __DIR__ );
$link = file_get_contents( $root . '/plugins/faluss-link/includes/class-faluss-link.php' );
$bootstrap = file_get_contents( $root . '/plugins/faluss-link/faluss-link.php' );
$card_css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link.css' );
$onboarding_css = file_get_contents( $root . '/plugins/faluss-link/assets/css/faluss-link-onboarding.css' );
$onboarding_js = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-onboarding.js' );
$studio_js = file_get_contents( $root . '/plugins/faluss-link/assets/js/faluss-link-editor.js' );
$onboarding_doc = file_get_contents( $root . '/docs/FALUSS_ONBOARDING.md' );

onb026_assert( false !== strpos( $bootstrap, 'Version: 0.3.17' ) && false !== strpos( $bootstrap, "FALUSS_LINK_VERSION','0.3.17'" ), 'The current CSS and JavaScript assets need plugin version 0.3.17.' );

foreach ( array( '.faluss-link-card--density-compact .faluss-link-card__preview-links', 'grid-template-columns:minmax(0,1fr)', 'width:100%', 'min-width:0', 'justify-self:stretch' ) as $needle ) {
    onb026_assert( false !== strpos( $card_css, $needle ), 'Compact real and skeleton links must retain the full card column: ' . $needle );
}
onb026_assert( false === strpos( $onboarding_css, 'transform:scale(' ), 'Compact geometry cannot be repaired by scaling a static card.' );

foreach ( array( 'private static function name_treatment_presentation(', "'weight' => 500", "'weight' => 800", "'tracking' => '-.065em'", "'tracking' => '-.045em'", '--fl-name-weight:%d', '--fl-name-tracking:%s', "'name_treatment' => \$name_treatment" ) as $needle ) {
    onb026_assert( false !== strpos( $link, $needle ), 'The shared presentation must resolve two genuinely distinct name treatments: ' . $needle );
}
foreach ( array( 'data-name-weight=', 'data-name-tracking=' ) as $needle ) {
    onb026_assert( substr_count( $link, $needle ) >= 1, 'Server-owned name metrics must be available to each live preview: ' . $needle );
}
foreach ( array( "card.style.setProperty('--fl-name-weight'", "card.style.setProperty('--fl-name-tracking'" ) as $needle ) {
    onb026_assert( false !== strpos( $onboarding_js, $needle ), 'Onboarding must apply name treatment without waiting for navigation: ' . $needle );
}
foreach ( array( "style.setProperty('--fl-name-weight'", "style.setProperty('--fl-name-tracking'" ) as $needle ) {
    onb026_assert( false !== strpos( $studio_js, $needle ), 'Studio must use the same server-provided name metrics: ' . $needle );
}

foreach ( array( 'overflow:hidden;border:0;border-radius:50%;clip-path:circle(50% at 50% 50%)', 'position:absolute;inset:0', 'width:100%;height:100%', 'object-fit:cover', 'object-position:center', 'faluss-link-card--avatar-border-yes', 'faluss-link-card--avatar-border-no' ) as $needle ) {
    onb026_assert( false !== strpos( $card_css, $needle ), 'Avatar crop and border must remain identical in compact and final renderers: ' . $needle );
}
onb026_assert( false !== strpos( $card_css, '.faluss-link-card--density-compact .faluss-link-card__avatar{--fl-avatar-ring-width:2px}' ), 'The compact avatar may reduce ring density without reducing its image.' );

foreach ( array( 'body.faluss-link-onboarding-route {', 'background: #f4f4f4', 'data-faluss-onboarding-layout', 'html:has(> body.faluss-link-onboarding-route .faluss-link-onboarding[data-onboarding-layout=upload])', 'html:has(> body.faluss-link-onboarding-route .faluss-link-onboarding[data-onboarding-layout=list])', 'background: #fff' ) as $needle ) {
    onb026_assert( false !== strpos( $onboarding_css . $onboarding_js, $needle ), 'Preview stage and no-preview surfaces need distinct route backgrounds: ' . $needle );
}

foreach ( array( 'data-onboarding-panel=header', 'grid-template-rows: auto auto minmax(0, 1fr)', 'gap: clamp(', 'min-height: clamp(40px', 'grid-row: 3' ) as $needle ) {
    onb026_assert( false !== strpos( $onboarding_css, $needle ), 'The header panel must reserve responsive space above its action row: ' . $needle );
}
onb026_assert( 1 === substr_count( $onboarding_css, 'overflow-y: auto' ), 'Short preview panels must not acquire a nested scroller.' );

onb026_assert( 2 === substr_count( $link, 'faluss-link-onboarding__choice-indicator' ), 'Both segmented controls need one real moving indicator.' );
foreach ( array( '--flo-choice-shift', 'data-active-index="1"', 'translateX(var(--flo-choice-shift))', 'transition: transform .2s ease, opacity .18s ease', '.faluss-link-onboarding__choice-indicator,' ) as $needle ) {
    onb026_assert( false !== strpos( $onboarding_css, $needle ), 'The switcher indicator must slide and respect reduced motion: ' . $needle );
}
onb026_assert( false !== strpos( $onboarding_js, "parentElement.dataset.activeIndex = String(selectedIndex)" ), 'Keyboard and pointer selection must drive the same indicator state.' );
onb026_assert( false !== strpos( $onboarding_css, '.faluss-link-onboarding__symbol' ) && substr_count( $onboarding_css, 'clip-path: circle(50% at 50% 50%)' ) >= 2 && false !== strpos( $onboarding_css, 'aspect-ratio: 1' ), 'The Faluss symbol and asset must remain circular.' );

foreach ( array( 'ONB-02.6', 'largeur pleine', 'Fort et Éditorial', 'safe area haute', 'switchers' ) as $needle ) {
    onb026_assert( false !== strpos( $onboarding_doc, $needle ), 'The visual finishing boundary must be documented: ' . $needle );
}

echo 'ONB-02.6 visual finishing contract: OK' . PHP_EOL;
