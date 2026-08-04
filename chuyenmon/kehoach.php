<?php
$page_title = 'Kế hoạch chuyên môn';
require_once 'includes/functions.php';
require_once 'includes/cm_docs.php';
require_login();

$tabs = [
    'thongbao' => ['Thông báo chuyên môn', 'bi-megaphone'],
    'vanban' => ['Văn bản kế hoạch', 'bi-file-earmark-pdf'],
    'chitieu' => ['Chỉ tiêu', 'bi-bullseye'],
];
$tab = $_GET['tab'] ?? 'vanban';
if (!isset($tabs[$tab])) $tab = 'vanban';
$section = 'kh_' . $tab;
$teachers = get_teachers_sorted();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $file = cm_handle_upload('file');
        $oldFile = trim($_POST['file_path'] ?? '');
        $hasDeadline = !empty($_POST['has_deadline']);
        $hasAssignees = !empty($_POST['has_assignees']);
        $assignees = [];
        if ($hasAssignees && !empty($_POST['assignees']) && is_array($_POST['assignees'])) {
            $assignees = array_values(array_filter(array_map('trim', $_POST['assignees'])));
        }
        cm_doc_save([
            'id' => trim($_POST['id'] ?? ''),
            'section' => $section,
            'title' => trim($_POST['title'] ?? ''),
            'date' => trim($_POST['date'] ?? date('Y-m-d')),
            'has_deadline' => $hasDeadline,
            'due_date' => $hasDeadline ? trim($_POST['due_date'] ?? '') : '',
            'day_from' => $hasDeadline ? trim($_POST['day_from'] ?? '') : '',
            'day_to' => $hasDeadline ? trim($_POST['day_to'] ?? '') : '',
            'has_assignees' => $hasAssignees,
            'assignees' => $assignees,
            'content' => trim($_POST['content'] ?? ''),
            'link' => trim($_POST['link'] ?? ''),
            'file_path' => $file !== '' ? $file : $oldFile,
            'by' => $_SESSION['cds_user']['name'] ?? 'admin',
        ]);
        flash('Đã lưu.');
        header('Location: ' . BASE_URL . 'kehoach.php?tab=' . urlencode($tab));
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
function cm_article_body($html) {
    $html = trim((string) $html);
    if ($html === '') {
        return '<span class="text-muted">Bài viết chưa có nội dung.</span>';
    }
    if ($html === strip_tags($html)) {
        return nl2br(e($html));
    }
    return strip_tags($html, '<p><br><div><strong><b><em><i><u><h2><h3><ul><ol><li><blockquote><a>');
}
require_once 'includes/header.php';
?>
<style>
.container,
.container-sm,
.container-md,
.container-lg,
.container-xl,
.container-xxl {
    width: 100% !important;
    max-width: none !important;
}
</style>

<h3 class="mb-3"><i class="bi bi-calendar2-week"></i> Kế hoạch chuyên môn</h3>

<ul class="nav nav-pills gap-1 mb-4 flex-wrap">
  <?php foreach ($tabs as $k => $info): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$k?'active':'' ?>" href="<?= BASE_URL ?>kehoach.php?tab=<?= urlencode($k) ?>">
      <i class="bi <?= e($info[1]) ?>"></i> <?= e($info[0]) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'chitieu'): ?>
