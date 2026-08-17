<?php
$page_title = 'Thống kê phân công';
require_once 'includes/functions.php';
require_login();

$view_id = $_GET['v'] ?? get_active_version_id();
if (!get_version($view_id)) {
    $versions = get_versions();
    $view_id = $versions[0]['id'] ?? null;
}
$view = get_version($view_id);
$stats = $view_id ? get_assignment_stats($view_id) : null;

require_once 'includes/header.php';

$versions = get_versions();
usort($versions, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

function status_badge($st) {
    if ($st === 'ok') return '<span class="badge bg-success">Đủ</span>';
    if ($st === 'missing') return '<span class="badge bg-danger">Thiếu môn</span>';
    if ($st === 'diff') return '<span class="badge bg-warning text-dark">Lệch tiết</span>';
    return '<span class="badge bg-secondary">—</span>';
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h3 class="mb-0"><i class="bi bi-bar-chart-line"></i> Thống kê phân công</h3>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <form method="get" class="d-flex gap-2 align-items-center">
      <label class="small text-muted mb-0">Phiên bản</label>
      <select name="v" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
        <?php foreach ($versions as $v): ?>
        <option value="<?= e($v['id']) ?>" <?= $v['id']===$view_id?'selected':'' ?>><?= e($v['name']) ?> (<?= e($v['date']??'') ?>)</option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="<?= BASE_URL ?>ketqua.php?v=<?= e($view_id) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-clipboard-data"></i> Kết quả</a>
  </div>
</div>

<?php if (!$view || !$stats): ?>
<div class="alert alert-info">Chưa có phiên bản / dữ liệu để thống kê.</div>
<?php require_once 'includes/footer.php'; exit; endif; ?>

<div class="alert alert-light border mb-3">
  <strong><?= e($view['name']) ?></strong> · Ngày <?= e($view['date'] ?? '') ?>
  <span class="text-muted small ms-2">So sánh với số tiết chuẩn theo môn–khối trong danh mục Môn học</span>
</div>

<?php
$s = $stats;
$cov = $s['total_std'] > 0 ? round($s['total_assigned'] / $s['total_std'] * 100, 1) : 0;
?>

<div class="row g-2 mb-4">
  <div class="col-6 col-md-3"><div class="card stat-card py-2"><div class="number fs-4"><?= number_format($s['total_assigned'],1) ?></div><div class="label">Tiết đã phân công (dạy)</div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card py-2"><div class="number fs-4 text-secondary"><?= number_format($s['total_std'],1) ?></div><div class="label">Tiết chuẩn (toàn trường)</div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card py-2"><div class="number fs-4 <?= abs($s['total_diff'])<0.05?'text-success':($s['total_diff']<0?'text-warning':'text-danger') ?>"><?= ($s['total_diff']>0?'+':'') . number_format($s['total_diff'],1) ?></div><div class="label">Chênh lệch (đã − chuẩn)</div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card py-2"><div class="number fs-4 text-info"><?= number_format($s['total_role'],1) ?></div><div class="label">Tiết kiêm nhiệm</div></div></div>
</div>

<div class="row g-2 mb-4">
  <div class="col-6 col-md-2"><div class="card text-center py-2"><div class="fw-bold text-success fs-5"><?= $s['slots_ok'] ?></div><div class="small text-muted">Ô đủ môn+lớp</div></div></div>
  <div class="col-6 col-md-2"><div class="card text-center py-2"><div class="fw-bold text-danger fs-5"><?= $s['slots_missing'] ?></div><div class="small text-muted">Ô thiếu phân công</div></div></div>
  <div class="col-6 col-md-2"><div class="card text-center py-2"><div class="fw-bold text-warning fs-5"><?= $s['slots_diff'] ?></div><div class="small text-muted">Ô lệch số tiết</div></div></div>
  <div class="col-6 col-md-2"><div class="card text-center py-2"><div class="fw-bold text-danger fs-5"><?= $s['slots_conflict'] ?></div><div class="small text-muted">Ô trùng GV</div></div></div>
  <div class="col-6 col-md-2"><div class="card text-center py-2"><div class="fw-bold fs-5"><?= $s['classes_ok'] ?>/<?= $s['classes_total'] ?></div><div class="small text-muted">Lớp đủ chuẩn</div></div></div>
  <div class="col-6 col-md-2"><div class="card text-center py-2"><div class="fw-bold text-primary fs-5"><?= $cov ?>%</div><div class="small text-muted">Tỷ lệ phủ tiết</div></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabClass" type="button">Theo lớp</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGrade" type="button">Theo khối</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSubject" type="button">Theo môn</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMissing" type="button">Thiếu / lệch <span class="badge bg-danger"><?= $s['slots_missing'] + $s['slots_diff'] ?></span></button></li>
</ul>

<div class="tab-content">

<div class="tab-pane fade show active" id="tabClass">
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
  <span><i class="bi bi-building"></i> So sánh từng lớp với tiết chuẩn chương trình</span>
  <input type="search" id="fClass" class="form-control form-control-sm" style="max-width:180px" placeholder="Lọc lớp...">
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 align-middle" id="tblClass">
<thead>
<tr>
  <th>Lớp</th>
  <th>Khối</th>
  <th>Cấp</th>
  <th class="text-end">Chuẩn</th>
  <th class="text-end">Đã PC</th>
  <th class="text-end">Chênh</th>
  <th class="text-center">Trạng thái</th>
  <th>Thiếu môn / Lệch tiết</th>
</tr>
</thead>
<tbody>
<?php foreach ($s['by_class'] as $row):
  $diffCls = abs($row['diff']) < 0.05 ? 'diff-ok' : ($row['diff'] < 0 ? 'diff-under' : 'diff-over');
  $detail = [];
  if ($row['missing']) $detail[] = '<span class="text-danger">Thiếu: '.e(implode(', ', $row['missing'])).'</span>';
  if ($row['period_diffs']) $detail[] = '<span class="text-warning">Lệch: '.e(implode('; ', $row['period_diffs'])).'</span>';
?>
<tr data-q="<?= e(mb_strtolower($row['class'].' '.$row['grade'].' '.$row['level'], 'UTF-8')) ?>">
  <td><strong><?= e($row['class']) ?></strong></td>
  <td><?= e($row['grade']) ?></td>
  <td><span class="badge bg-secondary"><?= e($row['level']) ?></span></td>
  <td class="text-end"><?= number_format($row['std'],1) ?></td>
  <td class="text-end"><?= number_format($row['assigned'],1) ?></td>
  <td class="text-end <?= $diffCls ?>"><?= ($row['diff']>0?'+':'').number_format($row['diff'],1) ?></td>
  <td class="text-center"><?= status_badge($row['status']) ?></td>
  <td class="small"><?= $detail ? implode('<br>', $detail) : '<span class="text-success">—</span>' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
<div class="card-footer small text-muted">Chuẩn = tổng số tiết các môn có phân bố cho khối/lớp (theo danh mục Môn học).</div>
</div>
</div>

<div class="tab-pane fade" id="tabGrade">
<div class="card">
<div class="card-header"><i class="bi bi-layers"></i> Tổng hợp theo khối</div>
<div class="card-body p-0">
<table class="table table-sm table-hover mb-0">
<thead>
<tr>
  <th>Khối</th>
  <th>Cấp</th>
  <th class="text-center">Số lớp</th>
  <th class="text-end">Chuẩn / lớp</th>
  <th class="text-end">Chuẩn (tổng)</th>
  <th class="text-end">Đã PC</th>
  <th class="text-end">Chênh</th>
  <th class="text-center">Lớp đủ</th>
</tr>
</thead>
<tbody>
<?php foreach ($s['by_grade'] as $g => $row):
  $diffCls = abs($row['diff']) < 0.05 ? 'diff-ok' : ($row['diff'] < 0 ? 'diff-under' : 'diff-over');
?>
<tr>
  <td><strong><?= e($g) ?></strong></td>
  <td><?= e($row['level']) ?></td>
  <td class="text-center"><?= (int)$row['class_count'] ?></td>
  <td class="text-end"><?= number_format($row['std_per_class'],1) ?></td>
  <td class="text-end"><?= number_format($row['std'],1) ?></td>
  <td class="text-end"><?= number_format($row['assigned'],1) ?></td>
  <td class="text-end <?= $diffCls ?>"><?= ($row['diff']>0?'+':'').number_format($row['diff'],1) ?></td>
  <td class="text-center"><?= (int)$row['classes_ok'] ?>/<?= (int)$row['class_count'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
</div>

<div class="tab-pane fade" id="tabSubject">
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
  <span><i class="bi bi-book"></i> Theo môn học (toàn trường)</span>
  <input type="search" id="fSub" class="form-control form-control-sm" style="max-width:180px" placeholder="Lọc môn...">
</div>
<div class="card-body p-0">
<table class="table table-sm table-hover mb-0" id="tblSub">
<thead>
<tr>
  <th>Môn</th>
  <th class="text-end">Chuẩn</th>
  <th class="text-end">Đã PC</th>
  <th class="text-end">Chênh</th>
  <th class="text-center">Ô đủ</th>
  <th class="text-center">Ô thiếu</th>
  <th class="text-center">Ô lệch</th>
</tr>
</thead>
<tbody>
<?php foreach ($s['by_subject'] as $sub => $row):
  $diffCls = abs($row['diff']) < 0.05 ? 'diff-ok' : ($row['diff'] < 0 ? 'diff-under' : 'diff-over');
?>
<tr data-q="<?= e(mb_strtolower($sub, 'UTF-8')) ?>">
  <td><strong><?= e($sub) ?></strong></td>
  <td class="text-end"><?= number_format($row['std'],1) ?></td>
  <td class="text-end"><?= number_format($row['assigned'],1) ?></td>
  <td class="text-end <?= $diffCls ?>"><?= ($row['diff']>0?'+':'').number_format($row['diff'],1) ?></td>
  <td class="text-center text-success"><?= (int)$row['ok'] ?></td>
  <td class="text-center text-danger"><?= (int)$row['missing'] ?></td>
  <td class="text-center text-warning"><?= (int)$row['diff_count'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
</div>

<div class="tab-pane fade" id="tabMissing">
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-danger"><i class="bi bi-exclamation-octagon"></i> Thiếu phân công (<?= count($s['missing_list']) ?>)</div>
      <div class="card-body p-0" style="max-height:420px;overflow:auto">
        <?php if ($s['missing_list']): ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>Lớp</th><th>Môn</th><th class="text-end">Chuẩn</th></tr></thead>
          <tbody>
          <?php foreach ($s['missing_list'] as $m): ?>
          <tr class="table-danger"><td><?= e($m['class']) ?></td><td><?= e($m['subject']) ?></td><td class="text-end"><?= number_format($m['std'],1) ?>t</td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="p-3 text-success">Không thiếu môn nào.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Lệch số tiết so với chuẩn (<?= count($s['diff_list']) ?>)</div>
      <div class="card-body p-0" style="max-height:420px;overflow:auto">
        <?php if ($s['diff_list']): ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>Lớp</th><th>Môn</th><th class="text-end">Chuẩn</th><th class="text-end">Đã PC</th><th>GV</th></tr></thead>
          <tbody>
          <?php foreach ($s['diff_list'] as $m): ?>
          <tr class="table-warning">
            <td><?= e($m['class']) ?></td>
            <td><?= e($m['subject']) ?></td>
            <td class="text-end"><?= number_format($m['std'],1) ?></td>
            <td class="text-end"><?= number_format($m['assigned'],1) ?></td>
            <td class="small"><?= e(implode(', ', $m['teachers'] ?? [])) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="p-3 text-success">Không có ô lệch số tiết.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($s['conflict_list']): ?>
<div class="card mt-3">
  <div class="card-header bg-danger"><i class="bi bi-people"></i> Trùng môn + lớp (<?= count($s['conflict_list']) ?>)</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th>Môn</th><th>Lớp</th><th>Giáo viên</th></tr></thead>
      <tbody>
      <?php foreach ($s['conflict_list'] as $c): ?>
      <tr class="table-danger"><td><?= e($c['subject']) ?></td><td><?= e($c['class']) ?></td><td><?= e(implode(', ', $c['teachers'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
</div>

</div>

<script>
function bindFilter(inputId, tableId) {
  var input = document.getElementById(inputId);
  var table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function() {
    var q = this.value.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(function(tr) {
      tr.style.display = !q || (tr.dataset.q || '').includes(q) ? '' : 'none';
    });
  });
}
bindFilter('fClass', 'tblClass');
bindFilter('fSub', 'tblSub');
</script>

<?php require_once 'includes/footer.php'; ?>
