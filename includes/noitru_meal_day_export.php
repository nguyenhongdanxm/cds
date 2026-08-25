<?php
/**
 * Xuất báo cáo bữa ăn cả ngày dạng SpreadsheetML/HTML, mở trực tiếp bằng Excel.
 * Biến có sẵn: $date, $overview, $rice, $riceKg, $user, $school.
 */
$mealLabels = ['sang' => 'BỮA SÁNG', 'trua' => 'BỮA TRƯA', 'toi' => 'BỮA TỐI'];
$schoolName = $school ?? 'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN';
$fileDate = date('d-m-Y', strtotime($date));

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="bao-cao-bua-an-ca-ngay-' . $fileDate . '.xls"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<style>
body{font-family:"Times New Roman",serif;font-size:12pt;color:#172033}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #9aa9b8;padding:7px;vertical-align:middle}
.no-border td{border:0;padding:2px}.center{text-align:center}.right{text-align:right}
.school{font-weight:700;font-size:13pt}.title{font-weight:700;font-size:18pt;color:#087fbd}
.subtitle{font-weight:700;font-size:13pt}.head{background:#dbeafe;font-weight:700;text-align:center}
.sang{background:#fff7ed}.trua{background:#ecfdf5}.toi{background:#eef2ff}
.total{background:#f1f5f9;font-weight:700}.absent{background:#fff1f2}.rice{background:#fffbeb}
.section{background:#e8eef4;font-weight:700}.muted{color:#64748b;font-style:italic}
</style>
</head>
<body>
<table class="no-border">
  <tr><td class="center school"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></td></tr>
  <tr><td class="center title">BÁO CÁO BỮA ĂN CẢ NGÀY</td></tr>
  <tr><td class="center subtitle">Ngày <?= date('d/m/Y', strtotime($date)) ?></td></tr>
</table>
<br>
<table>
  <tr class="head"><th>Bữa ăn</th><th>Số lớp đã báo</th><th>Tổng học sinh</th><th>Số suất ăn</th><th>Số vắng</th><th>Tỷ lệ ăn</th><th>Gạo (kg)</th></tr>
  <?php $totalStudents = $totalEat = $totalAbsent = 0; foreach ($mealLabels as $mealKey => $label):
    $info = $overview['meals'][$mealKey] ?? [];
    $total = (int)($info['total'] ?? 0); $eat = (int)($info['eat'] ?? 0); $absent = (int)($info['absent'] ?? 0);
    $grams = (float)($rice['settings'][$mealKey . '_grams'] ?? 0); $kg = $eat * $grams / 1000;
    $totalStudents += $total; $totalEat += $eat; $totalAbsent += $absent;
  ?>
  <tr class="<?= $mealKey ?>">
    <td><strong><?= $label ?></strong></td>
    <td class="center"><?= count($info['reported'] ?? []) ?></td>
    <td class="center"><?= $total ?></td>
    <td class="center"><?= $eat ?></td>
    <td class="center"><?= $absent ?></td>
    <td class="center"><?= $total ? number_format($eat * 100 / $total, 1) . '%' : '0%' ?></td>
    <td class="right"><?= number_format($kg, 3, ',', '.') ?></td>
  </tr>
  <?php endforeach; ?>
  <tr class="total"><td>TỔNG CỘNG</td><td></td><td class="center"><?= $totalStudents ?></td><td class="center"><?= $totalEat ?></td><td class="center"><?= $totalAbsent ?></td><td></td><td class="right"><?= number_format($riceKg, 3, ',', '.') ?></td></tr>
</table>
<br>
<table>
  <tr><td colspan="5" class="section">DANH SÁCH HỌC SINH VẮNG ĂN</td></tr>
  <tr class="head"><th>STT</th><th>Bữa ăn</th><th>Mâm/nhóm ăn</th><th>Họ và tên</th><th>Lớp</th></tr>
  <?php $stt = 0; foreach ($mealLabels as $mealKey => $label):
    $absentStudents = $overview['meals'][$mealKey]['absent_students'] ?? [];
    usort($absentStudents, static function ($a, $b) {
        return strnatcasecmp(($a['group'] ?? '') . '|' . ($a['class'] ?? '') . '|' . ($a['name'] ?? ''), ($b['group'] ?? '') . '|' . ($b['class'] ?? '') . '|' . ($b['name'] ?? ''));
    });
    foreach ($absentStudents as $student): $stt++; ?>
      <tr class="absent"><td class="center"><?= $stt ?></td><td><?= $label ?></td><td class="center"><?= htmlspecialchars(trim($student['group'] ?? '') ?: 'Chưa xếp mâm', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($student['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td class="center"><?= htmlspecialchars($student['class'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endforeach;
  endforeach; ?>
  <?php if (!$stt): ?><tr><td colspan="5" class="center muted">Không có học sinh vắng ăn trong ngày.</td></tr><?php endif; ?>
</table>
<br>
<table>
  <tr><td colspan="4" class="section">CHI TIẾT GẠO DỰ KIẾN</td></tr>
  <tr class="head"><th>Bữa ăn</th><th>Số suất</th><th>Định mức (g/HS)</th><th>Lượng gạo (kg)</th></tr>
  <?php foreach (['trua' => 'BỮA TRƯA', 'toi' => 'BỮA TỐI'] as $mealKey => $label):
    $eat = (int)($overview['meals'][$mealKey]['eat'] ?? 0);
    $grams = (float)($rice['settings'][$mealKey . '_grams'] ?? 0);
  ?><tr class="rice"><td><?= $label ?></td><td class="center"><?= $eat ?></td><td class="center"><?= number_format($grams, 0, ',', '.') ?></td><td class="right"><?= number_format($eat * $grams / 1000, 3, ',', '.') ?></td></tr><?php endforeach; ?>
  <tr class="total"><td colspan="3">TỔNG GẠO TRONG NGÀY</td><td class="right"><?= number_format($riceKg, 3, ',', '.') ?> kg</td></tr>
</table>
<br><br>
<table class="no-border">
  <tr><td>Xuất lúc: <?= date('H:i d/m/Y') ?></td><td class="right">Người xuất: <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>
</body>
</html>
<?php exit;
