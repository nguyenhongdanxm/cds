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
    $column=$db->query("SHOW COLUMNS FROM cds_student_support_members LIKE 'recommendation'")->fetch();
    if (!$column) $db->exec("ALTER TABLE cds_student_support_members ADD COLUMN recommendation VARCHAR(40) NOT NULL DEFAULT ''");
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
        } elseif (in_array($action, ['member','bulk_add','bulk_remove','set_group','bulk_group','set_recommendation'], true) && in_array($cat, ['tn','ts','muinhon','chuadat'], true) && in_array($subject,$available,true)) {
            if (in_array($cat,['tn','ts'],true) && !in_array($subject,$settings[$cat],true)) throw new RuntimeException('Môn thi chưa được cài đặt hoặc tài khoản không có quyền cập nhật đăng ký.');
            $ids = in_array($action,['member','set_group','set_recommendation'],true) ? [(string)($_POST['student_id'] ?? '')] : array_values(array_unique(array_map('strval', (array)($_POST['student_ids'] ?? []))));
            if (!$ids || count($ids) > 1500) throw new RuntimeException('Hãy chọn học sinh trong danh sách.');
            // Xác thực lại từng học sinh trên máy chủ, không tin vào danh sách gửi từ trình duyệt.
            foreach ($ids as $id) {
                if (!isset($students[$id])) throw new RuntimeException('Học sinh không có trong CSDL.');
                if (in_array($cat,['tn','ts'],true) && !isset($examClasses[$cat][$students[$id]['class_id']])) throw new RuntimeException('Lớp của học sinh chưa được cài đặt cho kỳ ôn thi.');
                if (!$admin && !$canSelect($students[$id],$subject,$cat)) throw new RuntimeException('Giáo viên chỉ được chọn học sinh đúng lớp và môn đang phụ trách.');
            }
            if ($action==='set_recommendation') {
                if (!in_array($cat,['muinhon','chuadat'],true)) throw new RuntimeException('Đề xuất chỉ dùng cho học sinh mũi nhọn hoặc chưa đạt.');
                $recommendation=(string)($_POST['recommendation']??'');
                $expected=$cat==='muinhon'?'Ôn thi HSG':'Cần bồi dưỡng';
                if ($recommendation!=='' && $recommendation!==$expected) throw new RuntimeException('Đề xuất không hợp lệ.');
                if ($recommendation!=='') {
                    $q=$db->prepare('INSERT INTO cds_student_support_members(school_year,category,subject,student_id,teacher,recommendation) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE recommendation=VALUES(recommendation)');
                    $q->execute([$year,$cat,$subject,$ids[0],$teacher,$recommendation]);
                } else {
                    $q=$db->prepare('UPDATE cds_student_support_members SET recommendation=? WHERE school_year=? AND category=? AND subject=? AND student_id=?');
                    $q->execute(['',$year,$cat,$subject,$ids[0]]);
                }
            } elseif ($action==='bulk_group') {
                if (!in_array($cat,['tn','ts'],true)) throw new RuntimeException('Chỉ danh sách ôn thi mới có nhóm TBK/TBY.');
                $group=(string)($_POST['group_name']??'');
                if(!in_array($group,['TBK','TBY'],true)) throw new RuntimeException('Hãy chọn nhóm TBK hoặc TBY.');
                $db->beginTransaction();
                $q=$db->prepare('INSERT INTO cds_student_support_members(school_year,category,subject,student_id,teacher,group_name) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE group_name=VALUES(group_name)');
                foreach($ids as $id) $q->execute([$year,$cat,$subject,$id,$teacher,$group]);
                $db->commit();
            } elseif ($action==='set_group') {
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
        header('Location: '.BASE_URL.'boiduong.php?'.http_build_query(['tab'=>$tab,'year'=>$year,'class'=>(string)($_POST['class'] ?? ''),'subject'=>$subject,'group'=>(string)($_POST['group_filter'] ?? '')])); exit;
    } catch (Throwable $ex) { if ($db->inTransaction()) $db->rollBack(); $error=$ex->getMessage(); }
    $st->execute([$year]); $settings=['tn'=>[],'ts'=>[]]; foreach ($st->fetchAll() as $r) $settings[$r['category']][]=$r['subject'];
}
$st=$db->prepare('SELECT category,subject,student_id,teacher,group_name,recommendation FROM cds_student_support_members WHERE school_year=?'); $st->execute([$year]); $members=[]; foreach ($st->fetchAll() as $r) $members[$r['category']][$r['subject']][$r['student_id']]=['teacher'=>$r['teacher'],'group'=>$r['group_name'],'recommendation'=>$r['recommendation']];
$allClasses=array_values(array_unique(array_filter(array_column($students,'class')))); usort($allClasses,'strnatcasecmp');
if(in_array($tab,['tn','ts'],true)) $allClasses=array_values(array_filter($allClasses,static function($name) use($classOptions,$examClasses,$tab) { foreach($examClasses[$tab] as $id=>$_) if(($classOptions[$id]??'')===$name) return true; return false; }));
$class=(string)($_GET['class'] ?? ''); if (!in_array($class,$allClasses,true)) $class='';
$groupFilter=(string)($_GET['group'] ?? ''); if (!in_array($tab,['tn','ts'],true) || !in_array($groupFilter,['TBK','TBY','unassigned'],true)) $groupFilter='';
$subjects = in_array($tab,['tn','ts'],true) ? $settings[$tab] : ($admin ? $available : array_values(array_filter($available, static function($s) use($allowed,$norm) { foreach($allowed as $row) if(isset($row[$norm($s)])) return true; return false; })));
$subject=(string)($_GET['subject'] ?? ''); if (!in_array($subject,$subjects,true)) $subject=$subjects[0] ?? '';
$viewStudents=array_filter($students,static function($s) use($class,$subject,$tab,$examClasses) { return (!$class || $s['class']===$class) && $subject!=='' && (!in_array($tab,['tn','ts'],true) || isset($examClasses[$tab][$s['class_id']])); });
if ($groupFilter!=='') $viewStudents=array_filter($viewStudents,static function($s,$id) use($members,$tab,$subject,$groupFilter) {
    $entry=$members[$tab][$subject][$id]??null;
    return $groupFilter==='unassigned' ? $entry!==null && !in_array($entry['group'],['TBK','TBY'],true) : $entry!==null && $entry['group']===$groupFilter;
},ARRAY_FILTER_USE_BOTH);
$sort=(string)($_GET['sort']??'priority'); if(!in_array($sort,['priority','name','class','status','group','recommendation','teacher'],true)) $sort='priority';
$direction=(string)($_GET['dir']??'asc')==='desc'?-1:1;
$nameKey=static function($name): string {
    $name=trim((string)$name); $parts=preg_split('/\s+/u',$name,-1,PREG_SPLIT_NO_EMPTY);
    $given=(string)array_pop($parts);
    $key=$given.'|'.$name;
    $key=strtr($key,['Đ'=>'D','đ'=>'d']);
    if(function_exists('iconv')) { $plain=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$key); if($plain!==false) $key=$plain; }
    return strtolower($key);
};
uksort($viewStudents,static function($a,$b) use(&$viewStudents,$members,$tab,$subject,$sort,$direction,$nameKey) {
    $x=$viewStudents[$a]; $y=$viewStudents[$b];
    $mx=$members[$tab][$subject][$a]??null; $my=$members[$tab][$subject][$b]??null;
    $nameOrder=strnatcasecmp($nameKey($x['name']),$nameKey($y['name']));
    $classOrder=strnatcasecmp($x['class'],$y['class']);
    if($sort==='priority') return ($my!==null)<=>($mx!==null) ?: $classOrder ?: $nameOrder;
    if($sort==='name') return $direction*($nameOrder ?: $classOrder);
    if($sort==='class') return $direction*($classOrder ?: $nameOrder);
    $left=$sort==='status'?($mx!==null?'1':'0'):(string)($mx[$sort]??'');
    $right=$sort==='status'?($my!==null?'1':'0'):(string)($my[$sort]??'');
    return $direction*(strnatcasecmp($left,$right) ?: $classOrder ?: $nameOrder);
});
$canEditAny=false; foreach($viewStudents as $row) if($canSelect($row,$subject,$tab)) { $canEditAny=true; break; }
$sortUrl=static function($column) use($tab,$year,$class,$subject,$groupFilter,$sort,$direction) { return BASE_URL.'boiduong.php?'.http_build_query(['tab'=>$tab,'year'=>$year,'class'=>$class,'subject'=>$subject,'group'=>$groupFilter,'sort'=>$column,'dir'=>$sort===$column&&$direction===1?'desc':'asc']); };
if (!$admin && !in_array($tab,['tn','ts'],true) && $subject !== '') $allClasses = array_values(array_filter($allClasses, static function($cl) use($norm,$allowed,$subject) { return isset($allowed[$norm($cl)][$norm($subject)]); }));
require __DIR__.'/includes/header.php';
?>
<style>
.support-bulk{display:flex;flex-wrap:wrap;align-items:stretch;gap:.5rem}
.support-bulk .btn{white-space:normal;line-height:1.2;min-height:2.75rem;max-width:10rem}
.support-bulk .form-select{width:auto;min-width:10rem;max-width:100%;flex:0 1 12rem}
@media(max-width:575px){.support-bulk .btn{flex:1 1 8rem;max-width:none}.support-bulk .form-select{flex:1 1 100%}}
</style>
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
<?php require __DIR__.'/includes/student_support_stats.php'; ?>
<?php else:?>
<?php if(in_array($tab,['tn','ts'],true) && !$examClasses[$tab]):?><div class="alert alert-info">Chưa chọn lớp ôn thi trong Cài đặt.</div><?php endif;?>
<?php if(in_array($tab,['tn','ts'],true) && !$subjects):?><div class="alert alert-info">Chưa cài đặt môn thi. Quản trị chọn môn trong tab Cài đặt.</div><?php endif;?>
<form method="get" action="<?=BASE_URL?>boiduong.php" class="row g-2 mb-3 align-items-end"><input type="hidden" name="tab" value="<?=e($tab)?>"><div class="col-6 col-lg-2"><label class="form-label">Năm học</label><input class="form-control" name="year" value="<?=e($year)?>" pattern="[0-9]{4}-[0-9]{4}"></div><div class="col-6 col-lg-3"><label class="form-label">Môn</label><select class="form-select" name="subject"><?php foreach($subjects as $s):?><option value="<?=e($s)?>" <?=$s===$subject?'selected':''?>><?=e($s)?></option><?php endforeach;?></select></div><div class="col-6 col-lg-2"><label class="form-label">Lớp</label><select class="form-select" name="class"><option value="">Tất cả lớp</option><?php foreach($allClasses as $cl):?><option value="<?=e($cl)?>" <?=$class===$cl?'selected':''?>><?=e($cl)?></option><?php endforeach;?></select></div><?php if(in_array($tab,['tn','ts'],true)):?><div class="col-6 col-lg-3"><label class="form-label">Nhóm ôn thi</label><select class="form-select" name="group"><option value="">Tất cả nhóm</option><option value="TBK" <?=$groupFilter==='TBK'?'selected':''?>>TBK</option><option value="TBY" <?=$groupFilter==='TBY'?'selected':''?>>TBY</option><option value="unassigned" <?=$groupFilter==='unassigned'?'selected':''?>>Đã chọn, chưa xếp nhóm</option></select></div><?php endif;?><div class="col-12 col-lg-2"><button class="btn btn-primary w-100">Lọc danh sách</button></div></form>
<div class="card"><div class="card-body"><h5><?=e($tabs[$tab])?> · <?=e($subject)?> <span class="badge text-bg-primary"><?=count(array_intersect_key($viewStudents,$members[$tab][$subject]??[]))?> đã chọn / <?=count($viewStudents)?> đang xem</span></h5><p class="text-muted small"><?=in_array($tab,['tn','ts'],true)?'Quản trị, GVCN lớp và giáo viên được phân công dạy ôn thi được chọn học sinh và xếp nhóm TBK/TBY.':'Giáo viên đánh dấu học sinh ở lớp và môn mình dạy.'?></p><?php if($subject!=='' && $canEditAny):?>
<form id="supportBulk" method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>" class="support-bulk mb-3">
<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="class" value="<?=e($class)?>"><input type="hidden" name="group_filter" value="<?=e($groupFilter)?>">
<button class="btn btn-sm btn-outline-primary" name="action" value="bulk_add">Thêm học sinh<br>đã tích</button><button class="btn btn-sm btn-outline-danger" name="action" value="bulk_remove" onclick="return confirm('Bỏ các học sinh đã tích khỏi nhóm này?')">Bỏ học sinh<br>đã tích</button>
<?php if(in_array($tab,['tn','ts'],true)):?><select name="group_name" class="form-select form-select-sm" aria-label="Chọn nhóm cho học sinh đã tích"><option value="">Chọn nhóm TBK/TBY</option><option value="TBK">TBK</option><option value="TBY">TBY</option></select><button class="btn btn-sm btn-primary" name="action" value="bulk_group" onclick="if(!this.form.group_name.value){alert('Hãy chọn TBK hoặc TBY.');return false}if(!document.querySelector('.support-check:checked')){alert('Hãy tích học sinh cần gán nhóm.');return false}">Gán nhóm cho<br>học sinh đã tích</button><?php endif;?>
<button class="btn btn-sm btn-outline-secondary" type="button" onclick="document.querySelectorAll('.support-check').forEach(c=>c.checked=true)">Tích tất cả<br>đang xem</button>
<button class="btn btn-sm btn-outline-secondary" type="button" onclick="document.querySelectorAll('.support-check').forEach(c=>c.checked=false)">Bỏ tích</button>
</form><?php endif;?>
<div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Chọn</th><th>STT</th><th><a href="<?=e($sortUrl('name'))?>">Học sinh ↕</a></th><th><a href="<?=e($sortUrl('class'))?>">Lớp ↕</a></th><th><a href="<?=e($sortUrl('status'))?>">Tham gia ↕</a></th><?php if(in_array($tab,['tn','ts'],true)):?><th><a href="<?=e($sortUrl('group'))?>">Lớp/nhóm TBK, TBY ↕</a></th><?php endif;?><?php if(in_array($tab,['muinhon','chuadat'],true)):?><th><a href="<?=e($sortUrl('recommendation'))?>">Đề xuất ↕</a></th><?php endif;?><th><a href="<?=e($sortUrl('teacher'))?>">Người chọn ↕</a></th></tr></thead><tbody><?php $i=0;foreach($viewStudents as $id=>$s):$selected=isset($members[$tab][$subject][$id]);$canEdit=$subject!==''&&$canSelect($s,$subject,$tab);?><tr><td><?php if($canEdit):?><input class="form-check-input support-check" type="checkbox" name="student_ids[]" form="supportBulk" value="<?=e($id)?>" aria-label="Chọn <?=e($s['name'])?>"><?php endif;?></td><td><?=++$i?></td><td><?=e($s['name'])?></td><td><?=e($s['class'])?></td><td><?php if($canEdit):?><form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="member"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="student_id" value="<?=e($id)?>"><input type="hidden" name="class" value="<?=e($class)?>"><input type="hidden" name="group_filter" value="<?=e($groupFilter)?>"><input type="hidden" name="selected" value="<?=$selected?'0':'1'?>"><button class="btn btn-sm <?=$selected?'btn-success':'btn-outline-secondary'?>"><?=$selected?'Đã chọn':'Chọn'?></button></form><?php else:?><?=$selected?'Có':'—'?><?php endif;?></td>
<?php if(in_array($tab,['tn','ts'],true)):?><td><?php if($selected && $canEdit):?><form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>" class="d-flex gap-1"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="set_group"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="student_id" value="<?=e($id)?>"><input type="hidden" name="class" value="<?=e($class)?>"><input type="hidden" name="group_filter" value="<?=e($groupFilter)?>"><select name="group_name" class="form-select form-select-sm" aria-label="Nhóm ôn thi của <?=e($s['name'])?>" onchange="this.form.requestSubmit()"><option value="" disabled>Chọn nhóm</option><?php foreach(['TBK','TBY'] as $gr):?><option value="<?=$gr?>" <?=($members[$tab][$subject][$id]['group']??'')===$gr?'selected':''?>><?=$gr?></option><?php endforeach;?></select><noscript><button class="btn btn-sm btn-primary">Lưu</button></noscript></form><?php else:?><?=e($selected?($members[$tab][$subject][$id]['group']?:'Chưa xếp'):'—')?><?php endif;?></td><?php endif;?><?php if(in_array($tab,['muinhon','chuadat'],true)):?><td><?php $proposal=$tab==='muinhon'?'Ôn thi HSG':'Cần bồi dưỡng';if($canEdit):?><form method="post" action="<?=BASE_URL?>boiduong.php?<?=e(http_build_query(['tab'=>$tab,'year'=>$year]))?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="set_recommendation"><input type="hidden" name="category" value="<?=e($tab)?>"><input type="hidden" name="subject" value="<?=e($subject)?>"><input type="hidden" name="student_id" value="<?=e($id)?>"><input type="hidden" name="class" value="<?=e($class)?>"><input type="hidden" name="group_filter" value="<?=e($groupFilter)?>"><select class="form-select form-select-sm" name="recommendation" aria-label="Đề xuất cho <?=e($s['name'])?>" onchange="this.form.requestSubmit()"><option value="" <?=empty($members[$tab][$subject][$id]['recommendation'])?'selected':''?>>Chưa đề xuất</option><option value="<?=e($proposal)?>" <?=($members[$tab][$subject][$id]['recommendation']??'')===$proposal?'selected':''?>><?=e($proposal)?></option></select><noscript><button class="btn btn-sm btn-primary">Lưu</button></noscript></form><?php else:?><?=e($members[$tab][$subject][$id]['recommendation']??'—')?><?php endif;?></td><?php endif;?><td><?=e($selected?$members[$tab][$subject][$id]['teacher']:'')?></td></tr><?php endforeach;if(!$i):?><tr><td colspan="7" class="text-muted">Không có học sinh phù hợp.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php endif;?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
