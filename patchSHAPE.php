<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
function shape($v,$d=0){
  if($d>3) return '...';
  if(is_array($v)){
    $isList = array_keys($v)===range(0,count($v)-1);
    if($isList){ return count($v)? '['.shape($v[0],$d+1).' x'.count($v).']' : '[]'; }
    $o=[]; foreach($v as $k=>$vv){ $o[]=$k.':'.shape($vv,$d+1); } return '{'.implode(', ',$o).'}';
  }
  if(is_int($v)||is_float($v)) return 'num';
  if(is_bool($v)) return 'bool';
  if($v===null) return 'null';
  return 'str';
}
$out=[];
try{ $r=db()->query("SELECT itinerary FROM tb_pkg_templates WHERE itinerary IS NOT NULL AND itinerary<>'' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC); $j=json_decode($r['itinerary']??'',true); $out['template_itinerary_shape']=$j!==null?shape($j):'RAW:'.substr($r['itinerary']??'',0,120); }catch(Throwable $e){$out['t_err']=$e->getMessage();}
try{ $r=db()->query("SELECT breakdown,itinerary FROM tb_quotes WHERE breakdown IS NOT NULL ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC); $b=json_decode($r['breakdown']??'',true); $out['quote_breakdown_shape']=$b!==null?shape($b):'RAW:'.substr($r['breakdown']??'',0,120); $qi=json_decode($r['itinerary']??'',true); $out['quote_itinerary_shape']=$qi!==null?shape($qi):'RAW:'.substr($r['itinerary']??'',0,120); }catch(Throwable $e){$out['q_err']=$e->getMessage();}
// sample hotel + distance rows for AI context format
try{ $out['hotel_sample']=db()->query("SELECT location,name,star,room_categories,price_range FROM tb_build_hotels WHERE active=1 LIMIT 3")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
try{ $out['distance_sample']=db()->query("SELECT from_loc,to_loc,km FROM tb_distances LIMIT 3")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
try{ $out['vehicle_sample']=db()->query("SELECT category,model FROM tb_vehicles WHERE active=1 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
file_put_contents("$root/_psh.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DONE\n";
