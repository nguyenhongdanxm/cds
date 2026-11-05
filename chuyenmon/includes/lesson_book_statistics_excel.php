<?php
require_once __DIR__.'/lesson_book_excel.php';
/** Runtime export uses the application's existing OOXML writer, with no external service. */
function lb_stats_excel_sheets(array $rows):array {
 $heads=['Tên','Tổng tiết TKB','Đã ký','Chưa hoàn thành','Thiếu PPCT','Đã dạy','Dạy thay','Dạy bù','Trực tuyến','Nghỉ/hoãn/hủy','Nghỉ lễ','GV nghỉ','Lớp nghỉ','Hoãn','Hủy','Chưa ghi','Tỷ lệ ký'];
 $keys=['scheduled','completed','incomplete','missing_ppct','taught','substitute','makeup','online','off','holiday','teacher_absent','class_absent','postponed','cancelled','pending'];
 $sheets=[];
 foreach(['Tổng hợp'=>null,'Giáo viên'=>'actual_teacher','Tổ chuyên môn'=>'teacher_group','Môn học'=>'subject','Lớp'=>'class','Tuần'=>'week_label']as$name=>$field){
  $data=[];foreach($field===null?[lb_stat_totals($rows)]:lb_stat_group($rows,$field)as$g){$values=[$g['name']];foreach($keys as$key)$values[]=(int)($g[$key]??0);$values[]=$g['scheduled']?$g['completed']/$g['scheduled']:0;$data[]=$values;}
  $sheets[]=['name'=>$name,'heads'=>$heads,'widths'=>array_merge([32],array_fill(0,15,12),[14]),'rows'=>$data,'percentage'=>16];
 }
 $labels=['pending'=>'Chưa ghi','taught'=>'Đã dạy','substitute'=>'Dạy thay','makeup'=>'Dạy bù','online'=>'Trực tuyến','holiday'=>'Nghỉ lễ','teacher_absent'=>'GV nghỉ','class_absent'=>'Lớp nghỉ','postponed'=>'Hoãn','cancelled'=>'Hủy'];$detail=[];
 foreach($rows as$r)$detail[]=[(string)($r['date']??''),isset($r['date'])?([1=>'Thứ Hai',2=>'Thứ Ba',3=>'Thứ Tư',4=>'Thứ Năm',5=>'Thứ Sáu',6=>'Thứ Bảy',7=>'Chủ nhật'][(int)date('N',strtotime($r['date']))]):'',(string)($r['session']??''),(int)($r['period']??0),(string)($r['class']??''),(string)($r['subject']??''),(string)($r['scheduled_teacher']??''),(string)($r['actual_teacher']??''),(int)($r['ppct_period']??0),(string)($r['lesson_title']??''),$labels[lb_stat_status($r)]??lb_stat_status($r),!empty($r['signed_at'])?'Đã ký':'Chưa ký',(string)($r['signed_by']??'')];
 $sheets[]=['name'=>'Chi tiết tiết học','heads'=>['Ngày','Thứ','Buổi','Tiết TKB','Lớp','Môn','GV theo TKB','GV thực tế','Tiết PPCT','Đầu bài','Trạng thái','Ký sổ','Người ký'],'widths'=>[14,14,12,10,10,24,28,28,12,60,20,14,28],'rows'=>$detail,'date'=>0];
 return $sheets;
}
function lb_stats_excel_styles():string {
 $styles=lb_xlsx_styles();
 $styles=str_replace('<fonts count="3">','<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts><fonts count="3">',$styles);
 // Alternate rows, actual percent cells, legible wrap, and borders.
 $styles=str_replace('<fills count="3">','<fills count="4">',$styles);
 $styles=str_replace('</fills>','<fill><patternFill patternType="solid"><fgColor rgb="FFF0F6FC"/><bgColor indexed="64"/></patternFill></fill></fills>',$styles);
 $styles=str_replace('<cellXfs count="7">','<cellXfs count="13">',$styles);
 $extra='<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
 foreach([0,3]as$fill)$extra.='<xf numFmtId="10" fontId="0" fillId="'.$fill.'" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';
 foreach([0,3]as$fill)$extra.='<xf numFmtId="164" fontId="0" fillId="'.$fill.'" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';
 return str_replace('</cellXfs>',$extra.'</cellXfs>',$styles);
}
function lb_stats_excel_sheet(array $sheet,string $subtitle):string {
 $lastCol=lb_xlsx_col(count($sheet['heads']));$last=max(4,count($sheet['rows'])+4);
 $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$lastCol.$last.'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="4" topLeftCell="B5" xSplit="1" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="24"/><cols>';
 foreach($sheet['widths']as$i=>$width)$xml.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
 $xml.='</cols><sheetData><row r="1" ht="32" customHeight="1">'.lb_xlsx_text('A1','THỐNG KÊ KÝ SỔ - '.mb_strtoupper($sheet['name'],'UTF-8'),1).'</row><row r="2" ht="30" customHeight="1">'.lb_xlsx_text('A2',$subtitle,0).'</row><row r="3" ht="24" customHeight="1">'.lb_xlsx_text('A3','Hoàn thành = đã ký. Tiết nghỉ/hoãn/hủy vẫn thuộc tổng tiết TKB; tỷ lệ tính theo phạm vi đang lọc.',0).'</row><row r="4" ht="48" customHeight="1">';
 foreach($sheet['heads']as$i=>$head)$xml.=lb_xlsx_text(lb_xlsx_col($i+1).'4',$head,2);
 $xml.='</row>';
 foreach($sheet['rows']as$n=>$values){
  $r=$n+5;$height=26;
  foreach($values as$i=>$value)if(is_string($value)){$lines=0;foreach(explode("\n",$value)as$line)$lines+=max(1,(int)ceil(mb_strlen($line,'UTF-8')/max(6,$sheet['widths'][$i]-3)));$height=max($height,min(409,18*$lines+8));}
  $xml.='<row r="'.$r.'" ht="'.$height.'" customHeight="1">';
  foreach($values as$i=>$value){$ref=lb_xlsx_col($i+1).$r;$alt=$n%2;
   if(isset($sheet['date'])&&$i===$sheet['date']&&preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$value))$xml.=lb_xlsx_number($ref,(int)(new DateTimeImmutable('1899-12-30'))->diff(new DateTimeImmutable($value))->format('%r%a'),11+$alt);
   elseif(is_int($value)||is_float($value))$xml.=lb_xlsx_number($ref,$value,isset($sheet['percentage'])&&$i===$sheet['percentage']?9+$alt:($alt?8:4));
   else $xml.=lb_xlsx_text($ref,$value,$alt?7:3);
  }$xml.='</row>';
 }
 return $xml.'</sheetData><autoFilter ref="A4:'.$lastCol.$last.'"/><mergeCells count="3"><mergeCell ref="A1:'.$lastCol.'1"/><mergeCell ref="A2:'.$lastCol.'2"/><mergeCell ref="A3:'.$lastCol.'3"/></mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="8" fitToWidth="1" fitToHeight="0"/><headerFooter><oddFooter>&amp;CTrang &amp;P / &amp;N</oddFooter></headerFooter></worksheet>';
}
function lb_stats_xlsx(array $rows,string $from,string $to,string $target,array $filters=[]):bool {
 $subtitle='Từ '.date('d/m/Y',strtotime($from)).' đến '.date('d/m/Y',strtotime($to));
 foreach($filters as$label=>$value)if($value!=='')$subtitle.=' · '.$label.': '.$value;
 $sheets=lb_stats_excel_sheets($rows);$files=lb_xlsx_base_files('Thống kê ký sổ','Tổng hợp',lb_stats_excel_sheet($sheets[0],$subtitle),lb_stats_excel_styles());
 $workbookSheets='';$rels='';$types='';$printTitles='';
 foreach($sheets as$i=>$sheet){$id=$i+1;$name=lb_xlsx_xml($sheet['name']);$workbookSheets.='<sheet name="'.$name.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';$rels.='<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';$types.='<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';$files['xl/worksheets/sheet'.$id.'.xml']=lb_stats_excel_sheet($sheet,$subtitle);$printTitles.='<definedName name="_xlnm.Print_Titles" localSheetId="'.$i.'">'.lb_xlsx_xml("'".$sheet['name']."'!$1:$4").'</definedName>';}
 $files['xl/workbook.xml']='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$workbookSheets.'</sheets><definedNames>'.$printTitles.'</definedNames></workbook>';
 $files['xl/_rels/workbook.xml.rels']='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'<Relationship Id="rId8" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
 $files['[Content_Types].xml']=preg_replace('~<Override PartName="/xl/worksheets/sheet1.xml"[^>]*/>~',$types,$files['[Content_Types].xml']);
 $names='';foreach($sheets as$sheet)$names.='<vt:lpstr>'.lb_xlsx_xml($sheet['name']).'</vt:lpstr>';
 $files['docProps/app.xml']='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>CDS</Application><TitlesOfParts><vt:vector size="7" baseType="lpstr">'.$names.'</vt:vector></TitlesOfParts></Properties>';
 return lb_xlsx_zip($files,$target);
}
