<?php
require_once __DIR__ . '/csdl_store.php';

if (!defined('VANBAN_POLLS_FILE')) define('VANBAN_POLLS_FILE', DATA_PATH . '/vanban_polls.json');
if (!defined('VANBAN_SURVEYS_FILE')) define('VANBAN_SURVEYS_FILE', DATA_PATH . '/vanban_surveys.json');
if (!defined('VANBAN_FEEDBACK_FILE')) define('VANBAN_FEEDBACK_FILE', DATA_PATH . '/vanban_feedback.json');

function vb_engagement_file(string $kind): string {
    return $kind === 'survey' ? VANBAN_SURVEYS_FILE : VANBAN_POLLS_FILE;
}

function vb_user_key(array $user): string {
    return (string)($user['id'] ?? $user['username'] ?? '');
}

function vb_engagement_group_labels(): array {
    return ['bgh'=>'Ban giám hiệu','qlnt'=>'Quản lý nội trú','vanthu'=>'Văn thư','ketoan'=>'Kế toán','doandoi'=>'Đoàn – Đội','thuvien_thietbi'=>'Thư viện – Thiết bị','totruong'=>'Quản lý tổ chuyên môn','gvcn'=>'Giáo viên chủ nhiệm','gv'=>'Giáo viên'];
}

function vb_engagement_user_groups(array $user): array {
    $groups=is_array($user['groups']??null)?$user['groups']:[];
    foreach(['to_chuyen_mon','pccm_group','department'] as $key)if(trim((string)($user[$key]??''))!=='')$groups[]=trim((string)$user[$key]);
    static $teacherGroups=null;
    if($teacherGroups===null){$teacherGroups=[];foreach(csdl_teachers_all() as $teacher){$name=vb_norm((string)($teacher['name']??''));$group=trim((string)($teacher['to_chuyen_mon']??$teacher['pccm_group']??''));if($name!==''&&$group!=='')$teacherGroups[$name]=$group;}}
    $teacherName=vb_norm((string)($user['teacher_name']??$user['name']??''));
    if($teacherName!==''&&!empty($teacherGroups[$teacherName]))$groups[]=$teacherGroups[$teacherName];
    return array_values(array_unique(array_filter(array_map('strval',$groups))));
}

function vb_engagement_can_participate(array $row,array $user): bool {
    $mode=(string)($row['audience_mode']??'all');
    if($mode==='all'||$mode==='')return true;
    if($mode==='users')return in_array(vb_user_key($user),(array)($row['audience_user_ids']??[]),true);
    return (bool)array_intersect(vb_engagement_user_groups($user),(array)($row['audience_groups']??[]));
}

function vb_engagement_actions(): array {
    return ['engagement_create','engagement_submit','engagement_status','engagement_delete','feedback_create','feedback_reply'];
}

function vb_engagement_classes(): array {
    $names=[];
    foreach(csdl_classes_all() as $row){$name=trim((string)($row['name']??''));if($name!=='')$names[$name]=true;}
    $names=array_keys($names);usort($names,'csdl_compare_class_names');return $names;
}

function vb_engagement_allowed_classes(array $user,bool $canManage=false): array {
    $all=vb_engagement_classes();if($canManage)return $all;
    $assigned=array_values(array_unique(array_filter(array_map('strval',(array)($user['homeroom_classes']??[])))));
    $teacherId=trim((string)($user['teacher_id']??''));$teacherName=vb_norm((string)($user['teacher_name']??$user['name']??''));
    if($teacherId===''&&$teacherName!=='')foreach(csdl_teachers_all() as $teacher)if(vb_norm((string)($teacher['name']??''))===$teacherName){$teacherId=(string)($teacher['id']??'');break;}
    if($teacherId!=='')foreach(csdl_classes_all() as $class)if((string)($class['homeroom_teacher_id']??'')===$teacherId)$assigned[]=(string)($class['name']??'');
    $assigned=array_values(array_unique(array_filter($assigned)));if($assigned)return array_values(array_intersect($all,$assigned));
    return in_array('gvcn',vb_engagement_user_groups($user),true)?[]:$all;
}

function vb_form_fields_from_post(): array {
    $labels=(array)($_POST['field_label']??[]);$types=(array)($_POST['field_type']??[]);$options=(array)($_POST['field_options']??[]);$required=(array)($_POST['field_required']??[]);$fields=[];
    foreach($labels as $index=>$label){$label=vb_clean((string)$label,220);$type=(string)($types[$index]??'text');if($label===''||!in_array($type,['number','text','select','radio','checkbox'],true))continue;$opts=array_values(array_unique(array_filter(array_map(fn($v)=>vb_clean($v,120),preg_split('/[,;\n]+/u',(string)($options[$index]??''))))));if(in_array($type,['select','radio','checkbox'],true)&&!$opts)throw new RuntimeException('Trường “'.$label.'” cần có danh sách lựa chọn.');$fields[]=['id'=>'f'.(count($fields)+1),'label'=>$label,'type'=>$type,'options'=>$opts,'required'=>isset($required[$index])];}
    if(!$fields)throw new RuntimeException('Biểu mẫu cần ít nhất một trường nhập liệu.');return $fields;
}

