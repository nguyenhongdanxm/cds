<?php
/** Kho dữ liệu và bộ đọc Excel cho Thời khóa biểu Chuyên môn. */
if (!defined('DATA_PATH')) require_once __DIR__ . '/config.php';
if (!function_exists('cds_school_week_calendar')) require_once dirname(__DIR__, 2) . '/includes/school_week_calendar.php';
if (!defined('TKB_WEEKS_FILE')) define('TKB_WEEKS_FILE', DATA_PATH . '/timetable_weeks.json');
if (!defined('TKB_MAPPING_FILE')) define('TKB_MAPPING_FILE', DATA_PATH . '/timetable_mapping.json');
if (!defined('TKB_SUBSTITUTIONS_FILE')) define('TKB_SUBSTITUTIONS_FILE', DATA_PATH . '/timetable_substitutions.json');
function tkb_load(string $file,$default=[]){return function_exists('load_json')?load_json($file,$default):(is_file($file)?(json_decode((string)file_get_contents($file),true)?:$default):$default);}
function tkb_save(string $file,$data):bool{return function_exists('save_json')?(bool)save_json($file,$data):(bool)file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));}
function tkb_key(string $value):string{$value=trim($value);if($value==='')return '';if(function_exists('iconv'))$value=(string)@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);$value=strtolower($value);return preg_replace('/[^a-z0-9]+/','',$value)??'';}

