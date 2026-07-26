<?php
/* patch28: Blank package builder (catalog packages from scratch).
   - tb_packages table
   - admin/package.php (list + create/edit, day-by-day editor reused, draft/published)
   Prologue (auth+includes) copied verbatim from admin/quotes.php so it is correct by construction.
   Idempotent; lint before write; status-only report. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];

/* 1. migrate */
try{
  db()->exec("CREATE TABLE IF NOT EXISTS tb_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NULL,
    regions VARCHAR(255) NULL,
    nights INT NULL,
    hero VARCHAR(500) NULL,
    summary TEXT NULL,
    price DECIMAL(12,2) NULL,
    itinerary MEDIUMTEXT NULL,
    published TINYINT NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $rep[]='migrate=ok';
}catch(Throwable $e){ $rep[]='migrate=ERR'; }

/* 2. extract prologue from quotes.php (leading require/auth block) */
$src=@file_get_contents("$root/admin/quotes.php");
$prologue='';
if($src){
  $ls=explode("\n",$src);
  foreach($ls as $ln){
    $t=trim($ln);
    $ok = ($t==='') || strpos($t,'<?php')===0 || strpos($t,'declare')===0
      || strpos($t,'require')===0 || strpos($t,'use ')===0 || strpos($t,'namespace')===0
      || strpos($t,'ini_set')===0 || strpos($t,'error_reporting')===0
      || strpos($t,'require_admin')!==false || strpos($t,'admin_user')!==false
      || strpos($t,'//')===0 || strpos($t,'/*')===0 || strpos($t,'*')===0
      || preg_match('/^\$pdo\s*=/',$t) || strpos($t,'rbac')!==false;
    if(!$ok) break;
    $prologue.=$ln."\n";
  }
}
if(strpos($prologue,'require_admin')===false){ $rep[]='prologue=BAD'; }
else { $rep[]='prologue=ok'; }
if(strpos($prologue,'$pdo')===false){ $prologue.="\$pdo = db();\n"; }

/* 3. body PHP (handler + load) */
$bodyPHP = <<<'PHPX'

$__saved = false; $__errmsg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (function_exists('csrf_check')) { csrf_check(); }
    $title   = trim((string) ($_POST['title'] ?? ''));
    $regions = trim((string) ($_POST['regions'] ?? ''));
    $nights  = (int) ($_POST['nights'] ?? 0);
    $hero    = trim((string) ($_POST['hero'] ?? ''));
    $summary = trim((string) ($_POST['summary'] ?? ''));
    $price   = (float) preg_replace('/[^0-9.]/', '', (string) ($_POST['price'] ?? '0'));
    $published = !empty($_POST['published']) ? 1 : 0;
    $pid     = (int) ($_POST['id'] ?? 0);
    $ijson   = (string) ($_POST['itinerary_json'] ?? '');
    $itin    = json_decode($ijson, true); if (!is_array($itin)) { $itin = []; }
    $itinJson = $itin ? json_encode($itin, JSON_UNESCAPED_UNICODE) : null;
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    if ($title === '') {
        $__errmsg = 'Title is required.';
    } else {
        try {
            if ($pid > 0) {
                $pdo->prepare('UPDATE tb_packages SET title=?, slug=?, regions=?, nights=?, hero=?, summary=?, price=?, itinerary=?, published=?, updated_at=NOW() WHERE id=?')
                    ->execute([$title, $slug, $regions, $nights, $hero, $summary, $price, $itinJson, $published, $pid]);
            } else {
                $pdo->prepare('INSERT INTO tb_packages (title, slug, regions, nights, hero, summary, price, itinerary, published, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
                    ->execute([$title, $slug, $regions, $nights, $hero, $summary, $price, $itinJson, $published, (function_exists('admin_user') && admin_user() ? (int) (admin_user()['id'] ?? 0) : null)]);
                $pid = (int) $pdo->lastInsertId();
            }
            $__base = rtrim((string) (function_exists('url') ? url('') : ''), '/');
            header('Location: ' . $__base . '/admin/package.php?id=' . $pid . '&saved=1');
            exit;
        } catch (Throwable $e) { $__errmsg = 'Save failed.'; }
    }
}

$__id = (int) ($_GET['id'] ?? 0);
$pkg = null;
if ($__id > 0) {
    try { $st = $pdo->prepare('SELECT * FROM tb_packages WHERE id=?'); $st->execute([$__id]); $pkg = $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) { $pkg = null; }
}
$__showList = ($__id === 0 && !isset($_GET['new']));
$__list = [];
if ($__showList) {
    try { $__list = $pdo->query('SELECT id, title, nights, price, published, updated_at FROM tb_packages ORDER BY updated_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $__list = []; }
}
$__rup = static function ($n): string { return '₹' . number_format((float) $n); };
admin_header('Package builder', 'packages');
PHPX;

/* 4. HTML body */
$htmlBody = <<<'HTMLX'

<style>
.pkgform label{display:block;font-weight:600;margin:12px 0 4px}
.pkgform input[type=text],.pkgform input[type=number],.pkgform textarea{width:100%;max-width:560px;padding:9px;border:1px solid var(--rule,#ccc);border-radius:7px}
.pkgform textarea{min-height:70px}
.flash.ok{background:#e4f5ec;border:1px solid #0d7a4f;color:#0d7a4f;border-radius:8px;padding:10px 14px;margin:12px 0}
.flash.err{background:#fdecec;border:1px solid #b00020;color:#b00020;border-radius:8px;padding:10px 14px;margin:12px 0}
.pkgtable{width:100%;border-collapse:collapse;margin-top:12px}
.pkgtable th,.pkgtable td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--rule,#eee);font-size:14px}
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:12px}
.badge.on{background:#e4f5ec;color:#0d7a4f}
.badge.off{background:#f0f0ee;color:#777}
.dayb{border:1px solid var(--rule,#ddd);border-radius:8px;padding:12px 14px;margin:10px 0;background:#fff;max-width:560px}
.dayb .dh{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.dayb .dh .dn{font-family:'Cormorant Garamond',serif;font-size:18px;color:var(--gold,#b08d57)}
.dayb input{width:100%;margin:4px 0}
.dayb .drow{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.dayb .itrow{display:flex;gap:8px;align-items:center;margin:3px 0}
.dayb .itrow .itn{flex:1}
@media(max-width:700px){.dayb .drow{grid-template-columns:1fr}}
</style>

<div class="pkgform" style="max-width:600px">
<h1 style="font-family:'Cormorant Garamond',serif;font-weight:600">Package builder</h1>
<?php if (isset($_GET['saved'])): ?><div class="flash ok">Package saved.</div><?php endif; ?>
<?php if ($__errmsg !== ''): ?><div class="flash err"><?= e($__errmsg) ?></div><?php endif; ?>

<?php if ($__showList): ?>
  <p style="margin:10px 0"><a class="btn" href="?new=1">+ New package</a></p>
  <?php if ($__list): ?>
  <table class="pkgtable">
    <tr><th>Title</th><th>Nights</th><th>From</th><th>Status</th><th></th></tr>
    <?php foreach ($__list as $row): ?>
    <tr>
      <td><?= e((string) $row['title']) ?></td>
      <td><?= e((string) (int) $row['nights']) ?></td>
      <td><?= e($__rup($row['price'])) ?></td>
      <td><?= (int) $row['published'] === 1 ? '<span class="badge on">Published</span>' : '<span class="badge off">Draft</span>' ?></td>
      <td><a href="?id=<?= (int) $row['id'] ?>">Edit</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><p style="color:#777">No packages yet. Create your first one.</p><?php endif; ?>

<?php else: ?>
  <form method="post">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
    <input type="hidden" name="id" value="<?= (int) ($pkg['id'] ?? 0) ?>">
    <label>Title</label>
    <input type="text" name="title" value="<?= e((string) ($pkg['title'] ?? '')) ?>" placeholder="e.g. Kerala Honeymoon Escape — 5 Nights">
    <label>Regions</label>
    <input type="text" name="regions" value="<?= e((string) ($pkg['regions'] ?? '')) ?>" placeholder="Kochi, Munnar, Alleppey">
    <label>Nights</label>
    <input type="number" name="nights" value="<?= e((string) (int) ($pkg['nights'] ?? 0)) ?>" min="0">
    <label>Hero image URL (optional)</label>
    <input type="text" name="hero" value="<?= e((string) ($pkg['hero'] ?? '')) ?>" placeholder="https://…">
    <label>Summary</label>
    <textarea name="summary" placeholder="Short description for the catalog card"><?= e((string) ($pkg['summary'] ?? '')) ?></textarea>
    <label>From price (₹)</label>
    <input type="number" name="price" value="<?= e((string) ($pkg['price'] ?? '')) ?>" min="0" step="1">

    <div style="margin-top:16px">
      <label style="font-size:17px">Day-by-day itinerary</label>
      <div id="itin" data-init="<?= base64_encode((string) ($pkg['itinerary'] ?? '')) ?>" style="margin-top:8px"></div>
      <button type="button" class="btn ghost small" id="addDay">+ Add day</button>
      <p style="color:#777;font-size:13px">Activities subtotal (reference): ₹<span id="itemsTot">0</span></p>
      <input type="hidden" name="itinerary_json" id="itinerary_json">
    </div>

    <label style="font-weight:600;margin-top:14px"><input type="checkbox" name="published" value="1" <?= !empty($pkg['published']) ? 'checked' : '' ?>> Published (visible in catalog)</label>
    <div style="margin-top:16px"><button class="btn" type="submit">Save package</button> &nbsp; <a href="package.php">Back to list</a></div>
  </form>

  <script>/*PKG_ITIN_BUILDER*/
  (function(){
    var wrap=document.getElementById('itin'); if(!wrap) return;
    var H=document.getElementById('itinerary_json');
    function v(s){return s==null?'':(''+s);}
    function itemRow(it){ it=it||{n:'',c:''}; var r=document.createElement('div'); r.className='itrow';
      r.innerHTML='<input class="itn" placeholder="Activity / inclusion"><input class="itc" type="number" placeholder="cost" style="width:100px"><button type="button" class="btn ghost small rmit">&times;</button>';
      r.querySelector('.itn').value=v(it.n); r.querySelector('.itc').value=(it.c||it.c===0)?it.c:'';
      r.querySelector('.rmit').addEventListener('click',function(){r.remove();ser();});
      return r;
    }
    function dayBlock(d){ d=d||{title:'',hotel:'',meal:'',transport:'',items:[]};
      var el=document.createElement('div'); el.className='dayb';
      el.innerHTML='<div class="dh"><span class="dn"></span><button type="button" class="btn ghost small rmday">remove day</button></div>'
        +'<input class="dt" placeholder="Day title (e.g. Arrival in Kochi)">'
        +'<div class="drow"><input class="dhotel" placeholder="Hotel / stay"><input class="dmeal" placeholder="Meals (CP/MAP/AP)"><input class="dtrans" placeholder="Transport"></div>'
        +'<div class="items"></div><button type="button" class="btn ghost small addit">+ activity</button>';
      el.querySelector('.dt').value=v(d.title); el.querySelector('.dhotel').value=v(d.hotel);
      el.querySelector('.dmeal').value=v(d.meal); el.querySelector('.dtrans').value=v(d.transport);
      var its=el.querySelector('.items'); (d.items||[]).forEach(function(it){its.appendChild(itemRow(it));});
      el.querySelector('.addit').addEventListener('click',function(){its.appendChild(itemRow());ser();});
      el.querySelector('.rmday').addEventListener('click',function(){el.remove();ser();});
      return el;
    }
    function ser(){
      var days=[].map.call(wrap.querySelectorAll('.dayb'),function(el,i){
        el.querySelector('.dn').textContent='Day '+(i+1);
        return {day:i+1,title:el.querySelector('.dt').value,hotel:el.querySelector('.dhotel').value,
          meal:el.querySelector('.dmeal').value,transport:el.querySelector('.dtrans').value,
          items:[].map.call(el.querySelectorAll('.itrow'),function(r){return {n:r.querySelector('.itn').value,c:parseFloat(r.querySelector('.itc').value)||0};}).filter(function(x){return x.n||x.c;})};
      });
      H.value=JSON.stringify(days);
      var t=0; days.forEach(function(d){d.items.forEach(function(it){t+=it.c||0;});});
      var sp=document.getElementById('itemsTot'); if(sp)sp.textContent=Math.round(t);
    }
    var init=[]; try{ var b=wrap.getAttribute('data-init')||''; if(b){ init=JSON.parse(atob(b))||[]; } }catch(e){ init=[]; }
    if(!init.length) init=[{}];
    init.forEach(function(d){ wrap.appendChild(dayBlock(d)); });
    document.getElementById('addDay').addEventListener('click',function(){ wrap.appendChild(dayBlock()); ser(); });
    wrap.addEventListener('input',ser); ser();
  })();
  </script>
<?php endif; ?>
</div>
HTMLX;

$pkgFile = rtrim($prologue,"\n")."\n".$bodyPHP."\n?>\n".$htmlBody."\n<?php admin_footer();\n";

/* 5. lint + write */
$lint='n/a';
$t=tempnam(sys_get_temp_dir(),'pk'); file_put_contents($t,$pkgFile);
exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc); unlink($t);
if($rc!==0){ $lint='FAIL'; }
else{
  $dst="$root/admin/package.php";
  if(is_file($dst)) copy($dst,$dst.'.bak.patch28');
  file_put_contents($dst,$pkgFile); $lint='ok-written';
}
$rep[]='lint='.$lint;
$rep[]='plen='.strlen($pkgFile);

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
file_put_contents("$root/_pk_st.txt", implode("\n",$rep)."\ncache=$cc\n");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
