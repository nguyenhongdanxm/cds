<?php
/** Export already-filtered rows, preserving the same order as the register. */
function vb_number_csv_cell($value): string {
    $value=(string)$value;
    // Spreadsheet applications must treat user content as text, never formulas.
    if(preg_match('/^[\x00-\x20]*[=+@-]/',$value)||preg_match('/^[\t\r\n]/',$value))return "'".$value;
    return $value;
}
function vb_number_csv_write($stream,array $rows): void {
    fwrite($stream,"\xEF\xBB\xBF");
    fputcsv($stream,['STT','Sổ văn bản','Năm','Số','Số, ký hiệu','Ngày phát hành','Tên / trích yếu văn bản','Đơn vị phát hành','Người soạn','Người ký','Trạng thái'],',','"','');
    foreach($rows as$i=>$r){
        $date=trim((string)($r['issued_date']??''));$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        $date=$parsed&&$parsed->format('Y-m-d')===$date?$parsed->format('d/m/Y'):$date;
        $status=match($r['status']??'reserved'){'published'=>'Đã phát hành','cancelled'=>'Đã hủy',default=>'Đang giữ số'};
        $values=[$i+1,($r['book']??'')==='decision'?'Quyết định':'Văn bản khác',$r['year']??'',$r['number']??'',$r['symbol']??'',$date,$r['title']??'',$r['issuer']??'',$r['drafter']??'',$r['signer']??'',$status];
        fputcsv($stream,array_map('vb_number_csv_cell',$values),',','"','');
    }
}

function vb_number_xlsx_xml($value): string {
    $value=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$value);
    return htmlspecialchars($value,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
function vb_number_xlsx_cell(string $ref,$value,int $style): string {
    // inlineStr prevents formulas and preserves leading zeros in identifiers.
    return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.vb_number_xlsx_xml($value).'</t></is></c>';
}
function vb_number_xlsx_zip(array $files): string {
    $body='';$directory='';$offset=0;
    foreach($files as$name=>$content){$size=strlen($content);$crc=crc32($content);
        $local=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,33,$crc,$size,$size,strlen($name),0).$name.$content;
        $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,33,$crc,$size,$size,strlen($name),0,0,0,0,0,$offset).$name;
        $body.=$local;$offset+=strlen($local);
    }
    return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,count($files),count($files),strlen($directory),strlen($body),0);
}
function vb_number_xlsx_styles(): string {
    $xml='<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts><fonts count="4"><font><sz val="11"/><name val="Times New Roman"/></font><font><b/><sz val="15"/><name val="Times New Roman"/><color rgb="FF17365D"/></font><font><b/><sz val="11"/><name val="Times New Roman"/><color rgb="FFFFFFFF"/></font><font><i/><sz val="10"/><name val="Times New Roman"/><color rgb="FF526477"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF17365D"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF1F8"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFB8C8D8"/></left><right style="thin"><color rgb="FFB8C8D8"/></right><top style="thin"><color rgb="FFB8C8D8"/></top><bottom style="thin"><color rgb="FFB8C8D8"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="10">';
    foreach([[0,0,0,'left',0],[1,0,0,'center',0],[3,0,0,'left',0],[2,2,1,'center',0],[0,0,1,'left',0],[0,3,1,'left',0],[0,0,1,'center',0],[0,3,1,'center',0],[0,0,1,'center',164],[0,3,1,'center',164]]as[$font,$fill,$border,$align,$num])$xml.='<xf numFmtId="'.$num.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyAlignment="1" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment horizontal="'.$align.'" vertical="center" wrapText="1"/></xf>';
    return $xml.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}
