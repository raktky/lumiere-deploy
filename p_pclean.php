<?php
$r='/var/www/lumiere/experience';
$rm=['_pkgsrc.txt','_pkghex.txt','_pkgdata.txt','_pwrite.txt','_pdiag.txt'];
$d=[]; foreach($rm as $f){ if(@file_exists("$r/$f")){ @unlink("$r/$f"); $d[]=$f; } }
file_put_contents("$r/_pclean.txt",'rm:'.implode(',',$d)); echo 'PC';
