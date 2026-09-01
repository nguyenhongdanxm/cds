<?php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/json_store.php';
require_once dirname(__DIR__, 2) . '/includes/session_user.php';
require_once dirname(__DIR__, 2) . '/includes/chuyenmon_permission_runtime.php';
require_once dirname(__DIR__, 2) . '/includes/drive_action_registry.php';
require_once dirname(__DIR__, 2) . '/includes/school_week_calendar.php';

/* Google Drive storage cho module Chuyên môn. */
if (!defined('CDS_DRIVE_SETTINGS')) define('CDS_DRIVE_SETTINGS', dirname(__DIR__,2) . '/data/google_drive_settings.json');
if (!defined('CDS_DRIVE_HISTORY')) define('CDS_DRIVE_HISTORY', dirname(__DIR__,2) . '/data/google_drive_history.json');
if (!defined('CDS_DRIVE_ACTIONS')) define('CDS_DRIVE_ACTIONS', dirname(__DIR__,2) . '/data/google_drive_actions.json');
if (!function_exists('cds_drive_settings')) {
function cds_drive_settings(): array {$raw=function_exists('cds_instance_config')?cds_instance_config('drive',[]):[];if(!is_array($raw)||!$raw)$raw=is_file(CDS_DRIVE_SETTINGS)?json_decode((string)file_get_contents(CDS_DRIVE_SETTINGS),true):[];$raw=is_array($raw)?$raw:[];$legacy=(array)($raw['folders']??[]);$types=is_array($raw['types']??null)?$raw['types']:[];foreach(['documents'=>'Văn bản','plans'=>'Kế hoạch và báo cáo','education_plans'=>'Kế hoạch giáo dục','photos'=>'Ảnh học sinh'] as $key=>$label){$types[$key]=array_merge(['label'=>$label,'folder_id'=>''],is_array($types[$key]??null)?$types[$key]:[]);if(empty($types[$key]['folder_id'])&&!empty($legacy[$key]))$types[$key]['folder_id']=$legacy[$key];}return array_merge(['enabled'=>false,'oauth'=>[],'types'=>$types,'bindings'=>[]],$raw,['types'=>$types,'bindings'=>is_array($raw['bindings']??null)?$raw['bindings']:[]]);}
function cds_drive_page_action(?string $uri=null): string {$path=parse_url($uri??($_SERVER['REQUEST_URI']??($_SERVER['SCRIPT_NAME']??'')),PHP_URL_PATH)?:'';$query=[];parse_str((string)parse_url($uri??($_SERVER['REQUEST_URI']??''),PHP_URL_QUERY),$raw);foreach(['tab','view','mode'] as $key)if(isset($raw[$key])&&preg_match('/^[a-zA-Z0-9_-]+$/',(string)$raw[$key]))$query[$key]=(string)$raw[$key];return 'page:/'.ltrim($path,'/').($query?'?'.http_build_query($query):'');}
function cds_drive_type_for_action(string $action,string $fallback): string {$settings=cds_drive_settings();$mapped=trim((string)($settings['bindings'][$action]??''));return $mapped!==''&&isset($settings['types'][$mapped])?$mapped:$fallback;}
function cds_drive_register_action(string $action): void {$label='Chuyên môn · '.ucwords(str_replace(['-','_','.php','/'],' ',substr($action,5)));cds_drive_action_register_shared(CDS_DRIVE_ACTIONS,$action,$label);}
function cds_drive_b64url(string $value): string {return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
function cds_drive_http(string $url,string $method='GET',array $headers=[],string $body=''): array {if(!function_exists('curl_init'))return ['ok'=>false,'status'=>0,'body'=>'','error'=>'Hosting chưa bật PHP cURL.'];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>15]);if($body!=='')curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$response=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return ['ok'=>$response!==false&&$status>=200&&$status<300,'status'=>$status,'body'=>(string)$response,'error'=>$error];}
function cds_drive_token(array $settings=[]): array {
    $settings=$settings?:cds_drive_settings();
    $oauth=(array)($settings['oauth']??[]);
    $cacheKey=hash('sha256',(string)($oauth['client_id']??'').'|'.(string)($oauth['refresh_token']??''));
    $cached=session_status()===PHP_SESSION_ACTIVE?($_SESSION['cds_drive_token_cache']??[]):[];
    if(($cached['key']??'')===$cacheKey&&!empty($cached['token'])&&(int)($cached['expires_at']??0)>time()+60)return ['ok'=>true,'token'=>$cached['token']];
    if(!empty($oauth['client_id'])&&!empty($oauth['client_secret'])&&!empty($oauth['refresh_token'])){
        $res=cds_drive_http('https://oauth2.googleapis.com/token','POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query(['client_id'=>$oauth['client_id'],'client_secret'=>$oauth['client_secret'],'refresh_token'=>$oauth['refresh_token'],'grant_type'=>'refresh_token']));
        $json=json_decode($res['body'],true);
        if(!$res['ok']||empty($json['access_token']))return ['ok'=>false,'message'=>$json['error_description']??'Kết nối Google hết hiệu lực.'];
        if(session_status()===PHP_SESSION_ACTIVE)$_SESSION['cds_drive_token_cache']=['key'=>$cacheKey,'token'=>$json['access_token'],'expires_at'=>time()+max(300,(int)($json['expires_in']??3600)-60)];
        return ['ok'=>true,'token'=>$json['access_token']];
    }
    return ['ok'=>false,'message'=>'Chưa kết nối OAuth Google Drive.'];
}
function cds_drive_clean_filename_part(string $value): string {$value=preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u',' ',trim($value));return trim((string)preg_replace('/\s+/u',' ',$value)," .-_\t\n\r\0\x0B");}
function cds_drive_descriptive_name(string $original,string $category): string {$settings=cds_drive_settings();$extension=strtolower((string)pathinfo($original,PATHINFO_EXTENSION));$request=array_merge($_GET,$_POST);$title='';foreach(['title','document_title','lesson_title','subject','name'] as $key)if(trim((string)($request[$key]??''))!==''){$title=(string)$request[$key];break;}if($title==='')$title=(string)pathinfo($original,PATHINFO_FILENAME);$scope='';foreach(['class_name','class','lop','grade','block','khoi'] as $key)if(trim((string)($request[$key]??''))!==''){$scope=(string)$request[$key];break;}$person=trim((string)($_SESSION['cds_user']['teacher_name']??$_SESSION['cds_user']['name']??$_SESSION['cds_user']['username']??''));$rawDate=trim((string)($request['date']??$request['document_date']??''));$timestamp=$rawDate!==''?strtotime($rawDate):false;$parts=[];$seen=[];foreach([(string)($settings['types'][$category]['label']??$category),$title,$scope!==''?(preg_match('/^(lớp|khối)\b/ui',$scope)?$scope:'Lớp '.$scope):'',$person,$timestamp?date('Y-m-d',$timestamp):date('Y-m-d')] as $part){$part=cds_drive_clean_filename_part($part);$key=function_exists('mb_strtolower')?mb_strtolower($part,'UTF-8'):strtolower($part);if($part!==''&&!isset($seen[$key])){$parts[]=$part;$seen[$key]=true;}}$base=implode(' - ',$parts)?:'Tài liệu';$limit=max(40,180-($extension!==''?strlen($extension)+1:0));$base=function_exists('mb_strcut')?mb_strcut($base,0,$limit,'UTF-8'):substr($base,0,$limit);return rtrim($base).($extension!==''?'.'.preg_replace('/[^a-z0-9]+/','',$extension):'');}
function cds_drive_upload_bytes(string $bytes,string $name,string $mime,string $category): array {$settings=cds_drive_settings();$folder=trim((string)($settings['types'][$category]['folder_id']??''));if(empty($settings['enabled'])||$folder==='')return ['ok'=>false,'disabled'=>true,'message'=>'Google Drive chưa bật hoặc chưa chọn thư mục.'];$name=cds_drive_descriptive_name($name,$category);$fingerprint=hash('sha256',$folder.'|'.$bytes);$history=is_file(CDS_DRIVE_HISTORY)?json_decode((string)file_get_contents(CDS_DRIVE_HISTORY),true):[];$history=is_array($history)?$history:[];foreach($history as $old)if(($old['fingerprint']??'')===$fingerprint&&!empty($old['file_id']))return ['ok'=>true,'id'=>$old['file_id'],'path'=>'gdrive:'.$old['file_id'],'duplicate'=>true];$token=cds_drive_token($settings);if(empty($token['ok']))return $token;$boundary='cds-'.bin2hex(random_bytes(12));$meta=json_encode(['name'=>$name,'parents'=>[$folder],'appProperties'=>['cdsType'=>$category,'cdsFingerprint'=>$fingerprint]],JSON_UNESCAPED_UNICODE);$body="--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$boundary\r\nContent-Type: $mime\r\n\r\n$bytes\r\n--$boundary--";$res=cds_drive_http('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name','POST',['Authorization: Bearer '.$token['token'],'Content-Type: multipart/related; boundary='.$boundary],$body);$json=json_decode($res['body'],true);if(!$res['ok']||empty($json['id']))return ['ok'=>false,'message'=>$json['error']['message']??'Không tải được file lên Drive.'];cds_json_prepend_bounded(CDS_DRIVE_HISTORY,['at'=>date('c'),'by'=>$_SESSION['cds_user']['name']??'','action'=>'upload','type'=>$category,'name'=>$name,'file_id'=>$json['id'],'folder_id'=>$folder,'fingerprint'=>$fingerprint],1000);return ['ok'=>true,'id'=>$json['id'],'path'=>'gdrive:'.$json['id']];}
function cds_storage_handle_upload(string $field,string $category): string {$category=cds_drive_type_for_action(cds_drive_page_action(),$category);$upload=$_FILES[$field]??null;if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';$settings=cds_drive_settings();if(!empty($settings['enabled'])&&!empty($settings['types'][$category]['folder_id'])){$tmp=(string)($upload['tmp_name']??'');$bytes=$tmp!==''?file_get_contents($tmp):false;if(($upload['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||$bytes===false)throw new RuntimeException('Không đọc được file tải lên.');$mime=function_exists('mime_content_type')?(mime_content_type($tmp)?:'application/octet-stream'):'application/octet-stream';$result=cds_drive_upload_bytes($bytes,basename((string)($upload['name']??'file')),$mime,$category);if(empty($result['ok']))throw new RuntimeException($result['message']??'Không lưu được file lên Drive.');return $result['path'];}return function_exists('cm_handle_upload')?cm_handle_upload($field):'';}
function cds_storage_file_url(string $path): string {return str_starts_with($path,'gdrive:')?BASE_URL.'../admin.php?drive_file='.rawurlencode(substr($path,7)):(function_exists('cm_file_url')?cm_file_url($path):$path);}
function cds_drive_csrf_token(): string {if(empty($_SESSION['cds_drive_csrf']))$_SESSION['cds_drive_csrf']=bin2hex(random_bytes(24));return (string)$_SESSION['cds_drive_csrf'];}
}

