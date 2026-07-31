<?php
$root='/var/www/lumiere/experience';
$f="$root/admin/ai-package.php";
$s=file_get_contents($f);
$o=['already'=>strpos($s,'lm_share')!==false];
$anchor='Refine in Quote Builder</a>';
$o['anchor']=substr_count($s,$anchor);
$blk = <<<'ADD'
Refine in Quote Builder</a>
<div style="margin-top:10px;text-align:right"><button type="button" id="lm_share" style="background:#e7c24b;color:#12241c;border:none;font-weight:700;padding:9px 15px;border-radius:9px;cursor:pointer;font-size:13px">Create customer link &rarr;</button><div id="lm_shareout" style="margin-top:8px;font-size:12px;color:#dfe9e0;word-break:break-all"></div></div>
<script>
document.getElementById('lm_share').addEventListener('click',function(){
  var b=this;b.disabled=true;b.textContent='Creating...';
  var g=function(s){var e=document.querySelector(s);return e?(e.value!==undefined?e.value:e.innerText):'';};
  var name=g('input[name=name]');
  var title=((document.querySelector('.card .hd')||{}).innerText||'Your Kerala Journey').replace(/DRAFT/i,'').replace(/^[\s0-9]+/,'').trim();
  var dest=g('input[name=destinations]');var nights=g('input[name=nights]');
  var days=[].map.call(document.querySelectorAll('.day'),function(d){return{place:((d.querySelector('.dh')||{}).innerText||'').replace(/^[\s0-9]+/,'').trim(),meta:((d.querySelector('.meta')||{}).innerText||'').trim(),items:[].map.call(d.querySelectorAll('.it'),function(i){return i.innerText.trim();})};});
  var total=(document.getElementById('lm_grand')||{}).textContent||'';
  var pp=(document.getElementById('lm_pp')||{}).textContent||'';
  var content=JSON.stringify({hero:(dest.split(',')[0]||'Kerala').trim(),sub:dest+(nights?(' · '+nights+' nights'):''),total:total,perperson:pp,days:days});
  var fd=new FormData();fd.append('create','1');fd.append('content',content);fd.append('customer',name);fd.append('title',title);
  fetch('/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    b.disabled=false;b.textContent='Create customer link →';
    var out=document.getElementById('lm_shareout');
    if(j&&j.ok){out.innerHTML='Version '+j.version+' &middot; <a href="'+j.url+'" target="_blank" style="color:#e7c24b">'+j.url+'</a> <button type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(\''+j.url+'\')" style="background:#1a3a2c;color:#fff;border:1px solid #2a4a3a;border-radius:6px;padding:2px 8px;cursor:pointer;font-size:11px">copy</button>';}
    else{out.textContent='Error: '+((j&&j.error)||'failed');}
  }).catch(function(){b.disabled=false;b.textContent='Create customer link →';document.getElementById('lm_shareout').textContent='Error';});
});
</script>
ADD;
if(!$o['already'] && $o['anchor']===1){
  $s2=str_replace($anchor,$blk,$s);
  copy($f,"$f.baksb");
  file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.baksb",$f);$o['restored']=true;}else{$o['done']=true;}
}
file_put_contents("$root/_sharebtn.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'SHAREBTN '.json_encode($o);
