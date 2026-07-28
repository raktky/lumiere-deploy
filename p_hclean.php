<?php
$root='/var/www/lumiere/experience';
$rm=['_hdata.txt','_himp.txt','_pfin3.txt','_pfin2.txt','_pfin.txt','_pls.txt','_psh.txt'];
$done=[];
foreach($rm as $f){ if(@file_exists("$root/$f")){ @unlink("$root/$f"); $done[]=$f; } }
file_put_contents("$root/_hclean.txt", 'removed: '.implode(',',$done));
echo "CLEAN
";
