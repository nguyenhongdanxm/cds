<?php
$root=dirname(__DIR__);$total=0;
foreach(['pullers-blue-v1.webp','pullers-red-v1.webp','pullers-blue-sad-v1.webp','pullers-red-sad-v1.webp','schoolyard-v1.webp']as$name){$path=$root.'/assets/keoco/'.$name;$i=getimagesize($path);$sprite=str_starts_with($name,'pullers');if(!$i||$i['mime']!=='image/webp'||[$i[0],$i[1]]!==($sprite?[1536,512]:[1920,640]))throw new RuntimeException('Invalid scene asset: '.$name);$total+=filesize($path);if($sprite){$bytes=file_get_contents($path);if(substr($bytes,12,4)!=='VP8X'||!(ord($bytes[20])&16))throw new RuntimeException('Transparent alpha missing: '.$name);}}
if($total>1200000)throw new RuntimeException('Scene asset budget exceeded');
echo "PASS: four transparent three-frame character sheets and school backdrop, ".$total." bytes\n";
