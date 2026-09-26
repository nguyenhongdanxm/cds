<?php
if (!defined('CDS_HOMEROOM_STANDALONE') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: /tienich_chunhiem.php'.(empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING']), true, 302);
    exit;
}
$page_title='Tiện ích chủ nhiệm';
require_once __DIR__.'/includes/functions.php';
require_once dirname(__DIR__).'/includes/database.php';
require_once __DIR__.'/includes/classroom_home_store.php';
require_login();
date_default_timezone_set('Asia/Ho_Chi_Minh');
$user=cds_user()??[];$admin=($user['role']??'')==='admin';
$teacherId=(string)($user['teacher_id']??'');$teacherName=trim((string)($user['teacher_name']??$user['name']??''));
$normal=static function($v){$v=trim((string)$v);return function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);};
try {
    $db=cds_db();
    cmhome_install($db);
    $classes=cmhome_rows($db,"SELECT c.id,c.name,c.school_year_id,c.homeroom_teacher_id,COALESCE(NULLIF(y.label,''),c.school_year_id) AS school_year,t.name AS homeroom_name FROM cds_classes c LEFT JOIN cds_school_years y ON y.id=c.school_year_id LEFT JOIN cds_teachers t ON t.id=c.homeroom_teacher_id WHERE c.active=1 ORDER BY school_year DESC,c.name");
} catch(Throwable $ex){http_response_code(503);exit('Không thể mở dữ liệu lớp chủ nhiệm. Vui lòng kiểm tra MySQL.');}
$myClasses=[];
foreach($classes as $c){
    $allowed=$admin||($teacherId!==''&&$teacherId===(string)$c['homeroom_teacher_id'])||($teacherName!==''&&$normal($teacherName)===$normal($c['homeroom_name']));
    if(!$allowed)foreach((array)($user['homeroom_classes']??[]) as $h){$v=is_array($h)?(string)($h['id']??$h['name']??''):(string)$h;if($v===(string)$c['id']||$normal($v)===$normal($c['name'])){$allowed=true;break;}}
    if($allowed)$myClasses[(string)$c['id']]=$c;
}
$year=(string)($_GET['year']??(function_exists('cds_current_school_year')?cds_current_school_year():date('Y').'-'.(date('Y')+1)));
$yearOptions=[];foreach($myClasses as $c)if(trim((string)$c['school_year'])!=='')$yearOptions[$c['school_year']]=true;
if(!isset($yearOptions[$year])&&$yearOptions)$year=(string)array_key_first($yearOptions);
$visibleClasses=array_filter($myClasses,static fn($c)=>$c['school_year']===$year);
$classId=(string)($_GET['class_id']??$_POST['class_id']??'');
if($_SERVER['REQUEST_METHOD']==='POST' && (!isset($visibleClasses[$classId]) || (string)($_POST['class_id']??'')!==$classId)) { http_response_code(403); exit('Không có quyền cập nhật lớp này.'); }
if(!isset($visibleClasses[$classId]))$classId=(string)(array_key_first($visibleClasses)??'');
$class=$visibleClasses[$classId]??null;
$tabs=['overview'=>'Tổng quan','organization'=>'Tổ chức lớp','notes'=>'Cần lưu ý','health'=>'Sức khỏe','meals'=>'Chế độ & thu chi','points'=>'Thi đua','plans'=>'Kế hoạch'];
$tab=(string)($_GET['tab']??'overview');if(!isset($tabs[$tab]))$tab='overview';
$students=$class?cmhome_rows($db,'SELECT id,code,cccd,name,gender,dob,ethnicity,hometown,address,parent_name,parent_phone,is_boarder,raw_json FROM cds_students WHERE class_id=? AND active=1 ORDER BY name',[$classId]):[];
// CSDL có thể đang đọc JSON khi bản sao MySQL chưa khớp. Dùng cùng nguồn
// hiển thị của CSDL, giữ MySQL làm dự phòng khi nguồn đó chưa sẵn sàng.
if($class){
    require_once dirname(__DIR__).'/includes/database_sql_read.php';
    $sourceFile=dirname(__DIR__).'/data/students.json';
    $decoded=is_file($sourceFile)?json_decode((string)file_get_contents($sourceFile),true):null;
    $jsonRows=is_array($decoded)?$decoded:[];
    $sourceRows=is_file(dirname(__DIR__).'/data/mysql_shadow_pending.json')?null:cds_core_sql_rows('students');
    if(!is_array($sourceRows))$sourceRows=$jsonRows;
    if($sourceRows){
        $canonical=[];
        foreach($sourceRows as $row){
            if(!is_array($row)||(string)($row['class_id']??'')!==$classId||array_key_exists('active',$row)&&!$row['active'])continue;
            $sid=(string)($row['id']??'');if($sid==='')continue;
            $canonical[$sid]=[
                'id'=>$sid,'code'=>(string)($row['code']??''),'cccd'=>(string)($row['cccd']??''),'name'=>(string)($row['name']??''),'gender'=>(string)($row['gender']??''),
                'dob'=>(string)($row['dob']??''),'ethnicity'=>(string)($row['ethnicity']??''),
                'hometown'=>(string)($row['hometown']??''),'address'=>(string)($row['address']??''),
                'parent_name'=>(string)($row['parent_name']??''),'parent_phone'=>(string)($row['parent_phone']??''),
                'is_boarder'=>!empty($row['boarder'])||!empty($row['is_boarder'])?1:0,
                'raw_json'=>json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
            ];
        }
        if($canonical){$students=array_values($canonical);usort($students,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));}
    }
    // Khi bản sao SQL thiếu ngày sinh, bù từ hồ sơ JSON theo ID hoặc mã định
    // danh duy nhất. Không ghi ngược hay thay đổi ngày đã có.
    $sourceById=[];$sourceByCode=[];$duplicateCodes=[];
    foreach($jsonRows as $row){
        if(!is_array($row))continue;
        $id=(string)($row['id']??'');if($id!=='')$sourceById[$id]=$row;
        foreach(['code','cccd'] as $field){$code=trim((string)($row[$field]??''));if($code==='')continue;$key=$field.':'.$code;if(isset($sourceByCode[$key]))$duplicateCodes[$key]=true;else $sourceByCode[$key]=$row;}
    }
    foreach($students as &$student){
        if(cmhome_dob($student['dob']??'')!=='')continue;
        $idMatched=isset($sourceById[(string)$student['id']]);
        $row=$sourceById[(string)$student['id']]??null;
        if(!$row)foreach(['code','cccd'] as $field){$code=trim((string)($student[$field]??''));$key=$field.':'.$code;if($code!==''&&!isset($duplicateCodes[$key])&&isset($sourceByCode[$key])){$row=$sourceByCode[$key];break;}}
        if($row&&($idMatched||(string)($row['class_id']??'')===$classId || trim((string)($row['class_name']??''))===trim((string)$class['name']))){
            $dob=cmhome_dob($row['dob']??$row['ngay_sinh']??'');
            if($dob!=='')$student['dob']=$dob;
        }
    }
    unset($student);
}
$studentMap=[];foreach($students as $s)$studentMap[(string)$s['id']]=$s;
$familyProfiles=[];
if($class)foreach(cmhome_rows($db,'SELECT * FROM cmhome_family_profiles WHERE class_id=? AND school_year=?',[$classId,$year]) as $row)$familyProfiles[(string)$row['student_id']]=$row;
$summary=['gender'=>[],'ethnicity'=>[],'commune'=>[],'age'=>[],'father_job'=>[],'mother_job'=>[]];
$missingDob=[];
foreach($students as &$s){
    $profile=$familyProfiles[(string)$s['id']]??[];
    $raw=json_decode((string)($s['raw_json']??''),true);$raw=is_array($raw)?$raw:[];
    $s['dob']=cmhome_dob($s['dob']??'');
    if($s['dob']==='')foreach(['dob','date_of_birth','ngay_sinh'] as $key){$s['dob']=cmhome_dob($raw[$key]??'');if($s['dob']!=='')break;}
    $s['commune']=trim((string)($profile['commune']??''));
    if($s['commune']==='')$s['commune']=cmhome_commune((string)($s['address']??'').' ; '.(string)($s['hometown']??''));
    foreach(['father_job','mother_job'] as $field)$s[$field]=trim((string)($profile[$field]??($raw[$field]??'')));
    foreach(['gender','ethnicity','commune','father_job','mother_job'] as $field){$key=trim((string)($s[$field]??''));$key=$key!==''?$key:'Chưa có dữ liệu';$summary[$field][$key]=($summary[$field][$key]??0)+1;}
    $dob=(string)($s['dob']??'');
    if(cmhome_date($dob)){$age=(new DateTimeImmutable(date('Y-m-d')))->diff(new DateTimeImmutable($dob))->y;$key=(string)$age.' tuổi';$summary['age'][$key]=($summary['age'][$key]??0)+1;}
    else {$missingDob[]=$s['name'];$summary['age']['Chưa có ngày sinh']=($summary['age']['Chưa có ngày sinh']??0)+1;}
}
unset($s);
foreach($summary as &$groups){arsort($groups,SORT_NUMERIC);}unset($groups);
$weekStart=date('Y-m-d',strtotime('monday this week'));
$weekEnd=date('Y-m-d',strtotime($weekStart.' +6 days'));
$birthdays=[];
foreach($students as $s){
    $birthday=substr((string)($s['dob']??''),5,5);
    if(!preg_match('/^\d{2}-\d{2}$/',$birthday))continue;
    for($offset=0;$offset<7;$offset++){
        $date=date('Y-m-d',strtotime($weekStart.' +'.$offset.' days'));
        if(substr($date,5,5)===$birthday){$birthdays[]=['student'=>$s,'date'=>$date];break;}
    }
}
usort($birthdays,static fn($a,$b)=>strcmp($a['date'],$b['date']));
if(empty($_SESSION['cmhome_csrf']))$_SESSION['cmhome_csrf']=bin2hex(random_bytes(24));$csrf=$_SESSION['cmhome_csrf'];
$month=(string)($_GET['month']??date('Y-m'));if(!cmhome_month($month))$month=date('Y-m');
$today=date('Y-m-d');$scoreDate=(string)($_GET['score_date']??$today);if(!cmhome_date($scoreDate))$scoreDate=$today;
$period=(string)($_GET['period']??'week');if(!in_array($period,['week','month'],true))$period='week';
$base='/tienich_chunhiem.php';
$url=static function(array $extra=[])use($base,$year,$classId,$tab,$month,$scoreDate,$period){return $base.'?'.http_build_query(array_merge(['year'=>$year,'class_id'=>$classId,'tab'=>$tab,'month'=>$month,'score_date'=>$scoreDate,'period'=>$period],$extra));};
$error='';
$criteria=['help'=>['🤝','Giúp đỡ bạn',2],'study'=>['📚','Chuẩn bị bài',1],'duty'=>['✨','Trực nhật tốt',1],'late'=>['⏰','Đi muộn',-1],'missing'=>['📝','Chưa chuẩn bị bài',-1],'conduct'=>['⚠️','Vi phạm nội quy',-2]];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))){http_response_code(403);exit('Phiên làm việc không hợp lệ.');}
    if(!$class){http_response_code(403);exit('Không có quyền với lớp này.');}
    $action=(string)($_POST['action']??'');$studentId=(string)($_POST['student_id']??'');
    try {
        if(in_array($action,['role_add','role_remove','note_save','point_add','review_save','refund_add','family_save'],true)&&!isset($studentMap[$studentId]))throw new RuntimeException('Học sinh không thuộc lớp đang quản lý.');
        if($action==='role_add'){
            $role=trim((string)($_POST['role_name']??''));if(!in_array($role,['Lớp trưởng','Lớp phó học tập','Lớp phó đời sống','Bí thư chi đoàn','Tổ trưởng 1','Tổ trưởng 2','Tổ trưởng 3','Tổ trưởng 4'],true))throw new RuntimeException('Chức vụ không hợp lệ.');
            $db->beginTransaction();
            cmhome_exec($db,'DELETE FROM cmhome_roles WHERE class_id=? AND school_year=? AND role_name=?',[$classId,$year,$role]);
            cmhome_exec($db,'INSERT INTO cmhome_roles(class_id,school_year,student_id,role_name,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE updated_by=VALUES(updated_by)',[$classId,$year,$studentId,$role,$teacherName]);
            $db->commit();
        }elseif($action==='role_remove'){
            cmhome_exec($db,'DELETE FROM cmhome_roles WHERE class_id=? AND school_year=? AND student_id=? AND role_name=?',[$classId,$year,$studentId,(string)($_POST['role_name']??'')]);
        }elseif($action==='layout_save'){
            $rows=(int)($_POST['seat_rows']??0);$aisles=(int)($_POST['seat_aisles']??0);$perDesk=(int)($_POST['seats_per_desk']??0);
            if($rows<1||$rows>12||$aisles<1||$aisles>5||!in_array($perDesk,[1,2],true)||$rows*$aisles*$perDesk>60)throw new RuntimeException('Sơ đồ cần từ 1 đến 60 chỗ, tối đa 12 hàng và 5 dãy.');
            $last=cmhome_one($db,'SELECT MAX(seat_no) AS seat_no FROM cmhome_seats WHERE class_id=? AND school_year=?',[$classId,$year]);
            if((int)($last['seat_no']??0)>$rows*$aisles*$perDesk)throw new RuntimeException('Hãy bỏ học sinh ở các chỗ vượt quá kích thước mới rồi lưu sơ đồ trước.');
            cmhome_exec($db,'INSERT INTO cmhome_layout(class_id,school_year,seat_rows,seat_aisles,seats_per_desk) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE seat_rows=VALUES(seat_rows),seat_aisles=VALUES(seat_aisles),seats_per_desk=VALUES(seats_per_desk)',[$classId,$year,$rows,$aisles,$perDesk]);
        }elseif($action==='seats_save'){
            $layout=cmhome_one($db,'SELECT * FROM cmhome_layout WHERE class_id=? AND school_year=?',[$classId,$year]);
            $capacity=(int)($layout['seat_rows']??6)*(int)($layout['seat_aisles']??2)*(int)($layout['seats_per_desk']??2);
            $seats=(array)($_POST['seats']??[]);if(count($seats)>$capacity)throw new RuntimeException('Số chỗ vượt quá sơ đồ đã cài đặt.');$seen=[];$db->beginTransaction();
            cmhome_exec($db,'DELETE FROM cmhome_seats WHERE class_id=? AND school_year=?',[$classId,$year]);
            foreach($seats as $seat=>$id){$seat=(int)$seat;$id=(string)$id;if($id==='')continue;if($seat<1||$seat>$capacity||!isset($studentMap[$id])||isset($seen[$id]))throw new RuntimeException('Sơ đồ có học sinh trùng hoặc không thuộc lớp.');$seen[$id]=true;cmhome_exec($db,'INSERT INTO cmhome_seats(class_id,school_year,seat_no,student_id) VALUES(?,?,?,?)',[$classId,$year,$seat,$id]);}$db->commit();
        }elseif($action==='family_save'){
            $commune=trim((string)($_POST['commune']??''));$father=trim((string)($_POST['father_job']??''));$mother=trim((string)($_POST['mother_job']??''));
            if(strlen($commune)>150||strlen($father)>150||strlen($mother)>150)throw new RuntimeException('Thông tin gia đình quá dài.');
            cmhome_exec($db,'INSERT INTO cmhome_family_profiles(class_id,school_year,student_id,commune,father_job,mother_job,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE commune=VALUES(commune),father_job=VALUES(father_job),mother_job=VALUES(mother_job),updated_by=VALUES(updated_by),updated_at=NOW()',[$classId,$year,$studentId,$commune,$father,$mother,$teacherName]);
        }elseif($action==='note_save'){
            $talent=trim((string)($_POST['talent']??''));$circumstance=trim((string)($_POST['circumstance']??''));$support=trim((string)($_POST['support_note']??''));
            if(strlen($talent)>3000||strlen($circumstance)>3000||strlen($support)>3000)throw new RuntimeException('Ghi chú quá dài.');
            cmhome_exec($db,'INSERT INTO cmhome_notes(class_id,school_year,student_id,talent,circumstance,support_note,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE talent=VALUES(talent),circumstance=VALUES(circumstance),support_note=VALUES(support_note),updated_by=VALUES(updated_by),updated_at=NOW()',[$classId,$year,$studentId,$talent,$circumstance,$support,$teacherName]);
        }elseif($action==='point_add'){
            $code=(string)($_POST['criterion']??'');$date=(string)($_POST['event_date']??'');if(!isset($criteria[$code])||!cmhome_date($date))throw new RuntimeException('Tiêu chí hoặc ngày không hợp lệ.');
            $note=trim((string)($_POST['note']??''));if(strlen($note)>500)throw new RuntimeException('Ghi chú quá dài.');
            cmhome_exec($db,'INSERT INTO cmhome_points(class_id,school_year,student_id,event_date,points,criterion,note,created_by) VALUES(?,?,?,?,?,?,?,?)',[$classId,$year,$studentId,$date,$criteria[$code][2],$criteria[$code][1],$note,$teacherName]);
        }elseif($action==='review_save'){
            $kind=(string)($_POST['period_type']??'');$date=(string)($_POST['period_date']??'');$rank=(string)($_POST['rank_label']??'');$comment=trim((string)($_POST['comment']??''));
            if(!in_array($kind,['week','month'],true)||!cmhome_date($date)||!in_array($rank,['Tốt','Khá','Đạt','Cần cố gắng'],true)||strlen($comment)>500)throw new RuntimeException('Đánh giá không hợp lệ.');
            $start=$kind==='week'?date('Y-m-d',strtotime($date.' -'.(date('N',strtotime($date))-1).' days')):substr($date,0,7).'-01';
            cmhome_exec($db,'INSERT INTO cmhome_reviews(class_id,school_year,student_id,period_type,period_start,rank_label,comment,updated_by) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rank_label=VALUES(rank_label),comment=VALUES(comment),updated_by=VALUES(updated_by),updated_at=NOW()',[$classId,$year,$studentId,$kind,$start,$rank,$comment,$teacherName]);
        }elseif($action==='class_review_save'){
            $date=(string)($_POST['period_date']??'');$rank=(string)($_POST['rank_label']??'');$comment=trim((string)($_POST['comment']??''));
            if(!cmhome_date($date)||!in_array($rank,['Tốt','Khá','Đạt','Cần cố gắng'],true)||strlen($comment)>1000)throw new RuntimeException('Xếp loại lớp hoặc nhận xét không hợp lệ.');
            $start=date('Y-m-d',strtotime($date.' -'.(date('N',strtotime($date))-1).' days'));
            cmhome_exec($db,'INSERT INTO cmhome_class_reviews(class_id,school_year,week_start,rank_label,comment,updated_by,updated_at) VALUES(?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE rank_label=VALUES(rank_label),comment=VALUES(comment),updated_by=VALUES(updated_by),updated_at=NOW()',[$classId,$year,$start,$rank,$comment,$teacherName]);
        }elseif($action==='plan_add'){
            $title=trim((string)($_POST['title']??''));$due=(string)($_POST['due_date']??'');$owner=trim((string)($_POST['owner']??''));$note=trim((string)($_POST['note']??''));
            if($title===''||strlen($title)>255||!cmhome_date($due)||strlen($owner)>255||strlen($note)>3000)throw new RuntimeException('Thông tin kế hoạch không hợp lệ.');
            cmhome_exec($db,'INSERT INTO cmhome_plans(class_id,school_year,title,due_date,owner,note,created_by) VALUES(?,?,?,?,?,?,?)',[$classId,$year,$title,$due,$owner,$note,$teacherName]);
        }elseif($action==='plan_toggle'){
            $id=(int)($_POST['id']??0);$next=(string)($_POST['status']??'');if(!in_array($next,['open','done'],true))throw new RuntimeException('Trạng thái không hợp lệ.');
            cmhome_exec($db,'UPDATE cmhome_plans SET status=? WHERE id=? AND class_id=? AND school_year=?',[$next,$id,$classId,$year]);
        }elseif($action==='ledger_add'){
            $date=(string)($_POST['entry_date']??'');$kind=(string)($_POST['kind']??'');$amount=cmhome_money($_POST['amount']??'');$description=trim((string)($_POST['description']??''));
            if(!cmhome_date($date)||!in_array($kind,['thu','chi'],true)||$amount<1||$description===''||strlen($description)>500)throw new RuntimeException('Khoản thu chi không hợp lệ.');
            $sid=(string)($_POST['ledger_student_id']??'');if($sid!==''&&!isset($studentMap[$sid]))throw new RuntimeException('Học sinh không thuộc lớp.');
            cmhome_exec($db,'INSERT INTO cmhome_ledger(class_id,school_year,entry_date,kind,amount,description,student_id,created_by) VALUES(?,?,?,?,?,?,?,?)',[$classId,$year,$date,$kind,$amount,$description,$sid,$teacherName]);
        }elseif($action==='settings_save'&&$admin){
            $rates=[];foreach(['sang','trua','toi'] as $meal)$rates[$meal]=cmhome_money($_POST['rate_'.$meal]??'0');
            $thresholds=[];foreach(['good_at','fair_at','pass_at'] as $key)$thresholds[$key]=(int)($_POST[$key]??0);
            if($thresholds['good_at']<$thresholds['fair_at']||$thresholds['fair_at']<$thresholds['pass_at'])throw new RuntimeException('Ngưỡng xếp loại phải theo thứ tự giảm dần.');
            cmhome_exec($db,'INSERT INTO cmhome_settings(class_id,school_year,rate_sang,rate_trua,rate_toi,good_at,fair_at,pass_at) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rate_sang=VALUES(rate_sang),rate_trua=VALUES(rate_trua),rate_toi=VALUES(rate_toi),good_at=VALUES(good_at),fair_at=VALUES(fair_at),pass_at=VALUES(pass_at)',[$classId,$year,$rates['sang'],$rates['trua'],$rates['toi'],$thresholds['good_at'],$thresholds['fair_at'],$thresholds['pass_at']]);
        }elseif($action==='refund_add'){
            $refundMonth=(string)($_POST['refund_month']??'');if(!cmhome_month($refundMonth))throw new RuntimeException('Tháng không hợp lệ.');
            $counts=[];$days=(int)date('t',strtotime($refundMonth.'-01'));foreach(['sang','trua','toi'] as $meal){$v=(string)($_POST['meals_'.$meal]??'0');if(!ctype_digit($v)||(int)$v>$days)throw new RuntimeException('Số bữa đề nghị không hợp lệ.');$counts[$meal]=(int)$v;}
            if(array_sum($counts)===0)throw new RuntimeException('Hãy nhập số bữa cần hoàn.');
            $rate=cmhome_one($db,'SELECT * FROM cmhome_settings WHERE class_id=? AND school_year=?',[$classId,$year]);$amount=0;foreach(['sang','trua','toi'] as $meal)$amount+=$counts[$meal]*(int)($rate['rate_'.$meal]??0);
            if($amount<1)throw new RuntimeException('Quản trị cần cài đơn giá trước khi lập đề nghị.');
            $note=trim((string)($_POST['note']??''));if($note===''||strlen($note)>500)throw new RuntimeException('Hãy ghi lý do hoàn trả (tối đa 500 ký tự).');
            $db->beginTransaction();
            $old=cmhome_one($db,'SELECT id,status FROM cmhome_refunds WHERE class_id=? AND school_year=? AND month_key=? AND student_id=? FOR UPDATE',[$classId,$year,$refundMonth,$studentId]);
            if($old&&$old['status']!=='rejected')throw new RuntimeException('Học sinh đã có đề nghị trong tháng; tránh lập trùng khoản hoàn.');
            if($old)cmhome_exec($db,'UPDATE cmhome_refunds SET meals_sang=?,meals_trua=?,meals_toi=?,amount=?,note=?,status=?,created_by=?,decided_by=? WHERE id=?',[$counts['sang'],$counts['trua'],$counts['toi'],$amount,$note,'pending',$teacherName,'',$old['id']]);
            else cmhome_exec($db,'INSERT INTO cmhome_refunds(class_id,school_year,month_key,student_id,meals_sang,meals_trua,meals_toi,amount,note,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[$classId,$year,$refundMonth,$studentId,$counts['sang'],$counts['trua'],$counts['toi'],$amount,$note,$teacherName]);
            $db->commit();
        }elseif($action==='refund_decide'&&$admin){
            $id=(int)($_POST['id']??0);$next=(string)($_POST['status']??'');if(!in_array($next,['approved','rejected','paid'],true))throw new RuntimeException('Trạng thái không hợp lệ.');
            $db->beginTransaction();$r=cmhome_one($db,'SELECT * FROM cmhome_refunds WHERE id=? AND class_id=? AND school_year=? FOR UPDATE',[$id,$classId,$year]);
            if(!$r||($next==='paid'&&$r['status']!=='approved')||($next!=='paid'&&$r['status']!=='pending'))throw new RuntimeException('Đề nghị không ở trạng thái cho phép xử lý.');
            cmhome_exec($db,'UPDATE cmhome_refunds SET status=?,decided_by=? WHERE id=?',[$next,$teacherName,$id]);
            if($next==='paid')cmhome_exec($db,'INSERT INTO cmhome_ledger(class_id,school_year,entry_date,kind,amount,description,student_id,created_by) VALUES(?,?,?,?,?,?,?,?)',[$classId,$year,$today,'chi',$r['amount'],'Hoàn tiền ăn tháng '.$r['month_key'].' · phiếu #'.$id,$r['student_id'],$teacherName]);
            $db->commit();
        }else throw new RuntimeException('Không có quyền thực hiện thao tác.');
        header('Location: '.$url(['tab'=>(string)($_POST['return_tab']??$tab)]));exit;
    }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();$error=$ex->getMessage();}
}
$settings=$class?cmhome_one($db,'SELECT * FROM cmhome_settings WHERE class_id=? AND school_year=?',[$classId,$year]):[];
$settings=array_merge(['rate_sang'=>0,'rate_trua'=>0,'rate_toi'=>0,'good_at'=>8,'fair_at'=>3,'pass_at'=>0],$settings);
$roles=[];$seats=[];$layout=['seat_rows'=>6,'seat_aisles'=>2,'seats_per_desk'=>2];$notes=[];$plans=[];$ledger=[];$refunds=[];$mealData=[];$grams=[];$healthRows=[];$pointEvents=[];$pointTotals=[];$reviews=[];$classReview=[];$classReviewHistory=[];$ledgerTotals=['income'=>0,'expense'=>0];
if($class){
    if($tab==='organization'){
        $roles=cmhome_rows($db,'SELECT * FROM cmhome_roles WHERE class_id=? AND school_year=? ORDER BY role_name',[$classId,$year]);
        $layout=array_merge($layout,cmhome_one($db,'SELECT * FROM cmhome_layout WHERE class_id=? AND school_year=?',[$classId,$year]));
        foreach(cmhome_rows($db,'SELECT seat_no,student_id FROM cmhome_seats WHERE class_id=? AND school_year=?',[$classId,$year]) as $r)$seats[(int)$r['seat_no']]=$r['student_id'];
    }elseif($tab==='health'){
        $healthFile=dirname(__DIR__).'/data/noitru/health.json';
        $allHealth=is_file($healthFile)?json_decode((string)file_get_contents($healthFile),true):[];
        $names=[];
        foreach($students as $student)$names[$normal($student['name'])][]=(string)$student['id'];
        foreach(is_array($allHealth)?$allHealth:[] as $record){
            if(!is_array($record))continue;
            $sid=(string)($record['student_id']??'');
            if(!isset($studentMap[$sid])){
                if($normal($record['class_name']??'')!==$normal($class['name']))continue;
                $matches=$names[$normal($record['student_name']??'')]??[];
                if(count($matches)!==1)continue;
                $sid=$matches[0];$record['student_id']=$sid;
            }
            $healthRows[]=$record;
        }
        usort($healthRows,static fn($a,$b)=>strcmp((string)($b['date']??'').(string)($b['created_at']??''),(string)($a['date']??'').(string)($a['created_at']??'')));
    }elseif($tab==='notes'){
        foreach(cmhome_rows($db,'SELECT * FROM cmhome_notes WHERE class_id=? AND school_year=?',[$classId,$year]) as $r)$notes[$r['student_id']]=$r;
    }elseif($tab==='plans'||$tab==='overview'){
        $plans=cmhome_rows($db,'SELECT * FROM cmhome_plans WHERE class_id=? AND school_year=? ORDER BY status ASC,due_date ASC,id DESC LIMIT 100',[$classId,$year]);
    }elseif($tab==='points'){
        $begin=$period==='week'?date('Y-m-d',strtotime($scoreDate.' -'.(date('N',strtotime($scoreDate))-1).' days')):substr($scoreDate,0,7).'-01';
        $end=$period==='week'?date('Y-m-d',strtotime($begin.' +6 days')):date('Y-m-t',strtotime($begin));
        $pointEvents=cmhome_rows($db,'SELECT * FROM cmhome_points WHERE class_id=? AND school_year=? AND event_date BETWEEN ? AND ? ORDER BY event_date DESC,id DESC LIMIT 300',[$classId,$year,$begin,$end]);
        foreach(cmhome_rows($db,'SELECT student_id,SUM(points) AS total FROM cmhome_points WHERE class_id=? AND school_year=? AND event_date BETWEEN ? AND ? GROUP BY student_id',[$classId,$year,$begin,$end]) as $r)$pointTotals[$r['student_id']]=(int)$r['total'];
        foreach(cmhome_rows($db,'SELECT * FROM cmhome_reviews WHERE class_id=? AND school_year=? AND period_type=? AND period_start=?',[$classId,$year,$period,$begin]) as $r)$reviews[$r['student_id']]=$r;
        $classWeek=date('Y-m-d',strtotime($scoreDate.' -'.(date('N',strtotime($scoreDate))-1).' days'));
        $classReview=cmhome_one($db,'SELECT * FROM cmhome_class_reviews WHERE class_id=? AND school_year=? AND week_start=?',[$classId,$year,$classWeek]);
        $classReviewHistory=cmhome_rows($db,'SELECT * FROM cmhome_class_reviews WHERE class_id=? AND school_year=? ORDER BY week_start DESC LIMIT 16',[$classId,$year]);
    }elseif($tab==='meals'){
        [$mealData,$grams]=cmhome_meal_summary($class['name'],array_keys($studentMap),$month);
        $ledger=cmhome_rows($db,'SELECT * FROM cmhome_ledger WHERE class_id=? AND school_year=? AND entry_date BETWEEN ? AND ? ORDER BY entry_date DESC,id DESC LIMIT 200',[$classId,$year,$month.'-01',date('Y-m-t',strtotime($month.'-01'))]);
        $ledgerTotals=cmhome_one($db,"SELECT COALESCE(SUM(CASE WHEN kind='thu' THEN amount ELSE 0 END),0) AS income,COALESCE(SUM(CASE WHEN kind='chi' THEN amount ELSE 0 END),0) AS expense FROM cmhome_ledger WHERE class_id=? AND school_year=? AND entry_date BETWEEN ? AND ?",[$classId,$year,$month.'-01',date('Y-m-t',strtotime($month.'-01'))]);
        $refunds=cmhome_rows($db,'SELECT * FROM cmhome_refunds WHERE class_id=? AND school_year=? AND month_key=? ORDER BY id DESC LIMIT 200',[$classId,$year,$month]);
    }
}
require defined('CDS_HOMEROOM_STANDALONE') ? dirname(__DIR__).'/includes/homeroom_header.php' : __DIR__.'/includes/header.php';
?>
<style>
.cmh-tabs{display:flex;flex-wrap:wrap;gap:.45rem}.cmh-point{min-width:120px;min-height:62px;white-space:normal}.cmh-table{min-width:750px}
.cmh-classroom{padding:1.2rem;background:#f3f8ff;border:1px solid #cbdceb;border-radius:18px}.cmh-board{max-width:460px;margin:0 auto 1.6rem;text-align:center;padding:.7rem;border-radius:8px;background:#24547b;color:#fff;font-weight:750;box-shadow:0 6px 14px #25496730}
.cmh-aisles{display:grid;grid-template-columns:repeat(var(--aisles),minmax(180px,1fr));gap:clamp(.6rem,2vw,2rem);overflow-x:auto}.cmh-aisle{min-width:180px}.cmh-aisle-title{text-align:center;color:#235b85;font-weight:750;margin-bottom:.5rem}.cmh-desk{margin-bottom:.9rem;padding:.55rem;background:#fff;border:1px solid #c9d9e9;border-radius:12px;box-shadow:0 5px 12px #24466910}.cmh-row-label{display:block;color:#64748b;font-size:.75rem;margin-bottom:.4rem}.cmh-desk-seats{display:flex;gap:.4rem}.cmh-seat{flex:1;min-width:0;padding:.35rem;border-radius:9px;background:#e9f2fb}.cmh-seat span{display:block;font-size:.75rem;font-weight:700;margin-bottom:.25rem}.cmh-seat select{width:100%;font-size:.78rem}
.cmh-birthday{background:linear-gradient(120deg,#fff7e6,#fff);border-color:#f2d99c}
@media(max-width:700px){.cmh-aisles{grid-template-columns:repeat(var(--aisles),minmax(180px,1fr))}}@media print{.cmh-classroom form .d-flex,.cmh-classroom select,.cmh-classroom~form{display:none!important}}
</style>
<div class="container-fluid py-3"><h2><i class="bi bi-person-workspace"></i> Tiện ích chủ nhiệm</h2>
<?php if(!$myClasses):?><div class="alert alert-info">Tài khoản chưa được phân công chủ nhiệm lớp trong CSDL. Quản trị kiểm tra GVCN của lớp và tài khoản giáo viên.</div><?php else:?>
<form method="get" class="row g-2 mb-3 align-items-end"><input type="hidden" name="tab" value="<?=e($tab)?>"><div class="col-sm-3"><label class="form-label">Năm học</label><select class="form-select" name="year" onchange="this.form.submit()"><?php foreach(array_keys($yearOptions) as $y):?><option <?=$year===$y?'selected':''?>><?=e($y)?></option><?php endforeach;?></select></div><div class="col-sm-3"><label class="form-label">Lớp</label><select class="form-select" name="class_id" onchange="this.form.submit()"><?php foreach($visibleClasses as $id=>$c):?><option value="<?=e($id)?>" <?=$id===$classId?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div><div class="col-sm-4 text-muted small">GVCN: <?=e($class['homeroom_name']??'')?> · <?=count($students)?> học sinh</div></form>
<?php if(!$class):?><div class="alert alert-info">Chưa có lớp phù hợp năm học này.</div><?php else:?>
<nav class="cmh-tabs mb-3"><?php foreach($tabs as $key=>$label):?><a class="btn btn-sm <?=$tab===$key?'btn-primary':'btn-outline-primary'?>" href="<?=e($url(['tab'=>$key]))?>"><?=e($label)?></a><?php endforeach;?></nav>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if($tab==='overview'):?>
<div class="row g-3"><div class="col-md-4"><div class="card h-100"><div class="card-body"><h5>Sĩ số</h5><strong class="display-6"><?=count($students)?></strong><div class="text-muted">Nữ: <?=count(array_filter($students,static fn($s)=>trim($s['gender'])==='Nữ'))?> · Nội trú: <?=count(array_filter($students,static fn($s)=>(int)$s['is_boarder']===1))?></div></div></div></div><div class="col-md-8"><div class="card h-100"><div class="card-body"><h5>Việc sắp tới</h5><?php $upcoming=array_values(array_filter($plans,static fn($p)=>$p['status']==='open'));foreach(array_slice($upcoming,0,6) as $p):?><div class="border-bottom py-2"><strong><?=e($p['title'])?></strong> · <?=e(date('d/m/Y',strtotime($p['due_date'])))?> · <?=e($p['owner'])?></div><?php endforeach;if(!$upcoming):?><div class="text-muted">Chưa có việc sắp tới.</div><?php endif;?></div></div></div></div>
<div class="card cmh-birthday mt-3"><div class="card-body"><h5>🎂 Sinh nhật tuần này <small class="text-muted fs-6"><?=e(date('d/m',strtotime($weekStart)))?> – <?=e(date('d/m',strtotime($weekEnd)))?></small></h5><?php if($birthdays):?><div class="d-flex flex-wrap gap-2"><?php foreach($birthdays as $item):?><span class="badge bg-white text-dark border p-2 fs-6"><?=e($item['student']['name'])?> · <?=e(date('d/m',strtotime($item['date'])))?></span><?php endforeach;?></div><?php else:?><p class="text-muted mb-0">Tuần này chưa có học sinh sinh nhật.</p><?php endif;?></div></div>
<?php if($missingDob):?><div class="alert alert-warning mt-3 mb-0"><strong><?=count($missingDob)?> học sinh chưa có ngày sinh trong CSDL:</strong> <?=e(implode(', ',$missingDob))?>. Hãy bổ sung ngày sinh tại Cơ sở dữ liệu để danh sách và nhắc sinh nhật hiển thị đầy đủ.</div><?php endif;?>
<div class="row g-3 mt-0"><?php foreach(['gender'=>'Nam, nữ','ethnicity'=>'Dân tộc','commune'=>'Xã / nơi ở','age'=>'Độ tuổi','father_job'=>'Nghề nghiệp bố','mother_job'=>'Nghề nghiệp mẹ'] as $field=>$title):?><div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><h5><?=e($title)?><?php if($field==='ethnicity'):?> <small class="text-muted fs-6">(<?=count(array_diff(array_keys($summary[$field]),['Chưa có dữ liệu']))?> dân tộc)</small><?php endif;?></h5><?php foreach($summary[$field] as $label=>$count):?><div class="d-flex justify-content-between gap-2 border-bottom py-1"><span><?=e($label)?></span><strong><?=$count?></strong></div><?php endforeach;?></div></div></div><?php endforeach;?></div>
<?php elseif($tab==='organization'):?>
<div class="card mb-3"><div class="card-body"><h5>Ban cán sự lớp</h5><form method="post" class="row g-2 align-items-end mb-3"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="role_add"><div class="col-md-4"><label class="form-label">Học sinh</label><select class="form-select" name="student_id"><?php foreach($students as $s):?><option value="<?=e($s['id'])?>"><?=e($s['name'])?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">Chức vụ</label><select class="form-select" name="role_name"><?php foreach(['Lớp trưởng','Lớp phó học tập','Lớp phó đời sống','Bí thư chi đoàn','Tổ trưởng 1','Tổ trưởng 2','Tổ trưởng 3','Tổ trưởng 4'] as $role):?><option><?=e($role)?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-primary">Phân công</button></div></form><div class="d-flex flex-wrap gap-2"><?php foreach($roles as $r):?><form method="post" class="badge text-bg-light border p-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="role_remove"><input type="hidden" name="student_id" value="<?=e($r['student_id'])?>"><input type="hidden" name="role_name" value="<?=e($r['role_name'])?>"><?=e($r['role_name'].' · '.($studentMap[$r['student_id']]['name']??'Đã chuyển lớp'))?> <button class="btn btn-sm text-danger" title="Bỏ chức vụ">×</button></form><?php endforeach;?></div></div></div>
<div class="card mb-3"><div class="card-body"><h5><i class="bi bi-grid-3x3-gap text-primary"></i> Sơ đồ chỗ ngồi</h5>
<p class="text-muted small">Chọn số hàng, số dãy và số học sinh mỗi bàn. Sơ đồ hiển thị theo hướng nhìn từ bàn giáo viên.</p>
<form method="post" class="row g-2 align-items-end mb-4"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="layout_save"><input type="hidden" name="return_tab" value="organization">
<div class="col-sm-3"><label class="form-label">Số hàng</label><input type="number" class="form-control" name="seat_rows" min="1" max="12" value="<?=e($layout['seat_rows'])?>" required></div>
<div class="col-sm-3"><label class="form-label">Số dãy</label><input type="number" class="form-control" name="seat_aisles" min="1" max="5" value="<?=e($layout['seat_aisles'])?>" required></div>
<div class="col-sm-3"><label class="form-label">Học sinh / bàn</label><select name="seats_per_desk" class="form-select"><option value="1" <?=(int)$layout['seats_per_desk']===1?'selected':''?>>1</option><option value="2" <?=(int)$layout['seats_per_desk']===2?'selected':''?>>2</option></select></div><div class="col-sm-3"><button class="btn btn-outline-primary w-100">Cập nhật bố cục</button></div></form>
<div class="cmh-classroom"><div class="cmh-board"><i class="bi bi-easel2 me-2"></i> BẢNG · BÀN GIÁO VIÊN</div>
<form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="seats_save"><input type="hidden" name="return_tab" value="organization">
<div class="cmh-aisles" style="--aisles:<?=(int)$layout['seat_aisles']?>"><?php for($aisle=0;$aisle<(int)$layout['seat_aisles'];$aisle++):?><div class="cmh-aisle"><div class="cmh-aisle-title">Dãy <?=$aisle+1?></div><?php for($row=0;$row<(int)$layout['seat_rows'];$row++):?><div class="cmh-desk"><span class="cmh-row-label">Hàng <?=$row+1?></span><div class="cmh-desk-seats"><?php for($side=0;$side<(int)$layout['seats_per_desk'];$side++):$i=($row*(int)$layout['seat_aisles']+$aisle)*(int)$layout['seats_per_desk']+$side+1;?><label class="cmh-seat"><span><i class="bi bi-person"></i> Chỗ <?=$i?></span><select class="form-select form-select-sm" name="seats[<?=$i?>]"><option value="">Trống</option><?php foreach($students as $s):?><option value="<?=e($s['id'])?>" <?=($seats[$i]??'')===$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?></select></label><?php endfor;?></div></div><?php endfor;?></div><?php endfor;?></div>
<div class="d-flex gap-2 mt-3"><button class="btn btn-primary">Lưu sơ đồ</button><button type="button" class="btn btn-outline-secondary" onclick="window.print()">In sơ đồ</button></div></form></div></div></div>
<div class="card"><div class="card-body"><h5>Danh sách lớp</h5><div class="table-responsive"><table class="table table-striped"><thead><tr><th>STT</th><th>Họ tên</th><th>Giới tính</th><th>Ngày sinh</th><th>Phụ huynh</th><th>SĐT</th></tr></thead><tbody><?php foreach($students as $i=>$s):?><tr><td><?=$i+1?></td><td><?=e($s['name'])?></td><td><?=e($s['gender'])?></td><td><?=!empty($s['dob'])?e(date('d/m/Y',strtotime($s['dob']))):'<span class="text-muted">Chưa có</span>'?></td><td><?=e($s['parent_name'])?></td><td><?=e($s['parent_phone'])?></td></tr><?php endforeach;?></tbody></table></div></div></div>
<div class="card mt-3"><div class="card-body"><h5>Thông tin xã và nghề nghiệp bố mẹ</h5><p class="text-muted small">Địa chỉ có ghi rõ xã/phường được hệ thống nhận diện. GVCN có thể bổ sung hoặc sửa thông tin cho từng học sinh; thống kê Tổng quan cập nhật theo dữ liệu này.</p><div class="table-responsive"><table class="table table-sm table-striped align-middle"><thead><tr><th>Học sinh</th><th>Xã / phường</th><th>Nghề nghiệp bố</th><th>Nghề nghiệp mẹ</th><th></th></tr></thead><tbody><?php foreach($students as $s):$profile=$familyProfiles[(string)$s['id']]??[];?><tr><td class="fw-semibold"><?=e($s['name'])?></td><td colspan="4"><form method="post" class="row g-2 align-items-center"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="student_id" value="<?=e($s['id'])?>"><input type="hidden" name="action" value="family_save"><input type="hidden" name="return_tab" value="organization"><div class="col-sm-4"><input class="form-control form-control-sm" name="commune" maxlength="150" aria-label="Xã / phường" value="<?=e($profile['commune']??$s['commune'])?>"></div><div class="col-sm-3"><input class="form-control form-control-sm" name="father_job" maxlength="150" placeholder="Nghề bố" aria-label="Nghề bố" value="<?=e($profile['father_job']??$s['father_job'])?>"></div><div class="col-sm-3"><input class="form-control form-control-sm" name="mother_job" maxlength="150" placeholder="Nghề mẹ" aria-label="Nghề mẹ" value="<?=e($profile['mother_job']??$s['mother_job'])?>"></div><div class="col-sm-2"><button class="btn btn-sm btn-outline-primary w-100">Lưu</button></div></form></td></tr><?php endforeach;?></tbody></table></div></div></div>
<?php elseif($tab==='notes'):?>
<div class="alert alert-warning">Thông tin hoàn cảnh và nhu cầu hỗ trợ chỉ hiển thị cho GVCN lớp và quản trị. Không dùng trong bảng thi đua hoặc trang công khai.</div>
<div class="accordion" id="cmhNotes"><?php foreach($students as $i=>$s):$n=$notes[$s['id']]??[];?><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#note<?=$i?>"><?=e($s['name'])?><?=!empty($n['talent'])||!empty($n['circumstance'])||!empty($n['support_note'])?' · Có lưu ý':''?></button></h2><div id="note<?=$i?>" class="accordion-collapse collapse" data-bs-parent="#cmhNotes"><div class="accordion-body"><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="student_id" value="<?=e($s['id'])?>"><input type="hidden" name="action" value="note_save"><label class="form-label">Năng khiếu / sở trường</label><textarea class="form-control mb-2" name="talent" rows="2"><?=e($n['talent']??'')?></textarea><label class="form-label">Hoàn cảnh cần lưu ý</label><textarea class="form-control mb-2" name="circumstance" rows="2"><?=e($n['circumstance']??'')?></textarea><label class="form-label">Hỗ trợ cần thực hiện</label><textarea class="form-control mb-2" name="support_note" rows="2"><?=e($n['support_note']??'')?></textarea><button class="btn btn-primary btn-sm">Lưu ghi chú</button></form></div></div></div><?php endforeach;?></div>
<?php elseif($tab==='health'):?>
<div class="card"><div class="card-body"><h5><i class="bi bi-heart-pulse text-danger me-1"></i> Sức khỏe học sinh lớp <?=e($class['name'])?></h5><p class="text-muted small">Các ghi nhận do y tế cập nhật trong Quản lý nội trú · Sức khỏe. Chỉ GVCN của lớp và quản trị xem được.</p>
<?php if(!$healthRows):?><div class="alert alert-info mb-0">Chưa có ghi nhận sức khỏe nào của học sinh lớp này.</div><?php else:?>
<p class="fw-semibold">Có <?=count($healthRows)?> lần ghi nhận của <?=count(array_unique(array_column($healthRows,'student_id')))?> học sinh.</p><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Ngày</th><th>Học sinh</th><th>Triệu chứng / chẩn đoán</th><th>Xử trí</th><th>Hình thức</th><th>Liên hệ phụ huynh</th><th>Ghi chú</th></tr></thead><tbody><?php foreach($healthRows as $record):?><tr><td><?=e($record['date']??'')?></td><td class="fw-semibold"><?=e($studentMap[(string)$record['student_id']]['name']??'')?></td><td><?=e($record['diagnosis']??'')?></td><td><?=e($record['treatment']??'')?></td><td><?=e(['medicine'=>'Phát thuốc','first_aid'=>'Sơ cứu','hospital'=>'Vào viện','family_pickup'=>'Gia đình đón về','thuoc'=>'Phát thuốc','kham'=>'Sơ cứu','theo_doi'=>'Theo dõi'][$record['type']??'']??($record['type']??''))?></td><td><?=!empty($record['parent_contacted'])?'Đã liên hệ':'—'?></td><td><?=e($record['note']??'')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></div>
<?php elseif($tab==='points'):?>
<div class="card mb-3"><div class="card-body"><h5>Ghi nhận nhanh</h5><form method="post" class="row g-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="point_add"><div class="col-md-4"><label class="form-label">Học sinh</label><select class="form-select" name="student_id"><?php foreach($students as $s):?><option value="<?=e($s['id'])?>"><?=e($s['name'])?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Ngày</label><input class="form-control" type="date" name="event_date" value="<?=e($scoreDate)?>"></div><div class="col-md-5"><label class="form-label">Ghi chú (nếu có)</label><input class="form-control" name="note" maxlength="500"></div><div class="col-12 d-flex flex-wrap gap-2"><?php foreach($criteria as $key=>$c):?><button class="btn <?=$c[2]>0?'btn-outline-success':'btn-outline-warning'?> cmh-point" name="criterion" value="<?=e($key)?>"><span class="fs-4"><?=$c[0]?></span><br><?=e($c[1])?> <?=$c[2]>0?'+':''?><?=$c[2]?></button><?php endforeach;?></div></form></div></div>
<form method="get" class="row g-2 mb-3"><input type="hidden" name="tab" value="points"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="year" value="<?=e($year)?>"><div class="col-auto"><input type="date" class="form-control" name="score_date" value="<?=e($scoreDate)?>"></div><div class="col-auto"><select class="form-select" name="period"><option value="week" <?=$period==='week'?'selected':''?>>Theo tuần</option><option value="month" <?=$period==='month'?'selected':''?>>Theo tháng</option></select></div><div class="col-auto"><button class="btn btn-primary">Xem đánh giá</button></div></form>
<div class="card mb-3"><div class="card-body"><h5>Đánh giá <?=e($period==='week'?'tuần':'tháng')?> · <?=e(date('d/m/Y',strtotime($begin)))?> – <?=e(date('d/m/Y',strtotime($end)))?></h5><div class="table-responsive"><table class="table table-striped cmh-table"><thead><tr><th>Học sinh</th><th>Điểm</th><th>Gợi ý</th><th>Đánh giá của GVCN</th></tr></thead><tbody><?php foreach($students as $s):$score=$pointTotals[$s['id']]??0;$rank=$score>=$settings['good_at']?'Tốt':($score>=$settings['fair_at']?'Khá':($score>=$settings['pass_at']?'Đạt':'Cần cố gắng'));$saved=$reviews[$s['id']]??[];?><tr><td><?=e($s['name'])?></td><td><?=$score?></td><td><?=e($rank)?></td><td><form method="post" class="d-flex flex-wrap gap-1"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="review_save"><input type="hidden" name="student_id" value="<?=e($s['id'])?>"><input type="hidden" name="period_type" value="<?=e($period)?>"><input type="hidden" name="period_date" value="<?=e($scoreDate)?>"><select class="form-select form-select-sm" name="rank_label" style="width:auto"><?php foreach(['Tốt','Khá','Đạt','Cần cố gắng'] as $choice):?><option <?=($saved['rank_label']??$rank)===$choice?'selected':''?>><?=e($choice)?></option><?php endforeach;?></select><input class="form-control form-control-sm" name="comment" value="<?=e($saved['comment']??'')?>" maxlength="500" placeholder="Nhận xét" style="max-width:220px"><button class="btn btn-sm btn-outline-primary">Lưu</button></form></td></tr><?php endforeach;?></tbody></table></div><div class="form-text">Điểm tạo gợi ý; đánh giá và nhận xét do GVCN lưu cho đúng tuần hoặc tháng.</div></div></div>
<div class="card"><div class="card-body"><h5>Lịch sử ghi nhận</h5><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Ngày</th><th>Học sinh</th><th>Tiêu chí</th><th>Điểm</th><th>Ghi chú</th><th>Người ghi</th></tr></thead><tbody><?php foreach($pointEvents as $r):?><tr><td><?=e($r['event_date'])?></td><td><?=e($studentMap[$r['student_id']]['name']??'Đã chuyển lớp')?></td><td><?=e($r['criterion'])?></td><td><?=$r['points']?></td><td><?=e($r['note'])?></td><td><?=e($r['created_by'])?></td></tr><?php endforeach;?></tbody></table></div></div></div>
<div class="card mb-3"><div class="card-body"><h5>🏅 Xếp loại thi đua của lớp theo tuần</h5><p class="text-muted small">Tuần <?=e(date('d/m/Y',strtotime($classWeek)))?> – <?=e(date('d/m/Y',strtotime($classWeek.' +6 days')))?>. GVCN ghi nhận kết quả chung của lớp; các lần cập nhật cùng tuần được lưu trên một dòng.</p>
<form method="post" class="row g-2 align-items-end"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="class_review_save"><input type="hidden" name="return_tab" value="points"><input type="hidden" name="period_date" value="<?=e($scoreDate)?>"><div class="col-md-3"><label class="form-label">Xếp loại lớp</label><select name="rank_label" class="form-select"><?php foreach(['Tốt','Khá','Đạt','Cần cố gắng'] as $choice):?><option <?=($classReview['rank_label']??'')===$choice?'selected':''?>><?=e($choice)?></option><?php endforeach;?></select></div><div class="col-md-7"><label class="form-label">Nhận xét tuần</label><input class="form-control" name="comment" value="<?=e($classReview['comment']??'')?>" maxlength="1000" placeholder="Thành tích, nề nếp, việc cần cải thiện"></div><div class="col-md-2"><button class="btn btn-primary w-100">Lưu xếp loại</button></div></form>
<?php if($classReviewHistory):?><div class="table-responsive mt-3"><table class="table table-sm table-striped"><thead><tr><th>Tuần</th><th>Xếp loại</th><th>Nhận xét</th><th>Người ghi</th></tr></thead><tbody><?php foreach($classReviewHistory as $item):?><tr><td><?=e(date('d/m/Y',strtotime($item['week_start'])))?> – <?=e(date('d/m/Y',strtotime($item['week_start'].' +6 days')))?></td><td><strong><?=e($item['rank_label'])?></strong></td><td><?=e($item['comment'])?></td><td><?=e($item['updated_by'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></div>
<?php elseif($tab==='plans'):?>
<div class="card mb-3"><div class="card-body"><h5>Thêm việc sắp tới</h5><form method="post" class="row g-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="plan_add"><div class="col-md-5"><label class="form-label">Nội dung</label><input class="form-control" name="title" required maxlength="255"></div><div class="col-md-3"><label class="form-label">Hạn</label><input type="date" class="form-control" name="due_date" value="<?=$today?>" required></div><div class="col-md-4"><label class="form-label">Người phụ trách</label><input class="form-control" name="owner"></div><div class="col-12"><textarea class="form-control" name="note" placeholder="Ghi chú"></textarea></div><div><button class="btn btn-primary">Thêm kế hoạch</button></div></form></div></div>
<div class="card"><div class="card-body"><h5>Danh sách việc</h5><?php foreach($plans as $p):?><div class="border-bottom py-2 d-flex align-items-center justify-content-between gap-2"><div><strong><?=e($p['title'])?></strong><div class="small text-muted">Hạn <?=e($p['due_date'])?> · <?=e($p['owner'])?> · <?=e($p['note'])?></div></div><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="plan_toggle"><input type="hidden" name="id" value="<?=$p['id']?>"><input type="hidden" name="status" value="<?=$p['status']==='done'?'open':'done'?>"><button class="btn btn-sm <?=$p['status']==='done'?'btn-success':'btn-outline-secondary'?>"><?=$p['status']==='done'?'Đã xong':'Đánh dấu xong'?></button></form></div><?php endforeach;if(!$plans):?><div class="text-muted">Chưa có kế hoạch.</div><?php endif;?></div></div>
<?php elseif($tab==='meals'):?>
<form method="get" class="row g-2 align-items-end mb-3"><input type="hidden" name="tab" value="meals"><input type="hidden" name="year" value="<?=e($year)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><div class="col-auto"><label class="form-label">Tháng đối soát</label><input class="form-control" type="month" name="month" value="<?=e($month)?>"></div><div class="col-auto"><button class="btn btn-primary">Xem tháng</button></div></form>
<div class="card mb-3"><div class="card-body"><h5>Bữa ăn và gạo · <?=e($month)?></h5><p class="small text-muted">Chỉ tính bữa đã báo cho lớp và đã khóa. Gạo = từng bữa × định mức tương ứng; dữ liệu chỉ đọc từ Nội trú.</p><div class="table-responsive"><table class="table table-striped table-sm cmh-table"><thead><tr><th>Học sinh</th><th>Sáng</th><th>Trưa</th><th>Tối</th><th>Tổng bữa</th><th>Gạo (kg)</th></tr></thead><tbody><?php foreach($students as $s):$m=$mealData[$s['id']]??['sang'=>0,'trua'=>0,'toi'=>0,'rice_kg'=>0];?><tr><td><?=e($s['name'])?></td><td><?=$m['sang']?></td><td><?=$m['trua']?></td><td><?=$m['toi']?></td><td><?=($m['sang']+$m['trua']+$m['toi'])?></td><td><?=number_format((float)$m['rice_kg'],3,',','.')?></td></tr><?php endforeach;?></tbody></table></div><div class="form-text">Định mức gạo: sáng <?=e($grams['sang_grams']??0)?>g, trưa <?=e($grams['trua_grams']??0)?>g, tối <?=e($grams['toi_grams']??0)?>g.</div></div></div>
<?php if($admin):?><div class="card mb-3"><div class="card-body"><h5>Cài đặt đơn giá và ngưỡng xếp loại · Quản trị</h5><form method="post" class="row g-2 align-items-end"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="settings_save"><?php foreach(['sang'=>'Sáng','trua'=>'Trưa','toi'=>'Tối'] as $key=>$label):?><div class="col-md-2"><label class="form-label">Giá hoàn <?=$label?> (đ)</label><input class="form-control" type="number" min="0" name="rate_<?=$key?>" value="<?=e($settings['rate_'.$key])?>"></div><?php endforeach;foreach(['good_at'=>'Tốt từ','fair_at'=>'Khá từ','pass_at'=>'Đạt từ'] as $key=>$label):?><div class="col-md-2"><label class="form-label"><?=$label?> điểm</label><input class="form-control" type="number" name="<?=$key?>" value="<?=e($settings[$key])?>"></div><?php endforeach;?><div><button class="btn btn-outline-primary">Lưu cài đặt</button></div></form></div></div><?php endif;?>
<div class="card mb-3"><div class="card-body"><h5>Đề nghị hoàn tiền ăn</h5><p class="small text-muted">GVCN nhập số bữa có căn cứ được hoàn. Quản trị duyệt và xác nhận đã trả; khi xác nhận, khoản chi được ghi một lần vào sổ thu chi. Đơn giá lấy tại lúc lập đề nghị.</p><div class="alert alert-light border small">Tiền hoàn = bữa sáng × <?=number_format((float)$settings['rate_sang'],0,',','.')?> đ + bữa trưa × <?=number_format((float)$settings['rate_trua'],0,',','.')?> đ + bữa tối × <?=number_format((float)$settings['rate_toi'],0,',','.')?> đ. Chỉ nhập bữa đủ căn cứ được hoàn.</div><form method="post" class="row g-2 align-items-end"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="refund_add"><input type="hidden" name="refund_month" value="<?=e($month)?>"><input type="hidden" name="return_tab" value="meals"><div class="col-md-3"><label class="form-label">Học sinh</label><select class="form-select" name="student_id"><?php foreach($students as $s):?><option value="<?=e($s['id'])?>"><?=e($s['name'])?></option><?php endforeach;?></select></div><?php foreach(['sang'=>'Sáng','trua'=>'Trưa','toi'=>'Tối'] as $key=>$label):?><div class="col-md-1 col-4"><label class="form-label"><?=$label?></label><input class="form-control" type="number" min="0" max="31" name="meals_<?=$key?>" value="0"></div><?php endforeach;?><div class="col-md-4"><label class="form-label">Lý do / căn cứ</label><input class="form-control" name="note" required maxlength="500"></div><div><button class="btn btn-primary">Gửi đề nghị</button></div></form><div class="table-responsive mt-3"><table class="table table-sm table-striped cmh-table"><thead><tr><th>Học sinh</th><th>Bữa S/T/T</th><th>Tiền hoàn</th><th>Lý do</th><th>Trạng thái</th><th>Xử lý</th></tr></thead><tbody><?php foreach($refunds as $r):?><tr><td><?=e($studentMap[$r['student_id']]['name']??'Đã chuyển lớp')?></td><td><?=$r['meals_sang']?>/<?=$r['meals_trua']?>/<?=$r['meals_toi']?></td><td><?=number_format((float)$r['amount'],0,',','.')?> đ</td><td><?=e($r['note'])?></td><td><?=e(['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối','paid'=>'Đã trả'][$r['status']]??$r['status'])?></td><td><?php if($admin&&in_array($r['status'],['pending','approved'],true)):?><form method="post" class="d-flex gap-1"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="refund_decide"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="return_tab" value="meals"><?php if($r['status']==='pending'):?><button class="btn btn-sm btn-outline-success" name="status" value="approved">Duyệt</button><button class="btn btn-sm btn-outline-danger" name="status" value="rejected">Từ chối</button><?php else:?><button class="btn btn-sm btn-success" name="status" value="paid" onclick="return confirm('Xác nhận đã trả tiền và ghi khoản chi?')">Đã trả</button><?php endif;?></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div></div>
<div class="card"><div class="card-body"><h5>Sổ thu chi · <?=e($month)?></h5><form method="post" class="row g-2 align-items-end mb-3"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="class_id" value="<?=e($classId)?>"><input type="hidden" name="action" value="ledger_add"><input type="hidden" name="return_tab" value="meals"><div class="col-md-2"><label class="form-label">Ngày</label><input class="form-control" type="date" name="entry_date" value="<?=$today?>"></div><div class="col-md-2"><label class="form-label">Loại</label><select class="form-select" name="kind"><option value="thu">Thu</option><option value="chi">Chi</option></select></div><div class="col-md-2"><label class="form-label">Số tiền (đ)</label><input class="form-control" type="number" min="1" name="amount" required></div><div class="col-md-4"><label class="form-label">Nội dung</label><input class="form-control" name="description" required maxlength="500"></div><div class="col-md-2"><button class="btn btn-primary">Ghi sổ</button></div><div class="col-md-4"><select class="form-select" name="ledger_student_id"><option value="">Chung cả lớp</option><?php foreach($students as $s):?><option value="<?=e($s['id'])?>"><?=e($s['name'])?></option><?php endforeach;?></select></div></form><?php $income=(float)$ledgerTotals['income'];$expense=(float)$ledgerTotals['expense'];?><p class="fw-semibold">Trong tháng: Thu <?=number_format($income,0,',','.')?> đ · Chi <?=number_format($expense,0,',','.')?> đ · Chênh lệch <?=number_format($income-$expense,0,',','.')?> đ</p><div class="table-responsive"><table class="table table-striped table-sm cmh-table"><thead><tr><th>Ngày</th><th>Loại</th><th>Số tiền</th><th>Nội dung</th><th>Học sinh</th><th>Người ghi</th></tr></thead><tbody><?php foreach($ledger as $r):?><tr><td><?=e($r['entry_date'])?></td><td><?=e($r['kind'])?></td><td><?=number_format((float)$r['amount'],0,',','.')?></td><td><?=e($r['description'])?></td><td><?=e($studentMap[$r['student_id']]['name']??'')?></td><td><?=e($r['created_by'])?></td></tr><?php endforeach;?></tbody></table></div></div></div>
<?php endif;endif;endif;?></div>
<?php require defined('CDS_HOMEROOM_STANDALONE') ? dirname(__DIR__).'/includes/homeroom_footer.php' : __DIR__.'/includes/footer.php'; ?>
