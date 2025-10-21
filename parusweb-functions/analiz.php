<?php
$old = file_get_contents('functions_full_old.php');
$modules = glob('modules/*.php');
foreach ($modules as $m) {
    $content = file_get_contents($m);
    $found = 0;
    foreach (explode("\n", $old) as $n => $line) {
        if (trim($line) && str_contains($content, trim($line))) $found++;
    }
    echo basename($m).": $found lines matched\n";
}
$missing=[];
foreach (explode("\n",$old) as $n=>$line){
  if(trim($line)==''||str_starts_with(trim($line),'//'))continue;
  $exists=false;
  foreach($modules as $m){
    if(str_contains(file_get_contents($m),trim($line))){$exists=true;break;}
  }
  if(!$exists)$missing[]="$n: $line";
}
file_put_contents('missing.txt',implode("\n",$missing));
echo "Missing lines saved to missing.txt (".count($missing)." total)\n";
