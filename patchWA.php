<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
try{ db()->prepare('UPDATE tb_track SET wa_number=? WHERE id=1')->execute(['919526232221']); $rep[]='wa=set'; }catch(Throwable $e){ $rep[]='wa=ERR:'.substr($e->getMessage(),0,40); }
try{ $v=db()->query("SELECT wa_number FROM tb_track WHERE id=1")->fetchColumn(); $rep[]='stored='.$v; }catch(Throwable $e){}
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_pwa.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
