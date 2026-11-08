<?php
require __DIR__.'/../includes/json_store.php';
require __DIR__.'/../includes/meal_primary.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function cds_db(){throw new RuntimeException('Fixture DB unavailable');}
$root=sys_get_temp_dir().'/meal-primary-guard-'.bin2hex(random_bytes(6));mkdir($root.'/noitru',0700,true);mkdir($root.'/other',0700);define('DATA_PATH',$root);
$file=$root.'/noitru/meals_daily.json';$other=$root.'/other/meals_daily.json';
try{
 check(!cds_meal_primary_enabled(),'Default off');
 check(cds_json_save($file,[['date'=>'2026-10-15','student_id'=>'p','sang'=>'yes']]),'Legacy save unchanged');
 check(cds_json_save($other,[['other'=>true]]),'Other store saved');$bytes=file_get_contents($file);
 file_put_contents(cds_meal_primary_marker(),'<?php http_response_code(404);exit;');
 check(!cds_meal_primary_routes($other),'Same filename elsewhere not routed');check(cds_json_load($other)===[['other'=>true]],'Other store unchanged');
 $failed=false;try{cds_json_load($file);}catch(Throwable $e){$failed=true;}check($failed,'Primary read fails closed');
 check(!cds_json_save($file,[]),'Primary save cannot use stale JSON');check(file_get_contents($file)===$bytes,'Backup unchanged on DB failure');
 echo "PASS meal primary default-off, exact path isolation, fail-closed reads/writes and frozen JSON\n";
}finally{foreach(glob($root.'/noitru/*')as$p)unlink($p);@unlink($root.'/noitru/.meal-write.lock');foreach(glob($root.'/other/*')as$p)unlink($p);rmdir($root.'/other');rmdir($root.'/noitru');rmdir($root);}
