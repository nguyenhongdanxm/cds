<?php
function lib_eq_norm(string $value): string {$value=trim(preg_replace('/\s+/u',' ',$value)??$value);return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);}
function lib_eq_year(array $row): string {
    $year=trim((string)($row['school_year']??''));if(preg_match('/(\d{4})\s*[-–\/]\s*(\d{4})/u',$year,$m))return $m[1].' - '.$m[2];
    if($year!=='')return $year;$date=substr((string)($row['borrowed_at']??''),0,10);
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m)&&checkdate((int)$m[2],(int)$m[3],(int)$m[1])){$start=(int)$m[1]-((int)$m[2]<8?1:0);return $start.' - '.($start+1);}
    return 'Chưa xác định năm học';
}
function lib_eq_ledger_id(array $row): string {return 'eqbook_'.substr(hash('sha256',json_encode([lib_eq_norm((string)($row['borrower']??'')),lib_eq_norm((string)($row['subject']??'')),lib_eq_norm(lib_eq_year($row))],JSON_UNESCAPED_UNICODE)),0,32);}
function lib_eq_ledgers(array $rows): array {
    $books=[];foreach($rows as$row){if(!is_array($row))continue;$id=lib_eq_ledger_id($row);if(!isset($books[$id]))$books[$id]=['id'=>$id,'borrower'=>trim((string)($row['borrower']??'')),'subject'=>trim((string)($row['subject']??'')),'school_year'=>lib_eq_year($row),'rows'=>[],'open'=>0];$books[$id]['rows'][]=$row;if(empty($row['returned_at']))$books[$id]['open']++;}
    foreach($books as&$book)usort($book['rows'],static fn($a,$b)=>strcmp((string)($a['borrowed_at']??''),(string)($b['borrowed_at']??''))?:strcmp((string)($a['created_at']??''),(string)($b['created_at']??'')));unset($book);
    uasort($books,static fn($a,$b)=>strcmp($b['school_year'],$a['school_year'])?:strnatcmp(lib_eq_norm($a['borrower']),lib_eq_norm($b['borrower']))?:strnatcmp(lib_eq_norm($a['subject']),lib_eq_norm($b['subject'])));return $books;
}
function lib_eq_batch_ledger(array $rows,string $batch): string {
    if($batch==='')return '';$ids=[];foreach($rows as$row)if(is_array($row)&&($row['batch_id']??'')===$batch)$ids[lib_eq_ledger_id($row)]=true;
    return count($ids)===1?(string)array_key_first($ids):'';
}
