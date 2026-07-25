<?php
/* patch9: dd/mm/yyyy dates + per-night date label; houseboat locks room=Houseboat & meal=All meals; numbered-circle progress bar. Idempotent. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $b=file_get_contents($tpl);
if(strpos($b,'class="stp ')!==false){ echo "already patched (patch9)\n"; }
else{
$fail=[]; $E=[
 // 1: dd/mm/yyyy dates
 ["function dateFor(i){if(!S.date)return'';var d=new Date(S.date);if(isNaN(d))return'';d.setDate(d.getDate()+i);return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short'});}",
  "function dateFor(i){if(!S.date)return'';var d=new Date(S.date);if(isNaN(d))return'';d.setDate(d.getDate()+i);return ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear();}"],
 // 2: per-night date on Night label
 ["<span class=\"day\">Night '+(i+1)+'</span>",
  "<span class=\"day\">Night '+(i+1)+(S.date?' &middot; '+dateFor(i):'')+'</span>"],
 // 3: houseboat force room/meal state
 ["fb=false;", "fb=false;if(isHB){st.room='Houseboat';st.meal='AP';}"],
 // 4: room select houseboat-only
 ["<select class=\"inp\" data-room=\"'+i+'\"><option value=\"\">Room type&hellip;</option><option'+(st.room==='Standard'?' selected':'')+'>Standard</option><option'+(st.room==='Deluxe'?' selected':'')+'>Deluxe</option><option'+(st.room==='Premium/Suite'?' selected':'')+'>Premium/Suite</option></select>",
  "<select class=\"inp\" data-room=\"'+i+'\">'+(isHB?'<option value=\"Houseboat\" selected>Houseboat</option>':'<option value=\"\">Room type&hellip;</option><option'+(st.room==='Standard'?' selected':'')+'>Standard</option><option'+(st.room==='Deluxe'?' selected':'')+'>Deluxe</option><option'+(st.room==='Premium/Suite'?' selected':'')+'>Premium/Suite</option>')+'</select>"],
 // 5: meal select houseboat all-meals-only
 ["<select class=\"inp\" data-meal=\"'+i+'\"><option value=\"CP\"'+(st.meal==='CP'?' selected':'')+'>Breakfast (CP)</option><option value=\"MAP\"'+(st.meal==='MAP'?' selected':'')+'>B+Dinner (MAP)</option><option value=\"AP\"'+(st.meal==='AP'?' selected':'')+'>All meals (AP)</option></select>",
  "<select class=\"inp\" data-meal=\"'+i+'\">'+(isHB?'<option value=\"AP\" selected>All meals (AP)</option>':'<option value=\"CP\"'+(st.meal==='CP'?' selected':'')+'>Breakfast (CP)</option><option value=\"MAP\"'+(st.meal==='MAP'?' selected':'')+'>B+Dinner (MAP)</option><option value=\"AP\"'+(st.meal==='AP'?' selected':'')+'>All meals (AP)</option>')+'</select>"],
 // 6: numbered-circle progress render
 ["dots.innerHTML=STEPLABELS.map(function(_,i){return '<i class=\"'+(i<S.step?'done':i===S.step?'on':'')+'\"></i>';}).join('');",
  "dots.innerHTML=STEPLABELS.map(function(_,i){var c=i<S.step?'done':i===S.step?'on':'';return '<span class=\"stp '+c+'\">'+(i<S.step?'&#10003;':(i+1))+'</span>'+(i<STEPLABELS.length-1?'<span class=\"seg'+(i<S.step?' fill':'')+'\"></span>':'');}).join('');"],
 // 7: steps container css
 ["#lmb .steps{display:flex;gap:5px;padding:12px 16px 4px;flex:0 0 auto}",
  "#lmb .steps{display:flex;align-items:center;gap:0;padding:14px 16px 8px;flex:0 0 auto}"],
 // 8: circle + segment css
 ["#lmb .steps i{height:5px;flex:1;border-radius:4px;background:var(--line)}",
  "#lmb .steps .stp{flex:0 0 auto;width:26px;height:26px;border-radius:50%;background:#e7e1d2;color:#8a968d;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}#lmb .steps .seg{flex:1;height:2px;background:#e7e1d2;margin:0 2px}"],
 // 9: state colors
 ["#lmb .steps i.on{background:var(--green)} #lmb .steps i.done{background:var(--gold)}",
  "#lmb .steps .stp.on{background:var(--green);color:#fff}#lmb .steps .stp.done{background:var(--gold);color:#3a2c05}#lmb .steps .seg.fill{background:var(--gold)}"],
];
foreach($E as $i=>$p){ $c=0; $b=str_replace($p[0],$p[1],$b,$c); if($c!==1)$fail[]="e$i=$c"; }
if($fail){ echo 'FAIL: '.implode(', ',$fail)."\nNOT written\n"; }
else{
 $t=tempnam(sys_get_temp_dir(),'b');file_put_contents($t,$b);exec('php -l '.escapeshellarg($t).' 2>&1',$o2,$rc);unlink($t);
 if($rc!==0){ echo "syntax fail:\n".implode("\n",$o2)."\n"; }
 else{ copy($tpl,$tpl.'.bak.patch9'); file_put_contents($tpl,$b); echo "patch9 ok (".count($E)." edits)\n"; }
}
}
$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
