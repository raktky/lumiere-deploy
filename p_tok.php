<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
$o['cols']=array_map(fn($r)=>$r['Field'].' '.$r['Type'],$pdo->query("SHOW COLUMNS FROM tb_ai_log")->fetchAll(PDO::FETCH_ASSOC));
$rows=$pdo->query("SELECT * FROM tb_ai_log ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as &$r){ foreach($r as $k=>$v){ if(is_string($v)&&strlen($v)>60) $r[$k]=substr($v,0,60).'…['.strlen($v).']'; } }
$o['recent']=$rows;
file_put_contents("$root/_tok.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'TOK';
