<?php
/**
 * Tổng quan CSDL — nguồn chuẩn hệ sinh thái.
 * Biến: $stats, $teachers, $classes, $students
 */
require_once __DIR__ . '/csdl_search.php';

$q = trim($_GET['q'] ?? '');
$scope = $_GET['scope'] ?? 'all';
$sr = csdl_search($q, $scope);
$hasQuery = ($q !== '' || $scope !== 'all');
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$stats['teachers'] ?></div><div class="text-muted small">Giáo viên</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="n text-success"><?= (int)$stats['classes'] ?></div><div class="text-muted small">Lớp học</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="n text-info"><?= (int)$stats['students'] ?></div><div class="text-muted small">Học sinh</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= e($stats['year']) ?></div><div class="text-muted small">Năm học</div></div></div>
</div>

<div class="card card-soft mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
      <div>
        <h5 class="mb-1"><i class="bi bi-database text-success"></i> CSDL là nguồn chuẩn</h5>
        <p class="small text-muted mb-0">
          Quản lý và cập nhật tại đây. Các module khác (PCCM, Nội trú, Thi đua…)
          <strong>đồng bộ một chiều từ CSDL</strong> khi người dùng ấn nút đồng bộ trên trang đó.
        </p>
      </div>
    </div>
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="overview">
      <div class="col-md-5">
        <label class="form-label small mb-1">Tìm kiếm</label>
        <input type="search" name="q" class="form-control" placeholder="Họ tên, mã, CCCD, SĐT, tổ, lớp, chuyên môn…" value="<?= e($_GET['q'] ?? '') ?>" autofocus>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Phạm vi</label>
        <select name="scope" class="form-select">
          <option value="all" <?= $scope==='all'?'selected':'' ?>>Tất cả</option>
          <option value="teachers" <?= $scope==='teachers'?'selected':'' ?>>Giáo viên</option>
          <option value="students" <?= $scope==='students'?'selected':'' ?>>Học sinh</option>
          <option value="classes" <?= $scope==='classes'?'selected':'' ?>>Lớp / khối</option>
          <option value="to" <?= $scope==='to'?'selected':'' ?>>Tổ chuyên môn</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
      <div class="col-md-2">
        <a href="?tab=overview" class="btn btn-outline-secondary w-100">Xóa lọc</a>
      </div>
    </form>
  </div>
</div>

