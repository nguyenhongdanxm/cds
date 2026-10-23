<?php
require __DIR__.'/../includes/noitru_health_excel_export.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function xml(string $value): DOMXPath {$doc=new DOMDocument();check($doc->loadXML($value),'Valid XML');$xp=new DOMXPath($doc);$xp->registerNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');return $xp;}
$records=[['date'=>'2026-10-02','student_name'=>'Học sinh B','diagnosis'=>'Chẩn đoán B'],['date'=>'2026-10-01','student_name'=>'Học sinh A','class_name'=>'9A','diagnosis'=>'Chẩn đoán A','medicines'=>[['name'=>'Thuốc thử','quantity'=>2,'unit'=>'viên']],'note'=>'Ghi chú','treatment'=>'Điều trị']];
$before=$records;$files=nt_health_excel_files($records,'2026-10-01','2026-10-31');$x=xml($files['xl/worksheets/sheet1.xml']);
$get=fn($ref)=>$x->evaluate('string(//s:c[@r="'.$ref.'"]/s:is/s:t)');
check($get('C2')==='HỌ VÀ TÊN NGƯỜI BỆNH'&&$get('D2')==='LỚP'&&$get('H2')==='GHI CHÚ','Eight-column header');
check($get('C3')==='Học sinh A'&&$get('D3')==='9A'&&$get('D4')==='','Class snapshot and missing class');
check($get('E3')==='Chẩn đoán A'&&$get('F3')==='Thuốc thử · 2 viên'&&$get('G3')===''&&$get('H3')==="Ghi chú\nĐiều trị",'Medical columns preserved after shift');
check($x->evaluate('string(//s:c[@r="B3"]/s:v)')==='46296','Numeric date preserved');
check($x->evaluate('string(//s:dimension/@ref)')==='A1:H4'&&$x->evaluate('string(//s:mergeCell/@ref)')==='A1:H1','Expanded range');
check($x->evaluate('string(//s:pageSetup/@paperSize)')==='9'&&$x->evaluate('string(//s:pageSetup/@orientation)')==='landscape'&&$x->evaluate('string(//s:pageSetup/@fitToWidth)')==='1'&&$x->evaluate('string(//s:pageSetup/@fitToHeight)')==='0','A4 landscape one page wide');
$w=xml($files['xl/workbook.xml']);check($w->evaluate('string(//s:definedName[@name="_xlnm.Print_Area"])')==="'Khám chữa bệnh'!\$A\$1:\$H\$4",'Print area');check($w->evaluate('string(//s:definedName[@name="_xlnm.Print_Titles"])')==="'Khám chữa bệnh'!\$2:\$2",'Repeated header');
$empty=nt_health_excel_files([],'2026-10-01','2026-10-31');$e=xml($empty['xl/worksheets/sheet1.xml']);check($e->evaluate('count(//s:row[@r="3"]/s:c)')===8.0,'Empty export has eight cells');check($records===$before,'Source records unchanged');
if(class_exists('ZipArchive')){$tmp=tempnam(sys_get_temp_dir(),'health_test_');try{check(lb_xlsx_zip($files,$tmp),'XLSX ZIP created');$z=new ZipArchive();check($z->open($tmp)===true,'XLSX opens');check($z->getFromName('xl/worksheets/sheet1.xml')===$files['xl/worksheets/sheet1.xml'],'ZIP contents preserved');$z->close();}finally{unlink($tmp);}}
echo "PASS health Excel: class column, medical values, dates, A4 landscape, repeated header, empty report, source preservation\n";
