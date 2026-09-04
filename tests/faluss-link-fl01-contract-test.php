<?php
define('ABSPATH',__DIR__.'/');
function wp_parse_url($url){return parse_url($url);}
function fl_assert($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$schema=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link-schema.php');$source=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php');$css=file_get_contents(dirname(__DIR__).'/plugins/faluss-link/assets/css/faluss-link.css');require_once dirname(__DIR__).'/plugins/faluss-link/includes/class-faluss-link.php';
foreach(array('faluss_id_unique','ENGINE=InnoDB','GET_LOCK','SHOW TABLE STATUS','social_links') as $s)fl_assert(false!==strpos($schema,$s),'Missing strict FL-01 schema invariant: '.$s);
foreach(array('Faluss_Identity_Registry','get_public_profiles_table','publication_status','available','announcement','NETWORKS','https','noopener noreferrer nofollow','faluss_link_save') as $s)fl_assert(false!==strpos($source,$s),'Missing FL-01 card invariant: '.$s);
 $socials=Faluss_Link::socials('[{"network":"instagram","url":"https://instagram.com/faluss"},{"network":"instagram","url":"https://instagram.com/faluss"},{"network":"unknown","url":"https://example.test"}]');fl_assert(1===count($socials)&&'instagram'===$socials[0]['network'],'Saved JSON social links are restored, validated and deduplicated.');
 $typed=Faluss_Link::socials("github|https://github.com/faluss\ngithub|https://github.com/faluss");fl_assert(1===count($typed)&&'github'===$typed[0]['network'],'Editor network input remains accepted and deduplicated.');
fl_assert(false===strpos($source,'user_email')&&false===strpos($source,'subscription'),'FL-01 does not duplicate identity or business data.');
foreach(array('#FFFDF5','#FF3D16','Outfit','focus-visible','@media') as $s)fl_assert(false!==strpos($css,$s),'Missing Faluss UI accessibility token: '.$s);
foreach(array('announcement--ink','social--inline','links-outline','name--editorial','mask-image') as $s)fl_assert(false!==strpos($css,$s),'Missing visible FL-01 variant: '.$s);
foreach(array('cover_attachment_id','ANNOUNCEMENTS','LAYOUTS','LINK_STYLES') as $s)fl_assert(false!==strpos($source,$s),'Missing member card preference: '.$s);
foreach(array('wp_ajax_faluss_link_upload_cover','check_ajax_referer','wp_check_filetype_and_ext','image/','wp_handle_upload','post_author','owned_image') as $s)fl_assert(false!==strpos($source,$s),'Missing restricted cover upload safeguard: '.$s);
fl_assert(false===strpos($source,'add_cap')&&false===strpos($source,'upload_files'),'FL-01 does not elevate WordPress roles or global capabilities.');
foreach(array("\$has_cover?'yes':'no'","\$has_avatar?'yes':'no'",'if($has_cover)','if($has_avatar)') as $s)fl_assert(false!==strpos($source,$s),'Missing card composition branch: '.$s);
foreach(array('cover-yes.faluss-link-card--avatar-yes','cover-yes.faluss-link-card--avatar-no','cover-no.faluss-link-card--avatar-no') as $s)fl_assert(false!==strpos($css,$s),'Missing visible cover/avatar composition style: '.$s);
echo "FL-01 contract: OK\n";
