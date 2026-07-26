<?php
/* patchE: branded printable quote (Save-as-PDF).
   - public quote-pdf.php?t=token (A4 print layout, no cost/margin, Print button)
   - q.php: "Print / Save as PDF" link
   - admin/quote.php: PDF link in customer-link box
   Idempotent; lint; status report. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$rep=[];
function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchE');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* quote-pdf.php */
$pdf = <<<'PHP'
<?php
/* Public branded printable quote. No cost/margin. */
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['t'] ?? ''));
$q = null;
if ($token !== '') {
    try {
        $st = db()->prepare('SELECT q.*, t.name, t.regions, t.nights, t.start_date, t.end_date, t.adults, t.children, t.occasion, t.hotels_selected FROM tb_quotes q JOIN trip_requests t ON t.id = q.trip_request_id WHERE q.token = ? LIMIT 1');
        $st->execute([$token]);
        $q = $st->fetch();
    } catch (Throwable $e) { $q = null; }
}
if ($q) { $blk = (!empty($q['revoked_at'])) || (!empty($q['valid_until']) && (string) $q['valid_until'] < date('Y-m-d')); if ($blk) { $q = null; } }
$rup = static function ($n): string { return '₹' . number_format((float) $n); };
$itin = $q ? json_decode((string) ($q['itinerary'] ?? ''), true) : null; if (!is_array($itin)) { $itin = []; }
$hotels = $q ? json_decode((string) ($q['hotels_selected'] ?? ''), true) : null; if (!is_array($hotels)) { $hotels = []; }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $q ? 'Kerala itinerary' : 'Not found' ?> · Lumiere Holidays</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap">
<style>
:root{--ink:#1E1E1C;--gold:#B08D57;--sage:#6F7D6B;--rule:#e3ddd3}
*{box-sizing:border-box;margin:0}
body{font:14px/1.6 Inter,system-ui,sans-serif;color:var(--ink);background:#f0ece5;padding:20px}
.sheet{max-width:800px;margin:0 auto;background:#fff;padding:44px 48px;box-shadow:0 2px 20px rgba(0,0,0,.08)}
.hd{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid var(--gold);padding-bottom:14px;margin-bottom:22px}
.brand{font-family:'Cormorant Garamond',serif;font-size:30px;letter-spacing:.03em}
.brand span{display:block;font-family:Inter;font-size:11px;letter-spacing:.22em;color:var(--sage);text-transform:uppercase}
h1{font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;margin-bottom:4px}
.meta{color:var(--sage);font-size:13px;margin-bottom:20px}
.sec{font-family:'Cormorant Garamond',serif;font-size:19px;color:var(--gold);margin:22px 0 8px}
.day{border-left:2px solid var(--gold);padding:2px 0 8px 14px;margin:10px 0}
.day .dn{font-weight:600}
.day .mt{color:var(--sage);font-size:12.5px;margin:2px 0}
.day ul{margin:5px 0 0 18px;font-size:13px}
.hotel{padding:6px 0;border-bottom:1px solid var(--rule)}
.hotel:last-child{border-bottom:0}
.tot{margin-top:22px;background:var(--ink);color:#fff;border-radius:10px;padding:16px 22px;display:flex;justify-content:space-between;align-items:center}
.tot .l{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#cdb892}
.tot .a{font-family:'Cormorant Garamond',serif;font-size:26px}
.foot{margin-top:22px;color:var(--sage);font-size:12px;border-top:1px solid var(--rule);padding-top:12px}
.pbar{max-width:800px;margin:0 auto 14px;text-align:right}
.btn{display:inline-block;background:var(--gold);color:#fff;border:0;border-radius:7px;padding:10px 22px;font:600 14px Inter;cursor:pointer;text-decoration:none}
@media print{body{background:#fff;padding:0}.sheet{box-shadow:none;max-width:none;padding:24px 20px}.pbar{display:none}}
</style></head><body>
<?php if (!$q): ?>
<div class="sheet"><h1>Quote not found</h1><p class="meta">This link is invalid, withdrawn or expired.</p></div>
<?php else: ?>
<div class="pbar"><a class="btn" href="#" onclick="window.print();return false;">Print / Save as PDF</a></div>
<div class="sheet">
  <div class="hd"><div class="brand">Lumiere Holidays<span>Kerala Journeys</span></div>
    <div style="text-align:right;font-size:12px;color:var(--sage)">Prepared for<br><strong style="color:var(--ink);font-size:14px"><?= e((string) $q['name']) ?></strong></div></div>
  <h1><?= e($q['regions'] !== '' ? str_replace(',', ' · ', (string) $q['regions']) : 'Your Kerala journey') ?></h1>
  <div class="meta"><?= (int) $q['nights'] > 0 ? (int) $q['nights'] . ' nights' : '' ?><?= $q['start_date'] ? ' · ' . e((string) $q['start_date']) . ($q['end_date'] ? ' → ' . e((string) $q['end_date']) : '') : '' ?> · <?= (int) $q['adults'] ?> adults<?= (int) $q['children'] > 0 ? ', ' . (int) $q['children'] . ' children' : '' ?></div>
  <?php if ($hotels): ?><div class="sec">Your stays</div><?php foreach ($hotels as $h): ?>
  <div class="hotel"><strong><?= e((string) ($h['name'] ?? '')) ?></strong><?= !empty($h['star']) ? ' · ' . e((string) $h['star']) : '' ?><div class="mt"><?= e((string) ($h['stop'] ?? '')) ?><?= !empty($h['room']) ? ' · ' . e((string) $h['room']) : '' ?><?= !empty($h['meal']) ? ' · ' . e((string) $h['meal']) : '' ?></div></div>
  <?php endforeach; endif; ?>
  <?php if ($itin): ?><div class="sec">Day-by-day plan</div><?php foreach ($itin as $d): ?>
  <div class="day"><div class="dn">Day <?= (int) ($d['day'] ?? 0) ?><?= !empty($d['title']) ? ' — ' . e((string) $d['title']) : '' ?></div>
  <?php $b=[]; if(!empty($d['hotel'])){$b[]='Stay: '.$d['hotel'];} if(!empty($d['meal'])){$b[]='Meals: '.$d['meal'];} if(!empty($d['transport'])){$b[]=(string)$d['transport'];} if($b): ?><div class="mt"><?= e(implode(' · ', $b)) ?></div><?php endif; ?>
  <?php if(!empty($d['items'])&&is_array($d['items'])): ?><ul><?php foreach($d['items'] as $it): if(!empty($it['n'])): ?><li><?= e((string) $it['n']) ?></li><?php endif; endforeach; ?></ul><?php endif; ?>
  </div><?php endforeach; endif; ?>
  <?php if ((float) $q['customer_price'] > 0): ?><div class="tot"><span class="l">Total package price</span><span class="a"><?= e($rup($q['customer_price'])) ?></span></div><?php endif; ?>
  <div class="foot">Inclusive of stays and private transport as listed. Taxes as applicable. Lumiere Holidays · lumiereholidays.com</div>
</div>
<?php endif; ?>
</body></html>
PHP;
put_lint("$root/quote-pdf.php",$pdf,$rep,'pdf');

/* q.php link after the muted total note */
$pf="$root/q.php"; $pc=file_get_contents($pf);
$aM='<p class="muted">Inclusive of stays and private transport as listed. Taxes as applicable.</p>';
if(strpos($pc,'quote-pdf.php')===false && strpos($pc,$aM)!==false){
  $ins=$aM."\n  <p style=\"margin-top:8px\"><a href=\"quote-pdf.php?t=<?= e(\$token) ?>\" target=\"_blank\" style=\"color:var(--gold)\">Print / Save as PDF</a></p>";
  $pc=str_replace($aM,$ins,$pc,$n); $rep[]='qlink='.$n;
  if($n===1){ put_lint($pf,$pc,$rep,'qphp'); } else { $rep[]='qphp=anchorfail'; }
} else { $rep[]='qlink=skip'; }

/* admin quote.php PDF link in customer-link box */
$qf="$root/admin/quote.php"; $qc=file_get_contents($qf);
$aBox='read-only quote to share:';
if(strpos($qc,'quote-pdf.php')===false && strpos($qc,$aBox)!==false){
  $qc=str_replace(
    '<a href="<?= e($__link) ?>" target="_blank"><?= e($__link) ?></a>',
    '<a href="<?= e($__link) ?>" target="_blank"><?= e($__link) ?></a> &middot; <a href="quote-pdf.php?t=<?= e((string) $__q[\'token\']) ?>" target="_blank">PDF</a>',
    $qc,$n2); $rep[]='adminpdf='.$n2;
  if($n2===1){ put_lint($qf,$qc,$rep,'quotephp'); } else { $rep[]='quotephp=anchorfail'; }
} else { $rep[]='adminpdf=skip'; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_pe.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
