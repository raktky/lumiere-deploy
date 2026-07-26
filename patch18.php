<?php
/* patch18: convert admin chrome from a flat top nav bar to a grouped LEFT SIDEBAR with
   collapsible Menu -> sub-menu sections. Full rewrite of app/admin_ui.php (backup + lint).
   Keeps: admin_header()/admin_footer()/flash_set()/flash_show() signatures, rbac require,
   admin_can() per-item filtering, all component CSS classes other pages use. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
$ui="$root/app/admin_ui.php";
if(!is_file($ui)){ die("admin_ui missing\n"); }

$new = <<<'PHPUI'
<?php
/**
 * Lumiere Holidays — admin chrome (v10: grouped left sidebar with collapsible submenus).
 * admin_header()/admin_footer()/flash helpers preserved. RBAC-aware (admin_can per item).
 */

declare(strict_types=1);

require_once __DIR__ . '/rbac.php';

function admin_header(string $title, string $active = ''): void
{
    // Grouped navigation. Each item keeps its permission key for RBAC filtering.
    $groups = [
        'Overview' => [
            'dashboard'     => ['Dashboard', 'index.php'],
            'ceo_dashboard' => ['CEO Dashboard', 'dashboard-ceo.php'],
        ],
        'Sales & CRM' => [
            'leads'         => ['Leads', 'leads.php'],
            'trip_requests' => ['Trip requests', 'list.php?t=trip_requests'],
            'quotes'        => ['Quotes', 'quotes.php'],
            'enquiries'     => ['Enquiries', 'list.php?t=enquiries'],
            'bookings'      => ['Bookings', 'bookings.php'],
        ],
        'Rates' => [
            'rate_vehicle'  => ['Vehicles', 'list.php?t=rate_vehicle'],
            'rate_hotel'    => ['Hotels', 'list.php?t=rate_hotel'],
        ],
        'Content' => [
            'quick_create'  => ['Quick create', 'quick-create.php'],
            'packages'      => ['Packages', 'list.php?t=packages'],
            'journeys'      => ['Journeys', 'list.php?t=journeys'],
            'destinations'  => ['Destinations', 'list.php?t=destinations'],
            'services'      => ['Services', 'list.php?t=services'],
            'menus'         => ['Menus', 'list.php?t=menus'],
            'stories'       => ['Stories', 'list.php?t=stories'],
            'posts'         => ['Journal', 'list.php?t=posts'],
            'pages'         => ['Pages', 'list.php?t=pages'],
            'team'          => ['Team', 'list.php?t=team'],
        ],
        'Settings' => [
            'users'         => ['Users', 'users.php'],
            'roles'         => ['Roles', 'roles.php'],
            'settings'      => ['Settings', 'settings.php'],
        ],
    ];

    // Filter by permission; drop empty groups; find which group holds the active item.
    $visible = [];
    $activeGroup = '';
    foreach ($groups as $gname => $items) {
        $keep = [];
        foreach ($items as $key => $item) {
            if (function_exists('admin_can') && !admin_can((string) $key)) {
                continue;
            }
            $keep[$key] = $item;
            if ($key === $active) {
                $activeGroup = $gname;
            }
        }
        if ($keep) {
            $visible[$gname] = $keep;
        }
    }
    if ($activeGroup === '' && $visible) {
        $activeGroup = array_key_first($visible);
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="google-site-verification" content="svPguTzRgoc2QOshVhyqQvYioeKlnjOeQ6cCBkFL2E8">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Lumiere Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap">
<style>
:root{--ivory:#FAF7F2;--ink:#1E1E1C;--gold:#B08D57;--sage:#6F7D6B;--rule:rgba(30,30,28,.12)}
*{box-sizing:border-box;margin:0}
body{background:var(--ivory);color:var(--ink);font:15px/1.6 Inter,system-ui,sans-serif;display:flex;min-height:100vh}
a{color:var(--gold);text-decoration:none}
a:hover{text-decoration:underline}

/* ---- left sidebar ---- */
.side{width:236px;flex:0 0 236px;background:#fff;border-right:1px solid var(--rule);position:sticky;top:0;height:100vh;overflow-y:auto;padding:16px 0 8px}
.side .brand{font-family:'Cormorant Garamond',serif;font-size:24px;letter-spacing:.04em;color:var(--ink);padding:2px 22px 14px}
.side details.grp{border-top:1px solid var(--rule)}
.side details.grp>summary{list-style:none;cursor:pointer;padding:11px 22px;font-size:11px;text-transform:uppercase;letter-spacing:.09em;color:var(--sage);font-weight:600;display:flex;justify-content:space-between;align-items:center;user-select:none}
.side details.grp>summary::-webkit-details-marker{display:none}
.side details.grp>summary::after{content:'\203A';font-size:15px;line-height:1;opacity:.55;transition:transform .15s ease}
.side details.grp[open]>summary::after{transform:rotate(90deg)}
.side a.lnk{display:block;padding:8px 22px 8px 30px;font-size:14px;color:var(--ink);opacity:.82}
.side a.lnk:hover{opacity:1;color:var(--gold);text-decoration:none;background:rgba(176,141,87,.06)}
.side a.lnk.on{opacity:1;color:var(--gold);font-weight:600;padding-left:28px;border-left:2px solid var(--gold);background:rgba(176,141,87,.08)}
.side .side-foot{border-top:1px solid var(--rule);margin-top:8px;padding:14px 22px 4px;display:flex;flex-direction:column;gap:9px}
.side .side-foot a{font-size:13px;color:var(--sage)}
.side .side-foot a:hover{color:var(--gold);text-decoration:none}

/* ---- main column ---- */
main{flex:1;min-width:0;max-width:1120px;padding:34px 42px 76px}
h1{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:34px;margin-bottom:20px}
h2{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:24px;margin:28px 0 12px}
h3{margin:0}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--rule)}
th,td{padding:10px 14px;border-bottom:1px solid var(--rule);text-align:left;vertical-align:top;font-size:14px}
th{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--sage)}
tr:last-child td{border-bottom:0}
label{display:block;font-size:13px;font-weight:600;margin:18px 0 6px}
input[type=text],input[type=email],input[type=password],input[type=number],textarea,select{width:100%;padding:10px 12px;border:1px solid var(--rule);border-radius:4px;background:#fff;font:inherit;color:var(--ink)}
textarea{min-height:120px}
textarea.tall{min-height:260px}
textarea,input{outline-color:var(--gold)}
.btn{display:inline-block;background:var(--ink);color:var(--ivory);border:0;border-radius:4px;padding:10px 22px;font:600 14px Inter,sans-serif;cursor:pointer;margin-top:20px}
.btn:hover{background:var(--gold);text-decoration:none}
.btn.small{padding:5px 12px;font-size:12.5px;margin:0}
.btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--rule)}
.btn.ghost:hover{border-color:var(--gold);color:var(--gold);background:transparent}
.btn.danger:hover{background:#8d3a2f}
.flash{padding:12px 16px;border:1px solid var(--sage);color:var(--sage);border-radius:4px;margin-bottom:20px;background:#fff}
.flash.err{border-color:#8d3a2f;color:#8d3a2f}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin:8px 0 12px}
.card{background:#fff;border:1px solid var(--rule);border-radius:6px;padding:18px}
.card .num{font-family:'Cormorant Garamond',serif;font-size:36px;line-height:1;color:var(--gold)}
.card .lbl{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--sage);margin-top:6px}
.muted{color:var(--sage);font-size:13px}
.inline{display:inline}
.pill{display:inline-block;font-size:11px;padding:2px 10px;border-radius:100px;border:1px solid var(--rule)}
.pill.on{border-color:var(--sage);color:var(--sage)}
.pill.off{color:#8d3a2f;border-color:#8d3a2f}
.help{font-size:12.5px;color:var(--sage);margin-top:4px}
.actions{white-space:nowrap}
.shortcut{display:block;background:var(--ink);color:var(--ivory);border-radius:8px;padding:22px 26px;margin:4px 0 24px}
.shortcut:hover{background:var(--gold);text-decoration:none;color:var(--ivory)}
.shortcut .t{font-family:'Cormorant Garamond',serif;font-size:24px}
.shortcut .s{font-size:13px;opacity:.85;margin-top:4px}

@media(max-width:820px){
  body{display:block}
  .side{width:auto;flex:none;height:auto;position:static;border-right:0;border-bottom:1px solid var(--rule)}
  main{padding:22px 18px 60px}
}
</style>
</head>
<body>
<aside class="side">
  <div class="brand">Lumiere</div>
  <?php foreach ($visible as $gname => $items): ?>
  <details class="grp"<?= $gname === $activeGroup ? ' open' : '' ?>>
    <summary><?= e($gname) ?></summary>
    <?php foreach ($items as $key => $item): ?>
    <a class="lnk<?= $key === $active ? ' on' : '' ?>" href="<?= e($item[1]) ?>"><?= e($item[0]) ?></a>
    <?php endforeach; ?>
  </details>
  <?php endforeach; ?>
  <div class="side-foot">
    <a href="<?= e(url('')) ?>" target="_blank" rel="noopener">View site ↗</a>
    <a href="logout.php">Log out</a>
  </div>
</aside>
<main>
<?php
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}

/** One-shot flash message helpers (stored in session). */
function flash_set(string $message, bool $error = false): void
{
    $_SESSION['flash'] = ['m' => $message, 'e' => $error];
}

function flash_show(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="flash' . (!empty($f['e']) ? ' err' : '') . '">' . e((string) $f['m']) . '</div>';
    }
}
PHPUI;

// lint then write
$t=tempnam(sys_get_temp_dir(),'ui');file_put_contents($t,$new);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
if($rc!==0){ echo "lint FAIL:\n".implode("\n",$o)."\n"; }
else{ copy($ui,$ui.'.bak.patch18'); file_put_contents($ui,$new); echo "admin_ui.php rewritten (left sidebar)\n"; }
$cc=0;foreach(glob("$root/cache/*.html") as $g){@unlink($g);$cc++;} echo "cache cleared: $cc\nDONE\n";
