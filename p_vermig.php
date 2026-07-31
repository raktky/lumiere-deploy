<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS tb_pkg_share (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(32) NOT NULL UNIQUE,
    lead_id INT DEFAULT NULL,
    customer VARCHAR(120) NOT NULL DEFAULT '',
    version_no INT NOT NULL DEFAULT 1,
    title VARCHAR(200) NOT NULL DEFAULT '',
    content MEDIUMTEXT,
    created_at DATETIME NOT NULL,
    KEY lead_idx (lead_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $o['share']='ok';
}catch(Throwable $e){ $o['share_err']=$e->getMessage(); }
try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS tb_pkg_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(32) NOT NULL,
    version_no INT NOT NULL DEFAULT 1,
    message TEXT,
    created_at DATETIME NOT NULL,
    seen TINYINT NOT NULL DEFAULT 0,
    KEY token_idx (token)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $o['feedback']='ok';
}catch(Throwable $e){ $o['feedback_err']=$e->getMessage(); }
$o['tables']=array_intersect($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN),['tb_pkg_share','tb_pkg_feedback']);
file_put_contents("$root/_vermig.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'VERMIG '.json_encode($o);
