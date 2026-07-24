<?php
/* Phase B: builder UI - vehicle category->model dropdown + Houseboat option. Idempotent-ish. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $s=file_get_contents($tpl);
if(strpos($s,'i_vehmodel')!==false){ die("already patched (phase B)\n"); }
$fail=[];
$old=<<<'EOO0'
SELECT location,name,star,room_categories,price_range FROM tb_build_hotels
EOO0;
$new=<<<'EON0'
SELECT location,name,star,room_categories,price_range,type FROM tb_build_hotels
EON0;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p0='.$n;
$old=<<<'EOO1'
$HOTELS[$loc][] = ['name'=>$r['name'],'star'=>(int)$r['star'],'room'=>$room,'price'=>trim((string)$r['price_range'])];
EOO1;
$new=<<<'EON1'
$HOTELS[$loc][] = ['name'=>$r['name'],'star'=>(int)$r['star'],'room'=>$room,'price'=>trim((string)$r['price_range']),'type'=>(isset($r['type'])?$r['type']:'Hotel')];
EON1;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p1='.$n;
$old=<<<'EOO2'
$LOCS = array_keys($HOTELS);
EOO2;
$new=<<<'EON2'
$LOCS = array_keys($HOTELS);
$__veh=[]; try{ $__veh=rows("SELECT category,model FROM tb_vehicles WHERE active=1 ORDER BY category ASC, sort ASC, model ASC"); }catch(Throwable $e){}
$VEHICLES=[]; foreach($__veh as $v){ $VEHICLES[$v['category']][]=$v['model']; }
EON2;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p2='.$n;
$old=<<<'EOO3'
var HOTELS = <?= json_encode($HOTELS, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
EOO3;
$new=<<<'EON3'
var HOTELS = <?= json_encode($HOTELS, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
var VEHICLES = <?= json_encode($VEHICLES, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
EON3;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p3='.$n;
$old=<<<'EOO4'
rooms:1,vehicle:'Sedan',nights:2
EOO4;
$new=<<<'EON4'
rooms:1,vehicle:'Sedan',vehModel:'',nights:2
EON4;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p4='.$n;
$old=<<<'EOO5'
+'<div><label class="f" style="margin-top:0">Vehicle</label><div class="chips3">'+['Sedan','SUV','Tempo'].map(function(v){return '<div class="pill '+(S.vehicle===v?'sel':'')+'" data-veh="'+v+'">'+v+'</div>';}).join('')+'</div></div></div>';
EOO5;
$new=<<<'EON5'
+'<div><label class="f" style="margin-top:0">Vehicle</label><div class="chips3">'+['Sedan','SUV','Large Vehicle'].map(function(v){return '<div class="pill '+(S.vehicle===v?'sel':'')+'" data-veh="'+v+'">'+v+'</div>';}).join('')+'</div></div></div>'+'<label class="f">Vehicle model <span class="opt">(optional)</span></label><select class="inp" id="i_vehmodel"><option value="">Any '+S.vehicle+'</option>'+((VEHICLES[S.vehicle]||[]).map(function(m){return '<option'+(S.vehModel===m?' selected':'')+'>'+m+'</option>';}).join(''))+'</select>';
EON5;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p5='.$n;
$old=<<<'EOO6'
el.querySelectorAll('[data-veh]').forEach(function(n){n.onclick=function(){S.vehicle=n.dataset.veh;step1();};});
EOO6;
$new=<<<'EON6'
el.querySelectorAll('[data-veh]').forEach(function(n){n.onclick=function(){S.vehicle=n.dataset.veh;S.vehModel='';step1();};});var vm=document.getElementById('i_vehmodel');if(vm)vm.onchange=function(){S.vehModel=this.value;};
EON6;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p6='.$n;
$old=<<<'EOO7'
var loc=d||('Night '+(i+1)),st=S.stays[i],star=CATSTAR[st.cat],pool=(HOTELS[d]||[]),list=pool.filter(function(x){return x.star===star;}),fb=false;
EOO7;
$new=<<<'EON7'
var loc=d||('Night '+(i+1)),st=S.stays[i],star=CATSTAR[st.cat],pool=(HOTELS[d]||[]),hasHB=pool.some(function(x){return x.type==='Houseboat';}),isHB=st.cat==='Houseboat',list=pool.filter(function(x){return isHB?x.type==='Houseboat':(x.star===star&&x.type!=='Houseboat');}),fb=false;
EON7;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p7='.$n;
$old=<<<'EOO8'
+'<div class="catrow">'+['Comfort','Premium','Luxury'].map(function(c){return '<div class="pill '+(st.cat===c?'sel':'')+'" data-cat="'+i+'|'+c+'">'+c+'</div>';}).join('')+'</div>';
EOO8;
$new=<<<'EON8'
+'<div class="catrow">'+['Comfort','Premium','Luxury'].concat(hasHB?['Houseboat']:[]).map(function(c){return '<div class="pill '+(st.cat===c?'sel':'')+'" data-cat="'+i+'|'+c+'">'+c+'</div>';}).join('')+'</div>';
EON8;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p8='.$n;
$old=<<<'EOO9'
L.push('Rooms: '+S.rooms+'  |  Vehicle: '+S.vehicle);
EOO9;
$new=<<<'EON9'
L.push('Rooms: '+S.rooms+'  |  Vehicle: '+S.vehicle+(S.vehModel?' ('+S.vehModel+')':''));
EON9;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p9='.$n;
$old=<<<'EOO10'
+row('Vehicle',h(S.vehicle))
EOO10;
$new=<<<'EON10'
+row('Vehicle',h(S.vehicle)+(S.vehModel?' &middot; '+h(S.vehModel):''))
EON10;
$n=0;$s=str_replace($old,$new,$s,$n);if($n!==1)$fail[]='p10='.$n;
if($fail){ echo 'FAIL anchors: '.implode(', ',$fail)."\n"; echo "NOT written.\n"; exit(1); }
$t=tempnam(sys_get_temp_dir(),'b');file_put_contents($t,$s);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
if($rc!==0){ echo "PHP syntax check failed:\n".implode("\n",$o)."\n"; exit(1); }
copy($tpl,$tpl.'.bak.phaseB'); file_put_contents($tpl,$s);
echo "build.php patched (11 edits ok)\n";
$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
