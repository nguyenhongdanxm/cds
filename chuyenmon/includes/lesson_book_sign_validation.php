<?php
function lb_sign_required_fields(array $row): array {
 $missing=[];if(!in_array(trim((string)($row['status']??'')),['taught','substitute','makeup','online','holiday','teacher_absent','class_absent','postponed','cancelled','fill'],true))$missing[]='trạng thái';
 if(trim((string)($row['teacher_comment']??''))==='')$missing[]='nhận xét';
 if(!in_array((string)($row['rating']??''),['Tốt','Khá','Trung bình','Yếu'],true))$missing[]='xếp loại';return $missing;
}

function lb_program_period_error(array $slot,array $input): string {
 $planned=(int)($slot['planned_ppct_period']??0);
 if($planned>0&&empty($slot['signed_at'])&&in_array((string)($input['status']??'taught'),['taught','substitute','makeup','online'],true)&&(int)($input['ppct_period']??0)!==$planned)return 'Tiết GDĐP này đã được xếp theo tiết chương trình '.$planned.'. Cần cập nhật TKB nếu đổi tiết hoặc giáo viên phụ trách.';
 return '';
}
