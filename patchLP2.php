<?php
/* patchLP2: admin CRUD for landing pages + campaigns report. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];
$src=@file_get_contents("$root/admin/quotes.php"); $prologue='';
if($src){ foreach(explode("\n",$src) as $ln){ $t=trim($ln);
  $ok=($t==='')||strpos($t,'<?php')===0||strpos($t,'declare')===0||strpos($t,'require')===0||strpos($t,'use ')===0||strpos($t,'namespace')===0||strpos($t,'ini_set')===0||strpos($t,'error_reporting')===0||strpos($t,'require_admin')!==false||strpos($t,'admin_user')!==false||strpos($t,'//')===0||strpos($t,'/*')===0||strpos($t,'*')===0||preg_match('/^\$pdo\s*=/',$t)||strpos($t,'rbac')!==false;
  if(!$ok) break; $prologue.=$ln."\n"; } }
if(strpos($prologue,'require_admin')===false){ $rep[]='BAD-prologue'; file_put_contents("$root/_plp2.txt",implode(" ",$rep)); echo "prologue bad\nDONE\n"; return; }
if(strpos($prologue,'$pdo')===false){ $prologue.="\$pdo = db();\n"; }
function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchLP2');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* landing.php (CRUD) */
$body1 = <<<'PHPX'

