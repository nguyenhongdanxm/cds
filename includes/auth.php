<?php
require_once __DIR__ . '/config.php';

/* Kho Google Drive: OAuth cho Drive của tôi, giữ tương thích Service Account cũ. */
if (!defined('CDS_DRIVE_SETTINGS')) define('CDS_DRIVE_SETTINGS', DATA_PATH.'/google_drive_settings.json');
if (!defined('CDS_DRIVE_HISTORY')) define('CDS_DRIVE_HISTORY', DATA_PATH.'/google_drive_history.json');
if (!defined('CDS_DRIVE_ACTIONS')) define('CDS_DRIVE_ACTIONS', DATA_PATH.'/google_drive_actions.json');
function cds_drive_default_types(): array {return ['documents'=>['label'=>'Văn bản','folder_id'=>'','prefix'=>'Van-ban'],'plans'=>['label'=>'Kế hoạch và báo cáo','folder_id'=>'','prefix'=>'Ke-hoach'],'education_plans'=>['label'=>'Kế hoạch giáo dục','folder_id'=>'','prefix'=>'Ke-hoach-giao-duc'],'photos'=>['label'=>'Ảnh học sinh','folder_id'=>'','prefix'=>'Anh-hoc-sinh'],'observations'=>['label'=>'Phiếu dự giờ','folder_id'=>'','prefix'=>'Phieu-du-gio'],'duty_reports'=>['label'=>'Biên bản trực nội trú','folder_id'=>'','prefix'=>'Bien-ban-truc'],'meal_reports'=>['label'=>'Thống kê ăn các lớp','folder_id'=>'','prefix'=>'Thong-ke-an'],'room_meal_lists'=>['label'=>'Danh sách phòng và mâm','folder_id'=>'','prefix'=>'Danh-sach']];}
function cds_drive_settings(): array {$raw=is_file(CDS_DRIVE_SETTINGS)?json_decode((string)file_get_contents(CDS_DRIVE_SETTINGS),true):[];$raw=is_array($raw)?$raw:[];$legacy=(array)($raw['folders']??[]);$types=is_array($raw['types']??null)?$raw['types']:[];foreach(cds_drive_default_types() as $key=>$type){$types[$key]=array_merge($type,is_array($types[$key]??null)?$types[$key]:[]);if(empty($types[$key]['folder_id'])&&!empty($legacy[$key]))$types[$key]['folder_id']=$legacy[$key];}return array_merge(['enabled'=>false,'provider'=>'oauth','oauth'=>['client_id'=>'','client_secret'=>'','refresh_token'=>'','email'=>''],'client_email'=>'','private_key'=>'','types'=>$types,'bindings'=>[]],$raw,['types'=>$types,'bindings'=>is_array($raw['bindings']??null)?$raw['bindings']:[]]);}
function cds_drive_save_settings(array $settings): bool {return false!==file_put_contents(CDS_DRIVE_SETTINGS,json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);}
function cds_drive_page_action(?string $uri=null): string {$path=parse_url($uri??($_SERVER['REQUEST_URI']??($_SERVER['SCRIPT_NAME']??'')),PHP_URL_PATH)?:'';$path='/'.ltrim($path,'/');$query=[];parse_str((string)parse_url($uri??($_SERVER['REQUEST_URI']??''),PHP_URL_QUERY),$raw);foreach(['tab','view','mode'] as $key)if(isset($raw[$key])&&preg_match('/^[a-zA-Z0-9_-]+$/',(string)$raw[$key]))$query[$key]=(string)$raw[$key];return 'page:'.$path.($query?'?'.http_build_query($query):'');}
function cds_drive_type_for_action(string $action,string $fallback): string {$settings=cds_drive_settings();$mapped=trim((string)($settings['bindings'][$action]??''));return $mapped!==''&&isset($settings['types'][$mapped])?$mapped:$fallback;}
function cds_drive_register_action(string $action,string $label=''): void {if(!str_starts_with($action,'page:'))return;$rows=is_file(CDS_DRIVE_ACTIONS)?json_decode((string)file_get_contents(CDS_DRIVE_ACTIONS),true):[];$rows=is_array($rows)?$rows:[];$rows[$action]=['label'=>$label!==''?$label:ucwords(str_replace(['-','_','.php','/'],' ',substr($action,5))),'last_seen'=>date('c')];file_put_contents(CDS_DRIVE_ACTIONS,json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);}
function cds_drive_action_catalog(): array {$builtins=['page:/vanban.php'=>['label'=>'Văn bản','default_type'=>'documents'],'page:/chuyenmon/kehoach.php'=>['label'=>'Chuyên môn · Kế hoạch','default_type'=>'plans'],'page:/chuyenmon/kehoach.php?tab=vanban'=>['label'=>'Chuyên môn · Kế hoạch giáo dục','default_type'=>'education_plans'],'page:/chuyenmon/baocao.php'=>['label'=>'Chuyên môn · Báo cáo','default_type'=>'plans'],'page:/chuyenmon/phieudugio.php'=>['label'=>'Chuyên môn · Phiếu dự giờ','default_type'=>'observations'],'page:/noitru.php?tab=duty_report'=>['label'=>'Nội trú · Biên bản trực','default_type'=>'duty_reports'],'page:/noitru.php?tab=meals'=>['label'=>'Nội trú · Báo ăn theo lớp','default_type'=>'meal_reports'],'page:/noitru.php?tab=meal_summary'=>['label'=>'Nội trú · Tổng hợp bữa ăn','default_type'=>'meal_reports'],'page:/noitru_assign.php?mode=rooms'=>['label'=>'Nội trú · Chia phòng','default_type'=>'room_meal_lists'],'page:/noitru_assign.php?mode=meals'=>['label'=>'Nội trú · Chia mâm','default_type'=>'room_meal_lists']];$seen=is_file(CDS_DRIVE_ACTIONS)?json_decode((string)file_get_contents(CDS_DRIVE_ACTIONS),true):[];$rows=$builtins;foreach(is_array($seen)?$seen:[] as $key=>$row)$rows[$key]=array_merge($rows[$key]??['default_type'=>''],is_array($row)?$row:[]);uasort($rows,fn($a,$b)=>strnatcasecmp((string)($a['label']??''),(string)($b['label']??'')));return $rows;}
function cds_drive_history(): array {$rows=is_file(CDS_DRIVE_HISTORY)?json_decode((string)file_get_contents(CDS_DRIVE_HISTORY),true):[];return is_array($rows)?$rows:[];}
function cds_drive_history_add(array $row): void {$rows=cds_drive_history();array_unshift($rows,array_merge(['at'=>date('c'),'by'=>$_SESSION['cds_user']['name']??'Hệ thống'],$row));file_put_contents(CDS_DRIVE_HISTORY,json_encode(array_slice($rows,0,1000),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);}
function cds_drive_http(string $url,string $method='GET',array $headers=[],string $body=''): array {if(!function_exists('curl_init'))return ['ok'=>false,'status'=>0,'body'=>'','error'=>'Hosting chưa bật PHP cURL.'];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>90,CURLOPT_CONNECTTIMEOUT=>15]);if($body!=='')curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$response=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return ['ok'=>$response!==false&&$status>=200&&$status<300,'status'=>$status,'body'=>(string)$response,'error'=>$error];}
function cds_drive_redirect_uri(): string {return 'https://'.($_SERVER['HTTP_HOST']??'cds.noitruxinman.edu.vn').BASE_URL.'admin.php?view=drive&oauth=callback';}
function cds_drive_oauth_url(array $settings): string {$oauth=(array)($settings['oauth']??[]);$state=bin2hex(random_bytes(20));$_SESSION['cds_drive_oauth_state']=$state;return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$oauth['client_id']??'','redirect_uri'=>cds_drive_redirect_uri(),'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/drive','access_type'=>'offline','prompt'=>'consent','include_granted_scopes'=>'true','state'=>$state]);}
function cds_drive_oauth_exchange(array &$settings,string $code): array {$oauth=(array)($settings['oauth']??[]);$res=cds_drive_http('https://oauth2.googleapis.com/token','POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query(['code'=>$code,'client_id'=>$oauth['client_id']??'','client_secret'=>$oauth['client_secret']??'','redirect_uri'=>cds_drive_redirect_uri(),'grant_type'=>'authorization_code']));$json=json_decode($res['body'],true);if(!$res['ok']||empty($json['access_token']))return ['ok'=>false,'message'=>$json['error_description']??'Không đổi được mã kết nối Google.'];if(!empty($json['refresh_token']))$settings['oauth']['refresh_token']=$json['refresh_token'];$settings['provider']='oauth';cds_drive_save_settings($settings);return ['ok'=>true];}
function cds_drive_b64url(string $value): string {return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
function cds_drive_token(array $settings=[]): array {
    $settings=$settings?:cds_drive_settings();
    $oauth=(array)($settings['oauth']??[]);
    $provider=!empty($oauth['client_id'])&&!empty($oauth['refresh_token'])?'oauth':'service';
    $identity=$provider==='oauth'?((string)($oauth['client_id']??'').'|'.(string)($oauth['refresh_token']??'')):(string)($settings['client_email']??'');
    $cacheKey=hash('sha256',$provider.'|'.$identity);
    $cached=session_status()===PHP_SESSION_ACTIVE?($_SESSION['cds_drive_token_cache']??[]):[];
    if(($cached['key']??'')===$cacheKey&&!empty($cached['token'])&&(int)($cached['expires_at']??0)>time()+60)return ['ok'=>true,'token'=>$cached['token'],'provider'=>$provider];
    if($provider==='oauth'&&!empty($oauth['client_secret'])){
        $res=cds_drive_http('https://oauth2.googleapis.com/token','POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query(['client_id'=>$oauth['client_id'],'client_secret'=>$oauth['client_secret'],'refresh_token'=>$oauth['refresh_token'],'grant_type'=>'refresh_token']));
        $json=json_decode($res['body'],true);
        if(!$res['ok']||empty($json['access_token']))return ['ok'=>false,'message'=>$json['error_description']??'Kết nối Google đã hết hiệu lực.'];
        if(session_status()===PHP_SESSION_ACTIVE)$_SESSION['cds_drive_token_cache']=['key'=>$cacheKey,'token'=>$json['access_token'],'expires_at'=>time()+max(300,(int)($json['expires_in']??3600)-60)];
        return ['ok'=>true,'token'=>$json['access_token'],'provider'=>'oauth'];
    }
    $email=trim((string)($settings['client_email']??''));$key=(string)($settings['private_key']??'');
    if($email===''||$key==='')return ['ok'=>false,'message'=>'Chưa kết nối tài khoản Google.'];
    $now=time();$head=cds_drive_b64url(json_encode(['alg'=>'RS256','typ'=>'JWT']));$claim=cds_drive_b64url(json_encode(['iss'=>$email,'scope'=>'https://www.googleapis.com/auth/drive','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now-30,'exp'=>$now+3500]));$input=$head.'.'.$claim;
    if(!openssl_sign($input,$sig,$key,OPENSSL_ALGO_SHA256))return ['ok'=>false,'message'=>'Khóa Service Account không hợp lệ.'];
    $res=cds_drive_http('https://oauth2.googleapis.com/token','POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$input.'.'.cds_drive_b64url($sig)]));$json=json_decode($res['body'],true);
    if(!$res['ok']||empty($json['access_token']))return ['ok'=>false,'message'=>$json['error_description']??'Không kết nối được Service Account.'];
    if(session_status()===PHP_SESSION_ACTIVE)$_SESSION['cds_drive_token_cache']=['key'=>$cacheKey,'token'=>$json['access_token'],'expires_at'=>time()+max(300,(int)($json['expires_in']??3600)-60)];
    return ['ok'=>true,'token'=>$json['access_token'],'provider'=>'service'];
}
function cds_drive_folder(string $type,array $settings=[]): string {$settings=$settings?:cds_drive_settings();return trim((string)($settings['types'][$type]['folder_id']??''));}
function cds_drive_clean_filename_part(string $value): string {$value=preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u',' ',trim($value));$value=preg_replace('/\s+/u',' ',$value);return trim((string)$value," .-_\t\n\r\0\x0B");}
function cds_drive_descriptive_name(string $original,string $type,array $extra=[]): string {$settings=cds_drive_settings();$extension=strtolower((string)pathinfo($original,PATHINFO_EXTENSION));$stem=(string)pathinfo($original,PATHINFO_FILENAME);$request=array_merge($_GET,$_POST);$pick=function(array $keys)use($extra,$request){foreach($keys as $key){$value=trim((string)($extra[$key]??$request[$key]??''));if($value!=='')return $value;}return '';};$label=(string)($settings['types'][$type]['label']??$type);$title=$pick(['title','document_title','lesson_title','subject','name']);if($title==='')$title=$stem;$scope=$pick(['class_name','class','lop','grade','block','khoi']);$person=$pick(['teacher_name','teacher','account_name']);if($person==='')$person=trim((string)($_SESSION['cds_user']['teacher_name']??$_SESSION['cds_user']['name']??$_SESSION['cds_user']['username']??''));$rawDate=$pick(['date','document_date','report_date','created_at']);$timestamp=$rawDate!==''?strtotime($rawDate):false;$date=$timestamp?date('Y-m-d',$timestamp):date('Y-m-d');$parts=[];$seen=[];foreach([$label,$title,$scope!==''?(preg_match('/^(lớp|khối)\b/ui',$scope)?$scope:'Lớp '.$scope):'',$person,$date] as $part){$part=cds_drive_clean_filename_part($part);$key=function_exists('mb_strtolower')?mb_strtolower($part,'UTF-8'):strtolower($part);if($part!==''&&!isset($seen[$key])){$parts[]=$part;$seen[$key]=true;}}$base=implode(' - ',$parts)?:'Tài liệu';$limit=max(40,180-($extension!==''?strlen($extension)+1:0));if(function_exists('mb_strcut'))$base=mb_strcut($base,0,$limit,'UTF-8');else $base=substr($base,0,$limit);return rtrim($base).($extension!==''?'.'.preg_replace('/[^a-z0-9]+/','',$extension):'');}
function cds_drive_test_folder(string $type,array $settings=[]): array {$settings=$settings?:cds_drive_settings();$folder=cds_drive_folder($type,$settings);if($folder==='')return ['ok'=>false,'message'=>'Chưa nhập ID thư mục.'];$token=cds_drive_token($settings);if(empty($token['ok']))return $token;$res=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($folder).'?supportsAllDrives=true&fields=id,name,mimeType,capabilities(canAddChildren)','GET',['Authorization: Bearer '.$token['token']]);$json=json_decode($res['body'],true);if(!$res['ok'])return ['ok'=>false,'message'=>$json['error']['message']??'Không truy cập được thư mục.'];if(($json['mimeType']??'')!=='application/vnd.google-apps.folder'||empty($json['capabilities']['canAddChildren']))return ['ok'=>false,'message'=>'Không có quyền thêm file vào thư mục này.'];return ['ok'=>true,'name'=>$json['name']??$type];}
function cds_drive_upload_bytes(string $bytes,string $name,string $mime,string $type,array $extra=[]): array {$settings=cds_drive_settings();$folder=cds_drive_folder($type,$settings);if(empty($settings['enabled'])||$folder==='')return ['ok'=>false,'disabled'=>true,'message'=>'Drive chưa bật hoặc loại này chưa có thư mục.'];$name=cds_drive_descriptive_name($name,$type,$extra);$fingerprint=hash('sha256',$folder.'|'.$bytes);foreach(cds_drive_history() as $old)if(($old['fingerprint']??'')===$fingerprint&&!empty($old['file_id']))return ['ok'=>true,'id'=>$old['file_id'],'path'=>'gdrive:'.$old['file_id'],'duplicate'=>true];$token=cds_drive_token($settings);if(empty($token['ok']))return $token;$boundary='cds-'.bin2hex(random_bytes(12));$meta=json_encode(['name'=>$name,'parents'=>[$folder],'appProperties'=>['cdsType'=>$type,'cdsFingerprint'=>$fingerprint]],JSON_UNESCAPED_UNICODE);$body="--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$boundary\r\nContent-Type: $mime\r\n\r\n$bytes\r\n--$boundary--";$res=cds_drive_http('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink','POST',['Authorization: Bearer '.$token['token'],'Content-Type: multipart/related; boundary='.$boundary],$body);$json=json_decode($res['body'],true);if(!$res['ok']||empty($json['id']))return ['ok'=>false,'message'=>$json['error']['message']??'Không tải được file lên Drive.'];cds_drive_history_add(array_merge(['action'=>'upload','type'=>$type,'name'=>$name,'file_id'=>$json['id'],'folder_id'=>$folder,'fingerprint'=>$fingerprint],$extra));return ['ok'=>true,'id'=>$json['id'],'path'=>'gdrive:'.$json['id'],'webViewLink'=>$json['webViewLink']??''];}
function cds_storage_handle_upload(string $field,string $type): string {$type=cds_drive_type_for_action(cds_drive_page_action(),$type);$upload=$_FILES[$field]??null;if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';$settings=cds_drive_settings();if(!empty($settings['enabled'])&&cds_drive_folder($type,$settings)!==''){$tmp=(string)($upload['tmp_name']??'');$bytes=$tmp!==''?file_get_contents($tmp):false;if(($upload['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||$bytes===false)throw new RuntimeException('Không đọc được file tải lên.');$mime=function_exists('mime_content_type')?(mime_content_type($tmp)?:'application/octet-stream'):'application/octet-stream';$result=cds_drive_upload_bytes($bytes,basename((string)($upload['name']??'file')),$mime,$type);if(empty($result['ok']))throw new RuntimeException($result['message']??'Không lưu được file lên Drive.');return $result['path'];}return function_exists('cm_handle_upload')?cm_handle_upload($field):'';}
function cds_storage_file_url(string $path): string {return str_starts_with($path,'gdrive:')?BASE_URL.'admin.php?drive_file='.rawurlencode(substr($path,7)):(function_exists('cm_file_url')?cm_file_url($path):$path);}
function cds_drive_download(string $id): array {
    if(!preg_match('/^[A-Za-z0-9_-]{10,}$/',$id))return ['ok'=>false,'status'=>404];
    $token=cds_drive_token();if(empty($token['ok']))return ['ok'=>false,'status'=>503];
    $headers=['Authorization: Bearer '.$token['token']];$info=[];
    foreach(cds_drive_history() as $row)if(($row['file_id']??'')===$id){$name=(string)($row['name']??'file');$extension=strtolower((string)pathinfo($name,PATHINFO_EXTENSION));$info=['name'=>$name,'mime'=>$extension==='pdf'?'application/pdf':'application/octet-stream'];break;}
    if(!$info){$meta=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?supportsAllDrives=true&fields=name,mimeType','GET',$headers);if(!$meta['ok'])return ['ok'=>false,'status'=>$meta['status']?:404];$json=json_decode($meta['body'],true);$info=['name'=>$json['name']??'file','mime'=>$json['mimeType']??'application/octet-stream'];}
    $file=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?supportsAllDrives=true&alt=media','GET',$headers);
    return !$file['ok']?['ok'=>false,'status'=>$file['status']?:404]:['ok'=>true,'body'=>$file['body'],'name'=>$info['name'],'mime'=>$info['mime']];
}
function cds_drive_save_generated(string $bytes,string $filename,string $mime,string $type): array {$type=cds_drive_type_for_action(cds_drive_page_action(),$type);return cds_drive_upload_bytes($bytes,$filename,$mime,$type,['action'=>'generated','source_action'=>cds_drive_page_action()]);}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function cds_drive_csrf_token(): string {
    if (empty($_SESSION['cds_drive_csrf'])) $_SESSION['cds_drive_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['cds_drive_csrf'];
}
cds_drive_register_action(cds_drive_page_action());

function load_json($file, $default = []) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : $default;
    }
    return $default;
}

function save_json($file, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;

    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return false;

    // Ghi vào tệp tạm cùng thư mục rồi đổi tên để tránh tệp JSON bị dở dang
    // khi hai người cùng lưu hoặc tiến trình bị ngắt giữa chừng.
    $tmp = tempnam($dir, basename($file) . '.tmp.');
    if ($tmp === false) return false;
    $written = file_put_contents($tmp, $json, LOCK_EX);
    if ($written === false || $written !== strlen($json)) {
        @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    clearstatcache(true, $file);
    return true;
}

function init_users() {
    if (!file_exists(USERS_FILE)) {
        $users = [[
            'id' => 'u1',
            'username' => DEFAULT_ADMIN_USER,
            'password_hash' => password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT),
            'name' => 'Quản trị hệ thống',
            'role' => 'admin',
            'modules' => [
                'chuyenmon' => 'admin', 'csdl' => 'admin', 'noitru' => 'admin',
                'vanban' => 'admin', 'thidua' => 'admin',
            ],
            'perms' => [],
            'classes' => [],
            'active' => true,
            'created_at' => date('c'),
        ]];
        save_json(USERS_FILE, $users);
    }
}

function get_users() { init_users(); return load_json(USERS_FILE, []); }
function save_users(array $users) { return save_json(USERS_FILE, array_values($users)); }
function find_user($username) { foreach (get_users() as $u) if (strcasecmp($u['username'] ?? '', $username) === 0) return $u; return null; }
function find_user_by_id($id) { foreach (get_users() as $u) if (($u['id'] ?? '') === $id) return $u; return null; }
function is_logged_in() { return !empty($_SESSION['cds_user']); }
function current_user() { return $_SESSION['cds_user'] ?? null; }

function session_user_from_record(array $u) {
    $role = (string)($u['role'] ?? 'gv');
    $groups = is_array($u['groups'] ?? null) ? $u['groups'] : [];
    $classes = is_array($u['classes'] ?? null) ? $u['classes'] : [];
    $homeroomClasses = is_array($u['homeroom_classes'] ?? null) ? $u['homeroom_classes'] : [];
    if ((int)($u['permission_model_version'] ?? 1) < 2 && !$groups && $role === 'gvcn') $groups[] = 'gvcn';
    // Giữ riêng lớp chủ nhiệm và lớp được giao thêm. allowed_classes() sẽ hợp
    // nhất hai phạm vi, nhờ đó một người có thể vừa là GVCN vừa nhận nhiệm vụ
    // ở các lớp khác mà quản trị đã chỉ định rõ.
    return [
        'id'=>$u['id']??'', 'username'=>$u['username']??'', 'name'=>$u['name']??($u['username']??''), 'role'=>$role,
        'modules'=>is_array($u['modules']??null)?$u['modules']:[], 'perms'=>is_array($u['perms']??null)?$u['perms']:[],
        'groups'=>$groups, 'permission_overrides'=>is_array($u['permission_overrides']??null)?$u['permission_overrides']:[],
        'permission_model_version'=>(int)($u['permission_model_version']??1), 'classes'=>$classes,
        'homeroom_classes'=>$homeroomClasses, 'teacher_name'=>$u['teacher_name']??'',
    ];
}

function refresh_current_user_session() {
    if (empty($_SESSION['cds_user']) || !is_array($_SESSION['cds_user'])) return;
    $sessionUser=$_SESSION['cds_user'];$record=null;
    foreach(get_users() as $candidate){
        if(!empty($sessionUser['id'])&&($candidate['id']??'')===$sessionUser['id']){$record=$candidate;break;}
        if(empty($sessionUser['id'])&&strcasecmp($candidate['username']??'',$sessionUser['username']??'')===0){$record=$candidate;break;}
    }
    if(!$record||empty($record['active'])){unset($_SESSION['cds_user'],$_SESSION['pccm_admin']);return;}
    $_SESSION['cds_user']=session_user_from_record($record);
    $effective=function_exists('permission_effective_access_for_user')?permission_effective_access_for_user($record):[];
    $hasChuyenMon=($record['role']??'')==='admin';
    foreach(permission_features_catalog() as $code=>$meta){if(($meta['module']??'')!=='chuyenmon')continue;if(level_rank($effective[$code]??'none')>=level_rank('view')){$hasChuyenMon=true;break;}}
    $_SESSION['pccm_admin']=$hasChuyenMon;
}

function attempt_login($username,$password){
    $u=find_user($username);
    if(!$u||empty($u['active'])||!password_verify($password,$u['password_hash']??'')){require_once __DIR__.'/audit.php';cds_audit_log('login_failed','auth',['username'=>$username],['username'=>$username]);return false;}
    $_SESSION['cds_user']=session_user_from_record($u);
    $role=$u['role']??'';$cmLevel=$u['modules']['chuyenmon']??'none';
    $_SESSION['pccm_admin']=($role==='admin')||in_array($role,['bgh','totruong'],true)||in_array($cmLevel,['edit','admin'],true)||in_array('cm.pccm',$u['perms']??[],true);
    require_once __DIR__.'/audit.php';cds_audit_log('login_success','auth');return true;
}
function logout_user(){require_once __DIR__.'/audit.php';if(is_logged_in())cds_audit_log('logout','auth');unset($_SESSION['cds_user'],$_SESSION['pccm_admin']);}
function require_login(){if(!is_logged_in()){header('Location: '.BASE_URL.'login.php?next='.urlencode($_SERVER['REQUEST_URI']??''));exit;}}
function require_admin(){require_login();$u=current_user();if(($u['role']??'')!=='admin'){flash('Chỉ quản trị hệ thống được truy cập.','danger');header('Location: '.BASE_URL.'admin.php');exit;}}
function e($str){return htmlspecialchars($str??'',ENT_QUOTES,'UTF-8');}
function flash($msg,$type='success'){$_SESSION['cds_flash']=['message'=>$msg,'type'=>$type];}
function show_flash(){if(empty($_SESSION['cds_flash']))return;$f=$_SESSION['cds_flash'];unset($_SESSION['cds_flash']);$type=$f['type']??'info';$cls=$type==='danger'?'alert-danger':($type==='warning'?'alert-warning':'alert-success');echo '<div class="alert '.$cls.' alert-dismissible fade show" role="alert">'.e($f['message']??'').'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';}

init_users();
require_once __DIR__ . '/permissions.php';
refresh_current_user_session();
require_once __DIR__ . '/audit.php';
cds_audit_touch();
require_once __DIR__ . '/noitru_meal_history.php';
require_once __DIR__ . '/noitru_duty_filter.php';
require_once __DIR__ . '/dashboard_home_controls.php';
require_once __DIR__ . '/dashboard_school_year_sync.php';
require_once __DIR__ . '/global_ui.php';
require_once __DIR__ . '/student_card_link.php';
