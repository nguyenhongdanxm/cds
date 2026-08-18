</div>
<footer class="text-center text-muted py-3 border-top bg-white">
<small>Chuyên môn – Trường PTDTNT THCS&THPT Xín Mần &copy; 2026</small><br>
<small class="text-secondary">Thiết kế bởi thầy giáo Nguyễn Hồng Dân</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var t = document.getElementById('pccm-toast');
  if (!t) return;
  setTimeout(function(){
    t.style.transition = 'opacity .35s, transform .35s';
    t.style.opacity = '0';
    t.style.transform = 'translateY(12px)';
    setTimeout(function(){ if (t.parentNode) t.remove(); }, 400);
  }, 3500);
})();
</script>
<?php
/*
 * Riêng trang Cài đặt TKB: lấy danh sách tuần từ CSDL năm học hiện hành.
 * Giữ input cũ làm fallback nếu lịch tuần dùng chung chưa được cài.
 */
$cdsSharedWeeksForTimetable = [];
$cdsScriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($cdsScriptName === 'thoikhoabieu.php' && (string)($_GET['tab'] ?? 'lookup') === 'settings') {
    $sharedWeekHelper = dirname(__DIR__, 2) . '/includes/school_week_calendar.php';
    if (is_file($sharedWeekHelper)) {
        require_once $sharedWeekHelper;
        if (function_exists('cds_school_week_calendar')) $cdsSharedWeeksForTimetable = cds_school_week_calendar();
    }
}
?>
<?php if ($cdsSharedWeeksForTimetable): ?>
<script>
(function(){
  var weeks = <?= json_encode($cdsSharedWeeksForTimetable, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  var labelInput = document.querySelector('form input[name="week_label"]');
  var startInput = document.querySelector('form input[name="start_date"]');
  if (!labelInput || !startInput || !weeks.length) return;
  var form = labelInput.closest('form');
  if (!form) return;

  var wrap = document.createElement('div');
  var label = document.createElement('label');
  label.className = 'form-label fw-semibold mb-1';
  label.textContent = 'Tuần áp dụng (đồng bộ từ CSDL)';
  var select = document.createElement('select');
  select.className = 'form-select';
  select.required = true;
  select.name = 'shared_week_selector';
  select.innerHTML = '<option value="">— Chọn tuần học —</option>';
  weeks.forEach(function(week){
    var option = document.createElement('option');
    option.value = week.key || String(week.number || '');
    option.dataset.label = week.label || '';
    option.dataset.start = week.start || '';
    option.textContent = (week.label || 'Tuần') + ' · ' + vnDate(week.start) + ' – ' + vnDate(week.end);
    select.appendChild(option);
  });
  wrap.appendChild(label);
  wrap.appendChild(select);
  var note = document.createElement('div');
  note.className = 'form-text';
  note.textContent = 'Tuần học trước 1/2 không làm thay đổi số Tuần 1 chính khóa.';
  wrap.appendChild(note);
  form.insertBefore(wrap, labelInput);

  labelInput.type = 'hidden';
  startInput.type = 'hidden';
  labelInput.required = true;
  startInput.required = true;

  select.addEventListener('change', function(){
    var option = select.options[select.selectedIndex];
    labelInput.value = option ? (option.dataset.label || '') : '';
    startInput.value = option ? (option.dataset.start || '') : '';
  });

  var today = new Date();
  var iso = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-' + String(today.getDate()).padStart(2,'0');
  var currentIndex = weeks.findIndex(function(week){ return iso >= week.start && iso <= week.end; });
  if (currentIndex >= 0) {
    select.selectedIndex = currentIndex + 1;
    select.dispatchEvent(new Event('change'));
  }

  function vnDate(value){
    if(!value) return '';
    var p=value.split('-');
    return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:value;
  }
})();
</script>
<?php endif; ?>
</body>
</html>
