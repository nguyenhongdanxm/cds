<?php
/**
 * Kho mẫu văn bản và xuất DOCX cho Trợ lý AI.
 * Tệp được lưu ngoài web root tại cds_private/ai_document_templates.
 */
function cds_ai_template_dir(): string {
    $dir=dirname(BASE_PATH).'/cds_private/ai_document_templates';
    if(!is_dir($dir))@mkdir($dir,0750,true);
    return $dir;
}
function cds_ai_template_index_path(): string { return cds_ai_template_dir().'/index.json'; }
function cds_ai_template_types(): array {
    return [
        'nghi_quyet'=>'Nghị quyết (cá biệt)','quyet_dinh'=>'Quyết định (cá biệt)','chi_thi'=>'Chỉ thị',
        'quy_che'=>'Quy chế','quy_dinh'=>'Quy định','thong_cao'=>'Thông cáo','thong_bao'=>'Thông báo',
        'huong_dan'=>'Hướng dẫn','chuong_trinh'=>'Chương trình','ke_hoach'=>'Kế hoạch','phuong_an'=>'Phương án',
        'de_an'=>'Đề án','du_an'=>'Dự án','bao_cao'=>'Báo cáo','bien_ban'=>'Biên bản','to_trinh'=>'Tờ trình',
        'hop_dong'=>'Hợp đồng','cong_van'=>'Công văn','cong_dien'=>'Công điện','ban_ghi_nho'=>'Bản ghi nhớ',
        'ban_thoa_thuan'=>'Bản thỏa thuận','giay_uy_quyen'=>'Giấy ủy quyền','giay_moi'=>'Giấy mời',
        'giay_gioi_thieu'=>'Giấy giới thiệu','giay_nghi_phep'=>'Giấy nghỉ phép','phieu_gui'=>'Phiếu gửi',
        'phieu_chuyen'=>'Phiếu chuyển','phieu_bao'=>'Phiếu báo','thu_cong'=>'Thư công','khac'=>'Loại khác'
    ];
}
function cds_ai_builtin_templates(): array {
    $rows=[];
    foreach(cds_ai_template_types() as$id=>$label){
        if($id==='khac')continue;
        $rows[]=['id'=>'builtin:'.$id,'name'=>'Mẫu chuẩn '.$label,'type'=>$id,'type_label'=>$label,'builtin'=>true,'original_name'=>'Nghị định 30/2020/NĐ-CP'];
    }
    return $rows;
}
function cds_ai_selectable_templates(): array { return array_merge(cds_ai_builtin_templates(),cds_ai_template_all()); }
function cds_ai_template_all(): array {
    $raw=@file_get_contents(cds_ai_template_index_path());$rows=json_decode((string)$raw,true);
    return is_array($rows)?array_values($rows):[];
}
function cds_ai_template_save_index(array $rows): bool {
    $path=cds_ai_template_index_path();$tmp=$path.'.tmp.'.bin2hex(random_bytes(4));
    $ok=@file_put_contents($tmp,json_encode(array_values($rows),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX)!==false;
    if(!$ok)return false;@chmod($tmp,0640);return @rename($tmp,$path);
}
function cds_ai_template_find(string $id): ?array {
    foreach(cds_ai_template_all() as $row)if(hash_equals((string)($row['id']??''),$id))return $row;
    return null;
}
function cds_ai_docx_text(string $path): string {
    if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))return '';
    $zip=new ZipArchive();if($zip->open($path)!==true)return '';
    $parts=[];$files=[];
    for($i=1;$i<=6;$i++)$files[]='word/header'.$i.'.xml';
    $files[]='word/document.xml';
    for($i=1;$i<=6;$i++)$files[]='word/footer'.$i.'.xml';
    foreach($files as$name){
        $raw=$zip->getFromName($name);if($raw===false)continue;
        $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
        $loaded=$dom->loadXML($raw,LIBXML_NONET|LIBXML_NOBLANKS);
        libxml_clear_errors();libxml_use_internal_errors($previous);if(!$loaded)continue;
        $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach($xp->query('//w:p') as$p){
            $line='';
            foreach($xp->query('.//w:t',$p) as$t)$line.=$t->textContent;
            $line=trim(preg_replace('/[ \t\x{00A0}]+/u',' ',$line));
            if($line===''||preg_match('/^[0-9\s._\/-]{8,}$/u',$line))continue;
            $parts[]=$line;
        }
    }$zip->close();
    return trim(preg_replace("/\n{3,}/","\n\n",implode("\n",$parts)));
}
function cds_ai_plain_text(string $path,string $ext): string {
    if($ext==='docx')return cds_ai_docx_text($path);
    if(in_array($ext,['txt','md'],true))return trim((string)@file_get_contents($path));
    return '';
}
function cds_ai_template_upload(array $file,string $type,string $name,array $user): array {
    $types=cds_ai_template_types();if(!isset($types[$type]))return ['ok'=>false,'message'=>'Loại văn bản không hợp lệ.'];
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return ['ok'=>false,'message'=>'Chưa chọn được tệp mẫu.'];
    if((int)($file['size']??0)>10*1024*1024)return ['ok'=>false,'message'=>'Tệp mẫu vượt quá 10 MB.'];
    $original=trim((string)($file['name']??''));$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    if(!in_array($ext,['docx','txt','md'],true))return ['ok'=>false,'message'=>'Chỉ nhận tệp DOCX, TXT hoặc MD.'];
    if($ext==='docx'&&!class_exists('ZipArchive'))return ['ok'=>false,'message'=>'Hosting chưa bật PHP ZipArchive.'];
    $id=date('YmdHis').'-'.bin2hex(random_bytes(5));$target=cds_ai_template_dir().'/'.$id.'.'.$ext;
    if(!@move_uploaded_file((string)$file['tmp_name'],$target))return ['ok'=>false,'message'=>'Không lưu được tệp mẫu ngoài web root.'];
    @chmod($target,0640);$text=cds_ai_plain_text($target,$ext);
    if($text===''){@unlink($target);return ['ok'=>false,'message'=>'Không đọc được nội dung tệp mẫu.'];}
    $row=['id'=>$id,'name'=>$name!==''?$name:pathinfo($original,PATHINFO_FILENAME),'type'=>$type,'type_label'=>$types[$type],
        'file'=>$id.'.'.$ext,'extension'=>$ext,'original_name'=>$original,'excerpt'=>mb_substr($text,0,240,'UTF-8'),
        'created_at'=>date('c'),'created_by'=>(string)($user['name']??$user['username']??'')];
    $rows=cds_ai_template_all();$rows[]=$row;
    if(!cds_ai_template_save_index($rows)){@unlink($target);return ['ok'=>false,'message'=>'Không cập nhật được danh mục mẫu.'];}
    return ['ok'=>true,'template'=>$row];
}
function cds_ai_template_delete(string $id): bool {
    $rows=cds_ai_template_all();$found=null;$keep=[];
    foreach($rows as$row){if(($row['id']??'')===$id)$found=$row;else$keep[]=$row;}
    if(!$found)return false;
    if(!cds_ai_template_save_index($keep))return false;
    $path=cds_ai_template_dir().'/'.basename((string)$found['file']);if(is_file($path))@unlink($path);
    return true;
}
function cds_ai_template_context(string $id): string {
    if(strpos($id,'builtin:')===0){
        $type=substr($id,8);$types=cds_ai_template_types();if(!isset($types[$type]))return '';
        return "LOẠI MẪU: ".$types[$type]."\nNGUỒN THỂ THỨC: Nghị định 30/2020/NĐ-CP\nYÊU CẦU: Dùng bố cục chuẩn của loại văn bản này; không tự tạo số, ký hiệu, ngày hoặc căn cứ nội dung.";
    }
    $row=cds_ai_template_find($id);if(!$row)return '';
    $path=cds_ai_template_dir().'/'.basename((string)$row['file']);
    $text=cds_ai_plain_text($path,(string)$row['extension']);
    return "LOẠI MẪU: ".($row['type_label']??'')."\nTÊN MẪU: ".($row['name']??'')."\nNỘI DUNG/THỂ THỨC MẪU:\n".mb_substr($text,0,18000,'UTF-8');
}
function cds_ai_template_preview(string $id): array {
    if(strpos($id,'builtin:')===0){
        $type=substr($id,8);$types=cds_ai_template_types();$profile=(array)(cds_ai_settings()['document_profile']??[]);
        return ['header'=>array_values(array_filter([
            (string)($profile['supervising_agency']??''),(string)($profile['issuing_agency']??''),
            'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM','Độc lập - Tự do - Hạnh phúc'
        ])),'type_label'=>(string)($types[$type]??'')];
    }
    $row=cds_ai_template_find($id);if(!$row)return ['header'=>[],'type_label'=>''];
    $path=cds_ai_template_dir().'/'.basename((string)$row['file']);
    $text=cds_ai_plain_text($path,(string)$row['extension']);$lines=preg_split('/\R/u',$text);
    $titles=['quyet_dinh'=>'QUYẾT ĐỊNH','ke_hoach'=>'KẾ HOẠCH','huong_dan'=>'HƯỚNG DẪN','quy_che'=>'QUY CHẾ'];
    $wanted=$titles[(string)($row['type']??'')]??mb_strtoupper((string)($row['type_label']??''),'UTF-8');$header=[];
    foreach($lines as$line){
        $line=trim($line);if($line==='')continue;
        if($wanted!==''&&strpos(mb_strtoupper($line,'UTF-8'),$wanted)!==false)break;
        if(count($header)<12)$header[]=$line;
    }
    return ['header'=>$header,'type_label'=>(string)($row['type_label']??'')];
}
function cds_ai_document_profile_context(): string {
    $p=(array)(cds_ai_settings()['document_profile']??[]);
    $labels=['supervising_agency'=>'Cơ quan chủ quản','issuing_agency'=>'Đơn vị ban hành','abbreviation'=>'Tên viết tắt','location'=>'Địa danh','signer_name'=>'Người ký','signer_title'=>'Chức vụ người ký','document_symbol'=>'Ký hiệu văn bản','academic_year'=>'Năm học'];
    $lines=['THÔNG TIN ĐƠN VỊ DÙNG ĐỂ ĐIỀN VĂN BẢN:'];
    foreach($labels as$key=>$label){$value=trim((string)($p[$key]??''));if($value!=='')$lines[]=$label.': '.$value;}
    $lines[]='Không tự điền thông tin còn trống; phải ghi [CẦN BỔ SUNG].';
    return implode("\n",$lines);
}