<link href="<?= BASE_URL ?>../assets/article-editor.css?v=20260804-2" rel="stylesheet">
<?php if ($articleView): ?>
<a class="btn btn-outline-secondary mb-3" href="<?= BASE_URL ?>kehoach.php?tab=chitieu"><i class="bi bi-arrow-left"></i> Quay lại danh sách</a>
<article class="article-compose"><h1 class="h3 text-primary mb-3"><?= e($articleView['title']??'') ?></h1><div class="article-editor-area p-0"><?= cm_article_body($articleView['content']??'') ?></div><div class="mt-4"><?php if(!empty($articleView['link'])): ?><a class="btn btn-outline-primary me-2" target="_blank" rel="noopener" href="<?= e($articleView['link']) ?>"><i class="bi bi-link-45deg"></i> Mở văn bản liên quan</a><?php endif; ?><?php if(!empty($articleView['file_path'])): ?><a class="btn btn-outline-success" target="_blank" rel="noopener" href="<?= e(cm_file_url($articleView['file_path'])) ?>"><i class="bi bi-file-earmark-arrow-down"></i> File đính kèm</a><?php endif; ?></div></article>
<?php else: ?>
<div class="article-feed">
<?php foreach ($items as $it): ?>
<article class="article-feed-item"><div class="article-feed-copy"><a href="<?= BASE_URL ?>kehoach.php?tab=chitieu&article=<?= urlencode($it['id']??'') ?>"><?= e($it['title']??'') ?></a><p><?= e(mb_strimwidth(strip_tags($it['content']??''),0,180,'…','UTF-8')) ?></p></div><div class="article-feed-actions"><button class="btn btn-sm btn-outline-primary" type="button" title="Sửa" data-id="<?= e($it['id']??'') ?>" data-title="<?= e($it['title']??'') ?>" data-content="<?= e(base64_encode($it['content']??'')) ?>" data-link="<?= e($it['link']??'') ?>" data-file="<?= e($it['file_path']??'') ?>" onclick="editArticle(this)"><i class="bi bi-pencil"></i></button><form method="post" action="<?= BASE_URL ?>kehoach.php?tab=chitieu" onsubmit="return confirm('Xóa bài viết này?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($it['id']??'') ?>"><button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash"></i></button></form></div></article>
<?php endforeach;if(!$items): ?><div class="alert alert-light border text-muted">Chưa có bài viết.</div><?php endif; ?>
</div>
<details class="article-manage" id="cmArticleManager"><summary><i class="bi bi-pencil-square"></i> Thêm bài viết</summary><form method="post" enctype="multipart/form-data" class="article-compose" action="<?= BASE_URL ?>kehoach.php?tab=chitieu"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="article_id"><input type="hidden" name="file_path" id="article_file"><input type="hidden" name="date" value="<?= date('Y-m-d') ?>"><div class="mb-3"><label class="form-label fw-semibold">Tiêu đề bài viết</label><input class="form-control" name="title" id="article_title" required></div><div class="mb-3"><label class="form-label fw-semibold">Nội dung</label><textarea hidden name="content" id="article_content"></textarea><div class="article-editor" id="cmArticleEditor" data-article-editor data-hidden="article_content"><div class="article-toolbar"><select data-format><option value="">Đoạn văn</option><option value="h2">Tiêu đề lớn</option><option value="h3">Tiêu đề nhỏ</option><option value="p">Đoạn văn</option></select><button type="button" data-cmd="bold"><i class="bi bi-type-bold"></i></button><button type="button" data-cmd="italic"><i class="bi bi-type-italic"></i></button><button type="button" data-cmd="underline"><i class="bi bi-type-underline"></i></button><button type="button" data-cmd="insertUnorderedList"><i class="bi bi-list-ul"></i></button><button type="button" data-cmd="insertOrderedList"><i class="bi bi-list-ol"></i></button><button type="button" data-cmd="justifyLeft"><i class="bi bi-text-left"></i></button><button type="button" data-cmd="justifyCenter"><i class="bi bi-text-center"></i></button><button type="button" data-cmd="justifyRight"><i class="bi bi-text-right"></i></button><button type="button" data-cmd="createLink"><i class="bi bi-link-45deg"></i></button><button type="button" data-cmd="removeFormat"><i class="bi bi-eraser"></i></button></div><div class="article-editor-area" contenteditable="true" data-editor-area data-placeholder="Soạn nội dung bài viết tại đây..."></div></div></div><div class="mb-3"><label class="form-label fw-semibold">Link văn bản liên quan</label><input class="form-control" type="url" name="link" id="article_link" placeholder="https://..."></div><div class="mb-3"><label class="form-label fw-semibold">File đính kèm</label><input class="form-control" type="file" name="file"></div><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> Lưu bài viết</button> <button class="btn btn-outline-secondary" type="button" onclick="resetArticle()">Làm mới</button></form></details>
<script src="<?= BASE_URL ?>../assets/article-editor.js?v=20260804"></script><script>
function decodeArticle(value){try{var bytes=Uint8Array.from(atob(value||''),function(c){return c.charCodeAt(0)});return new TextDecoder().decode(bytes)}catch(e){return ''}}
function editArticle(button){article_id.value=button.dataset.id||'';article_title.value=button.dataset.title||'';article_link.value=button.dataset.link||'';article_file.value=button.dataset.file||'';setArticleEditorContent('cmArticleEditor',decodeArticle(button.dataset.content));cmArticleManager.open=true;cmArticleManager.scrollIntoView({behavior:'smooth'})}
function resetArticle(){article_id.value='';article_title.value='';article_link.value='';article_file.value='';setArticleEditorContent('cmArticleEditor','')}
</script>
<?php endif; ?>
<?php else: ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card"><div class="card-header">Thêm / cập nhật — <?= e($tabs[$tab][0]) ?></div><div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>kehoach.php?tab=<?= urlencode($tab) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="doc_id" value="">
        <input type="hidden" name="file_path" id="doc_file" value="">
        <div class="mb-2">
          <label class="form-label small fw-semibold">Tiêu đề</label>
          <input type="text" name="title" id="doc_title" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Ngày ban hành / sự kiện</label>
          <input type="date" name="date" id="doc_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="has_deadline" value="1" id="chkDeadline" onchange="toggleDeadline()">
          <label class="form-check-label small fw-semibold" for="chkDeadline">Có hạn thực hiện / hạn báo cáo</label>
        </div>
        <div id="boxDeadline" class="border rounded p-2 mb-2 bg-light" style="display:none">
          <div class="mb-2">
            <label class="form-label small">Hạn (ngày cụ thể)</label>
            <input type="date" name="due_date" id="doc_due" class="form-control form-control-sm">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small">Từ ngày (hàng tháng)</label>
              <input type="number" name="day_from" id="doc_from" class="form-control form-control-sm" min="1" max="31" placeholder="22">
            </div>
            <div class="col-6">
              <label class="form-label small">Đến ngày</label>
              <input type="number" name="day_to" id="doc_to" class="form-control form-control-sm" min="1" max="31" placeholder="25">
            </div>
          </div>
          <div class="form-text">Chọn ngày cụ thể <em>hoặc</em> khung ngày lặp hàng tháng.</div>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="has_assignees" value="1" id="chkAssign" onchange="toggleAssign()">
          <label class="form-check-label small fw-semibold" for="chkAssign">Chỉ định người thực hiện</label>
        </div>
        <div id="boxAssign" class="border rounded p-2 mb-2 bg-light" style="display:none">
          <label class="form-label small">Chọn GV (giữ Ctrl/Cmd để chọn nhiều)</label>
          <select name="assignees[]" id="doc_assignees" class="form-select form-select-sm" multiple size="8">
            <?php foreach ($teachers as $t): ?>
            <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Danh sách lấy từ PCCM → Giáo viên.</div>
        </div>

        <div class="mb-2">
          <label class="form-label small fw-semibold">Nội dung / ghi chú</label>
          <textarea name="content" id="doc_content" class="form-control form-control-sm" rows="3"></textarea>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Link (Drive, website…)</label>
          <input type="url" name="link" id="doc_link" class="form-control form-control-sm" placeholder="https://…">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Hoặc tải file lên</label>
          <input type="file" name="file" class="form-control form-control-sm">
        </div>
        <button class="btn btn-primary btn-sm w-100" type="submit">Lưu</button>
        <button class="btn btn-outline-secondary btn-sm w-100 mt-1" type="button" onclick="resetForm()">Làm mới</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header"><?= e($tabs[$tab][0]) ?> (<?= count($items) ?>)</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Ngày</th><th>Hạn</th><th>Tiêu đề</th><th>Người TH</th><th>Tài liệu</th><th></th></tr></thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="6" class="text-muted text-center py-4">Chưa có mục nào.</td></tr>
        <?php else: foreach ($items as $it):
          $dl = !empty($it['has_deadline']) || !empty($it['due_date']) || !empty($it['day_from'])
            ? cm_resolve_deadline($it) : null;
          $asg = $it['assignees'] ?? [];
          if (!is_array($asg)) $asg = $asg ? [$asg] : [];
        ?>
          <tr>
            <td class="small text-nowrap"><?= e($it['date'] ?? '') ?></td>
            <td class="small">
              <?php if ($dl): ?>
                <?= e(date('d/m/Y', strtotime($dl['due_date']))) ?>
                <?php if (!empty($dl['window'])): ?><div class="text-muted"><?= e($dl['window']) ?></div><?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <?php if ($tab === 'chitieu'): ?>
                <a class="fw-bold text-decoration-none" href="<?= BASE_URL ?>kehoach.php?tab=chitieu&article=<?= urlencode($it['id'] ?? '') ?>"><i class="bi bi-file-text me-1"></i><?= e($it['title'] ?? '') ?></a>
              <?php else: ?><strong><?= e($it['title'] ?? '') ?></strong><?php endif; ?>
              <?php if (!empty($it['content'])): ?><div class="small text-muted"><?= e(mb_strimwidth($it['content'],0,80,'…','UTF-8')) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= $asg ? e(implode(', ', $asg)) : '—' ?></td>
            <td class="small">
              <?php if (!empty($it['link'])): ?><a href="<?= e($it['link']) ?>" target="_blank">Link</a><?php endif; ?>
              <?php if (!empty($it['file_path'])): ?><?= !empty($it['link'])?' · ':'' ?><a href="<?= e(cm_file_url($it['file_path'])) ?>" target="_blank">File</a><?php endif; ?>
              <?php if (empty($it['link']) && empty($it['file_path'])): ?>—<?php endif; ?>
            </td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-success" onclick='viewDoc(<?= json_encode($it, JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-eye"></i></button>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick='editDoc(<?= json_encode($it, JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
              <form method="post" class="d-inline" action="<?= BASE_URL ?>kehoach.php?tab=<?= urlencode($tab) ?>" onsubmit="return confirm('Xóa?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($it['id']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="viewTitle">Xem</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="small text-muted mb-2" id="viewMeta"></div>
    <div id="viewAssignees" class="mb-2 small"></div>
    <div id="viewContent" style="white-space:pre-wrap"></div>
    <div class="mt-3" id="viewLinks"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
