<?php
require __DIR__.'/learning_dashboard_fixture.php';
$items=[
 ['id'=>'legacyarticle','section'=>'resource','title'=>'Video gắn bài viết cũ','kind'=>'Video','source_kind'=>'article','article_body'=>'<p>Nội dung cũ cần giữ nguyên</p>','grade'=>'10','author'=>'Giáo viên A','author_id'=>'teacher1','approved'=>true],
 ['id'=>'legacyyoutube','section'=>'resource','title'=>'Video YouTube cũ','kind'=>'Video','source_kind'=>'link','url'=>'https://youtu.be/M7lc1UVf-VE','grade'=>'10','author_id'=>'teacher1','approved'=>true],
 ['id'=>'privateitem','section'=>'resource','title'=>'Bản chưa duyệt người khác','kind'=>'Video','source_kind'=>'youtube','url'=>'https://youtu.be/M7lc1UVf-VE','author_id'=>'other','approved'=>false],
 ['id'=>'examitem','section'=>'exam','title'=>'Đề kiểm tra','kind'=>'Đề kiểm tra','source_kind'=>'link','url'=>'https://exam.example/paper','author_id'=>'other','approved'=>true],
 ['id'=>'uploadpdf','section'=>'resource','title'=>'Văn bản PDF','kind'=>'Văn bản','source_kind'=>'upload','file_path'=>'gdrive:fixturepdf123','author_id'=>'teacher1','approved'=>true],
];file_put_contents($dir.'/data/learning_hub.json',json_encode(['items'=>$items,'links'=>[]]));
file_put_contents($dir.'/includes/auth.php', <<<'STUB'
<?php
session_start();define('BASE_URL','/');define('DATA_PATH',dirname(__DIR__).'/data');define('SCHOOL_NAME','Trường thử nghiệm');
function require_login(){if(($_COOKIE['test_role']??'')==='anonymous'){http_response_code(401);exit('Login required');}}
function current_user(){return ['id'=>'teacher1','username'=>'teacher1','name'=>'Giáo viên A','role'=>$_COOKIE['test_role']??'teacher'];}
function cds_is_viewer($u=null){return ($u??current_user())['role']==='viewer';}
function can_perm_level($code,$level){$role=current_user()['role'];if($role==='none')return false;if($role==='exam_only')return $code==='hl.kiemtra'&&$level==='view';if($role==='teacher')return in_array($code,['hl.xem','hl.kiemtra','hl.lienket'],true)&&in_array($level,['view','edit'],true);return $level==='view'&&in_array($code,['hl.xem','hl.kiemtra','hl.lienket'],true);}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function load_json($f,$default=[]){return is_file($f)?json_decode(file_get_contents($f),true):$default;}
function save_json($f,$data){file_put_contents($f,json_encode($data));return true;}
function flash($message,$kind='success'){$_SESSION['test_flash']=$message;}
function show_flash(){if(isset($_SESSION['test_flash'])){echo '<p role="alert">'.e($_SESSION['test_flash']).'</p>';unset($_SESSION['test_flash']);}}
function cds_drive_upload_bytes($bytes,$name,$mime,$type,$meta){$id='fixturefile'.bin2hex(random_bytes(5));file_put_contents(DATA_PATH.'/file_'.$id.'.json',json_encode(['name'=>$name,'mime'=>$mime,'body'=>base64_encode($bytes)]));return ['ok'=>true,'path'=>'gdrive:'.$id];}
function cds_drive_download($id){$data=load_json(DATA_PATH.'/file_'.$id.'.json');return $data?['ok'=>true,'body'=>base64_decode($data['body']),'name'=>$data['name'],'mime'=>$data['mime']]:['ok'=>false,'status'=>404];}
STUB);
