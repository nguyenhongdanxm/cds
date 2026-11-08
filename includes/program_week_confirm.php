<?php
$source=gdp_builder_configs();$ctx=gdp_week_context($data);
if(($_POST['program_workspace']??'')!==($data['_workspace_id']??'')||($_POST['program_week_key']??'')!==$ctx['key'])throw new RuntimeException('Tuần làm việc đã thay đổi, hãy tải lại trang.');
if(!$source['configs']||$ctx['start']==='')throw new RuntimeException('Chọn tuần làm việc hợp lệ trước.');
if(($_POST['program_version']??'')!==$source['version']||!hash_equals(hash('sha256',json_encode($source['configs'])),(string)($_POST['program_hash']??'')))throw new RuntimeException('Phân công vừa thay đổi, hãy tải lại trang.');
$picks=[];foreach($source['configs']as$class=>$config){$p=filter_var($_POST['program_period'][$class]??null,FILTER_VALIDATE_INT);if($p===false||$p<1||$p>36)throw new RuntimeException('Tiết GDĐP lớp '.$class.' phải từ 1 đến 35, hoặc 36 nếu hoàn thành.');$teacher=gdp_teacher($config,$p);if($p<=35&&$teacher==='')throw new RuntimeException('Chưa phân công tiết '.$p.' lớp '.$class);$picks[$class]=['period'=>$p,'teacher'=>$teacher,'hash'=>gdp_hash($config)];}
$data['program_week']=['week_key'=>$ctx['key'],'version_id'=>$source['version'],'classes'=>$picks,'progress_hash'=>gdp_progress_hash($source['configs'],$ctx,gdp_week_records($ctx)),'confirmed_at'=>date('c'),'confirmed_by'=>$user['name']??''];
$data['program_week']['plan_bindings']=gdp_confirm_plan_bindings($data,$picks);
if(!ttb_save($data))throw new RuntimeException('Không lưu được xác nhận.');
flash('Đã xác nhận tiết GDĐP và liên kết các tiết đang xếp đúng giáo viên với tuần hiện tại. Vị trí TKB được giữ nguyên; tiết sai giáo viên, thiếu hoặc trùng vẫn phải kiểm tra trước khi công bố.','success');
