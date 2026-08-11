<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/modules.php';
require_once __DIR__ . '/includes/dashboard_data.php';
require_login();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
if(isset($_GET['drive_file'])){$driveId=(string)$_GET['drive_file'];$etag='"'.sha1($driveId).'"';header('Cache-Control: private, max-age=300');header('ETag: '.$etag);if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}$file=cds_drive_download($driveId);if(empty($file['ok'])){http_response_code((int)($file['status']??404));exit;}header('Content-Type: '.$file['mime']);header('Content-Length: '.strlen($file['body']));header("Content-Disposition: inline; filename*=UTF-8''".rawurlencode($file['name']));echo $file['body'];exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['drive_api'])){
    header('Content-Type: application/json; charset=utf-8');
    if(!hash_equals(cds_drive_csrf_token(),(string)($_POST['csrf']??''))){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
    $type=preg_replace('/[^a-z0-9_]/','',(string)($_POST['type']??''));$name=basename((string)($_POST['name']??'tai-lieu'));
    $upload=$_FILES['file']??null;$bytes=$upload&&($upload['error']??1)===UPLOAD_ERR_OK?@file_get_contents((string)$upload['tmp_name']):false;
    if($bytes===false||strlen($bytes)>25*1024*1024){$result=['ok'=>false,'message'=>'File không hợp lệ hoặc vượt quá 25 MB.'];}
    else{$mime=function_exists('mime_content_type')?(mime_content_type((string)$upload['tmp_name'])?:($upload['type']??'application/octet-stream')):($upload['type']??'application/octet-stream');$source=(string)($_POST['source_action']??'');$type=cds_drive_type_for_action($source!==''?$source:cds_drive_page_action(),$type);$result=cds_drive_upload_bytes($bytes,$name,$mime,$type,['action'=>'generated','source_action'=>$source]);}
    echo json_encode($result,JSON_UNESCAPED_UNICODE);exit;
}
if(($_GET['view']??'')==='drive'){
    if(!$isAdmin){http_response_code(403);exit;}$drive=cds_drive_settings();$oauth=(array)($drive['oauth']??[]);
    if(($_GET['oauth']??'')==='callback'){$valid=!empty($_GET['state'])&&hash_equals((string)($_SESSION['cds_drive_oauth_state']??''),(string)$_GET['state']);if(!$valid)flash('Phiên kết nối Google không hợp lệ.','danger');elseif(!empty($_GET['error']))flash('Google từ chối kết nối: '.(string)$_GET['error'],'danger');else{$result=cds_drive_oauth_exchange($drive,(string)($_GET['code']??''));flash(!empty($result['ok'])?'Đã kết nối tài khoản Google Drive thành công.':($result['message']??'Không kết nối được Google.'),!empty($result['ok'])?'success':'danger');}unset($_SESSION['cds_drive_oauth_state']);header('Location: '.BASE_URL.'admin.php?view=drive');exit;}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $action=(string)($_POST['drive_action']??'save');
        if($action==='save_oauth'){$drive['enabled']=!empty($_POST['enabled']);$drive['oauth']['client_id']=trim((string)($_POST['client_id']??''));if(trim((string)($_POST['client_secret']??''))!=='')$drive['oauth']['client_secret']=trim((string)$_POST['client_secret']);cds_drive_save_settings($drive);flash('Đã lưu cấu hình OAuth.','success');}
        elseif($action==='connect'){$clientId=trim((string)($_POST['client_id']??($oauth['client_id']??'')));$secret=trim((string)($_POST['client_secret']??''));if($clientId!=='')$drive['oauth']['client_id']=$clientId;if($secret!=='')$drive['oauth']['client_secret']=$secret;cds_drive_save_settings($drive);if(empty($drive['oauth']['client_id'])||empty($drive['oauth']['client_secret']))flash('Hãy nhập Client ID và Client Secret trước.','danger');else{header('Location: '.cds_drive_oauth_url($drive));exit;}}
        elseif($action==='disconnect'){$drive['oauth']['refresh_token']='';$drive['enabled']=false;cds_drive_save_settings($drive);flash('Đã ngắt kết nối Google Drive.','warning');}
        elseif($action==='save_types'){$types=[];foreach((array)($_POST['type_key']??[]) as $i=>$rawKey){$key=preg_replace('/[^a-z0-9_]/','_',mb_strtolower(trim((string)$rawKey),'UTF-8'));$label=trim((string)($_POST['type_label'][$i]??''));if($key===''||$label==='')continue;$types[$key]=['label'=>$label,'folder_id'=>trim((string)($_POST['type_folder'][$i]??'')),'prefix'=>trim((string)($_POST['type_prefix'][$i]??''))];}$drive['types']=$types;$drive['enabled']=!empty($_POST['enabled']);cds_drive_save_settings($drive);flash('Đã lưu các loại dữ liệu và thư mục.','success');}
        elseif($action==='save_bindings'){$bindings=[];foreach((array)($_POST['binding_action']??[]) as $i=>$key){$key=trim((string)$key);$type=trim((string)($_POST['binding_type'][$i]??''));if(str_starts_with($key,'page:')&&isset($drive['types'][$type]))$bindings[$key]=$type;}$drive['bindings']=$bindings;cds_drive_save_settings($drive);flash('Đã gán chức năng CDS với loại lưu trữ.','success');}
        elseif($action==='test_folder'){$key=(string)($_POST['type']??'');$result=cds_drive_test_folder($key,$drive);flash(!empty($result['ok'])?'Kết nối và quyền ghi thư mục “'.($result['name']??$key).'” hợp lệ.':($result['message']??'Không kiểm tra được thư mục.'),!empty($result['ok'])?'success':'danger');}
        elseif($action==='test_upload'){$key=(string)($_POST['type']??'documents');$result=cds_drive_save_generated("CDS kiểm tra lúc ".date('d/m/Y H:i'), 'CDS-kiem-tra-'.date('Ymd-His').'.txt','text/plain',$key);flash(!empty($result['ok'])?'Đã tạo file kiểm tra trong Google Drive.':($result['message']??'Không tạo được file kiểm tra.'),!empty($result['ok'])?'success':'danger');}
        elseif($action==='manual_upload'){$key=(string)($_POST['type']??'documents');$upload=$_FILES['drive_file']??null;$bytes=$upload&&($upload['error']??1)===UPLOAD_ERR_OK?@file_get_contents((string)$upload['tmp_name']):false;if($bytes===false)$result=['ok'=>false,'message'=>'Không đọc được file đã chọn.'];else{$mime=function_exists('mime_content_type')?(mime_content_type((string)$upload['tmp_name'])?:'application/octet-stream'):'application/octet-stream';$result=cds_drive_upload_bytes($bytes,basename((string)$upload['name']),$mime,$key,['action'=>'manual']);}flash(!empty($result['ok'])?'Đã lưu file lên Google Drive.':($result['message']??'Không lưu được file.'),!empty($result['ok'])?'success':'danger');}
        elseif($action==='migrate'){$count=0;$skipped=0;$errors=[];$candidates=[];foreach([DATA_PATH.'/student_photos'=> 'photos',DATA_PATH.'/uploads'=>'documents',BASE_PATH.'/chuyenmon/uploads'=>'plans'] as $dir=>$type)if(is_dir($dir))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $item)if($item->isFile())$candidates[]=[(string)$item->getPathname(),$type];foreach($candidates as [$path,$type]){if($count>=20)break;$bytes=@file_get_contents($path);if($bytes===false)continue;$mime=function_exists('mime_content_type')?(mime_content_type($path)?:'application/octet-stream'):'application/octet-stream';$result=cds_drive_upload_bytes($bytes,basename($path),$mime,$type,['action'=>'migrate','source'=>$path]);if(!empty($result['duplicate'])){$skipped++;continue;}if(!empty($result['ok']))$count++;else $errors[]=$result['message']??basename($path);}flash('Đã chuyển '.$count.' file cũ lên Drive; bỏ qua '.$skipped.' file trùng'.($errors?'; lỗi: '.implode(' | ',array_slice($errors,0,3)):'.'),$errors?'warning':'success');}
        header('Location: '.BASE_URL.'admin.php?view=drive');exit;
    }
    $drive=cds_drive_settings();$oauth=(array)($drive['oauth']??[]);$connected=!empty($oauth['refresh_token']);$history=array_slice(cds_drive_history(),0,30);$actionCatalog=cds_drive_action_catalog();
    ?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kho Google Drive – CDS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>.drive-type-row{display:grid;grid-template-columns:1.1fr 1.4fr 2fr 1.2fr auto;gap:.5rem;align-items:center}.status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;background:#dc3545}.status-dot.on{background:#16a34a}@media(max-width:800px){.drive-type-row{grid-template-columns:1fr}.drive-type-head{display:none}}</style></head><body class="bg-light"><main class="container py-4" style="max-width:1100px"><div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1"><i class="bi bi-google"></i> Kho Google Drive</h2><p class="text-muted mb-0"><span class="status-dot <?=$connected?'on':''?>"></span> <?=$connected?'Đã kết nối tài khoản Google':'Chưa kết nối OAuth'?></p></div><a class="btn btn-outline-secondary" href="admin.php">Quay lại</a></div><?php show_flash();?>
    <section class="card shadow-sm border-0 mb-3"><div class="card-body p-4"><h5>1. Kết nối “Drive của tôi” bằng OAuth</h5><p class="text-muted small">Redirect URI cần khai báo trong Google Cloud: <code><?=e(cds_drive_redirect_uri())?></code></p><form method="post" class="row g-2"><input type="hidden" name="drive_action" value="save_oauth"><div class="col-md-5"><label class="form-label">OAuth Client ID</label><input class="form-control" name="client_id" value="<?=e($oauth['client_id']??'')?>"></div><div class="col-md-5"><label class="form-label">OAuth Client Secret</label><input class="form-control" type="password" name="client_secret" placeholder="Để trống nếu không đổi"></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Lưu OAuth</button></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="driveEnabled" <?=!empty($drive['enabled'])?'checked':''?>><label for="driveEnabled">Bật lưu file lên Drive</label></div></form><div class="d-flex gap-2 mt-3"><form method="post"><input type="hidden" name="drive_action" value="connect"><input type="hidden" name="client_id" value="<?=e($oauth['client_id']??'')?>"><button class="btn btn-primary"><i class="bi bi-google"></i> Kết nối tài khoản Google</button></form><?php if($connected):?><form method="post" onsubmit="return confirm('Ngắt kết nối Google Drive?')"><input type="hidden" name="drive_action" value="disconnect"><button class="btn btn-outline-danger">Ngắt kết nối</button></form><?php endif;?></div></div></section>
    <section class="card shadow-sm border-0 mb-3"><div class="card-body p-4"><h5>2. Loại dữ liệu và thư mục lưu</h5><p class="text-muted small">Có thể tự thêm loại mới. ID là phần sau <code>/folders/</code> trong URL Google Drive.</p><form method="post" id="typesForm"><input type="hidden" name="drive_action" value="save_types"><input type="hidden" name="enabled" value="<?=!empty($drive['enabled'])?'1':''?>"><div class="drive-type-row drive-type-head fw-bold small mb-2"><span>Mã loại</span><span>Tên hiển thị</span><span>ID thư mục</span><span>Tiền tố file</span><span></span></div><div id="driveTypes"><?php foreach($drive['types'] as $key=>$type):?><div class="drive-type-row mb-2"><input class="form-control" name="type_key[]" value="<?=e($key)?>" readonly><input class="form-control" name="type_label[]" value="<?=e($type['label']??$key)?>"><input class="form-control" name="type_folder[]" value="<?=e($type['folder_id']??'')?>" placeholder="ID thư mục"><input class="form-control" name="type_prefix[]" value="<?=e($type['prefix']??'')?>"><button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button></div><?php endforeach;?></div><div class="d-flex gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" onclick="addDriveType()"><i class="bi bi-plus"></i> Thêm loại</button><button class="btn btn-success"><i class="bi bi-floppy"></i> Lưu danh mục</button></div></form><hr><div class="row g-2"><form method="post" class="col-md-5 d-flex gap-2"><input type="hidden" name="drive_action" value="test_folder"><select class="form-select" name="type"><?php foreach($drive['types'] as $key=>$type):?><option value="<?=e($key)?>"><?=e($type['label']??$key)?></option><?php endforeach;?></select><button class="btn btn-outline-primary text-nowrap">Kiểm tra thư mục</button></form><form method="post" class="col-md-5 d-flex gap-2"><input type="hidden" name="drive_action" value="test_upload"><select class="form-select" name="type"><?php foreach($drive['types'] as $key=>$type):?><option value="<?=e($key)?>"><?=e($type['label']??$key)?></option><?php endforeach;?></select><button class="btn btn-primary text-nowrap">Tạo file thử</button></form></div></div></section>
    <section class="card shadow-sm border-0 mb-3"><div class="card-body p-4"><h5>3. Gán chức năng CDS → Loại lưu trữ</h5><p class="text-muted small">Trang và menu mới tự xuất hiện sau lần truy cập đầu tiên. Hệ thống nhận diện riêng các mục theo <code>tab</code>, <code>view</code> và <code>mode</code>.</p><form method="post"><input type="hidden" name="drive_action" value="save_bindings"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Trang / chức năng nhận diện</th><th>Đường dẫn</th><th style="min-width:240px">Loại lưu trữ</th></tr></thead><tbody><?php foreach($actionCatalog as $actionKey=>$actionInfo):$selected=$drive['bindings'][$actionKey]??($actionInfo['default_type']??'');?><tr><td><strong><?=e($actionInfo['label']??$actionKey)?></strong><?php if(!empty($actionInfo['last_seen'])):?><div class="small text-muted">Đã nhận diện <?=e(date('d/m/Y H:i',strtotime($actionInfo['last_seen'])))?></div><?php endif;?></td><td><code><?=e(substr($actionKey,5))?></code><input type="hidden" name="binding_action[]" value="<?=e($actionKey)?>"></td><td><select class="form-select" name="binding_type[]"><option value="">Không tự động gán</option><?php foreach($drive['types'] as $typeKey=>$typeInfo):?><option value="<?=e($typeKey)?>" <?=$selected===$typeKey?'selected':''?>><?=e($typeInfo['label']??$typeKey)?></option><?php endforeach;?></select></td></tr><?php endforeach;?></tbody></table></div><button class="btn btn-success"><i class="bi bi-diagram-3"></i> Lưu gán chức năng</button></form></div></section>
    <section class="card shadow-sm border-0 mb-3"><div class="card-body p-4"><h5>4. Tải file lên theo loại</h5><form method="post" enctype="multipart/form-data" class="row g-2 align-items-end"><input type="hidden" name="drive_action" value="manual_upload"><div class="col-md-4"><label class="form-label">Loại dữ liệu</label><select class="form-select" name="type"><?php foreach($drive['types'] as $key=>$type):?><option value="<?=e($key)?>"><?=e($type['label']??$key)?></option><?php endforeach;?></select></div><div class="col-md-6"><label class="form-label">Chọn file</label><input class="form-control" type="file" name="drive_file" required></div><div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button></div></form></div></section>
    <section class="card shadow-sm border-0 mb-3"><div class="card-body p-4"><h5>5. Chuyển dữ liệu cũ</h5><p class="text-muted">Mỗi lượt chuyển tối đa 20 file; bản gốc trên host được giữ nguyên. File trùng được bỏ qua.</p><form method="post" onsubmit="return confirm('Chuyển tối đa 20 file cũ lên Drive?')"><input type="hidden" name="drive_action" value="migrate"><button class="btn btn-warning"><i class="bi bi-cloud-arrow-up"></i> Chuyển 20 file tiếp theo</button></form></div></section>
    <section class="card shadow-sm border-0"><div class="card-body p-4"><h5>6. Lịch sử lưu Drive</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Thời gian</th><th>Loại</th><th>File</th><th>Người lưu</th><th>Trạng thái</th></tr></thead><tbody><?php foreach($history as $row):?><tr><td><?=e(isset($row['at'])?date('d/m/Y H:i',strtotime($row['at'])):'')?></td><td><?=e($drive['types'][$row['type']??'']['label']??($row['type']??''))?></td><td><?=e($row['name']??'')?></td><td><?=e($row['by']??'')?></td><td><?=!empty($row['file_id'])?'<span class="badge bg-success">Đã lưu</span>':'—'?></td></tr><?php endforeach;?><?php if(!$history):?><tr><td colspan="5" class="text-muted text-center">Chưa có lịch sử.</td></tr><?php endif;?></tbody></table></div></div></section></main><script>function addDriveType(){var key=prompt('Nhập mã loại, ví dụ: ho_so_y_te');if(!key)return;var label=prompt('Tên hiển thị','Loại dữ liệu mới')||'Loại dữ liệu mới';var row=document.createElement('div');row.className='drive-type-row mb-2';row.innerHTML='<input class="form-control" name="type_key[]" readonly><input class="form-control" name="type_label[]"><input class="form-control" name="type_folder[]" placeholder="ID thư mục"><input class="form-control" name="type_prefix[]"><button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>';row.children[0].value=key;row.children[1].value=label;row.children[3].value=key;document.getElementById('driveTypes').appendChild(row)}</script></body></html><?php exit;
}
if (empty($_SESSION['dashboard_csrf'])) $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(20));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mute_birthday') {
    $token = (string)($_POST['csrf'] ?? '');
    if (hash_equals((string)$_SESSION['dashboard_csrf'], $token)) cds_dashboard_mute_birthday($user, trim((string)($_POST['teacher_id'] ?? '')));
    header('Location: ' . BASE_URL . 'admin.php'); exit;
}

