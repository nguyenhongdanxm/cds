<?php
/** Cấu hình buổi điểm danh nội trú */
if (!defined('NOITRU_DIR')) {
    if (!defined('DATA_PATH')) require_once __DIR__ . '/config.php';
    define('NOITRU_DIR', DATA_PATH . '/noitru');
}
if (!defined('NOITRU_SHIFTS')) {
    define('NOITRU_SHIFTS', NOITRU_DIR . '/att_shifts.json');
}

if (!function_exists('noitru_att_shifts_default')) {
function noitru_att_shifts_default() {
    return [
        ['id'=>'the_duc_sang','label'=>'Thể dục buổi sáng','active'=>true,'sort'=>10,'start'=>'05:00','end'=>'06:30'],
        ['id'=>'sang','label'=>'Điểm danh sáng','active'=>true,'sort'=>20,'start'=>'06:31','end'=>'07:30'],
        ['id'=>'trua','label'=>'Giờ ngủ trưa','active'=>true,'sort'=>30,'start'=>'11:30','end'=>'13:30'],
        ['id'=>'toi','label'=>'Điểm danh tối','active'=>true,'sort'=>40,'start'=>'18:00','end'=>'19:30'],
        ['id'=>'hoc_toi','label'=>'Học tối','active'=>true,'sort'=>50,'start'=>'19:31','end'=>'21:30'],
        ['id'=>'dem','label'=>'Điểm danh đêm','active'=>true,'sort'=>60,'start'=>'21:31','end'=>'23:00'],
    ];
}
}

if (!function_exists('noitru_att_shifts_all')) {
function noitru_att_shifts_all() {
    if (function_exists('noitru_ensure_dir')) noitru_ensure_dir();
    elseif (!is_dir(NOITRU_DIR)) @mkdir(NOITRU_DIR, 0755, true);
    $rows = function_exists('load_json') ? load_json(NOITRU_SHIFTS, null) : null;
    if (!is_array($rows) || !$rows) {
        $rows = noitru_att_shifts_default();
        if (function_exists('save_json')) save_json(NOITRU_SHIFTS, $rows);
    } else {
        $defaults = [];
        foreach (noitru_att_shifts_default() as $default) $defaults[$default['id']] = $default;
        $changed = false;
        foreach ($rows as &$row) {
            $default = $defaults[$row['id'] ?? ''] ?? null;
            if ($default && empty($row['start']) && empty($row['end'])) {
                $row['start'] = $default['start'];
                $row['end'] = $default['end'];
                $changed = true;
            }
        }
        unset($row);
        if ($changed && function_exists('save_json')) save_json(NOITRU_SHIFTS, $rows);
    }
    usort($rows, fn($a, $b) => ($a['sort'] ?? 99) <=> ($b['sort'] ?? 99));
    return $rows;
}
}

if (!function_exists('noitru_att_shifts_active')) {
function noitru_att_shifts_active() {
    $out = [];
    foreach (noitru_att_shifts_all() as $s) {
        if (!empty($s['active'])) $out[$s['id']] = $s['label'];
    }
    return $out;
}
}

if (!function_exists('noitru_att_shifts_save')) {
function noitru_att_shifts_save(array $rows) {
    if (function_exists('noitru_ensure_dir')) noitru_ensure_dir();
    $clean = [];
    foreach ($rows as $r) {
        $id = trim($r['id'] ?? '');
        $label = trim($r['label'] ?? '');
        if ($id === '' || $label === '') continue;
        $id = preg_replace('/[^a-z0-9_]/', '', strtolower($id));
        if ($id === '') continue;
        $clean[] = [
            'id' => $id,
            'label' => $label,
            'active' => !empty($r['active']),
            'sort' => (int)($r['sort'] ?? 99),
            'start' => preg_match('/^\d{2}:\d{2}$/', $r['start'] ?? '') ? $r['start'] : '',
            'end' => preg_match('/^\d{2}:\d{2}$/', $r['end'] ?? '') ? $r['end'] : '',
        ];
    }
    if (!$clean) $clean = noitru_att_shifts_default();
    usort($clean, fn($a, $b) => $a['sort'] <=> $b['sort']);
    if (function_exists('save_json')) save_json(NOITRU_SHIFTS, $clean);
    return $clean;
}
}