$saved=false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (function_exists('csrf_check')) { csrf_check(); }
  $id=(int)($_POST['id'] ?? 0);
  $slug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',(string)($_POST['slug'] ?? '')),'-'));
  $f=[$slug, trim((string)($_POST['title'] ?? '')), trim((string)($_POST['meta_desc'] ?? '')), trim((string)($_POST['h1'] ?? '')), trim((string)($_POST['origin'] ?? '')), trim((string)($_POST['destination'] ?? '')), trim((string)($_POST['hero'] ?? '')), trim((string)($_POST['intro'] ?? '')), trim((string)($_POST['highlights'] ?? '')), trim((string)($_POST['price_from'] ?? '')), trim((string)($_POST['wa_number'] ?? '')), (!empty($_POST['published'])?1:0)];
  try {
    if ($slug!=='') {
      if ($id>0) { $f[]=$id; $pdo->prepare('UPDATE tb_landing SET slug=?,title=?,meta_desc=?,h1=?,origin=?,destination=?,hero=?,intro=?,highlights=?,price_from=?,wa_number=?,published=?,updated_at=NOW() WHERE id=?')->execute($f); }
      else { $pdo->prepare('INSERT INTO tb_landing (slug,title,meta_desc,h1,origin,destination,hero,intro,highlights,price_from,wa_number,published,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute($f); $id=(int)$pdo->lastInsertId(); }
      $__b=rtrim((string)(function_exists('url')?url(''):''),'/'); header('Location: '.$__b.'/admin/landing.php?id='.$id.'&saved=1'); exit;
    }
  } catch (Throwable $e) {}
}
$id=(int)($_GET['id'] ?? 0); $lp=null;
if ($id>0) { try { $st=$pdo->prepare('SELECT * FROM tb_landing WHERE id=?'); $st->execute([$id]); $lp=$st->fetch(PDO::FETCH_ASSOC)?:null; } catch(Throwable $e){} }
$show=($id===0 && !isset($_GET['new']));
$rows=[]; if($show){ try{ $rows=$pdo->query('SELECT id,slug,h1,origin,destination,published FROM tb_landing ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){} }
admin_header('Landing pages','landing');
$v=function($k) use($lp){ return e((string)($lp[$k] ?? '')); };
?>
<style>.lf label{display:block;font-weight:600;font-size:13px;margin:10px 0 3px}.lf input,.lf textarea{width:100%;max-width:640px;padding:8px;border:1px solid var(--rule,#ccc);border-radius:6px}.lt{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}.lt th,.lt td{padding:7px 9px;border-bottom:1px solid var(--rule,#eee);text-align:left}.lt th{background:#f4efe7}</style>
<div class="lf" style="max-width:680px">
<?php if (isset($_GET['saved'])): ?><div style="background:#e4f5ec;border:1px solid #0d7a4f;color:#0d7a4f;padding:10px;border-radius:8px;margin:8px 0">Saved. Live at <a href="/<?= $v('slug') ?>.php" target="_blank">/<?= $v('slug') ?>.php</a> (add a wrapper file for the clean URL, or use <a href="/lp.php?s=<?= $v('slug') ?>" target="_blank">/lp.php?s=<?= $v('slug') ?></a>).</div><?php endif; ?>
<?php if ($show): ?>
  <h1 style="font-family:'Cormorant Garamond',serif;font-weight:600">Landing pages</h1>
  <p><a class="btn" href="?new=1">+ New landing page</a></p>
  <table class="lt"><tr><th>Slug</th><th>H1</th><th>Origin→Dest</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?><tr><td><?= e((string)$r['slug']) ?></td><td><?= e((string)$r['h1']) ?></td><td><?= e((string)$r['origin']) ?> → <?= e((string)$r['destination']) ?></td><td><?= (int)$r['published']?'Live':'Draft' ?></td><td><a href="?id=<?= (int)$r['id'] ?>">Edit</a> · <a href="/lp.php?s=<?= e((string)$r['slug']) ?>" target="_blank">View</a></td></tr><?php endforeach; ?>
  </table>
<?php else: ?>
  <p><a href="landing.php">← All</a></p>
  <h1 style="font-family:'Cormorant Garamond',serif;font-weight:600">Landing page</h1>
  <form method="post"><?= function_exists('csrf_field')?csrf_field():'' ?><input type="hidden" name="id" value="<?= (int)($lp['id'] ?? 0) ?>">
  <label>Slug (keyword URL)</label><input name="slug" value="<?= $v('slug') ?>" placeholder="kerala-tour-package-from-chennai">
  <label>SEO title</label><input name="title" value="<?= $v('title') ?>">
  <label>Meta description</label><textarea name="meta_desc" rows="2"><?= $v('meta_desc') ?></textarea>
  <label>H1 heading</label><input name="h1" value="<?= $v('h1') ?>">
  <label>Origin</label><input name="origin" value="<?= $v('origin') ?>">
  <label>Destination</label><input name="destination" value="<?= $v('destination') ?>">
  <label>Hero image URL</label><input name="hero" value="<?= $v('hero') ?>">
  <label>Intro</label><textarea name="intro" rows="3"><?= $v('intro') ?></textarea>
  <label>Highlights (one per line)</label><textarea name="highlights" rows="5"><?= $v('highlights') ?></textarea>
  <label>Price from</label><input name="price_from" value="<?= $v('price_from') ?>">
  <label>WhatsApp number (with country code)</label><input name="wa_number" value="<?= $v('wa_number') ?>" placeholder="9198XXXXXXXX">
  <label style="font-weight:600;margin-top:12px"><input type="checkbox" name="published" value="1" <?= (!$lp || !empty($lp['published']))?'checked':'' ?> style="width:auto"> Published</label>
  <div style="margin-top:14px"><button class="btn" type="submit">Save</button></div>
  </form>
<?php endif; ?>
</div>
<?php admin_footer();
PHPX;
put_lint("$root/admin/landing.php",rtrim($prologue,"\n")."\n".$body1."\n",$rep,'landing');

/* campaigns.php (report) */
$body2 = <<<'PHPX'

admin_header('Campaigns','campaigns');
function grp($pdo,$col){ try{ return $pdo->query("SELECT COALESCE(NULLIF($col,''),'(none)') g, COUNT(*) c FROM trip_requests WHERE $col IS NOT NULL AND $col<>'' GROUP BY g ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ return []; } }
$bySrc=grp($pdo,'utm_source'); $byCamp=grp($pdo,'utm_campaign'); $byLand=grp($pdo,'landing_slug');
$recent=[]; try{ $recent=$pdo->query("SELECT name,phone,landing_slug,utm_source,utm_campaign,created_at FROM trip_requests WHERE (landing_slug IS NOT NULL AND landing_slug<>'') OR (utm_source IS NOT NULL AND utm_source<>'') ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
?>
<style>.cw{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}@media(max-width:800px){.cw{grid-template-columns:1fr}}.cb{background:#fff;border:1px solid var(--rule,#e4ded4);border-radius:10px;padding:14px 16px}.ct{width:100%;border-collapse:collapse;font-size:13px}.ct th,.ct td{padding:6px 8px;border-bottom:1px solid var(--rule,#eee);text-align:left}.ct th{background:#f4efe7}</style>
<div style="max-width:980px">
<h1 style="font-family:'Cormorant Garamond',serif;font-weight:600">Campaigns</h1>
<p class="muted">Lead sources captured from landing pages &amp; ad UTM tags.</p>
<div class="cw">
  <div class="cb"><h3>By source</h3><table class="ct"><?php foreach($bySrc as $r): ?><tr><td><?= e((string)$r['g']) ?></td><td style="text-align:right"><?= (int)$r['c'] ?></td></tr><?php endforeach; if(!$bySrc):?><tr><td class="muted">No data yet</td></tr><?php endif;?></table></div>
  <div class="cb"><h3>By campaign</h3><table class="ct"><?php foreach($byCamp as $r): ?><tr><td><?= e((string)$r['g']) ?></td><td style="text-align:right"><?= (int)$r['c'] ?></td></tr><?php endforeach; if(!$byCamp):?><tr><td class="muted">No data yet</td></tr><?php endif;?></table></div>
  <div class="cb"><h3>By landing page</h3><table class="ct"><?php foreach($byLand as $r): ?><tr><td><?= e((string)$r['g']) ?></td><td style="text-align:right"><?= (int)$r['c'] ?></td></tr><?php endforeach; if(!$byLand):?><tr><td class="muted">No data yet</td></tr><?php endif;?></table></div>
</div>
<h2 style="font-size:18px;margin-top:22px">Recent tagged leads</h2>
<table class="ct"><tr><th>Name</th><th>Phone</th><th>Landing</th><th>Source</th><th>Campaign</th><th>When</th></tr>
<?php foreach($recent as $r): ?><tr><td><?= e((string)$r['name']) ?></td><td><?= e((string)$r['phone']) ?></td><td><?= e((string)$r['landing_slug']) ?></td><td><?= e((string)$r['utm_source']) ?></td><td><?= e((string)$r['utm_campaign']) ?></td><td><?= e((string)$r['created_at']) ?></td></tr><?php endforeach; if(!$recent):?><tr><td colspan="6" class="muted">No tagged leads yet</td></tr><?php endif;?>
</table>
</div>
<?php admin_footer();
PHPX;
put_lint("$root/admin/campaigns.php",rtrim($prologue,"\n")."\n".$body2."\n",$rep,'campaigns');

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_plp2.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
