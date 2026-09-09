<?php
if (!defined('DASHBOARD_OPERATION_SCHEDULE')) define('DASHBOARD_OPERATION_SCHEDULE', DATA_PATH . '/dashboard_operation_schedule.json');
function cds_operation_groups(): array { return ['management'=>'Ban giám hiệu','departments'=>'Các tổ/bộ phận','youth'=>'Đoàn – Đội']; }
function cds_operation_assignments(): array {$rows=load_json(DASHBOARD_OPERATION_SCHEDULE,[]);return is_array($rows)?array_values(array_filter($rows,'is_array')):[];}
function cds_operation_save_assignments(array $rows): bool {return save_json(DASHBOARD_OPERATION_SCHEDULE,array_values($rows));}
function cds_operation_add_assignment(array $data): array {
    $groups=cds_operation_groups();$group=trim((string)($data['group']??''));$teacherId=trim((string)($data['teacher_id']??''));$teacherName=trim((string)($data['teacher_name']??''));$start=trim((string)($data['start_date']??''));$end=trim((string)($data['end_date']??''));$weekdays=array_values(array_unique(array_filter(array_map('intval',(array)($data['weekdays']??[])),fn($day)=>$day>=1&&$day<=7)));sort($weekdays);
    if(!isset($groups[$group])||$teacherId===''||$teacherName===''||!$weekdays||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)||$start>$end)return[false,'Thông tin phân công hiển thị chưa hợp lệ.'];
    $rows=cds_operation_assignments();$rows[]=['id'=>'ops_'.bin2hex(random_bytes(6)),'group'=>$group,'teacher_id'=>$teacherId,'teacher_name'=>$teacherName,'weekdays'=>$weekdays,'start_date'=>$start,'end_date'=>$end,'note'=>trim((string)($data['note']??'')),'created_at'=>date('c'),'created_by'=>trim((string)($data['created_by']??''))];
    return[cds_operation_save_assignments($rows),'Đã thêm lịch hiển thị trên trang Tổng quan.'];
}
function cds_operation_delete_assignment(string $id): bool {$rows=cds_operation_assignments();return cds_operation_save_assignments(array_values(array_filter($rows,fn($row)=>(string)($row['id']??'')!==$id)));}
function cds_operation_for_date(string $date): array {
    $result=array_fill_keys(array_keys(cds_operation_groups()),[]);$weekday=(int)date('N',strtotime($date));
    foreach(cds_operation_assignments()as$row){$group=(string)($row['group']??'');if(!isset($result[$group])||$date<(string)($row['start_date']??'')||$date>(string)($row['end_date']??''))continue;if(!in_array($weekday,array_map('intval',(array)($row['weekdays']??[])),true))continue;$name=trim((string)($row['teacher_name']??''));if($name!==''&&!in_array($name,$result[$group],true))$result[$group][]=$name;}
    return$result;
}
