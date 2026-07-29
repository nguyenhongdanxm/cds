<?php
if (!function_exists('noitru_rice_excel')) {
function noitru_rice_excel(array $usage, array $meta) {
    $filename = $meta['filename'] ?? 'bao-cao-gao.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    $e = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $number = fn($value) => number_format((float)$value, 3, ',', '.');
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><style>
body{font-family:"Times New Roman",serif;color:#172033}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #94a3b8;padding:7px;vertical-align:middle}
.no-border td{border:0}.center{text-align:center}.right{text-align:right}
.school{font-weight:bold;font-size:12pt}.country{font-weight:bold;font-size:12pt}
.title{font-weight:bold;font-size:18pt;color:#0f5e9c}.subtitle{font-style:italic;color:#475569}
.head{background:#0f6fae;color:#fff;font-weight:bold;text-align:center}
.subhead{background:#dbeafe;font-weight:bold;text-align:center}
.total{background:#dcfce7;font-weight:bold}.note{font-size:10pt;color:#475569}
</style></head><body>
<table class="no-border">
  <tr><td class="center school"><?= $e($meta['school'] ?? 'NHÀ TRƯỜNG') ?></td><td class="center country">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM<br><u>Độc lập - Tự do - Hạnh phúc</u></td></tr>
  <tr><td colspan="2" class="center title">BÁO CÁO SỬ DỤNG GẠO BẾP ĂN</td></tr>
  <tr><td colspan="2" class="center subtitle"><?= $e($meta['period'] ?? '') ?></td></tr>
  <tr><td colspan="2">&nbsp;</td></tr>
</table>
<table>
  <tr class="head"><th rowspan="2">STT</th><th rowspan="2">Ngày</th><th colspan="2">Bữa sáng</th><th colspan="2">Bữa trưa</th><th colspan="2">Bữa tối</th><th rowspan="2">Tổng lượt ăn</th><th rowspan="2">Tổng gạo (kg)</th></tr>
  <tr class="subhead"><th>Số HS</th><th>Gạo (kg)</th><th>Số HS</th><th>Gạo (kg)</th><th>Số HS</th><th>Gạo (kg)</th></tr>
  <?php $index=0; foreach ($usage['days'] ?? [] as $date=>$day): $index++; ?>
  <tr>
    <td class="center"><?= $index ?></td><td class="center"><?= $e(date('d/m/Y',strtotime($date))) ?></td>
    <?php foreach (['sang','trua','toi'] as $meal): ?><td class="center"><?= (int)($day[$meal]['students']??0) ?></td><td class="right"><?= $number($day[$meal]['kg']??0) ?></td><?php endforeach; ?>
    <td class="center"><?= (int)($day['students']??0) ?></td><td class="right"><strong><?= $number($day['kg']??0) ?></strong></td>
  </tr>
  <?php endforeach; if (!$index): ?><tr><td colspan="10" class="center">Không có bữa ăn đã chốt trong khoảng thời gian này.</td></tr><?php endif; ?>
  <tr class="total"><td colspan="2" class="center">TỔNG CỘNG</td>
    <?php foreach (['sang','trua','toi'] as $meal): ?><td class="center"><?= (int)($usage['meals'][$meal]['students']??0) ?></td><td class="right"><?= $number($usage['meals'][$meal]['kg']??0) ?></td><?php endforeach; ?>
    <td class="center"><?= (int)($usage['total_students']??0) ?></td><td class="right"><?= $number($usage['total_kg']??0) ?></td>
  </tr>
</table>
<br>
<table>
  <tr class="subhead"><th>Chỉ tiêu</th><th>Giá trị</th></tr>
  <tr><td>Tổng nhập kho trong giai đoạn</td><td class="right"><?= $number($meta['manual_in']??0) ?> kg</td></tr>
  <tr><td>Xuất/điều chỉnh thủ công</td><td class="right"><?= $number($meta['manual_out']??0) ?> kg</td></tr>
  <tr><td>Tiêu thụ tự động theo suất ăn đã chốt</td><td class="right"><?= $number($usage['total_kg']??0) ?> kg</td></tr>
  <tr class="total"><td>Tồn kho tại thời điểm xuất báo cáo</td><td class="right"><?= $number($meta['balance']??0) ?> kg</td></tr>
</table>
<p class="note">Định mức: Sáng <?= $e($meta['sang_grams']??0) ?> g/HS; Trưa <?= $e($meta['trua_grams']??0) ?> g/HS; Tối <?= $e($meta['toi_grams']??0) ?> g/HS.</p>
<p class="note">Xuất lúc: <?= $e($meta['exported_at']??'') ?> · Người xuất: <?= $e($meta['exported_by']??'') ?></p>
</body></html>
<?php
    exit;
}}
