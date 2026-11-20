<?php
define('CDS_SKIP_DRIVE_ACTION_REGISTRATION',true);
define('CDS_AUTH_LOGIN_ONLY',true);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/login_notice_store.php';
require_login();
header('Cache-Control: private, no-store');
$user=current_user();
$next=cds_ln_safe_next($_SESSION['cds_notice_next']??BASE_URL.'admin.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
    $json=str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json');$status=200;$result=['ok'=>false];
    if(($_POST['action']??'')!=='dismiss'||!hash_equals(cds_ln_csrf(),(is_string($_POST['csrf']??null)?$_POST['csrf']:''))){$status=403;$result['message']='Phiên làm việc không hợp lệ. Hãy tải lại trang.';}
    else{
        $id=is_string($_POST['notice_id']??null)?$_POST['notice_id']:'';$notice=cds_ln_load($id);
        if($id===''||!hash_equals((string)$notice['id'],$id)){$status=409;$result['message']='Thông báo đã thay đổi. Hãy tải lại trang.';}
        elseif(!cds_ln_dismiss($user,$id)){$status=500;$result['message']='Không lưu được trạng thái đóng.';}
        else{
            $gate=($_POST['context']??'')==='gate';$more=cds_ln_pending($user)!==null;
            $result=['ok'=>true,'more'=>$more,'next'=>$gate?($more?BASE_URL.'login_notice.php':$next):BASE_URL.'admin.php'];
            if($gate&&!$more)unset($_SESSION['cds_notice_next']);
        }
    }
    http_response_code($status);
    if($json){header('Content-Type: application/json; charset=utf-8');echo json_encode($result,JSON_UNESCAPED_UNICODE);exit;}
    if(!empty($result['ok'])){header('Location: '.$result['next'],true,303);exit;}
    exit(htmlspecialchars($result['message'],ENT_QUOTES,'UTF-8').' <a href="'.htmlspecialchars(BASE_URL.'login_notice.php',ENT_QUOTES,'UTF-8').'">Tải lại</a>');
}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
$loginNotice=cds_ln_pending($user);
if(!$loginNotice){unset($_SESSION['cds_notice_next']);header('Location: '.$next);exit;}
$loginNoticeGate=true;
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Thông báo – <?=e(SCHOOL_NAME)?></title><style>body{margin:0;min-height:100vh;background:radial-gradient(ellipse at 20% 10%,#dcecff,transparent 65%),linear-gradient(135deg,#edf5fb,#f9fafc);font:16px Arial,sans-serif;color:#173456;display:grid;place-items:center}.notice-welcome{text-align:center;padding:30px}.notice-welcome h1{font-size:30px}.notice-welcome p{color:#667b90}</style></head><body><main class="notice-welcome"><p>HỆ SINH THÁI NHÀ TRƯỜNG</p><h1><?=e(SCHOOL_NAME)?></h1><p>Chào mừng <?=e($user['name']??$user['username']??'')?> trở lại.</p></main><?php require __DIR__.'/includes/login_notice_popup.php';?></body></html>

