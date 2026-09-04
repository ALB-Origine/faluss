<?php
function fl02_assert($value,$message){if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$source=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');
$widgets=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-widgets.php');
$css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');
$js=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/js/faluss-link-editor.js');
$identity=file_get_contents(dirname(__DIR__).'/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php');
$identity_css=file_get_contents(dirname(__DIR__).'/plugins/faluss-identity/assets/css/faluss-identity-public-profile.css');
foreach(array('faluss_link_studio','render_studio','studio_profile','save_studio_profile','faluss_link_save_studio','save_studio','card_markup','faluss-link-card__handle','faluss-link-studio__preview','social_links') as $needle)fl02_assert(false!==strpos($source,$needle),'Missing unified Studio invariant: '.$needle);
foreach(array('role="tablist"','role="tabpanel"','aria-controls','aria-labelledby','aria-live') as $needle)fl02_assert(false!==strpos($source,$needle),'Missing accessible Studio semantics: '.$needle);
foreach(array('data-fl-tab','ArrowLeft','ArrowRight','FileReader','social_layout','announcement_variant','avatar_visible','cover-no','cover-yes') as $needle)fl02_assert(false!==strpos($js,$needle),'Missing live Studio preview behavior: '.$needle);
foreach(array('function icon','<svg aria-hidden="true"','network_label', 'rel="noopener noreferrer nofollow"', 'faluss-link-card__links') as $needle)fl02_assert(false!==strpos($source,$needle),'Missing safe public social or link rendering: '.$needle);
foreach(array('svg(network)','aria-label', 'faluss-link-card--avatar-yes', 'faluss-link-card--avatar-no', 'function updateLinks', "rel: 'noopener noreferrer nofollow'") as $needle)fl02_assert(false!==strpos($js,$needle),'Missing complete live-preview synchronization: '.$needle);
foreach(array('--faluss-action','--faluss-action-hover','--faluss-action-active','focus-visible','prefers-reduced-motion','linear-gradient','faluss-link-studio__tabs') as $needle)fl02_assert(false!==strpos($css,$needle),'Missing Faluss UI interaction token: '.$needle);
foreach(array('Faluss_Link_Card_Widget','Faluss_Link_Appearance_Widget','Faluss_Link_Studio_Widget','add_responsive_control','selectors_dictionary') as $needle)fl02_assert(false!==strpos($widgets,$needle),'Missing Elementor or historical widget invariant: '.$needle);
foreach(array('studio_profile','save_studio_profile') as $needle)fl02_assert(false!==strpos($identity,$needle),'Missing Identity-owned Studio contract: '.$needle);
fl02_assert(false===strpos($identity_css,'faluss-link-studio'),'Identity stylesheet must not style Studio.');
fl02_assert(false===strpos($source,'user_email')&&false===strpos($source,'subscription')&&false===strpos($source,'passwordless'),'Studio must not duplicate business or authentication data.');
echo "FL-02 contract: OK\n";
