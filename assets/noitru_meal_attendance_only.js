(function(){
  var form=document.getElementById('attendanceForm');if(!form||typeof window.rowData!=='function')return;
  var shift=form.querySelector('input[name="shift"]'),dateInput=form.querySelector('input[name="date"]');if(!shift||!dateInput)return;
  var res=window.NT_ATT_MEAL_PREFILL_DATA||{};
  var opt=document.querySelector('.att-shift-select option:checked');
  var label=(opt?opt.textContent:'').trim();
  var isMeal=!!res.meal;
  window.NT_ATT_IS_MEAL=isMeal;

  function notice(html,type){
    var old=document.getElementById('ntMealConnectionNotice');if(old)old.remove();
    var anchor=document.querySelector('.att-summary');if(!anchor)return;
    var n=document.createElement('div');n.id='ntMealConnectionNotice';n.className='alert alert-'+(type||'info')+' nt-meal-notice py-2 px-3 mb-3';n.innerHTML=html;anchor.insertAdjacentElement('afterend',n);if(!document.getElementById('ntMealNoticeStyle')){var st=document.createElement('style');st.id='ntMealNoticeStyle';st.textContent='.nt-meal-notice{font-size:.9rem;line-height:1.35}.nt-meal-notice strong{font-weight:750}@media(max-width:767px){.nt-meal-notice{font-size:.76rem;line-height:1.25;padding:.45rem .6rem!important;margin-bottom:.65rem!important}.nt-meal-notice .bi{font-size:.85rem}}';document.head.appendChild(st);}
  }

  var lateRadio=document.querySelector('input[name="absenceType"][value="P_SAU_AN"]');
  if(lateRadio){var lateLabel=lateRadio.closest('label');if(lateLabel)lateLabel.style.display=isMeal?'':'none';}
  var lateStat=document.querySelector('[data-count="P_SAU_AN"]');if(lateStat){var box=lateStat.closest('.nt-absence-reason-stat');if(box)box.style.display=isMeal?'':'none';}

  if(!isMeal){
    document.querySelectorAll('.att-person').forEach(function(row){var d=window.rowData(row);if(d&&d.excuse.value==='P_SAU_AN'){d.excuse.value='P';d.status.value='excused';if(typeof window.updateRow==='function')window.updateRow(row);}});
    return;
  }

  if(!res.ok){notice('<i class="bi bi-exclamation-triangle"></i> Không đọc được dữ liệu Báo ăn trên máy chủ.','danger');return;}
  if(!res.applied){
    if(res.reason==='attendance_saved'){notice('<i class="bi bi-shield-check"></i> Báo cáo điểm danh này <strong>đã lưu trước đó</strong>; hệ thống giữ nguyên dữ liệu đã lưu, không tự ghi đè từ Báo ăn.','secondary');return;}
    if(res.reason==='meal_not_locked'){notice('<i class="bi bi-unlock"></i> '+(res.meal_label||label)+' ngày '+dateInput.value.split('-').reverse().join('/')+' <strong>chưa chốt Báo ăn</strong>, nên chưa tự đánh dấu Có phép. Sau khi chốt, mở lại điểm danh để hệ thống tự nhận.','warning');return;}
    notice('<i class="bi bi-info-circle"></i> Chưa có dữ liệu Báo ăn để tự đối chiếu.','warning');return;
  }

  var wanted={};(res.student_ids||[]).forEach(function(id){wanted[String(id)]=true;});var applied=0;
  document.querySelectorAll('.att-person').forEach(function(row){
    var sid=row.querySelector('input[name="sid[]"]');if(!sid||!wanted[String(sid.value)])return;
    var d=window.rowData(row);if(!d||!['present','late'].includes(d.status.value))return;
    d.status.value='excused';d.excuse.value='P';d.reason.value='';row.dataset.mealPrefill='1';
    if(typeof window.updateRow==='function')window.updateRow(row);
    var meta=row.querySelector('.att-person-meta');if(meta)meta.textContent='Có phép · Đã báo vắng trước khi chốt '+(res.meal_label||'bữa ăn');
    row.classList.add('nt-meal-prefilled');applied++;
  });

  if(applied){
    notice('<i class="bi bi-link-45deg"></i> <strong>Báo ăn:</strong> '+applied+' HS đã báo vắng trước chốt → <strong>Có phép</strong>. Sau chốt → <strong>Sau chốt</strong>; không phép → <strong>K. phép</strong>.','success');
  }else{
    notice('<i class="bi bi-check-circle"></i> Đã đối chiếu '+(res.meal_label||label)+'. <strong>Không có HS báo vắng trước chốt</strong>.','info');
  }
})();