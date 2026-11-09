<?php
// Token-only match endpoint: no account data, no question-bank access, no PHP session lock.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/includes/keoco_match.php';
try{
 $method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true))throw new RuntimeException('Phương thức không hợp lệ.',405);
 $raw=$method==='POST'?file_get_contents('php://input',false,null,0,4097):'';if(strlen($raw)>4096)throw new RuntimeException('Yêu cầu quá lớn.',413);
 $input=$method==='POST'?json_decode($raw,true,8,JSON_THROW_ON_ERROR):$_GET;if(!is_array($input))throw new InvalidArgumentException('Dữ liệu không hợp lệ.');
 $action=(string)($input['action']??'state');if($method==='GET'&&$action!=='state')throw new RuntimeException('Cần gửi POST.',405);
 $token=preg_replace('/^Bearer /','',(string)($_SERVER['HTTP_X_KEOCO_TOKEN']??$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??''));
 echo json_encode(['ok'=>true,'state'=>kc_room((string)($input['code']??''),$token,$action,$input)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$code=$e->getCode();http_response_code(in_array($code,[403,404,405,409,410,413],true)?$code:400);echo json_encode(['ok'=>false,'message'=>$e instanceof JsonException?'Dữ liệu không hợp lệ.':$e->getMessage()],JSON_UNESCAPED_UNICODE);}
