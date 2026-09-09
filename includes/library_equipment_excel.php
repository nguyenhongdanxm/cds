<?php

/**
 * Đọc và nhập danh mục thiết bị từ XLSX.
 * Dùng chung bộ đọc ô XLSX nhẹ của danh mục sách để phù hợp hosting cPanel.
 */
function lib_equipment_condition(string $value): string {
    $value=lib_excel_norm_header($value);
    if(in_array($value,['hong','hỏng','hu hong','hư hỏng'],true))return 'Hỏng';
    if(in_array($value,['xuong cap','xuống cấp','can bao duong','cần bảo dưỡng','dung duoc','dùng được'],true))return 'Xuống cấp';
    return 'Tốt';
}

function lib_equipment_status_quantities(array $row): array {
    $total=max(0,(int)($row['quantity']??0));
    if(array_key_exists('degraded_quantity',$row)||array_key_exists('broken_quantity',$row)){
        $good=max(0,(int)($row['good_quantity']??0));$degraded=max(0,(int)($row['degraded_quantity']??0));$broken=max(0,(int)($row['broken_quantity']??0));
        return ['good'=>$good,'degraded'=>$degraded,'broken'=>$broken,'total'=>$good+$degraded+$broken];
    }
    $condition=lib_equipment_condition((string)($row['condition']??'Tốt'));
    return ['good'=>$condition==='Tốt'?$total:0,'degraded'=>$condition==='Xuống cấp'?$total:0,'broken'=>$condition==='Hỏng'?$total:0,'total'=>$total];
}

