<?php
/**
 * Mở rộng Báo ăn nội trú:
 * - Ghi lịch sử thay đổi trước/sau mỗi lần lưu.
 * - GVCN xem toàn bộ lịch sử lớp được giao và mở lại phiếu khi còn thời gian.
 * - Sau khi khóa chỉ admin hoặc người có mức quyền Xóa tại nt.baoan được sửa.
 *
 * File được nạp từ auth.php sau khi hệ thống xác thực và phân quyền hoàn tất.
 */

if (!defined('DATA_PATH') || !function_exists('current_user')) return;

function nt_meal_history_is_request() {
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    return $script === 'noitru.php' && (($_GET['tab'] ?? $_POST['tab'] ?? '') === 'meals' || in_array(($_POST['action'] ?? ''), ['meals_save','meal_history_delete','meal_history_bulk_delete'], true));
}

if (!nt_meal_history_is_request()) return;

require_once __DIR__ . '/noitru_store.php';

define('NOITRU_MEAL_HISTORY', DATA_PATH . '/noitru/meal_history.json');
define('NOITRU_MEAL_HISTORY_DELETED', DATA_PATH . '/noitru/meal_history_deleted.json');

function nt_meal_history_deleted_sources() {
    $rows = load_json(NOITRU_MEAL_HISTORY_DELETED, []);
    return is_array($rows) ? array_fill_keys(array_map('strval', $rows), true) : [];
}

function nt_meal_history_remember_deleted_sources(array $rows) {
    $deleted = nt_meal_history_deleted_sources();
    foreach ($rows as $row) {
        $sourceId = trim((string)($row['source_report_id'] ?? ''));
        if ($sourceId !== '') $deleted[$sourceId] = true;
    }
    save_json(NOITRU_MEAL_HISTORY_DELETED, array_keys($deleted));
}

/**
 * Xóa dữ liệu nguồn của các lượt lịch sử được chọn để mọi bảng tổng hợp,
 * báo cáo tháng và thống kê gạo không tiếp tục tính các lượt này.
 */
