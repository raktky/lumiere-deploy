<?php
/* patch19: Reports.
   - admin/reports.php (pipeline by month, quotes, conversion, by occasion, top routes, by vehicle)
   - admin_ui.php nav: add Reports under Overview (after CEO Dashboard). gate admin_can('reports')
   Idempotent; lint before write. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
$errs=[];
function put_lint($path,$code,&$errs,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ echo "$tag lint FAIL:\n".implode("\n",$o)."\n"; $errs[]=$tag; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patch19');
  file_put_contents($path,$code); echo "$tag written\n"; return true;
}

$rep = <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/admin_ui.php';
require_admin();
if (function_exists('admin_can') && !admin_can('reports')) { http_response_code(403); exit('Not allowed.'); }

$pdo = db();
$rows = static function (string $sql) use ($pdo): array {
    try { return $pdo->query($sql)->fetchAll(); } catch (Throwable $e) { return []; }
};
$one = static function (string $sql) use ($pdo): float {
    try { return (float) $pdo->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0.0; }
};
$rup = static function ($n): string { return '₹' . number_format((float) $n); };
$bar = static function (float $val, float $max): string {
    $p = $max > 0 ? max(2, round($val / $max * 100)) : 0;
    return '<span style="display:inline-block;height:9px;border-radius:5px;background:var(--gold);width:' . $p . '%;min-width:2px;vertical-align:middle"></span>';
};

$pipeline = $one('SELECT COALESCE(SUM(customer_price),0) FROM tb_quotes');
$quotes   = (int) $one('SELECT COUNT(*) FROM tb_quotes');
$requests = (int) $one('SELECT COUNT(*) FROM trip_requests');
$avg      = $quotes ? $pipeline / $quotes : 0;
$conv     = $requests ? round($quotes / $requests * 100) : 0;

$byMonth   = $rows("SELECT DATE_FORMAT(updated_at,'%Y-%m') ym, COUNT(*) c, COALESCE(SUM(customer_price),0) s FROM tb_quotes WHERE updated_at IS NOT NULL GROUP BY ym ORDER BY ym DESC LIMIT 12");
$maxMonth  = 0.0; foreach ($byMonth as $m) { $maxMonth = max($maxMonth, (float) $m['s']); }
$byVehicle = $rows("SELECT COALESCE(NULLIF(vehicle_model,''),'—') vm, COUNT(*) c, COALESCE(SUM(customer_price),0) s FROM tb_quotes GROUP BY vm ORDER BY c DESC");
$maxVeh    = 0; foreach ($byVehicle as $v) { $maxVeh = max($maxVeh, (int) $v['c']); }
$byOcc     = $rows("SELECT COALESCE(NULLIF(occasion,''),'—') o, COUNT(*) c FROM trip_requests GROUP BY o ORDER BY c DESC");
$maxOcc    = 0; foreach ($byOcc as $o) { $maxOcc = max($maxOcc, (int) $o['c']); }
$byRoute   = $rows("SELECT regions r, COUNT(*) c FROM trip_requests WHERE regions IS NOT NULL AND regions<>'' GROUP BY regions ORDER BY c DESC LIMIT 10");
$maxRoute  = 0; foreach ($byRoute as $r) { $maxRoute = max($maxRoute, (int) $r['c']); }

admin_header('Reports', 'reports');
flash_show();
?>
<style>
.rep .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin:6px 0 26px}
.rep .c{background:#fff;border:1px solid var(--rule);border-radius:10px;padding:18px 20px}
.rep .c .n{font-family:'Cormorant Garamond',serif;font-size:30px;line-height:1;color:var(--gold)}
.rep .c .l{font-size:11.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--sage);margin-top:7px}
.rep .grid2{display:grid;grid-template-columns:1fr 1fr;gap:26px}
@media(max-width:820px){.rep .grid2{grid-template-columns:1fr}}
.rep td .bw{display:flex;align-items:center;gap:8px}
</style>
<div class="rep">
<h1>Reports</h1>
<p class="muted">Pipeline, conversion and demand mix across all quotes and trip requests.</p>

<div class="cards">
  <div class="c"><div class="n"><?= e($rup($pipeline)) ?></div><div class="l">Pipeline value</div></div>
  <div class="c"><div class="n"><?= e((string)$quotes) ?></div><div class="l">Quotes</div></div>
  <div class="c"><div class="n"><?= e($rup($avg)) ?></div><div class="l">Avg quote</div></div>
  <div class="c"><div class="n"><?= e((string)$requests) ?></div><div class="l">Trip requests</div></div>
  <div class="c"><div class="n"><?= e((string)$conv) ?>%</div><div class="l">Conversion</div></div>
</div>

<h2>Pipeline by month</h2>
<?php if ($byMonth): ?>
<table><thead><tr><th>Month</th><th>Quotes</th><th>Value</th><th style="width:40%"></th></tr></thead><tbody>
<?php foreach ($byMonth as $m): ?>
<tr><td><?= e((string)$m['ym']) ?></td><td><?= e((string)(int)$m['c']) ?></td><td><?= e($rup($m['s'])) ?></td>
<td><div class="bw"><?= $bar((float)$m['s'],$maxMonth) ?></div></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><p class="muted">No quotes yet.</p><?php endif; ?>

<div class="grid2">
<div>
<h2>By vehicle</h2>
<?php if ($byVehicle): ?>
<table><thead><tr><th>Vehicle</th><th>Quotes</th><th>Value</th></tr></thead><tbody>
<?php foreach ($byVehicle as $v): ?>
<tr><td><div class="bw"><?= $bar((int)$v['c'],$maxVeh) ?> <?= e((string)$v['vm']) ?></div></td><td><?= e((string)(int)$v['c']) ?></td><td><?= e($rup($v['s'])) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><p class="muted">—</p><?php endif; ?>
</div>
<div>
<h2>By occasion</h2>
<?php if ($byOcc): ?>
<table><thead><tr><th>Occasion</th><th>Requests</th></tr></thead><tbody>
<?php foreach ($byOcc as $o): ?>
<tr><td><div class="bw"><?= $bar((int)$o['c'],$maxOcc) ?> <?= e((string)$o['o']) ?></div></td><td><?= e((string)(int)$o['c']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><p class="muted">—</p><?php endif; ?>
</div>
</div>

<h2>Top routes</h2>
<?php if ($byRoute): ?>
<table><thead><tr><th>Route</th><th>Requests</th><th style="width:35%"></th></tr></thead><tbody>
<?php foreach ($byRoute as $r): ?>
<tr><td><?= e(str_replace(',', ' → ', (string)$r['r'])) ?></td><td><?= e((string)(int)$r['c']) ?></td>
<td><div class="bw"><?= $bar((int)$r['c'],$maxRoute) ?></div></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><p class="muted">No routed requests yet.</p><?php endif; ?>
</div>
<?php admin_footer();
PHP;
put_lint("$root/admin/reports.php",$rep,$errs,'reports');

/* nav: add Reports after CEO Dashboard in Overview group */
$ui="$root/app/admin_ui.php";
$a=file_get_contents($ui);
if(strpos($a,"'reports'")===false){
  $anchor="'ceo_dashboard' => ['CEO Dashboard', 'dashboard-ceo.php'],";
  if(strpos($a,$anchor)!==false){
    $a=str_replace($anchor,$anchor."\n            'reports'       => ['Reports', 'reports.php'],",$a,$c);
    echo "nav reports applied=$c\n";
    if($c===1) put_lint($ui,$a,$errs,'admin_ui'); else $errs[]='nav';
  }else{ echo "nav anchor not found\n"; $errs[]='navanchor'; }
}else{ echo "nav reports already present\n"; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\n";
echo (empty($errs)?"DONE ok\n":"DONE issues: ".implode(',',$errs)."\n");
