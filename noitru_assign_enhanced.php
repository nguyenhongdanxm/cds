<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_assignment_store.php';
require_once __DIR__ . '/includes/noitru_room_excel_import.php';

header('Content-Type: application/json; charset=utf-8');
require_login();
require_perm_level('nt.chiaphong','edit');
$user=current_user();
$by=(string)($user['name']??$user['username']??'');

function nt_assign_json($payload,int $status=200): never { http_response_code($status); echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function nt_assign_error_detail(string $message): array {
    $detail=['row'=>'','student'=>'','class'=>'','room'=>'','error'=>$message];
    if(preg_match('/^(.+?)\s*·\s*dòng\s+(\d+)\s*:\s*(.+)$/u',$message,$m)){
        $detail['room']=trim($m[1]);$detail['row']=(int)$m[2];$detail['error']=trim($m[3]);
        if(preg_match('/học sinh\s+(.+?)\s*\(([^)]+)\)/u',$detail['error'],$x)){$detail['student']=trim($x[1]);$detail['class']=trim($x[2]);}
        elseif(preg_match('/của\s+(.+?)(?:\.|$)/u',$detail['error'],$x))$detail['student']=trim($x[1]);
        elseif(preg_match('/học sinh\s+(.+?)\s+đã/u',$detail['error'],$x))$detail['student']=trim($x[1]);
    }
    return $detail;
}
function nt_assign_vn_date($value): string { $iso=nt_room_excel_date($value); if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$iso,$m))return $m[3].'/'.$m[2].'/'.$m[1]; return trim((string)$value); }
function nt_assign_student_public(array $s): array { return ['id'=>(string)($s['id']??''),'name'=>(string)($s['name']??''),'class'=>(string)($s['class_name']??''),'dob'=>nt_assign_vn_date($s['dob']??''),'gender'=>noitru_assignment_gender($s)]; }

