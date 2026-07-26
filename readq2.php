<?php
/* readq2: SAFE slices only (no csrf/token values). For building save_template.php. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$o='';
/* header convention of an existing admin standalone page */
foreach(['admin/quotes.php','admin/reports.php'] as $rel){
  $c=@file_get_contents("$root/$rel");
  $o.="=== $rel first 16 lines ===\n";
  $ls=explode("\n",(string)$c);
  for($i=0;$i<16 && $i<count($ls);$i++){
    $ln=$ls[$i];
    if(preg_match('/csrf|token|password|secret/i',$ln)){ $o.=($i+1).": [redacted line]\n"; continue; }
    $o.=($i+1).": ".$ln."\n";
  }
  $o.="\n";
}
/* quote.php: does exact patch21 data-init string exist? */
$q=@file_get_contents("$root/admin/quote.php");
$needleInit='data-init="<?= base64_encode((string) (is_array($q) ? ($q[\'itinerary\'] ?? \'\') : \'\')) ?>"';
$o.="quote.php has exact data-init needle: ".(strpos($q,$needleInit)!==false?'YES':'NO')."\n";
$o.="quote.php has editor box head: ".(strpos($q,'<h2>Day-by-day itinerary</h2>')!==false?'YES':'NO')."\n";
$o.="quote.php has '<div id=\"itin\"': ".(strpos($q,'<div id="itin"')!==false?'YES':'NO')."\n";
$o.="quote.php uses \$pdo->: ".(strpos($q,'$pdo->')!==false?'YES':'NO')."\n";
$o.="quote.php has 'require_admin': ".(strpos($q,'require_admin')!==false?'YES':'NO')."\n";
$o.="quote.php has 'csrf_field': ".(strpos($q,'csrf_field')!==false?'YES':'NO')."\n";
/* helper availability */
require_once "$root/app/config.php"; require_once "$root/app/db.php";
@require_once "$root/app/bootstrap.php";
$o.="fn require_admin: ".(function_exists('require_admin')?'Y':'N').", admin_user: ".(function_exists('admin_user')?'Y':'N').", csrf_check: ".(function_exists('csrf_check')?'Y':'N').", e: ".(function_exists('e')?'Y':'N').", url: ".(function_exists('url')?'Y':'N')."\n";
@unlink("$root/_dq_ab.txt");
file_put_contents("$root/_dq2.txt",$o);
echo "done\nDONE\n";