if (!defined('TAP_SU_QUOTA_REDUCTION')) define('TAP_SU_QUOTA_REDUCTION', 2);
if (!defined('QUOTA_HIEU_TRUONG')) define('QUOTA_HIEU_TRUONG', 2);
if (!defined('QUOTA_PHO_HIEU_TRUONG')) define('QUOTA_PHO_HIEU_TRUONG', 4);

function load_json($file, $default = []) {
    return cds_json_load((string)$file, $default);
}
function save_json($file, $data) {
    return cds_json_save((string)$file, $data);
}

function assignments_file($vid) { return DATA_PATH . '/assignments_' . $vid . '.json'; }
function role_assignments_file($vid) { return DATA_PATH . '/roles_' . $vid . '.json'; }

function get_versions() { return load_json(VERSIONS_FILE, []); }
function save_versions($list) { save_json(VERSIONS_FILE, $list); }
function get_active_version_id() {
    $data = load_json(ACTIVE_VERSION_FILE, []);
    if (!empty($data['id'])) return $data['id'];
    $versions = get_versions();
    return $versions[0]['id'] ?? null;
}
function set_active_version_id($id) { save_json(ACTIVE_VERSION_FILE, ['id' => $id]); }
function get_version($id) {
    foreach (get_versions() as $v) if ($v['id'] === $id) return $v;
    return null;
}
function create_version($name, $date, $note = '', $copy_from = null) {
    $id = 'v' . date('YmdHis');
    $versions = get_versions();
    $versions[] = ['id' => $id, 'name' => $name, 'date' => $date, 'note' => $note, 'created_at' => date('c')];
    save_versions($versions);
    if ($copy_from && get_version($copy_from)) {
        $srcA = load_json(assignments_file($copy_from), []);
        $srcR = load_json(role_assignments_file($copy_from), []);
        foreach ($srcA as &$a) { $a['id'] = $id . '_' . ($a['id'] ?? uniqid()); } unset($a);
        foreach ($srcR as &$a) { $a['id'] = $id . '_' . ($a['id'] ?? uniqid()); } unset($a);
        save_json(assignments_file($id), $srcA);
        save_json(role_assignments_file($id), $srcR);
        $manualFile = DATA_PATH . '/manual_assignments.json';
        $manualRows = load_json($manualFile, []);
        $manualCopies = [];
        foreach ($manualRows as $manualRow) {
            if (!is_array($manualRow)) continue;
            $manualVersion = trim((string)($manualRow['version_id'] ?? ''));
            if ($manualVersion !== '' && $manualVersion !== $copy_from) continue;
            if ($manualVersion === '' && $copy_from !== get_active_version_id()) continue;
            $copy = $manualRow;
            $copy['id'] = 'ma_' . bin2hex(random_bytes(5));
            $copy['version_id'] = $id;
            $copy['created_at'] = date('c');
            $manualCopies[] = $copy;
        }
        if ($manualCopies) save_json($manualFile, array_merge($manualRows, $manualCopies));
    } else {
        save_json(assignments_file($id), []);
        save_json(role_assignments_file($id), []);
    }
    set_active_version_id($id);
    return $id;
}
function migrate_legacy_if_needed() {
    if (!empty(get_versions())) return;
    $id = 'v' . date('YmdHis');
    save_versions([['id'=>$id,'name'=>'Phân công lần 1','date'=>date('Y-m-d'),'note'=>'Tự tạo từ dữ liệu hiện có','created_at'=>date('c')]]);
    save_json(assignments_file($id), load_json(LEGACY_ASSIGNMENTS_FILE, []));
    save_json(role_assignments_file($id), load_json(LEGACY_ROLE_ASSIGNMENTS_FILE, []));
    set_active_version_id($id);
}
function init_data() {
    global $DEFAULT_TEACHERS, $DEFAULT_SUBJECTS, $DEFAULT_CLASSES, $DEFAULT_ROLES, $DEFAULT_GROUPS;
    if (!file_exists(TEACHERS_FILE)) save_json(TEACHERS_FILE, $DEFAULT_TEACHERS);
    if (!file_exists(SUBJECTS_FILE)) save_json(SUBJECTS_FILE, $DEFAULT_SUBJECTS);
    if (!file_exists(CLASSES_FILE)) save_json(CLASSES_FILE, $DEFAULT_CLASSES);
    if (!file_exists(ROLES_FILE)) save_json(ROLES_FILE, $DEFAULT_ROLES);
    if (!file_exists(TEACHER_META_FILE)) save_json(TEACHER_META_FILE, []);
    if (!file_exists(GROUPS_FILE)) save_json(GROUPS_FILE, $DEFAULT_GROUPS);
    if (!file_exists(SUBJECT_META_FILE)) save_json(SUBJECT_META_FILE, []);
    if (!file_exists(SETTINGS_FILE)) {
        save_json(SETTINGS_FILE, [
            'quota_thcs' => DEFAULT_QUOTA_THCS,
            'quota_thpt' => DEFAULT_QUOTA_THPT,
            'tap_su_reduction' => TAP_SU_QUOTA_REDUCTION,
            'quota_hieu_truong' => QUOTA_HIEU_TRUONG,
            'quota_pho_hieu_truong' => QUOTA_PHO_HIEU_TRUONG,
        ]);
    }
    migrate_legacy_if_needed();
}

