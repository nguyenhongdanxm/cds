<?php
/** Sửa hồ sơ chỉ điều chỉnh chênh lệch thuốc, không cấp thuốc lần thứ hai. */
$healthId=trim((string)($_POST['id']??''));
$healthBack=BASE_URL.'noitru.php?tab=health&health_view=record'.($healthId!==''?'&edit='.rawurlencode($healthId):'');
$healthLock=fopen(NOITRU_MEDICINES.'.health.lock','c');
if(!$healthLock || !flock($healthLock,LOCK_EX)){flash('Không khóa được dữ liệu y tế.','danger');header('Location: '.$healthBack);exit;}
$healthSnapshots=[];
try {
    $previous=null;
    if($healthId!=='') {
        foreach(noitru_health_all() as $r)if((string)($r['id']??'')===$healthId){$previous=$r;break;}
        if(!$previous || !can_class($previous['class_name']??''))throw new RuntimeException('Không có quyền sửa hồ sơ này.');
    }
    $sid=trim((string)($_POST['student_id']??''));
    noitru_require_student_scope($sid);
    if($previous && (string)$previous['student_id']!==$sid)throw new RuntimeException('Không đổi học sinh của hồ sơ đã lưu. Hãy tạo hồ sơ mới.');
    $student=null;foreach(noitru_boarders_on_date((string)($_POST['date']??date('Y-m-d'))) as $s)if((string)$s['id']===$sid){$student=$s;break;}
    $diagnosis=trim((string)($_POST['diagnosis']??''));
    $date=trim((string)($_POST['date']??''));
    $valid=DateTime::createFromFormat('!Y-m-d',$date);
    if(!$student || $diagnosis==='' || !$valid || $valid->format('Y-m-d')!==$date)throw new RuntimeException('Hãy chọn học sinh, ngày hợp lệ và nhập chẩn đoán.');
    $type=(string)($_POST['type']??'medicine');
    if(!in_array($type,['medicine','first_aid','hospital','family_pickup'],true))throw new RuntimeException('Hình thức xử lý không hợp lệ.');
    $old=[];foreach((array)($previous['medicines']??[]) as $m){$id=(string)$m['id'];$old[$id]=($old[$id]??0)+(int)$m['quantity'];}
    $new=[];
    if($type==='medicine')foreach((array)($_POST['medicine_id']??[]) as $i=>$id){
        $id=trim((string)$id);$qty=(int)($_POST['medicine_qty'][$i]??0);
        if($id==='' || $qty<1)throw new RuntimeException('Hãy chọn thuốc và số lượng lớn hơn 0.');
        $new[$id]=($new[$id]??0)+$qty;
    }
    $items=[];$deltas=[];
    foreach(array_unique(array_merge(array_keys($old),array_keys($new))) as $id){
        $delta=($new[$id]??0)-($old[$id]??0);$m=noitru_medicine_find($id);
        if(!$m && $delta!==0)throw new RuntimeException('Thuốc của hồ sơ đã bị xóa; cần khôi phục thuốc trước khi đổi số lượng.');
        if($delta>0 && (!$m || (int)$m['quantity']<$delta))throw new RuntimeException('Thuốc '.($m['name']??$id).' không đủ tồn kho.');
        if(($new[$id]??0)>0){
            $source=$m; if(!$source)foreach((array)$previous['medicines'] as $prior)if((string)$prior['id']===$id){$source=$prior;break;}
            $items[]=['id'=>$id,'name'=>$source['name']??'','unit'=>$source['unit']??'','quantity'=>$new[$id]];
        }
        if($delta!==0)$deltas[$id]=$delta;
    }
    foreach([NOITRU_MEDICINES,NOITRU_MEDICINE_TX,NOITRU_HEALTH] as $file)$healthSnapshots[$file]=load_json($file,[]);
    foreach($deltas as $id=>$delta)noitru_medicine_adjust($id,-$delta,$delta>0?'issue':'correction_return','Điều chỉnh hồ sơ y tế '.($healthId?:'mới'),$user['name']??'');
    $recordId=noitru_health_save(['id'=>$healthId,'student_id'=>$sid,'student_name'=>$student['name']??'','class_name'=>$student['class_name']??'','date'=>$date,'type'=>$type,'diagnosis'=>$diagnosis,'treatment'=>trim((string)($_POST['treatment']??'')),'medicines'=>$items,'parent_contacted'=>!empty($_POST['parent_contacted']),'note'=>trim((string)($_POST['note']??'')),'by'=>$user['name']??'']);
    flash($healthId!==''?'Đã cập nhật hồ sơ và điều chỉnh chênh lệch thuốc.':'Đã lưu hồ sơ. Có thể tiếp tục sửa thông tin.');
    $healthBack=BASE_URL.'noitru.php?tab=health&health_view=record&edit='.rawurlencode($recordId).'&date='.rawurlencode($date);
} catch(Throwable $ex){
    foreach($healthSnapshots as $file=>$rows)if(!save_json($file,$rows))error_log('Health rollback failed: '.$file);
    if($healthSnapshots && function_exists('cds_health_pending_mark'))cds_health_pending_mark();
    flash($ex->getMessage(),'danger');
} finally {flock($healthLock,LOCK_UN);fclose($healthLock);}
header('Location: '.$healthBack);exit;
