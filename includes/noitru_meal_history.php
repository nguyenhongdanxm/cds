<?php
/**
 * Mở rộng Báo ăn nội trú:
 * - Ghi lịch sử thay đổi trước/sau mỗi lần lưu.
 * - GVCN chỉ xem lịch sử lớp được giao.
 * - Sau khi khóa chỉ admin hoặc người có mức quyền Xóa tại nt.baoan được sửa.
 *
 * File được nạp từ auth.php sau khi hệ thống xác thực và phân quyền hoàn tất.
 */

if (!defined('DATA_PATH') || !function_exists('current_user')) return;

function nt_meal_history_is_request() {
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    return $script === 'noitru.php' && (($_GET['tab'] ?? $_POST['tab'] ?? '') === 'meals' || ($_POST['action'] ?? '') === 'meals_save');
}

if (!nt_meal_history_is_request()) return;

require_once __DIR__ . '/noitru_store.php';

define('NOITRU_MEAL_HISTORY', DATA_PATH . '/noitru/meal_history.json');

function nt_meal_history_all() {
    noitru_ensure_dir();
    $rows = load_json(NOITRU_MEAL_HISTORY, []);
    return is_array($rows) ? $rows : [];
}

function nt_meal_history_save(array $rows) {
    noitru_ensure_dir();
    save_json(NOITRU_MEAL_HISTORY, array_values($rows));
}

function nt_meal_history_can_edit_locked() {
    $user = current_user();
    if (!$user) return false;
    return ($user['role'] ?? '') === 'admin' || can_delete_perm('nt.baoan');
}

function nt_meal_history_target_meals($meal, $raw) {
    $allowed = ['sang','trua','toi'];
    $items = array_values(array_intersect($allowed, array_filter(array_map('trim', explode(',', (string)$raw)))));
    if ($items) return $items;
    return $meal === 'all' ? $allowed : (in_array($meal, $allowed, true) ? [$meal] : []);
}

function nt_meal_history_target_dates($date, $mode, $from, $until) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return [];
    if ($mode !== 'long') return [$date];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || $from < $date) $from = $date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || $until < $from) $until = $from;
    $max = date('Y-m-d', strtotime($date . ' +60 days'));
    if ($from > $max) $from = $max;
    if ($until > $max) $until = $max;
    $dates = [];
    for ($cursor = $from; $cursor <= $until; $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'))) $dates[] = $cursor;
    return $dates;
}

function nt_meal_history_student_map() {
    $map = [];
    foreach (noitru_boarders_live() as $student) $map[(string)($student['id'] ?? '')] = $student;
    return $map;
}

function nt_meal_history_snapshot(array $dates, array $meals, array $studentIds) {
    $snapshot = [];
    foreach ($dates as $date) {
        $day = noitru_meals_for_date($date);
        foreach ($studentIds as $sid) {
            foreach ($meals as $meal) {
                $snapshot[$date][$sid][$meal] = $day[$sid][$meal] ?? 'yes';
            }
        }
    }
    return $snapshot;
}

function nt_meal_history_append_from_change(array $context) {
    $dates = $context['dates'] ?? [];
    $meals = $context['meals'] ?? [];
    $studentIds = $context['student_ids'] ?? [];
    $before = $context['before'] ?? [];
    $className = $context['class_name'] ?? '';
    $studentMap = nt_meal_history_student_map();
    $rows = nt_meal_history_all();
    $user = current_user() ?: [];

    foreach ($dates as $date) {
        $afterDay = noitru_meals_for_date($date);
        foreach ($meals as $meal) {
            $changes = [];
            foreach ($studentIds as $sid) {
                $student = $studentMap[$sid] ?? null;
                if (!$student || (string)($student['class_name'] ?? '') !== (string)$className) continue;
                $old = $before[$date][$sid][$meal] ?? 'yes';
                $new = $afterDay[$sid][$meal] ?? 'yes';
                if ($old === $new) continue;
                $changes[] = [
                    'student_id' => $sid,
                    'student_name' => $student['name'] ?? '',
                    'from' => $old,
                    'to' => $new,
                ];
            }
            if (!$changes) continue;
            $rows[] = [
                'id' => noitru_uid('mh'),
                'date' => $date,
                'class_name' => $className,
                'meal' => $meal,
                'changes' => $changes,
                'changed_count' => count($changes),
                'changed_by' => $user['name'] ?? ($user['username'] ?? ''),
                'changed_by_id' => $user['id'] ?? '',
                'changed_at' => noitru_now(),
                'after_lock' => !empty($context['after_lock']),
                'submission_mode' => $context['submission_mode'] ?? 'regular',
            ];
        }
    }

    if (count($rows) > 3000) $rows = array_slice($rows, -3000);
    nt_meal_history_save($rows);
}

