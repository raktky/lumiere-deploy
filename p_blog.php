<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
// blog table candidates
foreach(['tb_posts','posts','tb_blog','tb_journal','tb_stories'] as $t){ try{ $o['count'][$t]=(int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); }catch(Throwable $e){} }
try{ $o['cols_posts']=array_map(fn($r)=>$r['Field'].' '.$r['Type'].' '.$r['Null'],$pdo->query("SHOW COLUMNS FROM tb_posts")->fetchAll(PDO::FETCH_ASSOC)); }catch(Throwable $e){$o['cErr']=$e->getMessage();}
try{ $rows=$pdo->query("SELECT * FROM tb_posts ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as &$r){ foreach($r as $k=>$v){ if(is_string($v)&&strlen($v)>80) $r[$k]=substr($v,0,80).'…['.strlen($v).']'; } }
  $o['sample']=$rows;
}catch(Throwable $e){$o['sErr']=$e->getMessage();}
// dump admin_types.php + list webroot php
@copy("$root/app/admin_types.php","$root/_at.txt");
$o['web_php']=array_map('basename', glob("$root/*.php"));
$o['admin_php']=array_map('basename', glob("$root/admin/*.php"));
file_put_contents("$root/_blogdiag.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo 'BLOG';
