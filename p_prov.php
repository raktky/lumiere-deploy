<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db();
$pdo->prepare("UPDATE tb_ai SET provider='anthropic' WHERE id=1")->execute();
$r=$pdo->query("SELECT provider,model FROM tb_ai WHERE id=1")->fetch(PDO::FETCH_ASSOC);
file_put_contents("$root/_dep_prov.txt",json_encode($r));
echo 'PROV';
