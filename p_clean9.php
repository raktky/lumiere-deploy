<?php
$root='/var/www/lumiere/experience';
$del=['_mig.txt','_aidump.txt','_aifns.txt','_ai_deploy.txt','_d_ui.txt','_d_plan.txt','_d_quote.txt','_d_ls.txt','_d_pdf.txt','_d_pkg.txt','_d_rbac.txt','_d_q.txt','_d_schema.txt','_dep_p_loc.txt','_dep_p_aipkg.txt','_dep_logo.txt','_dep_p_pdf.txt','_dep_nav.txt','_dep_nav2.txt'];
$done=[]; $left=[];
foreach($del as $x){ $p=$root.'/'.$x; if(is_file($p)){ @unlink($p); if(is_file($p)) $left[]=$x; else $done[]=$x; } }
file_put_contents($root.'/_cleanup.txt',json_encode(['removed'=>count($done),'left'=>$left]));
echo 'CLEAN';
