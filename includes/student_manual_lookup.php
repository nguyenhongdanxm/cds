<?php
/** Match an exact code within the caller's authorized student scope. */
function cds_student_code_key(string $value): string {
 $value=str_replace(["\xC2\xA0","\xE2\x80\x8B","\xEF\xBB\xBF"],'',trim($value));
 $value=str_replace(['–','—','‑','−','﹣','－'],'-',$value);
 $value=preg_replace('/\s+/u','',$value)??$value;
 return strtoupper($value);
}
function cds_student_manual_lookup(array $students,string $raw): ?array {
 if(strlen($raw)>300)return null;$key=cds_student_code_key($raw);if($key==='')return null;$matches=[];
 foreach($students as$student){
  $codes=[(string)($student['id']??''),(string)($student['code']??'')];
  if(function_exists('student_card_public_code')){
   $public=student_card_public_code($student);$codes[]=$public;
   // Cards without a CSDL code print XM- plus a suffix derived from the ID.
   if(trim((string)($student['code']??''))===''&&str_starts_with($public,'XM-'))$codes[]=substr($public,3);
  }
  foreach($codes as$value){$candidate=cds_student_code_key($value);if($candidate!==''&&$candidate===$key){$matches[(string)($student['id']??'')]=$student;break;}}
 }
 return count($matches)===1?reset($matches):null;
}
