<?php
/** Tổng hợp đánh giá CB-GV-NV từ các nguồn dữ liệu hiện có. */
require_once __DIR__ . '/csdl_store.php';

function te_norm($value): string {
    $value = preg_replace('/\s+/u', ' ', trim((string)$value));
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function te_default_config(): array {
    return [
        'version'=>1,
        'professional_max'=>40,
        'attendance_max'=>30,
        'achievement_max'=>20,
        'contribution_max'=>10,
        'unpermitted_day_deduction'=>2,
        'permitted_day_deduction'=>0.25,
        'late_deduction'=>0.5,
        'achievement_level_points'=>['Trường'=>2,'Huyện'=>4,'Tỉnh'=>7,'Quốc gia'=>10],
        'file_check_rating_scores'=>['Tốt'=>20,'Khá'=>16,'Đạt'=>12,'Chưa đạt'=>8],
        'ratings'=>[
            ['min'=>90,'label'=>'Hoàn thành xuất sắc'],
            ['min'=>75,'label'=>'Hoàn thành tốt'],
            ['min'=>50,'label'=>'Hoàn thành'],
            ['min'=>0,'label'=>'Không hoàn thành'],
        ],
    ];
}

function te_config(): array {
    $saved = load_json(DATA_PATH . '/teacher_evaluation_config.json', []);
    $config = array_replace_recursive(te_default_config(), is_array($saved) ? $saved : []);
    usort($config['ratings'], fn($a,$b)=>(float)($b['min']??0)<=>(float)($a['min']??0));
    return $config;
}

function te_teachers(): array {
    $rows = array_values(array_filter(csdl_teachers_all(), fn($row)=>!isset($row['active']) || !empty($row['active'])));
    usort($rows, fn($a,$b)=>strnatcasecmp($a['name']??'', $b['name']??''));
    return $rows;
}

function te_observations(): array {
    $files=[DATA_PATH.'/observations.json'];
    if(defined('PCCM_DATA_PATH')&&PCCM_DATA_PATH!=='')$files[]=rtrim(PCCM_DATA_PATH,'/').'/observations.json';
    $rows=[];$seen=[];
    foreach(array_unique($files) as $file)foreach(load_json($file,[]) as $row){
        $key=(string)($row['id']??sha1(json_encode($row,JSON_UNESCAPED_UNICODE)));
        if(isset($seen[$key]))continue;$seen[$key]=true;$rows[]=$row;
    }
    return $rows;
}

function te_file_checks(): array {
    $files=[DATA_PATH.'/professional_file_checks.json'];
    if(defined('PCCM_DATA_PATH')&&PCCM_DATA_PATH!=='')$files[]=rtrim(PCCM_DATA_PATH,'/').'/professional_file_checks.json';
    $rows=[];$seen=[];foreach(array_unique($files) as $file)foreach(load_json($file,[]) as $row){$key=(string)($row['id']??sha1(json_encode($row,JSON_UNESCAPED_UNICODE)));if(isset($seen[$key]))continue;$seen[$key]=true;$rows[]=$row;}return $rows;
}

function te_teacher_team(array $teacher): string {
    return trim((string)($teacher['to_chuyen_mon'] ?? $teacher['pccm_group'] ?? '')) ?: 'Chưa xếp tổ';
}

function te_period(array $year, string $type, string $anchor, string $from='', string $to=''): array {
    if (!csdl_date_valid($anchor)) $anchor = date('Y-m-d');
    $yearStart = csdl_date_valid($year['start']??'') ? $year['start'] : date('Y').'-09-01';
    $yearEnd = csdl_date_valid($year['end']??'') ? $year['end'] : (date('Y')+1).'-05-31';
    if ($type === 'custom') {
        $from = csdl_date_valid($from) ? $from : $yearStart;
        $to = csdl_date_valid($to) ? $to : $yearEnd;
    } elseif ($type === 'semester') {
        $boundary = substr($yearEnd,0,4).'-01-31';
        if ($anchor <= $boundary) { $from=$yearStart; $to=$boundary; }
        else { $from=substr($yearEnd,0,4).'-02-01'; $to=$yearEnd; }
    } elseif ($type === 'year') {
        $from=$yearStart; $to=$yearEnd;
    } else {
        $type='month'; $from=date('Y-m-01',strtotime($anchor)); $to=date('Y-m-t',strtotime($anchor));
    }
    if ($to < $from) [$from,$to]=[$to,$from];
    return ['type'=>$type,'anchor'=>$anchor,'from'=>$from,'to'=>$to,'key'=>$type.'|'.$from.'|'.$to];
}

function te_matches_teacher(array $row, array $teacher): bool {
    $teacherId = (string)($teacher['id']??'');
    foreach (['teacher_id','person_id'] as $key) if ($teacherId !== '' && (string)($row[$key]??'') === $teacherId) return true;
    $rowName = $row['teacher'] ?? $row['teacher_name'] ?? $row['person_name'] ?? '';
    return $rowName !== '' && te_norm($rowName) === te_norm($teacher['name']??'');
}

function te_overlap_days(array $row, string $from, string $to): int {
    $start = (string)($row['from_date'] ?? $row['date'] ?? '');
    $end = (string)($row['to_date'] ?? $start);
    if (!csdl_date_valid($start) || !csdl_date_valid($end) || $end<$from || $start>$to) return 0;
    $a=max($start,$from); $b=min($end,$to);
    return max(1,(int)((strtotime($b)-strtotime($a))/86400)+1);
}

function te_rating(float $score, array $config): string {
    foreach ($config['ratings'] as $row) if ($score >= (float)($row['min']??0)) return (string)($row['label']??'');
    return '';
}

function te_calculate(array $teacher, array $period, array $config, array $observations, array $fileChecks, array $tdRecords): array {
    $from=$period['from']; $to=$period['to'];
    $obs=array_values(array_filter($observations,function($row)use($teacher,$from,$to){$date=$row['date']??$row['start_date']??'';return $date>=$from&&$date<=$to&&te_matches_teacher($row,$teacher);}));
    $ratedObs=array_values(array_filter($obs,fn($row)=>($row['score']??'')!==''&&is_numeric($row['score'])));
    $obsAverage=$ratedObs?array_sum(array_map(fn($row)=>(float)$row['score'],$ratedObs))/count($ratedObs):0;
    $professionalParts=[];if($ratedObs)$professionalParts[]=$obsAverage/20*(float)$config['professional_max'];
    $checks=array_values(array_filter($fileChecks,fn($row)=>($row['date']??'')>=$from&&($row['date']??'')<=$to&&te_matches_teacher($row,$teacher)));
    $checkValues=array_map(fn($row)=>(float)($config['file_check_rating_scores'][$row['rating']??'']??0),$checks);$checkAverage=$checkValues?array_sum($checkValues)/count($checkValues):0;
    if($checks)$professionalParts[]=$checkAverage/20*(float)$config['professional_max'];
    $professional=round($professionalParts?min((float)$config['professional_max'],array_sum($professionalParts)/count($professionalParts)):0,2);

    $attendance=array_values(array_filter($tdRecords,fn($row)=>($row['type']??'')==='teacher_attendance'&&te_matches_teacher($row,$teacher)&&te_overlap_days($row,$from,$to)>0));
    $attendanceDeduction=0.0; $permitted=0; $unpermitted=0; $late=0;
    foreach($attendance as $row){
        $days=te_overlap_days($row,$from,$to); $status=te_norm($row['status']??''); $permission=te_norm($row['permission']??'');
        if(str_contains($status,'muộn')){$late++;$attendanceDeduction+=(float)$config['late_deduction'];continue;}
        if(str_contains($permission,'không')||str_contains($status,'không phép')){$unpermitted+=$days;$attendanceDeduction+=$days*(float)$config['unpermitted_day_deduction'];}
        elseif(str_contains($permission,'có phép')||str_contains($status,'có phép')){$permitted+=$days;$attendanceDeduction+=$days*(float)$config['permitted_day_deduction'];}
    }
    $attendanceScore=round(max(0,(float)$config['attendance_max']-$attendanceDeduction),2);

    $achievements=array_values(array_filter($tdRecords,fn($row)=>($row['type']??'')==='teacher_achievement'&&($row['date']??'')>=$from&&($row['date']??'')<=$to&&te_matches_teacher($row,$teacher)));
    $achievementRaw=0.0;
    foreach($achievements as $row){$value=(float)($row['score']??0);if($value==0)$value=(float)($config['achievement_level_points'][$row['level']??'']??0);$achievementRaw+=$value;}
    $achievementScore=round(min((float)$config['achievement_max'],$achievementRaw),2);
    $contributionScore=0.0;
    $total=round($professional+$attendanceScore+$achievementScore+$contributionScore,2);
    return [
        'teacher_id'=>(string)($teacher['id']??''),'teacher_name'=>(string)($teacher['name']??''),'team'=>te_teacher_team($teacher),
        'professional'=>$professional,'attendance'=>$attendanceScore,'achievement'=>$achievementScore,'contribution'=>$contributionScore,'total'=>$total,'rating'=>te_rating($total,$config),
        'observation_count'=>count($obs),'rated_observation_count'=>count($ratedObs),'observation_average'=>round($obsAverage,2),'file_check_count'=>count($checks),'file_check_average'=>round($checkAverage,2),
        'permitted_days'=>$permitted,'unpermitted_days'=>$unpermitted,'late_count'=>$late,'attendance_deduction'=>round($attendanceDeduction,2),
        'achievement_count'=>count($achievements),'achievement_raw'=>round($achievementRaw,2),
        'sources'=>['observations'=>$obs,'file_checks'=>$checks,'attendance'=>$attendance,'achievements'=>$achievements],
    ];
}

function te_review_key(array $period, string $teacherId): string { return sha1($period['key'].'|'.$teacherId); }
function te_reviews(): array { $rows=load_json(DATA_PATH.'/teacher_evaluation_reviews.json',[]);return is_array($rows)?$rows:[]; }
