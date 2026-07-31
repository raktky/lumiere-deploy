<?php
$root='/var/www/lumiere/experience';
$c=@file_get_contents("$root/admin/ai-package.php");
$o=[]; $lines=explode("\n",$c);
foreach($lines as $i=>$l){
  if(preg_match('/Download PDF|Refine in Quote|totalbox|Total incl|per person|Saved as a/i',$l)){
    $o[]=($i+1).': '.rtrim($l);
  }
}
file_put_contents("$root/_pkgsrc.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'PKGSRC '.count($o);