function nt_meal_history_delete_related_data(array $historyRows) {
    $targets = [];
    foreach ($historyRows as $row) {
        $date = trim((string)($row['date'] ?? ''));
        $className = trim((string)($row['class_name'] ?? ''));
        $meal = trim((string)($row['meal'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $className === '' || !in_array($meal, ['sang','trua','toi'], true)) continue;
        $targets[$date . '|' . $className . '|' . $meal] = ['date'=>$date, 'class_name'=>$className, 'meal'=>$meal];
    }
    if (!$targets) return 0;

    /* Ghi dấu theo ngày–lớp–bữa: mọi báo cáo dẫn xuất phải loại cùng nguồn. */
    $deletedTargets = noitru_meal_deleted_targets();
    foreach (array_keys($targets) as $targetKey) $deletedTargets[$targetKey] = true;
    noitru_meal_deleted_targets_save($deletedTargets);

    $reportData = noitru_meal_reports_data();
    $beforeReports = count($reportData['reports'] ?? []);
    $reportData['reports'] = array_values(array_filter($reportData['reports'] ?? [], function ($report) use ($targets) {
        $key = (string)($report['date'] ?? '') . '|' . (string)($report['class_name'] ?? '') . '|' . (string)($report['meal'] ?? '');
        return !isset($targets[$key]);
    }));
    noitru_meal_reports_save($reportData);

    $studentsByClass = [];
    foreach (noitru_boarders_live() as $student) {
        $className = trim((string)($student['class_name'] ?? ''));
        $studentId = trim((string)($student['id'] ?? ''));
        if ($className !== '' && $studentId !== '') $studentsByClass[$className][$studentId] = true;
    }
    /* Bổ sung ID lưu trong nhật ký để vẫn xóa đúng khi học sinh đã chuyển lớp. */
    foreach ($historyRows as $historyRow) foreach (($historyRow['changes'] ?? []) as $change) {
        $className = trim((string)($historyRow['class_name'] ?? ''));
        $studentId = trim((string)($change['student_id'] ?? ''));
        if ($className !== '' && $studentId !== '') $studentsByClass[$className][$studentId] = true;
    }

    $mealRows = noitru_meals_all();
    foreach ($mealRows as &$mealRow) {
        $date = (string)($mealRow['date'] ?? '');
        $studentId = (string)($mealRow['student_id'] ?? '');
        foreach ($targets as $target) {
            if ($date !== $target['date'] || empty($studentsByClass[$target['class_name']][$studentId])) continue;
            unset($mealRow[$target['meal']]);
        }
    }
    unset($mealRow);
    $mealRows = array_values(array_filter($mealRows, fn($row) => isset($row['sang']) || isset($row['trua']) || isset($row['toi'])));
    save_json(NOITRU_MEALS, $mealRows);
    return $beforeReports - count($reportData['reports']);
}

function nt_meal_history_target_keys(array $historyRows) {
    $keys = [];
    foreach ($historyRows as $row) {
        $keys[(string)($row['date'] ?? '') . '|' . (string)($row['class_name'] ?? '') . '|' . (string)($row['meal'] ?? '')] = true;
    }
    return $keys;
}

function nt_meal_history_all() {
    noitru_ensure_dir();
    $rows = load_json(NOITRU_MEAL_HISTORY, []);
    $rows = is_array($rows) ? $rows : [];
    /* Đưa các phiếu đã báo trước khi có nhật ký vào sổ lịch sử một lần. */
    $known = []; $knownKeys = [];
    foreach ($rows as $row) {
        $known[(string)($row['source_report_id'] ?? '')] = true;
        $knownKeys[implode('|', [(string)($row['date']??''),(string)($row['class_name']??''),(string)($row['meal']??'')])] = true;
    }
    $changed = false;
    $deletedSources = nt_meal_history_deleted_sources();
    foreach ((array)(noitru_meal_reports_data()['reports'] ?? []) as $report) {
        $reportId = trim((string)($report['id'] ?? ''));
        $reportKey = implode('|', [(string)($report['date']??''),(string)($report['class_name']??''),(string)($report['meal']??'')]);
        if ($reportId === '' || isset($deletedSources[$reportId]) || isset($known[$reportId]) || isset($knownKeys[$reportKey])) continue;
        $rows[] = [
            'id'=>noitru_uid('mh'),'source_report_id'=>$reportId,'date'=>(string)($report['date']??''),
            'class_name'=>(string)($report['class_name']??''),'meal'=>(string)($report['meal']??''),'changes'=>[],
            'changed_count'=>(int)($report['absent_count']??0),'student_count'=>(int)($report['student_count']??0),
            'changed_by'=>(string)($report['reported_by']??''),'changed_by_id'=>'',
            'changed_at'=>(string)($report['updated_at']??$report['created_at']??noitru_now()),
            'after_lock'=>false,'submission_mode'=>'regular','legacy_import'=>true,
        ];
        $known[$reportId] = true; $knownKeys[$reportKey] = true; $changed = true;
    }
    if ($changed) save_json(NOITRU_MEAL_HISTORY, array_values(array_slice($rows, -3000)));
    return $rows;
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

function nt_meal_history_can_delete() {
    $user = current_user();
    if (!$user) return false;
    $groups = is_array($user['groups'] ?? null) ? $user['groups'] : [];
    return ($user['role'] ?? '') === 'admin' || in_array('ketoan', $groups, true) || can_delete_perm('nt.baoan');
}

function nt_meal_history_is_admin() {
    $user = current_user();
    return $user && ($user['role'] ?? '') === 'admin';
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
            $rows[] = [
                'id' => noitru_uid('mh'),
                'date' => $date,
                'class_name' => $className,
                'meal' => $meal,
                'changes' => $changes,
                'changed_count' => count($changes),
                'student_count' => count($studentIds),
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

function nt_meal_history_visible_rows($date, $className, $meal, $mode = 'recent') {
    $rows = nt_meal_history_all();
    $visible = [];
    foreach (array_reverse($rows) as $row) {
        if ($mode === 'date' && ($row['date'] ?? '') !== $date) continue;
        if ($mode === 'recent' && ($row['date'] ?? '') < date('Y-m-d', strtotime('-4 days'))) continue;
        if ($className !== '' && ($row['class_name'] ?? '') !== $className) continue;
        if ($meal !== 'all' && ($row['meal'] ?? '') !== $meal) continue;
        if (!can_class($row['class_name'] ?? '')) continue;
        $visible[] = $row;
        if (count($visible) >= 300) break;
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
    $historyDate = trim((string)($_GET['history_date'] ?? ''));
    $showAll = ($_GET['history_all'] ?? '') === '1';
    $validHistoryDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyDate) === 1;
    $mode = $showAll ? 'all' : ($validHistoryDate ? 'date' : 'recent');
    if ($mode === 'date') $date = $historyDate;
    $rows = nt_meal_history_visible_rows($date, $className, $meal, $mode);
    $mealLabels = ['sang'=>'Bữa sáng','trua'=>'Bữa trưa','toi'=>'Bữa tối'];
    $canDelete = nt_meal_history_can_delete();
    $isAdmin = nt_meal_history_is_admin();
    $csrf = $_SESSION['nt_meal_history_csrf'] ??= bin2hex(random_bytes(24));

    ob_start();
    ?>
    <div class="meal-history-tabs mb-3"><a href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','class'=>$className,'meal'=>'sang'])) ?>"><i class="bi bi-clipboard-check"></i> Nhập báo ăn</a><a class="active" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','meal_view'=>'history','class'=>$className,'meal'=>'all'])) ?>"><i class="bi bi-clock-history"></i> Lịch sử báo ăn</a></div>
    <div class="card card-soft" id="mealEditHistory">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div><strong><i class="bi bi-clock-history me-1"></i>Lịch sử báo ăn</strong><div class="small text-muted"><?= $mode==='recent'?'Đang hiển thị 5 ngày gần nhất. ':'' ?>GVCN chỉ xem các lớp được giao.</div></div>
        <span class="badge bg-secondary"><?= count($rows) ?> lượt báo</span>
      </div>
      <form method="get" class="card-body border-bottom d-flex align-items-end gap-2 flex-wrap">
        <input type="hidden" name="tab" value="meals"><input type="hidden" name="meal_view" value="history"><input type="hidden" name="class" value="<?= e($className) ?>">
        <div><label class="form-label small mb-1">Lọc theo ngày</label><input class="form-control form-control-sm" type="date" name="history_date" value="<?= e($historyDate) ?>"></div>
        <div><label class="form-label small mb-1">Bữa ăn</label><select class="form-select form-select-sm" name="meal"><option value="all" <?= $meal==='all'?'selected':'' ?>>Cả 3 bữa</option><?php foreach ($mealLabels as $mealKey=>$mealLabel): ?><option value="<?= e($mealKey) ?>" <?= $meal===$mealKey?'selected':'' ?>><?= e($mealLabel) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Lọc</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','meal_view'=>'history','history_all'=>1,'class'=>$className,'meal'=>$meal])) ?>"><i class="bi bi-list-ul"></i> Xem tất cả</a>
      </form>
      <?php if ($isAdmin && $rows): ?><form method="post" id="mealHistoryBulkForm" class="card-body border-bottom d-flex align-items-center justify-content-between gap-2" onsubmit="return confirmMealHistoryBulkDelete(this);"><div class="form-check"><input class="form-check-input" type="checkbox" id="mealHistorySelectAll"><label class="form-check-label fw-semibold" for="mealHistorySelectAll">Chọn tất cả đang hiển thị</label></div><input type="hidden" name="action" value="meal_history_bulk_delete"><input type="hidden" name="tab" value="meals"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="class" value="<?= e($className) ?>"><input type="hidden" name="meal" value="<?= e($meal) ?>"><button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash"></i> Xóa mục đã chọn</button></form><?php endif; ?>
      <div class="card-body p-0">
        <?php if (!$rows): ?>
          <div class="text-muted text-center py-4">Chưa có lịch sử trong phạm vi tra cứu.</div>
        <?php else: ?>
          <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><?php if($isAdmin):?><th class="text-center" style="width:42px">Chọn</th><?php endif;?><th>Ngày ăn / thời gian báo</th><th>Lớp / bữa</th><th>Người báo</th><th>Nội dung</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <?php if($isAdmin):?><td class="text-center"><input class="form-check-input meal-history-check" type="checkbox" name="ids[]" value="<?= e($row['id']??'') ?>" form="mealHistoryBulkForm" aria-label="Chọn lượt báo ăn"></td><?php endif;?>
                <td class="small text-nowrap"><strong><?= e(date('d/m/Y', strtotime($row['date'] ?? 'now'))) ?></strong><br><?= e(date('H:i d/m/Y', strtotime($row['changed_at'] ?? 'now'))) ?><?= !empty($row['after_lock']) ? '<br><span class="badge bg-warning text-dark">Sau khóa</span>' : '' ?></td>
                <td><strong><?= e($row['class_name'] ?? '') ?></strong><br><span class="small text-muted"><?= e($mealLabels[$row['meal'] ?? ''] ?? '') ?></span></td>
                <td><?= e($row['changed_by'] ?? '') ?></td>
                <td class="small">
                  <?php if (empty($row['changes'])): ?><span class="text-muted">Đã báo đủ <?= (int)($row['student_count'] ?? 0) ?> học sinh, không có thay đổi trạng thái.</span><?php endif; ?>
                  <?php foreach (($row['changes'] ?? []) as $change): ?>
                    <div><strong><?= e($change['student_name'] ?? '') ?></strong>: <?= e(nt_meal_history_label($change['from'] ?? 'yes')) ?> → <?= e(nt_meal_history_label($change['to'] ?? 'yes')) ?></div>
                  <?php endforeach; ?>
                </td>
                <td class="text-end text-nowrap">
                  <?php $state=noitru_meal_state((string)($row['date']??''),(string)($row['meal']??''));$isOpen=($state['status']??'open')==='open'; ?>
                  <?php if ($isOpen || nt_meal_history_can_edit_locked()): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','date'=>$row['date']??'','class'=>$row['class_name']??'','meal'=>$row['meal']??''])) ?>" title="Mở lại phiếu"><i class="bi bi-pencil-square"></i></a><?php endif; ?>
                  <?php if ($canDelete): ?><form method="post" class="d-inline" onsubmit="return confirm('CẢNH BÁO: Xóa vĩnh viễn lượt báo ăn này và toàn bộ số liệu liên quan trong các bảng thống kê? Dữ liệu đã xóa không thể khôi phục.');"><input type="hidden" name="action" value="meal_history_delete"><input type="hidden" name="tab" value="meals"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($row['id']??'') ?>"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="class" value="<?= e($className) ?>"><input type="hidden" name="meal" value="<?= e($meal) ?>"><button class="btn btn-sm btn-outline-danger" title="Xóa lịch sử và số liệu liên quan"><i class="bi bi-trash"></i></button></form><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>
    </div>
    <style>.meal-history-tabs{display:grid;grid-template-columns:1fr 1fr;gap:.55rem}.meal-history-tabs a{display:flex;align-items:center;justify-content:center;gap:.45rem;min-height:44px;border:1px solid #dce5ec;border-radius:13px;background:#fff;color:#334155;text-decoration:none;font-weight:700}.meal-history-tabs a.active{border-color:#0ea5e9;background:#0ea5e9;color:#fff}@media(max-width:600px){.meal-history-tabs a{font-size:.82rem}}</style>
    <script>(function(){var all=document.getElementById('mealHistorySelectAll');if(all)all.addEventListener('change',function(){document.querySelectorAll('.meal-history-check').forEach(function(box){box.checked=all.checked})});window.confirmMealHistoryBulkDelete=function(){var count=document.querySelectorAll('.meal-history-check:checked').length;if(!count){alert('Hãy tích chọn ít nhất một lượt lịch sử.');return false}return confirm('CẢNH BÁO: Xóa vĩnh viễn '+count+' lượt báo ăn và toàn bộ số liệu liên quan trong các bảng thống kê? Dữ liệu đã xóa không thể khôi phục.')};})();</script>
    <?php
    return ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'meal_history_delete') {
    if (!nt_meal_history_can_delete()) { http_response_code(403); exit('Bạn không có quyền xóa lịch sử báo ăn.'); }
    if (empty($_POST['csrf']) || !hash_equals((string)($_SESSION['nt_meal_history_csrf'] ?? ''), (string)$_POST['csrf'])) { http_response_code(403); exit('Phiên làm việc không hợp lệ.'); }
    $id = trim((string)($_POST['id'] ?? '')); $rows = nt_meal_history_all(); $before = count($rows);
    $selectedRows = array_values(array_filter($rows, fn($row)=>(string)($row['id']??'') === $id));
    $targetKeys = nt_meal_history_target_keys($selectedRows);
    $deletedRows = array_values(array_filter($rows, fn($row)=>isset($targetKeys[(string)($row['date']??'').'|'.(string)($row['class_name']??'').'|'.(string)($row['meal']??'')])));
    nt_meal_history_remember_deleted_sources($deletedRows);
    nt_meal_history_delete_related_data($deletedRows);
    $rows = array_values(array_filter($rows, fn($row)=>!isset($targetKeys[(string)($row['date']??'').'|'.(string)($row['class_name']??'').'|'.(string)($row['meal']??'')])));
    if (count($rows) < $before) { nt_meal_history_save($rows); flash('Đã xóa lượt báo ăn và cập nhật toàn bộ bảng tổng hợp, báo cáo, thống kê liên quan.', 'warning'); }
    else flash('Không tìm thấy lịch sử cần xóa.', 'warning');
    header('Location: ' . BASE_URL . 'noitru.php?' . http_build_query(['tab'=>'meals','meal_view'=>'history','class'=>$_POST['class']??'','meal'=>$_POST['meal']??'sang'])); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'meal_history_bulk_delete') {
    if (!nt_meal_history_is_admin()) { http_response_code(403); exit('Chỉ quản trị được xóa nhiều lượt lịch sử báo ăn.'); }
    if (empty($_POST['csrf']) || !hash_equals((string)($_SESSION['nt_meal_history_csrf'] ?? ''), (string)$_POST['csrf'])) { http_response_code(403); exit('Phiên làm việc không hợp lệ.'); }
    $ids = array_fill_keys(array_values(array_filter(array_map('strval', (array)($_POST['ids'] ?? [])))), true);
    if (!$ids) { flash('Chưa chọn lịch sử cần xóa.', 'warning'); }
    else { $rows=nt_meal_history_all();$before=count($rows);$selectedRows=array_values(array_filter($rows,fn($row)=>isset($ids[(string)($row['id']??'')])));$targetKeys=nt_meal_history_target_keys($selectedRows);$deletedRows=array_values(array_filter($rows,fn($row)=>isset($targetKeys[(string)($row['date']??'').'|'.(string)($row['class_name']??'').'|'.(string)($row['meal']??'')])));nt_meal_history_remember_deleted_sources($deletedRows);nt_meal_history_delete_related_data($deletedRows);$rows=array_values(array_filter($rows,fn($row)=>!isset($targetKeys[(string)($row['date']??'').'|'.(string)($row['class_name']??'').'|'.(string)($row['meal']??'')])));nt_meal_history_save($rows);flash('Đã xóa '.($before-count($rows)).' lượt báo ăn và cập nhật toàn bộ bảng tổng hợp, báo cáo, thống kê liên quan.','warning'); }
    header('Location: '.BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','meal_view'=>'history','class'=>$_POST['class']??'','meal'=>$_POST['meal']??'sang']));exit;
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
    $historyView = ($_GET['meal_view'] ?? '') === 'history';
    ob_start(function ($html) use ($canLocked, $historyView) {
        $script = '';
        if ($canLocked) {
            $script = '<script>(function(){var f=document.getElementById("mealReportForm");if(!f)return;var locked=f.querySelectorAll("input.meal-absent:disabled");if(!locked.length)return;locked.forEach(function(x){x.disabled=false;});var body=f.querySelector(".card-body");if(body){var a=document.createElement("div");a.className="alert alert-warning py-2";a.innerHTML="<i class=\"bi bi-unlock\"></i> Bạn có quyền sửa báo ăn sau khóa. Thao tác sẽ được ghi vào lịch sử.";body.insertBefore(a,body.firstChild);}if(!f.querySelector(".meal-save-bar")){var bar=document.createElement("div");bar.className="card-body border-top meal-save-bar";bar.innerHTML="<button class=\"btn btn-warning w-100\" type=\"submit\"><i class=\"bi bi-pencil-square\"></i> Kiểm tra và lưu thay đổi sau khóa</button>";f.appendChild(bar);}})();</script>';
        }
        if (!$historyView) {
            $className=trim((string)($_GET['class']??''));$meal=(string)($_GET['meal']??'sang');
            $tabs='<div class="meal-history-tabs mb-3"><a class="active" href="'.e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','class'=>$className,'meal'=>$meal])).'"><i class="bi bi-clipboard-check"></i> Nhập báo ăn</a><a href="'.e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'meals','meal_view'=>'history','class'=>$className,'meal'=>'all'])).'"><i class="bi bi-clock-history"></i> Lịch sử báo ăn</a></div><style>.meal-history-tabs{display:grid;grid-template-columns:1fr 1fr;gap:.55rem}.meal-history-tabs a{display:flex;align-items:center;justify-content:center;gap:.45rem;min-height:44px;border:1px solid #dce5ec;border-radius:13px;background:#fff;color:#334155;text-decoration:none;font-weight:700}.meal-history-tabs a.active{border-color:#0ea5e9;background:#0ea5e9;color:#fff}</style>';
            $html=preg_replace('/(<div class="nt-page-head">)/',$tabs.'$1',$html,1);
        }
        $insert = $script;
        if (stripos($html, '</main>') !== false) return preg_replace('/<\/main>/i', $insert . '</main>', $html, 1);
        if (stripos($html, '</body>') !== false) return preg_replace('/<\/body>/i', $insert . '</body>', $html, 1);
        return $html . $insert;
    });
}
