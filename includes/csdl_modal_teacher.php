<?php
/** Modal GV — cần $editing, $kn_text */
?>
<div class="modal fade" id="modalTeacher" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="teacher_save">
        <input type="hidden" name="id" id="t_id" value="<?= e($editing['id'] ?? '') ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTeacherTitle"><?= $editing ? 'Sửa giáo viên' : 'Thêm giáo viên' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php $teacherPhotoId=(string)($editing['id']??'');$hasTeacherPhoto=$teacherPhotoId!==''&&function_exists('staff_card_has_photo')&&staff_card_has_photo($teacherPhotoId); ?>
          <div class="card border-0 bg-light mb-3"><div class="card-body"><div class="row g-3 align-items-center">
            <div class="col-auto"><img id="teacherPhotoPreview" src="<?= $hasTeacherPhoto?e(BASE_URL.'staff_photo.php?id='.rawurlencode($teacherPhotoId).'&v='.time()):'' ?>" alt="Ảnh thẻ giáo viên" style="width:100px;height:133px;object-fit:cover;border:1px solid #cbd5e1;border-radius:10px;background:#fff;<?= $hasTeacherPhoto?'':'display:none' ?>"></div>
            <div class="col"><label class="form-label fw-semibold">Ảnh thẻ giáo viên</label><input id="teacherPhotoInput" type="file" name="teacher_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
              <div class="form-text">JPG, PNG hoặc WebP; tối đa 20 MB; kích thước tối thiểu 300 × 400 px. Ảnh này dùng chung khi tạo/in thẻ CBGV.</div>
              <?php if($hasTeacherPhoto):?><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_teacher_photo" value="1"> <span class="form-check-label text-danger">Xóa ảnh thẻ hiện tại</span></label><?php endif;?>
            </div>
          </div></div></div>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label small">Họ và tên *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Mã GV</label>
              <input type="text" name="code" class="form-control" value="<?= e($editing['code'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">CCCD</label>
              <input type="text" name="cccd" class="form-control" value="<?= e($editing['cccd'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Ngày sinh</label>
              <input type="date" name="dob" class="form-control" value="<?= e($editing['dob'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Giới tính</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="Nam" <?= (($editing['gender'] ?? '')==='Nam')?'selected':'' ?>>Nam</option>
                <option value="Nữ" <?= (($editing['gender'] ?? '')==='Nữ')?'selected':'' ?>>Nữ</option>
              </select></div>
            <div class="col-md-2"><label class="form-label small">Dân tộc</label>
              <input type="text" name="ethnicity" class="form-control" value="<?= e($editing['ethnicity'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label small">SĐT</label>
              <input type="text" name="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label small">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($editing['email'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small">Quê quán</label>
              <input type="text" name="hometown" class="form-control" value="<?= e($editing['hometown'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small">Địa chỉ</label>
              <input type="text" name="address" class="form-control" value="<?= e($editing['address'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Cấp học</label>
              <input type="text" name="teaching_level" class="form-control" value="<?= e($editing['teaching_level'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Chuyên môn</label>
              <input type="text" name="specialty" class="form-control" value="<?= e($editing['specialty'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Tổ chuyên môn</label>
              <input type="text" name="to_chuyen_mon" class="form-control" value="<?= e($editing['to_chuyen_mon'] ?? $editing['pccm_group'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label small">Chức vụ (hành chính)</label>
              <input type="text" name="chuc_vu" class="form-control" value="<?= e($editing['chuc_vu'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label small">Kiêm nhiệm</label>
              <textarea name="kiem_nhiem_text" class="form-control form-control-sm" rows="3"><?= e($kn_text ?? '') ?></textarea></div>
            <div class="col-md-3"><label class="form-label small">Ngày vào ngành</label>
              <input type="date" name="join_date" class="form-control" value="<?= e($editing['join_date'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Bậc</label>
              <input type="text" name="bac" class="form-control" value="<?= e($editing['bac'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Hạng</label>
              <input type="text" name="hang" class="form-control" value="<?= e($editing['hang'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Cấp</label>
              <input type="text" name="cap_luong" class="form-control" value="<?= e($editing['cap_luong'] ?? '') ?>"></div>
            <div class="col-md-1"><label class="form-label small">Hệ số</label>
              <input type="text" name="he_so" class="form-control" value="<?= e($editing['he_so'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label small">Hưởng từ</label>
              <input type="date" name="he_so_from" class="form-control" value="<?= e($editing['he_so_from'] ?? '') ?>"></div>
            <div class="col-md-8"><label class="form-label small">Ghi chú</label>
              <input type="text" name="note" class="form-control" value="<?= e($editing['note'] ?? '') ?>"></div>
            <div class="col-md-4">
              <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_probation" id="isp" <?= !empty($editing['role_flags']['is_probation'])?'checked':'' ?>><label class="form-check-label small" for="isp">Tập sự</label></div>
              <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_principal" id="iht" <?= !empty($editing['role_flags']['is_principal'])?'checked':'' ?>><label class="form-check-label small" for="iht">HT</label></div>
              <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_vice" id="ivc" <?= !empty($editing['role_flags']['is_vice'])?'checked':'' ?>><label class="form-check-label small" for="ivc">PHT</label></div>
              <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="active" id="tact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>><label class="form-check-label small" for="tact">Đang công tác</label></div>
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
<script>document.addEventListener('DOMContentLoaded',function(){
  var input=document.getElementById('teacherPhotoInput'),preview=document.getElementById('teacherPhotoPreview');
  if(input&&preview)input.addEventListener('change',function(){var file=input.files&&input.files[0];if(!file)return;preview.src=URL.createObjectURL(file);preview.style.display='block';});
  <?php if (!empty($editing)): ?>new bootstrap.Modal('#modalTeacher').show();<?php endif; ?>
});</script>
