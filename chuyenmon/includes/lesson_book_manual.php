<?php
/** Tiết bổ sung độc lập với TKB, lưu trong kho Sổ đầu bài hiện hành. */
function lb_manual_assignments(): array {
 return array_values(array_filter(get_assignments(),static function($r){return is_array($r)&&trim((string)($r['class']??''))!==''&&trim((string)($r['subject']??''))!==''&&trim((string)($r['teacher']??''))!=='';}));
}
function lb_manual_conflict(array $rows,array $candidate): bool {
 foreach($rows as$r){
  if((string)($r['date']??'')!==$candidate['date']||(string)($r['session']??'')!==$candidate['session']||(int)($r['period']??0)!==$candidate['period'])continue;
  if(lb_same((string)($r['class']??''),$candidate['class'])||lb_same((string)($r['actual_teacher']??$r['scheduled_teacher']??''),$candidate['actual_teacher']))return true;
 }
 return false;
}
function lb_add_manual_slot(array $week,array $input): array {
 if(!lb_is_management())return['ok'=>false,'message'=>'Chỉ quản trị/BGH được bổ sung tiết thủ công.'];
 $date=trim((string)($input['manual_date']??''));$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if(!$parsed||$parsed->format('Y-m-d')!==$date||$date<(string)$week['start']||$date>(string)$week['end'])return['ok'=>false,'message'=>'Ngày bổ sung phải nằm trong tuần học đang chọn.'];
 $session=(string)($input['manual_session']??'');$period=filter_var($input['manual_period']??null,FILTER_VALIDATE_INT);
 if(!in_array($session,['Sáng','Chiều'],true)||$period===false||$period<1||$period>10)return['ok'=>false,'message'=>'Chọn buổi và tiết từ 1 đến 10.'];
 $class=trim((string)($input['manual_class']??''));$subject=trim((string)($input['manual_subject']??''));$teacher=trim((string)($input['manual_teacher']??''));$assignment=null;
 foreach(lb_manual_assignments()as$a)if(lb_same($class,(string)$a['class'])&&lb_same_subject($subject,(string)$a['subject'])&&lb_same($teacher,(string)$a['teacher'])){$assignment=$a;break;}
 if(!$assignment)return['ok'=>false,'message'=>'Lớp, môn và giáo viên phải khớp phân công chuyên môn.'];
 $row=['week_key'=>(string)$week['key'],'week_label'=>(string)$week['label'],'date'=>$date,'day'=>(int)$parsed->format('N')+1,'session'=>$session,'period'=>$period,'class'=>(string)$assignment['class'],'subject'=>tkb_subject_canonical((string)$assignment['subject']),'scheduled_teacher'=>(string)$assignment['teacher'],'actual_teacher'=>(string)$assignment['teacher'],'book_type'=>'main','grade'=>lb_grade((string)$assignment['class']),'manual_slot'=>true,'manual_note'=>mb_substr(trim((string)($input['manual_note']??'')),0,500,'UTF-8'),'status'=>'pending','ppct_period'=>0,'created_at'=>date('c'),'created_by'=>lb_teacher_name(),'updated_at'=>date('c'),'updated_by'=>lb_teacher_name()];
 if(lb_locked($week,$row))return['ok'=>false,'message'=>'Sổ của lớp này đã khóa. Hãy mở khóa trước khi bổ sung.'];
 if(lb_manual_conflict(array_merge(lb_slots($week),lb_rows(LB_RECORDS_FILE)),$row))return['ok'=>false,'message'=>'Lớp hoặc giáo viên đã có tiết tại ngày, buổi, tiết này. Không tạo tiết trùng.'];
 $row['slot_id']=lb_slot_id($week,$row);
 // Khóa kiểm tra và ghi trong cùng thao tác cập nhật, tránh hai cửa sổ tạo trùng.
 $ok=cds_json_update(LB_RECORDS_FILE,static function($rows)use($row){
  $rows=array_values(array_filter(is_array($rows)?$rows:[],'is_array'));
  if(lb_manual_conflict($rows,$row))return false;
  $rows[]=$row;return$rows;
 },[]);
 if(!$ok)return['ok'=>false,'message'=>'Không thêm được tiết; dữ liệu có thể vừa thay đổi. Hãy tải lại sổ.'];
 lb_rows_bust(LB_RECORDS_FILE);
 if(!cds_lb_shadow_upsert($row))$GLOBALS['cds_force_json_lb_read']=true;
 lb_audit('add_manual_slot',['slot_id'=>$row['slot_id'],'manual_note'=>$row['manual_note']]);
 return['ok'=>true,'message'=>'Đã thêm tiết thủ công chưa ký. Giáo viên mở tiết để nhập nội dung, PPCT và ký.'];
}
function lb_merge_independent_records(array $rows,array $records,array $week): array {
 // Dòng bổ sung luôn độc lập; bản ghi đã ký/qua ngày không biến mất khi TKB đổi.
 $protected=[];$today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');
 foreach($records as$r){
  if((string)($r['week_key']??'')!==(string)$week['key'])continue;
  if(empty($r['manual_slot'])&&empty($r['signed_at'])&&!((string)($r['date']??'')!==''&&(string)$r['date']<$today))continue;
  if(!empty($r['signed_snapshot'])&&is_array($r['signed_snapshot']))$r=array_merge($r,$r['signed_snapshot']);
  $protected[(string)$r['slot_id']]=$r;
 }
 $out=[];$used=[];
 foreach($rows as$r){
  $id=(string)($r['slot_id']??'');
  if(isset($protected[$id])){$r=$protected[$id];$used[$id]=true;}
  else foreach($protected as$p)if((string)($p['date']??'')===(string)($r['date']??'')&&(string)($p['session']??'')===(string)($r['session']??'')&&(int)($p['period']??0)===(int)($r['period']??0)&&lb_same((string)($p['class']??''),(string)($r['class']??''))){continue 2;}
  if($id!==''&&isset($out[$id]))continue;$out[$id]=$r;
 }
 foreach($protected as$id=>$r)if(!isset($used[$id]))$out[$id]=$r;
 return array_values($out);
}
