<?php
/* dump3: gather everything for patch12 (quote calc). Writes ONE temp txt to webroot; patch12 will delete it. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("root not found\n"); }
$out="APPROOT: $root\n\n";

/* locate admin dir: find dir containing list.php + edit.php under any web root */
$webroots=['/var/www/lumiere','/var/www/html','/home'];
$admdir='';
foreach(['/var/www/lumiere/admin','/var/www/lumiere/experience/admin','/var/www/html/admin','/var/www/lumiere/public_html/admin'] as $d){
  if(is_file("$d/list.php")){ $admdir=$d; break; }
}
if(!$admdir){
  // recursive hunt
  foreach($webroots as $wr){ if(!is_dir($wr)) continue;
    $rii=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wr, FilesystemIterator::SKIP_DOTS));
    foreach($rii as $f){ if($f->getFilename()==='list.php'){ $p=dirname($f->getPathname()); if(is_file("$p/edit.php")){ $admdir=$p; break 2; } } }
  }
}
$out.="ADMINDIR: $admdir\n\n";

/* dump admin layout/nav file: find the file containing the sidebar link 'Trip requests' */
if($admdir){
  foreach(glob("$admdir/*.php") as $f){
    $c=@file_get_contents($f);
    if($c!==false && strpos($c,'Trip requests')!==false && (strpos($c,'list.php?t=')!==false)){
      $out.="----- NAV FILE: $f (".strlen($c)." bytes) -----\n".$c."\n\n";
    }
  }
  // also list admin dir php files
  $out.="ADMIN PHP FILES:\n";
  foreach(glob("$admdir/*.php") as $f){ $out.="  ".basename($f)." (".filesize($f).")\n"; }
  $out.="\n";
  // dump edit.php + list.php heads to learn render + how fields drawn (first 2500 chars each)
  foreach(['list.php','edit.php'] as $bn){ $p="$admdir/$bn"; if(is_file($p)){ $out.="----- $bn (first 3000) -----\n".substr(file_get_contents($p),0,3000)."\n\n"; } }
}

/* DB introspection */
require_once "$root/app/config.php";
require_once "$root/app/db.php";
try{
  $pdo=db();
  // find trip_requests-ish tables
  $out.="TABLES:\n";
  foreach($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t){ $out.="  $t\n"; }
  $out.="\n";
  foreach(['tb_trip_requests','trip_requests','tb_enquiries','tb_leads'] as $t){
    try{
      $cols=$pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
      $out.="=== SCHEMA $t ===\n";
      foreach($cols as $c){ $out.="  {$c['Field']}  {$c['Type']}\n"; }
      $row=$pdo->query("SELECT * FROM `$t` ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      if($row){ $out.="--- latest row ---\n"; foreach($row as $k=>$v){ $out.="  $k = ".substr((string)$v,0,600)."\n"; } }
      $out.="\n";
    }catch(Throwable $e){ /* table absent */ }
  }
}catch(Throwable $e){ $out.="DB error: ".$e->getMessage()."\n"; }

/* how does the builder submit? find handler that inserts a trip_request */
$hits=[];
$rii=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach($rii as $f){ $p=$f->getPathname(); if(substr($p,-4)!=='.php')continue; if(strpos($p,'/cache/')!==false)continue;
  $c=@file_get_contents($p); if($c===false)continue;
  if(stripos($c,'trip_request')!==false && (stripos($c,'INSERT')!==false||stripos($c,'->prepare')!==false)){ $hits[$p]=$c; }
}
foreach($hits as $p=>$c){ $out.="----- SUBMIT HANDLER: $p (".strlen($c)." bytes) -----\n".substr($c,0,4000)."\n\n"; }

file_put_contents("$root/_d3_kx7.txt",$out);
echo "dump3 written: ".strlen($out)." bytes\nDONE\n";
