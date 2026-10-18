<?php
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
check(getenv('GITHUB_ACTIONS')==='true'&&getenv('CDS_TEST_MYSQL')==='disposable-ci','Refusing non-disposable environment');
check(ctype_digit((string)getenv('CDS_TEST_MYSQL_PORT')),'Missing disposable port');
$dir=sys_get_temp_dir().'/cds-buzzer-http-'.bin2hex(random_bytes(8));mkdir($dir,0700);putenv('CDS_BUZZER_AUDIO_FIXTURE='.$dir);
$sock=stream_socket_server('tcp://127.0.0.1:0');$addr=stream_socket_get_name($sock,false);fclose($sock);$GLOBALS['url']='http://'.$addr.'/hoclieu_game_buzzer_api.php';
$process=proc_open([PHP_BINARY,'-d','upload_max_filesize=1K','-d','post_max_size=256K','-S',$addr,__DIR__.'/support/buzzer_http_router.php'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes);fclose($pipes[0]);
function call_api($action,$data=[],$headers=[],$get=false){$data=array_merge(['action'=>$action,'role'=>'host','csrf'=>'fixture-csrf'],$data);$opts=['http'=>['method'=>$get?'GET':'POST','ignore_errors'=>true,'timeout'=>10,'header'=>implode("\r\n",['Content-Type: application/json']+$headers)]];
 if(!$get)$opts['http']['content']=json_encode($data);$body=@file_get_contents($GLOBALS['url'].($get?'?'.http_build_query($data):''),false,stream_context_create($opts));$status=isset($http_response_header[0])?(int)explode(' ',$http_response_header[0])[1]:0;return [$status,$body,$body!==false?json_decode($body,true):null];}
try{
 for($n=0;$n<50;$n++){[$status]=call_api('audio_settings',[],[],true);if($status===200)break;usleep(50000);}check($status===200,'HTTP fixture started');
 [$status,,$room1]=call_api('create',['title'=>'Room one']);check($status===200,'Real room one');
 [$status,,$room2]=call_api('create',['title'=>'Room two'],[1=>'X-Test-User: other-teacher',2=>'X-Test-Role: gv']);check($status===200,'Another teacher room');
 $audit=new PDO('mysql:host=127.0.0.1;port='.getenv('CDS_TEST_MYSQL_PORT').';dbname=cds_disposable_test;charset=utf8mb4','cds_fixture','fixture-only-password');$rawBefore=$audit->query('SELECT code,state_json FROM cds_buzzer_rooms ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
 $pcm=str_repeat("\0",300000);$wav='RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,1,8000,16000,2,16).'data'.pack('V',strlen($pcm)).$pcm;
 [$status,,$begin]=call_api('audio_begin',['kind'=>'bell','name'=>'Chuông chung.wav','size'=>strlen($wav)]);check($status===200,'Begin actual HTTP upload without room code');$id=$begin['upload_id'];
 foreach(str_split($wav,131072)as$n=>$chunk){$request=['upload_id'=>$id,'offset'=>$n*131072,'data'=>base64_encode($chunk)];[$status,,$out]=call_api('audio_chunk',$request);check($status===200,'Real chunk under tiny upload_max_filesize');if($n===0){[$status,,$retry]=call_api('audio_chunk',$request);check($status===200&&$retry['offset']===$out['offset'],'Repeated chunk idempotent');}}
 [$status,,$done]=call_api('audio_finish',['upload_id'=>$id]);check($status===200&&$done['audio']['bell']['name']==='Chuông chung.wav','Actual finish validates MIME and persists global setting');
 [$status]=call_api('audio_finish',['upload_id'=>$id]);check($status===200,'Finish retry idempotent');
 foreach([[$room1,[]],[$room2,[1=>'X-Test-User: other-teacher',2=>'X-Test-Role: gv']]]as[$room,$h]){[$status,,$out]=call_api('state',['code'=>$room['code']],$h,true);check($status===200&&$out['state']['audio']['bell']['id']===$id,'All owners rooms inherit global bell');}
 check($audit->query('SELECT code,state_json FROM cds_buzzer_rooms ORDER BY code')->fetchAll(PDO::FETCH_ASSOC)===$rawBefore,'Global setting never rewrites any room state');
 [$status,$bytes]=call_api('audio',['code'=>$room2['code'],'role'=>'screen','kind'=>'bell'],[1=>'X-Buzzer-Token: '.$room2['screen_key']],true);check($status===200&&$bytes===$wav,'Protected audio download byte-for-byte');
 [$status]=call_api('audio_reset',['kind'=>'bell'],[1=>'X-Test-Role: gv']);check($status===403,'Teacher cannot overwrite school-wide audio');
 [$status]=call_api('audio_begin',['kind'=>'bell','name'=>'bad','size'=>10,'csrf'=>'wrong']);check($status===403,'Real endpoint CSRF enforced');
 [$status]=call_api('audio_reset',['kind'=>'bell','role'=>'player']);check($status===403,'Student cannot configure audio');
 [$status,,$newRoom]=call_api('create',['title'=>'New room after upload']);check($status===200,'Future room created');[$status,,$view]=call_api('state',['code'=>$newRoom['code']],[],true);check($view['state']['audio']['bell']['id']===$id,'Future rooms inherit');
 [$status]=call_api('audio_reset',['kind'=>'bell']);check($status===200,'Global reset');
 [$status,,$view]=call_api('state',['code'=>$room2['code']],[1=>'X-Test-User: other-teacher',2=>'X-Test-Role: gv'],true);check($view['state']['audio']['bell']===null,'Reset applies across rooms');
 echo "PASS real HTTP: 300KB WAV upload with upload_max_filesize=1KB, chunk/finish retry, actual MIME/storage, byte-exact authorized download, old/new cross-owner rooms, reset and CSRF/admin checks\n";
}finally{proc_terminate($process);proc_close($process);foreach(glob($dir.'/buzzer_audio/*')as$f)unlink($f);if(is_file($dir.'/buzzer_audio/.htaccess'))unlink($dir.'/buzzer_audio/.htaccess');@rmdir($dir.'/buzzer_audio');foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
