<?php
/* dump8: diagnose itinerary render. Echo to STDOUT (Actions log). Clean _d7. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$q=@file_get_contents("$root/q.php");
echo "q.php has 'day-by-day plan': ".(strpos($q,'day-by-day plan')!==false?'YES':'NO')."\n";
echo "q.php has \$__itin: ".(strpos($q,'$__itin')!==false?'YES':'NO')."\n";
echo "q.php .bak.patch23 exists: ".(is_file("$root/q.php.bak.patch23")?'YES':'NO')."\n";
$tok='279ea3cde13aee7d';
try{
  $r=db()->prepare("SELECT id, trip_request_id, title, LENGTH(itinerary) L, accepted FROM tb_quotes WHERE token=?");
  $r->execute([$tok]); $row=$r->fetch(PDO::FETCH_ASSOC);
  echo "\n-- token $tok -->\n".($row?json_encode($row):'(none)')."\n";
}catch(Throwable $e){ echo "err1: ".$e->getMessage()."\n"; }
try{
  $r=db()->query("SELECT id, trip_request_id, token, title, LENGTH(itinerary) L FROM tb_quotes WHERE itinerary IS NOT NULL AND itinerary<>'' ORDER BY id DESC LIMIT 5");
  echo "\n-- quotes with itinerary -->\n";
  foreach($r->fetchAll(PDO::FETCH_ASSOC) as $x){ echo json_encode($x)."\n"; }
}catch(Throwable $e){ echo "err2: ".$e->getMessage()."\n"; }
@unlink("$root/_d7_kx7.txt"); echo "\n_d7 removed: ".(is_file("$root/_d7_kx7.txt")?'NO':'YES')."\n";
echo "DONE\n";
