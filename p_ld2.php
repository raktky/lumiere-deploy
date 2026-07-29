<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$o=[];
$s=@file_get_contents("$root/admin/leads.php");
$o['srclen']=strlen((string)$s);
file_put_contents("$root/_ld_src.txt",(string)$s);
try{ $o['now']=db()->query('SELECT NOW()')->fetchColumn(); }catch(Throwable $e){ $o['nowe']=$e->getMessage(); }
try{ $o['tz']=db()->query('SELECT @@session.time_zone s,@@global.time_zone g')->fetch(PDO::FETCH_ASSOC); }catch(Throwable $e){}
try{ $o['tables']=db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); }catch(Throwable $e){ $o['te']=$e->getMessage(); }
file_put_contents("$root/_ld_meta.txt", json_encode($o));
echo 'LD2';
