<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/noitru_assignment_store.php';
require_once __DIR__.'/includes/noitru_room_excel_import.php';
require_login();
require_perm_level('nt.chiaphong','edit');

$withData=(($_GET['data']??'0')==='1');
$students=$withData?noitru_assignment_apply(noitru_boarders_live()):[];
if($students){
    usort($students,static function($a,$b){
        $ca=(string)($a['class_name']??'');$cb=(string)($b['class_name']??'');
        $cmp=strnatcasecmp($ca,$cb);if($cmp!==0)return $cmp;
        return strnatcasecmp((string)($a['name']??''),(string)($b['name']??''));
    });
}
function nt_tpl_cell($ref,$value,$style=3,$type='inlineStr'){
    if($type==='n')return '<c r="'.$ref.'" s="'.$style.'"><v>'.(int)$value.'</v></c>';
    return '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t>'.nt_room_excel_xml((string)$value).'</t></is></c>';
}
function nt_tpl_date($v){$v=nt_room_excel_date($v);if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$v,$m))return $m[3].'/'.$m[2].'/'.$m[1];return (string)$v;}
$headers=['STT','Họ và tên','Lớp','Ngày sinh','Giới tính','Phòng KTX','Ghi chú'];$head='';foreach($headers as $i=>$h)$head.=nt_tpl_cell(chr(65+$i).'1',$h,1);
$rows=['<row r="1" ht="28">'.$head.'</row>'];
if($withData){$r=2;$stt=1;foreach($students as $s){$room=trim((string)($s['room_ktx']??''));$note='';$cells=nt_tpl_cell('A'.$r,$stt++,2,'n').nt_tpl_cell('B'.$r,$s['name']??'',3).nt_tpl_cell('C'.$r,$s['class_name']??'',3).nt_tpl_cell('D'.$r,nt_tpl_date($s['dob']??''),3).nt_tpl_cell('E'.$r,noitru_assignment_gender($s),3).nt_tpl_cell('F'.$r,$room,3).nt_tpl_cell('G'.$r,$note,3);$rows[]='<row r="'.$r.'">'.$cells.'</row>';$r++;}}
else{for($r=2;$r<=101;$r++)$rows[]='<row r="'.$r.'">'.nt_tpl_cell('A'.$r,$r-1,2,'n').'<c r="B'.$r.'" s="3"/><c r="C'.$r.'" s="3"/><c r="D'.$r.'" s="3"/><c r="E'.$r.'" s="3"/><c r="F'.$r.'" s="3"/><c r="G'.$r.'" s="3"/></row>';}
$last=max(2,$withData?count($students)+1:101);
$sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="14" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/><col min="5" max="5" width="14" customWidth="1"/><col min="6" max="6" width="18" customWidth="1"/><col min="7" max="7" width="32" customWidth="1"/></cols><sheetData>'.implode('',$rows).'</sheetData><autoFilter ref="A1:G'.$last.'"/><pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/></worksheet>';
$contentTypes='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
$workbook='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Chia phòng" sheetId="1" r:id="rId1"/></sheets></workbook>';
$rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
$styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right><top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
$files=['[Content_Types].xml'=>$contentTypes,'_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>','xl/workbook.xml'=>$workbook,'xl/_rels/workbook.xml.rels'=>$rels,'xl/styles.xml'=>$styles,'xl/worksheets/sheet1.xml'=>$sheet];
$tmp=tempnam(sys_get_temp_dir(),'nt-room-template-');nt_room_excel_zip($files,$tmp);$filename=$withData?'mau-chia-phong-co-du-lieu.xlsx':'mau-chia-phong-trong.xlsx';header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));header('Cache-Control: no-store');readfile($tmp);@unlink($tmp);exit;
