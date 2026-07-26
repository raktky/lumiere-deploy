<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
try{ db()->prepare('UPDATE tb_track SET ga4_id=? WHERE id=1')->execute(['G-WC325DLL9D']); $rep[]='ga4=set'; }catch(Throwable $e){ $rep[]='ga4=ERR'; }
try{ $r=db()->query("SELECT ga4_id,ads_conv_id,wa_number FROM tb_track WHERE id=1")->fetch(PDO::FETCH_ASSOC); $rep[]='row='.json_encode($r); }catch(Throwable $e){}
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_pga.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
