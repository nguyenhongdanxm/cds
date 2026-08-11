<?php
/** Nhập và in Biên bản trực nội trú hằng ngày theo mẫu hành chính A4. */
$reportDate = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) $reportDate = date('Y-m-d');
$report = noitru_duty_report_for_date($reportDate) ?? [];
$dutySettings = noitru_duty_settings();
$startTime = (string)($dutySettings['start_time'] ?? '06:00');
$endTime = (string)($dutySettings['end_time'] ?? '06:00');
$nextDate = date('Y-m-d', strtotime($reportDate . ' +1 day'));
$yearLabel = (string)(csdl_year_current()['label'] ?? SCHOOL_YEAR);

$dutyRows = array_values(array_filter(noitru_duty_all(), fn($row) => ($row['date'] ?? '') === $reportDate));
$nextDutyRows = array_values(array_filter(noitru_duty_all(), fn($row) => ($row['date'] ?? '') === $nextDate));
$dutyNames = array_values(array_unique(array_filter(array_map(fn($row) => trim((string)($row['teacher_name'] ?? '')), $dutyRows))));
$nextDutyNames = array_values(array_unique(array_filter(array_map(fn($row) => trim((string)($row['teacher_name'] ?? '')), $nextDutyRows))));
$manager = noitru_duty_manager_for_date($reportDate) ?? [];
$managerNames = array_values(array_filter((array)($manager['teacher_names'] ?? [])));
$participants = array_values(array_unique(array_merge($dutyNames, $managerNames)));

$attendance = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
$attendanceByShift = [];
$absentDetails = [];
$studentMap = [];
foreach (noitru_attendance_students_all() as $student) $studentMap[(string)($student['id'] ?? '')] = $student;
foreach (noitru_att_all() as $row) {
    if (($row['date'] ?? '') !== $reportDate) continue;
    $status = (string)($row['status'] ?? 'present');
    if (isset($attendance[$status])) $attendance[$status]++;
    $shift = trim((string)($row['shift'] ?? '')) ?: 'Khác';
    if (!isset($attendanceByShift[$shift])) $attendanceByShift[$shift] = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    if (isset($attendanceByShift[$shift][$status])) $attendanceByShift[$shift][$status]++;
    if (in_array($status, ['absent','late','excused'], true)) {
        $student = $studentMap[(string)($row['student_id'] ?? '')] ?? [];
        $absentDetails[] = [
            'name'=>$student['name'] ?? ($row['student_name'] ?? 'Không rõ'),
            'class'=>$student['class_name'] ?? ($row['class_name'] ?? ''),
            'status'=>$status,
            'reason'=>trim((string)($row['reason'] ?? '')),
        ];
    }
}
$shiftLabels = ['the_duc_sang'=>'Thể dục sáng','sang'=>'Sáng','trua'=>'Trưa','chieu'=>'Chiều','toi'=>'Tối','hoc_toi'=>'Học tối','dot_xuat'=>'Đột xuất'];
$statusLabels = ['absent'=>'Vắng','late'=>'Muộn','excused'=>'Vắng có phép'];
$location = (string)($report['location'] ?? 'Pà Vầy Sủ');
$shiftLabel = (string)($report['shift_label'] ?? ($startTime . ' ngày ' . date('d/m/Y',strtotime($reportDate)) . ' đến ' . $endTime . ' ngày ' . date('d/m/Y',strtotime($nextDate))));
$field = fn($key) => (string)($report[$key] ?? '');
?>

