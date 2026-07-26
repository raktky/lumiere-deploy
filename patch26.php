<?php
/* patch26: Template library.
   - tb_pkg_templates (name,nights,itinerary,created_by,created_at)
   - quote.php: template toolbar (load+apply, save-as-template), ?tpl override of editor init,
     save-as-template handled inside existing authenticated POST (rides csrf).
   Idempotent; lint before write; reports only integer counts (no source). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$rep=[];

/* 1. migrate */
try{
  db()->exec("CREATE TABLE IF NOT EXISTS tb_pkg_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    nights INT NULL,
    itinerary MEDIUMTEXT NULL,
    created_by INT NULL,
    created_at DATETIME NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $rep[]='migrate=ok';
}catch(Throwable $e){ $rep[]='migrate=ERR'; }

/* 2. quote.php edits */
$qf="$root/admin/quote.php";
$c=file_get_contents($qf); $c0=$c;

/* 2a. data-init override -> $__editInit */
$oldInit='data-init="<?= base64_encode((string) (is_array($q) ? ($q[\'itinerary\'] ?? \'\') : \'\')) ?>"';
$newInit='data-init="<?= base64_encode((string) $__editInit) ?>"';
if(strpos($c,$newInit)!==false){ $rep[]='init=already'; }
else { $c=str_replace($oldInit,$newInit,$c,$na); $rep[]='init='.$na; }

/* 2b. toolbar + compute block before <div id="itin" */
$anchorItin='<div id="itin"';
if(strpos($c,'TPL toolbar')===false && strpos($c,$anchorItin)!==false){
  $blk = <<<'PHP'
<?php /* TPL toolbar */ $__tpls=[]; try{ $__tpls=$pdo->query("SELECT id,name,itinerary FROM tb_pkg_templates ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){} $__editInit=(is_array($q)?($q['itinerary']??''):''); $__tplSel=(int)($_GET['tpl']??0); if($__tplSel){ foreach($__tpls as $__t){ if((int)$__t['id']===$__tplSel){ $__editInit=(string)$__t['itinerary']; } } } ?>
<div class="tplbar" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:2px 0 12px">
  <?php if ($__tpls): ?>
  <select id="tplPick" style="max-width:240px;padding:8px">
    <option value="">&mdash; Load saved template &mdash;</option>
    <?php foreach ($__tpls as $__t): ?><option value="<?= (int) $__t['id'] ?>"<?= $__tplSel===(int)$__t['id']?' selected':'' ?>><?= e((string) $__t['name']) ?></option><?php endforeach; ?>
  </select>
  <button type="button" class="btn ghost small" id="tplApply">Apply</button>
  <?php endif; ?>
  <input type="text" id="tplName" placeholder="Name to save as template&hellip;" style="max-width:220px;padding:8px">
  <button type="button" class="btn ghost small" id="tplSave">Save as template</button>
  <input type="hidden" name="save_as_template" id="save_as_template" value="">
  <input type="hidden" name="tpl_name" id="tpl_name_h" value="">
</div>

PHP;
  $c=str_replace($anchorItin,$blk.$anchorItin,$c,$nb); $rep[]='toolbar='.$nb;
} else { $rep[]='toolbar=already/anchorfail'; }

/* 2c. save-as-template inside existing POST (after patch21 itinerary UPDATE) */
$updNeedle="            \$saved = true;\n            try { \$pdo->prepare('UPDATE tb_quotes SET itinerary=?, title=? WHERE trip_request_id=?')->execute([isset(\$itinJson)?\$itinJson:null, isset(\$pkgTitle)?\$pkgTitle:'', \$id]); } catch (Throwable \$e) {}";
if(strpos($c,'save_as_template')!==false && strpos($c,'INSERT INTO tb_pkg_templates')===false && strpos($c,$updNeedle)!==false){
  $tplSave=$updNeedle."\n            if (!empty(\$_POST['save_as_template']) && isset(\$itinJson) && \$itinJson) { try { \$pdo->prepare('INSERT INTO tb_pkg_templates (name,nights,itinerary,created_by,created_at) VALUES (?,?,?,?,NOW())')->execute([ (trim((string)(\$_POST['tpl_name'] ?? '')) !== '' ? trim((string) \$_POST['tpl_name']) : (isset(\$pkgTitle) && \$pkgTitle!=='' ? \$pkgTitle : 'Template')), (isset(\$itin) && is_array(\$itin) ? count(\$itin) : 0), \$itinJson, (function_exists('admin_user') && admin_user() ? (int) (admin_user()['id'] ?? 0) : null) ]); } catch (Throwable \$e) {} }";
  $c=str_replace($updNeedle,$tplSave,$c,$nc); $rep[]='tplsave='.$nc;
} else { $rep[]='tplsave=skip('.(strpos($c,'INSERT INTO tb_pkg_templates')!==false?'already':(strpos($c,$updNeedle)!==false?'ok-anchor':'no-anchor')).')'; }

/* 2d. JS before admin_footer */
$footNeedle='<?php admin_footer();';
if(strpos($c,'TPL_LIB')===false && strpos($c,$footNeedle)!==false){
  $js = <<<'JS'
<script>/*TPL_LIB*/
(function(){
  var ap=document.getElementById('tplApply');
  if(ap) ap.addEventListener('click',function(){ var s=document.getElementById('tplPick'); if(!s||!s.value) return; var u=new URL(location.href); u.searchParams.set('tpl',s.value); location.href=u.toString(); });
  var sv=document.getElementById('tplSave');
  if(sv) sv.addEventListener('click',function(){ var el=document.getElementById('tplName'); var n=(el&&el.value)||''; if(!n.trim()){ if(el){el.focus();} return; } document.getElementById('save_as_template').value='1'; document.getElementById('tpl_name_h').value=n.trim(); document.dispatchEvent(new Event('input')); var f=sv.closest('form'); if(f){ f.submit(); } });
})();
</script>
JS;
  $c=str_replace($footNeedle,$js."\n".$footNeedle,$c,$nd); $rep[]='js='.$nd;
} else { $rep[]='js=already/anchorfail'; }

/* write with lint */
$lint='n/a';
if($c!==$c0){
  $t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$c);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $lint='FAIL'; }
  else{ copy($qf,$qf.'.bak.patch26'); file_put_contents($qf,$c); $lint='ok-written'; }
} else { $lint='no-change'; }
$rep[]='lint='.$lint;

/* cleanup temp diag files */
foreach(['_dq_ab.txt','_dq2.txt'] as $x){ @unlink("$root/$x"); }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
file_put_contents("$root/_dq3.txt", implode("\n",$rep)."\ncache=$cc\n");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