if (!function_exists('noitru_att_shift_now')) {
function noitru_att_shift_now($time = null) {
    $time = $time ?: date('H:i');
    foreach (noitru_att_shifts_all() as $shift) {
        if (empty($shift['active'])) continue;
        $start = $shift['start'] ?? '';
        $end = $shift['end'] ?? '';
        if ($start === '' || $end === '') continue;
        $inside = $start <= $end
            ? ($time >= $start && $time <= $end)
            : ($time >= $start || $time <= $end);
        if ($inside) return $shift['id'];
    }
    return 'dot_xuat';
}
}

if (!function_exists('noitru_att_bulk')) {
function noitru_att_bulk(array $studentIds, $date, $shift, $status, $by = '') {
    foreach ($studentIds as $sid) {
        $sid = trim($sid);
        if ($sid === '') continue;
        noitru_att_upsert([
            'date' => $date,
            'shift' => $shift,
            'student_id' => $sid,
            'status' => $status,
            'by' => $by,
        ]);
    }
}
}

/*
 * Điểm danh bữa ăn kế thừa trực tiếp dữ liệu Báo ăn đã chốt.
 * Script chỉ gắn vào trang noitru_attendance.php; endpoint JSON không bị ảnh hưởng.
 */
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'noitru_attendance.php') {
    register_shutdown_function(function () {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        ?>
<script id="ntMealAttendancePrefill">
(function(){
  var form=document.getElementById('attendanceForm');
  if(!form||typeof window.rowData!=='function')return;
  var dateInput=form.querySelector('input[name="date"]'),shiftInput=form.querySelector('input[name="shift"]');
  if(!dateInput||!shiftInput)return;
  var url=new URL('noitru_att_meal_prefill.php',location.href);
  url.searchParams.set('date',dateInput.value);
  url.searchParams.set('shift',shiftInput.value);
  fetch(url.toString(),{credentials:'same-origin',headers:{'Accept':'application/json'}})
    .then(function(r){return r.ok?r.json():null;})
    .then(function(res){
      if(!res||!res.ok||!res.applied)return;
      var wanted={};(res.student_ids||[]).forEach(function(id){wanted[String(id)]=true;});
      var applied=0;
      document.querySelectorAll('.att-person').forEach(function(row){
        var sid=row.querySelector('input[name="sid[]"]');if(!sid||!wanted[String(sid.value)])return;
        var d=window.rowData(row);if(!d)return;
        d.status.value='excused';d.excuse.value='P';d.reason.value='';
        row.dataset.mealPrefill='1';
        if(typeof window.updateRow==='function')window.updateRow(row);
        var meta=row.querySelector('.att-person-meta');if(meta)meta.textContent='Có phép · Đã báo vắng '+(res.meal_label||'bữa ăn');
        applied++;
      });
      if(applied){
        var summary=document.querySelector('.att-summary');
        if(summary&&!document.getElementById('ntMealPrefillNotice')){
          var note=document.createElement('div');note.id='ntMealPrefillNotice';note.className='alert alert-info py-2 px-3 mb-3';
          note.innerHTML='<i class="bi bi-link-45deg"></i> <strong>Đã kết nối Báo ăn:</strong> tự đánh dấu <strong>'+applied+'</strong> học sinh <strong>Có phép</strong> theo '+(res.meal_label||'bữa ăn')+' ngày '+dateInput.value.split('-').reverse().join('/')+'. Người trực có thể bấm lại từng học sinh để điều chỉnh theo thực tế.';
          summary.insertAdjacentElement('afterend',note);
        }
      }
    }).catch(function(){});
})();
</script>
        <?php
    });
}
