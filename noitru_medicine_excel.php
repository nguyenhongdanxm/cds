<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_login();
require_module('noitru','view');
require_perm_level('nt.yte','edit');

function nt_medicine_xlsx_col_to_index(string $letters): int {
    $n=0; foreach(str_split(strtoupper($letters)) as $ch) $n=$n*26+(ord($ch)-64); return $n-1;
}
function nt_medicine_xlsx_rows(string $file): array {
    if(!class_exists('ZipArchive')) return ['ok'=>false,'message'=>'Hosting chưa bật ZipArchive để đọc file .xlsx.'];
    $zip=new ZipArchive(); if($zip->open($file)!==true) return ['ok'=>false,'message'=>'Không mở được file Excel.'];
    $shared=[]; $sharedXml=$zip->getFromName('xl/sharedStrings.xml');
    if($sharedXml!==false){$sx=@simplexml_load_string($sharedXml); if($sx){foreach($sx->si as $si){$parts=[]; if(isset($si->t))$parts[]=(string)$si->t; foreach($si->r as $r)$parts[]=(string)$r->t; $shared[]=implode('',$parts);}}}
    $sheet=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
    if($sheet===false) return ['ok'=>false,'message'=>'Không tìm thấy Sheet1 trong file Excel.'];
    $xml=@simplexml_load_string($sheet); if(!$xml) return ['ok'=>false,'message'=>'Sheet1 không hợp lệ.'];
    $rows=[];
    foreach($xml->sheetData->row as $row){$cells=[]; foreach($row->c as $c){$ref=(string)$c['r']; preg_match('/([A-Z]+)(\d+)/',$ref,$m); $idx=isset($m[1])?nt_medicine_xlsx_col_to_index($m[1]):count($cells); $type=(string)$c['t']; $value=''; if($type==='inlineStr'){$value=(string)$c->is->t;} else {$value=(string)$c->v; if($type==='s')$value=$shared[(int)$value]??'';} $cells[$idx]=trim($value);} if($cells){ksort($cells); $max=max(array_keys($cells)); $line=[]; for($i=0;$i<=$max;$i++)$line[$i]=$cells[$i]??''; $rows[(int)$row['r']]=$line;}}
    return ['ok'=>true,'rows'=>$rows];
}
function nt_medicine_norm_header(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');
    $map=['á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','đ'=>'d','é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y'];
    $v=strtr($v,$map); return preg_replace('/[^a-z0-9]+/','',$v)??'';
}
function nt_medicine_excel_date($raw): string {
    $v=trim((string)$raw); if($v==='')return '';
    if(is_numeric($v)){ $ts=((float)$v-25569)*86400; return gmdate('Y-m-d',(int)$ts); }
    foreach(['Y-m-d','d/m/Y','d-m-Y'] as $fmt){$d=DateTime::createFromFormat($fmt,$v); if($d&&$d->format($fmt)===$v)return $d->format('Y-m-d');}
    return '';
}
function nt_medicine_template_xlsx(): void {
    if(!class_exists('ZipArchive')){http_response_code(500);exit('Hosting chưa bật ZipArchive.');}
    $tmp=tempnam(sys_get_temp_dir(),'medxlsx'); $zip=new ZipArchive(); $zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    $headers=['STT','Tên thuốc','Đơn vị','Số lượng','Hạn sử dụng','Ngưỡng sắp hết','Ghi chú'];
    $examples=[['1','Paracetamol 500mg','viên','100','31/12/2027','20','Thuốc hạ sốt'],['2','Oresol','gói','50','30/06/2027','10','Bù nước điện giải']];
    $rows=[]; $all=array_merge([$headers],$examples); foreach($all as $ri=>$r){$cells=''; foreach($r as $ci=>$v){$col='';$n=$ci+1;while($n>0){$n--; $col=chr(65+$n%26).$col; $n=intdiv($n,26);} $cells.='<c r="'.$col.($ri+1).'" t="inlineStr"><is><t>'.htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';}$rows[]='<row r="'.($ri+1).'">'.$cells.'</row>';}
    $sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="14" customWidth="1"/><col min="4" max="4" width="14" customWidth="1"/><col min="5" max="5" width="18" customWidth="1"/><col min="6" max="6" width="20" customWidth="1"/><col min="7" max="7" width="32" customWidth="1"/></cols><sheetData>'.implode('',$rows).'</sheetData></worksheet>';
    $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Kho thuốc" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="mau-nhap-kho-thuoc.xlsx"'); header('Content-Length: '.filesize($tmp)); readfile($tmp); @unlink($tmp); exit;
}

if($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['template']??'')==='1') nt_medicine_template_xlsx();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
$upload=$_FILES['medicine_excel']??null; if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){flash('Chưa chọn file Excel kho thuốc.','danger');header('Location: '.BASE_URL.'noitru.php?tab=health&health_view=inventory');exit;}
if(strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION))!=='xlsx'){flash('Chỉ nhận file .xlsx.','danger');header('Location: '.BASE_URL.'noitru.php?tab=health&health_view=inventory');exit;}
$parsed=nt_medicine_xlsx_rows((string)$upload['tmp_name']); if(empty($parsed['ok'])){flash($parsed['message']??'Không đọc được file Excel.','danger');header('Location: '.BASE_URL.'noitru.php?tab=health&health_view=inventory');exit;}
$aliases=['stt'=>'stt','tenthuoc'=>'name','thuoc'=>'name','donvi'=>'unit','soluong'=>'quantity','hansudung'=>'expiry','hsd'=>'expiry','nguongsaphet'=>'low','nguong'=>'low','ghichu'=>'note'];
$headerRow=0;$cols=[]; foreach($parsed['rows'] as $rn=>$row){$found=[];foreach($row as $ci=>$v){$k=nt_medicine_norm_header((string)$v);if(isset($aliases[$k]))$found[$aliases[$k]]=$ci;} if(isset($found['name'],$found['unit'],$found['quantity'])){$headerRow=$rn;$cols=$found;break;}}
if(!$headerRow){flash('Sai cấu trúc file. Bắt buộc có các cột: Tên thuốc, Đơn vị, Số lượng. Nên dùng file mẫu của hệ thống.','danger');header('Location: '.BASE_URL.'noitru.php?tab=health&health_view=inventory');exit;}
$allowedUnits=['viên','gói','lọ','ống','chai','hộp','vỉ','tuýp','cuộn']; $items=[];$errors=[];$seen=[];
foreach($parsed['rows'] as $rn=>$row){if($rn<=$headerRow)continue;$name=trim((string)($row[$cols['name']]??''));$unit=trim((string)($row[$cols['unit']]??''));$qtyRaw=trim((string)($row[$cols['quantity']]??'')); if($name===''&&$unit===''&&$qtyRaw==='')continue; $rowErrors=[];if($name==='')$rowErrors[]='thiếu tên thuốc';if($unit==='')$rowErrors[]='thiếu đơn vị';if(!is_numeric(str_replace(',','.',$qtyRaw))||(float)str_replace(',','.',$qtyRaw)<0)$rowErrors[]='số lượng không hợp lệ';$qty=(int)round((float)str_replace(',','.',$qtyRaw));$expiryRaw=trim((string)($row[$cols['expiry']??-1]??''));$expiry=nt_medicine_excel_date($expiryRaw);if($expiryRaw!==''&&$expiry==='')$rowErrors[]='hạn sử dụng không hợp lệ';$lowRaw=trim((string)($row[$cols['low']??-1]??'10'));if($lowRaw!==''&&!is_numeric($lowRaw))$rowErrors[]='ngưỡng sắp hết không hợp lệ';$low=max(0,(int)($lowRaw===''?10:$lowRaw));if($rowErrors){$errors[]='Dòng '.$rn.': '.implode(', ',$rowErrors);continue;}$key=mb_strtolower($name,'UTF-8').'|'.mb_strtolower($unit,'UTF-8');if(isset($seen[$key])){$errors[]='Dòng '.$rn.': trùng thuốc và đơn vị với dòng '.$seen[$key];continue;}$seen[$key]=$rn;$items[]=['name'=>$name,'unit'=>$unit,'quantity'=>$qty,'expiry_date'=>$expiry,'low_stock'=>$low,'note'=>trim((string)($row[$cols['note']??-1]??''))];}
if($errors){flash('Không nhập dữ liệu vì file có '.count($errors).' lỗi. '.implode(' | ',array_slice($errors,0,6)).(count($errors)>6?' …':''),'danger');header('Location: '.BASE_URL.'noitru.php?tab=health&health_view=inventory');exit;}
if(!$items){flash('File Excel không có dòng thuốc hợp lệ.','warning');header('Location: '.BASE_URL.'noitru.php?tab=health&health_view=inventory');exit;}
$existing=noitru_medicines_all();$map=[];foreach($existing as $m)$map[mb_strtolower(trim((string)($m['name']??'')),'UTF-8').'|'.mb_strtolower(trim((string)($m['unit']??'')),'UTF-8')]=$m;
$added=0;$updated=0;$restocked=0;$user=current_user()??[];
foreach($items as $item){$key=mb_strtolower($item['name'],'UTF-8').'|'.mb_strtolower($item['unit'],'UTF-8');if(isset($map[$key])){$m=$map[$key];$id=(string)$m['id'];noitru_medicine_save(['id'=>$id,'name'=>$item['name'],'unit'=>$item['unit'],'expiry_date'=>$item['expiry_date'],'low_stock'=>$item['low_stock'],'note'=>$item['note'],'quantity'=>(int)($m['quantity']??0)]);$updated++;if($item['quantity']>0){noitru_medicine_adjust($id,$item['quantity'],'restock','Nhập kho bằng Excel',$user['name']??'');$restocked++;}}else{$id=noitru_medicine_save(['id'=>'','name'=>$item['name'],'unit'=>$item['unit'],'expiry_date'=>$item['expiry_date'],'low_stock'=>$item['low_stock'],'note'=>$item['note'],'quantity'=>0]);if($item['quantity']>0)noitru_medicine_adjust($id,$item['quantity'],'initial','Nhập kho bằng Excel',$user['name']??'');$added++;}}
flash('Đã nhập Excel kho thuốc: thêm mới '.$added.' thuốc, cập nhật '.$updated.' thuốc, bổ sung tồn kho '.$restocked.' thuốc.','success');
header('Location: '.BASE_URL.'noitru.php?tab=health&health_view=inventory');exit;
