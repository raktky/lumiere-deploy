<?php
/* patchB: publish to catalog.
   - public tours.php (list of published packages)
   - public tour.php?slug= (detail: itinerary, no cost, enquire button)
   - admin/package.php: show public catalog link when published
   Idempotent; lint before write; status report. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$rep=[];

function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchB');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* shared head/css */
$CSS = <<<'CSS'
:root{--ivory:#FAF7F2;--ink:#1E1E1C;--gold:#B08D57;--sage:#6F7D6B;--rule:rgba(30,30,28,.12)}
*{box-sizing:border-box;margin:0}
body{background:var(--ivory);color:var(--ink);font:16px/1.65 Inter,system-ui,sans-serif;padding:0 0 60px}
.top{background:#fff;border-bottom:1px solid var(--rule);padding:18px 24px}
.brand{font-family:'Cormorant Garamond',serif;font-size:24px;letter-spacing:.04em}
.wrap{max-width:960px;margin:0 auto;padding:30px 22px}
.wrapn{max-width:680px;margin:0 auto;padding:30px 22px}
h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:32px;margin-bottom:6px}
.sub{color:var(--sage);font-size:14px;margin-bottom:8px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-top:18px}
.pcard{background:#fff;border:1px solid var(--rule);border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column}
.pcard .ph{height:150px;background:#eee var(--bg) center/cover no-repeat}
.pcard .pb{padding:16px 18px;flex:1;display:flex;flex-direction:column}
.pcard .pt{font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:600}
.pcard .pm{color:var(--sage);font-size:13px;margin:4px 0 10px}
.pcard .pp{margin-top:auto;font-weight:600;color:var(--gold)}
.card{background:#fff;border:1px solid var(--rule);border-radius:14px;padding:28px 30px;margin:18px 0}
.hero{height:280px;border-radius:14px;background:#ddd var(--bg) center/cover no-repeat;margin:0 0 18px}
.row{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px dashed var(--rule);font-size:15px}
.row:last-child{border-bottom:0}.row .k{color:var(--sage)}
.hotel{padding:10px 0;border-bottom:1px solid var(--rule)}.hotel:last-child{border-bottom:0}
.hotel .nm{font-weight:600}.hotel .mt{color:var(--sage);font-size:13px}
.total{background:var(--ink);color:var(--ivory);border-radius:14px;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;margin:18px 0}
.total .lbl{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:#cdb892}
.total .amt{font-family:'Cormorant Garamond',serif;font-size:30px}
.btn{display:inline-block;background:var(--gold);color:#fff;border:0;border-radius:8px;padding:14px 30px;font:600 15px Inter,sans-serif;cursor:pointer;text-decoration:none}
.btn:hover{background:#9a7947}.muted{color:var(--sage);font-size:13px}a{color:var(--gold)}
CSS;

/* ---------- tours.php (listing) ---------- */
$tours = '<?php'."\n"
.'/* Public catalog — published packages. */'."\n"
.'declare(strict_types=1);'."\n"
.'require __DIR__ . \'/app/bootstrap.php\';'."\n"
.'$rows = [];'."\n"
.'try { $rows = db()->query("SELECT id, title, slug, regions, nights, hero, summary, price FROM tb_packages WHERE published = 1 ORDER BY updated_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $rows = []; }'."\n"
.'$rup = static function ($n): string { return \'₹\' . number_format((float) $n); };'."\n"
.'?>'."\n"
.'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'."\n"
.'<title>Kerala Tour Packages · Lumiere Holidays</title>'."\n"
.'<meta name="description" content="Curated Kerala tour packages by Lumiere Holidays — handcrafted itineraries, private transport and premium stays.">'."\n"
.'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap">'."\n"
.'<style>'.$CSS.'</style></head><body>'."\n"
.'<div class="top"><span class="brand">Lumiere Holidays</span></div>'."\n"
.'<div class="wrap">'."\n"
.'<h1>Kerala tour packages</h1><p class="sub">Handcrafted journeys — pick one and we tailor it to you.</p>'."\n"
.'<?php if (!$rows): ?><div class="card"><p class="muted">New packages are being prepared. Please check back soon.</p></div><?php else: ?>'."\n"
.'<div class="grid">'."\n"
.'<?php foreach ($rows as $r): $link = \'tour.php?slug=\' . rawurlencode((string) $r[\'slug\']); ?>'."\n"
.'  <a class="pcard" href="<?= e($link) ?>">'."\n"
.'    <?php if (!empty($r[\'hero\'])): ?><div class="ph" style="--bg:url(\'<?= e((string) $r[\'hero\']) ?>\')"></div><?php endif; ?>'."\n"
.'    <div class="pb"><div class="pt"><?= e((string) $r[\'title\']) ?></div>'."\n"
.'      <div class="pm"><?= e($r[\'regions\'] !== \'\' ? str_replace(\',\', \' · \', (string) $r[\'regions\']) : \'\') ?><?= (int) $r[\'nights\'] > 0 ? \' · \' . (int) $r[\'nights\'] . \'N\' : \'\' ?></div>'."\n"
.'      <?php if ((float) $r[\'price\'] > 0): ?><div class="pp">From <?= e($rup($r[\'price\'])) ?></div><?php endif; ?>'."\n"
.'    </div></a>'."\n"
.'<?php endforeach; ?>'."\n"
.'</div><?php endif; ?>'."\n"
.'</div></body></html>'."\n";
put_lint("$root/tours.php",$tours,$rep,'tours');

/* ---------- tour.php (detail) ---------- */
$tour = '<?php'."\n"
.'/* Public package detail. */'."\n"
.'declare(strict_types=1);'."\n"
.'require __DIR__ . \'/app/bootstrap.php\';'."\n"
.'$slug = preg_replace(\'/[^a-z0-9\\-]/i\', \'\', (string) ($_GET[\'slug\'] ?? \'\'));'."\n"
.'$p = null;'."\n"
.'if ($slug !== \'\') { try { $st = db()->prepare("SELECT * FROM tb_packages WHERE slug = ? AND published = 1 ORDER BY id DESC LIMIT 1"); $st->execute([$slug]); $p = $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) { $p = null; } }'."\n"
.'$rup = static function ($n): string { return \'₹\' . number_format((float) $n); };'."\n"
.'$itin = $p ? json_decode((string) ($p[\'itinerary\'] ?? \'\'), true) : null; if (!is_array($itin)) { $itin = []; }'."\n"
.'?>'."\n"
.'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'."\n"
.'<title><?= $p ? e((string) $p[\'title\']) . \' · \' : \'Package not found · \' ?>Lumiere Holidays</title>'."\n"
.'<?php if ($p && !empty($p[\'summary\'])): ?><meta name="description" content="<?= e(mb_substr((string) $p[\'summary\'], 0, 155)) ?>"><?php endif; ?>'."\n"
.'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap">'."\n"
.'<style>'.$CSS.'</style></head><body>'."\n"
.'<div class="top"><span class="brand">Lumiere Holidays</span></div>'."\n"
.'<div class="wrapn">'."\n"
.'<?php if (!$p): ?><div class="card"><h1>Package not found</h1><p class="sub">This package may have been unpublished. <a href="tours.php">See all packages</a>.</p></div><?php else: ?>'."\n"
.'<?php if (!empty($p[\'hero\'])): ?><div class="hero" style="--bg:url(\'<?= e((string) $p[\'hero\']) ?>\')"></div><?php endif; ?>'."\n"
.'<h1><?= e((string) $p[\'title\']) ?></h1>'."\n"
.'<p class="sub"><?= e($p[\'regions\'] !== \'\' ? str_replace(\',\', \' · \', (string) $p[\'regions\']) : \'\') ?><?= (int) $p[\'nights\'] > 0 ? \' · \' . (int) $p[\'nights\'] . \' nights\' : \'\' ?></p>'."\n"
.'<?php if (!empty($p[\'summary\'])): ?><div class="card"><?= nl2br(e((string) $p[\'summary\'])) ?></div><?php endif; ?>'."\n"
.'<?php if ($itin): ?><div class="card"><h2 style="font-family:\'Cormorant Garamond\',serif;font-weight:600;font-size:22px;margin-bottom:10px">Day-by-day plan</h2>'."\n"
.'<?php foreach ($itin as $d): ?><div class="hotel"><div class="nm">Day <?= e((string) (int) ($d[\'day\'] ?? 0)) ?><?= !empty($d[\'title\']) ? \' — \' . e((string) $d[\'title\']) : \'\' ?></div>'."\n"
.'<?php $bits=[]; if(!empty($d[\'hotel\'])){$bits[]=\'Stay: \'.$d[\'hotel\'];} if(!empty($d[\'meal\'])){$bits[]=\'Meals: \'.$d[\'meal\'];} if(!empty($d[\'transport\'])){$bits[]=(string)$d[\'transport\'];} if($bits): ?><div class="mt"><?= e(implode(\' · \', $bits)) ?></div><?php endif; ?>'."\n"
.'<?php if(!empty($d[\'items\'])&&is_array($d[\'items\'])): ?><ul style="margin:6px 0 0 18px;padding:0;font-size:14px;color:#333"><?php foreach($d[\'items\'] as $it): if(!empty($it[\'n\'])): ?><li><?= e((string) $it[\'n\']) ?></li><?php endif; endforeach; ?></ul><?php endif; ?>'."\n"
.'</div><?php endforeach; ?></div><?php endif; ?>'."\n"
.'<?php if ((float) $p[\'price\'] > 0): ?><div class="total"><span class="lbl">From</span><span class="amt"><?= e($rup($p[\'price\'])) ?> <span style="font-size:14px;color:#cdb892">per package</span></span></div><?php endif; ?>'."\n"
.'<p style="margin:18px 0"><a class="btn" href="/kerala-tour-package-builder">Customise &amp; enquire</a></p>'."\n"
.'<p class="muted">Prices indicative and tailored to your dates, party size and hotel choice.</p>'."\n"
.'<?php endif; ?>'."\n"
.'</div></body></html>'."\n";
put_lint("$root/tour.php",$tour,$rep,'tour');

/* ---------- admin/package.php public link ---------- */
$pf="$root/admin/package.php";
$pc=@file_get_contents($pf);
if($pc){
  $anchor='> Published (visible in catalog)</label>';
  if(strpos($pc,'Public catalog link')===false && strpos($pc,$anchor)!==false){
    $ins=$anchor."\n"
      ."    <?php if (!empty(\$pkg['id']) && !empty(\$pkg['published']) && !empty(\$pkg['slug'])): \$__pub = rtrim((string) (function_exists('url') ? url('') : ''), '/') . '/tour.php?slug=' . rawurlencode((string) \$pkg['slug']); ?>\n"
      ."    <div style=\"margin-top:10px;padding:10px 12px;background:#f4efe7;border-radius:8px\">Public catalog link: <a href=\"<?= e(\$__pub) ?>\" target=\"_blank\"><?= e(\$__pub) ?></a></div>\n"
      ."    <?php endif; ?>";
    $pc2=str_replace($anchor,$ins,$pc,$n);
    if($n===1){ put_lint($pf,$pc2,$rep,'adminlink'); } else { $rep[]='adminlink=anchorfail'; }
  } else { $rep[]='adminlink=already/anchorfail'; }
}

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_pb.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
