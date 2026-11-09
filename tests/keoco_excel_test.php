<?php
require dirname(__DIR__).'/includes/keoco_match.php';require dirname(__DIR__).'/includes/keoco_excel.php';
$rows=kc_excel_questions(dirname(__DIR__).'/assets/mau-cau-hoi-keoco.xlsx');
if(count($rows)!==2||$rows[0]['correct_option']!==2||$rows[1]['correct_option']!==3)throw new RuntimeException('Template import roundtrip failed');
try{kc_excel_xml('<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>');throw new LogicException('XXE accepted');}catch(RuntimeException $e){}
// Real XLSX modifications: formula/invalid answer/header must fail before returning any rows.
foreach(['formula','answer','header']as$case){$path=tempnam(sys_get_temp_dir(),'kc-xlsx-');copy(dirname(__DIR__).'/assets/mau-cau-hoi-keoco.xlsx',$path);try{$z=new ZipArchive();$z->open($path);$s=$z->getFromName('xl/worksheets/sheet1.xml');
 if($case==='formula')$s=preg_replace('/(<c r="A2"[^>]*>)/','$1<f>1+1</f>',$s);
 elseif($case==='answer')$s=str_replace('<t>B</t></is></c></row>','<t>Z</t></is></c></row>',$s);
 else $s=str_replace('C&#226;u h&#7887;i','Sai tieu de',$s);
 $z->addFromString('xl/worksheets/sheet1.xml',$s);$z->close();$failed=false;try{kc_excel_questions($path);}catch(Throwable $e){$failed=true;}if(!$failed)throw new RuntimeException('Invalid XLSX accepted: '.$case);
 }finally{unlink($path);}}
echo "PASS: Excel template roundtrip, formula/header/answer rejection, XML entity protection\n";
