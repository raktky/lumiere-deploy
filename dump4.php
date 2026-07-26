<?php
/* dump4: final recon for patch12 quote calculator.
   - full app/admin_ui.php (nav sidebar + header/footer + helpers)
   - api/build.php from byte 3500 (hotels_selected JSON build)
   - schemas + sample rows: tb_vehicles, tb_distances, tb_points, tb_build_hotels
   Deletes prior public dump _d3_kx7.txt. Writes ONE temp txt; patch12 deletes it. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
@unlink("$root/_d3_kx7.txt");
$out="";

/* admin_ui.php full */
$ui="$root/app/admin_ui.php";
if(is_file($ui)){ $out.="===== app/admin_ui.php (".filesize($ui).") =====\n".file_get_contents($ui)."\n\n"; }
else { $out.="admin_ui.php NOT FOUND\n\n"; }

/* api/build.php tail (hotels_selected JSON build) */
$b="$root/api/build.php";
if(is_file($b)){ $c=file_get_contents($b); $out.="===== api/build.php bytes 3500.. (".strlen($c)." total) =====\n".substr($c,3500)."\n\n"; }

/* DB schemas + samples */
require_once "$root/app/config.php";
require_once "$root/app/db.php";
$pdo=db();
foreach(['tb_vehicles','tb_distances','tb_points','tb_build_hotels'] as $t){
  try{
    $out.="=== SCHEMA $t ===\n";
    foreach($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC) as $c){ $out.="  {$c['Field']}  {$c['Type']}\n"; }
    $rows=$pdo->query("SELECT * FROM `$t` LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    $out.="--- up to 6 rows ---\n";
    foreach($rows as $r){ $line=[]; foreach($r as $k=>$v){ $line[]="$k=".substr((string)$v,0,80); } $out.="  ".implode(' | ',$line)."\n"; }
    $out.="\n";
  }catch(Throwable $e){ $out.="  ($t error: ".$e->getMessage().")\n\n"; }
}

/* a trip_request that actually has hotels_selected populated */
try{
  $r=$pdo->query("SELECT id,nights,start_date,end_date,regions,pickup_point,drop_point,hotel_pref,room_category,meal_plan,hotels_selected,day_plan,notes FROM trip_requests WHERE hotels_selected IS NOT NULL AND hotels_selected<>'' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if($r){ $out.="=== sample trip_request WITH hotels_selected (id {$r['id']}) ===\n"; foreach($r as $k=>$v){ $out.="  $k = ".substr((string)$v,0,1500)."\n"; } }
  else { $out.="=== no trip_request has hotels_selected yet ===\n"; }
}catch(Throwable $e){ $out.="trip_request sample error: ".$e->getMessage()."\n"; }

file_put_contents("$root/_d4_kx7.txt",$out);
echo "dump4 written: ".strlen($out)." bytes\nDONE\n";
