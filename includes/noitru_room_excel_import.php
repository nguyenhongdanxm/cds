<?php
/** Mẫu và bộ nhập XLSX chia phòng. Mỗi worksheet là một phòng. */

function nt_room_excel_xml($value) {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function nt_room_excel_col($number) {
    $name='';
    while($number>0){$number--;$name=chr(65+($number%26)).$name;$number=intdiv($number,26);}
    return $name;
}

function nt_room_excel_zip(array $files,$target) {
    $body='';$central='';$offset=0;$time=getdate();
    $dosTime=(($time['hours']&0x1f)<<11)|(($time['minutes']&0x3f)<<5)|(($time['seconds']>>1)&0x1f);
    $dosDate=((($time['year']-1980)&0x7f)<<9)|(($time['mon']&0x0f)<<5)|($time['mday']&0x1f);
    foreach($files as $name=>$content){
        $name=str_replace('\\','/',$name);$size=strlen($content);$crc=crc32($content);
        $local=pack('VvvvvvVVVvv',0x04034b50,20,0,0,$dosTime,$dosDate,$crc,$size,$size,strlen($name),0).$name.$content;
        $body.=$local;$central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,$dosTime,$dosDate,$crc,$size,$size,strlen($name),0,0,0,0,0,$offset).$name;$offset+=strlen($local);
    }
    $end=pack('VvvvvVVv',0x06054b50,0,0,count($files),count($files),strlen($central),strlen($body),0);
    if(file_put_contents($target,$body.$central.$end)===false)throw new RuntimeException('Không thể tạo file Excel mẫu.');
}

function nt_room_excel_safe_sheet($name,array &$used) {
    $name=preg_replace('/[\\\\\/\?\*\[\]:]/u','-',trim((string)$name))?:'Phòng';$name=mb_substr($name,0,31);$base=$name;$i=2;
    while(isset($used[mb_strtolower($name,'UTF-8')])){$suffix='-'.$i++;$name=mb_substr($base,0,31-mb_strlen($suffix)).$suffix;}
    $used[mb_strtolower($name,'UTF-8')]=true;return $name;
}

