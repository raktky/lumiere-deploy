<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$out='';
$q=@file_get_contents("$root/q.php");
$out.="q.php has 'day-by-day plan': ".(strpos($q,'day-by-day plan')!==false?'YES':'NO')."\n";
$out.="q.php has 'class=\"total\"': ".(strpos($q,'class="total"')!==false?'YES':'NO')."\n";
$p=strpos($q,'class="total"');
$out.="--- 260 chars around class=total ---\n".($p!==false?substr($q,max(0,$p-40),260):'(not found)')."\n\n";
// SELECT structure of q.php around the total (find the div line)
$out.="--- q.php SELECT line ---\n";
foreach(explode("\n",$q) as $ln){ if(stripos($ln,'SELECT q.')!==false){ $out.=trim($ln)."\n"; } }
try{
  $r=db()->prepare("SELECT itinerary, title, LENGTH(itinerary) L FROM tb_quotes WHERE trip_request_id=26"); $r->execute();
  $row=$r->fetch(PDO::FETCH_ASSOC);
  $out.="\n--- tb_quotes #26 ---\ntitle=".($row['title']??'(null)')."\nitin_len=".($row['L']??'(null)')."\nitin=".substr((string)($row['itinerary']??''),0,300)."\n";
}catch(Throwable $e){ $out.="DB err: ".$e->getMessage()."\n"; }
file_put_contents("$root/_d7_kx7.txt",$out);
echo $out;
