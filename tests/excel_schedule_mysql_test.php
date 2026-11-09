<?php
/** Real SQL only in the explicitly disposable CI database; no school configuration. */
if(getenv('GITHUB_ACTIONS')!=='true'||getenv('CDS_TEST_MYSQL')!=='disposable-ci')throw new RuntimeException('Disposable CI only');
function cds_instance_config($key=null,$default=null){return$key==='database'?['host'=>'127.0.0.1','port'=>getenv('CDS_TEST_MYSQL_PORT'),'database'=>'cds_disposable_test','username'=>'cds_fixture','password'=>'fixture-only-password','charset'=>'utf8mb4']:$default;}
require __DIR__.'/../includes/database_migrations.php';require __DIR__.'/../includes/excel_schedule_store.php';require __DIR__.'/../includes/excel_schedule_runner.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
cds_db_run_pending_migrations();check(xlsched_ready(),'Schedule schema installed');check(!xlsched_state()['enabled'],'Feature defaults off');
$pdo=cds_db();$before=[];foreach(['cds_lesson_book_records','cds_meal_daily','cds_meal_reports']as$table)$before[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
$_SERVER['HTTP_HOST']='fixture.example';$_SERVER['HTTPS']='on';
$input=['name'=>'Synthetic job','report_id'=>'lb_stats','params'=>['stats_class'=>'6A'],'folder_id'=>'fixture_folder_123','date_policy'=>'yesterday','frequency'=>'daily','start_time'=>'00:00','end_time'=>'05:00','file_mode'=>'replace','enabled'=>1];
$data=xlsched_validate($input,'fixture-admin');$id=xlsched_save($data);$job=xlsched_job($id);check($job['owner_id']==='fixture-admin','Ownership stored');
$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh'));$run=xlsched_claim($job,'fixture-period',$now);check($run!==null,'First claim');check(xlsched_claim($job,'fixture-period',$now)===null,'Running claim cannot duplicate');
xlsched_mark_run((int)$run['id'],'success','Fixture complete','fixture_file_id');check(xlsched_claim($job,'fixture-period',$now)===null,'Completed period never duplicated');
$fail=xlsched_claim($job,'retry-period',$now);xlsched_mark_run((int)$fail['id'],'failed','Fixture retry');
$pdo->exec("UPDATE cds_excel_schedule_runs SET retry_after=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) WHERE id=".(int)$fail['id']);check(xlsched_claim($job,'retry-period',$now)===null,'Retry backoff');
$pdo->exec('UPDATE cds_excel_schedule_runs SET retry_after=NULL,remote_file_id=\'reserved_fixture_file\' WHERE id='.(int)$fail['id']);$again=xlsched_claim($job,'retry-period',$now);check($again['remote_file_id']==='reserved_fixture_file','Retry keeps reserved id');
xlsched_mark_run((int)$fail['id'],'failed','Fixture');$oldRevision=(int)$job['revision'];xlsched_save(array_replace($data,['folder_id'=>'other_fixture_folder']),$id,$oldRevision);$job=xlsched_job($id);check((int)$job['revision']===$oldRevision+1,'Optimistic revision');
$stale=false;try{xlsched_save($data,$id,$oldRevision);}catch(Throwable $e){$stale=true;}check($stale,'Stale edits rejected');$changed=xlsched_claim($job,'retry-period',$now);check($changed['remote_file_id']===null,'Changed revision never overwrites previous reservation');
check(count(xlsched_history())===2,'History unique period rows');check(count(xlsched_jobs())===1,'Job listing with latest status');
// The scheduler lock is per database, so two cron processes never race exports.
$lock='fixture_scheduler_lock';$q=$pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);check((int)$q->fetchColumn()===1,'First lock');
$c=cds_db_read_config();$second=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['database'],$c['username'],$c['password']);$q=$second->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);check((int)$q->fetchColumn()===0,'Second process cannot overlap');$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);
foreach($before as$table=>$n)check((int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$n,'Operational table unchanged: '.$table);
echo "PASS real MySQL/MariaDB schedule migration, default off, idempotent claims, backoff, reservation/revision, optimistic edits, history and competing locks; operational tables unchanged\n";
