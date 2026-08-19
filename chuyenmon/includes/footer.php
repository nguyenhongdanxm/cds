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
  setTimeout(function(){ t.style.transition='opacity .35s, transform .35s';t.style.opacity='0';t.style.transform='translateY(12px)';setTimeout(function(){if(t.parentNode)t.remove();},400);},3500);
})();
</script>
<?php
$cdsSharedWeeksForTimetable=[];
$cdsScriptName=basename((string)($_SERVER['SCRIPT_NAME']??''));
if($cdsScriptName==='thoikhoabieu.php'&&(string)($_GET['tab']??'lookup')==='settings'){
 $sharedWeekHelper=dirname(__DIR__,2).'/includes/school_week_calendar.php';
 if(is_file($sharedWeekHelper)){require_once $sharedWeekHelper;if(function_exists('cds_school_week_calendar'))$cdsSharedWeeksForTimetable=cds_school_week_calendar();}
}
?>
<?php if($cdsSharedWeeksForTimetable):?><script>
(function(){
 var weeks=<?=json_encode($cdsSharedWeeksForTimetable,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,labelInput=document.querySelector('form input[name="week_label"]'),startInput=document.querySelector('form input[name="start_date"]');if(!labelInput||!startInput||!weeks.length)return;var form=labelInput.closest('form');if(!form)return;
 var wrap=document.createElement('div'),label=document.createElement('label');label.className='form-label fw-semibold mb-1';label.textContent='Tuần áp dụng (đồng bộ từ CSDL)';var select=document.createElement('select');select.className='form-select';select.required=true;select.name='shared_week_selector';select.innerHTML='<option value="">— Chọn tuần học —</option>';
 weeks.forEach(function(week){var o=document.createElement('option');o.value=week.key||String(week.number||'');o.dataset.label=week.label||'';o.dataset.start=week.start||'';o.textContent=(week.label||'Tuần')+' · '+vnDate(week.start)+' – '+vnDate(week.end);select.appendChild(o);});wrap.appendChild(label);wrap.appendChild(select);var note=document.createElement('div');note.className='form-text';note.textContent='Tuần học trước 1/2 không làm thay đổi số Tuần 1 chính khóa.';wrap.appendChild(note);form.insertBefore(wrap,labelInput);labelInput.type='hidden';startInput.type='hidden';labelInput.required=true;startInput.required=true;
 select.addEventListener('change',function(){var o=select.options[select.selectedIndex];labelInput.value=o?(o.dataset.label||''):'';startInput.value=o?(o.dataset.start||''):'';});var today=new Date(),iso=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0'),i=weeks.findIndex(function(w){return iso>=w.start&&iso<=w.end;});if(i>=0){select.selectedIndex=i+1;select.dispatchEvent(new Event('change'));}function vnDate(v){if(!v)return'';var p=v.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:v;}
})();
</script><?php endif;?>
<?php if($cdsScriptName==='thoikhoabieu.php'&&(string)($_GET['tab']??'lookup')==='settings'):?><script>
(function(){
 var endpoint='<?=htmlspecialchars(BASE_URL,ENT_QUOTES,'UTF-8')?>tkb_manage.php';fetch(endpoint+'?action=list',{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('forbidden');return r.json();}).then(function(data){if(!data.ok||!Array.isArray(data.weeks))return;var tables=Array.from(document.querySelectorAll('table')),table=tables.find(function(t){return /Các tuần đã cập nhật/i.test((t.closest('section')||{}).innerText||'')||/TRẠNG THÁI/i.test((t.tHead||{}).innerText||'');});if(!table||!table.tHead||!table.tBodies.length)return;var hr=table.tHead.rows[0];if(!hr.querySelector('.tkb-admin-action-head')){var th=document.createElement('th');th.className='tkb-admin-action-head text-center';th.textContent='Thao tác';hr.appendChild(th);}Array.from(table.tBodies[0].rows).forEach(function(row,index){var week=data.weeks[index];if(!week)return;var td=document.createElement('td');td.className='text-center text-nowrap';var edit=document.createElement('button');edit.type='button';edit.className='btn btn-sm btn-outline-primary me-1';edit.innerHTML='<i class="bi bi-pencil"></i> Sửa';var del=document.createElement('button');del.type='button';del.className='btn btn-sm btn-outline-danger';del.innerHTML='<i class="bi bi-trash"></i> Xóa';edit.onclick=function(){editWeek(week,data.csrf)};del.onclick=function(){deleteWeek(week,data.csrf)};td.append(edit,del);row.appendChild(td);});}).catch(function(){});
 function editWeek(w,c){var l=prompt('Tên tuần:',w.label||'');if(l===null)return;l=l.trim();if(!l){alert('Tên tuần không được để trống.');return;}var s=prompt('Ngày bắt đầu (YYYY-MM-DD):',w.start_date||'');if(s===null)return;s=s.trim();if(!/^\d{4}-\d{2}-\d{2}$/.test(s)){alert('Ngày bắt đầu phải có dạng YYYY-MM-DD.');return;}send('update',{csrf:c,id:w.id,label:l,start_date:s});}
 function deleteWeek(w,c){if(confirm('Xóa '+(w.label||'thời khóa biểu')+'?\n\nDữ liệu TKB và các lịch dạy thay gắn với tuần này sẽ bị xóa.'))send('delete',{csrf:c,id:w.id});}
 function send(a,p){var b=new URLSearchParams();b.set('action',a);Object.keys(p).forEach(function(k){b.set(k,p[k]);});fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:b.toString()}).then(function(r){return r.json();}).then(function(d){alert(d.message||'Đã xử lý.');if(d.ok)location.reload();}).catch(function(){alert('Không thực hiện được thao tác.');});}
})();
</script><?php endif;?>
<?php if($cdsScriptName==='thoikhoabieu.php'&&(string)($_GET['tab']??'lookup')==='substitution'):?><script>
(function(){
 var endpoint='<?=htmlspecialchars(BASE_URL,ENT_QUOTES,'UTF-8')?>tkb_substitution_manage.php';fetch(endpoint,{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('forbidden');return r.json();}).then(function(data){if(data.ok&&Array.isArray(data.rows))render(data);}).catch(function(){});
 function render(data){var host=document.querySelector('.container')||document.querySelector('main')||document.body,section=document.createElement('section');section.className='tkb-card p-3 mt-3 mb-3';section.innerHTML='<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2"><div><h5 class="mb-1"><i class="bi bi-trash3 me-1"></i> Quản lý lịch dạy thay</h5><div class="small text-muted">Chọn một hoặc nhiều lịch để xóa. Xóa tại đây sẽ đồng thời gỡ dấu dạy thay trên TKB và Tổng quan.</div></div><button type="button" class="btn btn-sm btn-danger" id="tkbBulkDelete"><i class="bi bi-trash"></i> Xóa đã chọn</button></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th style="width:36px"><input type="checkbox" id="tkbSelectAll"></th><th>Ngày</th><th>GV nghỉ</th><th>Tiết/Lớp</th><th>GV thay</th><th>Trạng thái</th></tr></thead><tbody id="tkbManageBody"></tbody></table></div>';host.appendChild(section);var body=section.querySelector('#tkbManageBody');if(!data.rows.length){body.innerHTML='<tr><td colspan="6" class="text-center text-muted py-3">Chưa có lịch dạy thay.</td></tr>';section.querySelector('#tkbBulkDelete').disabled=true;return;}data.rows.forEach(function(r){var tr=document.createElement('tr'),status=r.status==='approved'?'Đã duyệt':(r.status==='rejected'?'Từ chối':'Chờ duyệt');tr.innerHTML='<td><input class="form-check-input tkb-sub-check" type="checkbox" value="'+esc(r.id)+'"></td><td>'+esc(vnDate(r.date))+'</td><td><strong>'+esc(r.absent_teacher)+'</strong></td><td>'+esc((r.session||'')+' '+(r.period||'')+' · '+(r.class||'')+' · '+(r.subject||''))+'</td><td>'+esc(r.substitute_teacher)+'</td><td>'+esc(status)+'</td>';body.appendChild(tr);});section.querySelector('#tkbSelectAll').onchange=function(){var x=this.checked;section.querySelectorAll('.tkb-sub-check').forEach(function(c){c.checked=x;});};section.querySelector('#tkbBulkDelete').onclick=function(){var ids=Array.from(section.querySelectorAll('.tkb-sub-check:checked')).map(function(c){return c.value;});if(!ids.length){alert('Hãy chọn ít nhất một lịch dạy thay.');return;}if(!confirm('Xóa '+ids.length+' lịch dạy thay đã chọn?\n\nCác hiển thị liên quan trên TKB và Tổng quan cũng sẽ được gỡ.'))return;var b=new URLSearchParams();b.set('action','delete_many');b.set('csrf',data.csrf);ids.forEach(function(id){b.append('ids[]',id);});fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:b.toString()}).then(function(r){return r.json();}).then(function(x){alert(x.message||'Đã xử lý.');if(x.ok)location.reload();}).catch(function(){alert('Không xóa được lịch dạy thay.');});};}
 function vnDate(v){if(!v)return'';var p=v.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:v;}function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
})();
</script><?php endif;?>
<?php if($cdsScriptName==='thoikhoabieu.php'&&(string)($_GET['tab']??'lookup')==='lookup'):?>
<style>
/* Tra cứu TKB: tên GV gọn và bảng mobile co vừa màn hình hơn. */
.tkb-teacher-mini{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media(max-width:767px){
 .tkb-matrix-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
 .tkb-matrix{min-width:540px!important;font-size:.61rem!important}
 .tkb-matrix th,.tkb-matrix td{padding:.06rem .08rem!important}
 .tkb-matrix td{height:31px!important}
 .tkb-matrix thead th{height:26px!important;font-size:.64rem!important}
 .tkb-matrix .session-col{width:25px!important}.tkb-matrix .period-col{width:27px!important}
 .tkb-matrix .session-name,.tkb-matrix .period-no{font-size:.62rem!important}
 .tkb-subject{font-size:.66rem!important;line-height:1!important}
 .tkb-teacher-mini,.tkb-class-mini,.tkb-sub-note{font-size:.58rem!important;line-height:1!important}
}
@media(max-width:430px){.tkb-matrix{min-width:500px!important}.tkb-matrix td{height:29px!important}}
</style>
<script>
(function(){
 /* Hiển thị tên GV trong ô TKB theo dạng: Nguyễn Văn Hồng Dân -> N.V.H.Dân. Giữ tên đầy đủ ở tooltip. */
 function shortTeacherName(name){name=String(name||'').trim().replace(/\s+/g,' ');var p=name.split(' ');if(p.length<2)return name;var last=p.pop(),initials=p.map(function(x){return x.charAt(0).toLocaleUpperCase('vi-VN')+'.';}).join('');return initials+last;}
 document.querySelectorAll('.tkb-teacher-mini').forEach(function(el){var full=el.textContent.trim();if(!full)return;el.title=full;el.textContent=shortTeacherName(full);});
})();
</script>
<?php endif;?>
</body>
</html>
