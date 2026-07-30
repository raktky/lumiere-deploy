<?php
$root='/var/www/lumiere/experience';
$f=$root.'/app/admin_ui.php';
$s=file_get_contents($f);
$out=['already'=>strpos($s,'ai_keys')!==false];
$anchor="settings.php'";
$pos=strpos($s,$anchor);
$out['found']=$pos!==false;
if($pos!==false && !$out['already']){
  $lineend=strpos($s,"\n",$pos);
  if($lineend!==false){
    $ls=strrpos(substr($s,0,$pos),"\n"); $ls=($ls===false)?0:$ls+1;
    $indent=''; for($i=$ls;$i<strlen($s)&&($s[$i]===' '||$s[$i]==="\t");$i++){ $indent.=$s[$i]; }
    $ins="\n".$indent."'ai_keys' => ['AI Keys', 'ai-keys.php'],";
    $s2=substr($s,0,$lineend).$ins.substr($s,$lineend);
    $bak=$f.'.bak5'; if(!is_file($bak)) @copy($f,$bak);
    file_put_contents($f,$s2);
    $o=[];$rc=0; exec('php -l '.escapeshellarg($f).' 2>&1',$o,$rc);
    if($rc!==0 && is_file($bak)){ @copy($bak,$f); }
    $out['rc']=$rc;$out['added']=($rc===0);
  }
}
file_put_contents($root.'/_dep_navk.txt',json_encode($out));
echo 'NAVK';
