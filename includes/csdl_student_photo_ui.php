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
.cds-photo-editor{display:flex;gap:1rem;align-items:center;padding:.9rem;border:1px solid #dbe5ee;border-radius:12px;background:#f8fafc}
.cds-photo-preview{width:120px;height:160px;flex:0 0 120px;border-radius:10px;object-fit:cover;background:#e9eef4;border:1px solid #cbd5e1;box-shadow:0 3px 12px rgba(15,23,42,.12);cursor:zoom-in}
.nt-profile-avatar{width:92px!important;height:122px!important;min-width:92px!important;border-radius:11px!important;overflow:hidden;cursor:default}
.nt-profile-avatar img{width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in}
.nt-profile-avatar.has-photo i{display:none}
.cds-photo-zoom{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(2,6,23,.88);backdrop-filter:blur(4px)}
.cds-photo-zoom.show{display:flex}
.cds-photo-zoom-dialog{position:relative;max-width:min(92vw,720px);max-height:92vh;display:flex;align-items:center;justify-content:center}
.cds-photo-zoom-image{display:block;max-width:100%;max-height:90vh;width:auto;height:auto;object-fit:contain;border-radius:14px;background:#fff;box-shadow:0 20px 70px rgba(0,0,0,.48)}
.cds-photo-zoom-close{position:absolute;top:-14px;right:-14px;width:42px;height:42px;border:0;border-radius:50%;background:#fff;color:#0f172a;font-size:1.55rem;line-height:1;box-shadow:0 5px 20px rgba(0,0,0,.28);display:grid;place-items:center;cursor:pointer}
.cds-photo-zoom-hint{position:absolute;left:50%;bottom:-34px;transform:translateX(-50%);color:#fff;font-size:.8rem;white-space:nowrap}
@media(max-width:575.98px){.cds-photo-editor{align-items:flex-start}.cds-photo-preview{width:105px;height:140px;flex-basis:105px}.nt-profile-avatar{width:78px!important;height:104px!important;min-width:78px!important}.cds-photo-zoom{padding:14px}.cds-photo-zoom-close{top:8px;right:8px}}
</style>
<div class="cds-photo-zoom" id="cdsStudentPhotoZoom" role="dialog" aria-modal="true" aria-label="Xem ảnh học sinh phóng to" aria-hidden="true">
  <div class="cds-photo-zoom-dialog">
    <img class="cds-photo-zoom-image" alt="Ảnh học sinh phóng to">
    <button type="button" class="cds-photo-zoom-close" aria-label="Đóng ảnh">×</button>
    <div class="cds-photo-zoom-hint">Bấm ra ngoài hoặc nhấn Esc để đóng</div>
  </div>
</div>
<script id="cdsStudentPhotoUiScript">
(function(){
  var zoom=document.getElementById('cdsStudentPhotoZoom');
  var zoomImg=zoom&&zoom.querySelector('.cds-photo-zoom-image');
  var lastFocus=null;
  function openZoom(src,alt,trigger){
    if(!zoom||!zoomImg||!src)return;
    lastFocus=trigger||document.activeElement;
    zoomImg.src=src;zoomImg.alt=alt||'Ảnh học sinh phóng to';
    zoom.classList.add('show');zoom.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
    zoom.querySelector('.cds-photo-zoom-close')?.focus();
  }
  function closeZoom(){
    if(!zoom)return;
    zoom.classList.remove('show');zoom.setAttribute('aria-hidden','true');
    zoomImg?.removeAttribute('src');document.body.style.overflow='';
    if(lastFocus&&typeof lastFocus.focus==='function')lastFocus.focus();
  }
  zoom?.querySelector('.cds-photo-zoom-close')?.addEventListener('click',closeZoom);
  zoom?.addEventListener('click',function(e){if(e.target===zoom)closeZoom()});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&zoom?.classList.contains('show'))closeZoom()});
  function makeZoomable(img){
    if(!img||img.dataset.cdsZoomReady)return;
    img.dataset.cdsZoomReady='1';img.tabIndex=0;img.setAttribute('role','button');img.setAttribute('aria-label','Bấm để xem ảnh phóng to');
    function open(){if(img.src&&!img.hidden&&img.style.visibility!=='hidden')openZoom(img.src,img.alt,img)}
    img.addEventListener('click',open);img.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();open()}});
  }
  function setupCsdl(){
    if(!/\/csdl\.php$/.test(location.pathname)||!new URLSearchParams(location.search).get('tab')?.includes('students'))return;
    var form=document.querySelector('#modalStudent form');if(!form||form.querySelector('[name="student_photo"]'))return;
    form.enctype='multipart/form-data';
    var body=form.querySelector('.modal-body .row');if(!body)return;
    var box=document.createElement('div');box.className='col-12';
    box.innerHTML='<div class="cds-photo-editor"><img class="cds-photo-preview" alt="Ảnh thẻ học sinh"><div class="flex-grow-1"><label class="form-label small fw-bold">Ảnh thẻ học sinh</label><input type="file" name="student_photo" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp"><div class="small text-muted mt-1">Ảnh được tự xoay, cắt giữa theo tỷ lệ 3:4 và chuẩn hóa 600 × 800 px. Bấm vào ảnh để xem phóng to. Tối đa 10 MB.</div><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_student_photo" value="1"><span class="form-check-label small">Xóa ảnh hiện tại</span></label></div></div>';
    body.insertBefore(box,body.firstChild);
    var img=box.querySelector('img'),input=box.querySelector('input[type="file"]'),id=form.querySelector('[name="id"]');makeZoomable(img);
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
    var img=document.createElement('img');img.alt='Ảnh thẻ học sinh';img.hidden=true;avatar.prepend(img);makeZoomable(img);
    img.addEventListener('error',function(){this.hidden=true;avatar.classList.remove('has-photo');avatar.style.cursor='default'});
    modal.addEventListener('show.bs.modal',function(event){
      var student={};try{student=JSON.parse(event.relatedTarget?.dataset.student||'{}')}catch(e){}
      var id=String(student.id||'').trim();
      if(!id){img.hidden=true;avatar.classList.remove('has-photo');avatar.style.cursor='default';return}
      img.hidden=false;avatar.classList.add('has-photo');avatar.style.cursor='zoom-in';img.src=(window.CSDL_BASE||'/')+'student_photo.php?id='+encodeURIComponent(id)+'&v='+Date.now();
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
