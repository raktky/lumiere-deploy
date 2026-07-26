<?php
/* dump5: replay quote.php DB logic for trip_request 26 to surface the runtime error. Writes txt; deleted by next patch. */
error_reporting(E_ALL); ini_set('display_errors','1');
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php";
require_once "$root/app/db.php";
$out="";
function P(&$out,$s){ $out.=$s."\n"; }
try{
  $pdo=db();
  $id=26;
  $s=$pdo->prepare('SELECT * FROM trip_requests WHERE id = ?'); $s->execute([$id]); $tr=$s->fetch();
  P($out,"tr loaded: ".($tr?'yes':'no'));
  $travelDate=(string)($tr['start_date'] ?: date('Y-m-d'));
  P($out,"travelDate=$travelDate nights=".$tr['nights']);

  P($out,"-- distances --");
  $DIST=[]; foreach($pdo->query('SELECT from_loc, to_loc, km FROM tb_distances') as $d){ $DIST[strtolower($d['from_loc']).'|'.strtolower($d['to_loc'])]=(int)$d['km']; }
  P($out,"dist rows=".count($DIST));

  P($out,"-- hotels json --");
  $hotels=json_decode((string)$tr['hotels_selected'],true); if(!is_array($hotels))$hotels=[];
  P($out,"hotels=".count($hotels));

  P($out,"-- hotel rate prepare --");
  $hrateStmt=$pdo->prepare("SELECT cost_rate, sell_rate FROM tb_hotel_rates WHERE active = 1 AND hotel = ? AND (period_from IS NULL OR period_from = '' OR period_from <= ?) AND (period_to IS NULL OR period_to = '' OR period_to >= ?) ORDER BY (room = ?) DESC, (meal = ?) DESC, id DESC LIMIT 1");
  P($out,"hotel prepare ok");
  foreach($hotels as $i=>$h){ $hrateStmt->execute([$h['name']??'',$travelDate,$travelDate,$h['room']??'',$h['meal']??'']); P($out,"hotel[$i] exec ok"); }

  P($out,"-- vehicles --");
  $vehicles=$pdo->query("SELECT model FROM tb_vehicles WHERE active = 1 ORDER BY category, sort, model")->fetchAll(PDO::FETCH_COLUMN);
  P($out,"vehicles=".count($vehicles)." -> ".implode(', ',$vehicles));
  $vrateStmt=$pdo->prepare("SELECT base_km, base_amount, extra_per_km FROM tb_vehicle_rates WHERE active = 1 AND vehicle_model = ? AND (period_from IS NULL OR period_from = '' OR period_from <= ?) AND (period_to IS NULL OR period_to = '' OR period_to >= ?) ORDER BY id DESC LIMIT 1");
  P($out,"vehicle prepare ok");
  foreach($vehicles as $vm){ $vrateStmt->execute([$vm,$travelDate,$travelDate]); $r=$vrateStmt->fetch(); P($out,"veh $vm exec ok rate=".($r?json_encode($r):'none')); }

  P($out,"-- existing quote --");
  $s=$pdo->prepare('SELECT * FROM tb_quotes WHERE trip_request_id = ?'); $s->execute([$id]); $q=$s->fetch();
  P($out,"quote row: ".($q?'yes':'no'));
  P($out,"ALL OK");
}catch(Throwable $e){
  P($out,"EXCEPTION: ".get_class($e).": ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine());
}
file_put_contents("$root/_d5_kx7.txt",$out);
echo "dump5 done\n".$out;
