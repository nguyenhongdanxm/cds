<?php
/** DOCX OOXML thực, không đổi đuôi HTML; ZIP lưu không nén, không phụ thuộc Composer/ZipArchive. */
function pr_xml($text): string {return htmlspecialchars((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$text),ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function pr_word_runs($node,array $format=[]): string {
    if($node->nodeType===XML_TEXT_NODE)return '<w:r><w:rPr>'.(!empty($format['bold'])?'<w:b/>':'').(!empty($format['italic'])?'<w:i/>':'').(!empty($format['underline'])?'<w:u w:val="single"/>':'').(!empty($format['strike'])?'<w:strike/>':'').(!empty($format['vert'])?'<w:vertAlign w:val="'.$format['vert'].'"/>':'').'<w:sz w:val="'.($format['size']??28).'"/>'.(!empty($format['color'])?'<w:color w:val="'.$format['color'].'"/>':'').'</w:rPr><w:t xml:space="preserve">'.pr_xml($node->nodeValue).'</w:t></w:r>';
    $tag=strtolower($node->nodeName);if($tag==='br')return '<w:r><w:br/></w:r>';
    if(in_array($tag,['b','strong','th'],true))$format['bold']=true;if(in_array($tag,['i','em'],true))$format['italic']=true;if($tag==='u')$format['underline']=true;if($tag==='s')$format['strike']=true;if(in_array($tag,['sup','sub'],true))$format['vert']=$tag==='sup'?'superscript':'subscript';
    $style=$node->nodeType===XML_ELEMENT_NODE?$node->getAttribute('style'):'';
    if(preg_match('/font-size:\s*(\d+)(pt|px)/',$style,$m))$format['size']=(int)round((int)$m[1]*($m[2]==='px'?1.5:2));
    if(strpos($style,'font-weight:bold')!==false)$format['bold']=true;if(strpos($style,'font-style:italic')!==false)$format['italic']=true;
    if(preg_match('/(?:^|;)\s*color:\s*#([a-f0-9]{6}|[a-f0-9]{3})(?:;|$)/i',$style,$m))$format['color']=strtoupper(strlen($m[1])===3?preg_replace('/(.)/','$1$1',$m[1]):$m[1]);
    $xml='';foreach($node->childNodes as$child)$xml.=pr_word_runs($child,$format);return $xml;
}
function pr_word_p($node,array $format=[],string $prefix=''): string {
    $style=$node->nodeType===XML_ELEMENT_NODE?$node->getAttribute('style'):'';$align=$format['align']??'both';if(preg_match('/text-align:\s*(left|center|right|justify)/',$style,$m))$align=$m[1]==='justify'?'both':$m[1];
    $rule=$node->nodeType===XML_ELEMENT_NODE?$node->getAttribute('data-pr-rule'):'';
    if($rule!==''){$width=$format['cellWidth']??4677;$length=$rule==='national'?3402:1701;$indent=max(0,(int)(($width-$length)/2));return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/></w:pBdr><w:spacing w:before="80" w:after="0" w:line="20" w:lineRule="exact"/><w:ind w:left="'.$indent.'" w:right="'.$indent.'"/></w:pPr></w:p>';}
    $line=empty($format['letterhead'])?288:276;$after=empty($format['letterhead'])?120:40;
    $heading=in_array(strtolower($node->nodeName),['h3','h4'],true);if($heading)$format['bold']=true;
    $runs=$prefix!==''?'<w:r><w:t xml:space="preserve">'.pr_xml($prefix).'</w:t></w:r>':'';
    return '<w:p><w:pPr><w:jc w:val="'.$align.'"/><w:spacing w:after="'.$after.'" w:line="'.$line.'" w:lineRule="auto"/>'.($heading?'<w:keepNext/>':'').(!$heading&&!in_array($align,['center','right'],true)&&empty($format['table'])?'<w:ind w:firstLine="567"/>':'').'</w:pPr>'.$runs.pr_word_runs($node,$format).'</w:p>';
}
function pr_word_blocks($root,array $format=[]): string {
    $xml='';$inline='';
    foreach($root->childNodes as$node){$tag=strtolower($node->nodeName);$isBlock=in_array($tag,['p','div','h3','h4','table','ul','ol','blockquote'],true);
        if(!$isBlock){$inline.=pr_word_runs($node,$format);continue;}
        if($inline!==''){$xml.='<w:p>'.$inline.'</w:p>';$inline='';}
        if($tag==='table'){$border=strpos($node->getAttribute('class'),'pr-letterhead')!==false?'nil':'single';$xml.='<w:tbl><w:tblPr><w:tblW w:w="9355" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders>';foreach(['top','left','bottom','right','insideH','insideV']as$side)$xml.='<w:'.$side.' w:val="'.$border.'" w:sz="4" w:color="000000"/>';$xml.='</w:tblBorders></w:tblPr>';
            $weights=array_map('intval',explode(',',$node->getAttribute('data-pr-widths')));$rows=$node->getElementsByTagName('tr');$gridCount=1;foreach($rows as$gridRow){if($gridRow->parentNode!==$node&&$gridRow->parentNode->parentNode!==$node)continue;$n=0;foreach($gridRow->childNodes as$gridCell)if(in_array(strtolower($gridCell->nodeName),['td','th'],true))$n+=max(1,(int)$gridCell->getAttribute('colspan'));$gridCount=max($gridCount,$n);}$xml.='<w:tblGrid>';for($i=0;$i<$gridCount;$i++)$xml.='<w:gridCol w:w="'.(int)(9355*(count($weights)===$gridCount&&array_sum($weights)>0?$weights[$i]/array_sum($weights):1/$gridCount)).'"/>';$xml.='</w:tblGrid>';foreach($rows as$row){if($row->parentNode!==$node&&$row->parentNode->parentNode!==$node)continue;$cells=[];foreach($row->childNodes as$cell)if(in_array(strtolower($cell->nodeName),['td','th'],true))$cells[]=$cell;$count=0;foreach($cells as$cell)$count+=max(1,(int)$cell->getAttribute('colspan'));$xml.='<w:tr><w:trPr><w:cantSplit/>'.(strtolower($row->parentNode->nodeName)==='thead'?'<w:tblHeader/>':'').'</w:trPr>';
                $column=0;foreach($cells as$cell){$span=max(1,(int)$cell->getAttribute('colspan'));$cellFormat=$format;$cellFormat['size']=$border==='nil'?28:22;$cellFormat['table']=true;$cellFormat['align']='left';$cellFormat['letterhead']=$border==='nil';$cellFormat['cellWidth']=(int)(9355*(count($weights)===$count&&array_sum($weights)>0?array_sum(array_slice($weights,$column,$span))/array_sum($weights):$span/max(1,$count)));$cellFormat['bold']=strtolower($cell->nodeName)==='th';if(preg_match('/text-align:\s*(left|center|right)/',$cell->getAttribute('style'),$m))$cellFormat['align']=$m[1];$xml.='<w:tc><w:tcPr><w:tcW w:w="'.(int)(9355*(count($weights)===$count&&array_sum($weights)>0?array_sum(array_slice($weights,$column,$span))/array_sum($weights):$span/max(1,$count))).'" w:type="dxa"/>'.($span>1?'<w:gridSpan w:val="'.$span.'"/>':'').'</w:tcPr>'.pr_word_blocks($cell,$cellFormat).'</w:tc>';$column+=$span;}$xml.='</w:tr>';}$xml.='</w:tbl>';continue;
        }
        if($tag==='ul'||$tag==='ol'){$number=0;foreach($node->childNodes as$item)if(strtolower($item->nodeName)==='li'){$number++;$xml.=pr_word_p($item,$format,$tag==='ol'?$number.'. ':'• ');}continue;}
        if($tag==='div'&&$node->getAttribute('class')==='pr-page-break'){$xml.='<w:p><w:r><w:br w:type="page"/></w:r></w:p>';continue;}
        if($tag==='div')$xml.=pr_word_blocks($node,$format);else $xml.=pr_word_p($node,$format);
    }
    if($inline!=='')$xml.='<w:p><w:pPr><w:jc w:val="'.($format['align']??'both').'"/></w:pPr>'.$inline.'</w:p>';return $xml!==''?$xml:'<w:p/>';
}
function pr_zip(array $files): string {
    $body='';$directory='';$count=0;
    foreach($files as$name=>$bytes){$offset=strlen($body);$crc=crc32($bytes);$length=strlen($bytes);$n=strlen($name);$body.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,33,$crc,$length,$length,$n,0).$name.$bytes;$directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,33,$crc,$length,$length,$n,0,0,0,0,0,$offset).$name;$count++;}
    return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($directory),strlen($body),0);
}
function pr_docx(array $report): string {
    $dom=pr_dom(pr_body($report));if(!$dom)throw new RuntimeException('Hosting cần bật extension PHP DOM/XML để tải bản Word. Có thể dùng In / Lưu PDF.');
    $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';$ns='http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $doc=$xml.'<w:document xmlns:w="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.pr_word_blocks($dom->getElementById('pr-root')).'<w:sectPr><w:headerReference w:type="default" r:id="rId2"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="850" w:bottom="1134" w:left="1701" w:header="567" w:footer="567"/><w:titlePg/></w:sectPr></w:body></w:document>';
    $styles=$xml.'<w:styles xmlns:w="'.$ns.'"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="28"/><w:lang w:val="vi-VN"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="288" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults></w:styles>';
    return pr_zip(['[Content_Types].xml'=>$xml.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/></Types>',
    '_rels/.rels'=>$xml.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
    'word/document.xml'=>$doc,'word/styles.xml'=>$styles,'word/header1.xml'=>$xml.'<w:hdr xmlns:w="'.$ns.'"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:sz w:val="26"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText> PAGE </w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p></w:hdr>',
    'word/_rels/document.xml.rels'=>$xml.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/></Relationships>']);
}
