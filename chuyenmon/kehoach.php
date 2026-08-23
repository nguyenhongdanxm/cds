<?php
$page_title = 'Kế hoạch chuyên môn';
require_once 'includes/functions.php';
require_once dirname(__DIR__) . '/includes/push_notifications.php';
require_login();

$tabs = [
    'thongbao' => ['Thông báo', 'bi-megaphone'],
    'vanban' => ['Kế hoạch giáo dục', 'bi-file-earmark-pdf'],
    'chitieu' => ['Chỉ tiêu', 'bi-bullseye'],
];
$tab = $_GET['tab'] ?? 'vanban';
if (!isset($tabs[$tab])) $tab = 'vanban';
$page_title = $tabs[$tab][0];

if ($tab === 'vanban') {
    require __DIR__ . '/includes/education_plans.php';
    exit;
}

require_once 'includes/cm_docs.php';
$section = 'kh_' . $tab;
$teachers = get_teachers_sorted();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        try {
            $recordId = trim((string)($_POST['id'] ?? ''));
            $isNewRecord = $recordId === '';
            if ($isNewRecord) $recordId = 'cm_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
            $oldFile = trim((string)($_POST['file_path'] ?? ''));
            $file = cds_storage_handle_upload('file', $tab === 'thongbao' ? 'plans' : 'plans');
            if (isset($_FILES['file']) && (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && $file === '') {
                throw new RuntimeException('File chưa được tải lên Google Drive. Hãy kiểm tra cấu hình Drive và thư mục Kế hoạch và báo cáo.');
            }
            $hasDeadline = !empty($_POST['has_deadline']);
            $hasAssignees = !empty($_POST['has_assignees']);
            $assignees = [];
            if ($hasAssignees && !empty($_POST['assignees']) && is_array($_POST['assignees'])) {
                $assignees = array_values(array_filter(array_map('trim', $_POST['assignees'])));
            }
            $savedRecord = [
                'id' => $recordId,
                'section' => $section,
                'title' => trim($_POST['title'] ?? ''),
                'date' => trim($_POST['date'] ?? date('Y-m-d')),
                'has_deadline' => $hasDeadline,
                'due_date' => $hasDeadline ? trim($_POST['due_date'] ?? '') : '',
                'day_from' => $hasDeadline ? trim($_POST['day_from'] ?? '') : '',
                'day_to' => $hasDeadline ? trim($_POST['day_to'] ?? '') : '',
                'has_assignees' => $hasAssignees,
                'assignees' => $assignees,
                'completed' => !empty($_POST['completed']),
                'content' => trim($_POST['content'] ?? ''),
                'link' => trim($_POST['link'] ?? ''),
                'file_path' => $file !== '' ? $file : $oldFile,
                'by' => $_SESSION['cds_user']['name'] ?? 'admin',
            ];
            cm_doc_save($savedRecord);
            if ($tab === 'thongbao' && $isNewRecord && empty($savedRecord['completed'])) {
                $dashboardItem = $savedRecord + ['_dashboard_module'=>'chuyenmon'];
                $pushResult = cds_push_publish(
                    (string)$savedRecord['title'],
                    trim((string)$savedRecord['content']) !== '' ? mb_strimwidth(strip_tags((string)$savedRecord['content']), 0, 180, '…', 'UTF-8') : 'Có thông báo chuyên môn mới.',
                    '/chuyenmon/kehoach.php?tab=thongbao&notice=' . rawurlencode($recordId),
                    ['source_id'=>cds_push_dashboard_source_id($dashboardItem),'audience'=>['all'],'expires_at'=>(string)$savedRecord['due_date']]
                );
                if (empty($pushResult['saved'])) flash('Đã lưu thông báo chuyên môn nhưng chưa tạo được thông báo chuông.', 'warning');
                elseif ((int)($pushResult['devices'] ?? 0) < 1) flash('Đã lưu thông báo và file lên Google Drive; hiện chưa có thiết bị nào đăng ký nhận thông báo.', 'warning');
                else flash('Đã lưu thông báo và file lên Google Drive.');
            } else flash('Đã lưu.');
        } catch (Throwable $error) {
            flash($error->getMessage(), 'danger');
        }
        header('Location: ' . BASE_URL . 'kehoach.php?tab=' . urlencode($tab));
        exit;
    }
    if ($action === 'toggle_complete' && $tab === 'thongbao') {
        $id = trim($_POST['id'] ?? '');
        foreach (cm_docs_by_section($section) as $row) {
            if (($row['id'] ?? '') !== $id) continue;
            $row['completed'] = empty($row['completed']);
            $row['updated_at'] = date('c');
            cm_doc_save($row);
            flash(!empty($row['completed']) ? 'Đã đánh dấu thông báo hoàn thành.' : 'Đã mở lại thông báo.');
            break;
        }
        header('Location: ' . BASE_URL . 'kehoach.php?tab=thongbao');
        exit;
    }
    if ($action === 'delete') {
        cm_doc_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa.', 'warning');
        header('Location: ' . BASE_URL . 'kehoach.php?tab=' . urlencode($tab));
        exit;
    }
}

