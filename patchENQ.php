<?php
/* patchENQ: quick "New enquiry" -> builder flow.
   - admin/new-enquiry.php (short form -> INSERT trip_requests -> redirect to quote.php?id=)
   - sidebar: Sales & CRM > + New enquiry (top)
   Idempotent; lint; status report. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$rep=[];
function put_lint($path,$code,&$rep,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ $rep[]="$tag=lintFAIL"; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patchENQ');
  file_put_contents($path,$code); $rep[]="$tag=ok"; return true;
}

/* prologue from quotes.php */
$src=@file_get_contents("$root/admin/quotes.php"); $prologue='';
if($src){ foreach(explode("\n",$src) as $ln){ $t=trim($ln);
  $ok=($t==='')||strpos($t,'<?php')===0||strpos($t,'declare')===0||strpos($t,'require')===0||strpos($t,'use ')===0||strpos($t,'namespace')===0||strpos($t,'ini_set')===0||strpos($t,'error_reporting')===0||strpos($t,'require_admin')!==false||strpos($t,'admin_user')!==false||strpos($t,'//')===0||strpos($t,'/*')===0||strpos($t,'*')===0||preg_match('/^\$pdo\s*=/',$t)||strpos($t,'rbac')!==false;
  if(!$ok) break; $prologue.=$ln."\n"; } }
if(strpos($prologue,'require_admin')===false){ $rep[]='enq=BAD-prologue'; }
else{
  if(strpos($prologue,'$pdo')===false){ $prologue.="\$pdo = db();\n"; }
  $body = <<<'PHPX'

$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (function_exists('csrf_check')) { csrf_check(); }
  $name = trim((string) ($_POST['name'] ?? ''));
  if ($name === '') { $err = 'Customer name is required.'; }
  else {
    $sd = trim((string) ($_POST['start_date'] ?? '')); $sd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sd) ? $sd : null;
    $ed = trim((string) ($_POST['end_date'] ?? '')); $ed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ed) ? $ed : null;
    try {
      $pdo->prepare('INSERT INTO trip_requests (name,email,phone,regions,nights,start_date,end_date,adults,children,occasion,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
          ->execute([
            $name,
            trim((string) ($_POST['email'] ?? '')),
            trim((string) ($_POST['phone'] ?? '')),
            trim((string) ($_POST['regions'] ?? '')),
            (int) ($_POST['nights'] ?? 0),
            $sd, $ed,
            max(1, (int) ($_POST['adults'] ?? 2)),
            max(0, (int) ($_POST['children'] ?? 0)),
            trim((string) ($_POST['occasion'] ?? '')),
            trim((string) ($_POST['notes'] ?? '')),
          ]);
      $nid = (int) $pdo->lastInsertId();
      $__b = rtrim((string) (function_exists('url') ? url('') : ''), '/');
      header('Location: ' . $__b . '/admin/quote.php?id=' . $nid);
      exit;
    } catch (Throwable $e) { $err = 'Could not save enquiry.'; }
  }
}
admin_header('New enquiry', 'new_enquiry');
?>
<style>
.enq{max-width:640px}
.enq .steps{display:flex;gap:8px;margin:8px 0 18px;font-size:12.5px;color:var(--sage,#6f7d6b)}
.enq .steps span{background:#f4efe7;border-radius:20px;padding:4px 12px}
.enq .steps span.on{background:var(--gold,#b08d57);color:#fff}
.enq label{display:block;font-weight:600;margin:12px 0 4px;font-size:13px}
.enq input,.enq textarea{width:100%;padding:9px;border:1px solid var(--rule,#ccc);border-radius:7px}
.enq .g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.enq .g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
@media(max-width:600px){.enq .g2,.enq .g3{grid-template-columns:1fr}}
.flash.err{background:#fdecec;border:1px solid #b00020;color:#b00020;border-radius:8px;padding:10px 14px;margin:10px 0}
</style>
<div class="enq">
<h1 style="font-family:'Cormorant Garamond',serif;font-weight:600">New enquiry</h1>
<div class="steps"><span class="on">1 · Enquiry details</span><span>2 · Build package</span><span>3 · Send on WhatsApp</span></div>
<?php if ($err !== ''): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<form method="post">
  <?= function_exists('csrf_field') ? csrf_field() : '' ?>
  <label>Customer name *</label>
  <input name="name" required placeholder="e.g. Rahul & Meera">
  <div class="g2">
    <div><label>WhatsApp number</label><input name="phone" placeholder="9XXXXXXXXX"></div>
    <div><label>Email (optional)</label><input name="email" type="email"></div>
  </div>
  <label>Regions / route</label>
  <input name="regions" placeholder="Munnar, Thekkady, Alleppey">
  <div class="g3">
    <div><label>Nights</label><input name="nights" type="number" min="0" value="0"></div>
    <div><label>Travel from</label><input name="start_date" type="date"></div>
    <div><label>Travel to</label><input name="end_date" type="date"></div>
  </div>
  <div class="g3">
    <div><label>Adults</label><input name="adults" type="number" min="1" value="2"></div>
    <div><label>Children</label><input name="children" type="number" min="0" value="0"></div>
    <div><label>Occasion</label><input name="occasion" placeholder="Honeymoon"></div>
  </div>
  <label>Notes (paste the WhatsApp message)</label>
  <textarea name="notes" rows="3" placeholder="Paste customer's WhatsApp enquiry here…"></textarea>
  <div style="margin-top:16px"><button class="btn" type="submit">Create &amp; build package &rarr;</button></div>
</form>
<p class="muted" style="margin-top:12px;font-size:13px">Saves the enquiry and opens the package builder. Build the day-by-day plan (use a template to go fast), set the price, then use the WhatsApp button to send the customer link.</p>
</div>
<?php admin_footer();
PHPX;
  put_lint("$root/admin/new-enquiry.php",rtrim($prologue,"\n")."\n".$body."\n",$rep,'enq');
}

/* sidebar: prepend New enquiry to Sales & CRM */
$uf="$root/app/admin_ui.php"; $uc=file_get_contents($uf);
if(strpos($uc,"'new_enquiry'")===false){
  $uc2=preg_replace("/('leads'\\s*=>\\s*\\['Leads',\\s*'leads\\.php'\\],)/","'new_enquiry'   => ['+ New enquiry', 'new-enquiry.php'],\n            $1",$uc,1,$nn);
  if($nn===1){ $t=tempnam(sys_get_temp_dir(),'u');file_put_contents($t,$uc2);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t); if($rc===0){ copy($uf,$uf.'.bak.patchENQ'); file_put_contents($uf,$uc2); $rep[]='nav=ok'; } else { $rep[]='nav=lintFAIL'; } }
  else{ $rep[]='nav=anchorfail'; }
} else { $rep[]='nav=already'; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;}
foreach(glob("$root/_*.txt") as $g){@unlink($g);}
file_put_contents("$root/_penq.txt", implode(" ",$rep)." cache=$cc");
echo implode("\n",$rep)."\ncache=$cc\nDONE\n";