</div></div></div>

<script>
function toggleDeadline(){
  document.getElementById('boxDeadline').style.display = document.getElementById('chkDeadline').checked ? 'block' : 'none';
}
function toggleAssign(){
  document.getElementById('boxAssign').style.display = document.getElementById('chkAssign').checked ? 'block' : 'none';
}
function resetForm(){
  ['doc_id','doc_file','doc_title','doc_content','doc_link','doc_due','doc_from','doc_to'].forEach(function(id){var el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('doc_date').value='<?= date('Y-m-d') ?>';
  document.getElementById('chkDeadline').checked=false;
  document.getElementById('chkAssign').checked=false;
  toggleDeadline(); toggleAssign();
  var sel=document.getElementById('doc_assignees');
  if(sel) Array.from(sel.options).forEach(function(o){o.selected=false;});
}
function editDoc(it){
  document.getElementById('doc_id').value=it.id||'';
  document.getElementById('doc_file').value=it.file_path||'';
  document.getElementById('doc_title').value=it.title||'';
  document.getElementById('doc_date').value=it.date||'';
  document.getElementById('doc_content').value=it.content||'';
  document.getElementById('doc_link').value=it.link||'';
  var hasDl = !!(it.has_deadline || it.due_date || it.day_from);
  document.getElementById('chkDeadline').checked=hasDl;
  document.getElementById('doc_due').value=it.due_date||'';
  document.getElementById('doc_from').value=it.day_from||'';
  document.getElementById('doc_to').value=it.day_to||'';
  toggleDeadline();
  var asg = it.assignees || [];
  if(typeof asg==='string') asg=asg?[asg]:[];
  var hasAs = !!(it.has_assignees || (asg && asg.length));
  document.getElementById('chkAssign').checked=hasAs;
  toggleAssign();
  var sel=document.getElementById('doc_assignees');
  if(sel) Array.from(sel.options).forEach(function(o){ o.selected = asg.indexOf(o.value)>=0; });
  window.scrollTo({top:0,behavior:'smooth'});
}
function viewDoc(it){
  document.getElementById('viewTitle').textContent=it.title||'Xem';
  var meta=(it.date||'')+(it.due_date?' · Hạn '+it.due_date:'')+(it.day_from?' · Kỳ '+it.day_from+'-'+it.day_to+'/tháng':'');
  document.getElementById('viewMeta').textContent=meta;
  var asg=it.assignees||[];
  document.getElementById('viewAssignees').innerHTML = asg.length ? '<strong>Người thực hiện:</strong> '+asg.join(', ') : '';
  document.getElementById('viewContent').textContent=it.content||'(Không có nội dung chữ)';
  var links='';
  if(it.link) links+='<a class="btn btn-sm btn-outline-primary me-2" target="_blank" href="'+it.link+'"><i class="bi bi-link-45deg"></i> Link</a>';
  if(it.file_path) links+='<a class="btn btn-sm btn-outline-success" target="_blank" href="<?= BASE_URL ?>data/'+it.file_path+'"><i class="bi bi-download"></i> File</a>';
  document.getElementById('viewLinks').innerHTML=links||'<span class="text-muted">Không có file/link</span>';
  new bootstrap.Modal(document.getElementById('viewModal')).show();
}
</script>
<?php require_once 'includes/footer.php'; ?>