$modules = [];
foreach (get_ecosystem_modules() as $module) {
    $id = $module['id'] ?? '';
    if (($module['status'] ?? '') === 'soon') continue;
    if (($module['status'] ?? '') === 'link' || $isAdmin || can_module($id, 'view')) $modules[] = $module;
}
$teachers = array_values(array_filter(csdl_teachers_all(), fn($row)=>!isset($row['active']) || !empty($row['active'])));
$preferences = cds_dashboard_preferences($user);
$birthday = cds_dashboard_birthday($teachers, $preferences['muted_birthdays'] ?? []);
$dashboard = cds_dashboard_scope_data($user);
$quickActions = cds_dashboard_quick_actions($user);
$feedItems = cds_dashboard_notice_tasks($user);
$observations = can_module('chuyenmon','view') ? cds_dashboard_observations() : [];
$lunar = cds_dashboard_solar_to_lunar((int)date('d'), (int)date('m'), (int)date('Y'));
$weekdays = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
$shiftLabels = ['morning'=>'Buổi sáng','noon'=>'Giờ ngủ trưa','afternoon'=>'Buổi chiều','evening'=>'Buổi tối','night'=>'Ban đêm','sang'=>'Buổi sáng','trua'=>'Buổi trưa','toi'=>'Buổi tối'];
$hour = (int)date('G');
$greeting = $hour < 11 ? 'Chào buổi sáng' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');
$scope = allowed_classes();
$scopeText = $scope === null ? 'Toàn trường' : ($scope ? implode(', ', $scope) : 'Chưa được gán lớp');
$avatarName = (string)($user['name'] ?? 'U');
$avatar = function_exists('mb_substr') ? mb_substr($avatarName, 0, 1, 'UTF-8') : substr($avatarName, 0, 1);
$avatarUpper = function_exists('mb_strtoupper') ? mb_strtoupper($avatar, 'UTF-8') : strtoupper($avatar);
$duty = $dashboard['noitru']['duty'] ?? null;
$dutyHours = $duty ? intdiv((int)$duty['remaining'], 3600) : 0;
$dutyMinutes = $duty ? intdiv((int)$duty['remaining'] % 3600, 60) : 0;
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0f4c81">
  <title>Trang chủ quản trị – <?= e(SCHOOL_SHORT) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(BASE_URL) ?>assets/admin-dashboard.css?v=20260807-4" rel="stylesheet">
