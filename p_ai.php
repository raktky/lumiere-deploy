<?php
$root='/var/www/lumiere/experience';
$t="$root/app/ai.php";
$s = <<<'AIEOF'
<?php
/* app/ai.php — Lumiere AI helper (v2: multi-provider Gemini|Anthropic + image reading). Server-side only. */
if (!function_exists('db')) { require_once __DIR__ . '/bootstrap.php'; }

/* single-row config table tb_ai. 'provider' column may be absent on old schema → defaults apply. */
function ai_cfg(): array {
    try { $r = db()->query("SELECT * FROM tb_ai WHERE id=1")->fetch(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { $r = null; }
    return $r ?: ['api_key'=>'','model'=>'','credits'=>0,'profile'=>'','provider'=>'gemini'];
}

/* which LLM backend: 'gemini' (default) or 'anthropic' */
function ai_provider(): string {
    $c = ai_cfg();
    $p = strtolower(trim((string)($c['provider'] ?? 'gemini')));
    return $p === 'anthropic' ? 'anthropic' : 'gemini';
}

/* key: prefer server env for the active provider, else admin-entered value in tb_ai */
function ai_key(): string {
    $prov = ai_provider();
    $env = $prov === 'anthropic' ? getenv('ANTHROPIC_API_KEY') : getenv('GEMINI_API_KEY');
    if ($env) return trim($env);
    $c = ai_cfg();
    return trim((string)($c['api_key'] ?? ''));
}

function ai_model(): string {
    $c = ai_cfg();
    $m = trim((string)($c['model'] ?? ''));
    if ($m !== '') return $m;
    return ai_provider() === 'anthropic' ? 'claude-3-5-sonnet-20241022' : 'gemini-2.5-flash';
}

function ai_configured(): bool { return ai_key() !== ''; }

/* brand instruction fragment injected into every call */
function ai_profile_text(): string {
    $c = ai_cfg();
    $p = json_decode($c['profile'] ?? '', true) ?: [];
    $lines = [
        'AGENCY: Lumiere Holidays' . (!empty($p['tagline']) ? ' — ' . $p['tagline'] : '') . '.',
        'TONE: ' . ($p['tone'] ?? 'warm, premium, concise') . '.',
        !empty($p['inclusions']) ? 'STANDARD INCLUSIONS: ' . $p['inclusions'] : '',
        !empty($p['exclusions']) ? 'STANDARD EXCLUSIONS: ' . $p['exclusions'] : '',
        !empty($p['tnc']) ? 'TERMS & CONDITIONS (append verbatim when asked): ' . $p['tnc'] : '',
        !empty($p['extra']) ? 'EXTRA RULES: ' . $p['extra'] : '',
        'RATE DISCIPLINE: use ONLY the hotels listed by exact name; if a cost is not given, estimate a reasonable INR amount and append " (ESTIMATE)" to that item name.',
    ];
    return implode("\n", array_filter($lines));
}

/* master T&C block for documents */
function ai_tnc(): string {
    $c = ai_cfg();
    $p = json_decode($c['profile'] ?? '', true) ?: [];
    return trim((string)($p['tnc'] ?? ''));
}

/* credit meter — deduct only on success. Returns [ok,left,error] */
function ai_charge(string $op, int $cost = 1): array {
    $c = ai_cfg();
    $have = (int)($c['credits'] ?? 0);
    if ($have < $cost) return ['ok'=>false, 'error'=>"Not enough AI credits (need $cost, have $have). Top up in AI settings."];
    $left = $have - $cost;
    db()->prepare("UPDATE tb_ai SET credits=?, updated_at=NOW() WHERE id=1")->execute([$left]);
    try { db()->prepare("INSERT INTO tb_ai_log (op,delta,balance,meta,created_at) VALUES (?,?,?,?,NOW())")->execute([$op, -$cost, $left, '']); } catch (Throwable $e) {}
    return ['ok'=>true, 'left'=>$left];
}

/* low-level HTTPS POST */
function ai__http(string $url, array $headers, array $body, int $timeout = 90): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['res'=>$res, 'code'=>$code, 'err'=>$err];
}

/**
 * Raw LLM call, provider-aware, multimodal. Throws on error, returns text.
 * @param array $images  [ ['mime'=>'image/jpeg','data'=>'<base64>'], ... ]
 */
function ai_call(string $system, string $user, int $maxTokens = 2000, array $images = []): string {
    $key = ai_key();
    if ($key === '') throw new RuntimeException('AI not configured. Set the API key in AI settings.');
    $prov = ai_provider();
    $model = ai_model();

    if ($prov === 'anthropic') {
        $content = [];
        foreach ($images as $img) {
            if (!empty($img['data'])) {
                $content[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>$img['mime'] ?? 'image/jpeg','data'=>$img['data']]];
            }
        }
        $content[] = ['type'=>'text','text'=>$user];
        $r = ai__http('https://api.anthropic.com/v1/messages',
            ['content-type: application/json','x-api-key: ' . $key,'anthropic-version: 2023-06-01'],
            ['model'=>$model,'max_tokens'=>$maxTokens,'system'=>$system,'messages'=>[['role'=>'user','content'=>$content]]]);
        if ($r['res'] === false) throw new RuntimeException('AI request failed: ' . $r['err']);
        if ($r['code'] !== 200) throw new RuntimeException('AI provider error (' . $r['code'] . '): ' . substr((string)$r['res'], 0, 200));
        $j = json_decode((string)$r['res'], true);
        $text = '';
        foreach (($j['content'] ?? []) as $blk) { $text .= $blk['text'] ?? ''; }
        if ($text === '') throw new RuntimeException('AI returned an empty response.');
        return $text;
    }

    /* Gemini */
    $parts = [['text' => $user]];
    foreach ($images as $img) {
        if (!empty($img['data'])) {
            $parts[] = ['inline_data' => ['mime_type' => $img['mime'] ?? 'image/jpeg', 'data' => $img['data']]];
        }
    }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
    $r = ai__http($url, ['Content-Type: application/json'], [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => [['role' => 'user', 'parts' => $parts]],
        'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => $maxTokens],
    ]);
    if ($r['res'] === false) throw new RuntimeException('AI request failed: ' . $r['err']);
    if ($r['code'] !== 200) {
        $j = json_decode((string)$r['res'], true);
        $m = $j['error']['message'] ?? substr((string)$r['res'], 0, 200);
        throw new RuntimeException('AI provider error (' . $r['code'] . '): ' . $m);
    }
    $j = json_decode((string)$r['res'], true);
    $text = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') throw new RuntimeException('AI returned an empty response.');
    return $text;
}

