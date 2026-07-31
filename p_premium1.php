<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
// 1) bump internal credit counter (config + running-balance top-up)
try{ $pdo->exec("UPDATE tb_ai SET credits=500"); $o['cfg_credits']=$pdo->query("SELECT credits FROM tb_ai LIMIT 1")->fetchColumn(); }catch(Throwable $e){$o['e_cfg']=$e->getMessage();}
try{
  $last=(int)$pdo->query("SELECT balance FROM tb_ai_log ORDER BY id DESC LIMIT 1")->fetchColumn();
  $st=$pdo->prepare("INSERT INTO tb_ai_log (op,delta,balance,meta,created_at) VALUES ('TOPUP',?,500,'manual top-up to 500',NOW())");
  $st->execute([500-$last]);
  $o['topup']=['from'=>$last,'to'=>500];
}catch(Throwable $e){$o['e_log']=$e->getMessage();}
// 2) Apple font on the PDF page
$f="$root/admin/package-pdf.php";
$s=file_get_contents($f);
$o['font_already']=strpos($s,'lumiere-apple-font')!==false;
if(strpos($s,'</head>')!==false && !$o['font_already']){
  $inj='<style id="lumiere-apple-font">*{font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","SF Pro Text","Helvetica Neue","Segoe UI",Roboto,Arial,sans-serif !important;-webkit-font-smoothing:antialiased;letter-spacing:-0.01em;}</style>'."\n</head>";
  $s2=preg_replace('#</head>#',$inj,$s,1);
  copy($f,"$f.bak2");
  file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['font_lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.bak2",$f);$o['font_restored']=true;} else {$o['font_done']=true;}
}
file_put_contents("$root/_premium1.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'PREMIUM1 '.json_encode($o);
