<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/database.php';
require_once __DIR__.'/includes/buzzer_store.php';
require_once __DIR__.'/includes/buzzer_access.php';
function bz_reply(int $status,array $data): void {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
try{
    $method=$_SERVER['REQUEST_METHOD']??'GET';$input=[];
    if($method==='POST'){
        if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)bz_fail('Chỉ nhận dữ liệu JSON.',415);
        $raw=file_get_contents('php://input',false,null,0,4097);if(strlen($raw)>4096)bz_fail('Yêu cầu quá lớn.',413);
        $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($input))bz_fail('Dữ liệu không hợp lệ.',422);
        $csrf=(string)($_SESSION['buzzer_csrf']??'');if($csrf===''||!hash_equals($csrf,(string)($input['csrf']??'')))bz_fail('Phiên đã hết hạn. Hãy tải lại trang.',403);
    }elseif($method!=='GET')bz_fail('Phương thức không hợp lệ.',405);
    $action=(string)($method==='POST'?($input['action']??''):($_GET['action']??'state'));
    if($method==='GET'&&!in_array($action,['state','list'],true))bz_fail('Thao tác này cần POST.',405);
    $role=(string)($method==='POST'?($input['role']??''):($_GET['role']??''));
    $actor=['role'=>$role,'id'=>(string)($method==='POST'?($input['player_id']??''):($_GET['player_id']??'')),'token'=>(string)($_SERVER['HTTP_X_BUZZER_TOKEN']??'')];
    if($role==='host'){
        $checkVisibility=in_array($action,['create','list'],true);
        if($checkVisibility)require_once __DIR__.'/includes/quiz_paper_store.php';$u=current_user()??[];
        if(!bz_can_host($u,$checkVisibility))bz_fail('Bạn chưa có quyền sử dụng học liệu/trò chơi.',403);
        $actor['owner']=bz_owner($u);
    }
    // Không giữ khóa PHP session trong lúc chờ khóa phòng hoặc đọc trạng thái.
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    $db=cds_db();
    if($action==='create'){
        if($method!=='POST'||$role!=='host')bz_fail('Chỉ giáo viên được tạo phòng.',403);
        bz_reply(200,['ok'=>true]+bz_create($db,$actor['owner'],(string)($input['title']??'Bấm chuông')));
    }
    if($action==='list'){
        if($role!=='host')bz_fail('Không có quyền xem danh sách phòng.',403);
        bz_reply(200,['ok'=>true,'rooms'=>bz_list($db,$actor['owner'])]);
    }
    $code=bz_code($method==='POST'?($input['code']??''):($_GET['code']??''));
    if($action==='state')bz_reply(200,['ok'=>true,'state'=>bz_read($db,$code,$actor)]);
    if($method!=='POST')bz_fail('Thao tác này cần POST.',405);
    bz_reply(200,['ok'=>true]+bz_write($db,$code,$actor,$action,$input));
}catch(BuzzerError $e){bz_reply($e->getCode()?:409,['ok'=>false,'message'=>$e->getMessage()]);}
catch(JsonException $e){bz_reply(422,['ok'=>false,'message'=>'Dữ liệu không hợp lệ.']);}
catch(Throwable $e){error_log('[CDS buzzer] '.$e->getMessage());bz_reply(503,['ok'=>false,'message'=>'Chưa kết nối được phòng bấm chuông. Hãy thử lại; thao tác chưa được xác nhận.']);}