function nt_room_excel_template(array $roomNames) {
    if(!$roomNames)$roomNames=['Phòng 01'];$used=[];$sheetNames=[];$sheetFiles=[];
    foreach(array_values($roomNames) as $index=>$roomName){
        $sheetName=nt_room_excel_safe_sheet($roomName,$used);$sheetNames[]=$sheetName;
        $headers=['STT','Họ và tên','Lớp','Ngày sinh','Giới tính'];$cells='';
        foreach($headers as $col=>$header){$ref=nt_room_excel_col($col+1).'1';$cells.='<c r="'.$ref.'" t="inlineStr" s="1"><is><t>'.nt_room_excel_xml($header).'</t></is></c>';}
        $rows=['<row r="1" ht="28">'.$cells.'</row>'];
        for($r=2;$r<=31;$r++)$rows[]='<row r="'.$r.'"><c r="A'.$r.'" s="2"><v>'.($r-1).'</v></c><c r="B'.$r.'" s="3"/><c r="C'.$r.'" s="3"/><c r="D'.$r.'" s="4"/><c r="E'.$r.'" s="3"/></row>';
        $sheetFiles['xl/worksheets/sheet'.($index+1).'.xml']='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="14" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/><col min="5" max="5" width="14" customWidth="1"/></cols><sheetData>'.implode('',$rows).'</sheetData><autoFilter ref="A1:E31"/><pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/></worksheet>';
    }
    $contentTypes='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach($sheetNames as $i=>$unused)$contentTypes.='<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';$contentTypes.='</Types>';
    $workbook='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';$rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach($sheetNames as $i=>$sheetName){$n=$i+1;$workbook.='<sheet name="'.nt_room_excel_xml($sheetName).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';$rels.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';}$workbook.='</sheets></workbook>';$rels.='<Relationship Id="rId'.(count($sheetNames)+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right><top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    $files=['[Content_Types].xml'=>$contentTypes,'_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>','xl/workbook.xml'=>$workbook,'xl/_rels/workbook.xml.rels'=>$rels,'xl/styles.xml'=>$styles]+$sheetFiles;
    $tmp=tempnam(sys_get_temp_dir(),'nt-room-template-');nt_room_excel_zip($files,$tmp);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="mau-nhap-chia-phong.xlsx"');header('Content-Length: '.filesize($tmp));header('Cache-Control: max-age=0');readfile($tmp);@unlink($tmp);exit;
}

function nt_room_excel_norm($value) {
    $value=mb_strtolower(trim((string)$value),'UTF-8');$value=preg_replace('/\s+/u',' ',$value);
    if(class_exists('Transliterator')){$tr=Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');if($tr)$value=$tr->transliterate($value);}
    return $value;
}

function nt_room_excel_date($value) {
    $value=trim((string)$value);if($value==='')return '';
    if(is_numeric($value)&&((float)$value)>1000)return gmdate('Y-m-d',(int)round(((float)$value-25569)*86400));
    if(preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/',$value,$m))return sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[2],(int)$m[1]);
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))return $value;return $value;
}

function nt_room_excel_dom($raw) {
    $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$ok=$dom->loadXML((string)$raw,LIBXML_NONET|LIBXML_NOBLANKS);libxml_clear_errors();libxml_use_internal_errors($previous);return $ok?$dom:null;
}

function nt_room_excel_cell_value(DOMElement $cell,DOMXPath $xpath,array $shared) {
    $type=$cell->getAttribute('t');$parts=$xpath->query('.//main:t',$cell);$text='';if($parts)foreach($parts as $part)$text.=$part->textContent;
    if($type==='inlineStr'||$text!=='')return trim($text);$valueNode=$xpath->query('./main:v',$cell)->item(0);$raw=$valueNode?$valueNode->textContent:'';
    return $type==='s'?(string)($shared[(int)$raw]??''):$raw;
}

function nt_room_excel_parse($path) {
    if(!class_exists('ZipArchive'))return ['ok'=>false,'errors'=>['Hosting chưa bật PHP ZipArchive nên chưa đọc được file XLSX.']];
    $zip=new ZipArchive();if($zip->open($path)!==true)return ['ok'=>false,'errors'=>['Không mở được file XLSX.']];
    $workbookRaw=$zip->getFromName('xl/workbook.xml');$relsRaw=$zip->getFromName('xl/_rels/workbook.xml.rels');
    if($workbookRaw===false||$relsRaw===false){$zip->close();return ['ok'=>false,'errors'=>['File không đúng cấu trúc Excel XLSX.']];}
    if(!class_exists('DOMDocument')){$zip->close();return ['ok'=>false,'errors'=>['Hosting chưa bật PHP DOM nên chưa đọc được file XLSX.']];}
    $shared=[];$sharedRaw=$zip->getFromName('xl/sharedStrings.xml');if($sharedRaw!==false&&($sharedDom=nt_room_excel_dom($sharedRaw))){$sharedXpath=new DOMXPath($sharedDom);$sharedXpath->registerNamespace('main','http://schemas.openxmlformats.org/spreadsheetml/2006/main');foreach($sharedXpath->query('//main:si') as $si){$text='';foreach($sharedXpath->query('.//main:t',$si) as $part)$text.=$part->textContent;$shared[]=$text;}}
    $relMap=[];$relsDom=nt_room_excel_dom($relsRaw);if($relsDom){$relsXpath=new DOMXPath($relsDom);$relsXpath->registerNamespace('pkg','http://schemas.openxmlformats.org/package/2006/relationships');foreach($relsXpath->query('//pkg:Relationship') as $rel)$relMap[$rel->getAttribute('Id')]=$rel->getAttribute('Target');}
    $workbook=nt_room_excel_dom($workbookRaw);if(!$workbook){$zip->close();return ['ok'=>false,'errors'=>['Không đọc được danh sách sheet Excel.']];}$workbookXpath=new DOMXPath($workbook);$workbookXpath->registerNamespace('main','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $result=[];$errors=[];
    foreach($workbookXpath->query('//main:sheets/main:sheet') as $sheet){
        $rid=$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$target=$relMap[$rid]??'';$sheetName=trim($sheet->getAttribute('name'));
        if($target===''||$sheetName==='')continue;$target=ltrim(str_replace('\\','/',$target),'/');$sheetPath=str_starts_with($target,'xl/')?$target:'xl/'.$target;$sheetRaw=$zip->getFromName($sheetPath);if($sheetRaw===false)continue;
        $sheetDom=nt_room_excel_dom($sheetRaw);if(!$sheetDom)continue;$sheetXpath=new DOMXPath($sheetDom);$sheetXpath->registerNamespace('main','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$rows=[];
        foreach($sheetXpath->query('//main:sheetData/main:row') as $row){$rowNumber=(int)$row->getAttribute('r');$values=[];foreach($sheetXpath->query('./main:c',$row) as $cell){$ref=$cell->getAttribute('r');if(!preg_match('/^([A-Z]+)/',$ref,$m))continue;$col=0;foreach(str_split($m[1]) as $char)$col=$col*26+(ord($char)-64);$values[$col]=nt_room_excel_cell_value($cell,$sheetXpath,$shared);}if($rowNumber===1)continue;
            $name=trim((string)($values[2]??''));$class=trim((string)($values[3]??''));$dob=nt_room_excel_date($values[4]??'');$gender=trim((string)($values[5]??''));if($name===''&&$class===''&&$dob===''&&$gender==='')continue;
            if($name===''||$class===''){$errors[]=$sheetName.' · dòng '.$rowNumber.': thiếu Họ và tên hoặc Lớp.';continue;}$rows[]=['sheet'=>$sheetName,'row'=>$rowNumber,'name'=>$name,'class'=>$class,'dob'=>$dob,'gender'=>$gender];
        }
        if($rows)$result[$sheetName]=$rows;
    }
    $zip->close();if(!$result&&!$errors)$errors[]='File chưa có dữ liệu học sinh. Mỗi sheet phải mang tên phòng.';return ['ok'=>!$errors,'rooms'=>$result,'errors'=>$errors];
}

function nt_room_excel_match_and_apply(array $parsed,array $students,array &$data,$replaceAll=false) {
    $index=[];foreach($students as $student){$key=nt_room_excel_norm($student['name']??'').'|'.nt_room_excel_norm($student['class_name']??'');$index[$key][]=$student;}
    $assignments=[];$errors=[];$seen=[];
    foreach($parsed['rooms']??[] as $room=>$rows)foreach($rows as $row){
        $key=nt_room_excel_norm($row['name']).'|'.nt_room_excel_norm($row['class']);$matches=$index[$key]??[];
        if(count($matches)>1&&$row['dob']!=='')$matches=array_values(array_filter($matches,static fn($student)=>nt_room_excel_date($student['dob']??'')===$row['dob']));
        if(count($matches)!==1){$errors[]=$room.' · dòng '.$row['row'].': không xác định duy nhất học sinh '.$row['name'].' ('.$row['class'].').';continue;}
        $student=$matches[0];$id=(string)($student['id']??'');if(isset($seen[$id])){$errors[]=$room.' · dòng '.$row['row'].': học sinh '.$row['name'].' đã xuất hiện ở phòng '.$seen[$id].'.';continue;}
        if($row['dob']!==''&&nt_room_excel_date($student['dob']??'')!==$row['dob'])$errors[]=$room.' · dòng '.$row['row'].': ngày sinh không khớp CSDL của '.$row['name'].'.';
        if($row['gender']!==''&&nt_room_excel_norm($row['gender'])!==nt_room_excel_norm(noitru_assignment_gender($student)))$errors[]=$room.' · dòng '.$row['row'].': giới tính không khớp CSDL của '.$row['name'].'.';
        $seen[$id]=$room;$assignments[$id]=$room;
    }
    if($errors)return ['ok'=>false,'errors'=>$errors,'count'=>0];
    $finalCounts=[];if(!$replaceAll)foreach($students as $student){$id=(string)($student['id']??'');if(isset($assignments[$id]))continue;$room=trim((string)($student['room_ktx']??''));if($room!=='')$finalCounts[$room]=($finalCounts[$room]??0)+1;}
    if($replaceAll)foreach($students as $student)$data['rooms'][(string)$student['id']]='';
    $roomCounts=[];foreach($assignments as $id=>$room){$roomCounts[$room]=($roomCounts[$room]??0)+1;$finalCounts[$room]=($finalCounts[$room]??0)+1;$data['rooms'][$id]=$room;}
    foreach($roomCounts as $room=>$count){if(!in_array($room,$data['room_names']??[],true))$data['room_names'][]=$room;$data['room_capacities'][$room]=max((int)($data['room_capacities'][$room]??8),(int)($finalCounts[$room]??$count));if(!isset($data['room_genders'][$room]))$data['room_genders'][$room]='Linh hoạt';}
    return ['ok'=>true,'errors'=>[],'count'=>count($assignments),'rooms'=>count($roomCounts)];
}
