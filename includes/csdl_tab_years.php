<?php
/** Tab Năm học — $years, $edit_id */
require_once __DIR__ . '/school_week_calendar.php';
$editing_year = null;
if ($edit_id) {
    foreach ($years as $y) {
        if (($y['id'] ?? '') === $edit_id) { $editing_year = $y; break; }
    }
}
$week_year = null;
$week_year_id = trim($_GET['weeks'] ?? '');
if ($week_year_id !== '') $week_year = csdl_year_find($week_year_id);
$week_rows = $week_year ? cds_school_week_calendar($week_year) : [];
$official_week_rows = array_values(array_filter($week_rows, fn($week) => empty($week['is_pre'])));
$pre_week_values = $week_year ? cds_school_preweek_values($week_year) : ['pre_1'=>'','pre_2'=>''];
$pre1Suggestion = $week_year && csdl_date_valid($week_year['start'] ?? '') ? date('Y-m-d', strtotime($week_year['start'] . ' -14 days')) : '';
$pre2Suggestion = $week_year && csdl_date_valid($week_year['start'] ?? '') ? date('Y-m-d', strtotime($week_year['start'] . ' -7 days')) : '';
?>
<div class="row g-4">
  <?php if (!empty($canYearEdit)): ?><div class="col-lg-4">
    <div class="card card-soft"><div class="card-body">
      <h5 class="mb-3"><?= $editing_year ? 'Sửa năm học' : 'Thêm năm học' ?></h5>
      <form method="post">
        <input type="hidden" name="action" value="year_save">
        <input type="hidden" name="id" value="<?= e($editing_year['id'] ?? '') ?>">
        <div class="mb-2"><label class="form-label small">Nhãn *</label>
          <input type="text" name="label" class="form-control" required placeholder="2025–2026" value="<?= e($editing_year['label'] ?? '') ?>"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small">Bắt đầu</label>
            <input type="date" name="start" class="form-control" value="<?= e($editing_year['start'] ?? '') ?>"></div>
          <div class="col-6"><label class="form-label small">Kết thúc</label>
            <input type="date" name="end" class="form-control" value="<?= e($editing_year['end'] ?? '') ?>"></div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="is_current" id="yc" <?= !empty($editing_year['is_current']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="yc">Năm hiện hành</label>
        </div>
        <button class="btn btn-primary w-100" type="submit"><?= $editing_year ? 'Cập nhật' : 'Thêm mới' ?></button>
        <?php if ($editing_year): ?>
          <a href="?tab=years" class="btn btn-outline-secondary w-100 mt-2">Hủy</a>
        <?php endif; ?>
      </form>
    </div></div>
  </div><?php endif; ?>
  <div class="<?= !empty($canYearEdit) ? 'col-lg-8' : 'col-12' ?>">
    <div class="card card-soft"><div class="card-body">
      <h5 class="mb-2">Danh sách năm học</h5>
      <table class="table align-middle mb-0">
        <thead><tr><th>Nhãn</th><th>Thời gian</th><th>TT</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($years as $y): ?>
          <tr>
            <td><strong><?= e($y['label'] ?? '') ?></strong></td>
            <td class="small"><?= e(($y['start'] ?? '') . ' → ' . ($y['end'] ?? '')) ?></td>
            <td><?= !empty($y['is_current']) ? '<span class="badge bg-success">Hiện hành</span>' : '—' ?></td>
            <td class="text-end text-nowrap">
              <?php if (!empty($canYearEdit)): ?>
              <a class="btn btn-sm btn-outline-secondary" href="?tab=years&weeks=<?= urlencode($y['id']) ?>" title="Cài đặt tuần học"><i class="bi bi-calendar-week"></i></a>
              <a class="btn btn-sm btn-outline-primary" href="?tab=years&edit=<?= urlencode($y['id']) ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
              <?php if (empty($y['is_current'])): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="year_set_current">
                <input type="hidden" name="id" value="<?= e($y['id']) ?>">
                <button class="btn btn-sm btn-outline-success" type="submit" title="Đặt hiện hành">Hiện hành</button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
              <?php if (!empty($canYearDelete)): ?><form method="post" class="d-inline" onsubmit="return confirm('Xóa năm học này?')">
                <input type="hidden" name="action" value="year_delete">
                <input type="hidden" name="id" value="<?= e($y['id']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa"><i class="bi bi-trash"></i></button>
              </form><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!empty($canYearEdit)): ?><div class="form-text mt-3"><i class="bi bi-info-circle"></i> Bấm biểu tượng lịch để cài đặt tuần dùng chung cho toàn hệ thống.</div><?php endif; ?>
    </div></div>
  </div>
</div>

