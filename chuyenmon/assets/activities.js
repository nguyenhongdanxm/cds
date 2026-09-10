function cmactEnhanceSelect(select){
 if(!select||select.dataset.enhanced==='1')return;
 select.dataset.enhanced='1';select.removeAttribute('required');select.style.display='none';
 const box=document.createElement('div');box.className='pick-list';box.dataset.for=select.id||'';
 [...select.options].forEach(option=>{
  const label=document.createElement('label');label.className='pick-item';
  const check=document.createElement('input');check.type='checkbox';check.checked=option.selected;check.value=option.value;
  check.addEventListener('change',()=>{option.selected=check.checked;select.dispatchEvent(new Event('change',{bubbles:true}))});
  const text=document.createElement('span');text.textContent=option.text;
  label.append(check,text);box.appendChild(label);
 });
 select.insertAdjacentElement('afterend',box);
}
function cmactSyncPicker(select){
 const box=select&&select.nextElementSibling;if(!box||!box.classList.contains('pick-list'))return;
 [...select.options].forEach((option,i)=>{const input=box.children[i]&&box.children[i].querySelector('input');if(input)input.checked=option.selected});
}
function filterPicker(inputId,selectId){
 const input=document.getElementById(inputId),select=document.getElementById(selectId);if(!input||!select)return;
 const apply=()=>{const q=input.value.toLocaleLowerCase('vi');const box=select.nextElementSibling;if(!box)return;[...select.options].forEach((o,i)=>{if(box.children[i])box.children[i].hidden=!o.text.toLocaleLowerCase('vi').includes(q)})};
 input.addEventListener('input',apply);
}
let cmactSlotIndex=0;
function cmactAddScheduleSlot(preset={}){
 const host=document.getElementById('scheduleSlots');if(!host)return;const i=cmactSlotIndex++;
 const row=document.createElement('div');row.className='schedule-slot';const selectedDays=preset.days||[];row.innerHTML='<div class="d-flex justify-content-between align-items-center mb-2"><strong>Khung '+(i+1)+'</strong><button type="button" class="btn btn-sm btn-outline-danger remove-schedule-slot"><i class="bi bi-trash"></i></button></div><div class="schedule-slot-days mb-2">'+['2','3','4','5','6','7','CN'].map(d=>'<label><input type="checkbox" name="slots['+i+'][days][]" value="'+d+'" '+(selectedDays.includes(d)?'checked':'')+'> '+(d==='CN'?'Chủ nhật':'Thứ '+d)+'</label>').join('')+'</div><div class="row g-2"><div class="col-md-4"><select class="form-select" name="slots['+i+'][session]">'+['Sáng','Chiều','Tối'].map(s=>'<option '+(s===(preset.session||'')?'selected':'')+'>'+s+'</option>').join('')+'</select></div><div class="col-md-4"><input type="time" class="form-control" name="slots['+i+'][start_time]" value="'+(preset.start_time||'19:30')+'" required></div><div class="col-md-4"><input type="time" class="form-control" name="slots['+i+'][end_time]" value="'+(preset.end_time||'21:00')+'" required></div></div>';
 host.appendChild(row);
}
function cmactInit(root=document){
 root.querySelectorAll('select[multiple]').forEach(cmactEnhanceSelect);
 filterPicker('studentFilter','clubStudents');filterPicker('onlineFilter','onlineStudents');if(document.getElementById('scheduleSlots')&&!document.querySelector('.schedule-slot'))cmactAddScheduleSlot();
 root.querySelectorAll('form').forEach(form=>{if(form.dataset.cmactChecked)return;form.dataset.cmactChecked='1';form.addEventListener('submit',e=>{const requiredMulti=form.querySelector('select[multiple][name="student_ids[]"]');if(requiredMulti&&![...requiredMulti.options].some(o=>o.selected)){e.preventDefault();alert('Hãy tích chọn ít nhất một học sinh.')}})});
}
async function cmactOpenTab(url){
 const shell=document.querySelector('.activity-shell');if(!shell)return;
 shell.style.opacity='.55';
 try{
  const response=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});
  if(!response.ok)throw new Error('HTTP '+response.status);
  const doc=new DOMParser().parseFromString(await response.text(),'text/html');
  const next=doc.querySelector('.activity-shell');if(!next)throw new Error('Thiếu nội dung');
  shell.replaceWith(next);cmactInit(next);
 }catch(error){shell.style.opacity='1';alert('Không tải được nội dung tab. Hãy thử tải lại trang.');}
}
document.addEventListener('click',event=>{const button=event.target.closest('.subtabs [data-tab-url]');if(!button)return;event.preventDefault();cmactOpenTab(new URL(button.dataset.tabUrl||button.href,location.href).href)});
function resetClub(){document.getElementById('clubForm').reset();document.getElementById('clubId').value='';document.querySelectorAll('#clubForm select[multiple]').forEach(cmactSyncPicker)}
function editClub(c){document.getElementById('clubId').value=c.id||'';document.getElementById('clubName').value=c.name||'';document.getElementById('clubCode').value=c.code||'';document.getElementById('clubDescription').value=c.description||'';const teachers=c.teacher_ids||[],students=c.student_ids||[];const ts=document.getElementById('clubTeachers'),ss=document.getElementById('clubStudents');[...ts.options].forEach(o=>o.selected=teachers.includes(o.value));[...ss.options].forEach(o=>o.selected=students.includes(o.value));cmactSyncPicker(ts);cmactSyncPicker(ss);document.getElementById('clubForm').scrollIntoView({behavior:'smooth',block:'start'})}
cmactInit();

