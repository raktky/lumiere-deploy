<?php
$root='/var/www/lumiere/experience';
$s=@file_get_contents("$root/admin/package.php");
file_put_contents("$root/_pkgsrc.txt", (string)$s);
echo 'LEN='.strlen((string)$s)."
";
