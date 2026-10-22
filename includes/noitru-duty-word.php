<?php
/** DOCX lịch trực theo tháng; chỉ đọc dữ liệu, không thay đổi phân công. */
require_once __DIR__ . '/../chuyenmon/includes/periodic_report_docx.php';
function nt_duty_word_p(string $text, string $align='left', bool $bold=false, int $size=24, bool $keep=false): string {
 return '<w:p><w:pPr><w:jc w:val="'.$align.'"/><w:spacing w:before="0" w:after="60" w:line="240" w:lineRule="auto"/>'.($keep?'<w:keepNext/>':'').'</w:pPr><w:r><w:rPr>'.($bold?'<w:b/>':'').'<w:sz w:val="'.$size.'"/></w:rPr><w:t xml:space="preserve">'.pr_xml($text).'</w:t></w:r></w:p>';
}
function nt_duty_word(string $month, array $rows, array $managers, array $settings): string {
 $start=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01');
 if(!$start||$start->format('Y-m')!==$month)throw new InvalidArgumentException('Tháng không hợp lệ.');
 $byDate=[];foreach($rows as$r)if(str_starts_with((string)($r['date']??''),$month.'-'))$byDate[(string)$r['date']][]=$r;
 $managerMap=[];foreach($managers as$r)$managerMap[(string)($r['date']??'')]=$r;
 $upper=static fn($s)=>function_exists('mb_strtoupper')?mb_strtoupper($s,'UTF-8'):$s;
 $body=nt_duty_word_p($upper(defined('SCHOOL_SO')?SCHOOL_SO:'SỞ GIÁO DỤC VÀ ĐÀO TẠO TUYÊN QUANG'),'left',false,24,true);
 $body.=nt_duty_word_p($upper(defined('SCHOOL_NAME')?SCHOOL_NAME:'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN'),'left',true,24,true);
 $body.='<w:p><w:pPr><w:ind w:left="0" w:right="6155"/><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="1" w:color="000000"/></w:pBdr><w:spacing w:before="0" w:after="160" w:line="20" w:lineRule="exact"/><w:keepNext/></w:pPr></w:p>';
 $body.=nt_duty_word_p('DANH SÁCH PHÂN CÔNG TRỰC NỘI TRÚ','center',true,28,true);
 $body.=nt_duty_word_p('Tháng '.$start->format('m').' năm '.$start->format('Y'),'center',true,26,true);
 $from=(string)($settings['start_time']??'06:00');$to=(string)($settings['end_time']??'06:00');
 $body.=nt_duty_word_p('Thời gian trực: '.$from.' – '.$to.($to<=$from?' ngày hôm sau.':'.'),'left',false,24,true);
 $widths=[1100,1150,3500,2400,1205];
 $body.='<w:tbl><w:tblPr><w:tblW w:w="9355" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders>';
 foreach(['top','left','bottom','right','insideH','insideV']as$side)$body.='<w:'.$side.' w:val="single" w:sz="4" w:color="777777"/>';
 $body.='</w:tblBorders><w:tblCellMar><w:top w:w="65" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="65" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
 foreach($widths as$w)$body.='<w:gridCol w:w="'.$w.'"/>';$body.='</w:tblGrid>';
 $tableRow=static function(array $values,bool $head=false)use($widths):string{
  $xml='<w:tr><w:trPr><w:cantSplit/>'.($head?'<w:tblHeader/>':'').'</w:trPr>';
  foreach($values as$i=>$value){$xml.='<w:tc><w:tcPr><w:tcW w:w="'.$widths[$i].'" w:type="dxa"/><w:vAlign w:val="center"/>'.($head?'<w:shd w:fill="EAEAEA"/>':'').'</w:tcPr>';
   foreach(explode("\n",(string)$value)as$line)$xml.=nt_duty_word_p($line,$head||$i<2?'center':'left',$head,22);
   $xml.='</w:tc>';}
  return$xml.'</w:tr>';
 };
 $body.=$tableRow(['Ngày','Thứ','Người trực','Quản lý trực','Ghi chú'],true);
 $weekdays=['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ nhật'];
 for($day=1;$day<=(int)$start->format('t');$day++){
  $date=$start->modify('+'.($day-1).' days');$key=$date->format('Y-m-d');$names=[];$notes=[];
  foreach($byDate[$key]??[]as$r){$name=trim((string)($r['teacher_name']??''));if($name!=='')$names[]=$name;$note=trim((string)($r['note']??''));if($note!=='')$notes[]=$note;}
  $manager=$managerMap[$key]??[];$managerNames=(array)($manager['teacher_names']??[]);
  if(trim((string)($manager['note']??''))!=='')$notes[]=(string)$manager['note'];
  $body.=$tableRow([$date->format('d/m'),$weekdays[(int)$date->format('N')-1],$names?implode("\n",array_unique($names)):'Chưa phân công',implode("\n",$managerNames),'']);
 }
 $body.='</w:tbl>'.nt_duty_word_p('', 'left',false,24,true);
 $body.='<w:tbl><w:tblPr><w:tblW w:w="9355" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders>';
 foreach(['top','left','bottom','right','insideH','insideV']as$side)$body.='<w:'.$side.' w:val="nil"/>';
 $body.='</w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="4677"/><w:gridCol w:w="4678"/></w:tblGrid><w:tr><w:trPr><w:cantSplit/></w:trPr>';
 foreach(['PHÊ DUYỆT CỦA BAN GIÁM HIỆU','TỔ QUẢN LÝ NỘI TRÚ']as$signer){
  $body.='<w:tc><w:tcPr><w:tcW w:w="4677" w:type="dxa"/></w:tcPr>'.nt_duty_word_p($signer,'center',true,24,true).nt_duty_word_p('(Ký, ghi rõ họ tên)','center',false,24,true);
  for($i=0;$i<4;$i++)$body.=nt_duty_word_p('','center',false,24,$i<3);
  $body.='</w:tc>';
 }
 $body.='</w:tr></w:tbl><w:p/>';
 $ns='http://schemas.openxmlformats.org/wordprocessingml/2006/main';$xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
 $doc=$xml.'<w:document xmlns:w="'.$ns.'"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="850" w:bottom="1134" w:left="1701" w:header="567" w:footer="567"/></w:sectPr></w:body></w:document>';
 $styles=$xml.'<w:styles xmlns:w="'.$ns.'"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:lang w:val="vi-VN"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>';
 return pr_zip(['[Content_Types].xml'=>$xml.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
 '_rels/.rels'=>$xml.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
 'word/document.xml'=>$doc,'word/styles.xml'=>$styles,'word/_rels/document.xml.rels'=>$xml.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>']);
}
