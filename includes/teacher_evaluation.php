<?php
/** Tổng hợp đánh giá CB-GV-NV từ các nguồn dữ liệu hiện có. */
require_once __DIR__ . '/csdl_store.php';

function te_norm($value): string {
    $value = preg_replace('/\s+/u', ' ', trim((string)$value));
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function te_default_config(): array {
    return [
        'version'=>2,
        'professional_max'=>40,
        'attendance_max'=>30,
        'achievement_max'=>20,
        'contribution_max'=>10,
        'unpermitted_day_deduction'=>2,
        'permitted_day_deduction'=>0.25,
        'late_deduction'=>0.5,
        'achievement_level_points'=>['Trường'=>2,'Huyện'=>4,'Tỉnh'=>7,'Quốc gia'=>10],
        'file_check_rating_scores'=>['Tốt'=>20,'Khá'=>16,'Đạt'=>12,'Chưa đạt'=>8],
        'groups'=>[
            ['id'=>'professional','name'=>'Chuyên môn','max'=>40],
            ['id'=>'attendance','name'=>'Ngày công','max'=>30],
            ['id'=>'achievement','name'=>'Thành tích','max'=>20],
            ['id'=>'contribution','name'=>'Nhiệm vụ','max'=>10],
        ],
        'criteria'=>[
            ['id'=>'obs_good','name'=>'Dự giờ xếp loại Tốt','group_id'=>'professional','source'=>'observation:Tốt','operation'=>'add','points'=>5],
            ['id'=>'obs_fair','name'=>'Dự giờ xếp loại Khá','group_id'=>'professional','source'=>'observation:Khá','operation'=>'add','points'=>3],
            ['id'=>'obs_pass','name'=>'Dự giờ xếp loại Đạt','group_id'=>'professional','source'=>'observation:Đạt','operation'=>'add','points'=>1],
            ['id'=>'file_good','name'=>'Hồ sơ xếp loại Tốt','group_id'=>'professional','source'=>'file_check:Tốt','operation'=>'add','points'=>5],
            ['id'=>'file_fair','name'=>'Hồ sơ xếp loại Khá','group_id'=>'professional','source'=>'file_check:Khá','operation'=>'add','points'=>3],
            ['id'=>'file_pass','name'=>'Hồ sơ xếp loại Đạt','group_id'=>'professional','source'=>'file_check:Đạt','operation'=>'add','points'=>1],
            ['id'=>'attendance_base','name'=>'Điểm ngày công ban đầu','group_id'=>'attendance','source'=>'attendance:base','operation'=>'add','points'=>30],
            ['id'=>'absence_unpermitted','name'=>'Nghỉ không phép','group_id'=>'attendance','source'=>'attendance:unpermitted_day','operation'=>'subtract','points'=>2],
            ['id'=>'absence_permitted','name'=>'Nghỉ có phép','group_id'=>'attendance','source'=>'attendance:permitted_day','operation'=>'subtract','points'=>0],
            ['id'=>'late','name'=>'Đi muộn','group_id'=>'attendance','source'=>'attendance:late','operation'=>'subtract','points'=>0.5],
            ['id'=>'ach_school','name'=>'Thành tích cấp Trường','group_id'=>'achievement','source'=>'achievement:Trường','operation'=>'add','points'=>2],
            ['id'=>'ach_district','name'=>'Thành tích cấp Huyện','group_id'=>'achievement','source'=>'achievement:Huyện','operation'=>'add','points'=>4],
            ['id'=>'ach_province','name'=>'Thành tích cấp Tỉnh','group_id'=>'achievement','source'=>'achievement:Tỉnh','operation'=>'add','points'=>7],
            ['id'=>'ach_national','name'=>'Thành tích cấp Quốc gia','group_id'=>'achievement','source'=>'achievement:Quốc gia','operation'=>'add','points'=>10],
        ],
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
    if(!empty($saved['groups'])&&is_array($saved['groups']))$config['groups']=array_values($saved['groups']);
    if(!empty($saved['criteria'])&&is_array($saved['criteria']))$config['criteria']=array_values($saved['criteria']);
    foreach($config['groups'] as &$group)$group['max']=max(0,(float)($group['max']??0));unset($group);
    usort($config['ratings'], fn($a,$b)=>(float)($b['min']??0)<=>(float)($a['min']??0));
    return $config;
}

function te_source_catalog(): array {
    return [
        'observation:Tốt'=>'Dự giờ: Tốt (mỗi tiết)','observation:Khá'=>'Dự giờ: Khá (mỗi tiết)','observation:Đạt'=>'Dự giờ: Đạt (mỗi tiết)','observation:Chưa đạt'=>'Dự giờ: Chưa đạt (mỗi tiết)',
        'file_check:Tốt'=>'Kiểm tra hồ sơ: Tốt (mỗi lần)','file_check:Khá'=>'Kiểm tra hồ sơ: Khá (mỗi lần)','file_check:Đạt'=>'Kiểm tra hồ sơ: Đạt (mỗi lần)','file_check:Chưa đạt'=>'Kiểm tra hồ sơ: Chưa đạt (mỗi lần)',
        'attendance:base'=>'Điểm nền ngày công (một lần/kỳ)','attendance:unpermitted_day'=>'Nghỉ không phép (mỗi ngày)','attendance:permitted_day'=>'Nghỉ có phép (mỗi ngày)','attendance:late'=>'Đi muộn (mỗi lần)',
        'achievement:Trường'=>'Thành tích cấp Trường (mỗi thành tích)','achievement:Huyện'=>'Thành tích cấp Huyện (mỗi thành tích)','achievement:Tỉnh'=>'Thành tích cấp Tỉnh (mỗi thành tích)','achievement:Quốc gia'=>'Thành tích cấp Quốc gia (mỗi thành tích)',
    ];
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
    $checks=array_values(array_filter($fileChecks,fn($row)=>($row['date']??'')>=$from&&($row['date']??'')<=$to&&te_matches_teacher($row,$teacher)));
    $fileOnlyChecks=array_values(array_filter($checks,fn($row)=>($row['content_type']??'file')==='file'));
    $checkValues=array_map(fn($row)=>(float)($config['file_check_rating_scores'][$row['rating']??'']??0),$fileOnlyChecks);$checkAverage=$checkValues?array_sum($checkValues)/count($checkValues):0;

    $attendance=array_values(array_filter($tdRecords,fn($row)=>($row['type']??'')==='teacher_attendance'&&te_matches_teacher($row,$teacher)&&te_overlap_days($row,$from,$to)>0));
    $attendanceDeduction=0.0; $permitted=0; $unpermitted=0; $late=0;
    foreach($attendance as $row){
        $days=te_overlap_days($row,$from,$to); $status=te_norm($row['status']??''); $permission=te_norm($row['permission']??'');
        if(str_contains($status,'muộn')){$late++;continue;}
        if(str_contains($permission,'không')||str_contains($status,'không phép'))$unpermitted+=$days;
        elseif(str_contains($permission,'có phép')||str_contains($status,'có phép'))$permitted+=$days;
    }

    $achievements=array_values(array_filter($tdRecords,fn($row)=>($row['type']??'')==='teacher_achievement'&&($row['date']??'')>=$from&&($row['date']??'')<=$to&&te_matches_teacher($row,$teacher)));
    $achievementRaw=array_sum(array_map(fn($row)=>(float)($row['score']??0),$achievements));
    $units=['attendance:base'=>1,'attendance:unpermitted_day'=>$unpermitted,'attendance:permitted_day'=>$permitted,'attendance:late'=>$late];
    foreach(['Tốt','Khá','Đạt','Chưa đạt'] as $rating){
        $units['observation:'.$rating]=count(array_filter($obs,fn($row)=>te_norm($row['rating']??'')===te_norm($rating)));
        $units['file_check:'.$rating]=count(array_filter($fileOnlyChecks,fn($row)=>te_norm($row['rating']??'')===te_norm($rating)));
    }
    foreach(['Trường','Huyện','Tỉnh','Quốc gia'] as $level)$units['achievement:'.$level]=count(array_filter($achievements,fn($row)=>te_norm($row['level']??'')===te_norm($level)));
    $groupScores=[];$details=[];$groupMax=[];foreach($config['groups'] as $group){$id=(string)($group['id']??'');$groupScores[$id]=0.0;$groupMax[$id]=(float)($group['max']??0);}
    $criteriaById=[];foreach($config['criteria'] as $criterion){$criteriaById[(string)($criterion['id']??'')]=$criterion;$groupId=(string)($criterion['group_id']??'');$source=(string)($criterion['source']??'');if(!isset($groupScores[$groupId])||!array_key_exists($source,$units))continue;$count=(float)$units[$source];$points=max(0,(float)($criterion['points']??0));$signed=$count*$points*(($criterion['operation']??'add')==='subtract'?-1:1);$groupScores[$groupId]+=$signed;$details[]=$criterion+['units'=>$count,'amount'=>round($signed,2)];}
    foreach($checks as $check){if(($check['content_type']??'file')!=='other')continue;$criterion=$criteriaById[(string)($check['criterion_id']??'')]??null;if(!$criterion)continue;$groupId=(string)($criterion['group_id']??'');if(!isset($groupScores[$groupId]))continue;$points=max(0,(float)($criterion['points']??0));$signed=$points*(($criterion['operation']??'add')==='subtract'?-1:1);$groupScores[$groupId]+=$signed;$details[]=$criterion+['name'=>($criterion['name']??'').' · Kiểm tra ngày '.date('d/m/Y',strtotime($check['date'])),'units'=>1,'amount'=>round($signed,2)];}
    foreach($groupScores as $id=>$score)$groupScores[$id]=round(min($groupMax[$id],max(0,$score)),2);
    $professional=(float)($groupScores['professional']??0);$attendanceScore=(float)($groupScores['attendance']??0);$achievementScore=(float)($groupScores['achievement']??0);$contributionScore=(float)($groupScores['contribution']??0);
    $attendanceDeduction=round(array_sum(array_map(fn($row)=>(($row['operation']??'')==='subtract'&&str_starts_with($row['source']??'','attendance:'))?abs((float)$row['amount']):0,$details)),2);
    $total=round(array_sum($groupScores),2);
    return [
        'teacher_id'=>(string)($teacher['id']??''),'teacher_name'=>(string)($teacher['name']??''),'team'=>te_teacher_team($teacher),
        'professional'=>$professional,'attendance'=>$attendanceScore,'achievement'=>$achievementScore,'contribution'=>$contributionScore,'group_scores'=>$groupScores,'criterion_details'=>$details,'total'=>$total,'rating'=>te_rating($total,$config),
        'observation_count'=>count($obs),'rated_observation_count'=>count($ratedObs),'observation_average'=>round($obsAverage,2),'file_check_count'=>count($fileOnlyChecks),'file_check_average'=>round($checkAverage,2),
        'permitted_days'=>$permitted,'unpermitted_days'=>$unpermitted,'late_count'=>$late,'attendance_deduction'=>round($attendanceDeduction,2),
        'achievement_count'=>count($achievements),'achievement_raw'=>round($achievementRaw,2),
        'sources'=>['observations'=>$obs,'file_checks'=>$checks,'attendance'=>$attendance,'achievements'=>$achievements],
    ];
}

function te_review_key(array $period, string $teacherId): string { return sha1($period['key'].'|'.$teacherId); }
function te_reviews(): array { $rows=load_json(DATA_PATH.'/teacher_evaluation_reviews.json',[]);return is_array($rows)?$rows:[]; }
