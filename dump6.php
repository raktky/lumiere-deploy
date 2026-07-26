<?php
/* dump6: RBAC recon — auth.php, users.php, login.php, admins schema+rows. Writes txt; deleted by next patch. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php";
require_once "$root/app/db.php";
$out="";
foreach(['app/auth.php','admin/users.php','admin/login.php'] as $rel){
  $p="$root/$rel";
  $out.="===== $rel (".(is_file($p)?filesize($p):'MISSING').") =====\n".(is_file($p)?file_get_contents($p):'')."\n\n";
}
try{
  $pdo=db();
  $out.="=== SCHEMA admins ===\n";
  foreach($pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_ASSOC) as $c){ $out.="  {$c['Field']}  {$c['Type']}  ".($c['Null']==='NO'?'NOT NULL':'null')." ".($c['Key']?:'')."\n"; }
  $out.="--- rows (no pw hash) ---\n";
  foreach($pdo->query("SELECT * FROM admins")->fetchAll(PDO::FETCH_ASSOC) as $r){ $line=[]; foreach($r as $k=>$v){ if(stripos($k,'pass')!==false||stripos($k,'hash')!==false){$v='***';} $line[]="$k=".substr((string)$v,0,60); } $out.="  ".implode(' | ',$line)."\n"; }
  $out.="\n=== existing tables (roles?) ===\n";
  foreach($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t){ if(stripos($t,'role')!==false||stripos($t,'perm')!==false||$t==='admins'){ $out.="  $t\n"; } }
}catch(Throwable $e){ $out.="DB err: ".$e->getMessage()."\n"; }
file_put_contents("$root/_d6_kx7.txt",$out);
echo "dump6 done ".strlen($out)." bytes\n";
