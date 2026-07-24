<?php
/* Phase A: backend control + data. Adds tb_vehicles, houseboat type, seeds them,
   and registers Hotels/Vehicles/Points/Distances as admin-editable types. Idempotent. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$H=getenv('DB_HOST')?:"localhost";$N=getenv('DB_NAME')?:"u406847900_lumiere_exp";$U=getenv('DB_USER')?:"u406847900_lumiere_exp";$P=getenv('DB_PASS')?:"";
try{ $pdo=new PDO("mysql:host=$H;dbname=$N;charset=utf8mb4",$U,$P,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); }
catch(Throwable $e){ die("DB FAIL: ".$e->getMessage()."\n"); }
function say($m){ echo $m."\n"; }

/* 1. schema: type column on tb_build_hotels */
$has=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tb_build_hotels' AND column_name='type'")->fetchColumn();
if(!$has){ $pdo->exec("ALTER TABLE tb_build_hotels ADD COLUMN type VARCHAR(40) NOT NULL DEFAULT 'Hotel'"); say("added tb_build_hotels.type"); } else say("type column exists");

/* 2. tb_vehicles */
$pdo->exec("CREATE TABLE IF NOT EXISTS tb_vehicles (id INT AUTO_INCREMENT PRIMARY KEY, category VARCHAR(60) NOT NULL, model VARCHAR(120) NOT NULL, active TINYINT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0, UNIQUE KEY uniq_cm (category,model)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$veh=[
 ['Sedan','Swift Dzire',1],['Sedan','Honda Amaze',2],['Sedan','Toyota Etios',3],
 ['SUV','Maruti Ertiga',1],['SUV','Toyota Innova',2],['SUV','Toyota Innova Crysta',3],
 ['Large Vehicle','Tempo Traveller (12-seater)',1],['Large Vehicle','Tempo Traveller (17-seater)',2],['Large Vehicle','Force Urbania',3],
];
$vi=$pdo->prepare("INSERT IGNORE INTO tb_vehicles (category,model,active,sort) VALUES (?,?,1,?)");
$vn=0; foreach($veh as $v){ $vi->execute($v); $vn+=$vi->rowCount(); }
say("vehicles inserted: $vn");

/* 3. houseboats (tb_build_hotels type=Houseboat) for Alleppey + Kumarakom */
$hb=[
 ['Alleppey','Standard 1-Bedroom Houseboat',3,'1 Bedroom AC','8,000-12,000'],
 ['Alleppey','Deluxe 1-Bedroom Houseboat',4,'1 Bedroom Deluxe','12,000-18,000'],
 ['Alleppey','Premium 2-Bedroom Houseboat',4,'2 Bedroom','18,000-28,000'],
 ['Alleppey','Luxury Houseboat (Upper Deck)',5,'1-2 Bedroom Luxury','25,000-45,000'],
 ['Kumarakom','Deluxe 1-Bedroom Houseboat',4,'1 Bedroom Deluxe','12,000-18,000'],
 ['Kumarakom','Premium 2-Bedroom Houseboat',4,'2 Bedroom','18,000-28,000'],
 ['Kumarakom','Luxury Houseboat',5,'Luxury Suite','25,000-45,000'],
];
$hi=$pdo->prepare("INSERT INTO tb_build_hotels (location,name,star,room_categories,price_range,address,contact,type,active,sort) SELECT ?,?,?,?,?,'','','Houseboat',1,900 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_build_hotels WHERE location=? AND name=?)");
$hn=0; foreach($hb as $h){ $hi->execute([$h[0],$h[1],$h[2],$h[3],$h[4],$h[0],$h[1]]); $hn+=$hi->rowCount(); }
say("houseboats inserted: $hn");

