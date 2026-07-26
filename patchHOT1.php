<?php
/* patchHOT1: hotel rate bank.
   - tb_hotels (directory) + tb_hotel_seasons (season-wise rack_rate + special_rate)
   - admin/hotels.php (list + per-hotel season-rate editor)
   - import-hotels.php (TEMP token-gated CORS ingest; deleted in HOT2)
   - sidebar: Rates > Hotel rate bank
   Idempotent; lint; status report. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchHOT1');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* 1. tables */
try{
  db()->exec("CREATE TABLE IF NOT EXISTS tb_hotels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(120) NULL,
    name VARCHAR(200) NOT NULL,
    star VARCHAR(20) NULL,
    room_categories VARCHAR(400) NULL,
    price_range VARCHAR(60) NULL,
    created_at DATETIME NULL,
    UNIQUE KEY uq_hotel (name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS tb_hotel_seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    season_name VARCHAR(120) NULL,
    from_date DATE NULL,
    to_date DATE NULL,
    room_type VARCHAR(160) NULL,
    meal_plan VARCHAR(12) NULL,
    rack_rate DECIMAL(12,2) NULL,
    special_rate DECIMAL(12,2) NULL,
    created_at DATETIME NULL,
    INDEX (hotel_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $rep[]='tables=ok';
}catch(Throwable $e){ $rep[]='tables=ERR'; }

/* 2. admin/hotels.php (prologue copied from quotes.php) */
$src=@file_get_contents("$root/admin/quotes.php"); $prologue='';
if($src){ foreach(explode("\n",$src) as $ln){ $t=trim($ln);
  $ok=($t==='')||strpos($t,'<?php')===0||strpos($t,'declare')===0||strpos($t,'require')===0||strpos($t,'use ')===0||strpos($t,'namespace')===0||strpos($t,'ini_set')===0||strpos($t,'error_reporting')===0||strpos($t,'require_admin')!==false||strpos($t,'admin_user')!==false||strpos($t,'//')===0||strpos($t,'/*')===0||strpos($t,'*')===0||preg_match('/^\$pdo\s*=/',$t)||strpos($t,'rbac')!==false;
  if(!$ok) break; $prologue.=$ln."\n"; } }
if(strpos($prologue,'require_admin')===false){ $rep[]='hotels=BAD-prologue'; }
else{
  if(strpos($prologue,'$pdo')===false){ $prologue.="\$pdo = db();\n"; }
  $body = <<<'PHPX'

/* POST handlers */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (function_exists('csrf_check')) { csrf_check(); }
  $hid = (int) ($_POST['hotel_id'] ?? 0);
  $act = (string) ($_POST['act'] ?? '');
  try {
    if ($act === 'add_season' && $hid > 0) {
      $fd = trim((string) ($_POST['from_date'] ?? '')); $td = trim((string) ($_POST['to_date'] ?? ''));
      $fd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fd) ? $fd : null;
      $td = preg_match('/^\d{4}-\d{2}-\d{2}$/', $td) ? $td : null;
      $pdo->prepare('INSERT INTO tb_hotel_seasons (hotel_id,season_name,from_date,to_date,room_type,meal_plan,rack_rate,special_rate,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
          ->execute([$hid, trim((string) ($_POST['season_name'] ?? '')), $fd, $td, trim((string) ($_POST['room_type'] ?? '')), trim((string) ($_POST['meal_plan'] ?? '')), (float) preg_replace('/[^0-9.]/','',(string)($_POST['rack_rate'] ?? '0')), (float) preg_replace('/[^0-9.]/','',(string)($_POST['special_rate'] ?? '0'))]);
    } elseif ($act === 'del_season') {
      $pdo->prepare('DELETE FROM tb_hotel_seasons WHERE id = ?')->execute([(int) ($_POST['sid'] ?? 0)]);
    } elseif ($act === 'add_hotel') {
      $pdo->prepare('INSERT INTO tb_hotels (location,name,star,room_categories,price_range,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE location=VALUES(location), star=VALUES(star)')
          ->execute([trim((string)($_POST['location'] ?? '')), trim((string)($_POST['name'] ?? '')), trim((string)($_POST['star'] ?? '')), trim((string)($_POST['room_categories'] ?? '')), trim((string)($_POST['price_range'] ?? ''))]);
      $hid = (int) $pdo->lastInsertId();
    }
  } catch (Throwable $e) {}
  $__b = rtrim((string)(function_exists('url')?url(''):''), '/');
  header('Location: ' . $__b . '/admin/hotels.php' . ($hid>0 ? '?id='.$hid : ''));
  exit;
}
$hid = (int) ($_GET['id'] ?? 0);
$hotel = null; $seasons = [];
if ($hid > 0) {
  try { $st=$pdo->prepare('SELECT * FROM tb_hotels WHERE id=?'); $st->execute([$hid]); $hotel=$st->fetch(PDO::FETCH_ASSOC) ?: null;
        $ss=$pdo->prepare('SELECT * FROM tb_hotel_seasons WHERE hotel_id=? ORDER BY from_date, id'); $ss->execute([$hid]); $seasons=$ss->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {}
}
$list = [];
if (!$hotel) { try { $list = $pdo->query("SELECT h.*, (SELECT COUNT(*) FROM tb_hotel_seasons s WHERE s.hotel_id=h.id) sc FROM tb_hotels h ORDER BY h.location, h.name")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {} }
$rup = static function($n){ return '₹'.number_format((float)$n); };
admin_header('Hotel rate bank', 'hotel_rates');
?>
<style>
.hb label{display:block;font-weight:600;margin:8px 0 3px;font-size:13px}
.hb input,.hb select{padding:7px;border:1px solid var(--rule,#ccc);border-radius:6px}
.htable{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
.htable th,.htable td{padding:7px 9px;border-bottom:1px solid var(--rule,#eee);text-align:left}
.htable th{background:#f4efe7}
.srow{display:grid;grid-template-columns:1.1fr .9fr .9fr 1fr .7fr .8fr .8fr auto;gap:6px;align-items:end;margin-top:8px}
@media(max-width:820px){.srow{grid-template-columns:1fr 1fr}}
</style>
<div class="hb" style="max-width:940px">
<?php if (!$hotel): ?>
  <h1 style="font-family:'Cormorant Garamond',serif;font-weight:600">Hotel rate bank</h1>
  <p class="muted"><?= count($list) ?> hotels. Click a hotel to add season-wise rack &amp; special rates.</p>
  <table class="htable"><tr><th>Location</th><th>Hotel</th><th>Star</th><th>Room categories</th><th>Seasons</th><th></th></tr>
  <?php foreach ($list as $h): ?>
    <tr><td><?= e((string)$h['location']) ?></td><td><?= e((string)$h['name']) ?></td><td><?= e((string)$h['star']) ?></td>
    <td style="font-size:12px;color:#666"><?= e((string)$h['room_categories']) ?></td><td><?= (int)$h['sc'] ?></td>
    <td><a href="?id=<?= (int)$h['id'] ?>">Rates &rarr;</a></td></tr>
  <?php endforeach; ?>
  </table>
<?php else: ?>
  <p><a href="hotels.php">&larr; All hotels</a></p>
  <h1 style="font-family:'Cormorant Garamond',serif;font-weight:600"><?= e((string)$hotel['name']) ?></h1>
  <p class="muted"><?= e((string)$hotel['location']) ?> · <?= e((string)$hotel['star']) ?> · <?= e((string)$hotel['room_categories']) ?><?= $hotel['price_range']?' · est. '.e((string)$hotel['price_range']):'' ?></p>
  <h2 style="font-size:17px;margin-top:16px">Season rates</h2>
  <table class="htable"><tr><th>Season</th><th>From</th><th>To</th><th>Room</th><th>Meal</th><th>Rack</th><th>Special</th><th></th></tr>
  <?php foreach ($seasons as $s): ?>
    <tr><td><?= e((string)$s['season_name']) ?></td><td><?= e((string)$s['from_date']) ?></td><td><?= e((string)$s['to_date']) ?></td>
    <td><?= e((string)$s['room_type']) ?></td><td><?= e((string)$s['meal_plan']) ?></td><td><?= e($rup($s['rack_rate'])) ?></td><td><?= e($rup($s['special_rate'])) ?></td>
    <td><form method="post" style="margin:0"><?= function_exists('csrf_field')?csrf_field():'' ?><input type="hidden" name="hotel_id" value="<?= (int)$hid ?>"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn ghost small" name="act" value="del_season">×</button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$seasons): ?><tr><td colspan="8" class="muted">No season rates yet.</td></tr><?php endif; ?>
  </table>
  <h2 style="font-size:16px;margin-top:18px">Add season rate</h2>
  <form method="post">
    <?= function_exists('csrf_field')?csrf_field():'' ?>
    <input type="hidden" name="hotel_id" value="<?= (int)$hid ?>"><input type="hidden" name="act" value="add_season">
    <div class="srow">
      <div><label>Season</label><input name="season_name" placeholder="Peak / Dec-Jan" style="width:100%"></div>
      <div><label>From</label><input type="date" name="from_date" style="width:100%"></div>
      <div><label>To</label><input type="date" name="to_date" style="width:100%"></div>
      <div><label>Room type</label><input name="room_type" placeholder="Deluxe" style="width:100%"></div>
      <div><label>Meal</label><select name="meal_plan" style="width:100%"><option value="">–</option><option>EP</option><option>CP</option><option>MAP</option><option>AP</option></select></div>
      <div><label>Rack rate</label><input type="number" name="rack_rate" style="width:100%"></div>
      <div><label>Special rate</label><input type="number" name="special_rate" style="width:100%"></div>
      <div><button class="btn" type="submit" style="margin-bottom:1px">Add</button></div>
    </div>
  </form>
<?php endif; ?>
</div>
<?php admin_footer();
PHPX;
  put_lint("$root/admin/hotels.php",rtrim($prologue,"\n")."\n".$body."\n",$rep,'hotels');
}

/* 3. import-hotels.php (TEMP token-gated ingest, CORS) */
$imp = <<<'PHP'
<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
require __DIR__ . '/app/bootstrap.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }
if (($_GET['k'] ?? '') !== 'imp_9f3a2b7c1d8e42') { http_response_code(403); echo json_encode(['err'=>'forbidden']); exit; }
$raw = file_get_contents('php://input');
$in = json_decode((string) $raw, true);
$rows = is_array($in['hotels'] ?? null) ? $in['hotels'] : [];
$n = 0;
$st = db()->prepare('INSERT INTO tb_hotels (location,name,star,room_categories,price_range,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE location=VALUES(location), star=VALUES(star), room_categories=VALUES(room_categories), price_range=VALUES(price_range)');
foreach ($rows as $r) {
  $name = trim((string) ($r['name'] ?? '')); if ($name === '') { continue; }
  try { $st->execute([trim((string)($r['location'] ?? '')), $name, trim((string)($r['star'] ?? '')), trim((string)($r['rooms'] ?? '')), trim((string)($r['price'] ?? ''))]); $n++; } catch (Throwable $e) {}
}
echo json_encode(['ok'=>true,'inserted'=>$n]);
PHP;
put_lint("$root/import-hotels.php",$imp,$rep,'import');

/* 4. nav link Rates > Hotel rate bank */
$uf="$root/app/admin_ui.php"; $uc=file_get_contents($uf);
if(strpos($uc,"'hotel_rates'")===false){
  $uc2=preg_replace("/('rate_hotel'\\s*=>\\s*\\['Hotels',\\s*'list\\.php\\?t=rate_hotel'\\],)/","$1\n            'hotel_rates'   => ['Hotel rate bank', 'hotels.php'],",$uc,1,$nn);
  if($nn===1){ $t=tempnam(sys_get_temp_dir(),'u');file_put_contents($t,$uc2);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t); if($rc===0){ copy($uf,$uf.'.bak.patchHOT1'); file_put_contents($uf,$uc2); $rep[]='nav=ok'; } else { $rep[]='nav=lintFAIL'; } }
  else{ $rep[]='nav=anchorfail'; }
} else { $rep[]='nav=already'; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_ph1.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
