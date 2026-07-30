<?php
$root='/var/www/lumiere/experience';
$f="$root/app/ai.php";
$s=file_get_contents($f);
$out=['changes'=>0];

$k_old = "    \$c = ai_cfg();\n    return trim((string)(\$c['api_key'] ?? ''));";
$k_new = "    \$c = ai_cfg();\n    \$pk = \$prov === 'anthropic' ? (\$c['key_anthropic'] ?? '') : (\$c['key_gemini'] ?? '');\n    if (trim((string)\$pk) !== '') return trim((string)\$pk);\n    return trim((string)(\$c['api_key'] ?? ''));";
if(strpos($s,$k_old)!==false){ $s=str_replace($k_old,$k_new,$s); $out['changes']++; $out['key']=1; } else $out['key']=0;

$m_old = "    \$m = trim((string)(\$c['model'] ?? ''));\n    if (\$m !== '') return \$m;";
$m_new = "    \$prov = ai_provider();\n    \$pcol = \$prov === 'anthropic' ? (\$c['model_anthropic'] ?? '') : (\$c['model_gemini'] ?? '');\n    if (trim((string)\$pcol) !== '') return trim((string)\$pcol);\n    \$m = trim((string)(\$c['model'] ?? ''));\n    if (\$m !== '') return \$m;";
if(strpos($s,$m_old)!==false){ $s=str_replace($m_old,$m_new,$s); $out['changes']++; $out['model']=1; } else $out['model']=0;

$s=str_replace("'claude-3-5-sonnet-20241022' : 'gemini-2.5-flash'","'claude-3-5-sonnet-20241022' : 'gemini-2.0-flash'",$s);

$bak="$f.bak4"; @copy($f,$bak);
file_put_contents($f,$s);
$o=[];$rc=0; exec('php -l '.escapeshellarg($f).' 2>&1',$o,$rc);
if($rc!==0){ @copy($bak,$f); }
$out['rc']=$rc;$out['lint']=$o;
file_put_contents("$root/_dep_ai2.txt",json_encode($out));
echo 'AI2';
