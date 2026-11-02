<?php
/** CSDL code is the public staff identifier; id remains the immutable relational key. */
function si_code(array $staff):string{return trim((string)($staff['code']??''));}
function si_code_label(array $staff):string{$code=si_code($staff);return $code!==''?$code:'Chưa khai mã trong CSDL';}
function si_index(array $roster):array{
 $out=['id'=>[],'code'=>[],'name'=>[]];foreach($roster as$s){$id=(string)($s['id']??'');if($id==='')continue;$out['id']['v:'.$id][$id]=true;$code=si_code($s);if($code!=='')$out['code']['v:'.$code][$id]=true;$name=si_name((string)($s['name']??''));if($name!=='')$out['name']['v:'.$name][$id]=true;}return$out;
}
function si_name(string $name):string{$name=preg_replace('/\s+/u',' ',trim($name));return function_exists('mb_strtolower')?mb_strtolower($name,'UTF-8'):strtolower($name);}
function si_resolve(string $value,array $index,string $kind='either'):?string{
 $value=trim($value);if($value==='')return null;$ids=[];foreach($kind==='either'?['id','code']:[$kind]as$type)foreach($index[$type]['v:'.$value]??[]as$id=>$unused)$ids[(string)$id]=true;return count($ids)===1?(string)array_key_first($ids):null;
}
function si_match(array $row,string $staffId,array $index):string{
 $explicit=false;$mode='Mã nội bộ';
 foreach(['teacher_id','person_id','staff_id','teacher_code','person_code','staff_code']as$field){$value=trim((string)($row[$field]??''));if($value==='')continue;$explicit=true;$isCode=str_ends_with($field,'_code');$resolved=si_resolve($value,$index,$isCode?'code':'either');if($resolved===null||$resolved!==$staffId)return'';if($isCode||isset($index['code']['v:'.$value]))$mode='Mã giáo viên CSDL';}
 if($explicit)return$mode;
 $name=si_name((string)($row['teacher']??$row['teacher_name']??$row['person_name']??''));$ids=$index['name']['v:'.$name]??[];return count($ids)===1&&isset($ids[$staffId])?'Tên duy nhất – cần đối chiếu':'';
}
function si_issues(array $roster):array{
 $index=si_index($roster);$issues=[];foreach($roster as$s){$code=si_code($s);if($code==='')$issues[]=(string)($s['name']??'').': chưa khai mã trong CSDL.';elseif(count($index['code']['v:'.$code]??[])>1)$issues[]=(string)($s['name']??'').': mã '.$code.' đang trùng, cần kiểm tra CSDL.';}return$issues;
}
