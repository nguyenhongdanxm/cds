<?php
/** Fragment: card đồng bộ QLHS — include từ csdl.php tab=sync */
$qlhs_info = function_exists('csdl_sync_qlhs_info') ? csdl_sync_qlhs_info() : ['ready'=>false,'url'=>'','school_id'=>'','ping_error'=>'Chưa load csdl_qlhs','qlhs_app'=>''];
?>
<div class="card card-soft mb-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="bi bi-building text-danger"></i> Đồng bộ với Quản lý học sinh (QLHS)</h5>
    <?php if (empty($qlhs_info['ready'])): ?>
      <div class="alert alert-warning mb-0">
        <strong>Chưa kết nối được Supabase QLHS.</strong>
        <div class="small mt-1"><?= e($qlhs_info['ping_error'] ?: 'Kiểm tra SUPABASE_URL / KEY hoặc RLS (anon cần SELECT schools, classes, students).') ?></div>
        <div class="small mt-1">URL: <code><?= e($qlhs_info['url'] ?: '—') ?></code></div>
        <div class="small mt-2">Nếu RLS chặn, thêm policy đọc hoặc điền <code>QLHS_SCHOOL_ID</code> trong <code>config.php</code>.</div>
      </div>
    <?php else: ?>
      <div class="alert alert-success py-2 small mb-3">
        <i class="bi bi-check-circle"></i> Supabase OK
        <?php if (!empty($qlhs_info['school_id'])): ?> · school <code><?= e(substr($qlhs_info['school_id'], 0, 8)) ?>…</code><?php endif; ?>
        <?php if (!empty($qlhs_info['qlhs_app'])): ?> · <a href="<?= e($qlhs_info['qlhs_app']) ?>" target="_blank" rel="noopener">Mở QLHS</a><?php endif; ?>
      </div>
      <div class="border rounded-3 p-3 bg-light">
        <div class="fw-bold mb-1"><i class="bi bi-download text-danger"></i> QLHS → CDS</div>
        <p class="small text-muted mb-3">Kéo <strong>lớp</strong> và <strong>học sinh</strong> (mã HS, GT, ngày sinh, nội trú, SĐT, dân tộc, phòng KTX…).</p>
        <form method="post" onsubmit="return confirm('Kéo lớp + học sinh từ QLHS vào CDS?')">
          <input type="hidden" name="action" value="sync_from_qlhs">
          <button class="btn btn-danger w-100" type="submit"><i class="bi bi-cloud-download"></i> Kéo từ QLHS</button>
        </form>
      </div>
      <p class="small text-muted mt-3 mb-0">Ánh xạ: Supabase <code>classes</code>/<code>students</code> → CDS JSON. Khớp HS theo <strong>mã</strong>; lớp theo <strong>tên</strong>.</p>
    <?php endif; ?>
  </div>
</div>
