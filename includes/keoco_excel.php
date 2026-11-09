<?php
/** Bounded XLSX reader for six-column question imports; preview only. */
function kc_excel_xml(string $raw): DOMDocument {
    if(stripos($raw,'<!DOCTYPE')!==false||stripos($raw,'<!ENTITY')!==false)throw new RuntimeException('File Excel chứa XML không hợp lệ.');
    $old=libxml_use_internal_errors(true);$doc=new DOMDocument();try{$ok=$doc->loadXML($raw,LIBXML_NONET|LIBXML_NOBLANKS);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    if(!$ok)throw new RuntimeException('Không đọc được nội dung Excel.');return $doc;
}
function kc_excel_read(string $path): array {
    if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))throw new RuntimeException('Hosting cần bật PHP ZipArchive và DOM để nhập Excel.');
    if(!is_file($path)||filesize($path)>5*1024*1024)throw new RuntimeException('File Excel tối đa 5 MB.');
    $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('Không mở được file XLSX. Hãy lưu lại dưới dạng Excel .xlsx.');
    try{
        $size=0;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$size+=(int)$stat['size'];if($size>20*1024*1024||$zip->numFiles>500)throw new RuntimeException('File Excel quá lớn sau giải nén.');}
        $read=function(string $name)use($zip){$raw=$zip->getFromName($name);if($raw===false)throw new RuntimeException('File Excel thiếu trang dữ liệu.');return kc_excel_xml($raw);};
        $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$wb=$read('xl/workbook.xml');$xp=new DOMXPath($wb);$xp->registerNamespace('m',$ns);$sheets=$xp->query('//m:sheets/m:sheet');$sheet=null;
        $sheet=$sheet??$sheets->item(0);if(!$sheet)throw new RuntimeException('File Excel chưa có trang tính.');
        $rid=$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$rels=$read('xl/_rels/workbook.xml.rels');$rx=new DOMXPath($rels);$rx->registerNamespace('r','http://schemas.openxmlformats.org/package/2006/relationships');$target='';
        foreach($rx->query('//r:Relationship')as$r)if($r->getAttribute('Id')===$rid&&$r->getAttribute('TargetMode')!=='External')$target=$r->getAttribute('Target');
        $target=ltrim($target,'/');if(!str_starts_with($target,'xl/'))$target='xl/'.$target;
        if(str_contains($target,'..')||!preg_match('~^xl/[a-zA-Z0-9_./-]+\.xml$~D',$target))throw new RuntimeException('Đường dẫn trang Excel không hợp lệ.');
        $shared=[];if($zip->locateName('xl/sharedStrings.xml')!==false){$dom=$read('xl/sharedStrings.xml');$sx=new DOMXPath($dom);$sx->registerNamespace('m',$ns);foreach($sx->query('//m:si')as$si){$text='';foreach($sx->query('.//m:t',$si)as$t)$text.=$t->textContent;$shared[]=$text;}}
        $dom=$read($target);$dx=new DOMXPath($dom);$dx->registerNamespace('m',$ns);$rows=[];$count=0;
        foreach($dx->query('//m:sheetData/m:row')as$row){if(++$count>501)throw new RuntimeException('Tối đa 500 câu hỏi mỗi file.');$values=[];
            foreach($dx->query('./m:c',$row)as$cell){if(!preg_match('/^([A-Z]+)[1-9][0-9]*$/D',$cell->getAttribute('r'),$m))continue;$col=$m[1];if(strlen($col)>1||$col>'F')continue;
                if($dx->query('./m:f',$cell)->length)throw new RuntimeException('Dòng '.$row->getAttribute('r').': hãy dán giá trị, không dùng công thức trong dữ liệu nhập.');
                $type=$cell->getAttribute('t');$text='';if($type==='inlineStr'){foreach($dx->query('./m:is//m:t',$cell)as$t)$text.=$t->textContent;}else{$value=$dx->query('./m:v',$cell)->item(0);$text=$value?$value->textContent:'';if($type==='s')$text=$shared[(int)$text]??'';if($type==='e')throw new RuntimeException('Ô '.$cell->getAttribute('r').' có lỗi Excel.');}
                $values[ord($col)-65]=trim($text);
            }if(implode('',$values)!=='')$rows[]=['row'=>(int)$row->getAttribute('r'),'values'=>$values];
        }return $rows;
    }finally{$zip->close();}
}

function kc_excel_questions(string $path): array {
 $rows=kc_excel_read($path);$header=array_shift($rows);
 $expected=['Câu hỏi','A','B','C','D','Đáp án'];
 if(!$header||array_map(fn($i)=>trim((string)($header['values'][$i]??'')),range(0,5))!==$expected)throw new InvalidArgumentException('Dòng đầu cần 6 cột: Câu hỏi, A, B, C, D, Đáp án. Hãy tải file mẫu.');
 $questions=[];foreach($rows as$row){$v=$row['values'];$a=strtoupper(trim((string)($v[5]??'')));$answer=in_array($a,['A','B','C','D'],true)?ord($a)-64:filter_var($a,FILTER_VALIDATE_INT);
 try{$questions[]=kc_questions([['question_text'=>$v[0]??'','option_a'=>$v[1]??'','option_b'=>$v[2]??'','option_c'=>$v[3]??'','option_d'=>$v[4]??'','correct_option'=>$answer]])[0];}catch(InvalidArgumentException $e){throw new InvalidArgumentException('Dòng '.$row['row'].': '.$e->getMessage());}}
 if(!$questions)throw new InvalidArgumentException('File chưa có câu hỏi.');return $questions;
}
