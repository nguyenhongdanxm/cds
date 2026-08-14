<?php
/* HOC_LIEU_BUILD: 2026-08-11.1 — marker để xác nhận cPanel đã nhận file mới. */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user() ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';
$canResourceView = $isAdmin || can_perm_level('hl.xem', 'view');
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

    if ($action === 'save_item') {
        $section = ($_POST['section'] ?? '') === 'exam' ? 'exam' : 'resource';
        $tab = $section === 'exam' ? 'exams' : 'resources';
        $canSectionEdit = $section === 'exam' ? $canExamEdit : $canResourceEdit;
        if (!$canSectionEdit) { http_response_code(403); exit('Bạn chưa có quyền nộp hoặc sửa nội dung tại trang này.'); }
        $title = trim((string)($_POST['title'] ?? ''));
        $grade = trim((string)($_POST['grade'] ?? ''));
        $source = trim((string)($_POST['source'] ?? ''));
        $kind = trim((string)($_POST['kind'] ?? ''));
        $sourceKind = in_array($_POST['source_kind'] ?? '', ['upload', 'link', 'html'], true) ? (string)$_POST['source_kind'] : 'link';
        $url = lh_url((string)($_POST['url'] ?? ''));
        $id = trim((string)($_POST['id'] ?? ''));
        if ($title === '' || $grade === '' || $kind === '') {
            flash('Vui lòng nhập đủ tên, loại và khối.', 'danger'); lh_redirect($tab);
        }

        $filePath = '';
        $upload = $_FILES['file'] ?? null;
        if ($sourceKind !== 'link' && $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
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
            if ($sourceKind === 'html') $mime = 'text/html';
            $driveType = $section === 'exam' ? 'exam_bank' : 'learning_resources';
            $result = cds_drive_upload_bytes($bytes, $name, $mime, $driveType, ['title' => $title, 'grade' => $grade, 'source_action' => 'page:/hoclieu.php']);
            if (empty($result['ok'])) {
                flash($result['message'] ?? 'Không tải được tệp lên Google Drive.', 'danger'); lh_redirect($tab);
            }
            $filePath = (string)$result['path'];
        }
        if ($sourceKind === 'link' && $url === '') {
            flash('Vui lòng nhập liên kết bắt đầu bằng http:// hoặc https://.', 'danger'); lh_redirect($tab);
        }

        $index = lh_find($data['items'], $id);
        if ($index >= 0) {
            $old = $data['items'][$index];
            if (!$canApprove && ($old['author_id'] ?? '') !== $userId) { http_response_code(403); exit('Chỉ được sửa nội dung do mình nộp.'); }
            $data['items'][$index] = array_merge($old, [
                'section' => $section, 'title' => $title, 'kind' => $kind, 'grade' => $grade,
                'source' => $source, 'source_kind' => $sourceKind,
                'url' => $sourceKind === 'link' ? $url : '',
                'file_path' => $filePath !== '' ? $filePath : (string)($old['file_path'] ?? ''),
                'updated_at' => date('c'),
            ]);
        } else {
            if ($sourceKind !== 'link' && $filePath === '') {
                flash('Vui lòng chọn tệp cần tải lên.', 'danger'); lh_redirect($tab);
            }
            $data['items'][] = [
                'id' => 'hl_' . bin2hex(random_bytes(8)), 'section' => $section, 'title' => $title,
                'kind' => $kind, 'grade' => $grade, 'source' => $source, 'source_kind' => $sourceKind,
                'url' => $sourceKind === 'link' ? $url : '', 'file_path' => $filePath,
                'author_id' => $userId, 'author' => $userName, 'approved' => $canApprove,
                'approved_at' => $canApprove ? date('c') : '', 'approved_by' => $canApprove ? $userName : '',
                'featured' => false, 'created_at' => date('c'), 'updated_at' => date('c'),
            ];
        }
        save_json($dataFile, $data);
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
        save_json($dataFile, $data); lh_redirect($tab);
    }

    if ($action === 'save_link') {
        if (!$canLinkEdit) { http_response_code(403); exit('Bạn chưa có quyền quản lý liên kết.'); }
        $id = trim((string)($_POST['id'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? 'bi-link-45deg'));
        $url = lh_url((string)($_POST['url'] ?? ''));
        if ($title === '' || $url === '') { flash('Tên trang hoặc liên kết không hợp lệ.', 'danger'); lh_redirect('links'); }
        $row = ['id' => $id !== '' ? $id : 'lk_' . bin2hex(random_bytes(6)), 'title' => $title, 'icon' => $icon ?: 'bi-link-45deg', 'url' => $url, 'updated_at' => date('c')];
        $index = lh_find($data['links'], $id);
        if ($index >= 0) $data['links'][$index] = $row; else $data['links'][] = $row;
        save_json($dataFile, $data); flash('Đã lưu liên kết.', 'success'); lh_redirect('links');
    }
    if ($action === 'delete_link') {
        if (!$canLinkDelete) { http_response_code(403); exit('Bạn chưa có quyền xóa liên kết.'); }
        $index = lh_find($data['links'], trim((string)($_POST['id'] ?? '')));
        if ($index >= 0) array_splice($data['links'], $index, 1);
        save_json($dataFile, $data); flash('Đã xóa liên kết.', 'warning'); lh_redirect('links');
    }
}

