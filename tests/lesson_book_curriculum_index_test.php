<?php
require __DIR__.'/support/load_functions.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
define('LB_RECORDS_FILE','records');define('LB_CURRICULUM_FILE','curriculum');
function lb_curriculum(){return $GLOBALS['curriculum'];}
function lb_norm($s){return mb_strtolower(trim($s),'UTF-8');}
function lb_same($a,$b){return lb_norm($a)===lb_norm($b);}
function lb_same_subject($a,$b){return lb_same($a,$b);}
function lb_subject_match($a,$b){return lb_same($a,$b);}
function lb_subject_key($s){return lb_norm($s);}
function lb_is_gdtc_subject($s){return lb_same($s,'GDTC');}
function lb_curriculum_subject_for_class($s,$c){return $s==='GDTC'&&$c==='6B'?'GDTC-Bóng đá':$s;}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_curriculum_index','lb_curriculum_exact_rows','lb_curriculum_for','lb_curriculum_titles_for','lb_rows_bust']);
require __DIR__.'/support/lesson_book_curriculum_reference.php';
$rows=[];foreach(range(6,12)as$grade)foreach(['Toán','Văn','GDTC','GDTC-Bóng đá','Nghệ thuật (Âm nhạc)','Nghệ thuật (Mỹ thuật)']as$subject)foreach(range(1,35)as$period)$rows[]=['grade'=>(string)$grade,'subject'=>$subject,'period'=>$period,'title'=>$subject.' '.$period];
$rows[]=['grade'=>'6','subject'=>'Toán','period'=>7,'title'=>'Riêng lớp','scope_type'=>'class','scope_target'=>'6A'];$rows[]=['grade'=>'6','subject'=>'Toán','period'=>8,'title'=>'','scope_type'=>'class','scope_target'=>'6A'];$rows[]=['grade'=>'6','subject'=>'Toán','period'=>7,'title'=>'Trùng sau','scope_type'=>'class','scope_target'=>'6A'];
$rows[]=['grade'=>'Khối 6','subject'=>'Toán','period'=>7,'title'=>'Legacy grade','scope_type'=>'class','scope_target'=>'6A'];
$GLOBALS['curriculum']=$rows;$before=serialize($rows);
foreach(['6','7','12','','Khối 6']as$grade)foreach(['Toán','GDTC','Nghệ thuật (Âm nhạc)','Nghệ thuật (Mỹ thuật)','Không có']as$subject)foreach(['','6A','6B']as$class){check(lb_curriculum_titles_for($subject,$grade,$class)===reference_lb_curriculum_titles_for($subject,$grade,$class),'Identical ordered title lists');foreach([0,1,7,8,35,99]as$p)check(lb_curriculum_for($subject,$grade,$p,$class)===reference_lb_curriculum_for($subject,$grade,$p,$class),'Identical class/school/variant priority');}
check(serialize($GLOBALS['curriculum'])===$before,'No source mutation');$GLOBALS['curriculum'][0]['title']='Updated';lb_rows_bust(LB_CURRICULUM_FILE);check(lb_curriculum_for('Toán','6',1)['title']==='Updated','Writes invalidate request index');
echo "PASS curriculum index: exact results across grades/classes/variants/duplicates/missing titles; invalidation after edits\n";
