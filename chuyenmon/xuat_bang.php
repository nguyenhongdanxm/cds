<?php
require_once 'includes/functions.php';
require_login();

$vid = $_GET['v'] ?? get_active_version_id();
$ver = get_version($vid);
if (!$ver) { $vid = get_active_version_id(); $ver = get_version($vid); }

$mode = $_GET['mode'] ?? ''; // '' | gv | to | all
$to = $_GET['to'] ?? ''; // khxh | khtn
$level = $_GET['level'] ?? ''; // THCS | THPT | ''

$title_lan = $ver['name'] ?? 'Phân công';
$date_str = $ver['date'] ?? date('Y-m-d');
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_str, $m)) {
    $date_display = $m[3] . '/' . $m[2] . '/' . $m[1];
} else {
    $date_display = $date_str;
}

// URL tuyệt đối trong thư mục app (tránh base href / trang chủ gốc)
function export_url($params = []) {
    $q = http_build_query($params);
    return BASE_URL . 'xuat_bang.php' . ($q ? '?' . $q : '');
}

// ===== Trang chọn loại xuất =====
if ($mode === '') {
    $page_title = 'Xuất bảng';
    require_once 'includes/header.php';
    $versions = get_versions();
    ?>
<div class="mb-3">
<h3 class="mb-1"><i class="bi bi-printer"></i> Xuất bảng</h3>
<p class="text-muted mb-0">Chọn loại bảng cần in / lưu PDF · Bố cục chuẩn hành chính</p>
</div>

<div class="card mb-3">
<div class="card-body">
<label class="form-label fw-semibold">Phiên bản phân công</label>
<select id="selVer" class="form-select" style="max-width:420px">
<?php foreach ($versions as $v): ?>
<option value="<?= e($v['id']) ?>" <?= $v['id']===$vid?'selected':'' ?>><?= e($v['name']) ?> (<?= e($v['date'] ?? '') ?>)</option>
<?php endforeach; ?>
</select>
</div></div>

<div class="row g-3">
<div class="col-md-4">
<div class="card h-100">
<div class="card-body">
<div class="mb-2"><i class="bi bi-people fs-3 text-primary"></i></div>
<h5 class="fw-bold">Danh sách giáo viên</h5>
<p class="text-muted small">STT, họ tên, chuyên môn, tổ, cấp, tập sự</p>
<a href="<?= e(export_url(['mode'=>'gv','v'=>$vid])) ?>" class="btn btn-primary btn-sm go-export" data-mode="gv" target="_blank" rel="noopener">Xuất danh sách GV</a>
</div></div></div>

<div class="col-md-4">
<div class="card h-100">
<div class="card-body">
<div class="mb-2"><i class="bi bi-diagram-3 fs-3 text-success"></i></div>
<h5 class="fw-bold">Phân công theo tổ</h5>
<p class="text-muted small">Bảng phân công lọc theo Tổ KHXH hoặc Tổ KHTN</p>
<div class="d-flex flex-wrap gap-2">
<a href="<?= e(export_url(['mode'=>'to','to'=>'khxh','v'=>$vid])) ?>" class="btn btn-success btn-sm go-export" data-mode="to" data-to="khxh" target="_blank" rel="noopener">Tổ KHXH</a>
<a href="<?= e(export_url(['mode'=>'to','to'=>'khtn','v'=>$vid])) ?>" class="btn btn-success btn-sm go-export" data-mode="to" data-to="khtn" target="_blank" rel="noopener">Tổ KHTN</a>
</div>
</div></div></div>

<div class="col-md-4">
<div class="card h-100">
<div class="card-body">
<div class="mb-2"><i class="bi bi-building fs-3 text-warning"></i></div>
<h5 class="fw-bold">Phân công cả trường</h5>
<p class="text-muted small">Toàn bộ GV · có thể lọc THCS / THPT</p>
<div class="d-flex flex-wrap gap-2">
<a href="<?= e(export_url(['mode'=>'all','v'=>$vid])) ?>" class="btn btn-warning btn-sm text-dark go-export" data-mode="all" target="_blank" rel="noopener">Cả trường</a>
<a href="<?= e(export_url(['mode'=>'all','level'=>'THCS','v'=>$vid])) ?>" class="btn btn-outline-warning btn-sm go-export" data-mode="all" data-level="THCS" target="_blank" rel="noopener">THCS</a>
<a href="<?= e(export_url(['mode'=>'all','level'=>'THPT','v'=>$vid])) ?>" class="btn btn-outline-warning btn-sm go-export" data-mode="all" data-level="THPT" target="_blank" rel="noopener">THPT</a>
</div>
</div></div></div>
</div>

<script>
(function(){
  var base = <?= json_encode(rtrim(BASE_URL, '/') . '/xuat_bang.php') ?>;
  var sel = document.getElementById('selVer');
  function rebuild() {
    var v = sel ? sel.value : '';
    document.querySelectorAll('.go-export').forEach(function(a){
      var u = base + '?mode=' + encodeURIComponent(a.getAttribute('data-mode') || 'all') + '&v=' + encodeURIComponent(v);
      if (a.getAttribute('data-to')) u += '&to=' + encodeURIComponent(a.getAttribute('data-to'));
      if (a.getAttribute('data-level')) u += '&level=' + encodeURIComponent(a.getAttribute('data-level'));
      a.setAttribute('href', u);
    });
  }
  if (sel) sel.addEventListener('change', rebuild);
  rebuild();
})();
</script>
<?php require_once 'includes/footer.php'; exit;
}

