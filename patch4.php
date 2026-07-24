<?php
/* Add viewport meta so phones render at device-width (fixes builder mobile view + site mobile SEO). Idempotent. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_dir("$c/templates")){ $root=$c; break; } }
if(!$root){ die("root not found\n"); }
$meta = '<meta name="viewport" content="width=device-width, initial-scale=1">';
$cands = array_merge(
  glob("$root/templates/partials/*.php") ?: [],
  glob("$root/templates/*.php") ?: [],
  glob("$root/app/*.php") ?: []
);
$patched=0; $skipped=0;
foreach($cands as $f){
  $s=file_get_contents($f);
  if(stripos($s,'</head>')===false) continue;               // only files with a <head>
  if(stripos($s,'name="viewport"')!==false){ echo "already has viewport: ".basename($f)."\n"; $skipped++; continue; }
  $n=0; $s2=preg_replace('/<\/head>/i', "  $meta\n</head>", $s, 1, $n);
  if($n===1){ copy($f,$f.'.bak.viewport'); file_put_contents($f,$s2); echo "added viewport -> ".$f."\n"; $patched++; }
}
echo "patched=$patched skipped=$skipped\n";
$cc=0; foreach(glob("$root/cache/*.html") as $ff){ @unlink($ff); $cc++; } echo "cache cleared: $cc\nDONE\n";
