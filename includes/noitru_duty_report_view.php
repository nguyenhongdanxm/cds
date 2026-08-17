<?php
/** Nhập trực tiếp và in Biên bản trực nội trú hằng ngày theo mẫu hành chính A4. */
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
noitru_att_ensure_legacy_reports(count($studentMap));
$attendanceReportShifts = [];
foreach (noitru_att_reports_all() as $attendanceReport) {
    if (($attendanceReport['date'] ?? '') !== $reportDate) continue;
    $shift = trim((string)($attendanceReport['shift'] ?? '')) ?: 'Khác';
    $attendanceReportShifts[$shift] = true;
    if (!isset($attendanceByShift[$shift])) $attendanceByShift[$shift] = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    foreach ($attendance as $status=>$_) {
        $count = (int)($attendanceReport[$status] ?? 0);
        $attendance[$status] += $count;
        $attendanceByShift[$shift][$status] += $count;
    }
}
foreach (noitru_att_all() as $row) {
    if (($row['date'] ?? '') !== $reportDate) continue;
    $status = (string)($row['status'] ?? 'present');
    $shift = trim((string)($row['shift'] ?? '')) ?: 'Khác';
    if (!isset($attendanceReportShifts[$shift])) {
        if (isset($attendance[$status])) $attendance[$status]++;
        if (!isset($attendanceByShift[$shift])) $attendanceByShift[$shift] = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
        if (isset($attendanceByShift[$shift][$status])) $attendanceByShift[$shift][$status]++;
    }
    if (in_array($status, ['absent','late','excused'], true)) {
        $student = $studentMap[(string)($row['student_id'] ?? '')] ?? [];
        $absentDetails[] = ['name'=>$student['name'] ?? ($row['student_name'] ?? 'Không rõ'),'class'=>$student['class_name'] ?? ($row['class_name'] ?? ''),'status'=>$status,'reason'=>trim((string)($row['reason'] ?? ''))];
    }
}
$shiftLabels = ['the_duc_sang'=>'Thể dục sáng','sang'=>'Sáng','trua'=>'Trưa','chieu'=>'Chiều','toi'=>'Tối','hoc_toi'=>'Học tối','dot_xuat'=>'Đột xuất'];
$statusLabels = ['absent'=>'Vắng','late'=>'Muộn','excused'=>'Vắng có phép'];
$location = trim((string)($report['location'] ?? ''));
if ($location === '' || in_array($location, ['Pà Vầy Sủ', 'Xã Pà Vầy Sủ'], true)) $location = 'Phòng trực nội trú';
$shiftLabel = (string)($report['shift_label'] ?? ($startTime . ' ngày ' . date('d/m/Y',strtotime($reportDate)) . ' đến ' . $endTime . ' ngày ' . date('d/m/Y',strtotime($nextDate))));
$field = fn($key) => (string)($report[$key] ?? '');
$oldParts = array_values(array_filter(array_map('trim', [$field('discipline'),$field('hygiene'),$field('safety'),$field('health')]), fn($value)=>$value!==''));
$disciplineText = implode("\n", array_values(array_unique($oldParts)));
?>

