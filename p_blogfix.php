<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db();
$out=[];
$cur=$pdo->query("SELECT perms FROM tb_roles WHERE id=2")->fetchColumn();
$out['before']=$cur;
$parts=array_values(array_filter(array_map('trim',explode(',',(string)$cur)),fn($x)=>$x!==''));
foreach(['posts'] as $add){ if(!in_array($add,$parts,true)) $parts[]=$add; }
$new=implode(',',$parts);
$st=$pdo->prepare("UPDATE tb_roles SET perms=? WHERE id=2");
$st->execute([$new]);
$out['after']=$pdo->query("SELECT perms FROM tb_roles WHERE id=2")->fetchColumn();
file_put_contents("$root/_fixstatus.txt",json_encode($out,JSON_UNESCAPED_SLASHES));
echo 'FIX '.$out['after'];