// ===== Dữ liệu xuất =====
$rows = get_export_rows($vid);

if ($mode === 'to') {
    if ($to === 'khxh') {
        $rows = array_values(array_filter($rows, function($r) {
            $f = get_teacher_flags($r['name']);
            return !empty($f['khxh']);
        }));
        $sub_title = 'TỔ KHOA HỌC XÃ HỘI';
    } elseif ($to === 'khtn') {
        $rows = array_values(array_filter($rows, function($r) {
            $f = get_teacher_flags($r['name']);
            return !empty($f['khtn']);
        }));
        $sub_title = 'TỔ KHOA HỌC TỰ NHIÊN';
    } else {
        $sub_title = '';
    }
} elseif ($mode === 'gv') {
    $sub_title = 'DANH SÁCH GIÁO VIÊN';
} else {
    $sub_title = 'CẢ TRƯỜNG';
    if ($level) {
        $rows = array_values(array_filter($rows, function($r) use ($level) {
            $f = get_teacher_flags($r['name']);
            if ($level === 'THCS') return !empty($f['thcs']);
            if ($level === 'THPT') return !empty($f['thpt']);
            return true;
        }));
        $sub_title .= ' – ' . $level;
    }
}

function cm_text($name) {
    $cm = get_teacher_chuyen_mon($name);
    return $cm ? implode(', ', $cm) : '';
}
function group_text($name) {
    $f = get_teacher_flags($name);
    $g = [];
    if (!empty($f['khxh'])) $g[] = 'KHXH';
    if (!empty($f['khtn'])) $g[] = 'KHTN';
    return implode(', ', $g);
}

