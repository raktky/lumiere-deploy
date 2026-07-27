<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$out=[];
foreach(['trip_requests','enquiries'] as $t){
  try{
    $r=db()->query("SELECT * FROM `$t` ORDER BY 1 DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    $out[$t]=$r;
  }catch(Throwable $e){$out[$t.'_err']=$e->getMessage();}
}
file_put_contents("$root/_pchk2.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DONE\n";
