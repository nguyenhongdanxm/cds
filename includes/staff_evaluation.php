<?php
/** NĐ 233: monthly workflow. Stored separately from legacy teacher evaluations. */
function se_text($value,int $limit=4000):string{if(!is_scalar($value)&&$value!==null)throw new RuntimeException('Nội dung không hợp lệ.');$s=trim((string)$value);if(strlen($s)>$limit)throw new RuntimeException('Nội dung quá dài.');return $s;}
function se_month(string $month):string{if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$month))throw new RuntimeException('Tháng không hợp lệ.');return $month;}
function se_date(string $date):string{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('Ngày không hợp lệ.');return $date;}
function se_uid(array $user):string{return (string)($user['id']??'');}
function se_readonly(array $user):bool{return ($user['role']??'')==='viewer';}
function se_admin(array $user):bool{return !se_readonly($user)&&($user['role']??'')==='admin';}
function se_defaults():array{
 $common=[['id'=>'A1','group'=>'A','name'=>'Phẩm chất chính trị, đạo đức; kỷ luật, kỷ cương','max'=>10,'guide'=>'Chấp hành chủ trương, pháp luật, phân công và nội quy; đạo đức nghề nghiệp, trung thực, đoàn kết; phòng chống tiêu cực, lãng phí; khắc phục hạn chế; kê khai tài sản khi thuộc đối tượng; báo cáo chính xác. Chấm theo mức đạt và minh chứng trong quy chế.'],['id'=>'A2','group'=>'A','name'=>'Năng lực, trách nhiệm, thái độ phục vụ và phối hợp','max'=>15,'guide'=>'Năng lực đáp ứng vị trí; cập nhật chuyên môn, ứng dụng công nghệ; trách nhiệm thực hiện công việc thường xuyên/đột xuất; ứng xử và phối hợp, phục vụ người học.'],['id'=>'A3','group'=>'A','name'=>'Đổi mới, chủ động, chịu trách nhiệm','max'=>5,'guide'=>'Chủ động xử lý công việc trong thẩm quyền; giải pháp cải tiến có kết quả; nhận trách nhiệm và sửa sai. Không bắt buộc mỗi tháng có sáng kiến/giấy khen.']];
 $regular=[['id'=>'B1','group'=>'B','name'=>'Số lượng, khối lượng nhiệm vụ','max'=>25,'guide'=>'Đối chiếu sản phẩm và khối lượng được giao/điều chỉnh đã xác nhận; công việc nhiều tháng tính theo mốc tháng, không tính trùng.'],['id'=>'B2','group'=>'B','name'=>'Chất lượng, hiệu quả sản phẩm','max'=>30,'guide'=>'Đối chiếu yêu cầu chất lượng khi giao việc và kết quả nghiệm thu; nộp tệp không đồng nghĩa chất lượng đạt.'],['id'=>'B3','group'=>'B','name'=>'Tiến độ thực hiện','max'=>15,'guide'=>'Đối chiếu ngày hoàn thành với hạn được phê duyệt; xem xét điều chỉnh và trường hợp đặc biệt theo quy chế.']];
 $manager=$regular;$manager[0]['max']=15;$manager[1]['max']=20;$manager[2]['max']=10;
 foreach([['B4','Kết quả lĩnh vực/đơn vị phụ trách',10],['B5','Tổ chức triển khai, kiểm tra và xử lý vướng mắc',10],['B6','Tập hợp, phối hợp và đoàn kết',5]]as$r)$manager[]=['id'=>$r[0],'group'=>'B','name'=>$r[1],'max'=>$r[2],'guide'=>'Đối chiếu nhiệm vụ quản lý, kết quả xác nhận và minh chứng theo quy chế, phân cấp thực tế.'];
 return ['revision'=>0,'versions'=>[],'assignments'=>[],'template'=>['id'=>'draft','active'=>false,'decision'=>'','effective'=>'','profiles'=>['regular'=>array_merge($common,$regular),'manager'=>array_merge($common,$manager)]]];
}
function se_version(array $config,string $month):array{
 $month=se_month($month);$last=date('Y-m-t',strtotime($month.'-01'));$valid=array_filter($config['versions']??[],fn($v)=>!empty($v['active'])&&($v['effective']??'9999')<=$last);
 usort($valid,fn($a,$b)=>strcmp($b['effective'],$a['effective'])?:((int)$b['number']<=>(int)$a['number']));$fallback=$config['template']??se_defaults()['template'];$fallback['active']=false;$fallback['id']='draft';return $valid[0]??$fallback;
}
function se_assignment(array $config,string $staffId):array{return $config['assignments'][$staffId]??['user_id'=>'','reviewer'=>'','approver'=>'','profile'=>'regular','employment'=>'vienchuc','position'=>'','external'=>false];}
function se_own(array $assignment,array $user):bool{return se_uid($user)!==''&&($assignment['user_id']??'')===se_uid($user);}
function se_can_read(array $record,array $user):bool{
 $id=se_uid($user);$a=$record['assignment']??[];return se_admin($user)||se_readonly($user)||($id!==''&&in_array($id,[$a['user_id']??'',$a['reviewer']??'',$a['approver']??''],true));
}
function se_actor(array $record,array $user,string $role):bool{
 return !se_readonly($user)&&!se_own($record['assignment'],$user)&&se_uid($user)!==''&&($record['assignment'][$role]??'')===se_uid($user);
}
function se_blank(array $staff,string $month,array $config):array{
 $id=se_text($staff['id']??'',100);if($id==='')throw new RuntimeException('Nhân sự thiếu mã.');$a=se_assignment($config,$id);$v=se_version($config,$month);
 return ['revision'=>0,'month'=>se_month($month),'staff_id'=>$id,'name'=>(string)($staff['name']??''),'team'=>(string)($staff['to_chuyen_mon']??$staff['pccm_group']??''),'assignment'=>$a,'criteria_version'=>$v,'criteria'=>$v['profiles'][$a['profile']??'regular']??$v['profiles']['regular'],'status'=>'draft','tasks'=>[],'self_scores'=>[],'review_scores'=>[],'approved_scores'=>[],'evidence_notes'=>[],'sources'=>[],'strengths'=>'','limits'=>'','explanation'=>'','review_note'=>'','approval_note'=>'','history'=>[]];
}
function se_total(array $scores,array $criteria):?float{$sum=0;foreach($criteria as$c){if(!array_key_exists($c['id'],$scores)||$scores[$c['id']]===null)return null;$sum+=(float)$scores[$c['id']];}return round($sum,2);}
function se_scores($input,array $criteria,bool $required=false):array{
 if(!is_array($input))throw new RuntimeException('Điểm không hợp lệ.');$out=[];
 foreach($criteria as$c){$v=$input[$c['id']]??'';if($v===''){if($required)throw new RuntimeException('Chưa đủ căn cứ chấm: '.$c['name']);$out[$c['id']]=null;continue;}
 if(!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>(float)$c['max'])throw new RuntimeException('Điểm ngoài giới hạn: '.$c['name']);$out[$c['id']]=round((float)$v,2);}
 return $out;
}
function se_url($value):string{$value=se_text($value,2000);if($value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true)))throw new RuntimeException('Minh chứng phải là liên kết http/https.');return$value;}
function se_editable(array $r):bool{return in_array($r['status'],['draft','returned'],true);}
function se_transition(array $record,array $user,string $action,array $input,array $sources=[],array $config=[]):array{
 if(se_readonly($user)||se_uid($user)==='')throw new RuntimeException('Tài khoản chỉ xem không được cập nhật.');
 if(!se_can_read($record,$user))throw new RuntimeException('Hồ sơ ngoài phạm vi được giao.');
 if((string)($input['revision']??'')!==(string)$record['revision'])throw new RuntimeException('Hồ sơ đã thay đổi. Hãy tải lại trước khi lưu.');
 $old=$record;$own=se_own($record['assignment'],$user);$review=se_actor($record,$user,'reviewer');$approve=se_actor($record,$user,'approver');
 if($record['status']==='locked'&&$action!=='reopen')throw new RuntimeException('Kỳ đã khóa, không thể chỉnh sửa.');
 $reason=se_text($input['reason']??'');
 if($action==='apply_config'){
  if(!se_admin($user)||!se_editable($record)||$reason===''||!$config)throw new RuntimeException('Chỉ quản trị được cập nhật cấu hình hồ sơ nháp với lý do.');
  $v=se_version($config,$record['month']);$record['assignment']=se_assignment($config,$record['staff_id']);$record['criteria_version']=$v;$record['criteria']=$v['profiles'][$record['assignment']['profile']]??$v['profiles']['regular'];$record['self_scores']=[];$record['review_scores']=[];$record['approved_scores']=[];
 }elseif($action==='task_adjust'){
  if(!($review||$approve)||!se_editable($record)||$reason==='')throw new RuntimeException('Chỉ người phụ trách điều chỉnh nhiệm vụ đang mở, cần lý do.');
  $id=se_text($input['task_id']??'',100);if(!isset($record['tasks'][$id]))throw new RuntimeException('Nhiệm vụ không tồn tại.');
  $t=&$record['tasks'][$id];if(!empty($input['cancel'])){$t['cancelled']=true;}else{$t['product']=se_text($input['product']??'',2000);$t['quality']=se_text($input['quality']??'',2000);$t['due']=se_date(se_text($input['due']??'',10));if(substr($t['due'],0,7)!==$record['month']||$t['product']===''||$t['quality']==='')throw new RuntimeException('Cần sản phẩm, chất lượng và mốc trong tháng.');$t['accepted']=false;unset($t['acceptance'],$t['accepted_by']);}$t['adjustment']=$reason;unset($t);
 }elseif($action==='task_add'){
  if(!se_editable($record)||!($own||$review||$approve))throw new RuntimeException('Không thể thêm nhiệm vụ ở bước này.');
  $title=se_text($input['title']??'',500);$product=se_text($input['product']??'',2000);$quality=se_text($input['quality']??'',2000);if($title===''||$product===''||$quality==='')throw new RuntimeException('Cần tên nhiệm vụ, sản phẩm và yêu cầu chất lượng.');
  $due=se_date(se_text($input['due']??'',10));if(substr($due,0,7)!==$record['month'])throw new RuntimeException('Hạn/mốc sản phẩm phải thuộc tháng đang đánh giá.');
  $weight=$input['weight']??1;if(!is_numeric($weight)||!is_finite((float)$weight)||(float)$weight<=0||(float)$weight>1000)throw new RuntimeException('Khối lượng không hợp lệ.');
  $id=bin2hex(random_bytes(10));$record['tasks'][$id]=['id'=>$id,'title'=>$title,'product'=>$product,'quality'=>$quality,'due'=>$due,'weight'=>(float)$weight,'assigned'=>$review||$approve,'created_by'=>se_uid($user),'result'=>'','url'=>'','completed'=>'','accepted'=>false];
 }elseif($action==='task_result'){
  if(!$own||!se_editable($record))throw new RuntimeException('Chỉ cá nhân cập nhật kết quả khi hồ sơ đang mở.');$id=se_text($input['task_id']??'',100);if(!isset($record['tasks'][$id])||!empty($record['tasks'][$id]['cancelled']))throw new RuntimeException('Không tìm thấy nhiệm vụ đang áp dụng.');
  $task=&$record['tasks'][$id];$result=se_text($input['result']??'');$url=se_url($input['url']??'');$completed=se_text($input['completed']??'',10);if($completed!=='')se_date($completed);
  if(($task['result']??'')!==$result||($task['url']??'')!==$url||($task['completed']??'')!==$completed){$task['accepted']=false;unset($task['accepted_by'],$task['acceptance']);}
  $task['result']=$result;$task['url']=$url;$task['completed']=$completed;unset($task);
 }elseif($action==='task_accept'){
  if(!($review||$approve)||!in_array($record['status'],['draft','returned','submitted'],true))throw new RuntimeException('Không có quyền xác nhận nhiệm vụ ở bước này.');$id=se_text($input['task_id']??'',100);if(!isset($record['tasks'][$id])||!empty($record['tasks'][$id]['cancelled']))throw new RuntimeException('Không tìm thấy nhiệm vụ đang áp dụng.');if($reason==='')throw new RuntimeException('Cần nhận xét xác nhận/nghiệm thu.');
  $task=&$record['tasks'][$id];$task['assigned']=true;if(!empty($input['accept_result'])){if($task['result']===''||$task['completed']==='')throw new RuntimeException('Chưa có kết quả và ngày hoàn thành để nghiệm thu.');$task['accepted']=true;$task['accepted_by']=se_uid($user);$task['acceptance']=$reason;}unset($task);
 }elseif(in_array($action,['save_self','submit'],true)){
  if(!$own||!se_editable($record))throw new RuntimeException('Không thể tự đánh giá ở bước này.');
  $record['self_scores']=se_scores($input['scores']??[],$record['criteria'],$action==='submit');
  $record['strengths']=se_text($input['strengths']??'');$record['limits']=se_text($input['limits']??'');$record['explanation']=se_text($input['explanation']??'');
  foreach($record['criteria']as$c)$record['evidence_notes'][$c['id']]=se_text($input['evidence'][$c['id']]??($record['evidence_notes'][$c['id']]??''),2000);
  foreach(['common_summary','task_summary']as$field)if(array_key_exists($field,$input))$record[$field]=se_text($input[$field],12000);
  if(($input['form_schema']??'')==='2')$record['form_schema']=2;
  if($action==='submit'){
   if(empty($record['criteria_version']['active']))throw new RuntimeException('Bộ tiêu chí còn dự thảo. Cần kích hoạt quy chế trước khi gửi.');
   if(($record['assignment']['employment']??'')==='hopdong'&&empty($record['assignment']['contract_basis']))throw new RuntimeException('Chưa có căn cứ áp dụng đánh giá cho lao động hợp đồng.');
   $summary=se_form_summary($record);
   if(($record['form_schema']??1)===2){
    foreach(['common_summary','task_summary','strengths','limits']as$field)if(trim($summary[$field])==='')throw new RuntimeException('Cần hoàn thiện kết quả tiêu chí chung, kết quả nhiệm vụ, ưu điểm và hạn chế (ghi rõ nếu không có).');
   }else{foreach($record['criteria']as$c)if($record['evidence_notes'][$c['id']]==='')throw new RuntimeException('Cần căn cứ/giải trình cho từng tiêu chí.');}
   if(!$record['tasks']&&$record['explanation']===''&&trim($summary['task_summary'])==='')throw new RuntimeException('Chưa có nhiệm vụ; cần bổ sung hoặc giải trình trường hợp đặc biệt.');
   foreach($record['tasks']as$t)if(empty($t['cancelled'])&&empty($t['assigned']))throw new RuntimeException('Nhiệm vụ tự đề xuất chưa được người phụ trách xác nhận.');
   $record['review_scores']=[];$record['approved_scores']=[];$record['review_note']='';$record['approval_note']='';$record['status']='submitted';
  }
 }elseif($action==='review'){
  if(!$review||$record['status']!=='submitted')throw new RuntimeException('Chỉ người thẩm định được giao được xử lý hồ sơ đã gửi.');
  if($reason==='')throw new RuntimeException('Cần nhận xét thẩm định và lý do điều chỉnh điểm (nếu có).');$record['review_scores']=se_scores($input['scores']??[],$record['criteria'],true);$record['review_note']=$reason;$record['status']='reviewed';
 }elseif($action==='approve'){
  if(!$approve||$record['status']!=='reviewed'||!empty($record['assignment']['external']))throw new RuntimeException('Chưa đủ điều kiện/thẩm quyền phê duyệt.');
  if($reason==='')throw new RuntimeException('Cần ý kiến phê duyệt.');$record['approved_scores']=se_scores($input['scores']??[],$record['criteria'],true);$record['approval_note']=$reason;$record['status']='locked';$record['locked_at']=date('c');$record['locked_by']=se_uid($user);
 }elseif($action==='return'){
  if(!(($review&&$record['status']==='submitted')||($approve&&$record['status']==='reviewed'))||$reason==='')throw new RuntimeException('Cần đúng người xử lý và lý do trả bổ sung.');$record['status']='returned';$record['return_reason']=$reason;
 }elseif($action==='reopen'){
  if(!$approve||$record['status']!=='locked'||$reason==='')throw new RuntimeException('Chỉ người phê duyệt được mở lại với lý do.');$snapshot=$old;unset($snapshot['closed_versions']);$record['closed_versions'][]=$snapshot;$record['approved_scores']=[];$record['approval_note']='';unset($record['locked_at'],$record['locked_by']);$record['status']='returned';$record['return_reason']=$reason;
 }elseif($action==='sync'){
  if(!($own||$review||$approve)||!se_editable($record))throw new RuntimeException('Chỉ cập nhật minh chứng khi hồ sơ đang mở.');$record['sources']=$sources;$record['sources_at']=date('c');
 }else throw new RuntimeException('Thao tác không hợp lệ.');
 if(str_starts_with($action,'task_')){$taskId=(string)($input['task_id']??$id??'');$record['task_history'][]=['at'=>date('c'),'by'=>se_uid($user),'action'=>$action,'reason'=>$reason,'before'=>$old['tasks'][$taskId]??null,'after'=>$record['tasks'][$taskId]??null];}
 $record['revision']++;$record['updated_at']=date('c');$record['history'][]=['at'=>date('c'),'user_id'=>se_uid($user),'name'=>se_text($user['name']??'',200),'action'=>$action,'from'=>$old['status'],'to'=>$record['status'],'reason'=>$reason,'config_before'=>$old['criteria_version']['id'],'config_after'=>$record['criteria_version']['id'],'assignment_before'=>$old['assignment'],'assignment_after'=>$record['assignment'],'form_before'=>se_form_summary($old),'form_after'=>se_form_summary($record),'before'=>['self'=>$old['self_scores'],'review'=>$old['review_scores'],'approved'=>$old['approved_scores']],'after'=>['self'=>$record['self_scores'],'review'=>$record['review_scores'],'approved'=>$record['approved_scores']]];
 return$record;
}
function se_configure(array $old,array $user,array $input,array $staff,array $users):array{
 if(!se_admin($user))throw new RuntimeException('Chỉ quản trị được cấu hình; quyền duyệt cấp riêng.');if(empty($input['config_complete']))throw new RuntimeException('Biểu mẫu cấu hình chưa đầy đủ; hãy kiểm tra giới hạn nhập liệu máy chủ và thử lại.');if((string)($input['revision']??'')!==(string)($old['revision']??0))throw new RuntimeException('Cấu hình đã thay đổi, hãy tải lại.');
 $new=$old;$template=$old['template'];
 foreach(['regular','manager']as$profile){$totals=['A'=>0,'B'=>0];foreach($template['profiles'][$profile]as&$c){$id=$c['id'];$v=$input['max'][$profile][$id]??$c['max'];if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>100)throw new RuntimeException('Điểm tiêu chí không hợp lệ.');$c['max']=(float)$v;$c['guide']=se_text($input['guide'][$profile][$id]??$c['guide'],6000);if($c['guide']==='')throw new RuntimeException('Cần hướng dẫn chấm điểm.');$totals[$c['group']]+=$c['max'];}unset($c);if(abs($totals['A']-30)>.001||abs($totals['B']-70)>.001)throw new RuntimeException('Mỗi mẫu phải đủ 30 điểm chung và 70 điểm nhiệm vụ.');}
 $template['decision']=se_text($input['decision']??'',500);$template['effective']=se_text($input['effective']??'',10);$template['active']=!empty($input['activate']);
 if($template['effective']!=='')se_date($template['effective']);if($template['active']&&($template['decision']===''||$template['effective']===''))throw new RuntimeException('Cần số/ngày quyết định, căn cứ và ngày hiệu lực trước khi kích hoạt.');
 $template['number']=count($old['versions']??[])+1;$template['id']='v'.$template['number'].'_'.bin2hex(random_bytes(4));$template['at']=date('c');$template['by']=se_uid($user);$new['template']=$template;$new['versions'][]=$template;
 $validUsers=[];foreach($users as$u)if(($u['active']??true)&&($u['role']??'')!=='viewer')$validUsers[(string)$u['id']]=true;
 $assigned=[];
 foreach($staff as$s){$id=(string)$s['id'];$r=$input['assignments'][$id]??se_assignment($old,$id);$a=[];foreach(['user_id','reviewer','approver']as$key){$a[$key]=se_text($r[$key]??'',100);if($a[$key]!==''&&!isset($validUsers[$a[$key]]))throw new RuntimeException('Tài khoản phân quyền không hợp lệ.');}
  if($a['user_id']!==''&&isset($assigned[$a['user_id']]))throw new RuntimeException('Một tài khoản chỉ liên kết một nhân sự.');if($a['user_id']!=='')$assigned[$a['user_id']]=true;
  if($a['user_id']!==''&&in_array($a['user_id'],[$a['reviewer'],$a['approver']],true))throw new RuntimeException('Không được tự thẩm định/phê duyệt hồ sơ của mình.');
  $a['profile']=($r['profile']??'')==='manager'?'manager':'regular';$a['employment']=($r['employment']??'')==='hopdong'?'hopdong':'vienchuc';$a['contract_basis']=se_text($r['contract_basis']??'',500);$a['position']=se_text($r['position']??'',200);$a['external']=!empty($r['external']);$new['assignments'][$id]=$a;
 }
 $new['assignment_history'][]=['at'=>date('c'),'by'=>se_uid($user),'before'=>$old['assignments']??[],'after'=>$new['assignments']];$new['revision']=($old['revision']??0)+1;return$new;
}
function se_path(string $month):string{return DATA_PATH.'/staff_evaluation/month-'.se_month($month).'.json';}
function se_config():array{$v=load_json(DATA_PATH.'/staff_evaluation/config.json',se_defaults());return array_replace(se_defaults(),is_array($v)?$v:[]);}
function se_update(string $file,callable $fn,array $default):void{
 $dir=dirname($file);if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Không tạo được kho hồ sơ.');
 $deny=$dir.'/.htaccess';if(!is_file($deny)&&file_put_contents($deny,"Require all denied\nDeny from all\n",LOCK_EX)===false)throw new RuntimeException('Không bảo vệ được kho hồ sơ, chưa lưu dữ liệu.');
 $error=null;$ok=cds_json_update($file,function($current)use($fn,&$error){try{return$fn($current);}catch(Throwable$e){$error=$e;return false;}},$default);if($error)throw$error;if(!$ok)throw new RuntimeException('Không lưu được dữ liệu. Hãy tải lại và thử lại.');
}
function se_source_rows(array $staff,string $month,array $roster):array{
 $sources=[];$name=te_norm($staff['name']??'');$same=count(array_filter($roster,fn($s)=>te_norm($s['name']??'')===$name));
 $groups=['Dự giờ'=>te_observations(),'Kiểm tra hồ sơ'=>te_file_checks(),'Thi đua'=>load_json(DATA_PATH.'/thidua.json',[])['records']??[]];
 foreach($groups as$type=>$rows)foreach($rows as$row){
  if($type==='Thi đua'&&!in_array($row['type']??'',['teacher_attendance','teacher_achievement'],true))continue;
  $date=(string)($row['date']??$row['start_date']??$row['from_date']??'');$end=(string)($row['to_date']??$date);$from=$month.'-01';$to=date('Y-m-t',strtotime($from));if($date===''||$date>$to||$end<$from)continue;
  $ids=array_filter([(string)($row['teacher_id']??''),(string)($row['person_id']??'')]);$byId=in_array((string)$staff['id'],$ids,true);$byName=!$ids&&$same===1&&te_norm($row['teacher']??$row['teacher_name']??$row['person_name']??'')===$name;if(!$byId&&!$byName)continue;
  $sources[]=['id'=>(string)($row['id']??hash('sha256',json_encode($row))),'type'=>$type,'date'=>$date,'label'=>se_text($row['title']??$row['content']??$row['rating']??$row['status']??$type,6000),'matched'=>$byId?'Mã nhân sự':'Tên duy nhất – cần đối chiếu','url'=>$type==='Dự giờ'?'/chuyenmon/dugio.php':($type==='Kiểm tra hồ sơ'?'/chuyenmon/kiemtrahoso.php':'/thidua.php')];
 }return$sources;
}

