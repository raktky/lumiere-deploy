<?php
$root='/var/www/lumiere/experience';
$t=$root.'/admin/package-pdf.php';
@mkdir(dirname($t),0775,true);
$s = <<<'ENDOFFILE7'
<?php
declare(strict_types=1);
/* admin/package-pdf.php — renders a DRAFT quote as the branded green mobile PDF (print-to-PDF).
   Access by token: package-pdf.php?t=<token>. Mirrors the approved green design; one page per destination. */
require_once __DIR__ . '/../app/ai.php';   // pulls bootstrap (db) + ai_cfg
$pdo = db();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function rup($n){ return '₹' . number_format((float)$n, 0, '.', ','); }

$tok = trim((string)($_GET['t'] ?? ''));
if ($tok === '') { http_response_code(400); echo 'Missing token.'; exit; }
$q = null;
try { $s = $pdo->prepare("SELECT * FROM tb_quotes WHERE token=?"); $s->execute([$tok]); $q = $s->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
if (!$q) { http_response_code(404); echo 'Package not found.'; exit; }

$tr = null;
try { $s = $pdo->prepare("SELECT * FROM trip_requests WHERE id=?"); $s->execute([(int)$q['trip_request_id']]); $tr = $s->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$tr = $tr ?: [];

$days = json_decode((string)($q['itinerary'] ?? '[]'), true);
if (!is_array($days)) $days = [];
$title  = (string)($q['title'] ?: 'Your Kerala Journey');
$net    = (float)($q['customer_price'] ?? 0);
$nights = (int)($tr['nights'] ?? max(0, count($days) - 1));
$adults = max(1, (int)($tr['adults'] ?? 2));
$children = max(0, (int)($tr['children'] ?? 0));
$heads  = max(1, $adults + $children);
$GTOTAL = (int)round($net * 1.05);
$PP     = (int)round($GTOTAL / $heads);
$regions = array_values(array_filter(array_map('trim', explode(',', (string)($tr['regions'] ?? '')))));

/* showcase points (blurb + image), for matching */
$points = [];
try {
    foreach ($pdo->query("SELECT name,image,blurb FROM tb_points WHERE blurb IS NOT NULL AND blurb<>''") as $p) {
        $points[] = $p;
    }
} catch (Throwable $e) {}
$match_point = function(string $dest) use ($points) {
    $d = strtolower(trim($dest));
    if ($d === '') return null;
    foreach ($points as $p) {
        $pn = strtolower((string)$p['name']);
        if ($pn === $d) return $p;
    }
    foreach ($points as $p) {
        $pn = strtolower((string)$p['name']);
        if ($pn !== '' && (strpos($d, $pn) !== false || strpos($pn, $d) !== false)) return $p;
    }
    return null;
};

/* assign each itinerary day to a destination bucket */
$buckets = [];              // dest => ['point'=>, 'days'=>[], 'hotels'=>[]]
$order = [];
foreach ($regions as $r) { $buckets[$r] = ['point'=>$match_point($r), 'days'=>[], 'hotels'=>[]]; $order[] = $r; }
if (!$order) { $order[] = 'Kerala'; $buckets['Kerala'] = ['point'=>null,'days'=>[],'hotels'=>[]]; }
$cur = $order[0];
foreach ($days as $d) {
    $hay = strtolower(($d['title'] ?? '') . ' ' . ($d['hotel'] ?? '') . ' ' . ($d['transport'] ?? ''));
    foreach ($order as $r) { if (strpos($hay, strtolower($r)) !== false) { $cur = $r; break; } }
    $buckets[$cur]['days'][] = $d;
    $hn = trim((string)($d['hotel'] ?? ''));
    if ($hn !== '') $buckets[$cur]['hotels'][$hn] = ($buckets[$cur]['hotels'][$hn] ?? 0) + 1;
}

/* inclusions / exclusions / tnc from AI profile, else defaults */
$cfg = function_exists('ai_cfg') ? ai_cfg() : [];
$prof = json_decode((string)($cfg['profile'] ?? ''), true) ?: [];
$splitLines = function($s){ $s=(string)$s; $parts = preg_split('/[\n;]+/', $s); return array_values(array_filter(array_map('trim', $parts))); };
$inclusions = $splitLines($prof['inclusions'] ?? '');
if (!$inclusions) $inclusions = ['Accommodation on twin-sharing with daily breakfast','Private air-conditioned vehicle with driver','All sightseeing and transfers per itinerary','Houseboat cruise where mentioned','Driver bata, tolls, parking and fuel','24x7 on-trip support from Lumiere'];
$exclusions = $splitLines($prof['exclusions'] ?? '');
if (!$exclusions) $exclusions = ['Airfare and train tickets','Monument and activity entry tickets','Lunch, dinner and personal expenses','Anything not listed under inclusions'];
$LOGO = '/assets/img/lumiere-logo.png';

/* build one destination page */
function dest_page($name, $b, $base, $LOGO) {
    $p = $b['point'];
    $img = $p && !empty($p['image']) ? ($base . '/' . $p['image']) : '';
    $blurb = $p ? (string)$p['blurb'] : '';
    $n = count($b['days']);
    $hotel = '';
    if ($b['hotels']) { arsort($b['hotels']); $hotel = (string)array_key_first($b['hotels']); }
    ob_start(); ?>
<div class="loc">
<img class="logo" src="<?=h($LOGO)?>">
<?php if ($img): ?><div class="imgblk"><img src="<?=h($img)?>"></div><?php else: ?><div class="imgblk noimg"><span><?=h($name)?></span></div><?php endif; ?>
<div class="ltitle"><div class="llabel"><?=h(strtoupper($name))?> · <?=$n?> DAY<?=$n===1?'':'S'?></div><h2><?=h($name)?></h2></div>
<?php if ($blurb): ?><p class="story"><?=h($blurb)?></p><?php endif; ?>
<div class="days">
<?php foreach ($b['days'] as $d): ?>
<div class="dy"><h4>Day <?=(int)($d['day'] ?? 0)?> · <?=h($d['title'] ?? '')?></h4>
<?php $items = $d['items'] ?? []; if ($items): ?><ul><?php foreach ($items as $it): $nm=is_array($it)?($it['n']??''):$it; if(trim((string)$nm)==='')continue; ?><li><?=h(str_replace(' (ESTIMATE)','',(string)$nm))?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (!empty($d['transport'])): ?><div class="tr"><?=h($d['transport'])?></div><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if ($hotel): ?><div class="hotel"><div class="hl">YOUR STAY</div><div class="hn"><?=h($hotel)?></div><div class="hd"><?=h(($d['meal'] ?? '') ?: 'Comfort category')?></div></div><?php endif; ?>
</div>
<?php return ob_get_clean();
}

$base = rtrim((string)(function_exists('url') ? url('') : ''), '/');
$heroImg = '';
foreach ($order as $r) { $p = $buckets[$r]['point']; if ($p && !empty($p['image'])) { $heroImg = $base . '/' . $p['image']; break; } }
$routeLabel = implode(' · ', $order);
$themeLabel = strtoupper((string)($tr['style'] ?? $tr['occasion'] ?? 'A LUMIERE JOURNEY'));
?><!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> — Lumiere Holidays</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&display=swap">
<style>
:root{--green:#10281f;--green2:#183a2a;--yel:#f0d048;--txt:#e9f1ea;--mut:#a7bcae}
@page{size:440px 960px;margin:0}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;color:var(--txt);font-size:15px;line-height:1.6;background:#0c1c15}
.sheet{width:440px;margin:0 auto;background:var(--green)}
.logo{position:absolute;top:18px;right:26px;height:20px;width:auto;z-index:6}
.rule{width:58px;height:3px;background:var(--yel)}
.imgblk{border-radius:14px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.4);border:1px solid rgba(240,208,72,.35)}
.imgblk img{width:100%;display:block;object-fit:cover}
.imgblk.noimg{display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#183a2a,#0c1c15)}
.imgblk.noimg span{font-family:'Cormorant Garamond',serif;font-size:30px;color:rgba(240,208,72,.85)}
.cover{position:relative;height:960px;background:var(--green);page-break-after:always;padding:46px 30px 30px;display:flex;flex-direction:column;color:#fff}
.cover .imgblk{height:322px;margin-top:14px}.cover .imgblk img{height:322px}.cover .imgblk.noimg{height:322px}
.theme{margin-top:22px;letter-spacing:.24em;font-size:13px;font-weight:600;color:var(--yel)}
.cover h1{font-family:'Cormorant Garamond',serif;font-weight:700;font-size:38px;line-height:1.05;margin-top:8px;color:#fff}
.cover .sub{margin-top:10px;font-size:16px;color:#e8f1ea}
.cover .meta{margin-top:auto;display:flex;justify-content:space-between;align-items:flex-end;border-top:1px solid rgba(240,208,72,.35);padding-top:16px}
.cover .price .l{font-size:11px;letter-spacing:.14em;color:var(--yel);text-transform:uppercase}
.cover .price .n{font-family:'Cormorant Garamond',serif;font-weight:700;font-size:38px;line-height:1;color:var(--yel)}
.cover .price .s{font-size:14px;color:#e8f1ea}
.cover .contact{font-size:12px;color:#bcccc0;text-align:right;line-height:1.7}
.about{position:relative;height:960px;background:var(--green);page-break-after:always;padding:52px 30px 32px;display:flex;flex-direction:column}
.about .rule{margin:30px 0 16px}
.about h3{font-family:'Cormorant Garamond',serif;font-weight:700;font-size:37px;color:#fff;line-height:1.1}
.about .lead{font-size:13px;color:var(--yel);margin-top:11px;letter-spacing:.2em;text-transform:uppercase;font-weight:600}
.about p{margin-top:16px;font-size:15px;line-height:1.75;color:var(--txt)}
.about .vals{margin-top:24px;display:flex;flex-direction:column;gap:16px}
.about .vals .v{border-top:2px solid var(--yel);padding-top:11px}
.about .vals .v b{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:21px;color:#fff;display:block}
.about .vals .v span{font-size:13px;color:var(--mut);line-height:1.5;display:block;margin-top:4px}
.about .cta{margin-top:auto;border-top:1px solid rgba(240,208,72,.3);padding-top:14px;font-size:12px;color:var(--mut)}
.about .cta b{color:#fff}
.loc{position:relative;min-height:960px;background:var(--green);page-break-after:always;padding:46px 30px 26px;display:flex;flex-direction:column}
.loc .imgblk{height:238px;margin-top:14px;flex:0 0 auto}.loc .imgblk img{height:238px}.loc .imgblk.noimg{height:238px}
.ltitle{margin-top:16px}
.llabel{font-size:13px;letter-spacing:.2em;color:var(--yel);font-weight:600}
.ltitle h2{font-family:'Cormorant Garamond',serif;font-weight:700;font-size:44px;color:#fff;line-height:1;margin-top:5px}
.story{margin-top:12px;font-size:16px;line-height:1.68;color:#eef4ee;font-family:Georgia,serif;font-style:italic}
.days{margin-top:16px;display:flex;flex-direction:column;gap:13px}
.dy h4{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:20px;color:#fff}
.dy ul{list-style:none;margin-top:6px}.dy li{font-size:15px;padding:5px 0 5px 20px;position:relative;color:var(--txt)}
.dy li:before{content:'';position:absolute;left:0;top:12px;width:7px;height:7px;background:var(--yel);border-radius:50%}
.dy .tr{font-size:13px;color:var(--mut);margin-top:4px}
.hotel{margin-top:20px;background:rgba(240,208,72,.1);border-left:4px solid var(--yel);border-radius:8px;padding:16px 18px}
.hotel .hl{font-size:11px;letter-spacing:.16em;color:var(--yel);font-weight:600}
.hotel .hn{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:22px;color:#fff;margin-top:4px}
.hotel .hd{font-size:14px;color:var(--mut);margin-top:3px}
.pg{position:relative;min-height:960px;background:var(--green);padding:52px 30px 32px}
h2.sec{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:28px;color:#fff;border-bottom:2px solid var(--yel);padding-bottom:8px;margin-bottom:16px}
.cols h4{font-family:'Cormorant Garamond',serif;font-size:20px;color:var(--yel);margin:14px 0 8px}
ul.tick,ul.cross{list-style:none}ul.tick li,ul.cross li{font-size:15px;padding:6px 0 6px 24px;position:relative;line-height:1.45;color:var(--txt)}
ul.tick li:before{content:'✓';position:absolute;left:0;color:var(--yel);font-weight:700}
ul.cross li:before{content:'✕';position:absolute;left:0;color:#e0a58a}
.totalbox{margin-top:22px;background:rgba(240,208,72,.12);border:1px solid rgba(240,208,72,.5);border-radius:12px;padding:22px 24px;text-align:center}
.totalbox .l{font-size:12px;letter-spacing:.16em;color:var(--yel);text-transform:uppercase}
.totalbox .n{font-family:'Cormorant Garamond',serif;font-weight:700;font-size:46px;color:#fff;line-height:1.05;margin-top:4px}
.totalbox .pp{font-size:16px;color:var(--txt);margin-top:6px}.totalbox .inc{font-size:12px;color:var(--mut);margin-top:4px}
.tnc{margin:14px 0 0 20px}.tnc li{font-size:13px;line-height:1.55;margin-bottom:8px;color:var(--mut)}
.sign{margin-top:22px;border-top:1px solid rgba(240,208,72,.3);padding-top:14px}.sign .ty{font-family:'Cormorant Garamond',serif;font-size:22px;color:#fff}
.sign .c{font-size:13px;color:var(--mut);margin-top:7px;line-height:1.7}
.bar{position:sticky;top:0;z-index:50;background:#0c1c15;color:#eef4ef;display:flex;gap:12px;align-items:center;justify-content:center;padding:12px;border-bottom:1px solid #21402f}
.bar button{background:var(--yel);color:#3a2f07;border:0;border-radius:8px;padding:9px 18px;font:600 14px Inter,sans-serif;cursor:pointer}
.bar span{font-size:12px;color:var(--mut)}
@media print{.bar{display:none}body{background:#fff}.sheet{width:auto}}
</style></head><body>
<div class="bar"><button onclick="window.print()">⬇ Download / Print PDF</button><span>Draft · <?=h($tr['name'] ?? '')?> · <?=$nights?>N — use "Save as PDF" in the print dialog</span></div>
<div class="sheet">
<div class="cover"><img class="logo" src="<?=h($LOGO)?>">
<?php if ($heroImg): ?><div class="imgblk"><img src="<?=h($heroImg)?>"></div><?php else: ?><div class="imgblk noimg"><span>Kerala</span></div><?php endif; ?>
<div class="theme"><?=h($themeLabel)?></div><h1><?=h($title)?></h1>
<div class="sub"><?=h($routeLabel)?> · <?=$nights?> nights</div>
<div class="meta"><div class="price"><div class="l">Package from</div><div class="n"><?=rup($GTOTAL)?></div><div class="s"><?=rup($PP)?> per person · incl. GST</div></div>
<div class="contact">Lumiere Holidays<br>lumiereholidays.com<br>+91 9526232221</div></div></div>

<div class="about"><img class="logo" src="<?=h($LOGO)?>"><div class="rule"></div>
<h3>Curators of Journeys,<br>Not Package Sellers</h3><div class="lead">About Lumiere Holidays</div>
<p>From our studio in Kochi, we design personal journeys through Kerala and beyond — planned by people, not templates. Every itinerary is built around you: your pace, your taste, your moments.</p>
<div class="vals"><div class="v"><b>Planned by people</b><span>Every day hand-built by a Kerala specialist.</span></div>
<div class="v"><b>Handpicked stays</b><span>Hotels &amp; houseboats we stand behind.</span></div>
<div class="v"><b>On the ground</b><span>Local drivers, verified partners, support throughout.</span></div></div>
<div class="cta"><b>Lumiere Holidays</b> · Vyttila, Kochi · lumiereholidays.com · +91 9526232221</div></div>

<?php foreach ($order as $r): echo dest_page($r, $buckets[$r], $base, $LOGO); endforeach; ?>

<div class="pg"><h2 class="sec">Inclusions</h2>
<div class="cols"><h4>Included</h4><ul class="tick"><?php foreach ($inclusions as $x): ?><li><?=h($x)?></li><?php endforeach; ?></ul>
<h4>Not included</h4><ul class="cross"><?php foreach ($exclusions as $x): ?><li><?=h($x)?></li><?php endforeach; ?></ul></div>
<div class="totalbox"><div class="l">Total Package Price</div><div class="n"><?=rup($GTOTAL)?></div><div class="pp"><?=rup($PP)?> per person</div><div class="inc">Inclusive of 5% GST · all listed services</div></div>
<h2 class="sec" style="margin-top:24px">Terms &amp; Conditions</h2>
<ol class="tnc"><li><b>Payment:</b> 30% advance; balance 15 days before travel.</li>
<li><b>Cancellation:</b> &gt;30d 10% · 15–30d 30% · 7–14d 50% · &lt;7d 100%. Houseboat/peak non-refundable.</li>
<li><b>Rates:</b> INR, incl. GST, for stated travellers, rooms and dates.</li>
<li><b>Hotels:</b> equivalent category substituted at no drop in standard.</li>
<li><b>Excludes:</b> airfare, entry tickets, tips, anything not listed.</li>
<li><b>Force majeure:</b> not liable for delay or loss beyond our control.</li></ol>
<div class="sign"><div class="ty">Thank you for choosing Lumiere Holidays.</div><div class="c">+91 9526232221 · lumiereholidays.com · Vyttila, Kochi</div></div></div>
</div>
</body></html>
ENDOFFILE7;
$bak=$t.'.bak'; if(is_file($t)&&!is_file($bak)) @copy($t,$bak);
file_put_contents($t,$s);
$out=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$out,$rc);
if($rc!==0 && is_file($bak)){ @copy($bak,$t); }
file_put_contents($root.'/_dep_p_pdf.txt',json_encode(['rc'=>$rc,'out'=>$out,'len'=>strlen($s),'crc'=>hash('crc32b',$s),'restored'=>($rc!==0),'target'=>'admin/package-pdf.php']));
echo 'DEP';
