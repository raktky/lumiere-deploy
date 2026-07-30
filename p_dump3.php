<?php
$root='/var/www/lumiere/experience';
function d($root,$rel,$tag){ $f="$root/$rel"; $s=is_file($f)?file_get_contents($f):"MISSING:$rel"; file_put_contents("$root/_d_$tag.txt",$s); }
d($root,'admin/quote-pdf.php','pdf');
d($root,'admin/package.php','pkg');
d($root,'app/rbac.php','rbac');
d($root,'q.php','q');
// schema
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $out=[];
foreach(['trip_requests','tb_quotes','tb_points','tb_hotel_rates','tb_vehicle_rates','tb_vehicles','tb_pkg_templates','tb_build_hotels'] as $t){
  try{ $c=$pdo->query("SHOW COLUMNS FROM $t")->fetchAll(PDO::FETCH_ASSOC); $out[$t]=array_map(fn($r)=>$r['Field'].' '.$r['Type'],$c); }
  catch(Throwable $e){ $out[$t]='ERR:'.$e->getMessage(); }
}
// sample counts + a couple tb_points rows to see name values
try{ $out['_points_sample']=$pdo->query("SELECT id,name,image,LEFT(blurb,30) b FROM tb_points ORDER BY name LIMIT 40")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ $out['_ps']=$e->getMessage(); }
file_put_contents("$root/_d_schema.txt",json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo 'D3';
