(function(){
  var form=document.getElementById('dutyReportForm');
  if(!form)return;

  var incidents=form.querySelector('textarea[name="incidents"]');
  var oldTitle=null;
  document.querySelectorAll('.report-subtitle').forEach(function(el){if(/^3\.3\./.test((el.textContent||'').trim()))oldTitle=el;});
  if(incidents&&oldTitle){
    var VIS='[[NT3.3_THAM_HOI_DUA_DON]]\n',SEP='\n[[NT3.4_SU_VIEC]]\n';
    var raw=incidents.value||'',visitValue='',incidentValue=raw;
    if(raw.indexOf(VIS)===0&&raw.indexOf(SEP)>=0){var cut=raw.indexOf(SEP);visitValue=raw.slice(VIS.length,cut).trim();incidentValue=raw.slice(cut+SEP.length).trim();}
    incidents.value=incidentValue;oldTitle.textContent='3.4. Các sự việc phát sinh/vấn đề tồn đọng';incidents.placeholder='Nhập nội dung 3.4…';
    var title=document.createElement('div');title.className='report-subtitle';title.textContent='3.3. Thăm hỏi và đưa, đón học sinh:';
    var newHint=document.createElement('div');newHint.className='report-entry-hint';newHint.textContent='Nhập nội dung thăm hỏi học sinh và việc đưa, đón học sinh trong ca trực (nếu có).';
    var area=document.createElement('textarea');area.className='report-entry';area.name='visits_transport';area.placeholder='Nhập nội dung 3.3…';area.value=visitValue;if(incidents.hasAttribute('readonly'))area.setAttribute('readonly','readonly');
    var preview=document.createElement('div');preview.className='report-entry-preview';preview.setAttribute('data-for','visits_transport');
    oldTitle.parentNode.insertBefore(title,oldTitle);oldTitle.parentNode.insertBefore(newHint,oldTitle);oldTitle.parentNode.insertBefore(area,oldTitle);oldTitle.parentNode.insertBefore(preview,oldTitle);
    function syncVisit(){preview.textContent=area.value.trim()||'Không có';}area.addEventListener('input',syncVisit);syncVisit();
    form.addEventListener('submit',function(){incidents.value=VIS+area.value.trim()+SEP+incidents.value.trim();});
  }

  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function norm(s){return String(s||'').replace(/\s+/g,' ').trim().toLocaleLowerCase('vi');}
  function isMealShift(label){var t=norm(label);return /(?:ăn|an)[ _-]*sáng|(?:an|meal)[_-]*sang|(?:ăn|an)[ _-]*trưa|(?:an|meal)[_-]*trua|(?:ăn|an)[ _-]*tối|(?:an|meal)[_-]*toi/.test(t);}
  function byClass(items){if(!items.length)return '<span class="report-empty">Không có</span>';var groups={};items.forEach(function(r){var c=(r.class||'(Chưa rõ lớp)').trim()||'(Chưa rõ lớp)';(groups[c]||(groups[c]=[])).push(r);});return Object.keys(groups).sort(function(a,b){return a.localeCompare(b,'vi',{numeric:true});}).map(function(c){return '<div class="attendance-class"><strong>'+esc(c)+':</strong> '+groups[c].map(function(r){return esc(r.name)+(r.reason?' – '+esc(r.reason):'');}).join(', ')+'</div>';}).join('');}
  function block(label,items){return '<div class="nt-duty-absence-group"><div class="nt-duty-absence-label">'+esc(label)+' <span>('+items.length+')</span></div><div>'+byClass(items)+'</div></div>';}
  var style=document.createElement('style');style.textContent='.nt-duty-absence-group{display:grid;grid-template-columns:52mm 1fr;gap:2mm;padding:1.1mm 0;border-bottom:1px dotted #bbb}.nt-duty-absence-group:last-child{border-bottom:0}.nt-duty-absence-label{font-style:normal;font-weight:700}.nt-duty-absence-label span{font-weight:400;color:#555}.nt-duty-absence-group .attendance-class{margin:0 0 .6mm}.nt-duty-absence-group .attendance-class:last-child{margin-bottom:0}@media(max-width:900px){.nt-duty-absence-group{grid-template-columns:1fr}}';document.head.appendChild(style);

  var params=new URLSearchParams(location.search),date=params.get('date')||'';if(!/^\d{4}-\d{2}-\d{2}$/.test(date))return;
  var endpoint=new URL('noitru_attendance_report_detail.php',location.href);endpoint.searchParams.set('date',date);
  fetch(endpoint.toString(),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(data){if(!data||!data.ok||!Array.isArray(data.rows))return;var trs=[].slice.call(document.querySelectorAll('.report-attendance tbody tr'));trs.forEach(function(tr){var tds=tr.querySelectorAll('td');if(tds.length<3)return;var label=(tds[0].textContent||'').trim(),rows=data.rows.filter(function(r){return norm(r.shift_label||r.shift)===norm(label);});if(!rows.length&&trs.length===1)rows=data.rows;if(!rows.length)return;var p=[],m=[],k=[];rows.forEach(function(r){var code=String(r.absence_type||r.excuse||'');if(code==='P_SAU_AN'||r.meal_after_registration)m.push(r);else if(code==='P')p.push(r);else k.push(r);});if(isMealShift(label)){tds[2].innerHTML=block('Có phép',p)+block('Có phép sau thời gian đăng ký bữa ăn',m)+block('Không phép',k);}else{p=p.concat(m);tds[2].innerHTML=block('Có phép',p)+block('Không phép',k);}});}).catch(function(){});
})();
