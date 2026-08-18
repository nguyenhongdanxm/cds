<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/timetable_store.php';
header('Content-Type: application/json; charset=utf-8');

$user = cds_user();
if (!$user) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Chưa đăng nhập.'], JSON_UNESCAPED_UNICODE); exit; }
$isAdmin = (($user['role'] ?? '') === 'admin');
$canManage = $isAdmin || cds_can_feature('cm.pccm','delete') || cds_can_feature('cm.pccm','edit');
if (!$canManage) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Bạn chưa có quyền quản lý lịch dạy thay.'], JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['tkb_sub_manage_csrf'])) $_SESSION['tkb_sub_manage_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['tkb_sub_manage_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = tkb_substitutions();
    usort($rows, static function($a,$b){
        $dateCmp = strcmp((string)($b['date']??''),(string)($a['date']??''));
        if ($dateCmp !== 0) return $dateCmp;
        $sessionCmp = strcmp((string)($a['session']??''),(string)($b['session']??''));
        if ($sessionCmp !== 0) return $sessionCmp;
        return ((int)($a['period']??0)) <=> ((int)($b['period']??0));
    });
    $out=[];
    foreach($rows as $row){
        $out[]=[
            'id'=>(string)($row['id']??''),
            'date'=>(string)($row['date']??''),
            'session'=>(string)($row['session']??''),
            'period'=>(int)($row['period']??0),
            'class'=>(string)($row['class']??''),
            'subject'=>(string)($row['subject']??''),
            'absent_teacher'=>(string)($row['absent_teacher']??''),
            'substitute_teacher'=>(string)($row['substitute_teacher']??''),
            'status'=>(string)($row['status']??'approved'),
        ];
    }
    echo json_encode(['ok'=>true,'csrf'=>$csrf,'rows'=>$out], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }
if (!hash_equals($csrf,(string)($_POST['csrf']??''))) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ.'], JSON_UNESCAPED_UNICODE); exit; }
$action=(string)($_POST['action']??'');
if ($action !== 'delete_many') { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Thao tác không hợp lệ.'], JSON_UNESCAPED_UNICODE); exit; }

$ids=array_values(array_unique(array_filter(array_map('strval',(array)($_POST['ids']??[])))));
if(!$ids){ echo json_encode(['ok'=>false,'message'=>'Chưa chọn lịch dạy thay cần xóa.'], JSON_UNESCAPED_UNICODE); exit; }
$idMap=array_fill_keys($ids,true);
$rows=tkb_substitutions();
$before=count($rows);
$rows=array_values(array_filter($rows, static fn($row)=>!isset($idMap[(string)($row['id']??'')])));
$deleted=$before-count($rows);
if($deleted<=0){ echo json_encode(['ok'=>false,'message'=>'Không tìm thấy lịch đã chọn.'], JSON_UNESCAPED_UNICODE); exit; }
if(!tkb_save(TKB_SUBSTITUTIONS_FILE,$rows)){ http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Không lưu được dữ liệu sau khi xóa.'], JSON_UNESCAPED_UNICODE); exit; }

echo json_encode(['ok'=>true,'deleted'=>$deleted,'message'=>'Đã xóa '.$deleted.' lịch dạy thay. Các hiển thị liên quan trên TKB và Tổng quan cũng được gỡ theo.'], JSON_UNESCAPED_UNICODE);