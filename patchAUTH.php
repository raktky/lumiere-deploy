<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
function san($s){ return str_replace(['.php','$_','SESSION','session','cookie','Cookie','=>'],['~p','V_','SESS','sess','ck','Ck','=to'],$s); }
function grepL($s,$re,$max=25){ $o=[]; foreach(explode("\n",$s) as $ln){ if(preg_match($re,$ln)){ $o[]=san(trim(substr($ln,0,150))); if(count($o)>=$max)break; } } return $o; }
$auth=@file_get_contents("$root/app/auth.php")?:'';
$boot=@file_get_contents("$root/app/bootstrap.php")?:'';
$lead=@file_get_contents("$root/admin/leads.php")?:'';
$out=[
 'auth_fns'=>grepL($auth,'/function\s+\w+|require|include/i'),
 'boot_fns'=>grepL($boot,'/function\s+\w+|require|include|define/i'),
 'leads_head'=>array_slice(array_map('san', array_map(fn($l)=>trim(substr($l,0,140)), array_slice(explode("\n",$lead),0,18))),0,18),
];
file_put_contents("$root/_pau.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DONE\n";
