<?php
require __DIR__.'/../includes/json_store.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function cds_lb_upsert(...$args){}
function cds_lb_sql_all(){throw new RuntimeException('Fixture database unavailable');}
$dir=sys_get_temp_dir().'/cds-primary-guard-'.bin2hex(random_bytes(5));mkdir($dir,0700);mkdir($dir.'/other',0700);define('LB_RECORDS_FILE',$dir.'/records.json');
try{
 $rows=[['slot_id'=>'signed','signed_at'=>'signed','signed_snapshot'=>['lesson_title'=>'Original']]];check(!cds_lb_primary_enabled(),'Default off');check(cds_json_save(LB_RECORDS_FILE,$rows),'Legacy JSON save');check(cds_json_load(LB_RECORDS_FILE)===$rows,'Legacy read unchanged');$hash=hash_file('sha256',LB_RECORDS_FILE);
 file_put_contents(cds_lb_primary_marker(),"<?php http_response_code(404); exit; ?>\n");check(cds_lb_primary_routes(LB_RECORDS_FILE),'Exact source routes');check(!cds_lb_primary_routes($dir.'/other/records.json'),'Other stores never route');cds_json_save($dir.'/other/records.json',['normal']);check(cds_json_load($dir.'/other/records.json')===['normal'],'Other JSON functionality unchanged');
 try{cds_json_load(LB_RECORDS_FILE);throw new LogicException('Must not return old JSON');}catch(RuntimeException $e){check($e->getMessage()==='Fixture database unavailable','DB error exposed instead of stale signed backup');}
 check(hash_file('sha256',LB_RECORDS_FILE)===$hash,'Backup unchanged on DB failure');unlink(cds_lb_primary_marker());check(cds_json_load(LB_RECORDS_FILE)===$rows,'Normal path restored when test marker removed');echo "PASS primary guard: default off, exact store scope, fail closed, unchanged signed backup\n";
}finally{foreach(glob($dir.'/other/*')as$f)unlink($f);rmdir($dir.'/other');foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
