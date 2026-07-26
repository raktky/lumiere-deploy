<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$out=[];
foreach(['trip_requests','leads'] as $tb){
  try{ $c=db()->query("SHOW COLUMNS FROM $tb")->fetchAll(PDO::FETCH_ASSOC);
    $cols=array_map(function($x){ return $x['Field'].':'.$x['Type'].':'.($x['Null']==='NO'?'NN':'nl').':'.($x['Default']===null?'nodef':'def').($x['Extra']?':'.$x['Extra']:''); },$c);
    $out[$tb]=base64_encode(implode("\n",$cols));
  }catch(Throwable $e){ $out[$tb]='ERR'; }
}
file_put_contents("$root/_enq.txt", json_encode($out));
echo "DONE\n";