<?php if ($week_year && !empty($canYearEdit)): ?>
<div class="modal fade" id="weekSettingsModal" tabindex="-1" aria-labelledby="weekSettingsTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <div>
          <h4 class="modal-title fw-bold" id="weekSettingsTitle">Cài đặt thời gian tuần</h4>
          <p class="text-muted mb-0">Tuần học trước 1/2 là tuần đặc biệt, không làm thay đổi số Tuần 1, 2, 3… chính khóa.</p>
        </div>
        <a class="btn-close" href="?tab=years" aria-label="Đóng"></a>
      </div>
      <div class="modal-body pt-4">
        <div class="mb-4">
          <label class="form-label fw-semibold">Năm học</label>
          <input class="form-control form-control-lg" value="<?= e($week_year['label'] ?? '') ?>" disabled>
        </div>

        <form method="post" action="<?= BASE_URL ?>csdl_preweeks.php" class="border rounded-3 p-3 mb-4 bg-light">
          <input type="hidden" name="year_id" value="<?= e($week_year['id'] ?? '') ?>">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div><h6 class="mb-1"><i class="bi bi-calendar2-minus text-primary"></i> Hai tuần học trước</h6><div class="small text-muted">Để trống tuần không sử dụng.</div></div>
            <button class="btn btn-sm btn-outline-primary" type="button" id="fillPreWeeks"><i class="bi bi-magic"></i> Gợi ý 2 tuần liền trước</button>
          </div>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small fw-semibold">Tuần học trước 1</label><input class="form-control" type="date" name="pre_week_1_start" id="preWeek1" value="<?= e($pre_week_values['pre_1'] ?? '') ?>"><div class="form-text">Bắt đầu tuần sớm hơn.</div></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Tuần học trước 2</label><input class="form-control" type="date" name="pre_week_2_start" id="preWeek2" value="<?= e($pre_week_values['pre_2'] ?? '') ?>"><div class="form-text">Tuần ngay trước Tuần 1 chính thức.</div></div>
          </div>
          <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-floppy"></i> Lưu tuần học trước</button>
        </form>

        <?php if ($week_rows): ?>
        <div class="border rounded-3 overflow-hidden mb-4">
          <div class="px-3 py-2 bg-light fw-semibold">Thứ tự tuần đang dùng chung</div>
          <div class="table-responsive" style="max-height:220px"><table class="table table-sm align-middle mb-0"><thead><tr><th>Tuần</th><th>Bắt đầu</th><th>Kết thúc</th></tr></thead><tbody>
          <?php foreach ($week_rows as $week): ?><tr class="<?= !empty($week['is_pre']) ? 'table-info' : '' ?>"><td><strong><?= e($week['label']) ?></strong></td><td><?= e(date('d/m/Y', strtotime($week['start']))) ?></td><td><?= e(date('d/m/Y', strtotime($week['end']))) ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="action" value="year_week_save">
          <input type="hidden" name="year_id" value="<?= e($week_year['id'] ?? '') ?>">
          <h6 class="mb-2"><i class="bi bi-calendar-week"></i> Điều chỉnh tuần chính khóa</h6>
          <p class="small text-muted">Chọn Tuần 1, 2, 3… cần điều chỉnh. Các tuần sau sẽ tự tính lại; hai tuần học trước không bị đổi.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="weekNumber">Chọn tuần cần điều chỉnh</label>
            <select class="form-select form-select-lg" name="week_number" id="weekNumber" required>
              <?php foreach ($official_week_rows as $week): ?>
              <option value="<?= (int)$week['number'] ?>" data-start="<?= e($week['start']) ?>">
                <?= e($week['label']) ?> (<?= e(date('d/m', strtotime($week['start']))) ?> - <?= e(date('d/m', strtotime($week['end']))) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="weekStart">Ngày bắt đầu tuần</label>
            <input class="form-control form-control-lg" type="date" name="week_start" id="weekStart" value="<?= e($official_week_rows[0]['start'] ?? '') ?>" required>
          </div>
          <div class="rounded-3 border bg-light p-3" id="weekPreview"></div>
          <div class="d-flex justify-content-end gap-2 mt-3"><a class="btn btn-outline-secondary" href="?tab=years">Hủy</a><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> Lưu tuần chính khóa</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var weeks = <?= json_encode($official_week_rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  function isoAdd(value, days) { var date = new Date(value + 'T00:00:00'); date.setDate(date.getDate() + days); return date.toISOString().slice(0, 10); }
  function vn(value) { var parts=value.split('-'); return parts.length===3?parts[2]+'/'+parts[1]+'/'+parts[0]:value; }
  function refreshPreview(resetDate) {
    var select=document.getElementById('weekNumber'), input=document.getElementById('weekStart');
    if(!select||!input)return;
    var number=parseInt(select.value||'1',10), row=weeks.find(function(item){return parseInt(item.number,10)===number;});
    if(resetDate&&row)input.value=row.start;if(!input.value)return;
    var lines=[];for(var offset=0;offset<3;offset++){var start=isoAdd(input.value,offset*7),end=isoAdd(start,6);lines.push('<div class="'+(offset===0?'text-primary fw-semibold':'')+'">Tuần '+(number+offset)+': '+vn(start)+' - '+vn(end)+'</div>');}
    document.getElementById('weekPreview').innerHTML='<div class="fw-semibold mb-2">Xem trước:</div>'+lines.join('')+'<div class="text-muted mt-2">…</div>';
  }
  document.addEventListener('DOMContentLoaded',function(){
    var modalElement=document.getElementById('weekSettingsModal');if(modalElement&&window.bootstrap)new bootstrap.Modal(modalElement).show();
    document.getElementById('weekNumber')?.addEventListener('change',function(){refreshPreview(true);});
    document.getElementById('weekStart')?.addEventListener('change',function(){refreshPreview(false);});
    document.getElementById('fillPreWeeks')?.addEventListener('click',function(){document.getElementById('preWeek1').value=<?= json_encode($pre1Suggestion) ?>;document.getElementById('preWeek2').value=<?= json_encode($pre2Suggestion) ?>;});
    refreshPreview(true);
  });
})();
</script>
<?php endif; ?>
