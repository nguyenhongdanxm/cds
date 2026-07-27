<?php
/**
 * Điểm danh nội trú – mobile-first
 * Popup P/KP + lý do · Xác nhận · Xuất ảnh chia sẻ
 */
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_once 'includes/noitru_att_shifts.php';
require_login();
$user = current_user();
$school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Trường';

$date  = $_GET['date']  ?? date('Y-m-d');
$shift = $_GET['shift'] ?? '';
$class = trim($_GET['class'] ?? '');
$q     = trim($_GET['q'] ?? '');
$view  = $_GET['view'] ?? 'diemdanh';

$shifts = function_exists('noitru_att_shifts_active') ? noitru_att_shifts_active() : [
    'the_duc_sang' => 'Thể dục buổi sáng',
    'sang' => 'Điểm danh sáng',
    'toi' => 'Điểm danh tối',
    'hoc_toi' => 'Học tối',
];
if ($shift === '' || !isset($shifts[$shift])) {
    $shift = array_key_first($shifts) ?: 'toi';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redir = BASE_URL . 'noitru_attendance.php?' . http_build_query(array_filter([
        'date' => $_POST['date'] ?? $date,
        'shift' => $_POST['shift'] ?? $shift,
        'class' => ($_POST['class'] ?? $class) ?: null,
        'view' => $_POST['view'] ?? null,
    ]));

    if ($action === 'att_save') {
        $d = trim($_POST['date'] ?? $date);
        $sh = trim($_POST['shift'] ?? $shift);
        $ids = $_POST['sid'] ?? [];
        $sts = $_POST['status'] ?? [];
        $reasons = $_POST['reason'] ?? [];
        $excuses = $_POST['excuse'] ?? [];
        foreach ($ids as $i => $sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            $st = $sts[$i] ?? 'present';
            if (!in_array($st, ['present','absent','late','excused'], true)) $st = 'present';
            $ex = $excuses[$i] ?? '';
            if ($st === 'absent' && $ex === 'P') $st = 'excused';
            noitru_att_upsert([
                'date' => $d, 'shift' => $sh, 'student_id' => $sid,
                'status' => $st, 'excuse' => $ex,
                'reason' => trim($reasons[$i] ?? ''),
                'by' => $user['name'] ?? '',
            ]);
        }
        flash('Đã lưu điểm danh.');
        $sep = strpos($redir, '?') !== false ? '&' : '?';
        header('Location: ' . $redir . $sep . 'saved=1');
        exit;
    }

    if ($action === 'att_bulk') {
        $d = trim($_POST['date'] ?? $date);
        $sh = trim($_POST['shift'] ?? $shift);
        $st = trim($_POST['bulk_status'] ?? 'present');
        if (!in_array($st, ['present','absent'], true)) $st = 'present';
        foreach (($_POST['sid'] ?? []) as $sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            noitru_att_upsert([
                'date'=>$d,'shift'=>$sh,'student_id'=>$sid,
                'status'=>$st,'excuse'=>'','reason'=>'','by'=>$user['name']??'',
            ]);
        }
        flash($st === 'present' ? 'Đã đánh dấu đủ tất cả.' : 'Đã đánh dấu vắng tất cả.');
        header('Location: ' . $redir); exit;
    }

    if ($action === 'shifts_save' && function_exists('noitru_att_shifts_save')) {
        $ids = $_POST['sid'] ?? [];
        $labels = $_POST['label'] ?? [];
        $actives = $_POST['active'] ?? [];
        $sorts = $_POST['sort'] ?? [];
        $rows = [];
        foreach ($ids as $i => $id) {
            $rows[] = [
                'id' => $id, 'label' => $labels[$i] ?? $id,
                'active' => !empty($actives[$i]),
                'sort' => (int)($sorts[$i] ?? (($i+1)*10)),
            ];
        }
        $newId = trim($_POST['new_id'] ?? '');
        $newLabel = trim($_POST['new_label'] ?? '');
        if ($newId !== '' && $newLabel !== '') {
            $rows[] = ['id'=>$newId,'label'=>$newLabel,'active'=>true,'sort'=>999];
        }
        noitru_att_shifts_save($rows);
        flash('Đã lưu cấu hình buổi điểm danh.');
        header('Location: ' . BASE_URL . 'noitru_attendance.php?view=settings'); exit;
    }
}

$boarders = noitru_boarders_live();
$attMap = noitru_att_for($date, $shift);

$byClass = [];
foreach ($boarders as $s) {
    $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
    $byClass[$cn][] = $s;
}
ksort($byClass, SORT_NATURAL);

$list = $boarders;
if ($class !== '') {
    $list = array_values(array_filter($list, function ($s) use ($class) {
        $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
        return $cn === $class;
    }));
}
if ($q !== '') {
    $qq = mb_strtolower($q, 'UTF-8');
    $list = array_values(array_filter($list, function ($s) use ($qq) {
        $blob = mb_strtolower(($s['name']??'').' '.($s['code']??'').' '.($s['class_name']??'').' '.($s['room_ktx']??''), 'UTF-8');
        return mb_strpos($blob, $qq) !== false;
    }));
}

$cntTotal = count($list);
$cntPresent = $cntAbsent = 0;
$absentList = [];
foreach ($list as $s) {
    $a = $attMap[$s['id']] ?? null;
    $st = $a['status'] ?? 'present';
    if ($st === 'present' || $st === 'late') {
        $cntPresent++;
    } else {
        $cntAbsent++;
        $ex = $a['excuse'] ?? ($st === 'excused' ? 'P' : 'KP');
        $absentList[] = ['name'=>$s['name'],'class'=>$s['class_name'],'excuse'=>$ex,'reason'=>$a['reason']??''];
    }
}

function att_url($params = []) {
    $base = [
        'date' => $_GET['date'] ?? date('Y-m-d'),
        'shift' => $_GET['shift'] ?? '',
        'class' => $_GET['class'] ?? '',
        'q' => $_GET['q'] ?? '',
        'view' => $_GET['view'] ?? 'diemdanh',
    ];
    $p = array_filter(array_merge($base, $params), fn($v) => $v !== null && $v !== '');
    return BASE_URL . 'noitru_attendance.php' . ($p ? ('?' . http_build_query($p)) : '');
}

$tabs_main = [
    'overview' => ['Tổng quan', BASE_URL . 'noitru.php?tab=overview'],
    'boarders' => ['Danh sách', BASE_URL . 'noitru_list.php'],
    'exits' => ['Xin ra/vào', BASE_URL . 'noitru.php?tab=exits'],
    'meals' => ['Báo ăn', BASE_URL . 'noitru.php?tab=meals'],
    'attendance' => ['Điểm danh', BASE_URL . 'noitru_attendance.php'],
    'duty' => ['Lịch trực', BASE_URL . 'noitru.php?tab=duty'],
    'health' => ['Y tế', BASE_URL . 'noitru.php?tab=health'],
    'menu' => ['Thực đơn', BASE_URL . 'noitru.php?tab=menu'],
    'stats' => ['Thống kê', BASE_URL . 'noitru.php?tab=stats'],
];

$shiftLabel = $shifts[$shift] ?? $shift;
$dateLabel = date('d/m/Y', strtotime($date));
$weekdayVi = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'][(int)date('w', strtotime($date))];
$reporter = $user['name'] ?? 'GV trực';
$rate = $cntTotal > 0 ? round($cntPresent / $cntTotal * 100) : 100;
$showReport = !empty($_GET['saved']);

require __DIR__ . '/includes/noitru_att_view.php';
