<?php
/* patchD: version history for quotes.
   - tb_quote_versions
   - quote.php: snapshot on save + version-history box (restore)
   - admin/quote-version.php: restore endpoint
   Idempotent; lint before write; status report. Cleanup stray temp. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchD');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* 1. table */
try{
  db()->exec("CREATE TABLE IF NOT EXISTS tb_quote_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_request_id INT NOT NULL,
    version INT NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'TEAM',
    snapshot MEDIUMTEXT NULL,
    created_by INT NULL,
    created_at DATETIME NULL,
    INDEX (trip_request_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $rep[]='table=ok';
}catch(Throwable $e){ $rep[]='table=ERR'; }

/* 2. quote.php edits */
$qf="$root/admin/quote.php"; $qc=file_get_contents($qf); $qc0=$qc;

/* 2a. snapshot on save */
$aSaved="            \$saved = true;";
if(strpos($qc,'tb_quote_versions')===false && strpos($qc,$aSaved)!==false){
  $snap="            \$saved = true;\n"
   ."            try { \$__vs = json_encode(['title'=>(isset(\$pkgTitle)?\$pkgTitle:''),'itinerary'=>(isset(\$itinJson)?\$itinJson:null),'price'=>(isset(\$price)?\$price:null),'breakdown'=>(isset(\$breakdown)?\$breakdown:null)], JSON_UNESCAPED_UNICODE); \$__vn=(int)\$pdo->query('SELECT COALESCE(MAX(version),0)+1 FROM tb_quote_versions WHERE trip_request_id='.(int)\$id)->fetchColumn(); \$pdo->prepare('INSERT INTO tb_quote_versions (trip_request_id,version,source,snapshot,created_by,created_at) VALUES (?,?,?,?,?,NOW())')->execute([\$id,\$__vn,'TEAM',\$__vs,(function_exists('admin_user')&&admin_user()?(int)(admin_user()['id']??0):null)]); } catch (Throwable \$e) {}";
  $qc=str_replace($aSaved,$snap,$qc,$n1); $rep[]='snap='.$n1;
} else { $rep[]='snap=skip'; }

/* 2b. version-history box before h1 */
$aH='<h1>Quote #<?= e((string)$id) ?> &mdash;';
if(strpos($qc,'Version history')===false && strpos($qc,$aH)!==false){
  $box='<?php $__vers = $pdo->query("SELECT id,version,source,created_at FROM tb_quote_versions WHERE trip_request_id=".(int)$id." ORDER BY version DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC); if ($__vers): ?>'."\n"
   .'<div class="box"><strong>Version history</strong>'."\n"
   .'<table style="width:100%;font-size:13px;margin-top:6px">'."\n"
   .'<?php foreach ($__vers as $vv): ?>'."\n"
   .'<tr><td>v<?= (int) $vv[\'version\'] ?></td><td><?= e((string) $vv[\'source\']) ?></td><td><?= e((string) $vv[\'created_at\']) ?></td>'."\n"
   .'<td style="text-align:right"><form method="post" action="quote-version.php" style="margin:0"><?= function_exists(\'csrf_field\') ? csrf_field() : \'\' ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="vid" value="<?= (int) $vv[\'id\'] ?>"><button class="btn ghost small" name="do" value="restore">Restore</button></form></td></tr>'."\n"
   .'<?php endforeach; ?>'."\n"
   .'</table></div>'."\n"
   .'<?php endif; ?>'."\n"
   .$aH;
  $qc=str_replace($aH,$box,$qc,$n2); $rep[]='vbox='.$n2;
} else { $rep[]='vbox=skip'; }

if($qc!==$qc0){ put_lint($qf,$qc,$rep,'quotephp'); } else { $rep[]='quotephp=nochange'; }

/* 3. quote-version.php endpoint */
$src=@file_get_contents("$root/admin/quotes.php"); $prologue='';
if($src){ foreach(explode("\n",$src) as $ln){ $t=trim($ln);
  $ok=($t==='')||strpos($t,'<?php')===0||strpos($t,'declare')===0||strpos($t,'require')===0||strpos($t,'use ')===0||strpos($t,'namespace')===0||strpos($t,'ini_set')===0||strpos($t,'error_reporting')===0||strpos($t,'require_admin')!==false||strpos($t,'admin_user')!==false||strpos($t,'//')===0||strpos($t,'/*')===0||strpos($t,'*')===0||preg_match('/^\$pdo\s*=/',$t)||strpos($t,'rbac')!==false;
  if(!$ok) break; $prologue.=$ln."\n"; } }
if(strpos($prologue,'require_admin')===false){ $rep[]='vendpoint=BAD-prologue'; }
else{
  if(strpos($prologue,'$pdo')===false){ $prologue.="\$pdo = db();\n"; }
  $body = <<<'PHPX'

if (function_exists('csrf_check')) { csrf_check(); }
$id = (int) ($_POST['id'] ?? 0);
$vid = (int) ($_POST['vid'] ?? 0);
if ($id > 0 && $vid > 0 && ($_POST['do'] ?? '') === 'restore') {
  try {
    $st = $pdo->prepare('SELECT snapshot FROM tb_quote_versions WHERE id = ? AND trip_request_id = ?');
    $st->execute([$vid, $id]);
    $s = $st->fetchColumn();
    $d = json_decode((string) $s, true);
    if (is_array($d)) {
      $pdo->prepare('UPDATE tb_quotes SET title = ?, itinerary = ?, customer_price = ?, breakdown = ? WHERE trip_request_id = ?')
          ->execute([$d['title'] ?? '', $d['itinerary'] ?? null, $d['price'] ?? null, $d['breakdown'] ?? null, $id]);
    }
  } catch (Throwable $e) {}
}
$__base = rtrim((string) (function_exists('url') ? url('') : ''), '/');
header('Location: ' . $__base . '/admin/quote.php?id=' . $id);
exit;
PHPX;
  $code=rtrim($prologue,"\n")."\n".$body."\n";
  put_lint("$root/admin/quote-version.php",$code,$rep,'vendpoint');
}

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_pd.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
