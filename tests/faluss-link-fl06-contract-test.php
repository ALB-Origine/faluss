<?php
function fl06_assert($value,$message){if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$link=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');
$widgets=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-widgets.php');
$immersive=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link-immersive.css');
$card_css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');
$studio=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link-studio.css');
$script=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/js/faluss-link-immersive.js');
$identity=file_get_contents(dirname(__DIR__).'/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php');
$identity_css=file_get_contents(dirname(__DIR__).'/plugins/faluss-identity/assets/css/faluss-identity-public-profile.css');
foreach(array('faluss-identity-public-shell','faluss-identity-profile-page--elementor','min-height: 100svh','margin: 0','padding: 0') as $needle)fl06_assert(false!==strpos($identity_css,$needle),'Public route shell still permits whitespace before the hero: '.$needle);
foreach(array('render_public_shell','show_admin_bar( false )','render_elementor_template') as $needle)fl06_assert(false!==strpos($identity,$needle),'Public route shell invariant regressed: '.$needle);
foreach(array('page_background','hero_transition_color','hero_transition_position','hero_transition_intensity','--fl-page-background','--fl-hero-transition-color') as $needle)fl06_assert(false!==strpos($link.$widgets,$needle),'Missing persisted or Elementor-overridable immersive color control: '.$needle);
foreach(array('--fl-page','--fl-hero-transition-color','--fl-hero-transition-position','--fl-hero-transition-intensity','background: var(--fl-page)','opacity: 1') as $needle)fl06_assert(false!==strpos($immersive.$studio,$needle),'Hero transition does not apply the selected color as its final surface: '.$needle);
foreach(array('faluss-link-card--align-center .faluss-link-card__avatar','faluss-link-card--align-center .faluss-link-card__availability','faluss-link-card--align-center .faluss-link-card__social','faluss-link-card--align-center .faluss-link-card__links') as $needle)fl06_assert(false!==strpos($card_css.$immersive.$studio,$needle),'Center alignment is incomplete: '.$needle);
foreach(array('position: relative','z-index: 4','visibility: visible','opacity: 1','width: 44px','height: 44px','@media (max-width: 799px)') as $needle)fl06_assert(false!==strpos($immersive,$needle),'A valid social network can disappear or lacks a mobile target: '.$needle);
foreach(array('IMMERSIVE_SCRIPT','faluss-link-immersive','wp_enqueue_script','faluss-link-card--presentation-immersive') as $needle)fl06_assert(false!==strpos($link,$needle),'Depth code is not limited to Page immersive: '.$needle);
foreach(array('addEventListener(\'scroll\', requestUpdate, { passive: true })','requestAnimationFrame','prefers-reduced-motion: reduce','falussLinkImmersiveReady','--fl-immersive-depth') as $needle)fl06_assert(false!==strpos($script.$immersive,$needle),'Depth effect is not passive, bounded, or motion-safe: '.$needle);
fl06_assert(false===strpos($link,'user_email')&&false===strpos($link,'passwordless')&&false===strpos($link,'subscription'),'FL-06 must not alter identity, SSO, or business data.');
echo "FL-06 contract: OK\n";
