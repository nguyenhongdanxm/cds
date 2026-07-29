<?php
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_login();
require_module('noitru', 'view');
$user = current_user();

$tab = $_GET['tab'] ?? 'overview';
$allowed = ['overview','boarders','exits','meals','meal_summary','rice','attendance','duty','health','menu','stats'];
if (!in_array($tab, $allowed, true)) $tab = 'overview';
$tabPerms = [
    'overview'=>'nt.tongquan', 'boarders'=>'nt.danhsach', 'exits'=>'nt.ravao',
    'meals'=>'nt.baoan', 'attendance'=>'nt.diemdanh', 'duty'=>'nt.lichtruc',
    'meal_summary'=>'nt.thongke', 'rice'=>'nt.baoan',
    'health'=>'nt.yte', 'menu'=>'nt.thucdon', 'stats'=>'nt.thongke',
];
require_perm($tabPerms[$tab] ?? 'nt.tongquan');

function noitru_student_in_scope($studentId) {
    foreach (noitru_boarders_live() as $student) {
        if (($student['id'] ?? '') !== $studentId) continue;
        return can_class($student['class_name'] ?? '');
    }
    return false;
}

function noitru_require_student_scope($studentId) {
    if (noitru_student_in_scope($studentId)) return;
    flash('Bạn không có quyền thao tác với học sinh ngoài lớp được giao.', 'danger');
    header('Location: ' . BASE_URL . 'noitru.php');
    exit;
}

function noitru_require_global_scope() {
    if (allowed_classes() === null) return;
    flash('Chức năng này chỉ dành cho người có phạm vi toàn trường.', 'danger');
    header('Location: ' . BASE_URL . 'noitru.php');
    exit;
}

