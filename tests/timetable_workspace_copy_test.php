<?php
/** Isolated JSON and SQL-shaped stores; never loads application config/live data. */
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/json_store.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/cds-tkb-copy-'.bin2hex(random_bytes(8));mkdir($root,0700);
define('DATA_PATH',$root);define('CDS_TTB_FILE',$root.'/legacy.json');define('CDS_TTB_WORKSPACES_FILE',$root.'/workspaces.json');
function ttb_store_load($key,$file,$default=[]){return $GLOBALS['mode']==='json'?cds_json_load($file,$default):($GLOBALS['sql'][$key]??$default);}
function ttb_store_save($key,$file,$data){if(($GLOBALS['fail']??'')===$key)return false;if($GLOBALS['mode']==='json')return cds_json_save($file,$data);$GLOBALS['sql'][$key]=$data;return true;}
function ttb_store_delete($key){unset($GLOBALS['sql'][$key]);return true;}
function tkb_subject_canonical($s){return $s;}
load_functions(__DIR__.'/../includes/timetable_builder.php',['ttb_default','ttb_workspaces','ttb_workspace','ttb_workspace_id','ttb_workspace_file','ttb_workspace_create','ttb_workspace_update_meta','ttb_canonicalize_subject_data','ttb_data','ttb_save','ttb_plan','ttb_replace_plan']);
try{foreach(['json','sql']as$mode){
    $GLOBALS['mode']=$mode;$GLOBALS['sql']=[];$_SESSION=[];$_REQUEST=[];
    $source=ttb_default();foreach($source as$key=>$value)if(is_array($value)&&$key!=='settings')$source[$key]=[['fixture'=>$key,'nested'=>['preserved'=>true]]];
    $source['settings']['max_teacher_daily']=3;$source['settings']['future_setting']='retained';
    $source['plans']=[];for($i=0;$i<12;$i++)$source['plans'][]=['id'=>'p'.$i,'entries'=>[['activity_id'=>'a','classes'=>['10A'],'subject'=>'Toán','day'=>2,'session'=>'Sáng','period'=>1,'locked'=>false]],'unplaced'=>[['id'=>'pending']],'errors'=>['existing'],'custom'=>['history'=>[1,2]]];
    $source['active_plan']='p5';$source['future_constraint']=['nested'=>['value'=>42]];$source['updated_at']='old';
    ttb_store_save('workspaces',CDS_TTB_WORKSPACES_FILE,[['id'=>'legacy','name'=>'Nguồn','week_key'=>'old']]);
    ttb_store_save('workspace:legacy',CDS_TTB_FILE,$source);
    $before=ttb_store_load('workspace:legacy',CDS_TTB_FILE);$hash=is_file(CDS_TTB_FILE)?hash_file('sha256',CDS_TTB_FILE):'';
    $row=ttb_workspace_create('Tuần mới','2026-w7','legacy');$id=$row['id'];$copy=ttb_store_load('workspace:'.$id,ttb_workspace_file($id));
    $expected=$source;$expected['updated_at']=$copy['updated_at'];check($copy===$expected,'Every source field and all 12 plans retained: '.$mode);
    check($row['week_key']==='2026-w7'&&$id!=='legacy'&&$_SESSION['ttb_workspace_id']===$id,'New week identity selected');
    $data=ttb_data();check($data['_workspace_id']===$id&&ttb_plan($data,$data['active_plan'])['id']==='p5','Active plan resolves in new workspace');
    $data['future_constraint']['nested']['value']=99;$plan=ttb_plan($data,$data['active_plan']);$plan['entries'][0]['period']=4;ttb_replace_plan($data,$plan);check(ttb_save($data),'Save edited copy');
    check(ttb_store_load('workspace:legacy',CDS_TTB_FILE)===$before,'Source remains byte-equivalent after copy edit');
    if($hash!=='')check(hash_file('sha256',CDS_TTB_FILE)===$hash,'Source JSON bytes unchanged');
    check(ttb_store_load('workspace:'.$id,ttb_workspace_file($id))['plans'][5]['entries'][0]['period']===4,'Only destination edited');
    $empty=ttb_workspace_create('Trống');$blank=ttb_store_load('workspace:'.$empty['id'],ttb_workspace_file($empty['id']));check($blank['plans']===[]&&$blank['blocked']===[],'Blank creation remains blank');
    $edit=ttb_workspace_create('Sửa bản công bố','','legacy',false);$editData=ttb_store_load('workspace:'.$edit['id'],ttb_workspace_file($edit['id']));check(!$editData['plans']&&$editData['settings']===$source['settings'],'Published-edit path retains original configuration-only behavior');
    $rows=ttb_workspaces();$session=$_SESSION;
    foreach(['missing','unreadable']as$bad){if($bad==='unreadable'){ $rows[]=['id'=>$bad];ttb_store_save('workspaces',CDS_TTB_WORKSPACES_FILE,$rows); }try{ttb_workspace_create('Invalid','',$bad);throw new LogicException('Unexpected success');}catch(RuntimeException $e){}check(ttb_workspaces()===$rows&&$_SESSION===$session,'Invalid source aborts without changes');}
    $GLOBALS['fail']='workspaces';$files=glob($root.'/timetable_builder_*.json');try{ttb_workspace_create('Fail','','legacy');throw new LogicException('Unexpected success');}catch(RuntimeException $e){}check(ttb_workspaces()===$rows&&$_SESSION===$session,'Registry failure preserves selection and registry');check(glob($root.'/timetable_builder_*.json')===$files,'No orphan JSON after registry failure');unset($GLOBALS['fail']);
    echo "PASS $mode: full clone, 12 plans, active plan, future fields, source isolation, blank/edit paths, invalid sources and failed registration\n";
}}finally{foreach(glob($root.'/*')as$file)unlink($file);rmdir($root);}
