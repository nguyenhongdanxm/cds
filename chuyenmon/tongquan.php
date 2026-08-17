<?php
/**
 * Tổng quan PCCM — phân công chuyên môn (không phải trang chủ hạn/kế hoạch)
 */
$page_title = 'Tổng quan PCCM';
require_once 'includes/functions.php';
require_login();

$vid = get_active_version_id();
$view = get_version($vid);
$stats = $vid ? get_assignment_stats($vid) : null;
$loads = get_teacher_loads($vid);
$teachers = get_teachers_sorted();
$classes = get_classes();
$assignments = get_assignments($vid);
$roles = get_role_assignments($vid);

$over = []; $under = []; $ok = [];
foreach ($teachers as $t) {
    $r = $loads[$t] ?? null;
    $total = $r['total'] ?? 0;
    $quota = $r['quota'] ?? get_quota($t);
    $diff = $total - $quota;
    $row = ['name' => $t, 'total' => $total, 'quota' => $quota, 'diff' => $diff, 'day' => $r['day'] ?? 0, 'role' => $r['role'] ?? 0];
    if ($diff > 0.05) $over[] = $row;
    elseif ($diff < -0.05) $under[] = $row;
    else $ok[] = $row;
}
usort($over, fn($a, $b) => $b['diff'] <=> $a['diff']);
usort($under, fn($a, $b) => $a['diff'] <=> $b['diff']);

require_once 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h3 class="mb-0"><i class="bi bi-grid-1x2"></i> Tổng quan phân công (PCCM)</h3>
    <div class="text-muted small">Phiên bản làm việc hiện tại · so với định mức & tiết chuẩn</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= BASE_URL ?>them.php" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square"></i> Phân công</a>
    <a href="<?= BASE_URL ?>danhsach.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-ul"></i> Danh sách</a>
    <a href="<?= BASE_URL ?>thongke.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart-line"></i> Thống kê PCCM</a>
    <a href="<?= BASE_URL ?>index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Trang chủ CM</a>
  </div>
</div>

<?php if ($view): ?>
<div class="alert alert-light border mb-3">
  <i class="bi bi-folder2-open"></i>
  Đang xem: <strong><?= e($view['name']) ?></strong>
  (<?= e($view['date'] ?? '') ?>)
  <?php if (!empty($view['note'])): ?> · <?= e($view['note']) ?><?php endif; ?>
  · <a href="<?= BASE_URL ?>ketqua.php">Đổi phiên bản</a>
</div>
<?php else: ?>
<div class="alert alert-warning">Chưa có phiên bản phân công. Tạo tại <a href="<?= BASE_URL ?>ketqua.php">Kết quả</a>.</div>
<?php endif; ?>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3 col-xl-2"><div class="card stat-card py-2"><div class="number fs-5"><?= count($teachers) ?></div><div class="label">Giáo viên</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card stat-card py-2"><div class="number fs-5"><?= count($classes) ?></div><div class="label">Lớp</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card stat-card py-2"><div class="number fs-5"><?= count($assignments) ?></div><div class="label">Phân công dạy</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card stat-card py-2"><div class="number fs-5"><?= count($roles) ?></div><div class="label">Kiêm nhiệm</div></div></div>
  <?php if ($stats): ?>
  <div class="col-6 col-md-3 col-xl-2"><div class="card stat-card py-2"><div class="number fs-5 text-success"><?= (int)$stats['slots_ok'] ?></div><div class="label">Ô đủ chuẩn</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card stat-card py-2"><div class="number fs-5 text-danger"><?= (int)$stats['slots_missing'] ?></div><div class="label">Ô thiếu PC</div></div></div>
  <?php endif; ?>
</div>

<?php if ($stats):
  $cov = $stats['total_std'] > 0 ? round($stats['total_assigned'] / $stats['total_std'] * 100, 1) : 0;
