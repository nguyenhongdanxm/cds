<?php
$source=gdp_builder_configs();$ctx=gdp_week_context($data);
if(($_POST['program_workspace']??'')!==($data['_workspace_id']??'')||($_POST['program_week_key']??'')!==$ctx['key'])throw new RuntimeException('Tuần làm việc đã thay đổi, hãy tải lại trang.');
if(!$source['configs']||$ctx['start']==='')throw new RuntimeException('Chọn tuần làm việc hợp lệ trước.');
if(($_POST['program_version']??'')!==$source['version']||!hash_equals(hash('sha256',json_encode($source['configs'])),(string)($_POST['program_hash']??'')))throw new RuntimeException('Phân công vừa thay đổi, hãy tải lại trang.');
$picks=[];foreach($source['configs']as$class=>$config){$p=filter_var($_POST['program_period'][$class]??null,FILTER_VALIDATE_INT);if($p===false||$p<1||$p>36)throw new RuntimeException('Tiết GDĐP lớp '.$class.' phải từ 1 đến 35, hoặc 36 nếu hoàn thành.');$teacher=gdp_teacher($config,$p);if($p<=35&&$teacher==='')throw new RuntimeException('Chưa phân công tiết '.$p.' lớp '.$class);$picks[$class]=['period'=>$p,'teacher'=>$teacher,'hash'=>gdp_hash($config)];}
$data['program_week']=['week_key'=>$ctx['key'],'version_id'=>$source['version'],'classes'=>$picks,'confirmed_at'=>date('c'),'confirmed_by'=>$user['name']??''];
if(!ttb_save($data))throw new RuntimeException('Không lưu được xác nhận.');
flash('Đã xác nhận tiết GDĐP. Phương án cũ được giữ nguyên; hãy xếp lại để cập nhật đúng giáo viên, kiểm tra trùng giờ trước khi công bố.','success');
