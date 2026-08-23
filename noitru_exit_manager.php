<?php
/*
 * Lớp giao diện nâng cao cho Ra/vào KTX.
 * Giữ nguyên nghiệp vụ trong noitru_exit.php, bổ sung upload Drive có tiến trình
 * và giữ người dùng ở URL manager để trải nghiệm nhất quán.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_perm('nt.ravao');

if (!isset($_GET['view'])) $_GET['view'] = 'register';

ob_start();
require __DIR__ . '/noitru_exit.php';
$html = ob_get_clean();

$enhancement = <<<'HTML'
<style>
.ntx-upload-overlay{position:fixed;inset:0;background:rgba(15,23,42,.66);z-index:99999;display:none;align-items:center;justify-content:center;padding:1rem}.ntx-upload-overlay.show{display:flex}.ntx-upload-box{width:min(560px,100%);background:#fff;border-radius:20px;padding:1.35rem;box-shadow:0 30px 90px rgba(0,0,0,.3)}.ntx-upload-icon{width:54px;height:54px;border-radius:16px;display:grid;place-items:center;background:#e8f2ff;color:#0d6efd;font-size:1.6rem}.ntx-upload-bar{height:16px}.ntx-upload-status{font-size:.92rem;color:#64748b}.ntx-drive-card{border:1px solid #dce7f1;border-radius:14px;background:#f8fbff;padding:1rem;margin-bottom:1rem}.ntx-drive-ready{color:#15803d}.ntx-drive-bad{color:#b45309}.ntx-toast{position:fixed;right:1rem;bottom:1rem;z-index:100000;max-width:min(460px,calc(100vw - 2rem));background:#fff;border:1px solid #dbe5ef;border-radius:14px;padding:.85rem 1rem;box-shadow:0 18px 50px rgba(15,23,42,.22);display:none}.ntx-toast.show{display:block}.ntx-toast.ok{border-left:5px solid #16a34a}.ntx-toast.bad{border-left:5px solid #dc2626}
</style>
<div class="ntx-upload-overlay" id="ntxUploadOverlay" aria-live="polite">
  <div class="ntx-upload-box">
    <div class="d-flex gap-3 align-items-center mb-3">
      <div class="ntx-upload-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
      <div class="flex-grow-1"><h5 class="mb-1" id="ntxUploadTitle">Đang tải đơn lên Google Drive</h5><div class="ntx-upload-status" id="ntxUploadStatus">Đang chuẩn bị file…</div></div>
    </div>
    <div class="progress ntx-upload-bar"><div id="ntxUploadBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:3%">3%</div></div>
    <div class="small text-muted mt-2">Thư mục: <strong>Đơn xin ra vào KTX</strong></div>
  </div>
</div>
<div class="ntx-toast" id="ntxToast"></div>
<script>
(function(){
const overlay=document.getElementById('ntxUploadOverlay'),bar=document.getElementById('ntxUploadBar'),status=document.getElementById('ntxUploadStatus'),title=document.getElementById('ntxUploadTitle'),toast=document.getElementById('ntxToast');
const API='noitru_exit_drive_api.php';
function showToast(message,ok=true){toast.className='ntx-toast show '+(ok?'ok':'bad');toast.innerHTML=(ok?'<i class="bi bi-check-circle-fill text-success me-2"></i>':'<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>')+message;setTimeout(()=>toast.classList.remove('show'),6000)}
const storedFlash=sessionStorage.getItem('ntxExitFlash');if(storedFlash){sessionStorage.removeItem('ntxExitFlash');setTimeout(()=>showToast(storedFlash,true),180)}
function progress(p,text){p=Math.max(3,Math.min(100,Math.round(p)));bar.style.width=p+'%';bar.textContent=p+'%';if(text)status.textContent=text}
function showProgress(){bar.className='progress-bar progress-bar-striped progress-bar-animated';title.textContent='Đang tải đơn lên Google Drive';progress(3,'Đang chuẩn bị file…');overlay.classList.add('show')}
function failProgress(message){bar.classList.remove('progress-bar-animated');bar.classList.add('bg-danger');title.textContent='Tải đơn không thành công';status.textContent=message;setTimeout(()=>overlay.classList.remove('show'),2600);showToast(message,false)}
function successProgress(message){bar.classList.remove('progress-bar-animated');bar.classList.add('bg-success');title.textContent='Đã tải đơn thành công';progress(100,message||'File đã nằm trong Google Drive.');}
function managerUrlFrom(responseUrl, fallbackView){try{const u=new URL(responseUrl,location.href);const q=u.searchParams.toString();return 'noitru_exit_manager.php'+(q?'?'+q:'?view='+(fallbackView||'register'));}catch(_){return 'noitru_exit_manager.php?view='+(fallbackView||'register')}}
function postBusinessForm(form, fallbackView, afterUpload, successMessage=''){const xhr=new XMLHttpRequest(),fd=new FormData(form);xhr.open('POST',location.href,true);xhr.onload=()=>{if(afterUpload){progress(96,'Đã tải file lên Drive. Đang lưu đăng ký…')}if(successMessage)sessionStorage.setItem('ntxExitFlash',successMessage);const target=managerUrlFrom(xhr.responseURL,fallbackView);setTimeout(()=>{location.href=target},afterUpload?700:0)};xhr.onerror=()=>{if(afterUpload)failProgress('Mất kết nối khi lưu đăng ký. File đã có thể được tải lên Drive; hãy thử lưu lại phiếu.');else showToast('Không kết nối được máy chủ.',false)};xhr.send(fd)}

/* Form đăng ký: upload file riêng để có % thật, chỉ lưu phiếu sau khi Drive trả File ID. */
const saveInput=document.querySelector('form input[name="action"][value="save_request"]');
if(saveInput){const form=saveInput.closest('form'),file=form.querySelector('input[type="file"][name="attachment"]'),old=form.querySelector('input[name="old_attachment"]'),isEdit=!!form.querySelector('input[name="id"]')?.value;
 form.addEventListener('submit',function(ev){
   if(form.dataset.ntxSubmitting==='1')return;
   if(!form.reportValidity()){ev.preventDefault();return;}
   const doneMessage=isEdit?'Đã cập nhật đơn xin thành công.':'Đăng ký thành công – đang chờ duyệt.';
   if(!file||!file.files||!file.files.length){ev.preventDefault();postBusinessForm(form,'register',false,doneMessage);return;}
   ev.preventDefault();showProgress();
   const selected=file.files[0],fd=new FormData();fd.append('action','upload');fd.append('attachment',selected);
   const xhr=new XMLHttpRequest();xhr.open('POST',API,true);
   xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=5+(e.loaded/e.total)*77;progress(p,'Đang gửi '+selected.name+' lên máy chủ…')}};
   xhr.upload.onload=()=>progress(84,'Máy chủ đã nhận file. Đang chuyển vào Google Drive…');
   xhr.onload=()=>{let d=null;try{d=JSON.parse(xhr.responseText)}catch(_){d=null}if(xhr.status<200||xhr.status>=300||!d||!d.ok){failProgress((d&&d.message)||('Upload lỗi HTTP '+xhr.status));return;}successProgress('Đã tải 100%: '+(d.name||selected.name));if(old)old.value=d.path||'';file.value='';form.dataset.ntxSubmitting='1';setTimeout(()=>postBusinessForm(form,'register',true,doneMessage),500)};
   xhr.onerror=()=>failProgress('Không kết nối được máy chủ khi tải file.');xhr.send(fd);
 });
}

