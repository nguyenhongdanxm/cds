<?php
// Isolated HTTP fixture: actual endpoints, synthetic auth, no database or production files.
$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/kc-http-'.bin2hex(random_bytes(6));mkdir($tmp);mkdir($tmp.'/includes');$proc=null;$roomFile=null;
function kc_http_assert($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
try{
 foreach(['hoclieu_game_keoco_api.php','hoclieu_game_keoco_match.php','hoclieu_game_keoco_team.php']as$f)copy($root.'/'.$f,$tmp.'/'.$f);
 copy($root.'/includes/keoco_match.php',$tmp.'/includes/keoco_match.php');
 file_put_contents($tmp.'/includes/auth.php', <<<'AUTH'
<?php
$_SESSION=['keoco_question_bank_csrf'=>'fixture-csrf'];
function is_logged_in(){return ($_SERVER['HTTP_X_FIXTURE_ROLE']??'admin')!=='anonymous';}
function current_user(){return ['id'=>'fixture','role'=>$_SERVER['HTTP_X_FIXTURE_ROLE']??'admin'];}
function can_perm_level($p,$l){return ($_SERVER['HTTP_X_FIXTURE_ROLE']??'admin')==='teacher';}
function can_perm($p){return false;}
AUTH
 );file_put_contents($tmp.'/includes/database.php','<?php function cds_db(){throw new RuntimeException("NO_FIXTURE_DB");}');
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);$port=substr(strrchr($address,':'),1);
 $proc=proc_open([PHP_BINARY,'-n','-S','127.0.0.1:'.$port,'-t',$tmp],[0=>['pipe','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes);fclose($pipes[0]);kc_http_assert(is_resource($proc),'HTTP server start');
 for($i=0;$i<50;$i++){$ready=@fsockopen('127.0.0.1',(int)$port);if($ready){fclose($ready);break;}usleep(20000);}
 $request=function(string $path,string $method='GET',?array $body=null,array $headers=[])use($port){$headers[]='Content-Type: application/json';$ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body===null?'':json_encode($body),'ignore_errors'=>true,'timeout'=>5]]);$raw=file_get_contents('http://127.0.0.1:'.$port.'/'.$path,false,$ctx);preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);return [(int)($m[1]??0),json_decode($raw,true),$raw];};
 [$status]=$request('hoclieu_game_keoco_api.php?action=list','GET',null,['X-Fixture-Role: anonymous']);kc_http_assert($status===401,'Anonymous bank access');
 [$status]=$request('hoclieu_game_keoco_api.php?action=delete&id=1');kc_http_assert($status===405,'GET deletion accepted');
 [$status]=$request('hoclieu_game_keoco_api.php','POST',['action'=>'create_match','csrf'=>'wrong']);kc_http_assert($status===403,'CSRF bypass');
 $rows=[];for($i=0;$i<4;$i++)$rows[]=['question_text'=>'Câu '.$i,'option_a'=>'1','option_b'=>'2','option_c'=>'3','option_d'=>'4','correct_option'=>2];$data=['action'=>'create_match','csrf'=>'fixture-csrf','questions'=>$rows,'settings'=>['mode'=>'shared','duration'=>60,'loop'=>true]];
 [$status]=$request('hoclieu_game_keoco_api.php','POST',$data,['X-Fixture-Role: student']);kc_http_assert($status===403,'Student created room');
 [$status,$created]=$request('hoclieu_game_keoco_api.php','POST',$data);kc_http_assert($status===200&&strlen($created['tokens']['b']??'')===64,'Create match failed');$code=$created['code'];$auth=fn($s)=>['X-Keoco-Token: '.$created['tokens'][$s]];
 $roomDir=sys_get_temp_dir().'/cds-keoco-'.substr(hash('sha256',$tmp.'/includes'),0,16);$roomFile=$roomDir.'/'.$code.'.json';
 [$status]=$request('hoclieu_game_keoco_match.php?code='.$code);kc_http_assert($status===403,'Missing token accepted');
 [$status]=$request('hoclieu_game_keoco_match.php','POST',['code'=>$code,'action'=>'begin'],$auth('b'));kc_http_assert($status===403,'Team started room');
 [$status]=$request('hoclieu_game_keoco_match.php','POST',['code'=>$code,'action'=>'begin'],$auth('host'));kc_http_assert($status===200,'Host start failed');
 [$status,$state,$raw]=$request('hoclieu_game_keoco_match.php?code='.$code,'GET',null,$auth('b'));kc_http_assert($status===200&&!str_contains($raw,'correct_option')&&!isset($state['state']['teams']['r']),'Team state leaked secrets');
 [$status,$result]=$request('hoclieu_game_keoco_match.php','POST',['code'=>$code,'action'=>'answer','side'=>'r','serial'=>1,'answer'=>1],$auth('b'));kc_http_assert($status===200&&$result['state']['score']['b']===1&&$result['state']['score']['r']===0,'Team impersonation or answer failed');
 [$status]=$request('hoclieu_game_keoco_match.php','POST',['code'=>$code,'action'=>'answer','serial'=>1,'answer'=>1],$auth('b'));kc_http_assert($status===409,'Replay accepted');
 [$status]=$request('hoclieu_game_keoco_match.php?code='.$code.'&action=stop','GET',null,$auth('host'));kc_http_assert($status===405,'GET stop accepted');
 [$status]=$request('hoclieu_game_keoco_team.php');kc_http_assert($status===200,'Team screen requires login');
 echo "PASS: actual HTTP endpoints enforce login, CSRF, permissions, POST mutations, team token scope, no answer leakage and replay protection\n";
}finally{if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}if($roomFile){@unlink($roomFile);@rmdir(dirname($roomFile));}foreach(glob($tmp.'/includes/*')?:[]as$f)unlink($f);foreach(glob($tmp.'/*')?:[]as$f)if(is_file($f))unlink($f);@rmdir($tmp.'/includes');@rmdir($tmp);}
