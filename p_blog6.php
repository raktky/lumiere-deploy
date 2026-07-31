<?php
$root='/var/www/lumiere/experience';
$c=@file_get_contents("$root/app/admin_ui.php");
$o=[];
$lines=explode("\n",$c);
foreach($lines as $i=>$l){
  if(preg_match('/journal|posts|Journal|admin_can|=>\s*\[|groups|list\.php/i',$l)){
    $o[]=($i+1).': '.trim($l);
  }
}
file_put_contents("$root/_ui.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'UI '.count($o);
