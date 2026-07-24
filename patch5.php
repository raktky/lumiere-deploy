<?php
/* Inject viewport meta into the builder page head (route serves build.php without site header). Idempotent. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $s=file_get_contents($tpl);
if(strpos($s,'lmb-viewport')!==false){ die("already has viewport inject\n"); }
$inject = '<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<script>/*lmb-viewport*/(function(){try{if(!document.querySelector(\'meta[name=viewport]\')){var m=document.createElement(\'meta\');m.name=\'viewport\';m.setAttribute(\'content\',\'width=device-width, initial-scale=1\');(document.head||document.documentElement).appendChild(m);}}catch(e){}})();</script>'."\n";
$n=0; $s2=str_replace('<div id="lmb"', $inject.'<div id="lmb"', $s, $n);
if($n!==1){ echo "anchor count=$n — not written\n"; exit(1); }
copy($tpl,$tpl.'.bak.viewport2'); file_put_contents($tpl,$s2);
echo "viewport inject added (n=$n)\n";
$cc=0; foreach(glob("$root/cache/*.html") as $f){ @unlink($f); $cc++; } echo "cache cleared: $cc\nDONE\n";
