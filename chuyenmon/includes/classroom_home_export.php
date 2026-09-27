<?php
/** XLSX theo tháng cho tiện ích chủ nhiệm; chỉ dùng dữ liệu đã kiểm tra quyền trong trang gọi. */
require_once __DIR__.'/lesson_book_excel.php';
function cmhome_xlsx_text(string $ref, $value, int $style): string {
    $value=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$value);
    return lb_xlsx_text($ref,$value??'', $style);
}
function cmhome_xlsx_styles(): string {
    return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0"/><numFmt numFmtId="165" formatCode="#,##0.000"/></numFmts>'
      .'<fonts count="3"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="16"/><color rgb="FF135334"/><name val="Arial"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts>'
      .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF176947"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F8F2"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD5EFDA"/></patternFill></fill></fills>'
      .'<borders count="2"><border/><border><left style="thin"><color rgb="FFD4E7D9"/></left><right style="thin"><color rgb="FFD4E7D9"/></right><top style="thin"><color rgb="FFD4E7D9"/></top><bottom style="thin"><color rgb="FFD4E7D9"/></bottom></border></borders>'
      .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="9">'
      .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
      .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
      .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
      .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
      .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
      .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
      .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
      .'<xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
      .'<xf numFmtId="164" fontId="1" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
      .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}
function cmhome_xlsx_sheet(string $title,string $subtitle,array $headers,array $rows,array $widths,array $totals=[]): string {
    $lastCol=lb_xlsx_col(count($headers));$xmlRows=[];
    $xmlRows[]='<row r="1" ht="32">'.cmhome_xlsx_text('A1',$title,1).'</row>';
    $xmlRows[]='<row r="2" ht="24">'.cmhome_xlsx_text('A2',$subtitle,0).'</row>';
    $cells='';foreach($headers as $i=>$head)$cells.=cmhome_xlsx_text(lb_xlsx_col($i+1).'3',$head,2);
    $xmlRows[]='<row r="3" ht="36">'.$cells.'</row>';
    foreach($rows as $i=>$values){$line=$i+4;$cells='';foreach($values as $j=>$value){$ref=lb_xlsx_col($j+1).$line;$style=$i%2?4:3;if(is_int($value)||is_float($value))$cells.=lb_xlsx_number($ref,$value,is_float($value)?6:($i%2?7:5));else $cells.=cmhome_xlsx_text($ref,$value,$style);}$xmlRows[]='<row r="'.$line.'" ht="24">'.$cells.'</row>';}
    $dataLast=max(3,count($rows)+3);
    if($totals){$line=$dataLast+1;$cells='';foreach($totals as $j=>$value){$ref=lb_xlsx_col($j+1).$line;$cells.=is_int($value)||is_float($value)?lb_xlsx_number($ref,$value,is_float($value)?6:8):cmhome_xlsx_text($ref,$value,4);}$xmlRows[]='<row r="'.$line.'" ht="28">'.$cells.'</row>';}
    $cols='';foreach($widths as $i=>$width){$n=$i+1;$cols.='<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';}
    return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$lastCol.max(4,$dataLast+($totals?1:0)).'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.implode('',$xmlRows).'</sheetData><mergeCells count="2"><mergeCell ref="A1:'.$lastCol.'1"/><mergeCell ref="A2:'.$lastCol.'2"/></mergeCells><autoFilter ref="A3:'.$lastCol.$dataLast.'"/><printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>';
}
function cmhome_export_xlsx(string $type,array $students,array $mealData,array $grams,array $settings,array $ledger,string $className,string $year,string $month,array $mealDone=[],array $ledgerDone=[]): void {
    if(!class_exists('ZipArchive')){http_response_code(503);exit('Máy chủ cần bật ZipArchive để xuất Excel.');}
    $days=(int)date('t',strtotime($month.'-01'));
    if($type==='meals'){
        $headers=['STT','Họ và tên','Sáng','Trưa','Tối','Tổng bữa','Gạo đã ăn (kg)','Hoàn sáng (đ)','Hoàn trưa (đ)','Hoàn tối (đ)','Tổng hoàn (đ)','Gạo còn (kg)','Đã hoàn thành'];
        $rows=[];$sum=array_fill(0,count($headers),0);$sum[0]='TỔNG LỚP';$sum[1]='';
        foreach($students as $i=>$student){$meal=$mealData[$student['id']]??[];$s=(int)($meal['sang']??0);$t=(int)($meal['trua']??0);$e=(int)($meal['toi']??0);$rice=(float)($meal['rice_kg']??0);$rs=max(0,$days-$s)*(int)$settings['rate_sang'];$rt=max(0,$days-$t)*(int)$settings['rate_trua'];$re=max(0,$days-$e)*(int)$settings['rate_toi'];$row=[$i+1,$student['name'],$s,$t,$e,$s+$t+$e,$rice,$rs,$rt,$re,$rs+$rt+$re,max(0,15-$rice),isset($mealDone[(string)$student['id']])?'Đã hoàn thành':'Chưa hoàn thành'];$rows[]=$row;for($j=2;$j<12;$j++)$sum[$j]+=$row[$j];}
        $sheet=cmhome_xlsx_sheet('BỮA ĂN VÀ GẠO · LỚP '.$className,'Năm học '.$year.' · Tháng '.$month.' · '.count($students).' học sinh · Tiền hoàn dự tính theo '. $days.' ngày/tháng; gạo 15 kg/học sinh',$headers,$rows,[8,30,11,11,11,13,19,19,19,19,21,19,20],$sum);
        $name='Bữa ăn và gạo';
    }else{
        $headers=['STT','Ngày','Loại','Số tiền (đ)','Nội dung','Học sinh','Người ghi','Đã hoàn thành'];$rows=[];$income=0;$expense=0;
        $map=[];foreach($students as $student)$map[(string)$student['id']]=$student['name'];
        foreach($ledger as $i=>$entry){$amount=(int)$entry['amount'];if($entry['kind']==='thu')$income+=$amount;else $expense+=$amount;$rows[]=[$i+1,(string)$entry['entry_date'],$entry['kind']==='thu'?'Thu':'Chi',$amount,(string)$entry['description'],$entry['student_id']===''?'Chung cả lớp':($map[$entry['student_id']]??'Đã chuyển lớp'),(string)$entry['created_by'],isset($ledgerDone[(int)$entry['id']])?'Đã hoàn thành':'Chưa hoàn thành'];}
        $sheet=cmhome_xlsx_sheet('SỔ THU CHI · LỚP '.$className,'Năm học '.$year.' · Tháng '.$month.' · Thu '.number_format($income,0,',','.').' đ · Chi '.number_format($expense,0,',','.').' đ',$headers,$rows,[8,16,12,19,48,30,28,20],['TỔNG','', '',$income-$expense,'Chênh lệch thu – chi','','','']);
        $name='Sổ thu chi';
    }
    $files=lb_xlsx_base_files($name.' '.$className.' '.$month,$name,$sheet,cmhome_xlsx_styles());
    $tmp=tempnam(sys_get_temp_dir(),'cmhome_');if($tmp===false||!lb_xlsx_zip($files,$tmp)){if($tmp!==false)@unlink($tmp);http_response_code(503);exit('Không tạo được tệp Excel.');}
    $filename=($type==='meals'?'bua-an-gao-':'so-thu-chi-').preg_replace('/[^A-Za-z0-9_-]/','_', $className).'-'.$month.'.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));header('Cache-Control: private, no-store');readfile($tmp);@unlink($tmp);exit;
}
