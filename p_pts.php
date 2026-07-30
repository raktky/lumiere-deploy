<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
try{ $o['points']=$pdo->query("SELECT id,name,kind,region,active,sort FROM tb_points ORDER BY sort,name")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){$o['pe']=$e->getMessage();}
try{ $o['hotel_locs']=$pdo->query("SELECT location, COUNT(*) c FROM tb_build_hotels WHERE active=1 GROUP BY location ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
file_put_contents("$root/_pts.json", json_encode($o));
echo 'PTS:'.count($o['points']??[]);
