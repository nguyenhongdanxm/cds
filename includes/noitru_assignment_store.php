<?php
/** Công cụ chia phòng và chia mâm cho học sinh nội trú. */
require_once __DIR__ . '/noitru_store.php';

if (!defined('NOITRU_ASSIGNMENTS')) define('NOITRU_ASSIGNMENTS', NOITRU_DIR . '/assignments.json');

function noitru_assignments_data(): array {
    noitru_ensure_dir();
    $data = load_json(NOITRU_ASSIGNMENTS, []);
    return array_merge([
        'rooms'=>[], 'meals'=>[], 'room_names'=>[], 'meal_names'=>[],
        'room_capacities'=>[], 'meal_capacities'=>[], 'room_genders'=>[], 'room_notes'=>[],
        'settings'=>[
            'room_enforce_gender'=>true,
            'room_max_grade_gap'=>1,
            'meal_max_grade_gap'=>0,
            'meal_balance_gender'=>true,
        ],
        'history'=>[],
        'updated_at'=>null, 'updated_by'=>'',
    ], is_array($data) ? $data : []);
}
function noitru_assignments_save(array $data, string $by = ''): void {
    noitru_ensure_dir();
    foreach (['rooms','meals','room_notes'] as $key) $data[$key] = is_array($data[$key] ?? null) ? $data[$key] : [];
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
    $genders=[];
    foreach((array)($data['room_genders']??[]) as $name=>$gender){
        $name=trim((string)$name);$gender=(string)$gender;
        if($name!==''&&in_array($gender,['Nam','Nữ','Linh hoạt'],true))$genders[$name]=$gender;
    }
    $data['room_genders']=$genders;
    $settings=is_array($data['settings']??null)?$data['settings']:[];
    $data['settings']=[
        'room_enforce_gender'=>(bool)($settings['room_enforce_gender']??true),
        'room_max_grade_gap'=>max(0,min(3,(int)($settings['room_max_grade_gap']??1))),
        'meal_max_grade_gap'=>max(0,min(3,(int)($settings['meal_max_grade_gap']??0))),
        'meal_balance_gender'=>(bool)($settings['meal_balance_gender']??true),
    ];
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
    ksort($s['grades'],SORT_NATURAL); uksort($s['classes'],'csdl_compare_class_names'); return $s;
}
function noitru_assignment_names(string $mode,int $count,string $prefix=''): array {
    $count=max(1,min(200,$count)); $prefix=trim($prefix);
    if($prefix==='')$prefix=$mode==='rooms'?'Phòng ':'Mâm ';
    $names=[]; for($i=1;$i<=$count;$i++)$names[]=$prefix.$i; return $names;
}
function noitru_assignment_capacity_for($capacity,string $name): int {
    return is_array($capacity)?max(1,(int)($capacity[$name]??1)):max(1,(int)$capacity);
}
function noitru_assignment_numeric_grade(array $student): int {
    $grade=noitru_assignment_grade($student);
    return ctype_digit($grade)?(int)$grade:99;
}
function noitru_assignment_grade_compatible(array $members,array $students,int $maxGap): bool {
    $grades=[];
    foreach(array_merge($members,$students) as $student){$grade=noitru_assignment_numeric_grade($student);if($grade<99)$grades[]=$grade;}
    return !$grades||(max($grades)-min($grades)<=$maxGap);
}
function noitru_assignment_grade_distance(array $members,array $students): int {
    $memberGrades=[];$studentGrades=[];
    foreach($members as $student){$grade=noitru_assignment_numeric_grade($student);if($grade<99)$memberGrades[]=$grade;}
    foreach($students as $student){$grade=noitru_assignment_numeric_grade($student);if($grade<99)$studentGrades[]=$grade;}
    if(!$memberGrades||!$studentGrades)return 0;
    return max(array_merge($memberGrades,$studentGrades))-min(array_merge($memberGrades,$studentGrades));
}
function noitru_assignment_sort_targets(array $names,array $slots,array $students,$capacity): array {
    usort($names,static function($left,$right)use($slots,$students,$capacity){
        $leftDistance=noitru_assignment_grade_distance($slots[$left]??[],$students);
        $rightDistance=noitru_assignment_grade_distance($slots[$right]??[],$students);
        if($leftDistance!==$rightDistance)return $leftDistance<=>$rightDistance;
        $leftRatio=count($slots[$left]??[])/noitru_assignment_capacity_for($capacity,$left);
        $rightRatio=count($slots[$right]??[])/noitru_assignment_capacity_for($capacity,$right);
        if($leftRatio!==$rightRatio)return $rightRatio<=>$leftRatio;
        return strnatcasecmp((string)$left,(string)$right);
    });
    return $names;
}
function noitru_assignment_interleave_gender(array $students): array {
    $buckets=['Nam'=>[],'Nữ'=>[],'Khác'=>[]];
    foreach($students as $student)$buckets[noitru_assignment_gender($student)][]=$student;
    foreach($buckets as &$bucket)usort($bucket,static fn($a,$b)=>(string)($a['name']??'')<=>(string)($b['name']??''));unset($bucket);
    $result=[];$counts=array_map('count',$buckets);$total=array_sum($counts);$placed=['Nam'=>0,'Nữ'=>0,'Khác'=>0];
    while(count($result)<$total){
        $best=null;$score=-INF;
        foreach($buckets as $gender=>$bucket){
            if(!$bucket)continue;
            $expected=$counts[$gender]*((count($result)+1)/max(1,$total));
            $candidate=$expected-$placed[$gender];
            if($candidate>$score){$score=$candidate;$best=$gender;}
        }
        $result[]=array_shift($buckets[$best]);$placed[$best]++;
    }
    return $result;
}
function noitru_assignment_group_students(array $students,bool $separateGender=false): array {
    $groups=[];
    foreach($students as $student){
        $class=trim((string)($student['class_name']??''))?:'Chưa lớp';
        $key=($separateGender?noitru_assignment_gender($student).'|':'').sprintf('%02d',noitru_assignment_numeric_grade($student)).'|'.$class;
        $groups[$key][]=$student;
    }
    ksort($groups,SORT_NATURAL);return $groups;
}
function noitru_assignment_auto_rooms(array $students,array $names,$capacity,array $roomGenders=[],array $options=[]): array {
    $enforce=(bool)($options['enforce_gender']??true);$maxGap=max(0,min(3,(int)($options['max_grade_gap']??1)));
    $slots=array_fill_keys($names,[]);$runtimeGender=[];$result=[];
    // Khi chia theo từng đợt, các phòng đã chia trước được dùng làm dữ liệu nền:
    // thuật toán chỉ xếp thêm vào chỗ trống phù hợp, không ghi đè kết quả cũ.
    foreach(($options['seed_slots']??[]) as $name=>$members){
        if(!isset($slots[$name])||!is_array($members))continue;
        foreach($members as $member){
            if(!is_array($member)||count($slots[$name])>=noitru_assignment_capacity_for($capacity,$name))continue;
            $slots[$name][]=$member;
            $gender=noitru_assignment_gender($member);
            if($gender==='Khác')continue;
            if(!isset($runtimeGender[$name]))$runtimeGender[$name]=$gender;
            elseif($runtimeGender[$name]!==$gender)$runtimeGender[$name]='Khác';
        }
    }
    foreach(noitru_assignment_group_students($students,true) as $group){
        usort($group,static fn($a,$b)=>(string)($a['name']??'')<=>(string)($b['name']??''));
        $gender=noitru_assignment_gender($group[0]);$empty=[];$partial=[];
        foreach($names as $name){
            $free=noitru_assignment_capacity_for($capacity,$name)-count($slots[$name]);if($free<=0)continue;
            $fixed=(string)($roomGenders[$name]??'Linh hoạt');
            if($enforce&&$gender!=='Khác'&&$fixed!=='Linh hoạt'&&$fixed!==$gender)continue;
            if($enforce&&isset($runtimeGender[$name])&&$runtimeGender[$name]!==$gender)continue;
            if(!noitru_assignment_grade_compatible($slots[$name],$group,$maxGap))continue;
            if($slots[$name])$partial[]=$name;else$empty[]=$name;
        }
        $partial=noitru_assignment_sort_targets($partial,$slots,$group,$capacity);
        $needed=count($group);$emptyCapacity=array_sum(array_map(static fn($name)=>noitru_assignment_capacity_for($capacity,$name),$empty));
        $pool=$emptyCapacity>=$needed?$empty:array_merge($partial,$empty);$selected=[];$freeTotal=0;
        foreach($pool as $name){$selected[]=$name;$freeTotal+=noitru_assignment_capacity_for($capacity,$name)-count($slots[$name]);if($freeTotal>=$needed)break;}
        // Nếu hết phòng phù hợp khối, vẫn giữ đúng giới tính và dùng phòng còn chỗ gần nhất.
        if($freeTotal<$needed){
            $fallback=[];
            foreach($names as $name){
                if(in_array($name,$selected,true))continue;
                $free=noitru_assignment_capacity_for($capacity,$name)-count($slots[$name]);if($free<=0)continue;
                $fixed=(string)($roomGenders[$name]??'Linh hoạt');
                if($enforce&&$gender!=='Khác'&&$fixed!=='Linh hoạt'&&$fixed!==$gender)continue;
                if($enforce&&isset($runtimeGender[$name])&&$runtimeGender[$name]!==$gender)continue;
                $fallback[]=$name;
            }
            foreach(noitru_assignment_sort_targets($fallback,$slots,$group,$capacity) as $name){$selected[]=$name;$freeTotal+=noitru_assignment_capacity_for($capacity,$name)-count($slots[$name]);if($freeTotal>=$needed)break;}
        }
        foreach($group as $student){
            $best=null;$bestScore=INF;
            foreach($selected as $name){
                $cap=noitru_assignment_capacity_for($capacity,$name);if(count($slots[$name])>=$cap)continue;
                $sameClass=count(array_filter($slots[$name],static fn($s)=>(string)($s['class_name']??'')===(string)($student['class_name']??'')));
                $score=$sameClass*1000+count($slots[$name])/$cap;
                if($score<$bestScore){$bestScore=$score;$best=$name;}
            }
            if($best===null)continue;
            $slots[$best][]=$student;$runtimeGender[$best]=$gender;$result[(string)$student['id']]=(string)$best;
        }
    }
    return $result;
}
function noitru_assignment_auto_meals(array $students,array $names,$capacity,array $options=[]): array {
    $maxGap=max(0,min(3,(int)($options['max_grade_gap']??0)));$balance=(bool)($options['balance_gender']??true);
    $slots=array_fill_keys($names,[]);$result=[];
    foreach(noitru_assignment_group_students($students,false) as $group){
        $group=$balance?noitru_assignment_interleave_gender($group):array_values($group);
        $empty=[];$partial=[];
        foreach($names as $name){
            $free=noitru_assignment_capacity_for($capacity,$name)-count($slots[$name]);if($free<=0)continue;
            if(!noitru_assignment_grade_compatible($slots[$name],$group,$maxGap))continue;
            if($slots[$name])$partial[]=$name;else$empty[]=$name;
        }
        $partial=noitru_assignment_sort_targets($partial,$slots,$group,$capacity);
        $needed=count($group);$emptyCapacity=array_sum(array_map(static fn($name)=>noitru_assignment_capacity_for($capacity,$name),$empty));
        $pool=$emptyCapacity>=$needed?$empty:array_merge($partial,$empty);$selected=[];$freeTotal=0;
        foreach($pool as $name){$selected[]=$name;$freeTotal+=noitru_assignment_capacity_for($capacity,$name)-count($slots[$name]);if($freeTotal>=$needed)break;}
        if($freeTotal<$needed){
            $fallback=array_values(array_filter($names,static fn($name)=>!in_array($name,$selected,true)&&count($slots[$name])<noitru_assignment_capacity_for($capacity,$name)));
            foreach(noitru_assignment_sort_targets($fallback,$slots,$group,$capacity) as $name){$selected[]=$name;$freeTotal+=noitru_assignment_capacity_for($capacity,$name)-count($slots[$name]);if($freeTotal>=$needed)break;}
        }
        foreach($group as $student){
            $best=null;$bestScore=INF;
            foreach($selected as $name){
                $cap=noitru_assignment_capacity_for($capacity,$name);if(count($slots[$name])>=$cap)continue;
                $sameClass=count(array_filter($slots[$name],static fn($s)=>(string)($s['class_name']??'')===(string)($student['class_name']??'')));
                $sameGender=count(array_filter($slots[$name],static fn($s)=>noitru_assignment_gender($s)===noitru_assignment_gender($student)));
                $score=$sameClass*1000+count($slots[$name])/$cap+($balance?$sameGender*.01:0);
                if($score<$bestScore){$bestScore=$score;$best=$name;}
            }
            if($best===null)continue;$slots[$best][]=$student;$result[(string)$student['id']]=(string)$best;
        }
    }
    return $result;
}
