<?php
// Exercise real bounded cURL transport against a local-only fixture, never the remote provider.
if(PHP_SAPI==='cli-server'){
    $mode=$_GET['mode']??'';
    if($mode==='json'){header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;}
    if($mode==='redirect'){header('Location: http://127.0.0.1:1/never-follow',true,302);exit;}
    if($mode==='denied'){http_response_code(403);echo 'secret provider error';exit;}
    if($mode==='large'){for($i=0;$i<32;$i++)echo str_repeat('x',1024);exit;}
    header('Content-Type: application/pdf');echo '%PDF-1.4 synthetic fixture';exit;
}
require __DIR__.'/../chuyenmon/includes/academic_drive.php';
function verify($value,$message){if(!$value)throw new RuntimeException($message);}
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException($error);
$address=stream_socket_get_name($socket,false);fclose($socket);$log=tempnam(sys_get_temp_dir(),'acd-transport-');
$process=proc_open([PHP_BINARY,'-S',$address,__FILE__],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);$stream=null;
try{
    fclose($pipes[0]);$ready=false;for($i=0;$i<40;$i++){$connection=@stream_socket_client('tcp://'.$address,$errno,$error,.1);if($connection){fclose($connection);$ready=true;break;}usleep(50000);}verify($ready,'Fixture started');
    $metadata=acd_transport('http://'.$address.'/?mode=json',[]);verify($metadata['status']===200&&json_decode($metadata['body'],true)['ok'],'Metadata transport response');
    $metadata=acd_transport('http://'.$address.'/?mode=denied',[]);verify($metadata['status']===403,'Reused metadata handle resets request status');
    $metadata=acd_transport('http://'.$address.'/?mode=redirect',[]);verify($metadata['status']===302,'Reused metadata handle never follows redirects');
    $stream=fopen('php://temp','w+b');$status=acd_binary_transport('http://'.$address.'/?mode=pdf',[],$stream,1024);rewind($stream);verify($status===200&&stream_get_contents($stream)==='%PDF-1.4 synthetic fixture','Real bytes transported');fclose($stream);$stream=null;
    foreach(['redirect'=>302,'denied'=>403]as$mode=>$expected){$stream=fopen('php://temp','w+b');verify(acd_binary_transport('http://'.$address.'/?mode='.$mode,[],$stream,1024)===$expected,'Status preserved and no redirect followed');fclose($stream);$stream=null;}
    $stream=fopen('php://temp','w+b');$rejected=false;try{acd_binary_transport('http://'.$address.'/?mode=large',[],$stream,4096);}catch(RuntimeException $ex){$rejected=true;}verify($rejected&&ftell($stream)<=4096,'Oversized/chunked body stopped before writing past budget');
    echo "PASS academic content transport: real cURL stream, response status, no redirects and hard byte budget\n";
}finally{if(is_resource($stream))fclose($stream);if(is_resource($process)){proc_terminate($process);proc_close($process);}unlink($log);}