function nt_meal_history_label($value) {
    return $value === 'no' ? 'Nghỉ ăn' : 'Có ăn';
}

function nt_meal_history_visible_rows($date, $className, $meal) {
    $rows = nt_meal_history_all();
    $visible = [];
    foreach (array_reverse($rows) as $row) {
        if (($row['date'] ?? '') !== $date) continue;
        if ($className !== '' && ($row['class_name'] ?? '') !== $className) continue;
        if ($meal !== 'all' && ($row['meal'] ?? '') !== $meal) continue;
        if (!can_class($row['class_name'] ?? '')) continue;
        $visible[] = $row;
        if (count($visible) >= 30) break;
    }
    return $visible;
}

function nt_meal_history_panel_html() {
    $date = (string)($_GET['date'] ?? date('Y-m-d', strtotime('+1 day')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d', strtotime('+1 day'));
    $className = trim((string)($_GET['class'] ?? ''));
    if ($className !== '' && !can_class($className)) $className = '';
    $meal = (string)($_GET['meal'] ?? 'sang');
    if (!in_array($meal, ['all','sang','trua','toi'], true)) $meal = 'sang';
    $rows = nt_meal_history_visible_rows($date, $className, $meal);
    $mealLabels = ['sang'=>'Bữa sáng','trua'=>'Bữa trưa','toi'=>'Bữa tối'];

    ob_start();
    ?>
    <div class="card card-soft mt-3" id="mealEditHistory">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div><strong><i class="bi bi-clock-history me-1"></i>Lịch sử chỉnh sửa báo ăn</strong><div class="small text-muted">GVCN chỉ xem lịch sử của lớp được giao.</div></div>
        <span class="badge bg-secondary"><?= count($rows) ?> lần sửa</span>
      </div>
      <div class="card-body p-0">
        <?php if (!$rows): ?>
          <div class="text-muted text-center py-4">Chưa có thay đổi nào trong ngày và bữa đang chọn.</div>
        <?php else: ?>
          <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Thời gian</th><th>Lớp / bữa</th><th>Người sửa</th><th>Nội dung thay đổi</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td class="small text-nowrap"><?= e(date('d/m/Y H:i', strtotime($row['changed_at'] ?? 'now'))) ?><?= !empty($row['after_lock']) ? '<br><span class="badge bg-warning text-dark">Sau khóa</span>' : '' ?></td>
                <td><strong><?= e($row['class_name'] ?? '') ?></strong><br><span class="small text-muted"><?= e($mealLabels[$row['meal'] ?? ''] ?? '') ?></span></td>
                <td><?= e($row['changed_by'] ?? '') ?></td>
                <td class="small">
                  <?php foreach (($row['changes'] ?? []) as $change): ?>
                    <div><strong><?= e($change['student_name'] ?? '') ?></strong>: <?= e(nt_meal_history_label($change['from'] ?? 'yes')) ?> → <?= e(nt_meal_history_label($change['to'] ?? 'yes')) ?></div>
                  <?php endforeach; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// Chặn hoặc chuẩn bị ghi lịch sử trước khi noitru.php xử lý lưu.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'meals_save') {
    $date = trim((string)($_POST['date'] ?? date('Y-m-d')));
    $meal = trim((string)($_POST['meal'] ?? ''));
    $className = trim((string)($_POST['class_name'] ?? ''));
    $mode = ($_POST['submission_mode'] ?? '') === 'long' ? 'long' : 'regular';
    $meals = nt_meal_history_target_meals($meal, $_POST['target_meals'] ?? '');
    $dates = nt_meal_history_target_dates($date, $mode, trim((string)($_POST['long_from'] ?? $date)), trim((string)($_POST['long_until'] ?? $date)));
    $ids = array_values(array_filter(array_map('strval', is_array($_POST['sid'] ?? null) ? $_POST['sid'] : [])));
    $studentMap = nt_meal_history_student_map();

    if ($className === '' || !can_class($className)) {
        flash('Bạn không có quyền báo ăn cho lớp này.', 'danger');
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals');
        exit;
    }
    foreach ($ids as $sid) {
        if (!isset($studentMap[$sid]) || (string)($studentMap[$sid]['class_name'] ?? '') !== $className) {
            flash('Danh sách học sinh không thuộc lớp được giao.', 'danger');
            header('Location: ' . BASE_URL . 'noitru.php?tab=meals');
            exit;
        }
    }

    $hasLocked = false;
    foreach ($dates as $targetDate) foreach ($meals as $targetMeal) {
        if ((noitru_meal_state($targetDate, $targetMeal)['status'] ?? 'open') !== 'open') $hasLocked = true;
    }
    $canLocked = nt_meal_history_can_edit_locked();
    if ($hasLocked && !$canLocked) {
        flash('Báo ăn đã khóa. Chỉ quản trị hoặc người được cấp mức quyền Xóa tại chức năng Báo ăn mới được sửa.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date) . '&class=' . urlencode($className) . '&meal=' . urlencode($meal));
        exit;
    }

    $context = [
        'dates'=>$dates,
        'meals'=>$meals,
        'student_ids'=>$ids,
        'class_name'=>$className,
        'before'=>nt_meal_history_snapshot($dates, $meals, $ids),
        'after_lock'=>$hasLocked,
        'submission_mode'=>$mode,
    ];
    register_shutdown_function(function () use ($context) {
        nt_meal_history_append_from_change($context);
    });

    // noitru.php cũ coi phạm vi toàn trường là quyền ép ghi sau khóa.
    // Chỉ trong POST đã được kiểm tra chặt ở trên, tạm chuyển session để đi qua nhánh đó.
    if ($hasLocked && $canLocked && allowed_classes() !== null) {
        $_SESSION['cds_user']['role'] = 'custom';
        $_SESSION['cds_user']['groups'] = array_values(array_filter($_SESSION['cds_user']['groups'] ?? [], fn($g) => $g !== 'gvcn'));
        $_SESSION['cds_user']['classes'] = [];
        $_SESSION['cds_user']['homeroom_classes'] = [];
    }
}

// Chèn lịch sử và mở giao diện sửa sau khóa cho tài khoản được cấp quyền.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['export'])) {
    $canLocked = nt_meal_history_can_edit_locked();
    ob_start(function ($html) use ($canLocked) {
        $panel = nt_meal_history_panel_html();
        $script = '';
        if ($canLocked) {
            $script = '<script>(function(){var f=document.getElementById("mealReportForm");if(!f)return;var locked=f.querySelectorAll("input.meal-absent:disabled");if(!locked.length)return;locked.forEach(function(x){x.disabled=false;});var body=f.querySelector(".card-body");if(body){var a=document.createElement("div");a.className="alert alert-warning py-2";a.innerHTML="<i class=\"bi bi-unlock\"></i> Bạn có quyền sửa báo ăn sau khóa. Thao tác sẽ được ghi vào lịch sử.";body.insertBefore(a,body.firstChild);}if(!f.querySelector(".meal-save-bar")){var bar=document.createElement("div");bar.className="card-body border-top meal-save-bar";bar.innerHTML="<button class=\"btn btn-warning w-100\" type=\"submit\"><i class=\"bi bi-pencil-square\"></i> Kiểm tra và lưu thay đổi sau khóa</button>";f.appendChild(bar);}})();</script>';
        }
        $insert = $panel . $script;
        if (stripos($html, '</main>') !== false) return preg_replace('/<\/main>/i', $insert . '</main>', $html, 1);
        if (stripos($html, '</body>') !== false) return preg_replace('/<\/body>/i', $insert . '</body>', $html, 1);
        return $html . $insert;
    });
}
