<?php
$r='/var/www/lumiere/experience';
$b=(string)@file_get_contents("$r/_pkgdata.txt");
file_put_contents("$r/_pdiag.txt", strlen($b).'|'.md5($b));
echo 'DIAG';
