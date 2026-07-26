<?php /** Modal lớp — $editing, $teachers */ ?>
<div class="modal fade" id="modalClass" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="class_save">
        <input type="hidden" name="id" id="c_id" value="<?= e($editing['id'] ?? '') ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalClassTitle"><?= $editing ? 'Sửa lớp' : 'Thêm lớp' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label small">Tên lớp *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Khối *</label>
              <input type="number" name="grade" class="form-control" min="6" max="12" required value="<?= e((string)($editing['grade'] ?? 6)) ?>"></div>
            <div class="col-md-6"><label class="form-label small">GVCN</label>
              <select name="homeroom_teacher_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($teachers as $t): if (empty($t['active'])) continue; ?>
                  <option value="<?= e($t['id']) ?>" <?= (($editing['homeroom_teacher_id'] ?? '') === $t['id'])?'selected':'' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="col-md-4"><label class="form-label small">Phòng</label>
              <input type="text" name="room" class="form-control" value="<?= e($editing['room'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label small">Sĩ số định mức</label>
              <input type="text" name="capacity" class="form-control" value="<?= e((string)($editing['capacity'] ?? '')) ?>"></div>
            <div class="col-md-4"><label class="form-label small">Ghi chú</label>
              <input type="text" name="note" class="form-control" value="<?= e($editing['note'] ?? '') ?>"></div>
            <div class="col-12">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="active" id="cact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>>
              <label class="form-check-label" for="cact">Đang hoạt động</label></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
          <button type="submit" class="btn btn-primary">Lưu</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if (!empty($editing)): ?>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal('#modalClass').show()});</script>
<?php endif; ?>
