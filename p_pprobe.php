<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
foreach(['tb_quotes','tb_quote_versions'] as $t){
  try{ $o['cols'][$t]=array_map(fn($r)=>$r['Field'].' '.$r['Type'],$pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC)); }catch(Throwable $e){ $o['cols'][$t]=$e->getMessage(); }
}
// grep helpers
$grep=function($file,$re) use($root){ $c=@file_get_contents("$root/$file"); if(!$c) return ['NOFILE']; $out=[]; foreach(explode("\n",$c) as $i=>$l){ if(preg_match($re,$l)) $out[]=($i+1).': '.trim(substr($l,0,150)); } return array_slice($out,0,40); };
$o['pdf']=$grep('admin/package-pdf.php','/token|SELECT|gst|total|price|json_decode|->fetch|FROM tb_/i');
$o['gen']=$grep('admin/ai-package.php','/INSERT INTO|UPDATE tb_|token|gst|json_encode|->prepare|trip_id|quote/i');
file_put_contents("$root/_pprobe.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'PPROBE';
