<?php
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
require __DIR__.'/support/load_functions.php';
function lb_norm($s){return mb_strtolower(trim($s),'UTF-8');}function lb_same($a,$b){return lb_norm($a)===lb_norm($b);}function lb_subject_match($a,$b){return lb_same($a,$b);}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}define('BASE_URL','/chuyenmon/');define('LB_AUDIT_FILE','fixture-audit');
function lb_weeks(){return [['key'=>'8','label'=>'Tuần 8','start'=>'2026-10-26','end'=>'2026-11-01']];}function lb_week($key){return $key==='8'?lb_weeks()[0]:null;}
function lb_stat_rows($from,$to){$GLOBALS['reads']['stats']++;check($from==='2026-10-26'&&$to==='2026-11-01','Only chosen week, not entire year');return $GLOBALS['fixtureRows'];}
function lb_rows($f){$GLOBALS['reads']['audit']++;return [['action'=>'sign_record','by'=>'GV <A>','at'=>'2026-10-28T10:00:00+07:00','class'=>'6A']];}
function lb_is_admin(){return true;}function lb_teacher_name(){return 'GV <A>';}
function lb_rating_points(){$GLOBALS['reads']['scores']++;return ['Tốt'=>10,'Khá'=>8,'Trung bình'=>6,'Yếu'=>4];}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_stat_filter_rows','lb_stat_status','lb_stat_group','lb_stat_totals','lb_score_ranking']);
require __DIR__.'/../chuyenmon/includes/lesson_book_attendance.php';
$GLOBALS['fixtureRows']=[
 ['date'=>'2026-10-26','session'=>'Sáng','class'=>'6A','actual_teacher'=>'GV <A>','subject'=>'Toán','_completed'=>true,'signed_at'=>'signed','rating'=>'Tốt','absent_names'=>"Nguyễn A, Trần B"],
 ['date'=>'2026-10-26','session'=>'Sáng','class'=>'6A','actual_teacher'=>'GV <A>','subject'=>'Toán','_completed'=>false,'rating'=>'Khá','absent_names'=>"Nguyễn A; Trần B\n Lê C"],
 ['date'=>'2026-10-26','session'=>'Chiều','class'=>'6A','actual_teacher'=>'GV B','subject'=>'Văn','_completed'=>false,'absent_names'=>'Nguyễn A'],
 ['date'=>'2026-10-27','session'=>'Sáng','class'=>'6B','actual_teacher'=>'GV B','subject'=>'Văn','_completed'=>false,'absent_names'=>'<script>alert(1)</script>'],
];
function renderStats(array $query){$_GET=$query;$weekKey='8';$week=lb_weeks()[0];$tab='progress';$GLOBALS['reads']=['stats'=>0,'audit'=>0,'scores'=>0];ob_start();require __DIR__.'/../chuyenmon/includes/lesson_book_statistics_view.php';return [ob_get_clean(),$GLOBALS['reads']];}
foreach(['scores','indicators','attendance','audit','invalid']as$section){[$html,$reads]=renderStats(['stats_section'=>$section]);check(array_sum($reads)===0,'Entering any tab must not read reports');preg_match_all('/<nav.*?<\/nav>/s',$html,$nav);check(substr_count(implode('',$nav[0]),'stats_section=')===4,'Exactly four navigation links');check(!str_contains(implode('',$nav[0]),'stats_load')&&!str_contains(implode('',$nav[0]),'audit_load'),'Navigation never carries load flags');}
foreach(['scores','indicators','attendance']as$section){[$html,$reads]=renderStats(['stats_section'=>$section,'stats_load'=>'1']);check($reads['stats']===1&&$reads['audit']===0,'Only chosen report read once');check(($reads['scores']>0)===($section==='scores'),'Points loaded only in points tab');if($section==='indicators'){check(str_contains($html,'text-danger')&&str_contains($html,'1 / 2')&&str_contains($html,'Đã ký / Cần ký'),'Unsigned subjects/teachers red and signed/required totals');check(!str_contains($html,'<script>alert(1)</script>'),'HTML escaped');}if($section==='attendance')check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;'),'Attendance names escaped');if($section==='scores')check(str_contains($html,'9,00'),'Existing rating average preserved');}
[$html,$reads]=renderStats(['stats_section'=>'audit','audit_load'=>'1','stats_load'=>'1']);check($reads===['stats'=>0,'audit'=>1,'scores'=>0],'Audit submit never constructs slot statistics');check(str_contains($html,'name="stats_section" value="audit"'),'Audit submits to same section');
[$html,$reads]=renderStats(['stats_section'=>'scores','audit_load'=>'1']);check(array_sum($reads)===0,'A load flag for another tab cannot load that report');
$summary=lb_attendance_summary($GLOBALS['fixtureRows']);check($summary['student_sessions']===5&&$summary['students']===4&&count($summary['groups'])===3,'Unique per buoi, distinct class/name, no double counting periods');
$filtered=lb_stat_filter_rows($GLOBALS['fixtureRows'],'GV <A>','','6A','all');check(count($filtered)===2&&lb_attendance_summary($filtered)['student_sessions']===3,'Existing class/teacher filter applied to attendance');
// Guard actual page dispatch, not just the included report views.
$source=file_get_contents(__DIR__.'/../chuyenmon/sodaubai.php');$begin=strpos($source,'$slots=');$end=strpos($source,';$me=',$begin);$snippet=substr($source,$begin,$end-$begin+1);$tab='progress';$week=lb_weeks()[0];eval($snippet);check($slots===[],'Opening progress never constructs daily timetable');
check(str_contains($source,"require __DIR__.'/includes/lesson_book_statistics_view.php'"),'Actual progress route uses lazy dispatcher');
// Viewer still GET-only, and only receives the explicit reviewed filter keys.
require __DIR__.'/../includes/viewer_access.php';check(cds_viewer_request_allowed('GET','chuyenmon/sodaubai.php',['tab'=>'progress','stats_section'=>'attendance','stats_load'=>'1','stats_week'=>'8']),'Viewer can read new reports');check(!cds_viewer_request_allowed('POST','chuyenmon/sodaubai.php',[]),'Viewer cannot write');
if(in_array('--html',$argv,true)){[$html]=renderStats(['stats_section'=>'indicators','stats_load'=>'1']);file_put_contents('/tmp/lb-statistics.html',$html);}
echo "PASS statistics: four lazy tabs, zero report reads on entry, isolated report reads, current week, existing scoring/totals, red unsigned, absence deduplication, escaping, viewer read-only\n";
