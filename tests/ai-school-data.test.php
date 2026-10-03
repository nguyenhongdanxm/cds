<?php
require_once dirname(__DIR__).'/includes/ai_school_data.php';
function current_user(){return $GLOBALS['test_user'];}
function can_perm($permission){return in_array($permission,$GLOBALS['test_permissions'],true);}
function noitru_student_is_boarder($s){return !empty($s['boarder']);}
function noitru_student_is_active_on_date($s,$date){return ($s['admission_date']??'')<=$date&&(!empty($s['departure_date'])?$date<=$s['departure_date']:!empty($s['active']));}
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
function rejected($request){try{cds_ai_data_filters($request);return false;}catch(InvalidArgumentException$e){return true;}}
$GLOBALS['test_user']=['role'=>'gvcn','classes'=>[],'homeroom_classes'=>['10A']];
$GLOBALS['test_permissions']=['csdl.students','nt.baoan','nt.yte','cm.baocao.tiendo','csdl.teachers'];
check(cds_ai_data_scope()===['10A'],'Homeroom scope');
check(!isset(cds_ai_data_sources()['staff']),'Restricted staff source');
$f=cds_ai_data_filters(['data_from'=>'2026-09-01','data_to'=>'2026-09-30','data_source'=>'students']);
check(rejected(['data_from'=>'2026-02-30','data_to'=>'2026-09-30']),'Invalid calendar day');
check(rejected(['data_from'=>'2026-01-01','data_to'=>'2026-09-30']),'Bounded period');
check(rejected(['data_from'=>'2026-09-01','data_to'=>'2026-09-30','data_class'=>'10B']),'No cross-class access');
check(rejected(['data_from'=>'2026-09-01','data_to'=>'2026-09-30','data_source'=>'rice']),'No unauthorized source');
$students=[['id'=>'1','class_id'=>'a','gender'=>'Nam','active'=>true,'boarder'=>true,'cccd'=>'PRIVATE'],['id'=>'2','class_id'=>'b','gender'=>'Nữ','active'=>true],['id'=>'3','class_id'=>'a','active'=>true,'admission_date'=>'2026-10-01'],['id'=>'4','class_id'=>'a','active'=>false,'departure_date'=>'2026-09-16']];
$data=cds_ai_data_population($students,$f,['a'=>'10A','b'=>'10B']);
check($data['total']===1&&$data['male']===1,'Population/date isolation');
check(strpos(json_encode($data),'PRIVATE')===false,'Private student fields excluded');
$f['to']='2026-09-16';check(cds_ai_data_population($students,$f,['a'=>'10A','b'=>'10B'])['total']===2,'Departure day included per meals rule');
$f['to']='2026-09-30';
$lessons=[['date'=>'2026-09-15','class'=>'10A','actual_teacher'=>'Teacher A','signed_at'=>'2026-09-16'],['date'=>'2026-09-15','class'=>'10A','actual_teacher'=>'Teacher A'],['date'=>'2026-09-15','class'=>'10B','actual_teacher'=>'Teacher B'],['date'=>'2026-10-01','class'=>'10A'],['date'=>'2026-09-15','class'=>'10A','signed_snapshot'=>['class'=>'10B']]];
$summary=cds_ai_data_lesson_summary($lessons,$f);
check($summary['recorded']===2&&$summary['signed']===1&&$summary['unsigned_recorded']===1,'Lesson signature counts, snapshot and permission isolation');
$GLOBALS['test_user']=['role'=>'gv','classes'=>[]];check(cds_ai_data_sources()===[],'Unassigned teacher denied');
$GLOBALS['test_user']=['role'=>'admin'];check(cds_ai_data_scope()===null,'Admin whole-school scope');
$GLOBALS['test_user']=['role'=>'bgh','classes'=>['10A']];check(cds_ai_data_scope()===['10A'],'Management explicit restriction preserved');
echo "AI school data tests passed\n";
