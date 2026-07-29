<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
try{ $o['settings_cols']=$pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){$o['sce']=$e->getMessage();}
try{ $o['settings_rows']=$pdo->query("SELECT * FROM settings LIMIT 60")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){$o['sre']=$e->getMessage();}
try{ $o['pkg_cols']=$pdo->query("SHOW COLUMNS FROM tb_packages")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){$o['pce']=$e->getMessage();}
try{ $o['tmpl_cols']=$pdo->query("SHOW COLUMNS FROM tb_pkg_templates")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
$o['package_src']=@file_get_contents("$root/admin/package.php");
$o['aiplan_src']=@file_get_contents("$root/admin/ai-plan.php");
$o['adminui_src']=@file_get_contents("$root/app/admin_ui.php");
file_put_contents("$root/_isp.json", json_encode($o));
echo 'ISP:'.strlen((string)($o['package_src']??''));
