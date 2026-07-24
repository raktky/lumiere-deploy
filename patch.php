<?php
/* lmb mobile-friendly patch: make the builder full-width + natural scroll on phones. Idempotent. */
$roots=["/var/www/lumiere/experience","/var/www/lumiere","/var/www/html/experience","/var/www/html"];
$root="";foreach($roots as $c){ if(is_file("$c/templates/build.php")){ $root=$c; break; } }
if(!$root){ die("build.php not found\n"); }
$tpl="$root/templates/build.php";
$s=file_get_contents($tpl);
if(strpos($s,'lmb-mobile-fix')!==false){ echo "already patched\n"; }
else{
  $mq="\n  /* lmb-mobile-fix */\n"
     ."  @media (max-width:640px){\n"
     ."    #lmb{padding:0}\n"
     ."    #lmb .phone{width:100%;max-width:none;height:auto;min-height:auto;border:0;border-radius:0;box-shadow:none}\n"
     ."    #lmb .scroll{overflow:visible}\n"
     ."    #lmb .topbar{position:sticky;top:0;z-index:20}\n"
     ."    #lmb .foot{position:sticky;bottom:0;z-index:20}\n"
     ."  }\n</style>";
  $n=0;$s2=str_replace('</style>',$mq,$s,$n);
  if($n<1){ die("no </style> found\n"); }
  copy($tpl,$tpl.".bak.mobile");
  file_put_contents($tpl,$s2);
  echo "PATCHED mobile (replacements=$n)\n";
}
$cc=0; foreach(glob("$root/cache/*.html") as $f){ @unlink($f); $cc++; }
echo "cache cleared: $cc\nDONE\n";