function nt_assign_excel_rows(array $parsed): array {
    $out=[];foreach((array)($parsed['rooms']??[]) as $room=>$rows)foreach((array)$rows as $row){$key=(string)$room.'|'.(string)($row['row']??'');$out[$key]=$row+['room'=>$room];}return $out;
}
function nt_assign_enrich_error_details(array $details,array $parsed,array $students): array {
    $excelRows=nt_assign_excel_rows($parsed);$byName=[];$byNameClass=[];
    foreach($students as $student){$nk=nt_room_excel_norm($student['name']??'');$ck=nt_room_excel_norm($student['class_name']??'');if($nk!==''){$byName[$nk][]=$student;$byNameClass[$nk.'|'.$ck][]=$student;}}
    foreach($details as &$detail){
        $rowKey=(string)($detail['room']??'').'|'.(string)($detail['row']??'');$excel=$excelRows[$rowKey]??null;
        $excelName=trim((string)($excel['name']??$detail['student']??''));$excelClass=trim((string)($excel['class']??$detail['class']??''));$excelDob=trim((string)($excel['dob']??''));$excelGender=trim((string)($excel['gender']??''));$excelRoom=trim((string)($excel['room']??$detail['room']??''));$excelNote=trim((string)($excel['note']??''));$rowNumber=(int)($detail['row']??0);
        $candidates=[];if($excelName!==''){$nk=nt_room_excel_norm($excelName);$ck=nt_room_excel_norm($excelClass);if($excelClass!==''&&isset($byNameClass[$nk.'|'.$ck]))$candidates=$byNameClass[$nk.'|'.$ck];if(!$candidates)$candidates=$byName[$nk]??[];}
        $db=count($candidates)===1?$candidates[0]:null;
        $detail['row_key']=$rowKey;$detail['student']=$excelName;$detail['class']=$db?trim((string)($db['class_name']??'')):$excelClass;
        $detail['excel']=['stt'=>$rowNumber>1?$rowNumber-1:'','name'=>$excelName,'class'=>$excelClass,'dob'=>nt_assign_vn_date($excelDob),'gender'=>$excelGender,'room'=>$excelRoom,'note'=>$excelNote];
        $detail['database']=$db?nt_assign_student_public($db):['id'=>'','name'=>'','class'=>'','dob'=>'','gender'=>''];
        $detail['candidates']=array_values(array_map('nt_assign_student_public',array_slice($candidates,0,20)));
        $diff=[];if($db){if(nt_room_excel_norm($excelName)!==nt_room_excel_norm($db['name']??''))$diff[]='Họ và tên';if(nt_room_excel_norm($excelClass)!==nt_room_excel_norm($db['class_name']??''))$diff[]='Lớp';if($excelDob!==''&&nt_room_excel_date($excelDob)!==nt_room_excel_date($db['dob']??''))$diff[]='Ngày sinh';if($excelGender!==''&&nt_room_excel_norm($excelGender)!==nt_room_excel_norm(noitru_assignment_gender($db)))$diff[]='Giới tính';}
        $detail['different_fields']=$diff;$detail['corrected_tsv']=implode("\t",[(string)($detail['excel']['stt']??''),(string)($db['name']??$excelName),(string)($db['class_name']??$excelClass),(string)($db?nt_assign_vn_date($db['dob']??''):$detail['excel']['dob']),(string)($db?noitru_assignment_gender($db):$excelGender),$excelRoom,$excelNote]);
    }unset($detail);return $details;
}
function nt_assign_make_pending(array $parsed,bool $replaceAll,array $details): string {
    if(!isset($_SESSION['nt_room_pending_import'])||!is_array($_SESSION['nt_room_pending_import']))$_SESSION['nt_room_pending_import']=[];
    foreach($_SESSION['nt_room_pending_import'] as $k=>$v)if((int)($v['created']??0)<time()-3600)unset($_SESSION['nt_room_pending_import'][$k]);
    $token=bin2hex(random_bytes(18));$required=[];foreach($details as $d){$key=(string)($d['row_key']??'');if($key!=='')$required[$key]=true;}
    $_SESSION['nt_room_pending_import'][$token]=['created'=>time(),'parsed'=>$parsed,'replace_all'=>$replaceAll,'required'=>$required];return $token;
}
function nt_assign_apply_resolved(array $pending,array $students,array &$data,array $resolutions): array {
    $parsed=(array)($pending['parsed']??[]);$required=(array)($pending['required']??[]);$byId=[];$index=[];
    foreach($students as $s){$id=(string)($s['id']??'');if($id!=='')$byId[$id]=$s;$key=nt_room_excel_norm($s['name']??'').'|'.nt_room_excel_norm($s['class_name']??'');$index[$key][]=$s;}
    $assign=[];$notes=[];$seen=[];$errors=[];$roomCounts=[];
    foreach((array)($parsed['rooms']??[]) as $room=>$rows)foreach((array)$rows as $row){
        $rowKey=(string)$room.'|'.(string)($row['row']??'');$student=null;$chosen=(string)($resolutions[$rowKey]??'');
        if(isset($required[$rowKey])){
            if($chosen===''||!isset($byId[$chosen])){$errors[]='Dòng '.$row['row'].' ('.$row['name'].'): chưa chọn đúng học sinh CSDL.';continue;}$student=$byId[$chosen];
            if(nt_room_excel_norm($student['name']??'')!==nt_room_excel_norm($row['name']??'')){$errors[]='Dòng '.$row['row'].': học sinh được chọn không cùng họ tên với Excel.';continue;}
        }else{
            $key=nt_room_excel_norm($row['name']??'').'|'.nt_room_excel_norm($row['class']??'');$matches=$index[$key]??[];if(count($matches)>1&&($row['dob']??'')!=='')$matches=array_values(array_filter($matches,static fn($s)=>nt_room_excel_date($s['dob']??'')===nt_room_excel_date($row['dob']??'')));
            if(count($matches)!==1){$errors[]='Dòng '.$row['row'].' ('.$row['name'].'): không xác định được học sinh.';continue;}$student=$matches[0];
        }
        $id=(string)($student['id']??'');if(isset($seen[$id])){$errors[]='Dòng '.$row['row'].' ('.$row['name'].'): cùng học sinh đã xuất hiện ở phòng '.$seen[$id].'.';continue;}
        $seen[$id]=$room;$assign[$id]=$room;$notes[$id]=trim((string)($row['note']??''));$roomCounts[$room]=($roomCounts[$room]??0)+1;
    }
    if($errors)return ['ok'=>false,'errors'=>$errors];
    $replaceAll=(bool)($pending['replace_all']??false);$finalCounts=[];if(!$replaceAll)foreach($students as $s){$id=(string)($s['id']??'');if(isset($assign[$id]))continue;$room=trim((string)($s['room_ktx']??''));if($room!=='')$finalCounts[$room]=($finalCounts[$room]??0)+1;}
    if(!isset($data['room_notes'])||!is_array($data['room_notes']))$data['room_notes']=[];if($replaceAll)foreach($students as $s){$id=(string)($s['id']??'');$data['rooms'][$id]='';unset($data['room_notes'][$id]);}
    foreach($assign as $id=>$room){$finalCounts[$room]=($finalCounts[$room]??0)+1;$data['rooms'][$id]=$room;if($notes[$id]!=='')$data['room_notes'][$id]=$notes[$id];else unset($data['room_notes'][$id]);}
    foreach($roomCounts as $room=>$count){if(!in_array($room,$data['room_names']??[],true))$data['room_names'][]=$room;$data['room_capacities'][$room]=max((int)($data['room_capacities'][$room]??8),(int)($finalCounts[$room]??$count));if(!isset($data['room_genders'][$room]))$data['room_genders'][$room]='Linh hoạt';}
    return ['ok'=>true,'count'=>count($assign),'rooms'=>count($roomCounts)];
}

