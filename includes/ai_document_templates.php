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
    return ['quyet_dinh'=>'Quyết định','ke_hoach'=>'Kế hoạch','huong_dan'=>'Hướng dẫn','quy_che'=>'Quy chế','khac'=>'Loại khác'];
}
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
    if(!class_exists('ZipArchive'))return '';
    $zip=new ZipArchive();if($zip->open($path)!==true)return '';
    $parts=[];foreach(['word/header1.xml','word/header2.xml','word/document.xml','word/footer1.xml'] as $name){
        $raw=$zip->getFromName($name);if($raw===false)continue;
        $raw=preg_replace('~</w:p>~','\n',$raw);$raw=preg_replace('~<w:tab[^>]*/>~','\t',$raw);
        $parts[]=html_entity_decode(strip_tags($raw),ENT_QUOTES|ENT_XML1,'UTF-8');
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
    $row=cds_ai_template_find($id);if(!$row)return '';
    $path=cds_ai_template_dir().'/'.basename((string)$row['file']);
    $text=cds_ai_plain_text($path,(string)$row['extension']);
    return "LOẠI MẪU: ".($row['type_label']??'')."\nTÊN MẪU: ".($row['name']??'')."\nNỘI DUNG/THỂ THỨC MẪU:\n".mb_substr($text,0,18000,'UTF-8');
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
function cds_ai_docx_paragraphs(string $content): string {
    $out='';$lines=preg_split('/\R/u',trim($content));$first=true;
    foreach($lines as$line){$line=trim($line);if($line===''){$out.='<w:p/>';continue;}
        $isTitle=$first||preg_match('/^[A-ZÀ-Ỹ0-9\s().,\-–—]{8,}$/u',$line);$first=false;
        $align=$isTitle?'center':'both';$bold=$isTitle?'<w:b/>':'';$size=$isTitle?'28':'26';
        $out.='<w:p><w:pPr><w:jc w:val="'.$align.'"/><w:spacing w:after="120" w:line="360" w:lineRule="auto"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/>'.$bold.'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/></w:rPr><w:t xml:space="preserve">'.cds_ai_xml($line).'</w:t></w:r></w:p>';
    }return$out;
}
function cds_ai_docx_generate(string $content,string $templateId,string $target): array {
    if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))return ['ok'=>false,'message'=>'Hosting cần bật ZipArchive và DOM để xuất Word.'];
    $row=$templateId!==''?cds_ai_template_find($templateId):null;
    if($row&&($row['extension']??'')==='docx'){
        $source=cds_ai_template_dir().'/'.basename((string)$row['file']);
        if(!@copy($source,$target))return ['ok'=>false,'message'=>'Không tạo được bản Word từ mẫu.'];
        $zip=new ZipArchive();if($zip->open($target)!==true)return ['ok'=>false,'message'=>'Không mở được bản Word mẫu.'];
        $raw=$zip->getFromName('word/document.xml');if($raw===false){$zip->close();return ['ok'=>false,'message'=>'Mẫu Word không có nội dung hợp lệ.'];}
        $body=cds_ai_docx_paragraphs($content);
        if(strpos($raw,'{{NOI_DUNG}}')!==false){
            $raw=preg_replace('~<w:p\b[^>]*>.*?\{\{NOI_DUNG\}\}.*?</w:p>~s',$body,$raw,1);
        }else{
            preg_match('~<w:sectPr\b.*?</w:sectPr>~s',$raw,$m);$sect=$m[0]??'';
            $raw=preg_replace('~<w:body>.*?</w:body>~s','<w:body>'.$body.$sect.'</w:body>',$raw,1);
        }
        $zip->addFromString('word/document.xml',$raw);$zip->close();return ['ok'=>true];
    }
    return cds_ai_docx_fresh($content,$target);
}
function cds_ai_docx_fresh(string $content,string $target): array {
    $zip=new ZipArchive();if($zip->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)return ['ok'=>false,'message'=>'Không tạo được tệp Word.'];
    $body=cds_ai_docx_paragraphs($content);$files=[
      '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
      '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
      'word/document.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1701" w:header="708" w:footer="708"/></w:sectPr></w:body></w:document>'
    ];foreach($files as$n=>$v)$zip->addFromString($n,$v);$zip->close();return ['ok'=>true];
}
