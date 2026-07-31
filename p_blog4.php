<?php
$root='/var/www/lumiere/experience';
$o=[];
foreach(array_merge(glob("$root/app/*.php"),glob("$root/admin/*.php")) as $f){
  $s=@file_get_contents($f); if(!$s) continue;
  foreach(['function require_admin','function admin_can','function require_login','function current_user','function current_role'] as $n){
    $p=strpos($s,$n);
    if($p!==false){ $o['def'][basename($f).'::'.$n]=preg_replace('/\s+/',' ',substr($s,$p,300)); }
  }
}
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db();
$o['tables']=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hide=fn($r)=>array_filter($r,fn($k)=>!preg_match('/pass|hash|token|secret|salt/i',$k),ARRAY_FILTER_USE_KEY);
foreach(['tb_users','users','tb_admins','admins'] as $tt){
  try{ $o['user_cols'][$tt]=array_map(fn($r)=>$r['Field'].' '.$r['Type'],$pdo->query("SHOW COLUMNS FROM `$tt`")->fetchAll(PDO::FETCH_ASSOC));
       $o['users'][$tt]=array_map($hide,$pdo->query("SELECT * FROM `$tt` LIMIT 20")->fetchAll(PDO::FETCH_ASSOC));
  }catch(Throwable $e){}
}
file_put_contents("$root/_rbac.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'RBAC '.strlen(json_encode($o));