document.addEventListener('click',event=>{if(event.target.closest('#addScheduleSlot'))cmactAddScheduleSlot();const remove=event.target.closest('.remove-schedule-slot');if(remove){const rows=document.querySelectorAll('.schedule-slot');if(rows.length<=1){alert('Cần giữ ít nhất một khung lịch.');return;}remove.closest('.schedule-slot').remove();}});

function cmactResetOnlineEdit(){
 const form=document.getElementById('onlineEnrollmentForm');if(!form)return;form.reset();document.getElementById('onlineEnrollmentId').value='';document.getElementById('scheduleSlots').innerHTML='';cmactSlotIndex=0;cmactAddScheduleSlot();const select=document.getElementById('onlineStudents');[...select.options].forEach(o=>o.selected=false);cmactSyncPicker(select);document.getElementById('cancelOnlineEdit').classList.add('d-none');document.querySelector('#saveOnlineEnrollment span').textContent='Lưu đăng ký';
}
document.addEventListener('click',event=>{
 const detailButton=event.target.closest('[data-online-detail]');if(detailButton){const payload=JSON.parse(decodeURIComponent(detailButton.dataset.onlineDetail));const record=payload.enrollment||{},student=payload.student||{},dialog=document.getElementById('onlineDetailDialog');if(!dialog)return;const date=value=>{if(!/^\d{4}-\d{2}-\d{2}$/.test(value||''))return 'Chưa xác định';const parts=value.split('-');return parts[2]+'/'+parts[1]+'/'+parts[0]},dayLabel=value=>String(value)==='CN'?'Chủ nhật':'Thứ '+value;dialog.querySelector('[data-detail-name]').textContent=student.name||'—';dialog.querySelector('[data-detail-class]').textContent=student.class||'—';dialog.querySelector('[data-detail-program]').textContent=record.program||'Chưa ghi';dialog.querySelector('[data-detail-duration]').textContent='Từ ngày '+date(record.program_start)+' đến ngày '+date(record.program_end);const slotBody=dialog.querySelector('[data-detail-slots]');slotBody.replaceChildren();let slots=record.slots||[];if(!slots.length)slots=[{days:record.days||[],session:record.session||'',start_time:record.start_time||'',end_time:record.end_time||''}];slots.forEach((slot,index)=>{const row=document.createElement('tr');[String(index+1),(slot.days||[]).map(dayLabel).join(', '),slot.session||'—',(slot.start_time||'—')+' – '+(slot.end_time||'—')].forEach(text=>{const cell=document.createElement('td');cell.textContent=text;row.appendChild(cell)});slotBody.appendChild(row)});const noteWrap=dialog.querySelector('[data-detail-note-wrap]'),note=String(record.note||'').trim();noteWrap.hidden=!note;dialog.querySelector('[data-detail-note]').textContent=note;if(dialog.parentElement!==document.body)document.body.appendChild(dialog);if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');return}
 if(event.target.closest('[data-online-close]')){document.getElementById('onlineDetailDialog')?.close();return}
 if(event.target.closest('[data-online-print]')){window.print();return}
 const edit=event.target.closest('.edit-online-enrollment');if(edit){const record=JSON.parse(decodeURIComponent(edit.dataset.record));const form=document.getElementById('onlineEnrollmentForm');if(!form)return;document.getElementById('onlineEnrollmentId').value=record.id||'';const select=document.getElementById('onlineStudents');[...select.options].forEach(o=>o.selected=o.value===(record.student_id||''));cmactSyncPicker(select);form.elements.program_start.value=record.program_start||'';form.elements.program_end.value=record.program_end||'';form.elements.program.value=record.program||'';form.elements.note.value=record.note||'';const host=document.getElementById('scheduleSlots');host.innerHTML='';cmactSlotIndex=0;let slots=record.slots||[];if(!slots.length)slots=[{days:record.days||[],session:record.session||'',start_time:record.start_time||'',end_time:record.end_time||''}];slots.forEach(cmactAddScheduleSlot);document.getElementById('cancelOnlineEdit').classList.remove('d-none');document.querySelector('#saveOnlineEnrollment span').textContent='Cập nhật đăng ký';form.scrollIntoView({behavior:'smooth',block:'start'});}
 if(event.target.closest('#cancelOnlineEdit'))cmactResetOnlineEdit();
});
