<?php
/** Nhập trực tiếp và in Biên bản trực nội trú hằng ngày theo mẫu hành chính A4. */
require_once __DIR__ . '/noitru_att_shifts.php';
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
$absentByShiftTypeClass = [];
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
$legacyMealPrefix = '[Có phép sau thời gian đăng ký bữa ăn] ';
foreach (noitru_att_all() as $row) {
    if (($row['date'] ?? '') !== $reportDate) continue;
    $status = (string)($row['status'] ?? 'present');
    $shift = trim((string)($row['shift'] ?? '')) ?: 'Khác';
    if (!isset($attendanceReportShifts[$shift])) {
        if (isset($attendance[$status])) $attendance[$status]++;
        if (!isset($attendanceByShift[$shift])) $attendanceByShift[$shift] = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
        if (isset($attendanceByShift[$shift][$status])) $attendanceByShift[$shift][$status]++;
    }
    if (!in_array($status, ['absent','excused'], true)) continue;

    $student = $studentMap[(string)($row['student_id'] ?? '')] ?? [];
    $reason = trim((string)($row['reason'] ?? ''));
    $excuse = trim((string)($row['excuse'] ?? ''));
    if ($excuse === '') $excuse = $status === 'excused' ? 'P' : 'KP';
    $legacyMeal = strncmp($reason, $legacyMealPrefix, strlen($legacyMealPrefix)) === 0;
    if ($legacyMeal) $reason = trim(substr($reason, strlen($legacyMealPrefix)));

    if ($excuse === 'P_SAU_AN' || $legacyMeal) $absenceType = 'meal_after';
    elseif ($excuse === 'KP' || $status === 'absent') $absenceType = 'unexcused';
    else $absenceType = 'permitted';

    $className = trim((string)($student['class_name'] ?? ($row['class_name'] ?? ''))) ?: '(Chưa rõ lớp)';
    $absentByShiftTypeClass[$shift][$absenceType][$className][] = [
        'name' => $student['name'] ?? ($row['student_name'] ?? 'Không rõ'),
        'class' => $className,
        'reason' => $reason,
        'excuse' => $excuse,
    ];
}
foreach ($absentByShiftTypeClass as &$types) {
    foreach ($types as &$classes) uksort($classes, 'csdl_compare_class_names');
    unset($classes);
}
unset($types);
$shiftLabels=[];$shiftSort=[];
foreach(noitru_att_shifts_all() as $configuredShift){$configuredId=trim((string)($configuredShift['id']??''));if($configuredId==='')continue;$shiftLabels[$configuredId]=trim((string)($configuredShift['label']??''))?:$configuredId;$shiftSort[$configuredId]=(int)($configuredShift['sort']??99);}
$shiftLabels['dot_xuat']=$shiftLabels['dot_xuat']??'Điểm danh đột xuất';$shiftSort['dot_xuat']=$shiftSort['dot_xuat']??999;
uksort($attendanceByShift,function($a,$b)use($shiftSort){$compare=($shiftSort[$a]??998)<=>($shiftSort[$b]??998);return $compare!==0?$compare:strcmp((string)$a,(string)$b);});
$location = trim((string)($report['location'] ?? ''));
if ($location === '' || in_array($location, ['Pà Vầy Sủ', 'Xã Pà Vầy Sủ'], true)) $location = 'Phòng trực nội trú';
$shiftLabel = (string)($report['shift_label'] ?? ($startTime . ' ngày ' . date('d/m/Y',strtotime($reportDate)) . ' đến ' . $endTime . ' ngày ' . date('d/m/Y',strtotime($nextDate))));
$field = fn($key) => (string)($report[$key] ?? '');
$oldParts = array_values(array_filter(array_map('trim', [$field('discipline'),$field('hygiene'),$field('safety'),$field('health')]), fn($value)=>$value!==''));
$disciplineText = implode("\n", array_values(array_unique($oldParts)));
$suggestions = [
    'discipline' => "- Việc chấp hành thời gian biểu, giờ tự học, giờ ngủ và nội quy nội trú:\n- Tình hình vệ sinh phòng ở, khu vực chung:\n- Học sinh vi phạm, hình thức nhắc nhở hoặc xử lý (nếu có):",
    'incidents' => "- Sự việc phát sinh về học sinh, sức khỏe, an ninh hoặc cơ sở vật chất:\n- Người/lớp/phòng liên quan, thời gian xảy ra:\n- Biện pháp đã xử lý và tình trạng hiện tại:",
    'assessment' => "Ca trực diễn ra ổn định; học sinh cơ bản chấp hành nội quy. Các nhiệm vụ kiểm tra sĩ số, tự học, vệ sinh và giờ ngủ đã được thực hiện.",
    'handover' => "Tình hình nội trú ổn định. Đề nghị ca trực tiếp theo tiếp tục theo dõi và xử lý các nội dung còn tồn tại (nếu có).",
];
$entryValue = static fn(string $key, string $saved): string => trim($saved) !== '' ? $saved : ($suggestions[$key] ?? '');
$disciplineText = $entryValue('discipline', $disciplineText);
?>

