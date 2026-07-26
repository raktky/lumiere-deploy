<?php
/* patchA: sidebar link for package.php (Content group) + temp cleanup. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$f="$root/app/admin_ui.php";
$c=file_get_contents($f); $c0=$c;
$rep=[];
if(strpos($c,"'pkg_builder'")!==false){ $rep[]='nav=already'; }
else{
  $c=preg_replace(
    "/('packages'\\s*=>\\s*\\['Packages',\\s*'list\\.php\\?t=packages'\\],)/",
    "$1\n            'pkg_builder'   => ['Package builder', 'package.php'],",
    $c, 1, $n);
  $rep[]='nav='.(int)$n;
}
$lint='n/a';
if($c!==$c0){
  $t=tempnam(sys_get_temp_dir(),'ui');file_put_contents($t,$c);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $lint='FAIL'; }
  else{ copy($f,$f.'.bak.patchA'); file_put_contents($f,$c); $lint='ok-written'; }
}else{ $lint='no-change'; }
$rep[]='lint='.$lint;
/* cleanup temp txt */
foreach(glob("$root/_*.txt") as $g){ @unlink($g); }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
file_put_contents("$root/_pa.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