<style>
.duty-report-toolbar{display:flex;justify-content:space-between;align-items:end;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.duty-report-editor{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.9fr);gap:1rem}.duty-report-form,.duty-report-preview-wrap{border:1px solid #dce4eb;border-radius:18px;background:#fff;box-shadow:0 5px 18px rgba(15,23,42,.05)}.duty-report-form{padding:1rem}.duty-report-form textarea{min-height:86px;resize:vertical}.duty-report-preview-wrap{padding:1rem;overflow:auto}.duty-report-paper{box-sizing:border-box;width:210mm;min-height:297mm;margin:0 auto;padding:20mm 15mm 20mm 20mm;background:#fff;color:#000;font-family:"Times New Roman",serif;font-size:13pt;line-height:1.25;box-shadow:0 3px 18px rgba(15,23,42,.16)}.report-national{display:grid;grid-template-columns:40% 60%;gap:8mm;text-align:center;font-weight:700}.report-national p{margin:0}.report-national .underline{display:inline-block;border-bottom:1px solid #000;padding-bottom:2px}.report-place{text-align:right;font-style:italic;margin:8mm 0 5mm}.duty-report-paper h1{margin:0;text-align:center;font-size:15pt;font-weight:700}.report-year{text-align:center;font-weight:700;margin:1mm 0 7mm}.report-section{margin:3mm 0}.report-section-title{font-weight:700}.report-subtitle{margin-top:2.5mm;font-weight:700}.report-text{white-space:pre-wrap;text-align:justify}.report-muted{font-style:italic}.report-attendance{width:100%;margin:2mm 0;border-collapse:collapse;font-size:12pt}.report-attendance th,.report-attendance td{border:1px solid #000;padding:1.5mm;text-align:center}.report-signatures{width:100%;margin-top:5mm;border-collapse:collapse;table-layout:fixed}.report-signatures td{height:62mm;border:1px solid #000;padding:2mm;vertical-align:top}.report-signatures strong{display:block;text-align:center}.report-sign-name{margin-top:12mm}.report-empty{color:#555;font-style:italic}@media(max-width:1200px){.duty-report-editor{grid-template-columns:1fr}.duty-report-preview-wrap{padding:.5rem}.duty-report-paper{transform-origin:top left}}@media print{@page{size:A4 portrait;margin:20mm 15mm 20mm 20mm}body{background:#fff!important}.nt-sidebar,.nt-bottom-nav,.nt-page-head,.duty-report-toolbar,.duty-report-form,.nt-sheet,.nt-sheet-backdrop{display:none!important}.nt-main,.nt-content,.duty-report-editor,.duty-report-preview-wrap{display:block!important;margin:0!important;padding:0!important;border:0!important;box-shadow:none!important}.duty-report-paper{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none;font-size:13pt}.report-signatures td{height:62mm}}
</style>

<div class="duty-report-toolbar">
  <div><h4 class="mb-1"><i class="bi bi-file-earmark-text text-info"></i> Biên bản trực nội trú</h4><div class="text-muted small">Tự động tổng hợp lịch trực và điểm danh trong ngày; nội dung nhận xét do người trực nhập.</div></div>
  <form method="get" class="d-flex gap-2 align-items-end"><input type="hidden" name="tab" value="duty_report"><div><label class="form-label small mb-1">Ngày trực</label><input type="date" name="date" class="form-control" value="<?= e($reportDate) ?>"></div><button class="btn btn-outline-info"><i class="bi bi-search"></i> Xem</button><button class="btn btn-info text-white" type="button" onclick="window.print()"><i class="bi bi-printer"></i> In / Xuất PDF</button><button class="btn btn-success" type="button" id="saveDutyDrive"><i class="bi bi-google"></i> Lưu Drive</button></form>
</div>
<script>
document.getElementById('saveDutyDrive')?.addEventListener('click',async function(){
  const button=this,old=button.innerHTML,paper=document.querySelector('.duty-report-paper');
  const styles=[...document.querySelectorAll('style')].map(node=>node.textContent).join('\n');
  const html='<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Biên bản trực <?=e($reportDate)?></title><style>'+styles+'</style></head><body>'+paper.outerHTML+'</body></html>';
  const data=new FormData();data.append('drive_api','save');data.append('csrf','<?=e(cds_drive_csrf_token())?>');data.append('type','duty_reports');data.append('name','bien-ban-truc-<?=e($reportDate)?>.html');data.append('file',new Blob([html],{type:'text/html'}),'document.html');
  button.disabled=true;button.innerHTML='Đang lưu…';try{const response=await fetch('<?=e(BASE_URL)?>admin.php',{method:'POST',body:data});const result=await response.json();alert(result.ok?'Đã lưu biên bản lên Google Drive.':(result.message||'Không lưu được lên Drive.'));}catch(error){alert('Không kết nối được máy chủ để lưu Drive.');}finally{button.disabled=false;button.innerHTML=old;}
});
</script>

<div class="duty-report-editor">
  <form method="post" class="duty-report-form">
    <input type="hidden" name="action" value="duty_report_save"><input type="hidden" name="date" value="<?= e($reportDate) ?>">
    <div class="row g-3"><div class="col-md-5"><label class="form-label">Địa điểm</label><input class="form-control" name="location" value="<?= e($location) ?>"></div><div class="col-md-7"><label class="form-label">Thời gian/ca trực</label><input class="form-control" name="shift_label" value="<?= e($shiftLabel) ?>"></div></div>
    <hr><label class="form-label fw-semibold">3.2. Tình hình sinh hoạt và kỷ luật</label><textarea class="form-control mb-3" name="discipline" placeholder="Giờ thức dậy, tự học buổi tối, giờ tắt đèn…"><?= e($field('discipline')) ?></textarea>
    <label class="form-label fw-semibold">Ý thức vệ sinh</label><textarea class="form-control mb-3" name="hygiene" placeholder="Phòng ở, khu vệ sinh, nhà ăn chung…"><?= e($field('hygiene')) ?></textarea>
    <label class="form-label fw-semibold">An ninh, trật tự, an toàn</label><textarea class="form-control mb-3" name="safety" placeholder="Tình hình an ninh, trật tự khu nội trú…"><?= e($field('safety')) ?></textarea>
    <label class="form-label fw-semibold">Sức khỏe và y tế</label><textarea class="form-control mb-3" name="health" placeholder="Ốm đau, tai nạn, xử lý, cấp thuốc hoặc đưa đi viện…"><?= e($field('health')) ?></textarea>
    <label class="form-label fw-semibold">3.3. Sự việc phát sinh/vấn đề tồn đọng</label><textarea class="form-control mb-3" name="incidents" placeholder="Cơ sở vật chất, vi phạm nội quy và đề xuất xử lý…"><?= e($field('incidents')) ?></textarea>
    <label class="form-label fw-semibold">Ý kiến nhận xét của người trực</label><textarea class="form-control mb-3" name="assessment" placeholder="Đánh giá chung ca trực…"><?= e($field('assessment')) ?></textarea>
    <label class="form-label fw-semibold">Bàn giao ca sau</label><textarea class="form-control mb-3" name="handover" placeholder="Nội dung cần nhắc nhở ca tiếp theo…"><?= e($field('handover')) ?></textarea>
    <button class="btn btn-info text-white w-100" <?= !$canEditCurrent?'disabled':'' ?>><i class="bi bi-floppy"></i> Lưu nội dung biên bản</button>
  </form>

  <div class="duty-report-preview-wrap">
    <article class="duty-report-paper">
      <div class="report-national"><div><p>SỞ GD&amp;ĐT TUYÊN QUANG</p><p>TRƯỜNG PTDT NỘI TRÚ<br>THCS&amp;THPT XÍN MẦN</p></div><div><p>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p><p><span class="underline">Độc lập - Tự do - Hạnh phúc</span></p></div></div>
      <div class="report-place"><?= e($location) ?>, ngày <?= (int)date('d',strtotime($reportDate)) ?> tháng <?= (int)date('m',strtotime($reportDate)) ?> năm <?= e(date('Y',strtotime($reportDate))) ?></div>
      <h1>BIÊN BẢN TRỰC NỘI TRÚ HẰNG NGÀY</h1><div class="report-year">Năm học <?= e($yearLabel) ?></div>
      <div class="report-section"><span class="report-section-title">1. Thời gian và địa điểm:</span> <?= e($shiftLabel) ?>, tại <?= e($location) ?>.</div>
      <div class="report-section"><span class="report-section-title">2. Thành phần trực:</span> <?= e(implode(', ',$participants)) ?: '<span class="report-empty">Chưa có dữ liệu phân công ngày trực</span>' ?></div>
      <div class="report-section"><div class="report-section-title">3. Nội dung</div><div class="report-subtitle">3.1. Dữ liệu các lần điểm danh</div>
        <?php if($attendanceByShift): ?><table class="report-attendance"><thead><tr><th>Lần điểm danh</th><th>Có mặt</th><th>Vắng</th><th>Muộn</th><th>Có phép</th></tr></thead><tbody><?php foreach($attendanceByShift as $shift=>$counts): ?><tr><td><?= e($shiftLabels[$shift]??$shift) ?></td><td><?= $counts['present'] ?></td><td><?= $counts['absent'] ?></td><td><?= $counts['late'] ?></td><td><?= $counts['excused'] ?></td></tr><?php endforeach; ?><tr><th>Tổng</th><th><?= $attendance['present'] ?></th><th><?= $attendance['absent'] ?></th><th><?= $attendance['late'] ?></th><th><?= $attendance['excused'] ?></th></tr></tbody></table><?php else: ?><div class="report-empty">Chưa có dữ liệu điểm danh trong ngày.</div><?php endif; ?>
        <?php if($absentDetails): ?><div class="report-text"><strong>Học sinh cần lưu ý:</strong> <?php foreach($absentDetails as $index=>$item): ?><?= $index?', ':'' ?><?= e($item['name']) ?> (<?= e($item['class']) ?> – <?= e($statusLabels[$item['status']]??$item['status']) ?><?= $item['reason']!==''?' – '.e($item['reason']):'' ?>)<?php endforeach; ?>.</div><?php endif; ?>
        <div class="report-subtitle">3.2. Tình hình sinh hoạt và kỷ luật</div><div class="report-text"><?= e($field('discipline')) ?: '<span class="report-empty">Chưa nhập nhận xét.</span>' ?></div>
        <div class="report-text"><strong>Ý thức vệ sinh:</strong> <?= e($field('hygiene')) ?: '<span class="report-empty">Chưa nhập.</span>' ?></div><div class="report-text"><strong>An ninh, trật tự, an toàn:</strong> <?= e($field('safety')) ?: '<span class="report-empty">Chưa nhập.</span>' ?></div><div class="report-text"><strong>Sức khỏe và y tế:</strong> <?= e($field('health')) ?: '<span class="report-empty">Chưa nhập.</span>' ?></div>
        <div class="report-subtitle">3.3. Các sự việc phát sinh/vấn đề tồn đọng</div><div class="report-text"><?= e($field('incidents')) ?: '<span class="report-empty">Không có/Chưa nhập.</span>' ?></div>
      </div>
      <div class="report-section"><div class="report-section-title">4. Phần kết luận và bàn giao</div><div class="report-text"><strong>Ý kiến nhận xét của người trực:</strong> <?= e($field('assessment')) ?: '<span class="report-empty">Chưa nhập.</span>' ?></div><div class="report-text"><strong>Bàn giao ca sau:</strong> <?= e($field('handover')) ?: '<span class="report-empty">Chưa nhập.</span>' ?></div></div>
      <table class="report-signatures"><tr><td><strong>BÊN NHẬN BÀN GIAO<br><span style="font-weight:400">(Ký và ghi rõ họ tên)</span></strong><?php foreach(array_slice($nextDutyNames,0,3) as $i=>$name): ?><div class="report-sign-name"><?= $i+1 ?>. <?= e($name) ?></div><?php endforeach; ?></td><td><strong>BÊN TRỰC BÀN GIAO<br><span style="font-weight:400">(Ký và ghi rõ họ tên)</span></strong><?php foreach(array_slice($dutyNames,0,3) as $i=>$name): ?><div class="report-sign-name"><?= $i+1 ?>. <?= e($name) ?></div><?php endforeach; ?></td></tr></table>
    </article>
  </div>
</div>
