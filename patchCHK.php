<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$out=[];
try{
  $st=db()->query("SHOW TABLES");
  $tabs=$st->fetchAll(PDO::FETCH_COLUMN);
  $out['tables']=$tabs;
}catch(Throwable $e){$out['tables_err']=$e->getMessage();}
foreach(['trip_request','leads','tb_lead','enquiry','tb_enquiry'] as $t){
  try{
    $r=db()->query("SELECT * FROM `$t` ORDER BY 1 DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    $out['sample_'.$t]=$r;
  }catch(Throwable $e){}
}
file_put_contents("$root/_pchk.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DONE\n";
