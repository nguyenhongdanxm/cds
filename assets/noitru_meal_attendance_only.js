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
    var n=document.createElement('div');n.id='ntMealConnectionNotice';n.className='alert alert-'+(type||'info')+' py-2 px-3 mb-3';n.innerHTML=html;anchor.insertAdjacentElement('afterend',n);
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
    notice('<i class="bi bi-link-45deg"></i> <strong>Đã kết nối Báo ăn:</strong> '+applied+' học sinh đã báo vắng trước khi chốt được tự chọn <strong>Có phép</strong>. Phát sinh sau chốt: chọn <strong>Có phép sau thời gian đăng ký bữa ăn</strong>; vắng không phép: chọn <strong>Không phép</strong>.','success');
  }else{
    notice('<i class="bi bi-check-circle"></i> Đã đối chiếu '+(res.meal_label||label)+' ngày '+dateInput.value.split('-').reverse().join('/')+'. <strong>Không có học sinh báo vắng trước khi chốt</strong>.','info');
  }
})();