(function(){
  var form=document.getElementById('attendanceForm');if(!form)return;
  var shift=form.querySelector('input[name="shift"]');if(!shift)return;
  var opt=document.querySelector('.att-shift-select option:checked');
  var text=(shift.value+' '+(opt?opt.textContent:'')).toLocaleLowerCase('vi');
  var meal=/(ăn|an)[ _-]*sáng|(an|meal)[_-]*sang|(ăn|an)[ _-]*trưa|(an|meal)[_-]*trua|(ăn|an)[ _-]*tối|(an|meal)[_-]*toi/.test(text);
  window.NT_ATT_IS_MEAL=meal;
  var radio=document.querySelector('input[name="absenceType"][value="P_SAU_AN"]');
  if(radio){var label=radio.closest('label');if(label)label.style.display=meal?'':'none';}
  if(!meal&&typeof window.rowData==='function')document.querySelectorAll('.att-person').forEach(function(row){var d=window.rowData(row);if(d&&d.excuse.value==='P_SAU_AN'){d.excuse.value='P';d.status.value='excused';if(typeof window.updateRow==='function')window.updateRow(row);}});
})();