<?php
$root='/var/www/lumiere/experience';
$c=@file_get_contents("$root/app/ai.php");
$o=['len'=>strlen($c)]; $lines=explode("\n",$c);
foreach($lines as $i=>$l){
  if(preg_match('/function ai_call|function ai_log|usage|input_token|output_token|tb_ai_log|AI_PACKAGE|AI_EXTRACT|json_decode|curl_exec|->content|function ai_generate|function ai_extract|credits|delta|balance/i',$l)){
    $o['hits'][]=($i+1).': '.rtrim(substr($l,0,150));
  }
}
file_put_contents("$root/_aicall.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo 'AICALL '.count($o['hits']??[]);
