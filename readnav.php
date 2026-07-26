<?php
/* readnav: dump admin_ui.php nav structure (redact any sensitive line). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$c=@file_get_contents("$root/app/admin_ui.php");
$o="len=".strlen($c)."\n";
$ls=explode("\n",$c);
foreach($ls as $i=>$ln){
  if(preg_match('/csrf|token|password|secret|session|cookie/i',$ln)){ $o.=($i+1).": [redacted]\n"; continue; }
  $o.=($i+1).": ".$ln."\n";
}
file_put_contents("$root/_nav.txt",$o);
echo "written ".strlen($o)."\nDONE\n";
