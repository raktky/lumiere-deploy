<?php
/* patchC: quote lifecycle — approval lock + link expiry/revoke.
   - tb_quotes: status, valid_until, revoked_at, locked_at, current_version
   - q.php: block revoked/expired links; accept -> status=APPROVED + lock
   - admin/quote.php: Link controls box (status + validity/revoke/unlock)
   - admin/quote-link.php: new endpoint handling those actions
   Idempotent; lint each; status report. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchC');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* 1. migrate */
try{
  $pdo=db(); $cols=[];
  foreach($pdo->query("SHOW COLUMNS FROM tb_quotes")->fetchAll(PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=1; }
  if(!isset($cols['status']))         $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'DRAFT'");
  if(!isset($cols['valid_until']))    $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN valid_until DATE NULL");
  if(!isset($cols['revoked_at']))     $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN revoked_at DATETIME NULL");
  if(!isset($cols['locked_at']))      $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN locked_at DATETIME NULL");
  if(!isset($cols['current_version'])) $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN current_version INT NOT NULL DEFAULT 1");
  $rep[]='migrate=ok';
}catch(Throwable $e){ $rep[]='migrate=ERR'; }

/* 2. q.php */
$pf="$root/q.php"; $pc=file_get_contents($pf); $pc0=$pc;
/* 2a. blocked check after fetch */
$aF='        $quote = $st->fetch();';
if(strpos($pc,'$__expired')===false && strpos($pc,$aF)!==false){
  $ins=$aF."\n        if (\$quote) { \$__expired = (!empty(\$quote['revoked_at'])) || (!empty(\$quote['valid_until']) && (string) \$quote['valid_until'] < date('Y-m-d')); if (\$__expired) { \$quote = null; } }";
  $pc=str_replace($aF,$ins,$pc,$n); $rep[]='q_block='.$n;
} else { $rep[]='q_block=skip'; }
/* ensure $__expired initialised (before the token block) */
if(strpos($pc,'$__expired = false;')===false){
  $pc=str_replace('$quote = null;'."\n".'if ($token !== \'\') {', '$quote = null;'."\n".'$__expired = false;'."\n".'if ($token !== \'\') {', $pc, $n2); $rep[]='q_init='.$n2;
}
/* 2b. accept -> status + lock */
$pc=str_replace(
  "SET accepted = 1, accepted_at = NOW() WHERE token = ?",
  "SET accepted = 1, accepted_at = NOW(), status = 'APPROVED', locked_at = NOW() WHERE token = ?",
  $pc,$n3); $rep[]='q_lock='.$n3;
/* 2c. not-found text -> expiry-aware */
$pc=str_replace(
  '<p class="sub">This link is invalid or has expired. Please contact us for a fresh quote.</p>',
  '<p class="sub"><?= (isset($__expired) && $__expired) ? \'This quote link has expired or been withdrawn. Please contact us for an updated quote.\' : \'This link is invalid or has expired. Please contact us for a fresh quote.\' ?></p>',
  $pc,$n4); $rep[]='q_text='.$n4;
if($pc!==$pc0){
  if(!put_lint($pf,$pc,$rep,'qphp')){
    $t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$pc);exec('php -l '.escapeshellarg($t).' 2>&1',$eo,$erc);unlink($t);
    file_put_contents("$root/_qerr.txt", base64_encode(implode("\n",$eo)));
  }
} else { $rep[]='qphp=nochange'; }

