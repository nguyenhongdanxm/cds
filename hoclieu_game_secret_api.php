<?php
require_once __DIR__.'/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
function sd_reply(int $s,array $d): void {http_response_code($s);echo json_encode($d,JSON_UNESCAPED_UNICODE);exit;}
if(!is_logged_in())sd_reply(401,['ok'=>false,'message'=>'Vui lòng đăng nhập.']);
$u=current_user()?:[];
if(empty($u['id']) || (($u['role']??'')!=='admin' && !can_perm_level('hl.xem','view')))sd_reply(403,['ok'=>false,'message'=>'Không có quyền học liệu.']);
require_once __DIR__.'/includes/secret_doors.php';
try{
    $method=$_SERVER['REQUEST_METHOD']??'GET';$in=[];
    if($method==='POST'){
        if(stripos($_SERVER['CONTENT_TYPE']??'','multipart/form-data')===0)$in=$_POST;
        else{$raw=file_get_contents('php://input',false,null,0,2097153);if(strlen($raw)>2097152)sd_reply(413,['ok'=>false,'message'=>'Dữ liệu quá lớn.']);$in=json_decode($raw,true);}
        if(!is_array($in)||!is_string($in['csrf']??null)||!hash_equals((string)($_SESSION['secret_doors_csrf']??''),$in['csrf'])||empty($_SESSION['secret_doors_csrf']))sd_reply(403,['ok'=>false,'message'=>'Phiên đã hết hạn. Tải lại trang.']);
        if(in_array($u['role']??'',['hs','student','phhs','parent'],true)||(function_exists('cds_is_viewer')&&cds_is_viewer()))sd_reply(403,['ok'=>false,'message'=>'Tài khoản chỉ được xem.']);
    }elseif($method!=='GET')sd_reply(405,['ok'=>false,'message'=>'Phương thức không hỗ trợ.']);
    $action=$method==='GET'?(string)($_GET['action']??'list'):(string)($in['action']??'');
    if($method==='GET'&&!in_array($action,['list','get'],true))sd_reply(405,['ok'=>false,'message'=>'Cần dùng POST.']);
    if($action==='import'){
        $f=$_FILES['excel']??[];
        if(($f['error']??4)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']??'')||($f['size']??0)>5242880||strtolower(pathinfo($f['name']??'',PATHINFO_EXTENSION))!=='xlsx')throw new InvalidArgumentException('Chọn file .xlsx tối đa 5 MB.');
        sd_reply(200,['ok'=>true,'questions'=>sd_excel($f['tmp_name'])]);
    }
    if(!in_array($action,['list','get','save','delete'],true))throw new InvalidArgumentException('Thao tác không hợp lệ.');
    require_once __DIR__.'/includes/database.php';$db=cds_db();
    // Viewer accounts must never initialize schema or write records.
    if(!(function_exists('cds_is_viewer')&&cds_is_viewer()))sd_schema($db);
    $admin=($u['role']??'')==='admin';
    if($action==='list'){
        $s=$db->prepare('SELECT id,owner_id,owner_name,title,shared,revision,updated_at FROM cds_secret_door_sets'.($admin?'':' WHERE owner_id=? OR shared=1').' ORDER BY updated_at DESC LIMIT 200');$s->execute($admin?[]:[(string)$u['id']]);$sets=$s->fetchAll();foreach($sets as &$set)$set['can_edit']=sd_can_edit($set,$u);unset($set);sd_reply(200,['ok'=>true,'sets'=>$sets]);
    }
    $id=filter_var($in['id']??$_GET['id']??0,FILTER_VALIDATE_INT);if($id===false||$id<0)throw new InvalidArgumentException('Mã bộ không hợp lệ.');$set=null;
    if($id){$s=$db->prepare('SELECT * FROM cds_secret_door_sets WHERE id=?'.($admin?'':' AND (owner_id=? OR shared=1)'));$s->execute($admin?[$id]:[$id,(string)$u['id']]);$set=$s->fetch();if(!$set)sd_reply(404,['ok'=>false,'message'=>'Không tìm thấy bộ câu hỏi.']);}
    if($action==='get') {if(!$set)throw new InvalidArgumentException('Chọn bộ câu hỏi.');$set['questions']=json_decode($set['questions_json'],true);unset($set['questions_json']);$set['can_edit']=sd_can_edit($set,$u);sd_reply(200,['ok'=>true,'set'=>$set]);}
    if($set&&!sd_can_edit($set,$u))sd_reply(403,['ok'=>false,'message'=>'Chỉ sửa hoặc xóa bộ của mình. Hãy lưu thành bản sao.']);
    $revision=(int)($in['revision']??0);
    if($action==='delete') {if(!$set)throw new InvalidArgumentException('Chọn bộ cần xóa.');$s=$db->prepare('DELETE FROM cds_secret_door_sets WHERE id=? AND revision=?');$s->execute([$id,$revision]);}
    else{
        $title=sd_text($in['title']??'',540);if($title===''||(function_exists('mb_strlen')?mb_strlen($title,'UTF-8'):strlen($title))>180)throw new InvalidArgumentException('Nhập tên bộ câu hỏi.');$rows=sd_questions($in['questions']??null);$json=json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if($id){$s=$db->prepare('UPDATE cds_secret_door_sets SET title=?,shared=?,questions_json=?,revision=revision+1 WHERE id=? AND revision=?');$s->execute([$title,!empty($in['shared'])?1:0,$json,$id,$revision]);}
        else{$s=$db->prepare('INSERT INTO cds_secret_door_sets (owner_id,owner_name,title,shared,questions_json) VALUES (?,?,?,?,?)');$s->execute([(string)$u['id'],(string)($u['teacher_name']??$u['name']??$u['username']??''),$title,!empty($in['shared'])?1:0,$json]);$id=(int)$db->lastInsertId();$revision=0;}
    }
    if($s->rowCount()!==1)sd_reply(409,['ok'=>false,'message'=>'Bộ đã được sửa ở cửa sổ khác. Tải lại bộ trước khi lưu.']);
    sd_reply(200,['ok'=>true,'id'=>$id,'revision'=>$revision+1]);
}catch(InvalidArgumentException $e){sd_reply(422,['ok'=>false,'message'=>$e->getMessage()]);}catch(Throwable $e){error_log('Secret doors: '.$e->getMessage());sd_reply(500,['ok'=>false,'message'=>'Chưa xử lý được ngân hàng câu hỏi. Kiểm tra kết nối MySQL hoặc thử lại.']);}
