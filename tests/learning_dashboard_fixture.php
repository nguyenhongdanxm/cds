<?php
// Render the actual controller/templates with isolated synthetic auth/data/SQL collaborators.
$dir=$argv[1]??'';if(!is_dir($dir))throw new RuntimeException('Supply a temporary directory');mkdir($dir.'/includes');mkdir($dir.'/data');mkdir($dir.'/assets');
$root=dirname(__DIR__);copy($root.'/hoclieu.php',$dir.'/hoclieu.php');copy($root.'/hoclieu_file.php',$dir.'/hoclieu_file.php');foreach(['learning_dashboard.php','learning_dashboard_view.php','learning_game_catalog.php','learning_article.php','learning_content.php','learning_content_preview.php','learning_thumbnail.php']as$f)copy($root.'/includes/'.$f,$dir.'/includes/'.$f);
foreach(['learning-thumbnail.css','page-navigation.css','page-navigation.js','learning-content.js','learning-dashboard.css','learning-article.js','hoclieu-games.css']as$f)copy($root.'/assets/'.$f,$dir.'/assets/'.$f);
mkdir($dir.'/assets/game-covers');symlink($root.'/assets/game-covers/illustrated-v3',$dir.'/assets/game-covers/illustrated-v3');
file_put_contents($dir.'/includes/module_switcher.php','<?php');file_put_contents($dir.'/includes/game_credit.php','<?php');
file_put_contents($dir.'/includes/quiz_paper_store.php', <<<'STUB'
<?php
function qp_game_visibility(){if(($_COOKIE['visibility_failure']??'')==='1')throw new RuntimeException('Synthetic database unavailable');return ['secret'=>0,'quiz'=>0];}
STUB);
file_put_contents($dir.'/includes/auth.php', <<<'STUB'
<?php
require_once '__PAGE_NAVIGATION__';
session_start();define('BASE_URL','/');define('DATA_PATH',dirname(__DIR__).'/data');define('SCHOOL_NAME','Trường thử nghiệm');
function require_login(){}
function current_user(){return ['id'=>'teacher1','username'=>'teacher1','name'=>'Giáo viên A','role'=>$_COOKIE['test_role']??'teacher'];}
function cds_is_viewer($u){return $u['role']==='viewer';}
function can_perm_level($code,$level){if($level!=='view')return false;$role=current_user()['role'];return match($role){'none'=>false,'exam_only'=>$code==='hl.kiemtra','resource_only'=>$code==='hl.xem',default=>in_array($code,['hl.xem','hl.kiemtra','hl.lienket'],true)};}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function show_flash(){}
function save_json($f,$d){throw new RuntimeException('Unexpected write');}
function load_json($f,$default=[]){if(($_COOKIE['empty_data']??'')==='1')return ['items'=>[],'links'=>[]];$items=[];for($i=0;$i<8;$i++)$items[]=['id'=>'resource'.$i,'section'=>'resource','title'=>$i===0?'Bài giảng Toán học':($i===7?'<img src=x onerror=alert(1)>':'Tài liệu tham khảo với tiêu đề dài '.$i.' '.str_repeat('Nội dung ',8)),'kind'=>'Bài viết','grade'=>'10','author'=>'Giáo viên A','author_id'=>'other','approved'=>true,'featured'=>$i===0,'source_kind'=>'article','created_at'=>'2026-11-'.sprintf('%02d',$i+1)];
$items[]=['id'=>'ownpending','section'=>'resource','title'=>'Bài soạn của tôi chờ duyệt','author_id'=>'teacher1','approved'=>false,'kind'=>'Văn bản'];$items[]=['id'=>'privatepending','section'=>'resource','title'=>'Bản nháp riêng của người khác','author_id'=>'other','approved'=>false];
for($i=0;$i<3;$i++)$items[]=['id'=>'exam'.$i,'section'=>'exam','title'=>'Đề kiểm tra học kỳ '.$i,'kind'=>'Đề kiểm tra','grade'=>'12','approved'=>true,'author'=>'Tổ chuyên môn','source_kind'=>'link','url'=>'https://school.example/exam/'.$i];
$items[]=['id'=>'examprivate','section'=>'exam','title'=>'Đề chưa duyệt riêng','author_id'=>'other','approved'=>false];
return ['items'=>$items,'links'=>[['title'=>'Học liệu OLM','icon'=>'bi-link-45deg','url'=>'https://olm.vn'],['title'=>'Liên kết thử','url'=>'javascript:alert(1)']]];}
STUB);

$auth=file_get_contents($dir.'/includes/auth.php');file_put_contents($dir.'/includes/auth.php',str_replace('__PAGE_NAVIGATION__',str_replace("'","\\'",$root.'/includes/page_navigation.php'),$auth));