/** Shared month-to-year fields, with read-only fallback for records created before schema 2. */
function se_form_summary(array $record):array{
 $a=trim((string)($record['common_summary']??''));$b=trim((string)($record['task_summary']??''));
 $byGroup=['A'=>[],'B'=>[]];foreach($record['criteria']??[]as$c){$note=trim((string)($record['evidence_notes'][$c['id']]??''));if($note!=='')$byGroup[$c['group']][]=$c['name'].': '.$note;}
 if($a==='')$a=implode("\n",$byGroup['A']);
 if($b===''){$lines=[];foreach($record['tasks']??[]as$t)if(empty($t['cancelled']))$lines[]=$t['title'].' — Sản phẩm: '.$t['product'].'; yêu cầu chất lượng: '.$t['quality'].'; hạn: '.$t['due'].'; kết quả: '.($t['result']?:'Chưa cập nhật').'; hoàn thành: '.($t['completed']?:'Chưa xác nhận');$b=implode("\n",array_merge($byGroup['B'],$lines));}
 return ['common_summary'=>$a,'task_summary'=>$b,'strengths'=>(string)($record['strengths']??''),'limits'=>(string)($record['limits']??'')];
}
function se_group_points(array $record,string $stage):array{
 $scores=$record[$stage]??[];$groups=['A'=>[],'B'=>[]];foreach($record['criteria']??[]as$c)$groups[$c['group']][]=$c;
 return ['A'=>se_total($scores,$groups['A']),'B'=>se_total($scores,$groups['B']),'total'=>se_total($scores,$record['criteria']??[])];
}
function se_year_summary(array $records,string $from,string $to):array{
 se_month($from);se_month($to);if($from>$to||substr($from,0,4)!==substr($to,0,4))throw new RuntimeException('Khoảng tháng phải trong cùng năm công tác.');
 $out=['months'=>[],'missing'=>[],'common_summary'=>[],'task_summary'=>[],'strengths'=>[],'limits'=>[],'points'=>['A'=>null,'B'=>null,'total'=>null]];$a=[];$b=[];$total=[];
 for($month=$from;$month<=$to;$month=date('Y-m',strtotime($month.'-01 +1 month'))){$r=$records[$month]??null;if(!$r||($r['status']??'')!=='locked'){$out['missing'][]=$month;continue;}$p=se_group_points($r,'approved_scores');if($p['total']===null){$out['missing'][]=$month;continue;}$a[]=$p['A'];$b[]=$p['B'];$total[]=$p['total'];$out['months'][]=$month;foreach(se_form_summary($r)as$key=>$value)if($value!=='')$out[$key][]=$month.': '.$value;}
 if($total)$out['points']=['A'=>round(array_sum($a)/count($a),2),'B'=>round(array_sum($b)/count($b),2),'total'=>round(array_sum($total)/count($total),2)];return$out;
}
