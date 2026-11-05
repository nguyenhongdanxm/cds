<?php
require __DIR__.'/../includes/program_assignment.php';
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function load_json($f,$d=[]){return str_contains($f,'program_assignments_')?['8A'=>['subject'=>'GDĐP','ranges'=>[['teacher'=>'Giáo viên A','from'=>1,'to'=>35]]]]:[];}
function get_active_version_id(){return 'v1';}
function get_teachers(){return [['name'=>'Giáo viên A'],['name'=>'Giáo viên B']];}
function get_teachers_sorted(){return ['Giáo viên A','Giáo viên B'];}
function get_subjects(){return ['GDĐP'=>['8'=>1],'Toán học'=>['8'=>4]];}
function get_classes(){return ['8A','8B'];}
function get_roles(){return [['name'=>'GVCN','periods'=>4,'need_class'=>true]];}
function get_assignments(){return [['id'=>'a','teacher'=>'Giáo viên A','subject'=>'Toán học','class'=>'8A','periods'=>4,'note'=>''],['id'=>'a2','teacher'=>'Giáo viên A','subject'=>'Toán học','class'=>'8B','periods'=>4,'note'=>'']];}
function get_manual_assignments(){return [['id'=>'manual1','teacher'=>'Giáo viên A','subject'=>'Ngữ văn','class'=>'8B','periods'=>0.3,'is_manual'=>true]];}
function get_role_assignments(){return [];}
function get_teacher_loads(){return ['Giáo viên A'=>['day'=>9,'role'=>0,'total'=>9,'quota'=>17,'diff'=>-8,'level'=>'THCS']];}
function get_quota($t){return 17;}
function get_teacher_level($t){return 'THCS';}
function get_teacher_flags($t){return ['khxh'=>true];}
function get_teacher_chuyen_mon($t){return ['Toán học'];}
function get_grade($c){return '8';}
function sort_teachers_by_ten($list){return $list;}
function get_version($v){return ['name'=>'Phiên bản thử'];}
define('BASE_URL','/chuyenmon/');define('DATA_PATH','fixture');$_SESSION=['gdp_csrf'=>'fixture'];$_GET=[];
$active_ver=['name'=>'Phiên bản thử'];
$src=file_get_contents(__DIR__.'/../chuyenmon/them.php');$view=explode("require_once 'includes/header.php';",$src,2)[1];
$view=str_replace("__DIR__.'/includes/program_assignment_view.php'",var_export(__DIR__.'/../chuyenmon/includes/program_assignment_view.php',true),$view);
$view=str_replace("__DIR__.'/includes/pccm_teacher_subjects.php'",var_export(__DIR__.'/../chuyenmon/includes/pccm_teacher_subjects.php',true),$view);
$view=str_replace("__DIR__.'/includes/pccm_workspace_view.php'",var_export(__DIR__.'/../chuyenmon/includes/pccm_workspace_view.php',true),$view);
$view=str_replace("require_once 'includes/footer.php';",'', $view);
ob_start();eval($view);$body=ob_get_clean();$body='<script>document.addEventListener("DOMContentLoaded",()=>{const p=document.createElement("section");p.id="cdsManualAssignments";p.textContent="Phân công thủ công";document.getElementById("board").after(p);});</script>'.$body;
$doc='<!doctype html><html><head><meta charset="utf-8"><base href="https://workspace.test/chuyenmon/"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://workspace.test/bootstrap.css"><style>'.file_get_contents(__DIR__.'/../chuyenmon/assets/pccm-workspace.css').'</style></head><body class="cm-body"><main class="container py-3">'.$body.'</main><script src="https://workspace.test/bootstrap.js"></script></body></html>';
file_put_contents('/tmp/pccm-workspace.html',$doc);echo "PASS actual workspace rendered with isolated assignments and base href\n";
