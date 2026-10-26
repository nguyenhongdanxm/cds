<?php if(!lb_is_admin())return; ?>
<div class="lb-card mb-3" id="lbBulkDeletePanel" data-csrf="<?=e($csrf)?>" data-week="<?=e($weekKey)?>">
<strong>Quản trị: xóa hàng loạt tiết đã ký</strong><p class="small mb-2">Tích ở cột “Xóa” của các tiết cần xóa (tối đa 200 tiết/lần). Chỉ xóa dữ liệu sổ và chữ ký của tiết được chọn; tiết trong thời khóa biểu vẫn được giữ.</p>
<label for="lbBulkDeleteReason" class="form-label">Lý do xóa</label><input id="lbBulkDeleteReason" class="form-control mb-2" maxlength="1000" placeholder="Nhập lý do để lưu lịch sử thao tác">
<button type="button" id="lbBulkDeleteButton" class="btn btn-outline-danger" disabled>Xóa 0 tiết đã chọn</button><span id="lbBulkDeleteStatus" class="small ms-2" role="status" aria-live="polite"></span>
</div><script src="<?=e(BASE_URL)?>../assets/lesson-book-bulk-delete.js?v=20261026-1" defer></script>