$items = cm_docs_by_section($section);
$articleView = null;
if ($tab === 'chitieu' && !empty($_GET['article'])) {
    foreach ($items as $candidate) if (($candidate['id'] ?? '') === (string)$_GET['article']) { $articleView = $candidate; break; }
}
$noticeView = null;
if ($tab === 'thongbao' && !empty($_GET['notice'])) {
    foreach ($items as $candidate) if (($candidate['id'] ?? '') === (string)$_GET['notice']) { $noticeView = $candidate; break; }
}
function cm_article_body($html) {
    $html = trim((string) $html);
    if ($html === '') return '<span class="text-muted">Bài viết chưa có nội dung.</span>';
    if ($html === strip_tags($html)) return nl2br(e($html));
    return strip_tags($html, '<p><br><div><strong><b><em><i><u><h2><h3><ul><ol><li><blockquote><a>');
}
require_once 'includes/header.php';
?>
<style>.container,.container-sm,.container-md,.container-lg,.container-xl,.container-xxl{width:100%!important;max-width:none!important}</style>
<h3 class="mb-3"><i class="bi <?= e($tabs[$tab][1]) ?>"></i> <?= e($tabs[$tab][0]) ?></h3>
<ul class="nav nav-pills gap-1 mb-4 flex-wrap"><?php foreach ($tabs as $k => $info): ?><li class="nav-item"><a class="nav-link <?= $tab===$k?'active':'' ?>" href="<?= BASE_URL ?>kehoach.php?tab=<?= urlencode($k) ?>"><i class="bi <?= e($info[1]) ?>"></i> <?= e($info[0]) ?></a></li><?php endforeach; ?></ul>
<?php if ($tab === 'chitieu'): ?>
<link href="<?= BASE_URL ?>../assets/article-editor.css?v=20260804-2" rel="stylesheet">
<?php if ($articleView): ?><a class="btn btn-outline-secondary mb-3" href="<?= BASE_URL ?>kehoach.php?tab=chitieu">Quay lại danh sách</a><article class="article-compose"><h1 class="h3 text-primary mb-3"><?= e($articleView['title']??'') ?></h1><div class="article-editor-area p-0"><?= cm_article_body($articleView['content']??'') ?></div></article><?php else: ?><div class="article-feed"><?php foreach ($items as $it): ?><article class="article-feed-item"><div class="article-feed-copy"><a href="<?= BASE_URL ?>kehoach.php?tab=chitieu&article=<?= urlencode($it['id']??'') ?>"><?= e($it['title']??'') ?></a><p><?= e(mb_strimwidth(strip_tags($it['content']??''),0,180,'…','UTF-8')) ?></p></div></article><?php endforeach; ?></div><?php endif; ?>
<?php else: ?>
<div class="row g-3"><div class="col-lg-4"><div class="card"><div class="card-header">Thêm / cập nhật — <?= e($tabs[$tab][0]) ?></div><div class="card-body"><form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>kehoach.php?tab=<?= urlencode($tab) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="doc_id"><input type="hidden" name="file_path" id="doc_file"><div class="mb-2"><label class="form-label small fw-semibold">Tiêu đề</label><input type="text" name="title" id="doc_title" class="form-control form-control-sm" required></div><div class="mb-2"><label class="form-label small fw-semibold">Ngày ban hành / sự kiện</label><input type="date" name="date" id="doc_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="has_deadline" value="1" id="chkDeadline"><label class="form-check-label small fw-semibold" for="chkDeadline">Có hạn thực hiện / hạn báo cáo</label></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="has_assignees" value="1" id="chkAssignees"><label class="form-check-label small fw-semibold" for="chkAssignees">Chỉ định người thực hiện</label></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="completed" value="1"><label class="form-check-label small fw-semibold">Đã hoàn thành (không hiện trên tổng quan)</label></div><div class="mb-2"><label class="form-label small fw-semibold">Nội dung / ghi chú</label><textarea name="content" id="doc_content" class="form-control" rows="4"></textarea></div><div class="mb-2"><label class="form-label small fw-semibold">Link (Drive, website...)</label><input type="url" name="link" id="doc_link" class="form-control" placeholder="https://..."></div><div class="mb-2"><label class="form-label small fw-semibold">Hoặc tải file lên Google Drive</label><input type="file" name="file" class="form-control"></div><button class="btn btn-primary w-100" type="submit">Lưu</button></form></div></div></div><div class="col-lg-8"><div class="card"><div class="card-header"><?= e($tabs[$tab][0]) ?> (<?= count($items) ?>)</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ngày</th><th>Hạn</th><th>Tiêu đề</th><th>Người TH</th><th>Trạng thái</th><th>Tài liệu</th></tr></thead><tbody><?php foreach ($items as $it): ?><tr><td><?= e($it['date']??'') ?></td><td><?= e($it['due_date']??'—') ?></td><td><strong><?= e($it['title']??'') ?></strong><div class="small text-muted"><?= e(mb_strimwidth(strip_tags($it['content']??''),0,90,'…','UTF-8')) ?></div></td><td><?= e(!empty($it['assignees'])?implode(', ',$it['assignees']):'—') ?></td><td><?= !empty($it['completed'])?'<span class="badge bg-success">Hoàn thành</span>':'<span class="badge bg-warning text-dark">Đang theo dõi</span>' ?></td><td><?php if(!empty($it['link'])): ?><a target="_blank" href="<?= e($it['link']) ?>">Link</a><?php endif; ?><?php if(!empty($it['file_path'])): ?> <a target="_blank" href="<?= e(cds_storage_file_url($it['file_path'])) ?>">File</a><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>