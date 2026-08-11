<?php
/**
 * Điểm danh nội trú – mobile-first
 * Popup P/KP + lý do · Xác nhận · Xuất ảnh chia sẻ
 */
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_once 'includes/noitru_att_shifts.php';
require_login();
require_perm('nt.diemdanh');
$user = current_user();
$isAdmin = (($user['role'] ?? '') === 'admin');
$canManageAttendance = $isAdmin || can_perm_level('nt.diemdanh.quantri', 'edit');
$canDeleteAttendance = $isAdmin || can_perm_level('nt.diemdanh.quantri', 'delete');
$school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Trường';
$reporters = array_values(array_filter(csdl_teachers_all(), fn($teacher) => !empty($teacher['active']) && trim($teacher['name'] ?? '') !== ''));

function noitru_attendance_school_students() {
    $classMap = [];
    foreach (csdl_classes_all() as $classRow) {
        $classMap[(string)($classRow['id'] ?? '')] = trim((string)($classRow['name'] ?? ''));
    }
    $students = [];
    foreach (csdl_students_all() as $student) {
        if (isset($student['active']) && empty($student['active'])) continue;
        $student['class_name'] = trim((string)($student['class_name'] ?? ''));
        if ($student['class_name'] === '') {
            $student['class_name'] = $classMap[(string)($student['class_id'] ?? '')] ?? '';
        }
        $student['room_ktx'] = (string)($student['room_ktx'] ?? '');
        $students[] = $student;
    }
    csdl_sort_students($students);
    return $students;
}

$attendanceStudents = noitru_attendance_school_students();
noitru_att_ensure_legacy_reports(count($attendanceStudents));
$attendanceStudentMap = [];
foreach ($attendanceStudents as $attendanceStudent) {
    $attendanceStudentMap[(string)($attendanceStudent['id'] ?? '')] = $attendanceStudent;
}

$date  = $_GET['date']  ?? date('Y-m-d');
$shift = trim($_GET['shift'] ?? '');
$class = trim($_GET['class'] ?? '');
$q     = trim($_GET['q'] ?? '');
$view  = $_GET['view'] ?? 'diemdanh';
if ($view === 'settings' && !$canManageAttendance) {
    flash('Bạn không có quyền quản trị cài đặt buổi điểm danh.', 'danger');
    header('Location: ' . BASE_URL . 'noitru_attendance.php');
    exit;
}

