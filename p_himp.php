<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$out=[];
try{
 $b=(string)@file_get_contents("$root/_hdata.txt");
 $out['blob_len']=strlen($b);
 $tsv=@gzuncompress(base64_decode($b));
 if($tsv===false){ throw new Exception('decode_fail'); }
 $lines=array_values(array_filter(explode("\n",$tsv),fn($l)=>trim($l)!==''));
 $out['rows_parsed']=count($lines);
 if(count($lines)<400){ throw new Exception('too_few_rows_'.count($lines)); }
 $pdo=db();
 $pdo->exec("DROP TABLE IF EXISTS tb_build_hotels_bak");
 $pdo->exec("CREATE TABLE tb_build_hotels_bak AS SELECT * FROM tb_build_hotels");
 $pdo->exec("TRUNCATE tb_build_hotels");
 $pdo->exec("TRUNCATE tb_hotel_rates");
 $ih=$pdo->prepare("INSERT INTO tb_build_hotels (location,name,star,room_categories,price_range,address,contact,active,sort,type) VALUES (?,?,?,?,?,?,?,1,?,'hotel')");
 $ir=$pdo->prepare("INSERT INTO tb_hotel_rates (hotel,location,room,meal,period_from,period_to,cost_rate,sell_rate,active,sort) VALUES (?,?,?,'CP','2026-01-01','2026-12-31',?,?,1,?)");
 $locs=[]; $sort=0;
 $pdo->beginTransaction();
 foreach($lines as $ln){
   $f=explode("\t",$ln);
   if(count($f)<9) continue;
   list($loc,$name,$star,$room,$price,$addr,$contact,$lo,$hi)=$f;
   $sort++;
   $ih->execute([$loc,$name,$star,$room,$price,$addr,$contact,$sort]);
   $rm=trim(explode('/',$room)[0]); if($rm==='') $rm='Standard Room';
   $ir->execute([$name,$loc,$rm,(int)$lo,(int)$hi,$sort]);
   $locs[$loc]=1;
 }
 $chk=$pdo->prepare("SELECT COUNT(*) FROM tb_points WHERE name=?");
 $ip=$pdo->prepare("INSERT INTO tb_points (name,kind,region,active,sort) VALUES (?,'both','Kerala',1,?)");
 $padd=0; $ps=0;
 foreach(array_keys($locs) as $L){ $ps++; $chk->execute([$L]); if($chk->fetchColumn()==0){ $ip->execute([$L,$ps]); $padd++; } }
 $pdo->commit();
 $out['hotels']=$pdo->query("SELECT COUNT(*) FROM tb_build_hotels")->fetchColumn();
 $out['rates']=$pdo->query("SELECT COUNT(*) FROM tb_hotel_rates")->fetchColumn();
 $out['points_added']=$padd;
 $out['ok']=true;
}catch(Throwable $e){
 if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
 $out['ok']=false; $out['err']=$e->getMessage();
}
file_put_contents("$root/_himp.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DONE_HIMP\n";
