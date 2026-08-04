<?php
$page_title = 'Kế hoạch chuyên môn';
require_once 'includes/functions.php';
require_once 'includes/cm_docs.php';
require_login();

$tabs = [
    'vanban' => ['Văn bản kế hoạch', 'bi-file-earmark-pdf'],
    'thongbao' => ['Thông báo chuyên môn', 'bi-megaphone'],
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
require_once 'includes/header.php';
?>

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
<style>
.news-list{display:grid;gap:.85rem;margin-bottom:1.25rem}.news-item{display:flex;gap:1rem;align-items:flex-start;padding:1rem 1.15rem;border:1px solid #dfe7ef;border-radius:14px;background:#fff;box-shadow:0 3px 12px rgba(15,23,42,.05)}.news-date{flex:0 0 62px;padding:.55rem .35rem;border-radius:11px;background:#e8f0fe;color:#1f4e79;text-align:center}.news-date strong,.news-date span{display:block}.news-date strong{font-size:1.25rem}.news-copy{min-width:0}.news-copy a{font-size:1.06rem;font-weight:750;color:#1f4e79;text-decoration:none}.news-copy a:hover{text-decoration:underline}.news-copy p{margin:.35rem 0 0;color:#64748b}.article-admin{margin-top:1rem}.article-admin>summary{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem 1rem;border:1px solid #b8c8d8;border-radius:10px;background:#fff;color:#1f4e79;font-weight:700;cursor:pointer;list-style:none}.article-admin[open]>summary{margin-bottom:1rem;background:#e8f0fe}
</style>
<section>
  <div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1"><i class="bi bi-newspaper"></i> Bài viết chỉ tiêu chuyên môn</h4><div class="text-muted small">Chọn tiêu đề để đọc nội dung và mở văn bản liên quan.</div></div><span class="badge bg-primary rounded-pill"><?= count($items) ?> bài</span></div>
  <div class="news-list">
    <?php foreach ($items as $it): $ts = !empty($it['date']) ? strtotime($it['date']) : time(); ?>
      <article class="news-item">
        <time class="news-date"><strong><?= date('d', $ts) ?></strong><span>Th <?= date('m', $ts) ?></span></time>
        <div class="news-copy"><a href="<?= BASE_URL ?>baiviet.php?id=<?= urlencode($it['id'] ?? '') ?>"><?= e($it['title'] ?? '') ?></a><p><?= e(mb_strimwidth($it['content'] ?? '', 0, 170, '…', 'UTF-8')) ?></p></div>
      </article>
    <?php endforeach; ?>
    <?php if (!$items): ?><div class="alert alert-light border text-muted mb-0">Chưa có bài viết chỉ tiêu chuyên môn.</div><?php endif; ?>
  </div>
</section>
<details class="article-admin"><summary><i class="bi bi-pencil-square"></i> Quản lý bài viết</summary>
<?php endif; ?>

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
                <a class="fw-bold text-decoration-none" href="<?= BASE_URL ?>baiviet.php?id=<?= urlencode($it['id'] ?? '') ?>"><i class="bi bi-file-text me-1"></i><?= e($it['title'] ?? '') ?></a>
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
<?php if ($tab === 'chitieu'): ?></details><?php endif; ?>

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
