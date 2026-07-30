<?php
$root='/var/www/lumiere/experience';
$f=$root.'/admin/ai-package.php';
$s=file_get_contents($f);
$a="require_once __DIR__ . '/../app/ai.php';";
$out=['already'=>strpos($s,'require_admin();')!==false,'anchor'=>strpos($s,$a)!==false];
if(!$out['already'] && $out['anchor']){
  $bak=$f.'.bak2'; @copy($f,$bak);
  file_put_contents($f,str_replace($a,"require_admin();\n".$a,$s));
  $o=[];$rc=0; exec('php -l '.escapeshellarg($f).' 2>&1',$o,$rc);
  if($rc!==0){ @copy($bak,$f); }
  $out['rc']=$rc;$out['lint']=$o;$out['fixed']=($rc===0);
}
file_put_contents($root.'/_dep_fix.txt',json_encode($out));
echo 'FIX';