function get_settings() {
    return load_json(SETTINGS_FILE, [
        'quota_thcs' => DEFAULT_QUOTA_THCS,
        'quota_thpt' => DEFAULT_QUOTA_THPT,
        'tap_su_reduction' => TAP_SU_QUOTA_REDUCTION,
        'quota_hieu_truong' => QUOTA_HIEU_TRUONG,
        'quota_pho_hieu_truong' => QUOTA_PHO_HIEU_TRUONG,
    ]);
}
function save_settings($s) { save_json(SETTINGS_FILE, $s); }
function get_quota_thcs() {
    $s = get_settings();
    return floatval($s['quota_thcs'] ?? DEFAULT_QUOTA_THCS);
}
function get_quota_thpt() {
    $s = get_settings();
    return floatval($s['quota_thpt'] ?? DEFAULT_QUOTA_THPT);
}
function get_tap_su_reduction() {
    $s = get_settings();
    $r = $s['tap_su_reduction'] ?? TAP_SU_QUOTA_REDUCTION;
    return is_numeric($r) ? floatval($r) : TAP_SU_QUOTA_REDUCTION;
}
function get_quota_hieu_truong() {
    $s = get_settings();
    $r = $s['quota_hieu_truong'] ?? QUOTA_HIEU_TRUONG;
    return is_numeric($r) ? floatval($r) : QUOTA_HIEU_TRUONG;
}
function get_quota_pho_hieu_truong() {
    $s = get_settings();
    $r = $s['quota_pho_hieu_truong'] ?? QUOTA_PHO_HIEU_TRUONG;
    return is_numeric($r) ? floatval($r) : QUOTA_PHO_HIEU_TRUONG;
}

