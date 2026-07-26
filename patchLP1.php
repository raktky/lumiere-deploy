<?php
/* patchLP1: geo/route landing pages + campaign tagging (360).
   - tb_landing
   - trip_requests: utm_source/medium/campaign, gclid, landing_slug
   - public lp.php (SEO landing + easy form + WhatsApp CTA + capture)
   - keyword wrapper files for seeds
   - seed 4 landing pages
   Idempotent; lint; status report. Admin CRUD + campaigns report = patchLP2. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchLP1');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* 1. table + columns */
try{
  db()->exec("CREATE TABLE IF NOT EXISTS tb_landing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    title VARCHAR(200) NULL,
    meta_desc VARCHAR(320) NULL,
    h1 VARCHAR(200) NULL,
    origin VARCHAR(120) NULL,
    destination VARCHAR(120) NULL,
    hero VARCHAR(500) NULL,
    intro TEXT NULL,
    highlights TEXT NULL,
    price_from VARCHAR(60) NULL,
    wa_number VARCHAR(40) NULL,
    published TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_slug (slug)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo=db(); $cols=[];
  foreach($pdo->query("SHOW COLUMNS FROM trip_requests")->fetchAll(PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=1; }
  foreach(['utm_source'=>'VARCHAR(120)','utm_medium'=>'VARCHAR(120)','utm_campaign'=>'VARCHAR(160)','gclid'=>'VARCHAR(200)','landing_slug'=>'VARCHAR(160)'] as $k=>$ty){
    if(!isset($cols[$k])){ $pdo->exec("ALTER TABLE trip_requests ADD COLUMN $k $ty NULL"); }
  }
  $rep[]='schema=ok';
}catch(Throwable $e){ $rep[]='schema=ERR:'.substr($e->getMessage(),0,40); }

/* 2. public lp.php */
$lp = <<<'PHP'
<?php
/* Public SEO landing page for geo/route tour packages. Captures campaign-tagged leads. */
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$slug = isset($LP) ? (string) $LP : preg_replace('/[^a-z0-9\-]/i', '', (string) ($_GET['s'] ?? ''));
$p = null;
if ($slug !== '') { try { $st = db()->prepare('SELECT * FROM tb_landing WHERE slug = ? AND published = 1 LIMIT 1'); $st->execute([$slug]); $p = $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) { $p = null; } }
$sent = false; $waLink = '';
if ($p && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && trim((string)($_POST['company'] ?? '')) === '') {
  $name = trim((string) ($_POST['name'] ?? '')); $phone = trim((string) ($_POST['phone'] ?? ''));
  if ($name !== '' && $phone !== '') {
    try {
      db()->prepare('INSERT INTO trip_requests (name,email,phone,regions,occasion,notes,utm_source,utm_medium,utm_campaign,gclid,landing_slug) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$name, trim((string)($_POST['email'] ?? '')), $phone, (string)$p['destination'], (string)($_POST['origin'] ?? $p['origin']),
          'Landing: ' . (string)$p['h1'] . ' | from ' . (string)($_POST['origin'] ?? $p['origin']),
          substr(trim((string)($_POST['utm_source'] ?? '')),0,120), substr(trim((string)($_POST['utm_medium'] ?? '')),0,120), substr(trim((string)($_POST['utm_campaign'] ?? '')),0,160), substr(trim((string)($_POST['gclid'] ?? '')),0,200), $slug]);
      $sent = true;
      if (function_exists('lm_notify')) { try { lm_notify('🌐 New landing lead: ' . $name . ' (' . $phone . ') — ' . (string)$p['h1']); } catch (Throwable $e) {} }
    } catch (Throwable $e) {}
  }
}
if ($p && !empty($p['wa_number'])) { $waLink = 'https://wa.me/' . preg_replace('/\D/', '', (string)$p['wa_number']) . '?text=' . rawurlencode('Hi, I am interested in ' . (string)$p['h1'] . '. Please share details.'); }
$hl = $p ? array_values(array_filter(array_map('trim', explode("\n", (string) $p['highlights'])))) : [];
$canon = 'https://www.lumiereholidays.com/' . $slug . '.php';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!$p): ?><title>Page not found · Lumiere Holidays</title><?php else: ?>
<title><?= e((string) ($p['title'] ?: $p['h1'])) ?></title>
<meta name="description" content="<?= e((string) $p['meta_desc']) ?>">
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:title" content="<?= e((string) ($p['title'] ?: $p['h1'])) ?>">
<meta property="og:description" content="<?= e((string) $p['meta_desc']) ?>">
<?php if (!empty($p['hero'])): ?><meta property="og:image" content="<?= e((string)$p['hero']) ?>"><?php endif; ?>
<?php endif; ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600;700&display=swap">
<style>
:root{--ink:#1E1E1C;--gold:#B08D57;--sage:#6F7D6B;--ivory:#FAF7F2;--rule:#e4ded4}
*{box-sizing:border-box;margin:0}
body{font:16px/1.6 Inter,system-ui,sans-serif;color:var(--ink);background:var(--ivory)}
.hero{min-height:60vh;background:linear-gradient(rgba(20,18,15,.5),rgba(20,18,15,.55)),var(--bg) center/cover no-repeat;color:#fff;display:flex;align-items:center;padding:40px 20px}
.hero .in{max-width:1040px;margin:0 auto;width:100%}
.brand{font-family:'Cormorant Garamond',serif;font-size:22px;letter-spacing:.05em;margin-bottom:18px}
h1{font-family:'Cormorant Garamond',serif;font-size:clamp(30px,6vw,52px);font-weight:600;max-width:16ch;line-height:1.08}
.sub{margin-top:12px;font-size:18px;max-width:44ch;color:#f0e9dd}
.cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
.btn{display:inline-block;background:var(--gold);color:#fff;border:0;border-radius:9px;padding:15px 30px;font:700 16px Inter;cursor:pointer;text-decoration:none}
.btn.wa{background:#25D366}
.btn:hover{opacity:.92}
.wrap{max-width:1040px;margin:0 auto;padding:44px 20px}
.grid{display:grid;grid-template-columns:1.3fr .9fr;gap:34px;align-items:start}
@media(max-width:820px){.grid{grid-template-columns:1fr}}
.sec{font-family:'Cormorant Garamond',serif;font-size:26px;margin:0 0 14px}
.hls{list-style:none;padding:0}
.hls li{padding:9px 0 9px 28px;border-bottom:1px solid var(--rule);position:relative}
.hls li:before{content:'✦';position:absolute;left:0;color:var(--gold)}
.price{margin-top:18px;font-size:15px;color:var(--sage)}
.form{background:#fff;border:1px solid var(--rule);border-radius:16px;padding:26px 24px;box-shadow:0 6px 30px rgba(30,28,22,.07);position:sticky;top:16px}
.form h3{font-family:'Cormorant Garamond',serif;font-size:23px;margin-bottom:4px}
.form p.s{color:var(--sage);font-size:13.5px;margin-bottom:14px}
.form label{display:block;font-weight:600;font-size:13px;margin:10px 0 4px}
.form input{width:100%;padding:12px;border:1px solid var(--rule);border-radius:9px;font-size:15px}
.hp{position:absolute;left:-9999px}
.ok{background:#e4f5ec;border:1px solid #0d7a4f;color:#0d7a4f;border-radius:10px;padding:16px;font-weight:600;text-align:center}
.foot{background:var(--ink);color:#cdb892;text-align:center;padding:22px;font-size:13px}
.foot a{color:#e6d3af}
.trust{display:flex;gap:22px;flex-wrap:wrap;margin-top:26px;color:var(--sage);font-size:14px}
.trust b{color:var(--ink)}
</style></head><body>
<?php if (!$p): ?>
<div class="wrap"><h1>Page not found</h1><p>This page may have moved. Visit <a href="/tours.php">our tour packages</a>.</p></div>
<?php else: ?>
<div class="hero" style="--bg:url('<?= e((string)($p['hero'] ?: 'https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?q=80&w=1600')) ?>')">
  <div class="in">
    <div class="brand">Lumiere Holidays</div>
    <h1><?= e((string) $p['h1']) ?></h1>
    <div class="sub"><?= e((string) $p['meta_desc']) ?></div>
    <div class="cta"><a class="btn" href="#enquire">Get a free quote</a><?php if ($waLink): ?><a class="btn wa" href="<?= e($waLink) ?>" target="_blank" rel="nofollow">WhatsApp us</a><?php endif; ?></div>
  </div>
</div>
<div class="wrap"><div class="grid">
  <div>
    <h2 class="sec">Why travel with us</h2>
    <?php if ($p['intro']): ?><p style="margin-bottom:16px"><?= nl2br(e((string) $p['intro'])) ?></p><?php endif; ?>
    <?php if ($hl): ?><ul class="hls"><?php foreach ($hl as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($p['price_from']): ?><div class="price">Packages from <b style="color:var(--ink);font-size:18px"><?= e((string)$p['price_from']) ?></b> · fully customisable</div><?php endif; ?>
    <div class="trust"><div><b>500+</b> trips crafted</div><div><b>Local</b> Kerala experts</div><div><b>24×7</b> on-trip support</div><div><b>Private</b> transport &amp; handpicked stays</div></div>
  </div>
  <div id="enquire">
    <div class="form">
      <?php if ($sent): ?>
        <div class="ok">✓ Thank you! Our travel expert will contact you shortly.</div>
        <?php if ($waLink): ?><p style="text-align:center;margin-top:14px"><a class="btn wa" href="<?= e($waLink) ?>" target="_blank" rel="nofollow" style="width:100%">Chat on WhatsApp now</a></p><?php endif; ?>
      <?php else: ?>
        <h3>Get your free quote</h3>
        <p class="s">Tell us your name &amp; number — we reply within hours.</p>
        <form method="post" id="lf">
          <input class="hp" name="company" tabindex="-1" autocomplete="off">
          <label>Your name</label><input name="name" required>
          <label>WhatsApp / phone</label><input name="phone" required inputmode="tel">
          <label>Email (optional)</label><input name="email" type="email">
          <input type="hidden" name="origin" value="<?= e((string)$p['origin']) ?>">
          <input type="hidden" name="utm_source" id="f_src"><input type="hidden" name="utm_medium" id="f_med"><input type="hidden" name="utm_campaign" id="f_camp"><input type="hidden" name="gclid" id="f_gclid">
          <button class="btn" type="submit" style="width:100%;margin-top:16px">Send my enquiry →</button>
        </form>
        <?php if ($waLink): ?><p style="text-align:center;margin-top:12px;font-size:13px">or <a href="<?= e($waLink) ?>" target="_blank" rel="nofollow" style="color:#128C7E;font-weight:600">message us on WhatsApp</a></p><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div></div>
<div class="foot">Lumiere Holidays · Kerala · <a href="/tours.php">All packages</a> · Handcrafted journeys since 2015</div>
<script>
(function(){var q=new URLSearchParams(location.search);function set(id,k){var e=document.getElementById(id);if(e)e.value=q.get(k)||'';}
set('f_src','utm_source');set('f_med','utm_medium');set('f_camp','utm_campaign');set('f_gclid','gclid');})();
</script>
<?php endif; ?>
</body></html>
PHP;
put_lint("$root/lp.php",$lp,$rep,'lp');

/* 3. seed 4 landings + keyword wrapper files */
$seeds=[
 ['kerala-tour-package-from-malaysia','Malaysia','Kerala','Kerala Tour Packages from Malaysia 2026 | Lumiere Holidays','Kerala tour packages for Malaysian travellers — backwaters, hills & beaches, private transfers, handpicked stays. Free quote in hours.','Kerala Tour Packages from Malaysia','From ₹45,000 / person'],
 ['kerala-tour-package-from-gujarat','Gujarat','Kerala','Kerala Tour Packages from Gujarat 2026 | Lumiere Holidays','Kerala holiday packages for Gujarat families & groups — Munnar, Alleppey houseboats, Kochi. Customisable, veg-friendly. Get a free quote.','Kerala Tour Packages from Gujarat','From ₹28,000 / person'],
 ['kerala-tour-package-from-mumbai','Mumbai','Kerala','Kerala Tour Packages from Mumbai 2026 | Lumiere Holidays','Kerala tour packages from Mumbai — quick flights, curated Munnar–Thekkady–Alleppey routes, private cars, premium resorts. Free quote.','Kerala Tour Packages from Mumbai','From ₹26,000 / person'],
 ['thailand-tour-package-from-kochi','Kochi, Kerala','Thailand','Thailand Tour Packages from Kochi, Kerala 2026 | Lumiere Holidays','Thailand tour packages from Kochi — Bangkok, Phuket, Krabi with flights, visa help & transfers. For Kerala travellers. Get a free quote.','Thailand Tour Packages from Kochi','From ₹55,000 / person'],
];
$hlKerala="Handpicked 4★/5★ stays & houseboats\nPrivate AC vehicle + experienced driver-guide\nMunnar, Thekkady, Alleppey & Kochi covered\nFlexible day-by-day itinerary\n24×7 on-trip support";
$hlThai="Bangkok, Pattaya, Phuket & Krabi options\nReturn flights & visa assistance from Kochi\nAirport transfers + city tours included\nHoneymoon & family packages\nEnglish-speaking local support";
$ins=db()->prepare("INSERT INTO tb_landing (slug,title,meta_desc,h1,origin,destination,intro,highlights,price_from,published,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title), meta_desc=VALUES(meta_desc), h1=VALUES(h1), origin=VALUES(origin), destination=VALUES(destination), intro=VALUES(intro), highlights=VALUES(highlights), price_from=VALUES(price_from), updated_at=NOW()");
$made=0;
foreach($seeds as $s){
  $intro='Planning a trip to '.$s[4].' from '.$s[1].'? Lumiere Holidays crafts private, fully-customisable '.$s[4].' packages with the best stays, transport and experiences — no cookie-cutter tours.';
  $hl = ($s[4]==='Thailand') ? $hlThai : $hlKerala;
  try{ $ins->execute([$s[0],$s[3],$s[4],$s[5],$s[1],$s[4]==='Thailand'?'Thailand':'Kerala',$intro,$hl,$s[6]]); $made++; }catch(Throwable $e){}
  /* wrapper file for clean keyword URL */
  $wrap='<?php $LP='.var_export($s[0],true).'; require __DIR__ . '."'".'/lp.php'."'".';';
  @file_put_contents("$root/".$s[0].".php",$wrap);
}
$rep[]='seeds='.$made;

/* 4. sidebar: Content > Landing pages (after pkg_builder) */
$uf="$root/app/admin_ui.php"; $uc=file_get_contents($uf);
if(strpos($uc,"'landing'")===false){
  $uc2=preg_replace("/('pkg_builder'\\s*=>\\s*\\['Package builder',\\s*'package\\.php'\\],)/","$1\n            'landing'       => ['Landing pages', 'landing.php'],\n            'campaigns'     => ['Campaigns', 'campaigns.php'],",$uc,1,$nn);
  if($nn===1){ $t=tempnam(sys_get_temp_dir(),'u');file_put_contents($t,$uc2);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t); if($rc===0){ copy($uf,$uf.'.bak.patchLP1'); file_put_contents($uf,$uc2); $rep[]='nav=ok'; } else { $rep[]='nav=lintFAIL'; } }
  else{ $rep[]='nav=anchorfail'; }
} else { $rep[]='nav=already'; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_plp1.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
