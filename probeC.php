<?php
/* probeC: base64 slices of quote.php (customer-link box + save area) and q.php (accept/fetch/notfound). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
$root='/var/www/lumiere/experience';
function sl($c,$needle,$b,$a){ $p=strpos($c,$needle); return $p===false?null:base64_encode(substr($c,max(0,$p-$b),$b+$a)); }
$q=(string)@file_get_contents("$root/admin/quote.php");
$pub=(string)@file_get_contents("$root/q.php");
$out=[
 'q_customerlink'=>sl($q,'Customer link',200,600),
 'q_savedtrue'=>sl($q,'$saved = true;',60,600),
 'q_token_on_save'=>sl($q,'SET token = ?',120,300),
 'pub_fetch'=>sl($pub,'$quote = $st->fetch();',40,200),
 'pub_accept'=>sl($pub,'SET accepted = 1',120,220),
 'pub_notfound'=>sl($pub,'invalid or has expired',120,220),
 'pub_elsebranch'=>sl($pub,'Your Kerala journey',260,60),
];
file_put_contents("$root/_pc.txt", json_encode($out));
echo "DONE\n";
