<?php
$root='/var/www/lumiere/experience';
$t=$root.'/admin/ai-package.php';
@mkdir(dirname($t),0775,true);
$s = <<<'ENDOFFILE7'
<?php
declare(strict_types=1);
/* admin/ai-package.php — AI Package Builder.
   WhatsApp screenshot / pasted text / existing lead -> AI reads contact + trip -> team confirms ->
   AI writes itinerary -> PHP prices from the rate bank (AI never sets price) -> saved as a DRAFT quote -> green PDF. */
require __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/ai.php';

$pdo = db();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

/* ---- collect uploaded screenshots into [mime,data] for the vision model ---- */
function collect_images(): array {
    $imgs = [];
    if (!empty($_FILES['shots']) && is_array($_FILES['shots']['tmp_name'])) {
        foreach ($_FILES['shots']['tmp_name'] as $i => $tmp) {
            if (!$tmp || !is_uploaded_file($tmp)) continue;
            if ((int)($_FILES['shots']['size'][$i] ?? 0) > 12*1024*1024) continue;
            $info = @getimagesize($tmp);
            if (!$info) continue;
            $mime = $info['mime'] ?? 'image/jpeg';
            $imgs[] = ['mime'=>$mime, 'data'=>base64_encode((string)file_get_contents($tmp))];
        }
    }
    return $imgs;
}

/* ---- compact route distance (mirrors quote.php) ---- */
function route_km(PDO $pdo, array $stops): int {
    static $DIST=null, $NEAR=null;
    if ($DIST===null) {
        $DIST=[];
        foreach ($pdo->query('SELECT from_loc,to_loc,km FROM tb_distances') as $d) {
            $DIST[strtolower($d['from_loc']).'|'.strtolower($d['to_loc'])] = (int)$d['km'];
        }
        $NEAR=['fort kochi'=>'kochi','ernakulam'=>'kochi','cochin'=>'kochi','kochi'=>'kochi','nedumbassery'=>'kochi',
        'marari'=>'alleppey','mararikulam'=>'alleppey','alappuzha'=>'alleppey','alleppey'=>'alleppey',
        'kumarakom'=>'kumarakom','kottayam'=>'kumarakom','munnar'=>'munnar','thekkady'=>'thekkady','periyar'=>'thekkady',
        'wayanad'=>'wayanad','kalpetta'=>'wayanad','kovalam'=>'trivandrum','trivandrum'=>'trivandrum',
        'thiruvananthapuram'=>'trivandrum','kozhikode'=>'kozhikode','calicut'=>'kozhikode'];
    }
    $node = function(string $s) use ($NEAR): string {
        $s = strtolower(trim($s));
        foreach ($NEAR as $k=>$v) { if ($k!=='' && strpos($s,$k)!==false) return $v; }
        return $s;
    };
    $leg = function(string $a,string $b) use ($DIST,$node): int {
        $a=$node($a); $b=$node($b);
        if ($a===$b) return 35;
        return $DIST[$a.'|'.$b] ?? $DIST[$b.'|'.$a] ?? 120;
    };
    $stops = array_values(array_filter(array_map('trim',$stops)));
    $km=0; $n=count($stops);
    for ($i=0;$i<$n-1;$i++){ $km += $leg($stops[$i],$stops[$i+1]); }
    if ($n>=2){ $km += $leg($stops[$n-1],$stops[0]); }
    return $km;
}

