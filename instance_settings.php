<?php
/* Chẩn đoán lỗi khởi động chỉ dành cho quản trị viên đã đăng nhập. */
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
$instanceDiagnostic = isset($_GET['diagnostic']) && (string)$_GET['diagnostic'] === '1';
register_shutdown_function(static function () use ($instanceDiagnostic): void {
    if (!$instanceDiagnostic || (string)($_SESSION['cds_user']['role'] ?? '') !== 'admin') return;
    $error = error_get_last();
    if (!is_array($error) || !in_array((int)($error['type'] ?? 0), [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) return;
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $message = htmlspecialchars((string)($error['message'] ?? 'Không rõ lỗi'), ENT_QUOTES, 'UTF-8');
    $file = htmlspecialchars(basename((string)($error['file'] ?? '')), ENT_QUOTES, 'UTF-8');
    $line = (int)($error['line'] ?? 0);
    echo '<!doctype html><meta charset="utf-8"><title>Chẩn đoán CDS</title>';
    echo '<main style="max-width:900px;margin:50px auto;font:16px/1.55 Arial;padding:24px">';
    echo '<h2>Chẩn đoán lỗi Cấu hình trường</h2><p><strong>Lỗi:</strong> '.$message.'</p>';
    echo '<p><strong>Tệp:</strong> '.$file.' — <strong>Dòng:</strong> '.$line.'</p>';
    echo '<p>Hãy chụp nguyên phần thông báo này gửi lại để sửa lỗi.</p></main>';
});

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['instance_settings_csrf'])) $_SESSION['instance_settings_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['instance_settings_csrf'];

$instance = function_exists('cds_instance_config') ? cds_instance_config() : [];
$school = cds_school_config();
$instance = is_array($instance) ? $instance : [];
$instance['deployment'] = is_array($instance['deployment'] ?? null) ? $instance['deployment'] : [];
$school = is_array($school) ? $school : [];
$school['levels'] = is_array($school['levels'] ?? null) ? $school['levels'] : [];
$school['modules'] = is_array($school['modules'] ?? null) ? $school['modules'] : [];
$school['chuyenmon'] = is_array($school['chuyenmon'] ?? null) ? $school['chuyenmon'] : [];
$school['pwa'] = is_array($school['pwa'] ?? null) ? $school['pwa'] : [];
$settingsWarnings = [];
$db = ['host'=>'localhost','port'=>'3306','database'=>'','username'=>'','password'=>'','charset'=>'utf8mb4'];
try {
    require_once __DIR__ . '/includes/database.php';
    $db = cds_db_read_config();
} catch (Throwable $e) {
    $settingsWarnings[] = 'Chưa đọc được cấu hình database cũ. Thầy vẫn có thể nhập lại thông tin database bên dưới.';
    error_log('CDS instance settings database bootstrap: ' . $e->getMessage());
}

$drive = ['enabled'=>false,'client_email'=>'','private_key'=>'','folders'=>[],'types'=>[],'bindings'=>[]];
try {
    require_once __DIR__ . '/includes/google_drive_storage.php';
    $drive = cds_drive_settings();
} catch (Throwable $e) {
    $settingsWarnings[] = 'Chưa đọc được cấu hình Google Drive cũ. Có thể để nguyên phần Drive và cấu hình lại sau.';
    error_log('CDS instance settings Drive bootstrap: ' . $e->getMessage());
}
$drive = is_array($drive) ? $drive : [];
$drive['folders'] = is_array($drive['folders'] ?? null) ? $drive['folders'] : [];
$drive['types'] = is_array($drive['types'] ?? null) ? $drive['types'] : [];
$drive['bindings'] = is_array($drive['bindings'] ?? null) ? $drive['bindings'] : [];

function instance_bool_post(string $key): bool { return isset($_POST[$key]) && (string)$_POST[$key] === '1'; }
function instance_clean(string $key, string $default=''): string { return trim((string)($_POST[$key] ?? $default)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        flash('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.', 'danger');
        header('Location: instance_settings.php'); exit;
    }

    $name = instance_clean('school_name');
    $short = instance_clean('school_short');
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]+/', '', instance_clean('school_code')));
    if ($name === '' || $short === '' || $code === '') {
        flash('Tên trường, tên rút gọn và mã trường là bắt buộc.', 'danger');
        header('Location: instance_settings.php'); exit;
    }

    $dbNext = [
        'host' => instance_clean('db_host', 'localhost'),
        'port' => instance_clean('db_port', '3306'),
        'database' => instance_clean('db_name'),
        'username' => instance_clean('db_user'),
        'password' => instance_clean('db_password') !== '' ? (string)$_POST['db_password'] : (string)($db['password'] ?? ''),
        'charset' => instance_clean('db_charset', 'utf8mb4'),
    ];
    if ($dbNext['database'] === '' || $dbNext['username'] === '' || $dbNext['password'] === '' || !ctype_digit($dbNext['port'])) {
        flash('Thông tin cơ sở dữ liệu chưa đầy đủ hoặc cổng MySQL không hợp lệ.', 'danger');
        header('Location: instance_settings.php'); exit;
    }

    /* Kiểm tra DB trước khi lưu để tránh làm hỏng một hệ thống đang vận hành. */
    try {
        if (!extension_loaded('pdo_mysql')) throw new RuntimeException('PHP chưa bật pdo_mysql.');
        $dsn = 'mysql:host='.$dbNext['host'].';port='.(int)$dbNext['port'].';dbname='.$dbNext['database'].';charset='.$dbNext['charset'];
        $testPdo = new PDO($dsn, $dbNext['username'], $dbNext['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $testPdo->query('SELECT 1');
        $testPdo = null;
    } catch (Throwable $e) {
        flash('Không lưu vì kết nối cơ sở dữ liệu chưa thành công: '.$e->getMessage(), 'danger');
        header('Location: instance_settings.php'); exit;
    }

    $modules = [];
    foreach (['tintuc','chuyenmon','vanban','thuvien','csdl','hoclieu','noitru','thidua','yte'] as $module) {
        $modules[$module] = instance_bool_post('module_'.$module);
    }

    $driveNext = $drive;
    $driveNext['enabled'] = instance_bool_post('drive_enabled');
    $serviceJson = trim((string)($_POST['service_account_json'] ?? ''));
    if ($serviceJson !== '') {
        $account = json_decode($serviceJson, true);
        if (!is_array($account) || empty($account['client_email']) || empty($account['private_key'])) {
            flash('JSON tài khoản dịch vụ Google không hợp lệ.', 'danger');
            header('Location: instance_settings.php'); exit;
        }
        $driveNext['client_email'] = (string)$account['client_email'];
        $driveNext['private_key'] = (string)$account['private_key'];
        $driveNext['provider'] = 'service';
    }
    if (!isset($driveNext['folders']) || !is_array($driveNext['folders'])) $driveNext['folders'] = [];
    foreach (['documents','plans','education_plans','photos'] as $type) {
        $folder = instance_clean('drive_folder_'.$type, (string)($driveNext['folders'][$type] ?? ''));
        $driveNext['folders'][$type] = $folder;
        if (isset($driveNext['types'][$type]) && is_array($driveNext['types'][$type])) $driveNext['types'][$type]['folder_id'] = $folder;
    }

    $deployTarget = instance_clean('deploy_target', BASE_PATH);
    if ($deployTarget === '' || $deployTarget[0] !== '/' || preg_match('#(?:^|/)\.\.(?:/|$)#', $deployTarget)) {
        flash('Đường dẫn triển khai phải là đường dẫn tuyệt đối hợp lệ.', 'danger');
        header('Location: instance_settings.php'); exit;
    }

    $next = is_array($instance) ? $instance : [];
    $next['version'] = 1;
    $next['updated_at'] = date('c');
    $next['school'] = [
        'code'=>$code,
        'name'=>$name,
        'short_name'=>$short,
        'department'=>instance_clean('department'),
        'school_year'=>instance_clean('school_year'),
        'website'=>instance_clean('website'),
        'cds_title'=>instance_clean('cds_title', 'CDS - '.$name),
        'cds_short_title'=>instance_clean('cds_short_title', 'CDS '.$short),
        'description'=>instance_clean('description', 'Hệ sinh thái quản lý nhà trường'),
        'address'=>instance_clean('address'),
        'phone'=>instance_clean('phone'),
        'email'=>instance_clean('email'),
        'logo'=>instance_clean('logo', 'assets/logo.png'),
        'levels'=>['thcs'=>instance_bool_post('level_thcs'),'thpt'=>instance_bool_post('level_thpt')],
        'modules'=>$modules,
        'chuyenmon'=>[
            'seed_profile'=>instance_clean('seed_profile', 'empty'),
            'defaults_file'=>instance_clean('defaults_file'),
        ],
        'pwa'=>[
            'theme_color'=>instance_clean('theme_color', '#0f4c81'),
            'background_color'=>instance_clean('background_color', '#f4f7fb'),
            'icon_192'=>instance_clean('icon_192', 'assets/icons/cds-192.png'),
            'icon_512'=>instance_clean('icon_512', 'assets/icons/cds-512.png'),
        ],
    ];
    $next['database'] = $dbNext;
    $next['drive'] = $driveNext;
    $next['deployment'] = ['target_path'=>$deployTarget];
    if (isset($next['paths']['drive_settings_file'])) unset($next['paths']['drive_settings_file']);
    if (isset($next['paths']) && !$next['paths']) unset($next['paths']);
    if (!cds_instance_save($next)) {
        flash('Không ghi được bộ cấu hình trường hợp nhất.', 'danger');
        header('Location: instance_settings.php'); exit;
    }

    flash('Đã lưu bộ cấu hình trường. Hệ thống sẽ áp dụng đầy đủ từ lần tải trang tiếp theo.', 'success');
    header('Location: instance_settings.php?saved=1'); exit;
}

$moduleLabels = ['tintuc'=>'Tin tức','chuyenmon'=>'Chuyên môn','vanban'=>'Văn bản','thuvien'=>'Thư viện – Thiết bị','csdl'=>'Cơ sở dữ liệu','hoclieu'=>'Học liệu & thi','noitru'=>'Quản lý nội trú','thidua'=>'Thi đua','yte'=>'Y tế'];
$deployTarget = (string)($instance['deployment']['target_path'] ?? BASE_PATH);
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cấu hình trường – CDS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>body{background:#f4f7fb}.card{border:0;box-shadow:0 .25rem 1rem rgba(0,0,0,.06)}.section-title{font-weight:800;color:#1f4e79}.form-text{font-size:.82rem}</style></head><body>
<main class="container py-4" style="max-width:1100px">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h2 class="mb-1"><i class="bi bi-sliders"></i> Cấu hình trường</h2><div class="text-muted">Một nơi duy nhất để cấu hình bản CDS khi triển khai sang trường khác.</div></div><a href="admin.php" class="btn btn-outline-secondary">Quay lại quản trị</a></div>
<?php show_flash(); ?>
<?php foreach ($settingsWarnings as $warning): ?><div class="alert alert-warning"><?=e($warning)?></div><?php endforeach; ?>
<div class="alert alert-success"><strong>Không cần sửa code.</strong> Cấu hình được lưu ngoài web root tại <code><?=e(cds_instance_config_path())?></code>. Nếu chưa tạo bộ cấu hình này, bản Xín Mần tiếp tục dùng cơ chế cũ như hiện tại.</div>
<form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>">
<div class="card mb-4"><div class="card-body p-4"><h5 class="section-title mb-3">1. Thông tin nhà trường</h5><div class="row g-3">
<div class="col-md-3"><label class="form-label">Mã trường *</label><input class="form-control" name="school_code" required value="<?=e((string)($school['code']??''))?>"></div>
<div class="col-md-6"><label class="form-label">Tên trường *</label><input class="form-control" name="school_name" required value="<?=e((string)($school['name']??''))?>"></div>
<div class="col-md-3"><label class="form-label">Tên rút gọn *</label><input class="form-control" name="school_short" required value="<?=e((string)($school['short_name']??''))?>"></div>
<div class="col-md-6"><label class="form-label">Cơ quan chủ quản</label><input class="form-control" name="department" value="<?=e((string)($school['department']??''))?>"></div>
<div class="col-md-3"><label class="form-label">Năm học</label><input class="form-control" name="school_year" value="<?=e((string)($school['school_year']??''))?>"></div>
<div class="col-md-3"><label class="form-label">Website</label><input class="form-control" name="website" value="<?=e((string)($school['website']??''))?>"></div>
<div class="col-md-6"><label class="form-label">Địa chỉ</label><input class="form-control" name="address" value="<?=e((string)($school['address']??''))?>"></div>
<div class="col-md-3"><label class="form-label">Điện thoại</label><input class="form-control" name="phone" value="<?=e((string)($school['phone']??''))?>"></div>
<div class="col-md-3"><label class="form-label">Email</label><input class="form-control" name="email" value="<?=e((string)($school['email']??''))?>"></div>
<div class="col-md-6"><label class="form-label">Tiêu đề CDS</label><input class="form-control" name="cds_title" value="<?=e((string)($school['cds_title']??('CDS - '.($school['name']??''))))?>"></div>
<div class="col-md-3"><label class="form-label">Tên ngắn CDS</label><input class="form-control" name="cds_short_title" value="<?=e((string)($school['cds_short_title']??''))?>"></div>
<div class="col-md-3"><label class="form-label">Logo</label><input class="form-control" name="logo" value="<?=e((string)($school['logo']??'assets/logo.png'))?>"></div>
<div class="col-12"><label class="form-label">Mô tả</label><input class="form-control" name="description" value="<?=e((string)($school['description']??''))?>"></div>
</div></div></div>

<div class="card mb-4"><div class="card-body p-4"><h5 class="section-title mb-3">2. Cấp học và module sử dụng</h5><div class="d-flex gap-4 mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="level_thcs" value="1" id="thcs" <?=!empty($school['levels']['thcs'])?'checked':''?>><label class="form-check-label" for="thcs">THCS</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="level_thpt" value="1" id="thpt" <?=!empty($school['levels']['thpt'])?'checked':''?>><label class="form-check-label" for="thpt">THPT</label></div></div><div class="row g-2">
<?php foreach($moduleLabels as $key=>$label): ?><div class="col-md-4"><div class="form-check border rounded p-2 ps-5 bg-light"><input class="form-check-input" type="checkbox" name="module_<?=e($key)?>" value="1" id="mod_<?=e($key)?>" <?=!array_key_exists($key,(array)($school['modules']??[]))||!empty($school['modules'][$key])?'checked':''?>><label class="form-check-label" for="mod_<?=e($key)?>"><?=e($label)?></label></div></div><?php endforeach; ?>
</div><hr><div class="row g-3"><div class="col-md-4"><label class="form-label">Khởi tạo Chuyên môn</label><select class="form-select" name="seed_profile"><option value="empty" <?=((string)($school['chuyenmon']['seed_profile']??'')==='empty')?'selected':''?>>Trường mới – dữ liệu rỗng</option><option value="xinman" <?=((string)($school['chuyenmon']['seed_profile']??'')==='xinman')?'selected':''?>>Fallback Xín Mần</option></select></div><div class="col-md-8"><label class="form-label">File dữ liệu khởi tạo riêng (nếu có)</label><input class="form-control" name="defaults_file" value="<?=e((string)($school['chuyenmon']['defaults_file']??''))?>"><div class="form-text">Để trống nếu nhập dữ liệu trực tiếp sau khi cài đặt.</div></div></div></div></div>

<div class="card mb-4"><div class="card-body p-4"><h5 class="section-title mb-3">3. Cơ sở dữ liệu MySQL</h5><div class="alert alert-warning py-2">Hệ thống sẽ <strong>kiểm tra kết nối trước khi lưu</strong>. Nếu thông tin sai, cấu hình hiện tại không bị thay đổi.</div><div class="row g-3"><div class="col-md-4"><label class="form-label">Host</label><input class="form-control" name="db_host" value="<?=e((string)($db['host']??'localhost'))?>"></div><div class="col-md-2"><label class="form-label">Port</label><input class="form-control" name="db_port" value="<?=e((string)($db['port']??'3306'))?>"></div><div class="col-md-3"><label class="form-label">Database</label><input class="form-control" name="db_name" value="<?=e((string)($db['database']??''))?>"></div><div class="col-md-3"><label class="form-label">Username</label><input class="form-control" name="db_user" value="<?=e((string)($db['username']??''))?>"></div><div class="col-md-6"><label class="form-label">Password</label><input type="password" class="form-control" name="db_password" placeholder="Để trống để giữ mật khẩu hiện tại"><div class="form-text">Mật khẩu không hiển thị lại trên giao diện.</div></div><div class="col-md-3"><label class="form-label">Charset</label><input class="form-control" name="db_charset" value="<?=e((string)($db['charset']??'utf8mb4'))?>"></div></div></div></div>

<div class="card mb-4"><div class="card-body p-4"><h5 class="section-title mb-3">4. Google Drive (tùy chọn)</h5><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="drive_enabled" value="1" id="drive_enabled" <?=!empty($drive['enabled'])?'checked':''?>><label class="form-check-label fw-semibold" for="drive_enabled">Bật Google Drive</label></div><div class="row g-3"><div class="col-12"><label class="form-label">JSON Service Account</label><textarea class="form-control font-monospace" rows="3" name="service_account_json" placeholder="Để trống để giữ tài khoản Google hiện tại"></textarea><div class="form-text">Tài khoản Drive và các ID thư mục được lưu chung trong bộ cấu hình trường, ngoài web root.</div></div><?php foreach(['documents'=>'Văn bản','plans'=>'Kế hoạch/báo cáo','education_plans'=>'Kế hoạch giáo dục','photos'=>'Ảnh học sinh'] as $key=>$label): ?><div class="col-md-6"><label class="form-label"><?=e($label)?></label><input class="form-control" name="drive_folder_<?=e($key)?>" value="<?=e((string)($drive['folders'][$key]??$drive['types'][$key]['folder_id']??''))?>" placeholder="ID thư mục Google Drive"></div><?php endforeach; ?></div><div class="form-text mt-2">Các cấu hình Drive nâng cao vẫn có thể quản lý tại trang Kho Google Drive và sẽ được ghi về cùng file instance.json.</div></div></div>

<div class="card mb-4"><div class="card-body p-4"><h5 class="section-title mb-3">5. Triển khai và PWA</h5><div class="row g-3"><div class="col-12"><label class="form-label">Thư mục website trên hosting</label><input class="form-control font-monospace" name="deploy_target" value="<?=e($deployTarget)?>"><div class="form-text">Ví dụ: /home/tenuser/cds.truongabc.edu.vn</div></div><div class="col-md-4"><label class="form-label">Màu giao diện</label><input class="form-control" name="theme_color" value="<?=e((string)($school['pwa']['theme_color']??'#0f4c81'))?>"></div><div class="col-md-4"><label class="form-label">Màu nền PWA</label><input class="form-control" name="background_color" value="<?=e((string)($school['pwa']['background_color']??'#f4f7fb'))?>"></div><div class="col-md-4"><label class="form-label">Icon 192</label><input class="form-control" name="icon_192" value="<?=e((string)($school['pwa']['icon_192']??'assets/icons/cds-192.png'))?>"></div><div class="col-md-4"><label class="form-label">Icon 512</label><input class="form-control" name="icon_512" value="<?=e((string)($school['pwa']['icon_512']??'assets/icons/cds-512.png'))?>"></div></div></div></div>

<div class="d-flex justify-content-end gap-2 mb-5"><a class="btn btn-outline-secondary" href="admin.php">Hủy</a><button class="btn btn-primary btn-lg px-4"><i class="bi bi-check2-circle"></i> Lưu bộ cấu hình trường</button></div>
</form></main></body></html>