<style>
.duty-report-toolbar{display:flex;justify-content:space-between;align-items:end;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.duty-report-actions{display:flex;justify-content:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem}.duty-report-preview-wrap{padding:1rem;overflow:auto;border:1px solid #dce4eb;border-radius:18px;background:#eef3f7;box-shadow:0 5px 18px rgba(15,23,42,.05)}.duty-report-paper{box-sizing:border-box;width:210mm;min-height:297mm;margin:0 auto;padding:20mm 15mm 20mm 20mm;background:#fff;color:#000;font-family:"Times New Roman",serif;font-size:13pt;line-height:1.25;box-shadow:0 3px 18px rgba(15,23,42,.16)}.report-national{display:grid;grid-template-columns:40% 60%;gap:8mm;text-align:center;font-weight:700}.report-national p{margin:0}.report-national .report-agency{font-weight:400}.report-national .underline{display:inline-block;border-bottom:1px solid #000;padding-bottom:2px}.report-place{text-align:right;font-style:italic;margin:8mm 0 5mm}.duty-report-paper h1{margin:0;text-align:center;font-size:15pt;font-weight:700}.report-year{text-align:center;font-weight:700;margin:1mm 0 7mm}.report-section{margin:3mm 0}.report-section-title{font-weight:700}.report-subtitle{margin-top:2.5mm;font-weight:700}.report-text{white-space:pre-wrap;text-align:justify}.report-attendance{width:100%;margin:2mm 0;border-collapse:collapse;font-size:12pt}.report-attendance th,.report-attendance td{border:1px solid #000;padding:1.5mm;text-align:center}.report-entry{display:block;width:100%;min-height:30mm;margin:1.5mm 0 2.5mm;padding:2.5mm;border:1px solid #b8c5d1;border-radius:5px;background:#f9fbfd;color:#000;font:13pt/1.25 "Times New Roman",serif;resize:vertical}.report-entry.short{min-height:18mm}.report-entry:focus{outline:2px solid #38bdf8;border-color:#0284c7;background:#fff}.report-entry-preview{display:none;min-height:8mm;white-space:pre-wrap;text-align:justify}.preview-mode .report-entry{display:none}.preview-mode .report-entry-preview{display:block}.report-signatures{width:100%;margin-top:7mm;border-collapse:collapse;table-layout:fixed}.report-signatures td{height:50mm;border:0;padding:2mm;vertical-align:top;text-align:center}.report-signatures strong{display:block}.report-sign-name{margin-top:12mm;text-align:center}.report-empty{color:#555;font-style:italic}@media(max-width:900px){.duty-report-preview-wrap{padding:.5rem}.duty-report-paper{transform-origin:top left}}@media print{@page{size:A4 portrait;margin:20mm 15mm 20mm 20mm}body{background:#fff!important}.nt-sidebar,.nt-bottom-nav,.nt-page-head,.duty-report-toolbar,.duty-report-actions,.nt-sheet,.nt-sheet-backdrop{display:none!important}.nt-main,.nt-content,.duty-report-preview-wrap{display:block!important;margin:0!important;padding:0!important;border:0!important;box-shadow:none!important}.duty-report-paper{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none;font-size:13pt}.report-entry{display:none!important}.report-entry-preview{display:block!important}.report-signatures td{height:50mm}}
</style>

<div class="duty-report-toolbar">
  <div><h4 class="mb-1"><i class="bi bi-file-earmark-text text-info"></i> Biên bản trực nội trú</h4><div class="text-muted small">Dữ liệu lịch trực và điểm danh tự động điền sẵn; nhập nhận xét trực tiếp trên biên bản.</div></div>
  <form method="get" class="d-flex gap-2 align-items-end"><input type="hidden" name="tab" value="duty_report"><div><label class="form-label small mb-1">Ngày trực</label><input type="date" name="date" class="form-control" value="<?= e($reportDate) ?>"></div><button class="btn btn-outline-info"><i class="bi bi-search"></i> Xem</button></form>
</div>

<form method="post" id="dutyReportForm">
  <input type="hidden" name="action" value="duty_report_save"><input type="hidden" name="date" value="<?= e($reportDate) ?>"><input type="hidden" name="location" value="<?= e($location) ?>"><input type="hidden" name="shift_label" value="<?= e($shiftLabel) ?>">
  <div class="duty-report-actions"><button class="btn btn-outline-info" type="button" id="toggleDutyPreview"><i class="bi bi-eye"></i> Xem trước</button><button class="btn btn-info text-white" <?= !$canEditCurrent?'disabled':'' ?>><i class="bi bi-floppy"></i> Lưu biên bản</button><button class="btn btn-outline-primary" type="button" onclick="printDutyReport()"><i class="bi bi-printer"></i> In / Xuất PDF</button><button class="btn btn-success" type="button" id="saveDutyDrive" data-csrf="<?=e(cds_drive_csrf_token())?>" data-source="<?=e(cds_drive_page_action())?>" data-filename="bien-ban-truc-<?=e($reportDate)?>.html"><i class="bi bi-google"></i> Lưu Drive</button></div>
  <div class="duty-report-preview-wrap"><article class="duty-report-paper">
    <div class="report-national"><div><p class="report-agency">SỞ GD&amp;ĐT TUYÊN QUANG</p><p>TRƯỜNG PTDT NỘI TRÚ<br><span class="underline">THCS&amp;THPT XÍN MẦN</span></p></div><div><p>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p><p><span class="underline">Độc lập - Tự do - Hạnh phúc</span></p></div></div>
    <div class="report-place">Pà Vầy Sủ, ngày <?= (int)date('d',strtotime($reportDate)) ?> tháng <?= (int)date('m',strtotime($reportDate)) ?> năm <?= e(date('Y',strtotime($reportDate))) ?></div>
    <h1>BIÊN BẢN TRỰC NỘI TRÚ HẰNG NGÀY</h1><div class="report-year">Năm học <?= e($yearLabel) ?></div>
    <div class="report-section"><span class="report-section-title">1. Thời gian và địa điểm:</span> <?= e($shiftLabel) ?>, tại <?= e($location) ?>.</div>
    <div class="report-section"><span class="report-section-title">2. Thành phần trực:</span> <?= e(implode(', ',$participants)) ?: '<span class="report-empty">Chưa có dữ liệu phân công ngày trực</span>' ?></div>
    <div class="report-section"><div class="report-section-title">3. Nội dung</div><div class="report-subtitle">3.1. Dữ liệu các lần điểm danh</div>
      <?php if($attendanceByShift): ?><table class="report-attendance"><thead><tr><th>Lần điểm danh</th><th>Có mặt</th><th>Vắng</th><th>Muộn</th><th>Có phép</th></tr></thead><tbody><?php foreach($attendanceByShift as $shift=>$counts): ?><tr><td><?= e($shiftLabels[$shift]??$shift) ?></td><td><?= $counts['present'] ?></td><td><?= $counts['absent'] ?></td><td><?= $counts['late'] ?></td><td><?= $counts['excused'] ?></td></tr><?php endforeach; ?><tr><th>Tổng</th><th><?= $attendance['present'] ?></th><th><?= $attendance['absent'] ?></th><th><?= $attendance['late'] ?></th><th><?= $attendance['excused'] ?></th></tr></tbody></table><?php else: ?><div class="report-empty">Chưa có dữ liệu điểm danh trong ngày.</div><?php endif; ?>
      <?php if($absentDetails): ?><div class="report-text"><strong>Học sinh cần lưu ý:</strong> <?php foreach($absentDetails as $index=>$item): ?><?= $index?', ':'' ?><?= e($item['name']) ?> (<?= e($item['class']) ?> – <?= e($statusLabels[$item['status']]??$item['status']) ?><?= $item['reason']!==''?' – '.e($item['reason']):'' ?>)<?php endforeach; ?>.</div><?php endif; ?>
      <div class="report-subtitle">3.2. Tình hình sinh hoạt và kỷ luật</div><textarea class="report-entry" name="discipline" placeholder="Nhập nội dung 3.2…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($disciplineText) ?></textarea><div class="report-entry-preview" data-for="discipline"></div>
      <div class="report-subtitle">3.3. Các sự việc phát sinh/vấn đề tồn đọng</div><textarea class="report-entry" name="incidents" placeholder="Nhập nội dung 3.3…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($field('incidents')) ?></textarea><div class="report-entry-preview" data-for="incidents"></div>
    </div>
    <div class="report-section"><div class="report-section-title">4. Phần kết luận và bàn giao</div><div class="report-subtitle">Ý kiến nhận xét của người trực</div><textarea class="report-entry short" name="assessment" placeholder="Nhập nhận xét…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($field('assessment')) ?></textarea><div class="report-entry-preview" data-for="assessment"></div><div class="report-subtitle">Bàn giao ca sau</div><textarea class="report-entry short" name="handover" placeholder="Nhập nội dung bàn giao…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($field('handover')) ?></textarea><div class="report-entry-preview" data-for="handover"></div></div>
    <table class="report-signatures"><tr><td><strong>BÊN NHẬN BÀN GIAO<br><span style="font-weight:400">(Ký và ghi rõ họ tên)</span></strong><?php foreach(array_slice($nextDutyNames,0,3) as $i=>$name): ?><div class="report-sign-name"><?= $i+1 ?>. <?= e($name) ?></div><?php endforeach; ?></td><td><strong>BÊN TRỰC BÀN GIAO<br><span style="font-weight:400">(Ký và ghi rõ họ tên)</span></strong><?php foreach(array_slice($dutyNames,0,3) as $i=>$name): ?><div class="report-sign-name"><?= $i+1 ?>. <?= e($name) ?></div><?php endforeach; ?></td></tr></table>
  </article></div>
</form>

<script>
(function(){
  const form=document.getElementById('dutyReportForm'),paper=form?.querySelector('.duty-report-paper'),toggle=document.getElementById('toggleDutyPreview');
  function sync(){form?.querySelectorAll('.report-entry').forEach(function(input){const out=form.querySelector('.report-entry-preview[data-for="'+input.name+'"]');if(out)out.textContent=input.value.trim()||'Không có';});}
  function setPreview(on){sync();paper?.classList.toggle('preview-mode',on);if(toggle)toggle.innerHTML=on?'<i class="bi bi-pencil-square"></i> Tiếp tục nhập':'<i class="bi bi-eye"></i> Xem trước';}
  toggle?.addEventListener('click',function(){setPreview(!paper.classList.contains('preview-mode'));});
  form?.querySelectorAll('.report-entry').forEach(function(input){input.addEventListener('input',sync);});sync();
  window.printDutyReport=function(){setPreview(true);window.print();};
  window.prepareDutyReportPreview=function(){setPreview(true);};
})();
</script>