function lib_equipment_excel_parse(string $path): array {
    if(!class_exists('ZipArchive'))return ['ok'=>false,'message'=>'Hosting chưa bật PHP ZipArchive nên chưa đọc được file XLSX.'];
    if(!class_exists('DOMDocument'))return ['ok'=>false,'message'=>'Hosting chưa bật PHP DOM nên chưa đọc được file XLSX.'];
    $zip=new ZipArchive();
    if($zip->open($path)!==true)return ['ok'=>false,'message'=>'Không mở được file Excel.'];
    $workbookRaw=$zip->getFromName('xl/workbook.xml');$relsRaw=$zip->getFromName('xl/_rels/workbook.xml.rels');
    if($workbookRaw===false||$relsRaw===false){$zip->close();return ['ok'=>false,'message'=>'File không đúng định dạng XLSX.'];}
    $shared=[];$sharedRaw=$zip->getFromName('xl/sharedStrings.xml');
    if($sharedRaw!==false&&($dom=lib_excel_dom($sharedRaw))){$xp=new DOMXPath($dom);$xp->registerNamespace('main','http://schemas.openxmlformats.org/spreadsheetml/2006/main');foreach($xp->query('//main:si') as $si){$s='';foreach($xp->query('.//main:t',$si) as $part)$s.=$part->textContent;$shared[]=$s;}}
    $rels=[];$relsDom=lib_excel_dom($relsRaw);$relsXp=new DOMXPath($relsDom);$relsXp->registerNamespace('pkg','http://schemas.openxmlformats.org/package/2006/relationships');foreach($relsXp->query('//pkg:Relationship') as $rel)$rels[$rel->getAttribute('Id')]=$rel->getAttribute('Target');
    $wb=lib_excel_dom($workbookRaw);$wbXp=new DOMXPath($wb);$wbXp->registerNamespace('main','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$sheet=$wbXp->query('//main:sheets/main:sheet')->item(0);
    if(!$sheet){$zip->close();return ['ok'=>false,'message'=>'File Excel không có trang dữ liệu.'];}
    $rid=$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$target=ltrim(str_replace('\\','/',$rels[$rid]??''),'/');$sheetPath=str_starts_with($target,'xl/')?$target:'xl/'.$target;$raw=$zip->getFromName($sheetPath);$zip->close();
    if($raw===false||!($dom=lib_excel_dom($raw)))return ['ok'=>false,'message'=>'Không đọc được trang dữ liệu đầu tiên.'];
    $xp=new DOMXPath($dom);$xp->registerNamespace('main','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$rows=[];
    foreach($xp->query('//main:sheetData/main:row') as $row){$number=(int)$row->getAttribute('r');$values=[];foreach($xp->query('./main:c',$row) as $cell){if(!preg_match('/^([A-Z]+)/',$cell->getAttribute('r'),$m))continue;$col=0;foreach(str_split($m[1]) as $char)$col=$col*26+ord($char)-64;$values[$col]=lib_excel_cell_value($cell,$xp,$shared);}$rows[$number]=$values;}
    if(!$rows)return ['ok'=>false,'message'=>'File Excel chưa có dữ liệu.'];
    $aliases=[
        'grade'=>['khoi lop','khối lớp','khoi','khối','lop','lớp'],
        'subject'=>['mon hoc','môn học','mon','môn'],
        'name'=>['ten thiet bi','tên thiết bị'],
        'code'=>['ma so','mã số','ma thiet bi','mã thiết bị'],
        'quantity'=>['so luong','số lượng','sl'],
        'unit'=>['don vi tinh','đơn vị tính','dvt','đvt'],
        'production_year'=>['nam san xuat','năm sản xuất','nam sx','năm sx'],
        'provider'=>['don vi cung cap','đơn vị cung cấp','nha cung cap','nhà cung cấp'],
        'condition'=>['tinh trang','tình trạng'],'good_quantity'=>['tot','tốt','so luong tot','số lượng tốt'],'degraded_quantity'=>['xuong cap','xuống cấp','so luong xuong cap','số lượng xuống cấp'],'broken_quantity'=>['hong','hỏng','so luong hong','số lượng hỏng'],
        'note'=>['ghi chu','ghi chú']
    ];
    $headerRow=null;$headerMap=[];
    foreach($rows as $candidateRow=>$candidateValues){$candidateMap=[];foreach($candidateValues as $col=>$header){$norm=lib_excel_norm_header($header);foreach($aliases as $key=>$names)if(in_array($norm,$names,true)){$candidateMap[$key]=$col;break;}}if(isset($candidateMap['name'])){$headerRow=$candidateRow;$headerMap=$candidateMap;break;}}
    if($headerRow===null)return ['ok'=>false,'message'=>'Không tìm thấy cột “Tên thiết bị”. Hãy tải và dùng đúng file mẫu.'];
    $items=[];$errors=[];
    foreach($rows as $number=>$values){if($number===$headerRow)continue;$get=fn($key)=>trim((string)($values[$headerMap[$key]??0]??''));$name=$get('name');$code=preg_replace('/\.0$/','',$get('code'));if($name===''&&$code==='')continue;if($name===''){$errors[]='Dòng '.$number.': thiếu Tên thiết bị.';continue;}$quantity=max(0,(int)$get('quantity'));$good=max(0,(int)$get('good_quantity'));$degraded=max(0,(int)$get('degraded_quantity'));$broken=max(0,(int)$get('broken_quantity'));$hasSplit=isset($headerMap['good_quantity'])||isset($headerMap['degraded_quantity'])||isset($headerMap['broken_quantity']);if($hasSplit){$statusTotal=$good+$degraded+$broken;if($statusTotal<1){$errors[]='Dòng '.$number.': tổng Tốt, Xuống cấp, Hỏng phải lớn hơn 0.';continue;}if($quantity>0&&$quantity!==$statusTotal){$errors[]='Dòng '.$number.': Số lượng không bằng tổng ba tình trạng.';continue;}$quantity=$statusTotal;}else{if($quantity<1){$errors[]='Dòng '.$number.': Số lượng phải lớn hơn 0.';continue;}$condition=lib_equipment_condition($get('condition'));$good=$condition==='Tốt'?$quantity:0;$degraded=$condition==='Xuống cấp'?$quantity:0;$broken=$condition==='Hỏng'?$quantity:0;}$items[]=['row'=>$number,'grade'=>$get('grade'),'subject'=>$get('subject'),'name'=>$name,'code'=>$code,'quantity'=>$quantity,'unit'=>$get('unit')?:'Cái','production_year'=>preg_replace('/\.0$/','',$get('production_year')),'provider'=>$get('provider'),'good_quantity'=>$good,'degraded_quantity'=>$degraded,'broken_quantity'=>$broken,'condition'=>$broken>0?'Hỏng':($degraded>0?'Xuống cấp':'Tốt'),'note'=>$get('note')];}
    return ['ok'=>true,'items'=>$items,'errors'=>$errors];
}

function lib_equipment_excel_import(array $parsed,array &$equipment,string $importedBy): array {
    $byCode=[];$byIdentity=[];
    foreach($equipment as $i=>$row){$code=lib_excel_norm_header($row['code']??'');$identity=lib_excel_norm_header(implode('|',[$row['grade']??$row['audience']??'',$row['subject']??$row['category']??'',$row['name']??'']));if($code!=='')$byCode[$code]=$i;if($identity!=='')$byIdentity[$identity]=$i;}
    $added=0;$updated=0;$now=date('c');
    foreach($parsed['items']??[] as $item){$sourceRow=$item['row']??null;unset($item['row']);$code=lib_excel_norm_header($item['code']??'');$identity=lib_excel_norm_header(implode('|',[$item['grade']??'',$item['subject']??'',$item['name']??'']));$index=$code!==''?($byCode[$code]??null):null;if($index===null)$index=$byIdentity[$identity]??null;$quantity=(int)$item['quantity'];$item['audience']=$item['grade'];$item['category']=$item['subject'];$item['source']=$item['provider'];$item['updated_at']=$now;
        if($index!==null){$old=$equipment[$index];$oldStatus=lib_equipment_status_quantities($old);$history=is_array($old['import_history']??null)?$old['import_history']:[];$history[]=['date'=>date('Y-m-d'),'quantity'=>$quantity,'good_quantity'=>$item['good_quantity'],'degraded_quantity'=>$item['degraded_quantity'],'broken_quantity'=>$item['broken_quantity'],'method'=>'excel','by'=>$importedBy,'source_row'=>$sourceRow];$item['id']=$old['id'];$item['created_at']=$old['created_at']??$now;$item['good_quantity']=$oldStatus['good']+(int)$item['good_quantity'];$item['degraded_quantity']=$oldStatus['degraded']+(int)$item['degraded_quantity'];$item['broken_quantity']=$oldStatus['broken']+(int)$item['broken_quantity'];$item['quantity']=$item['good_quantity']+$item['degraded_quantity']+$item['broken_quantity'];$item['condition']=$item['broken_quantity']>0?'Hỏng':($item['degraded_quantity']>0?'Xuống cấp':'Tốt');$item['import_history']=$history;foreach($item as $key=>$value)if($value===''&&isset($old[$key]))$item[$key]=$old[$key];$equipment[$index]=array_merge($old,$item);$updated++;
        }else{$item['id']='eq_'.bin2hex(random_bytes(7));$item['created_at']=$now;$item['import_date']=date('Y-m-d');$item['location']='';$item['initial_import']=['date'=>date('Y-m-d'),'quantity'=>$quantity,'method'=>'excel','by'=>$importedBy];$item['import_history']=[];$equipment[]=$item;$new=count($equipment)-1;if($code!=='')$byCode[$code]=$new;$byIdentity[$identity]=$new;$added++;}}
    return ['added'=>$added,'updated'=>$updated,'skipped'=>count($parsed['errors']??[]),'errors'=>$parsed['errors']??[]];
}
