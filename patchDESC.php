<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$tabs=['tb_packages','tb_pkg_templates','tb_quotes','tb_quote_versions','tb_build_hotels','tb_hotels','tb_hotel_rates','tb_hotel_seasons','tb_distances','tb_points','tb_vehicles','tb_vehicle_rates','destinations','journeys'];
$out=[];
foreach($tabs as $t){
  try{
    $cols=db()->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
    $c=db()->query("SELECT COUNT(*) n FROM `$t`")->fetch(PDO::FETCH_ASSOC)['n'];
    $out[$t]=['count'=>$c,'cols'=>array_map(fn($x)=>$x['Field'].' '.$x['Type'],$cols)];
  }catch(Throwable $e){$out[$t]=['err'=>$e->getMessage()];}
}
file_put_contents("$root/_pdesc.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DONE\n";
