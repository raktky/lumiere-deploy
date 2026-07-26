<?php
/* patch14 (RBAC foundation):
   - tb_roles table + admins.role_id column, seed 5 roles, assign existing admins -> Super Admin
   - app/rbac.php (admin_perms/admin_can), back-compat: no role or missing table => full access
   - admin_ui.php: require rbac + filter nav by admin_can + add Roles nav entry
   - admin/roles.php: create/edit/delete roles (permission checkboxes)
   - quote.php: hide cost/margin (.internal) unless admin_can('quote_cost')
   - remove public dump _d6_kx7.txt
   Idempotent; lints each file before writing; backs up edited files. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
if(!is_file("$root/templates/build.php")){ die("root not found\n"); }
@unlink("$root/_d6_kx7.txt");
require_once "$root/app/config.php";
require_once "$root/app/db.php";
$errs=[];

/* 1. migrate */
try{
  $pdo=db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS tb_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    perms TEXT NULL,
    sort INT NOT NULL DEFAULT 100
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // add role_id to admins if absent
  $has=false; foreach($pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_ASSOC) as $c){ if($c['Field']==='role_id') $has=true; }
  if(!$has){ $pdo->exec("ALTER TABLE admins ADD COLUMN role_id INT NULL"); echo "admins.role_id added\n"; } else { echo "admins.role_id exists\n"; }
  // seed roles
  $seed=[
    ['Super Admin','*',10],
    ['CEO','dashboard,leads,quotes,quote_cost,rate_vehicle,rate_hotel,trip_requests,enquiries,bookings,packages,journeys,destinations,services,reports',20],
    ['Sales','dashboard,leads,quotes,trip_requests,enquiries',30],
    ['Operations','dashboard,quotes,quote_cost,rate_vehicle,rate_hotel,trip_requests,bookings',40],
    ['Driver','dashboard',50],
  ];
  $ins=$pdo->prepare("INSERT INTO tb_roles (name,perms,sort) VALUES (?,?,?) ON DUPLICATE KEY UPDATE perms=VALUES(perms), sort=VALUES(sort)");
  foreach($seed as $s){ $ins->execute($s); }
  echo "roles seeded\n";
  // assign existing admins with NULL role_id to Super Admin
  $superId=(int)$pdo->query("SELECT id FROM tb_roles WHERE name='Super Admin'")->fetchColumn();
  if($superId>0){ $pdo->exec("UPDATE admins SET role_id=$superId WHERE role_id IS NULL"); echo "unassigned admins -> Super Admin ($superId)\n"; }
}catch(Throwable $e){ echo "DB error: ".$e->getMessage()."\n"; $errs[]='db'; }

/* helper: lint a string, write file on pass */
function put_lint($path,$code,&$errs,$tag){
  $t=tempnam(sys_get_temp_dir(),'x');file_put_contents($t,$code);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
  if($rc!==0){ echo "$tag lint FAIL:\n".implode("\n",$o)."\n"; $errs[]=$tag; return false; }
  if(is_file($path)) copy($path,$path.'.bak.patch14');
  file_put_contents($path,$code); echo "$tag written\n"; return true;
}