if(($_SERVER['REQUEST_METHOD']??'')!=='POST')nt_assign_json(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405);
$action=(string)($_POST['action']??'');$data=noitru_assignments_data();
if($action==='create_groups_append'){
    $count=max(1,min(200,(int)($_POST['group_count']??1)));$prefix=trim((string)($_POST['prefix']??''));if($prefix==='')$prefix='Phòng';$base=rtrim($prefix).' ';$capacity=max(1,min(100,(int)($_POST['default_capacity']??8)));$gender=(string)($_POST['default_room_gender']??'Linh hoạt');if(!in_array($gender,['Xen kẽ','Nam','Nữ','Linh hoạt'],true))$gender='Linh hoạt';
    $existing=array_values(array_unique(array_filter(array_map('strval',(array)($data['room_names']??[])))));$used=array_fill_keys($existing,true);$max=0;foreach($existing as $name)if(preg_match('/^'.preg_quote($base,'/').'(\d+)$/u',$name,$m))$max=max($max,(int)$m[1]);$added=[];$number=$max+1;
    while(count($added)<$count){$name=$base.$number++;if(isset($used[$name]))continue;$used[$name]=true;$added[]=$name;$data['room_names'][]=$name;$data['room_capacities'][$name]=$capacity;$i=count($added)-1;$data['room_genders'][$name]=$gender==='Xen kẽ'?($i%2===0?'Nam':'Nữ'):($gender==='Linh hoạt'?'Linh hoạt':$gender);}
    $data['history'][]=['mode'=>'rooms','action'=>'create_groups_append','by'=>$by,'at'=>date('c'),'count'=>count($added),'names'=>$added];noitru_assignments_save($data,$by);nt_assign_json(['ok'=>true,'message'=>'Đã thêm '.count($added).' phòng mới, giữ nguyên danh sách phòng đã có.','added'=>$added]);
}
if($action==='resolve_import'){
    $token=(string)($_POST['token']??'');$pending=$_SESSION['nt_room_pending_import'][$token]??null;if(!is_array($pending))nt_assign_json(['ok'=>false,'message'=>'Phiên đối chiếu đã hết hạn. Hãy tải lại file Excel.']);
    $res=json_decode((string)($_POST['resolutions']??'{}'),true);if(!is_array($res))$res=[];$boarders=noitru_assignment_apply(noitru_boarders_live());$result=nt_assign_apply_resolved($pending,$boarders,$data,$res);
    if(empty($result['ok']))nt_assign_json(['ok'=>false,'message'=>'Chưa thể nhập. Hãy kiểm tra các dòng đã chọn.','errors'=>$result['errors']??[]]);
    $data['history'][]=['mode'=>'rooms','action'=>'import_excel_confirmed','by'=>$by,'at'=>date('c'),'count'=>$result['count'],'rooms'=>$result['rooms'],'replace_all'=>(bool)($pending['replace_all']??false)];noitru_assignments_save($data,$by);unset($_SESSION['nt_room_pending_import'][$token]);nt_assign_json(['ok'=>true,'message'=>'Đã xác nhận nhận dạng và nhập '.$result['count'].' học sinh vào '.$result['rooms'].' phòng.']);
}
if($action==='import_rooms_excel_detailed'){
    if(!isset($_FILES['room_excel'])||($_FILES['room_excel']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)nt_assign_json(['ok'=>false,'message'=>'Hãy chọn file Excel XLSX hợp lệ.','errors'=>[['error'=>'Không nhận được file Excel hoặc quá trình tải file bị lỗi.']]]);
    $upload=$_FILES['room_excel'];$extension=mb_strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION),'UTF-8');$size=(int)($upload['size']??0);if($extension!=='xlsx')nt_assign_json(['ok'=>false,'message'=>'Chỉ chấp nhận file .xlsx.','errors'=>[['error'=>'Định dạng file không phải XLSX.']]]);if($size<=0||$size>10*1024*1024)nt_assign_json(['ok'=>false,'message'=>'File Excel không hợp lệ.','errors'=>[['error'=>'Dung lượng file phải từ 1 byte đến 10 MB.']]]);
    $parsed=nt_room_excel_parse((string)$upload['tmp_name']);$boarders=noitru_assignment_apply(noitru_boarders_live());$replaceAll=(($_POST['import_scope']??'merge')==='replace_all');
    if(empty($parsed['ok'])){$details=array_map('nt_assign_error_detail',(array)($parsed['errors']??['File không hợp lệ.']));$details=nt_assign_enrich_error_details($details,$parsed,$boarders);nt_assign_json(['ok'=>false,'message'=>'File Excel có lỗi cấu trúc/dữ liệu bắt buộc. Hãy sửa file trước khi nhập.','errors'=>$details,'resolvable'=>false]);}
    $testData=$data;$result=nt_room_excel_match_and_apply($parsed,$boarders,$testData,$replaceAll);
    if(empty($result['ok'])){$details=array_map('nt_assign_error_detail',(array)($result['errors']??[]));$details=nt_assign_enrich_error_details($details,$parsed,$boarders);$token=nt_assign_make_pending($parsed,$replaceAll,$details);$resolvable=true;foreach($details as $d)if(empty($d['candidates'])){$resolvable=false;break;}nt_assign_json(['ok'=>false,'message'=>'Có dữ liệu không khớp CSDL. Bạn có thể chọn đúng học sinh để xác nhận rồi nhập.','errors'=>$details,'resolvable'=>$resolvable,'token'=>$token]);}
    $data=$testData;$data['history'][]=['mode'=>'rooms','action'=>'import_excel','by'=>$by,'at'=>date('c'),'count'=>$result['count'],'rooms'=>$result['rooms'],'replace_all'=>$replaceAll];noitru_assignments_save($data,$by);nt_assign_json(['ok'=>true,'message'=>'Đã nhập chia phòng từ Excel: '.$result['count'].' học sinh vào '.$result['rooms'].' phòng.']);
}
nt_assign_json(['ok'=>false,'message'=>'Thao tác không hợp lệ.'],400);