$shifts = function_exists('noitru_att_shifts_active') ? noitru_att_shifts_active() : [
    'the_duc_sang' => 'Thể dục buổi sáng',
    'sang' => 'Điểm danh sáng',
    'toi' => 'Điểm danh tối',
    'hoc_toi' => 'Học tối',
];
if ($shift === '') {
    $shift = function_exists('noitru_att_shift_now') ? noitru_att_shift_now() : 'dot_xuat';
}
if ($shift === 'dot_xuat') $shifts['dot_xuat'] = 'Điểm danh đột xuất';
if (!isset($shifts[$shift])) $shift = 'dot_xuat';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    require_perm_level('nt.diemdanh', 'edit');
    if ($action === 'shifts_save' && !$canManageAttendance) {
        flash('Bạn không có quyền sửa cài đặt buổi điểm danh.', 'danger');
        header('Location: ' . BASE_URL . 'noitru_attendance.php'); exit;
    }
    if (in_array($action, ['att_delete_report','att_delete_dates'], true) && !$canDeleteAttendance) {
        flash('Bạn không có quyền xóa dữ liệu điểm danh.', 'danger');
        header('Location: ' . BASE_URL . 'noitru_attendance.php'); exit;
    }
    $redir = BASE_URL . 'noitru_attendance.php?' . http_build_query(array_filter([
        'date' => $_POST['date'] ?? $date,
        'shift' => $_POST['shift'] ?? $shift,
        'class' => ($_POST['class'] ?? $class) ?: null,
        'view' => $_POST['view'] ?? null,
    ]));

    if ($action === 'att_save') {
        $d = trim($_POST['date'] ?? $date);
        $sh = trim($_POST['shift'] ?? $shift);
        $payload = json_decode((string)($_POST['attendance_payload'] ?? ''), true);
        $payloadRows = is_array($payload) ? ($payload['students'] ?? []) : [];
        if (!$payloadRows) {
            $payloadRows=[];$ids=$_POST['sid']??[];$sts=$_POST['status']??[];$reasons=$_POST['reason']??[];$excuses=$_POST['excuse']??[];
            foreach($ids as $i=>$sid)$payloadRows[]=['id'=>$sid,'status'=>$sts[$i]??'present','reason'=>$reasons[$i]??'','excuse'=>$excuses[$i]??''];
        }
        $reporterName = $canManageAttendance ? trim($_POST['reporter'] ?? ($user['name'] ?? '')) : ($user['name'] ?? '');
        $generalNote = trim($_POST['general_note'] ?? '');
        $submitted=[];
        foreach ($payloadRows as $payloadRow) {
            $sid = trim((string)($payloadRow['id'] ?? ''));
            if ($sid === '') continue;
            $student = $attendanceStudentMap[$sid] ?? null;
            if (!$student) continue;
            $st = $payloadRow['status'] ?? 'present';
            if (!in_array($st, ['present','absent','late','excused'], true)) $st = 'present';
            $ex = (string)($payloadRow['excuse'] ?? '');
            if ($st === 'absent' && $ex === 'P') $st = 'excused';
            $submitted[$sid]=['status'=>$st,'excuse'=>$ex,'reason'=>trim((string)($payloadRow['reason']??''))];
        }
        $existing=noitru_att_for($d,$sh);$records=[];
        foreach($attendanceStudents as $student){
            $sid=(string)($student['id']??'');if($sid==='')continue;$value=$submitted[$sid]??($existing[$sid]??['status'=>'present','excuse'=>'','reason'=>'']);
            $records[] = [
                'date' => $d, 'shift' => $sh, 'student_id' => $sid,
                'status' => $value['status']??'present', 'excuse' => $value['excuse']??'',
                'reason' => trim((string)($value['reason'] ?? '')),
                'class_name' => $student['class_name'] ?? '',
                'by' => $reporterName,
                'saved_by' => $user['name'] ?? '',
                'report_note' => $generalNote,
            ];
        }
        $saved=noitru_att_save_report($d,$sh,$records,['by'=>$reporterName,'saved_by'=>$user['name']??'','report_note'=>$generalNote]);
        flash($saved?'Đã lưu đủ '.count($records).' học sinh.':'Không lưu được báo cáo điểm danh. Vui lòng thử lại.',$saved?'success':'danger');
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
            $student = $attendanceStudentMap[$sid] ?? null;
            if (!$student) continue;
            $studentClass = $student['class_name'] ?? '';
            noitru_att_upsert([
                'date'=>$d,'shift'=>$sh,'student_id'=>$sid,
                'status'=>$st,'excuse'=>'','reason'=>'','class_name'=>$studentClass,'by'=>$user['name']??'',
            ]);
        }
        flash($st === 'present' ? 'Đã đánh dấu đủ tất cả.' : 'Đã đánh dấu vắng tất cả.');
        header('Location: ' . $redir); exit;
    }

    if ($action === 'att_delete_report') {
        $deleteDate = trim($_POST['delete_date'] ?? '');
        $deleteShift = trim($_POST['delete_shift'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deleteDate) || $deleteShift === '') {
            flash('Báo cáo cần xoá không hợp lệ.', 'danger');
        } else {
            $deleted = noitru_att_delete([$deleteDate], $deleteShift);
            flash($deleted > 0 ? 'Đã xoá báo cáo, dữ liệu điểm danh và lịch sử của buổi đã chọn.' : 'Không tìm thấy dữ liệu báo cáo cần xoá.', $deleted > 0 ? 'success' : 'warning');
        }
        header('Location: ' . BASE_URL . 'noitru_attendance.php?view=history&history_date=' . rawurlencode($deleteDate)); exit;
    }

    if ($action === 'att_delete_dates') {
        $deleteDates = array_values(array_filter((array)($_POST['delete_dates'] ?? []), fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($d))));
        if (!$deleteDates) {
            flash('Bạn chưa chọn ngày cần xoá.', 'warning');
        } else {
            $deleted = noitru_att_delete($deleteDates);
            flash($deleted > 0 ? 'Đã xoá toàn bộ dữ liệu điểm danh và lịch sử của ' . count(array_unique($deleteDates)) . ' ngày đã chọn.' : 'Không tìm thấy dữ liệu trong các ngày đã chọn.', $deleted > 0 ? 'success' : 'warning');
        }
        header('Location: ' . BASE_URL . 'noitru_attendance.php?view=history'); exit;
    }

    if ($action === 'shifts_save' && function_exists('noitru_att_shifts_save')) {
        $ids = $_POST['sid'] ?? [];
        $labels = $_POST['label'] ?? [];
        $actives = $_POST['active'] ?? [];
        $sorts = $_POST['sort'] ?? [];
        $starts = $_POST['start'] ?? [];
        $ends = $_POST['end'] ?? [];
        $rows = [];
        foreach ($ids as $i => $id) {
            $rows[] = [
                'id' => $id, 'label' => $labels[$i] ?? $id,
                'active' => !empty($actives[$i]),
                'sort' => (int)($sorts[$i] ?? (($i+1)*10)),
                'start' => trim($starts[$i] ?? ''),
                'end' => trim($ends[$i] ?? ''),
            ];
        }
        $newId = trim($_POST['new_id'] ?? '');
        $newLabel = trim($_POST['new_label'] ?? '');
        if ($newId !== '' && $newLabel !== '') {
            $rows[] = ['id'=>$newId,'label'=>$newLabel,'active'=>true,'sort'=>999,'start'=>trim($_POST['new_start']??''),'end'=>trim($_POST['new_end']??'')];
        }
        noitru_att_shifts_save($rows);
        flash('Đã lưu cấu hình buổi điểm danh.');
        header('Location: ' . BASE_URL . 'noitru_attendance.php?view=settings'); exit;
    }
}

