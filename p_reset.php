<?php $f='/var/www/lumiere/experience/_aiblob.txt'; file_put_contents($f, substr(file_get_contents($f),0,18000)); echo "RESET ".strlen(file_get_contents($f))."\n";
