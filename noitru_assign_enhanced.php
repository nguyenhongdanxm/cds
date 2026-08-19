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

function nt_assign_json($payload,int $status=200): never {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function nt_assign_error_detail(string $message): array {
    $detail=['row'=>'','student'=>'','class'=>'','room'=>'','error'=>$message];
    if(preg_match('/^(.+?)\s*·\s*dòng\s+(\d+)\s*:\s*(.+)$/u',$message,$m)){
        $detail['room']=trim($m[1]);
        $detail['row']=(int)$m[2];
        $detail['error']=trim($m[3]);
        if(preg_match('/học sinh\s+(.+?)\s*\(([^)]+)\)/u',$detail['error'],$x)){
            $detail['student']=trim($x[1]);$detail['class']=trim($x[2]);
        }elseif(preg_match('/của\s+(.+?)(?:\.|$)/u',$detail['error'],$x)){
            $detail['student']=trim($x[1]);
        }elseif(preg_match('/học sinh\s+(.+?)\s+đã/u',$detail['error'],$x)){
            $detail['student']=trim($x[1]);
        }
    }
    return $detail;
}

/** Bổ sung lớp từ dòng Excel/CSDL để bảng lỗi không hiện "—" khi đã nhận diện được học sinh. */
function nt_assign_enrich_error_details(array $details,array $parsed,array $students): array {
    $excelRows=[];
    foreach((array)($parsed['rooms']??[]) as $room=>$rows){
        foreach((array)$rows as $row){
            $key=(string)$room.'|'.(string)($row['row']??'');
            $excelRows[$key]=$row;
        }
    }
    $byName=[];
    foreach($students as $student){
        $nameKey=nt_room_excel_norm($student['name']??'');
        if($nameKey!=='')$byName[$nameKey][]=$student;
    }
    foreach($details as &$detail){
        $rowKey=(string)($detail['room']??'').'|'.(string)($detail['row']??'');
        $excel=$excelRows[$rowKey]??null;
        if($excel){
            if(trim((string)($detail['student']??''))==='')$detail['student']=trim((string)($excel['name']??''));
            if(trim((string)($detail['class']??''))==='')$detail['class']=trim((string)($excel['class']??''));
        }
        $studentName=trim((string)($detail['student']??''));
        if($studentName==='')continue;
        $candidates=$byName[nt_room_excel_norm($studentName)]??[];
        $excelDob=$excel?nt_room_excel_date($excel['dob']??''):'';
        if(count($candidates)>1&&$excelDob!==''){
            $dobMatches=array_values(array_filter($candidates,static fn($s)=>nt_room_excel_date($s['dob']??'')===$excelDob));
            if(count($dobMatches)===1)$candidates=$dobMatches;
        }
        if(count($candidates)===1){
            $dbClass=trim((string)($candidates[0]['class_name']??''));
            if($dbClass!=='')$detail['class']=$dbClass;
        }
    }
    unset($detail);
    return $details;
}

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') nt_assign_json(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405);
$action=(string)($_POST['action']??'');
$data=noitru_assignments_data();

if($action==='create_groups_append'){
    $count=max(1,min(200,(int)($_POST['group_count']??1)));
    $prefix=(string)($_POST['prefix']??'');
    $prefix=trim($prefix);
    if($prefix==='')$prefix='Phòng';
    $separator=preg_match('/\s$/u',(string)($_POST['prefix']??''))?' ':' ';
    $base=rtrim($prefix).$separator;
    $capacity=max(1,min(100,(int)($_POST['default_capacity']??8)));
    $gender=(string)($_POST['default_room_gender']??'Linh hoạt');
    if(!in_array($gender,['Xen kẽ','Nam','Nữ','Linh hoạt'],true))$gender='Linh hoạt';

    $existing=array_values(array_unique(array_filter(array_map('strval',(array)($data['room_names']??[])))));
    $used=array_fill_keys($existing,true);
    $max=0;
    foreach($existing as $name){
        if(preg_match('/^'.preg_quote($base,'/').'(\d+)$/u',$name,$m))$max=max($max,(int)$m[1]);
    }
    $added=[];$number=$max+1;
    while(count($added)<$count){
        $name=$base.$number++;
        if(isset($used[$name]))continue;
        $used[$name]=true;$added[]=$name;
        $data['room_names'][]=$name;
        $data['room_capacities'][$name]=$capacity;
        $i=count($added)-1;
        $data['room_genders'][$name]=$gender==='Xen kẽ'?($i%2===0?'Nam':'Nữ'):($gender==='Linh hoạt'?'Linh hoạt':$gender);
    }
    $data['history'][]=['mode'=>'rooms','action'=>'create_groups_append','by'=>$by,'at'=>date('c'),'count'=>count($added),'names'=>$added];
    noitru_assignments_save($data,$by);
    nt_assign_json(['ok'=>true,'message'=>'Đã thêm '.count($added).' phòng mới, giữ nguyên danh sách phòng đã có.','added'=>$added]);
}

if($action==='import_rooms_excel_detailed'){
    if(!isset($_FILES['room_excel'])||($_FILES['room_excel']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
        nt_assign_json(['ok'=>false,'message'=>'Hãy chọn file Excel XLSX hợp lệ.','errors'=>[['row'=>'','student'=>'','class'=>'','room'=>'','error'=>'Không nhận được file Excel hoặc quá trình tải file bị lỗi.']]]);
    }
    $upload=$_FILES['room_excel'];
    $extension=mb_strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION),'UTF-8');
    $size=(int)($upload['size']??0);
    if($extension!=='xlsx')nt_assign_json(['ok'=>false,'message'=>'Chỉ chấp nhận file .xlsx.','errors'=>[['row'=>'','student'=>'','class'=>'','room'=>'','error'=>'Định dạng file không phải XLSX.']]]);
    if($size<=0||$size>10*1024*1024)nt_assign_json(['ok'=>false,'message'=>'File Excel không hợp lệ.','errors'=>[['row'=>'','student'=>'','class'=>'','room'=>'','error'=>'Dung lượng file phải từ 1 byte đến 10 MB.']]]);

    $parsed=nt_room_excel_parse((string)$upload['tmp_name']);
    if(empty($parsed['ok'])){
        $details=array_map('nt_assign_error_detail',(array)($parsed['errors']??['File không hợp lệ.']));
        nt_assign_json(['ok'=>false,'message'=>'File Excel có lỗi dữ liệu. Chưa có dữ liệu nào được lưu.','errors'=>$details]);
    }

    $boarders=noitru_assignment_apply(noitru_boarders_live());
    $replaceAll=(($_POST['import_scope']??'merge')==='replace_all');
    $result=nt_room_excel_match_and_apply($parsed,$boarders,$data,$replaceAll);
    if(empty($result['ok'])){
        $details=array_map('nt_assign_error_detail',(array)($result['errors']??[]));
        $details=nt_assign_enrich_error_details($details,$parsed,$boarders);
        nt_assign_json(['ok'=>false,'message'=>'Không thể nhập vì có dòng không khớp CSDL. Chưa có dữ liệu nào được lưu.','errors'=>$details]);
    }

    $data['history'][]=['mode'=>'rooms','action'=>'import_excel','by'=>$by,'at'=>date('c'),'count'=>$result['count'],'rooms'=>$result['rooms'],'replace_all'=>$replaceAll];
    noitru_assignments_save($data,$by);
    nt_assign_json(['ok'=>true,'message'=>'Đã nhập chia phòng từ Excel: '.$result['count'].' học sinh vào '.$result['rooms'].' phòng.','count'=>$result['count'],'rooms'=>$result['rooms']]);
}

nt_assign_json(['ok'=>false,'message'=>'Thao tác không hợp lệ.'],400);
