<?php
/* patch22: public q.php — render day-by-day itinerary (customer-facing, names only, no cost). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$f="$root/q.php";
if(!is_file($f)){ die("q.php missing\n"); }
$c=file_get_contents($f);
$c0=$c;
$anchor='  <div class="total"><span class="lbl">Total package price</span>';
if(strpos($c,'day-by-day plan')===false && strpos($c,$anchor)!==false){
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

PHP;
  $c=str_replace($anchor,$block.$anchor,$c,$n); echo "itinerary render applied=$n\n";
  if($n===1){
    $t=tempnam(sys_get_temp_dir(),'q');file_put_contents($t,$c);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
    if($rc!==0){ echo "lint FAIL:\n".implode("\n",$o)."\n"; }
    else{ copy($f,$f.'.bak.patch22'); file_put_contents($f,$c); echo "q.php written\n"; }
  }else{ echo "anchor fail\n"; }
}else{ echo "already patched or anchor missing\n"; }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\nDONE\n";