/* 2. app/rbac.php */
$rbac = <<<'PHP'
<?php
declare(strict_types=1);
/** RBAC helpers. Back-compat: if roles table missing or user has no role, grant full access. */
function admin_perms(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = ['*'];
    try {
        $u = $_SESSION[ADMIN_SESSION_KEY] ?? [];
        $id = (int) ($u['id'] ?? 0);
        if ($id <= 0) { return $cache = []; }
        $st = db()->prepare('SELECT role_id FROM admins WHERE id = ?');
        $st->execute([$id]);
        $rid = $st->fetchColumn();
        if ($rid === false || $rid === null) { return $cache = ['*']; }
        $rs = db()->prepare('SELECT perms FROM tb_roles WHERE id = ?');
        $rs->execute([(int) $rid]);
        $perms = $rs->fetchColumn();
        if ($perms === false) { return $cache = ['*']; }
        $perms = trim((string) $perms);
        if ($perms === '' || $perms === '*') { return $cache = ['*']; }
        $cache = array_values(array_filter(array_map('trim', explode(',', $perms))));
    } catch (Throwable $e) {
        $cache = ['*']; // schema not migrated yet -> do not lock anyone out
    }
    return $cache;
}
function admin_can(string $key): bool
{
    $p = admin_perms();
    return in_array('*', $p, true) || in_array($key, $p, true);
}
function admin_role_name(): string
{
    try {
        $u = $_SESSION[ADMIN_SESSION_KEY] ?? [];
        $id = (int) ($u['id'] ?? 0);
        if ($id <= 0) { return ''; }
        $st = db()->prepare('SELECT r.name FROM admins a LEFT JOIN tb_roles r ON r.id = a.role_id WHERE a.id = ?');
        $st->execute([$id]);
        return (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) { return ''; }
}
PHP;
put_lint("$root/app/rbac.php",$rbac,$errs,'rbac');

/* 3. admin_ui.php edits */
$ui="$root/app/admin_ui.php";
$a=file_get_contents($ui);
$aorig=$a;
if(strpos($a,"require_once __DIR__ . '/rbac.php';")===false){
  $a=str_replace("declare(strict_types=1);","declare(strict_types=1);\n\nrequire_once __DIR__ . '/rbac.php';",$a,$c1);
  if($c1!==1){ echo "ui rbac-require anchor fail=$c1\n"; $errs[]='ui1'; }
}
// nav filter
if(strpos($a,"if (function_exists('admin_can')")===false){
  $a=str_replace("<?php foreach (\$nav as \$key => \$item): ?>","<?php foreach (\$nav as \$key => \$item): if (function_exists('admin_can') && !admin_can((string) \$key)) { continue; } ?>",$a,$c2);
  if($c2!==1){ echo "ui navfilter anchor fail=$c2\n"; $errs[]='ui2'; }
}
// add Roles nav entry after Users
if(strpos($a,"'roles' => ['Roles'")===false){
  $a=str_replace("'users' => ['Users', 'users.php'],","'users' => ['Users', 'users.php'],\n'roles' => ['Roles', 'roles.php'],",$a,$c3);
  if($c3!==1){ echo "ui roles-nav anchor fail=$c3\n"; $errs[]='ui3'; }
}
if($a!==$aorig && !in_array('ui1',$errs) && !in_array('ui2',$errs) && !in_array('ui3',$errs)){
  put_lint($ui,$a,$errs,'admin_ui');
}else if($a===$aorig){ echo "admin_ui already patched\n"; }

/* 4. admin/roles.php */
$rolesPhp = <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/admin_ui.php';
require_admin();
if (function_exists('admin_can') && !admin_can('roles')) { http_response_code(403); exit('Not allowed.'); }

$pdo = db();

/* master permission list (label => key) */
$PERMS = [
  'Menus' => [
    'dashboard' => 'Dashboard', 'leads' => 'Leads', 'quotes' => 'Quotes',
    'trip_requests' => 'Trip requests', 'enquiries' => 'Enquiries', 'bookings' => 'Bookings',
    'rate_vehicle' => 'Rates - Vehicles', 'rate_hotel' => 'Rates - Hotels',
    'packages' => 'Packages', 'journeys' => 'Journeys', 'destinations' => 'Destinations',
    'services' => 'Services', 'menus' => 'Menus', 'stories' => 'Stories', 'posts' => 'Journal',
    'pages' => 'Pages', 'team' => 'Team', 'settings' => 'Settings',
    'users' => 'Users', 'roles' => 'Roles',
  ],
  'Actions' => [
    'quote_cost' => 'See cost & margin on quotes', 'reports' => 'Reports',
    'manage_users' => 'Manage team logins', 'manage_roles' => 'Manage roles',
  ],
];
$allKeys = [];
foreach ($PERMS as $g) { foreach ($g as $k => $v) { $allKeys[] = $k; } }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $full = ($_POST['full'] ?? '') === '1';
        $sel = (array) ($_POST['perm'] ?? []);
        $keys = array_values(array_intersect($allKeys, array_map('strval', $sel)));
        $perms = $full ? '*' : implode(',', $keys);
        if ($name === '') {
            flash_set('Role name is required.', true);
        } elseif ($id > 0) {
            $st = $pdo->prepare('UPDATE tb_roles SET name = ?, perms = ? WHERE id = ?');
            $st->execute([$name, $perms, $id]);
            flash_set('Role updated.');
        } else {
            try {
                $st = $pdo->prepare('INSERT INTO tb_roles (name, perms, sort) VALUES (?, ?, 100)');
                $st->execute([$name, $perms]);
                flash_set('Role created.');
            } catch (Throwable $e) { flash_set('A role with that name already exists.', true); }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $inuse = (int) $pdo->query('SELECT COUNT(*) FROM admins WHERE role_id = ' . $id)->fetchColumn();
        if ($inuse > 0) { flash_set('Cannot delete: ' . $inuse . ' user(s) still have this role.', true); }
        else { $pdo->prepare('DELETE FROM tb_roles WHERE id = ?')->execute([$id]); flash_set('Role deleted.'); }
    }
    header('Location: roles.php');
    exit;
}

