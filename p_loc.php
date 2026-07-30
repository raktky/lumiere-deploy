<?php
$root='/var/www/lumiere/experience';
$t=$root.'/admin/location-images.php';
@mkdir(dirname($t),0775,true);
$s = <<<'ENDOFFILE7'
<?php
declare(strict_types=1);
/* admin/location-images.php — manage hero image + description per showcase destination (tb_points). */
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/admin_ui.php';
require_admin();

$pdo = db();
$IMGDIR = dirname(__DIR__) . '/assets/img/locations';
if (!is_dir($IMGDIR)) { @mkdir($IMGDIR, 0775, true); }

$ok = ''; $err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (function_exists('csrf_check')) { try { csrf_check(); } catch (Throwable $e) {} }
    $pid   = (int) ($_POST['id'] ?? 0);
    $blurb = trim((string) ($_POST['blurb'] ?? ''));
    $row = null;
    if ($pid > 0) { $s = $pdo->prepare('SELECT id,name,image FROM tb_points WHERE id=?'); $s->execute([$pid]); $row = $s->fetch(PDO::FETCH_ASSOC); }
    if (!$row) {
        $err = 'Unknown location.';
    } else {
        $imgPath = (string) ($row['image'] ?? '');
        // handle upload
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $f = $_FILES['image'];
            if ((int) $f['error'] !== UPLOAD_ERR_OK) {
                $err = 'Upload failed (code ' . (int) $f['error'] . ').';
            } elseif ((int) $f['size'] > 12 * 1024 * 1024) {
                $err = 'Image too large (max 12 MB).';
            } else {
                $info = @getimagesize($f['tmp_name']);
                if (!$info) {
                    $err = 'That file is not a valid image.';
                } else {
                    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', (string) $row['name']));
                    $slug = trim($slug, '-');
                    $rel  = 'assets/img/locations/loc-' . (int) $row['id'] . '-' . $slug . '.jpg';
                    $dest = dirname(__DIR__) . '/' . $rel;
                    if (save_location_jpeg($f['tmp_name'], $dest, (int) $info[2])) {
                        $imgPath = $rel;
                    } else {
                        $err = 'Could not process the image (server missing GD?).';
                    }
                }
            }
        }
        if ($err === '') {
            $pdo->prepare('UPDATE tb_points SET image=?, blurb=? WHERE id=?')->execute([$imgPath ?: null, $blurb, (int) $row['id']]);
            $ok = e((string) $row['name']) . ' saved.';
        }
    }
}

/** Downscale + recompress an uploaded image to a web/PDF-friendly JPEG (<=1400px wide). */
function save_location_jpeg(string $src, string $dest, int $type): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return @copy($src, $dest); // no GD -> store as-is
    }
    switch ($type) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($src); break;
        case IMAGETYPE_WEBP: $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
        case IMAGETYPE_GIF:  $im = @imagecreatefromgif($src); break;
        default: $im = false;
    }
    if (!$im) { return false; }
    $w = imagesx($im); $h = imagesy($im);
    $max = 1400;
    if ($w > $max) {
        $nh = (int) round($h * ($max / $w));
        $dst = imagecreatetruecolor($max, $nh);
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $max, $nh, $w, $h);
        imagedestroy($im); $im = $dst;
    }
    $r = imagejpeg($im, $dest, 84);
    imagedestroy($im);
    return (bool) $r;
}

$locs = $pdo->query("SELECT id,name,region,image,blurb FROM tb_points WHERE blurb IS NOT NULL AND blurb<>'' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

admin_header('Location Images', 'loc_images');
flash_show();
$base = rtrim((string) (function_exists('url') ? url('') : ''), '/');
?>
<style>
.locwrap{max-width:960px}
.locgrid{display:grid;grid-template-columns:1fr;gap:16px;margin-top:6px}
.locrow{display:grid;grid-template-columns:200px 1fr;gap:18px;background:#fff;border:1px solid var(--rule);border-radius:10px;padding:16px 18px}
.locrow .thumb{width:200px;height:150px;border-radius:8px;border:1px solid var(--rule);background:#f4efe7 center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:var(--sage);font-size:12px;overflow:hidden}
.locrow .thumb img{width:100%;height:100%;object-fit:cover}
.locrow h3{font-family:'Cormorant Garamond',serif;font-size:22px;margin:0 0 2px}
.locrow .rg{font-size:12px;color:var(--sage);text-transform:uppercase;letter-spacing:.06em}
.locrow textarea{min-height:78px;margin-top:8px}
.locrow .frow{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px}
.locrow input[type=file]{font-size:13px}
@media(max-width:700px){.locrow{grid-template-columns:1fr}.locrow .thumb{width:100%;height:180px}}
</style>
<div class="locwrap">
<h1>Location Images</h1>
<p class="muted">Upload a hero photo and refine the description for each Kerala destination. These feed the AI package PDF — one page per location.</p>
<?php if ($ok): ?><div class="flash"><?= $ok ?></div><?php endif; ?>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<div class="locgrid">
<?php foreach ($locs as $L): $img = (string) ($L['image'] ?? ''); ?>
<form class="locrow" method="post" enctype="multipart/form-data">
<?= function_exists('csrf_field') ? csrf_field() : '' ?>
<input type="hidden" name="id" value="<?= (int) $L['id'] ?>">
<div class="thumb"><?php if ($img): ?><img src="<?= e($base . '/' . $img) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/' . $img) ?: 0 ?>" alt=""><?php else: ?>No image yet<?php endif; ?></div>
<div>
<h3><?= e((string) $L['name']) ?></h3>
<div class="rg"><?= e((string) ($L['region'] ?? '')) ?></div>
<label>Description</label>
<textarea name="blurb"><?= e((string) $L['blurb']) ?></textarea>
<div class="frow">
<input type="file" name="image" accept="image/*">
<button class="btn small" type="submit">Save</button>
<?php if ($img): ?><span class="muted"><?= e($img) ?></span><?php endif; ?>
</div>
</div>
</form>
<?php endforeach; ?>
</div>
</div>
<?php admin_footer();
ENDOFFILE7;
$bak=$t.'.bak'; if(is_file($t)&&!is_file($bak)) @copy($t,$bak);
file_put_contents($t,$s);
$out=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$out,$rc);
if($rc!==0 && is_file($bak)){ @copy($bak,$t); }
file_put_contents($root.'/_dep_p_loc.txt',json_encode(['rc'=>$rc,'out'=>$out,'len'=>strlen($s),'crc'=>hash('crc32b',$s),'restored'=>($rc!==0),'target'=>'admin/location-images.php']));
echo 'DEP';
