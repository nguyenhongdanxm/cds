<?php
$page_title = 'Bồi dưỡng học sinh';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_login();
if (!cds_can_feature('cm.kehoach', 'view')) { http_response_code(403); exit('Không có quyền xem kế hoạch.'); }
$user = cds_user() ?? [];
$admin = ($user['role'] ?? '') === 'admin';
$teacher = trim((string)($user['teacher_name'] ?? $user['name'] ?? ''));
$norm = static function ($s) { return function_exists('mb_strtolower') ? mb_strtolower(trim((string)$s), 'UTF-8') : strtolower(trim((string)$s)); };
$tabs = ['tn'=>'Ôn thi TN', 'ts'=>'Ôn thi TS', 'muinhon'=>'Học sinh mũi nhọn', 'chuadat'=>'Học sinh chưa đạt', 'thongke'=>'Thống kê', 'caidat'=>'Cài đặt'];
$tab = (string)($_GET['tab'] ?? 'tn'); if (!isset($tabs[$tab])) $tab = 'tn';
$year = (string)($_GET['year'] ?? (function_exists('cds_current_school_year') ? cds_current_school_year() : (date('n') >= 8 ? date('Y').'-'.(date('Y')+1) : (date('Y')-1).'-'.date('Y'))));
if (!preg_match('/^\d{4}-\d{4}$/', $year)) $year = date('Y').'-'.(date('Y')+1);
try {
    $db = cds_db();
    $db->exec("CREATE TABLE IF NOT EXISTS cds_student_support_settings (school_year VARCHAR(12) NOT NULL, category VARCHAR(20) NOT NULL, subject VARCHAR(100) NOT NULL, PRIMARY KEY(school_year,category,subject)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS cds_student_support_members (school_year VARCHAR(12) NOT NULL, category VARCHAR(20) NOT NULL, subject VARCHAR(100) NOT NULL, student_id VARCHAR(100) NOT NULL, teacher VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(school_year,category,subject,student_id), KEY idx_support_student(student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $ex) { http_response_code(503); exit('Không thể mở dữ liệu bồi dưỡng. Vui lòng kiểm tra kết nối MySQL.'); }
// Đọc CSDL chung mà không nạp lại includes/auth.php (trùng hàm với Chuyên môn).
try { $classes = $db->query('SELECT id,name FROM cds_classes')->fetchAll(); $studentRows = $db->query('SELECT id,name,class_id,school_year_id,active FROM cds_students')->fetchAll(); }
catch (Throwable $ex) { $classes = []; $studentRows = []; }
if (!$classes) $classes = (array)load_json(dirname(__DIR__).'/data/classes.json', []);
if (!$studentRows) $studentRows = (array)load_json(dirname(__DIR__).'/data/students.json', []);
$classNames = []; foreach ($classes as $c) $classNames[(string)($c['id'] ?? '')] = (string)($c['name'] ?? '');
$students = []; foreach ($studentRows as $s) {
    if (isset($s['active']) && !$s['active']) continue;
    $id = (string)($s['id'] ?? ''); if ($id === '') continue;
    $class = (string)($s['class_name'] ?? $s['class'] ?? ''); if ($class === '') $class = $classNames[(string)($s['class_id'] ?? '')] ?? '';
    $students[$id] = ['name'=>(string)($s['name'] ?? ''), 'class'=>$class, 'class_id'=>(string)($s['class_id'] ?? ''), 'year'=>(string)($s['school_year_id'] ?? '')];
}
$assignments = get_assignments(); $allowed = [];
foreach ($assignments as $a) if ($norm($a['teacher'] ?? '') === $norm($teacher)) $allowed[$norm($a['class'] ?? '')][$norm($a['subject'] ?? '')] = true;
$canSelect = static function ($student, $subject) use ($admin, $allowed, $norm) { return $admin || isset($allowed[$norm($student['class'])][$norm($subject)]); };
$available = array_values(array_filter(array_keys(get_subjects()), 'strlen')); sort($available);
$st = $db->prepare('SELECT category,subject FROM cds_student_support_settings WHERE school_year=? ORDER BY category,subject'); $st->execute([$year]); $settings = ['tn'=>[], 'ts'=>[]]; foreach ($st->fetchAll() as $r) $settings[$r['category']][] = $r['subject'];
if (empty($_SESSION['cm_support_csrf'])) $_SESSION['cm_support_csrf'] = bin2hex(random_bytes(24)); $csrf = $_SESSION['cm_support_csrf'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Phiên làm việc không hợp lệ.'); }
    $action = (string)($_POST['action'] ?? ''); $cat = (string)($_POST['category'] ?? ''); $subject = trim((string)($_POST['subject'] ?? ''));
    try {
        if ($action === 'settings' && $admin && in_array($cat, ['tn','ts'], true)) {
            $selected = array_values(array_unique(array_intersect($available, array_map('strval', (array)($_POST['subjects'] ?? [])))));
            $db->beginTransaction(); $del = $db->prepare('DELETE FROM cds_student_support_settings WHERE school_year=? AND category=?'); $del->execute([$year,$cat]);
            $add = $db->prepare('INSERT INTO cds_student_support_settings(school_year,category,subject) VALUES(?,?,?)'); foreach ($selected as $v) $add->execute([$year,$cat,$v]); $db->commit();
        } elseif ($action === 'member' && in_array($cat, ['tn','ts','muinhon','chuadat'], true) && in_array($subject,$available,true)) {
            if (in_array($cat,['tn','ts'],true) && (!$admin || !in_array($subject,$settings[$cat],true))) throw new RuntimeException('Môn thi chưa được cài đặt hoặc tài khoản không có quyền cập nhật đăng ký.');
            $id = (string)($_POST['student_id'] ?? ''); if (!isset($students[$id])) throw new RuntimeException('Học sinh không có trong CSDL.');
            if (!$admin && !$canSelect($students[$id],$subject)) throw new RuntimeException('Giáo viên chỉ được chọn học sinh đúng lớp và môn đang phụ trách.');
            $on = !empty($_POST['selected']);
            if ($on) { $q=$db->prepare('INSERT INTO cds_student_support_members(school_year,category,subject,student_id,teacher) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE teacher=VALUES(teacher)'); $q->execute([$year,$cat,$subject,$id,$teacher]); }
            else { $q=$db->prepare('DELETE FROM cds_student_support_members WHERE school_year=? AND category=? AND subject=? AND student_id=?'); $q->execute([$year,$cat,$subject,$id]); }
        } else throw new RuntimeException('Không có quyền thực hiện thao tác.');
        header('Location: '.BASE_URL.'boiduong.php?'.http_build_query(['tab'=>$tab,'year'=>$year,'class'=>(string)($_GET['class'] ?? ''),'subject'=>$subject])); exit;
    } catch (Throwable $ex) { if ($db->inTransaction()) $db->rollBack(); $error=$ex->getMessage(); }
    $st->execute([$year]); $settings=['tn'=>[],'ts'=>[]]; foreach ($st->fetchAll() as $r) $settings[$r['category']][]=$r['subject'];
}
$st=$db->prepare('SELECT category,subject,student_id,teacher FROM cds_student_support_members WHERE school_year=?'); $st->execute([$year]); $members=[]; foreach ($st->fetchAll() as $r) $members[$r['category']][$r['subject']][$r['student_id']]=$r['teacher'];
$allClasses=array_values(array_unique(array_filter(array_column($students,'class')))); usort($allClasses,'strnatcasecmp');
$class=(string)($_GET['class'] ?? ''); if (!in_array($class,$allClasses,true)) $class='';
$subjects = in_array($tab,['tn','ts'],true) ? $settings[$tab] : $available;
$subject=(string)($_GET['subject'] ?? ''); if (!in_array($subject,$subjects,true)) $subject=$subjects[0] ?? '';
$viewStudents=array_filter($students,static function($s) use($class,$subject,$tab,$canSelect) { return (!$class || $s['class']===$class) && (in_array($tab,['tn','ts'],true) || $subject==='' || $canSelect($s,$subject)); });
require __DIR__.'/includes/header.php';
?>
<div class="container-fluid py-3">
<h2 class="mb-3"><i class="bi bi-mortarboard"></i> Bồi dưỡng học sinh</h2>
<div class="small text-muted mb-3">Năm học <?=e($year)?> · Ôn thi lấy theo danh sách đăng ký môn; các nhóm bồi dưỡng do giáo viên phụ trách chọn.</div>
<nav class="nav nav-pills gap-2 mb-3 flex-wrap"><?php foreach($tabs as $key=>$label):?><a class="nav-link <?=$tab===$key?'active':''?>" href="?<?=e(http_build_query(['tab'=>$key,'year'=>$year]))?>"><?=e($label)?></a><?php endforeach;?></nav>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if($tab==='caidat'):?>
<?php if(!$admin):?><div class="alert alert-info">Chỉ quản trị được cài đặt môn thi.</div><?php else:?>
<?php foreach(['tn'=>'Ôn thi tốt nghiệp','ts'=>'Ôn thi tuyển sinh'] as $key=>$title):?><div class="card mb-3"><div class="card-body"><h5><?=e($title)?></h5><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="settings"><input type="hidden" name="category" value="<?=e($key)?>"><div class="row g-2 mb-3"><?php foreach($available as $s):?><label class="col-6 col-md-3"><input type="checkbox" name="subjects[]" value="<?=e($s)?>" <?=in_array($s,$settings[$key],true)?'checked':''?>> <?=e($s)?></label><?php endforeach;?></div><button class="btn btn-primary">Lưu môn thi</button></form></div></div><?php endforeach;?>
<?php endif;?>
<?php elseif($tab==='thongke'):?>
<div class="card"><div class="card-body"><h5>Thống kê theo nội dung, môn và lớp</h5><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Nội dung</th><th>Môn</th><th>Lớp</th><th>Số học sinh</th></tr></thead><tbody><?php $counts=[];foreach($members as $category=>$bySubject)foreach($bySubject as $sub=>$ids)foreach($ids as $id=>$who)if(isset($students[$id])){$key=$category.'|'.$sub.'|'.$students[$id]['class'];$counts[$key]=($counts[$key]??0)+1;}ksort($counts);foreach($counts as $key=>$count):[$c,$s,$cl]=explode('|',$key,3);?><tr><td><?=e($tabs[$c]??$c)?></td><td><?=e($s)?></td><td><?=e($cl)?></td><td><?=$count?></td></tr><?php endforeach;if(!$counts):?><tr><td colspan="4" class="text-muted">Chưa có dữ liệu.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php else:?>
<?php if(in_array($tab,['tn','ts'],true) && !$subjects):?><div class="alert alert-info">Chưa cài đặt môn thi. Quản trị chọn môn trong tab Cài đặt.</div><?php endif;?>
<form method="get" class="row g-2 mb-3"><input type="hidden" name="tab" value="<?=e($tab)?>"><div class="col-md-2"><label class="form-label">Năm học</label><input class="form-control" name="year" value="<?=e($year)?>" pattern="[0-9]{4}-[0-9]{4}"></div><div class="col-md-3"><label class="form-label">Môn</label><select class="form-select" name="subject"><?php foreach($subjects as $s):?><option value="<?=e($s)?>" <?=$s===$subject?'selected':''?>><?=e($s)?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Lớp</label><select class="form-select" name="class"><option value="">Tất cả lớp</option><?php foreach($allClasses as $cl):?><option value="<?=e($cl)?>" <?=$class===$cl?'selected':''?>><?=e($cl)?></option><?php endforeach;?></select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Xem danh sách</button></div></form>
<div class="card"><div class="card-body"><h5><?=e($tabs[$tab])?> · <?=e($subject)?> <span class="badge text-bg-primary"><?=count($members[$tab][$subject]??[])?></span></h5><p class="text-muted small"><?=in_array($tab,['tn','ts'],true)?'Quản trị đánh dấu học sinh đăng ký môn thi.':'Giáo viên đánh dấu học sinh ở lớp và môn mình dạy.'?></p><div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>STT</th><th>Học sinh</th><th>Lớp</th><th>Tham gia</th><th>Người chọn</th></tr></thead><tbody><?php $i=0;foreach($viewStudents as $id=>$s):$selected=isset($members[$tab][$subject][$id]);?><tr><td><?=++$i?></td><td><?=e($s['name'])?></td><td><?=e($s['class'])?></td><td><?php if($subject!=='' && ($admin || (!in_array($tab,['tn','ts'],true) && $canSelect($s,$subject)))):?><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="member"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="student_id" value="<?=e($id)?>"><input type="hidden" name="selected" value="<?=$selected?'0':'1'?>"><button class="btn btn-sm <?=$selected?'btn-success':'btn-outline-secondary'?>" aria-label="<?=$selected?'Bỏ chọn':'Chọn'?> <?=e($s['name'])?>"><?=$selected?'Đã chọn':'Chọn'?></button></form><?php else:?><?=$selected?'Có':'—'?><?php endif;?></td><td><?=e($selected?$members[$tab][$subject][$id]:'')?></td></tr><?php endforeach;if(!$i):?><tr><td colspan="5" class="text-muted">Không có học sinh phù hợp.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php endif;?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
