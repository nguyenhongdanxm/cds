<?php
/** Tab đồng bộ PCCM — dùng trong csdl.php */
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card card-soft mb-3">
      <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-arrow-left-right text-primary"></i> Đồng bộ 2 chiều với Phân công chuyên môn (PCCM)</h5>
        <?php if (!$sync_info['ready']): ?>
          <div class="alert alert-warning">
            <strong>Chưa kết nối được thư mục data PCCM.</strong>
            <div class="small mt-2">Path: <code><?= e($sync_info['path'] ?: '(trống)') ?></code></div>
          </div>
        <?php else: ?>
          <div class="alert alert-success py-2 small mb-3">
            <i class="bi bi-check-circle"></i> Data PCCM: <code><?= e($sync_info['path']) ?></code>
            · GV <?= $sync_info['teachers']?'✓':'—' ?>
            · meta <?= $sync_info['meta']?'✓':'—' ?>
            · lớp <?= $sync_info['classes']?'✓':'—' ?>
            · kiêm nhiệm <?= $sync_info['roles']?'✓':'—' ?>
            <?php if (!empty($sync_info['version'])): ?>· phiên bản <code><?= e($sync_info['version']) ?></code><?php endif; ?>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100 bg-light">
                <div class="fw-bold mb-1"><i class="bi bi-download text-success"></i> PCCM → CDS</div>
                <p class="small text-muted mb-3">Kéo GV, lớp, tổ, kiêm nhiệm.</p>
                <form method="post" onsubmit="return confirm('Kéo từ PCCM vào CDS?')">
                  <input type="hidden" name="action" value="sync_from_pccm">
                  <button class="btn btn-success w-100" type="submit"><i class="bi bi-cloud-download"></i> Kéo từ PCCM</button>
                </form>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100 bg-light">
                <div class="fw-bold mb-1"><i class="bi bi-upload text-primary"></i> CDS → PCCM</div>
                <p class="small text-muted mb-3">Đẩy GV, lớp, tổ, kiêm nhiệm. Không ghi đè tiết dạy.</p>
                <form method="post" onsubmit="return confirm('Đẩy CDS sang PCCM?')">
                  <input type="hidden" name="action" value="sync_to_pccm">
                  <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cloud-upload"></i> Đẩy sang PCCM</button>
                </form>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card card-soft mb-3">
      <div class="card-body">
        <h6>Hiện trạng CDS</h6>
        <ul class="mb-0">
          <li>Giáo viên: <strong><?= (int)$stats['teachers'] ?></strong></li>
          <li>Lớp: <strong><?= (int)$stats['classes'] ?></strong></li>
          <li>Học sinh: <strong><?= (int)$stats['students'] ?></strong></li>
        </ul>
      </div>
    </div>
    <div class="card card-soft">
      <div class="card-body">
        <h6>Thứ tự</h6>
        <ol class="small mb-0 ps-3">
          <li>PCCM → CDS</li>
          <li>Nhập CSV hồ sơ (tab GV / Lớp / HS)</li>
          <li>Xuất mẫu chuẩn cho module khác</li>
        </ol>
      </div>
    </div>
  </div>
</div>