/* Giữ các thao tác POST khác ở màn hình manager thay vì rơi về trang lõi. */
document.querySelectorAll('form').forEach(form=>{if(form===saveInput?.closest('form'))return;form.addEventListener('submit',function(ev){if(form.dataset.ntxNative==='1')return;const action=form.querySelector('[name="action"]')?.value||'';if(!action)return;if(!form.reportValidity()){ev.preventDefault();return;}ev.preventDefault();postBusinessForm(form,new URLSearchParams(location.search).get('view')||'history',false,'')})});

/* Cài đặt: hiển thị trạng thái và chủ động tạo/kiểm tra thư mục Drive. */
if(new URLSearchParams(location.search).get('view')==='settings'){
 const section=document.querySelector('main section.card');
 if(section){const card=document.createElement('div');card.className='ntx-drive-card';card.innerHTML='<div class="d-flex flex-wrap align-items-center gap-2"><div class="flex-grow-1"><strong><i class="bi bi-google me-1"></i> Google Drive – Đơn xin ra vào KTX</strong><div id="ntxDriveState" class="small text-muted mt-1">Đang kiểm tra kết nối và thư mục…</div></div><button type="button" id="ntxPrepareDrive" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder-plus"></i> Kiểm tra / Tạo thư mục</button><a id="ntxOpenFolder" class="btn btn-outline-success btn-sm d-none" target="_blank" rel="noopener"><i class="bi bi-folder2-open"></i> Mở thư mục Drive</a></div>';section.parentNode.insertBefore(card,section);const state=card.querySelector('#ntxDriveState'),prep=card.querySelector('#ntxPrepareDrive'),open=card.querySelector('#ntxOpenFolder');
   function render(d){if(d.ready){state.className='small mt-1 ntx-drive-ready';state.innerHTML='<i class="bi bi-check-circle-fill"></i> Đã sẵn sàng · Folder ID: <code>'+d.folder_id+'</code>';open.href=d.folder_url;open.classList.remove('d-none')}else{state.className='small mt-1 ntx-drive-bad';state.innerHTML='<i class="bi bi-exclamation-triangle-fill"></i> '+(d.message||'Chưa có thư mục Drive.');open.classList.add('d-none')}}
   fetch(API+'?action=status',{cache:'no-store'}).then(r=>r.json()).then(render).catch(()=>render({ready:false,message:'Không kiểm tra được Google Drive.'}));
   prep.addEventListener('click',()=>{prep.disabled=true;state.textContent='Đang tạo/kiểm tra thư mục trên Google Drive…';const fd=new FormData();fd.append('action','prepare');fetch(API,{method:'POST',body:fd}).then(async r=>{const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'Không tạo được thư mục.');render(d);showToast(d.message||'Drive đã sẵn sàng.',true)}).catch(e=>{render({ready:false,message:e.message});showToast(e.message,false)}).finally(()=>prep.disabled=false)});
 }
}
})();
</script>
HTML;

if (stripos($html, '</body>') !== false) {
    $html = preg_replace('~</body>~i', $enhancement . '</body>', $html, 1);
} else {
    $html .= $enhancement;
}
echo $html;
