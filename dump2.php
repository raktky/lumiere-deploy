<?php
/* dump2: locate DB bootstrap (files defining db()/rows()/config) + how creds are read. Writes to webroot for one-time read; patch cleans later. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("root not found\n"); }
$out="ROOT: $root\n\n";
// recursive scan for php files defining db()/rows() or containing DB connect
$rii=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$hits=[];
foreach($rii as $f){
  $p=$f->getPathname();
  if(substr($p,-4)!=='.php') continue;
  if(strpos($p,'/templates/')!==false||strpos($p,'/cache/')!==false) continue;
  $c=@file_get_contents($p);
  if($c===false) continue;
  if(preg_match('/function\s+(db|rows|db_pass|getenv)\s*\(/',$c) || stripos($c,'new PDO')!==false || stripos($c,'mysqli')!==false || stripos($c,'DB_PASS')!==false){
    $hits[$p]=$c;
  }
}
$out.="=== MATCHING FILES (".count($hits).") ===\n";
foreach($hits as $p=>$c){ $out.="\n----- FILE: $p (".strlen($c)." bytes) -----\n".$c."\n"; }
file_put_contents("$root/_bootdump_kx7.txt",$out);
echo "bootdump written: ".strlen($out)." bytes, files=".count($hits)."\n";
echo "DONE\n";
