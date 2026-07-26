<?php
/* patchC2: fix q.php lifecycle edits (escaped status quotes). quote.php + quote-link.php already live. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
/* migration idempotent */
try{
  $pdo=db(); $cols=[];
  foreach($pdo->query("SHOW COLUMNS FROM tb_quotes")->fetchAll(PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=1; }
  if(!isset($cols['status']))          $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'DRAFT'");
  if(!isset($cols['valid_until']))     $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN valid_until DATE NULL");
  if(!isset($cols['revoked_at']))      $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN revoked_at DATETIME NULL");
  if(!isset($cols['locked_at']))       $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN locked_at DATETIME NULL");
  if(!isset($cols['current_version'])) $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN current_version INT NOT NULL DEFAULT 1");
  $rep[]='migrate=ok';
}catch(Throwable $e){ $rep[]='migrate=ERR'; }

$pf="$root/q.php"; $pc=file_get_contents($pf); $pc0=$pc;
if(strpos($pc,'$__expired')!==false){ $rep[]='qphp=already'; }
else{
  $aF='        $quote = $st->fetch();';
  $pc=str_replace($aF,$aF."\n        if (\$quote) { \$__expired = (!empty(\$quote['revoked_at'])) || (!empty(\$quote['valid_until']) && (string) \$quote['valid_until'] < date('Y-m-d')); if (\$__expired) { \$quote = null; } }",$pc,$n1); $rep[]='q_block='.$n1;
  $pc=str_replace('$quote = null;'."\n".'if ($token !== \'\') {', '$quote = null;'."\n".'$__expired = false;'."\n".'if ($token !== \'\') {', $pc, $n2); $rep[]='q_init='.$n2;
  $pc=str_replace("SET accepted = 1, accepted_at = NOW() WHERE token = ?","SET accepted = 1, accepted_at = NOW(), status = \\'APPROVED\\', locked_at = NOW() WHERE token = ?",$pc,$n3); $rep[]='q_lock='.$n3;
  $pc=str_replace('<p class="sub">This link is invalid or has expired. Please contact us for a fresh quote.</p>','<p class="sub"><?= (isset($__expired) && $__expired) ? \'This quote link has expired or been withdrawn. Please contact us for an updated quote.\' : \'This link is invalid or has expired. Please contact us for a fresh quote.\' ?></p>',$pc,$n4); $rep[]='q_text='.$n4;
  if($pc!==$pc0){
    $t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$pc);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
    if($rc!==0){ $rep[]='qphp=lintFAIL'; }
    else{ copy($pf,$pf.'.bak.patchC2'); file_put_contents($pf,$pc); $rep[]='qphp=ok'; }
  }
}
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_pc3.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