function vb_number_xlsx(array $rows,string $school,string $query=''): string {
    $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$decl='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $names=['decision'=>'Quyết định','other'=>'Văn bản khác'];$files=[];$sheets='';$rels='';$types='';$defined='';$sheetId=0;
    foreach($names as$book=>$name){$sheetId++;$data=array_values(array_filter($rows,fn($r)=>($r['book']??'')===$book));$last=max(6,count($data)+5);$sheets.='<sheet name="'.$name.'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';$rels.='<Relationship Id="rId'.$sheetId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sheetId.'.xml"/>';$types.='<Override PartName="/xl/worksheets/sheet'.$sheetId.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $quoted="'".$name."'";$defined.='<definedName name="_xlnm.Print_Area" localSheetId="'.($sheetId-1).'">'.vb_number_xlsx_xml($quoted.'!$A$1:$J$'.$last).'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="'.($sheetId-1).'">'.vb_number_xlsx_xml($quoted.'!$1:$5').'</definedName>';
        $xml='<worksheet xmlns="'.$ns.'"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:J'.$last.'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="30"/><cols>';
        foreach([5,8,7,19,14,50,25,20,20,18]as$i=>$width)$xml.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
        $context='Tổng số: '.count($data).' văn bản'.($query!==''?' · Tìm kiếm: '.$query:'').' · Ngày xuất: '.date('d/m/Y');
        $xml.='</cols><sheetData><row r="1" ht="23" customHeight="1">'.vb_number_xlsx_cell('A1',$school,0).'</row><row r="2" ht="29" customHeight="1">'.vb_number_xlsx_cell('A2','DANH MỤC '.mb_strtoupper($name,'UTF-8'),1).'</row><row r="3" ht="30" customHeight="1">'.vb_number_xlsx_cell('A3',$context,2).'</row><row r="4" ht="8" customHeight="1"/><row r="5" ht="32" customHeight="1">';
        foreach(['STT','Năm','Số','Số, ký hiệu','Ngày phát hành','Tên / trích yếu văn bản','Đơn vị phát hành','Người soạn','Người ký','Trạng thái']as$i=>$header)$xml.=vb_number_xlsx_cell(chr(65+$i).'5',$header,3);$xml.='</row>';
        foreach($data as$i=>$r){$n=$i+6;$odd=$i%2;$status=match($r['status']??'reserved'){'published'=>'Đã phát hành','cancelled'=>'Đã hủy',default=>'Đang giữ số'};$date=(string)($r['issued_date']??'');$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));$serial=$parsed&&$parsed->format('Y-m-d')===$date?(int)(($parsed->getTimestamp()-gmmktime(0,0,0,12,30,1899))/86400):null;
            $values=[$i+1,$r['year']??'',$r['number']??'',$r['symbol']??'',$date,$r['title']??'',$r['issuer']??'',$r['drafter']??'',$r['signer']??'',$status];$lines=1;
            foreach($values as$c=>$v){$limit=[4,7,6,17,12,46,23,18,18,16][$c];foreach(explode("\n",(string)$v)as$part)$lines=max($lines,(int)ceil(mb_strlen($part,'UTF-8')/$limit));}
            $height=min(409,max(30,$lines*16+8));$xml.='<row r="'.$n.'" ht="'.$height.'" customHeight="1">';
            foreach($values as$c=>$v){$ref=chr(65+$c).$n;$style=in_array($c,[0,1,2,4,9],true)?6+$odd:4+$odd;
                if($c===4&&$serial!==null)$xml.='<c r="'.$ref.'" s="'.(8+$odd).'"><v>'.$serial.'</v></c>';
                elseif($c===0)$xml.='<c r="'.$ref.'" s="'.$style.'"><v>'.($i+1).'</v></c>';
                else $xml.=vb_number_xlsx_cell($ref,$v,$style);
            }$xml.='</row>';
        }
        if(!$data)$xml.='<row r="6" ht="30" customHeight="1">'.vb_number_xlsx_cell('A6','Không có văn bản phù hợp.',2).'</row>';
        $xml.='</sheetData><autoFilter ref="A5:J'.$last.'"/><mergeCells count="'.(!$data?4:3).'"><mergeCell ref="A1:J1"/><mergeCell ref="A2:J2"/><mergeCell ref="A3:J3"/>'.(!$data?'<mergeCell ref="A6:J6"/>':'').'</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.15" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><headerFooter><oddFooter>&amp;L'.vb_number_xlsx_xml($name).'&amp;RTrang &amp;P / &amp;N</oddFooter></headerFooter></worksheet>';
        $files['xl/worksheets/sheet'.$sheetId.'.xml']=$decl.$xml;
    }
    $files['xl/workbook.xml']=$decl.'<workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$sheets.'</sheets><definedNames>'.$defined.'</definedNames></workbook>';
    $files['xl/_rels/workbook.xml.rels']=$decl.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $files['xl/styles.xml']=$decl.vb_number_xlsx_styles();
    $files['_rels/.rels']=$decl.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $files['[Content_Types].xml']=$decl.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$types.'</Types>';
    return vb_number_xlsx_zip($files);
}
