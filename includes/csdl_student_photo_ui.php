<?php
/** Gắn giao diện ảnh thẻ vào form CSDL và hồ sơ Nội trú. */
if (defined('CSDL_STUDENT_PHOTO_UI')) return;
define('CSDL_STUDENT_PHOTO_UI', true);

function cds_student_photo_ui_filter(string $html): string {
    if (stripos($html, '</body>') === false) return $html;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/(csdl|noitru_list)\.php$~', $path)) return $html;
    $script = <<<'HTML'
<style id="cdsStudentPhotoStyle">
.cds-photo-editor{display:flex;gap:1rem;align-items:center;padding:.8rem;border:1px solid #dbe5ee;border-radius:12px;background:#f8fafc}.cds-photo-preview{width:90px;height:120px;border-radius:9px;object-fit:cover;background:#e9eef4;border:1px solid #cbd5e1}.nt-profile-avatar{overflow:hidden}.nt-profile-avatar img{width:100%;height:100%;object-fit:cover;display:block}.nt-profile-avatar.has-photo i{display:none}
</style>
<script id="cdsStudentPhotoUiScript">
(function(){
  function setupCsdl(){
    if(!/\/csdl\.php$/.test(location.pathname)||!new URLSearchParams(location.search).get('tab')?.includes('students'))return;
    var form=document.querySelector('#modalStudent form');if(!form||form.querySelector('[name="student_photo"]'))return;
    form.enctype='multipart/form-data';
    var body=form.querySelector('.modal-body .row');if(!body)return;
    var box=document.createElement('div');box.className='col-12';
    box.innerHTML='<div class="cds-photo-editor"><img class="cds-photo-preview" alt="Ảnh thẻ học sinh"><div class="flex-grow-1"><label class="form-label small fw-bold">Ảnh thẻ học sinh</label><input type="file" name="student_photo" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp"><div class="small text-muted mt-1">Ảnh được tự xoay, cắt giữa theo tỷ lệ 3:4 và chuẩn hóa 600 × 800 px. Tối đa 10 MB.</div><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_student_photo" value="1"><span class="form-check-label small">Xóa ảnh hiện tại</span></label></div></div>';
    body.insertBefore(box,body.firstChild);
    var img=box.querySelector('img'),input=box.querySelector('input[type="file"]'),id=form.querySelector('[name="id"]');
    function loadCurrent(){var sid=(id?.value||'').trim();img.src=sid?((window.CSDL_BASE||'/')+'student_photo.php?id='+encodeURIComponent(sid)+'&v='+Date.now()):'';img.style.visibility=sid?'visible':'hidden'}
    img.addEventListener('error',function(){img.removeAttribute('src');img.style.visibility='hidden'});
    input.addEventListener('change',function(){var f=this.files&&this.files[0];if(!f)return;img.src=URL.createObjectURL(f);img.style.visibility='visible'});
    document.getElementById('modalStudent')?.addEventListener('show.bs.modal',function(){setTimeout(loadCurrent,0)});
    var oldReset=window.resetStudentForm;window.resetStudentForm=function(){if(oldReset)oldReset();input.value='';box.querySelector('[name="remove_student_photo"]').checked=false;setTimeout(loadCurrent,0)};
    loadCurrent();
  }
  function setupNoitru(){
    if(!/\/noitru_list\.php$/.test(location.pathname))return;
    var modal=document.getElementById('ntStudentDetailModal');if(!modal)return;
    var avatar=modal.querySelector('.nt-profile-avatar');if(!avatar)return;
    var img=document.createElement('img');img.alt='Ảnh học sinh';img.hidden=true;avatar.prepend(img);
    img.addEventListener('error',function(){this.hidden=true;avatar.classList.remove('has-photo')});
    modal.addEventListener('show.bs.modal',function(event){
      var student={};try{student=JSON.parse(event.relatedTarget?.dataset.student||'{}')}catch(e){}
      var id=String(student.id||'').trim();
      if(!id){img.hidden=true;avatar.classList.remove('has-photo');return}
      img.hidden=false;avatar.classList.add('has-photo');img.src=(window.CSDL_BASE||'/')+'student_photo.php?id='+encodeURIComponent(id)+'&v='+Date.now();
    });
  }
  function run(){setupCsdl();setupNoitru()}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
})();
</script>
HTML;
    return preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
}
ob_start('cds_student_photo_ui_filter');
