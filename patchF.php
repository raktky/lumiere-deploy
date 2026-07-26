<?php
/* patchF: send customer link via WhatsApp (wa.me) + Email (mailto) from admin quote screen. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$rep=[];
$qf="$root/admin/quote.php"; $qc=file_get_contents($qf); $qc0=$qc;
$anchor='<a href="quote-pdf.php?t=<?= e((string) $__q[\'token\']) ?>" target="_blank">PDF</a>';
if(strpos($qc,'wa.me/')===false && strpos($qc,$anchor)!==false){
  $send=$anchor
   .' &middot; <?php $__ph = preg_replace(\'/\\D/\', \'\', (string) ($tr[\'phone\'] ?? $tr[\'mobile\'] ?? \'\')); if ($__ph !== \'\' && strlen($__ph) === 10) { $__ph = \'91\' . $__ph; } $__msg = rawurlencode(\'Namaste \' . (string) ($tr[\'name\'] ?? \'\') . \', your Kerala itinerary from Lumiere Holidays: \' . $__link); ?>'
   .'<a href="https://wa.me/<?= e($__ph) ?>?text=<?= $__msg ?>" target="_blank">WhatsApp</a>'
   .' &middot; <a href="mailto:<?= e((string) ($tr[\'email\'] ?? \'\')) ?>?subject=<?= rawurlencode(\'Your Kerala quote — Lumiere Holidays\') ?>&body=<?= $__msg ?>">Email</a>';
  $qc=str_replace($anchor,$send,$qc,$n); $rep[]='send='.$n;
  if($n===1){
    $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$qc);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
    if($rc!==0){ $rep[]='lintFAIL'; }
    else{ copy($qf,$qf.'.bak.patchF'); file_put_contents($qf,$qc); $rep[]='written'; }
  } else { $rep[]='anchorfail'; }
} else { $rep[]='send=skip'; }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_pf.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