function get_teachers() { global $DEFAULT_TEACHERS; return load_json(TEACHERS_FILE, $DEFAULT_TEACHERS); }
function get_subjects() { global $DEFAULT_SUBJECTS; return load_json(SUBJECTS_FILE, $DEFAULT_SUBJECTS); }
function get_classes() { global $DEFAULT_CLASSES; return load_json(CLASSES_FILE, $DEFAULT_CLASSES); }
function get_roles() { global $DEFAULT_ROLES; return load_json(ROLES_FILE, $DEFAULT_ROLES); }
function get_groups() { global $DEFAULT_GROUPS; return load_json(GROUPS_FILE, $DEFAULT_GROUPS); }
function save_groups($g) { save_json(GROUPS_FILE, $g); }

require_once __DIR__ . '/subject_meta.php';

function get_teacher_meta() { return load_json(TEACHER_META_FILE, []); }
function save_teacher_meta($meta) { save_json(TEACHER_META_FILE, $meta); }

function get_teacher_flags($name) {
    $m = get_teacher_meta()[$name] ?? [];
    $thcs = !empty($m['thcs']);
    $thpt = !empty($m['thpt']);
    if (!$thcs && !$thpt) {
        $lv = $m['level'] ?? 'THCS';
        $thcs = ($lv !== 'THPT');
        $thpt = ($lv === 'THPT');
    }
    $khxh = !empty($m['khxh']);
    $khtn = !empty($m['khtn']);
    if (!$khxh && !$khtn && !empty($m['group'])) {
        $g = mb_strtoupper($m['group'], 'UTF-8');
        if (strpos($g, 'KHXH') !== false) $khxh = true;
        if (strpos($g, 'KHTN') !== false) $khtn = true;
    }
    $cm = $m['chuyen_mon'] ?? [];
    if (is_string($cm) && $cm !== '') $cm = [$cm];
    if (!is_array($cm)) $cm = [];
    $cm = array_values(array_filter(array_map('strval', $cm)));
    return [
        'khxh' => $khxh,
        'khtn' => $khtn,
        'thcs' => $thcs,
        'thpt' => $thpt,
        'tap_su' => !empty($m['tap_su']),
        'hieu_truong' => !empty($m['hieu_truong']),
        'pho_hieu_truong' => !empty($m['pho_hieu_truong']),
        'group' => $m['group'] ?? '',
        'chuyen_mon' => $cm,
    ];
}

function set_teacher_flags($name, $flags) {
    $meta = get_teacher_meta();
    if (!isset($meta[$name])) $meta[$name] = [];
    $meta[$name]['khxh'] = !empty($flags['khxh']);
    $meta[$name]['khtn'] = !empty($flags['khtn']);
    $meta[$name]['thcs'] = !empty($flags['thcs']);
    $meta[$name]['thpt'] = !empty($flags['thpt']);
    $meta[$name]['tap_su'] = !empty($flags['tap_su']);
    $meta[$name]['hieu_truong'] = !empty($flags['hieu_truong']);
    $meta[$name]['pho_hieu_truong'] = !empty($flags['pho_hieu_truong']);
    if (!empty($meta[$name]['hieu_truong'])) $meta[$name]['pho_hieu_truong'] = false;
    if (!empty($flags['thpt']) && empty($flags['thcs'])) $meta[$name]['level'] = 'THPT';
    elseif (!empty($flags['thcs'])) $meta[$name]['level'] = 'THCS';
    else $meta[$name]['level'] = 'THCS';
    save_teacher_meta($meta);
}

function get_teacher_chuyen_mon($name) {
    return get_teacher_flags($name)['chuyen_mon'] ?? [];
}
function set_teacher_chuyen_mon($name, $subjects) {
    if (!is_array($subjects)) {
        $subjects = $subjects === '' || $subjects === null ? [] : [$subjects];
    }
    $subjects = array_values(array_unique(array_filter(array_map('trim', $subjects))));
    set_teacher_meta_field($name, 'chuyen_mon', $subjects);
}
function get_teacher_level($name) {
    $f = get_teacher_flags($name);
    if ($f['thpt'] && !$f['thcs']) return 'THPT';
    if ($f['thcs'] && !$f['thpt']) return 'THCS';
    if ($f['thpt'] && $f['thcs']) return 'THCS+THPT';
    return 'THCS';
}
function get_teacher_group($name) {
    return get_teacher_flags($name)['group'] ?? '';
}
function set_teacher_meta_field($name, $field, $value) {
    $meta = get_teacher_meta();
    if (!isset($meta[$name])) $meta[$name] = [];
    $meta[$name][$field] = $value;
    save_teacher_meta($meta);
}
function set_teacher_level($name, $level) {
    $f = get_teacher_flags($name);
    if ($level === 'THPT') { $f['thpt'] = true; $f['thcs'] = false; }
    else { $f['thcs'] = true; $f['thpt'] = false; }
    set_teacher_flags($name, $f);
}
function set_teacher_group($name, $group) {
    set_teacher_meta_field($name, 'group', $group);
}

