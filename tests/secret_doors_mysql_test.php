<?php
// CI-only MySQL sandbox; actual endpoint with synthetic auth and isolated schema.
$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/sd-http-'.bin2hex(random_bytes(5));mkdir($tmp);mkdir($tmp.'/includes');$proc=null;
function sd_assert($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
try{
copy($root.'/hoclieu_game_secret_api.php',$tmp.'/hoclieu_game_secret_api.php');copy($root.'/includes/secret_doors.php',$tmp.'/includes/secret_doors.php');
file_put_contents($tmp.'/includes/auth.php', <<<'AUTH'
<?php
$_SESSION=['secret_doors_csrf'=>'fixture-csrf'];function is_logged_in(){return ($_SERVER['HTTP_X_ROLE']??'teacher')!=='anonymous';}function current_user(){return ['id'=>$_SERVER['HTTP_X_USER']??'owner','role'=>$_SERVER['HTTP_X_ROLE']??'teacher','name'=>'Fixture'];}function can_perm_level($p,$l){return ($_SERVER['HTTP_X_ROLE']??'teacher')!=='denied';}function cds_is_viewer(){return ($_SERVER['HTTP_X_ROLE']??'teacher')==='viewer';}
AUTH
);
file_put_contents($tmp.'/includes/database.php', <<<'DB'
<?php
function cds_db(){return new PDO(getenv('SD_TEST_DSN'),getenv('SD_TEST_USER'),getenv('SD_TEST_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
DB
);
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);$port=substr(strrchr($address,':'),1);
$proc=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$tmp],[0=>['pipe','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes);fclose($pipes[0]);for($i=0;$i<50;$i++){if($s=@fsockopen('127.0.0.1',(int)$port)){fclose($s);break;}usleep(20000);}
$req=function($action,$body=null,$headers=[])use($port){$headers[]='Content-Type: application/json';$ctx=stream_context_create(['http'=>['method'=>$body===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$body===null?'':json_encode(array_merge(['action'=>$action,'csrf'=>'fixture-csrf'],$body)),'ignore_errors'=>true,'timeout'=>10]]);$raw=file_get_contents('http://127.0.0.1:'.$port.'/hoclieu_game_secret_api.php'.($body===null?'?action='.$action:''),false,$ctx);preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);return[(int)($m[1]??0),json_decode($raw,true)];};
[$s]=$req('list',null,['X-Role: anonymous']);sd_assert($s===401,'Anonymous access');[$s]=$req('delete');sd_assert($s===405,'GET mutation');[$s]=$req('save',['csrf'=>'wrong']);sd_assert($s===403,'CSRF');foreach(['viewer','student','denied']as$r){[$s]=$req('save',[],['X-Role: '.$r]);sd_assert($s===403,'Write role '.$r);}
$data=['title'=>'Bộ thử','shared'=>false,'questions'=>[['text'=>'Câu thử','answer'=>'Đúng','hint'=>'Gợi ý thật','points'=>20]]];[$s,$d]=$req('save',$data);sd_assert($s===200,'Create: '.json_encode($d));$id=$d['id'];sd_assert($d['revision']===1,'Initial revision');[$s,$d]=$req('get&id='.$id);sd_assert($s===200&&$d['set']['questions'][0]['answer']==='Đúng'&&$d['set']['questions'][0]['hint']==='Gợi ý thật'&&$d['set']['can_edit'],'Read saved questions');
[$s,$d]=$req('list',null,['X-User: other']);sd_assert($s===200&&count($d['sets'])===0,'Private set leak');
[$s]=$req('save',$data+['id'=>$id,'revision'=>1],['X-User: other']);sd_assert($s===404,'Other modified private bank');
[$s,$d]=$req('save',array_merge($data,['id'=>$id,'revision'=>1,'shared'=>true]));sd_assert($s===200&&$d['revision']===2,'Update revision');
[$s,$d]=$req('list',null,['X-User: other']);sd_assert($s===200&&count($d['sets'])===1&&!$d['sets'][0]['can_edit'],'Shared visibility');
[$s]=$req('save',$data+['id'=>$id,'revision'=>2],['X-User: other']);sd_assert($s===403,'Shared edit');
[$s]=$req('delete',['id'=>$id,'revision'=>1]);sd_assert($s===409,'Stale delete');[$s]=$req('save',$data+['id'=>$id,'revision'=>1]);sd_assert($s===409,'Stale save');
[$s]=$req('delete',['id'=>$id,'revision'=>2],['X-User: root','X-Role: admin']);sd_assert($s===200,'Admin deletion');
echo "PASS: actual MySQL API create/list/update/delete, sharing, owner/admin/student/viewer/anonymous permissions, CSRF and stale revision checks\n";
}finally{if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}foreach(glob($tmp.'/includes/*') as $f)unlink($f);foreach(glob($tmp.'/*') as $f)if(is_file($f))unlink($f);rmdir($tmp.'/includes');rmdir($tmp);}
