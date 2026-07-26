<?php
/* patch16: users.php role assignment — pick a role when creating a login + change role per row.
   Whitespace-tolerant / mid-line anchors. Each replace independent; file written only if lint passes.
   Idempotent (guards on already-applied markers). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$f="$root/admin/users.php";
if(!is_file($f)){ die("users.php missing\n"); }
$u=file_get_contents($f);
$orig=$u;
$log=[];

/* A. load $allRoles + join role name into the admins list query */
if(strpos($u,'$allRoles')===false){
  $anchor="\$admins = db()->query('SELECT id, username, created_at FROM admins ORDER BY created_at ASC, id ASC')->fetchAll();";
  $repl="\$allRoles = db()->query('SELECT id, name FROM tb_roles ORDER BY sort, name')->fetchAll();\n"
       ."\$admins = db()->query('SELECT a.id, a.username, a.created_at, a.role_id, r.name AS role_name FROM admins a LEFT JOIN tb_roles r ON r.id = a.role_id ORDER BY a.created_at ASC, a.id ASC')->fetchAll();";
  $u=str_replace($anchor,$repl,$u,$cA); $log[]="A allRoles=$cA";
}

/* B. create INSERT includes role_id */
$u=str_replace("INSERT INTO admins (username, pass_hash, created_at) VALUES (?, ?, NOW())",
               "INSERT INTO admins (username, pass_hash, role_id, created_at) VALUES (?, ?, ?, NOW())",$u,$cB1);
$u=str_replace("[\$username, password_hash(\$password, PASSWORD_DEFAULT)]",
               "[\$username, password_hash(\$password, PASSWORD_DEFAULT), ((int) (\$_POST['role_id'] ?? 0) ?: null)]",$u,$cB2);
$log[]="B insert=$cB1 exec=$cB2";

/* C. setrole POST handler (inject before the delete branch) */
if(strpos($u,"'setrole'")===false){
  $u=str_replace("elseif (\$action === 'delete') {",
    "elseif (\$action === 'setrole') { \$id=(int)(\$_POST['id']??0); \$rid=(int)(\$_POST['role_id']??0); if(\$id>0){ db()->prepare('UPDATE admins SET role_id = ? WHERE id = ?')->execute([\$rid?:null,\$id]); flash_set('Role updated.'); } header('Location: users.php'); exit; } elseif (\$action === 'delete') {",
    $u,$cC); $log[]="C setrole=$cC";
}

/* D. list: add Role column header before Created */
$u=str_replace("<th>Created</th>","<th>Role</th><th>Created</th>",$u,$cD); $log[]="D header=$cD";

/* E. list: add Role cell (inline change form) before the Created cell */
if(strpos($u,'name="action" value="setrole"')===false || strpos($u,'onchange="this.form.submit()"')===false){
  $createdCell="<td><?= e(date('j M Y, H:i', (int) strtotime((string) \$row['created_at']))) ?></td>";
  $roleCell="<td>\n"
    ."<form class=\"inline\" method=\"post\" action=\"users.php\">\n"
    ."<?= csrf_field() ?><input type=\"hidden\" name=\"action\" value=\"setrole\"><input type=\"hidden\" name=\"id\" value=\"<?= e((string) (int) \$row['id']) ?>\">\n"
    ."<select name=\"role_id\" onchange=\"this.form.submit()\">\n"
    ."<option value=\"0\">— none —</option>\n"
    ."<?php foreach (\$allRoles as \$ro): ?><option value=\"<?= e((string) \$ro['id']) ?>\" <?= (int) \$row['role_id'] === (int) \$ro['id'] ? 'selected' : '' ?>><?= e((string) \$ro['name']) ?></option><?php endforeach; ?>\n"
    ."</select>\n</form>\n</td>\n";
  $u=str_replace($createdCell,$roleCell.$createdCell,$u,$cE); $log[]="E cell=$cE";
}

/* F. create form: role dropdown before the submit button */
if(strpos($u,'id="u-role"')===false){
  $btn='<button class="btn" type="submit">Create admin account</button>';
  $sel="<label for=\"u-role\">Role</label>\n"
    ."<select id=\"u-role\" name=\"role_id\">\n"
    ."<option value=\"0\">— choose a role —</option>\n"
    ."<?php foreach (\$allRoles as \$ro): ?><option value=\"<?= e((string) \$ro['id']) ?>\"><?= e((string) \$ro['name']) ?></option><?php endforeach; ?>\n"
    ."</select>\n";
  $u=str_replace($btn,$sel.$btn,$u,$cF); $log[]="F formselect=$cF";
}

echo implode("\n",$log)."\n";
if($u===$orig){ echo "no change\nDONE\n"; return; }
$t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$u);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
if($rc!==0){ echo "lint FAIL:\n".implode("\n",$o)."\n"; }
else{ copy($f,$f.'.bak.patch16'); file_put_contents($f,$u); echo "users.php written\n"; }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\nDONE\n";
