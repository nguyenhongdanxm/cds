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
<?php if ($cdsScriptName === 'thoikhoabieu.php' && (string)($_GET['tab'] ?? 'lookup') === 'settings'): ?>
<script>
(function(){
  var endpoint = '<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>tkb_manage.php';
  fetch(endpoint + '?action=list', {credentials:'same-origin'})
    .then(function(r){ if(!r.ok) throw new Error('forbidden'); return r.json(); })
    .then(function(data){
      if(!data.ok || !Array.isArray(data.weeks)) return;
      var tables = Array.from(document.querySelectorAll('table'));
      var table = tables.find(function(t){ return /Các tuần đã cập nhật/i.test((t.closest('section')||{}).innerText||'') || /TRẠNG THÁI/i.test((t.tHead||{}).innerText||''); });
      if(!table || !table.tHead || !table.tBodies.length) return;
      var headRow = table.tHead.rows[0];
      if(!headRow.querySelector('.tkb-admin-action-head')){
        var th=document.createElement('th'); th.className='tkb-admin-action-head text-center'; th.textContent='Thao tác'; headRow.appendChild(th);
      }
      var rows=Array.from(table.tBodies[0].rows);
      rows.forEach(function(row,index){
        var week=data.weeks[index]; if(!week) return;
        var td=document.createElement('td'); td.className='text-center text-nowrap';
        var edit=document.createElement('button'); edit.type='button'; edit.className='btn btn-sm btn-outline-primary me-1'; edit.innerHTML='<i class="bi bi-pencil"></i> Sửa';
        var del=document.createElement('button'); del.type='button'; del.className='btn btn-sm btn-outline-danger'; del.innerHTML='<i class="bi bi-trash"></i> Xóa';
        edit.addEventListener('click',function(){ editWeek(week,data.csrf); });
        del.addEventListener('click',function(){ deleteWeek(week,data.csrf); });
        td.appendChild(edit);td.appendChild(del);row.appendChild(td);
      });
    }).catch(function(){});

  function editWeek(week,csrf){
    var label=prompt('Tên tuần:',week.label||''); if(label===null) return; label=label.trim(); if(!label){alert('Tên tuần không được để trống.');return;}
    var start=prompt('Ngày bắt đầu (YYYY-MM-DD):',week.start_date||''); if(start===null) return; start=start.trim();
    if(!/^\d{4}-\d{2}-\d{2}$/.test(start)){alert('Ngày bắt đầu phải có dạng YYYY-MM-DD.');return;}
    send('update',{csrf:csrf,id:week.id,label:label,start_date:start});
  }
  function deleteWeek(week,csrf){
    var warning='Xóa '+(week.label||'thời khóa biểu')+'?\n\nDữ liệu TKB và các lịch dạy thay gắn với tuần này sẽ bị xóa.';
    if(!confirm(warning)) return;
    send('delete',{csrf:csrf,id:week.id});
  }
  function send(action,payload){
    var body=new URLSearchParams(); body.set('action',action); Object.keys(payload).forEach(function(k){body.set(k,payload[k]);});
    fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()})
      .then(function(r){return r.json();}).then(function(data){alert(data.message||'Đã xử lý.');if(data.ok) location.reload();})
      .catch(function(){alert('Không thực hiện được thao tác.');});
  }
})();
</script>
<?php endif; ?>
<?php if ($cdsScriptName === 'thoikhoabieu.php' && (string)($_GET['tab'] ?? 'lookup') === 'substitution'): ?>
<script>
(function(){
  var endpoint='<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>tkb_substitution_manage.php';
  fetch(endpoint,{credentials:'same-origin'})
    .then(function(r){if(!r.ok)throw new Error('forbidden');return r.json();})
    .then(function(data){if(!data.ok||!Array.isArray(data.rows))return;render(data);})
    .catch(function(){});

  function render(data){
    var host=document.querySelector('.container')||document.querySelector('main')||document.body;
    var section=document.createElement('section');
    section.className='tkb-card p-3 mt-3 mb-3';
    section.innerHTML='<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2"><div><h5 class="mb-1"><i class="bi bi-trash3 me-1"></i> Quản lý lịch dạy thay</h5><div class="small text-muted">Chọn một hoặc nhiều lịch để xóa. Xóa tại đây sẽ đồng thời gỡ dấu dạy thay trên TKB và Tổng quan.</div></div><button type="button" class="btn btn-sm btn-danger" id="tkbBulkDelete"><i class="bi bi-trash"></i> Xóa đã chọn</button></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th style="width:36px"><input type="checkbox" id="tkbSelectAll"></th><th>Ngày</th><th>GV nghỉ</th><th>Tiết/Lớp</th><th>GV thay</th><th>Trạng thái</th></tr></thead><tbody id="tkbManageBody"></tbody></table></div>';
    host.appendChild(section);
    var body=section.querySelector('#tkbManageBody');
    if(!data.rows.length){body.innerHTML='<tr><td colspan="6" class="text-center text-muted py-3">Chưa có lịch dạy thay.</td></tr>';section.querySelector('#tkbBulkDelete').disabled=true;return;}
    data.rows.forEach(function(r){
      var tr=document.createElement('tr');
      var status=r.status==='approved'?'Đã duyệt':(r.status==='rejected'?'Từ chối':'Chờ duyệt');
      tr.innerHTML='<td><input class="form-check-input tkb-sub-check" type="checkbox" value="'+esc(r.id)+'"></td><td>'+esc(vnDate(r.date))+'</td><td><strong>'+esc(r.absent_teacher)+'</strong></td><td>'+esc((r.session||'')+' '+(r.period||'')+' · '+(r.class||'')+' · '+(r.subject||''))+'</td><td>'+esc(r.substitute_teacher)+'</td><td>'+esc(status)+'</td>';
      body.appendChild(tr);
    });
    section.querySelector('#tkbSelectAll').addEventListener('change',function(){var checked=this.checked;section.querySelectorAll('.tkb-sub-check').forEach(function(c){c.checked=checked;});});
    section.querySelector('#tkbBulkDelete').addEventListener('click',function(){
      var ids=Array.from(section.querySelectorAll('.tkb-sub-check:checked')).map(function(c){return c.value;});
      if(!ids.length){alert('Hãy chọn ít nhất một lịch dạy thay.');return;}
      if(!confirm('Xóa '+ids.length+' lịch dạy thay đã chọn?\n\nCác hiển thị liên quan trên TKB và Tổng quan cũng sẽ được gỡ.'))return;
      var bodyData=new URLSearchParams();bodyData.set('action','delete_many');bodyData.set('csrf',data.csrf);ids.forEach(function(id){bodyData.append('ids[]',id);});
      fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:bodyData.toString()})
       .then(function(r){return r.json();}).then(function(result){alert(result.message||'Đã xử lý.');if(result.ok)location.reload();}).catch(function(){alert('Không xóa được lịch dạy thay.');});
    });
  }
  function vnDate(v){if(!v)return'';var p=v.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:v;}
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
})();
</script>
<?php endif; ?>
</body>
</html>
