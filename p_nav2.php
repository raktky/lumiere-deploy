<?php
$root='/var/www/lumiere/experience';
$f=$root.'/app/admin_ui.php';
$s=file_get_contents($f);
$out=['already'=>strpos($s,'ai_package')!==false];
$pos=strpos($s,'pkg_builder');
$out['found']=$pos!==false;
if($pos!==false && !$out['already']){
  $nl=strpos($s,"\n",$pos);
  if($nl!==false){
    $ls=strrpos(substr($s,0,$pos),"\n"); $ls=($ls===false)?0:$ls+1;
    $indent=''; for($i=$ls;$i<strlen($s) && ($s[$i]===' '||$s[$i]==="\t");$i++){ $indent.=$s[$i]; }
    $ins="\n".$indent."'ai_package' => ['AI Package Builder', 'ai-package.php'],"
        ."\n".$indent."'loc_images' => ['Location Images', 'location-images.php'],";
    $s2=substr($s,0,$nl).$ins.substr($s,$nl);
    $bak=$f.'.bak'; if(!is_file($bak)) @copy($f,$bak);
    file_put_contents($f,$s2);
    $o=[];$rc=0; exec('php -l '.escapeshellarg($f).' 2>&1',$o,$rc);
    if($rc!==0 && is_file($bak)){ @copy($bak,$f); }
    $out['rc']=$rc;$out['lint']=$o;$out['indent']=strlen($indent);
  }
}
file_put_contents($root.'/_dep_nav2.txt',json_encode($out)); echo 'NAV';
