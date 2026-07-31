<?php
$root='/var/www/lumiere/experience';
$f="$root/app/admin_ui.php";
$s=file_get_contents($f);
$o=[];
$topgroup = <<<'GRP'
'AI Builder' => [
        'ai_package'  => ['AI Package Builder', 'ai-package.php'],
        'loc_images'  => ['Location Images', 'location-images.php'],
        'ai_plan'     => ['AI Planner & Settings', 'ai-plan.php'],
    ],
    'Overview' => [
GRP;
$anchor="'Overview' => [";
$o['anchor_found']=substr_count($s,$anchor);
$o['already']=strpos($s,"'AI Builder' =>")!==false;
$changed=$s;
if(!$o['already'] && $o['anchor_found']>=1){
  $changed=preg_replace('/'.preg_quote($anchor,'/').'/',$topgroup,$changed,1);
}
// remove duplicate entries from Content group
foreach([
  "        'ai_package'   => ['AI Package Builder', 'ai-package.php'],\n",
  "        'loc_images'   => ['Location Images', 'location-images.php'],\n",
] as $dup){ if(strpos($changed,$dup)!==false){ $changed=str_replace($dup,'',$changed); $o['removed'][]=trim($dup); } }
if($changed!==$s){
  copy($f,"$f.bakmenu");
  file_put_contents($f,$changed);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.bakmenu",$f);$o['restored']=true;} else {$o['menu_done']=true;}
}
// grant CEO role (id 2) the AI perms
try{
  require_once "$root/app/config.php"; require_once "$root/app/db.php"; $pdo=db();
  $cur=$pdo->query("SELECT perms FROM tb_roles WHERE id=2")->fetchColumn();
  $parts=array_values(array_filter(array_map('trim',explode(',',(string)$cur)),fn($x)=>$x!==''));
  foreach(['ai_package','loc_images','ai_plan'] as $p){ if(!in_array($p,$parts,true)) $parts[]=$p; }
  $new=implode(',',$parts);
  $pdo->prepare("UPDATE tb_roles SET perms=? WHERE id=2")->execute([$new]);
  $o['ceo_perms']=$new;
}catch(Throwable $e){ $o['perm_err']=$e->getMessage(); }
file_put_contents("$root/_menu.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'MENU '.json_encode($o);
