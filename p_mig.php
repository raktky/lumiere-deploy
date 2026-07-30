<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
function hc($pdo,$t,$c){ try{$s=$pdo->query("SHOW COLUMNS FROM $t LIKE ".$pdo->quote($c));return $s&&$s->fetch();}catch(Throwable $e){return false;} }
try{
 if(!hc($pdo,'tb_points','image')) $pdo->exec("ALTER TABLE tb_points ADD COLUMN image VARCHAR(500) NULL");
 if(!hc($pdo,'tb_points','blurb')) $pdo->exec("ALTER TABLE tb_points ADD COLUMN blurb TEXT NULL");
 $o['points']='ok';
}catch(Throwable $e){$o['pe']=$e->getMessage();}
try{
 if(!hc($pdo,'tb_ai','provider')) $pdo->exec("ALTER TABLE tb_ai ADD COLUMN provider VARCHAR(20) DEFAULT 'gemini'");
 $pdo->exec("UPDATE tb_ai SET provider='gemini', model='gemini-2.5-flash' WHERE id=1 AND (api_key IS NULL OR api_key='')");
 $o['ai']='ok';
}catch(Throwable $e){$o['ae']=$e->getMessage();}
$B=[
'Munnar'=>'Tea gardens rolling over the Western Ghats — plantation walks, Mattupetty Dam, Echo Point and cool misty mornings at 1,600 m.',
'Kochi'=>'Colonial streets and the Chinese fishing nets at sunset — St. Francis Church and the spice lanes of Mattancherry.',
'Thekkady'=>'The Periyar Tiger Reserve — a lake cruise past elephant herds and a walk through cardamom and pepper plantations.',
'Alleppey'=>'A private houseboat drifting the Vembanad backwaters — palm-fringed villages and dinner on deck at sunset.',
'Kovalam'=>'Three crescent beaches on the Arabian Sea, the Vizhinjam lighthouse, Ayurvedic massage and golden evenings by the surf.',
'Kumarakom'=>'Little islands on Vembanad Lake — a birding sanctuary, lakeside resorts and serene backwater cruising.',
'Wayanad'=>'Misty highland forests, spice and coffee estates, Edakkal caves and the still waters of Pookode Lake.',
'Vagamon'=>'Rolling meadows, pine forests and tea slopes — a quiet hill station for long walks and cloud-draped mornings.',
'Guruvayoor'=>'One of India\'s most revered Krishna temples, its gopuram alive with ritual and temple elephants.',
'Trivandrum'=>'Kerala\'s capital — the golden Padmanabhaswamy Temple, the Napier Museum and the calm of Shanghumugham beach.',
'Calicut'=>'The historic Malabar port where Vasco da Gama landed — a sweeping beach and warm Malabar cuisine.',
'Athirappilly'=>'Kerala\'s grandest waterfall, thundering through emerald rainforest — the Niagara of India.',
'Bekal'=>'A vast 17th-century seafort curving into the Arabian Sea and northern Kerala\'s laid-back Malabar coast.',
'Varkala'=>'Red laterite cliffs above a golden beach, a clifftop cafe promenade and the sacred Papanasam waters.'];
$seed=0; try{$up=$pdo->prepare("UPDATE tb_points SET blurb=? WHERE name=? AND (blurb IS NULL OR blurb='')");
foreach($B as $n=>$t){ $up->execute([$t,$n]); $seed+=$up->rowCount(); } }catch(Throwable $e){$o['be']=$e->getMessage();}
$o['blurbs']=$seed;
@mkdir("$root/assets/img/locations",0775,true); $o['dir']=is_dir("$root/assets/img/locations");
file_put_contents("$root/_mig.txt",json_encode($o)); echo 'MIG';