/* extract first JSON object/array from a model reply */
function ai_json(string $text) {
    if (preg_match('/[\{\[][\s\S]*[\}\]]/', $text, $m)) {
        $p = json_decode($m[0], true);
        if (is_array($p)) return $p;
    }
    return null;
}

/**
 * Read WhatsApp / enquiry screenshots + optional note → structured lead & requirements.
 * Returns an assoc array (best-effort; fields may be empty).
 */
function ai_extract_lead(array $images, string $note = ''): array {
    $sys = "You read a customer's WhatsApp screenshot(s) and/or note for a Kerala travel agency and extract a structured enquiry.\n"
        . "Output STRICT JSON ONLY, no prose. Shape:\n"
        . '{"name":"","phone":"","email":"","destinations":"comma list","nights":0,"adults":0,"children":0,"month_or_dates":"","theme":"honeymoon|family|pilgrimage|adventure|luxury|budget|group|","budget":"","meal":"CP|MAP|AP|","vehicle":"","must_include":"","notes":"anything else","raw":"verbatim text you read"}' . "\n"
        . "If a field is unknown, leave it empty/0. Phone in international form if visible.";
    $user = "Extract the enquiry. Note from agent: " . ($note !== '' ? $note : '(none)');
    $text = ai_call($sys, $user, 1200, $images);
    $j = ai_json($text);
    return is_array($j) ? $j : ['raw' => $text];
}

