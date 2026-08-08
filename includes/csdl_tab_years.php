<?php
/** Tab Năm học — $years, $edit_id */
$editing_year = null;
if ($edit_id) {
    foreach ($years as $y) {
        if (($y['id'] ?? '') === $edit_id) { $editing_year = $y; break; }
    }
}
$week_year = null;
$week_year_id = trim($_GET['weeks'] ?? '');
if ($week_year_id !== '') $week_year = csdl_year_find($week_year_id);
$week_rows = $week_year ? csdl_year_weeks($week_year) : [];
?>
<div class="row g-4">
  <?php if (!empty($canYearEdit)): ?><div class="col-lg-4">
    <div class="card card-soft"><div class="card-body">
      <h5 class="mb-3"><?= $editing_year ? 'Sửa năm học' : 'Thêm năm học' ?></h5>
      <form method="post">
        <input type="hidden" name="action" value="year_save">
        <input type="hidden" name="id" value="<?= e($editing_year['id'] ?? '') ?>">
        <div class="mb-2"><label class="form-label small">Nhãn *</label>
          <input type="text" name="label" class="form-control" required placeholder="2025–2026" value="<?= e($editing_year['label'] ?? '') ?>"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small">Bắt đầu</label>
            <input type="date" name="start" class="form-control" value="<?= e($editing_year['start'] ?? '') ?>"></div>
          <div class="col-6"><label class="form-label small">Kết thúc</label>
            <input type="date" name="end" class="form-control" value="<?= e($editing_year['end'] ?? '') ?>"></div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="is_current" id="yc" <?= !empty($editing_year['is_current']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="yc">Năm hiện hành</label>
        </div>
        <button class="btn btn-primary w-100" type="submit"><?= $editing_year ? 'Cập nhật' : 'Thêm mới' ?></button>
        <?php if ($editing_year): ?>
          <a href="?tab=years" class="btn btn-outline-secondary w-100 mt-2">Hủy</a>
        <?php endif; ?>
      </form>
    </div></div>
  </div><?php endif; ?>
  <div class="<?= !empty($canYearEdit) ? 'col-lg-8' : 'col-12' ?>">
    <div class="card card-soft"><div class="card-body">
      <h5 class="mb-2">Danh sách năm học</h5>
      <table class="table align-middle mb-0">
        <thead><tr><th>Nhãn</th><th>Thời gian</th><th>TT</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($years as $y): ?>
          <tr>
            <td><strong><?= e($y['label'] ?? '') ?></strong></td>
            <td class="small"><?= e(($y['start'] ?? '') . ' → ' . ($y['end'] ?? '')) ?></td>
            <td><?= !empty($y['is_current']) ? '<span class="badge bg-success">Hiện hành</span>' : '—' ?></td>
            <td class="text-end text-nowrap">
              <?php if (!empty($canYearEdit)): ?>
              <a class="btn btn-sm btn-outline-secondary" href="?tab=years&weeks=<?= urlencode($y['id']) ?>" title="Cài đặt tuần học"><i class="bi bi-calendar-week"></i></a>
              <a class="btn btn-sm btn-outline-primary" href="?tab=years&edit=<?= urlencode($y['id']) ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
              <?php if (empty($y['is_current'])): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="year_set_current">
                <input type="hidden" name="id" value="<?= e($y['id']) ?>">
                <button class="btn btn-sm btn-outline-success" type="submit" title="Đặt hiện hành">Hiện hành</button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
              <?php if (!empty($canYearDelete)): ?><form method="post" class="d-inline" onsubmit="return confirm('Xóa năm học này?')">
                <input type="hidden" name="action" value="year_delete">
                <input type="hidden" name="id" value="<?= e($y['id']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
              </form><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!empty($canYearEdit)): ?><div class="form-text mt-3"><i class="bi bi-info-circle"></i> Bấm biểu tượng lịch để cài đặt tuần dùng chung cho toàn hệ thống.</div><?php endif; ?>
    </div></div>
  </div>
</div>

<?php if ($week_year && !empty($canYearEdit)): ?>
<div class="modal fade" id="weekSettingsModal" tabindex="-1" aria-labelledby="weekSettingsTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <div>
          <h4 class="modal-title fw-bold" id="weekSettingsTitle">Cài đặt thời gian tuần</h4>
          <p class="text-muted mb-0">Chọn tuần cần điều chỉnh và ngày bắt đầu. Các tuần trước đó giữ nguyên, các tuần sau tự động tính lại.</p>
        </div>
        <a class="btn-close" href="?tab=years" aria-label="Đóng"></a>
      </div>
      <form method="post">
        <div class="modal-body pt-4">
          <input type="hidden" name="action" value="year_week_save">
          <input type="hidden" name="year_id" value="<?= e($week_year['id'] ?? '') ?>">
          <div class="mb-4">
            <label class="form-label fw-semibold">Năm học</label>
            <input class="form-control form-control-lg" value="<?= e($week_year['label'] ?? '') ?>" disabled>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" for="weekNumber">Chọn tuần cần điều chỉnh</label>
            <select class="form-select form-select-lg" name="week_number" id="weekNumber" required>
              <?php foreach ($week_rows as $week): ?>
              <option value="<?= (int)$week['number'] ?>" data-start="<?= e($week['start']) ?>">
                Tuần <?= (int)$week['number'] ?> (<?= e(date('d/m', strtotime($week['start']))) ?> - <?= e(date('d/m', strtotime($week['end']))) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" for="weekStart">Ngày bắt đầu tuần</label>
            <input class="form-control form-control-lg" type="date" name="week_start" id="weekStart" value="<?= e($week_rows[0]['start'] ?? '') ?>" required>
          </div>
          <div class="rounded-3 border bg-light p-3" id="weekPreview"></div>
        </div>
        <div class="modal-footer border-0">
          <a class="btn btn-outline-secondary btn-lg" href="?tab=years">Hủy</a>
          <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-floppy"></i> Lưu cài đặt</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  var weeks = <?= json_encode($week_rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  function isoAdd(value, days) {
    var date = new Date(value + 'T00:00:00');
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
  }
  function vn(value) {
    var parts = value.split('-');
    return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : value;
  }
  function refreshPreview(resetDate) {
    var select = document.getElementById('weekNumber');
    var input = document.getElementById('weekStart');
    var number = parseInt(select.value || '1', 10);
    var row = weeks.find(function(item){ return parseInt(item.number, 10) === number; });
    if (resetDate && row) input.value = row.start;
    if (!input.value) return;
    var lines = [];
    for (var offset = 0; offset < 3; offset++) {
      var start = isoAdd(input.value, offset * 7);
      var end = isoAdd(start, 6);
      lines.push('<div class="' + (offset === 0 ? 'text-primary fw-semibold' : '') + '">Tuần ' + (number + offset) + ': ' + vn(start) + ' - ' + vn(end) + '</div>');
    }
    document.getElementById('weekPreview').innerHTML = '<div class="fw-semibold mb-2">Xem trước:</div>' + lines.join('') + '<div class="text-muted mt-2">…</div>';
  }
  document.addEventListener('DOMContentLoaded', function(){
    var modalElement = document.getElementById('weekSettingsModal');
    if (modalElement && window.bootstrap) new bootstrap.Modal(modalElement).show();
    document.getElementById('weekNumber')?.addEventListener('change', function(){ refreshPreview(true); });
    document.getElementById('weekStart')?.addEventListener('change', function(){ refreshPreview(false); });
    refreshPreview(true);
  });
})();
</script>
<?php endif; ?>
