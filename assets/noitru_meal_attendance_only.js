(function(){
  var form=document.getElementById('attendanceForm');if(!form||typeof window.rowData!=='function')return;
  var shift=form.querySelector('input[name="shift"]'),dateInput=form.querySelector('input[name="date"]');if(!shift||!dateInput)return;
  var opt=document.querySelector('.att-shift-select option:checked');
  var label=(opt?opt.textContent:'').trim(),text=(shift.value+' '+label).toLocaleLowerCase('vi');
  var isMeal=/(ăn|an)[ _-]*sáng|(an|meal)[_-]*sang|(ăn|an)[ _-]*trưa|(an|meal)[_-]*trua|(ăn|an)[ _-]*tối|(an|meal)[_-]*toi/.test(text);
  window.NT_ATT_IS_MEAL=isMeal;

  function notice(html,type){
    var old=document.getElementById('ntMealConnectionNotice');if(old)old.remove();
    var anchor=document.querySelector('.att-summary');if(!anchor)return;
    var n=document.createElement('div');n.id='ntMealConnectionNotice';n.className='alert alert-'+(type||'info')+' py-2 px-3 mb-3';n.innerHTML=html;anchor.insertAdjacentElement('afterend',n);
  }
  function refresh(){if(typeof window.updateReasonStats==='function')window.updateReasonStats();}

  var lateRadio=document.querySelector('input[name="absenceType"][value="P_SAU_AN"]');
  if(lateRadio){var lateLabel=lateRadio.closest('label');if(lateLabel)lateLabel.style.display=isMeal?'':'none';}
  var lateStat=document.querySelector('[data-count="P_SAU_AN"]');if(lateStat){var box=lateStat.closest('.nt-absence-reason-stat');if(box)box.style.display=isMeal?'':'none';}

  if(!isMeal){
    document.querySelectorAll('.att-person').forEach(function(row){var d=window.rowData(row);if(d&&d.excuse.value==='P_SAU_AN'){d.excuse.value='P';d.status.value='excused';if(typeof window.updateRow==='function')window.updateRow(row);}});
    return;
  }

  notice('<i class="bi bi-arrow-repeat"></i> Đang đối chiếu <strong>Báo ăn '+dateInput.value.split('-').reverse().join('/')+'</strong> với '+label+'…','light');
  var url=new URL('noitru_att_meal_prefill.php',location.href);url.searchParams.set('date',dateInput.value);url.searchParams.set('shift',shift.value);
  fetch(url.toString(),{credentials:'same-origin',headers:{Accept:'application/json'}})
    .then(function(r){return r.ok?r.json():Promise.reject(new Error('HTTP '+r.status));})
    .then(function(res){
      if(!res||!res.ok){notice('<i class="bi bi-exclamation-triangle"></i> Không đọc được dữ liệu Báo ăn.','warning');return;}
      if(!res.applied){
        if(res.reason==='attendance_saved'){notice('<i class="bi bi-shield-check"></i> Báo cáo điểm danh này <strong>đã lưu trước đó</strong>; hệ thống giữ nguyên dữ liệu đã lưu, không tự ghi đè từ Báo ăn.','secondary');return;}
        if(res.reason==='meal_not_locked'){notice('<i class="bi bi-unlock"></i> '+label+' ngày '+dateInput.value.split('-').reverse().join('/')+' <strong>chưa chốt Báo ăn</strong>, nên chưa tự đánh dấu Có phép. Sau khi chốt, mở lại điểm danh để hệ thống tự nhận.','warning');return;}
        notice('<i class="bi bi-info-circle"></i> Buổi này chưa được nhận diện là điểm danh bữa ăn.','warning');return;
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
      refresh();
      if(applied){notice('<i class="bi bi-link-45deg"></i> <strong>Đã kết nối Báo ăn:</strong> '+applied+' học sinh đã báo vắng trước khi chốt được tự chọn <strong>Có phép</strong>. Học sinh vắng phát sinh sau chốt: chọn <strong>Có phép sau thời gian đăng ký bữa ăn</strong>; vắng không phép: chọn <strong>Không phép</strong>.','success');}
      else{notice('<i class="bi bi-check-circle"></i> Đã đối chiếu '+(res.meal_label||label)+' ngày '+dateInput.value.split('-').reverse().join('/')+'. <strong>Không có học sinh báo vắng trước khi chốt</strong>.','info');}
    }).catch(function(){notice('<i class="bi bi-exclamation-triangle"></i> Không kết nối được dữ liệu Báo ăn. Vui lòng tải lại trang.','danger');});
})();