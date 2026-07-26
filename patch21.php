<?php
/* patch21 (Package builder phase 1): day-by-day itinerary editor on the quote/package screen.
   - tb_quotes: add itinerary MEDIUMTEXT, title VARCHAR(200)
   - quote.php: itinerary editor UI + JS + save (activities cost folds into customer price)
   Idempotent; lint before write. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
require_once "$root/app/config.php";
require_once "$root/app/db.php";
$errs=[];

/* 1. migrate */
try{
  $pdo=db(); $cols=[];
  foreach($pdo->query("SHOW COLUMNS FROM tb_quotes")->fetchAll(PDO::FETCH_ASSOC) as $c){ $cols[$c['Field']]=1; }
  if(!isset($cols['itinerary'])){ $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN itinerary MEDIUMTEXT NULL"); echo "itinerary added\n"; }
  if(!isset($cols['title'])){ $pdo->exec("ALTER TABLE tb_quotes ADD COLUMN title VARCHAR(200) NULL"); echo "title added\n"; }
}catch(Throwable $e){ echo "DB error: ".$e->getMessage()."\n"; $errs[]='db'; }

/* 2. quote.php edits */
$qf="$root/admin/quote.php";
$c=file_get_contents($qf);
$c0=$c;

/* 2a. parse itinerary in save branch (after $hc line) */
$anchorHc="        \$hc       = \$_POST['hcost'] ?? [];";
if(strpos($c,'$itinJson')===false && strpos($c,$anchorHc)!==false){
  $ins=$anchorHc."\n"
    ."        \$ijson = (string) (\$_POST['itinerary_json'] ?? '');\n"
    ."        \$itin = json_decode(\$ijson, true); if (!is_array(\$itin)) { \$itin = []; }\n"
    ."        \$itemsCost = 0.0; foreach (\$itin as \$__d) { if (!empty(\$__d['items']) && is_array(\$__d['items'])) { foreach (\$__d['items'] as \$__it) { \$itemsCost += (float) (\$__it['c'] ?? 0); } } }\n"
    ."        \$itinJson = \$itin ? json_encode(\$itin, JSON_UNESCAPED_UNICODE) : null;\n"
    ."        \$pkgTitle = trim((string) (\$_POST['pkg_title'] ?? ''));";
  $c=str_replace($anchorHc,$ins,$c,$n1); echo "parse applied=$n1\n"; if($n1!==1)$errs[]='parse';
}

/* 2b. fold activities cost into auto price */
$c=str_replace('$price = $hotelSell + $vehicleCost + $margin;',
               '$price = $hotelSell + $vehicleCost + $margin + (isset($itemsCost)?$itemsCost:0);',$c,$n2);
echo "price-fold applied=$n2\n"; if($n2!==1)$errs[]='pricefold';

/* 2c. save itinerary+title after $saved=true */
$c=str_replace("            \$saved = true;",
  "            \$saved = true;\n            try { \$pdo->prepare('UPDATE tb_quotes SET itinerary=?, title=? WHERE trip_request_id=?')->execute([isset(\$itinJson)?\$itinJson:null, isset(\$pkgTitle)?\$pkgTitle:'', \$id]); } catch (Throwable \$e) {}",
  $c,$n3); echo "save applied=$n3\n"; if($n3!==1)$errs[]='save';

/* 2d. editor UI before Totals box */
$anchorTot="<div class=\"box\">\n  <h2>Totals</h2>";
if(strpos($c,'id="itin"')===false && strpos($c,$anchorTot)!==false){
  $editor='<div class="box">'."\n"
    .'  <h2>Day-by-day itinerary</h2>'."\n"
    .'  <label>Package title</label>'."\n"
    .'  <input type="text" name="pkg_title" id="pkg_title" value="<?= e((string) (is_array($q) ? ($q[\'title\'] ?? \'\') : \'\')) ?>" placeholder="e.g. Kerala Honeymoon — 5 Nights">'."\n"
    .'  <div id="itin" data-init="<?= base64_encode((string) (is_array($q) ? ($q[\'itinerary\'] ?? \'\') : \'\')) ?>" style="margin-top:12px"></div>'."\n"
    .'  <button type="button" class="btn ghost small" id="addDay">+ Add day</button>'."\n"
    .'  <p class="kv internal">Activities cost (internal): &#8377;<span id="itemsTot">0</span> &mdash; folded into customer price.</p>'."\n"
    .'  <input type="hidden" name="itinerary_json" id="itinerary_json">'."\n"
    .'</div>'."\n"
    .'<style>'."\n"
    .'.dayb{border:1px solid var(--rule);border-radius:8px;padding:12px 14px;margin:10px 0;background:#fff}'."\n"
    .'.dayb .dh{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}'."\n"
    .'.dayb .dh .dn{font-family:\'Cormorant Garamond\',serif;font-size:18px;color:var(--gold)}'."\n"
    .'.dayb input{width:100%;margin:4px 0}'."\n"
    .'.dayb .drow{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}'."\n"
    .'.dayb .itrow{display:flex;gap:8px;align-items:center;margin:3px 0}'."\n"
    .'.dayb .itrow .itn{flex:1}'."\n"
    .'@media(max-width:700px){.dayb .drow{grid-template-columns:1fr}}'."\n"
    .'</style>'."\n"
    .$anchorTot;
  $c=str_replace($anchorTot,$editor,$c,$n4); echo "editor applied=$n4\n"; if($n4!==1)$errs[]='editor';
}

/* 2e. grand-total includes activities */
$c=str_replace('Math.round(hsellt+vcost+M)','Math.round(hsellt+vcost+M+(window.__itemsCost||0))',$c,$n5);
echo "grandtotal applied=$n5\n"; if($n5!==1)$errs[]='grandtotal';

/* 2f. itinerary builder JS before admin_footer */
$anchorFoot='<?php admin_footer();';
if(strpos($c,'ITIN_BUILDER')===false && strpos($c,$anchorFoot)!==false){
  $js = <<<'JS'
<script>/*ITIN_BUILDER*/
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
    window.__itemsCost=t; var sp=document.getElementById('itemsTot'); if(sp)sp.textContent=Math.round(t);
    document.dispatchEvent(new Event('input'));
  }
  var init=[]; try{ var b=wrap.getAttribute('data-init')||''; if(b){ init=JSON.parse(atob(b))||[]; } }catch(e){ init=[]; }
  if(!init.length) init=[{}];
  init.forEach(function(d){ wrap.appendChild(dayBlock(d)); });
  document.getElementById('addDay').addEventListener('click',function(){ wrap.appendChild(dayBlock()); ser(); });
  wrap.addEventListener('input',ser); ser();
})();
</script>
JS;
  $c=str_replace($anchorFoot,$js."\n".$anchorFoot,$c,$n6); echo "js applied=$n6\n"; if($n6!==1)$errs[]='js';
}

if($c!==$c0){
  $t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$c);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ echo "quote.php lint FAIL:\n".implode("\n",$o)."\n"; $errs[]='lint'; }
  else{ copy($qf,$qf.'.bak.patch21'); file_put_contents($qf,$c); echo "quote.php written\n"; }
}else{ echo "quote.php no change\n"; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\n";
echo (empty($errs)?"DONE ok\n":"DONE issues: ".implode(',',$errs)."\n");
