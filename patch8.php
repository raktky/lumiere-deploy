<?php
/* patch8: hotel amenity chips in step3 (star-tier defaults). No DB dependency. Idempotent.
   Per-hotel admin override comes later once DB creds are wired into the deploy env. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $b=file_get_contents($tpl);
if(strpos($b,'amChips')!==false){ echo "build.php already patched (patch8)\n"; }
else{
$fail=[];
$jsFns="function suggestRooms(){return Math.max(1,Math.ceil((S.adults+S.kids)/2));}"
  ."\nfunction amDefault(sel){var s=sel.star,t=sel.type;if(t==='Houseboat')return['AC room','Sundeck','Meals included','Backwater cruise'];if(s>=5)return['Pool','Spa','Free WiFi','Restaurant','Bar','Room service'];if(s>=4)return['Pool','Free WiFi','Restaurant','Room service'];return['Free WiFi','Restaurant','Parking'];}"
  ."\nfunction amChips(sel){var a=(sel.am&&sel.am.trim())?sel.am.split(',').map(function(x){return x.trim();}).filter(Boolean):amDefault(sel);if(!a.length)return'';return '<div class=\"amrow\">'+a.slice(0,8).map(function(x){return '<span class=\"amchip\">'+h(x)+'</span>';}).join('')+'</div>';}";
$E=[
 ["function suggestRooms(){return Math.max(1,Math.ceil((S.adults+S.kids)/2));}", $jsFns],
 ["var sel=list.filter(function(x){return x.name===st.hotel;})[0];", "var sel=list.filter(function(x){return x.name===st.hotel;})[0];if(sel){html+=amChips(sel);}"],
 ["#lmb .hsel{margin-top:8px;background:#f2f7f3;border:1px solid #cfe3d6;border-radius:11px;padding:9px 11px;font-size:12.5px;color:#2f5d43}",
  "#lmb .hsel{margin-top:8px;background:#f2f7f3;border:1px solid #cfe3d6;border-radius:11px;padding:9px 11px;font-size:12.5px;color:#2f5d43}\n#lmb .amrow{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}\n#lmb .amchip{background:#eef3ee;border:1px solid #d7e5da;border-radius:999px;padding:5px 10px;font-size:11px;color:#3f5149;font-weight:500}"],
];
foreach($E as $i=>$p){ $c=0; $b=str_replace($p[0],$p[1],$b,$c); if($c!==1)$fail[]="b$i=$c"; }
if($fail){ echo 'FAIL: '.implode(', ',$fail)."\nNOT written\n"; }
else{
 $t=tempnam(sys_get_temp_dir(),'b');file_put_contents($t,$b);exec('php -l '.escapeshellarg($t).' 2>&1',$o2,$rc);unlink($t);
 if($rc!==0){ echo "syntax fail:\n".implode("\n",$o2)."\n"; }
 else{ copy($tpl,$tpl.'.bak.patch8'); file_put_contents($tpl,$b); echo "patch8 ok (".count($E)." edits)\n"; }
}
}
foreach(['_srcdump_kx7.txt','_admdump_kx7.txt'] as $f){ if(is_file("$root/$f")){ @unlink("$root/$f"); echo "removed $f\n"; } }
$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
