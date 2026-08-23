(function(){
  'use strict';
  const state={registration:null,deferredInstall:null,status:null,currentSubscription:null,guidePlatform:null};
  const $=selector=>document.querySelector(selector);
  const api=async(payload,method='POST')=>{
    const response=await fetch('/push_api.php'+(method==='GET'?'?action=status':''),{method,credentials:'same-origin',headers:{'Content-Type':'application/json'},body:method==='GET'?undefined:JSON.stringify(payload)});
    const data=await response.json();if(!response.ok||data.ok===false)throw new Error(data.message||'Không thực hiện được.');return data;
  };
  const b64=value=>{const padding='='.repeat((4-value.length%4)%4);const raw=atob((value+padding).replace(/-/g,'+').replace(/_/g,'/'));return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)))};
  const installed=()=>matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
  const isiOS=()=>/iphone|ipad|ipod/i.test(navigator.userAgent);
  function showGuide(platform){const panel=$('#pushSetup');if(!panel)return;state.guidePlatform=platform;panel.querySelectorAll('[data-guide-platform]').forEach(button=>{const active=button.dataset.guidePlatform===platform;button.classList.toggle('active',active);button.setAttribute('aria-selected',active?'true':'false')});panel.querySelectorAll('[data-guide-panel]').forEach(guide=>guide.hidden=guide.dataset.guidePanel!==platform)}
  function toast(message,error){let el=$('#pushToast');if(!el){el=document.createElement('div');el.id='pushToast';el.className='push-toast';el.setAttribute('role','status');el.setAttribute('aria-live','polite');document.body.appendChild(el)}el.textContent=message;el.classList.toggle('error',!!error);el.classList.add('show');clearTimeout(el._hideTimer);el._hideTimer=setTimeout(()=>el.classList.remove('show'),3800)}
  async function injectNoticeMenu(){
    const menu=document.querySelector('.user-picker .user-menu');if(!menu||menu.querySelector('[data-notice-manager]'))return;
    try{const response=await fetch('/notices.php?capability=1',{credentials:'same-origin',cache:'no-store'});const data=await response.json();if(!data.can_manage)return;const link=document.createElement('a');link.href='/notices.php';link.dataset.noticeManager='1';link.innerHTML='<i class="bi bi-megaphone-fill"></i>Quản lý thông báo';const logout=menu.querySelector('.logout');if(logout)menu.insertBefore(link,logout);else menu.appendChild(link)}catch(error){}
  }
  async function refresh(){
    try{state.status=await api({},'GET');state.currentSubscription=state.registration?await state.registration.pushManager.getSubscription():null;const badge=$('[data-push-unread]');if(badge){badge.textContent=state.status.unread||0;badge.hidden=!state.status.unread}renderPanel()}catch(error){}
  }
  function renderPanel(){
    const panel=$('#pushSetup');if(!panel||!state.status)return;
    const permission=typeof Notification!=='undefined'?Notification.permission:'unsupported';
    panel.dataset.permission=permission;panel.querySelector('[data-push-device-count]').textContent=state.status.devices||0;
    const enabled=permission==='granted'&&!!state.currentSubscription;
    panel.querySelector('[data-push-enable]').hidden=enabled;
    panel.querySelector('[data-push-test]').hidden=!enabled;
    panel.querySelector('[data-push-disable]').hidden=!enabled;
    panel.querySelector('[data-push-state]').textContent=enabled?'Đang nhận thông báo trên thiết bị này':permission==='denied'?'Thông báo đang bị chặn trong cài đặt điện thoại':'Chưa bật thông báo trên thiết bị này';
    const install=panel.querySelector('[data-pwa-install]');install.hidden=installed();
    showGuide(state.guidePlatform||(isiOS()?'ios':'android'));
  }
  async function subscribe(){
    if(!('serviceWorker'in navigator)||!('PushManager'in window)||!('Notification'in window))throw new Error('Thiết bị hoặc trình duyệt này chưa hỗ trợ thông báo Web Push.');
    if(isiOS()&&!installed())throw new Error('Trên iPhone, hãy Chia sẻ → Thêm vào Màn hình chính, rồi mở CDS từ biểu tượng vừa tạo.');
    const permission=await Notification.requestPermission();if(permission!=='granted')throw new Error('Bạn chưa cho phép CDS gửi thông báo.');
    const status=state.status||await api({},'GET');if(!status.publicKey)throw new Error('Máy chủ chưa tạo được khóa Web Push.');
    let subscription=await state.registration.pushManager.getSubscription();
    if(!subscription)subscription=await state.registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64(status.publicKey)});
    await api({action:'subscribe',subscription:subscription.toJSON()});await refresh();
    try{await api({action:'test'});toast('Đã bật thông báo và gửi một thông báo thử tới thiết bị này.')}catch(error){toast('Đã bật thông báo, nhưng chưa gửi được thông báo thử.',true)}
  }
  async function unsubscribe(){const sub=await state.registration.pushManager.getSubscription();if(sub){await api({action:'unsubscribe',endpoint:sub.endpoint});await sub.unsubscribe()}await refresh();toast('Đã tắt thông báo trên thiết bị này.')}
  async function install(){if(state.deferredInstall){state.deferredInstall.prompt();const choice=await state.deferredInstall.userChoice;state.deferredInstall=null;renderPanel();toast(choice.outcome==='accepted'?'Đã cài CDS vào màn hình chính.':'Bạn có thể cài CDS sau trong menu trình duyệt.',choice.outcome!=='accepted');return}if(isiOS())toast('Trong Safari, chọn Chia sẻ → Thêm vào Màn hình chính.');else toast('Chọn Cài đặt ứng dụng hoặc Thêm vào màn hình chính trong menu trình duyệt.')}
  window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();state.deferredInstall=event;renderPanel()});
  document.addEventListener('DOMContentLoaded',async()=>{
    injectNoticeMenu();
    if(!('serviceWorker'in navigator))return;
    try{state.registration=await navigator.serviceWorker.register('/sw.js',{scope:'/'});await navigator.serviceWorker.ready;await refresh()}catch(error){console.error(error)}
    $('#pushSetup')?.addEventListener('click',async event=>{const button=event.target.closest('button');if(!button)return;if(button.matches('[data-guide-platform]')){showGuide(button.dataset.guidePlatform);return}button.disabled=true;try{if(button.matches('[data-pwa-install]'))await install();if(button.matches('[data-push-enable]'))await subscribe();if(button.matches('[data-push-test]')){const result=await api({action:'test'});toast(result.message)}if(button.matches('[data-push-disable]'))await unsubscribe()}catch(error){toast(error.message,true)}finally{button.disabled=false}});
    document.querySelectorAll('[data-notification-id]').forEach(link=>link.addEventListener('click',()=>api({action:'mark_read',id:link.dataset.notificationId}).catch(()=>null)));
    $('[data-mark-all-read]')?.addEventListener('click',async()=>{try{await api({action:'mark_read',all:true});document.querySelectorAll('[data-notification-id]').forEach(link=>link.classList.remove('unread'));const badge=$('[data-push-unread]');if(badge)badge.hidden=true;const button=$('[data-mark-all-read]');if(button)button.hidden=true;toast('Đã đánh dấu tất cả thông báo là đã đọc.')}catch(error){toast(error.message,true)}});
  });
})();