/* 4. register admin types (inject before 'trip_requests') */
$roots=["/var/www/lumiere/experience","/var/www/lumiere","/var/www/html/experience","/var/www/html"];
$root="";foreach($roots as $c){ if(is_file("$c/app/admin_types.php")){ $root=$c; break; } }
if(!$root){ say("admin_types.php not found - skipped admin registration"); }
else{
  $f="$root/app/admin_types.php"; $s=file_get_contents($f);
  if(strpos($s,"'build_hotels'")!==false){ say("admin types already registered"); }
  else{
    $block = <<<'BLK'
        'build_hotels' => [
            'label' => 'Trip Builder — Hotels', 'singular' => 'hotel', 'table' => 'tb_build_hotels',
            'title_col' => 'name', 'order' => 'location ASC, star DESC, name ASC',
            'list_cols' => ['location','name','star','type','active'], 'toggle' => 'active',
            'fields' => [
                'location' => ['label' => 'Location', 'type' => 'text', 'required' => true, 'help' => 'Must match a builder place, e.g. Munnar, Alleppey, Kumarakom'],
                'name' => ['label' => 'Hotel / Houseboat name', 'type' => 'text', 'required' => true],
                'star' => ['label' => 'Star tier', 'type' => 'select', 'options' => ['3','4','5'], 'help' => '3 = Comfort, 4 = Premium, 5 = Luxury'],
                'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['Hotel','Houseboat']],
                'room_categories' => ['label' => 'Room categories', 'type' => 'text'],
                'price_range' => ['label' => 'Price range (INR)', 'type' => 'text'],
                'address' => ['label' => 'Address', 'type' => 'text'],
                'contact' => ['label' => 'Contact', 'type' => 'text'],
                'sort' => ['label' => 'Sort order', 'type' => 'number'],
                'active' => ['label' => 'Active', 'type' => 'checkbox'],
            ],
        ],
        'build_vehicles' => [
            'label' => 'Trip Builder — Vehicles', 'singular' => 'vehicle', 'table' => 'tb_vehicles',
            'title_col' => 'model', 'order' => 'category ASC, sort ASC, model ASC',
            'list_cols' => ['category','model','sort','active'], 'toggle' => 'active',
            'fields' => [
                'category' => ['label' => 'Category', 'type' => 'select', 'options' => ['Sedan','SUV','Large Vehicle']],
                'model' => ['label' => 'Model', 'type' => 'text', 'required' => true, 'help' => 'e.g. Swift Dzire, Innova, Tempo Traveller (12-seater)'],
                'sort' => ['label' => 'Sort order', 'type' => 'number'],
                'active' => ['label' => 'Active', 'type' => 'checkbox'],
            ],
        ],
        'build_points' => [
            'label' => 'Trip Builder — Pickup/Drop', 'singular' => 'point', 'table' => 'tb_points',
            'title_col' => 'name', 'order' => 'sort ASC, id ASC',
            'list_cols' => ['name','sort','active'], 'toggle' => 'active',
            'fields' => [
                'name' => ['label' => 'Point name', 'type' => 'text', 'required' => true],
                'sort' => ['label' => 'Sort order', 'type' => 'number'],
                'active' => ['label' => 'Active', 'type' => 'checkbox'],
            ],
        ],
        'build_distances' => [
            'label' => 'Trip Builder — Distances (km)', 'singular' => 'distance', 'table' => 'tb_distances',
            'title_col' => 'from_loc', 'order' => 'from_loc ASC, to_loc ASC',
            'list_cols' => ['from_loc','to_loc','km'],
            'fields' => [
                'from_loc' => ['label' => 'From', 'type' => 'text', 'required' => true],
                'to_loc' => ['label' => 'To', 'type' => 'text', 'required' => true],
                'km' => ['label' => 'Distance (km)', 'type' => 'number'],
            ],
        ],

BLK;
    $n=0; $s2=str_replace("'trip_requests' => [", $block."        'trip_requests' => [", $s, $n);
    if($n!==1){ say("ADMIN ANCHOR count=$n — NOT patched (safe abort)"); }
    else{ copy($f,$f.".bak.buildtypes"); if(php_check_syntax_str($s2)){ file_put_contents($f,$s2); say("admin types registered (Hotels/Vehicles/Points/Distances)"); } else say("syntax check failed — not written"); }
  }
}
function php_check_syntax_str($code){ $t=tempnam(sys_get_temp_dir(),'chk'); file_put_contents($t,$code); $o=[];$r=0; exec("php -l ".escapeshellarg($t)." 2>&1",$o,$r); unlink($t); return $r===0; }

/* clear cache */
$cc=0; foreach(glob("$root/cache/*.html") as $ff){ @unlink($ff); $cc++; } say("cache cleared: $cc");
say("DONE");
