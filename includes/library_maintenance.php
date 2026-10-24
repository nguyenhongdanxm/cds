<?php
/** Saved group snapshots preserve the historical scope of an inspection. */
function lib_mt_group(array $eq): array {
    $subject=trim((string)($eq['subject']??''));if($subject==='')$subject=trim((string)($eq['category']??''));
    $normal=mb_strtolower($subject,'UTF-8');
    if(in_array($normal,['dùng chung','thiết bị dùng chung','chung','thiết bị chung'],true))return ['key'=>'common','label'=>'Thiết bị dùng chung'];
    if($subject==='')return ['key'=>'unclassified','label'=>'Thiết bị chưa phân nhóm'];
    return ['key'=>'subject:'.$normal,'label'=>'Thiết bị bộ môn – '.$subject];
}
function lib_mt_groups(array $equipment): array {
    $groups=[];foreach($equipment as $eq){$g=lib_mt_group($eq);$key=$g['key'];if(!isset($groups[$key]))$groups[$key]=$g+['equipment_ids'=>[]];$id=(string)($eq['id']??'');if($id!=='')$groups[$key]['equipment_ids'][]=$id;}
    uasort($groups,fn($a,$b)=>strnatcasecmp($a['label'],$b['label']));return $groups;
}
function lib_mt_labels(array $row,array $equipment): array {
    if(!empty($row['groups']))return array_values(array_map(fn($g)=>(string)$g['label'],$row['groups']));
    foreach($equipment as $eq)if((string)($eq['id']??'')===(string)($row['equipment_id']??''))return [lib_mt_group($eq)['label']];
    return ['Thiết bị theo hồ sơ cũ (chưa xác định nhóm)'];
}
function lib_mt_text(array $request,string $key,int $max=4000): string {
    $value=$request[$key]??'';if(!is_string($value)||mb_strlen($value,'UTF-8')>$max)throw new InvalidArgumentException('Nội dung '.$key.' không hợp lệ hoặc quá dài.');return trim($value);
}
function lib_mt_date(string $value,bool $time=false): string {
    if($value==='')return ''; $format=$time?'Y-m-d\TH:i':'Y-m-d';$d=DateTimeImmutable::createFromFormat('!'.$format,$value);
    if(!$d||$d->format($format)!==$value)throw new InvalidArgumentException('Ngày hoặc giờ không hợp lệ.');return $value;
}
function lib_mt_build(array $request,array $equipment,array $prior,string $author,string $year): array {
    $row=$prior;
    if(!$prior||($request['change_scope']??'')==='1'){
        $groups=lib_mt_groups($equipment);$keys=$request['equipment_groups']??[];
        if(!is_array($keys))throw new InvalidArgumentException('Chọn nhóm thiết bị hợp lệ.');
        if(($request['all_equipment']??'')==='1')$keys=array_keys($groups);
        $selected=[];foreach($keys as $key){if(!is_string($key)||!isset($groups[$key]))throw new InvalidArgumentException('Nhóm thiết bị đã thay đổi. Tải lại trang để chọn lại.');$selected[$key]=$groups[$key];}
        if(!$selected)throw new InvalidArgumentException('Chọn ít nhất một nhóm hoặc tất cả thiết bị.');
        $row['groups']=array_values($selected);$row['equipment_ids']=array_values(array_unique(array_merge(...array_column($selected,'equipment_ids'))));$row['all_equipment']=($request['all_equipment']??'')==='1';
        unset($row['equipment_id'],$row['equipment_name']);
    }
    $types=['Kiểm tra','Bảo dưỡng','Sửa chữa','Kiểm định','Thay thế linh kiện'];$statuses=['Đã lên lịch','Đang thực hiện','Hoàn thành','Tạm hoãn'];
    $type=lib_mt_text($request,'maintenance_type',80);$status=lib_mt_text($request,'maintenance_status',80);
    if(!in_array($type,$types,true)||!in_array($status,$statuses,true))throw new InvalidArgumentException('Loại công việc hoặc trạng thái không hợp lệ.');
    $row['type']=$type;$row['status']=$status;
    foreach(['scheduled_at','completed_at'] as $key)$row[$key]=lib_mt_date(lib_mt_text($request,$key,10));
    if($row['scheduled_at']==='')throw new InvalidArgumentException('Nhập ngày dự kiến.');
    foreach(['started_at','ended_at'] as $key)$row[$key]=lib_mt_date(lib_mt_text($request,$key,16),true);
    if($row['started_at']!==''&&$row['ended_at']!==''&&$row['ended_at']<$row['started_at'])throw new InvalidArgumentException('Giờ kết thúc phải sau giờ bắt đầu.');
    foreach(['provider','inspector','secretary','keeper','place','report_number','basis','participants','result','conclusion','recommendation'] as $key)$row[$key]=lib_mt_text($request,$key);
    $row['note']=lib_mt_text($request,'maintenance_note');$cost=lib_mt_text($request,'cost',30);if($cost!==''&&(!is_numeric($cost)||!is_finite((float)$cost)||(float)$cost<0))throw new InvalidArgumentException('Chi phí không hợp lệ.');$row['cost']=$cost===''?0:(float)$cost;
    if($status==='Hoàn thành'&&($row['inspector']===''||$row['result']===''||$row['completed_at']===''))throw new InvalidArgumentException('Khi hoàn thành phải có ngày hoàn thành, người kiểm tra và kết quả kiểm tra.');
    if(!$prior){$row['id']='mt_'.bin2hex(random_bytes(7));$row['created_at']=date('c');$row['created_by']=$author;$row['school_year']=$year;}
    $row['updated_at']=date('c');$row['updated_by']=$author;return $row;
}
