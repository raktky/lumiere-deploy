<?php
$root='/var/www/lumiere/experience';
$f="$root/app/ai.php";
$s=file_get_contents($f);
$old='$j = json_decode((string)$r[\'res\'], true);';
$snip=$old.' if(isset($j["usage"])||isset($j["usageMetadata"])){ $ti=$j["usage"]["input_tokens"]??$j["usageMetadata"]["promptTokenCount"]??0; $to=$j["usage"]["output_tokens"]??$j["usageMetadata"]["candidatesTokenCount"]??0; try{db()->prepare("INSERT INTO tb_ai_log (op,delta,balance,meta,created_at) VALUES (\'TOKENS\',0,0,?,NOW())")->execute(["in=".$ti." out=".$to." tot=".($ti+$to)]);}catch(\\Throwable $e){} }';
$cnt=0; $s2=str_replace($old,$snip,$s,$cnt);
$o=['found'=>$cnt];
if($cnt>0){
  copy($f,"$f.baktok");
  file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.baktok",$f);$o['restored']=true;} else {$o['done']=true;}
}
file_put_contents("$root/_toklog.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'TOKLOG '.json_encode($o);
