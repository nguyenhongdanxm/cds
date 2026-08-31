<?php
$page_title = 'Trang chủ';
require_once 'includes/functions.php';
require_once 'includes/features.php';
require_once 'includes/cm_docs.php';
require_login();
$app = get_app_info();
$features = get_app_features();

if (is_logged_in()) {
    $items = cm_dashboard_items();
    $teachers = get_teachers();
    $classes = get_classes();
    $assignments = get_assignments();
    $roles = get_role_assignments();

    $nKh = 0; $nBc = 0; $nCt = 0;
    foreach ($items as $it) {
        if (($it['_group'] ?? '') === 'kh') $nKh++;
        if (($it['_group'] ?? '') === 'bc') $nBc++;
        if (($it['kind'] ?? '') === 'contest' || (($it['section'] ?? '') === 'bc_kythi' && empty($it['parent_id']))) $nCt++;
    }

    [$w0s, $w0e] = cm_week_bounds(0);
    [$w1s, $w1e] = cm_week_bounds(1);

    $thisWeek = [];
    $nextWeek = [];
    $urgent = []; // ≤5 ngày hoặc trong cửa sổ định kỳ
    $upcoming = []; // hạn trong 30 ngày

    foreach ($items as $it) {
        $dl = $it['_deadline'] ?? null;
        $eventDate = $it['date'] ?? '';
        $due = $dl['due_date'] ?? $eventDate;

        if (cm_in_range($eventDate, $w0s, $w0e) || cm_in_range($due, $w0s, $w0e)
            || (!empty($dl['start_date']) && cm_in_range($dl['start_date'], $w0s, $w0e))) {
            $thisWeek[] = $it;
        }
        if (cm_in_range($eventDate, $w1s, $w1e) || cm_in_range($due, $w1s, $w1e)
            || (!empty($dl['start_date']) && cm_in_range($dl['start_date'], $w1s, $w1e))) {
            $nextWeek[] = $it;
        }

        if ($dl) {
            $st = $dl['status'] ?? '';
            if (in_array($st, ['urgent', 'overdue'], true) || (!empty($dl['in_window']))) {
                $urgent[] = $it;
            }
            $days = $dl['days_left'] ?? 999;
            if ($days >= 0 && $days <= 30) {
                $upcoming[] = $it;
            }
        }
    }

    usort($urgent, function ($a, $b) {
        return ($a['_deadline']['days_left'] ?? 99) <=> ($b['_deadline']['days_left'] ?? 99);
    });
    usort($upcoming, function ($a, $b) {
        return ($a['_deadline']['days_left'] ?? 99) <=> ($b['_deadline']['days_left'] ?? 99);
    });

    require_once 'includes/header.php';
    ?>
<style>
.cm-hero{background:linear-gradient(135deg,#1F4E79 0%,#2E6DA4 60%,#3d8fd1 100%);color:#fff;border-radius:16px;padding:1.4rem 1.6rem;margin-bottom:1.25rem;box-shadow:0 8px 28px rgba(31,78,121,.22)}
.cm-hero h1{font-size:1.45rem;font-weight:700;margin:0}
.cm-stat .number{font-size:1.55rem;font-weight:700;color:#1F4E79}
.cm-stat .label{font-size:.8rem;color:#6c757d}
.cm-chip{font-size:.72rem;font-weight:600;border-radius:20px;padding:.2rem .55rem;display:inline-block}
.cm-chip-urgent{background:#f8d7da;color:#58151c}
.cm-chip-soon{background:#fff3cd;color:#664d03}
.cm-chip-ok{background:#d1e7dd;color:#0a3622}
.cm-chip-past{background:#e9ecef;color:#495057}
.cm-chip-win{background:#cfe2ff;color:#084298}
.feed-row{border-bottom:1px solid #eef2f6;padding:.65rem .25rem}
.feed-row:last-child{border-bottom:0}
.feed-row:hover{background:#f8fbff}
.filter-bar{background:#fff;border-radius:12px;padding:.85rem 1rem;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1rem}
</style>

<div class="cm-hero">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <div class="small text-uppercase opacity-75 mb-1">Bảng điều khiển</div>
      <h1><?= e($app['full_name']) ?></h1>
      <div class="opacity-90 small mt-1">Năm học <?= e($app['year']) ?> · Hôm nay <?= date('d/m/Y') ?> · Tuần <?= date('d/m', strtotime($w0s)) ?>–<?= date('d/m', strtotime($w0e)) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <?php if(cds_can_feature('cm.nhaplieu','edit')):?><a href="<?= BASE_URL ?>../tkb_xep.php" class="btn btn-sm btn-success fw-semibold"><i class="bi bi-cpu"></i> Xếp TKB</a><?php endif;?>
      <a href="<?= BASE_URL ?>kehoach.php" class="btn btn-sm btn-light"><i class="bi bi-calendar2-week"></i> Kế hoạch</a>
      <a href="<?= BASE_URL ?>baocao.php" class="btn btn-sm btn-outline-light"><i class="bi bi-file-earmark-text"></i> Báo cáo</a>
      <a href="<?= BASE_URL ?>them.php" class="btn btn-sm btn-warning text-dark fw-semibold"><i class="bi bi-clipboard-check"></i> PCCM</a>
    </div>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3 col-xl-2"><div class="card cm-stat text-center py-2"><div class="number"><?= $nKh ?></div><div class="label">Kế hoạch / TB / chỉ tiêu</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card cm-stat text-center py-2"><div class="number"><?= $nBc ?></div><div class="label">Báo cáo & cuộc thi</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card cm-stat text-center py-2"><div class="number text-danger"><?= count($urgent) ?></div><div class="label">Sắp hạn / trong kỳ (≤5 ngày)</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card cm-stat text-center py-2"><div class="number"><?= count($thisWeek) ?></div><div class="label">Trong tuần này</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card cm-stat text-center py-2"><div class="number"><?= count($teachers) ?></div><div class="label">Giáo viên (PCCM)</div></div></div>
  <div class="col-6 col-md-3 col-xl-2"><div class="card cm-stat text-center py-2"><div class="number"><?= count($assignments) + count($roles) ?></div><div class="label">Mục phân công</div></div></div>
</div>

<div class="filter-bar">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small mb-1 fw-semibold">Tìm kiếm</label>
      <input type="search" id="cmSearch" class="form-control form-control-sm" placeholder="Tiêu đề, nội dung, mục…">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1 fw-semibold">Nhóm</label>
      <select id="cmGroup" class="form-select form-select-sm">
        <option value="">Tất cả</option>
        <option value="kh">Kế hoạch</option>
        <option value="bc">Báo cáo</option>
        <option value="other">Khác</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1 fw-semibold">Mục</label>
      <select id="cmSection" class="form-select form-select-sm">
        <option value="">Tất cả mục</option>
        <option value="kh_vanban">Văn bản kế hoạch</option>
        <option value="kh_thongbao">Thông báo CM</option>
        <option value="kh_chitieu">Chỉ tiêu</option>
        <option value="bc_dinhky">Báo cáo định kỳ</option>
        <option value="bc_tiendo">Tiến độ CT</option>
        <option value="bc_dugio">Dự giờ</option>
        <option value="bc_kythi">Cuộc thi</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1 fw-semibold">Trạng thái hạn</label>
      <select id="cmStatus" class="form-select form-select-sm">
        <option value="">Tất cả</option>
        <option value="urgent">Sắp hết hạn / đang trong kỳ</option>
        <option value="soon">Trong 14 ngày</option>
        <option value="overdue">Quá hạn</option>
        <option value="ok">Còn hạn</option>
      </select>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-danger"><i class="bi bi-exclamation-triangle"></i> Sắp hạn / đang trong kỳ (<?= count($urgent) ?>)</div>
      <div class="card-body p-2" style="max-height:320px;overflow:auto" id="boxUrgent">
        <?php if (!$urgent): ?>
          <div class="text-muted small p-2">Không có mục gấp trong 5 ngày.</div>
        <?php else: foreach ($urgent as $it): echo cm_feed_item_html($it); endforeach; endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-week"></i> Tuần này (<?= date('d/m', strtotime($w0s)) ?>–<?= date('d/m', strtotime($w0e)) ?>)</div>
      <div class="card-body p-2" style="max-height:320px;overflow:auto" id="boxWeek">
        <?php if (!$thisWeek): ?>
          <div class="text-muted small p-2">Không có lịch trong tuần này.</div>
        <?php else: foreach ($thisWeek as $it): echo cm_feed_item_html($it); endforeach; endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-secondary"><i class="bi bi-calendar-plus"></i> Tuần tới</div>
      <div class="card-body p-2" style="max-height:320px;overflow:auto" id="boxNext">
        <?php if (!$nextWeek): ?>
          <div class="text-muted small p-2">Chưa có mục tuần tới.</div>
        <?php else: foreach ($nextWeek as $it): echo cm_feed_item_html($it); endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-list-check"></i> Tất cả lịch & hạn (Kế hoạch + Báo cáo)</span>
    <span class="small opacity-75" id="cmCount"><?= count($items) ?> mục</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle" id="cmTable">
        <thead>
          <tr>
            <th>Hạn / ngày</th>
            <th>Tiêu đề</th>
            <th>Mục</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu kế hoạch/báo cáo. Thêm từ menu tương ứng.</td></tr>
        <?php else: foreach ($items as $it):
          $dl = $it['_deadline'] ?? null;
          $st = $dl['status'] ?? '';
          $days = $dl['days_left'] ?? null;
          $sec = $it['section'] ?? '';
          if ($sec === 'bc_thang') $sec = 'bc_dinhky';
          $q = mb_strtolower(($it['title']??'').' '.($it['content']??'').' '.($it['_label']??''), 'UTF-8');
        ?>
          <tr class="cm-row"
              data-q="<?= e($q) ?>"
              data-group="<?= e($it['_group'] ?? '') ?>"
              data-section="<?= e($sec) ?>"
              data-status="<?= e($st) ?>">
            <td class="small text-nowrap">
              <?php if ($dl): ?>
                <strong><?= e(date('d/m/Y', strtotime($dl['due_date']))) ?></strong>
                <?php if (!empty($dl['window'])): ?><div class="text-muted"><?= e($dl['window']) ?></div><?php endif; ?>
              <?php else: ?>
                <?= e($it['date'] ?? '—') ?>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= e($it['title'] ?? '') ?></strong>
              <?php if (!empty($it['content'])): ?>
                <div class="small text-muted"><?= e(mb_strimwidth($it['content'], 0, 90, '…', 'UTF-8')) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><i class="bi <?= e($it['_icon'] ?? 'bi-folder') ?>"></i> <?= e($it['_label'] ?? '') ?></td>
            <td><?= cm_status_badge($dl) ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= e($it['_href']) ?>"><i class="bi bi-box-arrow-up-right"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-info-circle"></i> Gợi ý nhập hạn</span>
  </div>
  <div class="card-body small text-muted">
    Khi thêm <strong>Báo cáo định kỳ</strong> hoặc <strong>Thông báo</strong>, điền <em>Hạn nộp</em> (ngày cụ thể) hoặc <em>Ngày từ–đến hàng tháng</em> (vd 22–25).
    Trang chủ sẽ tự nhắc khi còn ≤ 5 ngày hoặc đang trong kỳ nộp.
  </div>
</div>

<script>
(function(){
  var search=document.getElementById('cmSearch');
  var group=document.getElementById('cmGroup');
  var section=document.getElementById('cmSection');
  var status=document.getElementById('cmStatus');
  var rows=document.querySelectorAll('#cmTable .cm-row');
  var count=document.getElementById('cmCount');
  function apply(){
    var q=(search.value||'').toLowerCase().trim();
    var g=group.value, s=section.value, st=status.value, n=0;
    rows.forEach(function(tr){
      var ok=true;
      if(q && !(tr.dataset.q||'').includes(q)) ok=false;
      if(g && tr.dataset.group!==g) ok=false;
      if(s && tr.dataset.section!==s) ok=false;
      if(st){
        var rs=tr.dataset.status||'';
        if(st==='urgent' && rs!=='urgent' && rs!=='overdue') ok=false;
        else if(st==='soon' && rs!=='soon' && rs!=='urgent') ok=false;
        else if(st!=='urgent' && st!=='soon' && rs!==st) ok=false;
      }
      tr.style.display=ok?'':'none';
      if(ok) n++;
    });
    if(count) count.textContent=n+' mục';
  }
  [search,group,section,status].forEach(function(el){ if(el) el.addEventListener('input',apply); el && el.addEventListener('change',apply); });
})();
</script>
<?php
    require_once 'includes/footer.php';
    exit;
}

/* —— helpers render (chỉ khi đã load cm_docs) —— */
function cm_status_badge($dl) {
    if (!$dl) return '<span class="cm-chip cm-chip-past">Không hạn</span>';
    $st = $dl['status'] ?? 'ok';
    $days = $dl['days_left'] ?? null;
    if (!empty($dl['in_window'])) {
        return '<span class="cm-chip cm-chip-win">Đang trong kỳ nộp</span>'
            . ($days !== null ? ' <span class="small text-muted">còn '.$days.' ngày</span>' : '');
    }
    if ($st === 'overdue' || $st === 'past') {
        return '<span class="cm-chip cm-chip-past">' . ($st === 'overdue' ? 'Quá hạn' : 'Đã qua') . '</span>';
    }
    if ($st === 'urgent') {
        return '<span class="cm-chip cm-chip-urgent">Còn ' . (int)$days . ' ngày</span>';
    }
    if ($st === 'soon') {
        return '<span class="cm-chip cm-chip-soon">Còn ' . (int)$days . ' ngày</span>';
    }
    return '<span class="cm-chip cm-chip-ok">Còn ' . (int)$days . ' ngày</span>';
}

function cm_feed_item_html(array $it) {
    $dl = $it['_deadline'] ?? null;
    $html = '<div class="feed-row">';
    $html .= '<div class="d-flex justify-content-between gap-2">';
    $html .= '<div><a href="'.e($it['_href']).'" class="fw-semibold text-decoration-none">'.e($it['title']??'').'</a>';
    $html .= '<div class="small text-muted"><i class="bi '.e($it['_icon']??'').'"></i> '.e($it['_label']??'').'</div></div>';
    $html .= '<div class="text-end">'.cm_status_badge($dl).'</div>';
    $html .= '</div></div>';
    return $html;
}

// ===== KHÁCH =====
require_once 'includes/header.php';
?>
<style>
.home-wrap{max-width:640px;margin:2.5rem auto 2rem;text-align:center;padding:0 1rem}
.home-title{font-size:clamp(1.55rem,4vw,2.1rem);font-weight:700;color:#1F4E79;margin:0 0 .65rem;line-height:1.3}
.home-author{color:#495057;font-size:1rem;font-weight:500;margin:0 0 2rem}
.home-author i{color:#1F4E79;margin-right:.35rem}
.home-actions{display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem;margin-bottom:2.25rem}
.home-actions .btn{min-width:180px;font-weight:600;padding:.65rem 1.25rem;border-radius:8px}
</style>
<div class="home-wrap">
  <h1 class="home-title">Ứng dụng Chuyên môn</h1>
  <p class="home-author"><i class="bi bi-person-badge"></i>Thiết kế bởi thầy giáo Nguyễn Hồng Dân</p>
  <div class="home-actions">
    <a href="<?= BASE_URL ?>tracuu.php" class="btn btn-success"><i class="bi bi-search"></i> Tra cứu phân công</a>
    <a href="<?= BASE_URL ?>login.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</a>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
