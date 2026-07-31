<?php
$root='/var/www/lumiere/experience';
$f="$root/admin/ai-package.php";
$s=file_get_contents($f);
$old='style="text-decoration:none">Download PDF →</a>';
$new='style="text-decoration:none;display:inline-block;margin-bottom:8px">Download PDF →</a>';
$cnt=0; $s2=str_replace($old,$new,$s,$cnt);
$o=['found'=>$cnt];
if($cnt>0){
  copy($f,"$f.bak");
  file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){ copy("$f.bak",$f); $o['restored']=true; }
}
$o['crc']=hash('crc32b',$s2);
file_put_contents("$root/_pkgfix.txt",json_encode($o,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'PKGFIX '.$o['found'];
