<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
$o['total']=(int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$o['published']=(int)$pdo->query("SELECT COUNT(*) FROM posts WHERE published=1")->fetchColumn();
$st=$pdo->query("SELECT id,slug,published,LENGTH(body) bl,LENGTH(excerpt) el,LENGTH(cover) cl FROM posts WHERE slug LIKE '%laksh%' OR title LIKE '%akshadw%' ORDER BY id");
$o['laksh']=$st->fetchAll(PDO::FETCH_ASSOC);
$o['empty_body']=$pdo->query("SELECT id,slug,published FROM posts WHERE body IS NULL OR body='' OR LENGTH(body)<50")->fetchAll(PDO::FETCH_ASSOC);
$o['unpublished']=$pdo->query("SELECT id,slug FROM posts WHERE published<>1")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents("$root/_lak.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'LAK '.count($o['laksh']);
