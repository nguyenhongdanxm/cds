<?php
require_once __DIR__.'/lesson_book_excel.php';
function lb_ppct_template_v2_xlsx(string $target): bool {
    $heads=['STT','Tên khối','Tên môn học','Tiết theo PPCT','Tên bài dạy','Thứ tự','Học kỳ'];
    $rows=[];$cells='';foreach($heads as$i=>$h)$cells.=lb_xlsx_text(lb_xlsx_col($i+1).'1',$h,2);$rows[]='<row r="1" ht="34">'.$cells.'</row>';
    $samples=[['1','Khối 12','Hóa học','1','Bài 1: Mở đầu','1','1'],['2','Khối 12','Hóa học','2','Bài 2: Nội dung bài học','2','1']];
    foreach($samples as$ri=>$vals){$n=$ri+2;$cells='';foreach($vals as$i=>$v)$cells.=is_numeric($v)&&in_array($i,[0,3,5,6],true)?lb_xlsx_number(lb_xlsx_col($i+1).$n,$v,4):lb_xlsx_text(lb_xlsx_col($i+1).$n,$v,3);$rows[]='<row r="'.$n.'" ht="30">'.$cells.'</row>';}
    for($n=4;$n<=203;$n++){$cells='';for($i=1;$i<=7;$i++)$cells.=lb_xlsx_text(lb_xlsx_col($i).$n,'',in_array($i,[1,4,6,7],true)?4:3);$rows[]='<row r="'.$n.'" ht="24">'.$cells.'</row>';}
    $sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/><col min="3" max="3" width="22" customWidth="1"/><col min="4" max="4" width="18" customWidth="1"/><col min="5" max="5" width="62" customWidth="1"/><col min="6" max="6" width="12" customWidth="1"/><col min="7" max="7" width="10" customWidth="1"/></cols><sheetData>'.implode('',$rows).'</sheetData><autoFilter ref="A1:G203"/><pageMargins left="0.35" right="0.35" top="0.45" bottom="0.45" header="0.2" footer="0.2"/></worksheet>';
    return lb_xlsx_zip(lb_xlsx_base_files('Mẫu nhập PPCT Sổ đầu bài','PPCT',$sheet,lb_xlsx_styles()),$target);
}
