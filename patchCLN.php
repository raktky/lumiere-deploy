<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
try{ db()->prepare("UPDATE trip_requests SET handled=1, notes=CONCAT(COALESCE(notes,''),' [TEST LEAD - safe to delete]') WHERE id=31")->execute(); $rep[]='row31=marked'; }catch(Throwable $e){$rep[]='row31_err='.$e->getMessage();}
$d=0; foreach(glob("$root/_p*.txt") as $g){@unlink($g);$d++;}
file_put_contents("$root/_pcln.txt", implode(' ',$rep)." deleted=$d");
echo "DONE\n";
