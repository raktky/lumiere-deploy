<?php
/* patch15: re-apply admin_ui.php RBAC edits (patch14 skipped them — roles-nav anchor had alignment
   spaces so matched 0x and aborted the write). Whitespace-tolerant anchors. Idempotent; lint before write. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$ui="$root/app/admin_ui.php";
if(!is_file($ui)){ die("admin_ui missing\n"); }
$a=file_get_contents($ui);
$orig=$a;

/* 1. require rbac.php after declare(strict_types=1); */
if(strpos($a,"rbac.php")===false){
  $a=preg_replace('/(declare\(strict_types=1\);)/', "$1\n\nrequire_once __DIR__ . '/rbac.php';", $a, 1, $c1);
  echo "rbac-require applied=".(int)$c1."\n";
}else{ echo "rbac-require already present\n"; }

/* 2. nav filter on the foreach */
if(strpos($a,"admin_can((string) \$key)")===false){
  $a=preg_replace('/<\?php\s+foreach\s*\(\s*\$nav\s+as\s+\$key\s*=>\s*\$item\s*\)\s*:\s*\?>/',
    "<?php foreach (\$nav as \$key => \$item): if (function_exists('admin_can') && !admin_can((string) \$key)) { continue; } ?>",
    $a, 1, $c2);
  echo "nav-filter applied=".(int)$c2."\n";
}else{ echo "nav-filter already present\n"; }

/* 3. add Roles nav entry before the Settings entry (tolerant of alignment spaces) */
if(strpos($a,"'roles'")===false || strpos($a,"roles.php']")===false){
  $a=preg_replace("/('settings'\s*=>\s*\['Settings',\s*'settings\.php'\],)/",
    "'roles' => ['Roles', 'roles.php'],\n\$1", $a, 1, $c3);
  echo "roles-nav applied=".(int)$c3."\n";
}else{ echo "roles-nav already present\n"; }

if($a===$orig){ echo "no change\nDONE\n"; return; }
$t=tempnam(sys_get_temp_dir(),'u');file_put_contents($t,$a);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
if($rc!==0){ echo "lint FAIL:\n".implode("\n",$o)."\n"; }
else{ copy($ui,$ui.'.bak.patch15'); file_put_contents($ui,$a); echo "admin_ui.php written\n"; }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\nDONE\n";
