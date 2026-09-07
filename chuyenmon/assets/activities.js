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
function cmactAddScheduleSlot(){
 const host=document.getElementById('scheduleSlots');if(!host)return;const i=cmactSlotIndex++;
 const row=document.createElement('div');row.className='schedule-slot';row.innerHTML='<div class="d-flex justify-content-between align-items-center mb-2"><strong>Khung '+(i+1)+'</strong><button type="button" class="btn btn-sm btn-outline-danger remove-schedule-slot"><i class="bi bi-trash"></i></button></div><div class="schedule-slot-days mb-2">'+['2','3','4','5','6','7','CN'].map(d=>'<label><input type="checkbox" name="slots['+i+'][days][]" value="'+d+'"> '+(d==='CN'?'Chủ nhật':'Thứ '+d)+'</label>').join('')+'</div><div class="row g-2"><div class="col-md-4"><select class="form-select" name="slots['+i+'][session]"><option>Sáng</option><option>Chiều</option><option>Tối</option></select></div><div class="col-md-4"><input type="time" class="form-control" name="slots['+i+'][start_time]" value="19:30" required></div><div class="col-md-4"><input type="time" class="form-control" name="slots['+i+'][end_time]" value="21:00" required></div></div>';
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
