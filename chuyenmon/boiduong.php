<?php
$page_title = 'Bồi dưỡng học sinh';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_login();
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
    $db->exec("CREATE TABLE IF NOT EXISTS cds_student_support_classes (school_year VARCHAR(12) NOT NULL, category VARCHAR(20) NOT NULL, class_id VARCHAR(100) NOT NULL, PRIMARY KEY(school_year,category,class_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS cds_student_support_teachers (school_year VARCHAR(12) NOT NULL, category VARCHAR(20) NOT NULL, subject VARCHAR(100) NOT NULL, teacher VARCHAR(255) NOT NULL, PRIMARY KEY(school_year,category,subject,teacher)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS cds_student_support_members (school_year VARCHAR(12) NOT NULL, category VARCHAR(20) NOT NULL, subject VARCHAR(100) NOT NULL, student_id VARCHAR(100) NOT NULL, teacher VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(school_year,category,subject,student_id), KEY idx_support_student(student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $column=$db->query("SHOW COLUMNS FROM cds_student_support_members LIKE 'group_name'")->fetch();
    if (!$column) $db->exec("ALTER TABLE cds_student_support_members ADD COLUMN group_name VARCHAR(3) NOT NULL DEFAULT ''");
} catch (Throwable $ex) { http_response_code(503); exit('Không thể mở dữ liệu bồi dưỡng. Vui lòng kiểm tra kết nối MySQL.'); }
// Đọc CSDL chung mà không nạp lại includes/auth.php (trùng hàm với Chuyên môn).
try { $classes = $db->query('SELECT id,name,homeroom_teacher_id FROM cds_classes')->fetchAll(); $studentRows = $db->query('SELECT id,name,class_id,school_year_id,active FROM cds_students')->fetchAll(); }
catch (Throwable $ex) { $classes = []; $studentRows = []; }
if (!$classes) $classes = (array)load_json(dirname(__DIR__).'/data/classes.json', []);
if (!$studentRows) $studentRows = (array)load_json(dirname(__DIR__).'/data/students.json', []);
$classNames = []; $homeroom = [];
try { $teacherRows=$db->query('SELECT id,name FROM cds_teachers')->fetchAll(); } catch (Throwable $ex) { $teacherRows=[]; }
if (!$teacherRows) $teacherRows=(array)load_json(dirname(__DIR__).'/data/teachers.json',[]);
$teacherNames=[]; foreach($teacherRows as $t) if(!empty($t['id'])) $teacherNames[(string)$t['id']] = (string)($t['name']??'');
foreach ($classes as $c) { $classNames[(string)($c['id'] ?? '')] = (string)($c['name'] ?? ''); $name=(string)($c['homeroom_teacher_name']??$c['homeroom_teacher']??($teacherNames[(string)($c['homeroom_teacher_id']??'')]??'')); if($name!=='') $homeroom[(string)($c['id']??'')]=$name; }
$students = []; foreach ($studentRows as $s) {
    if (isset($s['active']) && !$s['active']) continue;
    $id = (string)($s['id'] ?? ''); if ($id === '') continue;
    $class = (string)($s['class_name'] ?? $s['class'] ?? ''); if ($class === '') $class = $classNames[(string)($s['class_id'] ?? '')] ?? '';
    $students[$id] = ['name'=>(string)($s['name'] ?? ''), 'class'=>$class, 'class_id'=>(string)($s['class_id'] ?? ''), 'year'=>(string)($s['school_year_id'] ?? '')];
}
$classOptions=[]; foreach($classes as $c) { $id=(string)($c['id']??''); $name=(string)($c['name']??''); if($id!==''&&$name!=='') $classOptions[$id]=$name; }
$st=$db->prepare('SELECT category,class_id FROM cds_student_support_classes WHERE school_year=?'); $st->execute([$year]); $examClasses=['tn'=>[],'ts'=>[]]; foreach($st->fetchAll() as $r) if(isset($examClasses[$r['category']])) $examClasses[$r['category']][$r['class_id']]=true;
// Lần đầu sử dụng: TN lấy lớp 12, TS lấy lớp 9. Cài đặt sau đó là nguồn duy nhất.
foreach(['tn'=>12,'ts'=>9] as $category=>$grade) if(!$examClasses[$category]) {
    $add=$db->prepare('INSERT IGNORE INTO cds_student_support_classes(school_year,category,class_id) VALUES(?,?,?)');
    foreach($classOptions as $id=>$name) if(preg_match('/^(?:Lớp\s*)?'.$grade.'(?=\D|$)/iu',trim($name))) { $add->execute([$year,$category,$id]); $examClasses[$category][$id]=true; }
}
$assignments = get_assignments(); $allowed = [];
foreach ($assignments as $a) if ($norm($a['teacher'] ?? '') === $norm($teacher)) $allowed[$norm($a['class'] ?? '')][$norm($a['subject'] ?? '')] = true;
$st=$db->prepare('SELECT category,subject,teacher FROM cds_student_support_teachers WHERE school_year=?'); $st->execute([$year]); $examTeachers=[];
foreach($st->fetchAll() as $r) $examTeachers[$r['category']][$norm($r['subject'])][$norm($r['teacher'])]=true;
$canSelect = static function ($student, $subject, $category) use ($admin, $allowed, $norm, $teacher, $homeroom, $examTeachers, $user) {
    if ($admin) return true;
    if (in_array($category,['tn','ts'],true)) {
        $isHomeroom = $norm($homeroom[$student['class_id']]??'') === $norm($teacher) && $teacher!=='';
        foreach ((array)($user['homeroom_classes']??[]) as $c) if ($norm(is_array($c)?($c['name']??$c['id']??''):$c)===$norm($student['class'])) $isHomeroom=true;
        return $isHomeroom || ($teacher!=='' && isset($examTeachers[$category][$norm($subject)][$norm($teacher)]));
    }
    return isset($allowed[$norm($student['class'])][$norm($subject)]);
};
$available = array_values(array_filter(array_keys(get_subjects()), 'strlen')); sort($available);
$st = $db->prepare('SELECT category,subject FROM cds_student_support_settings WHERE school_year=? ORDER BY category,subject'); $st->execute([$year]); $settings = ['tn'=>[], 'ts'=>[]]; foreach ($st->fetchAll() as $r) $settings[$r['category']][] = $r['subject'];
if (empty($_SESSION['cm_support_csrf'])) $_SESSION['cm_support_csrf'] = bin2hex(random_bytes(24)); $csrf = $_SESSION['cm_support_csrf'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Phiên làm việc không hợp lệ.'); }
    $action = (string)($_POST['action'] ?? ''); $cat = (string)($_POST['category'] ?? ''); $subject = trim((string)($_POST['subject'] ?? ''));
    try {
        if ($action === 'exam_classes' && $admin && in_array($cat,['tn','ts'],true)) {
            $selected=array_values(array_unique(array_intersect(array_keys($classOptions),array_map('strval',(array)($_POST['class_ids']??[])))));
            if(!$selected) throw new RuntimeException('Hãy chọn ít nhất một lớp ôn thi.');
            $db->beginTransaction(); $q=$db->prepare('DELETE FROM cds_student_support_classes WHERE school_year=? AND category=?'); $q->execute([$year,$cat]);
            $q=$db->prepare('INSERT INTO cds_student_support_classes(school_year,category,class_id) VALUES(?,?,?)'); foreach($selected as $id) $q->execute([$year,$cat,$id]); $db->commit();
        } elseif ($action === 'assign_teachers' && $admin && in_array($cat,['tn','ts'],true) && in_array($subject,$settings[$cat],true)) {
            $names=array_values(array_unique(array_intersect(get_teachers_sorted(),array_map('strval',(array)($_POST['teachers']??[])))));
            $db->beginTransaction(); $q=$db->prepare('DELETE FROM cds_student_support_teachers WHERE school_year=? AND category=? AND subject=?'); $q->execute([$year,$cat,$subject]);
            $q=$db->prepare('INSERT INTO cds_student_support_teachers(school_year,category,subject,teacher) VALUES(?,?,?,?)'); foreach($names as $name) $q->execute([$year,$cat,$subject,$name]); $db->commit();
        } elseif ($action === 'settings' && $admin && in_array($cat, ['tn','ts'], true)) {
            $selected = array_values(array_unique(array_intersect($available, array_map('strval', (array)($_POST['subjects'] ?? [])))));
            $db->beginTransaction(); $del = $db->prepare('DELETE FROM cds_student_support_settings WHERE school_year=? AND category=?'); $del->execute([$year,$cat]);
            $add = $db->prepare('INSERT INTO cds_student_support_settings(school_year,category,subject) VALUES(?,?,?)'); foreach ($selected as $v) $add->execute([$year,$cat,$v]); $db->commit();
        } elseif (in_array($action, ['member','bulk_add','bulk_remove','set_group'], true) && in_array($cat, ['tn','ts','muinhon','chuadat'], true) && in_array($subject,$available,true)) {
            if (in_array($cat,['tn','ts'],true) && !in_array($subject,$settings[$cat],true)) throw new RuntimeException('Môn thi chưa được cài đặt hoặc tài khoản không có quyền cập nhật đăng ký.');
            $ids = in_array($action,['member','set_group'],true) ? [(string)($_POST['student_id'] ?? '')] : array_values(array_unique(array_map('strval', (array)($_POST['student_ids'] ?? []))));
            if (!$ids || count($ids) > 1500) throw new RuntimeException('Hãy chọn học sinh trong danh sách.');
            // Xác thực lại từng học sinh trên máy chủ, không tin vào danh sách gửi từ trình duyệt.
            foreach ($ids as $id) {
                if (!isset($students[$id])) throw new RuntimeException('Học sinh không có trong CSDL.');
                if (in_array($cat,['tn','ts'],true) && !isset($examClasses[$cat][$students[$id]['class_id']])) throw new RuntimeException('Lớp của học sinh chưa được cài đặt cho kỳ ôn thi.');
                if (!$admin && !$canSelect($students[$id],$subject,$cat)) throw new RuntimeException('Giáo viên chỉ được chọn học sinh đúng lớp và môn đang phụ trách.');
            }
            if ($action==='set_group') {
                if (!in_array($cat,['tn','ts'],true)) throw new RuntimeException('Chỉ nhóm ôn thi có TBK/TBY.');
                $group=(string)($_POST['group_name']??''); if(!in_array($group,['TBK','TBY'],true)) throw new RuntimeException('Nhóm không hợp lệ.');
                $q=$db->prepare('UPDATE cds_student_support_members SET group_name=? WHERE school_year=? AND category=? AND subject=? AND student_id=?'); $q->execute([$group,$year,$cat,$subject,$ids[0]]);
                if(!$q->rowCount()) { $q=$db->prepare('SELECT 1 FROM cds_student_support_members WHERE school_year=? AND category=? AND subject=? AND student_id=?'); $q->execute([$year,$cat,$subject,$ids[0]]); if(!$q->fetchColumn()) throw new RuntimeException('Hãy đánh dấu học sinh tham gia trước khi chọn nhóm.'); }
            } else {
            $on = $action === 'bulk_add' || ($action === 'member' && !empty($_POST['selected']));
            $db->beginTransaction();
            $addMember = $db->prepare('INSERT INTO cds_student_support_members(school_year,category,subject,student_id,teacher) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE teacher=VALUES(teacher)');
            $removeMember = $db->prepare('DELETE FROM cds_student_support_members WHERE school_year=? AND category=? AND subject=? AND student_id=?');
            foreach ($ids as $id) {
                if ($on) $addMember->execute([$year,$cat,$subject,$id,$teacher]);
                else $removeMember->execute([$year,$cat,$subject,$id]);
            }
            $db->commit();
            }
        } else throw new RuntimeException('Không có quyền thực hiện thao tác.');
        header('Location: '.BASE_URL.'boiduong.php?'.http_build_query(['tab'=>$tab,'year'=>$year,'class'=>(string)($_POST['class'] ?? ''),'subject'=>$subject])); exit;
    } catch (Throwable $ex) { if ($db->inTransaction()) $db->rollBack(); $error=$ex->getMessage(); }
    $st->execute([$year]); $settings=['tn'=>[],'ts'=>[]]; foreach ($st->fetchAll() as $r) $settings[$r['category']][]=$r['subject'];
}
$st=$db->prepare('SELECT category,subject,student_id,teacher,group_name FROM cds_student_support_members WHERE school_year=?'); $st->execute([$year]); $members=[]; foreach ($st->fetchAll() as $r) $members[$r['category']][$r['subject']][$r['student_id']]=['teacher'=>$r['teacher'],'group'=>$r['group_name']];
$allClasses=array_values(array_unique(array_filter(array_column($students,'class')))); usort($allClasses,'strnatcasecmp');
if(in_array($tab,['tn','ts'],true)) $allClasses=array_values(array_filter($allClasses,static function($name) use($classOptions,$examClasses,$tab) { foreach($examClasses[$tab] as $id=>$_) if(($classOptions[$id]??'')===$name) return true; return false; }));
$class=(string)($_GET['class'] ?? ''); if (!in_array($class,$allClasses,true)) $class='';
$subjects = in_array($tab,['tn','ts'],true) ? $settings[$tab] : ($admin ? $available : array_values(array_filter($available, static function($s) use($allowed,$norm) { foreach($allowed as $row) if(isset($row[$norm($s)])) return true; return false; })));
$subject=(string)($_GET['subject'] ?? ''); if (!in_array($subject,$subjects,true)) $subject=$subjects[0] ?? '';
$viewStudents=array_filter($students,static function($s) use($class,$subject,$tab,$examClasses) { return (!$class || $s['class']===$class) && $subject!=='' && (!in_array($tab,['tn','ts'],true) || isset($examClasses[$tab][$s['class_id']])); });
$sort=(string)($_GET['sort']??'class'); if(!in_array($sort,['name','class','group','status'],true)) $sort='class';
$direction=(string)($_GET['dir']??'asc')==='desc'?-1:1;
uksort($viewStudents,static function($a,$b) use(&$viewStudents,$members,$tab,$subject,$sort,$direction) {
    $x=$viewStudents[$a]; $y=$viewStudents[$b];
    $left=$sort==='name'?$x['name']:($sort==='group'?($members[$tab][$subject][$a]['group']??''):($sort==='status'?(isset($members[$tab][$subject][$a])?'1':'0'):$x['class']));
    $right=$sort==='name'?$y['name']:($sort==='group'?($members[$tab][$subject][$b]['group']??''):($sort==='status'?(isset($members[$tab][$subject][$b])?'1':'0'):$y['class']));
    return $direction*(strnatcasecmp((string)$left,(string)$right) ?: strnatcasecmp($x['name'],$y['name']));
});
$canEditAny=false; foreach($viewStudents as $row) if($canSelect($row,$subject,$tab)) { $canEditAny=true; break; }
$sortUrl=static function($column) use($tab,$year,$class,$subject,$sort,$direction) { return BASE_URL.'boiduong.php?'.http_build_query(['tab'=>$tab,'year'=>$year,'class'=>$class,'subject'=>$subject,'sort'=>$column,'dir'=>$sort===$column&&$direction===1?'desc':'asc']); };
if (!$admin && !in_array($tab,['tn','ts'],true) && $subject !== '') $allClasses = array_values(array_filter($allClasses, static function($cl) use($norm,$allowed,$subject) { return isset($allowed[$norm($cl)][$norm($subject)]); }));
require __DIR__.'/includes/header.php';
?>
<div class="container-fluid py-3">
<h2 class="mb-3"><i class="bi bi-mortarboard"></i> Bồi dưỡng học sinh</h2>
<div class="small text-muted mb-3">Năm học <?=e($year)?> · Ôn thi lấy theo danh sách đăng ký môn; các nhóm bồi dưỡng do giáo viên phụ trách chọn.</div>
<nav class="nav nav-pills gap-2 mb-3 flex-wrap"><?php foreach($tabs as $key=>$label):?><a class="nav-link <?=$tab===$key?'active':''?>" href="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$key,'year'=>$year]))?>"><?=e($label)?></a><?php endforeach;?></nav>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if($tab==='caidat'):?>
<?php if(!$admin):?><div class="alert alert-info">Chỉ quản trị được cài đặt môn thi.</div><?php else:?>
<?php foreach(['tn'=>'Ôn thi tốt nghiệp','ts'=>'Ôn thi tuyển sinh vào 10'] as $key=>$title):?><div class="card mb-3"><div class="card-body"><h5><?=e($title)?></h5>
<form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>'caidat','year'=>$year]))?>" class="border rounded p-3 mb-3"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="exam_classes"><input type="hidden" name="category" value="<?=e($key)?>"><div class="fw-bold mb-2">Lớp ôn thi · <?=e($title)?></div><div class="row g-2 mb-3"><?php foreach($classOptions as $id=>$name):?><label class="col-6 col-md-2"><input type="checkbox" name="class_ids[]" value="<?=e($id)?>" <?=isset($examClasses[$key][$id])?'checked':''?>> <?=e($name)?></label><?php endforeach;?></div><button class="btn btn-sm btn-primary">Lưu lớp ôn thi</button><div class="form-text">Mặc định: lớp 12 cho TN, lớp 9 cho tuyển sinh. Thay đổi chỉ điều chỉnh danh sách để chọn, không xóa học sinh đã đăng ký.</div></form><form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="settings"><input type="hidden" name="category" value="<?=e($key)?>"><div class="row g-2 mb-3"><?php foreach($available as $s):?><label class="col-6 col-md-3"><input type="checkbox" name="subjects[]" value="<?=e($s)?>" <?=in_array($s,$settings[$key],true)?'checked':''?>> <?=e($s)?></label><?php endforeach;?></div><button class="btn btn-primary">Lưu môn thi</button></form>
<?php foreach($settings[$key] as $examSubject):?><form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>'caidat','year'=>$year]))?>" class="border rounded p-3 mt-3"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="assign_teachers"><input type="hidden" name="category" value="<?=e($key)?>"><input type="hidden" name="subject" value="<?=e($examSubject)?>"><label class="form-label fw-bold"><?=e($examSubject)?> · Giáo viên dạy ôn thi</label><select class="form-select mb-2" name="teachers[]" multiple size="5"><?php foreach(get_teachers_sorted() as $name):?><option value="<?=e($name)?>" <?=isset($examTeachers[$key][$norm($examSubject)][$norm($name)])?'selected':''?>><?=e($name)?></option><?php endforeach;?></select><button class="btn btn-sm btn-outline-primary">Lưu phân công</button></form><?php endforeach;?></div></div><?php endforeach;?>
<?php endif;?>
<?php elseif($tab==='thongke'):?>
<?php $detailCategory=(string)($_GET['detail_category']??''); $detailSubject=(string)($_GET['detail_subject']??''); $detailClass=(string)($_GET['detail_class']??''); $detailGroup=(string)($_GET['detail_group']??'');
$detailActive=isset($tabs[$detailCategory]) && in_array($detailCategory,['tn','ts','muinhon','chuadat'],true) && $detailSubject!=='' && $detailClass!=='';
$counts=[]; $detailRows=[];
foreach($members as $category=>$bySubject) foreach($bySubject as $sub=>$ids) foreach($ids as $id=>$entry) if(isset($students[$id])) {
    $cl=$students[$id]['class']; $group=in_array($category,['tn','ts'],true)?($entry['group']?:'Chưa xếp'):'';
    $key=json_encode([$category,$sub,$cl,$group],JSON_UNESCAPED_UNICODE); $counts[$key]=($counts[$key]??0)+1;
    if($detailActive && $category===$detailCategory && $sub===$detailSubject && $cl===$detailClass && $group===$detailGroup) $detailRows[]=['student'=>$students[$id],'entry'=>$entry];
}
ksort($counts); usort($detailRows,static fn($a,$b)=>strnatcasecmp($a['student']['name'],$b['student']['name'])); ?>
<div class="card"><div class="card-body"><h5>Thống kê theo nội dung, môn, lớp và nhóm</h5><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Nội dung</th><th>Môn</th><th>Lớp</th><th>Nhóm ôn thi</th><th>Số học sinh</th></tr></thead><tbody><?php foreach($counts as $key=>$count):[$c,$s,$cl,$gr]=json_decode($key,true);?><tr><td><?=e($tabs[$c]??$c)?></td><td><?=e($s)?></td><td><?=e($cl)?></td><td><?=e($gr)?></td><td><a href="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>'thongke','year'=>$year,'detail_category'=>$c,'detail_subject'=>$s,'detail_class'=>$cl,'detail_group'=>$gr]))?>"><?=$count?> · Xem danh sách</a></td></tr><?php endforeach;if(!$counts):?><tr><td colspan="5" class="text-muted">Chưa có dữ liệu.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php if($detailActive):?><div class="card mt-3"><div class="card-body"><h5><?=e($tabs[$detailCategory])?> · <?=e($detailSubject)?> · <?=e($detailClass)?> <?=e($detailGroup)?></h5><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>STT</th><th>Họ và tên</th><th>Lớp</th><th>Nhóm</th><th>Người chọn</th></tr></thead><tbody><?php foreach($detailRows as $i=>$row):?><tr><td><?=$i+1?></td><td><?=e($row['student']['name'])?></td><td><?=e($row['student']['class'])?></td><td><?=e($row['entry']['group'])?></td><td><?=e($row['entry']['teacher'])?></td></tr><?php endforeach;if(!$detailRows):?><tr><td colspan="5">Không còn học sinh trong nhóm này.</td></tr><?php endif;?></tbody></table></div></div></div><?php endif;?>
<?php else:?>
<?php if(in_array($tab,['tn','ts'],true) && !$examClasses[$tab]):?><div class="alert alert-info">Chưa chọn lớp ôn thi trong Cài đặt.</div><?php endif;?>
<?php if(in_array($tab,['tn','ts'],true) && !$subjects):?><div class="alert alert-info">Chưa cài đặt môn thi. Quản trị chọn môn trong tab Cài đặt.</div><?php endif;?>
<form method="get" action="<?=BASE_URL?>boiduong.php" class="row g-2 mb-3"><input type="hidden" name="tab" value="<?=e($tab)?>"><div class="col-md-2"><label class="form-label">Năm học</label><input class="form-control" name="year" value="<?=e($year)?>" pattern="[0-9]{4}-[0-9]{4}"></div><div class="col-md-3"><label class="form-label">Môn</label><select class="form-select" name="subject"><?php foreach($subjects as $s):?><option value="<?=e($s)?>" <?=$s===$subject?'selected':''?>><?=e($s)?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Lớp</label><select class="form-select" name="class"><option value="">Tất cả lớp</option><?php foreach($allClasses as $cl):?><option value="<?=e($cl)?>" <?=$class===$cl?'selected':''?>><?=e($cl)?></option><?php endforeach;?></select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Xem danh sách</button></div></form>
<div class="card"><div class="card-body"><h5><?=e($tabs[$tab])?> · <?=e($subject)?> <span class="badge text-bg-primary"><?=count($members[$tab][$subject]??[])?></span></h5><p class="text-muted small"><?=in_array($tab,['tn','ts'],true)?'Quản trị, GVCN lớp và giáo viên được phân công dạy ôn thi được chọn học sinh và xếp nhóm TBK/TBY.':'Giáo viên đánh dấu học sinh ở lớp và môn mình dạy.'?></p><?php if($subject!=='' && $canEditAny):?>
<form id="supportBulk" method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>" class="d-flex gap-2 align-items-center flex-wrap mb-2">
<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="class" value="<?=e($class)?>">
<button class="btn btn-sm btn-outline-primary" name="action" value="bulk_add">Thêm học sinh đã tích</button><button class="btn btn-sm btn-outline-danger" name="action" value="bulk_remove" onclick="return confirm('Bỏ các học sinh đã tích khỏi nhóm này?')">Bỏ học sinh đã tích</button>
<button class="btn btn-sm btn-outline-secondary" type="button" onclick="document.querySelectorAll('.support-check').forEach(c=>c.checked=true)">Tích toàn bộ danh sách đang xem</button>
<button class="btn btn-sm btn-outline-secondary" type="button" onclick="document.querySelectorAll('.support-check').forEach(c=>c.checked=false)">Bỏ tích</button>
</form><?php endif;?>
<div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Chọn</th><th>STT</th><th><a href="<?=e($sortUrl('name'))?>">Học sinh ↕</a></th><th><a href="<?=e($sortUrl('class'))?>">Lớp ↕</a></th><th><a href="<?=e($sortUrl('status'))?>">Tham gia ↕</a></th><?php if(in_array($tab,['tn','ts'],true)):?><th><a href="<?=e($sortUrl('group'))?>">Lớp/nhóm TBK, TBY ↕</a></th><?php endif;?><th>Người chọn</th></tr></thead><tbody><?php $i=0;foreach($viewStudents as $id=>$s):$selected=isset($members[$tab][$subject][$id]);$canEdit=$subject!==''&&$canSelect($s,$subject,$tab);?><tr><td><?php if($canEdit):?><input class="form-check-input support-check" type="checkbox" name="student_ids[]" form="supportBulk" value="<?=e($id)?>" aria-label="Chọn <?=e($s['name'])?>"><?php endif;?></td><td><?=++$i?></td><td><?=e($s['name'])?></td><td><?=e($s['class'])?></td><td><?php if($canEdit):?><form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="member"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="student_id" value="<?=e($id)?>"><input type="hidden" name="class" value="<?=e($class)?>"><input type="hidden" name="selected" value="<?=$selected?'0':'1'?>"><button class="btn btn-sm <?=$selected?'btn-success':'btn-outline-secondary'?>"><?=$selected?'Đã chọn':'Chọn'?></button></form><?php else:?><?=$selected?'Có':'—'?><?php endif;?></td>
<?php if(in_array($tab,['tn','ts'],true)):?><td><?php if($selected && $canEdit):?><form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>" class="d-flex gap-1"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="set_group"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="student_id" value="<?=e($id)?>"><input type="hidden" name="class" value="<?=e($class)?>"><select name="group_name" class="form-select form-select-sm" aria-label="Nhóm ôn thi của <?=e($s['name'])?>" onchange="this.form.requestSubmit()"><option value="" disabled>Chọn nhóm</option><?php foreach(['TBK','TBY'] as $gr):?><option value="<?=$gr?>" <?=($members[$tab][$subject][$id]['group']??'')===$gr?'selected':''?>><?=$gr?></option><?php endforeach;?></select><noscript><button class="btn btn-sm btn-primary">Lưu</button></noscript></form><?php else:?><?=e($selected?($members[$tab][$subject][$id]['group']?:'Chưa xếp'):'—')?><?php endif;?></td><?php endif;?><td><?=e($selected?$members[$tab][$subject][$id]['teacher']:'')?></td></tr><?php endforeach;if(!$i):?><tr><td colspan="<?=in_array($tab,['tn','ts'],true)?7:6?>" class="text-muted">Không có học sinh phù hợp.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php endif;?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
