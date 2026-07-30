<?php
$root='/var/www/lumiere/experience';
$f=$root.'/admin/ai-plan.php';
$s=file_get_contents($f);
$out=['had'=>substr_count($s,'falze')];
if($out['had']>0){
  $bak=$f.'.bak3'; @copy($f,$bak);
  file_put_contents($f,str_replace('falze','false',$s));
  $o=[];$rc=0; exec('php -l '.escapeshellarg($f).' 2>&1',$o,$rc);
  if($rc!==0){ @copy($bak,$f); }
  $out['rc']=$rc;$out['fixed']=($rc===0);
}
$out['still']=strpos(file_get_contents($f),'falze')!==false;
file_put_contents($root.'/_dep_falze.txt',json_encode($out));
echo 'FZ';
