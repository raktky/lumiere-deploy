<?php
/* patch10: fix progress-circle class collision (done/on -> sdone/son). The .done class collides with the thank-you screen's #lmb .done padding rule, inflating circle 1. Idempotent. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php"; $b=file_get_contents($tpl);
if(strpos($b,'stp.sdone')!==false){ echo "already patched (patch10)\n"; }
else{
$fail=[]; $E=[
 ["var c=i<S.step?'done':i===S.step?'on':'';", "var c=i<S.step?'sdone':i===S.step?'son':'';"],
 ["#lmb .steps .stp.on{background:var(--green);color:#fff}#lmb .steps .stp.done{background:var(--gold);color:#3a2c05}",
  "#lmb .steps .stp{padding:0}#lmb .steps .stp.son{background:var(--green);color:#fff}#lmb .steps .stp.sdone{background:var(--gold);color:#3a2c05}"],
];
foreach($E as $i=>$p){ $c=0; $b=str_replace($p[0],$p[1],$b,$c); if($c!==1)$fail[]="e$i=$c"; }
if($fail){ echo 'FAIL: '.implode(', ',$fail)."\nNOT written\n"; }
else{
 $t=tempnam(sys_get_temp_dir(),'b');file_put_contents($t,$b);exec('php -l '.escapeshellarg($t).' 2>&1',$o2,$rc);unlink($t);
 if($rc!==0){ echo "syntax fail:\n".implode("\n",$o2)."\n"; }
 else{ copy($tpl,$tpl.'.bak.patch10'); file_put_contents($tpl,$b); echo "patch10 ok (".count($E)." edits)\n"; }
}
}
$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
