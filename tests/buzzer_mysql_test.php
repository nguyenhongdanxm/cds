<?php
/** Only empty disposable CI MySQL/MariaDB. Never reads application configuration. */
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
check(getenv('GITHUB_ACTIONS')==='true'&&getenv('CDS_TEST_MYSQL')==='disposable-ci','Refusing non-disposable database');
$port=(string)getenv('CDS_TEST_MYSQL_PORT');check(ctype_digit($port)&&(int)$port>0,'Fixture port required');
function fixture_db():PDO{return new PDO('mysql:host=127.0.0.1;port='.getenv('CDS_TEST_MYSQL_PORT').';dbname=cds_disposable_test;charset=utf8mb4','cds_fixture','fixture-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
require __DIR__.'/../includes/buzzer_store.php';
if(($argv[1]??'')==='worker'){
    [,,$code,$id,$token,$question,$race,$at]=$argv;
    while(bz_now()<(int)$at)usleep(1000);
    try{$reply=bz_write(fixture_db(),$code,['role'=>'player','id'=>$id,'token'=>$token],'press',['question'=>(int)$question,'race'=>(int)$race]);echo json_encode(['ok'=>true,'version'=>$reply['state']['version']]);}
    catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);}exit;
}
$db=fixture_db();check((int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()===0,'Database must be empty');
$db->exec('CREATE TABLE fixture_existing_school_data(id INT PRIMARY KEY,value VARCHAR(30)) ENGINE=InnoDB');$db->exec("INSERT INTO fixture_existing_school_data VALUES(1,'must-not-change')");
$room=bz_create($db,'teacher-one','Bấm chuông giả lập');$code=$room['code'];$host=['role'=>'host','owner'=>'teacher-one'];$actors=[];
for($i=0;$i<32;$i++){$token=hash('sha256','synthetic-'.$i);$join=bz_write($db,$code,['role'=>'player'],'join',['name'=>'Thí sinh '.$i,'join_token'=>$token]);$actors[]=['role'=>'player','id'=>$join['player_id'],'token'=>$token];}
function control($db,$code,$host,$action,$extra=[]){$s=bz_read($db,$code,$host);return bz_write($db,$code,$host,$action,['version'=>$s['version']]+$extra);}
control($db,$code,$host,'question',['seconds'=>300]);$state=control($db,$code,$host,'start')['state'];
function race_workers($code,$actors,$state){
    $processes=[];$at=bz_now()+2000;
    foreach($actors as$actor){$args=[PHP_BINARY,__FILE__,'worker',$code,$actor['id'],$actor['token'],(string)$state['question'],(string)$state['race'],(string)$at];$pipes=[];$process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$process,$pipes];}
    foreach($processes as[$process,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);check($exit===0&&json_decode($out,true)['ok'],'Concurrent worker: '.$error);}
}
race_workers($code,$actors,$state);
// Reconnect parent after forked subprocesses: parent PDO is never shared with children.
$row=bz_room($db,$code);$s=$row['state'];check(count($s['queue'])===32&&array_column($s['queue'],'rank')===range(1,32),'32 simultaneous presses serialized without rank gaps or loss');check($s['winner_event']===1&&$s['winner_id']===$s['queue'][0]['id'],'Exactly one winner/sound event');
$winner=$s['winner_id'];$oldRace=$s['race'];control($db,$code,$host,'wrong');$fresh=bz_read($db,$code,$host);check($fresh['race']===$oldRace+1&&!$fresh['winner_id'],'Wrong opens new race');
$actor=array_values(array_filter($actors,fn($a)=>$a['id']===$winner))[0];
try{bz_write($db,$code,$actor,'press',['question'=>$fresh['question'],'race'=>$fresh['race']]);throw new LogicException('Wrong player accepted');}catch(BuzzerError $e){check($e->getCode()===409,'Wrong rejection');}
$others=array_values(array_filter($actors,fn($a)=>$a['id']!==$winner));race_workers($code,$others,$fresh);$s=bz_room($db,$code)['state'];check(count($s['queue'])===31&&$s['winner_event']===2,'Remaining players can race again');
// Duplicate network submits from the same authenticated device.
control($db,$code,$host,'question',['seconds'=>300]);$state=control($db,$code,$host,'start')['state'];race_workers($code,array_fill(0,12,$actors[0]),$state);$s=bz_room($db,$code)['state'];check(count($s['queue'])===1&&$s['winner_event']===3,'12 duplicate simultaneous submits only one rank and one sound');
$before=$db->query('SELECT state_json FROM cds_buzzer_rooms')->fetchColumn();
foreach([['role'=>'screen','token'=>$room['screen_key']],['role'=>'player','id'=>$actors[1]['id'],'token'=>$actors[1]['token']],['role'=>'host','owner'=>'other-teacher']]as$bad){try{bz_write($db,$code,$bad,'wrong',['version'=>$s['version']]);throw new LogicException('Unauthorized control');}catch(BuzzerError $e){check($e->getCode()===403,'Authorization fails closed');}}
check($db->query('SELECT state_json FROM cds_buzzer_rooms')->fetchColumn()===$before,'Unauthorized controls never mutate');
$screen=bz_read($db,$code,['role'=>'screen','token'=>$room['screen_key']]);$student=bz_read($db,$code,$actors[0]);check(count($screen['players'])===32&&count($student['players'])===1&&!isset($screen['screen_key']),'Role-specific privacy');
check(!str_contains(json_encode($screen),'key_hash')&&!str_contains(json_encode($student),'screen_key'),'No token leakage');
require __DIR__.'/../includes/buzzer_audio.php';
$audioRoot=sys_get_temp_dir().'/buzzer-sql-audio-'.bin2hex(random_bytes(8));mkdir($audioRoot,0700);define('DATA_PATH',$audioRoot);
control($db,$code,$host,'stop');
$beforeState=bz_room($db,$code)['state'];
$meta=['id'=>str_repeat('a',48),'name'=>'Chuông giả.wav','mime'=>'audio/wav'];
bz_audio_set($db,$code,$host,'bell',$meta);$afterState=bz_room($db,$code)['state'];
check($afterState['queue']===$beforeState['queue']&&$afterState['winner_event']===$beforeState['winner_event']&&$afterState['audio']['bell']===$meta,'Audio settings cannot alter ordering or winner');
foreach([['role'=>'player'],['role'=>'screen'],['role'=>'host','owner'=>'other']]as$bad){try{bz_audio_set($db,$code,$bad,'bell',null);throw new LogicException('Unauthorized audio');}catch(BuzzerError $e){check($e->getCode()===403,'Audio owner enforced');}}
control($db,$code,$host,'question');control($db,$code,$host,'start');try{bz_audio_set($db,$code,$host,'bell',null);throw new LogicException('Audio changed during race');}catch(BuzzerError $e){check($e->getCode()===409,'No mid-question audio replacement');}
control($db,$code,$host,'stop');bz_audio_set($db,$code,$host,'bell',null);check(bz_room($db,$code)['state']['audio']['bell']===null,'Reset to default');rmdir($audioRoot);
control($db,$code,$host,'close');try{bz_write($db,$code,$actors[1],'press',['question'=>$state['question'],'race'=>$state['race']]);throw new LogicException('Closed press');}catch(BuzzerError $e){}
check($db->query('SELECT value FROM fixture_existing_school_data')->fetchColumn()==='must-not-change','Other school data untouched');
echo "PASS SQL: 32+31 simultaneous players, 12 duplicate requests, one winner per race, wrong exclusion, isolation and permissions\n";
