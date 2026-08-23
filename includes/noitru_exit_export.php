<?php
require_once __DIR__ . '/noitru_meal_month_export.php';

function nt_exit_export_xlsx(array $rows, string $from, string $to, string $periodLabel, string $exportedBy): void {
    $school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN';
    $sheet=[];$merges=['A1:J1','A2:J2','A3:J3','A4:J4'];
    $sheet[]='<row r="1" ht="22">'.nt_xlsx_text_cell('A1',$school,3).'</row>';
    $sheet[]='<row r="2" ht="26">'.nt_xlsx_text_cell('A2','DANH SÁCH HỌC SINH RA VÀO KHU NỘI TRÚ',2).'</row>';
    $sheet[]='<row r="3" ht="20">'.nt_xlsx_text_cell('A3',$periodLabel,3).'</row>';
    $sheet[]='<row r="4" ht="12"></row>';
    $headers=['STT','Họ và tên','Lớp','Thời gian đăng ký ra','Thời gian đăng ký vào','Thực tế ra','Thực tế vào','Lý do','Người đón / SĐT','Người xác nhận'];
    $cells='';foreach($headers as $i=>$h)$cells.=nt_xlsx_text_cell(nt_xlsx_col($i+1).'5',$h,6);$sheet[]='<row r="5" ht="34">'.$cells.'</row>';
    $r=6;$i=0;
    foreach($rows as $row){$i++;$pickup=trim((string)($row['pickup_name']??''));$phone=trim((string)($row['pickup_phone']??''));if($phone!=='')$pickup.=($pickup!==''?' · ':'').$phone;$confirmed=trim((string)($row['exit_confirmed_by']??''));if(!empty($row['return_confirmed_by']))$confirmed.=($confirmed!==''?' / ':'').(string)$row['return_confirmed_by'];
        $vals=[$i,$row['student_name']??'',$row['class_name']??'',nt_exit_fmt_dt($row['from_date']??''),nt_exit_fmt_dt($row['to_date']??''),nt_exit_fmt_dt($row['actual_exit_at']??''),nt_exit_fmt_dt($row['actual_return_at']??''),$row['reason']??'',$pickup,$confirmed];
        $cells='';foreach($vals as $c=>$v)$cells.=($c===0?nt_xlsx_number_cell('A'.$r,$v,4):nt_xlsx_text_cell(nt_xlsx_col($c+1).$r,$v,$c===1||$c===7||$c===8?5:4));$sheet[]='<row r="'.$r.'" ht="28">'.$cells.'</row>';$r++;
    }
    if(!$rows){$sheet[]='<row r="6" ht="28">'.nt_xlsx_text_cell('A6','Không có dữ liệu phù hợp bộ lọc.',9).'</row>';$merges[]='A6:J6';$r=7;}
    $sheet[]='<row r="'.$r.'" ht="22">'.nt_xlsx_text_cell('A'.$r,'Xuất lúc: '.date('d/m/Y H:i').' · Người xuất: '.$exportedBy,9).'</row>';$merges[]='A'.$r.':J'.$r;
    $sheetXml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:J'.$r.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="6"/><col min="2" max="2" width="26"/><col min="3" max="3" width="12"/><col min="4" max="7" width="21"/><col min="8" max="8" width="34"/><col min="9" max="10" width="25"/></cols><sheetData>'.implode('',$sheet).'</sheetData><mergeCells count="'.count($merges).'">'.implode('',array_map(fn($m)=>'<mergeCell ref="'.$m.'"/>',$merges)).'</mergeCells><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>';
    $styles=nt_xlsx_styles_xml();
    $files=[
      '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
      '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
      'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Danh sách ra vào" sheetId="1" r:id="rId1"/></sheets></workbook>',
      'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
      'xl/styles.xml'=>$styles,'xl/worksheets/sheet1.xml'=>$sheetXml
    ];
    $tmp=tempnam(sys_get_temp_dir(),'nt-exit-');nt_xlsx_write_zip($files,$tmp);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="ra-vao-ktx-'.$from.'-'.$to.'.xlsx"');
    header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
}
function nt_exit_fmt_dt($v): string {$v=trim((string)$v);if($v==='')return ''; $ts=strtotime($v);return $ts?date('d/m/Y H:i',$ts):$v;}
