<?php
/* patch13: fix quote.php rate queries — remove "OR period_from = ''" / "OR period_to = ''"
   which throw MySQL 1525 "Incorrect DATE value: ''" under strict mode (DATE column vs empty string).
   Idempotent; lints before writing; removes public dump _d5_kx7.txt. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
@unlink("$root/_d5_kx7.txt");

$f="$root/admin/quote.php";
if(!is_file($f)){ die("quote.php missing\n"); }
$q=file_get_contents($f);
$before=$q;
$q=str_replace("period_from IS NULL OR period_from = '' OR period_from <= ?","period_from IS NULL OR period_from <= ?",$q);
$q=str_replace("period_to   IS NULL OR period_to   = '' OR period_to   >= ?","period_to   IS NULL OR period_to   >= ?",$q);

if($q===$before){ echo "no change (already fixed or pattern not found)\n"; }
else{
  $t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$q);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ echo "lint FAIL:\n".implode("\n",$o)."\n"; }
  else{ copy($f,$f.'.bak.patch13'); file_put_contents($f,$q); echo "quote.php rate queries fixed\n"; }
}
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\nDONE\n";
