(function(){
 'use strict';
 const form=document.getElementById('open-play-form');if(!form)return;
 const mode=form.querySelector('[name=participant_mode]'),names=form.querySelector('[name=group_names]'),host=document.getElementById('qpGroupCards'),saved=new Map();
 if(!mode||!names||!host)return;
 function render(){
  host.querySelectorAll('select').forEach(el=>saved.set(el.dataset.group,el.value));
  const groups=names.value.split(/\r\n|\r|\n/).map(v=>v.trim()).filter(Boolean).slice(0,30);
  const classes=new Set(Array.from(form.querySelectorAll('[name="class_ids[]"]:checked'),el=>el.value));
  const students=(window.quizGroupCardStudents||[]).filter(s=>classes.has(s.class_id));
  const paper=form.querySelector('[name=mode]:checked')?.value==='paper';
  document.getElementById('qpGroupNames').hidden=mode.value!=='groups';host.replaceChildren();
  groups.forEach((name,i)=>{
   const row=document.createElement('div'),label=document.createElement('label'),select=document.createElement('select');
   row.style.marginTop='10px';label.textContent=name+' · Thẻ QR học sinh';select.name='group_cards['+i+']';select.dataset.group=name;
   select.required=mode.value==='groups'&&paper;select.disabled=mode.value!=='groups';
   select.add(new Option(paper?'Chọn thẻ học sinh đã in…':'Không dùng thẻ · chơi trên máy',''));
   students.forEach(s=>select.add(new Option(s.name,s.id)));
   if(students.some(s=>s.id===saved.get(name)))select.value=saved.get(name);
   row.append(label,select);host.append(row);
  });
 }
 names.addEventListener('input',render);form.addEventListener('change',e=>{if(e.target.name==='mode'||e.target.name==='class_ids[]'||e.target===mode)render();});
 form.addEventListener('submit',e=>{if(mode.value!=='groups')return;const ids=Array.from(host.querySelectorAll('select'),el=>el.value).filter(Boolean);if(new Set(ids).size!==ids.length){e.preventDefault();alert('Mỗi nhóm cần một thẻ học sinh khác nhau.');}});
 render();
})();
