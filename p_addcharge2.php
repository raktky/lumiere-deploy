<?php
$root='/var/www/lumiere/experience';
$f="$root/admin/ai-package.php";
$s=file_get_contents($f);
$o=['already'=>strpos($s,'lm_add')!==false];
$anchor='<div style="text-align:right;margin-top:10px;font-size:13px">Extra on total:';
$o['anchor']=substr_count($s,$anchor);
$btn = <<<'ADD'
<div style="margin-top:10px"><button type="button" id="lm_add" style="background:#eef6f0;border:1px solid #bfe0cb;color:#0c6b3e;border-radius:8px;padding:7px 12px;font-size:13px;cursor:pointer;font-weight:600">+ Add charge (driver bata / toll / parking / entry)</button></div>
<script>
document.getElementById('lm_add').addEventListener('click',function(){
  var tb=document.getElementById('lm_rows');
  var tr=document.createElement('tr');
  tr.innerHTML='<td style="padding:6px 4px"><input class="lm_label" placeholder="e.g. Driver bata / Toll / Parking" style="width:180px;padding:5px 7px;border:1px solid #e2e8e3;border-radius:7px"></td><td style="text-align:right"><input class="lm_cost" type="number" value="0" style="width:92px;text-align:right;padding:5px 7px;border:1px solid #e2e8e3;border-radius:7px"></td><td style="text-align:right"><select class="lm_type" style="padding:5px;border:1px solid #e2e8e3;border-radius:7px"><option value="flat">&#8377;</option><option value="pct">%</option></select> <input class="lm_val" type="number" value="0" style="width:62px;text-align:right;padding:5px 7px;border:1px solid #e2e8e3;border-radius:7px"></td><td style="text-align:right;font-weight:700;color:#0c6b3e" class="lm_sell">&mdash;</td>';
  tb.appendChild(tr); tr.querySelector('.lm_cost').focus();
});
</script>
ADD;
if(!$o['already'] && $o['anchor']===1){
  $s2=str_replace($anchor,$btn."\n  ".$anchor,$s);
  copy($f,"$f.bakadd2");
  file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.bakadd2",$f);$o['restored']=true;} else {$o['done']=true;}
}
file_put_contents("$root/_addcharge2.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'ADDCHARGE2 '.json_encode($o);
