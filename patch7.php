<?php
/* patch7: round-trip km + honeymoon differentiation. Also cleans up source dump files. Idempotent. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $s=file_get_contents($tpl);
if(strpos($s,'(round trip)')!==false){ echo "build.php already patched (patch7)\n"; }
else{
$fail=[];

/* 1: round trip total in routeDiagram */
$old=<<<'OO0'
S._totalkm=total;
return '<div class="route"><div class="rt-h">Your route (rough)</div>'+legs+'<div class="total"><span>Estimated total</span><b>&asymp; '+total+' km</b></div></div>';}
OO0;
$new=<<<'NN0'
var _ret=0;if(S.pickup&&S.drop){_ret=km(S.drop,S.pickup);total+=_ret;legs+='<div class="leg"><div class="node"><span class="d"></span></div><div class="txt"><div class="p">Return to '+h((S.pickup||'').split(' (')[0])+'</div><div class="km">&uarr; ~'+_ret+' km</div></div></div>';}
S._totalkm=total;
return '<div class="route"><div class="rt-h">Your route (round trip)</div>'+legs+'<div class="total"><span>Estimated total (round trip)</span><b>&asymp; '+total+' km</b></div></div>';}
NN0;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p0='.$c;

/* 2: round trip total in step4 */
$old=<<<'OO1'
var chain=[S.pickup].concat(S.days).concat([S.drop]).filter(Boolean),total=0;for(var i=0;i<chain.length-1;i++){total+=km(chain[i],chain[i+1]);}
OO1;
$new=<<<'NN1'
var chain=[S.pickup].concat(S.days).concat([S.drop]).filter(Boolean),total=0;for(var i=0;i<chain.length-1;i++){total+=km(chain[i],chain[i+1]);}if(S.pickup&&S.drop){total+=km(S.drop,S.pickup);}
NN1;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p1='.$c;

/* 3: distance row label */
$old="+row('Distance','&asymp; '+total+' km')";
$new="+row('Distance','&asymp; '+total+' km (round trip)')";
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p2='.$c;

/* 4: buildNotes distance label */
$old="L.push('Approx distance: '+(S._totalkm||0)+' km');";
$new="L.push('Approx distance (round trip): '+(S._totalkm||0)+' km');";
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p3='.$c;

/* 5: honeymoon banner in step3 */
$old="confirms live availability.</p>';";
$new="confirms live availability.</p>'+(S.occasion==='Honeymoon'?'<div class=\"note\" style=\"border-left-color:#c98a8a;background:#fbf0f0\"><b>Honeymoon trip</b> &middot; we add flower-bed decor and a candle-light dinner where the hotel offers it. Suite / Premium rooms are pre-selected below.</div>':'')+'';";
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p4='.$c;

/* 6: honeymoon default room in step3 */
$old="function step3(){syncDays();var html='<div class=\"tag\">Step 3</div><h2>Your stays</h2>";
$new="function step3(){syncDays();if(S.occasion==='Honeymoon'){S.stays.forEach(function(s){if(!s.room)s.room='Premium/Suite';});}var html='<div class=\"tag\">Step 3</div><h2>Your stays</h2>";
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p5='.$c;

/* 7: buildNotes honeymoon line */
$old="L.push('Occasion: '+S.occasion);";
$new="L.push('Occasion: '+S.occasion);if(S.occasion==='Honeymoon'){L.push('** HONEYMOON SPECIAL: arrange flower-bed decoration + candle-light dinner where available; suite/premium rooms preferred. **');}";
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p6='.$c;

if($fail){ echo 'FAIL: '.implode(', ',$fail)."\nNOT written\n"; }
else{
$t=tempnam(sys_get_temp_dir(),'b');file_put_contents($t,$s);exec('php -l '.escapeshellarg($t).' 2>&1',$o2,$rc);unlink($t);
if($rc!==0){ echo "syntax fail:\n".implode("\n",$o2)."\n"; }
else{ copy($tpl,$tpl.'.bak.patch7'); file_put_contents($tpl,$s); echo "patch7 build.php ok (7 edits)\n"; }
}
}
/* cleanup public source dumps + cache */
foreach(['_srcdump_kx7.txt','_admdump_kx7.txt'] as $f){ if(is_file("$root/$f")){ @unlink("$root/$f"); echo "removed $f\n"; } }
$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