function get_quota($name) {
    $f = get_teacher_flags($name);
    if (!empty($f['hieu_truong'])) return get_quota_hieu_truong();
    if (!empty($f['pho_hieu_truong'])) return get_quota_pho_hieu_truong();
    if ($f['thpt'] && !$f['thcs']) $q = get_quota_thpt();
    else $q = get_quota_thcs();
    if (!empty($f['tap_su'])) $q = max(0, $q - get_tap_su_reduction());
    return $q;
}

function get_assignments($vid = null) {
    $vid = $vid ?: get_active_version_id();
    if (!$vid) return [];
    $rows = load_json(assignments_file($vid), []);
    // Trang thêm/danh sách đã có bộ hiển thị tương tác riêng cho phân công thủ công.
    // Các trang còn lại nhận dữ liệu đã chuẩn hóa tại đây để tra cứu, thống kê và xuất bảng thống nhất.
    $page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
    if (!in_array($page, ['them', 'danhsach'], true)) $rows = array_merge($rows, get_manual_assignments($vid));
    return $rows;
}
function get_manual_assignments($vid = null) {
    $vid = $vid ?: get_active_version_id();
    if (!$vid) return [];
    $active = get_active_version_id();
    $manual = load_json(DATA_PATH . '/manual_assignments.json', []);
    $rows = [];
    foreach ($manual as $row) {
        if (!is_array($row)) continue;
        $rowVersion = trim((string)($row['version_id'] ?? ''));
        // Dữ liệu cũ chưa có version_id thuộc phiên bản đang hoạt động tại thời điểm nâng cấp.
        if (($rowVersion !== '' && $rowVersion !== $vid) || ($rowVersion === '' && $vid !== $active)) continue;
        $rows[] = [
            'id' => (string)($row['id'] ?? ''),
            'teacher' => trim((string)($row['teacher'] ?? '')),
            'subject' => trim((string)($row['subject'] ?? '')),
            'class' => trim((string)($row['class_name'] ?? $row['class'] ?? '')),
            'periods' => (float)($row['periods'] ?? 0),
            'note' => trim((string)($row['note'] ?? '')),
            'is_manual' => true,
        ];
    }
    return $rows;
}
function save_assignments($data, $vid = null) {
    save_json(assignments_file($vid ?: get_active_version_id()), $data);
}
function get_role_assignments($vid = null) {
    $vid = $vid ?: get_active_version_id();
    if (!$vid) return [];
    $items = load_json(role_assignments_file($vid), []);
    $map = [];
    foreach (get_roles() as $r) $map[$r['name']] = floatval($r['periods'] ?? 0);
    $changed = false;
    foreach ($items as &$a) {
        if (!isset($a['periods']) || $a['periods'] === '' || $a['periods'] === null) {
            $a['periods'] = $map[$a['role']] ?? 0; $changed = true;
        }
    }
    unset($a);
    if ($changed) save_json(role_assignments_file($vid), $items);
    return $items;
}
function save_role_assignments($data, $vid = null) {
    save_json(role_assignments_file($vid ?: get_active_version_id()), $data);
}

function ten_cuoi($hoten) {
    $parts = preg_split('/\s+/u', trim($hoten));
    return mb_strtolower(end($parts) ?: $hoten, 'UTF-8');
}
function sort_teachers_by_ten($teachers) {
    usort($teachers, function($a, $b) {
        $cmp = strcmp(ten_cuoi($a), ten_cuoi($b));
        return $cmp !== 0 ? $cmp : strcmp(mb_strtolower($a,'UTF-8'), mb_strtolower($b,'UTF-8'));
    });
    return $teachers;
}
function get_teachers_sorted() { return sort_teachers_by_ten(get_teachers()); }

function get_teacher_loads($vid = null) {
    $load = [];
    foreach (get_assignments($vid) as $a) {
        $t = $a['teacher'];
        if (!isset($load[$t])) $load[$t] = ['day'=>0,'role'=>0,'total'=>0,'classes'=>[],'subjects'=>[],'roles'=>[]];
        $load[$t]['day'] += floatval($a['periods'] ?? 0);
        if (!empty($a['class'])) $load[$t]['classes'][$a['class']] = true;
        $sub = $a['subject'] ?? '';
        if ($sub !== '') {
            if (!isset($load[$t]['subjects'][$sub])) $load[$t]['subjects'][$sub] = [];
            $load[$t]['subjects'][$sub][] = ($a['class'] ?? '') . '(' . ($a['periods'] ?? 0) . ')';
        }
    }
    foreach (get_role_assignments($vid) as $a) {
        $t = $a['teacher'];
        if (!isset($load[$t])) $load[$t] = ['day'=>0,'role'=>0,'total'=>0,'classes'=>[],'subjects'=>[],'roles'=>[]];
        $load[$t]['role'] += floatval($a['periods'] ?? 0);
        if (!empty($a['class'])) $load[$t]['classes'][$a['class']] = true;
        $label = $a['role'] ?? '';
        if (!empty($a['class'])) $label .= ' (' . $a['class'] . ')';
        if ($label !== '') $load[$t]['roles'][] = $label;
    }
    foreach ($load as $t => &$row) {
        $row['total'] = $row['day'] + $row['role'];
        $row['class_count'] = count($row['classes']);
        $row['level'] = get_teacher_level($t);
        $row['group'] = get_teacher_group($t);
        $row['flags'] = get_teacher_flags($t);
        $row['chuyen_mon'] = get_teacher_chuyen_mon($t);
        $row['quota'] = get_quota($t);
        $row['diff'] = $row['total'] - $row['quota'];
        $parts = []; ksort($row['subjects']);
        foreach ($row['subjects'] as $s => $cls) $parts[] = $s . ': ' . implode(', ', $cls);
        $row['mon_day'] = implode('; ', $parts);
        $row['kiem_nhiem'] = implode('; ', $row['roles']);
        unset($row['classes'], $row['subjects'], $row['roles']);
    }
    unset($row);
    return $load;
}

