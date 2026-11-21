<?php
require_once dirname(__DIR__,2).'/includes/program_assignment.php';
function lb_sign_required_fields(array $row): array {
 $missing=[];if(!in_array(trim((string)($row['status']??'')),['taught','substitute','makeup','online','holiday','teacher_absent','class_absent','postponed','cancelled','fill'],true))$missing[]='trạng thái';
 if(trim((string)($row['teacher_comment']??''))==='')$missing[]='nhận xét';
 if(!in_array((string)($row['rating']??''),['Tốt','Khá','Trung bình','Yếu'],true))$missing[]='xếp loại';return $missing;
}

/** Kept for callers: program metadata is a suggestion, never a signing lock. */
function lb_program_period_error(array $slot,array $input): string {return '';}
function lb_program_warning(array $row): string {
 require_once dirname(__DIR__,2).'/includes/program_assignment.php';
 if(!gdp_subject((string)($row['subject']??''))||!in_array((string)($row['status']??'taught'),['taught','substitute','makeup','online'],true))return '';
 require_once dirname(__DIR__,2).'/includes/pccm_release.php';
 $published=pccm_published_source(DATA_PATH);
 $version=(string)((load_json(DATA_PATH.'/active_version.json',[]))['id']??'');
 $configs=$published?$published['configs']:gdp_load(DATA_PATH,$version);
 $class=(string)($row['class']??'');
 return isset($configs[$class])?gdp_assignment_warning($configs[$class],$class,(int)($row['ppct_period']??0),(string)($row['actual_teacher']??$row['scheduled_teacher']??'')):'';
}
function lb_program_warning_result(array $result,array $row): array {
 $warning=lb_program_warning($row);
 if($warning!==''){$result['type']='warning';$result['warnings']=[$warning];$result['message'].=' '.$warning;}
 return $result;
}
