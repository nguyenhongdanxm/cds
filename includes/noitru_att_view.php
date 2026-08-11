<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Điểm danh nội trú – CDS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>includes/noitru_layout.css?v=20260731-4" rel="stylesheet">
  <style>
    .att-shell{background:#fff;border:1px solid #dce5ec;border-radius:18px;overflow:hidden}
    .att-tabs{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #dce5ec}
    .att-tabs a{padding:1rem;text-align:center;text-decoration:none;color:#64748b;font-weight:700;border-bottom:3px solid transparent}
    .att-tabs a.active{color:var(--nt-primary);border-color:var(--nt-primary)}
    .att-panel{padding:1.2rem}
    .att-controls{display:grid;grid-template-columns:1fr 1fr 2.1fr;gap:1rem}
    .att-class-chips{display:flex;flex-wrap:wrap;gap:.5rem}
    .att-class-chips a{min-height:42px;padding:.55rem .85rem;border:1px solid #d6e0e7;border-radius:12px;text-decoration:none;color:#253342;font-weight:650;background:#fff}
    .att-class-chips a.active{background:var(--nt-primary);border-color:var(--nt-primary);color:#fff}
    .att-tools{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1rem}
    .att-tool{display:flex;align-items:center;justify-content:center;gap:.6rem;min-height:48px;border:1px solid #d6e0e7;border-radius:12px;background:#fff}
    .att-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin:1rem 0}
    .att-summary>div{padding:1rem;text-align:center;border:1px solid #dce5ec;border-radius:15px}
    .att-summary strong{display:block;font-size:1.65rem}.att-total strong{color:#089dd8}.att-present strong{color:#16a34a}.att-absent strong{color:#dc2626}
    .att-bulk{display:flex;gap:.65rem;flex-wrap:wrap;margin-bottom:1rem}
    .att-search{position:relative;margin-bottom:1rem}.att-search i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#789}.att-search input{padding-left:2.7rem}
    .att-students{border:1px solid #dce5ec;border-radius:16px;overflow-y:auto;max-height:min(52vh,620px);scrollbar-gutter:stable}
    .att-person{display:flex;align-items:center;gap:.65rem;width:100%;min-height:44px;padding:.42rem .75rem;border:0;border-bottom:1px solid #e6edf2;background:#fff;text-align:left}
    .att-person:last-child{border-bottom:0}.att-person:hover{background:#f8fbfd}
    .att-dot{width:24px;height:24px;flex:0 0 24px;display:grid;place-items:center;border-radius:50%;border:2px solid #a8b6c2;background:#fff;color:transparent}
    .att-person.absent{background:#fff6f6;color:#b91c1c}.att-person.absent .att-dot{background:#dc2626}.att-person.excused .att-dot{background:#f59e0b}
    .att-person-name{font-weight:700}.att-person-class{color:#64748b;margin-left:.4rem;font-size:.8rem}.att-person-meta{display:block;font-size:.7rem;margin-top:.08rem}
    .att-save{width:100%;min-height:52px;margin-top:1rem;font-weight:750}
    .att-dialog{width:min(560px,calc(100% - 1.2rem));border:0;border-radius:20px;padding:0;box-shadow:0 25px 70px rgba(15,23,42,.28)}
    .att-dialog::backdrop{background:rgba(15,23,42,.68)}.att-dialog-body{padding:1.35rem}.att-dialog-head{display:flex;justify-content:space-between;gap:1rem;align-items:start}
    .att-dialog-close{border:0;background:transparent;font-size:1.2rem}.att-dialog-actions{display:flex;justify-content:flex-end;gap:.7rem;margin-top:1.2rem}
    .att-confirm-list{max-height:42vh;overflow:auto}.att-confirm-class{border:1px solid #dce5ec;border-radius:12px;margin:.6rem 0;overflow:hidden}.att-confirm-class header{display:flex;justify-content:space-between;padding:.55rem .7rem;font-weight:750}.att-confirm-class div{padding:.5rem .7rem;background:#fff7f7}
    .att-report-card{width:100%;background:#fff;padding:1.1rem;border:1px solid #dce5ec;border-radius:14px;text-align:center}.att-report-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.25rem;margin:1rem 0}.att-report-stats strong{display:block;font-size:1.25rem}
    .att-history-filter{display:flex;align-items:end;gap:.7rem;flex-wrap:wrap;margin-bottom:1rem}
    .att-history-day{border:1px solid #dce5ec;border-radius:16px;overflow:hidden;margin-bottom:1rem}
    .att-history-day>header{display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.75rem 1rem;background:#f7fafc;border-bottom:1px solid #dce5ec}
    .att-history-day-title{display:flex;align-items:center;gap:.7rem}.att-history-check{width:1.15rem;height:1.15rem;cursor:pointer}
    .att-history-shifts{display:grid;gap:.7rem;padding:.8rem}
    .att-history-shift{display:block;padding:.8rem;border:1px solid #e2e8f0;border-radius:12px;text-decoration:none;color:#253342;background:#fff}
    .att-history-shift-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem}.att-history-actions{display:flex;gap:.45rem;flex-wrap:wrap}.att-history-counts{display:flex;gap:.75rem;flex-wrap:wrap;font-size:.82rem;margin-top:.35rem}
    .att-history-absent{margin-top:.55rem;padding-top:.5rem;border-top:1px dashed #f2b8b8;font-size:.8rem;color:#b91c1c}.att-history-absent span{display:inline-block;margin:.15rem .65rem .15rem 0}
    .att-history-admin{display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap;margin:.9rem 0;padding:.75rem;border:1px solid #fecaca;border-radius:14px;background:#fff7f7}
    .att-export{margin-top:1.2rem;padding:1rem;border:1px solid #dce5ec;border-radius:16px;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;gap:1rem}
    @media(max-width:767.98px){
      .att-panel{padding:.8rem}.att-controls{grid-template-columns:1fr 1fr}.att-controls>div:last-child{grid-column:1/-1}.att-class-chips{flex-wrap:nowrap;overflow-x:auto;padding-bottom:.25rem}.att-class-chips a{white-space:nowrap}
      .att-tools{grid-template-columns:1fr}.att-summary{gap:.5rem}.att-summary>div{padding:.7rem .35rem}.att-summary strong{font-size:1.35rem}
      .att-person{min-height:46px;padding:.48rem .65rem}.att-dialog-body{padding:1rem}.att-dialog-actions .btn{flex:1}
      .att-history-day>header{align-items:flex-start}.att-history-shift-head{align-items:flex-start}.att-history-counts{gap:.5rem}
      .att-history-shift-head{flex-direction:column}.att-history-actions{width:100%}.att-history-actions .btn{flex:1}
      .att-export{align-items:stretch;flex-direction:column}.att-export .btn{width:100%}
    }
  </style>
</head>
<body class="nt-body">
<?php $nt_sec='attendance'; require __DIR__.'/noitru_shell.php'; ?>
<main class="nt-main"><div class="nt-content">
  <div class="nt-page-head">
    <div><h1><i class="bi bi-house-check text-primary"></i> Điểm danh nội trú</h1><div class="subtitle">Báo cáo sĩ số nội trú theo các buổi</div></div>
  </div>
  <?php show_flash(); ?>

  <?php if ($view==='settings'): ?>
    <?php $allShifts=noitru_att_shifts_all(); ?>
    <form method="post" class="card card-soft"><div class="card-body">
      <input type="hidden" name="action" value="shifts_save"><input type="hidden" name="view" value="settings">
      <div class="nt-page-head"><div><h2>Cài đặt buổi điểm danh</h2></div><a class="btn btn-outline-secondary" href="<?= e(att_url(['view'=>'diemdanh'])) ?>">Quay lại</a></div>
      <div class="small text-muted mb-3">Hệ thống tự nhận diện buổi theo giờ bắt đầu–kết thúc. Ngoài các khoảng giờ này sẽ là <strong>Điểm danh đột xuất</strong>.</div>
      <?php foreach ($allShifts as $i=>$row): ?><div class="row g-2 align-items-center mb-2">
        <div class="col-auto"><input class="form-check-input" type="checkbox" name="active[<?= $i ?>]" value="1" <?= !empty($row['active'])?'checked':'' ?>></div>
        <div class="col-12 col-md"><input type="hidden" name="sid[]" value="<?= e($row['id']) ?>"><label class="form-label">Tên buổi</label><input class="form-control" name="label[]" value="<?= e($row['label']) ?>"></div>
        <div class="col-5 col-md-2"><label class="form-label">Từ giờ</label><input class="form-control" type="time" name="start[]" value="<?= e($row['start']??'') ?>"></div>
        <div class="col-5 col-md-2"><label class="form-label">Đến giờ</label><input class="form-control" type="time" name="end[]" value="<?= e($row['end']??'') ?>"></div>
        <div class="col-2 col-md-1"><label class="form-label">Thứ tự</label><input class="form-control" type="number" name="sort[]" value="<?= (int)$row['sort'] ?>"></div>
      </div><?php endforeach; ?>
      <div class="border-top pt-3 mt-3"><h3 class="h6">Thêm buổi mới</h3><div class="row g-2"><div class="col-md-3"><input class="form-control" name="new_id" placeholder="Mã buổi"></div><div class="col-md-3"><input class="form-control" name="new_label" placeholder="Tên buổi"></div><div class="col"><input class="form-control" type="time" name="new_start"></div><div class="col"><input class="form-control" type="time" name="new_end"></div></div></div>
      <button class="btn btn-nt mt-2">Lưu cài đặt</button>
    </div></form>
  <?php else: ?>
  <section class="att-shell">
    <nav class="att-tabs">
      <a class="<?= $view!=='history'?'active':'' ?>" href="<?= e(att_url(['view'=>'diemdanh'])) ?>"><i class="bi bi-house-door"></i> Điểm danh</a>
      <a class="<?= $view==='history'?'active':'' ?>" href="<?= e(att_url(['view'=>'history'])) ?>"><i class="bi bi-file-earmark-text"></i> Lịch sử</a>
    </nav>

    <?php if ($view==='history'): ?>
      <?php
      $historyDate=trim($_GET['history_date']??'');
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$historyDate)) $historyDate='';
      $historyFrom=$historyDate!==''?$historyDate:date('Y-m-d',strtotime('-6 days'));
      $historyTo=$historyDate!==''?$historyDate:date('Y-m-d');
      $historyDays=[];
      $allowedStudentIds=array_fill_keys(array_column($boarders,'id'),true);
      $studentById=[];
      foreach ($boarders as $student) $studentById[$student['id']]=$student;
      $historyReportKeys=[];
      foreach (noitru_att_reports_all() as $report) {
        $rowDate=$report['date']??'';if($rowDate<$historyFrom||$rowDate>$historyTo)continue;$rowShift=$report['shift']??'dot_xuat';
        $historyReportKeys[$rowDate.'|'.$rowShift]=true;
        $historyDays[$rowDate][$rowShift]=['total'=>(int)($report['total']??0),'present'=>(int)($report['present']??0)+(int)($report['late']??0),'absent'=>(int)($report['absent']??0)+(int)($report['excused']??0),'students'=>[]];
      }
      foreach (noitru_att_all() as $row) {
        if (!isset($allowedStudentIds[$row['student_id']??''])) continue;
        $rowDate=$row['date']??'';
        if ($rowDate<$historyFrom || $rowDate>$historyTo) continue;
        $rowShift=$row['shift']??'dot_xuat';
        if (!isset($historyDays[$rowDate][$rowShift])) $historyDays[$rowDate][$rowShift]=['total'=>0,'present'=>0,'absent'=>0,'students'=>[]];
        $summary=&$historyDays[$rowDate][$rowShift];
        if (!isset($historyReportKeys[$rowDate.'|'.$rowShift])) {$summary['total']++;if(in_array($row['status']??'present',['present','late'],true))$summary['present']++;else $summary['absent']++;}
        if (!in_array($row['status']??'present',['present','late'],true)) {
          $student=$studentById[$row['student_id']??'']??[];
          $summary['students'][]=[
            'name'=>$student['name']??($row['student_name']??'Học sinh'),
            'class'=>$student['class_name']??($row['class_name']??''),
            'excuse'=>$row['excuse']??(($row['status']??'')==='excused'?'P':'KP'),
            'reason'=>$row['reason']??'',
          ];
        }
        unset($summary);
      }
      krsort($historyDays);
      $weekdayNames=['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
      ?>
      <div class="att-panel">
        <div class="nt-page-head">
          <div><h2 class="h5">Lịch sử điểm danh</h2><div class="subtitle"><?= $historyDate!==''?'Kết quả ngày '.e(date('d/m/Y',strtotime($historyDate))):'7 ngày gần nhất' ?></div></div>
          <form method="get" class="att-history-filter">
            <input type="hidden" name="view" value="history">
            <div><label class="form-label">Tra cứu ngày cũ</label><input class="form-control" type="date" name="history_date" value="<?= e($historyDate) ?>"></div>
            <button class="btn btn-nt"><i class="bi bi-calendar-search"></i> Xem</button>
            <?php if ($historyDate!==''): ?><a class="btn btn-outline-secondary" href="<?= e(att_url(['view'=>'history','history_date'=>null,'date'=>null,'shift'=>null,'class'=>null,'q'=>null])) ?>">7 ngày gần nhất</a><?php endif; ?>
          </form>
        </div>
        <?php if ($canDeleteAttendance && $historyDays): ?>
          <form method="post" id="historyBulkDeleteForm" onsubmit="return confirmHistoryBulkDelete()">
            <input type="hidden" name="action" value="att_delete_dates">
          </form>
          <div class="att-history-admin">
            <div><strong><i class="bi bi-shield-lock"></i> Công cụ quản trị</strong><div class="small text-muted">Chọn một hoặc nhiều ngày bên dưới để xoá toàn bộ báo cáo trong ngày.</div></div>
            <button class="btn btn-outline-danger" type="submit" form="historyBulkDeleteForm"><i class="bi bi-trash3"></i> Xoá ngày đã chọn</button>
          </div>
        <?php endif; ?>
        <?php foreach ($historyDays as $day=>$dayShifts): ?>
          <section class="att-history-day">
            <header><div class="att-history-day-title"><?php if ($canDeleteAttendance): ?><input class="form-check-input att-history-check" type="checkbox" name="delete_dates[]" value="<?= e($day) ?>" form="historyBulkDeleteForm" aria-label="Chọn ngày <?= e(date('d/m/Y',strtotime($day))) ?>"><?php endif; ?><div><strong><?= e($weekdayNames[(int)date('w',strtotime($day))]) ?></strong><div class="small text-muted"><?= e(date('d/m/Y',strtotime($day))) ?></div></div></div><span class="badge text-bg-light"><?= count($dayShifts) ?> buổi</span></header>
            <div class="att-history-shifts">
            <?php foreach ($dayShifts as $shiftKey=>$summary): ?>
              <div class="att-history-shift">
                <div class="att-history-shift-head"><strong><i class="bi bi-clock-history text-primary"></i> <?= e($shifts[$shiftKey]??($shiftKey==='dot_xuat'?'Điểm danh đột xuất':$shiftKey)) ?></strong>
                  <?php if ($canManageAttendance || $canDeleteAttendance): ?><div class="att-history-actions">
                    <?php if ($canManageAttendance): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(att_url(['view'=>'diemdanh','date'=>$day,'shift'=>$shiftKey,'history_date'=>null,'class'=>null,'q'=>null])) ?>"><i class="bi bi-pencil-square"></i> Sửa báo cáo</a>
                    <?php endif; if ($canDeleteAttendance): ?>
                    <form method="post" onsubmit="return confirm('Xoá báo cáo <?= e(date('d/m/Y',strtotime($day))) ?> – <?= e($shifts[$shiftKey]??$shiftKey) ?>? Dữ liệu đã lưu và lịch sử của buổi này sẽ bị xoá hoàn toàn.')">
                      <input type="hidden" name="action" value="att_delete_report"><input type="hidden" name="delete_date" value="<?= e($day) ?>"><input type="hidden" name="delete_shift" value="<?= e($shiftKey) ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Xoá</button>
                    </form>
                    <?php endif; ?>
                  </div><?php endif; ?>
                </div>
                <div class="att-history-counts"><span>Tổng: <strong><?= $summary['total'] ?></strong></span><span class="text-success">Có mặt: <strong><?= $summary['present'] ?></strong></span><span class="text-danger">Vắng: <strong><?= $summary['absent'] ?></strong></span></div>
                <?php if ($summary['students']): ?><div class="att-history-absent"><strong>Học sinh vắng:</strong><br><?php foreach ($summary['students'] as $absent): ?><span><?= e(($absent['class']!==''?$absent['class'].': ':'').$absent['name'].' ('.$absent['excuse'].')'.($absent['reason']!==''?' – '.$absent['reason']:'')) ?></span><?php endforeach; ?></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; if (!$historyDays): ?><div class="text-center text-muted py-5">Không có dữ liệu điểm danh trong khoảng này.</div><?php endif; ?>
        <?php
          $currentYear=(int)date('Y');
          $schoolStart=(int)date('n')>=8?$currentYear:$currentYear-1;
        ?>
        <section class="att-export"><div><h3 class="h6 mb-1"><i class="bi bi-file-earmark-excel text-success"></i> Báo cáo học sinh vắng</h3><p class="small text-muted mb-0">Xuất danh sách học sinh vắng và thời gian vắng theo tuần, tháng hoặc năm học.</p></div><button class="btn btn-success" type="button" onclick="document.getElementById('excelDialog').showModal()"><i class="bi bi-download"></i> Xuất Excel</button></section>
      </div>
    <?php else: ?>
    <div class="att-panel">
      <form method="get" id="attFilter">
        <input type="hidden" name="view" value="diemdanh"><input type="hidden" name="class" id="classFilter" value="<?= e($class) ?>">
        <div class="att-controls">
          <div><label class="form-label">Ngày</label><input class="form-control" type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
          <div><label class="form-label">Buổi tự động theo giờ</label><div class="form-control d-flex align-items-center bg-light"><i class="bi bi-clock me-2"></i><strong><?= e($shiftLabel) ?></strong></div></div>
          <div><label class="form-label">Chọn lớp</label><div class="att-class-chips">
            <a class="<?= $class===''?'active':'' ?>" href="<?= e(att_url(['class'=>null,'q'=>null])) ?>">Tất cả (<?= count($boarders) ?>)</a>
            <?php foreach ($byClass as $key=>$rows): ?><a class="<?= $class===$key?'active':'' ?>" href="<?= e(att_url(['class'=>$key,'q'=>null])) ?>"><?= e($key) ?> (<?= count($rows) ?>)</a><?php endforeach; ?>
          </div></div>
        </div>
      </form>
      <?php if ($canManageAttendance): ?><div class="att-tools">
        <?php if (can_edit_perm('nt.diemdanh')): ?>
        <label class="att-tool px-2"><i class="bi bi-person-check"></i><span class="flex-grow-1"><small class="d-block text-muted">Báo cáo thay</small><select class="form-select form-select-sm border-0 p-0" name="reporter" form="attendanceForm"><option value="<?= e($reporter) ?>"><?= e($reporter) ?> (Tôi)</option><?php foreach ($reporters as $teacher): if (($teacher['name']??'')===$reporter) continue; ?><option value="<?= e($teacher['name']) ?>"><?= e($teacher['name']) ?></option><?php endforeach; ?></select></span></label>
        <?php endif; ?>
        <a class="att-tool text-decoration-none text-dark" href="<?= e(att_url(['view'=>'settings'])) ?>"><i class="bi bi-sliders"></i><strong>Cài đặt buổi</strong></a>
      </div><?php endif; ?>
      <div class="att-summary"><div class="att-total"><strong id="sumTotal"><?= $cntTotal ?></strong><span>Tổng số</span></div><div class="att-present"><strong id="sumPresent"><?= $cntPresent ?></strong><span>Có mặt</span></div><div class="att-absent"><strong id="sumAbsent"><?= $cntAbsent ?></strong><span>Vắng</span></div></div>

      <form method="post" id="attendanceForm">
        <input type="hidden" name="action" value="att_save"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="shift" value="<?= e($shift) ?>"><input type="hidden" name="class" value="<?= e($class) ?>"><input type="hidden" name="view" value="diemdanh">
        <input type="hidden" name="attendance_payload" id="attendancePayload">
        <?php if (can_edit_perm('nt.diemdanh')): ?><div class="att-bulk"><button class="btn btn-outline-success" type="button" onclick="setAll('present')"><i class="bi bi-check-circle"></i> Đủ tất cả</button><button class="btn btn-outline-danger" type="button" onclick="setAll('absent')"><i class="bi bi-x-circle"></i> Vắng tất cả</button></div><?php endif; ?>
        <div class="att-search"><i class="bi bi-search"></i><input class="form-control" type="search" id="studentSearch" placeholder="Tìm học sinh…"></div>
        <div class="att-students" id="studentList">
        <?php foreach ($list as $i=>$student): $record=$attMap[$student['id']]??[]; $status=$record['status']??'present'; $isAbsent=!in_array($status,['present','late'],true); ?>
          <button type="button" class="att-person <?= $isAbsent?'absent':'' ?> <?= $status==='excused'?'excused':'' ?>" data-index="<?= $i ?>" data-name="<?= e($student['name']) ?>" data-class="<?= e($student['class_name']) ?>" <?= can_edit_perm('nt.diemdanh')?'':'disabled' ?>>
              <span class="att-dot"><i class="bi <?= $isAbsent?'bi-check':'bi-circle' ?>"></i></span><span><span class="att-person-name"><?= e($student['name']) ?></span><span class="att-person-class"><?= e($student['class_name']) ?></span><small class="att-person-meta"><?= $isAbsent?e(($record['excuse']??'KP').(($record['reason']??'')?' · '.$record['reason']:'')):'' ?></small></span>
            <input type="hidden" name="sid[]" value="<?= e($student['id']) ?>"><input type="hidden" name="status[]" value="<?= e($status) ?>"><input type="hidden" name="excuse[]" value="<?= e($record['excuse']??'') ?>"><input type="hidden" name="reason[]" value="<?= e($record['reason']??'') ?>">
          </button>
        <?php endforeach; if (!$list): ?><div class="text-center text-muted py-4">Không có học sinh phù hợp.</div><?php endif; ?>
        </div>
        <?php if (can_edit_perm('nt.diemdanh') && $list): ?><button class="btn btn-nt att-save" type="button" onclick="openConfirm()"><i class="bi bi-floppy"></i> Lưu báo cáo</button><?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div></main>

<dialog class="att-dialog" id="absenceDialog"><div class="att-dialog-body">
  <div class="att-dialog-head"><div><h3 class="h5 mb-1">Lý do vắng mặt</h3><p class="text-muted">Cập nhật cho học sinh: <strong id="absenceName"></strong></p></div><button class="att-dialog-close" type="button" onclick="closeDialog('absenceDialog')"><i class="bi bi-x-lg"></i></button></div>
  <label class="form-label fw-bold">Loại vắng</label><div class="d-flex gap-4 mb-3"><label><input type="radio" name="absenceType" value="P"> Có phép (P)</label><label><input type="radio" name="absenceType" value="KP" checked> Không phép (KP)</label></div>
  <label class="form-label fw-bold">Lý do</label><textarea class="form-control" id="absenceReason" rows="3" placeholder="Nhập lý do vắng mặt…"></textarea>
  <div class="att-dialog-actions"><button class="btn btn-outline-secondary" type="button" onclick="markPresentFromDialog()">Có mặt</button><button class="btn btn-nt" type="button" onclick="saveAbsence()">Lưu</button></div>
</div></dialog>

<?php if ($view==='history'): ?><dialog class="att-dialog" id="excelDialog"><div class="att-dialog-body">
  <div class="att-dialog-head"><div><h3 class="h5 mb-1"><i class="bi bi-file-earmark-excel text-success"></i> Xuất Excel học sinh vắng</h3><p class="text-muted mb-0">Chọn khoảng thời gian cần thống kê.</p></div><button class="att-dialog-close" type="button" onclick="closeDialog('excelDialog')"><i class="bi bi-x-lg"></i></button></div>
  <form method="get" class="mt-3" id="excelForm" onsubmit="return prepareExcelExport()">
    <input type="hidden" name="view" value="history"><input type="hidden" name="export" value="excel"><input type="hidden" name="period_value" id="excelPeriodValue">
    <label class="form-label fw-bold">Phạm vi báo cáo</label><select class="form-select mb-3" name="period_type" id="excelPeriodType" onchange="switchExcelPeriod()"><option value="week">Theo tuần</option><option value="month">Theo tháng</option><option value="school_year">Theo năm học</option></select>
    <div data-excel-period="week"><label class="form-label">Chọn tuần</label><input class="form-control" type="week" value="<?= e(date('o-\WW')) ?>"></div>
    <div data-excel-period="month" hidden><label class="form-label">Chọn tháng</label><input class="form-control" type="month" value="<?= e(date('Y-m')) ?>"></div>
    <div data-excel-period="school_year" hidden><label class="form-label">Chọn năm học</label><select class="form-select"><?php for ($i=$schoolStart+1;$i>=$schoolStart-3;$i--): ?><option value="<?= $i.'-'.($i+1) ?>" <?= $i===$schoolStart?'selected':'' ?>><?= $i.' - '.($i+1) ?></option><?php endfor; ?></select></div>
    <div class="att-dialog-actions"><button class="btn btn-outline-secondary" type="button" onclick="closeDialog('excelDialog')">Hủy</button><button class="btn btn-success" type="submit"><i class="bi bi-download"></i> Tải Excel</button></div>
  </form>
</div></dialog><?php endif; ?>

<dialog class="att-dialog" id="confirmDialog"><div class="att-dialog-body">
  <div class="att-dialog-head"><div><h3 class="h5 mb-1"><i class="bi bi-exclamation-triangle text-warning"></i> Xác nhận báo cáo điểm danh</h3><p class="text-muted mb-2"><?= e($dateLabel) ?> · <?= e($shiftLabel) ?></p></div><button class="att-dialog-close" type="button" onclick="closeDialog('confirmDialog')"><i class="bi bi-x-lg"></i></button></div>
  <div class="d-flex justify-content-around border-top border-bottom py-2 mb-2"><span class="text-success">Có mặt: <strong id="confirmPresent"></strong></span><span class="text-danger">Vắng: <strong id="confirmAbsent"></strong></span><span>Tổng: <strong id="confirmTotal"></strong></span></div>
  <div class="att-confirm-list" id="confirmList"></div>
  <label class="form-label fw-bold mt-2">Ghi chú chung</label><textarea name="general_note" form="attendanceForm" class="form-control" rows="2" placeholder="Nhập ghi chú nếu có…"></textarea>
  <div class="att-dialog-actions"><button class="btn btn-outline-secondary" type="button" onclick="closeDialog('confirmDialog')">Quay lại chỉnh sửa</button><button class="btn btn-nt" type="button" onclick="submitAttendanceForm()"><i class="bi bi-check-circle"></i> Xác nhận lưu</button></div>
</div></dialog>

<dialog class="att-dialog" id="reportDialog"><div class="att-dialog-body">
  <div class="att-dialog-head"><h3 class="h5"><i class="bi bi-image"></i> Xuất ảnh báo cáo</h3><button class="att-dialog-close" onclick="closeDialog('reportDialog')"><i class="bi bi-x-lg"></i></button></div>
  <canvas id="reportCanvas" style="width:100%;margin-top:1rem;border:1px solid #eee"></canvas>
  <div class="att-dialog-actions"><button class="btn btn-outline-secondary" onclick="downloadReport()"><i class="bi bi-download"></i> Tải ảnh</button><button class="btn btn-nt" onclick="shareReport()"><i class="bi bi-share"></i> Chia sẻ</button></div>
</div></dialog>

<script>
var activeRow=null;
function closeDialog(id){document.getElementById(id).close()}
function switchExcelPeriod(){var type=document.getElementById('excelPeriodType').value;document.querySelectorAll('[data-excel-period]').forEach(function(box){box.hidden=box.dataset.excelPeriod!==type})}
function prepareExcelExport(){var type=document.getElementById('excelPeriodType').value,box=document.querySelector('[data-excel-period="'+type+'"]'),input=box.querySelector('input,select');document.getElementById('excelPeriodValue').value=input.value;return input.value!==''}
function rowData(row){return {status:row.querySelector('[name="status[]"]'),excuse:row.querySelector('[name="excuse[]"]'),reason:row.querySelector('[name="reason[]"]')}}
function updateRow(row){
  var d=rowData(row), absent=!['present','late'].includes(d.status.value), meta=row.querySelector('.att-person-meta'), icon=row.querySelector('.att-dot i');
  row.classList.toggle('absent',absent);row.classList.toggle('excused',d.status.value==='excused');icon.className='bi '+(absent?'bi-check':'bi-circle');
  meta.textContent=absent?((d.excuse.value||'KP')+(d.reason.value?' · '+d.reason.value:'')):'';updateSummary();
}
function updateSummary(){var rows=[...document.querySelectorAll('.att-person')], absent=rows.filter(r=>r.classList.contains('absent')).length;document.getElementById('sumTotal').textContent=rows.length;document.getElementById('sumPresent').textContent=rows.length-absent;document.getElementById('sumAbsent').textContent=absent}
document.querySelectorAll('.att-person').forEach(function(row){row.addEventListener('click',function(e){if(e.target.matches('input'))return;activeRow=row;var d=rowData(row);document.getElementById('absenceName').textContent=row.dataset.name;document.getElementById('absenceReason').value=d.reason.value;document.querySelector('[name="absenceType"][value="'+(d.excuse.value||'KP')+'"]').checked=true;document.getElementById('absenceDialog').showModal()})});
function saveAbsence(){if(!activeRow)return;var d=rowData(activeRow), type=document.querySelector('[name="absenceType"]:checked').value;d.status.value=type==='P'?'excused':'absent';d.excuse.value=type;d.reason.value=document.getElementById('absenceReason').value.trim();updateRow(activeRow);closeDialog('absenceDialog')}
function markPresentFromDialog(){if(!activeRow)return;var d=rowData(activeRow);d.status.value='present';d.excuse.value='';d.reason.value='';updateRow(activeRow);closeDialog('absenceDialog')}
function setAll(status){document.querySelectorAll('.att-person').forEach(function(row){var d=rowData(row);d.status.value=status;d.excuse.value=status==='absent'?'KP':'';d.reason.value='';updateRow(row)})}
document.getElementById('studentSearch')?.addEventListener('input',function(){var q=this.value.toLocaleLowerCase('vi');document.querySelectorAll('.att-person').forEach(r=>r.hidden=!((r.dataset.name+' '+r.dataset.class).toLocaleLowerCase('vi').includes(q)))});
function openConfirm(){
  var rows=[...document.querySelectorAll('.att-person')], absent=rows.filter(r=>r.classList.contains('absent')), groups={};
  absent.forEach(function(r){(groups[r.dataset.class]??=[]).push(r)});
  document.getElementById('confirmTotal').textContent=rows.length;document.getElementById('confirmPresent').textContent=rows.length-absent.length;document.getElementById('confirmAbsent').textContent=absent.length;
  document.getElementById('confirmList').innerHTML=Object.entries(groups).map(([c,rs])=>'<section class="att-confirm-class"><header><span>'+escapeHtml(c)+'</span><span>'+rs.length+' vắng</span></header>'+rs.map((r,i)=>{var d=rowData(r);return '<div>'+(i+1)+'. '+escapeHtml(r.dataset.name)+' <strong>'+escapeHtml(d.excuse.value||'KP')+'</strong>'+(d.reason.value?' – '+escapeHtml(d.reason.value):'')+'</div>'}).join('')+'</section>').join('')||( '<div class="text-center text-success py-3">Tất cả học sinh có mặt.</div>');
  document.getElementById('confirmDialog').showModal();
}
function submitAttendanceForm(){
  var rows=[...document.querySelectorAll('.att-person')],students=rows.map(function(row){var d=rowData(row),sid=row.querySelector('[name="sid[]"]');return{id:sid?sid.value:'',status:d.status.value,excuse:d.excuse.value,reason:d.reason.value}});
  document.getElementById('attendancePayload').value=JSON.stringify({version:1,students:students});
  rows.forEach(function(row){row.querySelectorAll('input[name$="[]"]').forEach(function(input){input.disabled=true})});
  document.getElementById('attendanceForm').submit();
}
function confirmHistoryBulkDelete(){
  var checked=document.querySelectorAll('input[name="delete_dates[]"]:checked');
  if(!checked.length){alert('Hãy tích chọn ít nhất một ngày cần xoá.');return false}
  return confirm('Bạn sắp xoá toàn bộ dữ liệu điểm danh và lịch sử của '+checked.length+' ngày đã chọn. Thao tác này không thể hoàn tác. Tiếp tục?')
}
function escapeHtml(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML}
function drawReport(){
  var canvas=document.getElementById('reportCanvas'),ctx=canvas.getContext('2d'),rows=[...document.querySelectorAll('.att-person')],abs=rows.filter(r=>r.classList.contains('absent'));canvas.width=900;canvas.height=Math.max(560,390+abs.length*42);ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.textAlign='center';ctx.fillStyle='#64748b';ctx.font='22px Arial';ctx.fillText(<?= json_encode($school, JSON_UNESCAPED_UNICODE) ?>,450,48);ctx.fillStyle='#0284c7';ctx.font='bold 34px Arial';ctx.fillText('ĐIỂM DANH '+<?= json_encode(mb_strtoupper($shiftLabel,'UTF-8'), JSON_UNESCAPED_UNICODE) ?>,450,95);ctx.fillStyle='#334155';ctx.font='22px Arial';ctx.fillText(<?= json_encode($weekdayVi.', '.$dateLabel, JSON_UNESCAPED_UNICODE) ?>,450,132);
  [['TỔNG',rows.length,'#0f172a'],['CÓ MẶT',rows.length-abs.length,'#16a34a'],['VẮNG',abs.length,'#dc2626'],['TỶ LỆ',rows.length?Math.round((rows.length-abs.length)*100/rows.length)+'%':'100%','#0284c7']].forEach((x,i)=>{var x0=70+i*200;ctx.fillStyle='#f8fafc';ctx.fillRect(x0,165,170,100);ctx.fillStyle=x[2];ctx.font='bold 30px Arial';ctx.fillText(x[1],x0+85,207);ctx.font='16px Arial';ctx.fillText(x[0],x0+85,238)});
  ctx.textAlign='left';ctx.fillStyle='#dc2626';ctx.font='bold 20px Arial';ctx.fillText('DANH SÁCH VẮNG ('+abs.length+')',70,310);ctx.font='18px Arial';abs.forEach((r,i)=>{var d=rowData(r);ctx.fillText(r.dataset.class+': '+r.dataset.name+' ('+(d.excuse.value||'KP')+')'+(d.reason.value?' – '+d.reason.value:''),80,350+i*38)});ctx.fillStyle='#475569';ctx.font='16px Arial';var reporterSelect=document.querySelector('[name="reporter"]');ctx.fillText('Người báo: '+(reporterSelect?reporterSelect.value:<?= json_encode($reporter, JSON_UNESCAPED_UNICODE) ?>),70,canvas.height-45);ctx.textAlign='right';ctx.fillText(new Date().toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit'}),830,canvas.height-45)
}
function downloadReport(){drawReport();var a=document.createElement('a');a.download='diem-danh-<?= e($date) ?>.png';a.href=document.getElementById('reportCanvas').toDataURL('image/png');a.click()}
async function shareReport(){drawReport();var c=document.getElementById('reportCanvas');c.toBlob(async function(blob){var file=new File([blob],'diem-danh-<?= e($date) ?>.png',{type:'image/png'});if(navigator.canShare&&navigator.canShare({files:[file]}))await navigator.share({files:[file],title:'Báo cáo điểm danh'});else downloadReport()})}
<?php if ($showReport): ?>window.addEventListener('load',function(){drawReport();document.getElementById('reportDialog').showModal()});<?php endif; ?>
</script>
</body></html>
