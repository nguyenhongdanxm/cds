<?php
/** Calendar-day notice timing in the school's timezone; never deletes records. */
function cds_notice_date($value): string {
    if(!is_string($value))return ''; $value=trim($value);
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$value,$m)||$m[1]<1900||$m[1]>2199||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return '';
    return $value;
}
function cds_notice_settings(array $input): array {
    if(array_key_exists('hide_date',$input)||($input['notice_timing_version']??0)==2){
        $published=cds_notice_date($input['date']??'');$event=cds_notice_date($input['event_date']??'');$hide=$input['hide_date']??'';
        if($published===''||$event===''||!is_string($hide)||($hide!==''&&cds_notice_date($hide)==='')||($hide!==''&&$hide<$published))throw new InvalidArgumentException('Vui lòng nhập đúng ngày ban hành, ngày diễn ra và ngày ẩn (ngày ẩn không trước ngày ban hành).');
        return ['event_date'=>$event,'hide_date'=>$hide,'notice_timing_version'=>2];
    }
    $event=$input['event_date']??'';$days=$input['hide_after_days']??'7';
    if(!is_string($event)||($event!==''&&cds_notice_date($event)==='')||(!is_string($days)&&!is_int($days))||!preg_match('/^\d{1,4}$/D',(string)$days)||(int)$days>3650)throw new InvalidArgumentException('Ngày diễn ra không hợp lệ hoặc số ngày tự ẩn phải từ 0 đến 3650.');
    return ['event_date'=>trim($event),'hide_after_days'=>(int)$days];
}
function cds_notice_timing(array $row,?string $today=null): array {
    $zone=new DateTimeZone('Asia/Ho_Chi_Minh');$now=$today===null?new DateTimeImmutable('today',$zone):new DateTimeImmutable($today,$zone);$now=$now->setTime(0,0);
    $explicit=array_key_exists('hide_date',$row)||($row['notice_timing_version']??0)==2;
    $published=cds_notice_date($row['date']??'');
    $event=cds_notice_date($row['event_date']??'');if(!$explicit&&$event==='')$event=cds_notice_date($row['due_date']??'');
    if(!$explicit&&$event===''&&(!empty($row['day_to'])||!empty($row['day_from']))){$day=min((int)$now->format('t'),max(1,min(31,(int)(($row['day_to']??0)?:$row['day_from']))));$event=$now->setDate((int)$now->format('Y'),(int)$now->format('m'),$day)->format('Y-m-d');}
    if(!$explicit&&$event==='')foreach(['date','start_date','publish_date']as$key){$event=cds_notice_date($row[$key]??'');if($event!=='')break;}
    if($event==='')return [];
    $date=new DateTimeImmutable($event,$zone);$delta=(int)$now->diff($date)->format('%r%a');$keep=isset($row['hide_after_days'])&&is_numeric($row['hide_after_days'])?max(0,min(3650,(int)$row['hide_after_days'])):7;
    $hide=$explicit?cds_notice_date($row['hide_date']??''):$date->modify('+'.($keep+1).' days')->format('Y-m-d');
    $visible=($published===''||$now->format('Y-m-d')>=$published)&&($hide===''||$now->format('Y-m-d')<$hide);
    return ['publish_date'=>$published,'hide_date'=>$hide,'event_date'=>$event,'hide_after_days'=>$keep,'visible'=>$visible,'days'=>$delta,'label'=>$delta>0?'Còn '.$delta.' ngày':($delta===0?'Diễn ra hôm nay':'Đã diễn ra '.abs($delta).' ngày'),'state'=>$delta>0?'Sắp diễn ra':($delta===0?'Diễn ra hôm nay':'Đã diễn ra'),'class'=>$delta<0?'past':($delta===0?'due-today':($delta<=3?'urgent':($delta<=7?'warning':'future')))];
}
