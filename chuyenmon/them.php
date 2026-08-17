<?php
$page_title = 'Phân công chuyên môn';
require_once 'includes/functions.php';
require_login();

function go_board($extra = '') {
    // Không ép #board — JS sẽ khôi phục vị trí cuộn + bộ lọc
    header('Location: ' . BASE_URL . 'them.php?ok=1' . $extra);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_day') {
        $id = $_POST['id'] ?? '';
        save_assignments(array_values(array_filter(get_assignments(), fn($a) => $a['id'] !== $id)));
        flash('Đã xóa phân công dạy.', 'success');
        go_board();
    }
    if ($action === 'delete_role') {
        $id = $_POST['id'] ?? '';
        save_role_assignments(array_values(array_filter(get_role_assignments(), fn($a) => $a['id'] !== $id)));
        flash('Đã xóa kiêm nhiệm.', 'success');
        go_board();
    }

    if ($action === 'add_one') {
        $assignments = get_assignments();
        $teacher = trim($_POST['teacher'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $class_name = trim($_POST['class_name'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $periods_manual = trim($_POST['periods_manual'] ?? '');
        $replace = !empty($_POST['replace']);

        if (!$teacher || !$subject || !$class_name) {
            flash('Vui lòng chọn đầy đủ Giáo viên, Môn học và Lớp.', 'danger');
            header('Location: ' . BASE_URL . 'them.php'); exit;
        }

        $same_self = false;
        $conflict_teacher = null;
        foreach ($assignments as $a) {
            if ($a['subject'] === $subject && $a['class'] === $class_name) {
                if ($a['teacher'] === $teacher) { $same_self = true; break; }
                $conflict_teacher = $a['teacher'];
            }
        }

        if ($same_self) {
            flash("Đã có: $teacher – $subject – $class_name", 'warning');
            header('Location: ' . BASE_URL . 'them.php'); exit;
        }

        if ($conflict_teacher && !$replace) {
            $q = http_build_query([
                'ask_replace' => 1, 'teacher' => $teacher, 'subject' => $subject,
                'class' => $class_name, 'note' => $note, 'periods_manual' => $periods_manual,
                'conflict' => $conflict_teacher,
            ]);
            header('Location: ' . BASE_URL . 'them.php?' . $q); exit;
        }

        if ($conflict_teacher && $replace) {
            $assignments = array_values(array_filter($assignments, function($a) use ($subject, $class_name) {
                return !($a['subject'] === $subject && $a['class'] === $class_name);
            }));
        }

        $periods = get_periods($subject, $class_name);
        if ($periods === null) $periods = is_numeric($periods_manual) ? round(floatval($periods_manual), 2) : 0;

        $assignments[] = [
            'id' => date('YmdHis') . substr(microtime(), 2, 6),
            'teacher' => $teacher, 'subject' => $subject, 'class' => $class_name,
            'periods' => $periods, 'note' => $note, 'created_at' => date('c'),
        ];
        save_assignments($assignments);
        $msg = "Đã giao: $teacher dạy $subject lớp $class_name ($periods tiết)";
        if ($conflict_teacher) $msg .= " — đã thay thế $conflict_teacher";
        flash($msg, $conflict_teacher ? 'warning' : 'success');
        go_board();
    }

    if ($action === 'add_multi') {
        $assignments = get_assignments();
        $teacher = trim($_POST['teacher'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $class_list = $_POST['classes'] ?? [];
        $replace = !empty($_POST['replace']);
        if (!$teacher || !$subject || empty($class_list)) {
            flash('Vui lòng chọn đầy đủ.', 'danger');
            header('Location: ' . BASE_URL . 'them.php'); exit;
        }
        $added = 0; $replaced = [];
        foreach ($class_list as $cls) {
            $conflict = null;
            foreach ($assignments as $a) {
                if ($a['subject'] === $subject && $a['class'] === $cls) {
                    if ($a['teacher'] === $teacher) { $conflict = 'self'; break; }
                    $conflict = $a['teacher'];
                }
            }
            if ($conflict === 'self') continue;
            if ($conflict && !$replace) continue;
            if ($conflict && $replace) {
                $assignments = array_values(array_filter($assignments, fn($a) => !($a['subject']===$subject && $a['class']===$cls)));
                $replaced[] = "$cls←$conflict";
            }
            $assignments[] = [
                'id' => date('YmdHis') . $cls . substr(microtime(), 2, 4),
                'teacher' => $teacher, 'subject' => $subject, 'class' => $cls,
                'periods' => get_periods($subject, $cls) ?? 0, 'note' => '', 'created_at' => date('c'),
            ];
            $added++;
        }
        save_assignments($assignments);
        $msg = "Đã thêm $added phân công.";
        if ($replaced) $msg .= ' Thay thế: ' . implode(', ', $replaced);
        flash($msg, $replaced ? 'warning' : 'success');
        go_board();
    }

    if ($action === 'add_role') {
        $items = get_role_assignments();
        $roles = get_roles();
        $teacher = trim($_POST['teacher'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $class_name = trim($_POST['class_name'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $periods_manual = trim($_POST['periods_manual'] ?? '');
        $replace = !empty($_POST['replace']);
        $roleInfo = null;
        foreach ($roles as $r) if ($r['name'] === $role) { $roleInfo = $r; break; }
        $need_class = $roleInfo && !empty($roleInfo['need_class']);
        $periods = isset($roleInfo['periods']) ? floatval($roleInfo['periods']) : 0;
        if ($periods_manual !== '' && is_numeric($periods_manual)) $periods = round(floatval($periods_manual), 2);
        else $periods = round($periods, 2);

        if (!$teacher || !$role) {
            flash('Chọn Giáo viên và Chức vụ.', 'danger');
            header('Location: ' . BASE_URL . 'them.php'); exit;
        }
        if ($need_class && !$class_name) {
            flash('Chức vụ này cần chọn Lớp.', 'danger');
            header('Location: ' . BASE_URL . 'them.php'); exit;
        }

        $conflict_teacher = null;
        foreach ($items as $a) {
            if ($a['teacher'] === $teacher && $a['role'] === $role && ($a['class'] ?? '') === $class_name) {
                flash('Kiêm nhiệm này đã tồn tại cho GV này.', 'warning');
                header('Location: ' . BASE_URL . 'them.php'); exit;
            }
            if ($need_class && ($a['role'] === $role) && ($a['class'] ?? '') === $class_name && $a['teacher'] !== $teacher) {
                $conflict_teacher = $a['teacher'];
            }
        }

        if ($conflict_teacher && !$replace) {
            $q = http_build_query([
                'ask_replace_role' => 1, 'teacher' => $teacher, 'role' => $role,
                'class' => $class_name, 'note' => $note, 'periods_manual' => $periods_manual,
                'conflict' => $conflict_teacher,
            ]);
            header('Location: ' . BASE_URL . 'them.php?' . $q); exit;
        }

        if ($conflict_teacher && $replace && $need_class) {
            $items = array_values(array_filter($items, fn($a) => !(($a['role']===$role) && (($a['class']??'')===$class_name))));
        }

        $items[] = [
            'id' => date('YmdHis') . substr(microtime(), 2, 4),
            'teacher' => $teacher, 'role' => $role, 'class' => $class_name,
            'periods' => $periods, 'note' => $note, 'created_at' => date('c'),
        ];
        save_role_assignments($items);
        flash("Đã thêm KN: $teacher – $role" . ($class_name ? " ($class_name)" : '') . ($conflict_teacher ? " — thay $conflict_teacher" : ''), $conflict_teacher ? 'warning' : 'success');
        go_board();
    }

    if ($action === 'update_day') {
        $id = $_POST['id'] ?? '';
        $list = get_assignments();
        $found = false;
        foreach ($list as &$a) {
            if ($a['id'] !== $id) continue;
            $a['teacher'] = trim($_POST['teacher'] ?? $a['teacher']);
            $a['subject'] = trim($_POST['subject'] ?? $a['subject']);
            $a['class'] = trim($_POST['class_name'] ?? $a['class']);
            $pm = trim($_POST['periods'] ?? '');
            if ($pm !== '' && is_numeric($pm)) $a['periods'] = round(floatval($pm), 2);
            else {
                $p = get_periods($a['subject'], $a['class']);
                if ($p !== null) $a['periods'] = $p;
            }
            $a['note'] = trim($_POST['note'] ?? '');
            $found = true; break;
        }
        unset($a);
        if ($found) { save_assignments($list); flash('Đã cập nhật phân công.', 'success'); }
        else flash('Không tìm thấy.', 'danger');
        go_board();
    }

    if ($action === 'move_day') {
        $id = $_POST['id'] ?? '';
        $to = trim($_POST['to_teacher'] ?? '');
        if ($id && $to) {
            $list = get_assignments();
            $moved = null;
            foreach ($list as &$a) {
                if ($a['id'] === $id) {
                    foreach ($list as $b) {
                        if ($b['id'] !== $id && $b['teacher'] === $to && $b['subject'] === $a['subject'] && $b['class'] === $a['class']) {
                            flash("Không chuyển: $to đã có {$a['subject']} {$a['class']}.", 'warning');
                            go_board();
                        }
                    }
                    $from = $a['teacher'];
                    $a['teacher'] = $to;
                    $moved = "$from → $to: {$a['subject']} {$a['class']}";
                    break;
                }
            }
            unset($a);
            if ($moved) {
                $keep = []; $seen = [];
                foreach ($list as $a) {
                    if ($a['id'] === $id) { $keep[] = $a; $seen[$a['subject'].'|'.$a['class']] = true; }
                }
                foreach ($list as $a) {
                    if ($a['id'] === $id) continue;
                    $k = $a['subject'].'|'.$a['class'];
                    if (isset($seen[$k])) continue;
                    $seen[$k] = true;
                    $keep[] = $a;
                }
                save_assignments(array_values($keep));
                flash("Đã chuyển: $moved", 'success');
            }
        }
        go_board();
    }

    if ($action === 'swap_all') {
        $t1 = trim($_POST['teacher1'] ?? ''); $t2 = trim($_POST['teacher2'] ?? '');
        if (!$t1 || !$t2 || $t1 === $t2) flash('Chọn 2 GV khác nhau.', 'danger');
        else {
            $assignments = get_assignments(); $roles = get_role_assignments(); $nA=0;$nR=0;
            foreach ($assignments as &$a) {
                if ($a['teacher']===$t1){$a['teacher']=$t2;$nA++;} elseif($a['teacher']===$t2){$a['teacher']=$t1;$nA++;}
            } unset($a);
            foreach ($roles as &$a) {
                if ($a['teacher']===$t1){$a['teacher']=$t2;$nR++;} elseif($a['teacher']===$t2){$a['teacher']=$t1;$nR++;}
            } unset($a);
            save_assignments($assignments); save_role_assignments($roles);
            flash("Đổi chéo $nA dạy + $nR KN: $t1 ↔ $t2", 'success');
        }
        go_board();
    }
}

require_once 'includes/header.php';

$teachers = get_teachers_sorted();
$subjects = array_keys(get_subjects()); sort($subjects);
$all_subjects = get_subjects();
$classes = get_classes();
$roles = get_roles();
$assignments = get_assignments();
$role_items = get_role_assignments();
$loads = get_teacher_loads();

$day_by_t = [];
foreach ($assignments as $a) $day_by_t[$a['teacher']][] = $a;
$role_by_t = [];
foreach ($role_items as $a) $role_by_t[$a['teacher']][] = $a;
$board_names = sort_teachers_by_ten(array_unique(array_merge($teachers, array_keys($day_by_t), array_keys($role_by_t))));

$slot = [];
foreach ($assignments as $a) $slot[$a['subject'].'|'.$a['class']][] = $a['teacher'];
$conflicts = [];
foreach ($slot as $k => $list) {
    $u = array_values(array_unique($list));
    if (count($u) > 1) { [$s,$c] = explode('|',$k,2); $conflicts[] = ['subject'=>$s,'class'=>$c,'teachers'=>$u]; }
}
$missing = [];
foreach ($classes as $cls) {
    $grade = get_grade($cls);
    foreach ($all_subjects as $sub => $grades) {
        if (!isset($grades[$grade]) || floatval($grades[$grade]) <= 0) continue;
        $found = false;
        foreach ($assignments as $a) if ($a['subject']===$sub && $a['class']===$cls) { $found=true; break; }
        if (!$found) $missing[] = ['subject'=>$sub,'class'=>$cls,'periods'=>$grades[$grade]];
    }
}
$under=[]; $over=[];
foreach ($teachers as $t) {
    $total = $loads[$t]['total'] ?? 0; $quota = get_quota($t); $diff = $total - $quota;
    $item = ['teacher'=>$t,'level'=>get_teacher_level($t),'total'=>$total,'quota'=>$quota,'diff'=>$diff];
    if ($diff < -0.05) $under[] = $item; elseif ($diff > 0.05) $over[] = $item;
}
$n_conflict = count($conflicts);
$ask_replace = !empty($_GET['ask_replace']);
$ask_replace_role = !empty($_GET['ask_replace_role']);
?>

<style>
.board-drop{min-height:36px;border-radius:8px;transition:background .15s}
.board-drop.drag-over{background:#d4edda;outline:2px dashed #198754}
.chip[draggable=true]{cursor:grab}
.chip[draggable=true]:active{cursor:grabbing;opacity:.75}
.chip-clickable{cursor:pointer}
.chip-clickable:hover{box-shadow:0 0 0 2px var(--primary)}
.board-scroll{max-height:calc(100vh - 420px);min-height:280px;overflow:auto}
.filter-bar{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.filter-bar .form-select,.filter-bar .form-control{width:auto;min-width:110px}
@media (max-width:768px){.board-scroll{max-height:none}}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
<h3 class="mb-0"><i class="bi bi-clipboard-check"></i> Phân công</h3>
<div class="d-flex flex-wrap gap-1">
<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAudit">
<i class="bi bi-search"></i> Rà soát
<?php if ($n_conflict || $missing || $under || $over): ?>
<span class="badge bg-danger"><?= $n_conflict + count($missing) ?></span>
<?php endif; ?>
</button>
<button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalSwap">
<i class="bi bi-arrow-left-right"></i> Đổi chéo GV
</button>
</div>
</div>

<?php if ($n_conflict): ?>
<div class="danger-box py-2"><i class="bi bi-exclamation-octagon-fill"></i> Có <strong><?= $n_conflict ?></strong> trùng môn+lớp. <a href="#" data-bs-toggle="modal" data-bs-target="#modalAudit">Xem rà soát</a></div>
<?php endif; ?>

<div class="card mb-3">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="bi bi-plus-circle"></i> Thêm phân công</span>
<ul class="nav nav-pills card-header-pills gap-1 mb-0">
<li class="nav-item"><button type="button" class="nav-link active py-1 px-2" data-bs-toggle="tab" data-bs-target="#tabDay" style="color:inherit">Dạy môn</button></li>
<li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#tabRole" style="color:inherit">Kiêm nhiệm</button></li>
<li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#tabMulti" style="color:inherit">Nhiều lớp</button></li>
</ul>
</div>
<div class="card-body py-3">
<div class="tab-content">

<div class="tab-pane fade show active" id="tabDay">
<form method="post" id="formAddOne" class="row g-2 align-items-end">
<input type="hidden" name="action" value="add_one">
<input type="hidden" name="replace" id="replaceOne" value="">
<div class="col-md-3"><label class="form-label small mb-0 fw-semibold">Giáo viên *</label>
<select name="teacher" id="addTeacher" class="form-select form-select-sm" required>
<option value="">-- Chọn --</option>
<?php foreach ($teachers as $t): ?><option value="<?= e($t) ?>" <?= ($_GET['teacher']??'')===$t?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2"><label class="form-label small mb-0 fw-semibold">Môn *</label>
<select name="subject" id="subject" class="form-select form-select-sm" required>
<option value="">--</option>
<?php foreach ($subjects as $s): ?><option value="<?= e($s) ?>" <?= ($_GET['subject']??'')===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2"><label class="form-label small mb-0 fw-semibold">Lớp *</label>
<select name="class_name" id="class_name" class="form-select form-select-sm" required>
<option value="">--</option>
<?php foreach ($classes as $c): ?><option value="<?= e($c) ?>" <?= ($_GET['class']??'')===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2"><label class="form-label small mb-0 fw-semibold">Số tiết</label>
<div id="periods-display" class="small text-success d-none">Tự động: <b id="periods-value">0</b></div>
<input type="number" name="periods_manual" id="periods_manual" class="form-control form-control-sm d-none" step="0.01" min="0" value="<?= e($_GET['periods_manual']??'') ?>" placeholder="Thủ công">
</div>
<div class="col-md-2"><label class="form-label small mb-0">Ghi chú</label>
<input type="text" name="note" class="form-control form-control-sm" value="<?= e($_GET['note']??'') ?>"></div>
<div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Lưu</button></div>
</form>
</div>

<div class="tab-pane fade" id="tabMulti">
<form method="post" class="row g-2">
<input type="hidden" name="action" value="add_multi">
<input type="hidden" name="replace" value="1">
<div class="col-md-3"><select name="teacher" class="form-select form-select-sm" required><option value="">-- GV --</option>
<?php foreach ($teachers as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><select name="subject" class="form-select form-select-sm" required><option value="">-- Môn --</option>
<?php foreach ($subjects as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-md-5"><div class="d-flex flex-wrap gap-2">
<?php foreach ($classes as $c): ?>
<label class="form-check-label small border rounded px-2 py-1 bg-white"><input type="checkbox" name="classes[]" value="<?= e($c) ?>" class="form-check-input me-1"> <?= e($c) ?></label>
<?php endforeach; ?>
</div><div class="form-text">Trùng môn+lớp sẽ <strong>tự thay thế</strong> GV cũ.</div></div>
<div class="col-md-2"><button class="btn btn-success btn-sm w-100">Thêm</button></div>
</form>
</div>

<div class="tab-pane fade" id="tabRole">
<form method="post" id="formAddRole" class="row g-2 align-items-end">
<input type="hidden" name="action" value="add_role">
<input type="hidden" name="replace" id="replaceRole" value="">
<div class="col-md-3"><select name="teacher" class="form-select form-select-sm" required><option value="">-- GV --</option>
<?php foreach ($teachers as $t): ?><option value="<?= e($t) ?>" <?= ($_GET['teacher']??'')===$t?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><select name="role" id="roleSelect" class="form-select form-select-sm" required><option value="">-- Chức vụ --</option>
<?php foreach ($roles as $r): ?>
<option value="<?= e($r['name']) ?>" data-need-class="<?= !empty($r['need_class'])?'1':'0' ?>" data-periods="<?= e($r['periods']??0) ?>" <?= ($_GET['role']??'')===$r['name']?'selected':'' ?>><?= e($r['name']) ?> (<?= e($r['periods']??0) ?>t)</option>
<?php endforeach; ?></select></div>
<div class="col-md-2" id="roleClassWrap"><select name="class_name" class="form-select form-select-sm"><option value="">-- Lớp --</option>
<?php foreach ($classes as $c): ?><option value="<?= e($c) ?>" <?= ($_GET['class']??'')===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input type="number" name="periods_manual" class="form-control form-control-sm" step="0.01" min="0" placeholder="Tiết (tuỳ chọn)" value="<?= e($_GET['periods_manual']??'') ?>"></div>
<div class="col-md-2"><button class="btn btn-info btn-sm w-100">Lưu KN</button></div>
</form>
</div>

</div></div></div>

<div class="card" id="board">
<div class="card-header">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
<span><i class="bi bi-table"></i> Bảng phân công — kéo thả · bấm chip để sửa</span>
<button type="button" class="btn btn-sm btn-outline-light" id="fClear">Xóa lọc</button>
</div>
<div class="filter-bar">
<input type="search" id="boardFilter" class="form-control form-control-sm" placeholder="Tên / môn / lớp..." style="min-width:160px">
<select id="fLevel" class="form-select form-select-sm" title="Cấp">
<option value="">Mọi cấp</option>
<option value="THCS">THCS</option>
<option value="THPT">THPT</option>
</select>
<select id="fGroup" class="form-select form-select-sm" title="Tổ">
<option value="">Mọi tổ</option>
<option value="khxh">Tổ KHXH</option>
<option value="khtn">Tổ KHTN</option>
</select>
<select id="fLoad" class="form-select form-select-sm" title="Tải tiết">
<option value="">Mọi tải</option>
<option value="under">Thiếu tiết</option>
<option value="ok">Đủ định mức</option>
<option value="over">Thừa tiết</option>
</select>
<span class="small text-white-50" id="fCount"></span>
</div>
</div>
<div class="card-body board-scroll" id="boardBody">
<?php foreach ($board_names as $t):
    $load = $loads[$t] ?? ['day'=>0,'role'=>0,'total'=>0,'quota'=>get_quota($t),'diff'=>-get_quota($t),'level'=>get_teacher_level($t)];
    $flags = get_teacher_flags($t);
    $cm_list = get_teacher_chuyen_mon($t);
    $diffClass = abs($load['diff']) < 0.05 ? 'diff-ok' : ($load['diff'] > 0 ? 'diff-over' : 'diff-under');
    $loadStatus = abs($load['diff']) < 0.05 ? 'ok' : ($load['diff'] > 0 ? 'over' : 'under');
    $search = mb_strtolower($t . ' ' . implode(' ', $cm_list), 'UTF-8');
    foreach ($day_by_t[$t] ?? [] as $a) $search .= ' ' . mb_strtolower(($a['subject']??'').' '.($a['class']??''), 'UTF-8');
    foreach ($role_by_t[$t] ?? [] as $a) $search .= ' ' . mb_strtolower(($a['role']??'').' '.($a['class']??''), 'UTF-8');
    $level = $load['level'] ?? get_teacher_level($t);
?>
<div class="board-row"
  data-search="<?= e($search) ?>"
  data-teacher="<?= e($t) ?>"
  data-level="<?= e($level) ?>"
  data-khxh="<?= !empty($flags['khxh'])?'1':'0' ?>"
  data-khtn="<?= !empty($flags['khtn'])?'1':'0' ?>"
  data-load="<?= e($loadStatus) ?>"
  data-cm="<?= e(mb_strtolower(implode(' ', $cm_list), 'UTF-8')) ?>">
<div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
<strong class="teacher-name"><?= e($t) ?></strong>
<span class="small">
<span class="badge bg-secondary"><?= e($level) ?></span>
<?php if (!empty($flags['khxh'])): ?><span class="badge bg-info text-dark">KHXH</span><?php endif; ?>
<?php if (!empty($flags['khtn'])): ?><span class="badge bg-success">KHTN</span><?php endif; ?>
Dạy <b><?= number_format($load['day'],2) ?></b> · KN <b><?= number_format($load['role'],2) ?></b> ·
Tổng <b><?= number_format($load['total'],2) ?></b>/<?= number_format($load['quota'],0) ?>
<span class="<?= $diffClass ?>"><?= $load['diff']>0?'+':'' ?><?= number_format($load['diff'],2) ?></span>
</span>
</div>
<div class="board-drop mb-1" data-teacher="<?= e($t) ?>" ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="onDropChip(event, this)">
<span class="text-muted small me-1">Dạy:</span>
<?php if (!empty($day_by_t[$t])): foreach ($day_by_t[$t] as $a): ?>
<span class="chip chip-clickable" draggable="true"
  data-id="<?= e($a['id']) ?>" data-type="day"
  data-teacher="<?= e($a['teacher']) ?>" data-subject="<?= e($a['subject']) ?>"
  data-class="<?= e($a['class']) ?>" data-periods="<?= e($a['periods']) ?>" data-note="<?= e($a['note']??'') ?>"
  ondragstart="onDragChip(event)" onclick="openChipModal(this)">
<?= e($a['subject']) ?> <?= e($a['class']) ?> (<?= e($a['periods']) ?>t)
</span>
<?php endforeach; else: ?><span class="text-muted small drop-hint">— kéo chip vào đây</span><?php endif; ?>
</div>
<div>
<span class="text-muted small me-1">KN:</span>
<?php if (!empty($role_by_t[$t])): foreach ($role_by_t[$t] as $a): ?>
<span class="chip chip-role">
<?= e($a['role']) ?><?= !empty($a['class'])?' '.e($a['class']):'' ?> (<?= e($a['periods']??0) ?>t)
<form method="post" class="d-inline pccm-keep" onsubmit="return confirm('Xóa kiêm nhiệm?')">
<input type="hidden" name="action" value="delete_role"><input type="hidden" name="id" value="<?= e($a['id']) ?>">
<button type="submit" class="chip-x" title="Xóa" onclick="event.stopPropagation()">×</button></form>
</span>
<?php endforeach; else: ?><span class="text-muted small">—</span><?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<div class="card-footer small text-muted">Bộ lọc được giữ đến khi bấm «Xóa lọc» · Vị trí cuộn được giữ sau thêm/xóa</div>
</div>

<form method="post" id="formMove" class="d-none">
<input type="hidden" name="action" value="move_day">
<input type="hidden" name="id" id="moveId">
<input type="hidden" name="to_teacher" id="moveTo">
</form>

<div class="modal fade" id="modalChip" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Sửa phân công</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post" class="pccm-keep">
<input type="hidden" name="action" value="update_day">
<input type="hidden" name="id" id="chipId">
<div class="modal-body">
<div class="mb-2"><label class="form-label small fw-semibold">Giáo viên</label>
<select name="teacher" id="chipTeacher" class="form-select form-select-sm">
<?php foreach ($teachers as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?>
</select></div>
<div class="mb-2"><label class="form-label small fw-semibold">Môn</label>
<select name="subject" id="chipSubject" class="form-select form-select-sm">
<?php foreach ($subjects as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
</select></div>
<div class="mb-2"><label class="form-label small fw-semibold">Lớp</label>
<select name="class_name" id="chipClass" class="form-select form-select-sm">
<?php foreach ($classes as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
</select></div>
<div class="mb-2"><label class="form-label small fw-semibold">Số tiết</label>
<input type="number" name="periods" id="chipPeriods" class="form-control form-control-sm" step="0.01" min="0"></div>
<div class="mb-2"><label class="form-label small">Ghi chú</label>
<input type="text" name="note" id="chipNote" class="form-control form-control-sm"></div>
</div>
<div class="modal-footer justify-content-between">
<button type="button" class="btn btn-outline-danger btn-sm" id="btnChipDelete">Xóa</button>
<div>
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
<button type="submit" class="btn btn-primary btn-sm">Lưu thay đổi</button>
</div></div>
</form>
<form method="post" id="formChipDelete" class="d-none pccm-keep">
<input type="hidden" name="action" value="delete_day">
<input type="hidden" name="id" id="chipDeleteId">
</form>
</div></div></div>

<div class="modal fade" id="modalReplace" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header bg-warning"><h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Trùng phân công</h5></div>
<div class="modal-body">
<p>Môn <strong id="rpSubject"></strong> lớp <strong id="rpClass"></strong> đã giao cho <strong id="rpConflict" class="text-danger"></strong>.</p>
<p class="mb-0">Bạn có muốn <strong>thay thế</strong>? (Phân công của người kia sẽ bị gỡ, giao cho <strong id="rpNew"></strong>.)</p>
</div>
<div class="modal-footer">
<a href="<?= BASE_URL ?>them.php" class="btn btn-secondary">Không</a>
<button type="button" class="btn btn-warning" id="btnDoReplace">Có, thay thế</button>
</div>
</div></div></div>

<div class="modal fade" id="modalAudit" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-search"></i> Rà soát phân công</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="row g-2 mb-3 text-center">
<div class="col"><div class="border rounded p-2"><div class="fs-4 fw-bold text-danger"><?= $n_conflict ?></div><div class="small">Trùng</div></div></div>
<div class="col"><div class="border rounded p-2"><div class="fs-4 fw-bold text-warning"><?= count($missing) ?></div><div class="small">Thiếu môn</div></div></div>
<div class="col"><div class="border rounded p-2"><div class="fs-4 fw-bold text-warning"><?= count($under) ?></div><div class="small">Thiếu tiết</div></div></div>
<div class="col"><div class="border rounded p-2"><div class="fs-4 fw-bold text-danger"><?= count($over) ?></div><div class="small">Thừa tiết</div></div></div>
</div>
<h6 class="text-danger">Trùng môn + lớp</h6>
<?php if ($conflicts): ?><table class="table table-sm"><thead><tr><th>Môn</th><th>Lớp</th><th>GV</th></tr></thead><tbody>
<?php foreach ($conflicts as $c): ?><tr class="table-warning"><td><?= e($c['subject']) ?></td><td><?= e($c['class']) ?></td><td><?= e(implode(', ',$c['teachers'])) ?></td></tr><?php endforeach; ?>
</tbody></table><?php else: ?><p class="text-success small">Không trùng.</p><?php endif; ?>
<h6>Thiếu môn + lớp</h6>
<?php if ($missing): ?><div style="max-height:180px;overflow:auto"><table class="table table-sm"><tbody>
<?php foreach ($missing as $m): ?><tr><td><?= e($m['subject']) ?></td><td><?= e($m['class']) ?></td><td><?= e($m['periods']) ?>t</td></tr><?php endforeach; ?>
</tbody></table></div><?php else: ?><p class="text-success small">Không thiếu.</p><?php endif; ?>
<div class="row">
<div class="col-md-6"><h6 class="text-warning">Thiếu tiết</h6>
<table class="table table-sm"><tbody>
<?php foreach ($under as $u): ?><tr><td><?= e($u['teacher']) ?></td><td><?= number_format($u['total'],2) ?>/<?= number_format($u['quota'],0) ?></td><td class="diff-under"><?= number_format($u['diff'],2) ?></td></tr><?php endforeach; ?>
<?php if (!$under): ?><tr><td class="text-muted">Không có</td></tr><?php endif; ?>
</tbody></table></div>
<div class="col-md-6"><h6 class="text-danger">Thừa tiết</h6>
<table class="table table-sm"><tbody>
<?php foreach ($over as $u): ?><tr><td><?= e($u['teacher']) ?></td><td><?= number_format($u['total'],2) ?>/<?= number_format($u['quota'],0) ?></td><td class="diff-over">+<?= number_format($u['diff'],2) ?></td></tr><?php endforeach; ?>
<?php if (!$over): ?><tr><td class="text-muted">Không có</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</div>
<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
</div></div></div>

<div class="modal fade" id="modalSwap" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header bg-warning"><h5 class="modal-title">Đổi chéo toàn bộ 2 GV</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post" class="pccm-keep" onsubmit="return confirm('Đổi toàn bộ dạy + KN giữa 2 GV? Không hoàn tác!')">
<input type="hidden" name="action" value="swap_all">
<div class="modal-body">
<div class="warn-box small">Đổi tất cả phân công dạy và kiêm nhiệm giữa 2 giáo viên.</div>
<select name="teacher1" class="form-select form-select-sm mb-2" required><option value="">-- GV A --</option>
<?php foreach ($teachers as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
<div class="text-center text-muted"><i class="bi bi-arrow-down-up"></i></div>
<select name="teacher2" class="form-select form-select-sm mt-2" required><option value="">-- GV B --</option>
<?php foreach ($teachers as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
<button type="submit" class="btn btn-warning">Đổi chéo</button>
</div></form>
</div></div></div>

<script>
(function(){
  var KEY = 'pccm_them_state';
  function loadState(){ try { return JSON.parse(sessionStorage.getItem(KEY)||'{}'); } catch(e){ return {}; } }
  function saveState(patch){
    var s = loadState();
    Object.keys(patch).forEach(function(k){ s[k]=patch[k]; });
    sessionStorage.setItem(KEY, JSON.stringify(s));
  }
  function clearFilters(){
    var s = loadState();
    s.q=''; s.level=''; s.group=''; s.load='';
    sessionStorage.setItem(KEY, JSON.stringify(s));
    applyFilters();
  }

  function applyFilters(){
    var q = (document.getElementById('boardFilter')?.value||'').toLowerCase().trim();
    var level = document.getElementById('fLevel')?.value||'';
    var group = document.getElementById('fGroup')?.value||'';
    var load = document.getElementById('fLoad')?.value||'';
    var shown = 0, total = 0;
    document.querySelectorAll('#boardBody .board-row').forEach(function(row){
      total++;
      var ok = true;
      if (q && !(row.dataset.search||'').includes(q)) ok = false;
      if (ok && level && !(row.dataset.level||'').includes(level)) ok = false;
      if (ok && group === 'khxh' && row.dataset.khxh !== '1') ok = false;
      if (ok && group === 'khtn' && row.dataset.khtn !== '1') ok = false;
      if (ok && load && row.dataset.load !== load) ok = false;
      row.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    var fc = document.getElementById('fCount');
    if (fc) fc.textContent = shown + '/' + total + ' GV';
    saveState({ q:q, level:level, group:group, load:load });
  }

  // Khôi phục bộ lọc đã lưu
  var st = loadState();
  if (document.getElementById('boardFilter')) document.getElementById('boardFilter').value = st.q||'';
  if (document.getElementById('fLevel')) document.getElementById('fLevel').value = st.level||'';
  if (document.getElementById('fGroup')) document.getElementById('fGroup').value = st.group||'';
  if (document.getElementById('fLoad')) document.getElementById('fLoad').value = st.load||'';
  applyFilters();

  ['boardFilter','fLevel','fGroup','fLoad'].forEach(function(id){
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener(id==='boardFilter'?'input':'change', applyFilters);
  });
  document.getElementById('fClear')?.addEventListener('click', function(){
    document.getElementById('boardFilter').value = '';
    document.getElementById('fLevel').value = '';
    document.getElementById('fGroup').value = '';
    document.getElementById('fLoad').value = '';
    clearFilters();
  });

  // Lưu vị trí cuộn trước khi submit form
  function rememberScroll(){
    var board = document.getElementById('boardBody');
    saveState({
      scrollY: window.scrollY || window.pageYOffset || 0,
      boardScroll: board ? board.scrollTop : 0,
      q: document.getElementById('boardFilter')?.value||'',
      level: document.getElementById('fLevel')?.value||'',
      group: document.getElementById('fGroup')?.value||'',
      load: document.getElementById('fLoad')?.value||''
    });
  }
  document.querySelectorAll('form').forEach(function(f){
    f.addEventListener('submit', rememberScroll);
  });

  // Khôi phục cuộn sau load
  var restore = loadState();
  if (typeof restore.scrollY === 'number') {
    requestAnimationFrame(function(){
      window.scrollTo(0, restore.scrollY);
      var board = document.getElementById('boardBody');
      if (board && typeof restore.boardScroll === 'number') board.scrollTop = restore.boardScroll;
    });
  } else if (<?= !empty($_GET['ok']) ? 'true' : 'false' ?>) {
    document.getElementById('board')?.scrollIntoView({behavior:'smooth', block:'start'});
  }
})();

// Số tiết auto
const subjectSelect = document.getElementById('subject');
const classSelect = document.getElementById('class_name');
const periodsDisplay = document.getElementById('periods-display');
const periodsValue = document.getElementById('periods-value');
const periodsManual = document.getElementById('periods_manual');
function fetchPeriods() {
  const subject = subjectSelect?.value, cls = classSelect?.value;
  if (!subject || !cls) { periodsDisplay?.classList.add('d-none'); periodsManual?.classList.add('d-none'); return; }
  fetch('<?= BASE_URL ?>api/periods.php?subject=' + encodeURIComponent(subject) + '&class=' + encodeURIComponent(cls))
    .then(r => r.json()).then(data => {
      if (data.periods !== null && data.periods !== undefined) {
        periodsValue.textContent = data.periods;
        periodsDisplay.classList.remove('d-none');
        periodsManual.classList.add('d-none');
      } else {
        periodsDisplay.classList.add('d-none');
        periodsManual.classList.remove('d-none');
      }
    });
}
if (subjectSelect) { subjectSelect.addEventListener('change', fetchPeriods); classSelect.addEventListener('change', fetchPeriods); fetchPeriods(); }
const roleSelect = document.getElementById('roleSelect');
if (roleSelect) roleSelect.addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  document.getElementById('roleClassWrap').style.opacity = opt?.dataset?.needClass === '1' ? '1' : '0.55';
});

let dragId = null;
function onDragChip(e) {
  dragId = e.currentTarget.dataset.id;
  e.dataTransfer.setData('text/plain', dragId);
  e.dataTransfer.effectAllowed = 'move';
}
function onDropChip(e, el) {
  e.preventDefault();
  el.classList.remove('drag-over');
  const id = e.dataTransfer.getData('text/plain') || dragId;
  const to = el.dataset.teacher;
  if (!id || !to) return;
  const fromChip = document.querySelector('.chip[data-id="'+id+'"]');
  if (fromChip && fromChip.dataset.teacher === to) return;
  if (!confirm('Chuyển phân công sang « ' + to + ' »?')) return;
  document.getElementById('moveId').value = id;
  document.getElementById('moveTo').value = to;
  document.getElementById('formMove').submit();
}
function openChipModal(el) {
  document.getElementById('chipId').value = el.dataset.id;
  document.getElementById('chipDeleteId').value = el.dataset.id;
  document.getElementById('chipTeacher').value = el.dataset.teacher;
  document.getElementById('chipSubject').value = el.dataset.subject;
  document.getElementById('chipClass').value = el.dataset.class;
  document.getElementById('chipPeriods').value = el.dataset.periods;
  document.getElementById('chipNote').value = el.dataset.note || '';
  new bootstrap.Modal(document.getElementById('modalChip')).show();
}
document.getElementById('btnChipDelete')?.addEventListener('click', function() {
  if (confirm('Xóa phân công này?')) document.getElementById('formChipDelete').submit();
});

<?php if ($ask_replace): ?>
(function(){
  document.getElementById('rpSubject').textContent = <?= json_encode($_GET['subject'] ?? '') ?>;
  document.getElementById('rpClass').textContent = <?= json_encode($_GET['class'] ?? '') ?>;
  document.getElementById('rpConflict').textContent = <?= json_encode($_GET['conflict'] ?? '') ?>;
  document.getElementById('rpNew').textContent = <?= json_encode($_GET['teacher'] ?? '') ?>;
  document.getElementById('btnDoReplace').onclick = function() {
    document.getElementById('replaceOne').value = '1';
    document.getElementById('formAddOne').submit();
  };
  new bootstrap.Modal(document.getElementById('modalReplace')).show();
})();
<?php endif; ?>
<?php if ($ask_replace_role): ?>
(function(){
  document.getElementById('rpSubject').textContent = <?= json_encode($_GET['role'] ?? '') ?>;
  document.getElementById('rpClass').textContent = <?= json_encode($_GET['class'] ?? '') ?>;
  document.getElementById('rpConflict').textContent = <?= json_encode($_GET['conflict'] ?? '') ?>;
  document.getElementById('rpNew').textContent = <?= json_encode($_GET['teacher'] ?? '') ?>;
  document.getElementById('btnDoReplace').onclick = function() {
    document.getElementById('replaceRole').value = '1';
    document.getElementById('formAddRole').submit();
  };
  new bootstrap.Modal(document.getElementById('modalReplace')).show();
})();
<?php endif; ?>
</script>
<?php require_once 'includes/footer.php'; ?>
