<?php
/** Xem nhanh Word/Excel/PowerPoint ngay trong CDS, không gửi tệp nội bộ sang dịch vụ bên ngoài. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/vanban_store.php';
require_login();

function vp_fail(string $message, int $status=422): never {
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><style>body{font:16px system-ui;background:#f5f7fb;color:#344054;display:grid;place-items:center;min-height:80vh;margin:0}.box{text-align:center;max-width:650px;padding:2rem}h2{color:#172033}</style><div class="box"><h2>Chưa xem trước được tệp</h2><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><p>Thầy/cô vẫn có thể dùng nút <b>Tải về</b> phía trên.</p></div>';
    exit;
}
function vp_text(string $value): string { return htmlspecialchars(trim(preg_replace('/\s+/u',' ',$value)),ENT_QUOTES,'UTF-8'); }
function vp_xml(ZipArchive $zip, string $name): ?DOMDocument {
    $raw=$zip->getFromName($name); if($raw===false)return null;
    $dom=new DOMDocument(); libxml_use_internal_errors(true); $ok=$dom->loadXML($raw,LIBXML_NONET|LIBXML_COMPACT); libxml_clear_errors();
    return $ok?$dom:null;
}
function vp_docx(ZipArchive $zip): string {
    $dom=vp_xml($zip,'word/document.xml'); if(!$dom)return '';
    $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$out='';
    foreach($xp->query('/w:document/w:body/*') as $node){
        if($node->localName==='p'){$text='';foreach($xp->query('.//w:t',$node) as $t)$text.=$t->textContent;$out.='<p>'.vp_text($text).'</p>';}
        elseif($node->localName==='tbl'){$out.='<table>';foreach($xp->query('./w:tr',$node) as $tr){$out.='<tr>';foreach($xp->query('./w:tc',$tr) as $tc){$text='';foreach($xp->query('.//w:t',$tc) as $t)$text.=$t->textContent;$out.='<td>'.vp_text($text).'</td>';}$out.='</tr>';}$out.='</table>';}
    } return $out;
}
function vp_xlsx(ZipArchive $zip): string {
    $shared=[];$sharedDom=vp_xml($zip,'xl/sharedStrings.xml');if($sharedDom){$xp=new DOMXPath($sharedDom);foreach($xp->query('//*[local-name()="si"]') as $si){$s='';foreach($xp->query('.//*[local-name()="t"]',$si) as $t)$s.=$t->textContent;$shared[]=$s;}}
    $sheets=[];for($i=1;$i<=20;$i++)if($zip->locateName('xl/worksheets/sheet'.$i.'.xml')!==false)$sheets[]=$i;
    $out='';foreach($sheets as $sheetNo){$dom=vp_xml($zip,'xl/worksheets/sheet'.$sheetNo.'.xml');if(!$dom)continue;$xp=new DOMXPath($dom);$out.='<h2>Sheet '.$sheetNo.'</h2><table>';foreach($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row){$out.='<tr>';foreach($xp->query('./*[local-name()="c"]',$row) as $cell){$type=$cell->attributes?->getNamedItem('t')?->nodeValue??'';$v=$xp->query('./*[local-name()="v"]',$cell)->item(0)?->textContent??'';if($type==='s')$v=$shared[(int)$v]??$v;$out.='<td>'.vp_text($v).'</td>';}$out.='</tr>';}$out.='</table>';}
    return $out;
}
function vp_pptx(ZipArchive $zip): string {
    $slides=[];for($i=1;$i<=100;$i++)if($zip->locateName('ppt/slides/slide'.$i.'.xml')!==false)$slides[]=$i;$out='';
    foreach($slides as $slideNo){$dom=vp_xml($zip,'ppt/slides/slide'.$slideNo.'.xml');if(!$dom)continue;$xp=new DOMXPath($dom);$out.='<section class="slide"><h2>Trang '.$slideNo.'</h2>';foreach($xp->query('//*[local-name()="p"]') as $p){$text='';foreach($xp->query('.//*[local-name()="t"]',$p) as $t)$text.=$t->textContent;if(trim($text)!=='')$out.='<p>'.vp_text($text).'</p>';}$out.='</section>';}
    return $out;
}

$id=trim((string)($_GET['id']??''));$index=max(0,(int)($_GET['file']??0));$document=null;
foreach(vb_rows(VANBAN_DOCUMENTS_FILE) as $row)if((string)($row['id']??'')===$id){$document=$row;break;}
if(!$document)vp_fail('Không tìm thấy văn bản.',404);$files=vb_document_attachments($document);if(!isset($files[$index]))vp_fail('Văn bản chưa có tệp.',404);
$path=(string)$files[$index]['path'];$name=(string)$files[$index]['name'];$ext=strtolower((string)pathinfo($name,PATHINFO_EXTENSION));
if(str_starts_with($path,'gdrive:')){$download=cds_drive_download(substr($path,7));if(empty($download['ok']))vp_fail('Không đọc được tệp từ Google Drive.',503);$bytes=(string)$download['body'];}
else{$absolute=realpath(BASE_PATH.'/'.ltrim($path,'/'));$root=realpath(DATA_PATH);if(!$absolute||!$root||!str_starts_with($absolute,$root.DIRECTORY_SEPARATOR)||!is_file($absolute))vp_fail('Không tìm thấy tệp trên máy chủ.',404);$bytes=(string)file_get_contents($absolute);}
if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))vp_fail('Máy chủ chưa bật thư viện ZIP/XML để đọc nhanh tệp Office.');
$tmp=tempnam(sys_get_temp_dir(),'cds-vp-');if($tmp===false||file_put_contents($tmp,$bytes)===false)vp_fail('Không tạo được bản xem trước tạm thời.');$zip=new ZipArchive();if($zip->open($tmp)!==true){@unlink($tmp);vp_fail('Định dạng tệp Office cũ hoặc tệp không hợp lệ.');}
$content=match($ext){'docx'=>vp_docx($zip),'xlsx'=>vp_xlsx($zip),'pptx'=>vp_pptx($zip),default=>''};$zip->close();@unlink($tmp);if($content==='')vp_fail('Tệp này dùng định dạng Office cũ hoặc không có nội dung có thể đọc nhanh.');
header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: private, max-age=300');
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font:15px/1.55 Arial,sans-serif;color:#20242d;background:#eef1f6;margin:0;padding:24px}.paper{max-width:1000px;margin:auto;background:#fff;padding:38px 44px;box-shadow:0 4px 18px #18203620}p{min-height:1em;margin:.35em 0}table{border-collapse:collapse;width:100%;margin:1em 0}td{border:1px solid #aeb5c0;padding:6px 8px;vertical-align:top}.slide{border:1px solid #d8dce5;border-radius:10px;padding:20px;margin:0 0 18px}h2{font-size:18px}@media(max-width:700px){body{padding:8px}.paper{padding:18px}}</style></head><body><main class="paper"><?=$content?></main></body></html>
