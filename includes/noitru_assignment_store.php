<?php
/** Công cụ chia phòng và chia mâm cho học sinh nội trú. */
require_once __DIR__ . '/noitru_store.php';

if (!defined('NOITRU_ASSIGNMENTS')) define('NOITRU_ASSIGNMENTS', NOITRU_DIR . '/assignments.json');

function noitru_assignments_data(): array {
    noitru_ensure_dir();
    $data = load_json(NOITRU_ASSIGNMENTS, []);
    return array_merge([
        'rooms'=>[], 'meals'=>[], 'room_names'=>[], 'meal_names'=>[],
        'room_capacities'=>[], 'meal_capacities'=>[], 'history'=>[],
        'updated_at'=>null, 'updated_by'=>'',
    ], is_array($data) ? $data : []);
}
function noitru_assignments_save(array $data, string $by = ''): void {
    noitru_ensure_dir();
    foreach (['rooms','meals'] as $key) $data[$key] = is_array($data[$key] ?? null) ? $data[$key] : [];
    foreach (['room_names','meal_names'] as $key) {
        $data[$key] = array_values(array_unique(array_filter(array_map('strval', $data[$key] ?? []))));
        sort($data[$key], SORT_NATURAL);
    }
    foreach (['room_capacities','meal_capacities'] as $key) {
        $clean=[];
        foreach ((array)($data[$key] ?? []) as $name=>$value) {
            $name=trim((string)$name);
            if ($name!=='') $clean[$name]=max(1,min(100,(int)$value));
        }
        $data[$key]=$clean;
    }
    $data['updated_at']=date('c'); $data['updated_by']=$by;
    save_json(NOITRU_ASSIGNMENTS,$data);
}
function noitru_assignment_apply(array $boarders): array {
    $data=noitru_assignments_data();
    foreach($boarders as &$student){
        $id=(string)($student['id']??''); if($id==='') continue;
        if(array_key_exists($id,$data['rooms'])) $student['room_ktx']=(string)$data['rooms'][$id];
        if(array_key_exists($id,$data['meals'])) $student['meal_group']=(string)$data['meals'][$id];
    }
    unset($student); return $boarders;
}
function noitru_assignment_grade(array $student): string {
    $class=(string)($student['class_name']??'');
    if(preg_match('/(?<!\d)(1[0-2]|[6-9])(?!\d)/u',$class,$m)) return $m[1];
    if(preg_match('/^(1[0-2]|[6-9])/u',$class,$m)) return $m[1];
    return 'Khác';
}
function noitru_assignment_gender(array $student): string {
    $g=mb_strtolower(trim((string)($student['gender']??'')),'UTF-8');
    if(in_array($g,['nam','male','m','1'],true)) return 'Nam';
    if(in_array($g,['nữ','nu','female','f','0'],true)) return 'Nữ';
    return 'Khác';
}
function noitru_assignment_summary(array $students): array {
    $s=['total'=>count($students),'male'=>0,'female'=>0,'other'=>0,'grades'=>[],'classes'=>[]];
    foreach($students as $student){
        $g=noitru_assignment_gender($student);
        if($g==='Nam')$s['male']++; elseif($g==='Nữ')$s['female']++; else $s['other']++;
        $grade=noitru_assignment_grade($student); $class=trim((string)($student['class_name']??''))?:'Chưa lớp';
        $s['grades'][$grade]=($s['grades'][$grade]??0)+1; $s['classes'][$class]=($s['classes'][$class]??0)+1;
    }
    ksort($s['grades'],SORT_NATURAL); ksort($s['classes'],SORT_NATURAL); return $s;
}
function noitru_assignment_names(string $mode,int $count,string $prefix=''): array {
    $count=max(1,min(200,$count)); $prefix=trim($prefix);
    if($prefix==='')$prefix=$mode==='rooms'?'Phòng ':'Mâm ';
    $names=[]; for($i=1;$i<=$count;$i++)$names[]=$prefix.$i; return $names;
}
function noitru_assignment_capacity_for($capacity,string $name): int {
    return is_array($capacity)?max(1,(int)($capacity[$name]??1)):max(1,(int)$capacity);
}
function noitru_assignment_auto_rooms(array $students,array $names,$capacity): array {
    usort($students,static fn($a,$b)=>[noitru_assignment_gender($a),(string)($a['class_name']??''),noitru_assignment_grade($a),(string)($a['name']??'')]<=>[noitru_assignment_gender($b),(string)($b['class_name']??''),noitru_assignment_grade($b),(string)($b['name']??'')]);
    $result=[];$used=array_fill_keys($names,0);$index=0;
    foreach($students as $student){
        while(isset($names[$index])&&$used[$names[$index]]>=noitru_assignment_capacity_for($capacity,$names[$index])&&$index<count($names)-1)$index++;
        if(!isset($names[$index]))$index=count($names)-1; $target=(string)$names[$index];
        $result[(string)$student['id']]=$target;$used[$target]++;
    }
    return $result;
}
function noitru_assignment_auto_meals(array $students,array $names,$capacity): array {
    usort($students,static fn($a,$b)=>[(string)($a['class_name']??''),noitru_assignment_grade($a),noitru_assignment_gender($a),(string)($a['name']??'')]<=>[(string)($b['class_name']??''),noitru_assignment_grade($b),noitru_assignment_gender($b),(string)($b['name']??'')]);
    $slots=array_fill_keys($names,[]);$result=[];
    foreach($students as $student){
        $gender=noitru_assignment_gender($student);$class=(string)($student['class_name']??'');$best=null;$bestScore=PHP_INT_MAX;
        foreach($names as $name){
            $members=$slots[$name]; if(count($members)>=noitru_assignment_capacity_for($capacity,$name))continue;
            $sameClass=count(array_filter($members,static fn($s)=>(string)($s['class_name']??'')===$class));
            $sameGender=count(array_filter($members,static fn($s)=>noitru_assignment_gender($s)===$gender));
            $score=count($members)*100-$sameClass*20+$sameGender*5;
            if($score<$bestScore){$bestScore=$score;$best=$name;}
        }
        if($best===null)$best=$names[array_key_last($names)];
        $slots[$best][]=$student;$result[(string)$student['id']]=(string)$best;
    }
    return $result;
}
