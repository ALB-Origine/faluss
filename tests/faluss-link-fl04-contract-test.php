<?php
function fl04_assert($value,$message){if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$link=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');
$widgets=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-widgets.php');
$immersive=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link-immersive.css');
$css=$immersive.file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');
$profile=file_get_contents(dirname(__DIR__).'/plugins/faluss-identity/includes/class-faluss-identity-public-profile.php');
foreach(array('IMMERSIVE_STYLE','presentation','faluss-link-card--presentation-immersive','render_card','card_markup') as $needle)fl04_assert(false!==strpos($link,$needle),'Missing FL-04 presentation invariant: '.$needle);
foreach(array("'default' => 'immersive'",'Page immersive','Carte compacte','hero_overlay_top','hero_overlay_bottom','primary_text','secondary_text') as $needle)fl04_assert(false!==strpos($widgets,$needle),'Missing Elementor immersive presentation control: '.$needle);
foreach(array('min-height:100svh','max-width:none','border:0','box-shadow:none','object-fit:cover','faluss-link-card--cover-no','faluss-link-card--avatar-yes','faluss-link-card__links','--fl-hero-overlay-top','--fl-action') as $needle)fl04_assert(false!==strpos($css,$needle),'Missing immersive public-page treatment: '.$needle);
foreach(array('render_public_shell','wp_head();','wp_body_open();','wp_footer();','show_admin_bar( false )') as $needle)fl04_assert(false!==strpos($profile,$needle),'Missing public route shell without theme chrome: '.$needle);
fl04_assert(false===strpos($immersive,'.faluss-link-studio'),'The immersive shell must not affect the compact Studio preview.');
fl04_assert(false===strpos($link,'user_email')&&false===strpos($link,'subscription')&&false===strpos($link,'passwordless'),'FL-04 keeps Identity and business data separate.');
echo "FL-04 contract: OK\n";
