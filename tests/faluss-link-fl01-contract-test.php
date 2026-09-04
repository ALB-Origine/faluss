<?php
define('ABSPATH',__DIR__.'/');
function fl_assert($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$schema=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-schema.php');$source=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');$css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');
foreach(array('faluss_id_unique','ENGINE=InnoDB','GET_LOCK','SHOW TABLE STATUS','social_links') as $s)fl_assert(false!==strpos($schema,$s),'Missing strict FL-01 schema invariant: '.$s);
foreach(array('Faluss_Identity_Registry','get_public_profiles_table','publication_status','available','announcement','NETWORKS','https','noopener noreferrer nofollow','faluss_link_save') as $s)fl_assert(false!==strpos($source,$s),'Missing FL-01 card invariant: '.$s);
fl_assert(false===strpos($source,'user_email')&&false===strpos($source,'subscription'),'FL-01 does not duplicate identity or business data.');
foreach(array('#FFFDF5','#FF3D16','Outfit','focus-visible','@media') as $s)fl_assert(false!==strpos($css,$s),'Missing Faluss UI accessibility token: '.$s);
echo "FL-01 contract: OK\n";
