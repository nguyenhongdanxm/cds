<?php /** Modal HS — $editing, $classes */ ?>
<div class="modal fade" id="modalStudent" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="student_save">
        <input type="hidden" name="id" id="s_id" value="<?= e($editing['id'] ?? '') ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalStudentTitle"><?= $editing ? 'Sửa học sinh' : 'Thêm học sinh' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label small">Họ và tên *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Mã HS</label>
              <input type="text" name="code" class="form-control" value="<?= e($editing['code'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">CCCD</label>
              <input type="text" name="cccd" class="form-control" value="<?= e($editing['cccd'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Lớp</label>
              <select name="class_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($classes as $c): if (empty($c['active'])) continue; ?>
                  <option value="<?= e($c['id']) ?>" <?= (($editing['class_id'] ?? '') === $c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="col-md-2"><label class="form-label small">Giới tính</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="Nam" <?= (($editing['gender'] ?? '')==='Nam')?'selected':'' ?>>Nam</option>
                <option value="Nữ" <?= (($editing['gender'] ?? '')==='Nữ')?'selected':'' ?>>Nữ</option>
              </select></div>
            <div class="col-md-3"><label class="form-label small">Ngày sinh</label>
              <input type="date" name="dob" class="form-control" value="<?= e($editing['dob'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Dân tộc</label>
              <input type="text" name="ethnicity" class="form-control" value="<?= e($editing['ethnicity'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label small">SĐT HS</label>
              <input type="text" name="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small">Quê quán</label>
              <input type="text" name="hometown" class="form-control" value="<?= e($editing['hometown'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small">Địa chỉ</label>
              <input type="text" name="address" class="form-control" value="<?= e($editing['address'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label small">Họ tên PH</label>
              <input type="text" name="parent_name" class="form-control" value="<?= e($editing['parent_name'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label small">SĐT PH</label>
              <input type="text" name="parent_phone" class="form-control" value="<?= e($editing['parent_phone'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Phòng KTX</label>
              <input type="text" name="room_ktx" class="form-control" value="<?= e($editing['room_ktx'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Nhóm ăn</label>
              <input type="text" name="meal_group" class="form-control" value="<?= e($editing['meal_group'] ?? '') ?>"></div>
            <div class="col-md-8"><label class="form-label small">Ghi chú</label>
              <input type="text" name="note" class="form-control" value="<?= e($editing['note'] ?? '') ?>"></div>
            <div class="col-md-4">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="boarder" id="brd" <?= !empty($editing['boarder'])?'checked':'' ?>><label class="form-check-label" for="brd">Nội trú</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="active" id="sact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>><label class="form-check-label" for="sact">Đang học</label></div>
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
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal('#modalStudent').show()});</script>
<?php endif; ?>
