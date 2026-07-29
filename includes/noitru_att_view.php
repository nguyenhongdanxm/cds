PLACEHOLDER_WILL_UPDATE
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Điểm danh nội trú – CDS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>includes/noitru_layout.css?v=20260729-2" rel="stylesheet">
</head>
<body class="nt-body">
<?php $nt_sec = 'attendance'; require __DIR__ . '/noitru_shell.php'; ?>
<main class="nt-main"><div class="nt-content">
  <div class="nt-page-head">
    <div>
      <h1><i class="bi bi-clipboard2-check-fill text-success"></i> Điểm danh nội trú</h1>
      <div class="subtitle"><?= e($weekdayVi) ?>, <?= e($dateLabel) ?> · <?= e($shiftLabel) ?></div>
    </div>
    <div class="nt-actions">
      <?php if ($view === 'settings'): ?>
        <a class="btn btn-outline-secondary" href="<?= e(att_url(['view'=>'diemdanh'])) ?>"><i class="bi bi-arrow-left"></i> Điểm danh</a>
      <?php elseif (allowed_classes() === null && can_edit_perm('nt.diemdanh')): ?>
        <a class="btn btn-outline-secondary" href="<?= e(att_url(['view'=>'settings'])) ?>"><i class="bi bi-gear"></i> Cấu hình ca</a>
      <?php endif; ?>
    </div>
  </div>

  <?php show_flash(); ?>

  <?php if ($view === 'settings'): ?>
    <?php $allShifts = noitru_att_shifts_all(); ?>
    <form method="post" class="card card-soft">
      <div class="card-body">
        <input type="hidden" name="action" value="shifts_save">
        <input type="hidden" name="view" value="settings">
        <h2 class="h6 mb-3">Các ca điểm danh</h2>
        <div class="table-responsive">
          <table class="table align-middle mb-3">
            <thead><tr><th>Hoạt động</th><th>Mã ca</th><th>Tên hiển thị</th><th>Thứ tự</th></tr></thead>
            <tbody>
            <?php foreach ($allShifts as $i => $row): ?>
              <tr>
                <td><input class="form-check-input" type="checkbox" name="active[<?= $i ?>]" value="1" <?= !empty($row['active'])?'checked':'' ?>></td>
                <td><input type="hidden" name="sid[]" value="<?= e($row['id']) ?>"><code><?= e($row['id']) ?></code></td>
                <td><input class="form-control form-control-sm" name="label[]" value="<?= e($row['label']) ?>" required></td>
                <td><input class="form-control form-control-sm" type="number" name="sort[]" value="<?= (int)$row['sort'] ?>" style="max-width:90px"></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="nt-filter mb-3">
          <div><label class="form-label">Mã ca mới</label><input class="form-control" name="new_id" placeholder="vi_du: hoc_chieu"></div>
          <div class="wide"><label class="form-label">Tên ca mới</label><input class="form-control" name="new_label" placeholder="Ví dụ: Học buổi chiều"></div>
        </div>
        <button class="btn btn-nt" type="submit"><i class="bi bi-check2-circle"></i> Lưu cấu hình</button>
      </div>
    </form>
  <?php else: ?>
    <form method="get" class="card card-soft mb-3"><div class="card-body nt-filter">
      <input type="hidden" name="view" value="diemdanh">
      <div><label class="form-label">Ngày</label><input class="form-control" type="date" name="date" value="<?= e($date) ?>"></div>
      <div><label class="form-label">Ca</label><select class="form-select" name="shift">
        <?php foreach ($shifts as $key => $label): ?><option value="<?= e($key) ?>" <?= $shift===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select></div>
      <div><label class="form-label">Lớp</label><select class="form-select" name="class">
        <option value="">Tất cả lớp</option>
        <?php foreach ($byClass as $key => $rows): ?><option value="<?= e($key) ?>" <?= $class===$key?'selected':'' ?>><?= e($key) ?> (<?= count($rows) ?>)</option><?php endforeach; ?>
      </select></div>
      <div class="wide"><label class="form-label">Tìm học sinh</label><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Tên, mã, lớp hoặc phòng"></div>
      <div class="compact"><button class="btn btn-nt w-100" type="submit"><i class="bi bi-funnel"></i> Lọc</button></div>
    </div></form>

    <div class="row g-2 mb-3">
      <div class="col-4"><div class="stat"><div class="n"><?= $cntTotal ?></div><div class="text-muted small">Tổng số</div></div></div>
      <div class="col-4"><div class="stat"><div class="n text-success"><?= $cntPresent ?></div><div class="text-muted small">Có mặt</div></div></div>
      <div class="col-4"><div class="stat"><div class="n text-danger"><?= $cntAbsent ?></div><div class="text-muted small">Vắng</div></div></div>
    </div>

    <form method="post" id="attendanceForm" class="card card-soft">
      <input type="hidden" name="action" id="attAction" value="att_save">
      <input type="hidden" name="bulk_status" id="bulkStatus" value="">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="shift" value="<?= e($shift) ?>">
      <input type="hidden" name="class" value="<?= e($class) ?>">
      <input type="hidden" name="view" value="diemdanh">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div><strong><?= count($list) ?> học sinh</strong><div class="small text-muted">Chọn trạng thái, phép/không phép và ghi lý do khi cần.</div></div>
          <?php if (can_edit_perm('nt.diemdanh') && $list): ?>
          <div class="nt-actions">
            <button class="btn btn-outline-success btn-sm" type="button" onclick="bulkAttendance('present')">Tất cả có mặt</button>
            <button class="btn btn-outline-danger btn-sm" type="button" onclick="bulkAttendance('absent')">Tất cả vắng</button>
          </div>
          <?php endif; ?>
        </div>
        <div class="att-list">
          <?php foreach ($list as $i => $student):
            $record = $attMap[$student['id']] ?? [];
            $status = $record['status'] ?? 'present';
          ?>
          <div class="att-row">
            <div class="att-student">
              <input type="hidden" name="sid[]" value="<?= e($student['id']) ?>">
              <strong><?= e($student['name']) ?></strong>
              <small><?= e($student['class_name'] ?: 'Chưa lớp') ?> · Phòng <?= e($student['room_ktx'] ?: '—') ?></small>
            </div>
            <div><label>Trạng thái</label><select class="form-select form-select-sm" name="status[]" <?= can_edit_perm('nt.diemdanh')?'':'disabled' ?>>
              <?php foreach (['present'=>'Có mặt','absent'=>'Vắng','late'=>'Muộn','excused'=>'Có phép'] as $key=>$label): ?>
              <option value="<?= $key ?>" <?= $status===$key?'selected':'' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select></div>
            <div><label>Phép</label><select class="form-select form-select-sm" name="excuse[]" <?= can_edit_perm('nt.diemdanh')?'':'disabled' ?>>
              <option value="">—</option><option value="P" <?= ($record['excuse']??'')==='P'?'selected':'' ?>>P</option><option value="KP" <?= ($record['excuse']??'')==='KP'?'selected':'' ?>>KP</option>
            </select></div>
            <div><label>Lý do / ghi chú</label><input class="form-control form-control-sm" name="reason[]" value="<?= e($record['reason']??'') ?>" <?= can_edit_perm('nt.diemdanh')?'':'readonly' ?>></div>
          </div>
          <?php endforeach; ?>
          <?php if (!$list): ?><div class="text-center text-muted py-4">Không có học sinh phù hợp.</div><?php endif; ?>
        </div>
        <?php if (can_edit_perm('nt.diemdanh') && $list): ?>
        <div class="sticky-save"><button class="btn btn-nt" type="submit"><i class="bi bi-cloud-check"></i> Lưu điểm danh</button></div>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</div></main>
<script>
function bulkAttendance(status){
  document.getElementById('attAction').value='att_bulk';
  document.getElementById('bulkStatus').value=status;
  document.getElementById('attendanceForm').submit();
}
</script>
</body>
</html>
