<?php
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_login();
require_module('noitru', 'view');
$user = current_user();

$requestedTab = $_GET['tab'] ?? '';
$tab = $requestedTab !== '' ? $requestedTab : 'overview';
$allowed = ['overview','boarders','exits','meals','meal_summary','rice','attendance','duty','health','menu','stats'];
if (!in_array($tab, $allowed, true)) $tab = 'overview';
$tabPerms = [
    'overview'=>'nt.tongquan', 'boarders'=>'nt.danhsach', 'exits'=>'nt.ravao',
    'meals'=>'nt.baoan', 'attendance'=>'nt.diemdanh', 'duty'=>'nt.lichtruc',
    'meal_summary'=>'nt.buaan.tonghop', 'rice'=>'nt.gao',
    'health'=>'nt.yte', 'menu'=>'nt.thucdon', 'stats'=>'nt.thongke',
];
if ($requestedTab === '' && !can_perm($tabPerms[$tab])) {
    foreach ($tabPerms as $candidateTab => $candidatePermission) {
        if (can_perm($candidatePermission)) { $tab = $candidateTab; break; }
    }
}
require_perm($tabPerms[$tab] ?? 'nt.tongquan');

function noitru_attendance_students_all() {
    $classMap = [];
    foreach (csdl_classes_all() as $class) {
        $classMap[(string)($class['id'] ?? '')] = (string)($class['name'] ?? '');
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
    usort($students, function($a, $b) {
        $classCompare = strnatcasecmp((string)($a['class_name'] ?? ''), (string)($b['class_name'] ?? ''));
        return $classCompare !== 0 ? $classCompare : strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
    return $students;
}

function noitru_student_in_scope($studentId) {
    global $tab;
    $source = $tab === 'attendance' ? noitru_attendance_students_all() : noitru_boarders_live();
    foreach ($source as $student) {
        if (($student['id'] ?? '') !== $studentId) continue;
        // Điểm danh là dữ liệu dùng chung: quyền Xem/Sửa quyết định thao tác,
        // không giới hạn theo lớp chủ nhiệm.
        if ($tab === 'attendance') return true;
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
        'meals_generate'=>'nt.baoan', 'meals_save'=>'nt.baoan',
        'meals_lock'=>'nt.buaan.tonghop', 'meals_unlock'=>'nt.buaan.tonghop',
        'meal_state'=>'nt.buaan.tonghop', 'meal_settings'=>'nt.buaan.tonghop', 'meal_fill_missing'=>'nt.buaan.tonghop',
        'att_save'=>'nt.diemdanh',
        'duty_save'=>'nt.lichtruc', 'duty_delete'=>'nt.lichtruc',
        'duty_toggle'=>'nt.lichtruc', 'duty_auto'=>'nt.lichtruc', 'duty_copy'=>'nt.lichtruc',
        'duty_month_clear'=>'nt.lichtruc', 'duty_manager_save'=>'nt.lichtruc',
        'duty_settings_save'=>'nt.lichtruc', 'duty_group_save'=>'nt.lichtruc', 'duty_group_delete'=>'nt.lichtruc',
        'duty_swap'=>'nt.lichtruc', 'duty_assign_weekday'=>'nt.lichtruc', 'duty_manager_weekday'=>'nt.lichtruc',
        'duty_roster_save'=>'nt.lichtruc', 'duty_roster_delete'=>'nt.lichtruc',
        'health_save'=>'nt.yte', 'health_delete'=>'nt.yte',
        'medicine_save'=>'nt.yte', 'medicine_restock'=>'nt.yte', 'medicine_delete'=>'nt.yte',
        'menu_save'=>'nt.thucdon', 'menu_dish_add'=>'nt.thucdon', 'menu_dish_delete'=>'nt.thucdon',
        'menu_template_save'=>'nt.thucdon', 'menu_apply_template'=>'nt.thucdon', 'menu_copy_week'=>'nt.thucdon',
        'rice_settings'=>'nt.gao', 'rice_in'=>'nt.gao', 'rice_issue'=>'nt.gao', 'rice_delete'=>'nt.gao',
    ];
    if (isset($actionPerms[$action])) {
        $requiredLevel = substr($action, -7) === '_delete' || $action === 'duty_month_clear' ? 'delete' : 'edit';
        require_perm_level($actionPerms[$action], $requiredLevel);
    }
    if (in_array($action, ['sync_from_csdl','meals_generate','meals_lock','meals_unlock','meal_state','meal_settings','meal_fill_missing','duty_save','duty_delete','duty_toggle','duty_auto','duty_copy','duty_month_clear','duty_manager_save','duty_settings_save','duty_group_save','duty_group_delete','duty_swap','duty_assign_weekday','duty_manager_weekday','duty_roster_save','duty_roster_delete','menu_save','menu_dish_add','menu_dish_delete','menu_template_save','menu_apply_template','menu_copy_week'], true)) {
        noitru_require_global_scope();
    }
    if (in_array($action, ['rice_settings','rice_in','rice_issue','rice_delete'], true)) {
        noitru_require_global_scope();
        $rice = noitru_rice_data();
        if ($action === 'rice_settings') {
            $rice['settings']['sang_grams'] = max(0, (float)($_POST['sang_grams'] ?? 0));
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
        if (!in_array($meal, ['all','sang','trua','toi'], true) || $className === '' || !can_class($className)) {
            flash('Lớp hoặc bữa ăn không hợp lệ.', 'danger');
            header('Location: ' . BASE_URL . 'noitru.php?tab=meals'); exit;
        }
        $ids = $_POST['sid'] ?? [];
        $statuses = $_POST['meal_status'] ?? [];
        $submissionMode = ($_POST['submission_mode'] ?? '') === 'long' ? 'long' : 'regular';
        $targetMealsRaw = trim($_POST['target_meals'] ?? ($meal === 'all' ? 'sang,trua,toi' : $meal));
        $targetMeals = array_values(array_intersect(['sang','trua','toi'], array_filter(explode(',', $targetMealsRaw))));
        if (!$targetMeals) $targetMeals = $meal === 'all' ? ['sang','trua','toi'] : [$meal];
        $longFrom = $submissionMode === 'long' ? trim($_POST['long_from'] ?? $date) : $date;
        $longUntil = $submissionMode === 'long' ? trim($_POST['long_until'] ?? $longFrom) : $date;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $longFrom) || $longFrom < $date) $longFrom = $date;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $longUntil) || $longUntil < $longFrom) $longUntil = $longFrom;
        $maxLongDate = date('Y-m-d', strtotime($date . ' +60 days'));
        if ($longFrom > $maxLongDate) $longFrom = $maxLongDate;
        if ($longUntil > $maxLongDate) $longUntil = $maxLongDate;
        $targetDates = [];
        for ($cursor = $longFrom; $cursor <= $longUntil; $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'))) $targetDates[] = $cursor;

        if (allowed_classes() !== null) {
            foreach ($targetDates as $targetDate) foreach ($targetMeals as $targetMeal) {
                $state = noitru_meal_state($targetDate, $targetMeal)['status'] ?? 'open';
                if ($state !== 'open') {
                    flash('Có bữa ăn trong khoảng đã chọn đã khóa hoặc thông báo nghỉ. Báo cáo chưa được cập nhật.', 'warning');
                    header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date) . '&class=' . urlencode($className) . '&meal=' . urlencode($meal)); exit;
                }
            }
        }

        $studentMap = [];
        foreach (noitru_boarders_live() as $candidate) $studentMap[$candidate['id'] ?? ''] = $candidate;
        $validStudents = [];
        foreach ($ids as $i=>$sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            noitru_require_student_scope($sid);
            $student = $studentMap[$sid] ?? null;
            if (!$student || ($student['class_name'] ?? '') !== $className) continue;
            $validStudents[] = ['id'=>$sid, 'value'=>(($statuses[$i] ?? 'yes') === 'no' ? 'no' : 'yes')];
        }
        foreach ($targetDates as $targetDate) {
            $dayMap = noitru_meals_for_date($targetDate);
            foreach ($validStudents as $studentRow) {
                $sid = $studentRow['id'];
                $value = $studentRow['value'];
                $existing = $dayMap[$sid] ?? [];
                $values = [
                    'sang'=>$existing['sang'] ?? 'yes',
                    'trua'=>$existing['trua'] ?? 'yes',
                    'toi'=>$existing['toi'] ?? 'yes',
                ];
                foreach ($targetMeals as $targetMeal) $values[$targetMeal] = $value;
                noitru_meal_upsert([
                    'date'=>$targetDate, 'student_id'=>$sid,
                    'sang'=>$values['sang'], 'trua'=>$values['trua'], 'toi'=>$values['toi'],
                    'source'=>$submissionMode==='long'?'gvcn_long':'gvcn',
                    'reported_by'=>$user['name'] ?? '',
                    'force'=>allowed_classes() === null,
                ]);
            }
            foreach ($targetMeals as $targetMeal) {
                $eatCount = count(array_filter($validStudents, fn($row) => $row['value'] === 'yes'));
                noitru_meal_report_upsert([
                    'date'=>$targetDate, 'class_name'=>$className, 'meal'=>$targetMeal,
                    'student_count'=>count($validStudents), 'eat_count'=>$eatCount,
                    'absent_count'=>max(0, count($validStudents)-$eatCount),
                    'reported_by'=>$user['name'] ?? '', 'status'=>'submitted',
                ]);
            }
        }
        flash('Đã cập nhật báo ăn mới nhất của ' . $className . ($submissionMode==='long'?' từ '.date('d/m/Y',strtotime($longFrom)).' đến '.date('d/m/Y',strtotime($longUntil)):'') . '.');
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
    if ($action === 'meal_settings') {
        noitru_meal_settings_save($_POST);
        $rice = noitru_rice_data();
        foreach (['sang','trua','toi'] as $mealKey) {
            $rice['settings'][$mealKey . '_grams'] = max(0, (float)($_POST[$mealKey . '_grams'] ?? ($rice['settings'][$mealKey . '_grams'] ?? 0)));
        }
        noitru_rice_save($rice);
        flash('Đã lưu giờ khóa báo ăn và định mức gạo.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=meal_summary&date=' . urlencode(trim($_POST['date'] ?? date('Y-m-d')))); exit;
    }
    if ($action === 'meal_fill_missing') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $meal = trim($_POST['meal'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($meal, ['sang','trua','toi'], true)) {
            flash('Ngày hoặc bữa ăn không hợp lệ.', 'danger');
            header('Location: ' . BASE_URL . 'noitru.php?tab=meal_summary'); exit;
        }
        if ((noitru_meal_state($date, $meal)['status'] ?? 'open') !== 'open') {
            flash('Bữa ăn đã khóa hoặc thông báo nghỉ.', 'warning');
            header('Location: ' . BASE_URL . 'noitru.php?tab=meal_summary&date=' . urlencode($date)); exit;
        }
        $reportedClasses = [];
        foreach (noitru_meal_reports_for_date($date) as $report) {
            if (($report['meal'] ?? '') === $meal) $reportedClasses[$report['class_name'] ?? ''] = true;
        }
        $classes = [];
        foreach (noitru_boarders_live() as $student) {
            $class = trim($student['class_name'] ?? '') ?: '(Chưa lớp)';
            $classes[$class][] = $student;
        }
        $dayMap = noitru_meals_for_date($date);
        $filled = 0;
        foreach ($classes as $class=>$students) {
            if (isset($reportedClasses[$class])) continue;
            foreach ($students as $student) {
                $sid = $student['id'] ?? '';
                $existing = $dayMap[$sid] ?? [];
                noitru_meal_upsert([
                    'date'=>$date, 'student_id'=>$sid,
                    'sang'=>$meal==='sang'?'yes':($existing['sang']??'yes'),
                    'trua'=>$meal==='trua'?'yes':($existing['trua']??'yes'),
                    'toi'=>$meal==='toi'?'yes':($existing['toi']??'yes'),
                    'source'=>'manager_fill', 'reported_by'=>$user['name']??'', 'force'=>true,
                ]);
                $dayMap[$sid] = array_merge($existing, [$meal=>'yes']);
            }
            noitru_meal_report_upsert([
                'date'=>$date, 'class_name'=>$class, 'meal'=>$meal,
                'student_count'=>count($students), 'eat_count'=>count($students), 'absent_count'=>0,
                'reported_by'=>$user['name']??'', 'status'=>'submitted',
            ]);
            $filled++;
        }
        flash($filled ? 'Đã xác nhận đủ suất cho ' . $filled . ' lớp còn lại.' : 'Tất cả lớp đã báo cáo.', 'success');
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
    if (str_starts_with($action, 'duty_')) {
        $month = trim($_POST['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
        $section = trim($_POST['section'] ?? 'calendar');
        $teacherMap = [];
        foreach (array_filter(csdl_teachers_all(), fn($teacher) => !empty($teacher['active'])) as $teacher) {
            $teacherMap[(string)($teacher['id'] ?? '')] = (string)($teacher['name'] ?? '');
        }
        $rosterRows = noitru_duty_roster_all($teacherMap);
        $rosterTeacherMap = [];
        $rosterLimits = [];
        foreach ($rosterRows as $rosterRow) {
            $tid = (string)($rosterRow['teacher_id'] ?? '');
            if ($tid === '') continue;
            $rosterTeacherMap[$tid] = $teacherMap[$tid] ?? ($rosterRow['teacher_name'] ?? '');
            $rosterLimits[$tid] = (int)($rosterRow['max_per_month'] ?? 0);
        }
        if ($action === 'duty_toggle') {
            $date = trim($_POST['date'] ?? '');
            $teacherId = trim($_POST['teacher_id'] ?? '');
            if (str_starts_with($date, $month . '-') && isset($rosterTeacherMap[$teacherId])) {
                $existing = null;
                foreach (noitru_duty_all() as $row) {
                    if (($row['date'] ?? '') === $date && ($row['teacher_id'] ?? '') === $teacherId) { $existing = $row; break; }
                }
                if ($existing) noitru_duty_delete($existing['id'] ?? '');
                else {
                    $settings = noitru_duty_settings();
                    $dayCount = count(array_filter(noitru_duty_all(), fn($row) => ($row['date'] ?? '') === $date));
                    if ($dayCount >= (int)$settings['people_per_day'] && empty($_POST['force'])) {
                        flash('Ngày ' . date('d/m/Y', strtotime($date)) . ' đã đủ ' . $settings['people_per_day'] . ' người. Hãy xác nhận nếu vẫn muốn phân công thêm.', 'warning');
                    } else {
                        noitru_duty_save(['date'=>$date, 'shift'=>'ngay', 'teacher_id'=>$teacherId, 'teacher_name'=>$rosterTeacherMap[$teacherId], 'note'=>'']);
                    }
                }
            }
        } elseif ($action === 'duty_auto') {
            $settings = noitru_duty_settings();
            $perDay = (int)$settings['people_per_day'];
            $maxPerMonth = (int)$settings['max_per_month'];
            $teacherIds = array_keys($rosterTeacherMap);
            $monthRows = noitru_duty_for_month($month);
            $counts = array_fill_keys($teacherIds, 0);
            $sundayCounts = array_fill_keys($teacherIds, 0);
            $previousCounts = array_fill_keys($teacherIds, 0);
            $previousSundayCounts = array_fill_keys($teacherIds, 0);
            $assigned = [];
            foreach ($monthRows as $row) {
                $tid = $row['teacher_id'] ?? '';
                $date = $row['date'] ?? '';
                if (isset($counts[$tid])) $counts[$tid]++;
                if (isset($sundayCounts[$tid]) && (int)date('N', strtotime($date)) === 7) $sundayCounts[$tid]++;
                if ($tid !== '') $assigned[$date][$tid] = true;
            }
            $previousMonth = date('Y-m', strtotime($month . '-01 -1 month'));
            foreach (noitru_duty_for_month($previousMonth) as $row) {
                $tid = $row['teacher_id'] ?? '';
                $date = $row['date'] ?? '';
                if (isset($previousCounts[$tid])) $previousCounts[$tid]++;
                if (isset($previousSundayCounts[$tid]) && (int)date('N', strtotime($date)) === 7) $previousSundayCounts[$tid]++;
            }
            $days = (int)date('t', strtotime($month . '-01'));
            $added = 0;
            for ($day=1; $day<=$days; $day++) {
                $date = $month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                $isSunday = (int)date('N', strtotime($date)) === 7;
                $need = max(0, $perDay - count($assigned[$date] ?? []));
                for ($slot=0; $slot<$need; $slot++) {
                    $candidates = array_values(array_filter($teacherIds, function ($tid) use ($counts, $maxPerMonth, $rosterLimits, $assigned, $date) {
                        $limit = ($rosterLimits[$tid] ?? 0) > 0 ? $rosterLimits[$tid] : $maxPerMonth;
                        return ($counts[$tid] ?? 0) < $limit && !isset($assigned[$date][$tid]);
                    }));
                    usort($candidates, function ($a, $b) use ($isSunday, $sundayCounts, $previousSundayCounts, $counts, $previousCounts, $rosterTeacherMap) {
                        if ($isSunday) {
                            $cmp = (($sundayCounts[$a] ?? 0) + ($previousSundayCounts[$a] ?? 0)) <=> (($sundayCounts[$b] ?? 0) + ($previousSundayCounts[$b] ?? 0));
                            if ($cmp !== 0) return $cmp;
                        }
                        $cmp = (($counts[$a] ?? 0) + ($previousCounts[$a] ?? 0)) <=> (($counts[$b] ?? 0) + ($previousCounts[$b] ?? 0));
                        return $cmp !== 0 ? $cmp : strcasecmp($rosterTeacherMap[$a] ?? '', $rosterTeacherMap[$b] ?? '');
                    });
                    $picked = $candidates[0] ?? null;
                    if ($picked === null) break;
                    noitru_duty_save(['date'=>$date, 'shift'=>'ngay', 'teacher_id'=>$picked, 'teacher_name'=>$rosterTeacherMap[$picked], 'note'=>'Tự động phân công cân bằng']);
                    $assigned[$date][$picked] = true;
                    $counts[$picked] = ($counts[$picked] ?? 0) + 1;
                    if ($isSunday) $sundayCounts[$picked] = ($sundayCounts[$picked] ?? 0) + 1;
                    $added++;
                }
            }
            flash('Đã tự động bổ sung ' . $added . ' lượt trực.', $added ? 'success' : 'warning');
        } elseif ($action === 'duty_copy') {
            $sourceMonth = date('Y-m', strtotime($month . '-01 -1 month'));
            $targetDays = (int)date('t', strtotime($month . '-01'));
            $existingKeys = [];
            foreach (noitru_duty_for_month($month) as $row) $existingKeys[($row['date'] ?? '') . '|' . ($row['teacher_id'] ?? '')] = true;
            $copied = 0;
            foreach (noitru_duty_for_month($sourceMonth) as $row) {
                $day = min((int)substr((string)$row['date'], 8, 2), $targetDays);
                $date = $month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                $key = $date . '|' . ($row['teacher_id'] ?? '');
                if (isset($existingKeys[$key])) continue;
                unset($row['id'], $row['created_at'], $row['updated_at']);
                $row['date'] = $date;
                $row['note'] = trim(($row['note'] ?? '') . ' · Sao chép tháng trước', ' ·');
                noitru_duty_save($row);
                $existingKeys[$key] = true;
                $copied++;
            }
            flash('Đã sao chép ' . $copied . ' lượt trực từ tháng trước.', $copied ? 'success' : 'warning');
        } elseif ($action === 'duty_month_clear') {
            $deleted = noitru_duty_delete_month($month);
            flash('Đã xóa ' . $deleted . ' lượt trực trong tháng ' . date('m/Y', strtotime($month . '-01')) . '.', 'warning');
        } elseif ($action === 'duty_swap') {
            $rowAId = trim($_POST['row_a'] ?? '');
            $rowBId = trim($_POST['row_b'] ?? '');
            $rowA = $rowB = null;
            foreach (noitru_duty_for_month($month) as $row) {
                if (($row['id'] ?? '') === $rowAId) $rowA = $row;
                if (($row['id'] ?? '') === $rowBId) $rowB = $row;
            }
            if (!$rowA || !$rowB || $rowAId === $rowBId) {
                flash('Vui lòng chọn hai lượt trực khác nhau trong tháng.', 'warning');
            } elseif (($rowA['date'] ?? '') === ($rowB['date'] ?? '')) {
                flash('Hai người đang trực cùng ngày nên không cần đổi lịch.', 'warning');
            } else {
                $dateA = $rowA['date'];
                $dateB = $rowB['date'];
                $conflict = false;
                foreach (noitru_duty_for_month($month) as $row) {
                    $id = $row['id'] ?? '';
                    if ($id === $rowAId || $id === $rowBId) continue;
                    if ((($row['teacher_id'] ?? '') === ($rowA['teacher_id'] ?? '') && ($row['date'] ?? '') === $dateB)
                        || (($row['teacher_id'] ?? '') === ($rowB['teacher_id'] ?? '') && ($row['date'] ?? '') === $dateA)) { $conflict = true; break; }
                }
                if ($conflict) {
                    flash('Không thể đổi vì một trong hai người đã có lịch ở ngày nhận mới.', 'warning');
                } else {
                    $rowA['date'] = $dateB;
                    $rowB['date'] = $dateA;
                    $rowA['note'] = trim(($rowA['note'] ?? '') . ' · Đổi lịch', ' ·');
                    $rowB['note'] = trim(($rowB['note'] ?? '') . ' · Đổi lịch', ' ·');
                    noitru_duty_save($rowA);
                    noitru_duty_save($rowB);
                    flash('Đã đổi lịch trực giữa ' . ($rowA['teacher_name'] ?? '') . ' và ' . ($rowB['teacher_name'] ?? '') . '.');
                }
            }
        } elseif ($action === 'duty_assign_weekday') {
            $teacherId = trim($_POST['teacher_id'] ?? '');
            $weekdays = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['weekdays'] ?? [])), fn($day) => $day >= 1 && $day <= 7)));
            if (!isset($rosterTeacherMap[$teacherId]) || !$weekdays) {
                flash('Hãy chọn người trực và ít nhất một thứ trong tuần.', 'warning');
            } else {
                $settings = noitru_duty_settings();
                $perDay = max(1, (int)$settings['people_per_day']);
                $monthlyLimit = ($rosterLimits[$teacherId] ?? 0) > 0 ? $rosterLimits[$teacherId] : max(1, (int)$settings['max_per_month']);
                $rows = noitru_duty_for_month($month);
                $keys = [];
                $dailyCounts = [];
                foreach ($rows as $row) {
                    $date = (string)($row['date'] ?? '');
                    $keys[$date . '|' . ($row['teacher_id'] ?? '')] = true;
                    $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
                }
                $added = $skippedFull = 0;
                $teacherCount = count(array_filter($rows, fn($row) => ($row['teacher_id'] ?? '') === $teacherId));
                $days = (int)date('t', strtotime($month . '-01'));
                for ($day=1; $day<=$days; $day++) {
                    $date = $month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                    if (!in_array((int)date('N', strtotime($date)), $weekdays, true) || isset($keys[$date . '|' . $teacherId])) continue;
                    if ($teacherCount >= $monthlyLimit) break;
                    if (($dailyCounts[$date] ?? 0) >= $perDay) { $skippedFull++; continue; }
                    noitru_duty_save(['date'=>$date, 'shift'=>'ngay', 'teacher_id'=>$teacherId, 'teacher_name'=>$rosterTeacherMap[$teacherId], 'note'=>'Gán nhanh theo thứ']);
                    $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
                    $teacherCount++;
                    $added++;
                }
                $message = 'Đã gán nhanh ' . $added . ' lượt cho ' . $rosterTeacherMap[$teacherId] . '.';
                if ($skippedFull) $message .= ' Bỏ qua ' . $skippedFull . ' ngày đã đủ người.';
                flash($message, $added ? ($skippedFull ? 'warning' : 'success') : 'warning');
            }
        } elseif ($action === 'duty_manager_save') {
            $date = trim($_POST['date'] ?? '');
            if (str_starts_with($date, $month . '-')) {
                noitru_duty_manager_save($date, (array)($_POST['teacher_ids'] ?? []), $teacherMap, $_POST['note'] ?? '');
                flash('Đã cập nhật quản lý trực ngày ' . date('d/m/Y', strtotime($date)) . '.');
            }
        } elseif ($action === 'duty_manager_weekday') {
            $teacherId = trim($_POST['teacher_id'] ?? '');
            $weekdays = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['weekdays'] ?? [])), fn($day) => $day >= 1 && $day <= 7)));
            $append = !empty($_POST['append']);
            if (!isset($teacherMap[$teacherId]) || !$weekdays) {
                flash('Hãy chọn quản lý và ít nhất một thứ trong tuần.', 'warning');
            } else {
                $updated = 0;
                $days = (int)date('t', strtotime($month . '-01'));
                for ($day=1; $day<=$days; $day++) {
                    $date = $month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                    if (!in_array((int)date('N', strtotime($date)), $weekdays, true)) continue;
                    $ids = [$teacherId];
                    if ($append) {
                        $existingManager = noitru_duty_manager_for_date($date);
                        $ids = array_values(array_unique(array_merge((array)($existingManager['teacher_ids'] ?? []), $ids)));
                    }
                    noitru_duty_manager_save($date, $ids, $teacherMap, 'Gán nhanh theo thứ');
                    $updated++;
                }
                flash('Đã gán ' . $teacherMap[$teacherId] . ' quản lý ' . $updated . ' ngày trong tháng.');
            }
        } elseif ($action === 'duty_roster_save') {
            try {
                noitru_duty_roster_save(trim($_POST['teacher_id'] ?? ''), $teacherMap, (int)($_POST['max_per_month'] ?? 0), $_POST['note'] ?? '');
                flash('Đã lưu người trực trong danh sách.');
            } catch (Throwable $error) {
                flash($error->getMessage(), 'danger');
            }
        } elseif ($action === 'duty_roster_delete') {
            noitru_duty_roster_delete(trim($_POST['teacher_id'] ?? ''), $teacherMap);
            flash('Đã xóa người khỏi danh sách trực. Lịch đã phân công trước đây vẫn được giữ nguyên.', 'warning');
        } elseif ($action === 'duty_settings_save') {
            noitru_duty_settings_save($_POST);
            flash('Đã lưu cài đặt lịch trực.');
        } elseif ($action === 'duty_group_save') {
            try {
                noitru_duty_group_save($_POST['name'] ?? '', (array)($_POST['teacher_ids'] ?? []), $teacherMap);
                flash('Đã tạo nhóm trực.');
            } catch (Throwable $error) {
                flash($error->getMessage(), 'danger');
            }
        } elseif ($action === 'duty_group_delete') {
            noitru_duty_group_delete(trim($_POST['id'] ?? ''));
            flash('Đã xóa nhóm trực.', 'warning');
        }
        header('Location: ' . BASE_URL . 'noitru.php?' . http_build_query(['tab'=>'duty','section'=>$section,'month'=>$month]));
        exit;
    }

    /* Health */
    if ($action === 'health_save') {
        $sid = trim($_POST['student_id'] ?? '');
        noitru_require_student_scope($sid);
        $student = null;
        foreach (noitru_boarders_live() as $s) if (($s['id'] ?? '') === $sid) { $student = $s; break; }
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        if (!$student || $diagnosis === '') {
            flash('Vui lòng chọn học sinh và nhập chẩn đoán / triệu chứng.', 'danger');
            header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=record'); exit;
        }
        $type = trim($_POST['type'] ?? 'medicine');
        if (!in_array($type, ['medicine','first_aid','hospital','family_pickup'], true)) $type = 'medicine';
        $medicineItems = [];
        if ($type === 'medicine') {
            $medicineIds = (array)($_POST['medicine_id'] ?? []);
            $medicineQtys = (array)($_POST['medicine_qty'] ?? []);
            foreach ($medicineIds as $index=>$medicineId) {
                $medicineId = trim((string)$medicineId);
                $quantity = max(0, (int)($medicineQtys[$index] ?? 0));
                if ($medicineId === '' || $quantity < 1) continue;
                $medicine = noitru_medicine_find($medicineId);
                if (!$medicine || (int)($medicine['quantity'] ?? 0) < $quantity) {
                    flash('Thuốc được chọn không tồn tại hoặc không đủ số lượng.', 'danger');
                    header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=record'); exit;
                }
                $medicineItems[] = ['id'=>$medicineId,'name'=>$medicine['name'] ?? '','unit'=>$medicine['unit'] ?? '','quantity'=>$quantity];
            }
        }
        $recordId = noitru_health_save([
            'id' => trim($_POST['id'] ?? ''),
            'student_id' => $sid,
            'student_name' => $student['name'] ?? '',
            'class_name' => $student['class_name'] ?? '',
            'date' => trim($_POST['date'] ?? date('Y-m-d')),
            'type' => $type,
            'diagnosis' => $diagnosis,
            'treatment' => trim($_POST['treatment'] ?? ''),
            'medicines' => $medicineItems,
            'parent_contacted' => !empty($_POST['parent_contacted']),
            'note' => trim($_POST['note'] ?? ''),
            'by' => $user['name'] ?? '',
        ]);
        foreach ($medicineItems as $item) noitru_medicine_adjust($item['id'], -$item['quantity'], 'issue', 'Phát cho ' . ($student['name'] ?? '') . ' · ' . $diagnosis . ' · Hồ sơ ' . $recordId, $user['name'] ?? '');
        flash('Đã lưu hồ sơ y tế.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=record');
        exit;
    }
    if ($action === 'health_delete') {
        noitru_health_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa hồ sơ.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health');
        exit;
    }
    if ($action === 'medicine_save') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { flash('Vui lòng nhập tên thuốc.', 'danger'); header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=inventory'); exit; }
        $id = trim($_POST['id'] ?? '');
        $initialQty = max(0, (int)($_POST['quantity'] ?? 0));
        $savedId = noitru_medicine_save([
            'id'=>$id, 'name'=>$name, 'unit'=>trim($_POST['unit'] ?? 'viên') ?: 'viên',
            'expiry_date'=>trim($_POST['expiry_date'] ?? ''), 'low_stock'=>max(0, (int)($_POST['low_stock'] ?? 10)),
            'note'=>trim($_POST['note'] ?? ''), 'quantity'=>$id !== '' ? (int)(noitru_medicine_find($id)['quantity'] ?? 0) : 0,
        ]);
        if ($id === '' && $initialQty > 0) noitru_medicine_adjust($savedId, $initialQty, 'initial', 'Nhập kho ban đầu', $user['name'] ?? '');
        flash($id === '' ? 'Đã thêm thuốc mới.' : 'Đã cập nhật thông tin thuốc.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=inventory'); exit;
    }
    if ($action === 'medicine_restock') {
        $medicineId = trim($_POST['id'] ?? '');
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        try {
            if ($quantity < 1) throw new RuntimeException('Số lượng bổ sung phải lớn hơn 0.');
            noitru_medicine_adjust($medicineId, $quantity, 'restock', trim($_POST['note'] ?? '') ?: 'Bổ sung kho', $user['name'] ?? '');
            flash('Đã bổ sung thuốc vào kho.');
        } catch (Throwable $error) { flash($error->getMessage(), 'danger'); }
        header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=inventory'); exit;
    }
    if ($action === 'medicine_delete') {
        noitru_medicine_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa thuốc khỏi danh sách.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=inventory'); exit;
    }

    /* Menu */
    if ($action === 'menu_dish_add') {
        $ok = noitru_menu_dish_add($_POST['dish_name'] ?? '', $_POST['dish_category'] ?? 'breakfast');
        flash($ok ? 'Đã thêm món ăn.' : 'Tên món đã có hoặc chưa hợp lệ.', $ok ? 'success' : 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=menu&menu_view=dishes'); exit;
    }
    if ($action === 'menu_dish_delete') {
        noitru_menu_dish_delete(trim($_POST['dish_id'] ?? ''));
        flash('Đã xóa món ăn.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=menu&menu_view=dishes'); exit;
    }
    if ($action === 'menu_template_save') {
        $template = [];
        foreach (['mon','tue','wed','thu','fri','sat','sun'] as $d) foreach (['sang','trua','toi'] as $mealKey)
            $template[$d][$mealKey] = trim($_POST[$d . '_' . $mealKey] ?? '');
        noitru_menu_template_save($template);
        flash('Đã lưu thực đơn mẫu.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=menu&menu_view=template'); exit;
    }
    if ($action === 'menu_apply_template') {
        $ws = trim($_POST['week_start'] ?? '');
        $template = noitru_menu_config()['template'] ?? [];
        noitru_menu_save(['week_start'=>$ws, 'meals'=>$template]);
        flash('Đã lên thực đơn tuần từ mẫu.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=menu&menu_view=week&week=' . urlencode($ws)); exit;
    }
    if ($action === 'menu_copy_week') {
        $ws = trim($_POST['week_start'] ?? ''); $source = trim($_POST['source_week'] ?? '');
        $sourceMenu = noitru_menu_for_week($source);
        if ($sourceMenu) { noitru_menu_save(['week_start'=>$ws, 'meals'=>$sourceMenu['meals']??[]]); flash('Đã sao chép thực đơn từ tuần đã chọn.'); }
        else flash('Tuần nguồn chưa có thực đơn.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=menu&menu_view=week&week=' . urlencode($ws)); exit;
    }
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

$boarders = $tab === 'attendance'
    ? noitru_attendance_students_all()
    : array_values(array_filter(noitru_boarders_live(), fn($student) => can_class($student['class_name'] ?? '')));
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

if (in_array($tab, ['meals','meal_summary'], true) && in_array($_GET['export'] ?? '', ['month_breakfast','month_lunch_dinner'], true)) {
    $exportMonth = trim($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $exportMonth)) $exportMonth = date('Y-m');
    $exportStudents = $boarders;
    if ($tab === 'meals') {
        $exportClass = trim($_GET['class'] ?? '');
        if ($exportClass === '' || !can_class($exportClass)) {
            flash('Bạn không có quyền xuất báo cáo của lớp này.', 'danger');
            header('Location: ' . BASE_URL . 'noitru.php?tab=meals');
            exit;
        }
        $exportStudents = array_values(array_filter($boarders, fn($student) => ($student['class_name'] ?? '') === $exportClass));
    }
    try {
        require_once __DIR__ . '/includes/noitru_meal_month_export.php';
        nt_export_meal_month_xlsx(
            $exportStudents,
            $exportMonth,
            ($_GET['export'] ?? '') === 'month_breakfast' ? 'breakfast' : 'lunch_dinner',
            $user['name'] ?? ''
        );
    } catch (Throwable $error) {
        flash('Không thể tạo file Excel: ' . $error->getMessage(), 'danger');
        header('Location: ' . BASE_URL . 'noitru.php?tab=' . urlencode($tab));
        exit;
    }
}

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
if ($tab === 'meal_summary' && ($_GET['export'] ?? '') === 'excel') {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $overview = nt_meal_day_overview($date, $boarders);
    $rice = noitru_rice_data();
    $riceKg = 0;
    foreach (['trua','toi'] as $mealKey) {
        $riceKg += ($overview['meals'][$mealKey]['eat'] ?? 0) * (float)($rice['settings'][$mealKey . '_grams'] ?? 0) / 1000;
    }
    require __DIR__ . '/includes/noitru_meal_day_export.php';
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
if ($tab === 'rice' && ($_GET['export'] ?? '') === 'excel') {
    $periodType = $_GET['period_type'] ?? 'month';
    $month = $_GET['month'] ?? date('Y-m');
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    if ($periodType === 'month' && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $periodLabel = 'Tháng ' . date('m/Y', strtotime($from));
        $filePart = 'thang-' . $month;
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) $to = date('Y-m-d');
        $periodLabel = 'Từ ngày ' . date('d/m/Y', strtotime($from)) . ' đến ngày ' . date('d/m/Y', strtotime($to));
        $filePart = $from . '-' . $to;
    }
    $riceData = noitru_rice_data();
    $usage = noitru_rice_usage_summary($from, $to, $riceData);
    $manualIn = $manualOut = 0.0;
    foreach ($riceData['transactions'] ?? [] as $transaction) {
        $transactionDate = $transaction['date'] ?? '';
        if ($transactionDate < $from || $transactionDate > $to) continue;
        if (($transaction['type'] ?? '') === 'in') $manualIn += (float)($transaction['kg'] ?? 0);
        else $manualOut += (float)($transaction['kg'] ?? 0);
    }
    require_once __DIR__ . '/includes/noitru_rice_export.php';
    noitru_rice_excel($usage, [
        'school'=>defined('SCHOOL_NAME')?SCHOOL_NAME:'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN',
        'period'=>$periodLabel,
        'manual_in'=>$manualIn,
        'manual_out'=>$manualOut,
        'balance'=>noitru_rice_balance($riceData),
        'sang_grams'=>$riceData['settings']['sang_grams']??0,
        'trua_grams'=>$riceData['settings']['trua_grams']??180,
        'toi_grams'=>$riceData['settings']['toi_grams']??180,
        'from'=>$from,
        'to'=>$to,
        'rice_data'=>$riceData,
        'exported_at'=>date('d/m/Y H:i'),
        'exported_by'=>$user['name']??'',
        'filename'=>'bao-cao-gao-'.$filePart.'.xlsx',
    ]);
}
if ($tab === 'stats' && ($_GET['export'] ?? '') === 'csv') {
    $month = trim($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
    $from = $month . '-01'; $to = date('Y-m-t', strtotime($from));
    $report = noitru_stats_full($from, $to);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bao-cao-noi-tru-thang-' . $month . '.csv"');
    echo "\xEF\xBB\xBF"; $fp=fopen('php://output','w');
    fputcsv($fp, [defined('SCHOOL_NAME')?SCHOOL_NAME:'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN']);
    fputcsv($fp, ['BÁO CÁO TỔNG HỢP CÔNG TÁC NỘI TRÚ THÁNG '.date('m/Y',strtotime($from))]);
    fputcsv($fp, ['Ngày','Sáng','Trưa','Tối','Có mặt','Vắng','Muộn','Có phép','Phiếu KTX','Y tế','Ca trực','Gạo (kg)']);
    foreach ($report['daily'] as $date=>$row) fputcsv($fp,[date('d/m/Y',strtotime($date)),$row['meals']['sang'],$row['meals']['trua'],$row['meals']['toi'],$row['attendance']['present'],$row['attendance']['absent'],$row['attendance']['late'],$row['attendance']['excused'],$row['exits'],$row['health'],$row['duty'],number_format($row['rice_kg'],3,'.','')]);
    fputcsv($fp,[]); fputcsv($fp,['TỔNG HỢP']);
    fputcsv($fp,['Học sinh nội trú',$report['boarders']]);
    fputcsv($fp,['Suất sáng',$report['meals']['sang'],'Suất trưa',$report['meals']['trua'],'Suất tối',$report['meals']['toi']]);
    fputcsv($fp,['Vắng điểm danh',$report['attendance']['absent'],'Phiếu KTX',array_sum($report['exits']),'Hồ sơ y tế',$report['health'],'Ca trực',$report['duty'],'Gạo sử dụng (kg)',number_format($report['rice']['total_kg']??0,3,'.','')]);
    fputcsv($fp,[]); fputcsv($fp,['HỌC SINH NỘI TRÚ THEO LỚP']);
    fputcsv($fp,['Lớp','Số học sinh']); foreach($report['classes'] as $class=>$count) fputcsv($fp,[$class,$count]);
    fputcsv($fp,[]); fputcsv($fp,['Xuất lúc',date('d/m/Y H:i'),'Người xuất',$user['name']??'']); fclose($fp); exit;
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
<link href="<?= BASE_URL ?>includes/noitru_layout.css?v=20260801-stats1" rel="stylesheet">
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
.meal-tabs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:visible;gap:.45rem}.meal-tabs a{min-width:0;padding:.58rem .45rem;text-align:center;white-space:nowrap}
.meal-report-meta{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.6rem;align-items:end}.meal-quick-actions{display:flex;gap:.45rem;flex-wrap:nowrap}
.meal-confirm-list{max-height:34vh;overflow:auto}.meal-confirm-list>div{display:flex;justify-content:space-between;gap:1rem;padding:.38rem .1rem;border-bottom:1px solid #eef2f7}
.meal-student-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.45rem;max-height:52vh;overflow:auto;padding:.2rem;scrollbar-gutter:stable}
.meal-student{display:flex;align-items:center;gap:.55rem;min-height:42px;padding:.45rem .6rem;border:1px solid #dce5ec;border-radius:11px;background:#fff;cursor:pointer}
.meal-student:has(input:checked){background:#fff2f2;border-color:#fca5a5;color:#b91c1c}.meal-student input{width:1.15rem;height:1.15rem}
.meal-summary-card{border:1px solid #dce5ec;border-radius:18px;background:#fff;padding:1rem;margin-bottom:1rem}
.meal-summary-head{display:flex;justify-content:space-between;gap:.8rem;align-items:center;flex-wrap:wrap}
.meal-summary-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-top:1rem}.meal-summary-stats>div{text-align:center;padding:.8rem;border-radius:14px;background:#f1f5f9}
.meal-summary-stats .eat{background:#ecfdf5;color:#15803d}.meal-summary-stats .absent{background:#fff1f2;color:#dc2626}
.meal-report-classes{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.75rem}.meal-report-classes span{padding:.25rem .55rem;border:1px solid #dce5ec;border-radius:999px;font-size:.78rem}
.meal-state-actions{display:flex;gap:.45rem;flex-wrap:wrap}.meal-state-actions form{display:inline-flex}
.meal-summary-empty{text-align:center;color:#64748b;padding:1.4rem .5rem .65rem}
.meal-export-preview{display:block;width:min(100%,540px);max-height:62vh;object-fit:contain;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px}
.meal-export-modal .modal-content{border:0;border-radius:22px}.meal-export-modal .modal-body{background:#f8fafc}
.meal-export-modal .modal-footer{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}.meal-export-modal .modal-footer>*{margin:0}
.meal-missing-box{border:1px solid #f6c76b;border-radius:18px;background:#fffdf7;padding:1rem;margin-top:1rem}.meal-missing-row{padding:.65rem;border-radius:12px;background:#f8fafc;margin-top:.55rem}.meal-missing-chips{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.4rem}.meal-missing-chips span{padding:.25rem .58rem;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:.78rem;font-weight:700}
.overview-hero{position:relative;overflow:hidden;border-radius:22px;padding:1.35rem 1.45rem;background:linear-gradient(135deg,#9d174d 0%,#d63384 58%,#f472b6 100%);color:#fff;box-shadow:0 14px 34px rgba(166,30,92,.2)}
.overview-hero:after{content:"";position:absolute;width:220px;height:220px;border-radius:50%;right:-75px;top:-105px;background:rgba(255,255,255,.12)}
.overview-hero-date{display:inline-flex;align-items:center;gap:.45rem;padding:.35rem .7rem;border-radius:999px;background:rgba(255,255,255,.16);font-size:.8rem;font-weight:700}
.overview-hero h4{font-weight:800;margin:.8rem 0 .25rem}.overview-hero p{max-width:650px;margin:0;color:rgba(255,255,255,.83);font-size:.9rem}
.overview-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin:1rem 0}
.overview-metric{display:flex;align-items:center;gap:.75rem;min-height:92px;padding:1rem;border:1px solid #eef2f7;border-radius:18px;background:#fff;box-shadow:0 7px 22px rgba(15,23,42,.06)}
.overview-metric-icon{display:grid;place-items:center;flex:0 0 44px;width:44px;height:44px;border-radius:14px;font-size:1.25rem}.overview-metric strong{display:block;font-size:1.45rem;line-height:1.1;color:#172033}.overview-metric small{display:block;margin-top:.2rem;color:#64748b}
.overview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.overview-panel{overflow:hidden;border:1px solid #edf1f5;border-radius:20px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.overview-panel-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:1rem 1.1rem;border-bottom:1px solid #eef2f7}.overview-panel-title{display:flex;align-items:center;gap:.7rem}.overview-panel-title i{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;font-size:1.05rem}.overview-panel-title h6{margin:0;font-weight:800}.overview-panel-title small{display:block;margin-top:.12rem;color:#64748b}
.overview-panel-link{color:#a61e5c;text-decoration:none;font-size:.78rem;font-weight:800;white-space:nowrap}.overview-panel-body{padding:1rem 1.1rem}
.overview-att-main{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:.9rem;border-radius:16px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5)}.overview-att-main strong{font-size:2rem;line-height:1;color:#15803d}.overview-att-main span{color:#64748b;font-size:.82rem}
.overview-mini-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.55rem;margin-top:.75rem}.overview-mini{padding:.65rem .45rem;border-radius:13px;background:#f8fafc;text-align:center}.overview-mini strong{display:block;font-size:1.05rem}.overview-mini small{color:#64748b;font-size:.72rem}
.overview-list{display:grid;gap:.65rem}.overview-list-item{display:flex;align-items:flex-start;gap:.7rem;padding:.7rem;border-radius:14px;background:#f8fafc}.overview-list-date{flex:0 0 48px;padding:.4rem .25rem;border-radius:11px;background:#fff;text-align:center;box-shadow:0 2px 8px rgba(15,23,42,.06)}.overview-list-date strong{display:block;color:#a61e5c}.overview-list-date small{font-size:.68rem;color:#64748b}.overview-list-content{min-width:0;flex:1}.overview-list-content strong,.overview-list-content span{display:block}.overview-list-content span{color:#64748b;font-size:.8rem;white-space:normal}
.overview-duty-stack{display:grid;gap:.6rem}.overview-duty-shift{padding:.75rem;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc}.overview-duty-shift.current{border-color:#bae6fd;background:linear-gradient(135deg,#f0f9ff,#f8fcff)}.overview-duty-shift-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem;margin-bottom:.6rem}.overview-duty-status{display:flex;align-items:center;gap:.45rem;font-size:.78rem;font-weight:850;color:#334155}.overview-duty-shift.current .overview-duty-status{color:#0369a1}.overview-duty-date{display:block;margin-top:.12rem;color:#64748b;font-size:.7rem}.overview-duty-countdown{padding:.25rem .48rem;border:1px solid #bae6fd;border-radius:999px;background:#fff;color:#0369a1;font-size:.68rem;font-weight:800;white-space:nowrap}.overview-duty-role{display:grid;grid-template-columns:88px minmax(0,1fr);gap:.55rem;align-items:start;padding:.38rem 0;border-top:1px dashed #dbe4eb}.overview-duty-role:first-of-type{border-top:0}.overview-duty-role-label{display:flex;align-items:center;gap:.35rem;color:#64748b;font-size:.72rem;font-weight:750}.overview-duty-names{display:flex;gap:.32rem;flex-wrap:wrap}.overview-duty-name{display:inline-flex;align-items:center;min-height:27px;padding:.22rem .5rem;border-radius:999px;background:#fff;color:#0f172a;font-size:.73rem;font-weight:750;box-shadow:0 1px 4px rgba(15,23,42,.06)}.overview-duty-name.manager{background:#fff7ed;color:#9a3412}.overview-duty-unassigned{color:#94a3b8;font-size:.74rem;font-style:italic}.overview-duty-note{margin-top:.35rem;color:#64748b;font-size:.7rem}
.overview-menu{display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem}.overview-menu-item{min-height:116px;padding:.8rem;border-radius:15px;background:#fff7ed}.overview-menu-item:nth-child(2){background:#ecfdf5}.overview-menu-item:nth-child(3){background:#eef2ff}.overview-menu-item i{font-size:1.1rem;color:#ea580c}.overview-menu-item:nth-child(2) i{color:#16a34a}.overview-menu-item:nth-child(3) i{color:#4f46e5}.overview-menu-item small{display:block;margin:.25rem 0 .45rem;color:#64748b;font-weight:700}.overview-menu-item strong{display:block;font-size:.88rem;line-height:1.35;color:#273244}
.overview-empty{padding:1.25rem;text-align:center;color:#64748b}.overview-empty i{display:block;margin-bottom:.45rem;font-size:1.55rem;color:#cbd5e1}
.menu-subtabs{display:inline-flex;max-width:100%;overflow:auto;padding:5px;border-radius:15px;background:#eaf1f5}.menu-subtabs a{padding:.62rem 1rem;border-radius:12px;color:#526b83;text-decoration:none;font-weight:700;white-space:nowrap}.menu-subtabs a.active{background:#fff;color:#172334;box-shadow:0 2px 8px rgba(15,23,42,.08)}
.menu-panel{border:1px solid #dce6ed}.menu-add-row{display:grid;grid-template-columns:minmax(220px,1fr) 190px auto;gap:.55rem}.menu-add-row .form-control,.menu-add-row .form-select,.menu-add-row .btn{min-height:52px;border-radius:14px}.menu-chips{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.menu-chip{display:inline-flex;align-items:center;gap:.45rem;padding:.42rem .72rem;border-radius:999px;background:#e4f4fb;color:#06749f;font-weight:750;white-space:nowrap}.menu-chip.small{padding:.25rem .55rem;font-size:.76rem}.menu-chip form{display:inline-flex}.menu-chip button{padding:0;border:0;background:transparent;color:#ff4d5e;line-height:1}
.menu-toolbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;min-height:54px}.menu-toolbar .form-control{min-width:190px;border-radius:13px}.menu-grid-wrap{overflow:hidden;border:1px solid #dce6ed}.menu-grid{width:100%;min-width:980px;border-collapse:collapse;table-layout:fixed}.menu-grid th,.menu-grid td{border-right:1px solid #d9e4eb;border-bottom:1px solid #d9e4eb}.menu-grid thead th{height:72px;padding:.65rem;text-align:center;background:#f0f5f8;color:#1f3041;font-size:1rem}.menu-grid thead th:first-child{width:110px;text-align:left}.menu-grid thead small{display:block;color:#59728b;font-weight:500}.menu-grid tbody th{padding:.65rem;background:#fff;width:110px}.menu-grid tbody th span{display:inline-flex;padding:.25rem .65rem;border:1px solid #dce6ed;border-radius:999px}.menu-cell{height:92px;padding:.55rem;vertical-align:middle;text-align:center;cursor:pointer;background:#fff;transition:.15s}.menu-cell:hover{background:#f0f9ff}.menu-cell-content{display:flex;justify-content:center;align-items:center;gap:.35rem;flex-wrap:wrap}.menu-add-hint{color:#607995}.menu-grid-note{padding:1rem;color:#607995;font-size:.9rem}
@media(max-width:767.98px){
  .meal-student-grid{grid-template-columns:1fr 1fr;gap:.3rem;max-height:46vh}.meal-student{min-height:38px;padding:.32rem .45rem;gap:.4rem;font-size:.88rem}
  .meal-class-list{gap:.35rem;margin-bottom:.55rem!important}.meal-class-list a{min-height:38px;padding:.42rem .7rem;font-size:.88rem}
  .meal-tabs{gap:.3rem}.meal-tabs a{min-height:40px;padding:.42rem .2rem;font-size:.77rem;border-radius:10px}.meal-tabs a i{display:block;font-size:.9rem;line-height:1}
  .meal-report-meta{grid-template-columns:1.2fr .8fr;gap:.4rem}.meal-report-meta .form-label,.meal-report-meta .form-text{display:none}.meal-report-meta .form-control{min-height:40px;padding:.4rem .55rem;font-size:.82rem}
  .meal-form-card .card-body{padding:.75rem}.meal-form-card .alert{margin-bottom:.65rem;padding:.45rem .6rem;font-size:.82rem}.meal-form-head{margin-bottom:.6rem!important}.meal-form-head h5{font-size:1rem}.meal-form-head .small{font-size:.72rem}
  .meal-quick-actions .btn{padding:.35rem .5rem;font-size:.76rem;white-space:nowrap}.meal-save-bar{padding:.65rem!important}.meal-save-bar .btn{min-height:42px}
  .meal-summary-stats{gap:.4rem}.meal-summary-stats>div{padding:.65rem .25rem}.meal-export-modal .modal-dialog{margin:.5rem}.meal-export-modal .modal-footer{grid-template-columns:1fr}
  .overview-hero{padding:1.05rem;border-radius:18px}.overview-hero h4{font-size:1.15rem}.overview-metrics{grid-template-columns:1fr 1fr;gap:.55rem}.overview-metric{min-height:78px;padding:.72rem;gap:.55rem;border-radius:15px}.overview-metric-icon{width:38px;height:38px;flex-basis:38px}.overview-metric strong{font-size:1.18rem}.overview-metric small{font-size:.72rem}.overview-grid{grid-template-columns:1fr;gap:.75rem}.overview-panel{border-radius:17px}.overview-panel-head,.overview-panel-body{padding:.85rem}.overview-menu{gap:.4rem}.overview-menu-item{min-height:102px;padding:.65rem}.overview-menu-item strong{font-size:.78rem}.overview-duty-role{grid-template-columns:78px minmax(0,1fr)}
  .menu-subtabs{display:flex}.menu-subtabs a{flex:1;padding:.52rem .7rem;text-align:center;font-size:.83rem}.menu-add-row{grid-template-columns:1fr}.menu-add-row .form-control,.menu-add-row .form-select,.menu-add-row .btn{min-height:44px}.menu-toolbar{align-items:stretch;flex-direction:column}.menu-toolbar>div{justify-content:space-between}.menu-toolbar .form-control{min-width:0}.menu-grid{min-width:820px}.menu-grid thead th:first-child,.menu-grid tbody th{width:78px}.menu-grid thead th{font-size:.86rem}.menu-grid-note{font-size:.8rem}
}
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

<?php if ($tab !== 'health'): ?><div class="nt-page-head">
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
</div><?php endif; ?>

<?php if ($tab === 'overview'): ?>
  <?php
    $st = $stats;
    $overviewToday = date('Y-m-d');
    $overviewStudentIds = array_fill_keys(array_column($boarders, 'id'), true);
    $overviewPendingExits = array_values(array_filter(noitru_exits_all(), fn($row) =>
        ($row['status'] ?? '') === 'pending' && isset($overviewStudentIds[$row['student_id'] ?? ''])
    ));

    $overviewAttRows = array_values(array_filter(noitru_att_all(), fn($row) =>
        isset($overviewStudentIds[$row['student_id'] ?? ''])
    ));
    $overviewAttDate = '';
    foreach ($overviewAttRows as $row) {
        $rowDate = $row['date'] ?? '';
        if ($rowDate <= $overviewToday && $rowDate > $overviewAttDate) $overviewAttDate = $rowDate;
    }
    if ($overviewAttDate === '' && $overviewAttRows) {
        $overviewAttDate = max(array_column($overviewAttRows, 'date'));
    }
    $overviewShiftOrder = ['sang'=>1, 'toi'=>2, 'hoc_toi'=>3];
    $overviewAttShift = '';
    foreach ($overviewAttRows as $row) {
        if (($row['date'] ?? '') !== $overviewAttDate) continue;
        $candidateShift = $row['shift'] ?? '';
        if (($overviewShiftOrder[$candidateShift] ?? 0) >= ($overviewShiftOrder[$overviewAttShift] ?? 0)) {
            $overviewAttShift = $candidateShift;
        }
    }
    $overviewAttCounts = ['present'=>0, 'absent'=>0, 'late'=>0, 'excused'=>0];
    $overviewAttReported = 0;
    foreach ($overviewAttRows as $row) {
        if (($row['date'] ?? '') !== $overviewAttDate || ($row['shift'] ?? '') !== $overviewAttShift) continue;
        $status = $row['status'] ?? 'present';
        $overviewAttCounts[$status] = ($overviewAttCounts[$status] ?? 0) + 1;
        $overviewAttReported++;
    }
    $overviewPresent = $overviewAttCounts['present'] + $overviewAttCounts['late'];

    $overviewDutySettings = noitru_duty_settings();
    $overviewDutyStartTime = $overviewDutySettings['start_time'] ?? '06:00';
    $overviewDutyEndTime = $overviewDutySettings['end_time'] ?? '06:00';
    $overviewNow = time();
    $overviewTodayStart = strtotime($overviewToday . ' ' . $overviewDutyStartTime);
    $overviewCurrentDutyDate = $overviewNow < $overviewTodayStart
        ? date('Y-m-d', strtotime($overviewToday . ' -1 day'))
        : $overviewToday;
    $overviewCurrentDutyStart = strtotime($overviewCurrentDutyDate . ' ' . $overviewDutyStartTime);
    $overviewCurrentDutyEnd = strtotime($overviewCurrentDutyDate . ' ' . $overviewDutyEndTime);
    if ($overviewCurrentDutyEnd <= $overviewCurrentDutyStart) {
        $overviewCurrentDutyEnd = strtotime('+1 day', $overviewCurrentDutyEnd);
    }
    $overviewNextDutyDate = date('Y-m-d', $overviewCurrentDutyEnd);
    $overviewRemainingSeconds = max(0, $overviewCurrentDutyEnd - $overviewNow);
    $overviewRemainingHours = intdiv($overviewRemainingSeconds, 3600);
    $overviewRemainingMinutes = intdiv($overviewRemainingSeconds % 3600, 60);
    $overviewAllDuties = noitru_duty_all();
    $overviewCurrentDuties = array_values(array_filter($overviewAllDuties, fn($row) =>
        ($row['date'] ?? '') === $overviewCurrentDutyDate
    ));
    $overviewNextDuties = array_values(array_filter($overviewAllDuties, fn($row) =>
        ($row['date'] ?? '') === $overviewNextDutyDate
    ));
    $overviewCurrentManager = noitru_duty_manager_for_date($overviewCurrentDutyDate) ?? [];
    $overviewNextManager = noitru_duty_manager_for_date($overviewNextDutyDate) ?? [];

    $overviewHealth = array_values(array_filter(noitru_health_all(), fn($row) =>
        isset($overviewStudentIds[$row['student_id'] ?? ''])
    ));
    usort($overviewHealth, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? '') ?: strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $overviewHealthToday = count(array_filter($overviewHealth, fn($row) => ($row['date'] ?? '') === $overviewToday));
    $overviewHealthLatest = array_slice($overviewHealth, 0, 3);

    $overviewSharedWeek = csdl_week_for_date($overviewToday);
    $overviewWeekStart = $overviewSharedWeek['start'] ?? date('Y-m-d', strtotime('monday this week', strtotime($overviewToday)));
    $overviewMenu = noitru_menu_for_week($overviewWeekStart);
    $overviewDayKeys = ['mon','tue','wed','thu','fri','sat','sun'];
    $overviewDayLabels = ['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ Nhật'];
    $overviewDayIndex = (int)date('N') - 1;
    $overviewTodayMenu = $overviewMenu['meals'][$overviewDayKeys[$overviewDayIndex]] ?? [];
    $overviewShiftLabels = ['ngay'=>'Ca ngày', 'sang'=>'Sáng', 'toi'=>'Tối', 'hoc_toi'=>'Học tối', 'dem'=>'Đêm'];
    $overviewHealthTypeLabels = ['kham'=>'Khám', 'thuoc'=>'Thuốc', 'theo_doi'=>'Theo dõi'];
  ?>

  <section class="overview-hero">
    <span class="overview-hero-date"><i class="bi bi-calendar3"></i> <?= e($overviewDayLabels[$overviewDayIndex] . ', ' . date('d/m/Y')) ?></span>
    <h4>Tổng quan hoạt động nội trú</h4>
    <p>Số liệu mới nhất được tổng hợp từ điểm danh, lịch trực, y tế và thực đơn để theo dõi nhanh trong ngày.</p>
  </section>

  <div class="overview-metrics">
    <div class="overview-metric">
      <span class="overview-metric-icon" style="background:#fce7f3;color:#be185d"><i class="bi bi-people-fill"></i></span>
      <div><strong><?= (int)$st['total'] ?></strong><small>Học sinh nội trú</small></div>
    </div>
    <?php if (can_perm('nt.diemdanh')): ?>
    <div class="overview-metric">
      <span class="overview-metric-icon" style="background:#dcfce7;color:#15803d"><i class="bi bi-person-check-fill"></i></span>
      <div><strong><?= $overviewAttDate ? $overviewPresent . '/' . (int)$st['total'] : '—' ?></strong><small>Có mặt gần nhất</small></div>
    </div>
    <?php endif; ?>
    <?php if (can_perm('nt.ravao')): ?>
    <div class="overview-metric">
      <span class="overview-metric-icon" style="background:#fff7ed;color:#c2410c"><i class="bi bi-door-open-fill"></i></span>
      <div><strong><?= count($overviewPendingExits) ?></strong><small>Phiếu chờ duyệt</small></div>
    </div>
    <?php endif; ?>
    <?php if (can_perm('nt.yte')): ?>
    <div class="overview-metric">
      <span class="overview-metric-icon" style="background:#fee2e2;color:#dc2626"><i class="bi bi-heart-pulse-fill"></i></span>
      <div><strong><?= $overviewHealthToday ?></strong><small>Ghi nhận y tế hôm nay</small></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="overview-grid">
    <?php if (can_perm('nt.diemdanh')): ?>
    <section class="overview-panel">
      <div class="overview-panel-head">
        <div class="overview-panel-title"><i class="bi bi-clipboard2-check-fill" style="background:#dcfce7;color:#15803d"></i><div><h6>Sỹ số điểm danh</h6><small><?= $overviewAttDate ? 'Cập nhật gần nhất' : 'Chưa có dữ liệu' ?></small></div></div>
        <a class="overview-panel-link" href="<?= e(BASE_URL . 'noitru.php?tab=attendance') ?>">Xem điểm danh <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="overview-panel-body">
        <?php if ($overviewAttDate): ?>
          <div class="overview-att-main"><div><strong><?= $overviewPresent ?>/<?= (int)$st['total'] ?></strong><span class="d-block mt-1">Có mặt · <?= e($overviewShiftLabels[$overviewAttShift] ?? $overviewAttShift) ?></span></div><span><?= e(date('d/m/Y', strtotime($overviewAttDate))) ?><br>Đã ghi nhận <?= $overviewAttReported ?> HS</span></div>
          <div class="overview-mini-grid">
            <div class="overview-mini"><strong class="text-danger"><?= $overviewAttCounts['absent'] ?></strong><small>Vắng</small></div>
            <div class="overview-mini"><strong style="color:#d97706"><?= $overviewAttCounts['excused'] ?></strong><small>Có phép</small></div>
            <div class="overview-mini"><strong style="color:#7c3aed"><?= $overviewAttCounts['late'] ?></strong><small>Đi muộn</small></div>
          </div>
        <?php else: ?>
          <div class="overview-empty"><i class="bi bi-clipboard-x"></i>Chưa có lượt điểm danh nào.</div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (can_perm('nt.lichtruc')): ?>
    <section class="overview-panel">
      <div class="overview-panel-head">
        <div class="overview-panel-title"><i class="bi bi-calendar2-week-fill" style="background:#e0f2fe;color:#0369a1"></i><div><h6>Lịch trực</h6><small>Người trực và quản lý trực gần nhất</small></div></div>
        <a class="overview-panel-link" href="<?= e(BASE_URL . 'noitru.php?tab=duty') ?>">Xem lịch trực <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="overview-panel-body">
        <div class="overview-duty-stack">
          <?php foreach ([
              ['current', 'Đang trực hiện tại', $overviewCurrentDutyDate, $overviewCurrentDuties, $overviewCurrentManager],
              ['', 'Ca trực tiếp theo', $overviewNextDutyDate, $overviewNextDuties, $overviewNextManager],
          ] as $dutyBlock):
              [$blockClass, $blockLabel, $blockDate, $blockRows, $blockManager] = $dutyBlock;
              $blockWeekday = $overviewDayLabels[(int)date('N', strtotime($blockDate)) - 1] ?? '';
              $blockManagerNames = array_values(array_filter((array)($blockManager['teacher_names'] ?? []), fn($name) => trim((string)$name) !== ''));
          ?>
          <div class="overview-duty-shift <?= e($blockClass) ?>">
            <div class="overview-duty-shift-head">
              <div><span class="overview-duty-status"><i class="bi <?= $blockClass === 'current' ? 'bi-broadcast-pin' : 'bi-arrow-right-circle' ?>"></i><?= e($blockLabel) ?></span><small class="overview-duty-date"><?= e($blockWeekday . ', ' . date('d/m/Y', strtotime($blockDate))) ?> · <?= e($overviewDutyStartTime) ?>–<?= e($overviewDutyEndTime) ?> hôm sau</small></div>
              <?php if ($blockClass === 'current'): ?><span class="overview-duty-countdown"><i class="bi bi-clock"></i> Còn <?= $overviewRemainingHours ?>h <?= $overviewRemainingMinutes ?>p</span><?php endif; ?>
            </div>
            <div class="overview-duty-role">
              <span class="overview-duty-role-label"><i class="bi bi-person-check"></i> Người trực</span>
              <div class="overview-duty-names"><?php if ($blockRows): foreach ($blockRows as $dutyRow): ?><span class="overview-duty-name"><?= e($dutyRow['teacher_name'] ?? 'Chưa rõ') ?></span><?php endforeach; else: ?><span class="overview-duty-unassigned">Chưa phân công</span><?php endif; ?></div>
            </div>
            <div class="overview-duty-role">
              <span class="overview-duty-role-label"><i class="bi bi-shield-check"></i> Quản lý</span>
              <div><?php if ($blockManagerNames): ?><div class="overview-duty-names"><?php foreach ($blockManagerNames as $managerName): ?><span class="overview-duty-name manager"><?= e($managerName) ?></span><?php endforeach; ?></div><?php else: ?><span class="overview-duty-unassigned">Chưa phân công quản lý</span><?php endif; ?><?php if (trim((string)($blockManager['note'] ?? '')) !== ''): ?><div class="overview-duty-note"><i class="bi bi-chat-left-text"></i> <?= e($blockManager['note']) ?></div><?php endif; ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if (can_perm('nt.thucdon')): ?>
    <section class="overview-panel">
      <div class="overview-panel-head">
        <div class="overview-panel-title"><i class="bi bi-journal-richtext" style="background:#ffedd5;color:#c2410c"></i><div><h6>Thực đơn hôm nay</h6><small><?= e($overviewDayLabels[$overviewDayIndex] . ' · ' . date('d/m/Y')) ?></small></div></div>
        <a class="overview-panel-link" href="<?= e(BASE_URL . 'noitru.php?tab=menu') ?>">Xem thực đơn <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="overview-panel-body">
        <?php if (array_filter($overviewTodayMenu)): ?>
        <div class="overview-menu">
          <?php foreach (['sang'=>'Bữa sáng', 'trua'=>'Bữa trưa', 'toi'=>'Bữa tối'] as $mealKey=>$mealLabel): ?>
          <div class="overview-menu-item"><i class="bi <?= $mealKey === 'sang' ? 'bi-sunrise-fill' : ($mealKey === 'trua' ? 'bi-sun-fill' : 'bi-moon-stars-fill') ?>"></i><small><?= $mealLabel ?></small><strong><?= trim($overviewTodayMenu[$mealKey] ?? '') !== '' ? e($overviewTodayMenu[$mealKey]) : 'Chưa cập nhật' ?></strong></div>
          <?php endforeach; ?>
        </div>
        <?php else: ?><div class="overview-empty"><i class="bi bi-journal-x"></i>Thực đơn hôm nay chưa được cập nhật.</div><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (can_perm('nt.yte')): ?>
    <section class="overview-panel">
      <div class="overview-panel-head">
        <div class="overview-panel-title"><i class="bi bi-heart-pulse-fill" style="background:#fee2e2;color:#dc2626"></i><div><h6>Y tế mới nhất</h6><small><?= $overviewHealthToday ?> ghi nhận trong hôm nay</small></div></div>
        <a class="overview-panel-link" href="<?= e(BASE_URL . 'noitru.php?tab=health') ?>">Xem y tế <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="overview-panel-body">
        <?php if ($overviewHealthLatest): ?><div class="overview-list">
          <?php foreach ($overviewHealthLatest as $healthRow): ?>
          <div class="overview-list-item">
            <div class="overview-list-date"><strong><?= e(date('d/m', strtotime($healthRow['date'] ?? $overviewToday))) ?></strong><small><?= e($overviewHealthTypeLabels[$healthRow['type'] ?? ''] ?? 'Y tế') ?></small></div>
            <div class="overview-list-content"><strong><?= e($healthRow['student_name'] ?? '') ?></strong><span><?= e($healthRow['diagnosis'] ?? 'Chưa ghi tình trạng') ?><?= trim($healthRow['treatment'] ?? '') !== '' ? ' · ' . e($healthRow['treatment']) : '' ?></span></div>
          </div>
          <?php endforeach; ?>
        </div><?php else: ?><div class="overview-empty"><i class="bi bi-heart"></i>Chưa có ghi nhận y tế.</div><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
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
    $date = $_GET['date'] ?? date('Y-m-d', strtotime('+1 day'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d', strtotime('+1 day'));
    $mealMap = noitru_meals_for_date($date);
    $meal = $_GET['meal'] ?? 'sang';
    if (!in_array($meal, ['all','sang','trua','toi'], true)) $meal = 'sang';
    $mealLabels = ['all'=>'Cả ngày','sang'=>'Bữa sáng','trua'=>'Bữa trưa','toi'=>'Bữa tối'];
    $mealClasses = [];
    foreach ($boarders as $student) {
      $classKey = trim($student['class_name'] ?? '') ?: '(Chưa lớp)';
      $mealClasses[$classKey][] = $student;
    }
    ksort($mealClasses, SORT_NATURAL);
    $className = trim($_GET['class'] ?? '');
    if ($className === '' || !isset($mealClasses[$className])) $className = array_key_first($mealClasses) ?? '';
    $classStudents = $className !== '' ? ($mealClasses[$className] ?? []) : [];
    $viewMeals = $meal === 'all' ? ['sang','trua','toi'] : [$meal];
    $mealStates = [];
    foreach ($viewMeals as $viewMeal) $mealStates[$viewMeal] = noitru_meal_state($date, $viewMeal)['status'] ?? 'open';
    $mealState = count(array_filter($mealStates, fn($state)=>$state==='open')) === count($mealStates) ? 'open' : (count(array_filter($mealStates, fn($state)=>$state==='locked')) === count($mealStates) ? 'locked' : 'mixed');
    $mealReport = $className !== '' && $meal !== 'all' ? noitru_meal_report_for($date, $className, $meal) : null;
    $readOnly = $mealState !== 'open' && allowed_classes() !== null;
  ?>
  <div class="nt-page-head"><div><h4><i class="bi bi-fork-knife text-primary"></i> Báo ăn lớp chủ nhiệm</h4><div class="subtitle">Chỉ hiển thị học sinh thuộc lớp được giao</div></div>
    <?php if ($className!==''): ?><div class="dropdown"><button class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" onclick="openMealExcelModal('breakfast')">Báo cáo bữa sáng</button></li><li><button class="dropdown-item" type="button" onclick="openMealExcelModal('lunch_dinner')">Báo cáo bữa trưa, tối</button></li></ul></div><?php endif; ?>
  </div>
  <div class="card card-soft mb-3"><div class="card-body">
    <form method="get" class="meal-report-meta mb-2">
      <input type="hidden" name="tab" value="meals"><input type="hidden" name="class" value="<?= e($className) ?>"><input type="hidden" name="meal" value="<?= e($meal) ?>">
      <div><label class="form-label">Ngày sử dụng bữa ăn</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()"><div class="form-text">Bữa sáng mặc định là ngày hôm sau.</div></div>
      <div><label class="form-label">Người báo</label><div class="form-control bg-light text-truncate"><i class="bi bi-person-check me-1"></i><?= e($user['name'] ?? '') ?><?= in_array('gvcn',$user['groups']??[],true)?' · GVCN':'' ?></div></div>
    </form>
    <label class="form-label">Chọn lớp</label>
    <div class="meal-class-list mb-3">
      <?php foreach ($mealClasses as $classKey=>$students): ?><a class="<?= $className===$classKey?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','date'=>$date,'class'=>$classKey,'meal'=>$meal])) ?>"><?= e($classKey) ?> (<?= count($students) ?>)</a><?php endforeach; ?>
    </div>
    <div class="meal-tabs">
      <?php foreach ($mealLabels as $mealKey=>$label): $tabDate=(!isset($_GET['date'])&&!in_array($mealKey,['all','sang'],true))?date('Y-m-d'):$date;
        if ($mealKey==='all') { $tabStates=[]; foreach (['sang','trua','toi'] as $mk) $tabStates[]=noitru_meal_state($tabDate,$mk)['status']??'open'; $state=count(array_filter($tabStates,fn($s)=>$s==='locked'))===3?'locked':'open'; }
        else $state=noitru_meal_state($tabDate,$mealKey)['status']??'open'; ?>
        <a class="<?= $meal===$mealKey?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','date'=>$tabDate,'class'=>$className,'meal'=>$mealKey])) ?>"><i class="bi <?= $mealKey==='all'?'bi-calendar-check':($mealKey==='sang'?'bi-sunrise':($mealKey==='trua'?'bi-sun':'bi-moon-stars')) ?>"></i> <?= e($label) ?><?= $state==='locked'?' · Đã chốt':($state==='off'?' · Nghỉ':'') ?></a>
      <?php endforeach; ?>
    </div>
  </div></div>
  <?php if ($mealState !== 'open'): ?><div class="alert alert-info py-2"><i class="bi bi-lock"></i> Có bữa ăn đã được chốt hoặc thông báo nghỉ. Chọn từng bữa để xem chi tiết.</div><?php endif; ?>
  <form method="post" class="card card-soft meal-form-card" id="mealReportForm" data-regular-meals="<?= $meal==='all'?'sang,trua,toi':e($meal) ?>" onsubmit="return prepareMealConfirmation(event)">
    <input type="hidden" name="action" value="meals_save">
    <input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="class_name" value="<?= e($className) ?>"><input type="hidden" name="meal" value="<?= e($meal) ?>">
    <input type="hidden" name="submission_mode" id="mealSubmissionMode" value="regular">
    <input type="hidden" name="target_meals" id="mealTargetMeals" value="<?= $meal==='all'?'sang,trua,toi':e($meal) ?>">
    <input type="hidden" name="long_from" id="mealLongFrom" value="<?= e($date) ?>"><input type="hidden" name="long_until" id="mealLongUntil" value="<?= e($date) ?>">
    <div class="card-body">
      <div class="d-flex justify-content-between gap-2 flex-wrap meal-form-head mb-3"><div><h5 class="mb-1"><?= e($className) ?> · <?= e($mealLabels[$meal]) ?></h5><div class="small text-muted"><?= $mealReport?'Đã báo bởi '.e($mealReport['reported_by']??'').' lúc '.e(date('d/m/Y H:i',strtotime($mealReport['updated_at']??$mealReport['created_at']??'now'))):($meal==='all'?'Báo đồng thời cả 3 bữa':'Chưa gửi báo cáo') ?></div></div>
        <?php if (!$readOnly && $classStudents): ?><div class="meal-quick-actions"><button class="btn btn-outline-success btn-sm" type="button" onclick="setMealAbsent(false)">Đủ cả lớp</button><button class="btn btn-outline-danger btn-sm" type="button" onclick="setMealAbsent(true)">Nghỉ cả lớp</button><button class="btn btn-outline-warning btn-sm" type="button" onclick="openLongMealModal()"><i class="bi bi-calendar-range"></i> Nghỉ dài ngày</button></div><?php endif; ?>
      </div>
      <div class="alert alert-light border py-2"><strong>Mặc định tất cả học sinh ăn.</strong> Chỉ tích vào học sinh nghỉ ăn.</div>
      <div class="meal-student-grid">
        <?php foreach ($classStudents as $i=>$student): $studentMeals=$mealMap[$student['id']]??[]; $value=$meal==='all'?(count(array_filter(['sang','trua','toi'],fn($mk)=>($studentMeals[$mk]??'yes')==='no'))===3?'no':'yes'):($studentMeals[$meal]??'yes'); ?>
          <label class="meal-student" data-student-name="<?= e($student['name']) ?>"><input type="hidden" name="sid[]" value="<?= e($student['id']) ?>"><input class="form-check-input meal-absent" type="checkbox" <?= $value==='no'?'checked':'' ?> <?= $readOnly?'disabled':'' ?> onchange="this.nextElementSibling.value=this.checked?'no':'yes'"><input type="hidden" name="meal_status[]" value="<?= $value==='no'?'no':'yes' ?>"><span><strong><?= e($student['name']) ?></strong></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($classStudents && !$readOnly): ?><div class="card-body border-top meal-save-bar"><button class="btn btn-nt w-100" type="submit"><i class="bi bi-send-check"></i> Kiểm tra và lưu báo ăn <?= e($mealLabels[$meal]) ?></button></div><?php endif; ?>
  </form>
  <div class="modal fade" id="longMealModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-range me-2"></i>Học sinh nghỉ dài ngày</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="alert alert-warning py-2"><span id="longAbsentCount">0</span> học sinh đã được tích chọn.</div>
      <div class="row g-2"><div class="col-6"><label class="form-label">Từ ngày</label><input class="form-control" type="date" id="longFromInput" min="<?= e($date) ?>" max="<?= e(date('Y-m-d',strtotime($date.' +60 days'))) ?>" value="<?= e($date) ?>"></div><div class="col-6"><label class="form-label">Đến ngày</label><input class="form-control" type="date" id="longUntilInput" min="<?= e($date) ?>" max="<?= e(date('Y-m-d',strtotime($date.' +60 days'))) ?>" value="<?= e($date) ?>"></div></div>
      <label class="form-label mt-3">Áp dụng bữa ăn</label><select class="form-select" id="longMealsInput"><option value="sang">Bữa sáng</option><option value="trua">Bữa trưa</option><option value="toi">Bữa tối</option><option value="sang,trua,toi" selected>Cả 3 bữa</option></select>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-warning" type="button" onclick="continueLongMeal()">Tiếp tục kiểm tra</button></div>
  </div></div></div>
  <div class="modal fade" id="mealConfirmModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Kiểm tra báo ăn trước khi lưu</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="mealConfirmBody"></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Quay lại sửa</button><button class="btn btn-success" type="button" onclick="confirmMealSubmit()"><i class="bi bi-check-circle"></i> Xác nhận gửi</button></div>
  </div></div></div>
  <div class="modal fade" id="mealExcelModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="get">
    <div class="modal-header"><h5 class="modal-title" id="mealExcelTitle"><i class="bi bi-file-earmark-excel text-success me-2"></i>Xuất sổ bữa ăn</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="tab" value="meals"><input type="hidden" name="class" value="<?= e($className) ?>"><input type="hidden" name="export" id="mealExcelType" value="month_breakfast"><label class="form-label fw-bold">Chọn tháng báo cáo</label><input class="form-control" type="month" name="month" value="<?= e(substr($date,0,7)) ?>" required><div class="form-text mt-2">File chỉ chứa sheet lớp <?= e($className) ?> theo quyền GVCN.</div></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-success" type="submit"><i class="bi bi-download"></i> Tải file Excel</button></div>
  </form></div></div>

<?php elseif ($tab === 'meal_summary'): ?>
  <?php
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $overview = nt_meal_day_overview($date, $boarders);
    $mealLabels = ['sang'=>'Bữa sáng','trua'=>'Bữa trưa','toi'=>'Bữa tối'];
    $mealSettings = noitru_meal_settings();
    $rice = noitru_rice_data();
    $riceKg = 0;
    foreach (['sang','trua','toi'] as $mealKey) $riceKg += ($overview['meals'][$mealKey]['eat']??0) * (float)($rice['settings'][$mealKey.'_grams']??0) / 1000;
    $riceLunchKg = ($overview['meals']['trua']['eat']??0) * (float)($rice['settings']['trua_grams']??180) / 1000;
    $riceDinnerKg = ($overview['meals']['toi']['eat']??0) * (float)($rice['settings']['toi_grams']??180) / 1000;
    $riceDayKg = $riceLunchKg + $riceDinnerKg;
    $dayExportData = ['school'=>$school ?? 'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN','date'=>date('d/m/Y',strtotime($date)),'reporter'=>$user['name']??'','rice_kg'=>$riceDayKg,'rice_lunch_kg'=>$riceLunchKg,'rice_dinner_kg'=>$riceDinnerKg,'meals'=>[]];
    foreach ($mealLabels as $mealKey=>$label) {
      $info=$overview['meals'][$mealKey];
      $dayExportData['meals'][$mealKey]=[
        'label'=>$label,'total'=>(int)$info['total'],'eat'=>(int)$info['eat'],'absent'=>(int)$info['absent'],
        'rice_kg'=>(int)$info['eat']*(float)($rice['settings'][$mealKey.'_grams']??0)/1000,
        'students'=>array_values($info['absent_students']??[])
      ];
    }
  ?>
  <div class="nt-page-head">
    <div><h4><i class="bi bi-fork-knife text-primary"></i> Báo cơm cả trường – <?= e(date('d/m/Y',strtotime($date))) ?></h4><div class="subtitle">Nhận báo cáo từ GVCN, chốt số liệu và báo nhà bếp</div></div>
    <div class="dropdown"><button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-spreadsheet"></i> Xuất báo cáo</button><ul class="dropdown-menu dropdown-menu-end">
      <li><button class="dropdown-item" type="button" onclick="openMealDayExport('summary')"><i class="bi bi-image text-info me-2"></i>Xuất ảnh thống kê</button></li>
      <li><button class="dropdown-item" type="button" onclick="openMealDayExport('groups')"><i class="bi bi-people text-danger me-2"></i>DS vắng theo mâm</button></li>
      <li><hr class="dropdown-divider"></li>
      <li><button class="dropdown-item" type="button" onclick="openMealExcelModal('breakfast')"><i class="bi bi-file-earmark-excel text-success me-2"></i>Excel bữa sáng</button></li>
      <li><button class="dropdown-item" type="button" onclick="openMealExcelModal('lunch_dinner')"><i class="bi bi-file-earmark-excel text-success me-2"></i>Excel bữa trưa, tối</button></li>
    </ul></div>
  </div>
  <form method="get" class="card card-soft mb-3"><div class="card-body d-flex align-items-end gap-2 flex-wrap"><input type="hidden" name="tab" value="meal_summary"><div><label class="form-label">Ngày chuẩn bị</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>"></div><button class="btn btn-nt">Xem tổng hợp</button></div></form>
  <?php if (allowed_classes()===null && $canEditCurrent): ?><details class="card card-soft mb-3"><summary class="card-body fw-bold"><i class="bi bi-sliders"></i> Cài đặt giờ khóa và định mức gạo</summary><form method="post" class="card-body border-top">
    <input type="hidden" name="action" value="meal_settings"><input type="hidden" name="date" value="<?= e($date) ?>">
    <div class="row g-3">
      <?php foreach ($mealLabels as $mealKey=>$label): ?><div class="col-12 col-md-4"><div class="border rounded-3 p-3 h-100"><h6><?= e($label) ?></h6>
        <label class="form-label">Giờ khóa báo ăn</label><input class="form-control mb-2" type="time" name="<?= e($mealKey) ?>_lock_time" value="<?= e($mealSettings[$mealKey.'_lock_time']??'') ?>" required>
        <div class="form-text mb-2"><?= $mealKey==='sang'?'Áp dụng vào ngày hôm trước ngày ăn.':'Áp dụng trong chính ngày ăn.' ?></div>
        <label class="form-label">Gam gạo / 1 học sinh</label><input class="form-control" type="number" min="0" step="1" name="<?= e($mealKey) ?>_grams" value="<?= e($rice['settings'][$mealKey.'_grams']??0) ?>">
      </div></div><?php endforeach; ?>
    </div>
    <button class="btn btn-nt mt-3"><i class="bi bi-floppy"></i> Lưu cài đặt</button>
  </form></details><?php endif; ?>
  <?php foreach ($overview['meals'] as $mealKey=>$info): ?>
    <section class="meal-summary-card" id="mealCard-<?= e($mealKey) ?>" data-meal-label="<?= e($mealLabels[$mealKey]) ?>" data-date="<?= e(date('d/m/Y',strtotime($date))) ?>" data-total="<?= $info['total'] ?>" data-eat="<?= $info['eat'] ?>" data-absent="<?= $info['absent'] ?>" data-classes="<?= e(implode(', ',array_keys($info['reported']))) ?>">
      <div class="meal-summary-head"><div><h5 class="mb-1"><i class="bi bi-fork-knife text-primary"></i> <?= e($mealLabels[$mealKey]) ?></h5><span class="small text-muted">Khóa <?= e($mealSettings[$mealKey.'_lock_time']??'') ?><?= $mealKey==='sang'?' hôm trước':'' ?></span></div>
        <?php if (allowed_classes()===null && $canEditCurrent): ?><div class="meal-state-actions">
          <?php if ($info['state']!=='off'): ?><form method="post" onsubmit="return confirm('Thông báo nghỉ <?= e(mb_strtolower($mealLabels[$mealKey],'UTF-8')) ?>? Số suất chuẩn bị sẽ về 0.')"><input type="hidden" name="action" value="meal_state"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($mealKey) ?>"><input type="hidden" name="status" value="off"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-calendar-x"></i> Nghỉ</button></form><?php endif; ?>
          <button class="btn btn-outline-secondary btn-sm" type="button" onclick="downloadMealImage('<?= e($mealKey) ?>')"><i class="bi bi-image"></i> Xuất ảnh</button>
          <?php if ($info['missing'] && $info['state']==='open'): ?><form method="post" onsubmit="return confirm('Xác nhận tất cả học sinh của <?= count($info['missing']) ?> lớp chưa báo đều ăn <?= e(mb_strtolower($mealLabels[$mealKey],'UTF-8')) ?>?')"><input type="hidden" name="action" value="meal_fill_missing"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($mealKey) ?>"><button class="btn btn-outline-success btn-sm"><i class="bi bi-check-circle"></i> Còn lại đủ</button></form><?php endif; ?>
          <?php if ($info['state']==='open'): ?><form method="post" onsubmit="return confirm('Chốt số liệu <?= e(mb_strtolower($mealLabels[$mealKey],'UTF-8')) ?>? GVCN sẽ không thể sửa sau khi chốt.')"><input type="hidden" name="action" value="meal_state"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($mealKey) ?>"><input type="hidden" name="status" value="locked"><button class="btn btn-primary btn-sm"><i class="bi bi-lock"></i> Chốt (<?= count($info['reported']) ?> lớp)</button></form><?php endif; ?>
          <?php if ($info['state']!=='open'): ?><form method="post"><input type="hidden" name="action" value="meal_state"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($mealKey) ?>"><input type="hidden" name="status" value="open"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-unlock"></i> Mở lại</button></form><?php endif; ?>
          <span class="badge align-self-center <?= $info['state']==='locked'?'bg-success':($info['state']==='off'?'bg-danger':'bg-warning text-dark') ?>"><?= $info['state']==='locked'?'Đã chốt':($info['state']==='off'?'Đã nghỉ':($info['reported']?'Đã báo cáo':'Chưa báo cáo')) ?></span>
        </div><?php endif; ?>
      </div>
      <?php if ($info['reported']): ?>
        <div class="meal-report-classes"><?php foreach ($info['reported'] as $class=>$report): ?><span><strong><?= e($class) ?></strong></span><?php endforeach; ?></div>
        <div class="meal-summary-stats"><div><small class="d-block text-muted">Tổng</small><strong><?= $info['total'] ?></strong></div><div class="eat"><small class="d-block">Ăn</small><strong><?= $info['eat'] ?></strong></div><div class="absent"><small class="d-block">Vắng</small><strong><?= $info['absent'] ?></strong></div></div>
      <?php else: ?><div class="meal-summary-empty">Chưa có báo cáo cho bữa này</div><?php endif; ?>
    </section>
  <?php endforeach; ?>
  <?php $missingMealCount=0; foreach ($overview['meals'] as $info) if ($info['missing']) $missingMealCount=max($missingMealCount,count($info['missing'])); if ($missingMealCount): ?>
    <details class="meal-missing-box"><summary class="d-flex justify-content-between align-items-center"><strong class="text-warning"><i class="bi bi-exclamation-circle"></i> Lớp chưa báo cáo</strong><span class="badge text-bg-light"><?= $missingMealCount ?> lớp</span></summary>
      <?php foreach ($overview['meals'] as $mealKey=>$info): if (!$info['missing']) continue; ?><div class="meal-missing-row"><strong><?= e($mealLabels[$mealKey]) ?>: <?= count($info['missing']) ?> lớp</strong><div class="meal-missing-chips"><?php foreach ($info['missing'] as $class): ?><span><?= e($class) ?></span><?php endforeach; ?></div></div><?php endforeach; ?>
    </details>
  <?php endif; ?>
  <div class="alert alert-info">
    <div class="mb-2"><strong>Gạo dự kiến trong ngày</strong><div class="small">Tự động tính từ số học sinh ăn và định mức gạo từng bữa</div></div>
    <div class="row g-2 text-center">
      <div class="col-4"><div class="bg-white bg-opacity-75 rounded-3 p-2 h-100"><small class="d-block text-muted">Bữa trưa</small><strong class="fs-5"><?= number_format($riceLunchKg,2) ?> kg</strong><small class="d-block"><?= $overview['meals']['trua']['eat'] ?> suất × <?= e($rice['settings']['trua_grams']??180) ?>g</small></div></div>
      <div class="col-4"><div class="bg-white bg-opacity-75 rounded-3 p-2 h-100"><small class="d-block text-muted">Bữa tối</small><strong class="fs-5"><?= number_format($riceDinnerKg,2) ?> kg</strong><small class="d-block"><?= $overview['meals']['toi']['eat'] ?> suất × <?= e($rice['settings']['toi_grams']??180) ?>g</small></div></div>
      <div class="col-4"><div class="bg-primary text-white rounded-3 p-2 h-100"><small class="d-block">Cả ngày</small><strong class="fs-4"><?= number_format($riceDayKg,2) ?> kg</strong><small class="d-block">Trưa + Tối</small></div></div>
    </div>
  </div>
  <div class="modal fade meal-export-modal" id="mealDayExportModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="mealDayExportTitle"><i class="bi bi-image me-2"></i>Xuất báo cáo bữa ăn</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
    <div class="modal-body"><img class="meal-export-preview" id="mealDayExportPreview" alt="Ảnh báo cáo bữa ăn"></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" onclick="downloadMealDayExport()"><i class="bi bi-download"></i> Tải ảnh</button><button class="btn btn-info text-white" type="button" onclick="shareMealDayExport()"><i class="bi bi-share"></i> Chia sẻ</button></div>
  </div></div></div>
  <div class="modal fade" id="mealExcelModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="get">
    <div class="modal-header"><h5 class="modal-title" id="mealExcelTitle"><i class="bi bi-file-earmark-excel text-success me-2"></i>Xuất sổ bữa ăn</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="tab" value="meal_summary"><input type="hidden" name="export" id="mealExcelType" value="month_breakfast"><label class="form-label fw-bold">Chọn tháng báo cáo</label><input class="form-control" type="month" name="month" value="<?= e(substr($date,0,7)) ?>" required><div class="form-text mt-2">Mỗi lớp được tạo thành một sheet riêng, định dạng in A4 ngang.</div></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-success" type="submit"><i class="bi bi-download"></i> Tải file Excel</button></div>
  </form></div></div>
  <script>window.ntMealDayData=<?= json_encode($dayExportData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>

<?php elseif ($tab === 'rice'): ?>
  <?php
    $rice=noitru_rice_data();
    $riceBalance=noitru_rice_balance($rice);
    $riceRows=array_reverse($rice['transactions']??[]);
    $periodType=$_GET['period_type']??'month';
    $month=$_GET['month']??date('Y-m');
    $riceFrom=$_GET['from']??date('Y-m-01');
    $riceTo=$_GET['to']??date('Y-m-d');
    if ($periodType==='month' && preg_match('/^\d{4}-\d{2}$/',$month)) {
      $riceFrom=$month.'-01'; $riceTo=date('Y-m-t',strtotime($riceFrom));
    } else {
      $periodType='custom';
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$riceFrom)) $riceFrom=date('Y-m-01');
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$riceTo) || $riceTo<$riceFrom) $riceTo=date('Y-m-d');
    }
    $riceUsage=noitru_rice_usage_summary($riceFrom,$riceTo,$rice);
  ?>
  <div class="nt-page-head"><div><h4>Quản lý gạo</h4><div class="subtitle">Tự động trừ kho theo số suất ăn của các bữa đã chốt</div></div><a class="btn btn-success" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'rice','export'=>'excel','period_type'=>$periodType,'month'=>$month,'from'=>$riceFrom,'to'=>$riceTo])) ?>"><i class="bi bi-file-earmark-excel"></i> Xuất báo cáo Excel</a></div>
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= number_format($riceBalance,3) ?> kg</div><div class="small text-muted">Tồn kho hiện tại</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= number_format($riceUsage['total_kg'],3) ?> kg</div><div class="small text-muted">Đã dùng trong giai đoạn</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$riceUsage['total_students'] ?></div><div class="small text-muted">Tổng lượt học sinh ăn</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><i class="bi bi-arrow-repeat"></i></div><div class="small text-muted">Tự động theo bữa đã chốt</div></div></div>
  </div>
  <form method="get" class="card card-soft mb-3"><div class="card-body">
    <input type="hidden" name="tab" value="rice">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-2"><label class="form-label">Kiểu thống kê</label><select class="form-select" name="period_type" id="ricePeriodType" onchange="toggleRicePeriod()"><option value="month" <?= $periodType==='month'?'selected':'' ?>>Theo tháng</option><option value="custom" <?= $periodType==='custom'?'selected':'' ?>>Theo giai đoạn</option></select></div>
      <div class="col-12 col-md-3" data-rice-period="month"><label class="form-label">Tháng</label><input class="form-control" type="month" name="month" value="<?= e($month) ?>"></div>
      <div class="col-6 col-md-3" data-rice-period="custom"><label class="form-label">Từ ngày</label><input class="form-control" type="date" name="from" value="<?= e($riceFrom) ?>"></div>
      <div class="col-6 col-md-3" data-rice-period="custom"><label class="form-label">Đến ngày</label><input class="form-control" type="date" name="to" value="<?= e($riceTo) ?>"></div>
      <div class="col-12 col-md-2"><button class="btn btn-nt w-100"><i class="bi bi-bar-chart"></i> Thống kê</button></div>
    </div>
  </div></form>
  <div class="card card-soft mb-3"><div class="card-body"><h6>Gạo tiêu thụ tự động theo ngày</h6><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Ngày</th><th>Sáng</th><th>Trưa</th><th>Tối</th><th>Lượt ăn</th><th>Tổng gạo</th></tr></thead><tbody>
    <?php foreach (array_reverse($riceUsage['days'],true) as $usageDate=>$day): ?><tr><td><strong><?= e(date('d/m/Y',strtotime($usageDate))) ?></strong></td>
      <?php foreach (['sang','trua','toi'] as $mealKey): ?><td><?= (int)$day[$mealKey]['students'] ?> HS<br><small class="text-muted"><?= number_format($day[$mealKey]['kg'],3) ?> kg</small></td><?php endforeach; ?>
      <td><?= (int)$day['students'] ?></td><td><strong><?= number_format($day['kg'],3) ?> kg</strong></td></tr>
    <?php endforeach; if (!$riceUsage['days']): ?><tr><td colspan="6" class="text-center text-muted py-3">Chưa có bữa ăn đã chốt trong giai đoạn này.</td></tr><?php endif; ?>
    </tbody><tfoot><tr class="table-success"><th colspan="4">Tổng cộng</th><th><?= (int)$riceUsage['total_students'] ?> lượt</th><th><?= number_format($riceUsage['total_kg'],3) ?> kg</th></tr></tfoot>
  </table></div></div></div>
  <?php if (allowed_classes()===null && $canEditCurrent): ?>
  <div class="row g-3 mb-3">
    <div class="col-lg-4"><form method="post" class="card card-soft h-100"><div class="card-body">
      <input type="hidden" name="action" value="rice_settings"><h6>Định mức gạo</h6>
      <label class="form-label">Bữa sáng (gam/HS)</label><input type="number" step="1" min="0" name="sang_grams" class="form-control mb-2" value="<?= e($rice['settings']['sang_grams']??0) ?>">
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
      <input type="hidden" name="action" value="rice_issue"><h6>Xuất/điều chỉnh khác</h6>
      <label class="form-label">Ngày</label><input type="date" name="date" class="form-control mb-2" value="<?= date('Y-m-d') ?>">
      <label class="form-label">Số kg</label><input type="number" step=".001" min=".001" name="kg" class="form-control mb-2" required>
      <label class="form-label">Lý do</label><input name="note" class="form-control mb-3" placeholder="Hao hụt, điều chỉnh, xuất khác…" required>
      <button class="btn btn-warning w-100">Ghi xuất khác</button>
    </div></form></div>
  </div>
  <?php endif; ?>
  <div class="card card-soft"><div class="card-body"><h6>Nhập kho và điều chỉnh thủ công</h6><div class="table-responsive"><table class="table table-sm align-middle mb-0">
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
              <select name="status[]" class="form-select form-select-sm" style="max-width:140px" <?= !$canEditCurrent?'disabled aria-disabled="true"':'' ?>>
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
    <?php if ($boarders && $canEditCurrent): ?><div class="card-body border-top"><button class="btn btn-nt" type="submit">Lưu điểm danh</button></div><?php endif; ?>
    </div>
  </form>

<?php elseif ($tab === 'duty'): ?>
  <?php require __DIR__ . '/includes/noitru_duty_view.php'; ?>

<?php elseif ($tab === 'health'): ?>
  <?php require __DIR__ . '/includes/noitru_health_view.php'; ?>

<?php elseif ($tab === 'menu'): ?>
  <?php
    $menuView = in_array($_GET['menu_view'] ?? 'dishes', ['dishes','template','week'], true) ? ($_GET['menu_view'] ?? 'dishes') : 'dishes';
    $weekRequest = $_GET['week'] ?? date('Y-m-d');
    $sharedMenuWeek = csdl_week_for_date($weekRequest);
    if (!$sharedMenuWeek) $sharedMenuWeek = csdl_current_week();
    $week = $sharedMenuWeek['start'] ?? date('Y-m-d', strtotime('monday this week'));
    $menu = noitru_menu_for_week($week);
    $meals = $menu['meals'] ?? [];
    $menuConfig = noitru_menu_config();
    $dishes = $menuConfig['dishes'] ?? [];
    $template = $menuConfig['template'] ?? [];
    $dayLabels = ['mon'=>'Thứ 2','tue'=>'Thứ 3','wed'=>'Thứ 4','thu'=>'Thứ 5','fri'=>'Thứ 6','sat'=>'Thứ 7','sun'=>'Chủ nhật'];
    $mealLabels = ['sang'=>'Sáng','trua'=>'Trưa','toi'=>'Tối'];
    $categoryLabels = ['breakfast'=>'Đồ ăn sáng','meat'=>'Thịt','vegetable'=>'Rau củ'];
    $weekEnd = date('Y-m-d', strtotime($week . ' +6 days'));
    $previousWeek = date('Y-m-d', strtotime($week . ' -7 days'));
    $nextWeek = date('Y-m-d', strtotime($week . ' +7 days'));
    $cellValue = static function($value) { return is_array($value) ? implode(', ', $value) : trim((string)$value); };
  ?>
  <div class="menu-subtabs mb-3">
    <a class="<?= $menuView==='dishes'?'active':'' ?>" href="?tab=menu&menu_view=dishes">Danh sách món ăn</a>
    <a class="<?= $menuView==='template'?'active':'' ?>" href="?tab=menu&menu_view=template">Thực đơn mẫu</a>
    <a class="<?= $menuView==='week'?'active':'' ?>" href="?tab=menu&menu_view=week&week=<?= e($week) ?>">Thực đơn tuần</a>
  </div>

  <?php if ($menuView === 'dishes'): ?>
    <div class="card card-soft menu-panel"><div class="card-body p-3 p-md-4">
      <h5 class="fw-bold mb-3">Danh sách món ăn</h5>
      <?php if ($canEditCurrent): ?><form method="post" class="menu-add-row mb-3">
        <input type="hidden" name="action" value="menu_dish_add">
        <input class="form-control" name="dish_name" maxlength="80" placeholder="Nhập tên món (VD: Xôi, Gà rán...)" required>
        <select class="form-select" name="dish_category"><option value="breakfast">Đồ ăn sáng</option><option value="meat">Thịt</option><option value="vegetable">Rau củ</option></select>
        <button class="btn btn-nt px-4"><i class="bi bi-plus-lg me-2"></i>Thêm</button>
      </form><?php endif; ?>
      <?php foreach ($categoryLabels as $category=>$label): ?>
        <section class="mb-3"><h6 class="mb-2"><?= e($label) ?></h6><div class="menu-chips">
          <?php foreach ($dishes as $dish): if (($dish['category']??'') !== $category) continue; ?><span class="menu-chip"><?= e($dish['name']??'') ?>
            <?php if ($canDeleteCurrent): ?><form method="post" onsubmit="return confirm('Xóa món này?')"><input type="hidden" name="action" value="menu_dish_delete"><input type="hidden" name="dish_id" value="<?= e($dish['id']??'') ?>"><button aria-label="Xóa"><i class="bi bi-trash3"></i></button></form><?php endif; ?>
          </span><?php endforeach; ?>
          <?php if (!array_filter($dishes, fn($dish)=>($dish['category']??'')===$category)): ?><span class="text-muted small">Chưa có món.</span><?php endif; ?>
        </div></section>
      <?php endforeach; ?>
    </div></div>

  <?php else: $editingTemplate = $menuView === 'template'; $gridData = $editingTemplate ? $template : $meals; ?>
    <div class="menu-toolbar mb-3">
      <?php if ($editingTemplate): ?>
        <span></span><button type="button" class="btn btn-outline-secondary" onclick="openQuickDishModal()"><i class="bi bi-lightning-charge me-2"></i>Gán nhanh món</button>
      <?php else: ?>
        <div class="d-flex align-items-center gap-2">
          <a class="btn btn-light" href="?tab=menu&menu_view=week&week=<?= e($previousWeek) ?>"><i class="bi bi-chevron-left"></i></a>
          <form method="get"><input type="hidden" name="tab" value="menu"><input type="hidden" name="menu_view" value="week"><input type="date" class="form-control" name="week" value="<?= e($week) ?>" onchange="this.form.submit()"></form>
          <a class="btn btn-light" href="?tab=menu&menu_view=week&week=<?= e($nextWeek) ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
        <?php if ($canEditCurrent): ?><div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#copyMenuModal"><i class="bi bi-copy me-2"></i>Sao chép từ tuần khác</button>
          <form method="post"><input type="hidden" name="action" value="menu_apply_template"><input type="hidden" name="week_start" value="<?= e($week) ?>"><button class="btn btn-nt"><i class="bi bi-check-lg me-2"></i>Lên từ mẫu</button></form>
        </div><?php endif; ?>
      <?php endif; ?>
    </div>
    <form method="post" id="menuGridForm">
      <input type="hidden" name="action" value="<?= $editingTemplate?'menu_template_save':'menu_save' ?>">
      <?php if (!$editingTemplate): ?><input type="hidden" name="week_start" value="<?= e($week) ?>"><?php endif; ?>
      <div class="card card-soft menu-grid-wrap"><div class="table-responsive"><table class="menu-grid">
        <thead><tr><th>Bữa</th><?php $dateCursor=$week; foreach ($dayLabels as $dayKey=>$dayLabel): ?><th><?= e($dayLabel) ?><?php if (!$editingTemplate): ?><small><?= date('d/m', strtotime($dateCursor)) ?></small><?php $dateCursor=date('Y-m-d', strtotime($dateCursor.' +1 day')); endif; ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($mealLabels as $mealKey=>$mealLabel): ?><tr><th><span><?= e($mealLabel) ?></span></th><?php foreach ($dayLabels as $dayKey=>$dayLabel): $value=$cellValue($gridData[$dayKey][$mealKey]??''); ?>
          <td class="menu-cell" data-day="<?= e($dayKey) ?>" data-meal="<?= e($mealKey) ?>" onclick="openDishPicker(this)">
            <input type="hidden" name="<?= e($dayKey.'_'.$mealKey) ?>" value="<?= e($value) ?>"><div class="menu-cell-content"><?php if ($value!==''): foreach (array_filter(array_map('trim',explode(',',$value))) as $dishName): ?><span class="menu-chip small"><?= e($dishName) ?></span><?php endforeach; else: ?><span class="menu-add-hint">+ Thêm món</span><?php endif; ?></div>
          </td>
        <?php endforeach; ?></tr><?php endforeach; ?></tbody>
      </table></div><div class="menu-grid-note"><i class="bi bi-lightbulb text-warning"></i> Nhấn vào ô trong bảng để chọn món ăn cho bữa đó.</div></div>
      <?php if ($canEditCurrent): ?><div class="text-end mt-3"><button class="btn btn-nt px-4"><i class="bi bi-check2-circle me-2"></i>Lưu <?= $editingTemplate?'thực đơn mẫu':'thực đơn tuần' ?></button></div><?php endif; ?>
    </form>
  <?php endif; ?>

  <div class="modal fade" id="dishPickerModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chọn món ăn</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="dishPickerOptions"></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button class="btn btn-nt" type="button" onclick="applyDishPicker()">Áp dụng</button></div></div></div></div>
  <div class="modal fade" id="copyMenuModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Sao chép từ tuần khác</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="menu_copy_week"><input type="hidden" name="week_start" value="<?= e($week) ?>"><label class="form-label">Chọn ngày thứ Hai của tuần nguồn</label><input type="date" class="form-control" name="source_week" required></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal" type="button">Hủy</button><button class="btn btn-nt">Sao chép</button></div></form></div></div>
  <script>window.ntMenuDishes=<?= json_encode(array_values(array_map(fn($dish)=>$dish['name']??'', $dishes)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>

<?php elseif ($tab === 'stats'): ?>
  <?php
    $reportMonth = trim($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $reportMonth)) $reportMonth = date('Y-m');
    $from = $reportMonth . '-01';
    $to = date('Y-m-t', strtotime($from));
    $full = noitru_stats_full($from, $to);
    $attTotal = array_sum($full['attendance']);
    $exitTotal = array_sum($full['exits']);
    $mealTotal = $full['meals']['sang']+$full['meals']['trua']+$full['meals']['toi'];
    $healthTypeLabels = ['medicine'=>'Phát thuốc','first_aid'=>'Sơ cứu','hospital'=>'Vào viện','family_pickup'=>'Gia đình đón','other'=>'Khác'];
  ?>
  <section class="stats-report">
  <div class="stats-report-heading">
    <div><h4><i class="bi bi-bar-chart-line"></i> Báo cáo tổng hợp nội trú</h4><p>Thống kê đầy đủ hoạt động nội trú theo tháng</p></div>
    <form method="get" class="stats-month-form"><input type="hidden" name="tab" value="stats"><input class="form-control" type="month" name="month" value="<?= e($reportMonth) ?>" onchange="this.form.submit()"></form>
    <div class="stats-actions"><a class="btn btn-outline-success" href="<?= e(BASE_URL.'noitru.php?tab=stats&month='.$reportMonth.'&export=csv') ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Xuất bảng dữ liệu</a><button class="btn btn-nt" type="button" onclick="window.print()"><i class="bi bi-printer"></i> In / Lưu PDF</button></div>
  </div>

  <header class="stats-print-header"><div><strong><?= e(defined('SCHOOL_NAME')?SCHOOL_NAME:'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN') ?></strong></div><div><strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong><br>Độc lập - Tự do - Hạnh phúc</div><h2>BÁO CÁO TỔNG HỢP CÔNG TÁC NỘI TRÚ</h2><p>Tháng <?= e(date('m/Y',strtotime($from))) ?></p></header>

  <div class="stats-kpis">
    <div><i class="bi bi-people"></i><strong><?= (int)$full['boarders'] ?></strong><span>Học sinh nội trú</span></div>
    <div><i class="bi bi-egg-fried"></i><strong><?= number_format($mealTotal) ?></strong><span>Tổng lượt ăn</span></div>
    <div><i class="bi bi-clipboard-check"></i><strong><?= number_format($attTotal) ?></strong><span>Lượt điểm danh</span></div>
    <div><i class="bi bi-door-open"></i><strong><?= number_format($exitTotal) ?></strong><span>Phiếu ra/vào KTX</span></div>
    <div><i class="bi bi-heart-pulse"></i><strong><?= number_format($full['health']) ?></strong><span>Hồ sơ y tế</span></div>
    <div><i class="bi bi-calendar2-week"></i><strong><?= number_format($full['duty']) ?></strong><span>Ca trực</span></div>
    <div><i class="bi bi-box-seam"></i><strong><?= number_format($full['rice']['total_kg']??0,2) ?></strong><span>Kg gạo sử dụng</span></div>
    <div><i class="bi bi-capsule"></i><strong><?= number_format($full['medicine_issued']) ?></strong><span>Đơn vị thuốc đã phát</span></div>
  </div>

  <div class="stats-section"><h5>I. Bảng tổng hợp hoạt động theo ngày</h5><div class="table-responsive"><table class="table stats-daily-table align-middle"><thead><tr><th rowspan="2">Ngày</th><th colspan="3">Suất ăn</th><th colspan="4">Điểm danh</th><th rowspan="2">Ra/vào</th><th rowspan="2">Y tế</th><th rowspan="2">Ca trực</th><th rowspan="2">Gạo (kg)</th></tr><tr><th>Sáng</th><th>Trưa</th><th>Tối</th><th>Có mặt</th><th>Vắng</th><th>Muộn</th><th>Có phép</th></tr></thead><tbody>
    <?php foreach($full['daily'] as $date=>$row): ?><tr><td><?= e(date('d/m',strtotime($date))) ?></td><td><?= $row['meals']['sang'] ?></td><td><?= $row['meals']['trua'] ?></td><td><?= $row['meals']['toi'] ?></td><td><?= $row['attendance']['present'] ?></td><td class="text-danger"><?= $row['attendance']['absent'] ?></td><td><?= $row['attendance']['late'] ?></td><td><?= $row['attendance']['excused'] ?></td><td><?= $row['exits'] ?></td><td><?= $row['health'] ?></td><td><?= $row['duty'] ?></td><td><?= number_format($row['rice_kg'],3) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th>Tổng</th><th><?= $full['meals']['sang'] ?></th><th><?= $full['meals']['trua'] ?></th><th><?= $full['meals']['toi'] ?></th><th><?= $full['attendance']['present'] ?></th><th><?= $full['attendance']['absent'] ?></th><th><?= $full['attendance']['late'] ?></th><th><?= $full['attendance']['excused'] ?></th><th><?= $exitTotal ?></th><th><?= $full['health'] ?></th><th><?= $full['duty'] ?></th><th><?= number_format($full['rice']['total_kg']??0,3) ?></th></tr></tfoot></table></div></div>

  <div class="stats-detail-grid">
    <div class="stats-section"><h5>II. Học sinh nội trú theo lớp</h5><table class="table table-sm mb-0"><thead><tr><th>Lớp</th><th class="text-end">Số học sinh</th></tr></thead><tbody><?php foreach($full['classes'] as $class=>$count): ?><tr><td><?= e($class) ?></td><td class="text-end"><strong><?= $count ?></strong></td></tr><?php endforeach; ?></tbody><tfoot><tr><th>Tổng</th><th class="text-end"><?= $full['boarders'] ?></th></tr></tfoot></table></div>
    <div class="stats-section"><h5>III. Tổng hợp nghiệp vụ</h5><div class="stats-summary-list"><div><span>Điểm danh có mặt</span><strong><?= $full['attendance']['present'] ?></strong></div><div><span>Vắng</span><strong><?= $full['attendance']['absent'] ?></strong></div><div><span>Có phép</span><strong><?= $full['attendance']['excused'] ?></strong></div><div><span>Phiếu đã duyệt</span><strong><?= $full['exits']['approved'] ?></strong></div><div><span>Phiếu chờ duyệt</span><strong><?= $full['exits']['pending'] ?></strong></div><?php foreach($healthTypeLabels as $key=>$label): ?><div><span>Y tế: <?= e($label) ?></span><strong><?= $full['health_types'][$key]??0 ?></strong></div><?php endforeach; ?></div></div>
  </div>
  <div class="stats-signatures"><div><strong>NGƯỜI LẬP BÁO CÁO</strong><small>(Ký, ghi rõ họ tên)</small></div><div><em>Pà Vầy Sủ, ngày ..... tháng ..... năm <?= date('Y',strtotime($from)) ?></em><strong>HIỆU TRƯỞNG</strong><small>(Ký, đóng dấu)</small></div></div>
  </section>

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
function selectedMealStudents(){
  return Array.from(document.querySelectorAll('#mealReportForm .meal-student')).filter(function(row){
    return row.querySelector('.meal-absent')?.checked;
  }).map(function(row){return row.dataset.studentName||''});
}
function mealNames(value){
  var labels={sang:'Bữa sáng',trua:'Bữa trưa',toi:'Bữa tối'};
  return String(value||'').split(',').filter(Boolean).map(function(key){return labels[key]||key}).join(', ');
}
function mealDateVi(value){
  var parts=String(value||'').split('-');return parts.length===3?parts[2]+'/'+parts[1]+'/'+parts[0]:value;
}
function openLongMealModal(){
  var selected=selectedMealStudents();
  if(!selected.length){alert('Hãy tích chọn ít nhất 1 học sinh nghỉ trước.');return}
  document.getElementById('longAbsentCount').textContent=selected.length;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('longMealModal')).show();
}
function continueLongMeal(){
  var from=document.getElementById('longFromInput').value,until=document.getElementById('longUntilInput').value;
  if(!from||!until||until<from){alert('Khoảng ngày nghỉ chưa hợp lệ.');return}
  document.getElementById('mealSubmissionMode').value='long';
  document.getElementById('mealLongFrom').value=from;
  document.getElementById('mealLongUntil').value=until;
  document.getElementById('mealTargetMeals').value=document.getElementById('longMealsInput').value;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('longMealModal')).hide();
  showMealConfirmation();
}
function prepareMealConfirmation(event){
  event.preventDefault();
  var form=document.getElementById('mealReportForm');
  document.getElementById('mealSubmissionMode').value='regular';
  document.getElementById('mealTargetMeals').value=form.dataset.regularMeals||'';
  document.getElementById('mealLongFrom').value=form.elements.date.value;
  document.getElementById('mealLongUntil').value=form.elements.date.value;
  showMealConfirmation();return false;
}
function showMealConfirmation(){
  var form=document.getElementById('mealReportForm'),selected=selectedMealStudents(),total=form.querySelectorAll('input[name="sid[]"]').length;
  var mode=document.getElementById('mealSubmissionMode').value;
  var from=document.getElementById('mealLongFrom').value,until=document.getElementById('mealLongUntil').value;
  var target=document.getElementById('mealTargetMeals').value,range=mealDateVi(from);
  if(mode==='long'&&until!==from)range+=' – '+mealDateVi(until);
  var body=document.getElementById('mealConfirmBody');body.replaceChildren();
  var summary=document.createElement('div');summary.className='row g-2 mb-3';
  [['Lớp',form.elements.class_name.value],['Thời gian',range],['Bữa ăn',mealNames(target)],['Số học sinh',String(total)],['Ăn',String(total-selected.length)],['Vắng',String(selected.length)]].forEach(function(item){
    var cell=document.createElement('div');cell.className='col-6';var box=document.createElement('div');box.className='border rounded-3 p-2 h-100';
    var small=document.createElement('small');small.className='d-block text-muted';small.textContent=item[0];var strong=document.createElement('strong');strong.textContent=item[1];box.append(small,strong);cell.append(box);summary.append(cell);
  });body.append(summary);
  var title=document.createElement('h6');title.textContent=selected.length?'Danh sách học sinh vắng':'Không có học sinh vắng';body.append(title);
  if(selected.length){var list=document.createElement('div');list.className='meal-confirm-list';selected.forEach(function(name,index){var row=document.createElement('div');var n=document.createElement('span');n.textContent=(index+1)+'. '+name;row.append(n);list.append(row)});body.append(list)}
  bootstrap.Modal.getOrCreateInstance(document.getElementById('mealConfirmModal')).show();
}
function confirmMealSubmit(){
  var form=document.getElementById('mealReportForm');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('mealConfirmModal')).hide();
  form.submit();
}
function openMealExcelModal(type){
  var input=document.getElementById('mealExcelType'),title=document.getElementById('mealExcelTitle');
  if(!input||!title)return;
  var breakfast=type==='breakfast';
  input.value=breakfast?'month_breakfast':'month_lunch_dinner';
  title.innerHTML='<i class="bi bi-file-earmark-excel text-success me-2"></i>'+(breakfast?'Xuất báo cáo bữa sáng':'Xuất báo cáo bữa trưa, tối');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('mealExcelModal')).show();
}
function toggleRicePeriod(){
  var type=document.getElementById('ricePeriodType')?.value||'month';
  document.querySelectorAll('[data-rice-period]').forEach(function(box){
    box.hidden=box.dataset.ricePeriod!==type;
  });
}
function downloadMealImage(key){
  var card=document.getElementById('mealCard-'+key); if(!card)return;
  var canvas=document.createElement('canvas'),ctx=canvas.getContext('2d'),w=900,h=560;
  canvas.width=w;canvas.height=h;ctx.fillStyle='#fff';ctx.fillRect(0,0,w,h);
  ctx.textAlign='center';ctx.fillStyle='#64748b';ctx.font='20px Arial';ctx.fillText('TRƯỜNG PTDTNT THCS&THPT XÍN MẦN',w/2,48);
  ctx.fillStyle='#0284c7';ctx.font='bold 34px Arial';ctx.fillText((card.dataset.mealLabel||'BỮA ĂN').toLocaleUpperCase('vi'),w/2,95);
  ctx.fillStyle='#334155';ctx.font='21px Arial';ctx.fillText(card.dataset.date||'',w/2,130);
  ctx.strokeStyle='#0ea5e9';ctx.lineWidth=3;ctx.beginPath();ctx.moveTo(80,155);ctx.lineTo(820,155);ctx.stroke();
  var stats=[['TỔNG',card.dataset.total,'#334155'],['ĂN',card.dataset.eat,'#16a34a'],['VẮNG',card.dataset.absent,'#dc2626']];
  stats.forEach(function(s,i){var x=95+i*245;ctx.fillStyle=i===1?'#ecfdf5':(i===2?'#fff1f2':'#f1f5f9');ctx.fillRect(x,190,220,110);ctx.fillStyle=s[2];ctx.font='bold 34px Arial';ctx.fillText(s[1]||'0',x+110,238);ctx.font='16px Arial';ctx.fillText(s[0],x+110,270)});
  ctx.textAlign='left';ctx.fillStyle='#334155';ctx.font='bold 18px Arial';ctx.fillText('Lớp đã báo:',80,350);ctx.font='17px Arial';
  var classes=card.dataset.classes||'Chưa có';var words=classes.split(', '),line='',y=385;
  words.forEach(function(word){var next=line?(line+', '+word):word;if(ctx.measureText(next).width>730){ctx.fillText(line,80,y);line=word;y+=30}else line=next});if(line)ctx.fillText(line,80,y);
  ctx.fillStyle='#64748b';ctx.font='15px Arial';ctx.fillText('Xuất lúc: '+new Date().toLocaleString('vi-VN'),80,510);
  var link=document.createElement('a');link.download='bao-an-'+key+'-'+(card.dataset.date||'').replaceAll('/','-')+'.png';link.href=canvas.toDataURL('image/png');link.click();
}
var mealDayExport={canvas:null,type:'summary',filename:''};
function mealRoundRect(ctx,x,y,w,h,r,fill,stroke){
  ctx.beginPath();ctx.roundRect(x,y,w,h,r);
  if(fill){ctx.fillStyle=fill;ctx.fill()}if(stroke){ctx.strokeStyle=stroke;ctx.lineWidth=1;ctx.stroke()}
}
function mealWrap(ctx,text,x,y,maxWidth,lineHeight,maxLines){
  var words=String(text||'').split(/\s+/),line='',lines=[],i;
  for(i=0;i<words.length;i++){
    var test=line?line+' '+words[i]:words[i];
    if(ctx.measureText(test).width>maxWidth&&line){lines.push(line);line=words[i]}else line=test;
  }
  if(line)lines.push(line);
  if(maxLines&&lines.length>maxLines){lines=lines.slice(0,maxLines);lines[maxLines-1]=lines[maxLines-1].replace(/[,\s]+$/,'')+'…'}
  lines.forEach(function(row,index){ctx.fillText(row,x,y+index*lineHeight)});
  return Math.max(1,lines.length)*lineHeight;
}
function mealExportBase(title,color,height){
  var data=window.ntMealDayData||{},canvas=document.createElement('canvas'),ctx=canvas.getContext('2d'),w=900;
  canvas.width=w;canvas.height=height;ctx.fillStyle='#ffffff';ctx.fillRect(0,0,w,height);
  ctx.textAlign='center';ctx.fillStyle='#64748b';ctx.font='20px Arial';ctx.fillText(data.school||'',w/2,48);
  ctx.fillStyle=color;ctx.font='bold 34px Arial';ctx.fillText(title,w/2,94);
  ctx.fillStyle='#334155';ctx.font='21px Arial';ctx.fillText('Ngày '+(data.date||''),w/2,130);
  ctx.strokeStyle=color;ctx.lineWidth=3;ctx.beginPath();ctx.moveTo(70,155);ctx.lineTo(830,155);ctx.stroke();
  return {canvas:canvas,ctx:ctx,w:w};
}
function buildMealSummaryImage(){
  var data=window.ntMealDayData||{},meals=data.meals||{},keys=['sang','trua','toi'],totalEat=0,totalAbsent=0;
  keys.forEach(function(k){totalEat+=Number(meals[k]?.eat||0);totalAbsent+=Number(meals[k]?.absent||0)});
  var absentLines=0;keys.forEach(function(k){if(Number(meals[k]?.absent||0)>0)absentLines+=Math.max(2,Math.ceil((meals[k].students||[]).length/3))});
  var base=mealExportBase('THỐNG KÊ BỮA ĂN','#0284c7',Math.max(930,760+absentLines*32)),ctx=base.ctx,w=base.w;
  var colors={sang:['#fff7ed','#ea580c'],trua:['#ecfdf5','#16a34a'],toi:['#eef2ff','#4f46e5']};
  keys.forEach(function(k,i){
    var m=meals[k]||{},x=70+i*255,c=colors[k];
    mealRoundRect(ctx,x,185,235,145,14,c[0],'#dbe3eb');
    ctx.textAlign='center';ctx.fillStyle=c[1];ctx.font='bold 20px Arial';ctx.fillText(String(m.label||k).toLocaleUpperCase('vi'),x+117,220);
    ctx.fillStyle='#16a34a';ctx.font='bold 34px Arial';ctx.fillText(String(m.eat||0),x+82,270);
    ctx.fillStyle='#64748b';ctx.font='22px Arial';ctx.fillText('/ '+String(m.total||0),x+160,270);
    ctx.fillStyle='#dc2626';ctx.font='18px Arial';ctx.fillText('-'+String(m.absent||0)+' vắng',x+117,305);
  });
  mealRoundRect(ctx,70,350,760,115,14,'#f8fafc','#e2e8f0');
  [['TỔNG SUẤT',totalEat,'#16a34a'],['TỔNG VẮNG',totalAbsent,'#dc2626'],['KG GẠO',Number(data.rice_kg||0).toFixed(2),'#b45309']].forEach(function(s,i){
    var x=70+i*253;ctx.textAlign='center';ctx.fillStyle=s[2];ctx.font='bold 34px Arial';ctx.fillText(String(s[1]),x+126,400);ctx.fillStyle='#64748b';ctx.font='16px Arial';ctx.fillText(s[0],x+126,435);
      if(i<2){ctx.strokeStyle='#e2e8f0';ctx.beginPath();ctx.moveTo(x+253,370);ctx.lineTo(x+253,445);ctx.stroke()}
  });
  mealRoundRect(ctx,70,485,760,110,14,'#fffbeb','#fde68a');
  [['GẠO BỮA TRƯA',Number(data.rice_lunch_kg||0).toFixed(2)+' kg'],['GẠO BỮA TỐI',Number(data.rice_dinner_kg||0).toFixed(2)+' kg'],['GẠO CẢ NGÀY',Number(data.rice_kg||0).toFixed(2)+' kg']].forEach(function(s,i){
    var rx=70+i*253;ctx.textAlign='center';ctx.fillStyle=i===2?'#0369a1':'#b45309';ctx.font='bold 27px Arial';ctx.fillText(s[1],rx+126,535);ctx.fillStyle='#64748b';ctx.font='15px Arial';ctx.fillText(s[0],rx+126,566);
    if(i<2){ctx.strokeStyle='#fde68a';ctx.beginPath();ctx.moveTo(rx+253,500);ctx.lineTo(rx+253,580);ctx.stroke()}
  });
  ctx.textAlign='left';var y=625;
  mealRoundRect(ctx,70,y,760,Math.max(120,base.canvas.height-y-75),14,'#fff7f7','#fecaca');
  ctx.fillStyle='#dc2626';ctx.font='bold 19px Arial';ctx.fillText('● DANH SÁCH VẮNG',92,y+34);y+=68;
  var any=false;
  keys.forEach(function(k){
    var m=meals[k]||{},students=m.students||[];if(!students.length)return;any=true;
    ctx.fillStyle=colors[k][1];ctx.font='bold 18px Arial';ctx.fillText((m.label||k)+' ('+students.length+'): ',92,y);
    ctx.fillStyle='#334155';ctx.font='17px Arial';
    var text=students.map(function(s){return (s.class?s.class+': ':'')+(s.name||'')}).join(', ');
    y+=mealWrap(ctx,text,92,y+28,710,27)+38;
  });
  if(!any){ctx.fillStyle='#16a34a';ctx.font='bold 19px Arial';ctx.fillText('✓ Không có học sinh vắng ăn',92,y)}
  ctx.fillStyle='#64748b';ctx.font='15px Arial';ctx.fillText('Người báo: '+(data.reporter||''),70,base.canvas.height-30);
  ctx.textAlign='right';ctx.fillText(new Date().toLocaleString('vi-VN'),830,base.canvas.height-30);
  return base.canvas;
}
function buildMealGroupsImage(){
  var data=window.ntMealDayData||{},meals=data.meals||{},keys=['sang','trua','toi'],rows=0;
  keys.forEach(function(k){var groups={};(meals[k]?.students||[]).forEach(function(s){groups[String(s.group||'Chưa xếp mâm')]=1});rows+=Math.max(1,Object.keys(groups).length)});
  var base=mealExportBase('DS VẮNG THEO MÂM','#dc2626',Math.max(760,440+rows*76)),ctx=base.ctx,w=base.w;
  var total=keys.reduce(function(n,k){return n+Number(meals[k]?.absent||0)},0),x=70;
  mealRoundRect(ctx,70,180,760,95,14,'#fff7f7','#fecaca');
  keys.forEach(function(k,i){var m=meals[k]||{};ctx.textAlign='center';ctx.fillStyle=k==='sang'?'#ea580c':(k==='trua'?'#16a34a':'#4f46e5');ctx.font='17px Arial';ctx.fillText(m.label||k,x+i*170+75,215);ctx.font='bold 28px Arial';ctx.fillText(String(m.absent||0),x+i*170+75,250)});
  ctx.fillStyle='#dc2626';ctx.font='17px Arial';ctx.fillText('TỔNG',760,215);ctx.font='bold 28px Arial';ctx.fillText(String(total),760,250);
  var y=305,colors={sang:'#ea580c',trua:'#16a34a',toi:'#4f46e5'};
  keys.forEach(function(k){
    var m=meals[k]||{},students=m.students||[],groups={};
    students.forEach(function(s){var g=String(s.group||'Chưa xếp mâm');(groups[g]||(groups[g]=[])).push(s)});
    ctx.textAlign='left';ctx.fillStyle='#f8fafc';ctx.fillRect(70,y,760,42);ctx.fillStyle=colors[k];ctx.font='bold 19px Arial';ctx.fillText(m.label||k,90,y+27);
    ctx.textAlign='right';ctx.fillStyle=students.length?'#dc2626':'#16a34a';ctx.fillText(students.length?students.length+' vắng':'✓ Đủ',810,y+27);y+=56;
    if(!students.length){y+=20;return}
    Object.keys(groups).sort(function(a,b){return a.localeCompare(b,'vi',{numeric:true})}).forEach(function(group){
      var list=groups[group];ctx.textAlign='left';ctx.fillStyle=colors[k];ctx.font='bold 17px Arial';ctx.fillText('Mâm '+group+' ('+list.length+'):',92,y);
      ctx.fillStyle='#334155';ctx.font='16px Arial';var text=list.map(function(s){return (s.name||'')+(s.class?' ('+s.class+')':'')}).join(', ');
      y+=mealWrap(ctx,text,245,y,565,25)+22;
    });y+=8;
  });
  ctx.fillStyle='#64748b';ctx.font='15px Arial';ctx.textAlign='left';ctx.fillText('Người báo: '+(data.reporter||''),70,base.canvas.height-30);ctx.textAlign='right';ctx.fillText(new Date().toLocaleString('vi-VN'),830,base.canvas.height-30);
  return base.canvas;
}
function openMealDayExport(type){
  mealDayExport.type=type;mealDayExport.canvas=type==='groups'?buildMealGroupsImage():buildMealSummaryImage();
  mealDayExport.filename=(type==='groups'?'ds-vang-theo-mam-':'thong-ke-bua-an-')+String(window.ntMealDayData?.date||'').replaceAll('/','-')+'.png';
  document.getElementById('mealDayExportTitle').innerHTML='<i class="bi '+(type==='groups'?'bi-people':'bi-image')+' me-2"></i>'+(type==='groups'?'Xuất ảnh danh sách vắng theo mâm':'Xuất ảnh thống kê bữa ăn');
  document.getElementById('mealDayExportPreview').src=mealDayExport.canvas.toDataURL('image/png');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('mealDayExportModal')).show();
}
function downloadMealDayExport(){
  if(!mealDayExport.canvas)return;var link=document.createElement('a');link.download=mealDayExport.filename;link.href=mealDayExport.canvas.toDataURL('image/png');link.click();
}
async function shareMealDayExport(){
  if(!mealDayExport.canvas)return;
  mealDayExport.canvas.toBlob(async function(blob){
    var file=new File([blob],mealDayExport.filename,{type:'image/png'});
    if(navigator.canShare&&navigator.canShare({files:[file]})){try{await navigator.share({files:[file],title:'Báo cáo bữa ăn'});return}catch(error){if(error.name==='AbortError')return}}
    downloadMealDayExport();
  },'image/png');
}
var ntMenuCell=null;
function openDishPicker(cell){
  if(!cell||!document.getElementById('dishPickerModal'))return;
  ntMenuCell=cell;var current=(cell.querySelector('input')?.value||'').split(',').map(function(v){return v.trim()}).filter(Boolean);
  var box=document.getElementById('dishPickerOptions');box.replaceChildren();
  if(!(window.ntMenuDishes||[]).length){var empty=document.createElement('div');empty.className='alert alert-info mb-0';empty.textContent='Chưa có món ăn. Hãy thêm món trong tab Danh sách món ăn trước.';box.append(empty)}
  (window.ntMenuDishes||[]).forEach(function(name,index){var label=document.createElement('label');label.className='d-flex align-items-center gap-2 border rounded-3 p-2 mb-2';var input=document.createElement('input');input.type='checkbox';input.className='form-check-input m-0';input.value=name;input.id='dishPick'+index;input.checked=current.includes(name);var labelText=document.createElement('span');labelText.textContent=name;label.append(input,labelText);box.append(label)});
  bootstrap.Modal.getOrCreateInstance(document.getElementById('dishPickerModal')).show();
}
function applyDishPicker(){
  if(!ntMenuCell)return;var values=Array.from(document.querySelectorAll('#dishPickerOptions input:checked')).map(function(input){return input.value});var hidden=ntMenuCell.querySelector('input'),content=ntMenuCell.querySelector('.menu-cell-content');hidden.value=values.join(', ');content.replaceChildren();
  if(values.length)values.forEach(function(name){var chip=document.createElement('span');chip.className='menu-chip small';chip.textContent=name;content.append(chip)});else{var hint=document.createElement('span');hint.className='menu-add-hint';hint.textContent='+ Thêm món';content.append(hint)}
  bootstrap.Modal.getOrCreateInstance(document.getElementById('dishPickerModal')).hide();
}
function openQuickDishModal(){var first=document.querySelector('.menu-cell');if(first)openDishPicker(first)}
toggleRicePeriod();
</script>
</body>
</html>
