<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
$o['recent']=$pdo->query("SELECT id,op,meta,created_at FROM tb_ai_log WHERE op='TOKENS' ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$o['token_rows']=(int)$pdo->query("SELECT COUNT(*) FROM tb_ai_log WHERE op='TOKENS'")->fetchColumn();
file_put_contents("$root/_tokread.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'TOKREAD '.$o['token_rows'];
