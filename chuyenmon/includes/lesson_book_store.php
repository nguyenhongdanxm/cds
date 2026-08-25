<?php
/** Sổ đầu bài: dữ liệu độc lập theo từng tiết, dùng chung Tuần học và TKB. */
require_once __DIR__ . '/timetable_store.php';

define('LB_RECORDS_FILE', DATA_PATH . '/lesson_book_records.json');
define('LB_CURRICULUM_FILE', DATA_PATH . '/lesson_book_curriculum.json');
define('LB_SETTINGS_FILE', DATA_PATH . '/lesson_book_settings.json');
define('LB_LOCKS_FILE', DATA_PATH . '/lesson_book_locks.json');
define('LB_AUDIT_FILE', DATA_PATH . '/lesson_book_audit.json');
define('LB_SIGNATURES_DIR', DATA_PATH . '/lesson_book_signatures');

function lb_rows(string $file): array { $v=load_json($file,[]); return is_array($v)?array_values(array_filter($v,'is_array')):[]; }
function lb_write(string $file,array $rows): bool { return (bool)save_json($file,array_values($rows)); }
function lb_id(string $prefix='lb'): string { return $prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)); }
function lb_user(): array { return (array)(cds_user()?:[]); }
function lb_is_admin(): bool { return (lb_user()['role']??'')==='admin'; }
function lb_is_leader(): bool { $u=lb_user(); return ($u['role']??'')==='totruong'||in_array('totruong',(array)($u['groups']??[]),true); }
function lb_teacher_name(): string { $u=lb_user(); return trim((string)($u['teacher_name']??$u['name']??$u['full_name']??'')); }
function lb_norm(string $v): string { return tkb_key($v); }
function lb_same(string $a,string $b): bool { return lb_norm($a)!==''&&lb_norm($a)===lb_norm($b); }
function lb_settings(): array { return array_merge(['grace_days'=>1,'lock_time'=>'23:59','auto_lock'=>true],(array)load_json(LB_SETTINGS_FILE,[])); }
function lb_weeks(): array { return function_exists('cds_school_week_calendar')?cds_school_week_calendar():[]; }
function lb_week(string $key=''): ?array { $weeks=lb_weeks(); if($key!=='')foreach($weeks as $w)if((string)($w['key']??'')===$key)return$w; $today=date('Y-m-d'); foreach($weeks as $w)if($today>=($w['start']??'')&&$today<=($w['end']??''))return$w; if(!$weeks)return null;$last=end($weeks);return is_array($last)?$last:null; }
function lb_tkb_week(array $week): ?array { foreach(tkb_weeks() as $w){if(($w['start_date']??'')===($week['start']??''))return$w;if(lb_norm((string)($w['label']??''))===lb_norm((string)($week['label']??'')))return$w;} return null; }
function lb_grade(string $class): string { return preg_match('/\d+/', $class,$m)?$m[0]:''; }
function lb_slot_id(array $week,array $slot): string { return hash('sha256',implode('|',[(string)($week['key']??''),(string)($slot['date']??''),(string)($slot['session']??''),(string)($slot['period']??''),(string)($slot['class']??''),(string)($slot['subject']??''),(string)($slot['book_type']??'main')])); }
function lb_record_map(): array { $out=[]; foreach(lb_rows(LB_RECORDS_FILE) as $r)$out[(string)($r['slot_id']??'')]=$r; return$out; }
function lb_curriculum(): array { return lb_rows(LB_CURRICULUM_FILE); }
function lb_curriculum_for(string $subject,string $grade,$ppct): ?array { foreach(lb_curriculum() as$r)if(lb_same((string)($r['subject']??''),$subject)&&(string)($r['grade']??'')===(string)$grade&&(int)($r['period']??0)===(int)$ppct)return$r; return null; }
function lb_slots(array $week): array {
    $tkb=lb_tkb_week($week); if(!$tkb)return[]; $saved=lb_record_map(); $subs=tkb_week_substitutions($tkb,true); $out=[];
    foreach(tkb_resolved_slots($tkb) as $slot){
        $class=trim((string)($slot['class']??'')); $teacher=trim((string)($slot['teacher']??'')); if($class===''||$teacher==='')continue;
        $date=tkb_week_slot_date($tkb,(int)($slot['day']??2)); $actual=$teacher; $sub=tkb_substitution_for_slot($tkb,$slot); if($sub&&trim((string)($sub['substitute_teacher']??''))!=='')$actual=trim((string)$sub['substitute_teacher']);
        $base=['week_key'=>(string)$week['key'],'week_label'=>(string)$week['label'],'date'=>$date,'day'=>(int)($slot['day']??2),'session'=>(string)($slot['session']??'Sáng'),'period'=>(int)($slot['period']??0),'class'=>$class,'subject'=>(string)($slot['subject']??''),'scheduled_teacher'=>$teacher,'actual_teacher'=>$actual,'book_type'=>'main','grade'=>lb_grade($class)];
        $base['slot_id']=lb_slot_id($week,$base); $row=array_merge($base,(array)($saved[$base['slot_id']]??[]));
        $ppct=(int)($row['ppct_period']??0); if($ppct&&trim((string)($row['lesson_title']??''))===''){ $c=lb_curriculum_for($base['subject'],$base['grade'],$ppct); if($c)$row['lesson_title']=$c['title']??''; }
        $out[]=$row;
    }
    usort($out,fn($a,$b)=>strcmp(($a['date'].'|'.$a['session'].'|'.sprintf('%03d',$a['period']).'|'.$a['class']),($b['date'].'|'.$b['session'].'|'.sprintf('%03d',$b['period']).'|'.$b['class']))); return$out;
}
function lb_can_edit(array $row): bool { if(lb_is_admin())return true; $me=lb_teacher_name(); return lb_same($me,(string)($row['actual_teacher']??''))||lb_same($me,(string)($row['scheduled_teacher']??'')); }
function lb_can_view(array $row): bool { if(lb_is_admin()||lb_can_edit($row))return true;$u=lb_user();foreach(array_merge((array)($u['classes']??[]),(array)($u['homeroom_classes']??[]))as$class)if(lb_same((string)$class,(string)($row['class']??'')))return true;if(!lb_is_leader())return false;$me=lb_teacher_name();$group=$me!==''&&function_exists('get_teacher_group')?(string)get_teacher_group($me):'';$teacher=(string)($row['actual_teacher']??$row['scheduled_teacher']??'');return $group!==''&&function_exists('get_teacher_group')&&lb_same($group,(string)get_teacher_group($teacher)); }
function lb_lock_key(string $week,string $class,string $type): string { return lb_norm($week).'|'.lb_norm($class).'|'.lb_norm($type); }
function lb_lock_info(array $week,string $class,string $type='main'): array {
    $key=lb_lock_key((string)$week['key'],$class,$type); foreach(lb_rows(LB_LOCKS_FILE) as$r)if(($r['key']??'')===$key)return$r;
    $s=lb_settings(); $deadline=date('Y-m-d H:i:s',strtotime(($week['end']??date('Y-m-d')).' +'.max(0,(int)$s['grace_days']).' days '.($s['lock_time']??'23:59')));
    return ['key'=>$key,'locked'=>!empty($s['auto_lock'])&&date('Y-m-d H:i:s')>$deadline,'automatic'=>true,'deadline'=>$deadline];
}
function lb_locked(array $week,array $row): bool { return !empty(lb_lock_info($week,(string)$row['class'],(string)($row['book_type']??'main'))['locked']); }
function lb_audit(string $action,array $data=[]): void { $rows=lb_rows(LB_AUDIT_FILE); array_unshift($rows,array_merge(['id'=>lb_id('audit'),'at'=>date('c'),'by'=>lb_teacher_name(),'action'=>$action],$data)); lb_write(LB_AUDIT_FILE,array_slice($rows,0,5000)); }
function lb_save_record(array $week,array $input): array {
    $slotId=trim((string)($input['slot_id']??'')); $slot=null; foreach(lb_slots($week) as$r)if(($r['slot_id']??'')===$slotId){$slot=$r;break;} if(!$slot)return['ok'=>false,'message'=>'Không tìm thấy tiết học trong TKB tuần đã chọn.'];
    if(!lb_can_edit($slot))return['ok'=>false,'message'=>'Bạn không được nhập tiết học này.']; if(lb_locked($week,$slot))return['ok'=>false,'message'=>'Sổ của lớp này đã khóa.'];
    $allowedStatus=['pending','taught','substitute','makeup','online','holiday','teacher_absent','class_absent','postponed','cancelled']; $status=(string)($input['status']??'taught'); if(!in_array($status,$allowedStatus,true))$status='taught';
    $rating=(string)($input['rating']??''); if(!in_array($rating,['','Tốt','Khá','Trung bình','Yếu'],true))$rating='';
    $bookType=in_array((string)($input['book_type']??'main'),['main','session2'],true)?(string)$input['book_type']:'main';
    $changes=['book_type'=>$bookType,'ppct_period'=>max(0,(int)($input['ppct_period']??0)),'lesson_title'=>trim((string)($input['lesson_title']??'')),'absent_names'=>trim((string)($input['absent_names']??'')),'teacher_comment'=>trim((string)($input['teacher_comment']??'')),'rating'=>$rating,'status'=>$status,'actual_teacher'=>trim((string)($input['actual_teacher']??$slot['actual_teacher']))?:$slot['actual_teacher'],'actual_periods'=>max(0,(float)($input['actual_periods']??1)),'updated_at'=>date('c'),'updated_by'=>lb_teacher_name()];
    if($changes['ppct_period']&&$changes['lesson_title']===''){ $c=lb_curriculum_for((string)$slot['subject'],(string)$slot['grade'],$changes['ppct_period']); if($c)$changes['lesson_title']=(string)($c['title']??''); }
    $records=lb_rows(LB_RECORDS_FILE); $found=false; foreach($records as&$r)if(($r['slot_id']??'')===$slotId){$r=array_merge($r,$slot,$changes);$found=true;break;}unset($r); if(!$found)$records[]=array_merge($slot,$changes,['created_at'=>date('c')]); lb_write(LB_RECORDS_FILE,$records); lb_audit('save_record',['slot_id'=>$slotId,'status'=>$status]); return['ok'=>true,'message'=>'Đã lưu sổ đầu bài.'];
}
function lb_signature_meta(): ?array { $u=lb_user(); $uid=(string)($u['id']??lb_norm(lb_teacher_name())); foreach(lb_rows(DATA_PATH.'/lesson_book_signatures.json')as$r)if(($r['user_id']??'')===$uid)return$r; return null; }
function lb_upload_signature(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return['ok'=>false,'message'=>'Chưa chọn ảnh chữ ký.']; if(!function_exists('imagecreatetruecolor'))return['ok'=>false,'message'=>'Máy chủ chưa bật thư viện GD để chuẩn hóa ảnh chữ ký.'];
    $info=@getimagesize($file['tmp_name']); $mime=$info['mime']??''; $src=$mime==='image/png'?@imagecreatefrompng($file['tmp_name']):($mime==='image/jpeg'?@imagecreatefromjpeg($file['tmp_name']):null); if(!$src)return['ok'=>false,'message'=>'Chỉ nhận ảnh PNG hoặc JPG hợp lệ.'];
    $w=imagesx($src);$h=imagesy($src);$minX=$w;$minY=$h;$maxX=0;$maxY=0; for($y=0;$y<$h;$y+=max(1,(int)floor($h/600)))for($x=0;$x<$w;$x+=max(1,(int)floor($w/1000))){$c=imagecolorat($src,$x,$y);$r=($c>>16)&255;$g=($c>>8)&255;$b=$c&255;$a=($c>>24)&127;if($a<120&&min($r,$g,$b)<238){$minX=min($minX,$x);$maxX=max($maxX,$x);$minY=min($minY,$y);$maxY=max($maxY,$y);}}
    if($minX>$maxX){imagedestroy($src);return['ok'=>false,'message'=>'Không nhận thấy nét chữ ký trong ảnh.'];} $cw=max(1,$maxX-$minX+1);$ch=max(1,$maxY-$minY+1);$dst=imagecreatetruecolor(480,160);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,255,255,255,127);imagefill($dst,0,0,$transparent);$scale=min(440/$cw,120/$ch);$nw=(int)round($cw*$scale);$nh=(int)round($ch*$scale);imagecopyresampled($dst,$src,(int)((480-$nw)/2),(int)((160-$nh)/2),$minX,$minY,$nw,$nh,$cw,$ch);
    if(!is_dir(LB_SIGNATURES_DIR))@mkdir(LB_SIGNATURES_DIR,0775,true);$u=lb_user();$uid=(string)($u['id']??lb_norm(lb_teacher_name()));$version=date('YmdHis');$path=LB_SIGNATURES_DIR.'/'.$uid.'_'.$version.'.png';imagepng($dst,$path,9);imagedestroy($src);imagedestroy($dst);
    $rows=lb_rows(DATA_PATH.'/lesson_book_signatures.json');$rows[]= ['id'=>lb_id('sig'),'user_id'=>$uid,'teacher'=>lb_teacher_name(),'path'=>$path,'version'=>$version,'created_at'=>date('c')];lb_write(DATA_PATH.'/lesson_book_signatures.json',$rows);lb_audit('upload_signature',['version'=>$version]);return['ok'=>true,'message'=>'Đã chuẩn hóa và lưu ảnh chữ ký.'];
}
function lb_sign(array $week,string $slotId): array { foreach(lb_slots($week)as$row)if(($row['slot_id']??'')===$slotId){if(!lb_can_edit($row))return['ok'=>false,'message'=>'Bạn không được ký tiết này.'];if(lb_locked($week,$row))return['ok'=>false,'message'=>'Sổ đã khóa.'];$sig=lb_signature_meta();if(!$sig)return['ok'=>false,'message'=>'Hãy tải ảnh chữ ký trước khi ký.'];$records=lb_rows(LB_RECORDS_FILE);foreach($records as&$r)if(($r['slot_id']??'')===$slotId){$r['signed_at']=date('c');$r['signed_by']=lb_teacher_name();$r['signature_path']=$sig['path'];$r['signature_version']=$sig['version'];lb_write(LB_RECORDS_FILE,$records);lb_audit('sign_record',['slot_id'=>$slotId,'signature_version'=>$sig['version']]);return['ok'=>true,'message'=>'Đã ký tiết học.'];}return['ok'=>false,'message'=>'Cần lưu nội dung tiết học trước khi ký.'];}return['ok'=>false,'message'=>'Không tìm thấy tiết học.']; }
function lb_save_curriculum(array $in): array { if(!lb_is_admin())return['ok'=>false,'message'=>'Chỉ quản trị được sửa PPCT.'];$subject=trim((string)($in['subject']??''));$grade=trim((string)($in['grade']??''));$period=(int)($in['period']??0);$title=trim((string)($in['title']??''));if($subject===''||$grade===''||$period<1||$title==='')return['ok'=>false,'message'=>'Nhập đủ môn, khối, tiết PPCT và tên bài.'];$rows=lb_curriculum();$found=false;foreach($rows as&$r)if(lb_same((string)($r['subject']??''),$subject)&&(string)$r['grade']===$grade&&(int)$r['period']===$period){$r=array_merge($r,['subject'=>$subject,'grade'=>$grade,'period'=>$period,'title'=>$title]);$found=true;break;}unset($r);if(!$found)$rows[]=['id'=>lb_id('ppct'),'subject'=>$subject,'grade'=>$grade,'period'=>$period,'title'=>$title];lb_write(LB_CURRICULUM_FILE,$rows);return['ok'=>true,'message'=>'Đã lưu PPCT.']; }
function lb_import_curriculum(array $file): array {
    if(!lb_is_admin())return['ok'=>false,'message'=>'Chỉ quản trị được nhập PPCT.'];
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return['ok'=>false,'message'=>'Chưa chọn tệp Excel PPCT.'];
    if(strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='xlsx')return['ok'=>false,'message'=>'PPCT chỉ nhận tệp .xlsx theo mẫu.'];
    $raw=tkb_xlsx_rows((string)$file['tmp_name']);if(empty($raw['ok']))return$raw;
    $headerRow=0;$columns=[];$aliases=['mon'=>'subject','monhoc'=>'subject','khoi'=>'grade','khoilop'=>'grade','tiet'=>'period','tietppct'=>'period','ppct'=>'period','tenbai'=>'title','tenbainoidung'=>'title','noidung'=>'title','noidungcongviec'=>'title','ghichu'=>'note'];
    foreach((array)$raw['rows']as$n=>$row){$found=[];foreach($row as$col=>$value){$key=lb_norm((string)$value);if(isset($aliases[$key]))$found[$aliases[$key]]=(int)$col;}if(isset($found['subject'],$found['grade'],$found['period'],$found['title'])){$headerRow=(int)$n;$columns=$found;break;}}
    if(!$headerRow)return['ok'=>false,'message'=>'Không tìm thấy đủ 4 cột: Môn, Khối, Tiết PPCT, Tên bài / nội dung.'];
    $incoming=[];$errors=[];
    foreach((array)$raw['rows']as$n=>$row){if((int)$n<=$headerRow)continue;$subject=trim((string)($row[$columns['subject']]??''));$grade=trim((string)($row[$columns['grade']]??''));$periodRaw=trim((string)($row[$columns['period']]??''));$title=trim((string)($row[$columns['title']]??''));$note=isset($columns['note'])?trim((string)($row[$columns['note']]??'')):'';if($subject===''&&$grade===''&&$periodRaw===''&&$title==='')continue;if($subject===''||$grade===''||!is_numeric($periodRaw)||(int)$periodRaw<1||$title===''){$errors[]='Dòng '.$n.' chưa đủ hoặc sai Môn, Khối, Tiết PPCT, Tên bài.';continue;}$period=(int)$periodRaw;$key=lb_norm($subject).'|'.lb_norm($grade).'|'.$period;$incoming[$key]=['subject'=>$subject,'grade'=>$grade,'period'=>$period,'title'=>$title,'note'=>$note];}
    if($errors)return['ok'=>false,'message'=>'Chưa nhập vì tệp có '.count($errors).' dòng lỗi: '.implode(' ',array_slice($errors,0,5)).(count($errors)>5?' …':'')];
    if(!$incoming)return['ok'=>false,'message'=>'Tệp Excel không có dòng PPCT hợp lệ.'];
    $rows=lb_curriculum();$index=[];foreach($rows as$i=>$r)$index[lb_norm((string)($r['subject']??'')).'|'.lb_norm((string)($r['grade']??'')).'|'.(int)($r['period']??0)]=$i;$added=0;$updated=0;
    foreach($incoming as$key=>$item){if(isset($index[$key])){$rows[$index[$key]]=array_merge($rows[$index[$key]],$item,['updated_at'=>date('c'),'updated_by'=>lb_teacher_name()]);$updated++;}else{$rows[]=array_merge(['id'=>lb_id('ppct')],$item,['created_at'=>date('c'),'created_by'=>lb_teacher_name()]);$added++;}}
    usort($rows,fn($a,$b)=>strcmp(lb_norm((string)($a['subject']??'')).'|'.sprintf('%02d',(int)($a['grade']??0)).'|'.sprintf('%05d',(int)($a['period']??0)),lb_norm((string)($b['subject']??'')).'|'.sprintf('%02d',(int)($b['grade']??0)).'|'.sprintf('%05d',(int)($b['period']??0))));
    if(!lb_write(LB_CURRICULUM_FILE,$rows))return['ok'=>false,'message'=>'Không ghi được dữ liệu PPCT.'];lb_audit('import_curriculum',['added'=>$added,'updated'=>$updated,'total'=>count($incoming)]);return['ok'=>true,'message'=>'Đã nhập '.count($incoming).' dòng PPCT: thêm '.$added.', cập nhật '.$updated.'.'];
}
function lb_stat_rows(string $from,string $to,string $teacher='',string $subject='',string $class=''): array {
    $out=[];foreach(lb_rows(LB_RECORDS_FILE)as$r){$date=(string)($r['date']??'');if($date===''||($from!==''&&$date<$from)||($to!==''&&$date>$to)||!lb_can_view($r))continue;if($teacher!==''&&!lb_same($teacher,(string)($r['actual_teacher']??'')))continue;if($subject!==''&&!lb_same($subject,(string)($r['subject']??'')))continue;if($class!==''&&!lb_same($class,(string)($r['class']??'')))continue;$out[]=$r;}return$out;
}
function lb_stat_group(array $rows,string $field): array {
    $groups=[];$actualStatuses=['taught','substitute','makeup','online'];foreach($rows as$r){$name=trim((string)($r[$field]??''))?:'Chưa xác định';if(!isset($groups[$name]))$groups[$name]=['name'=>$name,'records'=>0,'completed'=>0,'actual'=>0.0,'substitute'=>0,'cancelled'=>0,'signed'=>0];$groups[$name]['records']++;$status=(string)($r['status']??'pending');if($status!=='pending')$groups[$name]['completed']++;if(in_array($status,$actualStatuses,true))$groups[$name]['actual']+=(float)($r['actual_periods']??1);if($status==='substitute')$groups[$name]['substitute']++;if(in_array($status,['teacher_absent','class_absent','postponed','cancelled'],true))$groups[$name]['cancelled']++;if(!empty($r['signed_at']))$groups[$name]['signed']++;}uasort($groups,fn($a,$b)=>$b['actual']<=>$a['actual']?:strnatcasecmp($a['name'],$b['name']));return array_values($groups);
}
function lb_set_lock(array $week,array $classes,bool $locked,string $reason=''): array { if(!lb_is_admin())return['ok'=>false,'message'=>'Chỉ quản trị được khóa/mở sổ.'];$rows=lb_rows(LB_LOCKS_FILE);foreach($classes as$class){$key=lb_lock_key((string)$week['key'],(string)$class,'main');$found=false;foreach($rows as&$r)if(($r['key']??'')===$key){$r=array_merge($r,['locked'=>$locked,'reason'=>$reason,'at'=>date('c'),'by'=>lb_teacher_name()]);$found=true;break;}unset($r);if(!$found)$rows[]=['key'=>$key,'week_key'=>$week['key'],'class'=>$class,'type'=>'main','locked'=>$locked,'reason'=>$reason,'at'=>date('c'),'by'=>lb_teacher_name()];}lb_write(LB_LOCKS_FILE,$rows);lb_audit($locked?'lock':'unlock',['classes'=>$classes,'reason'=>$reason]);return['ok'=>true,'message'=>$locked?'Đã khóa sổ.':'Đã mở khóa sổ.']; }
