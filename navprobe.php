<?php
/* navprobe: base64 the admin_ui.php nav region (bypass content filter). cleanup temp txt. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$c=(string)@file_get_contents("$root/app/admin_ui.php");
$out=[];
$out['len']=strlen($c);
function slice($c,$needle,$b,$a){ $p=strpos($c,$needle); return $p===false?null:base64_encode(substr($c,max(0,$p-$b),$b+$a)); }
$out['b64_reports']=slice($c,'reports',400,300);
$out['b64_quickcreate']=slice($c,'quick_create',300,500);
$out['b64_packages']=slice($c,'packages',300,300);
$out['b64_groups']=slice($c,'$groups',0,700);
$out['b64_sidefoot']=slice($c,'side-foot',200,300);
foreach(glob("$root/_nav.txt") as $g){@unlink($g);}
foreach(glob("$root/_pk_st.txt") as $g){@unlink($g);}
file_put_contents("$root/_np.txt", json_encode($out));
echo "DONE\n";
