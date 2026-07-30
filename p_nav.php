<?php
$root='/var/www/lumiere/experience';
$f=$root.'/app/admin_ui.php';
$s=file_get_contents($f);
$anchor="'pkg_builder' => ['Package builder', 'package.php'],";
$ins=$anchor."\n            'ai_package' => ['AI Package Builder', 'ai-package.php'],\n            'loc_images' => ['Location Images', 'location-images.php'],";
$out=['found'=>strpos($s,$anchor)!==false,'already'=>strpos($s,'ai_package')!==false];
if($out['found'] && !$out['already']){
  $bak=$f.'.bak'; if(!is_file($bak)) @copy($f,$bak);
  file_put_contents($f,str_replace($anchor,$ins,$s));
  $o=[];$rc=0; exec('php -l '.escapeshellarg($f).' 2>&1',$o,$rc);
  if($rc!==0 && is_file($bak)){ @copy($bak,$f); }
  $out['rc']=$rc;$out['lint']=$o;
}
file_put_contents($root.'/_dep_nav.txt',json_encode($out));
echo 'NAV';
