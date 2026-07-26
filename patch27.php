<?php
/* patch27: cleanup stray diagnostic files. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$gone=[];
foreach(glob("$root/_d*.txt") as $g){ @unlink($g); $gone[]=basename($g).'='.(is_file($g)?'STILL':'removed'); }
foreach(glob("$root/_dq*.txt") as $g){ @unlink($g); $gone[]=basename($g).'='.(is_file($g)?'STILL':'removed'); }
echo (empty($gone)?"nothing to remove":implode("\n",$gone))."\nDONE\n";