function tkb_subject_canonical(string $subject):string{
    $subject=trim($subject);$key=tkb_key($subject);
    if(in_array($key,['tienganh','anhvan'],true))return 'Ngoại ngữ';
    return$subject;
}
function tkb_col_index(string $letters):int{$n=0;foreach(str_split(strtoupper($letters)) as $c)$n=$n*26+(ord($c)-64);return $n;}
function tkb_teacher_alias(string $name):string{$parts=preg_split('/\s+/u',trim($name))?:[];if(!$parts)return '';$last=array_pop($parts);$prefix='';foreach($parts as $p)$prefix.=mb_substr($p,0,1,'UTF-8').'.';return $prefix.$last;}
function tkb_teacher_auto_map(string $alias,array $teachers):string{$wanted=tkb_key($alias);if($wanted==='')return '';$matches=[];foreach($teachers as $teacher){$teacher=trim((string)$teacher);if($teacher!==''&&(tkb_key($teacher)===$wanted||tkb_key(tkb_teacher_alias($teacher))===$wanted))$matches[]=$teacher;}return count(array_unique($matches))===1?$matches[0]:'';}
function tkb_class_auto_map(string $alias,array $classes):string{$wanted=tkb_key($alias);foreach($classes as $class)if(tkb_key((string)$class)===$wanted)return(string)$class;return '';}
function tkb_subject_auto_map(string $alias,array $subjects):string{$wanted=tkb_key(tkb_subject_canonical($alias));foreach($subjects as$subject)if(tkb_key(tkb_subject_canonical((string)$subject))===$wanted)return tkb_subject_canonical((string)$subject);return '';}
function tkb_xlsx_rows(string $path):array{if(!class_exists('ZipArchive'))return['ok'=>false,'message'=>'Hosting chưa bật ZipArchive để đọc file .xlsx.'];$zip=new ZipArchive();if($zip->open($path)!==true)return['ok'=>false,'message'=>'Không mở được file Excel.'];$shared=[];$sharedXml=$zip->getFromName('xl/sharedStrings.xml');if($sharedXml!==false){$xml=@simplexml_load_string($sharedXml);if($xml)foreach($xml->si as $si){$text='';if(isset($si->t))$text=(string)$si->t;else foreach($si->r as $run)$text.=(string)$run->t;$shared[]=$text;}}$sheetXml=$zip->getFromName('xl/worksheets/sheet1.xml');if($sheetXml===false){for($i=0;$i<$zip->numFiles;$i++){$name=$zip->getNameIndex($i);if(preg_match('~^xl/worksheets/sheet\d+\.xml$~',$name)){$sheetXml=$zip->getFromName($name);break;}}}$zip->close();if($sheetXml===false)return['ok'=>false,'message'=>'Không tìm thấy bảng tính trong Excel.'];$xml=@simplexml_load_string($sheetXml);if(!$xml)return['ok'=>false,'message'=>'File Excel không đọc được cấu trúc bảng tính.'];$rows=[];foreach($xml->sheetData->row as $row){$values=[];foreach($row->c as $cell){$ref=(string)$cell['r'];preg_match('/^([A-Z]+)/',$ref,$m);$col=tkb_col_index($m[1]??'A');$type=(string)$cell['t'];$value='';if($type==='s')$value=$shared[(int)$cell->v]??'';elseif($type==='inlineStr')$value=(string)($cell->is->t??'');else$value=(string)($cell->v??'');$values[$col]=trim($value);}$rows[(int)$row['r']]=$values;}return['ok'=>true,'rows'=>$rows];}
function tkb_parse_xlsx(string $path):array{$raw=tkb_xlsx_rows($path);if(empty($raw['ok']))return$raw;$rows=$raw['rows'];$headerRow=0;foreach($rows as $n=>$r)if(tkb_key((string)($r[1]??''))==='lop'&&tkb_key((string)($r[2]??''))==='buoi'&&tkb_key((string)($r[3]??''))==='tiet'){$headerRow=$n;break;}if(!$headerRow)return['ok'=>false,'message'=>'Không tìm thấy 3 cột Lớp – Buổi – Tiết.'];$slots=[];$lastClass='';$lastSession='';$days=[2=>4,3=>6,4=>8,5=>10,6=>12,7=>14];foreach($rows as $n=>$r){if($n<=$headerRow)continue;$class=trim((string)($r[1]??''));$session=trim((string)($r[2]??''));$period=trim((string)($r[3]??''));if($class!=='')$lastClass=$class;if($session!=='')$lastSession=$session;$class=$class?:$lastClass;$session=$session?:$lastSession;if($class===''||$period===''||!is_numeric($period))continue;foreach($days as $day=>$subjectCol){$subject=trim((string)($r[$subjectCol]??''));$teacher=trim((string)($r[$subjectCol+1]??''));if($subject===''&&$teacher==='')continue;$slots[]=['row'=>$n,'day'=>$day,'session'=>$session?:'Sáng','period'=>(int)$period,'class_raw'=>$class,'subject'=>$subject,'subject_raw'=>$subject,'teacher_raw'=>$teacher];}}return$slots?['ok'=>true,'slots'=>$slots,'header_row'=>$headerRow]:['ok'=>false,'message'=>'Không tìm thấy tiết học nào trong file Excel.'];}
function tkb_week_calendar_meta(array $week):?array{
    $key=trim((string)($week['school_week_key']??''));
    if($key!==''){$meta=cds_school_week_by_key($key);if($meta)return$meta;}
    $start=(string)($week['start_date']??'');
    foreach(cds_school_week_calendar() as $meta)if($start!==''&&$start===(string)($meta['start']??''))return$meta;
    return null;
}
function tkb_normalize_weeks(array $rows):array{
    $unique=[];$order=[];
    foreach(array_values(array_filter($rows,'is_array')) as $index=>$week){
        if(!isset($week['slots'])||!is_array($week['slots']))$week['slots']=[];foreach($week['slots']as&$slot)if(is_array($slot)&&isset($slot['subject']))$slot['subject']=tkb_subject_canonical((string)$slot['subject']);unset($slot);
        $meta=tkb_week_calendar_meta($week);
        if($meta){
            $key=(string)$meta['key'];$week['school_week_key']=$key;$week['label']=(string)$meta['label'];
            $week['start_date']=(string)$meta['start'];
            $week['end_date']=date('Y-m-d',strtotime($week['start_date'].' +5 days'));
            $identity='school:'.$key;
        }else{$identity='legacy:'.((string)($week['id']??'')?:$index);}
        $stamp=strtotime((string)($week['uploaded_at']??''))?:0;
        if(!isset($unique[$identity])){
            $unique[$identity]=['week'=>$week,'stamp'=>$stamp,'index'=>$index];$order[]=$identity;continue;
        }
        $current=$unique[$identity];
        if($stamp>$current['stamp']||($stamp===$current['stamp']&&$index>$current['index']))
            $unique[$identity]=['week'=>$week,'stamp'=>$stamp,'index'=>$index];
    }
    $out=[];foreach($order as $identity)$out[]=$unique[$identity]['week'];
    return$out;
}
function tkb_weeks():array{
    $raw=tkb_load(TKB_WEEKS_FILE,[]);$raw=is_array($raw)?array_values(array_filter($raw,'is_array')):[];
    $rows=tkb_normalize_weeks($raw);
    if(json_encode($raw,JSON_UNESCAPED_UNICODE)!==json_encode($rows,JSON_UNESCAPED_UNICODE))tkb_save(TKB_WEEKS_FILE,$rows);
    return$rows;
}
function tkb_active_week():?array{$weeks=tkb_weeks();foreach($weeks as $w)if(!empty($w['active']))return$w;return$weeks?end($weeks):null;}
function tkb_week_by_id(string $id):?array{foreach(tkb_weeks() as $w)if((string)($w['id']??'')===$id)return$w;return null;}
function tkb_mapping():array{$m=tkb_load(TKB_MAPPING_FILE,['teachers'=>[],'classes'=>[],'subjects'=>[]]);return['teachers'=>(array)($m['teachers']??[]),'classes'=>(array)($m['classes']??[]),'subjects'=>(array)($m['subjects']??[])];}
function tkb_resolve_slot(array $slot,array $mapping):array{$slot['teacher']=(string)($mapping['teachers'][$slot['teacher_raw']??'']??'');$slot['class']=(string)($mapping['classes'][$slot['class_raw']??'']??'');$rawSubject=(string)($slot['subject_raw']??$slot['subject']??'');$slot['subject']=tkb_subject_canonical((string)($mapping['subjects'][$rawSubject]??$rawSubject));return$slot;}
function tkb_resolved_slots(array $week):array{$mapping=tkb_mapping();$slots=array_values(array_filter((array)($week['slots']??[]),'is_array'));return array_map(function($slot)use($mapping){$slot=tkb_resolve_slot($slot,$mapping);$slot['subject']=tkb_subject_canonical((string)($slot['subject']??''));return$slot;},$slots);}
function tkb_aliases(array $week,string $field):array{$out=[];foreach((array)($week['slots']??[])as$slot){$v=trim((string)($slot[$field]??''));if($v!=='')$out[$v]=true;}return array_keys($out);}
function tkb_import_week(string $path,string $schoolWeekKey,array $teachers,array $classes,string $by='',array $subjects=[]):array{
    $schoolWeek=cds_school_week_by_key($schoolWeekKey);
    if(!$schoolWeek)return['ok'=>false,'message'=>'Tuần học không hợp lệ hoặc không còn trong lịch tuần CSDL.'];
    $parsed=tkb_parse_xlsx($path);if(empty($parsed['ok']))return$parsed;
    $mapping=tkb_mapping();$teacherAliases=[];$classAliases=[];$subjectAliases=[];
    foreach($parsed['slots']as$slot){if(trim((string)$slot['teacher_raw'])!=='')$teacherAliases[$slot['teacher_raw']]=true;$classAliases[$slot['class_raw']]=true;$rawSubject=trim((string)($slot['subject_raw']??$slot['subject']??''));if($rawSubject!=='')$subjectAliases[$rawSubject]=true;}
    foreach(array_keys($teacherAliases)as$alias)if(empty($mapping['teachers'][$alias])){$auto=tkb_teacher_auto_map($alias,$teachers);if($auto!=='')$mapping['teachers'][$alias]=$auto;}
    foreach(array_keys($classAliases)as$alias)if(empty($mapping['classes'][$alias])){$auto=tkb_class_auto_map($alias,$classes);if($auto!=='')$mapping['classes'][$alias]=$auto;}
    foreach(array_keys($subjectAliases)as$alias)if(empty($mapping['subjects'][$alias])){$auto=tkb_subject_auto_map($alias,$subjects);if($auto!=='')$mapping['subjects'][$alias]=$auto;}
    tkb_save(TKB_MAPPING_FILE,$mapping);
    $weeks=tkb_weeks();$replaceAt=null;$existingId='';
    foreach($weeks as$i=>&$w){$w['active']=false;if((string)($w['school_week_key']??'')===$schoolWeekKey){$replaceAt=$i;$existingId=(string)($w['id']??'');}}unset($w);
    $week=['id'=>$existingId?:('tkb_'.date('YmdHis')),'label'=>(string)$schoolWeek['label'],'school_week_key'=>$schoolWeekKey,'start_date'=>(string)$schoolWeek['start'],'end_date'=>date('Y-m-d',strtotime((string)$schoolWeek['start'].' +5 days')),'active'=>true,'uploaded_at'=>date('c'),'uploaded_by'=>$by,'source'=>'excel','slots'=>$parsed['slots']];
    if($replaceAt===null)$weeks[]=$week;else$weeks[$replaceAt]=$week;
    $weeks=tkb_normalize_weeks($weeks);
    if(!tkb_save(TKB_WEEKS_FILE,$weeks))return['ok'=>false,'message'=>'Không lưu được thời khóa biểu tuần.'];
    return['ok'=>true,'week'=>$week,'message'=>'Đã cập nhật '.$week['label'].' với '.count($parsed['slots']).' tiết; tuần này chỉ còn một bản TKB.'];
}
function tkb_save_mapping(array $teacherMap,array $classMap,array $subjectMap=[]):bool{$mapping=tkb_mapping();foreach($teacherMap as$raw=>$value){$raw=trim((string)$raw);$value=trim((string)$value);if($raw==='')continue;if($value==='')unset($mapping['teachers'][$raw]);else$mapping['teachers'][$raw]=$value;}foreach($classMap as$raw=>$value){$raw=trim((string)$raw);$value=trim((string)$value);if($raw==='')continue;if($value==='')unset($mapping['classes'][$raw]);else$mapping['classes'][$raw]=$value;}foreach($subjectMap as$raw=>$value){$raw=trim((string)$raw);$value=tkb_subject_canonical(trim((string)$value));if($raw==='')continue;if($value==='')unset($mapping['subjects'][$raw]);else$mapping['subjects'][$raw]=$value;}return tkb_save(TKB_MAPPING_FILE,$mapping);}
function tkb_substitutions():array{$rows=tkb_load(TKB_SUBSTITUTIONS_FILE,[]);return is_array($rows)?array_values(array_filter($rows,'is_array')):[];}
function tkb_substitution_status(array $row):string{return(string)($row['status']??'approved');}
function tkb_slot_key(array $slot):string{return implode('|',[(string)($slot['day']??''),(string)($slot['session']??''),(string)($slot['period']??''),(string)($slot['class_raw']??'')]);}
function tkb_week_slot_date(array $week,int $day):string{return date('Y-m-d',strtotime((string)($week['start_date']??date('Y-m-d')).' +'.max(0,$day-2).' days'));}
function tkb_week_substitutions(array $week,bool $approvedOnly=true):array{$out=[];foreach(tkb_substitutions()as$row)if(($row['week_id']??'')===($week['id']??'')&&(!$approvedOnly||tkb_substitution_status($row)==='approved'))$out[]=$row;return$out;}
function tkb_substitution_for_slot(array $week,array $slot):?array{$date=tkb_week_slot_date($week,(int)($slot['day']??2));$key=tkb_slot_key($slot);foreach(tkb_week_substitutions($week,true)as$row)if(($row['date']??'')===$date&&($row['slot_key']??'')===$key)return$row;return null;}
function tkb_teacher_busy(array $week,string $teacher,int $day,string $session,int $period,string $date=''):bool{foreach(tkb_resolved_slots($week)as$slot)if(($slot['teacher']??'')===$teacher&&(int)$slot['day']===$day&&(string)$slot['session']===$session&&(int)$slot['period']===$period)return true;if($date!=='')foreach(tkb_substitutions()as$row)if(tkb_substitution_status($row)==='approved'&&($row['date']??'')===$date&&($row['substitute_teacher']??'')===$teacher&&($row['session']??'')===$session&&(int)($row['period']??0)===$period)return true;return false;}
function tkb_save_substitution(array $row):bool{$rows=tkb_substitutions();$id=(string)($row['id']??'');$key=($row['date']??'').'|'.($row['slot_key']??'');$found=false;foreach($rows as&$old){if(($id!==''&&($old['id']??'')===$id)||(($old['date']??'').'|'.($old['slot_key']??''))===$key){$row['id']=$old['id']??($id?:'sub_'.bin2hex(random_bytes(6)));$old=array_merge($old,$row);$found=true;break;}}unset($old);if(!$found){if($id==='')$row['id']='sub_'.bin2hex(random_bytes(6));$rows[]=$row;}return tkb_save(TKB_SUBSTITUTIONS_FILE,$rows);}
function tkb_update_substitution(string $id,array $changes):bool{$rows=tkb_substitutions();$found=false;foreach($rows as&$row)if(($row['id']??'')===$id){$row=array_merge($row,$changes);$found=true;break;}unset($row);return$found&&tkb_save(TKB_SUBSTITUTIONS_FILE,$rows);}
function tkb_subject_related(string $a,string $b):bool{$ka=tkb_key(tkb_subject_canonical($a));$kb=tkb_key(tkb_subject_canonical($b));if($ka===''||$kb==='')return false;if($ka===$kb)return true;foreach([['khtn','sinhhoc'],['khtn','hoahoc'],['khtn','vatli'],['khtn','ly'],['lsdl','lichsu'],['lsdl','diali'],['nghethuat','amnhac'],['nghethuat','mythuat']]as$pair)if((str_contains($ka,$pair[0])&&str_contains($kb,$pair[1]))||(str_contains($kb,$pair[0])&&str_contains($ka,$pair[1])))return true;return false;}
function tkb_teacher_fit(string $teacher,array $target,array $week,string $date=''):array{$class=(string)($target['class']?:($target['class_raw']??''));$subject=(string)($target['subject']??'');$grade=function_exists('get_grade')?get_grade($class):preg_replace('/[^0-9]/','',$class);$sameClass=false;$sameGrade=false;$sameSubject=false;if(function_exists('get_assignments'))foreach(get_assignments()as$a){if(($a['teacher']??'')!==$teacher)continue;$aClass=(string)($a['class']??'');$aSubject=(string)($a['subject']??'');if($aClass!==''&&tkb_key($aClass)===tkb_key($class))$sameClass=true;$aGrade=function_exists('get_grade')?get_grade($aClass):preg_replace('/[^0-9]/','',$aClass);if($grade!==''&&$aGrade===$grade)$sameGrade=true;if(tkb_subject_related($aSubject,$subject))$sameSubject=true;}if(function_exists('get_teacher_chuyen_mon'))foreach((array)get_teacher_chuyen_mon($teacher)as$spec)if(tkb_subject_related((string)$spec,$subject)){$sameSubject=true;break;}$busy=tkb_teacher_busy($week,$teacher,(int)($target['day']??0),(string)($target['session']??''),(int)($target['period']??0),$date);$score=($sameSubject?100:0)+($sameClass?30:0)+($sameGrade?10:0);$labels=[];if($sameSubject)$labels[]='Cùng chuyên môn';if($sameClass)$labels[]='Có dạy lớp này';if($sameGrade)$labels[]='Có dạy cùng khối';return['teacher'=>$teacher,'busy'=>$busy,'score'=>$score,'same_subject'=>$sameSubject,'same_class'=>$sameClass,'same_grade'=>$sameGrade,'labels'=>$labels];}
function tkb_substitute_candidates(array $teachers,array $target,array $week,string $date,string $absentTeacher=''):array{$rows=[];foreach($teachers as$teacher){$teacher=trim((string)$teacher);if($teacher===''||$teacher===$absentTeacher)continue;$rows[]=tkb_teacher_fit($teacher,$target,$week,$date);}usort($rows,function($a,$b){if($a['busy']!==$b['busy'])return$a['busy']?1:-1;if($a['score']!==$b['score'])return$b['score']<=>$a['score'];return strcasecmp($a['teacher'],$b['teacher']);});return$rows;}
