<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
$o['ai_src']=@file_get_contents("$root/app/ai.php");
$o['quote_src']=@file_get_contents("$root/admin/quote.php");
try{ $o['tb_ai_cols']=$pdo->query("SHOW COLUMNS FROM tb_ai")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){$o['ae']=$e->getMessage();}
try{ $r=$pdo->query("SELECT id,model,credits,LENGTH(api_key) keylen,LEFT(profile,50) profpref FROM tb_ai LIMIT 3")->fetchAll(PDO::FETCH_ASSOC); $o['tb_ai']=$r; }catch(Throwable $e){$o['are']=$e->getMessage();}
$o['authhelpers']=[];
foreach(['admin_user','url','csrf_field','csrf_check','admin_can'] as $fn){ $o['authhelpers'][$fn]=function_exists($fn)?1:0; }
file_put_contents("$root/_isp2.json", json_encode($o));
echo 'ISP2:'.strlen((string)($o['ai_src']??'')).'/'.strlen((string)($o['quote_src']??''));
