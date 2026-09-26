<?php
/** Xuất danh sách bồi dưỡng đã chọn thành một tệp XLSX nhiều sheet. */
require_once __DIR__.'/lesson_book_excel.php';

function support_export_styles(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="4"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="16"/><color rgb="FF173B60"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font><font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Arial"/></font></fonts>'
        .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF3FA"/></patternFill></fill></fills>'
        .'<borders count="2"><border/><border><left style="thin"><color rgb="FFD4E1EC"/></left><right style="thin"><color rgb="FFD4E1EC"/></right><top style="thin"><color rgb="FFD4E1EC"/></top><bottom style="thin"><color rgb="FFD4E1EC"/></bottom></border></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="7">'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}

function support_export_sheet(string $title, string $year, array $rows, bool $exam, string $filter): string {
    $heads=$exam?['STT','Họ và tên','Lớp','Môn ôn thi','Tham gia','Nhóm TBK/TBY','Người chọn']:['STT','Họ và tên','Lớp','Môn','Tham gia','Đề xuất','Người chọn'];
    $data=[];
    $data[]='<row r="1" ht="34">'.lb_xlsx_text('A1',mb_strtoupper($title,'UTF-8'),1).'</row>';
    $data[]='<row r="2" ht="23">'.lb_xlsx_text('A2','Năm học '.$year.'  ·  '.$filter.'  ·  Tổng: '.count($rows).' học sinh theo môn',2).'</row>';
    $cells='';foreach($heads as $i=>$h)$cells.=lb_xlsx_text(lb_xlsx_col($i+1).'3',$h,3);
    $data[]='<row r="3" ht="32">'.$cells.'</row>';
    foreach($rows as $i=>$entry){$n=$i+4;$values=[$i+1,$entry['name'],$entry['class'],$entry['subject'],'Có',$exam?($entry['group']?:'Chưa xếp'):($entry['recommendation']?:'—'),$entry['teacher']];$cells='';foreach($values as $j=>$value){$style=$j===0||$j===4||($exam&&$j===5)?6:($i%2===0?4:5);$cells.= $j===0?lb_xlsx_number(lb_xlsx_col($j+1).$n,$value,$style):lb_xlsx_text(lb_xlsx_col($j+1).$n,$value,$style);}$data[]='<row r="'.$n.'" ht="24">'.$cells.'</row>';}
    if(!$rows)$data[]='<row r="4" ht="24">'.lb_xlsx_text('B4','Chưa có học sinh phù hợp.',4).'</row>';
    $last=max(3,count($rows)+3);
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:G'.max(4,$last).'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="7"/><col min="2" max="2" width="32"/><col min="3" max="3" width="13"/><col min="4" max="4" width="24"/><col min="5" max="5" width="13"/><col min="6" max="6" width="24"/><col min="7" max="7" width="30"/></cols><sheetData>'.implode('',$data).'</sheetData><mergeCells count="2"><mergeCell ref="A1:G1"/><mergeCell ref="A2:G2"/></mergeCells><autoFilter ref="A3:G'.$last.'"/><printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>';
}

function support_export_xlsx(array $categories, array $students, array $members, string $year, string $class, string $subject, string $group, bool $all): void {
    if(!class_exists('ZipArchive')) { http_response_code(503); exit('Máy chủ cần bật ZipArchive để xuất Excel.'); }
    $sheetNames=['tn'=>'Ôn thi TN','ts'=>'Ôn thi TS','muinhon'=>'HS mũi nhọn','chuadat'=>'HS chưa đạt'];
    $sheets=[];
    foreach($categories as $category){
        $rows=[];
        foreach(($members[$category]??[]) as $sub=>$byStudent){
            if(!$all&&$subject!==''&&$sub!==$subject)continue;
            foreach($byStudent as $id=>$entry){
                if(!isset($students[$id]))continue;
                $student=$students[$id];
                if(!$all&&$class!==''&&$student['class']!==$class)continue;
                if(!$all&&$group!==''&&in_array($category,['tn','ts'],true)){
                    if($group==='unassigned'&&in_array($entry['group'],['TBK','TBY'],true))continue;
                    if($group!=='unassigned'&&$entry['group']!==$group)continue;
                }
                $rows[]=['name'=>$student['name'],'class'=>$student['class'],'subject'=>$sub,'group'=>$entry['group'],'recommendation'=>$entry['recommendation'],'teacher'=>$entry['teacher']];
            }
        }
        usort($rows,static function($a,$b){$c=strnatcasecmp($a['class'],$b['class']);if($c)return $c;$c=strnatcasecmp($a['name'],$b['name']);return $c?:strnatcasecmp($a['subject'],$b['subject']);});
        $filter=$all?'Toàn trường · Tất cả môn':('Môn: '.($subject?:'Tất cả').' · Lớp: '.($class?:'Tất cả').(in_array($category,['tn','ts'],true)?' · Nhóm: '.(['TBK'=>'TBK','TBY'=>'TBY','unassigned'=>'Chưa xếp'][$group]??'Tất cả'):''));
        $sheets[]=['name'=>$sheetNames[$category],'xml'=>support_export_sheet($sheetNames[$category],$year,$rows,in_array($category,['tn','ts'],true),$filter)];
    }
    $types='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $workbook='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $files=['_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>','xl/styles.xml'=>support_export_styles()];
    foreach($sheets as $i=>$sheet){$n=$i+1;$types.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';$workbook.='<sheet name="'.lb_xlsx_xml($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';$rels.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';$files['xl/worksheets/sheet'.$n.'.xml']=$sheet['xml'];}
    $files['[Content_Types].xml']=$types.'</Types>';$files['xl/workbook.xml']=$workbook.'</sheets></workbook>';$files['xl/_rels/workbook.xml.rels']=$rels.'<Relationship Id="rId'.(count($sheets)+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $tmp=tempnam(sys_get_temp_dir(),'support_');if($tmp===false||!lb_xlsx_zip($files,$tmp)){if($tmp!==false)@unlink($tmp);http_response_code(503);exit('Không tạo được tệp Excel.');}
    $filename='boi-duong-hoc-sinh-'.($all?'tat-ca':$categories[0]).'-'.$year.'.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));header('Cache-Control: private, no-store');readfile($tmp);@unlink($tmp);exit;
}
