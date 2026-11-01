<?php
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$root=dirname(__DIR__);require $root.'/includes/timetable_lesson_constraints.php';
$data=['blocked'=>['a'=>['teacher'=>'A','key'=>'2|Sáng|1'],'b'=>['teacher'=>'B']], 'class_blocked'=>[], 'scope_blocks'=>[['scope'=>'school','key'=>'3|Sáng|1'],['special_id'=>'s','key'=>'2|Sáng|2']], 'special_slots'=>['s'=>['id'=>'s','label'=>'Chào cờ']], 'fixed_lessons'=>['f'=>['subject'=>'Toán']], 'double_lessons'=>['d'=>['subject'=>'Văn']], 'subject_sequences'=>['q'=>['subjects'=>['A','B']]], 'subject_rules'=>['r'=>['subject'=>'Sinh']], 'subject_limit_exceptions'=>['X'=>['daily'=>3]], 'period_overrides'=>['p'=>2], 'plans'=>[['id'=>'published','entries'=>[['activity_id'=>'f','locked'=>true]]]],'settings'=>['days'=>[2,3]]];
$original=$data;$items=tlc_bulk_items($data);
$tokens=[];foreach($items as$item)if(in_array($item['field'],['special_slots','fixed_lessons','double_lessons','subject_sequences'],true))$tokens[]=$item['token'];
$result=tlc_bulk_delete($data,['confirm_bulk'=>'1','selected_constraints'=>$tokens]);
check(!$result['special_slots']&&!$result['fixed_lessons']&&!$result['double_lessons']&&!$result['subject_sequences'],'selected rows removed');
check(count($result['scope_blocks'])===1&&empty($result['scope_blocks'][0]['special_id']),'linked special block removed, unrelated block retained');
check($result['plans']===$original['plans']&&$result['settings']===$original['settings']&&$result['blocked']===$original['blocked'],'plans, locks, settings, unrelated rows unchanged');
check($data===$original,'pure all-or-nothing transformation');
foreach([
 ['confirm_bulk'=>'0','selected_constraints'=>$tokens],
 ['confirm_bulk'=>'1','selected_constraints'=>[]],
 ['confirm_bulk'=>'1','selected_constraints'=>array_fill(0,501,$tokens[0])],
 ['confirm_bulk'=>'1','selected_constraints'=>[$tokens[0],json_encode(['field'=>'plans','key'=>'0','hash'=>hash('sha256',serialize($data['plans'][0]))])]],
 ['confirm_bulk'=>'1','selected_constraints'=>[$tokens[0],json_encode(['field'=>'fixed_lessons','key'=>'missing','hash'=>'x'])]],
 ['confirm_bulk'=>'1','selected_constraints'=>[$tokens[0],json_encode(['field'=>'fixed_lessons','key'=>'f','hash'=>'old'])]],
 ['confirm_bulk'=>'1','selected_constraints'=>[$tokens[0],['bad']]],
 ['confirm_bulk'=>'1','selected_constraints'=>[json_encode(['field'=>'scope_blocks','key'=>'1','hash'=>hash('sha256',serialize($data['scope_blocks'][1]))])]]
]as$bad){$failed=false;try{tlc_bulk_delete($data,$bad);}catch(Throwable$e){$failed=true;}check($failed&&$data===$original,'invalid/stale/mixed selection rejected without mutation');}
$all=tlc_bulk_delete($data,['confirm_bulk'=>'1','selected_constraints'=>array_column($items,'token')]);
foreach(tlc_bulk_fields()as$field=>$_)check(empty($all[$field]),'all listed constraint categories supported');
check($all['plans']===$data['plans'],'all selection still preserves plan');
require $root.'/tests/support/load_functions.php';
function tke_warning($e){return '';}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
load_functions($root.'/tkb_editor.php',['tke_matrix']);
$entries=[['activity_id'=>'one','teacher'=>'GV A','subject'=>'Toán','classes'=>['6A'],'day'=>2,'session'=>'Sáng','period'=>1],['activity_id'=>'two','teacher'=>'GV A','subject'=>'Toán','classes'=>['6B'],'day'=>3,'session'=>'Sáng','period'=>2],['activity_id'=>'three','teacher'=>'GV B','subject'=>'Văn','classes'=>['6A'],'day'=>2,'session'=>'Sáng','period'=>2]];
$data=['special_slots'=>[]];$manualMode=false;$manualOpenSessions=[];
ob_start();echo '<!doctype html><meta charset="utf-8"><select id="teacherPreviewSelect"><option>GV A</option><option>GV B</option></select>';
tke_matrix([$entries[0]],['days'=>[2,3],'morning_periods'=>2,'afternoon_periods'=>0],'Lớp 6A',false,'6A','class-editor-card');
tke_matrix([$entries[0],$entries[1]],['days'=>[2,3],'morning_periods'=>2,'afternoon_periods'=>0],'TKB giáo viên GV A',true,'','teacher-preview-card');
$preview=ob_get_clean();
$source=file_get_contents($root.'/tkb_editor.php');$start=strpos($source,'var teacherPreviewEntries=null');$end=strpos($source,'function filterUnplaced()',$start);
check($start!==false&&$end!==false,'production preview extraction');
check(str_contains($source,'renderTeacherPreview(teacherPreviewSelect.value);'),'initial teacher preview binds immediately');
if(in_array('--html',$argv,true)){
 file_put_contents('/tmp/teacher-preview.html',$preview);
 file_put_contents('/tmp/teacher-preview.js','var allEntries='.json_encode($entries,JSON_UNESCAPED_UNICODE).';'.substr($source,$start,$end-$start));
 ob_start();$csrf='fake';$data=$original;require $root.'/includes/timetable_constraints_bulk_view.php';file_put_contents('/tmp/constraint-summary.html','<!doctype html><meta charset="utf-8">'.ob_get_clean());
}
echo "PASS all bulk categories, stale/mixed rejection, confirmation/limit, linked block cleanup, unchanged plan/settings, production teacher matrix and preview initialization\n";
