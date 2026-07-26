<?php
/* patch25: q.php day-by-day render — FIX missing endif in block. Cleanup _d9. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$f="$root/q.php";
$c=file_get_contents($f);
$applied='skip';
if(strpos($c,'day-by-day plan')!==false){ $applied='already'; }
else {
  $needle='<div class="total">';
  if(strpos($c,$needle)===false){ $applied='anchor-missing'; }
  else{
    $block = <<<'PHP'
<?php $__itin = json_decode((string) ($quote['itinerary'] ?? ''), true); if (is_array($__itin) && $__itin): ?>
  <div class="card">
    <h2 style="font-family:'Cormorant Garamond',serif;font-weight:600;font-size:22px;margin-bottom:10px">Your day-by-day plan</h2>
    <?php foreach ($__itin as $__d): ?>
    <div class="hotel">
      <div class="nm">Day <?= e((string) (int) ($__d['day'] ?? 0)) ?><?= !empty($__d['title']) ? ' — ' . e((string) $__d['title']) : '' ?></div>
      <?php $bits = []; if (!empty($__d['hotel'])) { $bits[] = 'Stay: ' . $__d['hotel']; } if (!empty($__d['meal'])) { $bits[] = 'Meals: ' . $__d['meal']; } if (!empty($__d['transport'])) { $bits[] = (string) $__d['transport']; } if ($bits): ?><div class="mt"><?= e(implode(' · ', $bits)) ?></div><?php endif; ?>
      <?php if (!empty($__d['items']) && is_array($__d['items'])): ?>
      <ul style="margin:6px 0 0 18px;padding:0;font-size:14px;color:#333">
        <?php foreach ($__d['items'] as $__it): if (!empty($__it['n'])): ?><li><?= e((string) $__it['n']) ?></li><?php endif; endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

PHP;
    $c2=str_replace($needle,$block.$needle,$c,$n);
    if($n>=1){
      $t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$c2);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
      if($rc!==0){ $applied='lint-fail:'.implode(' ',$o); }
      else{ copy($f,$f.'.bak.patch25'); file_put_contents($f,$c2); $applied='applied'; }
    } else { $applied='replace0'; }
  }
}
@unlink("$root/_d9_zt.txt");
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
echo "patch25 render: $applied\ncache: $cc\n_d9 removed: ".(is_file("$root/_d9_zt.txt")?'NO':'YES')."\nDONE\n";
