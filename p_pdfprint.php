<?php
$root='/var/www/lumiere/experience';
$f="$root/admin/package-pdf.php";
$s=file_get_contents($f);
$o=['has_head'=>strpos($s,'</head>')!==false,'already'=>strpos($s,'print-color-adjust')!==false];
if($o['has_head'] && !$o['already']){
  $inject='<style>@media print{*{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;color-adjust:exact !important;}}html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}</style>'."\n</head>";
  $s2=preg_replace('#</head>#',$inject,$s,1);
  copy($f,"$f.bak");
  file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.bak",$f);$o['restored']=true;} else {$o['done']=true;$o['crc']=hash('crc32b',$s2);}
}
file_put_contents("$root/_pdfprint.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'PDFPRINT '.json_encode($o);