$back_url = export_url();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($mode==='gv' ? 'Danh sách giáo viên' : 'Bảng phân công') ?> – <?= htmlspecialchars($title_lan) ?></title>
<style>
@page { size: A4 landscape; margin: 10mm 12mm; }
*{box-sizing:border-box}
body{font-family:'Times New Roman',Times,serif;font-size:12pt;color:#000;margin:0;padding:12px;background:#fff}
.no-print{margin-bottom:14px;font-family:system-ui,sans-serif;font-size:14px}
.no-print a,.no-print button{margin-right:8px}
.no-print a{color:#1F4E79;text-decoration:none;font-weight:600}
.no-print button{padding:6px 14px;cursor:pointer;background:#1F4E79;color:#fff;border:none;border-radius:6px}
.header-table{width:100%;border:none;margin-bottom:6px}
.header-table td{border:none;vertical-align:top;padding:0}
.header-left{text-align:center;width:38%;line-height:1.35}
.header-left .so{text-transform:uppercase;font-size:11pt}
.header-left .truong{font-weight:bold;text-transform:uppercase;font-size:12pt;text-decoration:underline;text-underline-offset:3px}
.header-right{text-align:center;width:38%;line-height:1.35}
.header-right .qh{text-transform:uppercase;font-size:11pt;font-weight:bold}
.header-right .dl{font-style:italic;font-size:11pt}
.main-title{text-align:center;margin:14px 0 10px}
.main-title h1{font-size:15pt;margin:0 0 4px;text-transform:uppercase;letter-spacing:.4px}
.main-title .sub{font-size:12pt}
table.data{width:100%;border-collapse:collapse;font-size:11pt}
table.data th,table.data td{border:1px solid #000;padding:5px 6px;vertical-align:top}
table.data th{background:#f2f2f2;text-align:center;font-weight:bold}
table.data td.num{text-align:center;white-space:nowrap}
table.data td.right{text-align:right;white-space:nowrap}
table.data td.center{text-align:center}
.sign{display:flex;justify-content:space-between;margin-top:28px;page-break-inside:avoid}
.sign .box{width:42%;text-align:center}
.sign .muted{font-style:italic;font-size:11pt}
.sign strong{font-size:12pt}
@media print{
  .no-print{display:none!important}
  body{padding:0}
}
</style>
</head>
<body>

<div class="no-print">
  <a href="<?= htmlspecialchars($back_url) ?>">← Chọn loại xuất</a>
  <button type="button" onclick="window.print()">🖨 In / Lưu PDF</button>
  <span style="color:#555">Phiên bản: <strong><?= htmlspecialchars($title_lan) ?></strong></span>
</div>

<table class="header-table">
<tr>
  <td class="header-left">
    <div class="so"><?= htmlspecialchars(SCHOOL_SO) ?></div>
    <div class="truong"><?= htmlspecialchars(SCHOOL_NAME) ?></div>
  </td>
  <td></td>
  <td class="header-right">
    <div class="qh">Cộng hòa xã hội chủ nghĩa Việt Nam</div>
    <div class="dl">Độc lập – Tự do – Hạnh phúc</div>
    <div style="margin-top:4px">————————</div>
  </td>
</tr>
</table>

<?php if ($mode === 'gv'): ?>

<div class="main-title">
  <h1>DANH SÁCH GIÁO VIÊN</h1>
  <div class="sub"><?= htmlspecialchars(SCHOOL_NAME) ?></div>
  <div class="sub">Năm học / Phiên bản: <?= htmlspecialchars($title_lan) ?> (<?= htmlspecialchars($date_display) ?>)</div>
</div>

<table class="data">
<thead>
<tr>
  <th style="width:5%">STT</th>
  <th style="width:20%">Họ và tên</th>
  <th style="width:22%">Chuyên môn</th>
  <th style="width:12%">Tổ</th>
  <th style="width:12%">Cấp dạy</th>
  <th style="width:10%">Tập sự</th>
  <th style="width:10%">Định mức</th>
  <th style="width:9%">Ghi chú</th>
</tr>
</thead>
<tbody>
<?php $stt=0; foreach ($rows as $r):
  $stt++;
  $f = get_teacher_flags($r['name']);
?>
<tr>
  <td class="num"><?= $stt ?></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td><?= htmlspecialchars(cm_text($r['name'])) ?></td>
  <td class="center"><?= htmlspecialchars(group_text($r['name'])) ?></td>
  <td class="center"><?= htmlspecialchars($r['level'] ?? '') ?></td>
  <td class="center"><?= !empty($f['tap_su']) ? 'x' : '' ?></td>
  <td class="num"><?= (int)($r['quota'] ?? get_quota($r['name'])) ?></td>
  <td></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8" class="center">Chưa có dữ liệu</td></tr><?php endif; ?>
</tbody>
</table>

<?php else: ?>

<div class="main-title">
  <h1>BẢNG PHÂN CÔNG CHUYÊN MÔN</h1>
  <div class="sub"><?= htmlspecialchars(mb_strtoupper($title_lan, 'UTF-8')) ?></div>
  <div class="sub">(Ngày <?= htmlspecialchars($date_display) ?>)</div>
  <?php if (!empty($sub_title)): ?><div class="sub"><strong><?= htmlspecialchars($sub_title) ?></strong></div><?php endif; ?>
</div>

<table class="data">
<thead>
<tr>
  <th style="width:4%">STT</th>
  <th style="width:14%">Họ và tên</th>
  <th style="width:12%">Chuyên môn</th>
  <th style="width:26%">Phân công dạy</th>
  <th style="width:16%">Kiêm nhiệm</th>
  <th style="width:7%">Tiết dạy</th>
  <th style="width:7%">Tiết KN</th>
  <th style="width:7%">Tổng</th>
  <th style="width:7%">Định mức</th>
</tr>
</thead>
<tbody>
<?php $stt=0; foreach ($rows as $r):
  $stt++;
?>
<tr>
  <td class="num"><?= $stt ?></td>
  <td><?= htmlspecialchars($r['name']) ?><?php if (!empty($r['level'])): ?> <small>(<?= htmlspecialchars($r['level']) ?>)</small><?php endif; ?></td>
  <td><?= htmlspecialchars(cm_text($r['name'])) ?></td>
  <td><?= htmlspecialchars($r['mon_day'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['kiem_nhiem'] ?? '') ?></td>
  <td class="right"><?= number_format($r['day'] ?? 0, 1) ?></td>
  <td class="right"><?= number_format($r['role'] ?? 0, 1) ?></td>
  <td class="right"><strong><?= number_format($r['total'] ?? 0, 1) ?></strong></td>
  <td class="num"><?= (int)($r['quota'] ?? 0) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="9" class="center">Chưa có dữ liệu</td></tr><?php endif; ?>
</tbody>
</table>

<?php endif; ?>

<div class="sign">
  <div class="box">
    <div class="muted">Ngày ...... tháng ...... năm ......</div>
    <div><strong>NGƯỜI LẬP</strong></div>
    <div class="muted">(Ký, ghi rõ họ tên)</div>
  </div>
  <div class="box">
    <div class="muted">Ngày ...... tháng ...... năm ......</div>
    <div><strong>HIỆU TRƯỞNG</strong></div>
    <div class="muted">(Ký, ghi rõ họ tên)</div>
  </div>
</div>

</body>
</html>
