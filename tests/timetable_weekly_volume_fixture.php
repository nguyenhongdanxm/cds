<?php
/** Actual weekly-volume view/actions on disposable local data only. */
$dir=getenv('CDS_VOLUME_FIXTURE');if(!$dir||!is_dir($dir)){http_response_code(404);exit;}
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/json_store.php';
require __DIR__.'/../includes/program_assignment.php';
require __DIR__.'/../includes/pccm_release.php';
define('DATA_PATH',$dir);define('PCCM_DATA_PATH',$dir);define('BASE_PATH',dirname(__DIR__));define('TKB_WEEKS_FILE',$dir.'/weeks.json');
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function cds_school_week_calendar(){return [['key'=>'10','start'=>'2026-11-09','end'=>'2026-11-15']];}
function cds_lb_read_effective(){return false;}
function tkb_subject_canonical($s){return $s;}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function ttb_class_grade(string $class):string{preg_match('/^(\d+)/',$class,$m);return $m[1]??'';}
function ttb_save($data){return cds_json_save(DATA_PATH.'/workspace.json',$data);}
function flash($message,$type){}
load_functions(__DIR__.'/../includes/timetable_builder.php',['ttb_pccm_dir','ttb_assignments','ttb_activities']);
if(($argv[1]??'')==='--init'){
 $rows=[];$configs=[];foreach(['6A','6B','9A']as$class){$configs[$class]=['subject'=>'GDĐP','ranges'=>[['teacher'=>'GV '.$class,'from'=>1,'to'=>35]]];for($n=0;$n<32;$n++)$rows[]=['id'=>$class.'-m'.$n,'teacher'=>'GV '.$class,'subject'=>'Môn '.sprintf('%02d',$n),'class'=>$class,'periods'=>1];}
 cds_json_save($dir.'/active_version.json',['id'=>'v1']);cds_json_save($dir.'/assignments_v1.json',$rows);cds_json_save(gdp_file($dir,'v1'),$configs);cds_json_save($dir.'/workspace.json',['_workspace_id'=>'fixture','_workspace'=>['week_key'=>'10'],'period_overrides'=>[],'plans'=>[['id'=>'unchanged-plan','entries'=>[]]]]);exit;
}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/assets/timetable-weekly-volume.js'){header('Content-Type: application/javascript');readfile(__DIR__.'/../assets/timetable-weekly-volume.js');exit;}
$data=load_json($dir.'/workspace.json');$GLOBALS['CDS_TTB_CURRENT_DATA']=$data;$assignments=ttb_assignments();
if($path==='/state'){header('Content-Type: application/json');echo json_encode(['assignments'=>$assignments,'activities'=>ttb_activities($assignments,[]),'data'=>$data,'source_hash'=>hash_file('sha256',$dir.'/assignments_v1.json')]);exit;}
$source=file_get_contents(__DIR__.'/../tkb_xep.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals('fixture-csrf',(string)($_POST['csrf']??''))){http_response_code(403);exit;}
 $action=(string)($_POST['action']??'');$a=strpos($source,"  if($"."action==='period_overrides_save')");$b=strpos($source,"  if($"."action==='subject_rules_save')",$a);eval(substr($source,$a,$b-$a));exit;
}
$a=strpos($source,"<?php elseif($"."tab==='data'):");$b=strpos($source,"<?php elseif($"."tab==='guide')",$a);$view=substr($source,$a,$b-$a);
$view=str_replace("<?php elseif($"."tab==='data'):",'<?php if(true):',$view);
$view=str_replace("require __DIR__.'/includes/program_week_view.php';",'',$view);
$workspaceId='fixture';$csrf='fixture-csrf';
?><!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;font:16px Arial}main{padding:20px}#intro{height:1000px}.table-responsive{overflow:auto}table{border-collapse:collapse;width:100%}td,th{height:40px;border:1px solid #ddd;padding:4px}.card{border:1px solid #aaa}h5{margin:12px}select{padding:8px}.p-3{padding:16px}.sticky-top{position:sticky;top:0;background:white}#footer{height:800px}button,input{padding:8px}</style></head><body><main><div id="intro">Synthetic weekly-volume browser fixture</div><?php eval('?>'.$view.'<?php endif; ?>');?><div id="footer"></div></main></body></html>
