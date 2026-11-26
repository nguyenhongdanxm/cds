<?php
require_once __DIR__.'/json_store.php';
/** School-local weekly work calendar. No changes to the existing notice archive. */
function ww_path(): string { return DATA_PATH.'/weekly_work_schedule.json'; }
function ww_timezone(): DateTimeZone { return new DateTimeZone('Asia/Ho_Chi_Minh'); }
function ww_date(string $date): ?DateTimeImmutable {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,ww_timezone());
    return $d && $d->format('Y-m-d')===$date && $date>='2000-01-01' && $date<='2100-12-31' ? $d : null;
}
function ww_week(string $date=''): DateTimeImmutable {
    $d=ww_date($date) ?? new DateTimeImmutable('today',ww_timezone());
    return $d->modify('-'.((int)$d->format('N')-1).' days');
}
function ww_sessions(): array { return ['all'=>'Cả ngày','morning'=>'Sáng','afternoon'=>'Chiều','evening'=>'Tối']; }
function ww_allowed(array $user,string $level='edit'): bool {
    if(($user['role']??'')==='viewer')return false;
    return level_rank(permission_access('school.weekly_schedule',$user))>=level_rank($level);
}
function ww_csrf(): string {
    if(empty($_SESSION['weekly_work_csrf']))$_SESSION['weekly_work_csrf']=bin2hex(random_bytes(24));
    return $_SESSION['weekly_work_csrf'];
}
function ww_all(): array {
    $raw=cds_json_load(ww_path(),['items'=>[]]);
    return is_array($raw['items']??null)?array_values(array_filter($raw['items'],'is_array')):[];
}
function ww_rows(array $items,DateTimeImmutable $week): array {
    $start=$week->format('Y-m-d');$end=$week->modify('+6 days')->format('Y-m-d');
    $rows=array_values(array_filter($items,fn($r)=>is_array($r)&&($r['date']??'')>=$start&&($r['date']??'')<=$end));
    $order=array_flip(array_keys(ww_sessions()));
    usort($rows,fn($a,$b)=>[$a['date'],$order[$a['session']]??9,$a['time']??'',$a['id']??'']<=>[$b['date'],$order[$b['session']]??9,$b['time']??'',$b['id']??'']);
    return $rows;
}
function ww_text(array $input,string $key,int $max,bool $required=false): string {
    if(!is_string($input[$key]??''))throw new RuntimeException('Dữ liệu nhập không hợp lệ.');
    $value=trim($input[$key]??'');
    if(($required&&$value==='')||!preg_match('//u',$value)||strlen($value)>$max)throw new RuntimeException('Thiếu nội dung hoặc nội dung quá dài: '.$key.'.');
    return $value;
}
function ww_change(array $input,array $user,string $action): array {
    if(!in_array($action,['save','delete'],true)||!ww_allowed($user,$action==='delete'?'delete':'edit'))throw new RuntimeException('Bạn chưa được cấp quyền thực hiện thao tác này.');
    if(!is_string($input['csrf']??null)||!hash_equals(ww_csrf(),$input['csrf']))throw new RuntimeException('Phiên làm việc không hợp lệ. Hãy tải lại trang.');
    $id=ww_text($input,'id',80);$version=ww_text($input,'version',80);
    $row=[];
    if($action==='save'){
        $date=ww_text($input,'date',10,true);$session=ww_text($input,'session',20,true);$time=ww_text($input,'time',5);$end=ww_text($input,'end_time',5);
        if(!ww_date($date)||!isset(ww_sessions()[$session]))throw new RuntimeException('Ngày hoặc buổi không hợp lệ.');
        foreach([$time,$end] as $t)if($t!==''&&!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$t))throw new RuntimeException('Giờ phải có dạng HH:MM.');
        if($end!==''&&($time===''||$end<$time))throw new RuntimeException('Giờ kết thúc phải từ giờ bắt đầu trở đi.');
        $row=['date'=>$date,'session'=>$session,'time'=>$time,'end_time'=>$end,'content'=>ww_text($input,'content',6000,true),'people'=>ww_text($input,'people',1500),'place'=>ww_text($input,'place',800),'note'=>ww_text($input,'note',2000)];
    }
    $error='Không lưu được lịch. Vui lòng thử lại.';$saved=[];
    $ok=cds_json_update(ww_path(),function($raw)use($id,$version,$row,$action,$user,&$error,&$saved){
        if(!is_array($raw['items']??null)){ $error='Dữ liệu lịch không hợp lệ, không ghi đè.';return false; }
        $items=$raw['items'];$index=null;
        foreach($items as $i=>$old)if(($old['id']??'')===$id&&$id!==''){$index=$i;break;}
        if(($id!==''&&$index===null)||($index!==null&&!hash_equals((string)($items[$index]['version']??''),$version))){$error='Lịch đã được thay đổi. Hãy tải lại trang trước khi sửa hoặc xóa.';return false;}
        if($action==='delete'){
            if($index===null){$error='Không tìm thấy lịch cần xóa.';return false;}
            $saved=$items[$index];array_splice($items,$index,1);
        }else{
            $now=(new DateTimeImmutable('now',ww_timezone()))->format(DATE_ATOM);
            $saved=array_merge($row,['id'=>$id!==''?$id:'work_'.bin2hex(random_bytes(12)),'version'=>bin2hex(random_bytes(16)),'updated_at'=>$now,'updated_by'=>(string)($user['id']??$user['username']??''),'created_at'=>$index!==null?($items[$index]['created_at']??$now):$now]);
            if($index===null)$items[]=$saved;else $items[$index]=$saved;
        }
        $raw['items']=array_values($items);
        $audit=is_array($raw['audit']??null)?$raw['audit']:[];
        $audit[]=['action'=>$action,'id'=>$saved['id'],'at'=>date(DATE_ATOM),'by'=>(string)($user['id']??$user['username']??'')];
        $raw['audit']=array_slice($audit,-200);return $raw;
    },['items'=>[]]);
    if(!$ok)throw new RuntimeException($error);
    return $saved;
}
