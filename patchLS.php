<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$out=[];
foreach(['','/admin','/admin/api','/api','/app'] as $d){
  $p=$root.$d; $f=@scandir($p);
  $out[$d?:'/']=$f?array_values(array_filter($f,fn($x)=>$x[0]!=='.')):'(none)';
}
// config keys (names only, no values) to see how secrets read
$cfg=@file_get_contents("$root/app/config.php");
$keys=[]; if($cfg){ preg_match_all('/(define\\(|\\$)([A-Za-z_][A-Za-z0-9_]*)/',$cfg,$m); $keys=array_values(array_unique($m[2])); }
$out['_config_symbols']=array_slice($keys,0,60);
$out['_has_anthropic']=$cfg?(stripos($cfg,'anthropic')!==false || stripos($cfg,'ANTHROPIC')!==false):false;
file_put_contents("$root/_pls.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DONE\n";
