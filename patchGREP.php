<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$build=@file_get_contents("$root/api/build.php")?:'';
$quote=@file_get_contents("$root/admin/quote.php")?:'';
function grepLines($s,$re,$max=40){ $o=[]; foreach(explode("\n",$s) as $ln){ if(preg_match($re,$ln)){ $o[]=trim(substr($ln,0,160)); if(count($o)>=$max)break; } } return $o; }
$out=[
 'build_post'=>grepLines($build,'/\$_(POST|GET|REQUEST)\[/'),
 'build_json'=>grepLines($build,'/json_encode|echo json|=>\s*\$|\'day\'|"day"|km|margin|customer_price|hotel_cost|vehicle/i',50),
 'build_sql'=>grepLines($build,'/FROM\s+tb_|INSERT INTO|UPDATE tb_/i',30),
 'quote_fetch'=>grepLines($quote,'/fetch\(|api\/build|\.php|XMLHttpRequest|action=/i',40),
 'quote_fn'=>grepLines($quote,'/function\s+\w+|const \w+\s*=\s*\(|addDay|renderDay|calc|recalc/i',50),
 'quote_ids'=>grepLines($quote,'/id="[a-zA-Z0-9_-]+"|name="[a-zA-Z0-9_\[\]]+"/i',60),
 'quote_ai'=>grepLines($quote,'/AI|✨|anthropic|generate|magic/i',20),
];
file_put_contents("$root/_pg.txt", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo "DONE\n";