$boarders = $attendanceStudents;
$attMap = noitru_att_for($date, $shift);

$byClass = [];
foreach ($boarders as $s) {
    $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
    $byClass[$cn][] = $s;
}
uksort($byClass, 'csdl_compare_class_names');

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
$showReport = !empty($_GET['saved']);
if ($showReport) {
    foreach ($attMap as $savedAttendance) {
        if (trim($savedAttendance['by'] ?? '') !== '') {
            $reporter = $savedAttendance['by'];
            break;
        }
    }
}
$rate = $cntTotal > 0 ? round($cntPresent / $cntTotal * 100) : 100;

if ($view === 'history' && ($_GET['export'] ?? '') === 'excel') {
    $type = $_GET['period_type'] ?? 'week';
    $value = trim($_GET['period_value'] ?? '');
    $from = $to = date('Y-m-d');
    $periodLabel = '';
    $filePart = '';
    if ($type === 'week' && preg_match('/^(\d{4})-W(\d{2})$/', $value, $match)) {
        $weekStart = new DateTime();
        $weekStart->setISODate((int)$match[1], (int)$match[2], 1);
        $from = $weekStart->format('Y-m-d');
        $to = (clone $weekStart)->modify('+6 days')->format('Y-m-d');
        $periodLabel = 'Tuần ' . (int)$match[2] . ' · Từ ' . date('d/m/Y', strtotime($from)) . ' đến ' . date('d/m/Y', strtotime($to));
        $filePart = 'tuan-' . (int)$match[2] . '-' . $match[1];
    } elseif ($type === 'month' && preg_match('/^(\d{4})-(\d{2})$/', $value, $match)) {
        $from = $value . '-01';
        $to = date('Y-m-t', strtotime($from));
        $periodLabel = 'Tháng ' . (int)$match[2] . '/' . $match[1];
        $filePart = 'thang-' . $match[2] . '-' . $match[1];
    } elseif ($type === 'school_year' && preg_match('/^(\d{4})-(\d{4})$/', $value, $match) && (int)$match[2] === (int)$match[1] + 1) {
        $from = $match[1] . '-08-01';
        $to = $match[2] . '-07-31';
        $periodLabel = 'Năm học ' . $value . ' · Từ 01/08/' . $match[1] . ' đến 31/07/' . $match[2];
        $filePart = 'nam-hoc-' . $value;
    } else {
        flash('Khoảng thời gian xuất Excel không hợp lệ.', 'danger');
        header('Location: ' . BASE_URL . 'noitru_attendance.php?view=history');
        exit;
    }
    $allowedIds = array_fill_keys(array_column($boarders, 'id'), true);
    $exportRows = array_values(array_filter(noitru_att_all(), function ($row) use ($allowedIds, $from, $to) {
        $rowDate = $row['date'] ?? '';
        $isAbsent = !in_array($row['status'] ?? 'present', ['present','late'], true);
        return $isAbsent && isset($allowedIds[$row['student_id'] ?? '']) && $rowDate >= $from && $rowDate <= $to;
    }));
    require_once __DIR__ . '/includes/noitru_att_export.php';
    noitru_att_excel($exportRows, $boarders, $shifts, [
        'school' => $school,
        'period' => $periodLabel,
        'exported_at' => date('d/m/Y H:i'),
        'exported_by' => $user['name'] ?? '',
        'filename' => 'bao-cao-diem-danh-' . $filePart . '.xls',
    ]);
}

require __DIR__ . '/includes/noitru_att_view.php';
