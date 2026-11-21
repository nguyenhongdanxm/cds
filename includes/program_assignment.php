<?php
/** GDĐP: dải tiết chương trình độc lập với số tuần. No writes on reads. */
function gdp_subject(string $s): bool {
    $s=mb_strtolower(preg_replace('/[\s.]+/u','',trim($s)),'UTF-8');
    return in_array($s,['gdđp','gddp','giáodụcđịaphương','nộidunggiáodụcđịaphương'],true);
}
function gdp_teacher_names(array $teachers): array {
    $names=[];foreach($teachers as$teacher){
        $name=is_string($teacher)?trim($teacher):(is_array($teacher)?trim((string)($teacher['name']??'')):'');
        if($name!==''&&!in_array($name,$names,true))$names[]=$name;
    }return $names;
}
function gdp_file(string $dir,string $version): string {
    if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$version))throw new RuntimeException('Phiên bản không hợp lệ.');
    return rtrim($dir,'/').'/program_assignments_'.$version.'.json';
}
function gdp_load(string $dir,string $version): array {return $version===''?[]:(array)load_json(gdp_file($dir,$version),[]);}
function gdp_validate(array $rows,array $teachers): array {
    $map=[];$out=[];
    foreach($rows as$r){
        $teacher=trim((string)($r['teacher']??''));$from=filter_var($r['from']??null,FILTER_VALIDATE_INT);$to=filter_var($r['to']??null,FILTER_VALIDATE_INT);
        if($teacher===''&&empty($r['from'])&&empty($r['to']))continue;
        if(!in_array($teacher,$teachers,true))throw new RuntimeException('Chọn giáo viên có trong danh mục.');
        if($from===false||$to===false||$from<1||$to>35||$from>$to)throw new RuntimeException('Dải tiết phải là số nguyên từ 1 đến 35, tiết đầu không vượt tiết cuối.');
        for($p=$from;$p<=$to;$p++){if(isset($map[$p]))throw new RuntimeException('Tiết '.$p.' bị giao trùng.');$map[$p]=$teacher;}
        $out[]=['teacher'=>$teacher,'from'=>$from,'to'=>$to];
    }
    $missing=array_diff(range(1,35),array_keys($map));if($missing)throw new RuntimeException('Chưa giao tiết: '.implode(', ',$missing).'. Cần đủ 35 tiết.');
    usort($out,fn($a,$b)=>$a['from']<=>$b['from']);return $out;
}
function gdp_teacher(array $config,int $period): string {foreach((array)($config['ranges']??[])as$r)if($period>=(int)$r['from']&&$period<=(int)$r['to'])return(string)$r['teacher'];return '';}
function gdp_hash(array $config): string {return hash('sha256',json_encode($config,JSON_UNESCAPED_UNICODE));}
function gdp_overlay(array $rows,array $configs): array {
    $rows=array_values(array_filter($rows,fn($r)=>!gdp_subject((string)($r['subject']??''))||!isset($configs[(string)($r['class']??$r['class_name']??'')])));
    foreach($configs as$class=>$config){$totals=[];foreach((array)($config['ranges']??[])as$r)$totals[$r['teacher']]=($totals[$r['teacher']]??0)+(int)$r['to']-(int)$r['from']+1;
        foreach($totals as$teacher=>$count)$rows[]=['id'=>'gdp_'.hash('sha256',$class.'|'.$teacher),'teacher'=>$teacher,'subject'=>$config['subject'],'class'=>$class,'periods'=>$count/35,'annual_periods'=>$count,'program_assignment'=>true,'note'=>$count.' tiết chương trình/năm; bình quân '.$count.'/35 tiết/tuần'];
    }return $rows;
}
/** Only completed, signed PPCT before the target week within its school year. First gap wins. */
function gdp_next(array $records,string $class,string $start,string $yearStart): int {
    $used=[];foreach($records as$r){if((string)($r['class']??'')!==$class||!gdp_subject((string)($r['subject']??''))||empty($r['signed_at'])||!in_array((string)($r['status']??''),['taught','substitute','makeup','online'],true))continue;
        $date=(string)($r['date']??'');if($date<$yearStart||$date>=$start)continue;$p=(int)($r['ppct_period']??0);if($p>=1&&$p<=35)$used[$p]=true;
    }for($p=1;$p<=35;$p++)if(!isset($used[$p]))return $p;return 36;
}
function gdp_week_context(array $data): array {
    $key=(string)($data['_workspace']['week_key']??'');$weeks=function_exists('cds_school_week_calendar')?cds_school_week_calendar():[];$week=null;$first='';
    foreach($weeks as$w){$s=(string)($w['start']??'');if($s!==''&&($first===''||$s<$first))$first=$s;if((string)($w['key']??'')===$key)$week=$w;}
    return ['key'=>$key,'start'=>(string)($week['start']??''),'year_start'=>$first];
}
/** Same SQL/JSON authority as the lesson book; reads only the current school-year range. */
function gdp_week_records(array $ctx): array {
    if(($ctx['start']??'')==='')return [];
    if(!function_exists('cds_lb_read_effective'))require_once __DIR__.'/database_lesson_book.php';
    if(cds_lb_read_effective()){
        try{return cds_lb_sql_range($ctx['year_start'],date('Y-m-d',strtotime($ctx['start'].' -1 day')));}
        catch(Throwable $e){error_log('[CDS GDĐP progress fallback] '.$e->getMessage());}
    }
    return (array)load_json(ttb_pccm_dir().'/lesson_book_records.json',[]);
}
function gdp_progress_hash(array $configs,array $ctx,array $records): string {
    $next=[];foreach($configs as$class=>$config)$next[$class]=gdp_next($records,(string)$class,$ctx['start'],$ctx['year_start']);
    return hash('sha256',json_encode($next));
}
/** Weekly picks suggest teachers for NEW activities; they do not lock existing lessons. */
function gdp_week_rows(array $data,array $configs,string $version): array {
    $ctx=gdp_week_context($data);$snap=(array)($data['program_week']??[]);$records=gdp_week_records($ctx);$out=[];
    foreach($configs as$class=>$config){
        $pick=($snap['week_key']??'')===$ctx['key']&&($snap['version_id']??'')===$version?(array)($snap['classes'][$class]??[]):[];
        $period=(int)($pick['period']??gdp_next($records,(string)$class,$ctx['start'],$ctx['year_start']));
        if($period<1||$period>36)$period=1;
        if($period===36)continue;
        $teacher=gdp_teacher($config,$period);if($teacher==='')continue;
        $out[]=['id'=>'gdpw_'.substr(hash('sha256',$version.'|'.$class.'|'.$period.'|'.$teacher),0,24),'teacher'=>$teacher,'subject'=>$config['subject'],'class'=>$class,'periods'=>1,'base_periods'=>1,'program_period'=>$period,'note'=>'Gợi ý tiết chương trình '.$period.'/35; được điều chỉnh khi dạy thực tế'];
    }return $out;
}
function gdp_builder_configs(): array {
    $dir=ttb_pccm_dir();$published=pccm_published_source($dir);
    if($published)return ['version'=>$published['version'],'configs'=>$published['configs']];
    $version=trim((string)((load_json($dir.'/active_version.json',[]))['id']??''));
    if($version==='')foreach((array)load_json($dir.'/versions.json',[])as$v)if(!empty($v['id'])){$version=(string)$v['id'];break;}
    return ['version'=>$version,'configs'=>gdp_load($dir,$version)];
}
function gdp_builder_ready(array $data): void {$source=gdp_builder_configs();if($source['configs'])gdp_week_rows($data,$source['configs'],$source['version']);}
/** A copied week may retain the source period's ID while its confirmed teacher stays the same. */
function gdp_copied_activity_matches(array $data,array $entry,string $class,string $version): bool {
    if(empty($data['_workspace']['copied_from']))return false;
    $id=(string)($entry['activity_id']??'');$teacher=(string)($entry['teacher']??'');
    // Only recognize genuine GDĐP activity IDs from this assignment version/class/teacher.
    // Do not rewrite draft IDs: room assignments, locks and constraints still refer to them.
    for($period=1;$period<=35;$period++){
        $sourceId='gdpw_'.substr(hash('sha256',$version.'|'.$class.'|'.$period.'|'.$teacher),0,24).'_1';
        if($id===$sourceId)return true;
    }
    return false;
}
/** Bind legacy/copied draft identities only during the user's explicit weekly confirmation. */
function gdp_plan_binding_signature(array $entry): string {
    return hash('sha256',json_encode([(string)($entry['activity_id']??''),(string)($entry['teacher']??''),(string)($entry['subject']??''),array_values((array)($entry['classes']??[]))],JSON_UNESCAPED_UNICODE));
}
function gdp_confirm_plan_bindings(array $data,array $picks): array {
    $bindings=[];
    foreach((array)($data['plans']??[])as$plan){
        $planId=(string)($plan['id']??'');if($planId==='')continue;
        $classCounts=[];$idCounts=[];
        foreach((array)($plan['entries']??[])as$entry){
            $id=(string)($entry['activity_id']??'');$idCounts[$id]=($idCounts[$id]??0)+1;
            if(gdp_subject((string)($entry['subject']??'')))foreach((array)($entry['classes']??[])as$class)$classCounts[$class]=($classCounts[$class]??0)+1;
        }
        foreach((array)($plan['entries']??[])as$entry){
            $classes=array_values((array)($entry['classes']??[]));$id=(string)($entry['activity_id']??'');
            if(!gdp_subject((string)($entry['subject']??''))||count($classes)!==1||$id===''||($idCounts[$id]??0)!==1)continue;
            $class=(string)$classes[0];$pick=$picks[$class]??null;
            if(!$pick||($classCounts[$class]??0)!==1||(int)$pick['period']>35||($entry['teacher']??'')!==$pick['teacher'])continue;
            $bindings[$planId][$id]=['class'=>$class,'teacher'=>$pick['teacher'],'period'=>(int)$pick['period'],'signature'=>gdp_plan_binding_signature($entry)];
        }
    }
    return $bindings;
}
/** Advisory only: preserve scheduled teachers, copied identities and extra lessons. */
function gdp_assignment_warning(array $config,string $class,int $period,string $actual): string {
    $expected=gdp_teacher($config,$period);
    return $expected!==''&&$actual!==''&&$actual!==$expected
        ? 'GDĐP lớp '.$class.', tiết PPCT '.$period.': phân công '.$expected.'; giáo viên thực tế '.$actual.'. Vui lòng kiểm tra phân công/dạy thay.' : '';
}
function gdp_publish_periods(array $data,array $plan,string $weekKey,string $from): array {
    $GLOBALS['gdp_publish_warnings']=[];
    $source=gdp_builder_configs();$configs=$source['configs'];if(!$configs)return [];
    $target=$data;$target['_workspace']['week_key']=$weekKey;$ctx=gdp_week_context($target);$ctx['start']=$from;
    $records=gdp_week_records($ctx);$snap=(array)($data['program_week']??[]);$next=[];
    foreach($configs as$class=>$config){
        $pick=($snap['week_key']??'')===$weekKey&&($snap['version_id']??'')===$source['version']?(array)($snap['classes'][$class]??[]):[];
        $next[$class]=(int)($pick['period']??gdp_next($records,(string)$class,$from,$ctx['year_start']));
    }
    $entries=(array)($plan['entries']??[]);$idCounts=[];$periods=[];
    foreach($entries as$e){$id=(string)($e['activity_id']??'');$idCounts[$id]=($idCounts[$id]??0)+1;}
    usort($entries,fn($a,$b)=>[(int)($a['day']??0),($a['session']??'')==='Sáng'?0:1,(int)($a['period']??0)]<=>[(int)($b['day']??0),($b['session']??'')==='Sáng'?0:1,(int)($b['period']??0)]);
    $gdpIds=[];
    foreach($entries as$e){
        if(!gdp_subject((string)($e['subject']??'')))continue;
        $id=(string)($e['activity_id']??'');
        if($id===''||$idCounts[$id]!==1)throw new RuntimeException('GDĐP: mã tiết bị thiếu hoặc dùng trùng trong phương án.');
        $gdpIds[$id]=true;$classPeriods=[];
        foreach((array)($e['classes']??[])as$class){
            if(!isset($configs[$class]))continue;
            $p=$next[$class]++;$p=$p>=1&&$p<=35?$p:0;$classPeriods[]=$p;
            $warning=gdp_assignment_warning($configs[$class],(string)$class,$p,(string)($e['teacher']??''));
            if($warning!=='')$GLOBALS['gdp_publish_warnings'][]=$warning;
        }
        // A grouped lesson may cover classes at different progress: let each book suggest its own PPCT.
        if($classPeriods&&count(array_unique($classPeriods))===1)$periods[$id]=$classPeriods[0];
    }
    $busy=[];$classes=[];
    foreach($entries as$e){
        $slot=($e['day']??'').'|'.($e['session']??'').'|'.($e['period']??'');$isGdp=isset($gdpIds[$e['activity_id']??'']);
        $key=($e['teacher']??'').'|'.$slot;
        if(trim((string)($e['teacher']??''))!==''&&isset($busy[$key])&&($isGdp||$busy[$key]))throw new RuntimeException('Trùng lịch giáo viên GDĐP.');
        $busy[$key]=$isGdp||($busy[$key]??false);
        foreach((array)($e['classes']??[])as$class){$key=$class.'|'.$slot;if(isset($classes[$key])&&($isGdp||$classes[$key]))throw new RuntimeException('Trùng lịch lớp GDĐP.');$classes[$key]=$isGdp||($classes[$key]??false);}
    }
    $GLOBALS['gdp_publish_warnings']=array_values(array_unique($GLOBALS['gdp_publish_warnings']));
    return $periods;
}
