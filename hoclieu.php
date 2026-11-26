<?php
/* HOC_LIEU_BUILD: 2026-08-11.1 — marker để xác nhận cPanel đã nhận file mới. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
require_login();
require_once __DIR__ . '/includes/learning_article.php';
require_once __DIR__ . '/includes/learning_content.php';
require_once __DIR__ . '/includes/learning_thumbnail.php';
require_once __DIR__ . '/includes/learning_game_catalog.php';
require_once __DIR__ . '/includes/learning_dashboard.php';
require_once __DIR__ . '/includes/learning_categories.php';
require_once __DIR__.'/includes/achievement_pccm.php';

$user = current_user() ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';
$canResourceView = $isAdmin || can_perm_level('hl.xem', 'view');
$canUtilityView = $canResourceView || can_perm_level('hl.thanhtich','view') || ab_pccm_navigation_hint($user);
$canUtilityCatalog = $canResourceView ? lh_game_catalog() : array_values(array_filter(lh_game_catalog(),fn($g)=>$g['key']==='achievement'));
$canResourceEdit = $isAdmin || can_perm_level('hl.xem', 'edit');
$canResourceDelete = $isAdmin || can_perm_level('hl.xem', 'delete');
$canExamView = $isAdmin || can_perm_level('hl.kiemtra', 'view');
$canExamEdit = $isAdmin || can_perm_level('hl.kiemtra', 'edit');
$canExamDelete = $isAdmin || can_perm_level('hl.kiemtra', 'delete');
$canLinkView = $isAdmin || can_perm_level('hl.lienket', 'view');
$canLinkEdit = $isAdmin || can_perm_level('hl.lienket', 'edit');
$canLinkDelete = $isAdmin || can_perm_level('hl.lienket', 'delete');
$canApprove = $isAdmin || can_perm_level('hl.duyet', 'edit');
$userId = (string)($user['id'] ?? $user['username'] ?? '');
$userName = trim((string)($user['teacher_name'] ?? $user['name'] ?? $user['username'] ?? 'Giáo viên'));
$dataFile = DATA_PATH . '/learning_hub.json';
$data = load_json($dataFile, ['items' => [], 'links' => []]);
$data['items'] = is_array($data['items'] ?? null) ? array_values($data['items']) : [];
$data['links'] = is_array($data['links'] ?? null) ? array_values($data['links']) : [];
$categoryStore=lhc_empty();$categoryError='';
try{$categoryStore=lhc_load();}catch(Throwable $e){$categoryError='Không đọc được chuyên mục. Vui lòng liên hệ quản trị để kiểm tra dữ liệu.';}
$gameKeys=array_column(lh_game_catalog(),'key');
$gameVisibility=[];

if (empty($_SESSION['learning_hub_csrf'])) $_SESSION['learning_hub_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['learning_hub_csrf'];

function lh_redirect(string $tab = 'dashboard'): void {
    header('Location: ' . BASE_URL . 'hoclieu.php?tab=' . urlencode($tab));
    exit;
}
function lh_url(string $value): string {
    $value = trim($value);
    return preg_match('#^https?://#i', $value) ? $value : '';
}
function lh_find(array $rows, string $id): int {
    foreach ($rows as $index => $row) if (($row['id'] ?? '') === $id) return $index;
    return -1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Phiên làm việc không hợp lệ.');
    }
    $action = (string)($_POST['action'] ?? '');
    $tab = in_array($_POST['tab'] ?? '', ['resources', 'exams', 'links'], true) ? (string)$_POST['tab'] : 'resources';

    if(in_array($action,['save_category','delete_category','assign_game_category'],true)){
        $scope=is_string($_POST['category_section']??null)?$_POST['category_section']:'';
        $scopeView=['resources'=>$canResourceView,'exams'=>$canExamView,'games'=>$canUtilityView];
        if(empty($scopeView[$scope])||!($scope==='games'?$isAdmin:($isAdmin||$canApprove))){http_response_code(403);exit('Bạn chưa có quyền quản lý chuyên mục trong phần này.');}
        try{
            if($categoryError!=='')throw new RuntimeException($categoryError);
            foreach(['category_revision','category_id','name','description','order','game_key']as$field)if(isset($_POST[$field])&&!is_string($_POST[$field]))throw new InvalidArgumentException('Dữ liệu chuyên mục không hợp lệ.');
            lhc_save_change((string)($_POST['category_revision']??''),$scope,$action,$_POST,$gameKeys);flash('Đã cập nhật chuyên mục.','success');
        }catch(Throwable $e){flash($e->getMessage(),'danger');}
        header('Location: '.BASE_URL.'hoclieu.php?tab='.rawurlencode($scope).'&manage_categories=1#categoryManager');exit;
    }
    if ($action === 'game_visibility') {
        if (!$isAdmin) { http_response_code(403); exit('Chỉ quản trị được cài đặt trò chơi.'); }
        $key=(string)($_POST['game_key']??'');
        if (!in_array($key,$gameKeys,true)) { http_response_code(400); exit('Trò chơi không hợp lệ.'); }
        qp_schema();
        qp_db()->prepare('INSERT INTO cds_quiz_game_visibility(game_key,visible) VALUES(?,?) ON DUPLICATE KEY UPDATE visible=VALUES(visible)')->execute([$key,($_POST['visible']??'')==='1'?1:0]);
        lh_redirect('games');
    }
    if ($action === 'save_item') {
        $section = ($_POST['section'] ?? '') === 'exam' ? 'exam' : 'resource';
        $tab = $section === 'exam' ? 'exams' : 'resources';
        $canSectionEdit = $section === 'exam' ? $canExamEdit : $canResourceEdit;
        if (!$canSectionEdit) { http_response_code(403); exit('Bạn chưa có quyền nộp hoặc sửa nội dung tại trang này.'); }
        $title = trim((string)($_POST['title'] ?? ''));
        $grade = trim((string)($_POST['grade'] ?? ''));
        $source = trim((string)($_POST['source'] ?? ''));
        $kind = trim((string)($_POST['kind'] ?? ''));
        $sourceKind = is_string($_POST['source_kind']??null) ? $_POST['source_kind'] : '';
        $url = lh_url((string)($_POST['url'] ?? ''));
        $id = trim((string)($_POST['id'] ?? ''));
        if ($title === '' || $grade === '' || $kind === '') {
            flash('Vui lòng nhập đủ tên, loại và khối.', 'danger'); lh_redirect($tab);
        }

        try { $url=lc_validate_choice($section,$kind,$sourceKind,$url); } catch (InvalidArgumentException $e) { flash($e->getMessage(),'danger'); lh_redirect($tab); }
        $article = $sourceKind === 'article' ? lh_article_clean(trim((string)($_POST['article_body'] ?? ''))) : '';
        if ($sourceKind === 'article' && ($section !== 'resource' || strlen($article) > 8 * 1024 * 1024 || (trim(html_entity_decode(strip_tags($article))) === '' && !preg_match('/<img\b/i', $article)))) {
            flash('Bài viết cần có nội dung và không vượt quá 8 MB (gồm ảnh).', 'danger'); lh_redirect($tab);
        }
        $index = lh_find($data['items'], $id);
        if ($index >= 0 && !$canApprove && ($data['items'][$index]['author_id'] ?? '') !== $userId) { http_response_code(403); exit('Chỉ được sửa nội dung do mình nộp.'); }
        $categoryScope=$section==='exam'?'exams':'resources';
        $categoryId=isset($_POST['category_id'])?(is_string($_POST['category_id'])?$_POST['category_id']:'invalid'):lhc_category_id($categoryStore,$categoryScope,(string)($data['items'][$index]['category_id']??''));
        try{if($categoryError!=='')throw new RuntimeException($categoryError);$categoryId=lhc_validate_item_category($categoryStore,$categoryScope,$categoryId);}catch(Throwable $e){flash($e->getMessage(),'danger');lh_redirect($tab);}
        $filePath = ''; $fileName = ''; $fileMime = '';
        $upload = $_FILES['file'] ?? null;
        if (in_array($sourceKind, ['upload', 'html'], true) && $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                flash('Không đọc được tệp tải lên.', 'danger'); lh_redirect($tab);
            }
            $name = basename((string)($upload['name'] ?? 'hoc-lieu'));
            $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
            if ($sourceKind === 'html' && !in_array($extension, ['html', 'htm'], true)) {
                flash('Học liệu HTML phải là tệp .html hoặc .htm.', 'danger'); lh_redirect($tab);
            }
            $bytes = file_get_contents((string)$upload['tmp_name']);
            if ($bytes === false || strlen($bytes) > 50 * 1024 * 1024) {
                flash('Tệp không hợp lệ hoặc vượt quá 50 MB.', 'danger'); lh_redirect($tab);
            }
            $mime = function_exists('mime_content_type') ? (mime_content_type((string)$upload['tmp_name']) ?: 'application/octet-stream') : 'application/octet-stream';
            try { $mime=lc_upload_mime($kind,$sourceKind,$name,$bytes,$mime); } catch (InvalidArgumentException $e) { flash($e->getMessage(),'danger'); lh_redirect($tab); }
            $driveType = $section === 'exam' ? 'exam_bank' : 'learning_resources';
            $result = cds_drive_upload_bytes($bytes, $name, $mime, $driveType, ['title' => $title, 'grade' => $grade, 'source_action' => 'page:/hoclieu.php']);
            if (empty($result['ok'])) {
                flash($result['message'] ?? 'Không tải được tệp lên Google Drive.', 'danger'); lh_redirect($tab);
            }
            $filePath = (string)$result['path']; $fileName=$name; $fileMime=$mime;
        }
        if (in_array($sourceKind,['link','youtube','vimeo','gdrive','video_url','image_url'],true) && $url === '') {
            flash('Vui lòng nhập liên kết bắt đầu bằng http:// hoặc https://.', 'danger'); lh_redirect($tab);
        }

        $index = lh_find($data['items'], $id);
        $previousThumbnail=$index>=0?(string)($data['items'][$index]['thumbnail_file']??''):'';
        $thumbnailFile=$previousThumbnail;
        if ($index >= 0) {
            $old = $data['items'][$index];
            if (!$canApprove && ($old['author_id'] ?? '') !== $userId) { http_response_code(403); exit('Chỉ được sửa nội dung do mình nộp.'); }
            if (in_array($sourceKind,['upload','html'],true) && $filePath==='' && (($old['source_kind']??'')!==$sourceKind || empty($old['file_path']) || ($sourceKind==='upload' && ($old['kind']??'')!==$kind))) { flash('Vui lòng chọn tệp phù hợp khi đổi loại hoặc hình thức nội dung.','danger'); lh_redirect($tab); }
            try{if(isset($_POST['thumbnail_external_url'])&&!is_string($_POST['thumbnail_external_url']))throw new InvalidArgumentException('Link ảnh đại diện không hợp lệ.');$thumbnail=lt_select_thumbnail($index>=0?$data['items'][$index]:[],is_array($_FILES['thumbnail']??null)?$_FILES['thumbnail']:null,($_POST['remove_thumbnail']??'')==='1',$_POST['thumbnail_external_url']??null);$thumbnailFile=$thumbnail['thumbnail_file'];}catch(Throwable $e){flash($e->getMessage(),'danger');lh_redirect($tab);}
            $data['items'][$index] = array_merge($old, [
                'section' => $section, 'title' => $title, 'kind' => $kind, 'grade' => $grade,
                'source' => $source, 'source_kind' => $sourceKind, 'article_body' => $article, 'thumbnail_file'=>$thumbnailFile, 'thumbnail_external_url'=>$thumbnail['thumbnail_external_url'], 'category_id'=>$categoryId,
                'url' => in_array($sourceKind,['link','youtube','vimeo','gdrive','video_url','image_url'],true) ? $url : '',
                'file_path' => $sourceKind === 'article' ? '' : ($filePath !== '' ? $filePath : (string)($old['file_path'] ?? '')),
                'file_name'=>$fileName!==''?$fileName:($old['file_name']??''), 'file_mime'=>$fileMime!==''?$fileMime:($old['file_mime']??''),
                'updated_at' => date('c'),
            ]);
        } else {
            if (in_array($sourceKind, ['upload', 'html'], true) && $filePath === '') {
                flash('Vui lòng chọn tệp cần tải lên.', 'danger'); lh_redirect($tab);
            }
            try{if(isset($_POST['thumbnail_external_url'])&&!is_string($_POST['thumbnail_external_url']))throw new InvalidArgumentException('Link ảnh đại diện không hợp lệ.');$thumbnail=lt_select_thumbnail($index>=0?$data['items'][$index]:[],is_array($_FILES['thumbnail']??null)?$_FILES['thumbnail']:null,($_POST['remove_thumbnail']??'')==='1',$_POST['thumbnail_external_url']??null);$thumbnailFile=$thumbnail['thumbnail_file'];}catch(Throwable $e){flash($e->getMessage(),'danger');lh_redirect($tab);}
            $data['items'][] = [
                'id' => 'hl_' . bin2hex(random_bytes(8)), 'section' => $section, 'title' => $title,
                'kind' => $kind, 'grade' => $grade, 'source' => $source, 'source_kind' => $sourceKind, 'article_body' => $article, 'thumbnail_file'=>$thumbnailFile, 'thumbnail_external_url'=>$thumbnail['thumbnail_external_url'], 'category_id'=>$categoryId,
                'url' => in_array($sourceKind,['link','youtube','vimeo','gdrive','video_url','image_url'],true) ? $url : '', 'file_path' => $filePath, 'file_name'=>$fileName, 'file_mime'=>$fileMime,
                'author_id' => $userId, 'author' => $userName, 'approved' => $canApprove,
                'approved_at' => $canApprove ? date('c') : '', 'approved_by' => $canApprove ? $userName : '',
                'featured' => false, 'created_at' => date('c'), 'updated_at' => date('c'),
            ];
        }
        $saved=save_json($dataFile, $data);
        if($saved===false){if($thumbnailFile!==$previousThumbnail)lt_delete_file($thumbnailFile);flash('Không lưu được học liệu. Vui lòng thử lại.','danger');lh_redirect($tab);}
        if($previousThumbnail!==$thumbnailFile)lt_delete_file($previousThumbnail);
        flash($canApprove ? 'Đã lưu và hiển thị nội dung.' : 'Đã gửi nội dung, vui lòng chờ người có quyền duyệt.', 'success');
        lh_redirect($tab);
    }

    if ($action === 'approve_item' || $action === 'feature_item' || $action === 'delete_item') {
        $id = trim((string)($_POST['id'] ?? ''));
        $index = lh_find($data['items'], $id);
        if ($index < 0) { flash('Không tìm thấy nội dung.', 'danger'); lh_redirect($tab); }
        $itemSection = ($data['items'][$index]['section'] ?? '') === 'exam' ? 'exam' : 'resource';
        $canItemDelete = $itemSection === 'exam' ? $canExamDelete : $canResourceDelete;
        if (($action === 'approve_item' || $action === 'feature_item') && !$canApprove) { http_response_code(403); exit('Bạn chưa có quyền duyệt nội dung.'); }
        if ($action === 'delete_item' && !$canItemDelete && !$canApprove) { http_response_code(403); exit('Bạn chưa có quyền xóa nội dung.'); }
        if ($action === 'delete_item') {
            $deletedThumbnail=(string)($data['items'][$index]['thumbnail_file']??'');
            array_splice($data['items'], $index, 1);
            flash('Đã xóa nội dung.', 'warning');
        } elseif ($action === 'approve_item') {
            $data['items'][$index]['approved'] = true;
            $data['items'][$index]['approved_at'] = date('c');
            $data['items'][$index]['approved_by'] = $userName;
            flash('Đã duyệt và công khai nội dung.', 'success');
        } else {
            $data['items'][$index]['featured'] = empty($data['items'][$index]['featured']);
            flash('Đã cập nhật trạng thái nổi bật.', 'success');
        }
        $saved=save_json($dataFile, $data);if($saved!==false&&isset($deletedThumbnail))lt_delete_file($deletedThumbnail); lh_redirect($tab);
    }

    if ($action === 'save_link') {
        if (!$canLinkEdit) { http_response_code(403); exit('Bạn chưa có quyền quản lý liên kết.'); }
        $id = trim((string)($_POST['id'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? 'bi-link-45deg'));
        $url = lh_url((string)($_POST['url'] ?? ''));
        if ($title === '' || $url === '') { flash('Tên trang hoặc liên kết không hợp lệ.', 'danger'); lh_redirect('links'); }
        $index = lh_find($data['links'], $id);
        if($id!==''&&$index<0){flash('Không tìm thấy liên kết cần sửa.','danger');lh_redirect('links');}
        $old=$index>=0?$data['links'][$index]:[];
        try{
            foreach(['image_url','description','open_mode']as$field)if(isset($_POST[$field])&&!is_string($_POST[$field]))throw new InvalidArgumentException('Thông tin liên kết không hợp lệ.');
            $image=lt_external_url($_POST['image_url']??($old['image_url']??''));$description=trim($_POST['description']??($old['description']??''));$mode=$_POST['open_mode']??($old['open_mode']??'new_tab');
            if(mb_strlen($title)>200||mb_strlen($description)>1000||!in_array($mode,['embed','new_tab'],true)||lc_web_url($url)==='')throw new InvalidArgumentException('Kiểm tra lại liên kết, tên (tối đa 200 ký tự), mô tả (tối đa 1000 ký tự) và cách mở.');
        }catch(InvalidArgumentException $e){flash($e->getMessage(),'danger');lh_redirect('links');}
        $row = array_merge($old,['id'=>$id!==''?$id:'lk_'.bin2hex(random_bytes(6)),'title'=>$title,'icon'=>preg_match('/^bi-[a-z0-9-]+$/D',$icon)?$icon:'bi-link-45deg','url'=>$url,'image_url'=>$image,'description'=>$description,'open_mode'=>$mode,'updated_at'=>date('c')]);
        if ($index >= 0) $data['links'][$index] = $row; else $data['links'][] = $row;
        if(save_json($dataFile,$data)===false){flash('Không lưu được liên kết.','danger');lh_redirect('links');} flash('Đã lưu liên kết.', 'success'); lh_redirect('links');
    }
    if ($action === 'delete_link') {
        if (!$canLinkDelete) { http_response_code(403); exit('Bạn chưa có quyền xóa liên kết.'); }
        $index = lh_find($data['links'], trim((string)($_POST['id'] ?? '')));
        if ($index >= 0) array_splice($data['links'], $index, 1);
        save_json($dataFile, $data); flash('Đã xóa liên kết.', 'warning'); lh_redirect('links');
    }
}

$tab = in_array($_GET['tab'] ?? '', ['dashboard', 'resources', 'exams', 'games', 'links'], true) ? (string)$_GET['tab'] : 'dashboard';
if ($tab === 'resources' && !$canResourceView) $tab = 'dashboard';
if ($tab === 'exams' && !$canExamView) $tab = 'dashboard';
if ($tab === 'links' && !$canLinkView) $tab = 'dashboard';
if ($tab === 'games' && !$canUtilityView) $tab = 'dashboard';
$selectedCategory=is_string($_GET['category']??null)?$_GET['category']:'';
$canManageCategories=$categoryError===''&&in_array($tab,['resources','exams','games'],true)&&($tab==='games'?$isAdmin:($isAdmin||$canApprove));
$gameVisibilityLoaded = false;
if ($canUtilityView && in_array($tab, ['dashboard','games'], true)) {
    try { $gameVisibility = qp_game_visibility(); $gameVisibilityLoaded = true; } catch (Throwable $e) { $gameVisibility = []; }
}
$q = trim((string)($_GET['q'] ?? ''));
$gradeFilter = trim((string)($_GET['grade'] ?? ''));
$visibleItems = array_values(array_filter($data['items'], fn($row) => !empty($row['approved']) || $isAdmin || cds_is_viewer($user) || ($row['author_id'] ?? '') === $userId));

$list = array_values(array_filter($visibleItems, function($row) use ($tab, $q, $gradeFilter) {
    if ($tab === 'resources' && ($row['section'] ?? '') !== 'resource') return false;
    if ($tab === 'exams' && ($row['section'] ?? '') !== 'exam') return false;
    if ($gradeFilter !== '' && (string)($row['grade'] ?? '') !== $gradeFilter) return false;
    if ($q !== '') {
        $haystack = mb_strtolower(implode(' ', [$row['title'] ?? '', $row['kind'] ?? '', $row['source'] ?? '', $row['author'] ?? '']), 'UTF-8');
        if (!str_contains($haystack, mb_strtolower($q, 'UTF-8'))) return false;
    }
    return true;
}));
usort($list, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

function lh_item_url(array $row): string { return BASE_URL.'hoclieu_file.php?id='.rawurlencode((string)($row['id']??'')).'&preview=1'; }

function lh_icon(string $kind): string {
    $map = ['Bài viết'=>'bi-journal-richtext','Văn bản'=>'bi-file-earmark-text','Ứng dụng'=>'bi-window','Video'=>'bi-play-circle','Trình chiếu'=>'bi-easel2','Hình ảnh'=>'bi-image','Đề kiểm tra'=>'bi-ui-checks','Thi thử'=>'bi-stopwatch','Kỳ thi'=>'bi-mortarboard'];
    return $map[$kind] ?? 'bi-folder2-open';
}
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Học liệu và thi – <?= e(SCHOOL_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#164e7a;--blue2:#236a9f;--bg:#eef4f9}body{background:var(--bg);font-family:Segoe UI,system-ui,sans-serif;color:#1f2937}.top{background:linear-gradient(125deg,#103e64,#2379ad);color:#fff}.brand{font-size:1.25rem;font-weight:800}.nav-pills .nav-link{color:#dceeff;font-weight:650;border-radius:10px}.nav-pills .nav-link.active,.nav-pills .nav-link:hover{background:#fff;color:var(--blue)}.card{border:0;border-radius:16px;box-shadow:0 5px 20px rgba(15,55,85,.08)}.stat{position:relative;overflow:hidden}.stat i{position:absolute;right:18px;bottom:4px;font-size:3.5rem;opacity:.12}.stat strong{font-size:2rem;color:var(--blue)}.resource-card{height:100%;transition:.2s}.resource-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(15,55,85,.14)}.resource-icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#e3f2fd;color:var(--blue);font-size:1.45rem}.status{font-size:.75rem;font-weight:700;border-radius:999px;padding:.25rem .55rem}.pending{background:#fff3cd;color:#765b00}.approved{background:#d1e7dd;color:#0f5132}.link-tile{display:flex;align-items:center;gap:12px;text-decoration:none;color:#1f2937;padding:18px;height:100%}.link-tile i{width:48px;height:48px;border-radius:14px;background:#e3f2fd;color:var(--blue);display:grid;place-items:center;font-size:1.5rem}.modal-header{background:var(--blue);color:#fff}.modal-header .btn-close{filter:invert(1)}@media(max-width:767px){.top .nav{margin-top:1rem}.nav-pills{flex-wrap:nowrap;overflow:auto}.nav-pills .nav-link{white-space:nowrap}}
</style>
<link rel="stylesheet" href="<?=BASE_URL?>assets/learning-thumbnail.css?v=3"><link rel="stylesheet" href="<?=BASE_URL?>assets/learning-categories.css?v=1">
</head><body>
<?php if ($tab === 'dashboard'): ?><link rel="stylesheet" href="<?= BASE_URL ?>assets/learning-dashboard.css?v=2"><?php endif; ?>
<?php if ($tab === 'games'): ?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/hoclieu-games.css?v=20261110-5">
<?php endif; ?>
<style>.stat{padding-right:3.5rem!important}.stat>i{right:12px;bottom:12px;font-size:2rem;opacity:.16;pointer-events:none}.stat strong{display:block;line-height:1.1}.stat>span{display:block;line-height:1.35}</style>
<?php require_once __DIR__.'/includes/module_switcher.php'; ?>
<header class="top shadow-sm"><div class="container py-3">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
<a class="brand text-white text-decoration-none" href="<?= BASE_URL ?>hoclieu.php"><i class="bi bi-laptop me-2"></i>HỌC LIỆU VÀ THI</a>
<ul class="nav nav-pills gap-1">
<li><a class="nav-link <?= $tab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="bi bi-grid me-1"></i>Tổng quan</a></li>
<?php if($canResourceView):?><li><a class="nav-link <?= $tab==='resources'?'active':'' ?>" href="?tab=resources"><i class="bi bi-collection me-1"></i>Học liệu</a></li><?php endif;?>
<?php if($canExamView):?><li><a class="nav-link <?= $tab==='exams'?'active':'' ?>" href="?tab=exams"><i class="bi bi-ui-checks me-1"></i>Kiểm tra và thi</a></li><?php endif;?>
<?php if($canUtilityView):?><li><a class="nav-link <?= $tab==='games'?'active':'' ?>" href="?tab=games"><i class="bi bi-trophy me-1"></i>Tiện ích</a></li><?php endif;?>
<?php if($canLinkView):?><li><a class="nav-link <?= $tab==='links'?'active':'' ?>" href="?tab=links"><i class="bi bi-link-45deg me-1"></i>Liên kết</a></li><?php endif;?>
</ul>
<div class="d-flex gap-2"><a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>"><i class="bi bi-diagram-3"></i> Hệ sinh thái</a><a class="btn btn-sm btn-light" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right"></i></a></div>
</div></div></header>
<main class="container py-4<?= $tab === 'games' ? ' games-page' : '' ?>"><?php show_flash(); if($categoryError!==''):?><div class="alert alert-warning" role="alert"><?=e($categoryError)?></div><?php endif;?>

<?php if ($tab === 'dashboard'): ?>
<?php
$dashboard = lh_dashboard_model($visibleItems, $data['links'], $canUtilityCatalog, $gameVisibility, $gameVisibilityLoaded, ['resources'=>$canResourceView,'exams'=>$canExamView,'games'=>$canUtilityView,'links'=>$canLinkView], $isAdmin);
foreach($dashboard as$key=>&$dashboardSection)$dashboardSection['categories']=in_array($key,['resources','exams','games'],true)?lhc_categories($categoryStore,$key):[];unset($dashboardSection);
require __DIR__.'/includes/learning_dashboard_view.php';
?>

<?php elseif ($tab === 'resources' || $tab === 'exams'): $isExam=$tab==='exams';$canCurrentEdit=$isExam?$canExamEdit:$canResourceEdit;$canCurrentDelete=$isExam?$canExamDelete:$canResourceDelete; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h2 class="fw-bold mb-1"><?= $isExam?'Kiểm tra và thi':'Học liệu' ?></h2><p class="text-muted mb-0"><?= $isExam?'Ngân hàng đề, thi thử và các kỳ thi':'Học liệu số dùng chung đã được quản trị phê duyệt' ?></p></div><?php if($canCurrentEdit):?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal"><i class="bi bi-plus-circle me-1"></i> Nhập <?= $isExam?'đề / kỳ thi':'học liệu' ?></button><?php endif;?></div>
<?php require __DIR__.'/includes/learning_category_manager.php'; ?>
<form class="card p-3 mb-3"><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="category" value="<?=e($selectedCategory)?>"><div class="row g-2"><div class="col-md-7"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên, loại, nguồn hoặc giáo viên"></div><div class="col-md-3"><select class="form-select" name="grade"><option value="">Tất cả khối</option><?php foreach (['6','7','8','9','10','11','12','Toàn trường'] as $g): ?><option <?= $gradeFilter===$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Tìm</button></div></div></form>
<?php $categoryShelves=lhc_shelves($categoryStore,$tab,$list,$selectedCategory);require __DIR__.'/includes/learning_category_shelves.php'; ?>

<div class="modal fade" id="itemModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data" id="itemForm"><div class="modal-header"><h5 class="modal-title">Nhập <?= $isExam?'đề, bài kiểm tra hoặc kỳ thi':'học liệu' ?></h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_item"><input type="hidden" name="id" id="itemId"><input type="hidden" name="section" value="<?= $isExam?'exam':'resource' ?>">
<div class="row g-3"><div class="col-md-8"><label class="form-label fw-semibold">Tên <?= $isExam?'đề / kỳ thi':'học liệu' ?></label><input class="form-control" name="title" required></div><div class="col-md-4"><label class="form-label fw-semibold">Loại</label><select class="form-select" name="kind" id="contentKind" required><option value="">Chọn loại</option><?php foreach ($isExam?['Đề kiểm tra','Thi thử','Kỳ thi']:['Bài viết','Văn bản','Ứng dụng','Video','Trình chiếu','Hình ảnh','Trò chơi'] as $kind): ?><option><?= e($kind) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Khối</label><select class="form-select" name="grade" required><option value="">Chọn khối</option><?php foreach (['6','7','8','9','10','11','12','Toàn trường'] as $g): ?><option><?= $g ?></option><?php endforeach; ?></select></div><div class="col-md-8"><label class="form-label fw-semibold">Nguồn</label><input class="form-control" name="source" placeholder="Bộ GDĐT, giáo viên biên soạn…"></div>
<div class="col-12"><label class="form-label fw-semibold" for="learningCategory">Chuyên mục</label><select class="form-select" name="category_id" id="learningCategory"><option value="">Chưa phân chuyên mục</option><?php foreach(lhc_categories($categoryStore,$tab)as$categoryKey=>$category):?><option value="<?=e($categoryKey)?>" <?=$selectedCategory===$categoryKey?'selected':''?>><?=e($category['name'])?></option><?php endforeach;?></select></div>
<div class="col-12"><label for="learningThumbnail" class="form-label fw-semibold">Ảnh đại diện <span class="fw-normal text-muted">(không bắt buộc)</span></label><div class="learning-thumbnail-editor"><div class="learning-thumbnail-preview"><img id="learningThumbnailImage" alt="Xem trước ảnh đại diện" hidden><div id="learningThumbnailDefault"><?=lt_cover(['kind'=>''])?></div></div><div class="learning-thumbnail-controls"><label for="learningThumbnailUrl" class="form-label">Link ảnh bên ngoài</label><input type="url" class="form-control mb-2" id="learningThumbnailUrl" name="thumbnail_external_url" maxlength="2048" placeholder="https://…/anh-dai-dien.jpg"><div class="form-text mb-2">Dán link trực tiếp đến ảnh hoặc tải ảnh bên dưới. Ảnh tự phủ kín khung 16:9, có thể cắt bớt phần rìa để giữ đúng tỉ lệ.</div><label for="learningThumbnail" class="form-label">Hoặc tải ảnh lên</label><input type="file" class="form-control" id="learningThumbnail" name="thumbnail" accept="image/png,image/jpeg,image/gif,image/webp"><div class="form-text">PNG, JPG, GIF hoặc WebP · tối đa 2 MB, 4096 × 4096 px. Nên chọn ảnh ngang 16:9. Không chọn ảnh sẽ dùng hình đại diện theo loại.</div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_thumbnail" value="1" id="learningThumbnailRemove"><label class="form-check-label" for="learningThumbnailRemove">Bỏ ảnh, dùng hình mặc định</label></div><div id="learningThumbnailError" class="text-danger small" role="alert"></div></div></div></div>
<div class="col-12"><label class="form-label fw-semibold">Hình thức nội dung</label><select class="form-select" name="source_kind" id="sourceKind" required disabled><option value="">Chọn loại nội dung trước</option></select><div id="contentSourceHelp" class="form-text"></div></div>
<div class="col-12 d-none" id="articleField"><label class="form-label fw-semibold">Nội dung bài viết</label><textarea id="articleBody" name="article_body" class="form-control" rows="18"></textarea><div class="form-text">Dán chữ và ảnh bằng Ctrl+V; dùng nút ảnh để chọn ảnh từ máy. Tối đa 8 MB gồm ảnh.</div><div id="editorError" class="text-danger small" role="alert"></div></div><div class="col-12" id="fileField"><label class="form-label fw-semibold">Chọn tệp</label><input class="form-control" type="file" name="file" id="itemFile"><div class="form-text"><span id="contentFileHelp">Tối đa 50 MB. Chọn định dạng phù hợp với loại nội dung.</span></div></div><div class="col-12 d-none" id="urlField"><label class="form-label fw-semibold" id="contentUrlLabel" for="contentUrl">Đường liên kết</label><input class="form-control" type="url" name="url" id="contentUrl" placeholder="https://..."></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" id="saveButton"><i class="bi bi-cloud-arrow-up"></i> Tải lên và gửi duyệt</button></div></form></div></div></div>

<?php elseif ($tab === 'games' && $canUtilityView): ?>
<div class="games-intro mb-3"><h2 class="fw-bold mb-2"><i class="bi bi-controller me-2" aria-hidden="true"></i>Tiện ích hỗ trợ dạy học</h2><p>Các công cụ và hoạt động tương tác phục vụ dạy học.</p></div>
<?php if ($isAdmin): ?><details class="card p-3 mb-3"><summary class="fw-bold">Ẩn / hiện tiện ích với giáo viên</summary><div class="row g-2 mt-2"><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="secret"><span>Ô cửa bí mật</span><button class="btn btn-sm btn-outline-primary" name="visible" value="<?=(!isset($gameVisibility['secret']) || $gameVisibility['secret'])?'0':'1'?>"><?=(!isset($gameVisibility['secret']) || $gameVisibility['secret'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="quiz"><span>Hỏi Nhanh - Đáp Gọn</span><button class="btn btn-sm <?=(!isset($gameVisibility['quiz']) || $gameVisibility['quiz'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['quiz']) || $gameVisibility['quiz'])?'0':'1'?>"><?=(!isset($gameVisibility['quiz']) || $gameVisibility['quiz'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="cards"><span>Thẻ trả lời A–D</span><button class="btn btn-sm <?=(!isset($gameVisibility['cards']) || $gameVisibility['cards'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['cards']) || $gameVisibility['cards'])?'0':'1'?>"><?=(!isset($gameVisibility['cards']) || $gameVisibility['cards'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="olympia"><span>Đường lên đỉnh Olympia</span><button class="btn btn-sm <?=(!isset($gameVisibility['olympia']) || $gameVisibility['olympia'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['olympia']) || $gameVisibility['olympia'])?'0':'1'?>"><?=(!isset($gameVisibility['olympia']) || $gameVisibility['olympia'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="wheel"><span>Vòng quay may mắn</span><button class="btn btn-sm <?=(!isset($gameVisibility['wheel']) || $gameVisibility['wheel'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['wheel']) || $gameVisibility['wheel'])?'0':'1'?>"><?=(!isset($gameVisibility['wheel']) || $gameVisibility['wheel'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="keoco"><span>Kéo co kiến thức</span><button class="btn btn-sm <?=(!isset($gameVisibility['keoco']) || $gameVisibility['keoco'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['keoco']) || $gameVisibility['keoco'])?'0':'1'?>"><?=(!isset($gameVisibility['keoco']) || $gameVisibility['keoco'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="duavit"><span>Đua vịt gắn tên học sinh</span><button class="btn btn-sm <?=(!isset($gameVisibility['duavit']) || $gameVisibility['duavit'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['duavit']) || $gameVisibility['duavit'])?'0':'1'?>"><?=(!isset($gameVisibility['duavit']) || $gameVisibility['duavit'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="loto"><span>Quay số gọi tên</span><button class="btn btn-sm <?=(!isset($gameVisibility['loto']) || $gameVisibility['loto'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['loto']) || $gameVisibility['loto'])?'0':'1'?>"><?=(!isset($gameVisibility['loto']) || $gameVisibility['loto'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="chopnot"><span>Chớp Nốt</span><button class="btn btn-sm <?=(!isset($gameVisibility['chopnot']) || $gameVisibility['chopnot'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['chopnot']) || $gameVisibility['chopnot'])?'0':'1'?>"><?=(!isset($gameVisibility['chopnot']) || $gameVisibility['chopnot'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="clock"><span>Bộ đếm thời gian</span><button class="btn btn-sm <?=(!isset($gameVisibility['clock']) || $gameVisibility['clock'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['clock']) || $gameVisibility['clock'])?'0':'1'?>"><?=(!isset($gameVisibility['clock']) || $gameVisibility['clock'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="balldrop"><span>Thả bóng gọi tên</span><button class="btn btn-sm <?=(!isset($gameVisibility['balldrop']) || $gameVisibility['balldrop'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['balldrop']) || $gameVisibility['balldrop'])?'0':'1'?>"><?=(!isset($gameVisibility['balldrop']) || $gameVisibility['balldrop'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="rocket"><span>Tên lửa hạ cánh</span><button class="btn btn-sm <?=(!isset($gameVisibility['rocket']) || $gameVisibility['rocket'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['rocket']) || $gameVisibility['rocket'])?'0':'1'?>"><?=(!isset($gameVisibility['rocket']) || $gameVisibility['rocket'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="buzzer"><span>Bấm chuông</span><button class="btn btn-sm <?=(!isset($gameVisibility['buzzer']) || $gameVisibility['buzzer'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['buzzer']) || $gameVisibility['buzzer'])?'0':'1'?>"><?=(!isset($gameVisibility['buzzer']) || $gameVisibility['buzzer'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div><div class="col-md-6"><form method="post" class="d-flex align-items-center justify-content-between gap-2"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="game_visibility"><input type="hidden" name="game_key" value="muc"><span class="fw-bold">Mực ơi đừng dậy!</span><button class="btn btn-sm <?=(!isset($gameVisibility['muc']) || $gameVisibility['muc'])?'btn-outline-success':'btn-outline-secondary'?>" name="visible" value="<?=(!isset($gameVisibility['muc']) || $gameVisibility['muc'])?'0':'1'?>"><?=(!isset($gameVisibility['muc']) || $gameVisibility['muc'])?'Đang hiện · bấm để ẩn':'Đang ẩn · bấm để hiện'?></button></form></div></div></details><?php endif; ?>
<?php
$games = $canUtilityCatalog;

?>
<?php require __DIR__.'/includes/learning_category_manager.php';
$games=$gameVisibilityLoaded?array_values(array_filter($games,fn($game)=>$isAdmin||!isset($gameVisibility[$game['key']])||$gameVisibility[$game['key']])):[];
if($q!=='')$games=array_values(array_filter($games,fn($game)=>str_contains(mb_strtolower($game['name'].' '.$game['description']),mb_strtolower($q))));
$categoryShelves=lhc_shelves($categoryStore,'games',$games,$selectedCategory);require __DIR__.'/includes/learning_category_shelves.php'; ?>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="fw-bold mb-1">Liên kết hữu ích</h2><p class="text-muted mb-0">Truy cập nhanh các nền tảng phục vụ dạy và học.</p></div><?php if ($canLinkEdit): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#linkModal"><i class="bi bi-plus-circle"></i> Thêm liên kết</button><?php endif; ?></div>
<div class="row g-3"><?php if (!$data['links']): ?><div class="col-12"><div class="card p-5 text-center text-muted">Chưa có liên kết.</div></div><?php else: foreach ($data['links'] as $link): ?><div class="col-6 col-md-4 col-xl-3"><div class="card h-100"><a class="learning-link-card" <?=lc_link_attributes($link)?>><?=lc_link_body($link)?></a><?php if($canLinkEdit):?><div class="px-3 pb-2"><button type="button" class="btn btn-sm btn-outline-secondary w-100" data-learning-link-edit data-link="<?=e(base64_encode(json_encode($link,JSON_UNESCAPED_UNICODE)))?>">Sửa liên kết</button></div><?php endif;?><?php if($canLinkDelete):?><form method="post" class="px-3 pb-3" onsubmit="return confirm('Xóa liên kết này?')"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_link"><input type="hidden" name="id" value="<?= e($link['id']) ?>"><button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Xóa</button></form><?php endif;?></div></div><?php endforeach; endif; ?></div>
<?php if($canLinkEdit):?><div class="modal fade" id="linkModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post" id="learningLinkForm"><div class="modal-header"><h5 class="modal-title">Thêm liên kết</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_link"><input type="hidden" name="id"><div class="mb-3"><label class="form-label">Tên trang<input class="form-control" name="title" maxlength="200" required></label></div><div class="mb-3"><label class="form-label">Liên kết<input class="form-control" type="url" name="url" placeholder="https://…" required></label></div><div class="mb-3"><label class="form-label">Link ảnh đại diện<input class="form-control" type="url" name="image_url" maxlength="2048" placeholder="https://…/anh.jpg"></label><img id="learningLinkImagePreview" alt="Xem trước ảnh đại diện" hidden style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:8px"><div class="form-text">Không nhập ảnh sẽ dùng biểu tượng bên dưới.</div></div><div class="mb-3"><label class="form-label">Mô tả<textarea class="form-control" name="description" rows="3" maxlength="1000"></textarea></label></div><div class="mb-3"><label class="form-label">Cách mở<select class="form-select" name="open_mode"><option value="new_tab">Mở tab mới ngoài trang</option><option value="embed">Xem ngay trong trang</option></select></label><div class="form-text">Một số website không cho phép nhúng. Khi đó hãy chọn mở tab mới.</div></div><div><label class="form-label">Biểu tượng thay thế<input class="form-control" name="icon" value="bi-link-45deg" placeholder="bi-google, bi-book…"></label></div></div><div class="modal-footer"><button class="btn btn-primary">Lưu liên kết</button></div></form></div></div></div><?php endif; ?>
<?php endif; ?>
<div class="modal fade" id="learningPreviewModal" tabindex="-1" aria-labelledby="learningPreviewTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="learningPreviewTitle">Xem nội dung</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div><div class="modal-body p-0"><iframe id="learningPreviewFrame" title="Xem nội dung học liệu" style="display:block;width:100%;height:75vh;border:0" allow="fullscreen; autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div></div></div></div>
</main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var sourceKind=document.getElementById('sourceKind');
function editLearningItem(button){if(window.learningContentEditRow){try{window.learningContentEditRow(JSON.parse(decodeURIComponent(escape(atob(button.dataset.item||'')))));}catch(error){alert('Không đọc được dữ liệu cần sửa.');}}}

var itemForm=document.getElementById('itemForm');if(itemForm)itemForm.addEventListener('submit',function(event){if(sourceKind.value==='article'){var editor=window.tinymce&&tinymce.get('articleBody');if(!editor){event.preventDefault();alert('Bộ soạn thảo chưa tải xong. Vui lòng thử lại.');return;}editor.save();if(!editor.getContent({format:'text'}).trim()&&!/<img\b/i.test(editor.getContent())){event.preventDefault();alert('Vui lòng nhập nội dung bài viết.');return;}}var button=document.getElementById('saveButton');button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Đang tải lên…'});
</script>
<script id="learningContentConfig" type="application/json"><?=json_encode(['schema'=>lc_schema(($tab==='exams')?'exam':'resource'),'labels'=>lc_sources(),'thumbnailTypes'=>array_combine(array_keys(lc_schema(($tab==='exams')?'exam':'resource')),array_map(fn($kind)=>lt_presentation($kind),array_keys(lc_schema(($tab==='exams')?'exam':'resource')))),'extensions'=>array_combine(array_keys(lc_schema(($tab==='exams')?'exam':'resource')),array_map(fn($kind)=>lc_extensions($kind,'upload'),array_keys(lc_schema(($tab==='exams')?'exam':'resource'))))],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
<script src="<?=BASE_URL?>assets/learning-content.js?v=6"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.6/tinymce.min.js"></script>
<script src="<?= BASE_URL ?>assets/learning-article.js?v=20261116-1"></script>
<?php if ($tab === 'games'): require __DIR__ . '/includes/game_credit.php'; endif; ?></body></html>

