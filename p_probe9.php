<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/ai.php";
$o=['provider'=>ai_provider(),'model'=>ai_model(),'key_set'=>ai_key()!=='','key_len'=>strlen(ai_key())];
try{ $r=ai_call('You are a connectivity test.','Reply with exactly: OK',10); $o['call']=substr(trim($r),0,60); $o['ok']=true; }
catch(Throwable $e){ $o['ok']=false; $o['err']=substr($e->getMessage(),0,200); }
file_put_contents("$root/_probe.txt",json_encode($o));
echo 'PROBE';
