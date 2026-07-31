<?php
$root='/var/www/lumiere/experience';
require_once "$root/app/config.php"; require_once "$root/app/db.php";
$pdo=db(); $o=[];
try{ $o['posts_cols']=array_map(fn($r)=>$r['Field'].' '.$r['Type'].' '.($r['Null']==='NO'?'NOTNULL':'null').($r['Default']!==null?(' def='.$r['Default']):'').($r['Key']?(' '.$r['Key']):''),$pdo->query("SHOW COLUMNS FROM posts")->fetchAll(PDO::FETCH_ASSOC)); }catch(Throwable $e){$o['e1']=$e->getMessage();}
try{ $o['sample']=$pdo->query("SELECT id,title,slug,LENGTH(body) body_len,LENGTH(excerpt) exc_len,published,created_at FROM posts ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){$o['e2']=$e->getMessage();}
try{ $o['published_count']=(int)$pdo->query("SELECT COUNT(*) FROM posts WHERE published=1")->fetchColumn(); }catch(Throwable $e){}
try{ $o['body_nonempty']=(int)$pdo->query("SELECT COUNT(*) FROM posts WHERE body IS NOT NULL AND body<>''")->fetchColumn(); }catch(Throwable $e){$o['e3']=$e->getMessage();}
// how is blog served? dump index.php router + search journal/posts routes
$idx=@file_get_contents("$root/index.php");
$o['index_len']=$idx?strlen($idx):0;
$o['journal_hits']=[];
if($idx){ foreach(['journal','posts','blog'] as $w){ $p=stripos($idx,$w); $o['journal_hits'][$w]=$p===false?-1:$p; } }
@copy("$root/index.php","$root/_idx.txt");
// any file referencing posts table render
$o['htaccess']=@file_exists("$root/.htaccess")?substr((string)@file_get_contents("$root/.htaccess"),0,1200):'no-htaccess';
file_put_contents("$root/_blog2.txt",json_encode($o,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo 'BLOG2';
