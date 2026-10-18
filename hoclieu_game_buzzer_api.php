<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/includes/buzzer_access.php';
if(bz_token_read_request($_SERVER,$_GET)){
    require_once __DIR__.'/includes/config.php';
}else{
    require_once __DIR__.'/includes/auth.php';
}
require_once __DIR__.'/includes/database.php';
require_once __DIR__.'/includes/buzzer_store.php';
require_once __DIR__.'/includes/buzzer_access.php';
function bz_reply(int $status,array $data): void {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
try{
    $method=$_SERVER['REQUEST_METHOD']??'GET';$input=[];
    if($method==='POST'){
        if(stripos($_SERVER['CONTENT_TYPE']??'','multipart/form-data')===0){
            $input=$_POST;if(($input['action']??'')!=='audio_upload')bz_fail('Thao tác upload không hợp lệ.',422);
        }else{
            if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)bz_fail('Chỉ nhận dữ liệu JSON hoặc file âm thanh.',415);
            $raw=file_get_contents('php://input',false,null,0,196609);if(strlen($raw)>196608)bz_fail('Yêu cầu quá lớn.',413);
            $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($input))bz_fail('Dữ liệu không hợp lệ.',422);
        }
        $csrf=(string)($_SESSION['buzzer_csrf']??'');if($csrf===''||!hash_equals($csrf,(string)($input['csrf']??'')))bz_fail('Phiên đã hết hạn. Hãy tải lại trang.',403);
    }elseif($method!=='GET')bz_fail('Phương thức không hợp lệ.',405);
    $action=(string)($method==='POST'?($input['action']??''):($_GET['action']??'state'));
    if($action==='audio_upload')bz_fail('Trang đang dùng bản upload cũ. Hãy tải lại trang rồi dùng nút Tải lên & áp dụng mọi phòng.',409);
    if($method==='GET'&&!in_array($action,['state','list','audio','audio_settings','open_rooms','join_options'],true))bz_fail('Thao tác này cần POST.',405);
    $role=(string)($method==='POST'?($input['role']??''):($_GET['role']??''));
    $actor=['role'=>$role,'id'=>(string)($method==='POST'?($input['player_id']??''):($_GET['player_id']??'')),'token'=>(string)($_SERVER['HTTP_X_BUZZER_TOKEN']??'')];
    if($role==='host'){
        $checkVisibility=in_array($action,['create','list'],true);
        if($checkVisibility)require_once __DIR__.'/includes/quiz_paper_store.php';$u=current_user()??[];
        if(!bz_can_host($u,$checkVisibility))bz_fail('Bạn chưa có quyền sử dụng học liệu/trò chơi.',403);
        $actor['owner']=bz_owner($u);$actor['audio_admin']=($u['role']??'')==='admin';
    }
    // Không giữ khóa PHP session trong lúc chờ khóa phòng hoặc đọc trạng thái.
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    $db=cds_db();
    if($action==='create'){
        if($method!=='POST'||$role!=='host')bz_fail('Chỉ giáo viên được tạo phòng.',403);
        $options=[];if(isset($input['teams'])||!empty($input['room_code'])){if(empty($actor['audio_admin']))bz_fail('Chỉ quản trị viên được khai báo mã phòng và đội chơi.',403);if(!is_array($input['teams']??null))bz_fail('Danh sách đội không hợp lệ.',422);$options=['teams'=>$input['teams'],'room_code'=>$input['room_code']??''];}
        bz_reply(200,['ok'=>true]+bz_create($db,$actor['owner'],(string)($input['title']??'Bấm chuông'),$options));
    }
    if($action==='open_rooms')bz_reply(200,['ok'=>true,'rooms'=>bz_open_rooms($db)]);
    if($action==='list'){
        if($role!=='host')bz_fail('Không có quyền xem danh sách phòng.',403);
        bz_reply(200,['ok'=>true,'rooms'=>bz_list($db,$actor['owner'])]);
    }
    if(in_array($action,['audio_settings','audio_begin','audio_chunk','audio_finish','audio_cancel','audio_reset'],true)){
        require_once __DIR__.'/includes/buzzer_audio_global.php';
        if($role!=='host')bz_fail('Không có quyền cài âm thanh.',403);
        if($action==='audio_settings'){bz_global_audio_schema($db);bz_reply(200,['ok'=>true,'audio'=>bz_global_audio($db)]);}
        if($method!=='POST')bz_fail('Cần POST.',405);
        $result=$action==='audio_reset'?bz_audio_global_set($db,$actor,bz_audio_kind($input['kind']??''),null):bz_audio_upload_step($db,$actor,$action,$input);
        bz_reply(200,['ok'=>true]+$result);
    }
    $code=bz_code($method==='POST'?($input['code']??''):($_GET['code']??''));
    if($action==='join_options')bz_reply(200,['ok'=>true]+bz_join_options($db,$code));
    if($action==='audio'){
        require_once __DIR__.'/includes/buzzer_audio.php';$kind=bz_audio_kind($input['kind']??$_GET['kind']??'');
        if($action==='audio'){
            $file=bz_audio_file($db,$code,$actor,$kind);header('Content-Type: '.$file['mime']);header('Content-Length: '.filesize($file['path']));readfile($file['path']);exit;
        }
        if($method!=='POST')bz_fail('Cần POST.',405);
        bz_fail('Dùng nút Tải lên & áp dụng trong phần âm thanh dùng chung.',422);$result=[];
        bz_reply(200,['ok'=>true]+$result);
    }
    if($action==='state')bz_reply(200,['ok'=>true,'state'=>bz_read($db,$code,$actor)]);
    if($method!=='POST')bz_fail('Thao tác này cần POST.',405);
    bz_reply(200,['ok'=>true]+bz_write($db,$code,$actor,$action,$input));
}catch(BuzzerError $e){bz_reply($e->getCode()?:409,['ok'=>false,'message'=>$e->getMessage()]);}
catch(JsonException $e){bz_reply(422,['ok'=>false,'message'=>'Dữ liệu không hợp lệ.']);}
catch(Throwable $e){error_log('[CDS buzzer] '.$e->getMessage());bz_reply(503,['ok'=>false,'message'=>'Chưa kết nối được phòng bấm chuông. Hãy thử lại; thao tác chưa được xác nhận.']);}
