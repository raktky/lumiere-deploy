<?php
$root='/var/www/lumiere/experience';
$f="$root/app/ai.php";
$src=is_file($f)?file_get_contents($f):'MISSING';
file_put_contents("$root/_aidump.txt",$src);
preg_match_all('/function\s+([a-zA-Z0-9_]+)/',$src,$m);
file_put_contents("$root/_aifns.txt",implode("\n",$m[1])."\nLEN=".strlen($src));
echo 'DUMP';
