<?php
function fl03_assert($value,$message){if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$source=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');
$widgets=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-widgets.php');
$css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');
$js=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/js/faluss-link-editor.js');
foreach(array('elementor/frontend/after_register_scripts','elementor/frontend/after_register_styles',"add_action('wp_enqueue_scripts'",'wp_register_style','wp_register_script') as $needle)fl03_assert(false!==strpos($source,$needle),'Missing early Elementor asset registration: '.$needle);
foreach(array('Faluss_Link_Card_Widget','Faluss_Link_Appearance_Widget','Faluss_Link_Studio_Widget','get_style_depends','get_script_depends','faluss-link-editor') as $needle)fl03_assert(false!==strpos($widgets,$needle),'Missing explicit Elementor dependency: '.$needle);
foreach(array('falussLinkStudioReady','function initialize','ensureStudioMarkup','frontend/element_ready/faluss_link_studio.default','elementor/frontend/init') as $needle)fl03_assert(false!==strpos($js,$needle),'Studio initialization is not idempotent across Elementor instances: '.$needle);
foreach(array("['profile', 'links', 'style']","tab.text('Style')",'aria-selected','aria-controls','aria-labelledby','ArrowLeft','ArrowRight','Home','End') as $needle)fl03_assert(false!==strpos($js,$needle),'Missing accessible Profil/Liens/Style switcher behavior: '.$needle);
foreach(array('--faluss-link-tab-left','--faluss-link-tab-width','is-entering','position:sticky','prefers-reduced-motion') as $needle)fl03_assert(false!==strpos($css,$needle),'Missing Studio cursor, motion, or mobile switcher treatment: '.$needle);
foreach(array('height:clamp(280px,64vw,420px)','linear-gradient(180deg','faluss-link-card--cover-yes .faluss-link-card__body','faluss-link-card--cover-no .faluss-link-card__body','cover-yes.faluss-link-card--avatar-yes','cover-no.faluss-link-card--avatar-no') as $needle)fl03_assert(false!==strpos($css,$needle),'Missing immersive cover/avatar composition: '.$needle);
fl03_assert(false===strpos($source,'public-tabs'),'No fictitious public navigation is rendered before a real tab exists.');
foreach(array('faluss-link-card__handle','<svg aria-hidden="true"','rel="noopener noreferrer nofollow"','esc_url','esc_html') as $needle)fl03_assert(false!==strpos($source,$needle),'Public identity, social, or safe-link contract regressed: '.$needle);
foreach(array('--faluss-action','--faluss-action-hover','--faluss-action-active') as $needle)fl03_assert(false!==strpos($css,$needle),'Card actions must consume Faluss Theme tokens: '.$needle);
fl03_assert(false===strpos($source,'user_email')&&false===strpos($source,'passwordless')&&false===strpos($source,'subscription'),'FL-03 keeps Identity and business data separate.');
echo "FL-03 contract: OK\n";
