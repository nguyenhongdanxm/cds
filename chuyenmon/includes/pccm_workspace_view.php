<?php
/** Same-page editors. Existing forms/actions and permissions remain unchanged. */
?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const editor=document.getElementById('pccmCompose');if(!editor||typeof bootstrap==='undefined')return;
 const host=document.getElementById('pccmManualHost'),manual=host?.querySelector('[data-native-manual]');
 // Remove stale legacy panels if an older header script is still present during deployment.
 document.querySelectorAll('#board .cds-manual-chip').forEach(p=>p.remove());
 if(manual){document.querySelectorAll('#cdsManualAssignments').forEach(p=>{if(p!==manual)p.remove();});manual.querySelectorAll('.cds-manual-chip').forEach(p=>p.remove());manual.querySelectorAll('datalist').forEach(list=>{const seen=new Set();list.querySelectorAll('option').forEach(o=>{if(seen.has(o.value))o.remove();else seen.add(o.value);});});}
 function openProgram(){resetContext();bootstrap.Tab.getOrCreateInstance(document.getElementById('pccmProgramTab')).show();bootstrap.Modal.getOrCreateInstance(editor).show();}
 document.querySelectorAll('[data-pccm-program]').forEach(b=>b.addEventListener('click',openProgram));
 const context=document.createElement('div');context.className='pccm-context-teacher';context.hidden=true;editor.querySelector('.modal-body').prepend(context);
 function resetContext(){context.hidden=true;editor.querySelectorAll('[name="teacher"]').forEach(s=>{const box=s.closest('.col-md-3');if(box)box.hidden=false;});const manualTeacher=editor.querySelector('[name="manual_teacher"]');if(manualTeacher){manualTeacher.readOnly=false;const field=manualTeacher.closest('.pccm-manual-teacher-field');if(field)field.hidden=false;}editor.querySelectorAll('[data-manual-teacher]').forEach(g=>g.hidden=false);}
 function chooseTeacher(name,subject='',mode='day'){
  resetContext();context.textContent=name+' · Chọn môn và lớp để gán';context.hidden=false;
  editor.querySelectorAll('[name="teacher"]').forEach(select=>{if(!Array.from(select.options).some(o=>o.value===name))select.add(new Option(name,name));select.value=name;const box=select.closest('.col-md-3');if(box)box.hidden=true;});
  const subjectSelect=document.getElementById('subject');subjectSelect.value=subject||'';document.getElementById('class_name').value='';subjectSelect.dispatchEvent(new Event('change',{bubbles:true}));editor.querySelectorAll('#tabMulti [name="classes[]"]').forEach(c=>c.checked=false);
  const mt=editor.querySelector('[name="manual_teacher"]');if(mt){mt.value=name;mt.readOnly=true;const field=mt.closest('.pccm-manual-teacher-field');if(field)field.hidden=true;}editor.querySelectorAll('[data-manual-teacher]').forEach(g=>g.hidden=g.dataset.manualTeacher!==name);
  if(mode==='program'){const first=editor.querySelector('#gdpRanges select');if(first&&(editor.querySelectorAll('#gdpRanges select').length===1||!first.value)){first.value=name;first.dispatchEvent(new Event('change',{bubbles:true}));}}
  const tab=mode==='program'?'#tabProgram':mode==='manual'?'#tabManual':'#tabDay';
  bootstrap.Tab.getOrCreateInstance(editor.querySelector('[data-bs-target="'+tab+'"]')).show();
  if(subject&&mode==='day'){const select=document.getElementById('subject');select.value=subject;select.dispatchEvent(new Event('change',{bubbles:true}));}
  bootstrap.Modal.getOrCreateInstance(editor).show();
 }
 document.querySelectorAll('[data-pccm-assign]').forEach(b=>b.addEventListener('click',()=>chooseTeacher(b.dataset.pccmAssign,b.dataset.pccmSubject||'',b.dataset.pccmMode||'day')));
 document.querySelectorAll('[data-pccm-manual]').forEach(b=>b.addEventListener('click',()=>chooseTeacher(b.dataset.pccmManual,'','manual')));
 document.querySelectorAll('[data-bs-target="#pccmCompose"]').forEach(b=>b.addEventListener('click',()=>{resetContext();bootstrap.Tab.getOrCreateInstance(editor.querySelector('[data-bs-target="#tabDay"]')).show();}));
 function assignmentFilter(){
  if(window.pccmApplyTeacherFilters)window.pccmApplyTeacherFilters();
  const subject=document.getElementById('fSubject').value,grade=document.getElementById('fGrade').value,cls=document.getElementById('fClass').value;
  let shown=0;const rows=document.querySelectorAll('#boardBody .board-row');
  rows.forEach(row=>{const baseVisible=row.style.display!=='none';let found=false;row.querySelectorAll('[data-subject-row]').forEach(group=>{let count=0;group.querySelectorAll('[data-assignment-class]').forEach(chip=>{const match=(!grade||chip.dataset.grade===grade)&&(!cls||chip.dataset.assignmentClass===cls);chip.hidden=!match;if(match)count++;});group.hidden=!!subject&&group.dataset.subjectRow!==subject||count===0;if(!group.hidden)found=true;});row.style.display=baseVisible&&(!(subject||grade||cls)||found)?'':'none';if(row.style.display!=='none')shown++;});
  const advanced=['fLevel','fGroup','fLoad'].map(id=>document.getElementById(id)).filter(e=>e.value);if(advanced.length)document.querySelector('.pccm-extra-filters').open=true;const active=[subject,grade?'Khối '+grade:'',cls,...advanced.map(e=>e.selectedOptions[0].textContent)].filter(Boolean);document.getElementById('fCount').textContent=shown+'/'+rows.length+' GV'+(active.length?' · Đang lọc: '+active.join(' · '):' · Tất cả môn, lớp');
 }
 ['boardFilter','fLevel','fGroup','fLoad','fSubject','fGrade','fClass'].forEach(id=>{const el=document.getElementById(id);el?.addEventListener(id==='boardFilter'?'input':'change',assignmentFilter);});
 document.getElementById('fGrade').addEventListener('change',()=>{const grade=document.getElementById('fGrade').value,select=document.getElementById('fClass');Array.from(select.options).forEach(o=>o.hidden=!!o.value&&!!grade&&o.dataset.grade!==grade);if(select.selectedOptions[0]?.hidden)select.value='';assignmentFilter();});
 document.getElementById('fClear').addEventListener('click',()=>{['fSubject','fGrade','fClass'].forEach(id=>document.getElementById(id).value='');Array.from(document.getElementById('fClass').options).forEach(o=>o.hidden=false);assignmentFilter();});
 assignmentFilter();
 const params=new URL(location.href).searchParams;
 if(params.has('program'))openProgram();
 if(location.hash==='#rasoat')bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAudit')).show();
 if(location.hash==='#doicheo')bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSwap')).show();
 // The form stays directly accessible; only the existing configuration list is collapsed.
 const panels=document.querySelectorAll('#tabProgram .gdp-wrap>.gdp-panel');if(panels.length>1){const list=panels[panels.length-1],details=document.createElement('details'),label=document.createElement('summary');label.textContent=list.querySelector('h6')?.textContent||'Các lớp đã cấu hình';details.className='small border-top pt-2';details.append(label);list.before(details);details.append(list);const title=list.querySelector('h6');if(title)title.hidden=true;}
});
</script>
