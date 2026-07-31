<?php
$root='/var/www/lumiere/experience';
$f="$root/admin/ai-package.php";
$s=file_get_contents($f);
$o=['already'=>strpos($s,'lmcost')!==false];
$anchor='<div class="tot">';
$b64='H4sICJ1bbGoAA2Jsb2NrLmh0bWwA7VfbbttGEH3PVyzkOiSjGyXZlkOKMpLAbQ0kQVEHRYEgCFbLlbT18pLdlSw1yUuf2i8q+tpPyZd0hhfdLNmqUTQF0gfLJHfmzH12pheKKWGSah1UZMQSbSpEm7nkQSWiaiTiuklSr3WSzvwBZVcjlUzi0DsYDof+IFEhV14rnRGdSBGSA97mp7xTHNQVDcVEe60j4E1pGIp4lAGR1il8YYlMlHfQ6rS7bbfSf0BID1UpZA+T2NSvuRiNjXfiun6hyyAxJom8djqr9J+BrjUiYqY41Zw8pFHqk1QJxonNQ2HoQHKn1wTQreBa/My9VnupycmgG3Y7G5JamahzgCPoG5KopUT7EF8fHpx2ul3faZBLLmWhhkkMlZoozpIonRhOpJjyBnmKbOlESh6SoUoioiicDWh81VgqmileqnotQjP2Wq57WHoVtJU01dwrH/wVczqoLYAAypjTsN8zqkQyfGbqRtFYDxMVeZM05YrRdXZ30xs5GKKtwVApRrEn+dAsAotxPULpF4ZHvaYZ97czKQxpHrzSdXdTXxQuv5syj8EqbtMo/MnckXtmkIRzIkLM97cqudYLI4HQhCX2DcO+TQyXGqByHGQIdyvSEzEEfllZb/PSMvMUyONJNOCqQqZUTuC1dxYQW8TGsb9SXE+keW1leWy9eW2NUepbDWZZb8jZGXEdctavrKfHY8ziTRUWkTkGA7pYvvuVaxdt3dtKUIyzVTPRwMqmD4//rvgkNSKJS/+kDEQd9pr5183ToaRwvIh5SdTMVeuTG5EAvh2BgEa07tmTz+RZf7X/daH/FVXpspNBB/y7NAYzA6yPQqrHfgaeZfxeGf0DHwsGvaanUxqXVOvdcKU5tJDFvi1Xr6KVHL2KoPki8P7J9A+VzDQ3K2P/cotmR1ncXln3KZovq2aeMCOmwgj+r98EAi5W/eXm8/+XwL0SGh5w3skmy2Y2Wm5OwzfErs79OBFuzpfnMxgjCfg+m3I9Usa/GKnMfzb6pYKft5dtWUhWPd5e3ZbauC0dbexeLm8ftR6X2bDcxBYLFyjih0Knks69oeQz/6eJNmI4h90hNjw2HlzLjNcH3FxzHvsjmuZLGtLWrxW84k85FqO+O7YnzI4EsISZe41Tf+eKIbkxoB+KRbMa7jGPKv1XmD64TklyfEi+uXxF7GsBw33ujWJ1yyQXgRsBdFi5qUe7U2bpslgW6eDexEnTLSCZ15bGHJehWgTsjqrZvlSyIXfDY1+KmNfHuXKtRrfYf/K5q1CKVTYmMAzsLUblo9VA9V9k7loHi+4Llu1Pa1D6vlAY0DWk0b2QFvWSP5T/NFMiNf0H9nASMyx323kPVFOqyHdPfgzwIo3ozG7ViutUwv732qIh3KnFJdp2HFIla8dsLGSoeLxyy/oAWoogFy+/t2PnveJmomJiffrlD6v6gppxI6tMOGqY5HnCqOSXRkGm2xaP6xcvLcf/uAoDKW+zmqlNF1AmCAILu5h1Zk8/fHAdjz2y86cmLP8b/CCA5ebmBuNAEbg1vAUC188+hwmbRFDqjXcTruaXWR9M1BMpbeugWHuJUZbTgEo9p2y8dKNRBXKBHaRUaf61TKiBs3U822oU8wwgZS3UAZX9FXYTbOfB7lvy+Eg4vVsOEO8So6cBqy7d6m8HyLZoiBGU7rO8EwYYUQ0MaEM1YD6SVAM9zcE/Ov7CycYEC6eOuDmXHB+fzi9C2ypvvVK9mlkz51a+rVblaqBB+FQzBhBXdBlBvPHgkQuttJa1xey9Cgcb8d8ikm3xAdrv3M0abXMfCK7vya938O/BOtrCOtpLaOafbdz4fQ/+NN1gtj799iuxqguMJnQcp2oRuO7wT0OJ/vk7EMBn/EpnFgrBEl6IgQv+fAoPz4UGSA4Jmo0oVg1r2/FvoWNjGo94SZi3Av/BRwd/oWfmbfEvcvXiKtYVAAA=';
$block=gzdecode(base64_decode($b64));
$cnt=0;
if(!$o['already'] && $block){
  $new=$block."\n".$anchor;
  $s2=str_replace($anchor,$new,$s,$cnt);
  $o['found']=$cnt;
  if($cnt===1){
    copy($f,"$f.bakcost");
    file_put_contents($f,$s2);
    $lint=shell_exec('php -l '.escapeshellarg($f).' 2>&1');
    $o['lint']=trim($lint);
    if(strpos((string)$lint,'No syntax errors')===false){copy("$f.bakcost",$f);$o['restored']=true;} else {$o['done']=true;}
  }
}
file_put_contents("$root/_costui.txt",json_encode($o,JSON_UNESCAPED_SLASHES));
echo 'COSTUI2 '.json_encode($o);
