<?php
$root='/var/www/lumiere/experience';
$src=base64_decode((string)@file_get_contents("$root/_aiblob.txt"));
file_put_contents("$root/_run.php",$src);
$r='wrote='.strlen($src);
try{ include "$root/_run.php"; $r.=' inc=ok'; }catch(Throwable $e){ $r.=' inc_ERR='.$e->getMessage(); }
@unlink("$root/_run.php");
$r.=' ai='.(file_exists("$root/app/ai.php")?'Y':'N').' plan='.(file_exists("$root/admin/ai-plan.php")?'Y':'N');
file_put_contents("$root/_pfin3.txt",$r);
echo "DONE3\n";
