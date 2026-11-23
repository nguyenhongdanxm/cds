<?php
$dir=$argv[1];mkdir($dir.'/includes');$root=dirname(__DIR__);copy($root.'/chuyenmon/phieudugio.php',$dir.'/phieudugio.php');copy($root.'/chuyenmon/includes/observation_form.php',$dir.'/includes/observation_form.php');
file_put_contents($dir.'/includes/header.php','<!doctype html><html lang="vi"><meta charset="utf-8"><style>*{box-sizing:border-box}body{margin:0;background:#eee}table{border-collapse:collapse}.text-end{text-align:right}.container{padding:20px}</style><body><main class="container">');
file_put_contents($dir.'/includes/footer.php','</main></body></html>');
file_put_contents($dir.'/includes/functions.php', <<<'STUB'
<?php
session_start();define('BASE_URL','/');define('DATA_PATH',__DIR__);
function require_login(){}function cds_user(){return ['role'=>'teacher','teacher_name'=>'Nguyễn Thị Thu Hương'];}function cds_user_has_group($u,$g){return false;}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function cds_form_department(){return 'Sở Giáo dục và Đào tạo Tuyên Quang';}function cds_form_school_name(){return 'Trường PTDTNT THCS và THPT Xín Mần';}function cds_form_place(){return 'Xín Mần';}
function cds_drive_csrf_token(){return 'fixture';}function cds_drive_page_action(){return 'fixture';}
function load_json($f,$d=[]){$name='Nguyễn Thị Thu Hương';$lesson=isset($_GET['long'])?str_repeat('Vận dụng kiến thức gắn với thực tiễn địa phương và phát triển năng lực học sinh. ',4):'Trách nhiệm với gia đình';return [['id'=>'fixture','teacher'=>'Trần Thị Hồng Loan','observers'=>[$name],'lesson_title'=>$lesson,'subject'=>'Hoạt động trải nghiệm, hướng nghiệp','class'=>'12A','timetable_period'=>3,'date'=>'2026-11-23','evaluations'=>[sha1(mb_strtolower($name,'UTF-8'))=>['completed'=>true,'scores'=>[1,2,1,2,2,1,2,2,2,2,2,1],'total'=>20,'rating'=>'Giỏi']]]];}
STUB);