</head>
<body>
<header class="app-header">
  <a class="school-brand" href="<?= e(BASE_URL) ?>">
    <span class="school-mark"><i class="bi bi-mortarboard-fill"></i></span>
    <span><strong><?= e(SCHOOL_NAME) ?></strong><small>Hệ sinh thái quản lý nhà trường</small></span>
  </a>
  <div class="header-actions">
    <details class="module-picker">
      <summary><i class="bi bi-grid-3x3-gap-fill"></i><span>Chuyển module</span><i class="bi bi-chevron-down"></i></summary>
      <div class="module-menu">
        <?php foreach ($modules as $module): ?><a href="<?= e($module['url']) ?>" style="--module-color:<?= e($module['color']) ?>"><i class="bi <?= e($module['icon']) ?>"></i><span><?= e($module['title']) ?></span></a><?php endforeach; ?>
        <?php if ($isAdmin): ?><a href="users.php" style="--module-color:#7c3aed"><i class="bi bi-shield-check"></i><span>Phân quyền</span></a><a href="admin.php?view=drive" style="--module-color:#0f9d58"><i class="bi bi-cloud-check"></i><span>Kho Google Drive</span></a><a href="activity.php" style="--module-color:#475569"><i class="bi bi-activity"></i><span>Nhật ký</span></a><?php endif; ?>
      </div>
    </details>
    <details class="user-picker">
      <summary><span class="avatar"><?= e($avatarUpper) ?></span><span class="user-copy"><strong><?= e($user['name'] ?? '') ?></strong><small><?= e($scopeText) ?></small></span><i class="bi bi-chevron-down"></i></summary>
      <div class="user-menu"><?php if ($isAdmin): ?><a href="users.php"><i class="bi bi-person-gear"></i>Tài khoản và quyền</a><a href="admin.php?view=drive"><i class="bi bi-google"></i>Kho Google Drive</a><?php endif; ?><a href="logout.php" class="logout"><i class="bi bi-box-arrow-right"></i>Đăng xuất</a></div>
    </details>
  </div>
