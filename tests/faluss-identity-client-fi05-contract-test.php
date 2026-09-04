<?php
define('ABSPATH',__DIR__.'/');
function wp_parse_url($u){return parse_url($u);}
require_once dirname(__DIR__).'/plugins/faluss-identity-client/includes/class-faluss-identity-client-schema.php';
require_once dirname(__DIR__).'/plugins/faluss-identity-client/includes/class-faluss-identity-client.php';
function fi05_assert($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$s=Faluss_Identity_Client_Schema::schema();
fi05_assert(isset($s['links']['indexes'][1],$s['states']['columns']['state_hash'],$s['states']['columns']['consumed_at']),'Links and one-use state tables exist.');
$source=file_get_contents(dirname(__DIR__).'/plugins/faluss-identity-client/includes/class-faluss-identity-client.php');
foreach(array('START TRANSACTION','FOR UPDATE','consumed_at IS NULL','code_challenge_method','S256','code_verifier','wp_remote_post','FALUSS_IDENTITY_CLIENT_SECRET',"'subscriber'",'flow_mode','link_required','enabled') as $need){fi05_assert(false!==strpos($source,$need),'Missing FI-05 invariant: '.$need);}
fi05_assert(false===strpos($source,'get_user_by( \'email\', $email )'),'No automatic e-mail linkage primitive is present.');
fi05_assert(false===strpos($source,'access_token'),'The client does not persist bearer tokens.');
echo "FI-05 client contract: OK\n";
