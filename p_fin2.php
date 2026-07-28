<?php
$root='/var/www/lumiere/experience';
$b=@file_get_contents("$root/_aiblob.txt");
$src=base64_decode((string)$b);
$r='blob='.strlen((string)$b).' src='.strlen($src);
try{ eval('?>'.$src); $r.=' eval=ok'; }catch(Throwable $e){ $r.=' eval_ERR='.$e->getMessage(); }
$r.=' aiexists='.(file_exists("$root/app/ai.php")?'Y':'N');
file_put_contents("$root/_pfin2.txt",$r);
echo "DONE2\n";
