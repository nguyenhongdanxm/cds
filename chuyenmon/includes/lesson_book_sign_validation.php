<?php
function lb_sign_required_fields(array $row): array {
 $missing=[];if(!in_array(trim((string)($row['status']??'')),['taught','substitute','makeup','online','holiday','teacher_absent','class_absent','postponed','cancelled','fill'],true))$missing[]='trạng thái';
 if(trim((string)($row['teacher_comment']??''))==='')$missing[]='nhận xét';
 if(!in_array((string)($row['rating']??''),['Tốt','Khá','Trung bình','Yếu'],true))$missing[]='xếp loại';return $missing;
}
