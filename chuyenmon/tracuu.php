<?php
$page_title = 'Tra cứu phân công';
require_once 'includes/functions.php';

// Khách chỉ xem phiên bản đang active (hoặc ?v= nếu hợp lệ)
$view_id = $_GET['v'] ?? get_active_version_id();
if (!get_version($view_id)) {
    $versions = get_versions();
    $view_id = $versions[0]['id'] ?? null;
}
$view = get_version($view_id);

$teacher = trim($_GET['gv'] ?? '');

$assignments = $view_id ? get_assignments($view_id) : [];
$role_assignments = $view_id ? get_role_assignments($view_id) : [];
$loads = $view_id ? get_teacher_loads($view_id) : [];

// Danh sách GV có phân công (dạy hoặc KN)
$names = [];
foreach ($assignments as $a) {
    if (!empty($a['teacher'])) $names[$a['teacher']] = true;
}
foreach ($role_assignments as $a) {
    if (!empty($a['teacher'])) $names[$a['teacher']] = true;
}
$teacher_list = sort_teachers_by_ten(array_keys($names));

// Dữ liệu GV được chọn
$items = [];
$roles = [];
$load = null;
if ($teacher !== '') {
    foreach ($assignments as $a) {
        if (($a['teacher'] ?? '') === $teacher) $items[] = $a;
    }
    foreach ($role_assignments as $a) {
        if (($a['teacher'] ?? '') === $teacher) $roles[] = $a;
    }
    $load = $loads[$teacher] ?? ['day' => 0, 'role' => 0, 'total' => 0, 'class_count' => 0];
}

require_once 'includes/header.php';
?>

<style>
.lookup-wrap{max-width:820px;margin:0 auto}
.lookup-card{
  background:#fff;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.08);
  padding:1.5rem 1.35rem;margin-bottom:1.25rem
}
.lookup-title{font-size:1.35rem;font-weight:700;color:#1F4E79;margin:0 0 .35rem}
.lookup-meta{color:#6c757d;font-size:.9rem;margin-bottom:1.1rem}
.lookup-select{font-size:1.05rem;padding:.65rem 1rem;border-radius:10px;border:2px solid #1F4E79}
.result-card{
  background:#fff;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.08);
  overflow:hidden
}
.result-head{
  background:linear-gradient(135deg,#1F4E79,#2E6DA4);color:#fff;
  padding:1rem 1.25rem
}
.result-head h4{margin:0;font-weight:700;font-size:1.15rem}
.result-head .badges{margin-top:.4rem}
.result-body{padding:1.15rem 1.25rem}
.section-label{font-weight:700;color:#1F4E79;font-size:.95rem;margin-bottom:.5rem}
.empty-hint{
  text-align:center;padding:2rem 1rem;color:#6c757d
}
</style>

<div class="lookup-wrap">

  <div class="lookup-card">
    <div class="lookup-title"><i class="bi bi-search"></i> Tra cứu phân công giáo viên</div>
    <div class="lookup-meta">
      <?php if ($view): ?>
      Phiên bản: <strong><?= e($view['name']) ?></strong>
      <?php if (!empty($view['date'])): ?> · Ngày <?= e($view['date']) ?><?php endif; ?>
      <?php else: ?>
      Chưa có dữ liệu phân công.
      <?php endif; ?>
    </div>

    <form method="get" id="frmLookup">
      <?php if ($view_id): ?><input type="hidden" name="v" value="<?= e($view_id) ?>"><?php endif; ?>
      <label class="form-label fw-semibold" for="gv">Chọn giáo viên</label>
      <select name="gv" id="gv" class="form-select lookup-select" onchange="this.form.submit()">
        <option value="">— Chọn tên giáo viên —</option>
        <?php foreach ($teacher_list as $n): ?>
        <option value="<?= e($n) ?>" <?= $teacher === $n ? 'selected' : '' ?>><?= e($n) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if ($teacher === ''): ?>
  <div class="result-card">
    <div class="empty-hint">
      <i class="bi bi-person-lines-fill" style="font-size:2.2rem;color:#adb5bd"></i>
      <div class="mt-2">Chọn tên giáo viên ở danh sách phía trên để xem phân công.</div>
    </div>
  </div>

  <?php elseif (!$items && !$roles): ?>
  <div class="result-card">
    <div class="result-head">
      <h4><?= e($teacher) ?></h4>
    </div>
    <div class="empty-hint">Giáo viên này chưa có phân công trong phiên bản đang xem.</div>
  </div>

  <?php else: ?>
  <div class="result-card">
    <div class="result-head">
      <h4><i class="bi bi-person-badge"></i> <?= e($teacher) ?></h4>
      <div class="badges">
        <span class="badge bg-light text-dark">Dạy: <?= number_format($load['day'], 1) ?> tiết</span>
        <span class="badge bg-info text-dark">Kiêm nhiệm: <?= number_format($load['role'], 1) ?> tiết</span>
        <span class="badge bg-warning text-dark">Tổng: <?= number_format($load['total'], 1) ?> tiết</span>
        <?php if (!empty($load['class_count'])): ?>
        <span class="badge bg-secondary"><?= (int)$load['class_count'] ?> lớp</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="result-body">

      <?php if ($roles): ?>
      <div class="section-label"><i class="bi bi-award"></i> Kiêm nhiệm</div>
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Chức vụ</th>
              <th>Lớp</th>
              <th class="text-center" style="width:80px">Tiết</th>
              <th>Ghi chú</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roles as $r): ?>
            <tr>
              <td><span class="badge bg-info text-dark"><?= e($r['role'] ?? '') ?></span></td>
              <td><?= e($r['class'] ?? '') ?></td>
              <td class="text-center fw-semibold"><?= e($r['periods'] ?? 0) ?></td>
              <td class="text-muted small"><?= e($r['note'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($items): ?>
      <div class="section-label"><i class="bi bi-book"></i> Phân công dạy môn</div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0">
          <thead>
            <tr>
              <th style="width:48px">STT</th>
              <th>Môn</th>
              <th>Lớp</th>
              <th class="text-center" style="width:80px">Tiết</th>
              <th>Ghi chú</th>
            </tr>
          </thead>
          <tbody>
            <?php $stt = 1; foreach ($items as $a): ?>
            <tr>
              <td class="text-muted"><?= $stt++ ?></td>
              <td><strong><?= e($a['subject'] ?? '') ?></strong></td>
              <td><?= e($a['class'] ?? '') ?></td>
              <td class="text-center fw-semibold"><?= e($a['periods'] ?? 0) ?></td>
              <td class="text-muted small"><?= e($a['note'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div>
  </div>
  <?php endif; ?>

  <div class="text-center mt-3">
    <a href="<?= BASE_URL ?>index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Trang chủ</a>
    <?php if (!is_logged_in()): ?>
    <a href="<?= BASE_URL ?>login.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập quản trị</a>
    <?php endif; ?>
  </div>

</div>

<?php require_once 'includes/footer.php'; ?>