/* ---- price an AI itinerary from the live rate bank ---- */
function price_package(PDO $pdo, array $days, string $travelDate, int $km, string $vehicle): array {
    // hotels: sum sell_rate for each day that names a hotel (one night each)
    $hstmt = $pdo->prepare("SELECT sell_rate FROM tb_hotel_rates WHERE active=1 AND hotel=?
        AND (period_from IS NULL OR period_from<=?) AND (period_to IS NULL OR period_to>=?)
        ORDER BY id DESC LIMIT 1");
    $hraw = $pdo->query("SELECT hotel, MAX(sell_rate) sr FROM tb_hotel_rates WHERE active=1 GROUP BY hotel")->fetchAll(PDO::FETCH_KEY_PAIR);
    $hmap = []; foreach ($hraw as $k=>$v){ $hmap[strtolower(trim((string)$k))] = (float)$v; }
    $hotelSell = 0.0; $hotelLines = [];
    foreach ($days as $d) {
        $hn = trim((string)($d['hotel'] ?? ''));
        if ($hn==='') continue;
        $sr = 0.0;
        try { $hstmt->execute([$hn,$travelDate,$travelDate]); if ($r=$hstmt->fetch()) $sr=(float)$r['sell_rate']; } catch (Throwable $e) {}
        if ($sr<=0) { $sr = $hmap[strtolower($hn)] ?? 0.0; }
        $hotelSell += $sr;
        $hotelLines[] = ['hotel'=>$hn,'night'=>1,'sell'=>$sr];
    }
    // vehicle
    $vr=['base_km'=>0,'base_amount'=>0.0,'extra_per_km'=>0.0];
    try {
        $vs=$pdo->prepare("SELECT base_km,base_amount,extra_per_km FROM tb_vehicle_rates WHERE active=1 AND vehicle_model=?
            AND (period_from IS NULL OR period_from<=?) AND (period_to IS NULL OR period_to>=?) ORDER BY id DESC LIMIT 1");
        $vs->execute([$vehicle,$travelDate,$travelDate]);
        if ($r=$vs->fetch()) $vr=['base_km'=>(int)$r['base_km'],'base_amount'=>(float)$r['base_amount'],'extra_per_km'=>(float)$r['extra_per_km']];
    } catch (Throwable $e) {}
    $vehicleCost = (float)$vr['base_amount'] + max(0,$km-(int)$vr['base_km'])*(float)$vr['extra_per_km'];
    // activities
    $items=0.0;
    foreach ($days as $d) { foreach (($d['items'] ?? []) as $it){ $items += (float)($it['c'] ?? 0); } }
    $net = $hotelSell + $vehicleCost + $items;
    return ['hotel_sell'=>$hotelSell,'vehicle_cost'=>$vehicleCost,'items'=>$items,'net'=>$net,
            'hotel_lines'=>$hotelLines,'km'=>$km,'vehicle'=>$vehicle];
}

$vehicles = $pdo->query("SELECT model FROM tb_vehicles WHERE active=1 ORDER BY category,sort,model")->fetchAll(PDO::FETCH_COLUMN);
$leads = [];
try { $leads = $pdo->query("SELECT id,name,phone,regions,nights,adults,children,start_date FROM trip_requests ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$configured = ai_configured();
$provider = ai_provider();
$flash=''; $error=''; $lead=null; $result=null; $draft=null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    try {
        if (!$configured && $action==='generate') throw new RuntimeException('AI is not configured. Add a '.$provider.' API key in AI settings first.');

        if ($action==='extract') {
            $leadId=(int)($_POST['lead_id'] ?? 0);
            if ($leadId>0) {
                $s=$pdo->prepare("SELECT * FROM trip_requests WHERE id=?"); $s->execute([$leadId]); $tr=$s->fetch(PDO::FETCH_ASSOC);
                if ($tr) {
                    $lead=['name'=>$tr['name'],'phone'=>$tr['phone'],'email'=>$tr['email'],
                        'destinations'=>$tr['regions'],'nights'=>(int)$tr['nights'],'adults'=>(int)$tr['adults'],
                        'children'=>(int)$tr['children'],'month_or_dates'=>(string)$tr['start_date'],
                        'theme'=>$tr['style'] ?: $tr['occasion'],'meal'=>$tr['meal_plan'],'budget'=>'',
                        'must_include'=>'','notes'=>(string)$tr['notes'],'pickup'=>(string)$tr['pickup_point'],'drop'=>(string)$tr['drop_point'],'lead_id'=>$leadId];
                }
            } else {
                $imgs = collect_images();
                $note = trim((string)($_POST['paste'] ?? ''));
                if (!$imgs && $note==='') throw new RuntimeException('Add a screenshot or paste the enquiry text first.');
                if (!$configured) throw new RuntimeException('AI is not configured. Add a '.$provider.' API key in AI settings to read screenshots.');
                $lead = ai_extract_lead($imgs, $note);
                ai_charge('AI_EXTRACT', 1);
            }
            if (!$lead) throw new RuntimeException('Could not read that. Try a clearer screenshot or type the details.');
        }

        if ($action==='generate') {
            $name=trim((string)($_POST['name'] ?? '')); $phone=trim((string)($_POST['phone'] ?? '')); $email=trim((string)($_POST['email'] ?? ''));
            $dest=trim((string)($_POST['destinations'] ?? '')); $nights=max(1,(int)($_POST['nights'] ?? 3));
            $adults=max(1,(int)($_POST['adults'] ?? 2)); $children=max(0,(int)($_POST['children'] ?? 0));
            $dates=trim((string)($_POST['month_or_dates'] ?? '')); $theme=trim((string)($_POST['theme'] ?? 'family'));
            $meal=trim((string)($_POST['meal'] ?? 'CP')); $budget=trim((string)($_POST['budget'] ?? ''));
            $must=trim((string)($_POST['must_include'] ?? '')); $notes=trim((string)($_POST['notes'] ?? ''));
            $pickup=trim((string)($_POST['pickup'] ?? '')); $drop=trim((string)($_POST['drop'] ?? ''));
            $vehicle=trim((string)($_POST['vehicle'] ?? ($vehicles[0] ?? '')));
            $leadId=(int)($_POST['lead_id'] ?? 0);
            if ($dest==='') throw new RuntimeException('Destinations are required.');

            $travelDate = date('Y-m-d');
            if ($dates!=='' && ($ts=strtotime($dates))!==false) { $travelDate=date('Y-m-d',$ts); }

            // create / update the trip request (keeps it in CRM)
            if ($leadId>0) {
                $pdo->prepare("UPDATE trip_requests SET name=?,phone=?,email=?,regions=?,nights=?,adults=?,children=?,style=?,meal_plan=?,pickup_point=?,drop_point=? WHERE id=?")
                    ->execute([$name,$phone,$email,$dest,$nights,$adults,$children,$theme,$meal,$pickup,$drop,$leadId]);
                $tripId=$leadId;
            } else {
                $pdo->prepare("INSERT INTO trip_requests (occasion,adults,children,nights,start_date,regions,style,name,email,phone,notes,created_at,handled,pickup_point,drop_point,meal_plan,source_package)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),0,?,?,?,?)")
                    ->execute([$theme,$adults,$children,$nights,($travelDate ?: null),$dest,$theme,$name,$email,$phone,$notes,$pickup,$drop,$meal,'AI Builder']);
                $tripId=(int)$pdo->lastInsertId();
            }

            // AI writes the itinerary; PHP will price it
            $brief = "Client: $name. Destinations: $dest. Theme: $theme. Meal plan: $meal. Budget hint: $budget. Must include: $must. Notes: $notes. Pickup: $pickup. Drop: $drop.";
            $itin = ai_generate_itinerary($brief, $nights, $adults, $children, $theme);
            ai_charge('AI_PACKAGE', 1);
            $days = $itin['days'] ?? [];
            if (!$days) throw new RuntimeException('AI did not return an itinerary. Try again.');

            $stops = array_merge($pickup!==''?[$pickup]:[], array_map('trim', explode(',', $dest)), $drop!==''?[$drop]:[]);
            $km = route_km($pdo, $stops);
            $price = price_package($pdo, $days, $travelDate, $km, $vehicle);
            $title = ($itin['destination'] ?: $dest) . ' — ' . $nights . 'N ' . ucfirst($theme);

            $breakdown = json_encode(['hotels'=>$price['hotel_lines'],'vehicle'=>['model'=>$vehicle,'cost'=>$price['vehicle_cost'],'km'=>$km],'items'=>$price['items'],'net'=>$price['net']], JSON_UNESCAPED_UNICODE);
            $itinJson = json_encode($days, JSON_UNESCAPED_UNICODE);
            $token = bin2hex(random_bytes(8));
            $by = (function_exists('admin_user') && admin_user() ? (string)(admin_user()['name'] ?? 'admin') : 'admin');

            $pdo->prepare("INSERT INTO tb_quotes (trip_request_id,total_km,vehicle_model,vehicle_cost,hotel_cost,hotel_sell,margin,customer_price,breakdown,itinerary,title,status,token,updated_by,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE total_km=VALUES(total_km),vehicle_model=VALUES(vehicle_model),vehicle_cost=VALUES(vehicle_cost),
                hotel_sell=VALUES(hotel_sell),customer_price=VALUES(customer_price),breakdown=VALUES(breakdown),itinerary=VALUES(itinerary),
                title=VALUES(title),status=VALUES(status),updated_by=VALUES(updated_by),updated_at=NOW()")
                ->execute([$tripId,$km,$vehicle,$price['vehicle_cost'],0,$price['hotel_sell'],0,$price['net'],$breakdown,$itinJson,$title,'DRAFT',$token,$by]);
            // ensure a token exists (ON DUP won't overwrite an existing one above only if we didn't pass it; we always pass token on insert)
            $tok = $pdo->query("SELECT token FROM tb_quotes WHERE trip_request_id=".(int)$tripId)->fetchColumn();
            if (!$tok) { $pdo->prepare("UPDATE tb_quotes SET token=? WHERE trip_request_id=?")->execute([$token,$tripId]); $tok=$token; }

            $gst = (int)round($price['net']*1.05);
            $result = ['days'=>$days,'title'=>$title,'net'=>$price['net'],'gst'=>$gst,'perPerson'=>(int)round($gst/max(1,$adults+$children)),'price'=>$price];
            $draft = ['trip_id'=>$tripId,'token'=>$tok,'nights'=>$nights];
            $flash = 'Draft package created and priced from your rate bank.';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
    // keep the confirm form populated after generate errors / show
    if ($action==='generate' && !$lead) {
        $lead = ['name'=>$_POST['name']??'','phone'=>$_POST['phone']??'','email'=>$_POST['email']??'','destinations'=>$_POST['destinations']??'',
            'nights'=>(int)($_POST['nights']??3),'adults'=>(int)($_POST['adults']??2),'children'=>(int)($_POST['children']??0),
            'month_or_dates'=>$_POST['month_or_dates']??'','theme'=>$_POST['theme']??'family','meal'=>$_POST['meal']??'CP',
            'budget'=>$_POST['budget']??'','must_include'=>$_POST['must_include']??'','notes'=>$_POST['notes']??'',
            'pickup'=>$_POST['pickup']??'','drop'=>$_POST['drop']??'','lead_id'=>(int)($_POST['lead_id']??0),'vehicle'=>$_POST['vehicle']??''];
    }
}

$base = rtrim((string)(function_exists('url') ? url('') : ''), '/');
$themes = ['family','honeymoon','pilgrimage','adventure','luxury','budget','group'];
$meals = ['CP','MAP','AP','EP'];
?><!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AI Package Builder · Lumiere Admin</title>
<style>
:root{--green:#10281f;--green2:#183a2a;--yel:#f0d048;--bg:#f7f4ee;--card:#fff;--ink:#26302a;--mut:#7f8b83;--line:#e5e0d5;--danger:#b5544a}
*{box-sizing:border-box}body{margin:0;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink);font-size:14px}
.top{background:var(--green);color:#eef4ef;padding:13px 22px;display:flex;align-items:center;gap:14px}
.top b{font-weight:600;letter-spacing:.02em}.top .sp{flex:1}
.top a{color:#dfe9e0;text-decoration:none;font-size:13px;border:1px solid #2f5142;padding:6px 12px;border-radius:8px}
.top .chip{background:var(--yel);color:#3a2f07;border-radius:20px;padding:3px 12px;font-size:12px;font-weight:700}
.wrap{max-width:1000px;margin:18px auto;padding:0 18px}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;margin-bottom:16px;overflow:hidden}
.card .hd{padding:12px 16px;border-bottom:1px solid var(--line);font-weight:600;background:#fbfaf6}
.card .hd .n{display:inline-flex;width:22px;height:22px;border-radius:50%;background:var(--green);color:var(--yel);align-items:center;justify-content:center;font-size:12px;margin-right:8px}
.card .bd{padding:16px}
label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--mut);margin:0 0 4px;font-weight:600}
input,select,textarea{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:8px;font-size:13px;background:#fdfcfa;font-family:inherit}
textarea{min-height:70px;resize:vertical}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.mt{margin-top:12px}
.btn{background:var(--green);color:#fff;border:0;padding:11px 20px;border-radius:9px;font-weight:600;cursor:pointer;font-size:13px}
.btn:hover{background:var(--green2)}
.btn.gold{background:var(--yel);color:#3a2f07}
.btn.sec{background:#fff;border:1px solid var(--line);color:var(--green)}
.ai{border-color:var(--yel);box-shadow:0 0 0 2px rgba(240,208,72,.25)}
.flash{background:#eaf5ee;border:1px solid #bfe0cd;color:#245c3f;padding:11px 14px;border-radius:9px;margin-bottom:14px}
.err{background:#fbecea;border:1px solid #f0c4bf;color:#8f362d;padding:11px 14px;border-radius:9px;margin-bottom:14px}
.hint{font-size:12px;color:var(--mut);margin-top:5px}
.day{border:1px solid var(--line);border-radius:10px;margin-bottom:10px;overflow:hidden}
.day .dh{background:#f4f7f4;padding:8px 13px;font-weight:600;display:flex;gap:10px;align-items:center}
.day .dh .n{width:22px;height:22px;border-radius:50%;background:var(--green);color:var(--yel);display:flex;align-items:center;justify-content:center;font-size:12px}
.day .db{padding:9px 13px;font-size:13px}
.day .meta{color:var(--mut);font-size:12px;margin-bottom:6px}
.it{display:flex;justify-content:space-between;border-bottom:1px dashed var(--line);padding:4px 0}
.tot{background:var(--green);color:#eef4ef;border-radius:12px;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.tot .big{font-size:26px;font-weight:700;color:var(--yel)}
.pill{display:inline-block;background:#eef4ef;color:var(--green);border:1px solid var(--line);border-radius:20px;padding:3px 11px;font-size:12px;font-weight:700}
.tabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.tabs button{background:#fff;border:1px solid var(--line);border-radius:20px;padding:7px 14px;font-size:13px;cursor:pointer;width:auto}
.tabs button.on{background:var(--green);color:#fff;border-color:var(--green)}
.pane{display:none}.pane.on{display:block}
</style></head><body>
<div class="top"><b>Lumiere · AI Package Builder</b>
<span class="chip"><?=h(ucfirst($provider))?><?=$configured?'':' · no key'?></span>
<span class="sp"></span>
<a href="ai-plan.php">AI Planner</a><a href="location-images.php">Location Images</a><a href="index.php">Admin home</a></div>
<div class="wrap">
<?php if($flash):?><div class="flash"><?=h($flash)?></div><?php endif;?>
<?php if($error):?><div class="err"><?=h($error)?></div><?php endif;?>
<?php if(!$configured):?><div class="err">No <?=h($provider)?> API key set. Add it in <a href="ai-plan.php">AI settings</a> before generating.</div><?php endif;?>

<?php if(!$result): ?>
<!-- STEP 1: capture -->
<div class="card"><div class="hd"><span class="n">1</span>Capture the enquiry</div><div class="bd">
<div class="tabs">
<button type="button" class="tb on" data-p="p_shot">WhatsApp screenshot</button>
<button type="button" class="tb" data-p="p_text">Paste / type</button>
<?php if($leads):?><button type="button" class="tb" data-p="p_lead">Pick a saved lead</button><?php endif;?>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="extract">
<div class="pane on" id="p_shot">
<label>Upload WhatsApp screenshot(s)</label>
<input type="file" name="shots[]" accept="image/*" multiple>
<div class="hint">AI reads the name, phone and trip details straight from the chat image(s).</div>
</div>
<div class="pane" id="p_text">
<label>Paste or type the enquiry</label>
<textarea name="paste" placeholder="e.g. Hi, this is Sunita. We are 2 adults + 1 kid, want Munnar and Alleppey for 4 nights in December, houseboat please. Budget around 40k."></textarea>
</div>
<div class="pane" id="p_lead">
<label>Existing lead</label>
<select name="lead_id">
<option value="0">— choose —</option>
<?php foreach($leads as $ld):?><option value="<?=(int)$ld['id']?>"><?=h($ld['name'].' · '.$ld['phone'].' · '.$ld['regions'].' · '.$ld['nights'].'N')?></option><?php endforeach;?>
</select>
</div>
<div class="mt"><button class="btn gold" <?=$configured?'':''?>>Read &amp; prefill →</button></div>
</form>
</div></div>
<?php endif; ?>

<?php if($lead && !$result): ?>
<!-- STEP 2: confirm + specifics -->
<div class="card"><div class="hd"><span class="n">2</span>Confirm details &amp; trip specifics</div><div class="bd">
<form method="post">
<input type="hidden" name="action" value="generate">
<input type="hidden" name="lead_id" value="<?=(int)($lead['lead_id']??0)?>">
<div class="grid3">
<div><label>Name</label><input class="ai" name="name" value="<?=h($lead['name']??'')?>"></div>
<div><label>Phone</label><input class="ai" name="phone" value="<?=h($lead['phone']??'')?>"></div>
<div><label>Email</label><input class="ai" name="email" value="<?=h($lead['email']??'')?>"></div>
</div>
<div class="mt"><label>Destinations (comma separated, in travel order)</label><input class="ai" name="destinations" value="<?=h($lead['destinations']??'')?>" placeholder="Munnar, Thekkady, Alleppey"></div>
<div class="grid4 mt">
<div><label>Nights</label><input type="number" name="nights" value="<?=h((string)($lead['nights']??5))?>" min="1"></div>
<div><label>Adults</label><input type="number" name="adults" value="<?=h((string)($lead['adults']??2))?>" min="1"></div>
<div><label>Children</label><input type="number" name="children" value="<?=h((string)($lead['children']??0))?>" min="0"></div>
<div><label>Month / dates</label><input name="month_or_dates" value="<?=h($lead['month_or_dates']??'')?>"></div>
</div>
<div class="grid4 mt">
<div><label>Theme</label><select name="theme"><?php foreach($themes as $t):?><option <?=(($lead['theme']??'')===$t)?'selected':''?>><?=$t?></option><?php endforeach;?></select></div>
<div><label>Meal plan</label><select name="meal"><?php foreach($meals as $m):?><option <?=(($lead['meal']??'')===$m)?'selected':''?>><?=$m?></option><?php endforeach;?></select></div>
<div><label>Vehicle</label><select name="vehicle"><?php foreach($vehicles as $vm):?><option <?=(($lead['vehicle']??'')===$vm)?'selected':''?>><?=h($vm)?></option><?php endforeach;?></select></div>
<div><label>Budget hint</label><input name="budget" value="<?=h($lead['budget']??'')?>"></div>
</div>
<div class="grid2 mt">
<div><label>Pickup</label><input name="pickup" value="<?=h($lead['pickup']??'')?>" placeholder="Cochin Airport"></div>
<div><label>Drop</label><input name="drop" value="<?=h($lead['drop']??'')?>" placeholder="Trivandrum Airport"></div>
</div>
<div class="mt"><label>Must include</label><input name="must_include" value="<?=h($lead['must_include']??'')?>"></div>
<div class="mt"><label>Notes</label><textarea name="notes"><?=h($lead['notes']??'')?></textarea></div>
<div class="hint">Gold-outlined fields were filled by AI — check them. Price is computed by the system from your rate bank; AI only writes the plan.</div>
<div class="mt"><button class="btn" <?=$configured?'':'disabled'?>>Generate package (draft) →</button></div>
</form>
</div></div>
<?php endif; ?>

<?php if($result): ?>
<!-- STEP 3: draft preview -->
<div class="card"><div class="hd"><span class="n">3</span><?=h($result['title'])?> <span class="pill">DRAFT</span></div><div class="bd">
<?php foreach($result['days'] as $d): ?>
<div class="day"><div class="dh"><span class="n"><?=(int)$d['day']?></span><?=h($d['title'])?></div><div class="db">
<div class="meta"><?=h($d['hotel']?:'—')?> · <?=h($d['meal'])?> <?=$d['transport']?'· '.h($d['transport']):''?></div>
<?php foreach(($d['items']??[]) as $it): $est=strpos((string)$it['n'],'(ESTIMATE)')!==false; ?>
<div class="it"><span><?=h($it['n'])?></span><span><?=$est?'<em style="color:#b5544a;font-size:11px">est</em>':''?></span></div>
<?php endforeach; ?>
</div></div>
<?php endforeach; ?>
<div class="tot"><div><div style="font-size:12px;opacity:.85">Total incl. 5% GST</div><div class="big">₹<?=number_format($result['gst'])?></div><div style="font-size:12px;opacity:.85">≈ ₹<?=number_format($result['perPerson'])?> per person</div></div>
<div style="text-align:right">
<a class="btn gold" href="package-pdf.php?t=<?=h($draft['token'])?>" target="_blank" style="text-decoration:none">Download PDF →</a><br>
<a href="quote.php?id=<?=(int)$draft['trip_id']?>" style="color:#dfe9e0;font-size:12px">Refine in Quote Builder</a>
</div></div>
<div class="hint" style="margin-top:10px">Saved as a <b>draft</b> — nothing is sent to the customer. The PDF uses your branded green layout with one page per destination. Rate breakdown: hotels ₹<?=number_format($result['price']['hotel_sell'])?> + vehicle ₹<?=number_format($result['price']['vehicle_cost'])?> (<?=$result['price']['km']?>km) + activities ₹<?=number_format($result['price']['items'])?>.</div>
<div class="mt"><a class="btn sec" href="ai-package.php" style="text-decoration:none">+ New package</a></div>
</div></div>
<?php endif; ?>
</div>
<script>
document.querySelectorAll('.tb').forEach(function(b){b.addEventListener('click',function(){
  document.querySelectorAll('.tb').forEach(function(x){x.classList.remove('on');});
  document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on');});
  b.classList.add('on'); var p=document.getElementById(b.dataset.p); if(p)p.classList.add('on');
});});
</script>
</body></html>
ENDOFFILE7;
$bak=$t.'.bak'; if(is_file($t)&&!is_file($bak)) @copy($t,$bak);
file_put_contents($t,$s);
$out=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$out,$rc);
if($rc!==0 && is_file($bak)){ @copy($bak,$t); }
file_put_contents($root.'/_dep_p_aipkg.txt',json_encode(['rc'=>$rc,'out'=>$out,'len'=>strlen($s),'crc'=>hash('crc32b',$s),'restored'=>($rc!==0),'target'=>'admin/ai-package.php']));
echo 'DEP';