/* 3. admin/quote.php link-controls box */
$qf="$root/admin/quote.php"; $qc=file_get_contents($qf); $qc0=$qc;
$aH='<h1>Quote #<?= e((string)$id) ?> &mdash;';
if(strpos($qc,'Link controls')===false && strpos($qc,$aH)!==false){
  $box='<?php $__lc = $pdo->query("SELECT status, valid_until, revoked_at, accepted, locked_at FROM tb_quotes WHERE trip_request_id = " . (int) $id)->fetch(); if ($__lc): ?>'."\n"
    .'<div class="box"><strong>Link controls</strong> &mdash; status: <span class="pill '.'<?= (int) $__lc[\'accepted\']===1?\'on\':\'off\' ?>"><?= e((string) ($__lc[\'status\'] ?? \'DRAFT\')) ?></span>'."\n"
    .'<?= !empty($__lc[\'valid_until\']) ? \' &middot; valid until \' . e((string) $__lc[\'valid_until\']) : \'\' ?>'."\n"
    .'<?= !empty($__lc[\'revoked_at\']) ? \' &middot; <span class="pill off">REVOKED</span>\' : \'\' ?>'."\n"
    .'<form method="post" action="quote-link.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">'."\n"
    .'<?= function_exists(\'csrf_field\') ? csrf_field() : \'\' ?>'."\n"
    .'<input type="hidden" name="id" value="<?= (int) $id ?>">'."\n"
    .'<input type="date" name="valid_until" value="<?= e((string) ($__lc[\'valid_until\'] ?? \'\')) ?>">'."\n"
    .'<button class="btn ghost small" name="link_action" value="setvalidity">Set validity</button>'."\n"
    .'<?php if (empty($__lc[\'revoked_at\'])): ?><button class="btn ghost small" name="link_action" value="revoke">Revoke link</button><?php else: ?><button class="btn ghost small" name="link_action" value="restore">Restore link</button><?php endif; ?>'."\n"
    .'<?php if ((int) $__lc[\'accepted\']===1): ?><button class="btn ghost small" name="link_action" value="unlock">Unlock (revise)</button><?php endif; ?>'."\n"
    .'</form></div>'."\n"
    .'<?php endif; ?>'."\n"
    .$aH;
  $qc=str_replace($aH,$box,$qc,$n5); $rep[]='q_ctrl='.$n5;
  if($n5===1){ put_lint($qf,$qc,$rep,'quotephp'); } else { $rep[]='quotephp=anchorfail'; }
} else { $rep[]='q_ctrl=already/anchorfail'; }

/* 4. admin/quote-link.php endpoint (prologue copied from quotes.php) */
$src=@file_get_contents("$root/admin/quotes.php"); $prologue='';
if($src){ foreach(explode("\n",$src) as $ln){ $t=trim($ln);
  $ok=($t==='')||strpos($t,'<?php')===0||strpos($t,'declare')===0||strpos($t,'require')===0||strpos($t,'use ')===0||strpos($t,'namespace')===0||strpos($t,'ini_set')===0||strpos($t,'error_reporting')===0||strpos($t,'require_admin')!==false||strpos($t,'admin_user')!==false||strpos($t,'//')===0||strpos($t,'/*')===0||strpos($t,'*')===0||preg_match('/^\$pdo\s*=/',$t)||strpos($t,'rbac')!==false;
  if(!$ok) break; $prologue.=$ln."\n"; } }
if(strpos($prologue,'require_admin')===false){ $rep[]='qlink_prologue=BAD'; }
else{
  if(strpos($prologue,'$pdo')===false){ $prologue.="\$pdo = db();\n"; }
  $body = <<<'PHPX'

if (function_exists('csrf_check')) { csrf_check(); }
$id = (int) ($_POST['id'] ?? 0);
$act = (string) ($_POST['link_action'] ?? '');
if ($id > 0) {
  try {
    if ($act === 'revoke') {
      $pdo->prepare('UPDATE tb_quotes SET revoked_at = NOW() WHERE trip_request_id = ?')->execute([$id]);
    } elseif ($act === 'restore') {
      $pdo->prepare('UPDATE tb_quotes SET revoked_at = NULL WHERE trip_request_id = ?')->execute([$id]);
    } elseif ($act === 'setvalidity') {
      $vu = trim((string) ($_POST['valid_until'] ?? ''));
      if ($vu !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $vu)) {
        $pdo->prepare('UPDATE tb_quotes SET valid_until = ? WHERE trip_request_id = ?')->execute([$vu, $id]);
      } else {
        $pdo->prepare('UPDATE tb_quotes SET valid_until = NULL WHERE trip_request_id = ?')->execute([$id]);
      }
    } elseif ($act === 'unlock') {
      $pdo->prepare("UPDATE tb_quotes SET accepted = 0, status = 'REVISED', locked_at = NULL, current_version = current_version + 1 WHERE trip_request_id = ?")->execute([$id]);
    }
  } catch (Throwable $e) {}
}
$__base = rtrim((string) (function_exists('url') ? url('') : ''), '/');
header('Location: ' . $__base . '/admin/quote.php?id=' . $id);
exit;
PHPX;
  $code=rtrim($prologue,"\n")."\n".$body."\n";
  put_lint("$root/admin/quote-link.php",$code,$rep,'qlink');
}

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){ if(basename($g)==='_qerr.txt') continue; @unlink($g);}
file_put_contents("$root/_pc2.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
