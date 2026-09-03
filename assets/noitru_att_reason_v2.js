(function(){
  var P='P', LATE='P_SAU_AN', KP='KP';
  var PREFIX='[Có phép sau thời gian đăng ký bữa ăn] ';
  var labels={P:'Có phép',P_SAU_AN:'Có phép sau thời gian đăng ký bữa ăn',KP:'Không phép'};
  function label(code){return labels[code]||code||labels[P];}
  function data(row){return typeof window.rowData==='function'?window.rowData(row):null;}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}

  var dialog=document.getElementById('absenceDialog');
  if(dialog){
    var oldMeal=document.getElementById('absenceMealAfter');
    if(oldMeal){var wrap=oldMeal.closest('.form-check');if(wrap)wrap.remove();}
    var first=dialog.querySelector('input[name="absenceType"]');
    var typeWrap=first?first.closest('.d-flex'):null;
    if(typeWrap){
      typeWrap.className='nt-absence-type-grid mb-3';
      typeWrap.innerHTML=''
        +'<label class="nt-absence-type"><input type="radio" name="absenceType" value="P" checked><span><strong>Có phép</strong><small>Mặc định</small></span></label>'
        +'<label class="nt-absence-type"><input type="radio" name="absenceType" value="P_SAU_AN"><span><strong>Có phép sau thời gian đăng ký bữa ăn</strong><small>Báo nghỉ sau thời điểm chốt bữa ăn</small></span></label>'
        +'<label class="nt-absence-type"><input type="radio" name="absenceType" value="KP"><span><strong>Không phép</strong><small>Vắng không được phép</small></span></label>';
      if(!document.getElementById('ntAbsenceTypeStyle')){var st=document.createElement('style');st.id='ntAbsenceTypeStyle';st.textContent='.nt-absence-type-grid{display:grid;gap:.55rem}.nt-absence-type{display:flex;align-items:center;gap:.7rem;padding:.7rem .8rem;border:1px solid #dbe4eb;border-radius:12px;background:#fff;cursor:pointer}.nt-absence-type:has(input:checked){border-color:#0ea5e9;background:#f0f9ff;box-shadow:0 0 0 2px rgba(14,165,233,.08)}.nt-absence-type input{width:1.05rem;height:1.05rem}.nt-absence-type span{display:flex;align-items:baseline;gap:.5rem;flex-wrap:wrap}.nt-absence-type strong{font-size:.92rem}.nt-absence-type small{color:#64748b;font-size:.72rem}';document.head.appendChild(st);}
    }
  }

  var baseUpdate=window.updateRow;
  window.updateRow=function(row){
    if(typeof baseUpdate==='function')baseUpdate(row);
    var d=data(row),meta=row&&row.querySelector('.att-person-meta');
    if(!d||!meta)return;
    var absent=!['present','late'].includes(d.status.value);
    meta.textContent=absent?(label(d.excuse.value)+(d.reason.value?' · '+d.reason.value:'')):'';
  };

  document.querySelectorAll('.att-person').forEach(function(row){
    var d=data(row);if(!d)return;
    var reason=String(d.reason.value||'');
    var legacy=row.dataset.mealAfterRegistration==='1'||reason.indexOf(PREFIX)===0;
    if(reason.indexOf(PREFIX)===0)d.reason.value=reason.slice(PREFIX.length).trim();
    if(legacy){d.excuse.value=LATE;d.status.value='excused';}
    if(d.excuse.value===LATE)d.status.value='excused';
    row.dataset.mealAfterRegistration='0';
    window.updateRow(row);
    row.addEventListener('click',function(){setTimeout(function(){var x=data(row);if(!x)return;var code=x.excuse.value||P;if(!labels[code])code=P;var radio=document.querySelector('input[name="absenceType"][value="'+code+'"]');if(radio)radio.checked=true;},0);});
  });

  window.saveAbsence=function(){
    var row=window.activeRow;if(!row)return;var d=data(row);if(!d)return;
    var radio=document.querySelector('input[name="absenceType"]:checked');var code=radio?radio.value:P;if(!labels[code])code=P;
    d.excuse.value=code;d.status.value=code===KP?'absent':'excused';d.reason.value=(document.getElementById('absenceReason')||{}).value?document.getElementById('absenceReason').value.trim():'';row.dataset.mealAfterRegistration='0';
    window.updateRow(row);if(typeof window.closeDialog==='function')window.closeDialog('absenceDialog');
  };
  window.markPresentFromDialog=function(){var row=window.activeRow;if(!row)return;var d=data(row);if(!d)return;d.status.value='present';d.excuse.value='';d.reason.value='';row.dataset.mealAfterRegistration='0';window.updateRow(row);if(typeof window.closeDialog==='function')window.closeDialog('absenceDialog');};
  window.setAll=function(status){document.querySelectorAll('.att-person').forEach(function(row){var d=data(row);if(!d)return;if(status==='absent'){d.status.value='excused';d.excuse.value=P;}else{d.status.value='present';d.excuse.value='';}d.reason.value='';row.dataset.mealAfterRegistration='0';window.updateRow(row);});};

  var oldConfirm=window.openConfirm;
  window.openConfirm=function(){
    if(typeof oldConfirm==='function')oldConfirm();
    var box=document.getElementById('confirmList');if(!box)return;
    var groups={};document.querySelectorAll('.att-person.absent').forEach(function(row){var c=row.dataset.class||'(Chưa lớp)';(groups[c]||(groups[c]=[])).push(row);});
    var classes=Object.keys(groups).sort(function(a,b){return a.localeCompare(b,'vi',{numeric:true});});
    box.innerHTML=classes.map(function(c){return '<section class="att-confirm-class"><header><span>'+esc(c)+'</span><span>'+groups[c].length+' vắng</span></header>'+groups[c].map(function(r,i){var d=data(r);return '<div>'+(i+1)+'. '+esc(r.dataset.name)+' <strong>'+esc(label(d.excuse.value))+'</strong>'+(d.reason.value?' – '+esc(d.reason.value):'')+'</div>';}).join('')+'</section>';}).join('')||'<div class="text-center text-success py-3">Tất cả học sinh có mặt.</div>';
  };

  var chips=[].slice.call(document.querySelectorAll('.att-class-chips a'));
  var search=document.getElementById('studentSearch');
  var activeClass='';
  function applyFilter(){var q=(search&&search.value||'').toLocaleLowerCase('vi');document.querySelectorAll('.att-person').forEach(function(row){var classOk=!activeClass||row.dataset.class===activeClass;var searchOk=!q||((row.dataset.name+' '+row.dataset.class).toLocaleLowerCase('vi').includes(q));row.hidden=!(classOk&&searchOk);});}
  if(chips.length&&document.querySelectorAll('.att-person').length){
    chips.forEach(function(a){a.addEventListener('click',function(e){var u=new URL(a.href,location.href);var picked=u.searchParams.get('class')||'';e.preventDefault();activeClass=picked;chips.forEach(function(x){x.classList.toggle('active',x===a);});var hidden=document.querySelector('input[name="class"]#classFilter, #attendanceForm input[name="class"]');if(hidden)hidden.value='';applyFilter();});});
    if(search)search.addEventListener('input',applyFilter);
    applyFilter();
  }
})();
