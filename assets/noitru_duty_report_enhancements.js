(function(){
  var form=document.getElementById('dutyReportForm');
  if(!form)return;

  var incidents=form.querySelector('textarea[name="incidents"]');
  var oldTitle=null;
  document.querySelectorAll('.report-subtitle').forEach(function(el){if(/^3\.3\./.test((el.textContent||'').trim()))oldTitle=el;});
  if(incidents&&oldTitle){
    var VIS='[[NT3.3_THAM_HOI_DUA_DON]]\n', SEP='\n[[NT3.4_SU_VIEC]]\n';
    var raw=incidents.value||'',visitValue='',incidentValue=raw;
    if(raw.indexOf(VIS)===0&&raw.indexOf(SEP)>=0){var cut=raw.indexOf(SEP);visitValue=raw.slice(VIS.length,cut).trim();incidentValue=raw.slice(cut+SEP.length).trim();}
    incidents.value=incidentValue;
    oldTitle.textContent='3.4. Các sự việc phát sinh/vấn đề tồn đọng';
    incidents.placeholder='Nhập nội dung 3.4…';
    var title=document.createElement('div');title.className='report-subtitle';title.textContent='3.3. Thăm hỏi và đưa, đón học sinh:';
    var newHint=document.createElement('div');newHint.className='report-entry-hint';newHint.textContent='Nhập nội dung thăm hỏi học sinh và việc đưa, đón học sinh trong ca trực (nếu có).';
    var area=document.createElement('textarea');area.className='report-entry';area.name='visits_transport';area.placeholder='Nhập nội dung 3.3…';area.value=visitValue;if(incidents.hasAttribute('readonly'))area.setAttribute('readonly','readonly');
    var preview=document.createElement('div');preview.className='report-entry-preview';preview.setAttribute('data-for','visits_transport');
    oldTitle.parentNode.insertBefore(title,oldTitle);oldTitle.parentNode.insertBefore(newHint,oldTitle);oldTitle.parentNode.insertBefore(area,oldTitle);oldTitle.parentNode.insertBefore(preview,oldTitle);
    function syncVisit(){preview.textContent=area.value.trim()||'Không có';}
    area.addEventListener('input',syncVisit);syncVisit();
    form.addEventListener('submit',function(){incidents.value=VIS+area.value.trim()+SEP+incidents.value.trim();});
  }

  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function groupText(items,withBase){if(!items.length)return '<span class="report-empty">Không có</span>';var groups={};items.forEach(function(r){var cls=(r.class||'(Chưa rõ lớp)').trim()||'(Chưa rõ lớp)';(groups[cls]||(groups[cls]=[])).push(r);});return Object.keys(groups).sort(function(a,b){return a.localeCompare(b,'vi',{numeric:true});}).map(function(cls){var names=groups[cls].map(function(r){return esc(r.name)+(withBase?' ('+esc(r.excuse||'KP')+')':'');}).join(', ');return '<div class="attendance-class"><strong>'+esc(cls)+':</strong> '+names+'</div>';}).join('');}
  var params=new URLSearchParams(location.search),date=params.get('date')||'';
  if(!/^\d{4}-\d{2}-\d{2}$/.test(date))return;
  var endpoint=new URL('noitru_attendance_report_detail.php',location.href);endpoint.searchParams.set('date',date);
  fetch(endpoint.toString(),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(data){if(!data||!data.ok||!Array.isArray(data.rows))return;var byLabel={};data.rows.forEach(function(r){var k=String(r.shift_label||r.shift||'').trim();(byLabel[k]||(byLabel[k]=[])).push(r);});document.querySelectorAll('.report-attendance tbody tr').forEach(function(tr){var tds=tr.querySelectorAll('td');if(tds.length<3)return;var label=(tds[0].textContent||'').trim(),rows=byLabel[label]||[];if(!rows.length)return;var permitted=[],mealAfter=[],unexcused=[];rows.forEach(function(r){if(r.meal_after_registration)mealAfter.push(r);else if((r.excuse||'')==='P')permitted.push(r);else unexcused.push(r);});tds[2].innerHTML='<div class="attendance-class"><strong>Có phép:</strong> '+groupText(permitted,false)+'</div><div class="attendance-class"><strong>Có phép sau thời gian đăng ký bữa ăn:</strong> '+groupText(mealAfter,true)+'</div><div class="attendance-class"><strong>Không phép:</strong> '+groupText(unexcused,false)+'</div>';});}).catch(function(){});
})();
