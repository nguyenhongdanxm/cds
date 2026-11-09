<?php
/** Reviewed read/export actions. Never accept an arbitrary URL, PHP path or action. */
function xlsched_catalog(): array {
    $rows=[];
    $add=static function($id,$label,$path,$fixed=[],$fields=[],$format='xlsx',$date=[])use(&$rows){$rows[$id]=compact('id','label','path','fixed','fields','format','date')+['method'=>'GET'];};
    $range=['from','to'];$class=['class','grade','q'];
    foreach(['teachers'=>'Giáo viên','classes'=>'Lớp','students'=>'Học sinh']as$entity=>$label){
        $add('csdl_'.$entity,'CSDL · '.$label,'csdl_export_filtered_excel.php',['entity'=>$entity],['q','grade','class','status','split','gender','fields','ids'],'xls');
        $add('csv_'.$entity,'CSDL · '.$label.' (CSV)','csdl_export.php',['entity'=>$entity,'mode'=>'export'],['fields','ids'],'csv');
        $add('template_'.$entity,'Mẫu nhập · '.$label,'csdl_export.php',['entity'=>$entity,'mode'=>'template'],[],'csv');
    }
    $add('csdl_stats','CSDL · Thống kê','csdl_statistics_export_xlsx.php',[],['stat_scope','stat_status','stat_team','stat_grade','stat_class']);
    $add('lb_class','Sổ đầu bài · Theo lớp/tuần','chuyenmon/sodaubai_export.php',[],['week','class'],'xlsx',['week'=>'week']);
    $add('lb_stats','Sổ đầu bài · Thống kê ký sổ','chuyenmon/sodaubai_stats_export.php',['format'=>'xlsx'],['stats_from','stats_to','stats_teacher','stats_subject','stats_class','stats_completion'],'xlsx',['from'=>'stats_from','to'=>'stats_to']);
    $add('lb_stats_csv','Sổ đầu bài · Thống kê (CSV)','chuyenmon/sodaubai_stats_export.php',['format'=>'csv'],['stats_from','stats_to','stats_teacher','stats_subject','stats_class','stats_completion'],'csv',['from'=>'stats_from','to'=>'stats_to']);
    $rows['lb_stats']['optional_fixed']=['format'];
    $add('hours','Chuyên môn · Tổng hợp giờ dạy','chuyenmon/gio_day_xuat.php',['download'=>'1'],['from','to','teacher','format'],'xlsx',['from'=>'from','to'=>'to']);
    $add('pccm','Chuyên môn · Phân công','chuyenmon/xuat.php',[],[],'csv');
    $add('online','Chuyên môn · Học online','chuyenmon/activities.php',['tab'=>'online','export'=>'list'],['class','status','q','from','to'],'csv',['from'=>'from','to'=>'to']);
    $add('tkb_plan','Thời khóa biểu · Phương án xếp','chuyenmon/tkb_print.php',['download'=>'excel'],['mode','class','teacher','workspace'],'xls');
    $add('tkb_builder','Thời khóa biểu · Bản nháp','tkb_xep.php',['download'=>'excel'],['tab','view','class','teacher','week_id','workspace'],'xls');
    $add('tkb_template','Thời khóa biểu · Mẫu nhập có dữ liệu','chuyenmon/thoikhoabieu.php',['download_template'=>'filled'],['template_week_id'],'xlsx',['timetable_week'=>'template_week_id']);
    $add('tkb_blank','Thời khóa biểu · Mẫu nhập trống','chuyenmon/thoikhoabieu.php',['download_template'=>'blank'],[]);
    foreach(['month_breakfast'=>'Ăn sáng tháng','month_lunch_dinner'=>'Ăn trưa/tối tháng']as$key=>$label)$add('meal_'.$key,'Nội trú · '.$label,'noitru.php',['tab'=>'meal_summary','export'=>$key],['month','class'],'xlsx',['month'=>'month']);
    foreach(['excel'=>['Tổng hợp báo ăn ngày','xls'],'kitchen'=>['Báo ăn nhà bếp','csv'],'period_csv'=>['Suất ăn theo khoảng ngày','csv'],'csv'=>['Tổng hợp suất ăn','csv']]as$key=>$v)$add('meal_'.$key,'Nội trú · '.$v[0],'noitru.php',['tab'=>'meal_summary','export'=>$key],['date','from','to','month','class'],$v[1],['day'=>'date','from'=>'from','to'=>'to','month'=>'month']);
    $add('rice','Nội trú · Gạo','noitru.php',['tab'=>'rice','export'=>'excel'],['period_type','month','from','to'],'xlsx',['month'=>'month','from'=>'from','to'=>'to']);
    $add('nt_stats','Nội trú · Thống kê','noitru.php',['tab'=>'stats','export'=>'csv'],['from','to','class','month'],'csv',['from'=>'from','to'=>'to','month'=>'month']);
    $add('attendance','Nội trú · Điểm danh','noitru_attendance.php',['view'=>'history','export'=>'excel'],['period_type','period_value','class','shift','q'],'xls',['attendance'=>'period_value']);
    $add('exit','Nội trú · Xin ra/vào','noitru_exit.php',['view'=>'history','export'=>'xlsx'],['period','date','from','to','class','q'],'xlsx',['from'=>'from','to'=>'to']);
    $add('health','Y tế · Khám chữa bệnh','noitru_health_excel.php',[],['range','date','student_id','health_class','health_q'],'xlsx',['health'=>'date']);
    $add('medicine','Y tế · Mẫu nhập thuốc','noitru_medicine_excel.php',['template'=>'1'],[]);
    $add('rooms','Nội trú · Mẫu chia phòng có dữ liệu','noitru_room_template.php',['data'=>'1'],[]);
    $add('rooms_current','Nội trú · Mẫu phòng hiện tại','noitru_assign.php',['mode'=>'rooms','download_template'=>'rooms'],[]);
    $add('equipment','Thư viện · Thiết bị có dữ liệu','thietbi_mau.php',[],[]);
    $add('documents','Văn bản · Danh mục/sổ số','vanban.php',['tab'=>'numbers','export_numbers'=>'xlsx'],['number_book','number_q','number_sort','number_dir','type','issuer_level','field']);
    $add('documents_csv','Văn bản · Danh mục/sổ số (CSV)','vanban.php',['tab'=>'numbers','export_numbers'=>'csv'],['number_book','number_q','number_sort','number_dir','type','issuer_level','field'],'csv');
    $add('rooms_blank','Nội trú · Mẫu chia phòng trống','noitru_room_template.php',['data'=>'0'],[]);$rows['rooms_blank']['optional_fixed']=['data'];
    $add('evaluation','Đánh giá · Tổng hợp hồ sơ','thidua_danhgia.php',['view'=>'annual','export'=>'csv'],['month','from_month','to_month','staff_id'],'csv',['month'=>'month','from_month'=>'from_month','to_month'=>'to_month']);
    $add('quiz','Học liệu · Kết quả Quiz','hoclieu_game_quiz.php',['export'=>'xlsx'],['set','report']);
    $add('quiz_csv','Học liệu · Kết quả Quiz (CSV)','hoclieu_game_quiz.php',['export'=>'csv'],['set','report'],'csv');
    $add('olympia','Học liệu · Olympia chi tiết','hoclieu_game_olympia_export_xlsx.php',[],['from','to','week','class','rank_from','rank_to','rank_class']);
    $add('olympia_stats','Học liệu · Olympia tổng hợp','hoclieu_game_olympia.php',['export_stats'=>'1'],['view','rank_from','rank_to','rank_class'],'xls');
    $add('olympia_questions','Học liệu · Câu hỏi Olympia','hoclieu_game_olympia.php',[],['export_week_ids']);
    $rows['olympia_questions']['method']='POST';$rows['olympia_questions']['fixed']=['action'=>'export_questions'];$rows['olympia_questions']['csrf_key']='olympia_csrf';
    $add('olympia_template','Học liệu · Mẫu Olympia','hoclieu_game_olympia_template.php',[],['stage']);
    $add('ppct_template','Chuyên môn · Mẫu PPCT','chuyenmon/sodaubai_ppct_template.php',[],[]);
    $add('gddp_template','Chuyên môn · Phân công GDĐP 1–35','chuyenmon/program_assignment_template.php',[],['class','grade','with_data','filled']);
    foreach(['month_breakfast','month_lunch_dinner']as$key)$rows['meal_'.$key]['aliases']=[['tab'=>'meals','export'=>$key]];
    foreach(['lb_class'=>['week','class'],'lb_stats'=>['stats_from','stats_to'],'lb_stats_csv'=>['stats_from','stats_to'],'hours'=>['from','to'],'tkb_template'=>['template_week_id'],'attendance'=>['period_value'],'quiz'=>['set','report'],'quiz_csv'=>['set','report'],'olympia_questions'=>['export_week_ids']]as$id=>$required)$rows[$id]['required']=$required;
    $rows['attendance']['policies']=['fixed','week','previous_week','month','previous_month'];
    foreach(['lb_class','tkb_template']as$id)$rows[$id]['policies']=['fixed','today','yesterday','week','previous_week'];
    // Static Excel templates are also selectable, but never arbitrary files/uploads.
    foreach(glob(dirname(__DIR__).'/assets/*.xlsx')?:[]as$file){$id='asset_'.substr(hash('sha256',basename($file)),0,12);$add($id,'Mẫu Excel · '.basename($file),'assets/'.basename($file));$rows[$id]['asset']=true;}
    return $rows;
}
function xlsched_report(string $id): array { $r=xlsched_catalog()[$id]??null;if(!$r)throw new InvalidArgumentException('Báo cáo chưa được đăng ký.');return $r; }
function xlsched_params(array $report,array $input): array {
    $out=[];
    foreach($report['fields'] as$key){if(!array_key_exists($key,$input))continue;$v=$input[$key];
        if(is_array($v)){if(!in_array($key,['ids','fields','export_week_ids'],true))throw new InvalidArgumentException('Bộ lọc phải là một giá trị.');if(count($v)>1000)throw new InvalidArgumentException('Danh sách lọc quá dài.');$out[$key]=[];foreach($v as$item){if(!is_scalar($item)||strlen((string)$item)>500)throw new InvalidArgumentException('Bộ lọc không hợp lệ.');$out[$key][]=trim((string)$item);}}
        else{if(!is_scalar($v)||strlen((string)$v)>1000||str_contains((string)$v,"\0"))throw new InvalidArgumentException('Bộ lọc không hợp lệ.');$out[$key]=trim((string)$v);}
    }
    if(strlen(json_encode($out))>40000)throw new InvalidArgumentException('Bộ lọc quá lớn.');
    return $out;
}
function xlsched_match(string $path,string $method,array $params): ?array {
    $path=ltrim($path,'/');foreach(xlsched_catalog() as$r){if($r['path']!==$path||$r['method']!==strtoupper($method))continue;foreach(array_merge([$r['fixed']],$r['aliases']??[])as$fixed){$ok=true;foreach($fixed as$k=>$v){$actual=$params[$k]??(in_array($k,$r['optional_fixed']??[],true)?$v:null);if(!is_scalar($actual)||(string)$actual!==$v){$ok=false;break;}}if($ok)return$r;}}return null;
}
function xlsched_hhmm(string $value): int {if(!preg_match('/^(\d{2}):(\d{2})$/D',$value,$m)||(int)$m[1]>23||(int)$m[2]>59)throw new InvalidArgumentException('Giờ không hợp lệ.');return(int)$m[1]*60+(int)$m[2];}
function xlsched_window(array $job,DateTimeImmutable $now): array {
    $now=$now->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));$s=xlsched_hhmm($job['start_time']);$e=xlsched_hhmm($job['end_time']);
    if($s===$e)throw new InvalidArgumentException('Khung giờ phải có thời điểm bắt đầu/kết thúc khác nhau.');
    $start=$now->setTime(intdiv($s,60),$s%60);$end=$now->setTime(intdiv($e,60),$e%60);
    if($e<$s){$end=$end->modify('+1 day');if($now<$start){$start=$start->modify('-1 day');$end=$end->modify('-1 day');}}
    $span=(int)(($end->getTimestamp()-$start->getTimestamp())/60);
    if($span<15)throw new InvalidArgumentException('Chọn khung giờ ít nhất 15 phút.');
    $period=$start->format('Y-m-d');$frequency=$job['frequency']??'daily';
    $dayOk=$frequency==='daily'||($frequency==='weekly'&&(int)$start->format('N')===(int)$job['day'])||($frequency==='monthly'&&(int)$start->format('j')===min((int)$job['day'],(int)$start->format('t')));
    $offset=hexdec(substr(hash('sha256',$job['id'].'|'.$period),0,7))%max(1,min(31,$span-14));
    $planned=$start->modify('+'.$offset.' minutes');
    return compact('start','end','planned','period')+['eligible'=>$dayOk,'due'=>$dayOk&&$now>=$planned&&$now<$end];
}
function xlsched_resolve_dates(array $report,array $params,string $policy,DateTimeImmutable $now,array $weeks=[]): array {
    $now=$now->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));$from=$to=$now->setTime(0,0);
    switch($policy){case'fixed':return$params;case'yesterday':$from=$to=$from->modify('-1 day');break;case'today':break;case'month':$from=$from->modify('first day of this month');break;case'previous_month':$from=$from->modify('first day of previous month');$to=$from->modify('last day of this month');break;case'week':$from=$from->modify('monday this week');break;case'previous_week':$from=$from->modify('monday last week');$to=$from->modify('+6 days');break;default:throw new InvalidArgumentException('Khoảng dữ liệu không hợp lệ.');}
    foreach($report['date']as$type=>$key){switch($type){case'from':$params[$key]=$from->format('Y-m-d');break;case'to':case'day':$params[$key]=$to->format('Y-m-d');break;case'month':$params[$key]=$from->format('Y-m');break;case'from_month':$params[$key]=$from->format('Y-m');break;case'to_month':$params[$key]=$to->format('Y-m');break;case'timetable_week':case'week':$found=null;foreach($weeks as$week)if(($week['start']??'')<=$to->format('Y-m-d')&&($week['end']??'')>=$to->format('Y-m-d')){$found=$week['key']??$week['id']??'';break;}if(!$found)throw new RuntimeException('Chưa có tuần học phù hợp với ngày xuất.');$params[$key]=(string)$found;break;}}
    if($report['id']==='attendance'){$params['period_type']=str_contains($policy,'month')?'month':'week';$params['period_value']=$from->format($params['period_type']==='month'?'Y-m':'o-\\WW');}
    if($report['id']==='health'){$params['range']=str_contains($policy,'month')?'month':(str_contains($policy,'week')?'week':'day');$params['date']=$to->format('Y-m-d');}
    if($report['id']==='exit')$params['period']='custom';
    if($report['id']==='rice')$params['period_type']=str_contains($policy,'month')?'month':'custom';
    return$params;
}
function xlsched_field_label(string $key): string {return ['class'=>'Lớp','grade'=>'Khối','teacher'=>'Giáo viên','subject'=>'Môn','q'=>'Tìm kiếm','from'=>'Từ ngày','to'=>'Đến ngày','date'=>'Ngày','month'=>'Tháng','week'=>'Tuần học (mã)','stats_from'=>'Từ ngày','stats_to'=>'Đến ngày','stats_teacher'=>'Giáo viên','stats_subject'=>'Môn','stats_class'=>'Lớp','stats_completion'=>'Tình trạng ký','health_from'=>'Từ ngày','health_to'=>'Đến ngày','health_class'=>'Lớp','health_q'=>'Tìm kiếm','from_month'=>'Từ tháng','to_month'=>'Đến tháng','export_week_ids'=>'Các tuần câu hỏi đã chọn','ids'=>'Các bản ghi đã chọn','fields'=>'Các cột đã chọn','status'=>'Trạng thái','scope'=>'Phạm vi','staff_id'=>'Nhân sự','set'=>'Bộ câu hỏi','report'=>'Mã phòng/báo cáo','period_type'=>'Loại kỳ','school_year'=>'Năm học','version'=>'Phiên bản','template_week_id'=>'Tuần mẫu (mã)'][$key]??str_replace('_',' ',$key);}
