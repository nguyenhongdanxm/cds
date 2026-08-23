<?php
require_once __DIR__ . '/csdl_store.php';

if (!defined('VANBAN_POLLS_FILE')) define('VANBAN_POLLS_FILE', DATA_PATH . '/vanban_polls.json');
if (!defined('VANBAN_SURVEYS_FILE')) define('VANBAN_SURVEYS_FILE', DATA_PATH . '/vanban_surveys.json');
if (!defined('VANBAN_FEEDBACK_FILE')) define('VANBAN_FEEDBACK_FILE', DATA_PATH . '/vanban_feedback.json');

function vb_engagement_file(string $kind): string { return $kind === 'survey' ? VANBAN_SURVEYS_FILE : VANBAN_POLLS_FILE; }
function vb_user_key(array $user): string { return (string)($user['id'] ?? $user['username'] ?? ''); }
function vb_engagement_group_labels(): array { return ['bgh'=>'Ban giám hiệu','qlnt'=>'Quản lý nội trú','vanthu'=>'Văn thư','ketoan'=>'Kế toán','doandoi'=>'Đoàn – Đội','thuvien_thietbi'=>'Thư viện – Thiết bị','totruong'=>'Quản lý tổ chuyên môn','gvcn'=>'Giáo viên chủ nhiệm','gv'=>'Giáo viên']; }

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

function vb_engagement_actions(): array { return ['engagement_create','engagement_submit','engagement_status','engagement_delete','feedback_create','feedback_reply']; }

