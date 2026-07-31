<?php
$root='/var/www/lumiere/experience';
$rm=[];
$targets=['_edit.txt','_list.txt','_rbac.txt','_roles.txt','_ui.txt','_fixstatus.txt','_blogdiag.txt','_blog2.txt','_idx.txt','_at.txt','_probe.txt','_cleanup.txt'];
foreach($targets as $f){ if(is_file("$root/$f")){ @unlink("$root/$f"); $rm[]=$f; } }
foreach(glob("$root/_*.txt") as $f){ @unlink($f); $rm[]=basename($f); }
echo 'CLEAN '.implode(',',array_unique($rm));
