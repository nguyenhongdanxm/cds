<?php
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/json_store.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/cds-progress-test-'.bin2hex(random_bytes(8));mkdir($root,0700);
define('DATA_PATH',$root);
foreach(['TKB_WEEKS_FILE','TKB_MAPPING_FILE','TKB_SUBSTITUTIONS_FILE','TKB_ROOMS_FILE','TKB_ROOM_BOOKINGS_FILE']as$name){define($name,$root.'/'.$name.'.json');file_put_contents(constant($name),'[]');}
require __DIR__.'/../chuyenmon/includes/progress_runtime.php';
function lb_weeks(){return $GLOBALS['weeks'];}
function lb_norm($value){return strtolower(trim($value));}
function lb_subject_key($value){return lb_norm($value);}
function lb_grade($class){return cm_progress_grade($class);}
function lb_slots($week,$saved=[],$scheduleOnly=false){$GLOBALS['slot_calls'][]=[$week['number'],$scheduleOnly];return $GLOBALS['slots'][$week['number']]??[];}
function lb_record_map_range($from,$to){return array_filter($GLOBALS['records'],fn($r)=>$r['date']>=$from&&$r['date']<=$to);}
function lb_curriculum_titles_for($subject,$grade,$class){$max=$GLOBALS['ppct_limit']??30;return $max?['1'=>'A',$max=>'B']:[];}
load_functions(__DIR__.'/../chuyenmon/baocao.php',['cm_progress_auto_metrics','cm_progress_scope_key','cm_progress_num','cm_progress_assignment_key','cm_progress_name_key']);
function cm_progress_school_year(){return ['id'=>'fixture','start'=>'2026-08-03','end'=>'2026-10-11'];}
function cm_progress_weeks($year){return $GLOBALS['weeks'];}
function get_assignments(){return $GLOBALS['assignments'];}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function cds_can_feature($feature,$level){return $level!=='delete'||($_SESSION['cds_user']['role']??'')==='admin';}
require __DIR__.'/support/progress_metrics_reference.php';
try{
    $legacy=$root.'/legacy.json';$fixture=['old-key'=>['id'=>'historical','week_number'=>2]];cds_json_save($legacy,$fixture);$hash=hash_file('sha256',$legacy);
    check(iterator_to_array((function()use($legacy){yield from cm_progress_records_for_view($legacy);})())===$fixture,'Legacy keyed history remains readable');
    check(hash_file('sha256',$legacy)===$hash,'History view never rewrites legacy data');
    $GLOBALS['weeks']=[];$GLOBALS['slots']=[];$GLOBALS['records']=[];
    for($n=1;$n<=10;$n++){
        $start=(new DateTimeImmutable('2026-08-03'))->modify('+'.(($n-1)*7).' days');
        $week=['number'=>$n,'key'=>'year-'.$n,'label'=>'Tuần '.$n,'start'=>$start->format('Y-m-d'),'end'=>$start->modify('+6 days')->format('Y-m-d')];
        $GLOBALS['weeks'][]=$week;
        foreach(['6A','10A','11B']as$class)foreach(['Toán','Văn']as$subject){
            $GLOBALS['slots'][$n][]=['date'=>$week['start'],'class'=>$class,'subject'=>$subject];
            $GLOBALS['slots'][$n][]=['date'=>$week['end'],'class'=>$class,'subject'=>$subject];
            $GLOBALS['records'][]=['date'=>$week['start'],'class'=>$class,'subject'=>$subject,'signed_at'=>'yes','status'=>['taught','substitute','makeup','online'][$n%4],'ppct_period'=>$n*2];
            $GLOBALS['records'][]=['date'=>$week['start'],'class'=>$class,'subject'=>$subject,'status'=>'taught','ppct_period'=>999];
        }
    }
    $weeks=$GLOBALS['weeks'];$today='2026-09-10';
    check(cm_progress_selected_week($weeks,'',$today)['number']===6,'Default current week');
    check(cm_progress_selected_week($weeks,'3',$today)['number']===3,'Explicit past week');
    check(cm_progress_selected_week($weeks,'999',$today)['number']===6,'Invalid selection falls back to current');
    check(cm_progress_selected_week($weeks,'','2026-01-01')['number']===1,'Before year');
    check(cm_progress_selected_week($weeks,'','2027-01-01')['number']===10,'After year selects nearest past week');
    check(cm_progress_grade('Lớp 10A')==='10'&&cm_progress_grade('6A')==='6','Grade extraction');
    $assignments=['a'=>['class'=>'10A','subject'=>'Toán','periods'=>4],'b'=>['class'=>'10A','subject'=>'Văn','periods'=>3]];
    $GLOBALS['slot_calls']=[];
    $result=cm_progress_auto_metrics($assignments,['start'=>'2026-08-03'],$weeks[5]);
    check(count($GLOBALS['slot_calls'])===6&&array_column($GLOBALS['slot_calls'],0)===range(1,6),'Only required historical aggregates, never future weeks');
    check(!in_array(false,array_column($GLOBALS['slot_calls'],1),true),'Skip detailed PPCT slot derivation');
    check($result['a']['scheduled_period']===12&&$result['a']['actual_period']===12&&$result['a']['ppct_period']===12,'Cumulative formula retained');
    check(count($result)===2&&!isset($result['6A']),'Filtered assignment scope retained');
    $GLOBALS['slot_calls']=[];
    check(cm_progress_auto_metrics($assignments,['start'=>'2026-08-03'],$weeks[5])===$result&&!$GLOBALS['slot_calls'],'Warm selected week does not rebuild timetable');
    cm_progress_auto_metrics($assignments,['start'=>'2026-08-03'],$weeks[6]);
    check($GLOBALS['slot_calls']===[[7,true]],'Selecting another week builds only its missing aggregate');
    $GLOBALS['records'][]=['date'=>$weeks[5]['start'],'class'=>'10A','subject'=>'Toán','signed_at'=>'yes','status'=>'online','ppct_period'=>15];
    $GLOBALS['slot_calls']=[];$fresh=cm_progress_auto_metrics($assignments,['start'=>'2026-08-03'],$weeks[5]);
    check($fresh['a']['actual_period']===15&&!$GLOBALS['slot_calls'],'Signed PPCT is always fresh, not cached');
    $version=cm_progress_schedule_version();file_put_contents(TKB_MAPPING_FILE,'{"v":1}');$first=cm_progress_schedule_version();
    $mtime=filemtime(TKB_MAPPING_FILE);file_put_contents(TKB_MAPPING_FILE,'{"v":2}');touch(TKB_MAPPING_FILE,$mtime);clearstatcache();
    check($version!==$first&&$first!==cm_progress_schedule_version(),'Source content invalidates even at identical size/mtime');
    $GLOBALS['slots'][6][]=['date'=>$weeks[5]['start'],'class'=>'10A','subject'=>'Toán'];$GLOBALS['slot_calls']=[];
    $changed=cm_progress_auto_metrics($assignments,['start'=>'2026-08-03'],$weeks[5]);check($changed['a']['scheduled_period']===13&&count($GLOBALS['slot_calls'])===6,'Changed timetable rebuilds accurate aggregates');
    foreach(glob(cm_progress_schedule_cache_dir().'/*.json')as$file)file_put_contents($file,'{broken');
    check(cm_progress_auto_metrics($assignments,['start'=>'2026-08-03'],$weeks[5])===$changed,'Corrupt cache recomputes safely');
    for($case=0;$case<40;$case++){
        $GLOBALS['ppct_limit']=[0,5,30,100][$case%4];$target=$weeks[$case%10];
        $year=['start'=>(new DateTimeImmutable('2026-08-03'))->modify('+'.($case%3).' days')->format('Y-m-d')];
        check(cm_progress_auto_metrics($assignments,$year,$target)===reference_cm_progress_auto_metrics($assignments,$year,$target),'Original cumulative PPCT parity at '.$case);
    }
    echo "PASS: 40 original progress parity fixtures, partial weeks, missing PPCT and planned-period caps\n";
    $GLOBALS['assignments']=[['teacher'=>'GV6','class'=>'6A','subject'=>'Toán','periods'=>4],['teacher'=>'GV10','class'=>'10A','subject'=>'Toán','periods'=>4],['teacher'=>'GV11','class'=>'11B','subject'=>'Văn','periods'=>3]];
    $page=file_get_contents(__DIR__.'/../chuyenmon/baocao.php');$start=strpos($page,'$progressFile =');$end=strpos($page,"if (\$_SERVER['REQUEST_METHOD'] === 'POST')",$start);
    $setup=substr($page,$start,$end-$start);$tab='tiendo';$_SERVER['REQUEST_METHOD']='GET';
    $_SESSION=['cds_user'=>['role'=>'admin','teacher_name'=>'Admin']];$_GET=['view'=>'thongke','grade'=>'10','class'=>'6A','week'=>'6'];
    eval($setup);
    check(count($progressMetricAssignments)===1&&reset($progressMetricAssignments)['class']==='10A','Grade filter applied before calculation');
    check($progressClassFilter===''&&$progressWeekNumber===6,'Grade change clears conflicting class and preserves selected week');
    $_SESSION=['cds_user'=>['role'=>'gv','teacher_name'=>'GV6']];$_GET=['view'=>'thongke','grade'=>'11','week'=>'6'];
    eval($setup);
    check($progressView==='nhaplieu'&&count($progressMetricAssignments)===1&&reset($progressMetricAssignments)['teacher']==='GV6','Non-admin scope unchanged despite forged statistics filters');
    echo "PASS progress: current/selected week, no future preload, warm cache, new week only, source invalidation, fresh signatures, cumulative totals and corrupt cache\n";
}finally{
    foreach(glob(cm_progress_schedule_cache_dir().'/*.json')as$file)unlink($file);@rmdir(cm_progress_schedule_cache_dir());
    foreach(glob($root.'/*')as$file)unlink($file);rmdir($root);
}
