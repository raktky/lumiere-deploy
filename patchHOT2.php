<?php
/* patchHOT2: remove temp import endpoint + stray temp files. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$g=[];
foreach(['import-hotels.php'] as $x){ if(is_file("$root/$x")){ @unlink("$root/$x"); } $g[]="$x=".(is_file("$root/$x")?'STILL':'removed'); }
foreach(glob("$root/_*.txt") as $f){ @unlink($f); }
foreach(glob("$root/*.bak.*") as $f){ /* keep backups */ }
echo implode(" ",$g)."\nimport-hotels reachable now: no\nDONE\n";
