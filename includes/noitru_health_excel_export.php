<?php
/** Báo cáo khám chữa bệnh XLSX, bảy cột theo mẫu, A4 ngang. */
require_once dirname(__DIR__).'/chuyenmon/includes/lesson_book_excel.php';
function nt_health_excel_text(string $ref,$value,int $style): string {
    $value=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string)$value)??'';
    return lb_xlsx_text($ref,$value,$style);
}
function nt_health_excel_styles(): string {
    return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts><fonts count="3"><font><sz val="11"/><name val="Times New Roman"/></font><font><b/><sz val="14"/><name val="Times New Roman"/></font><font><b/><sz val="11"/><name val="Times New Roman"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}
function nt_health_excel_files(array $records,string $from,string $to): array {
    usort($records,static fn($a,$b)=>strcmp(($a['date']??'').'|'.($a['created_at']??''),($b['date']??'').'|'.($b['created_at']??'')));
    $rows='<row r="1" ht="25" customHeight="1">'.nt_health_excel_text('A1',defined('SCHOOL_NAME')?SCHOOL_NAME:'',1).'</row>';
    $rows.='<row r="2" ht="28" customHeight="1">'.nt_health_excel_text('A2','SỔ THEO DÕI KHÁM CHỮA BỆNH',1).'</row>';
    $rows.='<row r="3" ht="22" customHeight="1">'.nt_health_excel_text('A3','Từ ngày '.date('d/m/Y',strtotime($from)).' đến ngày '.date('d/m/Y',strtotime($to)).' · '.count($records).' lượt',0).'</row>';
    $heads=['STT','NGÀY THÁNG NĂM','HỌ VÀ TÊN NGƯỜI BỆNH','CHẨN ĐOÁN','TÊN THUỐC, SỐ LƯỢNG','KÝ NHẬN HS','GHI CHÚ'];
    $cells='';foreach($heads as $j=>$h)$cells.=nt_health_excel_text(lb_xlsx_col($j+1).'4',$h,2);
    $rows.='<row r="4" ht="36" customHeight="1">'.$cells.'</row>';
    foreach($records as $i=>$record) {
        $n=$i+5;$meds=[];
        foreach((array)($record['medicines']??[]) as $m)$meds[]=trim((string)($m['name']??'').' · '.(string)($m['quantity']??0).' '.(string)($m['unit']??''));
        $values=[$i+1,(string)($record['date']??''),(string)($record['student_name']??''),(string)($record['diagnosis']??''),implode("\n",$meds),'',(string)($record['note']??'')];
        $treatment=trim((string)($record['treatment']??''));if($treatment!=='')$values[6]=trim($values[6]."\n".$treatment);
        $lines=1;foreach([2=>26,3=>28,4=>34,6=>22] as $j=>$width) { $count=0;foreach(explode("\n",$values[$j]) as $line)$count+=max(1,(int)ceil((function_exists('mb_strlen')?mb_strlen($line,'UTF-8'):strlen($line))/$width));$lines=max($lines,$count); }
        $height=max(32,16*$lines+8);$cells='';
        foreach($values as $j=>$value) {
            $ref=lb_xlsx_col($j+1).$n;
            if($j===0)$cells.=lb_xlsx_number($ref,$value,4);
            elseif($j===1 && preg_match('/^\d{4}-\d{2}-\d{2}$/',$value) && strtotime($value)!==false)$cells.=lb_xlsx_number($ref,(strtotime($value.' UTC')-strtotime('1899-12-30 UTC'))/86400,5);
            else $cells.=nt_health_excel_text($ref,$value,$j===5?4:3);
        }
        $rows.='<row r="'.$n.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';
    }
    if(!$records){$cells='';for($j=1;$j<=7;$j++)$cells.=nt_health_excel_text(lb_xlsx_col($j).'5','',3);$rows.='<row r="5" ht="32" customHeight="1">'.$cells.'</row>';}
    $last=max(5,count($records)+4);
    $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:G'.$last.'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="2" width="17" customWidth="1"/><col min="3" max="3" width="28" customWidth="1"/><col min="4" max="4" width="30" customWidth="1"/><col min="5" max="5" width="36" customWidth="1"/><col min="6" max="6" width="15" customWidth="1"/><col min="7" max="7" width="24" customWidth="1"/></cols><sheetData>'.$rows.'</sheetData><mergeCells count="3"><mergeCell ref="A1:G1"/><mergeCell ref="A2:G2"/><mergeCell ref="A3:G3"/></mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.35" bottom="0.35" header="0.15" footer="0.15"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><headerFooter><oddFooter>&amp;CTrang &amp;P / &amp;N</oddFooter></headerFooter></worksheet>';
    $files=lb_xlsx_base_files('Sổ khám chữa bệnh','Khám chữa bệnh',$sheet,nt_health_excel_styles());
    $names='<definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'Khám chữa bệnh\'!$A$1:$G$'.$last.'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Khám chữa bệnh\'!$4:$4</definedName></definedNames>';
    $files['xl/workbook.xml']=str_replace('</workbook>',$names.'</workbook>',$files['xl/workbook.xml']);
    return $files;
}
function nt_health_export_xlsx(array $records,string $from,string $to): void {
    $tmp=tempnam(sys_get_temp_dir(),'health_xlsx_');
    if($tmp===false || !lb_xlsx_zip(nt_health_excel_files($records,$from,$to),$tmp)){if($tmp!==false)@unlink($tmp);http_response_code(503);exit('Không tạo được Excel. Kiểm tra ZipArchive trên máy chủ.');}
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="kham-chua-benh-'.$from.'-'.$to.'.xlsx"');
    header('Content-Length: '.filesize($tmp));header('Cache-Control: private, no-store');
    readfile($tmp);@unlink($tmp);exit;
}
