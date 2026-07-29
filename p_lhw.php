<?php
$root='/var/www/lumiere/experience';
$h=trim((string)@file_get_contents("$root/_ldhex.txt"));
$o=['hexlen'=>strlen($h)];
$bin=@gzuncompress(@hex2bin($h));
if($bin===false||$bin===null){ $o['err']='decode_fail'; file_put_contents("$root/_ldw.txt",json_encode($o)); echo 'ERR'; return; }
$o['bytes']=strlen($bin); $o['md5']=md5($bin);
if(strlen($bin)<8000 || strpos($bin,'lead_is_spam')===false){ $o['err']='guard_fail'; file_put_contents("$root/_ldw.txt",json_encode($o)); echo 'GUARD'; return; }
$t="$root/admin/leads.php";
if(is_file($t)){ @copy($t,"$t.bak"); $o['backup']=true; }
file_put_contents($t,$bin);
$o['ok']=true;
file_put_contents("$root/_ldw.txt",json_encode($o));
echo 'OK';
