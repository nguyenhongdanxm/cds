<?php
/** Read-only teacher/period matrix. Do not label filtered-out lessons as free periods. */
function td_key(string $value): string {$value=trim(preg_replace('/\s+/u',' ',$value)??$value);return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);}
function td_load_session(array $slot): string {$v=td_key((string)($slot['session']??''));return str_contains($v,'chiều')||str_contains($v,'chieu')||in_array($v,['pm','afternoon'],true)?'afternoon':'morning';}
function td_matches(array $slot,array $filters): bool {
    foreach(['subject','class']as$key){$wanted=(string)($filters[$key]??'');$actual=trim((string)($slot[$key]??''));if($key==='class'&&$actual==='')$actual=(string)($slot['class_raw']??'');if($wanted!==''&&td_key($actual)!==td_key($wanted))return false;}return true;
}
function td_daily_matrix(array $teachers,array $slots,int $day,array $filters=[]): array {
    $scope=in_array($filters['scope']??'day',['day','morning','afternoon'],true)?($filters['scope']??'day'):'day';
    $people=[];$eligible=[];$subjects=[];$classes=[];$periods=['morning'=>4,'afternoon'=>4];$invalid=0;
    $ensure=static function(string $name)use(&$people){$name=trim($name);$key=td_key($name);if($key!==''&&!isset($people[$key]))$people[$key]=['teacher'=>$name,'day'=>0,'morning'=>0,'afternoon'=>0,'matched'=>0,'cells'=>['morning'=>[],'afternoon'=>[]]];return$key;};
    foreach($teachers as$teacher)if(is_string($teacher))$ensure($teacher);
    foreach($slots as$slot){
        if(!is_array($slot))continue;$original=(string)($slot['teacher']??'');$actual=(string)($slot['_actual_teacher']??$original);$originalKey=$ensure($original);$actualKey=$ensure($actual);
        $subject=trim((string)($slot['subject']??''));$class=trim((string)($slot['class']??''));if($class==='')$class=trim((string)($slot['class_raw']??''));if($subject!=='')$subjects[td_key($subject)]=$subject;if($class!=='')$classes[td_key($class)]=$class;
        if(td_matches($slot,$filters)){if($originalKey!=='')$eligible[$originalKey]=true;if($actualKey!=='')$eligible[$actualKey]=true;}
        $session=td_load_session($slot);$period=(int)($slot['period']??0);if($period>=1&&$period<=12)$periods[$session]=max($periods[$session],$period);
        if((int)($slot['day']??0)!==$day||$actualKey==='')continue;
        if($period<1||$period>12){$invalid++;continue;}
        $entry=['class'=>$class,'subject'=>$subject,'matches'=>td_matches($slot,$filters),'substitution'=>td_key($actual)!==td_key($original),'original_teacher'=>$original];
        $people[$actualKey]['cells'][$session][$period][]=$entry;$people[$actualKey]['day']++;$people[$actualKey][$session]++;
        if($entry['matches']&&($scope==='day'||$session===$scope))$people[$actualKey]['matched']++;
    }
    $rows=[];$stats=['teachers'=>0,'busy'=>0,'free'=>0,'lessons'=>0,'matched'=>0,'conflicts'=>0];$distribution=[];
    foreach($people as$key=>$person){
        if((!empty($filters['subject'])||!empty($filters['class']))&&!isset($eligible[$key]))continue;
        if(!empty($filters['teacher'])&&td_key((string)$filters['teacher'])!==$key)continue;
        $count=$person[$scope];$distribution[$count]=($distribution[$count]??0)+1;
        if(($filters['lessons']??'all')!=='all'&&$count!==(int)$filters['lessons'])continue;
        $stats['teachers']++;$stats[$count>0?'busy':'free']++;$stats['lessons']+=$count;$stats['matched']+=$person['matched'];
        foreach(['morning','afternoon']as$session)if($scope==='day'||$scope===$session)foreach($person['cells'][$session]as$entries)if(count($entries)>1)$stats['conflicts']++;
        $rows[]=$person;
    }
    $names=array_column($rows,'teacher');if(function_exists('sort_teachers_by_ten'))$names=sort_teachers_by_ten($names);else natcasesort($names);$order=array_flip(array_values($names));usort($rows,static fn($a,$b)=>($order[$a['teacher']]??0)<=>($order[$b['teacher']]??0));
    natcasesort($subjects);natcasesort($classes);$names=array_column($people,'teacher');if(function_exists('sort_teachers_by_ten'))$names=sort_teachers_by_ten($names);else natcasesort($names);
    return ['rows'=>$rows,'stats'=>$stats,'distribution'=>$distribution,'periods'=>$periods,'teachers'=>array_values($names),'subjects'=>array_values($subjects),'classes'=>array_values($classes),'invalid'=>$invalid];
}
