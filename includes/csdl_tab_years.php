<?php
/** Tab Năm học — $years, $edit_id */
$editing_year = null;
if ($edit_id) {
    foreach ($years as $y) {
        if (($y['id'] ?? '') === $edit_id) { $editing_year = $y; break; }
    }
}
?>
<div class="row g-4">
  <div class="col-lg-4">
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
  </div>
  <div class="col-lg-8">
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
              <a class="btn btn-sm btn-outline-primary" href="?tab=years&edit=<?= urlencode($y['id']) ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
              <?php if (empty($y['is_current'])): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="year_set_current">
                <input type="hidden" name="id" value="<?= e($y['id']) ?>">
                <button class="btn btn-sm btn-outline-success" type="submit" title="Đặt hiện hành">Hiện hành</button>
              </form>
              <?php endif; ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa năm học này?')">
                <input type="hidden" name="action" value="year_delete">
                <input type="hidden" name="id" value="<?= e($y['id']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
