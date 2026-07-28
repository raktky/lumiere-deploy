<?php
$root='/var/www/lumiere/experience';
$out=[];
try{
 $b=(string)@file_get_contents("$root/_pkgdata.txt");
 $src=@gzuncompress(base64_decode($b));
 if($src===false||strlen($src)<8000){ throw new Exception('decode_fail_'.strlen((string)$src)); }
 if(strpos($src,'PKG_ITIN_BUILDER')===false){ throw new Exception('marker_missing'); }
 @copy("$root/admin/package.php", "$root/admin/package.php.bak");
 file_put_contents("$root/admin/package.php", $src);
 $out['ok']=true; $out['bytes']=strlen($src);
}catch(Throwable $e){ $out['ok']=false; $out['err']=$e->getMessage(); }
file_put_contents("$root/_pwrite.txt", json_encode($out));
echo "PWDONE
";
