<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$files=['api/build.php'=>'build','admin/quote.php'=>'quote','app/config.php'=>'config','app/db.php'=>'db','app/auth.php'=>'auth'];
$out=[];
foreach($files as $rel=>$k){
  $p="$root/$rel"; $c=@file_get_contents($p);
  $out[$k]=['size'=>$c!==false?strlen($c):-1];
}
// dump build.php + quote.php full (base64) for offline read
$out['build_b64']=base64_encode(@file_get_contents("$root/api/build.php")?:'');
$out['quote_b64']=base64_encode(@file_get_contents("$root/admin/quote.php")?:'');
file_put_contents("$root/_psrc.txt", json_encode($out));
echo "DONE\n";