</header>

<main class="dashboard">
  <section class="welcome-card">
    <div class="clock-block">
      <strong id="liveClock"><?= date('H:i') ?></strong>
      <span><?= e($weekdays[(int)date('w')]) ?>, <?= date('d/m/Y') ?></span>
      <small>Âm lịch: ngày <?= (int)$lunar['day'] ?> tháng <?= (int)$lunar['month'] ?><?= $lunar['leap'] ? ' nhuận' : '' ?> năm <?= (int)$lunar['year'] ?></small>
    </div>
    <div class="welcome-copy">
      <span class="eyebrow"><i class="bi bi-stars"></i><?= e($greeting) ?></span>
      <h1><?= e($user['name'] ?? 'Thầy/Cô') ?></h1>
      <?php if ($birthday): ?>
        <div class="birthday-line"><span class="birthday-icon">🎂</span><div><strong><?= $birthday['today'] ? 'Chúc mừng sinh nhật ' : 'Sinh nhật sắp tới: ' ?><?= e($birthday['name']) ?></strong><small><?= $birthday['today'] ? 'Chúc một tuổi mới nhiều sức khỏe, niềm vui và thành công!' : 'Còn ' . (int)$birthday['days'] . ' ngày · ' . date('d/m', strtotime($birthday['date'])) ?></small></div><form method="post"><input type="hidden" name="action" value="mute_birthday"><input type="hidden" name="csrf" value="<?= e($_SESSION['dashboard_csrf']) ?>"><input type="hidden" name="teacher_id" value="<?= e($birthday['id']) ?>"><button title="Ẩn thông báo sinh nhật của người này" aria-label="Ẩn thông báo sinh nhật"><i class="bi bi-x-lg"></i></button></form></div>
      <?php else: ?><p class="daily-quote">“<?= e(cds_dashboard_quote()) ?>”</p><?php endif; ?>
    </div>
    <div class="school-year"><i class="bi bi-calendar3"></i><span>Năm học</span><strong><?= e(SCHOOL_YEAR) ?></strong></div>
  </section>

  <section class="stat-grid" aria-label="Số liệu toàn trường">
    <article class="stat-card class-stat"><i class="bi bi-buildings"></i><div><span>Số lớp</span><strong><?= (int)$dashboard['csdl']['classes'] ?></strong><small>Trong phạm vi được xem</small></div></article>
    <article class="stat-card student-stat"><i class="bi bi-people-fill"></i><div><span>Học sinh</span><strong><?= (int)$dashboard['csdl']['students']['total'] ?></strong><small><b><?= (int)$dashboard['csdl']['students']['male'] ?></b> nam · <b><?= (int)$dashboard['csdl']['students']['female'] ?></b> nữ</small></div></article>
    <article class="stat-card teacher-stat"><i class="bi bi-person-badge-fill"></i><div><span>CBGVNV</span><strong><?= (int)$dashboard['csdl']['teachers']['total'] ?></strong><small><b><?= (int)$dashboard['csdl']['teachers']['male'] ?></b> nam · <b><?= (int)$dashboard['csdl']['teachers']['female'] ?></b> nữ</small></div></article>
  </section>

  <?php if ($quickActions): ?><section class="quick-section"><div class="section-heading"><div><span class="section-kicker">Truy cập nhanh</span><h2>Thao tác thường dùng</h2></div></div><div class="quick-grid"><?php foreach ($quickActions as $action): ?><a href="<?= e($action['url']) ?>" style="--quick-color:<?= e($action['color']) ?>"><i class="bi <?= e($action['icon']) ?>"></i><span><?= e($action['label']) ?></span><i class="bi bi-arrow-up-right"></i></a><?php endforeach; ?></div></section><?php endif; ?>

  <div class="content-grid">
    <section class="panel feed-panel">
      <div class="panel-head"><div><span class="section-kicker">Thông báo</span><h2>Thông báo đang và sắp diễn ra</h2></div><span class="count-pill"><?= count($feedItems) ?></span></div>
      <div class="feed-list" id="professionalFeed">
        <?php foreach ($feedItems as $feedIndex => $item):
          $title = cds_dashboard_feed_title($item) ?: 'Nội dung Chuyên môn';
          $url = $item['url'] ?? $item['link'] ?? '';
          $nearestDate = $item['_dashboard_nearest'] ?? '';
          $endDate = $item['_dashboard_end'] ?? '';
          $state = $item['_dashboard_state'] ?? 'Đang diễn ra';
          $assigneeText = implode(', ', array_slice($item['_dashboard_assignees'] ?? [], 0, 3));
          $kind = in_array(($item['kind'] ?? ''), ['notice','task','salary','seniority'], true) ? $item['kind'] : 'task';
          $icons = ['notice'=>'bi-megaphone-fill','task'=>'bi-check2-square','salary'=>'bi-cash-coin','seniority'=>'bi-award-fill'];
          $detail = $item['_dashboard_detail'] ?? ($assigneeText ? 'Giao cho ' . $assigneeText : 'Nội dung chuyên môn');
        ?>
          <?php if ($url): ?><a href="<?= e($url) ?>" class="feed-row<?= $feedIndex >= 5 ? ' feed-page-hidden' : '' ?>" data-feed-item data-feed-page="<?= intdiv($feedIndex, 5) + 1 ?>"><?php else: ?><div class="feed-row<?= $feedIndex >= 5 ? ' feed-page-hidden' : '' ?>" data-feed-item data-feed-page="<?= intdiv($feedIndex, 5) + 1 ?>"><?php endif; ?>
            <span class="feed-icon <?= e($kind) ?>"><i class="bi <?= e($icons[$kind]) ?>"></i></span>
            <span class="feed-copy">
              <strong><?= e($title) ?></strong>
              <small><?= e($detail) ?><?php if ($nearestDate): ?> · <i class="bi bi-calendar-event"></i> <?= $endDate ? 'Hạn ' : '' ?><?= e(date('d/m/Y', strtotime($nearestDate))) ?><?php endif; ?></small>
            </span>
            <span class="schedule-pill <?= $state === 'Sắp diễn ra' ? 'upcoming' : 'active' ?>"><?= e($state) ?></span>
          <?php if ($url): ?></a><?php else: ?></div><?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$feedItems): ?><div class="empty-state"><i class="bi bi-inbox"></i><strong>Chưa có nội dung sắp tới</strong><span>Thông báo chuyên môn chung, công việc được giao và mốc nhân sự cá nhân sẽ xuất hiện tại đây.</span></div><?php endif; ?>
      </div>
      <?php if (count($feedItems) > 5): ?><nav class="feed-pagination" aria-label="Trang thông báo"><?php for($feedPage=1,$feedPages=(int)ceil(count($feedItems)/5);$feedPage<=$feedPages;$feedPage++): ?><button type="button" class="<?= $feedPage===1?'active':'' ?>" data-feed-page-button="<?= $feedPage ?>" <?= $feedPage===1?'aria-current="page"':'' ?>><?= $feedPage ?></button><?php endfor; ?></nav><?php endif; ?>
    </section>

    <div class="side-stack">
      <?php if ($observations): ?><section class="panel observation-panel"><div class="panel-head"><div><span class="section-kicker">Chuyên môn</span><h2>Lịch dự giờ sắp tới</h2></div><i class="bi bi-journal-check panel-symbol"></i></div><div class="compact-list"><?php foreach($observations as $row): ?><div><time><strong><?= date('d',strtotime($row['dashboard_date'])) ?></strong><span>Th <?= date('m',strtotime($row['dashboard_date'])) ?></span></time><p><strong><?= e($row['teacher']??$row['teacher_name']??$row['name']??'Lịch dự giờ') ?></strong><small><?= e(implode(' · ',array_filter([$row['time']??'',$row['subject']??'',$row['class']??$row['class_name']??'']))) ?></small></p></div><?php endforeach; ?></div></section><?php endif; ?>

      <section class="panel leave-panel"><div class="panel-head"><div><span class="section-kicker">Nhân sự</span><h2>Lịch nghỉ giáo viên</h2></div><i class="bi bi-calendar2-week panel-symbol"></i></div><div class="compact-list leave-list"><?php foreach($dashboard['leave'] as $row): ?><div><time><strong><?= date('d',strtotime($row['from'])) ?></strong><span>Th <?= date('m',strtotime($row['from'])) ?></span></time><p><strong><?= e($row['name']) ?></strong><small><?= e($row['reason'] ?: $row['permission']) ?><?= $row['to']!==$row['from']?' · đến '.date('d/m',strtotime($row['to'])):'' ?></small></p></div><?php endforeach; ?><?php if(!$dashboard['leave']): ?><div class="mini-empty"><i class="bi bi-calendar-check"></i><span>Không có lịch nghỉ hiện tại hoặc sắp tới.</span></div><?php endif; ?></div></section>
    </div>

    <?php if(can_module('noitru','view')): ?><section class="panel operation-panel">
      <div class="panel-head"><div><span class="section-kicker">Nội trú</span><h2>Vận hành hôm nay</h2></div><a href="noitru.php?tab=overview">Xem chi tiết <i class="bi bi-arrow-right"></i></a></div>
      <div class="operation-grid">
        <article class="duty-box"><span class="op-icon"><i class="bi bi-calendar2-check"></i></span><div class="op-copy"><span>Lịch trực hiện tại</span><?php if($duty && $duty['people']): ?><strong><?= e(implode(', ',$duty['people'])) ?></strong><small><?= e($duty['start']) ?> – <?= e($duty['end']) ?> hôm sau · còn <?= $dutyHours ?>h <?= $dutyMinutes ?>p</small><?php else: ?><strong>Chưa phân công</strong><small>Chưa có người trực trong ca hiện tại</small><?php endif; ?><?php if($duty && $duty['managers']): ?><em>Quản lý: <?= e(implode(', ',$duty['managers'])) ?></em><?php endif; ?></div></article>
        <article class="attendance-box"><span class="op-icon"><i class="bi bi-person-check-fill"></i></span><div class="op-copy"><span>Sĩ số điểm danh gần nhất</span><?php if($dashboard['noitru']['attendance_date']): ?><strong><b><?= (int)$dashboard['noitru']['present'] ?></b> có mặt · <b class="absent"><?= (int)$dashboard['noitru']['absent'] ?></b> vắng</strong><small><?= e($shiftLabels[$dashboard['noitru']['attendance_shift']]??$dashboard['noitru']['attendance_shift']) ?> · <?= date('d/m/Y',strtotime($dashboard['noitru']['attendance_date'])) ?></small><?php else: ?><strong>Chưa có dữ liệu</strong><small>Chưa ghi nhận báo cáo điểm danh</small><?php endif; ?></div><a href="noitru_attendance.php" aria-label="Mở điểm danh"><i class="bi bi-chevron-right"></i></a></article>
      </div>
    </section><?php endif; ?>
  </div>

  <section class="module-section"><div class="section-heading"><div><span class="section-kicker">Hệ sinh thái CDS</span><h2>Các module được sử dụng</h2></div></div><div class="module-grid"><?php foreach($modules as $module): ?><a href="<?= e($module['url']) ?>" style="--module-color:<?= e($module['color']) ?>"><i class="bi <?= e($module['icon']) ?>"></i><span><strong><?= e($module['title']) ?></strong><small><?= e($module['subtitle']) ?></small></span><i class="bi bi-chevron-right"></i></a><?php endforeach; ?></div></section>
