(function(){
'use strict';
var cells=Array.from(document.querySelectorAll('.slot-grid td'));
var single=null, multiple=new Map(), active=requestedPanel;
var key='cds.lessonConstraints.'+document.querySelector('main').dataset.workspace;
function slot(cell){return {day:cell.dataset.day,session:cell.dataset.session,period:cell.dataset.period};}
function id(s){return s.day+'|'+s.session+'|'+s.period;}
function render(){
 var special=active==='special', chosen=special?multiple:new Map(single?[[id(single),single]]:[]);
 cells.forEach(function(c){var yes=chosen.has(id(slot(c)));c.classList.toggle('selected',yes);c.setAttribute('aria-pressed',String(yes));});
 var label=document.getElementById('selectedSlot');
 label.textContent=special?'Đã chọn '+multiple.size+' tiết':single?(Number(single.day)===8?'Chủ nhật':'Thứ '+single.day)+' · '+single.session+' · Tiết '+single.period:'Chưa chọn ô';
 document.querySelectorAll('.slot-form').forEach(function(form){
  var s=single, isSpecial=form.closest('#specialPanel');
  form.querySelector('.slot-submit').disabled=isSpecial?multiple.size===0:!s;
  if(s){form.querySelector('.slot-day').value=s.day;form.querySelector('.slot-session').value=s.session;form.querySelector('.slot-period').value=s.period;}
 });
 var inputs=document.getElementById('specialSlotInputs');inputs.replaceChildren();
 Array.from(multiple.values()).forEach(function(s,i){Object.keys(s).forEach(function(k){var input=document.createElement('input');input.type='hidden';input.name='slots['+i+']['+k+']';input.value=s[k];inputs.appendChild(input);});});
}
cells.forEach(function(cell){
 cell.tabIndex=0;cell.setAttribute('role','button');
 function choose(){var s=slot(cell);if(active==='special'){var k=id(s);if(multiple.has(k))multiple.delete(k);else multiple.set(k,s);}else single=s;render();}
 cell.addEventListener('click',choose);
 cell.addEventListener('keydown',function(ev){if(ev.key===' '||ev.key==='Enter'){ev.preventDefault();choose();}});
});
function updateScope(select){
 var form=select.closest('form'),scope=select.value;
 form.querySelectorAll('.scope-value').forEach(function(x){x.classList.remove('show');var child=x.querySelector('[data-scope-name]');if(child)child.removeAttribute('name');});
 if(scope!=='school'){var box=form.querySelector('.'+scope+'-value');if(box){box.classList.add('show');box.querySelector('[data-scope-name]').name='scope_value';}}
}
document.querySelectorAll('.scope-select').forEach(function(select){updateScope(select);select.addEventListener('change',function(){updateScope(this);});});
var useClasses=document.getElementById('specialUseClasses'),classBox=document.getElementById('specialClasses'),scopeSelect=document.querySelector('#specialPanel .scope-select');
function classesMode(){
 classBox.hidden=!useClasses.checked;scopeSelect.disabled=useClasses.checked;
 classBox.querySelectorAll('[name="classes[]"]').forEach(function(c){c.disabled=!useClasses.checked;});
 var hidden=document.querySelector('#specialPanel input[data-multi-scope]');
 if(!hidden){hidden=document.createElement('input');hidden.type='hidden';hidden.name='scope';hidden.value='classes';hidden.dataset.multiScope='1';scopeSelect.form.appendChild(hidden);}
 hidden.disabled=!useClasses.checked;
 var all=document.getElementById('specialAllClasses'),boxes=Array.from(classBox.querySelectorAll('[name="classes[]"]'));
 all.checked=boxes.length>0&&boxes.every(function(c){return c.checked;});all.indeterminate=boxes.some(function(c){return c.checked;})&&!all.checked;
}
useClasses.addEventListener('change',classesMode);
classBox.addEventListener('change',function(ev){if(ev.target.id==='specialAllClasses')classBox.querySelectorAll('[name="classes[]"]').forEach(function(c){c.checked=ev.target.checked;});classesMode();});
document.getElementById('specialClearSlots').addEventListener('click',function(){multiple.clear();render();});
document.querySelectorAll('.slot-form').forEach(function(form){form.addEventListener('submit',function(ev){
 if(form.closest('#specialPanel')){if(!multiple.size){ev.preventDefault();alert('Hãy chọn ít nhất một ô trên bảng TKB.');}else if(useClasses.checked&&!classBox.querySelector('[name="classes[]"]:checked')){ev.preventDefault();alert('Hãy chọn ít nhất một lớp áp dụng.');}}
 else if(!single){ev.preventDefault();alert('Hãy chọn một ô trên bảng TKB.');}
});});
function setPanel(name){
 if(!['fixed','special','double','sequence'].includes(name))name='fixed';
 active=name;showOnlyPanel(name);
 document.querySelectorAll('[data-bs-target$="Panel"]').forEach(function(b){b.classList.toggle('active',b.dataset.bsTarget==='#'+name+'Panel');});
 var url=new URL(location.href);url.searchParams.set('panel',name);history.replaceState(null,'',url);render();
}
document.querySelectorAll('[data-bs-target$="Panel"]').forEach(function(b){b.addEventListener('click',function(){setPanel(b.dataset.bsTarget.slice(1,-5));});});
function editableControls(form){return Array.from(form.elements).filter(function(el){return el.type!=='hidden';});}
var saved=null;try{saved=JSON.parse(sessionStorage.getItem(key)||'null');sessionStorage.removeItem(key);}catch(e){}
if(saved&&Date.now()-saved.time<30*60*1000){
 single=saved.single; (saved.multiple||[]).forEach(function(s){if(cells.some(function(c){return id(slot(c))===id(s);}))multiple.set(id(s),s);});
 if(single&&!cells.some(function(c){return id(slot(c))===id(single);}))single=null;
 // Restore only controls from these forms; never restore CSRF or actions.
 (saved.controls||[]).forEach(function(r){var form=document.forms[r.form];if(!form)return;var el=editableControls(form)[r.index];if(!el||el.type==='hidden'||(el.name!==r.name&&!(r.name==='scope_value'&&el.dataset.scopeName))||el.id!==r.id)return;if(el.type==='checkbox')el.checked=r.checked;else el.value=r.value;});
 if(saved.panel===requestedPanel)active=saved.panel;
 document.querySelectorAll('.scope-select').forEach(updateScope);classesMode();
 history.scrollRestoration='manual';
 requestAnimationFrame(function(){window.scrollTo(0,Math.max(0,Number(saved.y)||0));});
}
classesMode();setPanel(active);
document.addEventListener('submit',function(ev){
 if(ev.defaultPrevented)return;var form=ev.target;
 if(!form.matches('form'))return;
 if(form.method.toLowerCase()==='post'){
  function hidden(name,value){var el=form.querySelector('input[name="'+name+'"]');if(!el){el=document.createElement('input');el.type='hidden';el.name=name;form.appendChild(el);}el.value=value;}
  hidden('return_constraints','1');
  var url=new URL(location.href);['double_scope','double_value','double_subject','double_q'].forEach(function(k){if(url.searchParams.has(k))hidden('return_'+k,url.searchParams.get(k));});
 }
 var controls=[];Array.from(document.forms).forEach(function(f,fi){editableControls(f).forEach(function(el,index){if(el.type!=='hidden'&&(el.name||el.id)&&el.type!=='submit')controls.push({form:fi,index:index,name:el.name,id:el.id,value:el.value,checked:el.checked});});});
 try{sessionStorage.setItem(key,JSON.stringify({time:Date.now(),panel:active,y:window.scrollY,single:single,multiple:Array.from(multiple.values()),controls:controls}));}catch(e){}
});
})();
