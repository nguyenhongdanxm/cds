<?php
/** Weekly lesson-book presets. Read school data without seeding or modifying it. */
require_once __DIR__.'/school_week_calendar.php';
function xlsched_lb_classes(): array {
    require_once __DIR__.'/database_sql_read.php';
    try{$rows=cds_core_sql_rows('classes');}catch(Throwable $e){$rows=null;}
    if(!is_array($rows))$rows=json_decode((string)@file_get_contents(dirname(__DIR__).'/data/classes.json'),true);
    $names=[];foreach(is_array($rows)?$rows:[]as$row){if(!is_array($row)||isset($row['active'])&&!$row['active'])continue;$name=trim((string)($row['name']??''));if($name!=='')$names[$name]=true;}
    $names=array_keys($names);sort($names,SORT_NATURAL);return$names;
}
function xlsched_lb_context(array $params,DateTimeImmutable $now): array {
    $year=cds_school_year_resolve();
    if(!$year||(string)($year['id']??'')!==(string)($params['school_year']??''))throw new RuntimeException('Năm học đã thay đổi; hãy tạo lịch sổ đầu bài cho năm học hiện tại.');
    $date=$now->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d');$week=cds_school_week_for_date($date,$year);
    if(!$week||(string)$week['key']!==(string)($params['week']??''))throw new RuntimeException('Ngày chạy không thuộc tuần học đã cấu hình; không xuất sang tuần khác.');
    $yearLabel=trim((string)($year['label']??$year['name']??''));if($yearLabel==='')$yearLabel=substr((string)$year['start'],0,4).'-'.substr((string)$year['end'],0,4);
    return['_week_label'=>$week['label'],'_archive_year'=>'Năm học '.$yearLabel,'_archive_week'=>$week['label'].' ('.$week['start'].' - '.$week['end'].')','_archive_period'=>(string)$year['id'].'|'.$week['start']];
}
function xlsched_lb_create(array $classes,string $folder,string $actor,array $print=[]): int {
    $allowed=xlsched_lb_classes();$year=cds_school_year_resolve();if(!$year||empty($year['id']))throw new RuntimeException('Chưa cấu hình năm học.');
    $selected=[];foreach($classes as$class){if(!is_string($class)||!in_array($class,$allowed,true))throw new InvalidArgumentException('Lớp không thuộc danh mục hiện tại.');$selected[$class]=true;}if(!$selected)throw new InvalidArgumentException('Hãy chọn ít nhất một lớp.');
    $data=[];foreach(array_keys($selected)as$class)$data[]=xlsched_validate(['name'=>'Sổ đầu bài · Lớp '.$class,'report_id'=>'lb_weekly','params'=>array_replace(['paper'=>'a3','session'=>'all','only_filled'=>'0','include_cover'=>'1'],array_intersect_key($print,array_flip(['paper','session','only_filled','include_cover'])),['class'=>$class,'school_year'=>(string)$year['id']]),'folder_id'=>$folder,'frequency'=>'weekly','day'=>7,'start_time'=>'23:00','end_time'=>'23:30','date_policy'=>'week','file_mode'=>'snapshot','enabled'=>1],$actor);
    $pdo=cds_db();$pdo->beginTransaction();try{
        // Serialize preset submissions and keep all selected classes in one atomic change.
        $pdo->query('SELECT id FROM cds_excel_schedule_state WHERE id=1 FOR UPDATE')->fetchColumn();
        $existing=[];foreach(xlsched_jobs()as$job){if($job['report_id']!=='lb_weekly')continue;$p=json_decode($job['params_json'],true);if(($p['school_year']??'')===(string)$year['id'])$existing[(string)($p['class']??'')]=true;}
        $created=0;foreach($data as$row){$p=json_decode($row['params_json'],true);if(isset($existing[$p['class']]))continue;xlsched_save($row);$created++;}
        // The explicit preset action enables the scheduler; individual unrelated job states are retained.
        $pdo->exec('UPDATE cds_excel_schedule_state SET enabled=1 WHERE id=1');$pdo->commit();return$created;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}