function cds_ai_single_reference_upload(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return ['text'=>'','name'=>''];
    if((int)($file['size']??0)>8*1024*1024)return ['text'=>'','name'=>''];
    $name=(string)($file['name']??'');$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    if(!in_array($ext,['docx','txt','md'],true))return ['text'=>'','name'=>''];
    return ['text'=>cds_ai_plain_text((string)$file['tmp_name'],$ext),'name'=>$name];
}
function cds_ai_reference_uploads(array $files): array {
    $texts=[];$names=[];$count=is_array($files['name']??null)?count($files['name']):0;
    for($i=0;$i<$count;$i++){
        if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;
        if((int)($files['size'][$i]??0)>8*1024*1024)continue;
        $name=(string)$files['name'][$i];$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($ext,['docx','txt','md'],true))continue;
        $text=cds_ai_plain_text((string)$files['tmp_name'][$i],$ext);
        if($text!==''){$names[]=$name;$texts[]="--- ".$name." ---\n".$text;}
    }
    return ['text'=>implode("\n\n",$texts),'names'=>$names];
}
function cds_ai_xml(string $s): string { return htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8'); }
function cds_ai_docx_paragraphs(string $content,bool $inheritTemplate=false): string {
    $out='';$lines=preg_split('/\R/u',trim($content));$firstText=true;
    foreach($lines as$line){
        $line=trim($line);if($line===''){$out.='<w:p><w:pPr><w:spacing w:after="0"/></w:pPr></w:p>';continue;}
        $upper=mb_strtoupper($line,'UTF-8');
        $isMain=(bool)preg_match('/^(QUYẾT ĐỊNH|KẾ HOẠCH|HƯỚNG DẪN|QUY CHẾ|THÔNG BÁO|TỜ TRÌNH|BÁO CÁO)(\s*:)?$/u',$upper);
        $isDecision=$upper==='QUYẾT ĐỊNH:';$isSubtitle=preg_match('/^(Về việc|VỀ VIỆC)\b/u',$line);
        $isBasis=preg_match('/^Căn cứ\b/u',$line);$isArticle=preg_match('/^(Điều\s+\d+[a-zA-Z]?\.?)(.*)$/u',$line,$article);
        $isSection=!$isMain&&!$isDecision&&!$isArticle&&(bool)preg_match('/^[A-ZÀ-Ỹ0-9][A-ZÀ-Ỹ0-9\s().,\-–—:]{7,}$/u',$line);
        $center=$isMain||$isDecision||$isSubtitle;$bold=$isMain||$isDecision||$isSubtitle||$isSection;
        $size=($isMain||$isDecision)?'28':'26';$firstIndent=(!$center&&!$isBasis&&!$isArticle&&!$isSection)?'<w:ind w:firstLine="567"/>':'';
        $before=$isMain?'240':'0';$after=($isMain||$isSubtitle||$isDecision)?'120':'100';
        $out.='<w:p><w:pPr><w:jc w:val="'.($center?'center':'both').'"/>'.$firstIndent.'<w:spacing w:before="'.$before.'" w:after="'.$after.'" w:line="276" w:lineRule="auto"/></w:pPr>';
        $font='<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/><w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/>';
        if($isArticle){
            $out.='<w:r><w:rPr>'.$font.'<w:b/></w:rPr><w:t xml:space="preserve">'.cds_ai_xml($article[1]).'</w:t></w:r>';
            $out.='<w:r><w:rPr>'.$font.'</w:rPr><w:t xml:space="preserve">'.cds_ai_xml($article[2]).'</w:t></w:r>';
        }else{
            $style=($bold?'<w:b/>':'').($isBasis?'<w:i/>':'');
            $out.='<w:r><w:rPr>'.$font.$style.'</w:rPr><w:t xml:space="preserve">'.cds_ai_xml($line).'</w:t></w:r>';
        }
        $out.='</w:p>';$firstText=false;
    }return$out;
}
function cds_ai_docx_insert_xml(DOMDocument $dom,DOMNode $parent,?DOMNode $before,string $xml): bool {
    $source=new DOMDocument();$previous=libxml_use_internal_errors(true);
    $ok=$source->loadXML('<?xml version="1.0" encoding="UTF-8"?><w:root xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'.$xml.'</w:root>',LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($previous);if(!$ok)return false;
    foreach(iterator_to_array($source->documentElement->childNodes) as$child){
        $imported=$dom->importNode($child,true);
        $before?$parent->insertBefore($imported,$before):$parent->appendChild($imported);
    }
    return true;
}
function cds_ai_docx_generate(string $content,string $templateId,string $target): array {
    if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))return ['ok'=>false,'message'=>'Hosting cần bật ZipArchive và DOM để xuất Word.'];
    $row=$templateId!==''?cds_ai_template_find($templateId):null;
    if($row&&($row['extension']??'')==='docx'){
        $source=cds_ai_template_dir().'/'.basename((string)$row['file']);
        if(!@copy($source,$target))return ['ok'=>false,'message'=>'Không tạo được bản Word từ mẫu.'];
        $zip=new ZipArchive();if($zip->open($target)!==true)return ['ok'=>false,'message'=>'Không mở được bản Word mẫu.'];
        $raw=$zip->getFromName('word/document.xml');if($raw===false){$zip->close();return ['ok'=>false,'message'=>'Mẫu Word không có nội dung hợp lệ.'];}
        $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
        $loaded=$dom->loadXML($raw,LIBXML_NONET|LIBXML_NOBLANKS);
        libxml_clear_errors();libxml_use_internal_errors($previous);
        if(!$loaded){$zip->close();return ['ok'=>false,'message'=>'Không đọc được cấu trúc tệp Word mẫu.'];}
        $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $bodyNode=$xp->query('//w:body')->item(0);
        if(!$bodyNode){$zip->close();return ['ok'=>false,'message'=>'Mẫu Word không có thân văn bản hợp lệ.'];}
        $contentXml=cds_ai_docx_paragraphs($content,true);$replaceNode=null;
        foreach($xp->query('//w:body//w:p') as$p){
            $text='';foreach($xp->query('.//w:t',$p) as$t)$text.=$t->textContent;
            if(strpos($text,'{{NOI_DUNG}}')!==false){$replaceNode=$p;break;}
        }
        if($replaceNode){
            if(!cds_ai_docx_insert_xml($dom,$replaceNode->parentNode,$replaceNode,$contentXml)){$zip->close();return ['ok'=>false,'message'=>'Không chèn được nội dung vào mẫu Word.'];}
            $replaceNode->parentNode->removeChild($replaceNode);
        }else{
            $titles=['quyet_dinh'=>'QUYẾT ĐỊNH','ke_hoach'=>'KẾ HOẠCH','huong_dan'=>'HƯỚNG DẪN','quy_che'=>'QUY CHẾ'];
            $wanted=$titles[(string)($row['type']??'')]??'';
            $startNode=null;
            foreach(iterator_to_array($bodyNode->childNodes) as$child){
                if($child->nodeType!==XML_ELEMENT_NODE)continue;
                $text='';foreach($xp->query('.//w:t',$child) as$t)$text.=$t->textContent;
                $normalized=mb_strtoupper(trim(preg_replace('/\s+/u',' ',$text)),'UTF-8');
                if($wanted!==''&&strpos($normalized,$wanted)!==false){$startNode=$child;break;}
            }
            if($startNode){
                $remove=false;
                foreach(iterator_to_array($bodyNode->childNodes) as$child){
                    if($child===$startNode)$remove=true;
                    if(!$remove)continue;
                    if($child->nodeType===XML_ELEMENT_NODE&&$child->localName==='sectPr')continue;
                    $bodyNode->removeChild($child);
                }
                $sect=$xp->query('./w:sectPr',$bodyNode)->item(0);
                if(!cds_ai_docx_insert_xml($dom,$bodyNode,$sect,$contentXml)){$zip->close();return ['ok'=>false,'message'=>'Không chèn được nội dung vào mẫu Word.'];}
            }else{
                $sect=$xp->query('./w:sectPr',$bodyNode)->item(0);
                foreach(iterator_to_array($bodyNode->childNodes) as$child)if($child!==$sect)$bodyNode->removeChild($child);
                if(!cds_ai_docx_insert_xml($dom,$bodyNode,$sect,$contentXml)){$zip->close();return ['ok'=>false,'message'=>'Không chèn được nội dung vào mẫu Word.'];}
            }
        }
        $zip->addFromString('word/document.xml',$dom->saveXML());$zip->close();return ['ok'=>true];
    }
    return cds_ai_docx_fresh($content,$target);
}
function cds_ai_docx_fresh(string $content,string $target): array {
    $zip=new ZipArchive();if($zip->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)return ['ok'=>false,'message'=>'Không tạo được tệp Word.'];
    $profile=(array)(cds_ai_settings()['document_profile']??[]);
    $supervising=trim((string)($profile['supervising_agency']??''))?:'[CƠ QUAN CHỦ QUẢN]';
    $issuing=trim((string)($profile['issuing_agency']??''))?:'[ĐƠN VỊ BAN HÀNH]';
    $symbol=trim((string)($profile['document_symbol']??''));
    $location=trim((string)($profile['location']??''))?:'[ĐỊA DANH]';
    $cell=function(string $first,string $second,bool $right=false): string {
        $jc=$right?'center':'center';
        return '<w:tc><w:tcPr><w:tcW w:w="4800" w:type="dxa"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="'.$jc.'"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="24"/><w:b/></w:rPr><w:t>'.cds_ai_xml($first).'</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="24"/><w:b/></w:rPr><w:t>'.cds_ai_xml($second).'</w:t></w:r></w:p></w:tc>';
    };
    $header='<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="4800"/><w:gridCol w:w="4800"/></w:tblGrid><w:tr>'
        .$cell(mb_strtoupper($supervising,'UTF-8'),mb_strtoupper($issuing,'UTF-8'))
        .$cell('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM','Độc lập - Tự do - Hạnh phúc',true).'</w:tr><w:tr>'
        .$cell('Số: '.($symbol!==''?$symbol:'…/…'),'').$cell($location.', ngày … tháng … năm …','',true).'</w:tr></w:tbl>';
    $body=$header.cds_ai_docx_paragraphs($content);$files=[
      '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
      '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
      'word/document.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1701" w:header="708" w:footer="708"/></w:sectPr></w:body></w:document>'
    ];foreach($files as$n=>$v)$zip->addFromString($n,$v);$zip->close();return ['ok'=>true];
}
