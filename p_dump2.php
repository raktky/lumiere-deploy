<?php
$root='/var/www/lumiere/experience';
function d($root,$rel,$tag){ $f="$root/$rel"; $s=is_file($f)?file_get_contents($f):"MISSING:$rel"; file_put_contents("$root/_d_$tag.txt",$s); }
d($root,'app/admin_ui.php','ui');
d($root,'admin/ai-plan.php','plan');
d($root,'admin/quote.php','quote');
$ls=[];
foreach(['admin','app'] as $dir){ foreach(glob("$root/$dir/*.php") as $p){ $ls[]=$dir.'/'.basename($p).' ('.filesize($p).')'; } }
file_put_contents("$root/_d_ls.txt",implode("\n",$ls));
echo 'D2';
