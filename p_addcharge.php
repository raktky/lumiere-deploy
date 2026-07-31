<?php
$root='/var/www/lumiere/experience';
$f="$root/admin/ai-package.php";
$s=file_get_contents($f);
$o=['already'=>strpos($s,'lm_add')!==false];
$anchor='<div style="text-align:right;margin-top:10px;font-size:13px">Extra on total:';
$o['anchor']=substr_count($s,$anchor);
$btn=gzdecode(base64_decode('H4sICCdkbGoAA2J0bi5odG1sAL1U247TMBB936+wUkFSQdN0y7YlTStxWQmkRUJi35FjT1JrXdvYk15A/DvTpmW7ZYXgAR6S2JnjuZwz40KqFQu41TCLltzXyvTQunyQuU00L8oG0RqGW0fmdhMxJWeRXn7mUkbHkyUXd7W3jZF5B6AaVdm0tF6Czwduw4LVSrJOWUEmyqmw2vq8k4lROYQDrOe5VE3IJ24zdeRYmTof08nBJf2orMFeUF8hHwxpKxofyIGzyiD41roGVS8wH2VZNH/GXknJxIKKAZZIr1bgWcmRsz5DqzV9HPd3FIJWYNBvu0W/rW1e9ImO+UURhFcO5xfSimZJmLQGvNawW75evpdJ3NYfd1N6X6/o940KCAZ8EgutxF38vGqMQGVN0v12wdiKe4bl7Hf+vF2HuDs9gv09WHjgCAd8EqNvYehTZSjku9sPN7O4QHlU48jgiBh8sddRGdcgE5qHsNdO8xJ0xJzmAhZWkwKzCNI6ZW8f0HXb0vWxpeun2mslcZEPJtmJWlcUixR7RHa4hAkMz4Qe79Pqo5yf5I2wwR7Xqja53+n5SOLCBowO/WiaZQk+Irp0Q9vsLL+Xu945d/mv8w2gQZwmvEs1Ohfm6m8DW7drpWOlleYU62lnMhyPp0W/NZ6DnCDMk3trv01tzn7hlA78IaWj/07pg/EeZ9nD2yM6qYLK00TKUvKwmO7dxvshKVPuHBj5ZqG0TNAfR+dLA377ac+JpalND81FI13R3IWEcN/pId4Od8EP3TZNOSoFAAA='));
if(!$o['already'] && $o['anchor']===1 && $btn){
  $s2=str_replace($anchor,$btn."\n  ".$anchor,$s);
  copy($f,"$f.bakadd");
  file_put_contents($f,$s2);
  $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
  $o['lint']=trim($lint);
  if(strpos((string)$lint,'No syntax errors')===false){copy("$f.bakadd",$f);$o['restored']=true;} else {$o['done']=true;}
}
file_put_contents("$root/_addcharge.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'ADDCHARGE '.json_encode($o);
