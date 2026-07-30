<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
function hascol($pdo,$c){ $s=$pdo->query("SHOW COLUMNS FROM tb_ai LIKE ".$pdo->quote($c)); return $s&&$s->fetch(); }
foreach(['key_gemini'=>'VARCHAR(255)','key_anthropic'=>'VARCHAR(255)','model_gemini'=>'VARCHAR(80)','model_anthropic'=>'VARCHAR(80)'] as $c=>$t){
  if(!hascol($pdo,$c)){ $pdo->exec("ALTER TABLE tb_ai ADD COLUMN $c $t NULL"); $o[$c]='added'; } else $o[$c]='exists';
}
// seed model defaults if empty
$pdo->exec("UPDATE tb_ai SET model_gemini='gemini-2.0-flash' WHERE id=1 AND (model_gemini IS NULL OR model_gemini='')");
$pdo->exec("UPDATE tb_ai SET model_anthropic='claude-3-5-sonnet-20241022' WHERE id=1 AND (model_anthropic IS NULL OR model_anthropic='')");
// migrate existing single api_key into gemini slot if gemini empty (current key is the gemini one)
$pdo->exec("UPDATE tb_ai SET key_gemini=api_key WHERE id=1 AND (key_gemini IS NULL OR key_gemini='') AND api_key<>''");
$r=$pdo->query("SELECT provider,model_gemini,model_anthropic,(key_gemini<>'') gk,(key_anthropic<>'') ak FROM tb_ai WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$o['row']=$r;
file_put_contents("$root/_dep_mig2.txt",json_encode($o));
echo 'MIG2';
