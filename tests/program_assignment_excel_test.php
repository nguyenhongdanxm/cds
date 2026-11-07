<?php
require __DIR__.'/../includes/json_store.php';require __DIR__.'/../includes/program_assignment.php';require __DIR__.'/../includes/program_assignment_excel.php';require __DIR__.'/../includes/pccm_release.php';require __DIR__.'/../chuyenmon/includes/program_excel_template.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function load_json($f,$d=[]){return cds_json_load($f,$d);}
function rejects(callable $call,string $message):void{try{$call();}catch(RuntimeException $e){return;}throw new RuntimeException($message);}
$catalog=gdp_excel_catalog([['code'=>'02001','name'=>'A'],['code'=>'02002','name'=>'B']],['A','B']);$context=['catalog'=>$catalog,'classes'=>['8A','8B'],'subjects'=>['GDĐP']];
$header=['row'=>1,'values'=>['Mã giáo viên','Họ tên','Lớp','Từ tiết','Đến tiết']];
$rows=[$header,['row'=>2,'values'=>['02001','A','8A','1','7']],['row'=>3,'values'=>['02002','','8A','8','35']]];
$valid=gdp_excel_preview($rows,$context,'GDĐP');check(!$valid['errors']&&gdp_teacher($valid['configs']['8A'],7)==='A'&&gdp_teacher($valid['configs']['8A'],8)==='B','Exact codes map correct PPCT boundary');
foreach([[0,'2001'],[0,'unknown'],[1,'B'],[2,'9Z'],[3,'0'],[3,'1.5'],[4,'36']]as[$col,$value]){$bad=$rows;$bad[1]['values'][$col]=$value;check(gdp_excel_preview($bad,$context,'GDĐP')['error_count']>0,'Invalid row rejected');}
$bad=$rows;$bad[2]['values'][3]='7';check(gdp_excel_preview($bad,$context,'GDĐP')['error_count']>0,'Overlap rejected');$bad[2]['values'][3]='9';check(gdp_excel_preview($bad,$context,'GDĐP')['error_count']>0,'Gap rejected');
$dup=$context;$dup['catalog']=gdp_excel_catalog([['code'=>'02001','name'=>'A'],['code'=>'02001','name'=>'B']],['A','B']);check(gdp_excel_preview($rows,$dup,'GDĐP')['error_count']>0,'Duplicate CSDL codes rejected');
$dup['catalog']=gdp_excel_catalog([['code'=>'02001','name'=>'A'],['code'=>'02002','name'=>'A']],['A']);check(gdp_excel_preview($rows,$dup,'GDĐP')['error_count']>0,'Ambiguous CSDL to PCCM names rejected');
$dir=sys_get_temp_dir().'/gdp-excel-'.bin2hex(random_bytes(5));mkdir($dir,0700);$file=gdp_file($dir,'v1');$before=['8B'=>['subject'=>'GDĐP','ranges'=>[['teacher'=>'A','from'=>1,'to'=>35]]]];cds_json_save($file,$before);$hash=hash('sha256',json_encode($before));
try{
 rejects(fn()=>gdp_excel_apply($dir,'v1',$valid['configs'],'stale'),'Stale preview cannot replace');check(load_json($file)===$before,'No partial write on rejection');
 gdp_excel_apply($dir,'v1',$valid['configs'],$hash);$after=load_json($file);check($after['8B']===$before['8B']&&$after['8A']===$valid['configs']['8A'],'Only file classes replaced');
 pccm_save_release_state($dir,['locked'=>['v1'=>true]]);rejects(fn()=>gdp_excel_apply($dir,'v1',$valid['configs'],hash('sha256',json_encode($after))),'Locked version cannot import');
 if(class_exists('ZipArchive')&&class_exists('DOMDocument')){
  $target=$dir.'/template.xlsx';check(gdp_excel_template($context,$after,'Bản thử',$target),'Template exported');$parsed=gdp_excel_read($target);$roundtrip=gdp_excel_preview($parsed,$context,'GDĐP');check(!$roundtrip['errors']&&$roundtrip['configs']===$after,'Real XLSX template round-trip retains leading zeros and configurations');
  $z=new ZipArchive();$z->open($target);$sheet=$z->getFromName('xl/worksheets/sheet1.xml');check(str_contains($sheet,'numFmtId')===false&&str_contains($sheet,'02001'),'Teacher code stored as text');$z->addFromString('xl/worksheets/sheet1.xml',str_replace('<is><t xml:space="preserve">02001</t></is>','<f>1+1</f><v>2</v>',$sheet));$z->close();rejects(fn()=>gdp_excel_read($target),'Formula input rejected');
  file_put_contents($target,'invalid');rejects(fn()=>gdp_excel_read($target),'Invalid XLSX rejected');
 }else echo "SKIP XLSX round-trip: ZipArchive/DOM not installed locally; required in CI\n";
 echo "PASS GDĐP Excel: codes, duplicate identities, missing/overlap, immutable other classes, stale/locked rejection, template round-trip and invalid XLSX\n";
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
