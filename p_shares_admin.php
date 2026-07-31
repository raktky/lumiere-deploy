<?php
$root='/var/www/lumiere/experience';
$o=[];
// 1) write admin/shares.php
$dst="$root/admin/shares.php";
$code=gzdecode(base64_decode('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'));
if($code){ file_put_contents($dst,$code); $lint=shell_exec('php -l '.escapeshellarg($dst).' 2>&1'); $o['shares_lint']=trim($lint); $o['shares_ok']=strpos((string)$lint,'No syntax errors')!==false; if(!$o['shares_ok']) @unlink($dst); }
// 2) add sidebar link in AI Builder group
$f="$root/app/admin_ui.php"; $s=file_get_contents($f);
$o['menu_already']=strpos($s,"'shares'")!==false;
$anchor="['AI Planner & Settings', 'ai-plan.php'],";
if(!$o['menu_already'] && strpos($s,$anchor)!==false){
  $ins=$anchor."\n        'shares'      => ['Shared & feedback', 'shares.php'],";
  $s2=str_replace($anchor,$ins,$s);
  copy($f,"$f.baksh"); file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1'); $o['menu_lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.baksh",$f);$o['menu_restored']=true;}else{$o['menu_done']=true;}
}
// 3) grant CEO role perm
try{ require_once "$root/app/config.php"; require_once "$root/app/db.php"; $pdo=db();
  $cur=$pdo->query("SELECT perms FROM tb_roles WHERE id=2")->fetchColumn();
  $parts=array_values(array_filter(array_map('trim',explode(',',(string)$cur)),fn($x)=>$x!==''));
  if(!in_array('shares',$parts,true)){ $parts[]='shares'; $pdo->prepare("UPDATE tb_roles SET perms=? WHERE id=2")->execute([implode(',',$parts)]); }
  $o['ceo_has_shares']=true;
}catch(Throwable $e){ $o['perm_err']=$e->getMessage(); }
file_put_contents("$root/_sharesadmin.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'SHARESADMIN '.json_encode($o);