?>
<div class="row g-2 mb-4">
  <div class="col-md-3"><div class="card text-center py-3"><div class="fw-bold fs-4 text-primary"><?= number_format($stats['total_assigned'], 1) ?></div><div class="small text-muted">Tiết dạy đã PC</div></div></div>
  <div class="col-md-3"><div class="card text-center py-3"><div class="fw-bold fs-4"><?= number_format($stats['total_std'], 1) ?></div><div class="small text-muted">Tiết chuẩn CT</div></div></div>
  <div class="col-md-3"><div class="card text-center py-3"><div class="fw-bold fs-4 <?= abs($stats['total_diff'])<0.05?'text-success':($stats['total_diff']<0?'text-warning':'text-danger') ?>"><?= ($stats['total_diff']>0?'+':'') . number_format($stats['total_diff'], 1) ?></div><div class="small text-muted">Chênh (đã − chuẩn)</div></div></div>
  <div class="col-md-3"><div class="card text-center py-3"><div class="fw-bold fs-4 text-info"><?= $cov ?>%</div><div class="small text-muted">Tỷ lệ phủ tiết · Lớp đủ <?= (int)$stats['classes_ok'] ?>/<?= (int)$stats['classes_total'] ?></div></div></div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-danger"><i class="bi bi-arrow-up-circle"></i> Vượt định mức (<?= count($over) ?>)</div>
      <div class="card-body p-0" style="max-height:280px;overflow:auto">
        <?php if (!$over): ?>
          <div class="p-3 text-muted small">Không có GV vượt định mức.</div>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>GV</th><th class="text-end">Tổng</th><th class="text-end">ĐM</th><th class="text-end">+</th></tr></thead>
          <tbody>
          <?php foreach ($over as $r): ?>
            <tr>
              <td class="small"><?= e($r['name']) ?></td>
              <td class="text-end"><?= number_format($r['total'], 1) ?></td>
              <td class="text-end"><?= number_format($r['quota'], 0) ?></td>
              <td class="text-end text-danger fw-semibold">+<?= number_format($r['diff'], 1) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-warning text-dark"><i class="bi bi-arrow-down-circle"></i> Dưới định mức (<?= count($under) ?>)</div>
      <div class="card-body p-0" style="max-height:280px;overflow:auto">
        <?php if (!$under): ?>
          <div class="p-3 text-muted small">Không có GV dưới định mức.</div>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>GV</th><th class="text-end">Tổng</th><th class="text-end">ĐM</th><th class="text-end">−</th></tr></thead>
          <tbody>
          <?php foreach ($under as $r): ?>
            <tr>
              <td class="small"><?= e($r['name']) ?></td>
              <td class="text-end"><?= number_format($r['total'], 1) ?></td>
              <td class="text-end"><?= number_format($r['quota'], 0) ?></td>
              <td class="text-end text-warning fw-semibold"><?= number_format($r['diff'], 1) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-success"><i class="bi bi-check-circle"></i> Đúng định mức (<?= count($ok) ?>)</div>
      <div class="card-body p-0" style="max-height:280px;overflow:auto">
        <?php if (!$ok): ?>
          <div class="p-3 text-muted small">—</div>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>GV</th><th class="text-end">Tổng</th><th class="text-end">ĐM</th></tr></thead>
          <tbody>
          <?php foreach ($ok as $r): ?>
            <tr>
              <td class="small"><?= e($r['name']) ?></td>
              <td class="text-end"><?= number_format($r['total'], 1) ?></td>
              <td class="text-end"><?= number_format($r['quota'], 0) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($stats && !empty($stats['missing_list'])): ?>
<div class="card mb-3">
  <div class="card-header bg-danger d-flex justify-content-between">
    <span><i class="bi bi-exclamation-octagon"></i> Thiếu phân công (<?= count($stats['missing_list']) ?> ô)</span>
    <a href="<?= BASE_URL ?>thongke.php" class="btn btn-sm btn-light">Xem thống kê đầy đủ</a>
  </div>
  <div class="card-body p-0" style="max-height:260px;overflow:auto">
    <table class="table table-sm mb-0">
      <thead><tr><th>Lớp</th><th>Môn</th><th class="text-end">Chuẩn</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($stats['missing_list'], 0, 40) as $m): ?>
        <tr class="table-danger"><td><?= e($m['class']) ?></td><td><?= e($m['subject']) ?></td><td class="text-end"><?= number_format($m['std'], 1) ?>t</td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (count($stats['missing_list']) > 40): ?>
      <div class="p-2 small text-muted">… và <?= count($stats['missing_list']) - 40 ?> mục nữa (xem Thống kê PCCM)</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="bi bi-lightning"></i> Thao tác nhanh</div>
  <div class="card-body d-flex flex-wrap gap-2">
    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>them.php"><i class="bi bi-plus-lg"></i> Thêm phân công</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>rasoat.php"><i class="bi bi-search"></i> Rà soát</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>doicheo.php"><i class="bi bi-arrow-left-right"></i> Đổi chéo</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>giaovien.php"><i class="bi bi-people"></i> Giáo viên</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>xuat_bang.php"><i class="bi bi-printer"></i> Xuất bảng</a>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