$roles = $pdo->query('SELECT * FROM tb_roles ORDER BY sort, name')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
foreach ($roles as $r) { if ((int) $r['id'] === $editId) { $edit = $r; } }
$editKeys = [];
$editFull = false;
if ($edit) {
    $pp = trim((string) $edit['perms']);
    if ($pp === '*') { $editFull = true; }
    else { $editKeys = array_filter(array_map('trim', explode(',', $pp))); }
}

admin_header('Roles', 'roles');
flash_show();
?>
<h1>Roles</h1>
<p class="muted">Roles control which menus and actions each team login can see. Assign a role to each person on the <a href="users.php">Users</a> screen.</p>

<table>
<thead><tr><th>Role</th><th>Access</th><th>Users</th><th></th></tr></thead>
<tbody>
<?php foreach ($roles as $r): $cnt = (int) $pdo->query('SELECT COUNT(*) FROM admins WHERE role_id = ' . (int) $r['id'])->fetchColumn(); ?>
<tr>
  <td><strong><?= e((string) $r['name']) ?></strong></td>
  <td><?= trim((string) $r['perms']) === '*' ? '<span class="pill on">Full access</span>' : e((string) $r['perms']) ?></td>
  <td><?= e((string) $cnt) ?></td>
  <td class="actions">
    <a class="btn small ghost" href="roles.php?edit=<?= e((string) $r['id']) ?>">Edit</a>
    <?php if ($cnt === 0 && trim((string) $r['perms']) !== '*'): ?>
    <form class="inline" method="post" onsubmit="return confirm('Delete role <?= e((string) $r['name']) ?>?');">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string) $r['id']) ?>">
      <button class="btn small ghost danger" type="submit">Delete</button>
    </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<h2><?= $edit ? 'Edit role: ' . e((string) $edit['name']) : 'Add a new role' ?></h2>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?= e((string) ($edit['id'] ?? 0)) ?>">
<label>Role name</label>
<input type="text" name="name" required maxlength="80" value="<?= e((string) ($edit['name'] ?? '')) ?>">
<label style="margin-top:14px"><input type="checkbox" name="full" value="1" <?= $editFull ? 'checked' : '' ?>> Full access (super admin — ignores the checkboxes below)</label>
<?php foreach ($PERMS as $group => $items): ?>
<h3 style="margin:16px 0 6px;font-size:14px;text-transform:uppercase;letter-spacing:.06em;color:var(--sage)"><?= e($group) ?></h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:4px 14px">
<?php foreach ($items as $k => $lbl): ?>
<label style="font-weight:400;margin:2px 0"><input type="checkbox" name="perm[]" value="<?= e($k) ?>" <?= in_array($k, $editKeys, true) ? 'checked' : '' ?>> <?= e($lbl) ?></label>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<button class="btn" type="submit"><?= $edit ? 'Save role' : 'Create role' ?></button>
<?php if ($edit): ?> <a class="btn ghost small" href="roles.php" style="margin-left:8px">Cancel</a><?php endif; ?>
</form>
<?php admin_footer();
PHP;
put_lint("$root/admin/roles.php",$rolesPhp,$errs,'roles');

/* 5. quote.php cost/margin gate */
$qf="$root/admin/quote.php";
$q=file_get_contents($qf);
$qorig=$q;
if(strpos($q,'$showCost')===false){
  $q=str_replace("require_admin();","require_admin();\n\$showCost = !function_exists('admin_can') || admin_can('quote_cost');",$q,$cq1);
  if($cq1!==1){ echo "quote showCost anchor fail=$cq1\n"; $errs[]='q1'; }
  // hide .internal via CSS when !showCost
  $q=str_replace('<div class="qt">','<div class="qt"><?php if (!$showCost): ?><style>.qt .internal{display:none}</style><?php endif; ?>',$q,$cq2);
  if($cq2!==1){ echo "quote css anchor fail=$cq2\n"; $errs[]='q2'; }
  // mark margin field group as internal so it hides for non-cost roles
  $q=str_replace("    <div>\n      <label>Margin / markup","    <div class=\"internal\">\n      <label>Margin / markup",$q,$cq3);
  if($cq3!==1){ echo "quote margin anchor fail=$cq3 (margin not hidden)\n"; }
  if(!in_array('q1',$errs)&&!in_array('q2',$errs)){ put_lint($qf,$q,$errs,'quote'); }
}else{ echo "quote already gated\n"; }

$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\n";
echo (empty($errs)?"DONE ok\n":"DONE with issues: ".implode(',',$errs)."\n");