<style>
.duty-report-toolbar{display:flex;justify-content:space-between;align-items:end;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.duty-report-actions{display:flex;justify-content:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem}.duty-report-preview-wrap{padding:1rem;overflow:auto;border:1px solid #dce4eb;border-radius:18px;background:#eef3f7;box-shadow:0 5px 18px rgba(15,23,42,.05)}.duty-report-paper{box-sizing:border-box;width:210mm;min-height:297mm;margin:0 auto;padding:18mm 15mm 18mm 20mm;background:#fff;color:#000;font-family:"Times New Roman",serif;font-size:13pt;line-height:1.25;box-shadow:0 3px 18px rgba(15,23,42,.16)}.report-national{display:grid;grid-template-columns:40% 60%;gap:8mm;text-align:center;font-weight:700}.report-national p{margin:0}.report-national .report-agency{font-weight:400}.report-national .underline{display:inline-block;border-bottom:1px solid #000;padding-bottom:2px}.report-place{text-align:right;font-style:italic;margin:7mm 0 4mm}.duty-report-paper h1{margin:0;text-align:center;font-size:15pt;font-weight:700}.report-year{text-align:center;font-weight:700;margin:1mm 0 6mm}.report-section{margin:2.5mm 0}.report-section-title{font-weight:700}.report-info-line{display:grid;grid-template-columns:25mm 1fr;gap:2mm;margin:1.2mm 0;padding-left:5mm}.report-info-label{font-weight:700}.report-subtitle{margin-top:2.5mm;font-weight:700}.report-entry-hint{margin-top:1mm;color:#64748b;font-size:10.5pt;font-style:italic}.report-text{white-space:pre-wrap;text-align:justify}.report-attendance{width:100%;margin:2mm 0;border-collapse:collapse;table-layout:fixed;font-size:11.5pt}.report-attendance th,.report-attendance td{border:1px solid #000;padding:1.6mm;text-align:center;vertical-align:top}.report-attendance th:nth-child(1){width:22%}.report-attendance th:nth-child(2){width:18%}.report-attendance th:nth-child(3){width:60%}.report-attendance .attendance-absent{text-align:left;font-size:10.5pt;font-style:normal;line-height:1.25}.attendance-type-row{display:grid;grid-template-columns:48mm 1fr;gap:2mm;padding:1.1mm 0;border-bottom:1px dotted #aaa}.attendance-type-row:last-child{border-bottom:0}.attendance-type-label{font-weight:700}.attendance-type-count{font-weight:400}.attendance-class{margin:0 0 .7mm}.attendance-class:last-child{margin-bottom:0}.attendance-class strong{font-style:normal}.attendance-name{font-style:italic}.report-entry{display:block;width:100%;min-height:30mm;margin:1mm 0 2.5mm;padding:2.5mm;border:1px solid #b8c5d1;border-radius:5px;background:#f9fbfd;color:#000;font:13pt/1.25 "Times New Roman",serif;resize:vertical}.report-entry.short{min-height:18mm}.report-entry:focus{outline:2px solid #38bdf8;border-color:#0284c7;background:#fff}.report-entry-preview{display:none;min-height:8mm;white-space:pre-wrap;text-align:justify}.preview-mode .report-entry,.preview-mode .report-entry-hint{display:none}.preview-mode .report-entry-preview{display:block}.report-signatures{width:100%;margin-top:7mm;border-collapse:collapse;table-layout:fixed;break-inside:avoid;page-break-inside:avoid}.report-signatures td{height:45mm;border:0;padding:2mm;vertical-align:top;text-align:center}.report-signatures strong{display:block}.report-sign-name{margin-top:11mm;text-align:center}.report-empty{color:#555;font-style:italic}.drive-save-note{width:100%;text-align:center;font-size:.85rem;color:#64748b;margin-top:-.3rem;margin-bottom:.75rem}@media(max-width:900px){.duty-report-preview-wrap{padding:.5rem}.duty-report-paper{transform-origin:top left}.attendance-type-row{grid-template-columns:1fr}}@media print{@page{size:A4 portrait;margin:18mm 15mm 18mm 20mm}body{background:#fff!important}.nt-sidebar,.nt-bottom-nav,.nt-page-head,.duty-report-toolbar,.duty-report-actions,.drive-save-note,.nt-sheet,.nt-sheet-backdrop{display:none!important}.nt-main,.nt-content,.duty-report-preview-wrap{display:block!important;margin:0!important;padding:0!important;border:0!important;box-shadow:none!important}.duty-report-paper{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none;font-size:13pt}.report-entry,.report-entry-hint{display:none!important}.report-entry-preview{display:block!important}.report-attendance{break-inside:avoid;page-break-inside:avoid}.report-signatures td{height:45mm}}
</style>

<div class="duty-report-toolbar">
  <div><h4 class="mb-1"><i class="bi bi-file-earmark-text text-info"></i> Biên bản trực nội trú</h4><div class="text-muted small">Dữ liệu lịch trực và điểm danh tự động điền sẵn; nhập nhận xét trực tiếp trên biên bản.</div></div>
  <form method="get" class="d-flex gap-2 align-items-end"><input type="hidden" name="tab" value="duty_report"><div><label class="form-label small mb-1">Ngày trực</label><input type="date" name="date" class="form-control" value="<?= e($reportDate) ?>"></div><button class="btn btn-outline-info"><i class="bi bi-search"></i> Xem</button></form>
</div>

<form method="post" id="dutyReportForm">
  <input type="hidden" name="action" value="duty_report_save"><input type="hidden" name="date" value="<?= e($reportDate) ?>"><input type="hidden" name="location" value="<?= e($location) ?>"><input type="hidden" name="shift_label" value="<?= e($shiftLabel) ?>">
  <div class="duty-report-actions"><button class="btn btn-outline-info" type="button" id="toggleDutyPreview"><i class="bi bi-eye"></i> Xem trước</button><button class="btn btn-info text-white" <?= !$canEditCurrent?'disabled':'' ?>><i class="bi bi-floppy"></i> Lưu biên bản</button><button class="btn btn-outline-primary" type="button" onclick="printDutyReport()"><i class="bi bi-printer"></i> In / Xuất PDF</button><button class="btn btn-success" type="button" id="saveDutyDrive" data-csrf="<?=e(cds_drive_csrf_token())?>" data-date="<?=e($reportDate)?>" data-endpoint="<?=e(BASE_URL.'noitru_duty_drive.php')?>"><i class="bi bi-google"></i> Lưu Google Docs</button></div>
  <div class="drive-save-note">Google Docs được tạo từ bản xem trước A4; mở để xem và có thể tải xuống PDF từ Google Docs.</div>
  <div class="duty-report-preview-wrap"><article class="duty-report-paper">
    <div class="report-national"><div><p class="report-agency">SỞ GD&amp;ĐT TUYÊN QUANG</p><p>TRƯỜNG PTDT NỘI TRÚ<br><span class="underline">THCS&amp;THPT XÍN MẦN</span></p></div><div><p>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p><p><span class="underline">Độc lập - Tự do - Hạnh phúc</span></p></div></div>
    <div class="report-place">Pà Vầy Sủ, ngày <?= (int)date('d',strtotime($reportDate)) ?> tháng <?= (int)date('m',strtotime($reportDate)) ?> năm <?= e(date('Y',strtotime($reportDate))) ?></div>
    <h1>BIÊN BẢN TRỰC NỘI TRÚ HẰNG NGÀY</h1><div class="report-year">Năm học <?= e($yearLabel) ?></div>
    <div class="report-section"><div class="report-section-title">1. Thời gian và địa điểm</div><div class="report-info-line"><span class="report-info-label">Thời gian:</span><span><?= e($shiftLabel) ?>.</span></div><div class="report-info-line"><span class="report-info-label">Địa điểm:</span><span><?= e($location) ?>.</span></div></div>
    <div class="report-section"><span class="report-section-title">2. Thành phần trực:</span> <?= e(implode(', ',$participants)) ?: '<span class="report-empty">Chưa có dữ liệu phân công ngày trực</span>' ?></div>
    <div class="report-section"><div class="report-section-title">3. Nội dung</div><div class="report-subtitle">3.1. Dữ liệu các lần điểm danh</div>
      <?php if($attendanceByShift): ?><table class="report-attendance"><thead><tr><th>Điểm danh</th><th>Sỹ số<br><small>(Có mặt/Tổng)</small></th><th>Vắng (liệt kê học sinh)</th></tr></thead><tbody><?php foreach($attendanceByShift as $shift=>$counts): $total=array_sum($counts);$presentTotal=(int)$counts['present']+(int)$counts['late'];$absentTotal=(int)$counts['absent']+(int)$counts['excused'];$shiftTypes=$absentByShiftTypeClass[$shift]??[];$displayShiftLabel=$shiftLabels[$shift]??$shift;$isMealAttendance=function_exists('noitru_att_meal_for_shift')&&noitru_att_meal_for_shift($shift,$displayShiftLabel)!=='';if(!$isMealAttendance&&!empty($shiftTypes['meal_after'])){foreach($shiftTypes['meal_after'] as $className=>$items){if(!isset($shiftTypes['permitted'][$className]))$shiftTypes['permitted'][$className]=[];$shiftTypes['permitted'][$className]=array_merge($shiftTypes['permitted'][$className],$items);}if(!empty($shiftTypes['permitted']))uksort($shiftTypes['permitted'],'csdl_compare_class_names');unset($shiftTypes['meal_after']);} ?><tr><td><?= e($displayShiftLabel) ?></td><td><strong><?= $presentTotal ?>/<?= (int)$total ?></strong></td><td class="attendance-absent"><?php if($absentTotal): $typeDefs=$isMealAttendance?[['permitted','Có phép'],['meal_after','Có phép sau thời gian đăng ký bữa ăn'],['unexcused','Không phép']]:[['permitted','Có phép'],['unexcused','Không phép']]; foreach($typeDefs as [$typeKey,$typeLabel]): $typeClasses=$shiftTypes[$typeKey]??[];$typeCount=0;foreach($typeClasses as $typeItems)$typeCount+=count($typeItems); ?><div class="attendance-type-row"><div class="attendance-type-label"><?=e($typeLabel)?> <span class="attendance-type-count">(<?=$typeCount?>)</span>:</div><div><?php if($typeClasses): foreach($typeClasses as $className=>$items): ?><div class="attendance-class"><strong><?=e($className)?>:</strong> <?php foreach($items as $index=>$item): ?><?= $index?', ':'' ?><span class="attendance-name"><?=e($item['name'])?></span><?php if($item['reason']!==''): ?> – <?=e($item['reason'])?><?php endif; ?><?php endforeach; ?></div><?php endforeach; else: ?><span class="report-empty">Không có</span><?php endif; ?></div></div><?php endforeach; else: ?>Không có học sinh vắng.<?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="report-empty">Chưa có dữ liệu điểm danh trong ngày.</div><?php endif; ?>
      <div class="report-subtitle">3.2. Tình hình sinh hoạt và kỷ luật</div><div class="report-entry-hint">Gợi ý nội dung: có thể sửa hoặc xóa nội dung mẫu bên dưới.</div><textarea class="report-entry" name="discipline" placeholder="Nhập nội dung 3.2…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($disciplineText) ?></textarea><div class="report-entry-preview" data-for="discipline"></div>
      <div class="report-subtitle">3.3. Các sự việc phát sinh/vấn đề tồn đọng</div><div class="report-entry-hint">Gợi ý nội dung: có thể sửa hoặc xóa nội dung mẫu bên dưới.</div><textarea class="report-entry" name="incidents" placeholder="Nhập nội dung 3.3…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($entryValue('incidents', $field('incidents'))) ?></textarea><div class="report-entry-preview" data-for="incidents"></div>
    </div>
    <div class="report-section"><div class="report-section-title">4. Phần kết luận và bàn giao</div><div class="report-subtitle">Ý kiến nhận xét của người trực</div><div class="report-entry-hint">Gợi ý nội dung: có thể sửa hoặc xóa nội dung mẫu bên dưới.</div><textarea class="report-entry short" name="assessment" placeholder="Nhập nhận xét…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($entryValue('assessment', $field('assessment'))) ?></textarea><div class="report-entry-preview" data-for="assessment"></div><div class="report-subtitle">Bàn giao ca sau</div><div class="report-entry-hint">Gợi ý nội dung: có thể sửa hoặc xóa nội dung mẫu bên dưới.</div><textarea class="report-entry short" name="handover" placeholder="Nhập nội dung bàn giao…" <?= !$canEditCurrent?'readonly':'' ?>><?= e($entryValue('handover', $field('handover'))) ?></textarea><div class="report-entry-preview" data-for="handover"></div></div>
    <table class="report-signatures"><tr><td><strong>BÊN NHẬN BÀN GIAO<br><span style="font-weight:400">(Ký và ghi rõ họ tên)</span></strong><?php foreach(array_slice($nextDutyNames,0,3) as $i=>$name): ?><div class="report-sign-name"><?= $i+1 ?>. <?= e($name) ?></div><?php endforeach; ?></td><td><strong>BÊN TRỰC BÀN GIAO<br><span style="font-weight:400">(Ký và ghi rõ họ tên)</span></strong><?php foreach(array_slice($dutyNames,0,3) as $i=>$name): ?><div class="report-sign-name"><?= $i+1 ?>. <?= e($name) ?></div><?php endforeach; ?></td></tr></table>
  </article></div>
</form>

<script>
(function(){
  const form=document.getElementById('dutyReportForm'),paper=form?.querySelector('.duty-report-paper'),toggle=document.getElementById('toggleDutyPreview'),driveBtn=document.getElementById('saveDutyDrive');
  function sync(){form?.querySelectorAll('.report-entry').forEach(function(input){const out=form.querySelector('.report-entry-preview[data-for="'+input.name+'"]');if(out)out.textContent=input.value.trim()||'Không có';});}
  function setPreview(on){sync();paper?.classList.toggle('preview-mode',on);if(toggle)toggle.innerHTML=on?'<i class="bi bi-pencil-square"></i> Tiếp tục nhập':'<i class="bi bi-eye"></i> Xem trước';}
  function exportHtml(){
    sync();
    const clone=paper.cloneNode(true);
    clone.classList.add('preview-mode');
    clone.querySelectorAll('.report-entry,.report-entry-hint').forEach(function(el){el.remove();});
    clone.querySelectorAll('.report-entry-preview').forEach(function(el){el.style.display='block';});
    clone.removeAttribute('style');
    return clone.innerHTML;
  }
  toggle?.addEventListener('click',function(){setPreview(!paper.classList.contains('preview-mode'));});
  form?.querySelectorAll('.report-entry').forEach(function(input){input.addEventListener('input',sync);});sync();
  driveBtn?.addEventListener('click',async function(){
    const popup=window.open('about:blank','_blank');
    const oldHtml=driveBtn.innerHTML;driveBtn.disabled=true;driveBtn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Đang tạo Google Docs…';
    try{
      const fd=new FormData();fd.append('csrf',driveBtn.dataset.csrf||'');fd.append('date',driveBtn.dataset.date||'');fd.append('content',exportHtml());
      const res=await fetch(driveBtn.dataset.endpoint,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      let data={};try{data=await res.json();}catch(e){throw new Error('Máy chủ trả về dữ liệu không hợp lệ.');}
      if(!res.ok||!data.ok)throw new Error(data.message||'Không tạo được Google Docs.');
      if(popup){popup.location=data.webViewLink;popup.focus();}else window.location.href=data.webViewLink;
      driveBtn.innerHTML='<i class="bi bi-check-circle"></i> Đã lưu Google Docs';
      setTimeout(function(){driveBtn.innerHTML=oldHtml;driveBtn.disabled=false;},1800);
    }catch(error){if(popup)popup.close();alert(error.message||'Không thể lưu Google Docs.');driveBtn.innerHTML=oldHtml;driveBtn.disabled=false;}
  });
  window.printDutyReport=function(){setPreview(true);window.print();};
  window.prepareDutyReportPreview=function(){setPreview(true);};
})();
</script>