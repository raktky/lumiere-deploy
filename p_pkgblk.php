<?php
$root='/var/www/lumiere/experience';
$lines=explode("\n",file_get_contents("$root/admin/ai-package.php"));
$slice=implode("\n",array_slice($lines,295,65));
file_put_contents("$root/_pkgblk.txt",$slice);
echo 'PKGBLK '.strlen($slice);
