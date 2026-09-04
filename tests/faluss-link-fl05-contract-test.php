<?php
function fl05_assert($value,$message){if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$link=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');
$admin=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-admin.php');
$widgets=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-widgets.php');
$css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link-studio.css').file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');
$js=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/js/faluss-link-editor.js');
$schema=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-schema.php');
foreach(array('data-fl-tab="profile"','data-fl-tab="links"','data-fl-tab="style"','data-fl-panel="profile"','data-fl-panel="links"','data-fl-panel="style"','role="tablist"','role="tabpanel"','aria-controls','aria-labelledby') as $needle)fl05_assert(false!==strpos($link,$needle),'Missing exclusive accessible Studio panel: '.$needle);
foreach(array('data-fl-panel][hidden','display:none!important','is-entering','faluss-link-tab-left','prefers-reduced-motion') as $needle)fl05_assert(false!==strpos($css,$needle),'Missing robust Studio panel visibility or motion: '.$needle);
foreach(array('function activate','prop(\'hidden\', true)','function update','updateSocials','updateLinks','falussLinkStudioReady','hero_transition_color','avatar-yes','avatar-no') as $needle)fl05_assert(false!==strpos($js,$needle),'Missing live Studio state synchronization: '.$needle);
foreach(array('social_networks','faluss-link-studio__add-link','faluss-link-studio__add-network','faluss-link-studio__network-row','https') as $needle)fl05_assert(false!==strpos($link,$needle),'Missing member-friendly links or social editor: '.$needle);
fl05_assert(false===strpos($link,'réseau|URL')&&false===strpos($link,'un par ligne'),'No technical pipe syntax remains in the member editor.');
foreach(array('active_catalog','wp_enqueue_media','wp_attachment_is_image','manage_options','label','active','icon') as $needle)fl05_assert(false!==strpos($admin,$needle),'Missing constrained network catalog administration: '.$needle);
foreach(array('hero_transition_color','hero_transition_intensity','hero_transition_position','faluss-link-card--align-center','faluss-link-card__availability','faluss-link-card__network-icon') as $needle)fl05_assert(false!==strpos($link.$css,$needle),'Missing immersive card visual control: '.$needle);
foreach(array('get_style_depends','faluss-link-studio','get_script_depends') as $needle)fl05_assert(false!==strpos($widgets,$needle),'Missing Elementor Studio dependencies: '.$needle);
foreach(array('faluss_id_unique','cover_attachment_id','social_links') as $needle)fl05_assert(false!==strpos($schema,$needle),'Existing single FL schema invariant regressed: '.$needle);
fl05_assert(false===strpos($link,'user_email')&&false===strpos($link,'passwordless')&&false===strpos($link,'subscription'),'No identity, SSO, or business data is duplicated.');
echo "FL-05 contract: OK\n";