</main>

<nav class="mobile-dock" aria-label="Điều hướng nhanh"><a class="active" href="admin.php"><i class="bi bi-house-fill"></i><span>Trang chủ</span></a><?php foreach(array_slice($quickActions,0,3) as $action): ?><a href="<?= e($action['url']) ?>"><i class="bi <?= e($action['icon']) ?>"></i><span><?= e($action['label']) ?></span></a><?php endforeach; ?><button type="button" id="mobileModules"><i class="bi bi-grid-fill"></i><span>Module</span></button></nav>

<script>
(function(){
  const clock=document.getElementById('liveClock');
  function tick(){clock.textContent=new Intl.DateTimeFormat('vi-VN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(new Date())}
  tick();setInterval(tick,1000);
  document.getElementById('mobileModules')?.addEventListener('click',function(){document.querySelector('.module-picker').open=true;document.querySelector('.module-picker summary').focus()});
  document.querySelectorAll('[data-feed-page-button]').forEach(function(button){button.addEventListener('click',function(){
    const page=button.dataset.feedPageButton;
    document.querySelectorAll('[data-feed-item]').forEach(function(item){item.classList.toggle('feed-page-hidden',item.dataset.feedPage!==page)});
    document.querySelectorAll('[data-feed-page-button]').forEach(function(other){const active=other===button;other.classList.toggle('active',active);if(active)other.setAttribute('aria-current','page');else other.removeAttribute('aria-current')});
  })});
  document.addEventListener('click',function(e){document.querySelectorAll('details[open]').forEach(function(d){if(!d.contains(e.target)&&!e.target.closest('#mobileModules'))d.open=false})});
})();
</script>
</body></html>
