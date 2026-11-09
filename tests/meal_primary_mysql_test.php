<?php
/** Disposable service only: source transition, real app operations, and historical parity. */
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
check(getenv('GITHUB_ACTIONS')==='true'&&getenv('CDS_TEST_MYSQL')==='disposable-ci','Refusing non-disposable database');
check(ctype_digit((string)getenv('CDS_TEST_MYSQL_PORT')),'Missing disposable port');
function cds_instance_config($key=null,$default=null){return $key==='database'?['host'=>'127.0.0.1','port'=>getenv('CDS_TEST_MYSQL_PORT'),'database'=>'cds_disposable_test','username'=>'cds_fixture','password'=>'fixture-only-password','charset'=>'utf8mb4']:$default;}
$worker=($argv[1]??'')==='worker';
$root=$worker?($argv[2]??''):sys_get_temp_dir().'/cds-meal-primary-'.bin2hex(random_bytes(8));
check(str_starts_with($root,sys_get_temp_dir().'/cds-meal-primary-')&&!str_contains($root,'..'),'Unsafe fixture');
if(!$worker)mkdir($root.'/noitru',0700,true);
define('DATA_PATH',$root);define('NOITRU_DIR',$root.'/noitru');define('NOITRU_MEALS',NOITRU_DIR.'/meals_daily.json');define('NOITRU_MEAL_REPORTS',NOITRU_DIR.'/meal_reports.json');define('NOITRU_MEAL_HISTORY',NOITRU_DIR.'/meal_history.json');define('NOITRU_MEAL_HISTORY_DELETED',NOITRU_DIR.'/meal_history_deleted.json');
require __DIR__.'/../includes/json_store.php';require __DIR__.'/../includes/database_meals.php';require __DIR__.'/../includes/database_meal_read.php';require __DIR__.'/../includes/database_migrations.php';require __DIR__.'/../includes/noitru_report_stream.php';require __DIR__.'/support/load_functions.php';
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function save_json($file,$value){return cds_json_save($file,$value);}
function noitru_ensure_dir(){}
function noitru_uid($prefix){return $prefix.'_'.($GLOBALS['fixture_uid']++);}
function noitru_now(){return '2026-10-15T12:00:00+07:00';}
function current_user(){return ['id'=>'fixture','name'=>'Fixture','username'=>'fixture'];}
function noitru_boarders_on_date($date){return [['id'=>'p1','name'=>'Hiện tại','class_name'=>'6A'],['id'=>'p2','class_name'=>'7A']];}
function noitru_student_is_active($row){return ($row['status']??'active')==='active';}
function noitru_meal_effective_student_context(){return ['students'=>['p1'=>['id'=>'p1','class_name'=>'6A'],'p2'=>['id'=>'p2','class_name'=>'7A']],'classes'=>['6A'=>['p1'],'7A'=>['p2']]];}
$GLOBALS['fixture_uid']=1;
load_functions(__DIR__.'/../includes/noitru_store.php',[
 'noitru_meal_write','noitru_meal_read_cache_clear','noitru_meals_all','noitru_meals_for_date','noitru_meals_for_range',
 'noitru_meal_submission_save','noitru_meal_upsert','noitru_meal_upsert_unlocked','noitru_meal_upsert_many','noitru_meal_upsert_many_unlocked','noitru_meals_replace_rows',
 'noitru_meals_lock_day','noitru_meals_lock_day_unlocked','noitru_meal_target_key','noitru_meal_deleted_targets','noitru_meal_deleted_targets_save','noitru_meal_deleted_targets_save_unlocked',
 'noitru_meal_settings','noitru_meal_report_snapshot','noitru_meal_report_upsert','noitru_meal_report_upsert_unlocked','noitru_meal_reports_data','noitru_meal_reports_save','noitru_meal_reports_save_unlocked','noitru_meal_state','noitru_meal_state_set','noitru_meal_state_set_unlocked',
 'noitru_meal_period_summary','noitru_meal_status_index','noitru_meal_status_day','noitru_rice_usage_summary','noitru_meal_report_effective_counts','noitru_student_is_active_on_date',
]);
function nt_meal_history_student_map(){return ['p1'=>['id'=>'p1','name'=>'Một','class_name'=>'6A'],'p2'=>['id'=>'p2','name'=>'Hai','class_name'=>'7A']];}
load_functions(__DIR__.'/../includes/noitru_meal_history.php',['nt_meal_history_deleted_sources','nt_meal_history_all','nt_meal_history_save','nt_meal_history_append_from_change']);
require __DIR__.'/support/meal_history_reference.php';
require __DIR__.'/support/meal_summary_reference.php';
$pdo=cds_db();check($pdo->query('SELECT DATABASE()')->fetchColumn()==='cds_disposable_test','Wrong DB');
if($worker){
 $id=(int)($argv[3]??0);$GLOBALS['fixture_uid']=10000+$id*100;
 for($i=0;$i<8;$i++)check(noitru_meal_write(static function()use($id,$i){
  if(!noitru_meal_upsert(['date'=>'2026-10-17','student_id'=>"w$id-$i",'sang'=>'yes']))return false;
  return noitru_meal_report_upsert(['date'=>'2026-10-17','class_name'=>"w$id-$i",'meal'=>'sang','student_snapshot'=>[['id'=>'p1','name'=>'Giữ nguyên']],'eat_count'=>1]);
 }), 'Concurrent operation');
 exit;
}
function exact($a,$b,$message){check($a===$b,$message);}
function assert_throws(callable $call,$message){$caught=false;try{$call();}catch(Throwable $e){$caught=true;}check($caught,$message);}
function scan_steps(PDO $pdo){$out=0;foreach($pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Handler_read_next','Handler_read_rnd_next')")as $row)$out+=(int)$row['Value'];return $out;}
$renamed=false;
try{
 check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()===0,'Database must be empty');
 cds_db_run_pending_migrations();
 $daily=[['id'=>'old','date'=>'2026-08-01','student_id'=>'old','sang'=>'yes','note'=>'Cũ','custom'=>['keep'=>false]],['id'=>'p2','date'=>'2026-10-15','student_id'=>'p2','sang'=>'no','trua'=>'yes'],['id'=>'p1','date'=>'2026-10-15','student_id'=>'p1','sang'=>'yes','trua'=>'no']];
 $report=['id'=>'r1','date'=>'2026-10-15','class_name'=>'6A','meal'=>'sang','eat_count'=>1,'student_snapshot'=>[['id'=>'historical','name'=>'Học sinh cũ']],'unknown'=>['keep'=>true]];
 $meta=['reports'=>[$report],'states'=>[['date'=>'2026-10-15','meal'=>'toi','status'=>'off']],'settings'=>['sang_lock_time'=>'20:00','trua_lock_time'=>'09:00','toi_lock_time'=>'15:00','custom'=>false],'unknown'=>['untouched'=>true]];
 check(cds_json_save(NOITRU_MEALS,$daily)&&cds_json_save(NOITRU_MEAL_REPORTS,$meta),'Seed JSON');
 foreach(['history'=>[['id'=>'h1','changes'=>[['student_id'=>'old']]]],'deleted'=>[],'targets'=>[]]as $key=>$value)cds_json_save(cds_meal_primary_files()[$key],$value);
 check(!cds_meal_primary_enabled(),'Default off');
 assert_throws(static fn()=>cds_meal_primary_switch(true),'Activation without comparison refused');
 check(cds_meal_import_snapshot(current_user())['is_match'],'Initial mirror');$pdo->exec("UPDATE cds_meal_reports SET id='old-recreated-id' WHERE id='r1'");check(cds_meal_import_snapshot(current_user())['is_match'],'Mirror replaces old id for same target');cds_meal_shadow_set(true,current_user());cds_meal_sql_read_set(true,current_user());
 // Import parity is insufficient when original duplicate keys were collapsed: do not silently merge.
 $before=file_get_contents(NOITRU_MEALS);file_put_contents(NOITRU_MEALS,json_encode(array_merge($daily,[$daily[0]])));
 assert_throws(static fn()=>cds_meal_primary_switch(true),'Duplicate source activation refused');exact(file_get_contents(NOITRU_MEALS),json_encode(array_merge($daily,[$daily[0]])),'Failed activation did not rewrite source');file_put_contents(NOITRU_MEALS,$before);
 cds_meal_primary_switch(true);check(cds_meal_primary_enabled(),'Primary activated');
 exact(cds_json_load(NOITRU_MEALS),$daily,'Exact daily payload/order');exact(cds_json_load(NOITRU_MEAL_REPORTS),$meta,'Exact report snapshot, metadata and order');
 $jsonHashes=[];foreach(cds_meal_primary_files()as $key=>$file)$jsonHashes[$key]=hash_file('sha256',$file);
 assert_throws(static fn()=>cds_meal_import_snapshot(),'Old snapshot cannot overwrite primary');assert_throws(static fn()=>cds_meal_shadow_set(false,current_user()),'Cannot disable mirror while primary');assert_throws(static fn()=>cds_meal_sql_read_set(false,current_user()),'Cannot disable read while primary');
 check(noitru_meal_upsert_many([['date'=>'2026-10-15','student_id'=>'p1','trua'=>'yes'],['date'=>'2026-10-15','student_id'=>'p1','toi'=>'no']]),'Sparse sequential daily merges');
 $saved=noitru_meals_for_date('2026-10-15')['p1'];exact($saved['sang'],'yes','Sparse flags retained');exact($saved['trua'],'yes','First batch update retained');exact($saved['toi'],'no','Second batch update retained');
 check(noitru_meals_lock_day('2026-10-15',true),'Lock day');$hash=cds_meal_primary_digest(cds_meal_primary_rows('daily'));
 check(!noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'p1','sang'=>'no']),'Locked edit refused');exact(cds_meal_primary_digest(cds_meal_primary_rows('daily')),$hash,'Locked refusal preserves data');check(noitru_meals_lock_day('2026-10-15',false),'Unlock day');
 // Exact same original callback result on JSON and SQL, including tombstone cleanup and snapshots.
 mt_srand(20261108);$expected=[$report];$deleted=[];
 for($case=0;$case<40;$case++){
  $updates=[];for($i=0;$i<1+$case%4;$i++)$updates[]=['date'=>'2026-10-'.(15+mt_rand(0,2)),'class_name'=>mt_rand(0,1)?'6A':'7A','meal'=>['sang','trua','toi'][mt_rand(0,2)],'eat_count'=>mt_rand(0,2),'student_snapshot'=>[['id'=>'historical','name'=>'Giữ nguyên']],'note'=>'Lượt '.$case];
  $pending=[];foreach($updates as $row){$row=noitru_meal_report_snapshot($row);$key=serialize([$row['date'],$row['class_name'],$row['meal']]);$pending[$key]=isset($pending[$key])?array_merge($pending[$key],$row,['updated_at'=>noitru_now()]):$row;}
  $uid=$GLOBALS['fixture_uid'];$found=[];
  foreach($expected as &$row){$key=serialize([$row['date'],$row['class_name'],$row['meal']]);if(isset($pending[$key])&&!isset($found[$key])){$row=array_merge($row,$pending[$key],['updated_at'=>noitru_now()]);$found[$key]=true;}}unset($row);
  foreach($pending as $key=>$row)if(!isset($found[$key]))$expected[]=array_merge(['id'=>noitru_uid('mr'),'created_at'=>noitru_now()],$row);
  $GLOBALS['fixture_uid']=$uid;check(noitru_meal_report_upsert_many($updates),'Report batch');exact(iterator_to_array(cds_meal_primary_rows('reports'),false),$expected,'Exact original report callback parity '.$case);
 }
 $students=[['id'=>'p1','class_name'=>'6A'],['id'=>'p2','class_name'=>'7A']];
 exact(noitru_meal_period_summary('2026-10-15','2026-10-17',$students),reference_noitru_meal_period_summary('2026-10-15','2026-10-17',$students),'Period totals unchanged');
 $rice=['settings'=>['sang_grams'=>100,'trua_grams'=>180,'toi_grams'=>175]];exact(noitru_rice_usage_summary('2026-10-15','2026-10-17',$rice),reference_noitru_rice_usage_summary('2026-10-15','2026-10-17',$rice),'Rice totals and rounding unchanged');
 load_functions(__DIR__.'/../chuyenmon/includes/classroom_home_store.php',['cmhome_meal_summary']);
 $home=cmhome_meal_summary('6A',['p1'],'2026-10');check(is_array($home[0]['p1']),'Homeroom reads current SQL source');
 // Nested caller ignores false, nevertheless the entire outer operation must be rolled back.
 $oldHash=cds_meal_primary_digest(cds_meal_primary_rows('daily'));
 check(!noitru_meal_write(static function(){noitru_meal_upsert(['date'=>'2026-10-16','student_id'=>'abort','sang'=>'yes']);cds_json_rows_save(NOITRU_MEALS,(static function(){yield ['date'=>'2026-10-16','student_id'=>'abort'];throw new RuntimeException('Injected stream failure');})());return true;}),'Injected mid-write failure');
 exact(cds_meal_primary_digest(cds_meal_primary_rows('daily')),$oldHash,'Whole transaction rolled back');
 $oldHash=cds_meal_primary_digest(cds_meal_primary_rows('daily'));
 check(!noitru_meal_submission_save([['date'=>'2026-10-16','student_id'=>'bad-submission','sang'=>'yes']],[['date'=>'invalid','class_name'=>'6A','meal'=>'sang']]),'Whole submission refuses invalid report');
 exact(cds_meal_primary_digest(cds_meal_primary_rows('daily')),$oldHash,'Failed report rolls back daily flags');
 // Auxiliary changes and source deletes are in the same transaction.
 check(noitru_meal_write(static function(){save_json(NOITRU_DIR.'/meal_history_deleted.json',['r1']);save_json(NOITRU_DIR.'/meal_history_deleted_targets.json',['2026-10-15|6A|sang']);return noitru_meal_report_upsert(['date'=>'2026-10-15','class_name'=>'6A','meal'=>'sang','eat_count'=>2]);}),'Tombstones/reactivation');
 check(!isset(noitru_meal_deleted_targets()['2026-10-15|6A|sang']),'Reactivation removes target tombstone');
 check(noitru_meal_write(static fn()=>cds_json_field_save(NOITRU_MEAL_REPORTS,'reports',static function($rows){foreach($rows as $row)if($row['class_name']!=='7A')yield $row;})),'Generic report delete');
 foreach(cds_meal_primary_rows('reports')as $row)check($row['class_name']!=='7A','Deleted report not retained');
 // Legacy history must be byte-for-byte equivalent at the PHP array level, with no student payload reads.
 $historyBefore=cds_meal_primary_meta('history');$uid=$GLOBALS['fixture_uid'];
 $expectedHistory=reference_nt_meal_history_all();
 check(save_json(NOITRU_MEAL_HISTORY,$historyBefore),'Reset fixture history');$GLOBALS['fixture_uid']=$uid;
 exact(nt_meal_history_all(),$expectedHistory,'Lightweight legacy history matches original synthesis exactly');
 $GLOBALS['nt_meal_history_pending_context']=['dates'=>['2026-10-15'],'meals'=>['sang'],'student_ids'=>['p1'],'class_name'=>'6A','before'=>['2026-10-15'=>['p1'=>['sang'=>'yes']]],'after_lock'=>false,'submission_mode'=>'regular'];
 check(noitru_meal_submission_save([['date'=>'2026-10-15','student_id'=>'p1','sang'=>'no']],[['date'=>'2026-10-15','class_name'=>'6A','meal'=>'sang','eat_count'=>0]]),'Submission plus actual production history');
 $history=cds_meal_primary_meta('history');$last=end($history);exact($last['changes'][0]['from'],'yes','History before flag');exact($last['changes'][0]['to'],'no','History committed with latest source');unset($GLOBALS['nt_meal_history_pending_context']);
 // Keyset boundary and indexed save: unrelated annual rows must not be scanned for a daily change.
 check(noitru_meal_write(static function(){return cds_json_rows_save(NOITRU_MEALS,(static function(){yield from cds_meal_primary_rows('daily');for($i=0;$i<2500;$i++)yield ['id'=>'large-'.$i,'date'=>'2026-08-01','student_id'=>'large-'.$i,'sang'=>'no'];})());}),'Large synthetic source');
 $beforeSteps=scan_steps($pdo);check(noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'p1','note'=>'Indexed']),'Indexed edit');$steps=scan_steps($pdo)-$beforeSteps;check($steps<500,'Daily edit must not scan whole history: '.$steps);
 $range=iterator_to_array(noitru_meal_daily_rows_range('2026-10-15','2026-10-15'),false);check(count($range)===2,'Date query excludes annual history');
 $workers=[];for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$root,(string)$i],[0=>['pipe','r'],1=>['file',$root.'/worker-'.$i.'.log','a'],2=>['file',$root.'/worker-'.$i.'.log','a']],$pipes);check(is_resource($proc),'Worker started');fclose($pipes[0]);$workers[]=$proc;}
 foreach($workers as $i=>$proc)check(proc_close($proc)===0,'Worker completed: '.file_get_contents($root.'/worker-'.$i.'.log'));
 foreach([0,1]as $i)for($j=0;$j<8;$j++)check(isset(noitru_meals_for_date('2026-10-17')["w$i-$j"]),'Concurrent row retained');
 foreach(cds_meal_primary_files()as $key=>$file)exact(hash_file('sha256',$file),$jsonHashes[$key],'JSON frozen while primary: '.$key);
 $pdo->exec('RENAME TABLE cds_meal_primary_rows TO cds_meal_primary_unavailable');$renamed=true;
 assert_throws(static fn()=>noitru_meals_for_range('2026-10-15','2026-10-17'),'DB unavailable fails closed');check(!noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'db-failed','sang'=>'yes']),'DB unavailable write refused');
 assert_throws(static fn()=>cds_meal_primary_switch(false),'Rollback cannot use stale JSON');check(cds_meal_primary_enabled(),'Authority unchanged after unavailable DB');
 $pdo->exec('RENAME TABLE cds_meal_primary_unavailable TO cds_meal_primary_rows');$renamed=false;
 $latest=[];foreach(cds_meal_primary_files()as $key=>$file)$latest[$key]=cds_meal_primary_load($file);
 cds_meal_primary_switch(false);check(!cds_meal_primary_enabled(),'Verified rollback');foreach(cds_meal_primary_files()as $key=>$file)exact(cds_json_load($file),$latest[$key],'Latest JSON after rollback: '.$key);
 check(cds_meal_pending_status()!==null&&!cds_meal_sql_read_effective(),'Frozen shadow blocked after rollback');
 check(noitru_meal_write(static fn()=>cds_meal_shadow_sync('test_recovery')),'Refresh shadow after rollback');check(cds_meal_compare_snapshot()['is_match'],'Latest restored JSON matches refreshed mirror');
 echo 'PASS meal primary: exact payload/order, 40 report batches, period/rice parity, locks, atomic failure, tombstones, indexed edit ('.$steps.' scan steps), concurrent writes, fail closed and verified rollback'.PHP_EOL;
}finally{
 if($renamed)$pdo->exec('RENAME TABLE cds_meal_primary_unavailable TO cds_meal_primary_rows');
 foreach(glob($root.'/noitru/*')as $file)if(is_file($file))unlink($file);@unlink($root.'/noitru/.meal-write.lock');@rmdir($root.'/noitru');foreach(glob($root.'/*')as $file)if(is_file($file))unlink($file);@rmdir($root);
}
