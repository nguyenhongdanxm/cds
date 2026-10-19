<?php
function lb_norm(string $s):string{return strtolower(preg_replace('/\s+/u','',trim($s)));}
require __DIR__.'/../chuyenmon/includes/lesson_book_print_homeroom.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$teachers=[['id'=>'a','name'=>'Nguyễn Văn A'],['id'=>'b','name'=>'Trần Thị B']];
$classes=[['name'=>'6A','school_year_id'=>'old','homeroom_teacher_id'=>'b','active'=>1],['name'=>'6A','school_year_id'=>'current','homeroom_teacher_id'=>'a','active'=>1],['name'=>'7A','school_year_id'=>'current','homeroom_teacher_id'=>'b'],['name'=>'8A','school_year_id'=>'current','homeroom_teacher_id'=>'missing'],['name'=>'9A','school_year_id'=>'current','homeroom_teacher_id'=>'b','active'=>0],['name'=>'10A','homeroom_teacher_id'=>'a']];
$before=serialize([$classes,$teachers]);$map=lb_print_homeroom_map($classes,$teachers,'current');
check($map[lb_print_class_key('Lớp 6a')]==='Nguyễn Văn A','Current year and normalized class');
check($map[lb_print_class_key('7A')]==='Trần Thị B','Separate teachers by class');
check($map[lb_print_class_key('8A')]==='','Missing teacher stays blank');
check(!isset($map[lb_print_class_key('9A')]),'Inactive class excluded');
check($map[lb_print_class_key('10A')]==='Nguyễn Văn A','Legacy class without year supported');
check(lb_print_homeroom_map($classes,$teachers,'old')['6a']==='Trần Thị B','Academic year isolation');
$classes[]=['name'=>'6A','school_year_id'=>'current','homeroom_teacher_id'=>'b'];
check(lb_print_homeroom_map($classes,$teachers,'current')['6a']==='','Conflicting assignment never guessed');
array_pop($classes);check(serialize([$classes,$teachers])===$before,'Lookup does not mutate data');
$print=file_get_contents(__DIR__.'/../chuyenmon/sodaubai_print.php');check(!str_contains($print,"e(\$ps['homeroom_name'])"),'All signature sections use resolved class teacher');
check(substr_count($print,'e($homeroomName)')===3,'A3, paired A4 and weekly review signatures');
echo "PASS homeroom print: class/year isolation, missing/conflicting assignments, legacy classes, unchanged data, all three signature sections\n";
foreach(['a3','a4_pair']as$paper)foreach(['6A','7A','8A']as$class){
    $proc=proc_open([PHP_BINARY,__DIR__.'/support/lesson_book_print_fixture.php','class',$class,$paper],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$html=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0,'Render print fixture '.$err);
    check(!str_contains($html,'TÊN CHUNG CŨ KHÔNG ĐƯỢC IN'),'Never print shared default');
    if($class!=='8A')check(substr_count($html,'GVCN lớp '.$class)===2,'Correct teacher on lesson and weekly pages '.$paper);
    else check(!str_contains($html,'GVCN lớp'),'Unassigned class stays blank');
}
echo "PASS actual A3/A4 print rendering with synthetic classes and no operating data access\n";
