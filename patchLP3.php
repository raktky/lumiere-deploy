<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
try{ db()->exec("ALTER TABLE tb_track ADD COLUMN wa_number VARCHAR(40) NULL"); $rep[]="wa_col=added"; }catch(Throwable $e){ $rep[]="wa_col=exists"; }
$lp = '<?php
/* Public SEO landing page + campaign-tagged leads + GA4/Ads tracking + WhatsApp + JSON-LD. */
declare(strict_types=1);
require __DIR__ . \'/app/bootstrap.php\';
$slug = isset($LP) ? (string) $LP : preg_replace(\'/[^a-z0-9\\-]/i\', \'\', (string) ($_GET[\'s\'] ?? \'\'));
$p = null;
if ($slug !== \'\') { try { $st = db()->prepare(\'SELECT * FROM tb_landing WHERE slug = ? AND published = 1 LIMIT 1\'); $st->execute([$slug]); $p = $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) { $p = null; } }
$trk = null; try { $trk = db()->query(\'SELECT * FROM tb_track WHERE id = 1\')->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) { $trk = null; }
$sent = false; $waLink = \'\';
if ($p && ($_SERVER[\'REQUEST_METHOD\'] ?? \'\') === \'POST\' && trim((string)($_POST[\'company\'] ?? \'\')) === \'\') {
  $name = trim((string) ($_POST[\'name\'] ?? \'\')); $phone = trim((string) ($_POST[\'phone\'] ?? \'\'));
  if ($name !== \'\' && $phone !== \'\') {
    try {
      db()->prepare(\'INSERT INTO trip_requests (name,email,phone,regions,occasion,notes,utm_source,utm_medium,utm_campaign,gclid,landing_slug) VALUES (?,?,?,?,?,?,?,?,?,?,?)\')
        ->execute([$name, trim((string)($_POST[\'email\'] ?? \'\')), $phone, (string)$p[\'destination\'], (string)($_POST[\'origin\'] ?? $p[\'origin\']),
          \'Landing: \' . (string)$p[\'h1\'] . \' | from \' . (string)($_POST[\'origin\'] ?? $p[\'origin\']),
          substr(trim((string)($_POST[\'utm_source\'] ?? \'\')),0,120), substr(trim((string)($_POST[\'utm_medium\'] ?? \'\')),0,120), substr(trim((string)($_POST[\'utm_campaign\'] ?? \'\')),0,160), substr(trim((string)($_POST[\'gclid\'] ?? \'\')),0,200), $slug]);
      $sent = true;
      if (function_exists(\'lm_notify\')) { try { lm_notify(\'New landing lead: \' . $name . \' (\' . $phone . \') - \' . (string)$p[\'h1\']); } catch (Throwable $e) {} }
    } catch (Throwable $e) {}
  }
}
$waNum = \'\';
if ($p) { $waNum = trim((string)($p[\'wa_number\'] ?? \'\')); if ($waNum === \'\') { $waNum = trim((string)($trk[\'wa_number\'] ?? \'\')); } }
if ($p && $waNum !== \'\') { $waLink = \'https://wa.me/\' . preg_replace(\'/\\D/\', \'\', $waNum) . \'?text=\' . rawurlencode(\'Hi, I am interested in \' . (string)$p[\'h1\'] . \'. Please share details.\'); }
$telLink = $waNum !== \'\' ? \'tel:+\' . preg_replace(\'/\\D/\', \'\', $waNum) : \'\';
$hl = $p ? array_values(array_filter(array_map(\'trim\', explode("\\n", (string) $p[\'highlights\'])))) : [];
$canon = \'https://www.lumiereholidays.com/\' . $slug . \'.php\';
$gtagId = $trk[\'ga4_id\'] ?? \'\'; $adsId = $trk[\'ads_conv_id\'] ?? \'\'; $adsLabel = $trk[\'ads_conv_label\'] ?? \'\';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!$p): ?><title>Page not found - Lumiere Holidays</title><?php else: ?>
<title><?= e((string) ($p[\'title\'] ?: $p[\'h1\'])) ?></title>
<meta name="description" content="<?= e((string) $p[\'meta_desc\']) ?>">
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:title" content="<?= e((string) ($p[\'title\'] ?: $p[\'h1\'])) ?>">
<meta property="og:description" content="<?= e((string) $p[\'meta_desc\']) ?>">
<?php if (!empty($p[\'hero\'])): ?><meta property="og:image" content="<?= e((string)$p[\'hero\']) ?>"><?php endif; ?>
<script type="application/ld+json"><?= json_encode([
  \'@context\'=>\'https://schema.org\',\'@type\'=>\'TouristTrip\',\'name\'=>(string)$p[\'h1\'],
  \'description\'=>(string)$p[\'meta_desc\'],\'url\'=>$canon,
  \'touristType\'=>[\'Leisure travellers\',\'Families\',\'Honeymooners\'],
  \'itinerary\'=>[\'@type\'=>\'ItemList\',\'numberOfItems\'=>count($hl)],
  \'provider\'=>[\'@type\'=>\'TravelAgency\',\'name\'=>\'Lumiere Holidays\',\'url\'=>\'https://www.lumiereholidays.com/\'] + ($waNum!==\'\'?[\'telephone\'=>\'+\'.preg_replace(\'/\\D/\',\'\',$waNum)]:[]),
  \'offers\'=>[\'@type\'=>\'Offer\',\'category\'=>\'Tour package\'] + ($p[\'price_from\']!==\'\'?[\'price\'=>preg_replace(\'/\\D/\',\'\',(string)$p[\'price_from\']),\'priceCurrency\'=>\'INR\']:[]),
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<?php $__tagId = $gtagId !== \'\' ? $gtagId : $adsId; if ($__tagId !== \'\'): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($__tagId) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(\'js\',new Date());<?php if($gtagId!==\'\'): ?>gtag(\'config\',\'<?= e($gtagId) ?>\');<?php endif; ?><?php if($adsId!==\'\'): ?>gtag(\'config\',\'<?= e($adsId) ?>\');<?php endif; ?></script>
<?php endif; ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600;700&display=swap">
<style>
:root{--ink:#1E1E1C;--gold:#B08D57;--sage:#6F7D6B;--ivory:#FAF7F2;--rule:#e4ded4}
*{box-sizing:border-box;margin:0}
body{font:16px/1.6 Inter,system-ui,sans-serif;color:var(--ink);background:var(--ivory)}
.hero{min-height:60vh;background:linear-gradient(rgba(20,18,15,.5),rgba(20,18,15,.55)),var(--bg) center/cover no-repeat;color:#fff;display:flex;align-items:center;padding:40px 20px}
.hero .in{max-width:1040px;margin:0 auto;width:100%}
.brand{font-family:\'Cormorant Garamond\',serif;font-size:22px;letter-spacing:.05em;margin-bottom:18px}
h1{font-family:\'Cormorant Garamond\',serif;font-size:clamp(30px,6vw,52px);font-weight:600;max-width:16ch;line-height:1.08}
.sub{margin-top:12px;font-size:18px;max-width:44ch;color:#f0e9dd}
.cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
.btn{display:inline-block;background:var(--gold);color:#fff;border:0;border-radius:9px;padding:15px 30px;font:700 16px Inter;cursor:pointer;text-decoration:none}
.btn.wa{background:#25D366}
.btn.call{background:#1a73e8}
.btn:hover{opacity:.92}
.wrap{max-width:1040px;margin:0 auto;padding:44px 20px}
.grid{display:grid;grid-template-columns:1.3fr .9fr;gap:34px;align-items:start}
@media(max-width:820px){.grid{grid-template-columns:1fr}}
.sec{font-family:\'Cormorant Garamond\',serif;font-size:26px;margin:0 0 14px}
.hls{list-style:none;padding:0}
.hls li{padding:9px 0 9px 28px;border-bottom:1px solid var(--rule);position:relative}
.hls li:before{content:\'\\2726\';position:absolute;left:0;color:var(--gold)}
.price{margin-top:18px;font-size:15px;color:var(--sage)}
.form{background:#fff;border:1px solid var(--rule);border-radius:16px;padding:26px 24px;box-shadow:0 6px 30px rgba(30,28,22,.07);position:sticky;top:16px}
.form h3{font-family:\'Cormorant Garamond\',serif;font-size:23px;margin-bottom:4px}
.form p.s{color:var(--sage);font-size:13.5px;margin-bottom:14px}
.form label{display:block;font-weight:600;font-size:13px;margin:10px 0 4px}
.form input{width:100%;padding:12px;border:1px solid var(--rule);border-radius:9px;font-size:15px}
.hp{position:absolute;left:-9999px}
.ok{background:#e4f5ec;border:1px solid #0d7a4f;color:#0d7a4f;border-radius:10px;padding:16px;font-weight:600;text-align:center}
.foot{background:var(--ink);color:#cdb892;text-align:center;padding:22px;font-size:13px}
.foot a{color:#e6d3af}
.trust{display:flex;gap:22px;flex-wrap:wrap;margin-top:26px;color:var(--sage);font-size:14px}
.trust b{color:var(--ink)}
.wafab{position:fixed;right:16px;bottom:16px;background:#25D366;color:#fff;border-radius:50px;padding:14px 20px;font-weight:700;text-decoration:none;box-shadow:0 4px 16px rgba(0,0,0,.25);z-index:99}
</style></head><body>
<?php if (!$p): ?>
<div class="wrap"><h1>Page not found</h1><p>This page may have moved. Visit <a href="/tours.php">our tour packages</a>.</p></div>
<?php else: ?>
<div class="hero" style="--bg:url(\'<?= e((string)($p[\'hero\'] ?: \'https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?q=80&w=1600\')) ?>\')">
  <div class="in">
    <div class="brand">Lumiere Holidays</div>
    <h1><?= e((string) $p[\'h1\']) ?></h1>
    <div class="sub"><?= e((string) $p[\'meta_desc\']) ?></div>
    <div class="cta"><a class="btn" href="#enquire">Get a free quote</a><?php if ($waLink): ?><a class="btn wa" href="<?= e($waLink) ?>" target="_blank" rel="nofollow">WhatsApp us</a><?php endif; ?><?php if ($telLink): ?><a class="btn call" href="<?= e($telLink) ?>">Call now</a><?php endif; ?></div>
  </div>
</div>
<div class="wrap"><div class="grid">
  <div>
    <h2 class="sec">Why travel with us</h2>
    <?php if ($p[\'intro\']): ?><p style="margin-bottom:16px"><?= nl2br(e((string) $p[\'intro\'])) ?></p><?php endif; ?>
    <?php if ($hl): ?><ul class="hls"><?php foreach ($hl as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($p[\'price_from\']): ?><div class="price">Packages from <b style="color:var(--ink);font-size:18px"><?= e((string)$p[\'price_from\']) ?></b> - fully customisable</div><?php endif; ?>
    <div class="trust"><div><b>500+</b> trips crafted</div><div><b>Local</b> Kerala experts</div><div><b>24x7</b> on-trip support</div><div><b>Private</b> transport &amp; handpicked stays</div></div>
  </div>
  <div id="enquire">
    <div class="form">
      <?php if ($sent): ?>
        <div class="ok">Thank you! Our travel expert will contact you shortly.</div>
        <?php if ($waLink): ?><p style="text-align:center;margin-top:14px"><a class="btn wa" href="<?= e($waLink) ?>" target="_blank" rel="nofollow" style="width:100%">Chat on WhatsApp now</a></p><?php endif; ?>
        <script>try{if(typeof gtag===\'function\'){<?php if($adsId!==\'\' && $adsLabel!==\'\'): ?>gtag(\'event\',\'conversion\',{\'send_to\':\'<?= e($adsId) ?>/<?= e($adsLabel) ?>\'});<?php endif; ?>gtag(\'event\',\'generate_lead\',{\'event_category\':\'landing\',\'event_label\':\'<?= e($slug) ?>\'});}}catch(e){}</script>
      <?php else: ?>
        <h3>Get your free quote</h3>
        <p class="s">Tell us your name &amp; number - we reply within hours.</p>
        <form method="post" id="lf">
          <input class="hp" name="company" tabindex="-1" autocomplete="off">
          <label>Your name</label><input name="name" required>
          <label>WhatsApp / phone</label><input name="phone" required inputmode="tel">
          <label>Email (optional)</label><input name="email" type="email">
          <input type="hidden" name="origin" value="<?= e((string)$p[\'origin\']) ?>">
          <input type="hidden" name="utm_source" id="f_src"><input type="hidden" name="utm_medium" id="f_med"><input type="hidden" name="utm_campaign" id="f_camp"><input type="hidden" name="gclid" id="f_gclid">
          <button class="btn" type="submit" style="width:100%;margin-top:16px">Send my enquiry</button>
        </form>
        <?php if ($waLink): ?><p style="text-align:center;margin-top:12px;font-size:13px">or <a href="<?= e($waLink) ?>" target="_blank" rel="nofollow" style="color:#128C7E;font-weight:600">message us on WhatsApp</a></p><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div></div>
<div class="foot">Lumiere Holidays - Kerala - <a href="/tours.php">All packages</a> - <a href="/privacy.php">Privacy</a> - Handcrafted journeys since 2015</div>
<?php if ($waLink): ?><a class="wafab" href="<?= e($waLink) ?>" target="_blank" rel="nofollow">WhatsApp</a><?php endif; ?>
<script>
(function(){var q=new URLSearchParams(location.search);function set(id,k){var e=document.getElementById(id);if(e)e.value=q.get(k)||\'\';}
set(\'f_src\',\'utm_source\');set(\'f_med\',\'utm_medium\');set(\'f_camp\',\'utm_campaign\');set(\'f_gclid\',\'gclid\');})();
</script>
<?php endif; ?>
</body></html>';
$t=tempnam(sys_get_temp_dir(),"x");file_put_contents($t,$lp);exec("php -l ".escapeshellarg($t)." 2>&1",$o,$rc);unlink($t);
if($rc!==0){ $rep[]="lp=lintFAIL"; } else { copy("$root/lp.php","$root/lp.php.bak.patchLP3"); file_put_contents("$root/lp.php",$lp); $rep[]="lp=ok"; }
$tf="$root/admin/tracking.php"; $tc=file_get_contents($tf);
if(strpos($tc,"wa_number")===false){
  $tc=str_replace("UPDATE tb_track SET ga4_id=?, ads_conv_id=?, ads_conv_label=?, updated_at=NOW()", "UPDATE tb_track SET ga4_id=?, ads_conv_id=?, ads_conv_label=?, wa_number=?, updated_at=NOW()", $tc, $a);
  $tc=str_replace("trim((string)(\$_POST['ads_conv_label'] ?? ''))])", "trim((string)(\$_POST['ads_conv_label'] ?? '')), trim((string)(\$_POST['wa_number'] ?? ''))])", $tc, $b);
  $anchor='<label>GA4 Measurement ID</label>';
  $add='<label>Agency WhatsApp number (with country code)</label><input name="wa_number" value="<?= e((string)(\$t[\'wa_number\'] ?? \'\')) ?>" placeholder="9198XXXXXXXX"><div class="h">Shows WhatsApp + Call buttons on all landing pages.</div>' . "\n  " . $anchor;
  $tc=str_replace($anchor,$add,$tc,$c);
  $tt=tempnam(sys_get_temp_dir(),"y");file_put_contents($tt,$tc);exec("php -l ".escapeshellarg($tt)." 2>&1",$o2,$rc2);unlink($tt);
  if($rc2!==0){ $rep[]="track=lintFAIL(a=$a,b=$b,c=$c)"; } else { copy($tf,$tf.".bak.patchLP3"); file_put_contents($tf,$tc); $rep[]="track=ok(a=$a,b=$b,c=$c)"; }
} else { $rep[]="track=already"; }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_plp3.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