/* rate/distance context from live tables */
function ai_hotel_context(int $limit = 90): string {
    $rows = db()->query("SELECT location,name,star,price_range FROM tb_build_hotels WHERE active=1 ORDER BY location,name LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    $byLoc = [];
    foreach ($rows as $r) { $byLoc[$r['location']][] = $r['name'] . ' (' . $r['star'] . ', ~INR ' . $r['price_range'] . '/night)'; }
    $out = [];
    foreach ($byLoc as $loc => $hs) { $out[] = $loc . ': ' . implode('; ', array_slice($hs, 0, 8)); }
    return implode("\n", $out);
}
function ai_distance_context(): string {
    $rows = db()->query("SELECT from_loc,to_loc,km FROM tb_distances ORDER BY from_loc LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    $seen = []; $out = [];
    foreach ($rows as $r) {
        $k = $r['from_loc'] . '|' . $r['to_loc']; $rk = $r['to_loc'] . '|' . $r['from_loc'];
        if (isset($seen[$rk])) continue; $seen[$k] = 1;
        $out[] = $r['from_loc'] . '-' . $r['to_loc'] . ':' . $r['km'] . 'km';
    }
    return implode(', ', array_slice($out, 0, 120));
}

/* main: prompt (+optional images) -> itinerary array matching quote.php shape:
   [{day:int,title,hotel,meal,transport,items:[{n,c}]}] */
function ai_generate_itinerary(string $prompt, int $nights, int $adults, int $children, string $style, array $images = []): array {
    $sys = "You are a senior Kerala travel agent building a day-by-day itinerary.\n"
        . ai_profile_text() . "\n\n"
        . "Output STRICT JSON ONLY, no prose, no markdown fences. Shape:\n"
        . '{"destination":"...","nights":N,"days":[{"day":1,"title":"...","hotel":"EXACT hotel name from list or empty","meal":"EP|CP|MAP|AP","transport":"route e.g. Kochi -> Munnar","items":[{"n":"activity name","c":1200}]}]}' . "\n"
        . "Rules: one object per day, day count = nights + optional checkout day. Costs are net INR numbers only. Use hotels ONLY from HOTELS list by exact name. Keep 2-4 items per day.\n\n"
        . "HOTELS:\n" . ai_hotel_context() . "\n\n"
        . "DISTANCES (km):\n" . ai_distance_context();
    $user = "Plan a {$nights}-night trip. Brief: " . substr($prompt, 0, 1500)
        . ". Travellers: {$adults} adults, {$children} children. Style/budget: {$style}."
        . ($images ? " Additional details are in the attached screenshot(s); read them." : "");
    $text = ai_call($sys, $user, 2600, $images);
    $parsed = ai_json($text);
    if (!is_array($parsed) || empty($parsed['days'])) throw new RuntimeException('Could not parse AI itinerary.');
    $days = [];
    foreach ($parsed['days'] as $i => $d) {
        $items = [];
        foreach (($d['items'] ?? []) as $it) {
            $items[] = ['n' => (string)($it['n'] ?? $it['name'] ?? ''), 'c' => (float)($it['c'] ?? $it['cost'] ?? 0)];
        }
        $days[] = [
            'day' => (int)($d['day'] ?? $i + 1),
            'title' => (string)($d['title'] ?? ''),
            'hotel' => (string)($d['hotel'] ?? ''),
            'meal' => (string)($d['meal'] ?? 'CP'),
            'transport' => (string)($d['transport'] ?? ''),
            'items' => $items,
        ];
    }
    return ['destination' => (string)($parsed['destination'] ?? ''), 'nights' => (int)($parsed['nights'] ?? $nights), 'days' => $days];
}
AIEOF;
$bak="$t.bak"; if(is_file($t)&&!is_file($bak)) @copy($t,$bak);
file_put_contents($t,$s);
$out=[]; $rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$out,$rc);
if($rc!==0 && is_file($bak)){ @copy($bak,$t); }
file_put_contents("$root/_ai_deploy.txt",json_encode(['rc'=>$rc,'out'=>$out,'len'=>strlen($s),'restored'=>($rc!==0)]));
echo 'AIW';