function vb_engagement_classes(): array {
    $names=[];foreach(csdl_classes_all() as $row){$name=trim((string)($row['name']??''));if($name!=='')$names[$name]=true;}
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
    $userKey=vb_user_key($user);if($userKey==='')throw new RuntimeException('Không xác định được tài khoản tham gia.');

    if($action==='engagement_create'){
        if(!$canManage)throw new RuntimeException('Bạn không có quyền tạo bình chọn hoặc khảo sát.');
        $kind=(string)($_POST['kind']??'poll');if(!in_array($kind,['poll','survey'],true))$kind='poll';
        $title=vb_clean((string)($_POST['title']??''),300);if($title==='')throw new RuntimeException('Hãy nhập tiêu đề.');
        $rows=vb_rows(vb_engagement_file($kind));
        $audienceMode=(string)($_POST['audience_mode']??'all');if(!in_array($audienceMode,['all','groups','users'],true))$audienceMode='all';
        $audienceGroups=array_values(array_unique(array_filter(array_map(fn($v)=>vb_clean((string)$v,100),(array)($_POST['audience_groups']??[])))));
        $audienceUsers=array_values(array_unique(array_filter(array_map(fn($v)=>vb_clean((string)$v,100),(array)($_POST['audience_user_ids']??[])))));
        if($audienceMode==='groups'&&!$audienceGroups)throw new RuntimeException('Hãy chọn ít nhất một tổ hoặc nhóm được tham gia.');
        if($audienceMode==='users'&&!$audienceUsers)throw new RuntimeException('Hãy chọn ít nhất một tài khoản được tham gia.');
        $responseScope=(string)($_POST['response_scope']??'individual');if(!in_array($responseScope,['individual','class'],true))$responseScope='individual';
        $template=vb_clean((string)($_POST['template_id']??'blank'),60);
        $record=['id'=>vb_id($kind),'kind'=>$kind,'title'=>$title,'description'=>vb_clean((string)($_POST['description']??''),2000),'ends_at'=>vb_date((string)($_POST['ends_at']??'')),'status'=>'active','show_on_dashboard'=>!empty($_POST['show_on_dashboard']),'audience_mode'=>$audienceMode,'audience_groups'=>$audienceMode==='groups'?$audienceGroups:[],'audience_user_ids'=>$audienceMode==='users'?$audienceUsers:[],'response_scope'=>$responseScope,'require_class'=>$responseScope==='class','template_id'=>$template,'responses'=>[],'created_by'=>$user['name']??'','created_by_id'=>$userKey,'created_at'=>date('c')];
        if($kind==='poll'){
            $options=array_values(array_unique(array_filter(array_map(fn($v)=>vb_clean($v,180),preg_split('/\R/u',(string)($_POST['options']??''))))));if(count($options)<2)throw new RuntimeException('Bình chọn cần ít nhất 2 phương án.');
            $record['options']=$options;$record['allow_other_text']=!empty($_POST['allow_other_text']);$record['other_label']=vb_clean((string)($_POST['other_label']??'Yêu cầu/ý kiến khác'),120)?:'Yêu cầu/ý kiến khác';
        }else{
            $surveyMode=(string)($_POST['survey_mode']??'choice');
            if($surveyMode==='form'){$record['survey_mode']='form';$record['fields']=vb_form_fields_from_post();}
            else{$questions=[];foreach(preg_split('/\R/u',(string)($_POST['questions']??''))as$line){$parts=array_values(array_filter(array_map(fn($v)=>vb_clean($v,250),explode('|',$line)),fn($v)=>$v!==''));if(count($parts)>=3)$questions[]=['question'=>array_shift($parts),'options'=>$parts];}if(!$questions)throw new RuntimeException('Mỗi câu khảo sát nhập theo mẫu: Câu hỏi | Lựa chọn 1 | Lựa chọn 2.');$record['questions']=$questions;$record['survey_mode']='choice';}
        }
        $rows[]=$record;if(!vb_save_rows(vb_engagement_file($kind),$rows))throw new RuntimeException('Không lưu được nội dung.');flash('Đã tạo '.($kind==='poll'?'bình chọn':'khảo sát').'.');return;
    }

    if($action==='engagement_submit'){
        $kind=(string)($_POST['kind']??'poll');$id=vb_clean((string)($_POST['id']??''),80);if(!in_array($kind,['poll','survey'],true))throw new RuntimeException('Loại nội dung không hợp lệ.');
        $rows=vb_rows(vb_engagement_file($kind));$found=false;
        foreach($rows as &$row)if(($row['id']??'')===$id){$found=true;if(($row['status']??'active')!=='active')throw new RuntimeException('Nội dung này đã đóng.');if(!empty($row['ends_at'])&&$row['ends_at']<date('Y-m-d'))throw new RuntimeException('Nội dung này đã hết hạn.');if(!vb_engagement_can_participate($row,$user))throw new RuntimeException('Tài khoản của bạn không thuộc phạm vi được tham gia nội dung này.');$responses=is_array($row['responses']??null)?$row['responses']:[];if(isset($responses[$userKey]))throw new RuntimeException('Tài khoản của bạn đã tham gia nội dung này.');
            $scope=(string)($row['response_scope']??(!empty($row['require_class'])?'class':'individual'));$className='';
            if($scope==='class'){$className=vb_clean((string)($_POST['class_name']??''),80);$allowed=vb_engagement_allowed_classes($user,$canManage);if($className===''||!in_array($className,$allowed,true))throw new RuntimeException('Hãy chọn đúng lớp được phân công.');}
            if($kind==='poll'){$answer=(int)($_POST['answer']??-1);if(!isset($row['options'][$answer]))throw new RuntimeException('Hãy chọn một phương án.');$other=!empty($row['allow_other_text'])?vb_clean((string)($_POST['other_text']??''),1000):'';$responses[$userKey]=['answer'=>$answer,'other_text'=>$other,'class_name'=>$className,'name'=>$user['name']??'','at'=>date('c')];}
            elseif(($row['survey_mode']??'choice')==='form'){$posted=(array)($_POST['form_value']??[]);$values=[];foreach((array)($row['fields']??[])as$field){$fid=(string)($field['id']??'');$type=(string)($field['type']??'text');$raw=$posted[$fid]??($type==='checkbox'?[]:'');if($type==='number'){$raw=str_replace(',','.',trim((string)$raw));if($raw!==''&&!is_numeric($raw))throw new RuntimeException('Trường “'.($field['label']??'').'” phải là số.');$value=$raw===''?'':(float)$raw;}elseif($type==='checkbox'){$value=array_values(array_intersect(array_map('strval',(array)$raw),(array)($field['options']??[])));}else{$value=vb_clean((string)$raw,500);if(in_array($type,['select','radio'],true)&&$value!==''&&!in_array($value,(array)($field['options']??[]),true))$value='';}$empty=is_array($value)?!$value:$value==='';if(!empty($field['required'])&&$empty)throw new RuntimeException('Hãy nhập trường “'.($field['label']??'').'”.');$values[$fid]=$value;}$responses[$userKey]=['class_name'=>$className,'values'=>$values,'name'=>$user['name']??'','at'=>date('c')];}
            else{$answers=[];foreach((array)($_POST['answer']??[])as$question=>$answer){$q=(int)$question;$a=(int)$answer;if(isset($row['questions'][$q]['options'][$a]))$answers[$q]=$a;}if(count($answers)!==count($row['questions']??[]))throw new RuntimeException('Hãy trả lời đầy đủ các câu hỏi.');$responses[$userKey]=['answers'=>$answers,'class_name'=>$className,'name'=>$user['name']??'','at'=>date('c')];}
            $row['responses']=$responses;$row['updated_at']=date('c');
        }unset($row);if(!$found||!vb_save_rows(vb_engagement_file($kind),$rows))throw new RuntimeException('Không lưu được câu trả lời.');flash('Đã ghi nhận câu trả lời của bạn.');return;
    }

    if($action==='engagement_status'||$action==='engagement_delete'){
        if(!$canManage)throw new RuntimeException('Bạn không có quyền quản lý nội dung này.');$kind=(string)($_POST['kind']??'poll');$id=vb_clean((string)($_POST['id']??''),80);$rows=vb_rows(vb_engagement_file($kind));$found=false;$next=[];
        foreach($rows as$row){if(($row['id']??'')===$id){$found=true;if($action==='engagement_status'){$row['status']=($row['status']??'active')==='active'?'closed':'active';$next[]=$row;}}else$next[]=$row;}if(!$found||!vb_save_rows(vb_engagement_file($kind),$next))throw new RuntimeException('Không cập nhật được nội dung.');flash($action==='engagement_delete'?'Đã xóa nội dung.':'Đã đổi trạng thái nội dung.','warning');return;
    }

    if($action==='feedback_create'){$subject=vb_clean((string)($_POST['subject']??''),300);$content=vb_clean((string)($_POST['content']??''),4000);if($subject===''||$content==='')throw new RuntimeException('Hãy nhập tiêu đề và nội dung góp ý.');$rows=vb_rows(VANBAN_FEEDBACK_FILE);$id=vb_id('ticket');$rows[]=['id'=>$id,'code'=>'GY-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,5)),'subject'=>$subject,'category'=>vb_clean((string)($_POST['category']??'Góp ý chung'),100),'priority'=>vb_clean((string)($_POST['priority']??'Bình thường'),50),'content'=>$content,'status'=>'new','owner_id'=>$userKey,'owner_name'=>$user['name']??'','messages'=>[],'created_at'=>date('c'),'updated_at'=>date('c')];if(!vb_save_rows(VANBAN_FEEDBACK_FILE,$rows))throw new RuntimeException('Không gửi được góp ý.');flash('Đã gửi góp ý. Mã theo dõi: '.$rows[array_key_last($rows)]['code']);return;}
    if($action==='feedback_reply'){$id=vb_clean((string)($_POST['id']??''),80);$message=vb_clean((string)($_POST['message']??''),4000);$status=(string)($_POST['status']??'');$rows=vb_rows(VANBAN_FEEDBACK_FILE);$found=false;foreach($rows as&$row)if(($row['id']??'')===$id){$found=true;$isOwner=($row['owner_id']??'')===$userKey;if(!$canManage&&!$isOwner)throw new RuntimeException('Bạn không có quyền phản hồi góp ý này.');if($message!=='')$row['messages'][]=['by_id'=>$userKey,'by_name'=>$user['name']??'','role'=>$canManage?'handler':'sender','content'=>$message,'at'=>date('c')];if($canManage&&in_array($status,['new','processing','completed'],true))$row['status']=$status;elseif(!$canManage&&($row['status']??'')==='completed'&&$message!=='')$row['status']='processing';$row['updated_at']=date('c');}unset($row);if(!$found||($message===''&&!$canManage)||!vb_save_rows(VANBAN_FEEDBACK_FILE,$rows))throw new RuntimeException('Không cập nhật được góp ý.');flash('Đã cập nhật góp ý.');}
}

function vb_response_count(array $row): int{return count(is_array($row['responses']??null)?$row['responses']:[]);}
function vb_poll_counts(array $row): array{$counts=array_fill(0,count($row['options']??[]),0);foreach((array)($row['responses']??[])as$response){$answer=(int)($response['answer']??-1);if(isset($counts[$answer]))$counts[$answer]++;}return$counts;}
function vb_survey_counts(array $row,int $question): array{$counts=array_fill(0,count($row['questions'][$question]['options']??[]),0);foreach((array)($row['responses']??[])as$response){$answer=(int)($response['answers'][$question]??-1);if(isset($counts[$answer]))$counts[$answer]++;}return$counts;}
function vb_form_summary(array $row): array{$classes=[];$totals=[];foreach((array)($row['fields']??[])as$field)if(($field['type']??'')==='number')$totals[(string)$field['id']]=0.0;foreach((array)($row['responses']??[])as$response){if(!is_array($response))continue;$class=trim((string)($response['class_name']??''))?:'Cá nhân';if(!isset($classes[$class]))$classes[$class]=['count'=>0,'values'=>[]];$classes[$class]['count']++;foreach((array)($row['fields']??[])as$field){$id=(string)($field['id']??'');$value=$response['values'][$id]??'';if(($field['type']??'')==='number'){$number=is_numeric($value)?(float)$value:0;$classes[$class]['values'][$id]=($classes[$class]['values'][$id]??0)+$number;$totals[$id]=($totals[$id]??0)+$number;}else{$display=is_array($value)?implode(', ',$value):(string)$value;if($display!=='')$classes[$class]['values'][$id][]=$display;}}}if(isset($classes['Cá nhân'])){$personal=$classes['Cá nhân'];unset($classes['Cá nhân']);uksort($classes,'csdl_compare_class_names');$classes=['Cá nhân'=>$personal]+$classes;}else uksort($classes,'csdl_compare_class_names');return['classes'=>$classes,'totals'=>$totals];}
function vb_engagement_people_for_option(array $row,string $kind,int $option,int $question=0): array{$people=[];foreach((array)($row['responses']??[])as$account=>$response){$answer=$kind==='poll'?(int)($response['answer']??-1):(int)($response['answers'][$question]??-1);if($answer===$option)$people[]=['account'=>(string)$account,'name'=>(string)($response['name']??$account),'class_name'=>(string)($response['class_name']??''),'other_text'=>(string)($response['other_text']??''),'at'=>(string)($response['at']??'')];}usort($people,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));return$people;}
function vb_engagement_state(array $row): array{$today=date('Y-m-d');$end=trim((string)($row['ends_at']??''));if(($row['status']??'active')!=='active'||($end!==''&&$end<$today))return['Đã hết hạn','danger'];if($end!==''&&$end<=date('Y-m-d',strtotime('+3 days')))return['Sắp hết hạn','warning'];$created=substr((string)($row['created_at']??''),0,10);if($created!==''&&$created>=date('Y-m-d',strtotime('-3 days')))return['Mới','success'];return['Còn hạn','success'];}

function vb_engagement_scope(array $row): string{return(string)($row['response_scope']??(!empty($row['require_class'])?'class':'individual'));}
function vb_engagement_class_stats(array $row): array{$stats=[];foreach((array)($row['responses']??[])as$r){$class=trim((string)($r['class_name']??''));if($class==='')continue;$stats[$class]=($stats[$class]??0)+1;}uksort($stats,'csdl_compare_class_names');return$stats;}
function vb_engagement_other_notes(array $row): array{$out=[];foreach((array)($row['responses']??[])as$r){$text=trim((string)($r['other_text']??''));if($text==='')continue;$out[]=['name'=>(string)($r['name']??''),'class_name'=>(string)($r['class_name']??''),'text'=>$text,'at'=>(string)($r['at']??'')];}return$out;}

/* Nâng cấp giao diện Khảo sát/Bình chọn mà không phá view hiện tại. */
if(!defined('VB_ENGAGEMENT_ENHANCER')&&in_array((string)($_GET['tab']??''),['engagement','polls','surveys'],true)){
    define('VB_ENGAGEMENT_ENHANCER',true);
    ob_start(function($html){
        if(stripos($html,'id="engagementDialog"')===false&&stripos($html,'class="engagement-tabs"')===false)return$html;
        $u=function_exists('current_user')?current_user():[];$isAdmin=(($u['role']??'')==='admin');$allowed=vb_engagement_allowed_classes($u,$isAdmin);
        $meta=[];$stats=[];foreach(['poll','survey']as$kind){foreach(vb_rows(vb_engagement_file($kind))as$row){$id=(string)($row['id']??'');$responses=(array)($row['responses']??[]);$classStats=vb_engagement_class_stats($row);$notes=vb_engagement_other_notes($row);$m=['id'=>$id,'kind'=>$kind,'title'=>(string)($row['title']??''),'scope'=>vb_engagement_scope($row),'allow_other'=>!empty($row['allow_other_text']),'other_label'=>(string)($row['other_label']??'Yêu cầu/ý kiến khác'),'responses'=>count($responses),'classes'=>$classStats,'notes'=>$notes];$meta[$id]=$m;$stats[]=$m;}}
        usort($stats,fn($a,$b)=>0);$payload=json_encode(['items'=>$meta,'classes'=>$allowed,'stats'=>$stats],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $enhance=<<<'HTML'
<style>
.vb-template-box{grid-column:1/-1;padding:.8rem;border:1px solid #d9e2ec;border-radius:14px;background:#f8fbff}.vb-template-box>label{display:block;font-weight:800;margin-bottom:.45rem}.vb-template-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.45rem}.vb-template-card{padding:.55rem .6rem;border:1px solid #dbe4ee;border-radius:11px;background:#fff;cursor:pointer;text-align:left;font-size:.78rem;color:#334155}.vb-template-card:hover,.vb-template-card.active{border-color:#3b82f6;background:#eff6ff}.vb-template-card strong{display:block;color:#173f65;font-size:.82rem}.vb-scope-box{grid-column:1/-1}.vb-scope-choices{display:flex;gap:.5rem;flex-wrap:wrap}.vb-scope-choices label{display:flex;align-items:center;gap:.4rem;padding:.55rem .7rem;border:1px solid #dbe4ee;border-radius:11px;background:#fff}.vb-scope-help{font-size:.75rem;color:#64748b;margin-top:.3rem}.vb-other-field{margin:.55rem 0;padding:.65rem;background:#fff8e6;border:1px solid #f4d78c;border-radius:10px}.vb-scope-badge{display:inline-flex;align-items:center;gap:.25rem;margin-left:.35rem;padding:.18rem .45rem;border-radius:999px;background:#eef4ff;color:#315b7d;font-size:.7rem;font-weight:800}.vb-stat-extra{margin:.8rem 0;padding:.75rem;border:1px solid #dfe7ef;border-radius:12px;background:#f8fbfd}.vb-stat-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:.65rem}.vb-stat-kpi{padding:.55rem;border-radius:10px;background:#fff;border:1px solid #e5ebf1;text-align:center}.vb-stat-kpi strong{display:block;font-size:1.15rem;color:#173f65}.vb-class-summary{display:flex;gap:.35rem;flex-wrap:wrap}.vb-class-summary span{padding:.25rem .45rem;border-radius:999px;background:#eaf7ef;color:#166534;font-size:.73rem;font-weight:750}.vb-note-list{margin-top:.6rem;display:grid;gap:.35rem}.vb-note-item{padding:.5rem .6rem;border-radius:9px;background:#fff;border:1px solid #eceff3;font-size:.78rem}.vb-note-item strong{color:#173f65}@media(max-width:900px){.vb-template-grid{grid-template-columns:1fr 1fr}.vb-stat-kpis{grid-template-columns:1fr 1fr 1fr}}@media(max-width:520px){.vb-template-grid{grid-template-columns:1fr}.vb-stat-kpis{grid-template-columns:1fr}}
</style>
<script>
(function(){
 const DATA=__VB_DATA__;
 function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
 function addClassSelect(form){if(form.querySelector('[data-vb-class-added]')||form.querySelector('select[name="class_name"]'))return;const box=document.createElement('div');box.className='field vb-class-field';box.dataset.vbClassAdded='1';box.innerHTML='<label>Lớp *</label><select name="class_name" required><option value="">Chọn lớp</option>'+DATA.classes.map(c=>'<option>'+esc(c)+'</option>').join('')+'</select><div class="vb-scope-help">Chỉ xuất hiện vì nội dung này được tạo theo phạm vi lớp.</div>';const first=form.querySelector('fieldset,.choice');form.insertBefore(box,first||form.firstChild);}
 document.querySelectorAll('.engage-card').forEach(card=>{const form=card.querySelector('form.choice-list,form.survey-form');const id=form&&form.querySelector('input[name="id"]')?.value;const m=id&&DATA.items[id];if(!m)return;const head=card.querySelector('.engage-head>div');if(head&&!head.querySelector('.vb-scope-badge'))head.insertAdjacentHTML('beforeend','<span class="vb-scope-badge"><i class="bi '+(m.scope==='class'?'bi-building':'bi-person')+'"></i> '+(m.scope==='class'?'Theo lớp':'Cá nhân')+'</span>');if(!form)return;if(m.scope==='class')addClassSelect(form);else{const existing=form.querySelector('select[name="class_name"]');if(existing){const field=existing.closest('.field');if(field)field.remove();}}
   if(m.kind==='poll'&&m.allow_other&&!form.querySelector('[name="other_text"]')){const box=document.createElement('div');box.className='vb-other-field';box.innerHTML='<label><strong>'+esc(m.other_label||'Yêu cầu/ý kiến khác')+'</strong></label><textarea class="input" name="other_text" rows="2" placeholder="Có thể nhập thêm nội dung nếu cần..."></textarea>';const btn=form.querySelector('button[type="submit"],button.btn-primary');form.insertBefore(box,btn);}
 });
 const dialog=document.getElementById('engagementDialog');if(dialog){const form=dialog.querySelector('form');const grid=form&&form.querySelector('.form-grid');if(grid&&!form.querySelector('[name="response_scope"]')){
   const template=document.createElement('div');template.className='vb-template-box';template.innerHTML='<label><i class="bi bi-grid-3x3-gap"></i> Chọn mẫu tạo nhanh</label><div class="vb-template-grid"><button type="button" class="vb-template-card active" data-t="blank"><strong>Tạo trống</strong>Tự thiết kế nội dung</button><button type="button" class="vb-template-card" data-t="yesno"><strong>Có / Không + ý kiến</strong>Bình chọn nhanh có ô nhập thêm</button><button type="button" class="vb-template-card" data-t="agree"><strong>Đồng ý / Không đồng ý</strong>Xin ý kiến cá nhân</button><button type="button" class="vb-template-card" data-t="quick"><strong>Khảo sát nhanh</strong>Các câu lựa chọn</button><button type="button" class="vb-template-card" data-t="classform"><strong>Biểu mẫu theo lớp</strong>Thu số liệu và tổng hợp theo lớp</button></div><input type="hidden" name="template_id" value="blank">';grid.insertBefore(template,grid.firstChild);
   const titleField=form.querySelector('[name="title"]')?.closest('.field');const scope=document.createElement('div');scope.className='vb-scope-box';scope.innerHTML='<label><strong>Đơn vị trả lời</strong></label><div class="vb-scope-choices"><label><input type="radio" name="response_scope" value="individual" checked> <span><strong>Cá nhân</strong> – mỗi tài khoản trả lời một lần</span></label><label><input type="radio" name="response_scope" value="class"> <span><strong>Theo lớp</strong> – người trả lời phải chọn lớp</span></label></div><div class="vb-scope-help">Chỉ chọn “Theo lớp” khi nội dung cần tổng hợp số liệu theo từng lớp. Bình chọn cá nhân sẽ không hiện ô Lớp.</div>';if(titleField)titleField.parentNode.insertBefore(scope,titleField);
   if(form.querySelector('[name="options"]')){const other=document.createElement('div');other.className='field span-3';other.innerHTML='<label class="check-row"><input type="checkbox" name="allow_other_text" value="1"><span><strong>Cho phép nhập yêu cầu/ý kiến khác</strong> – hiện thêm ô gõ văn bản sau phần lựa chọn.</span></label><input class="input" name="other_label" value="Yêu cầu/ý kiến khác" placeholder="Tên ô nhập thêm">';const opt=form.querySelector('[name="options"]').closest('.field');opt.parentNode.insertBefore(other,opt.nextSibling);}
   function setDate(days){const d=form.querySelector('[name="ends_at"]');if(d&&!d.value){const x=new Date();x.setDate(x.getDate()+days);d.value=x.toISOString().slice(0,10)}}
   template.querySelectorAll('[data-t]').forEach(btn=>btn.onclick=function(){template.querySelectorAll('[data-t]').forEach(b=>b.classList.remove('active'));btn.classList.add('active');form.elements.template_id.value=btn.dataset.t;const t=btn.dataset.t;const title=form.elements.title,desc=form.elements.description,opts=form.elements.options,questions=form.elements.questions;setDate(7);
     if(t==='yesno'&&opts){if(title&&!title.value)title.value='Bình chọn ý kiến';if(desc&&!desc.value)desc.value='Vui lòng chọn Có hoặc Không. Nếu có yêu cầu/ý kiến khác, nhập thêm vào ô bên dưới.';opts.value='Có\nKhông';if(form.elements.allow_other_text)form.elements.allow_other_text.checked=true;}
     if(t==='agree'&&opts){if(title&&!title.value)title.value='Xin ý kiến';opts.value='Đồng ý\nKhông đồng ý';}
     if(t==='quick'&&questions){if(title&&!title.value)title.value='Khảo sát nhanh';questions.value='Mức độ đồng ý | Đồng ý | Phân vân | Không đồng ý';const r=form.querySelector('[name="survey_mode"][value="choice"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}))}}
     if(t==='classform'&&questions){const sr=form.querySelector('[name="response_scope"][value="class"]');if(sr)sr.checked=true;const r=form.querySelector('[name="survey_mode"][value="form"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}))}if(title&&!title.value)title.value='Khảo sát số liệu theo lớp';}
   });
 }}
 document.querySelectorAll('.stats-modal').forEach(modal=>{const title=(modal.querySelector('.modal-head strong')?.textContent||'').trim();const candidates=Object.values(DATA.items).filter(x=>x.title===title);if(!candidates.length)return;const m=candidates[0];const body=modal.querySelector('.modal-body');if(!body||body.querySelector('.vb-stat-extra'))return;const classEntries=Object.entries(m.classes||{});const notes=m.notes||[];const extra=document.createElement('div');extra.className='vb-stat-extra';extra.innerHTML='<div class="vb-stat-kpis"><div class="vb-stat-kpi"><strong>'+m.responses+'</strong><span>Lượt tham gia</span></div><div class="vb-stat-kpi"><strong>'+(m.scope==='class'?classEntries.length:'—')+'</strong><span>Lớp đã gửi</span></div><div class="vb-stat-kpi"><strong>'+notes.length+'</strong><span>Ý kiến khác</span></div></div>'+(m.scope==='class'?'<div><strong>Tham gia theo lớp</strong><div class="vb-class-summary">'+classEntries.map(([c,n])=>'<span>'+esc(c)+': '+n+'</span>').join('')+'</div></div>':'<div class="vb-scope-help"><i class="bi bi-person"></i> Bình chọn/khảo sát cá nhân – không sử dụng cột lớp.</div>')+(notes.length?'<div class="vb-note-list"><strong>Yêu cầu/ý kiến khác</strong>'+notes.map(n=>'<div class="vb-note-item"><strong>'+esc(n.name)+(n.class_name?' · '+esc(n.class_name):'')+':</strong> '+esc(n.text)+'</div>').join('')+'</div>':'');body.insertBefore(extra,body.firstChild);});
})();
</script>
HTML;
        $enhance=str_replace('__VB_DATA__',$payload,$enhance);return preg_replace('/<\/body>/i',$enhance.'</body>',$html,1)?:($html.$enhance);
    });
}
