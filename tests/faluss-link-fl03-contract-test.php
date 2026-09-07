<?php
function fl03_assert($value,$message){if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$source=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');
$widgets=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-widgets.php');
$css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');
$js=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/js/faluss-link-editor.js');
foreach(array('elementor/frontend/after_register_scripts','elementor/frontend/after_register_styles','wp_enqueue_scripts','wp_register_style','wp_register_script') as $needle)fl03_assert(false!==strpos($source,$needle),'Missing early Elementor asset registration: '.$needle);
foreach(array('Faluss_Link_Card_Widget','Faluss_Link_Appearance_Widget','Faluss_Link_Studio_Widget','get_style_depends','get_script_depends','faluss-link-editor') as $needle)fl03_assert(false!==strpos($widgets,$needle),'Missing explicit Elementor dependency: '.$needle);
foreach(array('falussLinkStudioReady','function initialize','frontend/element_ready/faluss_link_studio.default','elementor/frontend/init') as $needle)fl03_assert(false!==strpos($js,$needle),'Studio initialization is not idempotent across Elementor instances: '.$needle);
foreach(array('data-fl-tab="links"','data-fl-tab="style"','data-fl-context-tab="all"','data-fl-context-tab="collections"','data-fl-context-tab="appearance"','data-fl-context-tab="header"','data-fl-context-tab="link-style"','aria-selected','aria-controls','ArrowLeft','ArrowRight','Home','End') as $needle)fl03_assert(false!==strpos($js.$source,$needle),'Missing accessible Studio V1 navigation behavior: '.$needle);
$studio_css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link-studio.css');
foreach(array('--fl-dock-left','--fl-context-left','is-entering','position: fixed','prefers-reduced-motion') as $needle)fl03_assert(false!==strpos($studio_css,$needle),'Missing Studio V1 cursor, motion, or fixed dock treatment: '.$needle);
foreach(array('height:clamp(280px,64vw,420px)','linear-gradient(180deg','faluss-link-card--cover-yes .faluss-link-card__body','faluss-link-card--cover-no .faluss-link-card__body','cover-yes.faluss-link-card--avatar-yes','cover-no.faluss-link-card--avatar-no') as $needle)fl03_assert(false!==strpos($css,$needle),'Missing immersive cover/avatar composition: '.$needle);
fl03_assert(false===strpos($source,'public-tabs'),'No fictitious public navigation is rendered before a real tab exists.');
foreach(array('faluss-link-card__handle','social_asset_markup','rel="noopener noreferrer nofollow"','esc_url','esc_html') as $needle)fl03_assert(false!==strpos($source,$needle),'Public identity, social, or safe-link contract regressed: '.$needle);
foreach(array('--faluss-action','--faluss-action-hover','--faluss-action-active') as $needle)fl03_assert(false!==strpos($css,$needle),'Card actions must consume Faluss Theme tokens: '.$needle);
foreach(array('--faluss-card-radius','--faluss-control-radius','--faluss-pill-radius') as $needle)fl03_assert(false!==strpos($css,$needle),'FL-03 must consume Faluss Theme dashed radius aliases: '.$needle);
fl03_assert(false===strpos($source,'user_email')&&false===strpos($source,'passwordless')&&false===strpos($source,'subscription'),'FL-03 keeps Identity and business data separate.');
echo "FL-03 contract: OK\n";
