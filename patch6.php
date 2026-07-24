<?php
/* patch6: 3/4/5 Star rename, houseboat-fallback fix, nav links, progress fraction. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $s=file_get_contents($tpl);
if(strpos($s,"'3 Star'")!==false){ die("already patched (patch6)\n"); }
$fail=[];
$old=<<<'OO0'
if(!list.length&&pool.length){fb=true;list=pool.slice().sort(function(a,b){return Math.abs(a.star-star)-Math.abs(b.star-star);}).slice(0,4);}
OO0;
$new=<<<'NN0'
if(!list.length&&pool.length&&!isHB){fb=true;list=pool.filter(function(x){return x.type!=='Houseboat';}).sort(function(a,b){return Math.abs(a.star-star)-Math.abs(b.star-star);}).slice(0,4);}
NN0;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p0='.$c;
$old=<<<'OO1'
var CATSTAR={Comfort:3,Premium:4,Luxury:5};
OO1;
$new=<<<'NN1'
var CATSTAR={'3 Star':3,'4 Star':4,'5 Star':5};
NN1;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p1='.$c;
$old=<<<'OO2'
S.stays.push({cat:'Comfort',hotel:'',room:'',meal:'CP'})
OO2;
$new=<<<'NN2'
S.stays.push({cat:'3 Star',hotel:'',room:'',meal:'CP'})
NN2;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p2='.$c;
$old=<<<'OO3'
['Comfort','Premium','Luxury'].concat(hasHB?['Houseboat']:[])
OO3;
$new=<<<'NN3'
['3 Star','4 Star','5 Star'].concat(hasHB?['Houseboat']:[])
NN3;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p3='.$c;
$old=<<<'OO4'
stepName.textContent=STEPLABELS[S.step]||'';
OO4;
$new=<<<'NN4'
stepName.textContent=(S.step+1)+' / '+STEPLABELS.length+(STEPLABELS[S.step]?'  '+String.fromCharCode(183)+'  '+STEPLABELS[S.step]:'');
NN4;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p4='.$c;
$old=<<<'OO5'
<div class="topbar"><span class="brand"><b></b>Lumiere Holidays</span><span style="font-size:12px;opacity:.85" id="lmbStepName"></span></div>
OO5;
$new=<<<'NN5'
<div class="topbar"><a href="/" class="brand" style="color:#fff;text-decoration:none"><b></b>Lumiere Holidays</a><span style="display:flex;gap:14px;align-items:center"><span style="font-size:11.5px;opacity:.85" id="lmbStepName"></span><a href="/packages" style="color:#e9c46a;font-size:12px;font-weight:600;text-decoration:none">Menu</a></span></div>
NN5;
$c=0;$s=str_replace($old,$new,$s,$c);if($c!==1)$fail[]='p5='.$c;
if($fail){ echo 'FAIL: '.implode(', ',$fail)."\nNOT written\n"; exit(1); }
$t=tempnam(sys_get_temp_dir(),'b');file_put_contents($t,$s);exec('php -l '.escapeshellarg($t).' 2>&1',$o2,$rc);unlink($t);
if($rc!==0){ echo "syntax fail:\n".implode("\n",$o2)."\n"; exit(1); }
copy($tpl,$tpl.'.bak.patch6'); file_put_contents($tpl,$s);
echo "patch6 ok (6 edits)\n";
$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