function get_export_rows($vid = null) {
    $loads = get_teacher_loads($vid);
    $teachers = get_teachers_sorted();
    $rows = [];
    foreach ($teachers as $t) {
        $r = $loads[$t] ?? null;
        $rows[] = [
            'name' => $t,
            'level' => $r['level'] ?? get_teacher_level($t),
            'group' => $r['group'] ?? get_teacher_group($t),
            'chuyen_mon' => $r['chuyen_mon'] ?? get_teacher_chuyen_mon($t),
            'mon_day' => $r['mon_day'] ?? '',
            'kiem_nhiem' => $r['kiem_nhiem'] ?? '',
            'day' => $r['day'] ?? 0,
            'role' => $r['role'] ?? 0,
            'total' => $r['total'] ?? 0,
            'quota' => $r['quota'] ?? get_quota($t),
            'diff' => $r['diff'] ?? -get_quota($t),
        ];
    }
    foreach ($loads as $t => $r) {
        if (!in_array($t, $teachers, true)) {
            $rows[] = ['name'=>$t,'level'=>$r['level'],'group'=>$r['group']??'','chuyen_mon'=>$r['chuyen_mon']??[],'mon_day'=>$r['mon_day'],'kiem_nhiem'=>$r['kiem_nhiem'],'day'=>$r['day'],'role'=>$r['role'],'total'=>$r['total'],'quota'=>$r['quota'],'diff'=>$r['diff']];
        }
    }
    return $rows;
}

function get_grade($class_name) { return preg_replace('/[^0-9]/', '', $class_name); }

function resolve_std_period($grades_data, $class_name) {
    if (!is_array($grades_data)) return null;
    if (isset($grades_data[$class_name]) && $grades_data[$class_name] !== '' && $grades_data[$class_name] !== null) {
        return floatval($grades_data[$class_name]);
    }
    $grade = get_grade($class_name);
    if ($grade !== '' && isset($grades_data[$grade]) && $grades_data[$grade] !== '' && $grades_data[$grade] !== null) {
        return floatval($grades_data[$grade]);
    }
    return null;
}

function get_periods($subject, $class_name) {
    $subjects = get_subjects();
    if (!isset($subjects[$subject])) return null;
    $level = (intval(get_grade($class_name)) >= 10) ? 'thpt' : 'thcs';
    if (!is_subject_visible_for_level($subject, $level)) return null;
    $v = resolve_std_period($subjects[$subject], $class_name);
    if ($v === null || $v <= 0) return null;
    return $v;
}

