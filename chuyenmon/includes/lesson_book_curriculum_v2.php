<?php
require_once __DIR__.'/lesson_book_store.php';
function lb_ppct_grade_v2(string $v): string { if(preg_match('/(6|7|8|9|10|11|12)/',$v,$m))return $m[1]; return trim($v); }
function lb_ppct_import_v2(array $file,array $input=[]): array {
 if(!lb_can_manage_curriculum())return['ok'=>false,'message'=>'Bạn chưa được phân quyền nhập PPCT.'];
 $isAdmin=lb_is_admin();$targetSubject=trim((string)($input['target_subject']??''));$targetGrade=lb_ppct_grade_v2((string)($input['target_grade']??''));
 if(!$isAdmin&&($targetSubject===''||$targetGrade===''))return['ok'=>false,'message'=>'Hãy chọn Môn–Khối được phân công trước khi tải PPCT.'];
 if(!$isAdmin&&!lb_can_manage_curriculum($targetSubject,$targetGrade))return['ok'=>false,'message'=>'Môn–Khối đã chọn không thuộc phân công chuyên môn hiện hành của tài khoản.'];
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return['ok'=>false,'message'=>'Chưa chọn tệp Excel PPCT.'];
 if(strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='xlsx')return['ok'=>false,'message'=>'Chỉ nhận tệp .xlsx.'];
 $raw=tkb_xlsx_rows((string)$file['tmp_name']); if(empty($raw['ok']))return$raw;
 $required=['stt'=>'STT','grade'=>'Tên khối','subject'=>'Tên môn học','period'=>'Tiết theo PPCT','title'=>'Tên bài dạy','order'=>'Thứ tự','semester'=>'Học kỳ'];
 $aliases=['stt'=>'stt','tenkhoi'=>'grade','khoi'=>'grade','tenmonhoc'=>'subject','monhoc'=>'subject','tiettheoppct'=>'period','tietppct'=>'period','tenbaiday'=>'title','tenbai'=>'title','thutu'=>'order','hocky'=>'semester','hocki'=>'semester'];
 $headerRow=null;$columns=[];
 foreach((array)$raw['rows'] as $n=>$row){$found=[];foreach($row as$col=>$value){$k=lb_norm((string)$value);if(isset($aliases[$k]))$found[$aliases[$k]]=(int)$col;}if(count(array_intersect_key($required,$found))>=4){$headerRow=(int)$n;$columns=$found;break;}}
 if($headerRow===null)return['ok'=>false,'message'=>'Không tìm thấy hàng tiêu đề PPCT. Mẫu bắt buộc gồm: '.implode(' – ',$required).'.'];
 $missing=[];foreach($required as$k=>$label)if(!isset($columns[$k]))$missing[]=$label;
 if($missing)return['ok'=>false,'message'=>'Sai mẫu PPCT. Thiếu cột: '.implode(', ',$missing).'. Hãy tải lại Mẫu Excel mới của hệ thống.'];
 $incoming=[];$errors=[];$seen=[];
 foreach((array)$raw['rows'] as$n=>$row){if((int)$n<=$headerRow)continue;$excelRow=(int)$n+1;$stt=trim((string)($row[$columns['stt']]??''));$gradeRaw=trim((string)($row[$columns['grade']]??''));$subject=trim((string)($row[$columns['subject']]??''));$periodRaw=trim((string)($row[$columns['period']]??''));$title=trim((string)($row[$columns['title']]??''));$orderRaw=trim((string)($row[$columns['order']]??''));$semesterRaw=trim((string)($row[$columns['semester']]??''));if($stt===''&&$gradeRaw===''&&$subject===''&&$periodRaw===''&&$title===''&&$orderRaw===''&&$semesterRaw==='')continue;
  $grade=lb_ppct_grade_v2($gradeRaw);$semester=(int)preg_replace('/\D+/','',$semesterRaw);
  if($grade===''||!in_array((int)$grade,[6,7,8,9,10,11,12],true))$errors[]='Dòng '.$excelRow.': Tên khối không hợp lệ ('.$gradeRaw.').';
  if($subject==='')$errors[]='Dòng '.$excelRow.': thiếu Tên môn học.';
  if(!is_numeric($periodRaw)||(int)$periodRaw<1)$errors[]='Dòng '.$excelRow.': Tiết theo PPCT phải là số ≥ 1.';
  if($title==='')$errors[]='Dòng '.$excelRow.': thiếu Tên bài dạy.';
  if($orderRaw!==''&&(!is_numeric($orderRaw)||(int)$orderRaw<1))$errors[]='Dòng '.$excelRow.': Thứ tự phải là số ≥ 1.';
  if(!in_array($semester,[1,2],true))$errors[]='Dòng '.$excelRow.': Học kỳ chỉ nhận 1 hoặc 2.';
  if($errors&&str_starts_with(end($errors),'Dòng '.$excelRow.':'))continue;
  $period=(int)$periodRaw;$order=$orderRaw===''?$period:(int)$orderRaw;$key=lb_norm($subject).'|'.$grade.'|'.$period;
  if(isset($seen[$key])){$errors[]='Dòng '.$excelRow.': trùng Môn + Khối + Tiết PPCT với dòng '.$seen[$key].'.';continue;}$seen[$key]=$excelRow;
  $incoming[$key]=['stt'=>$stt!==''?(int)$stt:$excelRow-$headerRow-1,'subject'=>$subject,'grade'=>$grade,'grade_name'=>'Khối '.$grade,'period'=>$period,'title'=>$title,'order'=>$order,'semester'=>$semester];
 }
 foreach($incoming as$item)if(!lb_can_manage_curriculum($item['subject'],$item['grade']))$errors[]='Không có quyền môn '.$item['subject'].' khối '.$item['grade'].'.';
 if(!$isAdmin)foreach($incoming as$item){if(!lb_same($targetSubject,(string)$item['subject'])||$targetGrade!==(string)$item['grade'])$errors[]='Tệp có Môn–Khối ngoài lựa chọn '.$targetSubject.' – Khối '.$targetGrade.': '.$item['subject'].' – Khối '.$item['grade'].'. Mỗi tệp chỉ được chứa một Môn–Khối.';if(lb_curriculum_scope_exists($item['subject'],$item['grade']))$errors[]='PPCT '.$item['subject'].' khối '.$item['grade'].' đã có. Giáo viên không được sửa hoặc ghi đè; quản trị phải xóa bản cũ trước khi tải lại.';}
 if($errors)return['ok'=>false,'message'=>'Không nhập dữ liệu vì phát hiện '.count($errors).' lỗi hoặc phạm vi không được cấp. '.implode(' ',array_slice(array_values(array_unique($errors)),0,8)).(count($errors)>8?' …':'')];
 if(!$incoming)return['ok'=>false,'message'=>'Tệp không có dòng PPCT hợp lệ.'];
 $rows=lb_curriculum();$index=[];foreach($rows as$i=>$r)$index[lb_norm((string)($r['subject']??'')).'|'.lb_ppct_grade_v2((string)($r['grade']??'')).'|'.(int)($r['period']??0)]=$i;$added=0;$updated=0;
 foreach($incoming as$key=>$item){if(isset($index[$key])){$rows[$index[$key]]=array_merge($rows[$index[$key]],$item,['updated_at'=>date('c'),'updated_by'=>lb_teacher_name()]);$updated++;}else{$rows[]=array_merge(['id'=>lb_id('ppct')],$item,['created_at'=>date('c'),'created_by'=>lb_teacher_name()]);$added++;}}
 usort($rows,fn($a,$b)=>[(int)($a['grade']??0),lb_norm((string)($a['subject']??'')),(int)($a['order']??$a['period']??0)]<=>[(int)($b['grade']??0),lb_norm((string)($b['subject']??'')),(int)($b['order']??$b['period']??0)]);
 if(!lb_write(LB_CURRICULUM_FILE,$rows))return['ok'=>false,'message'=>'Không ghi được dữ liệu PPCT.'];lb_audit('import_curriculum_v2',['added'=>$added,'updated'=>$updated,'total'=>count($incoming)]);return['ok'=>true,'message'=>'Kiểm tra hợp lệ. Đã nhập '.count($incoming).' dòng PPCT: thêm '.$added.', cập nhật '.$updated.'.'];
}
function lb_ppct_save_v2(array $in): array {if(!lb_is_admin())return['ok'=>false,'message'=>'Giáo viên chỉ được tải PPCT mới từ Excel, không được sửa từng dòng.'];$grade=lb_ppct_grade_v2(trim((string)($in['grade']??'')));$subject=trim((string)($in['subject']??''));$period=(int)($in['period']??0);$title=trim((string)($in['title']??''));$order=max(1,(int)($in['order']??$period));$semester=(int)($in['semester']??0);if($subject===''||$grade===''||$period<1||$title===''||!in_array($semester,[1,2],true))return['ok'=>false,'message'=>'Nhập đủ Tên khối, Tên môn học, Tiết theo PPCT, Tên bài dạy và Học kỳ 1/2.'];$rows=lb_curriculum();$found=false;foreach($rows as&$r)if(lb_same((string)($r['subject']??''),$subject)&&lb_ppct_grade_v2((string)($r['grade']??''))===$grade&&(int)($r['period']??0)===$period){$r=array_merge($r,['subject'=>$subject,'grade'=>$grade,'grade_name'=>'Khối '.$grade,'period'=>$period,'title'=>$title,'order'=>$order,'semester'=>$semester,'updated_at'=>date('c'),'updated_by'=>lb_teacher_name()]);$found=true;break;}unset($r);if(!$found)$rows[]=['id'=>lb_id('ppct'),'subject'=>$subject,'grade'=>$grade,'grade_name'=>'Khối '.$grade,'period'=>$period,'title'=>$title,'order'=>$order,'semester'=>$semester,'created_at'=>date('c'),'created_by'=>lb_teacher_name()];lb_write(LB_CURRICULUM_FILE,$rows);lb_audit('save_curriculum_v2',['subject'=>$subject,'grade'=>$grade,'period'=>$period]);return['ok'=>true,'message'=>'Đã lưu PPCT.'];}
