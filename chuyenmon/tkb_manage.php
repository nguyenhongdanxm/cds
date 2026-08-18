<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/timetable_store.php';

header('Content-Type: application/json; charset=utf-8');
$user = cds_user();
if (!$user || (($user['role'] ?? '') !== 'admin')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Chỉ Quản trị viên được sửa hoặc xóa thời khóa biểu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['tkb_manage_csrf'])) $_SESSION['tkb_manage_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['tkb_manage_csrf'];
$action = (string)($_REQUEST['action'] ?? 'list');

function tkb_manage_reply(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tkb_manage_save_weeks(array $weeks): bool {
    return tkb_save(TKB_WEEKS_FILE, array_values($weeks));
}

if ($action === 'list') {
    $rows = [];
    foreach (array_reverse(tkb_weeks()) as $w) {
        $rows[] = [
            'id'=>(string)($w['id'] ?? ''),
            'label'=>(string)($w['label'] ?? ''),
            'start_date'=>(string)($w['start_date'] ?? ''),
            'end_date'=>(string)($w['end_date'] ?? ''),
            'slots'=>count((array)($w['slots'] ?? [])),
            'active'=>!empty($w['active']),
        ];
    }
    tkb_manage_reply(['ok'=>true,'csrf'=>$csrf,'weeks'=>$rows]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') tkb_manage_reply(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405);
if (!hash_equals($csrf,(string)($_POST['csrf'] ?? ''))) tkb_manage_reply(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ, hãy tải lại trang.'],419);
$id = trim((string)($_POST['id'] ?? ''));
if ($id === '') tkb_manage_reply(['ok'=>false,'message'=>'Thiếu mã thời khóa biểu.'],422);

if ($action === 'update') {
    $label = trim((string)($_POST['label'] ?? ''));
    $start = trim((string)($_POST['start_date'] ?? ''));
    if ($label === '') tkb_manage_reply(['ok'=>false,'message'=>'Tên tuần không được để trống.'],422);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)) tkb_manage_reply(['ok'=>false,'message'=>'Ngày bắt đầu không hợp lệ.'],422);
    $weeks = tkb_weeks(); $found=false;
    foreach ($weeks as &$w) {
        if ((string)($w['id'] ?? '') !== $id) continue;
        $w['label']=$label;
        $w['start_date']=$start;
        $w['end_date']=date('Y-m-d',strtotime($start.' +5 days'));
        $w['updated_at']=date('c');
        $w['updated_by']=(string)($user['name'] ?? $user['username'] ?? 'admin');
        $found=true; break;
    }
    unset($w);
    if (!$found) tkb_manage_reply(['ok'=>false,'message'=>'Không tìm thấy thời khóa biểu cần sửa.'],404);
    if (!tkb_manage_save_weeks($weeks)) tkb_manage_reply(['ok'=>false,'message'=>'Không lưu được thay đổi.'],500);
    tkb_manage_reply(['ok'=>true,'message'=>'Đã cập nhật thời khóa biểu.']);
}

if ($action === 'delete') {
    $weeks = tkb_weeks(); $found=false; $wasActive=false;
    $kept=[];
    foreach ($weeks as $w) {
        if ((string)($w['id'] ?? '') === $id) { $found=true; $wasActive=!empty($w['active']); continue; }
        $kept[]=$w;
    }
    if (!$found) tkb_manage_reply(['ok'=>false,'message'=>'Không tìm thấy thời khóa biểu cần xóa.'],404);
    if ($wasActive && $kept) {
        foreach ($kept as &$w) $w['active']=false;
        unset($w);
        $kept[count($kept)-1]['active']=true;
    }
    if (!tkb_manage_save_weeks($kept)) tkb_manage_reply(['ok'=>false,'message'=>'Không xóa được thời khóa biểu.'],500);
    $subs = tkb_substitutions();
    $subs = array_values(array_filter($subs, static fn($r)=>(string)($r['week_id'] ?? '') !== $id));
    tkb_save(TKB_SUBSTITUTIONS_FILE,$subs);
    tkb_manage_reply(['ok'=>true,'message'=>'Đã xóa thời khóa biểu'.($wasActive&&$kept?' và chuyển bản gần nhất thành hiện hành.':'.')]);
}

tkb_manage_reply(['ok'=>false,'message'=>'Thao tác không hợp lệ.'],400);
