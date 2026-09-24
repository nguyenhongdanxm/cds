<?php
require_once __DIR__.'/includes/auth.php';
require_login();
require_once __DIR__.'/includes/olympia_store.php';
if(!olympia_admin()){http_response_code(403);exit('Chỉ quản trị được tải mẫu câu hỏi.');}
if(!class_exists('ZipArchive')){http_response_code(500);exit('Hosting chưa bật PHP ZIP (ZipArchive), chưa thể tạo file XLSX.');}

$stages=[
    'Khởi động'=>['points'=>10,'slug'=>'khoi-dong'],
    'Vượt chướng ngại vật'=>['points'=>20,'slug'=>'vuot-chuong-ngai-vat'],
    'Tăng tốc'=>['points'=>30,'slug'=>'tang-toc'],
    'Về đích'=>['points'=>40,'slug'=>'ve-dich'],
];
$stage=trim((string)($_GET['stage']??'Khởi động'));
if(!isset($stages[$stage]))$stage='Khởi động';
$points=(int)$stages[$stage]['points'];

function ot_xml($value): string {
    $value=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string)$value)??(string)$value;
    return htmlspecialchars($value,ENT_QUOTES|ENT_XML1,'UTF-8');
}
function ot_cell($value,string $ref,int $style=0): string {
    $styleAttr=$style?' s="'.$style.'"':'';
    if(is_int($value)||is_float($value))return '<c r="'.$ref.'"'.$styleAttr.'><v>'.$value.'</v></c>';
    return '<c r="'.$ref.'"'.$styleAttr.' t="inlineStr"><is><t xml:space="preserve">'.ot_xml($value).'</t></is></c>';
}
function ot_sheet_questions(string $stage,int $points): string {
    $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml.='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:E11"/><sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="16" customWidth="1"/><col min="2" max="2" width="27" customWidth="1"/><col min="3" max="3" width="58" customWidth="1"/><col min="4" max="4" width="35" customWidth="1"/><col min="5" max="5" width="10" customWidth="1"/></cols><sheetData>';
    $xml.='<row r="1" ht="28" customHeight="1">'.ot_cell('Khối','A1',1).ot_cell('Vòng','B1',1).ot_cell('Câu hỏi','C1',1).ot_cell('Đáp án','D1',1).ot_cell('Điểm','E1',1).'</row>';
    for($row=2;$row<=11;$row++)$xml.='<row r="'.$row.'">'.ot_cell('6,7','A'.$row).ot_cell($stage,'B'.$row).ot_cell('','C'.$row).ot_cell('','D'.$row).ot_cell($points,'E'.$row).'</row>';
    $xml.='</sheetData><autoFilter ref="A1:E11"/><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
    return $xml;
}
function ot_sheet_guide(): string {
    $rows=[
        ['HƯỚNG DẪN NẠP CÂU HỎI OLYMPIA',''],
        ['Cấu trúc','Khối | Vòng | Câu hỏi | Đáp án | Điểm'],
        ['Khối','Nhập 6, 7, 6,7 hoặc Tất cả.'],
        ['Vòng','Khởi động; Vượt chướng ngại vật; Tăng tốc; Về đích.'],
        ['Cách dùng','Điền dữ liệu ở sheet Câu hỏi, sao chép các dòng cần nạp rồi dán vào ô Nạp nhanh câu hỏi theo khối.'],
        ['Lưu ý','Không dùng ký tự | trong nội dung câu hỏi hoặc đáp án. Dòng tiêu đề được hệ thống tự bỏ qua.'],
    ];
    $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:B6"/><cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="95" customWidth="1"/></cols><sheetData>';
    foreach($rows as $index=>$row){$n=$index+1;$style=$n===1?1:0;$xml.='<row r="'.$n.'" ht="'.($n===1?'28':'34').'" customHeight="1">'.ot_cell($row[0],'A'.$n,$style).ot_cell($row[1],'B'.$n,$style).'</row>';}
    return $xml.'</sheetData><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
}

$tmp=tempnam(sys_get_temp_dir(),'olympia_xlsx_');
$zip=new ZipArchive();
if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){http_response_code(500);exit('Không tạo được file XLSX tạm.');}
$zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Câu hỏi" sheetId="1" r:id="rId1"/><sheet name="Hướng dẫn" sheetId="2" r:id="rId2"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
$zip->addFromString('xl/worksheets/sheet1.xml',ot_sheet_questions($stage,$points));
$zip->addFromString('xl/worksheets/sheet2.xml',ot_sheet_guide());
$zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
$zip->close();

$filename='mau-olympia-'.$stages[$stage]['slug'].'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmp));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tmp);
@unlink($tmp);
exit;
