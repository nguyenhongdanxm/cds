<?php
/** Lưu phân công thủ công bằng endpoint độc lập, tránh xung đột form trang Chuyên môn. */
if (!isset($current) || !in_array($current, ['them', 'danhsach'], true)) return;
?>
<script>
(function(){
  var busy=false;
  function findManualForm(){
    return document.querySelector('#cdsManualAssignments form input[name="cds_manual_action"][value="save"]')?.closest('form') || null;
  }
  async function saveManual(form){
    if(busy||!form)return;
    var teacher=form.querySelector('[name="manual_teacher"]');
    var subject=form.querySelector('[name="manual_subject"]');
    var className=form.querySelector('[name="manual_class"]');
    var periods=form.querySelector('[name="manual_periods"]');
    if(!teacher||!subject||!className||!periods)return;
    busy=true;
    var button=form.querySelector('button[type="submit"],button');
    if(button)button.disabled=true;
    try{
      var body=new URLSearchParams();
      body.set('manual_teacher',teacher.value||'');
      body.set('manual_subject',subject.value||'');
      body.set('manual_class',className.value||'');
      body.set('manual_periods',periods.value||'');
      var note=form.querySelector('[name="manual_note"]');
      body.set('manual_note',note?note.value:'');
      var response=await fetch('/chuyenmon/cds_manual_save.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin',
        body:body.toString()
      });
      var data=null;
      try{data=await response.json()}catch(e){}
      if(!response.ok||!data||!data.ok)throw new Error((data&&data.message)||('Không thể lưu, mã lỗi '+response.status));
      window.location.reload();
    }catch(error){
      window.alert(error&&error.message?error.message:'Không thể thêm phân công thủ công.');
      busy=false;if(button)button.disabled=false;
    }
  }
  document.addEventListener('submit',function(e){
    var form=e.target;
    if(!(form instanceof HTMLFormElement))return;
    var action=form.querySelector('input[name="cds_manual_action"][value="save"]');
    if(!action||!form.closest('#cdsManualAssignments'))return;
    e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();
    saveManual(form);
  },true);
  document.addEventListener('click',function(e){
    var button=e.target.closest('#cdsManualAssignments button[type="submit"]');
    if(!button)return;
    var form=findManualForm();
    if(!form)return;
    var action=form.querySelector('input[name="cds_manual_action"]');
    if(!action||action.value!=='save')return;
    e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();
    saveManual(form);
  },true);
})();
</script>