/* Danh sách → trang 4 tab riêng */
if ($tab === 'boarders') {
    header('Location: ' . BASE_URL . 'noitru_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $actionPerms = [
        'sync_from_csdl'=>'nt.danhsach',
        'exit_save'=>'nt.ravao', 'exit_status'=>'nt.ravao', 'exit_delete'=>'nt.ravao',
        'meals_generate'=>'nt.baoan', 'meals_save'=>'nt.baoan', 'meals_lock'=>'nt.baoan', 'meals_unlock'=>'nt.baoan',
        'meal_state'=>'nt.thongke',
        'att_save'=>'nt.diemdanh',
        'duty_save'=>'nt.lichtruc', 'duty_delete'=>'nt.lichtruc',
        'health_save'=>'nt.yte', 'health_delete'=>'nt.yte',
        'menu_save'=>'nt.thucdon',
        'rice_settings'=>'nt.baoan', 'rice_in'=>'nt.baoan', 'rice_issue'=>'nt.baoan', 'rice_delete'=>'nt.baoan',
    ];
    if (isset($actionPerms[$action])) {
        $requiredLevel = substr($action, -7) === '_delete' ? 'delete' : 'edit';
        require_perm_level($actionPerms[$action], $requiredLevel);
    }
    if (in_array($action, ['sync_from_csdl','meals_generate','meals_lock','meals_unlock','meal_state','duty_save','duty_delete','menu_save'], true)) {
        noitru_require_global_scope();
    }
    if (in_array($action, ['rice_settings','rice_in','rice_issue','rice_delete'], true)) {
        noitru_require_global_scope();
        $rice = noitru_rice_data();
        if ($action === 'rice_settings') {
            $rice['settings']['trua_grams'] = max(0, (float)($_POST['trua_grams'] ?? 180));
            $rice['settings']['toi_grams'] = max(0, (float)($_POST['toi_grams'] ?? 180));
            flash('Đã lưu định mức gạo.');
        } elseif ($action === 'rice_delete') {
            $id = trim($_POST['id'] ?? '');
            $rice['transactions'] = array_values(array_filter($rice['transactions'] ?? [], fn($r)=>($r['id']??'')!==$id));
            flash('Đã xóa giao dịch gạo.', 'warning');
        } else {
            $date = trim($_POST['date'] ?? date('Y-m-d'));
            $kg = max(0, (float)($_POST['kg'] ?? 0));
            if ($action === 'rice_issue' && !empty($_POST['auto'])) {
                $counts = noitru_meals_count_day($date);
                $meal = ($_POST['meal'] ?? 'trua') === 'toi' ? 'toi' : 'trua';
                $kg = round(($counts[$meal] ?? 0) * (float)($rice['settings'][$meal.'_grams'] ?? 180) / 1000, 3);
            }
            if ($kg > 0) $rice['transactions'][] = [
                'id'=>noitru_uid('rice'),'date'=>$date,'type'=>$action==='rice_in'?'in':'out',
                'kg'=>$kg,'meal'=>trim($_POST['meal'] ?? ''),'note'=>trim($_POST['note'] ?? ''),
                'by'=>$user['name'] ?? '','created_at'=>noitru_now(),
            ];
            flash($action === 'rice_in' ? 'Đã nhập kho gạo.' : 'Đã ghi xuất kho gạo.');
        }
        noitru_rice_save($rice);
        header('Location: ' . BASE_URL . 'noitru.php?tab=rice'); exit;
    }
    if (in_array($action, ['exit_status','exit_delete'], true)) {
        $targetId = trim($_POST['id'] ?? '');
        foreach (noitru_exits_all() as $targetRow) {
            if (($targetRow['id'] ?? '') === $targetId) {
                noitru_require_student_scope($targetRow['student_id'] ?? '');
                break;
            }
        }
    }
    if ($action === 'health_delete') {
        $targetId = trim($_POST['id'] ?? '');
        foreach (noitru_health_all() as $targetRow) {
            if (($targetRow['id'] ?? '') === $targetId) {
                noitru_require_student_scope($targetRow['student_id'] ?? '');
                break;
            }
        }
    }

    if ($action === 'sync_from_csdl') {
        $r = noitru_sync_from_csdl();
        flash($r['message'], $r['ok'] ? 'success' : 'danger');
        header('Location: ' . BASE_URL . 'noitru.php?tab=' . urlencode($tab));
        exit;
    }

    /* Exits */
    if ($action === 'exit_save') {
        $sid = trim($_POST['student_id'] ?? '');
        noitru_require_student_scope($sid);
        $bmap = [];
        foreach (noitru_boarders_live() as $s) $bmap[$s['id']] = $s;
        $st = $bmap[$sid] ?? null;
        noitru_exit_save([
            'id' => trim($_POST['id'] ?? ''),
            'student_id' => $sid,
            'student_name' => $st['name'] ?? '',
            'class_name' => $st['class_name'] ?? '',
            'from_date' => trim($_POST['from_date'] ?? ''),
            'to_date' => trim($_POST['to_date'] ?? ''),
            'reason' => trim($_POST['reason'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
            'status' => trim($_POST['status'] ?? 'pending'),
            'created_by' => $user['name'] ?? '',
        ]);
        flash('Đã lưu phiếu xin ra/vào KTX.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=exits');
        exit;
    }
    if ($action === 'exit_status') {
        $id = trim($_POST['id'] ?? '');
        $st = trim($_POST['status'] ?? '');
        if (in_array($st, ['approved','rejected','pending'], true)) {
            foreach (noitru_exits_all() as $r) {
                if (($r['id'] ?? '') === $id) {
                    $r['status'] = $st;
                    $r['approved_by'] = $user['name'] ?? '';
                    $r['approved_at'] = noitru_now();
                    noitru_exit_save($r);
                    break;
                }
            }
            flash($st === 'approved' ? 'Đã duyệt phiếu.' : ($st === 'rejected' ? 'Đã từ chối.' : 'Đã cập nhật.'));
        }
        header('Location: ' . BASE_URL . 'noitru.php?tab=exits');
        exit;
    }
    if ($action === 'exit_delete') {
        noitru_exit_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa phiếu.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=exits');
        exit;
    }

    /* Meals */
    if ($action === 'meals_generate') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $n = noitru_meals_generate_day($date);
        flash("Đã tạo/cập nhật báo ăn $n HS cho ngày $date (theo phiếu KTX nếu có).");
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date));
        exit;
    }
    if ($action === 'meals_save') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $meal = trim($_POST['meal'] ?? '');
        $className = trim($_POST['class_name'] ?? '');
        if (!in_array($meal, ['sang','trua','toi'], true) || $className === '' || !can_class($className)) {
            flash('Lớp hoặc bữa ăn không hợp lệ.', 'danger');
            header('Location: ' . BASE_URL . 'noitru.php?tab=meals'); exit;
        }
        $mealState = noitru_meal_state($date, $meal)['status'] ?? 'open';
        if ($mealState !== 'open' && allowed_classes() !== null) {
            flash($mealState === 'off' ? 'Bữa ăn này đã được thông báo nghỉ.' : 'Bữa ăn đã chốt, GVCN không thể sửa báo cáo.', 'warning');
            header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date) . '&class=' . urlencode($className) . '&meal=' . urlencode($meal)); exit;
        }
        $ids = $_POST['sid'] ?? [];
        $statuses = $_POST['meal_status'] ?? [];
        $eatCount = 0;
        foreach ($ids as $i => $sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            noitru_require_student_scope($sid);
            $student = null;
            foreach (noitru_boarders_live() as $candidate) {
                if (($candidate['id'] ?? '') === $sid) { $student = $candidate; break; }
            }
            if (!$student || ($student['class_name'] ?? '') !== $className) continue;
            $value = ($statuses[$i] ?? 'yes') === 'no' ? 'no' : 'yes';
            if ($value === 'yes') $eatCount++;
            $existing = noitru_meals_for_date($date)[$sid] ?? [];
            noitru_meal_upsert([
                'date' => $date,
                'student_id' => $sid,
                'sang' => $meal === 'sang' ? $value : ($existing['sang'] ?? 'yes'),
                'trua' => $meal === 'trua' ? $value : ($existing['trua'] ?? 'yes'),
                'toi' => $meal === 'toi' ? $value : ($existing['toi'] ?? 'yes'),
                'source' => 'gvcn',
                'reported_by' => $user['name'] ?? '',
                'force' => allowed_classes() === null,
            ]);
        }
        noitru_meal_report_upsert([
            'date'=>$date, 'class_name'=>$className, 'meal'=>$meal,
            'student_count'=>count($ids), 'eat_count'=>$eatCount,
            'absent_count'=>max(0, count($ids)-$eatCount),
            'reported_by'=>$user['name'] ?? '', 'status'=>'submitted',
        ]);
        flash('Đã gửi báo ăn ' . $className . ' – ' . (['sang'=>'bữa sáng','trua'=>'bữa trưa','toi'=>'bữa tối'][$meal]) . '.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date) . '&class=' . urlencode($className) . '&meal=' . urlencode($meal));
        exit;
    }
    if ($action === 'meals_lock') {
        $date = trim($_POST['date'] ?? '');
        noitru_meals_lock_day($date, true);
        flash("Đã chốt báo ăn ngày $date.");
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date));
        exit;
    }
    if ($action === 'meals_unlock') {
        $date = trim($_POST['date'] ?? '');
        noitru_meals_lock_day($date, false);
        flash("Đã mở khóa báo ăn ngày $date.", 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date));
        exit;
    }
    if ($action === 'meal_state') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $meal = trim($_POST['meal'] ?? '');
        $status = trim($_POST['status'] ?? 'open');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && in_array($meal, ['sang','trua','toi'], true) && in_array($status, ['open','locked','off'], true)) {
            noitru_meal_state_set($date, $meal, $status, $user['name'] ?? '');
            flash($status === 'locked' ? 'Đã chốt số liệu bữa ăn.' : ($status === 'off' ? 'Đã thông báo nghỉ bữa ăn.' : 'Đã mở lại để nhận báo cáo.'), $status === 'off' ? 'warning' : 'success');
        }
        header('Location: ' . BASE_URL . 'noitru.php?tab=meal_summary&date=' . urlencode($date)); exit;
    }

    /* Attendance */
    if ($action === 'att_save') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $shift = trim($_POST['shift'] ?? 'toi');
        $ids = $_POST['sid'] ?? [];
        $sts = $_POST['status'] ?? [];
        foreach ($ids as $i => $sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            noitru_require_student_scope($sid);
            noitru_att_upsert([
                'date' => $date,
                'shift' => $shift,
                'student_id' => $sid,
                'status' => $sts[$i] ?? 'present',
                'by' => $user['name'] ?? '',
            ]);
        }
        flash('Đã lưu điểm danh.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=attendance&date=' . urlencode($date) . '&shift=' . urlencode($shift));
        exit;
    }

    /* Duty */
    if ($action === 'duty_save') {
        noitru_duty_save([
            'id' => trim($_POST['id'] ?? ''),
            'date' => trim($_POST['date'] ?? ''),
            'shift' => trim($_POST['shift'] ?? 'toi'),
            'teacher_id' => trim($_POST['teacher_id'] ?? ''),
            'teacher_name' => trim($_POST['teacher_name'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
        ]);
        flash('Đã lưu lịch trực.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=duty');
        exit;
    }
    if ($action === 'duty_delete') {
        noitru_duty_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa ca trực.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=duty');
        exit;
    }

    /* Health */
    if ($action === 'health_save') {
        $sid = trim($_POST['student_id'] ?? '');
        noitru_require_student_scope($sid);
        $name = '';
        foreach (noitru_boarders_live() as $s) if ($s['id'] === $sid) { $name = $s['name']; break; }
        noitru_health_save([
            'id' => trim($_POST['id'] ?? ''),
            'student_id' => $sid,
            'student_name' => $name,
            'date' => trim($_POST['date'] ?? date('Y-m-d')),
            'type' => trim($_POST['type'] ?? 'kham'),
            'diagnosis' => trim($_POST['diagnosis'] ?? ''),
            'treatment' => trim($_POST['treatment'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
            'by' => $user['name'] ?? '',
        ]);
        flash('Đã lưu hồ sơ y tế.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health');
        exit;
    }
    if ($action === 'health_delete') {
        noitru_health_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa hồ sơ.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health');
        exit;
    }

    /* Menu */
    if ($action === 'menu_save') {
        $ws = trim($_POST['week_start'] ?? '');
        $days = ['mon','tue','wed','thu','fri','sat','sun'];
        $meals = [];
        foreach ($days as $d) {
            $meals[$d] = [
                'sang' => trim($_POST[$d . '_sang'] ?? ''),
                'trua' => trim($_POST[$d . '_trua'] ?? ''),
                'toi' => trim($_POST[$d . '_toi'] ?? ''),
            ];
        }
        noitru_menu_save(['week_start' => $ws, 'meals' => $meals]);
        flash('Đã lưu thực đơn tuần.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=menu&week=' . urlencode($ws));
        exit;
    }
}

$boarders = array_values(array_filter(noitru_boarders_live(), fn($student) => can_class($student['class_name'] ?? '')));
$stats = noitru_stats();
if (allowed_classes() !== null) {
    $stats['total'] = count($boarders);
    $stats['by_class'] = $stats['by_room'] = $stats['by_meal'] = [];
    foreach ($boarders as $student) {
        $className = $student['class_name'] ?: '(Chưa lớp)';
        $room = $student['room_ktx'] ?: '(Chưa phòng)';
        $meal = $student['meal_group'] ?: '(Chưa nhóm ăn)';
        $stats['by_class'][$className] = ($stats['by_class'][$className] ?? 0) + 1;
        $stats['by_room'][$room] = ($stats['by_room'][$room] ?? 0) + 1;
        $stats['by_meal'][$meal] = ($stats['by_meal'][$meal] ?? 0) + 1;
    }
}
$teachers = array_values(array_filter(csdl_teachers_all(), fn($t) => !empty($t['active'])));

$tabs = [
    'overview' => ['Tổng quan', 'bi-grid', BASE_URL . 'noitru.php?tab=overview'],
    'boarders' => ['Danh sách', 'bi-people', BASE_URL . 'noitru_list.php'],
    'exits' => ['Xin ra/vào KTX', 'bi-door-open', BASE_URL . 'noitru.php?tab=exits'],
    'meals' => ['Báo ăn', 'bi-egg-fried', BASE_URL . 'noitru.php?tab=meals'],
    'meal_summary' => ['Tổng hợp bữa ăn', 'bi-clipboard-data', BASE_URL . 'noitru.php?tab=meal_summary'],
    'rice' => ['Gạo', 'bi-box-seam', BASE_URL . 'noitru.php?tab=rice'],
    'attendance' => ['Điểm danh', 'bi-clipboard-check', BASE_URL . 'noitru.php?tab=attendance'],
    'duty' => ['Lịch trực', 'bi-calendar2-week', BASE_URL . 'noitru.php?tab=duty'],
    'health' => ['Y tế', 'bi-heart-pulse', BASE_URL . 'noitru.php?tab=health'],
    'menu' => ['Thực đơn', 'bi-journal-text', BASE_URL . 'noitru.php?tab=menu'],
    'stats' => ['Thống kê', 'bi-bar-chart', BASE_URL . 'noitru.php?tab=stats'],
];
$tabs = array_filter($tabs, fn($info, $key) => can_perm($tabPerms[$key] ?? ''), ARRAY_FILTER_USE_BOTH);
$canEditCurrent = can_edit_perm($tabPerms[$tab] ?? '');
$canDeleteCurrent = can_delete_perm($tabPerms[$tab] ?? '');

function nt_meal_label($v) {
    return ['yes'=>'Có','no'=>'Không','sick'=>'Bệnh','guest'=>'Khách'][$v] ?? $v;
}
function nt_meal_day_overview($date, array $students) {
    $mealMap = noitru_meals_for_date($date);
    $reports = noitru_meal_reports_for_date($date);
    $reportMap = [];
    foreach ($reports as $report) $reportMap[($report['class_name'] ?? '') . '|' . ($report['meal'] ?? '')] = $report;
    $classes = [];
    foreach ($students as $student) {
        $class = trim($student['class_name'] ?? '') ?: '(Chưa lớp)';
        $classes[$class][] = $student;
    }
    ksort($classes, SORT_NATURAL);
    $result = ['classes'=>$classes, 'meals'=>[]];
    foreach (['sang','trua','toi'] as $meal) {
        $state = noitru_meal_state($date, $meal);
        $info = ['state'=>$state['status'] ?? 'open', 'reported'=>[], 'missing'=>[], 'eat'=>0, 'absent'=>0, 'total'=>0, 'groups'=>[], 'absent_students'=>[]];
        foreach ($classes as $class=>$classStudents) {
            $report = $reportMap[$class . '|' . $meal] ?? null;
            if (!$report) {
                $info['missing'][] = $class;
                continue;
            }
            $info['reported'][$class] = $report;
            foreach ($classStudents as $student) {
                $value = $mealMap[$student['id']][$meal] ?? 'yes';
                $info['total']++;
                if ($value === 'no') {
                    $info['absent']++;
                    $info['absent_students'][] = ['name'=>$student['name'] ?? '', 'class'=>$class, 'group'=>$student['meal_group'] ?? ''];
                } else {
                    $info['eat']++;
                    $group = trim($student['meal_group'] ?? '') ?: '(Chưa mâm)';
                    $info['groups'][$group] = ($info['groups'][$group] ?? 0) + 1;
                }
            }
        }
        if ($info['state'] === 'off') {
            $info['eat'] = 0;
            $info['absent'] = $info['total'];
            $info['groups'] = [];
        }
        ksort($info['groups'], SORT_NATURAL);
        $result['meals'][$meal] = $info;
    }
    return $result;
}
function nt_att_label($v) {
    return ['present'=>'Có mặt','absent'=>'Vắng','late'=>'Muộn','excused'=>'Có phép'][$v] ?? $v;
}
if ($tab === 'meal_summary' && ($_GET['export'] ?? '') === 'kitchen') {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $overview = nt_meal_day_overview($date, $boarders);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bao-an-nha-bep-' . $date . '.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, [$school ?? 'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN']);
    fputcsv($fp, ['BÁO ĂN NHÀ BẾP NGÀY ' . date('d/m/Y', strtotime($date))]);
    fputcsv($fp, ['Bữa','Trạng thái','Số lớp đã báo','Lớp chưa báo','Số suất ăn','Số nghỉ']);
    $labels = ['sang'=>'Bữa sáng','trua'=>'Bữa trưa','toi'=>'Bữa tối'];
    foreach ($overview['meals'] as $meal=>$info) {
        fputcsv($fp, [$labels[$meal], $info['state']==='locked'?'Đã chốt':($info['state']==='off'?'Nghỉ':'Đang nhận'), count($info['reported']), implode(', ', $info['missing']), $info['eat'], $info['absent']]);
    }
    fputcsv($fp, []);
    fputcsv($fp, ['CHI TIẾT SUẤT ĂN THEO MÂM']);
    fputcsv($fp, ['Bữa','Mâm/nhóm ăn','Số suất']);
    foreach ($overview['meals'] as $meal=>$info) foreach ($info['groups'] as $group=>$count) fputcsv($fp, [$labels[$meal],$group,$count]);
    fputcsv($fp, []);
    fputcsv($fp, ['DANH SÁCH HỌC SINH NGHỈ ĂN']);
    fputcsv($fp, ['Bữa','Lớp','Học sinh','Mâm/nhóm ăn']);
    foreach ($overview['meals'] as $meal=>$info) foreach ($info['absent_students'] as $student) fputcsv($fp, [$labels[$meal],$student['class'],$student['name'],$student['group']]);
    fputcsv($fp, []);
    fputcsv($fp, ['Xuất lúc', date('d/m/Y H:i'), 'Người xuất', $user['name'] ?? '']);
    fclose($fp); exit;
}
if ($tab === 'meal_summary' && ($_GET['export'] ?? '') === 'csv') {
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $summary = noitru_meals_summary($from, $to);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tong-hop-bua-an-' . $from . '-' . $to . '.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['Loại','Đơn vị','Bữa sáng','Bữa trưa','Bữa tối']);
    foreach (['classes'=>'Lớp','groups'=>'Mâm'] as $key=>$label) {
        foreach ($summary[$key] as $name=>$row) fputcsv($fp, [$label,$name,$row['sang']??0,$row['trua']??0,$row['toi']??0]);
    }
    fclose($fp); exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quản lý nội trú – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>includes/noitru_layout.css?v=20260729-2" rel="stylesheet">
<style>
:root{--primary:#d63384;--pd:#a61e5c}
body{background:#f8f0f4}
.navbar{background:var(--pd)!important}
.stat{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 2px 12px rgba(0,0,0,.06);text-align:center}
.stat .n{font-size:1.5rem;font-weight:800;color:var(--primary)}
.nav-pills .nav-link{border-radius:999px;font-weight:600;color:#445;font-size:.85rem}
.nav-pills .nav-link.active{background:var(--primary)}
.card-soft{background:#fff;border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.table thead th{font-size:.7rem;text-transform:uppercase;color:#667;background:#fce8f0;white-space:nowrap}
.btn-nt{background:var(--primary);border-color:var(--primary);color:#fff}
.meal-class-list,.meal-tabs{display:flex;gap:.55rem;overflow-x:auto;padding-bottom:.35rem;scrollbar-width:thin}
.meal-class-list a,.meal-tabs a{flex:0 0 auto;min-height:44px;padding:.62rem 1rem;border:1px solid #dce5ec;border-radius:13px;background:#fff;color:#253342;text-decoration:none;font-weight:700}
.meal-class-list a.active,.meal-tabs a.active{background:#0ea5e9;border-color:#0ea5e9;color:#fff}
.meal-student-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.55rem;max-height:52vh;overflow:auto;padding:.2rem;scrollbar-gutter:stable}
.meal-student{display:flex;align-items:center;gap:.6rem;min-height:48px;padding:.65rem;border:1px solid #dce5ec;border-radius:12px;background:#fff;cursor:pointer}
.meal-student:has(input:checked){background:#fff2f2;border-color:#fca5a5;color:#b91c1c}.meal-student input{width:1.15rem;height:1.15rem}
.meal-summary-card{border:1px solid #dce5ec;border-radius:18px;background:#fff;padding:1rem;margin-bottom:1rem}
.meal-summary-head{display:flex;justify-content:space-between;gap:.8rem;align-items:center;flex-wrap:wrap}
.meal-summary-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-top:1rem}.meal-summary-stats>div{text-align:center;padding:.8rem;border-radius:14px;background:#f1f5f9}
.meal-summary-stats .eat{background:#ecfdf5;color:#15803d}.meal-summary-stats .absent{background:#fff1f2;color:#dc2626}
.meal-report-classes{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.75rem}.meal-report-classes span{padding:.25rem .55rem;border:1px solid #dce5ec;border-radius:999px;font-size:.78rem}
.meal-state-actions{display:flex;gap:.45rem;flex-wrap:wrap}.meal-state-actions form{display:inline-flex}
@media(max-width:767.98px){.meal-student-grid{grid-template-columns:1fr 1fr}.meal-summary-stats{gap:.4rem}.meal-summary-stats>div{padding:.65rem .25rem}}
@media(max-width:420px){.meal-student-grid{grid-template-columns:1fr}}
.btn-nt:hover{background:var(--pd);color:#fff}
.badge-room{background:#fce8f0;color:#a61e5c}
.badge-meal{background:#e8f5e9;color:#2e7d32}
<?php if (!$canEditCurrent): ?>
form[method="post"]{display:none!important}
<?php endif; ?>
</style>
</head>
<body class="nt-body">
<?php $nt_sec = $tab; require __DIR__ . '/includes/noitru_shell.php'; ?>
<main class="nt-main"><div class="nt-content">
<?php show_flash(); ?>

<div class="nt-page-head">
  <div>
    <h3 class="mb-0">Quản lý nội trú</h3>
    <div class="text-muted small">Nguồn HS: <strong>CSDL</strong> · <?= e(SCHOOL_NAME) ?></div>
  </div>
  <?php if (allowed_classes() === null && can_edit_perm('nt.danhsach')): ?>
  <form method="post" class="m-0">
    <input type="hidden" name="action" value="sync_from_csdl">
    <button class="btn btn-nt btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i> Đồng bộ từ CSDL</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($tab === 'overview'): ?>
  <?php $st = $stats; ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$st['total'] ?></div><div class="text-muted small">HS nội trú</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n" style="font-size:.95rem;padding-top:.4rem"><?= $st['last_sync_at'] ? e(date('d/m H:i', strtotime($st['last_sync_at']))) : 'Chưa' ?></div><div class="text-muted small">Đồng bộ CSDL</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= count($st['by_room']) ?></div><div class="text-muted small">Phòng</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= count(array_filter(noitru_exits_all(), fn($x)=>($x['status']??'')==='pending' && noitru_student_in_scope($x['student_id'] ?? ''))) ?></div><div class="text-muted small">Phiếu chờ duyệt</div></div></div>
  </div>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body"><h6>Theo lớp</h6>
      <?php foreach ($st['by_class'] as $k=>$n): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= $n ?></strong></div><?php endforeach; ?>
      <?php if (!$st['by_class']): ?><p class="text-muted small mb-0">Chưa có HS nội trú trong CSDL.</p><?php endif; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body"><h6>Theo phòng</h6>
      <?php foreach ($st['by_room'] as $k=>$n): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= $n ?></strong></div><?php endforeach; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body"><h6>Theo nhóm ăn</h6>
      <?php foreach ($st['by_meal'] as $k=>$n): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= $n ?></strong></div><?php endforeach; ?>
    </div></div></div>
  </div>

<?php elseif ($tab === 'exits'): ?>
  <?php
    $exits = array_values(array_filter(noitru_exits_all(), fn($row) => noitru_student_in_scope($row['student_id'] ?? '')));
    usort($exits, fn($a,$b) => strcmp($b['from_date']??'', $a['from_date']??''));
  ?>
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card card-soft"><div class="card-body">
        <h6 class="mb-3">Thêm phiếu xin ra KTX</h6>
        <form method="post">
          <input type="hidden" name="action" value="exit_save">
          <div class="mb-2"><label class="form-label small">Học sinh</label>
            <select name="student_id" class="form-select form-select-sm" required>
              <option value="">— Chọn —</option>
              <?php foreach ($boarders as $s): ?>
                <option value="<?= e($s['id']) ?>"><?= e($s['name']) ?> (<?= e($s['class_name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Từ ngày</label><input type="date" name="from_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
            <div class="col-6"><label class="form-label small">Đến ngày</label><input type="date" name="to_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
          </div>
          <div class="mb-2"><label class="form-label small">Lý do</label><input type="text" name="reason" class="form-control form-control-sm" required></div>
          <div class="mb-2"><label class="form-label small">Ghi chú</label><input type="text" name="note" class="form-control form-control-sm"></div>
          <button class="btn btn-nt btn-sm w-100" type="submit">Lưu phiếu</button>
        </form>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card card-soft"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead><tr><th>HS</th><th>Thời gian</th><th>Lý do</th><th>TT</th><th></th></tr></thead>
          <tbody>
          <?php if (!$exits): ?><tr><td colspan="5" class="text-muted text-center py-3">Chưa có phiếu.</td></tr>
          <?php else: foreach ($exits as $x):
            $st = $x['status']??'pending';
            $badge = $st==='approved'?'success':($st==='rejected'?'danger':'warning');
          ?>
            <tr>
              <td><strong><?= e($x['student_name']??'') ?></strong><br><span class="small text-muted"><?= e($x['class_name']??'') ?></span></td>
              <td class="small"><?= e($x['from_date']??'') ?> → <?= e($x['to_date']??'') ?></td>
              <td class="small"><?= e($x['reason']??'') ?></td>
              <td><span class="badge bg-<?= $badge ?>"><?= e($st) ?></span></td>
              <td class="text-nowrap">
                <?php if ($st==='pending'): ?>
                <form method="post" class="d-inline"><input type="hidden" name="action" value="exit_status"><input type="hidden" name="id" value="<?= e($x['id']) ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success" title="Duyệt">✓</button></form>
                <form method="post" class="d-inline"><input type="hidden" name="action" value="exit_status"><input type="hidden" name="id" value="<?= e($x['id']) ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline-danger" title="Từ chối">✗</button></form>
                <?php endif; ?>
                <?php if ($canDeleteCurrent): ?><form method="post" class="d-inline" onsubmit="return confirm('Xóa?')"><input type="hidden" name="action" value="exit_delete"><input type="hidden" name="id" value="<?= e($x['id']) ?>"><button class="btn btn-sm btn-outline-secondary">🗑</button></form><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div></div>
    </div>
  </div>

<?php elseif ($tab === 'meals'): ?>
  <?php
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $mealMap = noitru_meals_for_date($date);
    $meal = $_GET['meal'] ?? 'sang';
    if (!in_array($meal, ['sang','trua','toi'], true)) $meal = 'sang';
    $mealLabels = ['sang'=>'Bữa sáng','trua'=>'Bữa trưa','toi'=>'Bữa tối'];
    $mealClasses = [];
    foreach ($boarders as $student) {
      $classKey = trim($student['class_name'] ?? '') ?: '(Chưa lớp)';
      $mealClasses[$classKey][] = $student;
    }
    ksort($mealClasses, SORT_NATURAL);
    $className = trim($_GET['class'] ?? '');
    if ($className === '' || !isset($mealClasses[$className])) $className = array_key_first($mealClasses) ?? '';
    $classStudents = $className !== '' ? ($mealClasses[$className] ?? []) : [];
    $mealState = noitru_meal_state($date, $meal)['status'] ?? 'open';
    $mealReport = $className !== '' ? noitru_meal_report_for($date, $className, $meal) : null;
    $readOnly = $mealState !== 'open' && allowed_classes() !== null;
  ?>
  <div class="nt-page-head"><div><h4><i class="bi bi-fork-knife text-primary"></i> Báo ăn lớp chủ nhiệm</h4><div class="subtitle">Chỉ hiển thị học sinh thuộc lớp được giao</div></div></div>
  <div class="card card-soft mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end mb-3">
      <input type="hidden" name="tab" value="meals"><input type="hidden" name="class" value="<?= e($className) ?>"><input type="hidden" name="meal" value="<?= e($meal) ?>">
      <div class="col-12 col-md-5"><label class="form-label">Ngày báo ăn</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
      <div class="col-12 col-md-7"><label class="form-label">Người báo</label><div class="form-control bg-light"><i class="bi bi-person-check me-2"></i><?= e($user['name'] ?? '') ?><?= in_array('gvcn',$user['groups']??[],true)?' · GVCN':'' ?></div></div>
    </form>
    <label class="form-label">Chọn lớp</label>
    <div class="meal-class-list mb-3">
      <?php foreach ($mealClasses as $classKey=>$students): ?><a class="<?= $className===$classKey?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','date'=>$date,'class'=>$classKey,'meal'=>$meal])) ?>"><?= e($classKey) ?> (<?= count($students) ?>)</a><?php endforeach; ?>
    </div>
    <div class="meal-tabs">
      <?php foreach ($mealLabels as $mealKey=>$label): $state=noitru_meal_state($date,$mealKey)['status']??'open'; ?>
        <a class="<?= $meal===$mealKey?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','date'=>$date,'class'=>$className,'meal'=>$mealKey])) ?>"><i class="bi <?= $mealKey==='sang'?'bi-sunrise':($mealKey==='trua'?'bi-sun':'bi-moon-stars') ?>"></i> <?= e($label) ?><?= $state==='locked'?' · Đã chốt':($state==='off'?' · Nghỉ':'') ?></a>
      <?php endforeach; ?>
    </div>
  </div></div>
  <?php if ($mealState === 'locked'): ?><div class="alert alert-info"><i class="bi bi-lock"></i> <?= e($mealLabels[$meal]) ?> đã được người phụ trách chốt. Báo cáo hiện chỉ được xem.</div><?php endif; ?>
  <?php if ($mealState === 'off'): ?><div class="alert alert-warning"><i class="bi bi-calendar-x"></i> Người phụ trách đã thông báo nghỉ <?= mb_strtolower(e($mealLabels[$meal]),'UTF-8') ?> ngày này.</div><?php endif; ?>
  <form method="post" class="card card-soft">
    <input type="hidden" name="action" value="meals_save">
    <input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="class_name" value="<?= e($className) ?>"><input type="hidden" name="meal" value="<?= e($meal) ?>">
    <div class="card-body">
      <div class="d-flex justify-content-between gap-2 flex-wrap mb-3"><div><h5 class="mb-1"><?= e($className) ?> · <?= e($mealLabels[$meal]) ?></h5><div class="small text-muted"><?= $mealReport?'Đã báo bởi '.e($mealReport['reported_by']??'').' lúc '.e(date('d/m/Y H:i',strtotime($mealReport['updated_at']??$mealReport['created_at']??'now'))):'Chưa gửi báo cáo' ?></div></div>
        <?php if (!$readOnly && $classStudents): ?><div class="d-flex gap-2"><button class="btn btn-outline-success btn-sm" type="button" onclick="setMealAbsent(false)">Đủ cả lớp</button><button class="btn btn-outline-danger btn-sm" type="button" onclick="setMealAbsent(true)">Nghỉ cả lớp</button></div><?php endif; ?>
      </div>
      <div class="alert alert-light border py-2"><strong>Mặc định tất cả học sinh ăn.</strong> Chỉ tích vào học sinh nghỉ ăn.</div>
      <div class="meal-student-grid">
        <?php foreach ($classStudents as $i=>$student): $value=$mealMap[$student['id']][$meal]??'yes'; ?>
          <label class="meal-student"><input type="hidden" name="sid[]" value="<?= e($student['id']) ?>"><input class="form-check-input meal-absent" type="checkbox" <?= $value==='no'?'checked':'' ?> <?= $readOnly?'disabled':'' ?> onchange="this.nextElementSibling.value=this.checked?'no':'yes'"><input type="hidden" name="meal_status[]" value="<?= $value==='no'?'no':'yes' ?>"><span><strong><?= e($student['name']) ?></strong><small class="d-block text-muted"><?= e($student['class_name']) ?><?= ($student['meal_group']??'')!==''?' · Mâm '.e($student['meal_group']):'' ?></small></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($classStudents && !$readOnly): ?><div class="card-body border-top"><button class="btn btn-nt w-100" type="submit"><i class="bi bi-send-check"></i> <?= $mealReport?'Cập nhật':'Gửi' ?> báo ăn <?= e($mealLabels[$meal]) ?></button></div><?php endif; ?>
  </form>

<?php elseif ($tab === 'meal_summary'): ?>
  <?php
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $overview = nt_meal_day_overview($date, $boarders);
    $mealLabels = ['sang'=>'Bữa sáng','trua'=>'Bữa trưa','toi'=>'Bữa tối'];
    $rice = noitru_rice_data();
    $riceKg = (($overview['meals']['trua']['eat']??0)*(float)($rice['settings']['trua_grams']??180)+($overview['meals']['toi']['eat']??0)*(float)($rice['settings']['toi_grams']??180))/1000;
  ?>
  <div class="nt-page-head">
    <div><h4><i class="bi bi-fork-knife text-primary"></i> Báo cơm cả trường – <?= e(date('d/m/Y',strtotime($date))) ?></h4><div class="subtitle">Nhận báo cáo từ GVCN, chốt số liệu và báo nhà bếp</div></div>
    <div class="nt-actions">
      <a class="btn btn-outline-success" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meal_summary','date'=>$date,'export'=>'kitchen'])) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Xuất báo cáo nhà bếp</a>
      <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> In báo cáo</button>
    </div>
  </div>
  <form method="get" class="card card-soft mb-3"><div class="card-body d-flex align-items-end gap-2 flex-wrap"><input type="hidden" name="tab" value="meal_summary"><div><label class="form-label">Ngày chuẩn bị</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>"></div><button class="btn btn-nt">Xem tổng hợp</button></div></form>
  <?php foreach ($overview['meals'] as $mealKey=>$info): ?>
    <section class="meal-summary-card">
      <div class="meal-summary-head"><div><h5 class="mb-1"><i class="bi bi-fork-knife text-primary"></i> <?= e($mealLabels[$mealKey]) ?></h5><span class="badge <?= $info['state']==='locked'?'bg-success':($info['state']==='off'?'bg-danger':'bg-warning text-dark') ?>"><?= $info['state']==='locked'?'Đã chốt':($info['state']==='off'?'Nghỉ':'Đang nhận báo cáo') ?></span></div>
        <?php if (allowed_classes()===null && $canEditCurrent): ?><div class="meal-state-actions">
          <?php if ($info['state']!=='locked'): ?><form method="post" onsubmit="return confirm('Chốt số liệu <?= e(mb_strtolower($mealLabels[$mealKey],'UTF-8')) ?>? GVCN sẽ không thể sửa sau khi chốt.')"><input type="hidden" name="action" value="meal_state"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($mealKey) ?>"><input type="hidden" name="status" value="locked"><button class="btn btn-primary btn-sm"><i class="bi bi-lock"></i> Chốt</button></form><?php endif; ?>
          <?php if ($info['state']!=='off'): ?><form method="post" onsubmit="return confirm('Thông báo nghỉ <?= e(mb_strtolower($mealLabels[$mealKey],'UTF-8')) ?>? Số suất chuẩn bị sẽ về 0.')"><input type="hidden" name="action" value="meal_state"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($mealKey) ?>"><input type="hidden" name="status" value="off"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-calendar-x"></i> Nghỉ</button></form><?php endif; ?>
          <?php if ($info['state']!=='open'): ?><form method="post"><input type="hidden" name="action" value="meal_state"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($mealKey) ?>"><input type="hidden" name="status" value="open"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-unlock"></i> Mở lại</button></form><?php endif; ?>
        </div><?php endif; ?>
      </div>
      <div class="meal-report-classes"><?php foreach ($info['reported'] as $class=>$report): ?><span class="border-success text-success"><i class="bi bi-check-circle"></i> <?= e($class) ?></span><?php endforeach; ?></div>
      <div class="meal-summary-stats"><div><strong><?= $info['total'] ?></strong><small class="d-block">Đã báo</small></div><div class="eat"><strong><?= $info['eat'] ?></strong><small class="d-block">Suất ăn</small></div><div class="absent"><strong><?= $info['absent'] ?></strong><small class="d-block">Nghỉ ăn</small></div></div>
      <?php if ($info['missing']): ?><details class="alert alert-warning mt-3 mb-0"><summary><strong><?= count($info['missing']) ?> lớp chưa báo:</strong> <?= e(implode(', ',$info['missing'])) ?></summary><div class="small mt-2">Người phụ trách có thể liên hệ GVCN trước khi chốt.</div></details><?php else: ?><div class="alert alert-success py-2 mt-3 mb-0"><i class="bi bi-check-circle"></i> Tất cả lớp đã gửi báo cáo.</div><?php endif; ?>
      <?php if ($info['groups']): ?><details class="mt-3"><summary><strong>Chi tiết theo mâm/nhóm ăn</strong></summary><div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Mâm/nhóm</th><th>Số suất</th></tr></thead><tbody><?php foreach ($info['groups'] as $group=>$count): ?><tr><td><?= e($group) ?></td><td><strong><?= $count ?></strong></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
    </section>
  <?php endforeach; ?>
  <div class="alert alert-info d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong>Tổng gạo dự kiến trong ngày</strong><div class="small">(Trưa <?= $overview['meals']['trua']['eat'] ?> suất × <?= e($rice['settings']['trua_grams']??180) ?>g) + (Tối <?= $overview['meals']['toi']['eat'] ?> suất × <?= e($rice['settings']['toi_grams']??180) ?>g)</div></div><strong class="fs-4"><?= number_format($riceKg,2) ?> kg</strong></div>

<?php elseif ($tab === 'rice'): ?>
  <?php $rice=noitru_rice_data(); $riceBalance=noitru_rice_balance($rice); $riceRows=array_reverse($rice['transactions']??[]); ?>
  <div class="nt-page-head"><div><h4>Quản lý gạo</h4><div class="subtitle">Nhập kho, xuất kho và định mức theo suất ăn</div></div></div>
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-4"><div class="stat"><div class="n"><?= number_format($riceBalance,3) ?> kg</div><div class="small text-muted">Tồn kho hiện tại</div></div></div>
    <div class="col-6 col-md-4"><div class="stat"><div class="n"><?= e($rice['settings']['trua_grams']??180) ?> g</div><div class="small text-muted">Một HS / bữa trưa</div></div></div>
    <div class="col-6 col-md-4"><div class="stat"><div class="n"><?= e($rice['settings']['toi_grams']??180) ?> g</div><div class="small text-muted">Một HS / bữa tối</div></div></div>
  </div>
  <?php if (allowed_classes()===null && $canEditCurrent): ?>
  <div class="row g-3 mb-3">
    <div class="col-lg-4"><form method="post" class="card card-soft h-100"><div class="card-body">
      <input type="hidden" name="action" value="rice_settings"><h6>Định mức gạo</h6>
      <label class="form-label">Bữa trưa (gam/HS)</label><input type="number" step="1" min="0" name="trua_grams" class="form-control mb-2" value="<?= e($rice['settings']['trua_grams']??180) ?>">
      <label class="form-label">Bữa tối (gam/HS)</label><input type="number" step="1" min="0" name="toi_grams" class="form-control mb-3" value="<?= e($rice['settings']['toi_grams']??180) ?>">
      <button class="btn btn-nt w-100">Lưu định mức</button>
    </div></form></div>
    <div class="col-lg-4"><form method="post" class="card card-soft h-100"><div class="card-body">
      <input type="hidden" name="action" value="rice_in"><h6>Nhập kho</h6>
      <label class="form-label">Ngày</label><input type="date" name="date" class="form-control mb-2" value="<?= date('Y-m-d') ?>">
      <label class="form-label">Số kg</label><input type="number" step=".001" min=".001" name="kg" class="form-control mb-2" required>
      <label class="form-label">Ghi chú</label><input name="note" class="form-control mb-3" placeholder="Nguồn nhập, số phiếu…">
      <button class="btn btn-success w-100">Nhập kho</button>
    </div></form></div>
    <div class="col-lg-4"><form method="post" class="card card-soft h-100"><div class="card-body">
      <input type="hidden" name="action" value="rice_issue"><h6>Xuất kho theo suất ăn</h6>
      <label class="form-label">Ngày</label><input type="date" name="date" class="form-control mb-2" value="<?= date('Y-m-d') ?>">
      <label class="form-label">Bữa ăn</label><select name="meal" class="form-select mb-2"><option value="trua">Bữa trưa</option><option value="toi">Bữa tối</option></select>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="auto" value="1" id="riceAuto" checked><label class="form-check-label" for="riceAuto">Tự tính theo số suất đã báo</label></div>
      <label class="form-label">Hoặc nhập số kg thực xuất</label><input type="number" step=".001" min="0" name="kg" class="form-control mb-3">
      <button class="btn btn-warning w-100">Xuất kho</button>
    </div></form></div>
  </div>
  <?php endif; ?>
  <div class="card card-soft"><div class="card-body"><h6>Lịch sử kho gạo</h6><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Ngày</th><th>Loại</th><th>Số lượng</th><th>Bữa</th><th>Ghi chú</th><th></th></tr></thead><tbody>
    <?php foreach ($riceRows as $row): ?><tr><td><?= e(date('d/m/Y',strtotime($row['date']))) ?></td><td><span class="badge <?= ($row['type']??'')==='in'?'bg-success':'bg-warning text-dark' ?>"><?= ($row['type']??'')==='in'?'Nhập':'Xuất' ?></span></td><td><strong><?= number_format((float)$row['kg'],3) ?> kg</strong></td><td><?= e(($row['meal']??'')==='trua'?'Trưa':(($row['meal']??'')==='toi'?'Tối':'—')) ?></td><td><?= e($row['note']??'') ?></td><td><?php if ($canDeleteCurrent && allowed_classes()===null): ?><form method="post"><input type="hidden" name="action" value="rice_delete"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa giao dịch này?')"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
    <?php endforeach; if (!$riceRows): ?><tr><td colspan="6" class="text-center text-muted py-3">Chưa có giao dịch.</td></tr><?php endif; ?></tbody>
  </table></div></div></div>

<?php elseif ($tab === 'attendance'): ?>
  <?php
    $date = $_GET['date'] ?? date('Y-m-d');
    $shift = $_GET['shift'] ?? 'toi';
    $attMap = noitru_att_for($date, $shift);
    $shifts = ['sang'=>'Sáng','toi'=>'Tối','hoc_toi'=>'Học tối'];
  ?>
  <form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="tab" value="attendance">
    <div class="col-auto"><label class="form-label small mb-1">Ngày</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
    <div class="col-auto"><label class="form-label small mb-1">Ca</label>
      <select name="shift" class="form-select" onchange="this.form.submit()">
        <?php foreach ($shifts as $k=>$v): ?><option value="<?= $k ?>" <?= $shift===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
  </form>
  <form method="post">
    <input type="hidden" name="action" value="att_save">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <input type="hidden" name="shift" value="<?= e($shift) ?>">
    <div class="card card-soft"><div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead><tr><th>HS</th><th>Lớp</th><th>Phòng</th><th>Trạng thái</th></tr></thead>
        <tbody>
        <?php foreach ($boarders as $s):
          $a = $attMap[$s['id']] ?? null;
          $cur = $a['status'] ?? 'present';
        ?>
          <tr>
            <td><input type="hidden" name="sid[]" value="<?= e($s['id']) ?>"><strong><?= e($s['name']) ?></strong></td>
            <td><?= e($s['class_name']) ?></td>
            <td><?= e($s['room_ktx']) ?></td>
            <td>
              <select name="status[]" class="form-select form-select-sm" style="max-width:140px">
                <?php foreach (['present'=>'Có mặt','absent'=>'Vắng','late'=>'Muộn','excused'=>'Có phép'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $cur===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$boarders): ?><tr><td colspan="4" class="text-muted text-center py-3">Chưa có HS.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($boarders): ?><div class="card-body border-top"><button class="btn btn-nt" type="submit">Lưu điểm danh</button></div><?php endif; ?>
    </div>
  </form>

<?php elseif ($tab === 'duty'): ?>
  <?php
    $duties = noitru_duty_all();
    usort($duties, fn($a,$b) => strcmp($b['date']??'', $a['date']??''));
  ?>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Thêm ca trực</h6>
      <form method="post">
        <input type="hidden" name="action" value="duty_save">
        <div class="mb-2"><label class="form-label small">Ngày</label><input type="date" name="date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label small">Ca</label>
          <select name="shift" class="form-select form-select-sm"><option value="sang">Sáng</option><option value="toi" selected>Tối</option><option value="dem">Đêm</option></select>
        </div>
        <div class="mb-2"><label class="form-label small">Giáo viên (CSDL)</label>
          <select name="teacher_id" class="form-select form-select-sm" onchange="var o=this.options[this.selectedIndex];document.getElementById('tn').value=o.getAttribute('data-name')||''">
            <option value="">—</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= e($t['id']) ?>" data-name="<?= e($t['name']??'') ?>"><?= e($t['name']??'') ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="teacher_name" id="tn" value="">
        </div>
        <div class="mb-2"><label class="form-label small">Ghi chú</label><input type="text" name="note" class="form-control form-control-sm"></div>
        <button class="btn btn-nt btn-sm w-100">Lưu</button>
      </form>
    </div></div></div>
    <div class="col-md-8"><div class="card card-soft"><div class="table-responsive">
      <table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th>Ca</th><th>GV</th><th>Ghi chú</th><th></th></tr></thead><tbody>
      <?php foreach ($duties as $d): ?>
        <tr>
          <td><?= e($d['date']??'') ?></td>
          <td><?= e($d['shift']??'') ?></td>
          <td><?= e($d['teacher_name']??'') ?></td>
          <td class="small"><?= e($d['note']??'') ?></td>
          <td><?php if ($canDeleteCurrent): ?><form method="post" onsubmit="return confirm('Xóa?')"><input type="hidden" name="action" value="duty_delete"><input type="hidden" name="id" value="<?= e($d['id']) ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$duties): ?><tr><td colspan="5" class="text-muted text-center py-3">Chưa có lịch.</td></tr><?php endif; ?>
      </tbody></table>
    </div></div></div>
  </div>

<?php elseif ($tab === 'health'): ?>
  <?php
    $health = array_values(array_filter(noitru_health_all(), fn($row) => noitru_student_in_scope($row['student_id'] ?? '')));
    usort($health, fn($a,$b) => strcmp($b['date']??'', $a['date']??''));
  ?>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Ghi nhận y tế</h6>
      <form method="post">
        <input type="hidden" name="action" value="health_save">
        <div class="mb-2"><label class="form-label small">HS</label>
          <select name="student_id" class="form-select form-select-sm" required>
            <option value="">—</option>
            <?php foreach ($boarders as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label small">Ngày</label><input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label small">Loại</label>
          <select name="type" class="form-select form-select-sm"><option value="kham">Khám</option><option value="thuoc">Thuốc</option><option value="theo_doi">Theo dõi</option></select>
        </div>
        <div class="mb-2"><label class="form-label small">Chẩn đoán / tình trạng</label><input type="text" name="diagnosis" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small">Xử trí</label><input type="text" name="treatment" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small">Ghi chú</label><input type="text" name="note" class="form-control form-control-sm"></div>
        <button class="btn btn-nt btn-sm w-100">Lưu</button>
      </form>
    </div></div></div>
    <div class="col-md-8"><div class="card card-soft"><div class="table-responsive">
      <table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th>HS</th><th>Loại</th><th>Tình trạng</th><th>Xử trí</th><th></th></tr></thead><tbody>
      <?php foreach ($health as $h): ?>
        <tr>
          <td class="small"><?= e($h['date']??'') ?></td>
          <td><?= e($h['student_name']??'') ?></td>
          <td><?= e($h['type']??'') ?></td>
          <td class="small"><?= e($h['diagnosis']??'') ?></td>
          <td class="small"><?= e($h['treatment']??'') ?></td>
          <td><?php if ($canDeleteCurrent): ?><form method="post" onsubmit="return confirm('Xóa?')"><input type="hidden" name="action" value="health_delete"><input type="hidden" name="id" value="<?= e($h['id']) ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$health): ?><tr><td colspan="6" class="text-muted text-center py-3">Chưa có hồ sơ.</td></tr><?php endif; ?>
      </tbody></table>
    </div></div></div>
  </div>

<?php elseif ($tab === 'menu'): ?>
  <?php
    $week = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
    $menu = noitru_menu_for_week($week);
    $meals = $menu['meals'] ?? [];
    $dayLabels = ['mon'=>'Thứ 2','tue'=>'Thứ 3','wed'=>'Thứ 4','thu'=>'Thứ 5','fri'=>'Thứ 6','sat'=>'Thứ 7','sun'=>'CN'];
    $groups = $stats['by_meal'];
  ?>
  <div class="row g-3 mb-3">
    <div class="col-md-8">
      <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="menu">
        <div class="col-auto"><label class="form-label small mb-1">Tuần (thứ 2)</label><input type="date" name="week" class="form-control" value="<?= e($week) ?>" onchange="this.form.submit()"></div>
      </form>
      <form method="post" class="card card-soft"><div class="card-body">
        <input type="hidden" name="action" value="menu_save">
        <input type="hidden" name="week_start" value="<?= e($week) ?>">
        <h6 class="mb-3">Thực đơn tuần <?= e($week) ?></h6>
        <?php foreach ($dayLabels as $dk => $dl):
          $row = $meals[$dk] ?? ['sang'=>'','trua'=>'','toi'=>''];
        ?>
          <div class="border rounded p-2 mb-2">
            <div class="fw-semibold small mb-1"><?= $dl ?></div>
            <div class="row g-1">
              <div class="col-md-4"><input type="text" name="<?= $dk ?>_sang" class="form-control form-control-sm" placeholder="Sáng" value="<?= e($row['sang']??'') ?>"></div>
              <div class="col-md-4"><input type="text" name="<?= $dk ?>_trua" class="form-control form-control-sm" placeholder="Trưa" value="<?= e($row['trua']??'') ?>"></div>
              <div class="col-md-4"><input type="text" name="<?= $dk ?>_toi" class="form-control form-control-sm" placeholder="Tối" value="<?= e($row['toi']??'') ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-nt" type="submit">Lưu thực đơn</button>
      </div></form>
    </div>
    <div class="col-md-4">
      <div class="card card-soft"><div class="card-body">
        <h6>Nhóm ăn (từ CSDL)</h6>
        <p class="small text-muted">Sửa nhóm ăn trên hồ sơ HS ở CSDL, rồi đồng bộ.</p>
        <?php foreach ($groups as $g=>$n): ?>
          <div class="d-flex justify-content-between border-bottom py-1 small"><span><?= e($g) ?></span><strong><?= $n ?></strong></div>
        <?php endforeach; ?>
        <?php if (!$groups): ?><p class="text-muted small mb-0">Chưa có.</p><?php endif; ?>
      </div></div>
    </div>
  </div>

<?php elseif ($tab === 'stats'): ?>
  <?php
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $full = noitru_stats_full($from, $to);
  ?>
  <form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="tab" value="stats">
    <div class="col-auto"><label class="form-label small mb-1">Từ</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-auto"><label class="form-label small mb-1">Đến</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-auto"><button class="btn btn-nt">Xem</button></div>
  </form>
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['boarders'] ?></div><div class="text-muted small">HS nội trú</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['meals']['sang'] ?></div><div class="text-muted small">Suất sáng</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['meals']['trua'] ?></div><div class="text-muted small">Suất trưa</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['meals']['toi'] ?></div><div class="text-muted small">Suất tối</div></div></div>
  </div>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Điểm danh</h6>
      <?php foreach ($full['attendance'] as $k=>$v): ?>
        <div class="d-flex justify-content-between small border-bottom py-1"><span><?= e(nt_att_label($k)) ?></span><strong><?= (int)$v ?></strong></div>
      <?php endforeach; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Phiếu KTX</h6>
      <?php foreach ($full['exits'] as $k=>$v): ?>
        <div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= (int)$v ?></strong></div>
      <?php endforeach; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Y tế</h6>
      <div class="d-flex justify-content-between small"><span>Hồ sơ trong kỳ</span><strong><?= (int)$full['health'] ?></strong></div>
    </div></div></div>
  </div>
  <?php if ($full['meals']['days']): ?>
  <div class="card card-soft mt-3"><div class="card-body">
    <h6>Suất ăn theo ngày</h6>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th>Sáng</th><th>Trưa</th><th>Tối</th></tr></thead><tbody>
    <?php foreach ($full['meals']['days'] as $d=>$c): ?>
      <tr><td><?= e($d) ?></td><td><?= (int)$c['sang'] ?></td><td><?= (int)$c['trua'] ?></td><td><?= (int)$c['toi'] ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div></div>
  <?php endif; ?>

<?php endif; ?>
</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setMealAbsent(absent){
  document.querySelectorAll('.meal-absent:not(:disabled)').forEach(function(box){
    box.checked=absent;
    if(box.nextElementSibling) box.nextElementSibling.value=absent?'no':'yes';
  });
}
</script>
</body>
</html>
