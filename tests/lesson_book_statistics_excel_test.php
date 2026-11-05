<?php
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
require __DIR__.'/support/load_functions.php';
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_stat_status','lb_stat_group','lb_stat_totals']);
require __DIR__.'/../chuyenmon/includes/lesson_book_statistics_excel.php';
$rows=[
 ['date'=>'2026-10-26','class'=>'6A','subject'=>'Toán','scheduled_teacher'=>'GV A','actual_teacher'=>'GV A','teacher_group'=>'Tổ 1','week_label'=>'Tuần 8','signed_at'=>'yes','status'=>'taught','ppct_period'=>12,'lesson_title'=>'=HYPERLINK("bad")','signed_by'=>'GV A'],
 ['date'=>'2026-10-27','class'=>'6A','subject'=>'Toán','actual_teacher'=>'GV A','teacher_group'=>'Tổ 1','week_label'=>'Tuần 8','status'=>'postponed','_ppct_missing'=>true],
 ['date'=>'2026-10-28','class'=>'6B','subject'=>'Văn','actual_teacher'=>'GV B','teacher_group'=>'Tổ 2','week_label'=>'Tuần 8','status'=>'substitute','lesson_title'=>str_repeat('Nội dung dài ',30)],
];
$sheets=lb_stats_excel_sheets($rows);check(count($sheets)===7,'Seven separate sheets');check($sheets[0]['rows'][0][1]===3&&$sheets[0]['rows'][0][2]===1,'Original total and signature count preserved');
foreach(array_slice($sheets,1,5)as$sheet){check(array_sum(array_column($sheet['rows'],1))===3&&array_sum(array_column($sheet['rows'],2))===1,'Each grouping reconciles to totals');}
$parts=[];foreach($sheets as$sheet){$xml=lb_stats_excel_sheet($sheet,'Phạm vi kiểm thử');check(str_contains($xml,'ySplit="4"')&&str_contains($xml,'autoFilter')&&str_contains($xml,'fitToWidth="1"'),'Frozen headers, filters and print fit');check(!str_contains($xml,'<f>'),'Text cannot execute spreadsheet formulas');$parts[$sheet['name']]=$xml;}
check(str_contains($parts['Chi tiết tiết học'],'HYPERLINK')&&str_contains($parts['Chi tiết tiết học'],'s="11"'),'Safe text and typed date');
check(count(lb_stats_excel_sheets([])[6]['rows'])===0,'Empty export remains valid');
$parts['styles']=lb_stats_excel_styles();
if(class_exists('ZipArchive')){$tmp=tempnam(sys_get_temp_dir(),'stats_test_');try{check(lb_stats_xlsx($rows,'2026-10-26','2026-11-01',$tmp,['Lớp'=>'6A']),'Actual XLSX write');$zip=new ZipArchive();check($zip->open($tmp)===true,'Workbook opens as ZIP');foreach(range(1,7)as$n)check($zip->locateName('xl/worksheets/sheet'.$n.'.xml')!==false,'All worksheet parts packaged');check(substr_count($zip->getFromName('xl/workbook.xml'),'<sheet ')===7,'Seven sheets wired');check(str_contains($zip->getFromName('xl/worksheets/sheet1.xml'),'Lớp: 6A'),'Applied scope included');$zip->close();}finally{unlink($tmp);}}
if(in_array('--parts',$argv,true))file_put_contents('/tmp/lb-statistics-excel-parts.json',json_encode($parts,JSON_UNESCAPED_UNICODE));
echo "PASS statistics Excel: seven sheets, reconciled totals, typed dates/percentages, safe text, widths, wrapped cells, headers, filters, print settings and empty data\n";
