<?php
function fixture_equipment(){return [
 ['id'=>'a','grade'=>'Khối 6','subject'=>'Toán','name'=>'Thước <mẫu>','code'=>'00012','quantity'=>6,'good_quantity'=>3,'degraded_quantity'=>2,'broken_quantity'=>1,'unit'=>'Bộ','production_year'=>'2025','provider'=>'Đơn vị A & B','note'=>"=1+1\nGhi chú"],
 ['id'=>'b','name'=>'Thiết bị cũ','code'=>'TB-02','quantity'=>2,'condition'=>'Hỏng','unit'=>'Cái']];}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
if(($argv[1]??'')==='route'){
 define('DATA_PATH','fixture-only');define('BASE_URL','/');$_SERVER['SCRIPT_NAME']='/thietbi_mau.php';
 function require_login(){}
 function current_user(){return ['role'=>'teacher'];}
 function can_perm_level($key,$level){return ($GLOBALS['argv'][2]??'')==='allowed'&&$key==='tb.danhmuc';}
 function load_json($path,$default){if($path!=='fixture-only/library_equipment.json')throw new RuntimeException('Unexpected data access');return ['equipment'=>fixture_equipment()];}
 $path=__DIR__.'/../thietbi_mau.php';$s=file_get_contents($path);$s=str_replace("require_once __DIR__.'/includes/auth.php';",';',$s);$s=str_replace('__DIR__',var_export(realpath(dirname($path)),true),$s);eval('?>'.$s);exit;
}
require __DIR__.'/../includes/library_book_excel.php';
require __DIR__.'/../includes/library_equipment_excel.php';
require __DIR__.'/../includes/library_equipment_template.php';
$equipment=fixture_equipment();$before=serialize($equipment);$path=lib_equipment_template_file($equipment);
try{
 $z=new ZipArchive();check($z->open($path)===true,'Real XLSX archive');$sheet=$z->getFromName('xl/worksheets/sheet1.xml');check(!str_contains($sheet,'Kính hiển vi'),'Example row replaced');check(str_contains($sheet,'00012'),'Leading zero code preserved');check(str_contains($sheet,'SUM(J4:L4)'),'Quantity formula retained');check(str_contains($sheet,'CỘNG THÊM'),'Additive import guidance');$z->close();
 $parsed=lib_equipment_excel_parse($path);check($parsed['ok']&&count($parsed['items'])===2&&!$parsed['errors'],'Existing importer accepts populated template');
 $a=$parsed['items'][0];check($a['code']==='00012'&&$a['name']==='Thước <mẫu>'&&$a['quantity']===6&&$a['good_quantity']===3&&$a['degraded_quantity']===2&&$a['broken_quantity']===1,'Fields and split quantities round-trip');
 check($parsed['items'][1]['broken_quantity']===2,'Legacy condition exports correct quantities');check(serialize($equipment)===$before,'Inventory never mutated');
}finally{unlink($path);}
$empty=lib_equipment_template_file([]);try{$p=lib_equipment_excel_parse($empty);check($p['ok']&&count($p['items'])===0,'Empty inventory exports no fake example');}finally{unlink($empty);}
foreach(['allowed','denied']as$role){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'route',$role],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0&&$err==='','Endpoint succeeds '.$err);if($role==='allowed')check(str_starts_with($out,"PK\x03\x04"),'Endpoint returns pure XLSX with no HTML prefix');else check(str_contains($out,'Không có quyền')&&!str_starts_with($out,'PK'),'Unauthorized export denied');}
echo "PASS populated equipment XLSX: template compatibility, formulas, split/legacy quantities, zero-prefixed codes, empty inventory, source unchanged and endpoint permissions/no HTML contamination\n";