<?php if ($hasQuery || $q === ''): ?>

  <?php if ($scope === 'all' || $scope === 'teachers'): ?>
  <div class="card card-soft mb-3">
    <div class="card-body">
      <h6 class="mb-2">Giáo viên <span class="badge bg-primary"><?= count($sr['teachers']) ?></span></h6>
      <?php if (!$sr['teachers']): ?>
        <p class="text-muted small mb-0">Không có kết quả.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Họ tên</th><th>Mã</th><th>CCCD</th><th>Chuyên môn</th><th>Tổ</th><th>SĐT</th><th></th></tr></thead>
          <tbody>
          <?php foreach (array_slice($sr['teachers'], 0, 50) as $t):
            $to = $t['to_chuyen_mon'] ?? $t['pccm_group'] ?? '';
          ?>
            <tr>
              <td><strong><?= e($t['name'] ?? '') ?></strong></td>
              <td class="small"><?= e($t['code'] ?? '') ?></td>
              <td class="small"><?= e($t['cccd'] ?? '') ?></td>
              <td class="small"><?= e($t['specialty'] ?? '') ?></td>
              <td class="small"><?= e($to) ?></td>
              <td class="small"><?= e($t['phone'] ?? '') ?></td>
              <td><a class="btn btn-sm btn-outline-primary" href="?tab=teachers&edit=<?= urlencode($t['id']) ?>"><i class="bi bi-pencil"></i></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($sr['teachers']) > 50): ?>
          <p class="small text-muted mt-2 mb-0">Hiển thị 50 / <?= count($sr['teachers']) ?> — thu hẹp từ khóa hoặc mở tab Giáo viên.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($scope === 'all' || $scope === 'students'): ?>
  <div class="card card-soft mb-3">
    <div class="card-body">
      <h6 class="mb-2">Học sinh <span class="badge bg-info text-dark"><?= count($sr['students']) ?></span></h6>
      <?php if (!$sr['students']): ?>
        <p class="text-muted small mb-0">Không có kết quả.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Họ tên</th><th>Mã</th><th>CCCD</th><th>Lớp</th><th>SĐT</th><th>Nội trú</th><th></th></tr></thead>
          <tbody>
          <?php foreach (array_slice($sr['students'], 0, 50) as $s):
            $cn = '';
            $cid = $s['class_id'] ?? '';
            if ($cid && isset($sr['class_by_id'][$cid])) $cn = $sr['class_by_id'][$cid]['name'] ?? '';
          ?>
            <tr>
              <td><strong><?= e($s['name'] ?? '') ?></strong></td>
              <td class="small"><?= e($s['code'] ?? '') ?></td>
              <td class="small"><?= e($s['cccd'] ?? '') ?></td>
              <td><?= e($cn) ?></td>
              <td class="small"><?= e($s['phone'] ?? '') ?></td>
              <td><?= !empty($s['boarder']) ? 'Có' : '' ?></td>
              <td><a class="btn btn-sm btn-outline-primary" href="?tab=students&edit=<?= urlencode($s['id']) ?>"><i class="bi bi-pencil"></i></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($sr['students']) > 50): ?>
          <p class="small text-muted mt-2 mb-0">Hiển thị 50 / <?= count($sr['students']) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($scope === 'all' || $scope === 'classes'): ?>
  <div class="card card-soft mb-3">
    <div class="card-body">
      <h6 class="mb-2">Lớp / khối <span class="badge bg-success"><?= count($sr['classes']) ?></span></h6>
      <?php if (!$sr['classes']): ?>
        <p class="text-muted small mb-0">Không có kết quả.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Lớp</th><th>Khối</th><th>Cấp</th><th>GVCN</th><th>Phòng</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($sr['classes'] as $c):
            $gvcn = '';
            $hid = $c['homeroom_teacher_id'] ?? '';
            if ($hid && isset($sr['teacher_by_id'][$hid])) $gvcn = $sr['teacher_by_id'][$hid]['name'] ?? '';
          ?>
            <tr>
              <td><strong><?= e($c['name'] ?? '') ?></strong></td>
              <td><?= e((string)($c['grade'] ?? '')) ?></td>
              <td><?= e($c['level'] ?? '') ?></td>
              <td class="small"><?= e($gvcn) ?></td>
              <td><?= e($c['room'] ?? '') ?></td>
              <td><a class="btn btn-sm btn-outline-primary" href="?tab=classes&edit=<?= urlencode($c['id']) ?>"><i class="bi bi-pencil"></i></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($scope === 'all' || $scope === 'to'): ?>
  <div class="card card-soft mb-3">
    <div class="card-body">
      <h6 class="mb-2">Tổ chuyên môn <span class="badge bg-secondary"><?= count($sr['to_groups']) ?></span></h6>
      <?php if (!$sr['to_groups']): ?>
        <p class="text-muted small mb-0">Chưa có dữ liệu tổ (kéo từ tab Giáo viên hoặc nhập CSV).</p>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($sr['to_groups'] as $toName => $list): ?>
            <div class="col-md-6 col-lg-4">
              <div class="border rounded-3 p-3 h-100 bg-light">
                <div class="fw-bold mb-2"><?= e($toName) ?> <span class="badge bg-primary"><?= count($list) ?></span></div>
                <ul class="small mb-0 ps-3">
                  <?php foreach ($list as $t): ?>
                    <li>
                      <a href="?tab=teachers&edit=<?= urlencode($t['id']) ?>"><?= e($t['name'] ?? '') ?></a>
                      <?php if (!empty($t['specialty'])): ?>
                        <span class="text-muted">— <?= e($t['specialty']) ?></span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

<?php endif; ?>