function vb_engagement_process(string $action, array $user, bool $canManage): void {
    $userKey = vb_user_key($user);
    if ($userKey === '') throw new RuntimeException('Không xác định được tài khoản tham gia.');

    if ($action === 'engagement_create') {
        if (!$canManage) throw new RuntimeException('Bạn không có quyền tạo bình chọn hoặc khảo sát.');
        $kind = (string)($_POST['kind'] ?? 'poll');
        if (!in_array($kind, ['poll','survey'], true)) $kind = 'poll';
        $title = vb_clean((string)($_POST['title'] ?? ''), 300);
        if ($title === '') throw new RuntimeException('Hãy nhập tiêu đề.');
        $rows = vb_rows(vb_engagement_file($kind));
        $audienceMode=(string)($_POST['audience_mode']??'all');
        if(!in_array($audienceMode,['all','groups','users'],true))$audienceMode='all';
        $audienceGroups=array_values(array_unique(array_filter(array_map(fn($v)=>vb_clean((string)$v,100),(array)($_POST['audience_groups']??[])))));
        $audienceUsers=array_values(array_unique(array_filter(array_map(fn($v)=>vb_clean((string)$v,100),(array)($_POST['audience_user_ids']??[])))));
        if($audienceMode==='groups'&&!$audienceGroups)throw new RuntimeException('Hãy chọn ít nhất một tổ hoặc nhóm được tham gia.');
        if($audienceMode==='users'&&!$audienceUsers)throw new RuntimeException('Hãy chọn ít nhất một tài khoản được tham gia.');
        $record = [
            'id'=>vb_id($kind), 'kind'=>$kind, 'title'=>$title,
            'description'=>vb_clean((string)($_POST['description'] ?? ''), 2000),
            'ends_at'=>vb_date((string)($_POST['ends_at'] ?? '')), 'status'=>'active',
            'show_on_dashboard'=>!empty($_POST['show_on_dashboard']),
            'audience_mode'=>$audienceMode,'audience_groups'=>$audienceMode==='groups'?$audienceGroups:[],
            'audience_user_ids'=>$audienceMode==='users'?$audienceUsers:[],
            'responses'=>[], 'created_by'=>$user['name'] ?? '', 'created_by_id'=>$userKey, 'created_at'=>date('c')
        ];
        if ($kind === 'poll') {
            $options = array_values(array_unique(array_filter(array_map(fn($v)=>vb_clean($v,180), preg_split('/\R/u',(string)($_POST['options']??''))))));
            if (count($options) < 2) throw new RuntimeException('Bình chọn cần ít nhất 2 phương án.');
            $record['options']=$options;
        } else {
            $surveyMode=(string)($_POST['survey_mode']??'choice');
            if($surveyMode==='form'){
                $record['survey_mode']='form';$record['fields']=vb_form_fields_from_post();$record['require_class']=true;
            }else{
            $questions=[];
            foreach (preg_split('/\R/u',(string)($_POST['questions']??'')) as $line) {
                $parts=array_values(array_filter(array_map(fn($v)=>vb_clean($v,250),explode('|',$line)),fn($v)=>$v!==''));
                if (count($parts)>=3) $questions[]=['question'=>array_shift($parts),'options'=>$parts];
            }
            if (!$questions) throw new RuntimeException('Mỗi câu khảo sát nhập theo mẫu: Câu hỏi | Lựa chọn 1 | Lựa chọn 2.');
            $record['questions']=$questions;
            $record['survey_mode']='choice';
            }
        }
        $rows[]=$record;
        if (!vb_save_rows(vb_engagement_file($kind),$rows)) throw new RuntimeException('Không lưu được nội dung.');
        flash('Đã tạo '.($kind==='poll'?'bình chọn':'khảo sát').'.');
        return;
    }

    if ($action === 'engagement_submit') {
        $kind=(string)($_POST['kind']??'poll'); $id=vb_clean((string)($_POST['id']??''),80);
        if (!in_array($kind,['poll','survey'],true)) throw new RuntimeException('Loại nội dung không hợp lệ.');
        $rows=vb_rows(vb_engagement_file($kind)); $found=false;
        foreach($rows as &$row) if(($row['id']??'')===$id){
            $found=true;
            if(($row['status']??'active')!=='active') throw new RuntimeException('Nội dung này đã đóng.');
            if(!empty($row['ends_at'])&&$row['ends_at']<date('Y-m-d')) throw new RuntimeException('Nội dung này đã hết hạn.');
            if(!vb_engagement_can_participate($row,$user))throw new RuntimeException('Tài khoản của bạn không thuộc phạm vi được tham gia nội dung này.');
            $responses=is_array($row['responses']??null)?$row['responses']:[];
            if(isset($responses[$userKey])) throw new RuntimeException('Tài khoản của bạn đã tham gia nội dung này.');
            if($kind==='poll'){
                $answer=(int)($_POST['answer']??-1);
                if(!isset($row['options'][$answer])) throw new RuntimeException('Hãy chọn một phương án.');
                $responses[$userKey]=['answer'=>$answer,'name'=>$user['name']??'','at'=>date('c')];
            }elseif(($row['survey_mode']??'choice')==='form'){
                $className=vb_clean((string)($_POST['class_name']??''),80);$allowed=vb_engagement_allowed_classes($user,$canManage);
                if($className===''||!in_array($className,$allowed,true))throw new RuntimeException('Hãy chọn đúng lớp được phân công.');
                $posted=(array)($_POST['form_value']??[]);$values=[];
                foreach((array)($row['fields']??[]) as $field){$fid=(string)($field['id']??'');$type=(string)($field['type']??'text');$raw=$posted[$fid]??($type==='checkbox'?[]:'');
                    if($type==='number'){$raw=str_replace(',','.',trim((string)$raw));if($raw!==''&&!is_numeric($raw))throw new RuntimeException('Trường “'.($field['label']??'').'” phải là số.');$value=$raw===''?'':(float)$raw;}
                    elseif($type==='checkbox'){$value=array_values(array_intersect(array_map('strval',(array)$raw),(array)($field['options']??[])));}
                    else{$value=vb_clean((string)$raw,500);if(in_array($type,['select','radio'],true)&&$value!==''&&!in_array($value,(array)($field['options']??[]),true))$value='';}
                    $empty=is_array($value)?!$value:$value==='';if(!empty($field['required'])&&$empty)throw new RuntimeException('Hãy nhập trường “'.($field['label']??'').'”.');$values[$fid]=$value;
                }
                $responses[$userKey]=['class_name'=>$className,'values'=>$values,'name'=>$user['name']??'','at'=>date('c')];
            }else{
                $answers=[];foreach((array)($_POST['answer']??[]) as $question=>$answer){$q=(int)$question;$a=(int)$answer;if(isset($row['questions'][$q]['options'][$a]))$answers[$q]=$a;}
                if(count($answers)!==count($row['questions']??[])) throw new RuntimeException('Hãy trả lời đầy đủ các câu hỏi.');
                $responses[$userKey]=['answers'=>$answers,'name'=>$user['name']??'','at'=>date('c')];
            }
            $row['responses']=$responses;$row['updated_at']=date('c');
        }
        unset($row);
        if(!$found||!vb_save_rows(vb_engagement_file($kind),$rows)) throw new RuntimeException('Không lưu được câu trả lời.');
        flash('Đã ghi nhận câu trả lời của bạn.'); return;
    }

    if ($action === 'engagement_status' || $action === 'engagement_delete') {
        if(!$canManage) throw new RuntimeException('Bạn không có quyền quản lý nội dung này.');
        $kind=(string)($_POST['kind']??'poll');$id=vb_clean((string)($_POST['id']??''),80);
        $rows=vb_rows(vb_engagement_file($kind));$found=false;$next=[];
        foreach($rows as $row){if(($row['id']??'')===$id){$found=true;if($action==='engagement_status'){$row['status']=($row['status']??'active')==='active'?'closed':'active';$next[]=$row;}}else $next[]=$row;}
        if(!$found||!vb_save_rows(vb_engagement_file($kind),$next)) throw new RuntimeException('Không cập nhật được nội dung.');
        flash($action==='engagement_delete'?'Đã xóa nội dung.':'Đã đổi trạng thái nội dung.','warning'); return;
    }

    if ($action === 'feedback_create') {
        $subject=vb_clean((string)($_POST['subject']??''),300);$content=vb_clean((string)($_POST['content']??''),4000);
        if($subject===''||$content==='') throw new RuntimeException('Hãy nhập tiêu đề và nội dung góp ý.');
        $rows=vb_rows(VANBAN_FEEDBACK_FILE);$id=vb_id('ticket');
        $rows[]=['id'=>$id,'code'=>'GY-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,5)),'subject'=>$subject,
            'category'=>vb_clean((string)($_POST['category']??'Góp ý chung'),100),'priority'=>vb_clean((string)($_POST['priority']??'Bình thường'),50),
            'content'=>$content,'status'=>'new','owner_id'=>$userKey,'owner_name'=>$user['name']??'',
            'messages'=>[],'created_at'=>date('c'),'updated_at'=>date('c')];
        if(!vb_save_rows(VANBAN_FEEDBACK_FILE,$rows)) throw new RuntimeException('Không gửi được góp ý.');
        flash('Đã gửi góp ý. Mã theo dõi: '.$rows[array_key_last($rows)]['code']); return;
    }

    if ($action === 'feedback_reply') {
        $id=vb_clean((string)($_POST['id']??''),80);$message=vb_clean((string)($_POST['message']??''),4000);
        $status=(string)($_POST['status']??'');$rows=vb_rows(VANBAN_FEEDBACK_FILE);$found=false;
        foreach($rows as &$row)if(($row['id']??'')===$id){$found=true;$isOwner=($row['owner_id']??'')===$userKey;if(!$canManage&&!$isOwner)throw new RuntimeException('Bạn không có quyền phản hồi góp ý này.');
            if($message!==''){$row['messages'][]=['by_id'=>$userKey,'by_name'=>$user['name']??'','role'=>$canManage?'handler':'sender','content'=>$message,'at'=>date('c')];}
            if($canManage&&in_array($status,['new','processing','completed'],true))$row['status']=$status;
            elseif(!$canManage&&($row['status']??'')==='completed'&&$message!=='')$row['status']='processing';
            $row['updated_at']=date('c');
        }unset($row);
        if(!$found||($message===''&&!$canManage)||!vb_save_rows(VANBAN_FEEDBACK_FILE,$rows))throw new RuntimeException('Không cập nhật được góp ý.');
        flash('Đã cập nhật góp ý.');
    }
}

