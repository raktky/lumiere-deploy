<?php
/* patch17: CEO dashboard + CEO login.
   - create admin login ceo@lumiereholidays.com (password pre-hashed; plaintext never in this file), role = CEO
   - admin/dashboard-ceo.php (KPIs + pipeline value + recent quotes)
   - admin_ui.php nav: add "CEO Dashboard" after Dashboard (tolerant anchor)
   - grant ceo_dashboard perm to CEO role + add checkbox to roles.php
   Idempotent; lint before writing. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
require_once "$root/app/config.php";
require_once "$root/app/db.php";
$errs=[];
$CEO_HASH='$2y$12$LFDJ/cjhmXUHDhBfMwk7z.sdo6ZR1CBswgctVnJ5rAQuekL2tTvpy';

/* 1. CEO user + perm */
try{
  $pdo=db();
  $ceoRole=(int)$pdo->query("SELECT id FROM tb_roles WHERE name='CEO'")->fetchColumn();
  // grant ceo_dashboard perm to CEO role
  $pdo->exec("UPDATE tb_roles SET perms = CONCAT(perms, ',ceo_dashboard') WHERE name='CEO' AND perms NOT LIKE '%ceo_dashboard%'");
  // upsert the CEO login
  $st=$pdo->prepare("INSERT INTO admins (username, pass_hash, role_id, created_at) VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE pass_hash=VALUES(pass_hash), role_id=VALUES(role_id)");
  $st->execute(['ceo@lumiereholidays.com',$CEO_HASH,$ceoRole?:null]);
  echo "CEO login ready (role_id=$ceoRole)\n";
}catch(Throwable $e){ echo "DB error: ".$e->getMessage()."\n"; $errs[]='db'; }

function put_lint($path,$code,&$errs,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ echo "$tag lint FAIL:\n".implode("\n",$o)."\n"; $errs[]=$tag; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patch17');
  file_put_contents($path,$code); echo "$tag written\n"; return true;
}

/* 2. admin/dashboard-ceo.php */
$dash = <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/admin_ui.php';
require_admin();
if (function_exists('admin_can') && !admin_can('ceo_dashboard')) { http_response_code(403); exit('Not allowed.'); }

$pdo = db();
$one = static function (string $sql) use ($pdo): float {
    try { return (float) $pdo->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0.0; }
};

$requests  = (int) $one('SELECT COUNT(*) FROM trip_requests');
$quotes    = (int) $one('SELECT COUNT(*) FROM tb_quotes');
$pipeline  = $one('SELECT COALESCE(SUM(customer_price),0) FROM tb_quotes');
$avg       = $quotes > 0 ? $pipeline / $quotes : 0;
$newLeads  = (int) $one('SELECT COUNT(*) FROM trip_requests WHERE handled = 0')
           + (int) $one('SELECT COUNT(*) FROM enquiries WHERE handled = 0');
$conv      = $requests > 0 ? round($quotes / $requests * 100) : 0;
$mtd       = $one("SELECT COALESCE(SUM(customer_price),0) FROM tb_quotes WHERE updated_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')");

$recent = [];
try {
    $recent = $pdo->query(
        'SELECT q.trip_request_id, q.customer_price, q.total_km, q.updated_at, q.vehicle_model,
                t.name, t.regions, t.nights
         FROM tb_quotes q JOIN trip_requests t ON t.id = q.trip_request_id
         ORDER BY q.updated_at DESC LIMIT 12'
    )->fetchAll();
} catch (Throwable $e) { $recent = []; }

$rup = static function ($n): string { return '₹' . number_format((float) $n); };

