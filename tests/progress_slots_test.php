<?php
require __DIR__.'/support/load_functions.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function lb_tkb_week($week){return ['id'=>'fixture','start_date'=>$week['start']];}
function tkb_resolved_slots($tkb){return $GLOBALS['slots'];}
function tkb_week_slot_date($tkb,$day){return date('Y-m-d',strtotime($tkb['start_date'].' +'.($day-2).' days'));}
function tkb_substitution_for_slot($tkb,$slot){return $slot['fixture_sub']??null;}
function tkb_substitutions(){return $GLOBALS['substitutions'];}
function tkb_substitution_status($row){return $row['status']??'approved';}
function tkb_subject_canonical($subject){return $subject==='Tiếng Anh'?'Ngoại ngữ':$subject;}
function lb_norm($text){return strtolower(trim($text));}
function lb_grade($class){preg_match('/\d+/',$class,$m);return $m[0]??'';}
function lb_slot_id($week,$row){return hash('sha256',json_encode([$week,$row]));}
function lb_saved_row_for_base($saved,$all,$base,&$matched){return [];}
function lb_merge_independent_records($out,$all,$week){return $out;}
function lb_ppct_enrich($row){return $row+['school_year_key'=>'year','class_key'=>$row['class'],'subject_key'=>$row['subject']];}
function lb_ppct_next_period($used){return count($used)+1;}
function lb_curriculum_for($subject,$grade,$period,$class){$GLOBALS['curriculum_calls']++;return ['title'=>'Bài '.$period];}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_slots']);
function project($rows){$out=[];foreach($rows as$row){$key=implode('|',[$row['date'],$row['class'],$row['subject']]);$out[$key]=($out[$key]??0)+1;}ksort($out);return $out;}
$week=['key'=>'fixture','label'=>'Tuần thử','start'=>'2026-09-07','end'=>'2026-09-13'];
for($case=0;$case<40;$case++){
    $GLOBALS['slots']=[];$GLOBALS['substitutions']=[];
    foreach(['6A','10A']as$class)foreach(['Toán','Tiếng Anh']as$subject)for($p=1;$p<=3;$p++){
        $row=['class'=>$class,'teacher'=>'GV gốc','subject'=>$subject,'day'=>2+$case%5,'session'=>'Sáng','period'=>$p];
        if($p===2){$sub=['id'=>'sub-'.$class.'-'.$subject,'date'=>'2026-09-07','status'=>'approved','type'=>$case%2?'fill':'substitute','substitute_teacher'=>'GV thay','absent_teacher'=>'GV gốc','replacement_subject'=>'Văn'];$row['fixture_sub']=$sub;$GLOBALS['substitutions'][]=$sub;}
        $GLOBALS['slots'][]=$row;
    }
    $GLOBALS['substitutions'][]=['id'=>'makeup','date'=>'2026-09-09','class'=>'10A','subject'=>'Toán','period'=>5,'type'=>'makeup','substitute_teacher'=>'GV bù','status'=>'approved'];
    $GLOBALS['substitutions'][]=['id'=>'pending','date'=>'2026-09-09','class'=>'10A','subject'=>'Toán','period'=>6,'substitute_teacher'=>'Chưa duyệt','status'=>'pending'];
    $GLOBALS['curriculum_calls']=0;$full=lb_slots($week,[]);check($GLOBALS['curriculum_calls']>0,'Old detailed path still derives PPCT');
    $GLOBALS['curriculum_calls']=0;$light=lb_slots($week,[],true);
    check($GLOBALS['curriculum_calls']===0,'Light path never loads per-slot curriculum');
    check(project($full)===project($light),'Exact date/class/subject counts including substitution/fill/makeup at '.$case);
}
echo "PASS: 40 actual lb_slots projection parity fixtures; normal/substitute/fill/makeup and canonical subjects; default detailed path unchanged\n";
