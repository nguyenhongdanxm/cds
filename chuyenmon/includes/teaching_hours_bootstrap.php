<?php
require_once __DIR__.'/functions.php';require_once __DIR__.'/lesson_book_store.php';require_once __DIR__.'/teaching_hours.php';
require_login();
$thManage=lb_is_management()&&cds_can_feature('cm.pccm','edit')&&(!function_exists('cds_is_viewer')||!cds_is_viewer(lb_user()));
$thCatalog=th_catalog(lb_stat_staff_rows());$thMe=null;$thUser=lb_user();
foreach($thCatalog['staff']as$people)if(count($people)===1){$p=$people[0];if(!empty($thUser['teacher_id'])&&in_array((string)$thUser['teacher_id'],[$p['id'],$p['code']],true))$thMe=$p;}
if(!$thMe&&empty($thUser['teacher_id']))$thMe=th_person((string)($thUser['teacher_name']??''),$thCatalog);
$thVisible=static function($person,$name)use($thMe):bool{
 if(lb_is_management()||((lb_user()['role']??'')==='viewer'))return true;
 if(!$person)return false;if(lb_is_leader()){$team=lb_stat_leader_group();return $team!==''&&th_key($team)===th_key($person['group']);}
 return $thMe&&$thMe['key']===$person['key'];
};
$now=new DateTimeImmutable('now');$year=(int)$now->format('Y')-((int)$now->format('n')<7?1:0);
$thFrom=(string)($_GET['from']??$year.'-07-01');$thTo=(string)($_GET['to']??($year+1).'-06-30');
th_dates($thFrom,$thTo);$thPerson=(string)($_GET['teacher']??'');$thCategory=(string)($_GET['category']??'');if($thCategory!==''&&!isset(th_categories()[$thCategory]))$thCategory='';
$thFile=DATA_PATH.'/teaching_hours.php';$thState=th_read($thFile);if(empty($_SESSION['teaching_hours_csrf']))$_SESSION['teaching_hours_csrf']=bin2hex(random_bytes(24));$thCsrf=$_SESSION['teaching_hours_csrf'];$thMessage='';$thError='';
$thSourceVersion=(string)($_REQUEST['source_version']??get_active_version_id());if($thSourceVersion!==''&&!get_version($thSourceVersion))throw new RuntimeException('Phiên bản phân công không tồn tại.');
$thSourceRoles=$thSourceVersion!==''?(array)load_json(role_assignments_file($thSourceVersion),[]):[];
$thSourceHash=hash('sha256',json_encode($thSourceRoles));$thRates=[];foreach(get_roles()as$r)$thRates[$r['name']]=(float)($r['periods']??0);
$thReadRecords=function()use($thFrom,$thTo){
 if(cds_lb_read_effective()){try{return cds_lb_sql_range($thFrom,$thTo);}catch(Throwable $error){error_log('[CDS hours SQL fallback] '.$error->getMessage());$GLOBALS['th_source_warning']='Không đọc được nguồn MySQL của sổ đầu bài; đang dùng dữ liệu JSON dự phòng. Cần đối chiếu đồng bộ trước khi chốt bảng kê.';return (array)load_json(LB_RECORDS_FILE,[]);}}
 return lb_rows(LB_RECORDS_FILE);
};
$thNeedActual=basename($_SERVER['PHP_SELF']??'')!=='gio_day.php'||($_GET['tab']??'actual')!=='roles'||($_POST['action']??'')==='hours_category';
$thRows=$thNeedActual?$thReadRecords():[];$thAll=th_signed_rows($thRows,$thFrom,$thTo,$thState['categories'],$thCatalog,$thVisible);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  if(!$thManage)throw new RuntimeException('Chỉ người quản lý chuyên môn có quyền cập nhật dữ liệu tổng hợp.');
  if(!hash_equals($thCsrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Phiên thao tác hết hạn. Hãy tải lại trang.');
  $revision=filter_var($_POST['revision']??'',FILTER_VALIDATE_INT);if($revision===false)throw new RuntimeException('Thiếu phiên bản dữ liệu.');$action=(string)($_POST['action']??'');
  if($action==='hours_category'){
   $cat=(string)($_POST['category_value']??'');if(!in_array($cat,['tn','vao10','hsg','on'],true))throw new RuntimeException('Chọn loại ôn.');
   $ids=array_values(array_unique(array_filter((array)($_POST['record_ids']??[]),'is_string')));if(!$ids||count($ids)>1000)throw new RuntimeException('Chọn từ 1 đến 1.000 tiết đã ký.');$allowed=array_column($thAll['rows'],null,'report_id');foreach($ids as$id)if(!isset($allowed[$id]))throw new RuntimeException('Có tiết nằm ngoài phạm vi đang xem.');elseif(!th_on_subject((string)($allowed[$id]['subject']??'')))throw new RuntimeException('Chỉ gán loại ôn cho tiết có chữ Ôn trong tên môn.');
   th_write($thFile,$revision,function($data)use($ids,$cat){foreach($ids as$id)$data['categories'][$id]=$cat;return $data;},lb_teacher_name());
  }elseif($action==='hours_role_add'){
   $new=th_role_validate($_POST,$thCatalog);th_write($thFile,$revision,function($data)use($new){$data['roles']=th_role_add($data['roles'],$new);return $data;},lb_teacher_name());
  }elseif($action==='hours_role_source'){
   if(!hash_equals($thSourceHash,(string)($_POST['source_hash']??'')))throw new RuntimeException('Phân công gốc vừa thay đổi. Hãy tải lại trang.');
   $weeks=(string)($_POST['weeks']??'');$selected=(array)($_POST['source_ids']??[]);if(!$selected)throw new RuntimeException('Chọn nhiệm vụ cần lấy sang.');$new=[];
   foreach($selected as$i){if(!ctype_digit((string)$i)||!isset($thSourceRoles[(int)$i]))throw new RuntimeException('Nhiệm vụ không hợp lệ.');$row=$thSourceRoles[(int)$i];$p=th_person((string)($row['teacher']??''),$thCatalog);if(!$p)throw new RuntimeException('Giáo viên chưa có mã duy nhất trong CSDL.');
    $new[]=th_role_validate(['person_key'=>$p['key'],'role'=>$row['role']??'','scope'=>$row['class']??$row['class_name']??'','rate'=>$row['periods']??$thRates[$row['role']??'']??0,'weeks'=>$weeks,'from'=>$thFrom,'to'=>$thTo,'note'=>'Lấy từ phiên bản '.$thSourceVersion],$thCatalog);
   }
   th_write($thFile,$revision,function($data)use($new){foreach($new as$row)$data['roles']=th_role_add($data['roles'],$row);return $data;},lb_teacher_name());
  }elseif($action==='hours_role_delete'){
   $id=(string)($_POST['id']??'');$found=false;foreach($thState['roles']as$r)if($r['id']===$id)$found=true;if(!$found)throw new RuntimeException('Dòng kiêm nhiệm đã thay đổi.');
   th_write($thFile,$revision,function($data)use($id){$data['roles']=array_values(array_filter($data['roles'],fn($r)=>$r['id']!==$id));return $data;},lb_teacher_name());
  }else throw new RuntimeException('Thao tác không hợp lệ.');
  header('Location: '.BASE_URL.'gio_day.php?'.http_build_query(['tab'=>$_GET['tab']??'actual','from'=>$thFrom,'to'=>$thTo,'teacher'=>$thPerson,'category'=>$thCategory]));exit;
 }catch(Throwable $error){$thError=$error->getMessage();}
}
$thAllRoles=th_roles_range($thState['roles'],$thFrom,$thTo,$thVisible,$thCatalog);
$thActual=array_values(array_filter($thAll['rows'],fn($r)=>($thPerson===''||$r['person']['key']===$thPerson)&&($thCategory===''||$r['category']===$thCategory)));
$thRoles=array_values(array_filter($thAllRoles['rows'],fn($r)=>$thPerson===''||$r['person_key']===$thPerson));
$thActualTotals=th_totals($thActual,[]);$thTotals=th_totals($thActual,$thRoles);$thWarnings=array_merge($thAll['warnings'],$thAllRoles['warnings']);$thNotices=$thAll['notices']??[];if(isset($GLOBALS['th_source_warning']))$thWarnings[]=$GLOBALS['th_source_warning'];
$thPeople=[];foreach($thCatalog['staff']as$people)if(count($people)===1&&$thVisible($people[0],$people[0]['name']))$thPeople[$people[0]['key']]=$people[0];
$thQuery=['from'=>$thFrom,'to'=>$thTo,'teacher'=>$thPerson,'category'=>$thCategory];

foreach($thAll['rows']as$row)if(!empty($row['person']['unresolved']))$thPeople[$row['person']['key']]=$row['person'];
