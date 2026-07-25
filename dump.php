<?php
/* dump.php — one-off: copy build.php + admin_types.php to webroot txt so source can be read, then self-cleanup happens in patch7. */
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$b=file_get_contents("$root/templates/build.php");
file_put_contents("$root/_srcdump_kx7.txt",$b);
echo "build.php dumped: ".strlen($b)." bytes -> _srcdump_kx7.txt\n";
$a="$root/app/admin_types.php";
if(is_file($a)){ file_put_contents("$root/_admdump_kx7.txt",file_get_contents($a)); echo "admin_types dumped\n"; }
echo "DONE\n";
