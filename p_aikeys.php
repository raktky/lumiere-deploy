<?php
$root='/var/www/lumiere/experience';
$t=$root.'/admin/ai-keys.php';
@mkdir(dirname($t),0775,true);
$s = <<<'ENDOFFILE7'
<?php
declare(strict_types=1);
/* admin/ai-keys.php — one place to hold BOTH provider keys + models and switch active provider. */
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/admin_ui.php';
require_admin();
$pdo = db();

function col_exists(PDO $pdo, string $c): bool { $s=$pdo->query("SHOW COLUMNS FROM tb_ai LIKE ".$pdo->quote($c)); return (bool)($s&&$s->fetch()); }

$ok=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    if (function_exists('csrf_check')) { try{ csrf_check(); }catch(Throwable $e){} }
    try {
        $provider = ($_POST['provider'] ?? 'gemini')==='anthropic' ? 'anthropic' : 'gemini';
        $mg = trim((string)($_POST['model_gemini'] ?? '')) ?: 'gemini-2.0-flash';
        $ma = trim((string)($_POST['model_anthropic'] ?? '')) ?: 'claude-3-5-sonnet-20241022';
        $credits = (int)($_POST['credits'] ?? 0);
        $kg = trim((string)($_POST['key_gemini'] ?? ''));
        $ka = trim((string)($_POST['key_anthropic'] ?? ''));
        $sets=['provider=?','model_gemini=?','model_anthropic=?','credits=?']; $vals=[$provider,$mg,$ma,$credits];
        // only overwrite a key when a real new value is typed (not blank, not the mask)
        if ($kg!=='' && strpos($kg,'*')===false) { $sets[]='key_gemini=?'; $vals[]=$kg; }
        if ($ka!=='' && strpos($ka,'*')===false) { $sets[]='key_anthropic=?'; $vals[]=$ka; }
        $vals[]=1;
        $pdo->prepare("UPDATE tb_ai SET ".implode(',', $sets).", updated_at=NOW() WHERE id=?")->execute($vals);
        $ok='Saved. Active provider: '.strtoupper($provider).'.';
    } catch (Throwable $e) { $err=$e->getMessage(); }
}

$row = $pdo->query("SELECT * FROM tb_ai WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
$mask = function($k){ $k=(string)$k; return $k==='' ? '' : str_repeat('*',8).substr($k,-4); };
$provider = ($row['provider'] ?? 'gemini');
$credits = (int)($row['credits'] ?? 0);
$mg = (string)($row['model_gemini'] ?? 'gemini-2.0-flash');
$ma = (string)($row['model_anthropic'] ?? 'claude-3-5-sonnet-20241022');
$gset = trim((string)($row['key_gemini'] ?? ''))!=='';
$aset = trim((string)($row['key_anthropic'] ?? ''))!=='';

admin_header('AI Keys', 'ai_keys');
flash_show();
?>
<style>
.kwrap{max-width:640px}
.kwrap .card{background:#fff;border:1px solid var(--rule);border-radius:10px;padding:18px 20px;margin:14px 0}
.kwrap h2{font-family:'Cormorant Garamond',serif;font-size:22px;margin:0 0 10px}
.kwrap .prov{display:flex;gap:10px}
.kwrap .prov label{flex:1;border:1px solid var(--rule);border-radius:8px;padding:12px 14px;cursor:pointer;display:flex;gap:8px;align-items:center;font-weight:600;margin:0}
.kwrap .prov input{width:auto;margin:0}
.kwrap .set{font-size:12px;color:var(--sage)}
.kwrap .on{color:#0d7a4f;font-weight:600}
.kwrap .off{color:#b5544a}
.kwrap .help{font-size:12px;color:var(--sage);margin-top:4px}
</style>
<div class="kwrap">
<h1>AI Keys</h1>
<p class="muted">Store both keys once. Pick which one the package builder uses. No re-pasting when you switch.</p>
<?php if($ok):?><div class="flash"><?= e($ok) ?></div><?php endif;?>
<?php if($err):?><div class="flash err"><?= e($err) ?></div><?php endif;?>
<form method="post">
<?= function_exists('csrf_field') ? csrf_field() : '' ?>
<div class="card"><h2>Active provider</h2>
<div class="prov">
<label><input type="radio" name="provider" value="gemini" <?= $provider!=='anthropic'?'checked':'' ?>> Gemini <span class="set <?= $gset?'on':'off' ?>"><?= $gset?'key set':'no key' ?></span></label>
<label><input type="radio" name="provider" value="anthropic" <?= $provider==='anthropic'?'checked':'' ?>> Claude <span class="set <?= $aset?'on':'off' ?>"><?= $aset?'key set':'no key' ?></span></label>
</div></div>

<div class="card"><h2>Gemini (Google AI Studio)</h2>
<label>Gemini API key</label>
<input type="password" name="key_gemini" autocomplete="off" placeholder="<?= $gset ? e($mask($row['key_gemini'])) : 'AIza...' ?>">
<div class="help">Leave blank to keep current. Needs billing/prepay on the Google project.</div>
<label style="margin-top:10px">Gemini model</label>
<input type="text" name="model_gemini" value="<?= e($mg) ?>">
</div>

<div class="card"><h2>Claude (Anthropic)</h2>
<label>Claude API key</label>
<input type="password" name="key_anthropic" autocomplete="off" placeholder="<?= $aset ? e($mask($row['key_anthropic'])) : 'sk-ant-...' ?>">
<div class="help">Leave blank to keep current. Needs prepaid credit in the Anthropic console.</div>
<label style="margin-top:10px">Claude model</label>
<input type="text" name="model_anthropic" value="<?= e($ma) ?>">
</div>

<div class="card"><h2>Credits</h2>
<label>Internal credit balance (each generate uses 1)</label>
<input type="number" name="credits" value="<?= $credits ?>">
</div>
<button class="btn" type="submit">Save AI keys</button>
</form>
</div>
<?php admin_footer();
ENDOFFILE7;
$bak=$t.'.bak'; if(is_file($t)&&!is_file($bak)) @copy($t,$bak);
file_put_contents($t,$s);
$out=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$out,$rc);
if($rc!==0 && is_file($bak)){ @copy($bak,$t); }
file_put_contents($root.'/_dep_aikeys.txt',json_encode(['rc'=>$rc,'len'=>strlen($s),'crc'=>hash('crc32b',$s),'restored'=>($rc!==0)]));
echo 'AIK';
