<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$rep=[];
$src=@file_get_contents("$root/admin/quotes.php"); $prologue="";
foreach(explode("\n",(string)$src) as $ln){ $t2=trim($ln); $ok=($t2==="")||strpos($t2,"<?php")===0||strpos($t2,"declare")===0||strpos($t2,"require")===0||strpos($t2,"use ")===0||strpos($t2,"namespace")===0||strpos($t2,"ini_set")===0||strpos($t2,"error_reporting")===0||strpos($t2,"require_admin")!==false||strpos($t2,"admin_user")!==false||strpos($t2,"//")===0||strpos($t2,"/*")===0||strpos($t2,"*")===0||preg_match('/^\$pdo\s*=/',$t2)||strpos($t2,"rbac")!==false; if(!$ok) break; $prologue.=$ln."\n"; }
if(strpos($prologue,"require_admin")===false){ $rep[]="prologue=BAD"; } else {
  if(strpos($prologue,"\$pdo")===false){ $prologue.="\$pdo = db();\n"; }
  $body = '
$saved=false;
if (($_SERVER[\'REQUEST_METHOD\'] ?? \'\') === \'POST\') {
  if (function_exists(\'csrf_check\')) { csrf_check(); }
  try { $pdo->prepare(\'UPDATE tb_track SET ga4_id=?, ads_conv_id=?, ads_conv_label=?, wa_number=?, updated_at=NOW() WHERE id=1\')
      ->execute([trim((string)($_POST[\'ga4_id\'] ?? \'\')), trim((string)($_POST[\'ads_conv_id\'] ?? \'\')), trim((string)($_POST[\'ads_conv_label\'] ?? \'\')), trim((string)($_POST[\'wa_number\'] ?? \'\'))]); $saved=true; } catch (Throwable $e) {}
}
$t=null; try{ $t=$pdo->query(\'SELECT * FROM tb_track WHERE id=1\')->fetch(PDO::FETCH_ASSOC); }catch(Throwable $e){}
admin_header(\'Tracking\',\'tracking\');
$v=function($k) use($t){ return e((string)($t[$k] ?? \'\')); };
?>
<style>.tk label{display:block;font-weight:600;font-size:13px;margin:12px 0 4px}.tk input{width:100%;max-width:420px;padding:9px;border:1px solid var(--rule,#ccc);border-radius:7px}.tk .h{color:#777;font-size:12px;margin-top:2px}</style>
<div class="tk" style="max-width:560px">
<h1 style="font-family:\'Cormorant Garamond\',serif;font-weight:600">Tracking &amp; conversions</h1>
<p class="muted">Paste your IDs and WhatsApp number. Landing pages fire GA4 + a Google Ads conversion on every enquiry, and show WhatsApp/Call buttons.</p>
<?php if ($saved): ?><div style="background:#e4f5ec;border:1px solid #0d7a4f;color:#0d7a4f;padding:10px;border-radius:8px;margin:10px 0">Saved.</div><?php endif; ?>
<form method="post"><?= function_exists(\'csrf_field\')?csrf_field():\'\' ?>
  <label>Agency WhatsApp number (with country code)</label><input name="wa_number" value="<?= $v(\'wa_number\') ?>" placeholder="9198XXXXXXXX"><div class="h">Turns on WhatsApp + Call buttons on all landing pages.</div>
  <label>GA4 Measurement ID</label><input name="ga4_id" value="<?= $v(\'ga4_id\') ?>" placeholder="G-XXXXXXXXXX"><div class="h">Analytics 4 - Admin - Data streams - Measurement ID.</div>
  <label>Google Ads Conversion ID</label><input name="ads_conv_id" value="<?= $v(\'ads_conv_id\') ?>" placeholder="AW-XXXXXXXXX"><div class="h">Ads - Tools - Conversions - your action - tag setup.</div>
  <label>Google Ads Conversion Label</label><input name="ads_conv_label" value="<?= $v(\'ads_conv_label\') ?>" placeholder="abCdEfGhIj"><div class="h">The label after the slash in send_to.</div>
  <div style="margin-top:16px"><button class="btn" type="submit">Save</button></div>
</form>
</div>
<?php admin_footer();';
  $code=rtrim($prologue,"\n")."\n".$body."\n";
  $tt=tempnam(sys_get_temp_dir(),"y");file_put_contents($tt,$code);exec("php -l ".escapeshellarg($tt)." 2>&1",$o,$rc);unlink($tt);
  if($rc!==0){ $rep[]="track=lintFAIL"; } else { copy("$root/admin/tracking.php","$root/admin/tracking.php.bak.patchLP4"); file_put_contents("$root/admin/tracking.php",$code); $rep[]="track=ok"; }
}
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_plp4.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
