<?php
/** Exact, unambiguous code lookup inside the caller's authorized student scope. */
function cds_student_manual_lookup(array $students,string $raw): ?array {
 $code=trim($raw);if($code===''||strlen($code)>100)return null;$matches=[];
 foreach($students as$student){$codes=[trim((string)($student['code']??''))];if(function_exists('student_card_public_code'))$codes[]=trim(student_card_public_code($student));
 foreach($codes as$value)if($value!==''&&strcasecmp($value,$code)===0){$matches[(string)($student['id']??'')]=$student;break;}}
 return count($matches)===1?reset($matches):null;
}
