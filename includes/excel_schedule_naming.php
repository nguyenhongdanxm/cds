<?php
/** Names use the actual resolved export filters, never the calendar week as a lesson number. */
function xlsched_filename(array $job,array $run,array $report,?DateTimeImmutable $now=null): string {
    $clean=static function($v,int $limit=75):string{$s=preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u','-',trim((string)$v));$s=preg_replace('/\s+/u',' ',(string)$s);return trim(mb_substr($s,0,$limit),' .-');};
    $params=$job['_resolved_params']??json_decode((string)($job['params_json']??'{}'),true);$params=is_array($params)?$params:[];
    if(($report['id']??'')==='lb_weekly')return 'Sổ đầu bài_Lớp '.$clean($params['class']??'',40).'_'.$clean($job['_week_label']??('Tuần '.($params['week']??'')),60).'.xlsx';
    if(($params['__auto_name']??'0')!=='1'){$legacy=preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u','-',trim((string)($job['name']??$report['label'])));return mb_substr($legacy,0,140).(($job['file_mode']??'replace')==='snapshot'?'-'.str_replace([':', ' '],'-',(string)($run['period_key']??'')):'').'.'.$report['format'];}
    $parts=[$clean($job['name']??$report['label'],80)];
    foreach(['class'=>'Lớp','stats_class'=>'Lớp','health_class'=>'Lớp','class_id'=>'Mã lớp','grade'=>'Khối','teacher'=>'GV','stats_teacher'=>'GV','subject'=>'Môn','stats_subject'=>'Môn']as$key=>$label){$v=$params[$key]??'';if(is_scalar($v)&&trim((string)$v)!==''&&$v!=='all')$parts[]=$label.' '.$clean($v,35);}
    foreach(['week','template_week_id']as$key){$v=$params[$key]??'';if(is_scalar($v)&&$v!==''){$label=$job['_week_label']??'';$parts[]=$clean($label!==''?$label:'Tuần '.$v,60);break;}}
    foreach(['month','from_month','to_month','date','period_value','from','to','stats_from','stats_to']as$key){$v=$params[$key]??'';if(is_scalar($v)&&$v!=='')$parts[]=($key==='to'||$key==='stats_to'||$key==='to_month'?'đến ':'').$clean($v,25);}
    if(!empty($params['id']))$parts[]='Mã '.$clean($params['id'],35);
    $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
    $parts=array_values(array_unique(array_filter($parts)));$context=$clean(implode(' - ',$parts),190);
    return$context.' - Đồng bộ '.$now->format('Y-m-d_His').'.'.$report['format'];
}