function get_assignment_stats($vid = null) {
    $vid = $vid ?: get_active_version_id();
    $subjects = get_subjects();
    $classes = get_classes();
    $assignments = get_assignments($vid);
    $role_items = get_role_assignments($vid);

    $map = [];
    $by_class_assigned = [];
    $by_subject_assigned = [];
    $total_day = 0;
    foreach ($assignments as $a) {
        $p = floatval($a['periods'] ?? 0);
        $total_day += $p;
        $cls = $a['class'] ?? '';
        $sub = $a['subject'] ?? '';
        $key = $sub . '|' . $cls;
        if (!isset($map[$key])) $map[$key] = ['periods' => 0, 'teachers' => []];
        $map[$key]['periods'] += $p;
        if (!empty($a['teacher'])) $map[$key]['teachers'][] = $a['teacher'];
        $by_class_assigned[$cls] = ($by_class_assigned[$cls] ?? 0) + $p;
        $by_subject_assigned[$sub] = ($by_subject_assigned[$sub] ?? 0) + $p;
    }

    $total_role = 0;
    foreach ($role_items as $a) $total_role += floatval($a['periods'] ?? 0);

    $by_class = [];
    $by_grade = [];
    $by_subject = [];
    $missing_list = [];
    $diff_list = [];
    $conflict_list = [];
    $slots_ok = 0;
    $slots_missing = 0;
    $slots_diff = 0;
    $total_std = 0;
    $classes_ok = 0;

    foreach ($map as $key => $info) {
        $teachers = array_values(array_unique($info['teachers']));
        if (count($teachers) > 1) {
            [$sub, $cls] = explode('|', $key, 2);
            $conflict_list[] = ['subject' => $sub, 'class' => $cls, 'teachers' => $teachers];
        }
    }

    foreach ($classes as $cls) {
        $grade = get_grade($cls);
        $level = (intval($grade) >= 10) ? 'THPT' : 'THCS';
        $level_key = (intval($grade) >= 10) ? 'thpt' : 'thcs';
        $std = 0;
        $assigned = floatval($by_class_assigned[$cls] ?? 0);
        $miss = [];
        $pdiffs = [];

        foreach ($subjects as $sub => $grades) {
            if (!is_subject_visible_for_level($sub, $level_key)) continue;
            $sp = resolve_std_period($grades, $cls);
            if ($sp === null || $sp <= 0) continue;

            $std += $sp;
            if (!isset($by_subject[$sub])) {
                $by_subject[$sub] = ['std' => 0, 'assigned' => 0, 'ok' => 0, 'missing' => 0, 'diff_count' => 0];
            }
            $by_subject[$sub]['std'] += $sp;

            $key = $sub . '|' . $cls;
            $ap = floatval($map[$key]['periods'] ?? 0);

            if ($ap <= 0.0001) {
                $slots_missing++;
                $miss[] = $sub . ' (' . rtrim(rtrim(number_format($sp, 2, '.', ''), '0'), '.') . 't)';
                $missing_list[] = ['class' => $cls, 'subject' => $sub, 'std' => $sp];
                $by_subject[$sub]['missing']++;
            } elseif (abs($ap - $sp) > 0.05) {
                $slots_diff++;
                $pdiffs[] = $sub . ': ' . $ap . '/' . $sp;
                $diff_list[] = [
                    'class' => $cls,
                    'subject' => $sub,
                    'std' => $sp,
                    'assigned' => $ap,
                    'teachers' => $map[$key]['teachers'] ?? [],
                ];
                $by_subject[$sub]['diff_count']++;
            } else {
                $slots_ok++;
                $by_subject[$sub]['ok']++;
            }
        }

        $total_std += $std;
        $diff = $assigned - $std;
        $status = 'ok';
        if ($miss) $status = 'missing';
        elseif ($pdiffs) $status = 'diff';
        if ($status === 'ok') $classes_ok++;

        $by_class[] = [
            'class' => $cls, 'grade' => $grade, 'level' => $level,
            'std' => $std, 'assigned' => $assigned, 'diff' => $diff,
            'missing' => $miss, 'period_diffs' => $pdiffs, 'status' => $status,
        ];

        if (!isset($by_grade[$grade])) {
            $by_grade[$grade] = ['level'=>$level,'class_count'=>0,'std'=>0,'assigned'=>0,'std_per_class'=>0,'classes_ok'=>0];
        }
        $by_grade[$grade]['class_count']++;
        $by_grade[$grade]['std'] += $std;
        $by_grade[$grade]['assigned'] += $assigned;
        if ($status === 'ok') $by_grade[$grade]['classes_ok']++;
        $by_grade[$grade]['std_per_class'] = $std;
    }

    foreach ($by_subject as $sub => &$row) {
        $row['assigned'] = floatval($by_subject_assigned[$sub] ?? 0);
        $row['diff'] = $row['assigned'] - $row['std'];
    }
    unset($row);
    ksort($by_subject);

    foreach ($by_grade as $g => &$row) {
        $row['diff'] = $row['assigned'] - $row['std'];
        if ($row['class_count'] > 0 && $row['std_per_class'] <= 0) {
            $row['std_per_class'] = $row['std'] / $row['class_count'];
        }
    }
    unset($row);
    ksort($by_grade, SORT_NUMERIC);

    return [
        'total_assigned' => $total_day, 'total_std' => $total_std,
        'total_diff' => $total_day - $total_std, 'total_role' => $total_role,
        'slots_ok' => $slots_ok, 'slots_missing' => $slots_missing,
        'slots_diff' => $slots_diff, 'slots_conflict' => count($conflict_list),
        'classes_ok' => $classes_ok, 'classes_total' => count($classes),
        'by_class' => $by_class, 'by_grade' => $by_grade, 'by_subject' => $by_subject,
        'missing_list' => $missing_list, 'diff_list' => $diff_list, 'conflict_list' => $conflict_list,
    ];
}

function flash($message, $type = 'success') { $_SESSION['flash'] = ['message'=>$message,'type'=>$type]; }

function show_flash() {
    if (empty($_SESSION['flash'])) return;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = $f['type'] ?? 'success';
    $msg = htmlspecialchars($f['message'] ?? '', ENT_QUOTES, 'UTF-8');
    $icon = 'bi-check-circle-fill';
    if ($type === 'danger') $icon = 'bi-x-circle-fill';
    elseif ($type === 'warning') $icon = 'bi-exclamation-triangle-fill';
    elseif ($type === 'info') $icon = 'bi-info-circle-fill';
    echo '<div id="pccm-toast" class="pccm-toast pccm-toast-' . htmlspecialchars($type) . '" role="status">'
        . '<i class="bi ' . $icon . ' pccm-toast-icon"></i>'
        . '<span class="pccm-toast-msg">' . $msg . '</span>'
        . '<button type="button" class="pccm-toast-close" onclick="this.parentElement.remove()" aria-label="Đóng">&times;</button>'
        . '</div>';
}

function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function cds_permission_rank($level) {
    return cds_cm_permission_rank((string)$level);
}

function cds_default_chuyenmon_groups() {
    return cds_cm_default_group_access();
}

function cds_refresh_chuyenmon_session() {
    static $done = false;
    if ($done) return;
    $done = true;
    $sessionUser = is_array($_SESSION['cds_user'] ?? null) ? $_SESSION['cds_user'] : null;
    if (!$sessionUser) return;

    $usersFile = dirname(BASE_PATH) . '/data/users.json';
    $users = load_json($usersFile, []);
    $record = null;
    foreach ($users as $candidate) {
        if (!empty($sessionUser['id']) && ($candidate['id'] ?? '') === $sessionUser['id']) { $record = $candidate; break; }
        if (empty($sessionUser['id']) && strcasecmp($candidate['username'] ?? '', $sessionUser['username'] ?? '') === 0) { $record = $candidate; break; }
    }
    if (!$record || empty($record['active'])) {
        unset($_SESSION['cds_user'], $_SESSION['pccm_admin']);
        return;
    }

    $_SESSION['cds_user'] = cds_session_user_from_record($record);

    $_SESSION['pccm_admin'] = ($record['role']??'') === 'admin'
        || cds_can_feature_for_user($record, 'cm.pccm', 'edit')
        || cds_can_feature_for_user($record, 'cm.nhaplieu', 'edit')
        || cds_can_feature_for_user($record, 'cm.kehoach', 'edit')
        || cds_can_feature_for_user($record, 'cm.baocao', 'edit')
        || cds_can_feature_for_user($record, 'cm.baocao.dinhky', 'edit')
        || cds_can_feature_for_user($record, 'cm.baocao.tiendo', 'edit')
        || cds_can_feature_for_user($record, 'cm.baocao.dugio', 'edit')
        || cds_can_feature_for_user($record, 'cm.baocao.kythi', 'edit');
}