$tab = in_array($_GET['tab'] ?? '', ['dashboard', 'resources', 'exams', 'links'], true) ? (string)$_GET['tab'] : 'dashboard';
if ($tab === 'resources' && !$canResourceView) $tab = 'dashboard';
if ($tab === 'exams' && !$canExamView) $tab = 'dashboard';
if ($tab === 'links' && !$canLinkView) $tab = 'dashboard';
$q = trim((string)($_GET['q'] ?? ''));
$gradeFilter = trim((string)($_GET['grade'] ?? ''));
$visibleItems = array_values(array_filter($data['items'], fn($row) => !empty($row['approved']) || $isAdmin || ($row['author_id'] ?? '') === $userId));
$approved = array_values(array_filter($data['items'], fn($row) => !empty($row['approved'])));
$resourceCount = count(array_filter($approved, fn($row) => ($row['section'] ?? '') === 'resource'));
$examCount = count(array_filter($approved, fn($row) => ($row['section'] ?? '') === 'exam'));
$pendingCount = count(array_filter($data['items'], fn($row) => empty($row['approved'])));
$featured = array_slice(array_values(array_filter($approved, fn($row) => !empty($row['featured']))), 0, 6);

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

function lh_item_url(array $row): string {
    if (($row['source_kind'] ?? '') === 'link') return (string)($row['url'] ?? '#');
    $path = (string)($row['file_path'] ?? '');
    if (($row['source_kind'] ?? '') !== 'html' && str_starts_with($path, 'gdrive:')) {
        $fileId = trim(substr($path, 7));
        if ($fileId !== '') return 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/view?usp=drive_link';
    }
    return BASE_URL . 'hoclieu_file.php?id=' . urlencode((string)($row['id'] ?? ''));
}
function lh_icon(string $kind): string {
    $map = ['Văn bản'=>'bi-file-earmark-text','Ứng dụng'=>'bi-window','Video'=>'bi-play-circle','Trình chiếu'=>'bi-easel2','Hình ảnh'=>'bi-image','Đề kiểm tra'=>'bi-ui-checks','Thi thử'=>'bi-stopwatch','Kỳ thi'=>'bi-mortarboard'];
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
</head><body>
<style>.stat{padding-right:3.5rem!important}.stat>i{right:12px;bottom:12px;font-size:2rem;opacity:.16;pointer-events:none}.stat strong{display:block;line-height:1.1}.stat>span{display:block;line-height:1.35}</style>
<?php require_once __DIR__.'/includes/module_switcher.php'; ?>
<header class="top shadow-sm"><div class="container py-3">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
<a class="brand text-white text-decoration-none" href="<?= BASE_URL ?>hoclieu.php"><i class="bi bi-laptop me-2"></i>HỌC LIỆU VÀ THI</a>
<ul class="nav nav-pills gap-1">
<li><a class="nav-link <?= $tab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="bi bi-grid me-1"></i>Tổng quan</a></li>
<?php if($canResourceView):?><li><a class="nav-link <?= $tab==='resources'?'active':'' ?>" href="?tab=resources"><i class="bi bi-collection me-1"></i>Học liệu</a></li><?php endif;?>
<?php if($canExamView):?><li><a class="nav-link <?= $tab==='exams'?'active':'' ?>" href="?tab=exams"><i class="bi bi-ui-checks me-1"></i>Kiểm tra và thi</a></li><?php endif;?>
<?php if($canLinkView):?><li><a class="nav-link <?= $tab==='links'?'active':'' ?>" href="?tab=links"><i class="bi bi-link-45deg me-1"></i>Liên kết</a></li><?php endif;?>
</ul>
<div class="d-flex gap-2"><a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>"><i class="bi bi-diagram-3"></i> Hệ sinh thái</a><a class="btn btn-sm btn-light" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right"></i></a></div>
</div></div></header>
<main class="container py-4"><?php show_flash(); ?>

<?php if ($tab === 'dashboard'): ?>
<div class="mb-4"><h2 class="fw-bold mb-1">Kho tri thức số nhà trường</h2><p class="text-muted">Học liệu, ngân hàng đề và các kỳ thi dùng chung cho giáo viên.</p></div>
<div class="row g-3 mb-4">
<?php foreach ([[$resourceCount,'Học liệu đã duyệt','bi-collection'],[$examCount,'Đề và kỳ thi','bi-ui-checks'],[count($data['links']),'Liên kết hữu ích','bi-link-45deg'],[$pendingCount,'Đang chờ duyệt','bi-hourglass-split']] as $stat): ?>
<div class="col-6 col-lg-3"><div class="card stat p-3"><strong><?= $stat[0] ?></strong><span class="text-muted"><?= e($stat[1]) ?></span><i class="bi <?= $stat[2] ?>"></i></div></div>
<?php endforeach; ?></div>
<div class="d-flex justify-content-between align-items-center mb-3"><h4 class="fw-bold mb-0">Học liệu nổi bật</h4><?php if ($canApprove): ?><span class="small text-muted">Người duyệt có thể đánh dấu nội dung nổi bật</span><?php endif; ?></div>
<div class="row g-3 mb-4">
<?php if (!$featured): ?><div class="col-12"><div class="card p-5 text-center text-muted"><i class="bi bi-stars fs-1"></i><p class="mb-0 mt-2">Chưa có học liệu nổi bật.</p></div></div>
<?php else: foreach ($featured as $row): ?><div class="col-md-6 col-lg-4"><a class="card resource-card p-3 text-decoration-none text-dark" target="_blank" rel="noopener" href="<?= e(lh_item_url($row)) ?>"><div class="d-flex gap-3"><span class="resource-icon"><i class="bi <?= lh_icon((string)$row['kind']) ?>"></i></span><div><h6 class="fw-bold mb-1"><?= e($row['title']) ?></h6><div class="small text-muted"><?= e($row['kind']) ?> · Khối <?= e($row['grade']) ?></div><div class="small mt-2"><?= e($row['author']) ?></div></div></div></a></div><?php endforeach; endif; ?>
</div>
<h4 class="fw-bold mb-3">Liên kết nhanh</h4><div class="row g-3"><?php foreach (array_slice($data['links'],0,6) as $link): ?><div class="col-6 col-lg-3"><div class="card h-100"><a class="link-tile" target="_blank" rel="noopener" href="<?= e($link['url']) ?>"><i class="bi <?= e($link['icon']) ?>"></i><strong><?= e($link['title']) ?></strong></a></div></div><?php endforeach; ?></div>

<?php elseif ($tab === 'resources' || $tab === 'exams'): $isExam=$tab==='exams';$canCurrentEdit=$isExam?$canExamEdit:$canResourceEdit;$canCurrentDelete=$isExam?$canExamDelete:$canResourceDelete; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h2 class="fw-bold mb-1"><?= $isExam?'Kiểm tra và thi':'Học liệu' ?></h2><p class="text-muted mb-0"><?= $isExam?'Ngân hàng đề, thi thử và các kỳ thi':'Học liệu số dùng chung đã được quản trị phê duyệt' ?></p></div><?php if($canCurrentEdit):?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal"><i class="bi bi-plus-circle me-1"></i> Nhập <?= $isExam?'đề / kỳ thi':'học liệu' ?></button><?php endif;?></div>
<form class="card p-3 mb-3"><input type="hidden" name="tab" value="<?= e($tab) ?>"><div class="row g-2"><div class="col-md-7"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên, loại, nguồn hoặc giáo viên"></div><div class="col-md-3"><select class="form-select" name="grade"><option value="">Tất cả khối</option><?php foreach (['6','7','8','9','10','11','12','Toàn trường'] as $g): ?><option <?= $gradeFilter===$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Tìm</button></div></div></form>
<div class="row g-3"><?php if (!$list): ?><div class="col-12"><div class="card p-5 text-center text-muted">Chưa có nội dung phù hợp.</div></div><?php else: foreach ($list as $row): ?>
<div class="col-md-6 col-xl-4"><div class="card resource-card p-3"><div class="d-flex gap-3"><span class="resource-icon"><i class="bi <?= lh_icon((string)$row['kind']) ?>"></i></span><div class="flex-grow-1 min-w-0"><div class="d-flex justify-content-between gap-2"><h5 class="fw-bold mb-1"><?= e($row['title']) ?></h5><span class="status <?= !empty($row['approved'])?'approved':'pending' ?>"><?= !empty($row['approved'])?'Đã duyệt':'Chờ duyệt' ?></span></div><div class="small text-muted"><?= e($row['kind']) ?> · Khối <?= e($row['grade']) ?></div><div class="small mt-2"><i class="bi bi-person"></i> <?= e($row['author']) ?><?php if (!empty($row['source'])): ?> · <?= e($row['source']) ?><?php endif; ?></div></div></div>
<div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= e(lh_item_url($row)) ?>"><i class="bi bi-eye"></i> Xem</a>
<?php if (($canCurrentEdit && (($row['author_id'] ?? '') === $userId || $canApprove)) || $canApprove): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-item="<?= e(base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE))) ?>" onclick="editLearningItem(this)" title="Sửa"><i class="bi bi-pencil"></i></button><?php endif; ?>
<?php if ($canApprove && empty($row['approved'])): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="approve_item"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Duyệt</button></form><?php endif; ?>
<?php if ($canApprove): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="feature_item"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><button class="btn btn-sm btn-outline-warning" title="Nổi bật"><i class="bi bi-star<?= !empty($row['featured'])?'-fill':'' ?>"></i></button></form><?php endif; ?>
<?php if ($canCurrentDelete || $canApprove): ?><form method="post" onsubmit="return confirm('Xóa nội dung này?')"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></div></div></div>
<?php endforeach; endif; ?></div>

<div class="modal fade" id="itemModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data" id="itemForm"><div class="modal-header"><h5 class="modal-title">Nhập <?= $isExam?'đề, bài kiểm tra hoặc kỳ thi':'học liệu' ?></h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_item"><input type="hidden" name="id" id="itemId"><input type="hidden" name="section" value="<?= $isExam?'exam':'resource' ?>">
<div class="row g-3"><div class="col-md-8"><label class="form-label fw-semibold">Tên <?= $isExam?'đề / kỳ thi':'học liệu' ?></label><input class="form-control" name="title" required></div><div class="col-md-4"><label class="form-label fw-semibold">Loại</label><select class="form-select" name="kind" required><option value="">Chọn loại</option><?php foreach ($isExam?['Đề kiểm tra','Thi thử','Kỳ thi']:['Văn bản','Ứng dụng','Video','Trình chiếu','Hình ảnh'] as $kind): ?><option><?= e($kind) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Khối</label><select class="form-select" name="grade" required><option value="">Chọn khối</option><?php foreach (['6','7','8','9','10','11','12','Toàn trường'] as $g): ?><option><?= $g ?></option><?php endforeach; ?></select></div><div class="col-md-8"><label class="form-label fw-semibold">Nguồn</label><input class="form-control" name="source" placeholder="Bộ GDĐT, giáo viên biên soạn…"></div>
<div class="col-12"><label class="form-label fw-semibold">Hình thức nội dung</label><select class="form-select" name="source_kind" id="sourceKind"><option value="upload">Tải tệp lên Google Drive</option><option value="link">Liên kết website</option><option value="html">Tệp HTML chạy tương tác</option></select></div>
<div class="col-12" id="fileField"><label class="form-label fw-semibold">Chọn tệp</label><input class="form-control" type="file" name="file" id="itemFile"><div class="form-text">Tối đa 50 MB. Tệp HTML chỉ nhận .html hoặc .htm.</div></div><div class="col-12 d-none" id="urlField"><label class="form-label fw-semibold">Đường liên kết</label><input class="form-control" type="url" name="url" placeholder="https://..."></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" id="saveButton"><i class="bi bi-cloud-arrow-up"></i> Tải lên và gửi duyệt</button></div></form></div></div></div>

<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="fw-bold mb-1">Liên kết hữu ích</h2><p class="text-muted mb-0">Truy cập nhanh các nền tảng phục vụ dạy và học.</p></div><?php if ($canLinkEdit): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#linkModal"><i class="bi bi-plus-circle"></i> Thêm liên kết</button><?php endif; ?></div>
<div class="row g-3"><?php if (!$data['links']): ?><div class="col-12"><div class="card p-5 text-center text-muted">Chưa có liên kết.</div></div><?php else: foreach ($data['links'] as $link): ?><div class="col-6 col-md-4 col-xl-3"><div class="card h-100"><a class="link-tile" target="_blank" rel="noopener" href="<?= e($link['url']) ?>"><i class="bi <?= e($link['icon']) ?>"></i><strong class="flex-grow-1"><?= e($link['title']) ?></strong></a><?php if($canLinkDelete):?><form method="post" class="px-3 pb-3" onsubmit="return confirm('Xóa liên kết này?')"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_link"><input type="hidden" name="id" value="<?= e($link['id']) ?>"><button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Xóa</button></form><?php endif;?></div></div><?php endforeach; endif; ?></div>
<?php if($canLinkEdit):?><div class="modal fade" id="linkModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Thêm liên kết</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_link"><div class="mb-3"><label class="form-label fw-semibold">Tên trang</label><input class="form-control" name="title" required></div><div class="mb-3"><label class="form-label fw-semibold">Biểu tượng Bootstrap Icons</label><input class="form-control" name="icon" value="bi-link-45deg"><div class="form-text">Ví dụ: bi-google, bi-youtube, bi-book</div></div><div><label class="form-label fw-semibold">Liên kết</label><input class="form-control" type="url" name="url" placeholder="https://..." required></div></div><div class="modal-footer"><button class="btn btn-primary">Lưu liên kết</button></div></form></div></div></div><?php endif; ?>
<?php endif; ?>
</main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var sourceKind=document.getElementById('sourceKind');if(sourceKind)sourceKind.addEventListener('change',function(){var link=this.value==='link';document.getElementById('urlField').classList.toggle('d-none',!link);document.getElementById('fileField').classList.toggle('d-none',link);document.getElementById('itemFile').accept=this.value==='html'?'.html,.htm':''});
function editLearningItem(button){try{var row=JSON.parse(decodeURIComponent(escape(atob(button.dataset.item||'')))),form=document.getElementById('itemForm');form.querySelector('#itemId').value=row.id||'';form.querySelector('[name="title"]').value=row.title||'';form.querySelector('[name="kind"]').value=row.kind||'';form.querySelector('[name="grade"]').value=row.grade||'';form.querySelector('[name="source"]').value=row.source||'';form.querySelector('[name="source_kind"]').value=row.source_kind||'link';form.querySelector('[name="url"]').value=row.url||'';form.querySelector('[name="source_kind"]').dispatchEvent(new Event('change'));form.querySelector('.modal-title').textContent='Sửa nội dung';document.getElementById('saveButton').innerHTML='<i class="bi bi-save"></i> Lưu thay đổi';bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).show()}catch(error){alert('Không đọc được dữ liệu cần sửa.')}}
var itemForm=document.getElementById('itemForm');if(itemForm)itemForm.addEventListener('submit',function(){var button=document.getElementById('saveButton');button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Đang tải lên…'});
</script></body></html>
