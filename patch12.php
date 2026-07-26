<?php
/* patch12 (CRM milestone 1): quote calculator.
   - tb_quotes table
   - admin/quote.php  (per trip-request quote calculator: km + vehicle + hotels + margin -> customer price)
   - admin/quotes.php (list of trip requests with quote status)
   - admin_ui.php nav: add Rates - Vehicles, Rates - Hotels, Quotes
   - remove public dump _d4_kx7.txt
   Idempotent; lints new files; backs up admin_ui.php. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
@unlink("$root/_d4_kx7.txt"); @unlink("$root/_d3_kx7.txt");

$errs=[];

/* 1. table */
try{
  db(); // ensure loaded via config+db below
}catch(Throwable $e){}
require_once "$root/app/config.php";
require_once "$root/app/db.php";
try{
  db()->exec("CREATE TABLE IF NOT EXISTS tb_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_request_id INT NOT NULL,
    total_km INT NOT NULL DEFAULT 0,
    vehicle_model VARCHAR(120) NULL,
    vehicle_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    hotel_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    hotel_sell DECIMAL(10,2) NOT NULL DEFAULT 0,
    margin DECIMAL(10,2) NOT NULL DEFAULT 0,
    customer_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    breakdown MEDIUMTEXT NULL,
    updated_by VARCHAR(120) NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_tr (trip_request_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "tb_quotes ready\n";
}catch(Throwable $e){ echo "DB error: ".$e->getMessage()."\n"; $errs[]='db'; }

/* 2. admin/quote.php */
$quotePhp = <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/admin_ui.php';
require_admin();

$pdo = db();
$id  = (int) ($_GET['id'] ?? 0);
$tr  = null;
if ($id > 0) { $s = $pdo->prepare('SELECT * FROM trip_requests WHERE id = ?'); $s->execute([$id]); $tr = $s->fetch(); }

if (!$tr) {
    admin_header('Quote', 'quotes');
    echo '<h1>Quote</h1><p class="muted">Choose a trip request from <a href="quotes.php">Quotes</a>.</p>';
    admin_footer();
    exit;
}

if (empty($_SESSION['q_csrf'])) { $_SESSION['q_csrf'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['q_csrf'];

$travelDate = (string) ($tr['start_date'] ?: date('Y-m-d'));
$nights     = max(1, (int) $tr['nights']);

/* ---- distance estimate --------------------------------------------- */
$DIST = [];
foreach ($pdo->query('SELECT from_loc, to_loc, km FROM tb_distances') as $d) {
    $DIST[strtolower($d['from_loc']) . '|' . strtolower($d['to_loc'])] = (int) $d['km'];
}
$NEAR = ['fort kochi'=>'kochi','ernakulam'=>'kochi','cochin'=>'kochi','kochi'=>'kochi','nedumbassery'=>'kochi',
    'marari'=>'alleppey','mararikulam'=>'alleppey','alappuzha'=>'alleppey','alleppey'=>'alleppey',
    'kumarakom'=>'kumarakom','kottayam'=>'kumarakom','munnar'=>'munnar','thekkady'=>'thekkady','periyar'=>'thekkady',
    'wayanad'=>'wayanad','kalpetta'=>'wayanad','kovalam'=>'trivandrum','trivandrum'=>'trivandrum',
    'thiruvananthapuram'=>'trivandrum','kozhikode'=>'kozhikode','calicut'=>'kozhikode'];
$nearnode = static function (string $s) use ($NEAR): string {
    $s = strtolower(trim($s));
    foreach ($NEAR as $k => $v) { if ($k !== '' && strpos($s, $k) !== false) { return $v; } }
    return $s;
};
$legkm = static function (string $a, string $b) use ($DIST, $nearnode): int {
    $a = $nearnode($a); $b = $nearnode($b);
    if ($a === $b) { return 35; }
    return $DIST[$a . '|' . $b] ?? $DIST[$b . '|' . $a] ?? 120;
};

$route = array_values(array_filter(array_map('trim', explode(',', (string) $tr['regions']))));
$pick = ''; $drop = '';
if (preg_match('/Pickup:\s*([^\/\n]+)/i', (string) $tr['notes'], $m)) { $pick = trim($m[1]); }
if (preg_match('/Drop:\s*([^\/\n]+)/i', (string) $tr['notes'], $m))   { $drop = trim($m[1]); }
$seq = [];
if ($pick !== '') { $seq[] = $pick; }
foreach ($route as $r) { $seq[] = $r; }
if ($drop !== '') { $seq[] = $drop; }
$estKm = 0;
for ($i = 0; $i < count($seq) - 1; $i++) { $estKm += $legkm($seq[$i], $seq[$i + 1]); }
if (count($seq) >= 2) { $estKm += $legkm($seq[count($seq) - 1], $seq[0]); } // return leg (round trip)

/* ---- hotels picked -------------------------------------------------- */
$hotels = json_decode((string) $tr['hotels_selected'], true);
if (!is_array($hotels)) { $hotels = []; }

/* helper: first integer in a price string like "15000 - 35000" */
$firstNum = static function ($s): int { return preg_match('/\d[\d,]*/', (string) $s, $mm) ? (int) str_replace(',', '', $mm[0]) : 0; };

/* hotel sell/cost rate lookup from tb_hotel_rates (by name + travel date) */
$hrateStmt = $pdo->prepare(
    "SELECT cost_rate, sell_rate FROM tb_hotel_rates
     WHERE active = 1 AND hotel = ?
       AND (period_from IS NULL OR period_from = '' OR period_from <= ?)
       AND (period_to   IS NULL OR period_to   = '' OR period_to   >= ?)
     ORDER BY (room = ?) DESC, (meal = ?) DESC, id DESC LIMIT 1"
);
foreach ($hotels as $i => $h) {
    $sell = 0; $cost = 0;
    try {
        $hrateStmt->execute([$h['name'] ?? '', $travelDate, $travelDate, $h['room'] ?? '', $h['meal'] ?? '']);
        if ($r = $hrateStmt->fetch()) { $cost = (float) $r['cost_rate']; $sell = (float) $r['sell_rate']; }
    } catch (Throwable $e) {}
    if ($sell <= 0) { $sell = $firstNum($h['rate'] ?? ''); } // fallback to price_range low end
    $hotels[$i]['_sell'] = $sell;
    $hotels[$i]['_cost'] = $cost;
    $hotels[$i]['_nights'] = 0; // team assigns below
}

/* ---- vehicles + current rate (by travel date) ---------------------- */
$vehicles = $pdo->query("SELECT model FROM tb_vehicles WHERE active = 1 ORDER BY category, sort, model")->fetchAll(PDO::FETCH_COLUMN);
$vrateStmt = $pdo->prepare(
    "SELECT base_km, base_amount, extra_per_km FROM tb_vehicle_rates
     WHERE active = 1 AND vehicle_model = ?
       AND (period_from IS NULL OR period_from = '' OR period_from <= ?)
       AND (period_to   IS NULL OR period_to   = '' OR period_to   >= ?)
     ORDER BY id DESC LIMIT 1"
);
$vrates = [];
foreach ($vehicles as $vm) {
    $vrates[$vm] = ['base_km'=>0,'base_amount'=>0,'extra_per_km'=>0];
    try { $vrateStmt->execute([$vm, $travelDate, $travelDate]); if ($r = $vrateStmt->fetch()) {
        $vrates[$vm] = ['base_km'=>(int)$r['base_km'],'base_amount'=>(float)$r['base_amount'],'extra_per_km'=>(float)$r['extra_per_km']];
    } } catch (Throwable $e) {}
}

/* ---- existing saved quote ------------------------------------------ */
$q = null;
try { $s = $pdo->prepare('SELECT * FROM tb_quotes WHERE trip_request_id = ?'); $s->execute([$id]); $q = $s->fetch(); } catch (Throwable $e) {}

/* prefill saved per-hotel nights from breakdown */
$savedHotelN = [];
if ($q && $q['breakdown']) { $bd = json_decode((string) $q['breakdown'], true); if (isset($bd['hotels']) && is_array($bd['hotels'])) { foreach ($bd['hotels'] as $bh) { $savedHotelN[] = (int) ($bh['nights'] ?? 0); } } }
foreach ($hotels as $i => $h) { $hotels[$i]['_nights'] = $savedHotelN[$i] ?? ($i === 0 ? $nights : 0); }

$curKm     = $q ? (int) $q['total_km'] : $estKm;
$curVeh    = $q ? (string) $q['vehicle_model'] : ($vehicles[0] ?? '');
$curMargin = $q ? (float) $q['margin'] : 0;
$curPrice  = $q ? (float) $q['customer_price'] : 0;

/* ---- save ----------------------------------------------------------- */
$saved = false; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($CSRF, (string) ($_POST['q_csrf'] ?? ''))) {
        $err = 'Session expired, please retry.';
    } else {
        $km       = max(0, (int) ($_POST['total_km'] ?? 0));
        $veh      = trim((string) ($_POST['vehicle_model'] ?? ''));
        $margin   = (float) ($_POST['margin'] ?? 0);
        $price    = (float) ($_POST['customer_price'] ?? 0);
        $hn       = $_POST['hnights'] ?? [];
        $hs       = $_POST['hsell'] ?? [];
        $hc       = $_POST['hcost'] ?? [];
        $vr = $vrates[$veh] ?? ['base_km'=>0,'base_amount'=>0,'extra_per_km'=>0];
        $vehicleCost = (float) $vr['base_amount'] + max(0, $km - (int) $vr['base_km']) * (float) $vr['extra_per_km'];
        $hotelSell = 0; $hotelCost = 0; $bdHotels = [];
        foreach ($hotels as $i => $h) {
            $n = max(0, (int) ($hn[$i] ?? 0));
            $sell = (float) ($hs[$i] ?? 0);
            $cost = (float) ($hc[$i] ?? 0);
            $hotelSell += $n * $sell; $hotelCost += $n * $cost;
            $bdHotels[] = ['name'=>$h['name'] ?? '','stop'=>$h['stop'] ?? '','room'=>$h['room'] ?? '','meal'=>$h['meal'] ?? '','nights'=>$n,'sell'=>$sell,'cost'=>$cost];
        }
        if ($price <= 0) { $price = $hotelSell + $vehicleCost + $margin; }
        $breakdown = json_encode(['hotels'=>$bdHotels,'vehicle'=>['model'=>$veh,'cost'=>$vehicleCost,'rate'=>$vr]], JSON_UNESCAPED_UNICODE);
        $by = (string) ($_SESSION['admin_user'] ?? $_SESSION['admin'] ?? 'admin');
        try {
            $pdo->prepare(
                'INSERT INTO tb_quotes (trip_request_id, total_km, vehicle_model, vehicle_cost, hotel_cost, hotel_sell, margin, customer_price, breakdown, updated_by, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE total_km=VALUES(total_km), vehicle_model=VALUES(vehicle_model), vehicle_cost=VALUES(vehicle_cost),
                   hotel_cost=VALUES(hotel_cost), hotel_sell=VALUES(hotel_sell), margin=VALUES(margin), customer_price=VALUES(customer_price),
                   breakdown=VALUES(breakdown), updated_by=VALUES(updated_by), updated_at=NOW()'
            )->execute([$id, $km, $veh, $vehicleCost, $hotelCost, $hotelSell, $margin, $price, $breakdown, $by]);
            $saved = true;
            $curKm = $km; $curVeh = $veh; $curMargin = $margin; $curPrice = $price;
            foreach ($hotels as $i => $h) { $hotels[$i]['_nights'] = max(0,(int)($hn[$i] ?? 0)); $hotels[$i]['_sell']=(float)($hs[$i]??0); $hotels[$i]['_cost']=(float)($hc[$i]??0); }
        } catch (Throwable $e) { $err = 'Save failed: ' . $e->getMessage(); }
    }
}

$routeLabel = $tr['regions'] !== '' ? str_replace(',', ' -> ', (string) $tr['regions']) : 'Route to suggest';

admin_header('Quote #' . $id, 'quotes');
flash_show();
?>
<style>
.qt{max-width:960px}
.qt .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 22px}
.qt .box{background:#fff;border:1px solid var(--rule);border-radius:8px;padding:16px 18px;margin:0 0 18px}
.qt table{margin:6px 0}
.qt .num{width:120px}
.qt .tot{font-family:'Cormorant Garamond',serif;font-size:26px;color:var(--gold)}
.qt .kv{font-size:13px;color:var(--sage)}
.qt .right{text-align:right}
.qt .internal{background:#fbf7ef}
</style>
<div class="qt">
<h1>Quote #<?= e((string)$id) ?> &mdash; <?= e((string)$tr['name']) ?></h1>
<p class="muted"><?= e($routeLabel) ?> &middot; <?= e((string)$nights) ?> nights &middot; <?= e((string)$tr['adults']) ?>A<?= ((int)$tr['children']>0?'+'.e((string)$tr['children']).'C':'') ?> &middot; <?= e((string)($tr['start_date']?:'dates TBD')) ?> &middot; <a href="list.php?t=trip_requests">back to trip requests</a></p>

<?php if ($saved): ?><div class="flash">Quote saved.</div><?php endif; ?>
<?php if ($err !== ''): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>

<form method="post">
<input type="hidden" name="q_csrf" value="<?= e($CSRF) ?>">

<div class="box">
  <h2>Vehicle &amp; distance</h2>
  <p class="kv">Auto-estimated round-trip distance: <strong><?= e((string)$estKm) ?> km</strong> (editable).</p>
  <div class="grid">
    <div>
      <label>Total km (round trip)</label>
      <input type="number" class="num" id="km" name="total_km" value="<?= e((string)$curKm) ?>">
    </div>
    <div>
      <label>Vehicle</label>
      <select id="veh" name="vehicle_model">
        <?php foreach ($vehicles as $vm): $vr=$vrates[$vm]; ?>
        <option value="<?= e($vm) ?>" data-basekm="<?= e((string)$vr['base_km']) ?>" data-base="<?= e((string)$vr['base_amount']) ?>" data-extra="<?= e((string)$vr['extra_per_km']) ?>" <?= $vm===$curVeh?'selected':'' ?>><?= e($vm) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="help" id="vrate"></p>
    </div>
  </div>
  <p class="kv internal">Vehicle cost (internal): <strong>&#8377;<span id="vcost">0</span></strong> = base + max(0, km &minus; base km) &times; extra/km</p>
</div>

<div class="box">
  <h2>Hotels</h2>
  <?php if (!$hotels): ?>
    <p class="muted">No hotels were selected on this request. Add rows from <a href="list.php?t=rate_hotel">Rates &middot; Hotels</a> and enter nights + rate manually if needed.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Stop</th><th>Hotel</th><th>Room / Meal</th><th>Nights</th><th class="internal">Cost/night</th><th>Sell/night</th><th class="right">Line (sell)</th></tr></thead>
    <tbody>
    <?php foreach ($hotels as $i => $h): ?>
      <tr>
        <td><?= e((string)($h['stop'] ?? '')) ?></td>
        <td><?= e((string)($h['name'] ?? '')) ?><br><span class="muted"><?= e((string)($h['star'] ?? '')) ?></span></td>
        <td><?= e((string)($h['room'] ?? '-')) ?> / <?= e((string)($h['meal'] ?? '')) ?></td>
        <td><input type="number" class="num hn" data-i="<?= e((string)$i) ?>" name="hnights[<?= e((string)$i) ?>]" value="<?= e((string)$h['_nights']) ?>" style="width:70px"></td>
        <td class="internal"><input type="number" class="num hc" data-i="<?= e((string)$i) ?>" name="hcost[<?= e((string)$i) ?>]" value="<?= e((string)$h['_cost']) ?>" style="width:100px"></td>
        <td><input type="number" class="num hs" data-i="<?= e((string)$i) ?>" name="hsell[<?= e((string)$i) ?>]" value="<?= e((string)$h['_sell']) ?>" style="width:100px"></td>
        <td class="right">&#8377;<span class="hline" data-i="<?= e((string)$i) ?>">0</span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="kv">Total nights assigned should equal <?= e((string)$nights) ?>. Rates prefilled from Rates &middot; Hotels (or the hotel price range) &mdash; adjust as needed.</p>
  <p class="kv internal">Hotels cost (internal): <strong>&#8377;<span id="hcostt">0</span></strong> &middot; Hotels sell: <strong>&#8377;<span id="hsellt">0</span></strong></p>
  <?php endif; ?>
</div>

<div class="box">
  <h2>Totals</h2>
  <div class="grid">
    <div>
      <label>Margin / markup (&#8377;)</label>
      <input type="number" class="num" id="margin" name="margin" value="<?= e((string)$curMargin) ?>">
      <p class="help">Added on top of hotel sell + vehicle cost.</p>
    </div>
    <div>
      <label>Customer price (&#8377;) &mdash; sales editable</label>
      <input type="number" class="num" id="price" name="customer_price" value="<?= e((string)($curPrice ?: '')) ?>" placeholder="auto">
      <p class="help">Leave blank to auto = hotels sell + vehicle + margin.</p>
    </div>
  </div>
  <p class="kv internal">Suggested total: hotels sell &#8377;<span id="s_h">0</span> + vehicle &#8377;<span id="s_v">0</span> + margin &#8377;<span id="s_m">0</span> = <span class="tot">&#8377;<span id="s_t">0</span></span></p>
  <button class="btn" type="submit">Save quote</button>
</div>
</form>
</div>

<script>
(function(){
  function n(v){v=parseFloat(v);return isNaN(v)?0:v;}
  var veh=document.getElementById('veh'),km=document.getElementById('km'),margin=document.getElementById('margin'),price=document.getElementById('price');
  function calc(){
    var o=veh?veh.options[veh.selectedIndex]:null;
    var bkm=o?n(o.dataset.basekm):0, base=o?n(o.dataset.base):0, extra=o?n(o.dataset.extra):0, K=n(km.value);
    var vcost=base+Math.max(0,K-bkm)*extra;
    var vr=document.getElementById('vrate'); if(vr&&o){vr.textContent='Rate: base '+bkm+'km = ₹'+base+', then ₹'+extra+'/km';}
    document.getElementById('vcost').textContent=Math.round(vcost);
    var hs=document.querySelectorAll('.hs'),hc=document.querySelectorAll('.hc'),hn=document.querySelectorAll('.hn');
    var hsellt=0,hcostt=0,map={};
    hn.forEach(function(el){map[el.dataset.i]=map[el.dataset.i]||{};map[el.dataset.i].n=n(el.value);});
    hs.forEach(function(el){map[el.dataset.i]=map[el.dataset.i]||{};map[el.dataset.i].s=n(el.value);});
    hc.forEach(function(el){map[el.dataset.i]=map[el.dataset.i]||{};map[el.dataset.i].c=n(el.value);});
    Object.keys(map).forEach(function(i){var r=map[i];var line=(r.n||0)*(r.s||0);hsellt+=line;hcostt+=(r.n||0)*(r.c||0);var sp=document.querySelector('.hline[data-i="'+i+'"]');if(sp)sp.textContent=Math.round(line);});
    var ht=document.getElementById('hsellt');if(ht)ht.textContent=Math.round(hsellt);
    var hct=document.getElementById('hcostt');if(hct)hct.textContent=Math.round(hcostt);
    var M=n(margin.value);
    document.getElementById('s_h').textContent=Math.round(hsellt);
    document.getElementById('s_v').textContent=Math.round(vcost);
    document.getElementById('s_m').textContent=Math.round(M);
    document.getElementById('s_t').textContent=Math.round(hsellt+vcost+M);
  }
  document.addEventListener('input',calc); if(veh)veh.addEventListener('change',calc); calc();
})();
</script>
<?php admin_footer();
PHP;

$f="$root/admin/quote.php";
$t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$quotePhp);exec('php -l '.escapeshellarg($t).' 2>&1',$o1,$rc1);unlink($t);
if($rc1!==0){ echo "quote.php lint FAIL:\n".implode("\n",$o1)."\n"; $errs[]='quote'; }
else{ file_put_contents($f,$quotePhp); echo "quote.php written\n"; }

/* 3. admin/quotes.php (list) */
$quotesPhp = <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/admin_ui.php';
require_admin();

$pdo = db();
$quotes = [];
try { foreach ($pdo->query('SELECT trip_request_id, customer_price, updated_at FROM tb_quotes') as $r) { $quotes[(int) $r['trip_request_id']] = $r; } } catch (Throwable $e) {}

$rows = $pdo->query('SELECT id, name, phone, regions, nights, start_date, style, created_at FROM trip_requests ORDER BY created_at DESC, id DESC LIMIT 200')->fetchAll();

admin_header('Quotes', 'quotes');
flash_show();
?>
<h1>Quotes</h1>
<p class="muted">Every trip request, newest first. Open one to price it (distance + vehicle + hotels + margin &rarr; customer price). Rates come from <a href="list.php?t=rate_vehicle">Rates &middot; Vehicles</a> and <a href="list.php?t=rate_hotel">Rates &middot; Hotels</a>.</p>
<?php if ($rows): ?>
<table>
<thead><tr><th>#</th><th>Name</th><th>Route</th><th>Nights</th><th>Travel</th><th>Quote</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): $qid=(int)$r['id']; $has=isset($quotes[$qid]); ?>
<tr>
  <td><strong><?= e((string)$qid) ?></strong></td>
  <td><?= e((string)$r['name']) ?><br><span class="muted"><?= e((string)$r['phone']) ?></span></td>
  <td><?= e($r['regions'] !== '' ? str_replace(',', ' -> ', (string)$r['regions']) : '-') ?></td>
  <td><?= e((string)$r['nights']) ?></td>
  <td><?= e((string)($r['start_date'] ?: '-')) ?></td>
  <td><?php if ($has): ?><span class="pill on">&#8377;<?= e((string)(int)$quotes[$qid]['customer_price']) ?></span><?php else: ?><span class="pill off">none</span><?php endif; ?></td>
  <td class="actions"><a class="btn small" href="quote.php?id=<?= e((string)$qid) ?>"><?= $has ? 'Edit quote' : 'Make quote' ?></a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p class="muted">No trip requests yet.</p>
<?php endif; ?>
<?php admin_footer();
PHP;

$f2="$root/admin/quotes.php";
$t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$quotesPhp);exec('php -l '.escapeshellarg($t).' 2>&1',$o2,$rc2);unlink($t);
if($rc2!==0){ echo "quotes.php lint FAIL:\n".implode("\n",$o2)."\n"; $errs[]='quotes'; }
else{ file_put_contents($f2,$quotesPhp); echo "quotes.php written\n"; }

/* 4. admin_ui.php nav insert */
$ui="$root/app/admin_ui.php";
$a=file_get_contents($ui);
if(strpos($a,"list.php?t=rate_vehicle")!==false){ echo "nav already has rates/quotes\n"; }
else{
  $anchor="'trip_requests' => ['Trip requests', 'list.php?t=trip_requests'],";
  $ins=$anchor."\n"
      ."'rate_vehicle' => ['Rates · Vehicles', 'list.php?t=rate_vehicle'],\n"
      ."'rate_hotel' => ['Rates · Hotels', 'list.php?t=rate_hotel'],\n"
      ."'quotes' => ['Quotes', 'quotes.php'],";
  $cnt=0; $a2=str_replace($anchor,$ins,$a,$cnt);
  if($cnt!==1){ echo "nav anchor fail=$cnt\n"; $errs[]='nav'; }
  else{
    $t=tempnam(sys_get_temp_dir(),'u');file_put_contents($t,$a2);exec('php -l '.escapeshellarg($t).' 2>&1',$o3,$rc3);unlink($t);
    if($rc3!==0){ echo "admin_ui lint FAIL:\n".implode("\n",$o3)."\n"; $errs[]='navlint'; }
    else{ copy($ui,$ui.'.bak.patch12'); file_put_contents($ui,$a2); echo "nav updated (Rates + Quotes)\n"; }
  }
}

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\n";
echo (empty($errs)?"DONE ok\n":"DONE with issues: ".implode(',',$errs)."\n");
