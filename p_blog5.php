<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
try{ $o['roles']=$pdo->query("SELECT * FROM tb_roles")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ $o['e']=$e->getMessage(); }
$s=@file_get_contents("$root/app/rbac.php");
foreach(['function admin_perms','function admin_can','function admin_role_name','function require_perm'] as $n){ $p=strpos($s,$n); if($p!==false)$o['fn'][$n]=preg_replace('/\s+/',' ',substr($s,$p,700)); }
foreach(['app/admin_ui.php','admin/list.php','admin/edit.php','app/admin_types.php'] as $rel){ $c=@file_get_contents("$root/$rel"); if($c){ if(preg_match_all('/admin_can\(\s*[^)]*\)/',$c,$m)) $o['admin_can_calls'][$rel]=array_values(array_unique($m[0])); if(preg_match_all('/require_perm\(\s*[^)]*\)/',$c,$m2)) $o['require_perm_calls'][$rel]=array_values(array_unique($m2[0])); } }
file_put_contents("$root/_roles.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'ROLES '.strlen(json_encode($o));
