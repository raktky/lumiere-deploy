<?php
/* patch7b: round-trip km + honeymoon differentiation. Robust single-line anchors. Idempotent. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $s=file_get_contents($tpl);
if(strpos($s,'(round trip)')!==false){ echo "build.php already patched\n"; }
else{
$fail=[]; $E=[
 // round trip: route heading
 ['<div class="rt-h">Your route (rough)</div>','<div class="rt-h">Your route (round trip)</div>'],
 // round trip: total label in routeDiagram
 ['<span>Estimated total</span>','<span>Estimated total (round trip)</span>'],
 // round trip: add return leg into routeDiagram total (before storing)
 ['S._totalkm=total;','if(S.pickup&&S.drop){total+=km(S.drop,S.pickup);}S._totalkm=total;'],
 // round trip: step4 total
 ['var chain=[S.pickup].concat(S.days).concat([S.drop]).filter(Boolean),total=0;for(var i=0;i<chain.length-1;i++){total+=km(chain[i],chain[i+1]);}',
  'var chain=[S.pickup].concat(S.days).concat([S.drop]).filter(Boolean),total=0;for(var i=0;i<chain.length-1;i++){total+=km(chain[i],chain[i+1]);}if(S.pickup&&S.drop){total+=km(S.drop,S.pickup);}'],
 // round trip: step4 distance row label
 ["+row('Distance','&asymp; '+total+' km')","+row('Distance','&asymp; '+total+' km (round trip)')"],
 // round trip: buildNotes distance label
 ["L.push('Approx distance: '+(S._totalkm||0)+' km');","L.push('Approx distance (round trip): '+(S._totalkm||0)+' km');"],
 // honeymoon: banner in step3
 ["confirms live availability.</p>';",
  "confirms live availability.</p>'+(S.occasion==='Honeymoon'?'<div class=\"note\" style=\"border-left-color:#c98a8a;background:#fbf0f0\"><b>Honeymoon trip</b> &middot; we add flower-bed decor and a candle-light dinner where the hotel offers it. Suite / Premium rooms are pre-selected below.</div>':'')+'';"],
 // honeymoon: default room Premium/Suite in step3
 ["function step3(){syncDays();var html='<div class=\"tag\">Step 3</div><h2>Your stays</h2>",
  "function step3(){syncDays();if(S.occasion==='Honeymoon'){S.stays.forEach(function(s){if(!s.room)s.room='Premium/Suite';});}var html='<div class=\"tag\">Step 3</div><h2>Your stays</h2>"],
 // honeymoon: notes line
 ["L.push('Occasion: '+S.occasion);",
  "L.push('Occasion: '+S.occasion);if(S.occasion==='Honeymoon'){L.push('** HONEYMOON SPECIAL: arrange flower-bed decoration + candle-light dinner where available; suite/premium rooms preferred. **');}"],
];
foreach($E as $i=>$p){ $c=0; $s=str_replace($p[0],$p[1],$s,$c); if($c!==1)$fail[]="e$i=$c"; }
if($fail){ echo 'FAIL: '.implode(', ',$fail)."\nNOT written\n"; }
else{
 $t=tempnam(sys_get_temp_dir(),'b');file_put_contents($t,$s);exec('php -l '.escapeshellarg($t).' 2>&1',$o2,$rc);unlink($t);
 if($rc!==0){ echo "syntax fail:\n".implode("\n",$o2)."\n"; }
 else{ copy($tpl,$tpl.'.bak.patch7b'); file_put_contents($tpl,$s); echo "patch7b ok (".count($E)." edits)\n"; }
}
}
$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
