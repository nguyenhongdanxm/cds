<?php
/** GDĐP Excel import: exact CSDL codes, full-class replacement, no writes during preview. */
function gdp_excel_key(string $value): string {return mb_strtolower(preg_replace('/\s+/u',' ',trim($value)),'UTF-8');}
function gdp_excel_catalog(array $roster,array $teachers): array {
    $names=[];foreach(gdp_teacher_names($teachers)as$name)$names[gdp_excel_key($name)][]=$name;
    $rosterNames=[];foreach($roster as$row)if(is_array($row))$rosterNames[gdp_excel_key((string)($row['name']??''))]=($rosterNames[gdp_excel_key((string)($row['name']??''))]??0)+1;
    $codes=[];foreach($roster as$row){if(!is_array($row))continue;$code=trim((string)($row['code']??''));if($code==='')continue;
        $name=trim((string)($row['name']??''));$matches=$names[gdp_excel_key($name)]??[];
        $codes['v:'.$code][]=['code'=>$code,'name'=>$name,'teacher'=>count($matches)===1&&($rosterNames[gdp_excel_key($name)]??0)===1?$matches[0]:''];
    }return $codes;
}
function gdp_excel_context(): array {
    require_once dirname(__DIR__).'/chuyenmon/includes/csdl_teacher_sync.php';
    return ['catalog'=>gdp_excel_catalog(pccm_load_csdl_teachers(),get_teachers()),'classes'=>get_classes(),'subjects'=>array_values(array_filter(array_keys(get_subjects()),'gdp_subject'))];
}
function gdp_excel_context_hash(array $context): string {return hash('sha256',json_encode($context,JSON_UNESCAPED_UNICODE));}
function gdp_excel_xml(string $raw): DOMDocument {
    if(stripos($raw,'<!DOCTYPE')!==false||stripos($raw,'<!ENTITY')!==false)throw new RuntimeException('File Excel chứa XML không hợp lệ.');
    $old=libxml_use_internal_errors(true);$doc=new DOMDocument();try{$ok=$doc->loadXML($raw,LIBXML_NONET|LIBXML_NOBLANKS);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    if(!$ok)throw new RuntimeException('Không đọc được nội dung Excel.');return $doc;
}
function gdp_excel_read(string $path): array {
    if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))throw new RuntimeException('Hosting cần bật PHP ZipArchive và DOM để nhập Excel.');
    if(!is_file($path)||filesize($path)>5*1024*1024)throw new RuntimeException('File Excel tối đa 5 MB.');
    $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('Không mở được file XLSX. Hãy lưu lại dưới dạng Excel .xlsx.');
    try{
        $size=0;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$size+=(int)$stat['size'];if($size>20*1024*1024||$zip->numFiles>500)throw new RuntimeException('File Excel quá lớn sau giải nén.');}
        $read=function(string $name)use($zip){$raw=$zip->getFromName($name);if($raw===false)throw new RuntimeException('File Excel thiếu trang dữ liệu.');return gdp_excel_xml($raw);};
        $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$wb=$read('xl/workbook.xml');$xp=new DOMXPath($wb);$xp->registerNamespace('m',$ns);$sheets=$xp->query('//m:sheets/m:sheet');$sheet=null;
        foreach($sheets as$s)if($s->getAttribute('name')==='Phan cong GDDP'){$sheet=$s;break;}
        $sheet=$sheet??$sheets->item(0);if(!$sheet)throw new RuntimeException('File Excel chưa có trang tính.');
        $rid=$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$rels=$read('xl/_rels/workbook.xml.rels');$rx=new DOMXPath($rels);$rx->registerNamespace('r','http://schemas.openxmlformats.org/package/2006/relationships');$target='';
        foreach($rx->query('//r:Relationship')as$r)if($r->getAttribute('Id')===$rid&&$r->getAttribute('TargetMode')!=='External')$target=$r->getAttribute('Target');
        $target=ltrim($target,'/');if(!str_starts_with($target,'xl/'))$target='xl/'.$target;
        if(str_contains($target,'..')||!preg_match('~^xl/[a-zA-Z0-9_./-]+\.xml$~D',$target))throw new RuntimeException('Đường dẫn trang Excel không hợp lệ.');
        $shared=[];if($zip->locateName('xl/sharedStrings.xml')!==false){$dom=$read('xl/sharedStrings.xml');$sx=new DOMXPath($dom);$sx->registerNamespace('m',$ns);foreach($sx->query('//m:si')as$si){$text='';foreach($sx->query('.//m:t',$si)as$t)$text.=$t->textContent;$shared[]=$text;}}
        $dom=$read($target);$dx=new DOMXPath($dom);$dx->registerNamespace('m',$ns);$rows=[];$count=0;
        foreach($dx->query('//m:sheetData/m:row')as$row){if(++$count>5001)throw new RuntimeException('Tối đa 5.000 dòng phân công mỗi file.');$values=[];
            foreach($dx->query('./m:c',$row)as$cell){if(!preg_match('/^([A-Z]+)[1-9][0-9]*$/D',$cell->getAttribute('r'),$m))continue;$col=$m[1];if(strlen($col)>1||$col>'E')continue;
                if($dx->query('./m:f',$cell)->length)throw new RuntimeException('Dòng '.$row->getAttribute('r').': hãy dán giá trị, không dùng công thức trong dữ liệu nhập.');
                $type=$cell->getAttribute('t');$text='';if($type==='inlineStr'){foreach($dx->query('./m:is//m:t',$cell)as$t)$text.=$t->textContent;}else{$value=$dx->query('./m:v',$cell)->item(0);$text=$value?$value->textContent:'';if($type==='s')$text=$shared[(int)$text]??'';if($type==='e')throw new RuntimeException('Ô '.$cell->getAttribute('r').' có lỗi Excel.');}
                $values[ord($col)-65]=trim($text);
            }if(implode('',$values)!=='')$rows[]=['row'=>(int)$row->getAttribute('r'),'values'=>$values];
        }return $rows;
    }finally{$zip->close();}
}
function gdp_excel_preview(array $rows,array $context,string $subject): array {
    if(!in_array($subject,$context['subjects'],true))throw new RuntimeException('Chọn môn GDĐP trong danh mục.');
    $header=array_shift($rows);$expected=['Mã giáo viên','Họ tên','Lớp','Từ tiết','Đến tiết'];
    if(!$header||array_map('gdp_excel_key',array_map(fn($i)=>(string)($header['values'][$i]??''),range(0,4)))!==array_map('gdp_excel_key',$expected))throw new RuntimeException('Dòng đầu cần đúng 5 cột: '.implode(', ',$expected).'. Hãy dùng mẫu tải từ hệ thống.');
    $configs=[];$errors=[];$detail=[];$classMap=[];foreach($context['classes']as$c)$classMap[gdp_excel_key($c)][]=$c;
    foreach($rows as$row){$v=$row['values'];$code=trim((string)($v[0]??''));$name=trim((string)($v[1]??''));$class=trim((string)($v[2]??''));$prefix='Dòng '.$row['row'].': ';
        $matches=$context['catalog']['v:'.$code]??[];$classes=$classMap[gdp_excel_key($class)]??[];
        if(count($matches)!==1||$matches[0]['teacher']===''){$errors[]=$prefix.'mã giáo viên “'.$code.'” thiếu, trùng hoặc chưa liên kết tên duy nhất với danh mục phân công. Giữ nguyên số 0 đầu mã.';continue;}
        $teacher=$matches[0]['teacher'];if($name!==''&&gdp_excel_key($name)!==gdp_excel_key($matches[0]['name'])){$errors[]=$prefix.'họ tên không khớp mã '.$code.'.';continue;}
        if(count($classes)!==1){$errors[]=$prefix.'lớp “'.$class.'” không có trong danh mục.';continue;}$class=$classes[0];
        $range=['teacher'=>$teacher,'from'=>$v[3]??'','to'=>$v[4]??''];$configs[$class]['subject']=$subject;$configs[$class]['ranges'][]=$range;
        $detail[]=['row'=>$row['row'],'code'=>$code,'teacher'=>$teacher,'class'=>$class,'from'=>$range['from'],'to'=>$range['to']];
    }
    foreach($configs as$class=>&$config){try{$config['ranges']=gdp_validate($config['ranges'],array_column($detail,'teacher'));}catch(RuntimeException $e){$errors[]='Lớp '.$class.': '.$e->getMessage();}}unset($config);
    if(!$rows)$errors[]='File chưa có dòng phân công.';
    return ['configs'=>$configs,'rows'=>$detail,'errors'=>array_slice($errors,0,100),'error_count'=>count($errors)];
}
function gdp_excel_apply(string $dir,string $version,array $configs,string $fingerprint): void {
    $file=gdp_file($dir,$version);$ok=pccm_assignment_write($file,fn()=>cds_json_update($file,function($current)use($configs,$fingerprint){
        if(!hash_equals($fingerprint,hash('sha256',json_encode((array)$current))))throw new RuntimeException('Phân công đã thay đổi sau khi xem trước. Hãy tải file và xem trước lại.');
        foreach($configs as$class=>$config)$current[$class]=$config;return $current;
    },[]));if(!$ok)throw new RuntimeException('Không lưu được phân công. Kiểm tra trạng thái khóa phiên bản.');
}
