<?php
/* patch11 (CRM milestone 1): create rate tables + register admin screens. Uses on-disk config.php creds. Idempotent.
   Also removes the public source-dump files (security). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$roots=['/var/www/lumiere/experience','/var/www/lumiere','/var/www/html/experience','/var/www/html'];
$root='';foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("root not found\n"); }

/* SECURITY: nuke public dumps immediately */
foreach(['_srcdump_kx7.txt','_admdump_kx7.txt','_bootdump_kx7.txt'] as $f){ if(is_file("$root/$f")){ @unlink("$root/$f"); echo "removed $f\n"; } }

/* DB: create rate tables */
require_once "$root/app/config.php";
require_once "$root/app/db.php";
try{
  $pdo=db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS tb_vehicle_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_model VARCHAR(120) NOT NULL,
    period_from DATE NULL, period_to DATE NULL,
    base_km INT NOT NULL DEFAULT 100,
    base_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    extra_per_km DECIMAL(8,2) NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    sort INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS tb_hotel_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel VARCHAR(200) NOT NULL,
    location VARCHAR(120) NULL,
    room VARCHAR(80) NULL,
    meal VARCHAR(20) NULL,
    period_from DATE NULL, period_to DATE NULL,
    cost_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    sell_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    sort INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "rate tables ready\n";
}catch(Throwable $e){ echo "DB error: ".$e->getMessage()."\n"; }

/* ADMIN: register the two rate screens */
$adm="$root/app/admin_types.php";
if(is_file($adm)){
  $a=file_get_contents($adm);
  if(strpos($a,"'rate_vehicle' => [")!==false){ echo "admin already has rate screens\n"; }
  else{
    $anchor="'trip_requests' => [";
    $ins=<<<'PHP'
'rate_vehicle' => [
'label' => 'Rates - Vehicles', 'singular' => 'vehicle rate', 'table' => 'tb_vehicle_rates',
'title_col' => 'vehicle_model', 'order' => 'vehicle_model ASC, period_from ASC',
'list_cols' => ['vehicle_model','period_from','period_to','base_km','base_amount','extra_per_km','active'], 'toggle' => 'active',
'fields' => [
'vehicle_model' => ['label' => 'Vehicle model', 'type' => 'text', 'required' => true, 'help' => 'Match a builder model, e.g. Swift Dzire, Innova, Tempo Traveller (12-seater)'],
'period_from' => ['label' => 'Season from (YYYY-MM-DD)', 'type' => 'text', 'help' => 'Blank = applies always.'],
'period_to' => ['label' => 'Season to (YYYY-MM-DD)', 'type' => 'text', 'help' => 'Blank = applies always.'],
'base_km' => ['label' => 'Base km (e.g. 100 or 150)', 'type' => 'number'],
'base_amount' => ['label' => 'Base amount for base km (INR)', 'type' => 'number'],
'extra_per_km' => ['label' => 'Extra per km beyond base (INR)', 'type' => 'number'],
'sort' => ['label' => 'Sort order', 'type' => 'number'],
'active' => ['label' => 'Active', 'type' => 'checkbox'],
],
],
'rate_hotel' => [
'label' => 'Rates - Hotels', 'singular' => 'hotel rate', 'table' => 'tb_hotel_rates',
'title_col' => 'hotel', 'order' => 'hotel ASC, period_from ASC',
'list_cols' => ['hotel','location','room','meal','period_from','period_to','cost_rate','sell_rate','active'], 'toggle' => 'active',
'fields' => [
'hotel' => ['label' => 'Hotel / Houseboat name', 'type' => 'text', 'required' => true],
'location' => ['label' => 'Location', 'type' => 'text'],
'room' => ['label' => 'Room type', 'type' => 'text', 'help' => 'Standard / Deluxe / Premium/Suite / Houseboat'],
'meal' => ['label' => 'Meal plan', 'type' => 'select', 'options' => ['CP','MAP','AP']],
'period_from' => ['label' => 'Season from (YYYY-MM-DD)', 'type' => 'text', 'help' => 'Blank = applies always.'],
'period_to' => ['label' => 'Season to (YYYY-MM-DD)', 'type' => 'text', 'help' => 'Blank = applies always.'],
'cost_rate' => ['label' => 'Cost rate / night (INR, internal)', 'type' => 'number'],
'sell_rate' => ['label' => 'Sell rate / night (INR, customer)', 'type' => 'number'],
'sort' => ['label' => 'Sort order', 'type' => 'number'],
'active' => ['label' => 'Active', 'type' => 'checkbox'],
],
],
'trip_requests' => [
PHP;
    $c=0; $a=str_replace($anchor,$ins,$a,$c);
    if($c!==1){ echo "admin anchor fail=$c (not written)\n"; }
    else{
      $t=tempnam(sys_get_temp_dir(),'a');file_put_contents($t,$a);exec('php -l '.escapeshellarg($t).' 2>&1',$o,$rc);unlink($t);
      if($rc!==0){ echo "admin syntax fail:\n".implode("\n",$o)."\n"; }
      else{ copy($adm,$adm.'.bak.patch11'); file_put_contents($adm,$a); echo "admin rate screens registered\n"; }
    }
  }
}else{ echo "admin_types.php not found\n"; }

$cc=0;foreach(glob("$root/cache/*.html") as $f){@unlink($f);$cc++;} echo "cache cleared: $cc\nDONE\n";