function cds_feature_access_for_user($user, $code) {
    return cds_cm_feature_access_for_user((array)$user, (string)$code, dirname(BASE_PATH) . '/data');
}

function cds_can_feature_for_user($user, $code, $level = 'view') {
    return cds_permission_rank(cds_feature_access_for_user($user, $code)) >= cds_permission_rank($level);
}

function cds_user() {
    cds_refresh_chuyenmon_session();
    return is_array($_SESSION['cds_user'] ?? null) ? $_SESSION['cds_user'] : null;
}

function cds_can_feature($code, $level = 'view') {
    $user = cds_user();
    return $user ? cds_can_feature_for_user($user, $code, $level) : false;
}

function cds_current_page_feature() {
    $page = basename($_SERVER['PHP_SELF'] ?? '', '.php');
    $map = [
        'index'=>'cm.dashboard', 'tracuu'=>'cm.tracuu', 'ketqua'=>'cm.tracuu',
        'tongquan'=>'cm.pccm', 'them'=>'cm.pccm', 'danhsach'=>'cm.pccm',
        'doicheo'=>'cm.pccm', 'rasoat'=>'cm.pccm', 'sua'=>'cm.pccm',
        'giaovien'=>'cm.nhaplieu', 'monhoc'=>'cm.nhaplieu', 'lop'=>'cm.nhaplieu',
        'kiemnhiem'=>'cm.nhaplieu', 'thongke'=>'cm.thongke', 'xuat_bang'=>'cm.thongke',
        'kehoach'=>'cm.kehoach', 'sodaubai'=>'cm.dashboard', 'sodaubai_export'=>'cm.dashboard', 'sodaubai_ppct_template'=>'cm.dashboard', 'sodaubai_ppct_import_v2'=>'cm.dashboard',
        'dugio'=>'cm.baocao.dugio', 'danhgia'=>'cm.baocao.dugio', 'kiemtrahoso'=>'cm.baocao.kythi',
    ];
    if ($page === 'baocao') {
        $tab = $_GET['tab'] ?? 'dinhky';
        if ($tab === 'thang') $tab = 'dinhky';
        $reportMap = [
            'dinhky'=>'cm.baocao.dinhky',
            'tiendo'=>'cm.baocao.tiendo',
            'dugio'=>'cm.baocao.dugio',
            'kythi'=>'cm.baocao.kythi',
        ];
        return $reportMap[$tab] ?? 'cm.baocao.dinhky';
    }
    return $map[$page] ?? 'cm.dashboard';
}

function is_logged_in() {
    $user = cds_user();
    return $user && cds_can_feature(cds_current_page_feature(), 'view');
}

function require_login() {
    if (!cds_user()) {
        $next = $_SERVER['REQUEST_URI'] ?? (BASE_URL . 'index.php');
        header('Location: /login.php?next=' . urlencode($next));
        exit;
    }

    if (!is_logged_in()) {
        http_response_code(403);
        exit('Tài khoản chưa được cấp quyền cho chức năng Chuyên môn này.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = strtolower(trim((string)($_POST['action'] ?? '')));
        $observationSelfService = cds_current_page_feature() === 'cm.baocao.dugio'
            && in_array($action, ['observation_save','observation_review'], true);
        $fileCheckSelfService = cds_current_page_feature() === 'cm.baocao.kythi'
            && $action === 'file_check_save';
        $educationPlanSelfService = cds_current_page_feature() === 'cm.kehoach'
            && in_array($action, ['save_plan', 'delete_plan'], true);
        $lessonBookScript = basename($_SERVER['PHP_SELF'] ?? '');
        $lessonBookSelfService = ($lessonBookScript === 'sodaubai.php' && in_array($action, ['save_record','sign_record','upload_signature'], true))
            || ($lessonBookScript === 'sodaubai_export.php' && $action === 'export_book')
            || ($lessonBookScript === 'sodaubai_ppct_import_v2.php' && $action === 'import_curriculum');
        $requiredLevel = ($observationSelfService || $fileCheckSelfService || $educationPlanSelfService || $lessonBookSelfService)
            ? 'view'
            : (str_contains($action, 'delete') || str_contains($action, 'xoa') ? 'delete' : 'edit');
        if (!cds_can_feature(cds_current_page_feature(), $requiredLevel)) {
            http_response_code(403);
            exit('Tài khoản chưa được cấp quyền ' . ($requiredLevel === 'delete' ? 'xóa' : 'cập nhật') . ' cho chức năng Chuyên môn này.');
        }
    }
}

if (session_status() === PHP_SESSION_NONE) session_start();
cds_drive_register_action(cds_drive_page_action());
init_data();
$__vid = get_active_version_id();
if ($__vid) {
    if (!defined('ASSIGNMENTS_FILE')) define('ASSIGNMENTS_FILE', assignments_file($__vid));
    if (!defined('ROLE_ASSIGNMENTS_FILE')) define('ROLE_ASSIGNMENTS_FILE', role_assignments_file($__vid));
} else {
    if (!defined('ASSIGNMENTS_FILE')) define('ASSIGNMENTS_FILE', LEGACY_ASSIGNMENTS_FILE);
    if (!defined('ROLE_ASSIGNMENTS_FILE')) define('ROLE_ASSIGNMENTS_FILE', LEGACY_ROLE_ASSIGNMENTS_FILE);
}
if (!defined('QUOTA_THCS')) define('QUOTA_THCS', get_quota_thcs());
if (!defined('QUOTA_THPT')) define('QUOTA_THPT', get_quota_thpt());
