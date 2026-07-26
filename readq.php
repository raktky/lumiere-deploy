<?php
/* readq: dump quote.php structure slices for safe patching. Temp file, deleted by next patch. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$f="$root/admin/quote.php";
$c=file_get_contents($f);
$o='';
$o.="=== TOP 55 lines ===\n";
$lines=explode("\n",$c);
for($i=0;$i<55 && $i<count($lines);$i++){ $o.=($i+1).": ".$lines[$i]."\n"; }
function region($c,$needle,$before,$after,$tag){
  $p=strpos($c,$needle);
  if($p===false) return "\n=== $tag: NOT FOUND ($needle) ===\n";
  return "\n=== $tag (offset $p) ===\n".substr($c,max(0,$p-$before),$before+$after)."\n";
}
$o.=region($c,'data-init','120','200','data-init line');
$o.=region($c,'$saved = true;','40','420','saved region');
$o.='<h2>Day-by-day itinerary</h2>'."\n";
$o.=region($c,'Day-by-day itinerary','120','260','editor box head');
$o.=region($c,"REQUEST_METHOD",'20','260','post handler start');
file_put_contents("$root/_dq_ab.txt",$o);
echo "written ".strlen($o)."\nDONE\n";
