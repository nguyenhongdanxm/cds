<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function load_json($file, $default = []) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : $default;
    }
    return $default;
}

function save_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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
function save_users(array $users) { save_json(USERS_FILE, array_values($users)); }
function find_user($username) { foreach (get_users() as $u) if (strcasecmp($u['username'] ?? '', $username) === 0) return $u; return null; }
function find_user_by_id($id) { foreach (get_users() as $u) if (($u['id'] ?? '') === $id) return $u; return null; }
function is_logged_in() { return !empty($_SESSION['cds_user']); }
function current_user() { return $_SESSION['cds_user'] ?? null; }

function session_user_from_record(array $u) {
    $role = (string)($u['role'] ?? 'gv');
    $groups = is_array($u['groups'] ?? null) ? $u['groups'] : [];
    $classes = is_array($u['classes'] ?? null) ? $u['classes'] : [];
    $homeroomClasses = is_array($u['homeroom_classes'] ?? null) ? $u['homeroom_classes'] : [];
    if ($role === 'gvcn' && !in_array('gvcn', $groups, true)) $groups[] = 'gvcn';
    if ($role === 'gvcn' || in_array('gvcn', $groups, true)) {
        $homeroomScope = array_values(array_unique(array_filter(array_map('strval', $homeroomClasses))));
        if ($homeroomScope) $classes = $homeroomScope;
    }
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
require_once __DIR__ . '/student_card_designer_ui.php';
require_once __DIR__ . '/student_card_scale_loader.php';
require_once __DIR__ . '/student_card_duplex_ui.php';