admin_header('CEO Dashboard', 'ceo_dashboard');
flash_show();
?>
<style>
.ceo .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:8px 0 26px}
.ceo .kpi{background:#fff;border:1px solid var(--rule);border-radius:10px;padding:20px 22px}
.ceo .kpi .n{font-family:'Cormorant Garamond',serif;font-size:38px;line-height:1;color:var(--gold)}
.ceo .kpi .l{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--sage);margin-top:8px}
.ceo .kpi.big{background:var(--ink)}
.ceo .kpi.big .n{color:var(--ivory)}
.ceo .kpi.big .l{color:#cdb892}
</style>
<div class="ceo">
<h1>CEO Dashboard</h1>
<p class="muted">Business at a glance — pipeline value is the sum of customer prices across all saved quotes.</p>

<div class="kpis">
  <div class="kpi big"><div class="n"><?= e($rup($pipeline)) ?></div><div class="l">Pipeline value (all quotes)</div></div>
  <div class="kpi"><div class="n"><?= e($rup($mtd)) ?></div><div class="l">Quoted this month</div></div>
  <div class="kpi"><div class="n"><?= e((string) $quotes) ?></div><div class="l">Quotes made</div></div>
  <div class="kpi"><div class="n"><?= e($rup($avg)) ?></div><div class="l">Avg quote value</div></div>
  <div class="kpi"><div class="n"><?= e((string) $requests) ?></div><div class="l">Trip requests</div></div>
  <div class="kpi"><div class="n"><?= e((string) $conv) ?>%</div><div class="l">Quoted / requests</div></div>
  <div class="kpi"><div class="n"><?= e((string) $newLeads) ?></div><div class="l">New &amp; unhandled leads</div></div>
</div>

<h2>Recent quotes</h2>
<?php if ($recent): ?>
<table>
<thead><tr><th>#</th><th>Customer</th><th>Route</th><th>Nights</th><th>Vehicle</th><th>Km</th><th>Customer price</th><th>Updated</th></tr></thead>
<tbody>
<?php foreach ($recent as $r): ?>
<tr>
  <td><a href="quote.php?id=<?= e((string) (int) $r['trip_request_id']) ?>"><strong><?= e((string) (int) $r['trip_request_id']) ?></strong></a></td>
  <td><?= e((string) $r['name']) ?></td>
  <td><?= e($r['regions'] !== '' ? str_replace(',', ' → ', (string) $r['regions']) : '—') ?></td>
  <td><?= e((string) (int) $r['nights']) ?></td>
  <td><?= e((string) ($r['vehicle_model'] ?? '')) ?></td>
  <td><?= e((string) (int) $r['total_km']) ?></td>
  <td><strong><?= e($rup($r['customer_price'])) ?></strong></td>
  <td><?= e($r['updated_at'] ? date('j M Y, H:i', (int) strtotime((string) $r['updated_at'])) : '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p class="muted">No quotes saved yet. Price a trip request from <a href="quotes.php">Quotes</a> and it will appear here.</p>
<?php endif; ?>
</div>
<?php admin_footer();
PHP;
put_lint("$root/admin/dashboard-ceo.php",$dash,$errs,'dashboard-ceo');

/* 3. admin_ui.php nav: add CEO Dashboard after Dashboard */
$ui="$root/app/admin_ui.php";
$a=file_get_contents($ui);
if(strpos($a,"dashboard-ceo.php")===false){
  $a=preg_replace("/('dashboard'\s*=>\s*\['Dashboard',\s*'index\.php'\],)/",
    "$1\n'ceo_dashboard' => ['CEO Dashboard', 'dashboard-ceo.php'],", $a, 1, $cN);
  echo "nav ceo entry applied=".(int)$cN."\n";
  if($cN===1) put_lint($ui,$a,$errs,'admin_ui'); else $errs[]='nav';
}else{ echo "nav ceo entry already present\n"; }

/* 4. roles.php: add ceo_dashboard checkbox to the Menus group */
$rp="$root/admin/roles.php";
if(is_file($rp)){
  $r=file_get_contents($rp);
  if(strpos($r,"ceo_dashboard")===false){
    $r=str_replace("'dashboard' => 'Dashboard',","'dashboard' => 'Dashboard', 'ceo_dashboard' => 'CEO Dashboard',",$r,$cR);
    echo "roles checkbox applied=".(int)$cR."\n";
    if($cR===1) put_lint($rp,$r,$errs,'roles');
  }else{ echo "roles checkbox already present\n"; }
}

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\n";
echo (empty($errs)?"DONE ok\n":"DONE with issues: ".implode(',',$errs)."\n");