function vb_response_count(array $row): int { return count(is_array($row['responses']??null)?$row['responses']:[]); }

function vb_poll_counts(array $row): array {
    $counts=array_fill(0,count($row['options']??[]),0);
    foreach((array)($row['responses']??[]) as $response){$answer=(int)($response['answer']??-1);if(isset($counts[$answer]))$counts[$answer]++;}
    return $counts;
}

function vb_survey_counts(array $row,int $question): array {
    $counts=array_fill(0,count($row['questions'][$question]['options']??[]),0);
    foreach((array)($row['responses']??[]) as $response){$answer=(int)($response['answers'][$question]??-1);if(isset($counts[$answer]))$counts[$answer]++;}
    return $counts;
}

function vb_form_summary(array $row): array {
    $classes=[];$totals=[];
    foreach((array)($row['fields']??[]) as $field)if(($field['type']??'')==='number')$totals[(string)$field['id']]=0.0;
    foreach((array)($row['responses']??[]) as $response){if(!is_array($response))continue;$class=trim((string)($response['class_name']??''))?:'Chưa xác định';if(!isset($classes[$class]))$classes[$class]=['count'=>0,'values'=>[]];$classes[$class]['count']++;
        foreach((array)($row['fields']??[]) as $field){$id=(string)($field['id']??'');$value=$response['values'][$id]??'';if(($field['type']??'')==='number'){$number=is_numeric($value)?(float)$value:0;$classes[$class]['values'][$id]=($classes[$class]['values'][$id]??0)+$number;$totals[$id]=($totals[$id]??0)+$number;}else{$display=is_array($value)?implode(', ',$value):(string)$value;if($display!=='')$classes[$class]['values'][$id][]=$display;}}
    }
    uksort($classes,'csdl_compare_class_names');return ['classes'=>$classes,'totals'=>$totals];
}

function vb_engagement_people_for_option(array $row,string $kind,int $option,int $question=0): array {
    $people=[];
    foreach((array)($row['responses']??[]) as $account=>$response){
        $answer=$kind==='poll'?(int)($response['answer']??-1):(int)($response['answers'][$question]??-1);
        if($answer===$option)$people[]=['account'=>(string)$account,'name'=>(string)($response['name']??$account),'at'=>(string)($response['at']??'')];
    }
    usort($people,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
    return $people;
}

function vb_engagement_state(array $row): array {
    $today = date('Y-m-d');
    $end = trim((string)($row['ends_at'] ?? ''));
    if (($row['status'] ?? 'active') !== 'active' || ($end !== '' && $end < $today)) return ['Đã hết hạn','danger'];
    if ($end !== '' && $end <= date('Y-m-d', strtotime('+3 days'))) return ['Sắp hết hạn','warning'];
    $created = substr((string)($row['created_at'] ?? ''), 0, 10);
    if ($created !== '' && $created >= date('Y-m-d', strtotime('-3 days'))) return ['Mới','success'];
    return ['Còn hạn','success'];
}
