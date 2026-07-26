<?php
/* patch20: customer quote link.
   - tb_quotes: add token (unique), accepted, accepted_at; backfill tokens
   - public q.php (read-only customer quote view + Accept button; NO cost/margin)
   - quote.php: generate token on save + show shareable customer link
   Idempotent; lint before write. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
require_once "$root/app/config.php";
require_once "$root/app/db.php";
$errs=[];

/* 1. migrate */
try{
  $pdo=db();
  $cols=[]; foreach($pdo->query("SHOW COLUMNS FROM tb_quotes")->fetchAll(PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=1; }
  if(!isset($cols['token'])){ $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN token VARCHAR(40) NULL"); $pdo->exec("CREATE UNIQUE INDEX uq_quote_token ON tb_quotes (token)"); echo "token added\n"; }
  if(!isset($cols['accepted'])){ $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN accepted TINYINT NOT NULL DEFAULT 0"); echo "accepted added\n"; }
  if(!isset($cols['accepted_at'])){ $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN accepted_at DATETIME NULL"); echo "accepted_at added\n"; }
  // backfill tokens
  $need=$pdo->query("SELECT id FROM tb_quotes WHERE token IS NULL OR token=''")->fetchAll(PDO::FETCH_COLUMN);
  $up=$pdo->prepare("UPDATE tb_quotes SET token=? WHERE id=?");
  foreach($need as $qid){ $up->execute([bin2hex(random_bytes(8)),(int)$qid]); }
  echo "tokens backfilled: ".count($need)."\n";
}catch(Throwable $e){ echo "DB error: ".$e->getMessage()."\n"; $errs[]='db'; }

function put_lint($path,$code,&$errs,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ echo "$tag lint FAIL:\n".implode("\n",$o)."\n"; $errs[]=$tag; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patch20');
  file_put_contents($path,$code); echo "$tag written\n"; return true;
}

/* 2. public q.php */
$q = <<<'PHP'
<?php
/* Public, read-only customer quote view. No admin auth. No cost/margin shown. */
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
@require_once __DIR__ . '/app/notify.php';

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['t'] ?? ''));
$quote = null;
if ($token !== '') {
    try {
        $st = db()->prepare(
            'SELECT q.*, t.name, t.regions, t.nights, t.start_date, t.end_date, t.adults, t.children,
                    t.occasion, t.hotels_selected
             FROM tb_quotes q JOIN trip_requests t ON t.id = q.trip_request_id
             WHERE q.token = ? LIMIT 1'
        );
        $st->execute([$token]);
        $quote = $st->fetch();
    } catch (Throwable $e) { $quote = null; }
}

$done = false;
if ($quote && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'accept') {
    try {
        db()->prepare('UPDATE tb_quotes SET accepted = 1, accepted_at = NOW() WHERE token = ?')->execute([$token]);
        $quote['accepted'] = 1;
        $done = true;
        if (function_exists('lm_notify')) {
            try { lm_notify('✅ Quote accepted by customer — ' . (string) $quote['name'] . ' (₹' . number_format((float) $quote['customer_price']) . ')'); } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}
}

$rup = static function ($n): string { return '₹' . number_format((float) $n); };
$hotels = [];
if ($quote) { $hotels = json_decode((string) $quote['hotels_selected'], true); if (!is_array($hotels)) { $hotels = []; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $quote ? 'Your Kerala quote' : 'Quote not found' ?> · Lumiere Holidays</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap">
<style>
:root{--ivory:#FAF7F2;--ink:#1E1E1C;--gold:#B08D57;--sage:#6F7D6B;--rule:rgba(30,30,28,.12)}
*{box-sizing:border-box;margin:0}
body{background:var(--ivory);color:var(--ink);font:16px/1.65 Inter,system-ui,sans-serif;padding:0 0 60px}
.top{background:#fff;border-bottom:1px solid var(--rule);padding:18px 24px}
.brand{font-family:'Cormorant Garamond',serif;font-size:24px;letter-spacing:.04em}
.wrap{max-width:680px;margin:0 auto;padding:30px 22px}
.card{background:#fff;border:1px solid var(--rule);border-radius:14px;padding:28px 30px;margin:18px 0}
h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:32px;margin-bottom:6px}
.sub{color:var(--sage);font-size:14px}
.row{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px dashed var(--rule);font-size:15px}
.row:last-child{border-bottom:0}
.row .k{color:var(--sage)}
.hotel{padding:10px 0;border-bottom:1px solid var(--rule)}
.hotel:last-child{border-bottom:0}
.hotel .nm{font-weight:600}
.hotel .mt{color:var(--sage);font-size:13px}
.total{background:var(--ink);color:var(--ivory);border-radius:14px;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;margin:18px 0}
.total .lbl{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:#cdb892}
.total .amt{font-family:'Cormorant Garamond',serif;font-size:34px}
.btn{display:inline-block;background:var(--gold);color:#fff;border:0;border-radius:8px;padding:14px 30px;font:600 15px Inter,sans-serif;cursor:pointer;text-decoration:none}
.btn:hover{background:#9a7947}
.ok{background:#e4f5ec;border:1px solid #0d7a4f;color:#0d7a4f;border-radius:10px;padding:14px 18px;margin:16px 0;font-weight:600}
.muted{color:var(--sage);font-size:13px}
a{color:var(--gold)}
</style>
</head>
<body>
<div class="top"><span class="brand">Lumiere Holidays</span></div>
<div class="wrap">
<?php if (!$quote): ?>
  <div class="card"><h1>Quote not found</h1><p class="sub">This link is invalid or has expired. Please contact us for a fresh quote.</p></div>
<?php else: ?>
  <h1>Your Kerala journey</h1>
  <p class="sub">Prepared for <?= e((string) $quote['name']) ?><?= $quote['occasion'] ? ' · ' . e((string) $quote['occasion']) : '' ?></p>

  <?php if ($done || (int) $quote['accepted'] === 1): ?>
    <div class="ok">✓ Thank you — your acceptance is recorded. Our team will be in touch to finalise the details.</div>
  <?php endif; ?>

  <div class="card">
    <div class="row"><span class="k">Route</span><span><?= e($quote['regions'] !== '' ? str_replace(',', ' → ', (string) $quote['regions']) : 'To be planned') ?></span></div>
    <div class="row"><span class="k">Duration</span><span><?= e((string) (int) $quote['nights']) ?> nights</span></div>
    <?php if ($quote['start_date']): ?><div class="row"><span class="k">Travel dates</span><span><?= e((string) $quote['start_date']) ?><?= $quote['end_date'] ? ' → ' . e((string) $quote['end_date']) : '' ?></span></div><?php endif; ?>
    <div class="row"><span class="k">Travellers</span><span><?= e((string) (int) $quote['adults']) ?> adults<?= (int) $quote['children'] > 0 ? ', ' . e((string) (int) $quote['children']) . ' children' : '' ?></span></div>
  </div>

  <?php if ($hotels): ?>
  <div class="card">
    <h2 style="font-family:'Cormorant Garamond',serif;font-weight:600;font-size:22px;margin-bottom:8px">Your stays</h2>
    <?php foreach ($hotels as $h): ?>
    <div class="hotel">
      <div class="nm"><?= e((string) ($h['name'] ?? '')) ?><?= !empty($h['star']) ? ' · ' . e((string) $h['star']) : '' ?></div>
      <div class="mt"><?= e((string) ($h['stop'] ?? '')) ?><?= !empty($h['room']) ? ' · ' . e((string) $h['room']) : '' ?><?= !empty($h['meal']) ? ' · ' . e((string) $h['meal']) : '' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="total"><span class="lbl">Total package price</span><span class="amt"><?= e($rup($quote['customer_price'])) ?></span></div>
  <p class="muted">Inclusive of stays and private transport as listed. Taxes as applicable.</p>

  <?php if ((int) $quote['accepted'] !== 1): ?>
  <form method="post" style="margin-top:18px">
    <input type="hidden" name="action" value="accept">
    <button class="btn" type="submit">Accept this quote</button>
  </form>
  <p class="muted" style="margin-top:12px">Questions or changes? Reply to the email this link came from and our team will adjust it.</p>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
PHP;
put_lint("$root/q.php",$q,$errs,'q.php');

/* 3. quote.php: token on save + share link box */
$qf="$root/admin/quote.php";
$c=file_get_contents($qf);
$c0=$c;
if(strpos($c,"SET token = ?")===false){
  $c=str_replace("            \$saved = true;",
    "            \$saved = true;\n            try { \$pdo->prepare('UPDATE tb_quotes SET token = ? WHERE trip_request_id = ? AND (token IS NULL OR token = \\'\\')')->execute([bin2hex(random_bytes(8)), \$id]); } catch (Throwable \$e) {}",
    $c,$cs);
  echo "token-on-save applied=".(int)$cs."\n"; if($cs!==1){ $errs[]='qsave'; }
}
if(strpos($c,'Customer link')===false){
  $anchor='<div class="qt"><?php if (!$showCost): ?><style>.qt .internal{display:none}</style><?php endif; ?>';
  $box=$anchor."\n"
    ."<?php \$__q = \$pdo->query('SELECT token, accepted FROM tb_quotes WHERE trip_request_id = ' . (int) \$id)->fetch(); if (\$__q && !empty(\$__q['token'])): \$__link = rtrim((string) url(''), '/') . '/q.php?t=' . \$__q['token']; ?>\n"
    ."<div class=\"box\"><strong>Customer link</strong> &mdash; read-only quote to share: <a href=\"<?= e(\$__link) ?>\" target=\"_blank\"><?= e(\$__link) ?></a> <?= (int) \$__q['accepted'] === 1 ? '<span class=\"pill on\">Accepted</span>' : '<span class=\"pill off\">Not accepted</span>' ?></div>\n"
    ."<?php endif; ?>";
  $c=str_replace($anchor,$box,$c,$cb);
  echo "share-box applied=".(int)$cb."\n"; if($cb!==1){ $errs[]='qbox'; }
}
if($c!==$c0 && !in_array('qsave',$errs) && !in_array('qbox',$errs)){ put_lint($qf,$c,$errs,'quote'); }
else if($c===$c0){ echo "quote.php already patched\n"; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\n";
echo (empty($errs)?"DONE ok\n":"DONE issues: ".implode(',',$errs)."\n");
