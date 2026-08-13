(function(){
  'use strict';
  const state={registration:null,deferredInstall:null,status:null,currentSubscription:null};
  const $=selector=>document.querySelector(selector);
  const api=async(payload,method='POST')=>{
    const response=await fetch('/push_api.php'+(method==='GET'?'?action=status':''),{method,credentials:'same-origin',headers:{'Content-Type':'application/json'},body:method==='GET'?undefined:JSON.stringify(payload)});
    const data=await response.json();if(!response.ok||data.ok===false)throw new Error(data.message||'Không thực hiện được.');return data;
  };
  const b64=value=>{const padding='='.repeat((4-value.length%4)%4);const raw=atob((value+padding).replace(/-/g,'+').replace(/_/g,'/'));return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)))};
  const installed=()=>matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
  const isiOS=()=>/iphone|ipad|ipod/i.test(navigator.userAgent);
  function toast(message,error){let el=$('#pushToast');if(!el){el=document.createElement('div');el.id='pushToast';el.className='push-toast';document.body.appendChild(el)}el.textContent=message;el.classList.toggle('error',!!error);el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3500)}
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
    if(isiOS()&&!installed())panel.querySelector('[data-ios-help]').hidden=false;
  }
  async function subscribe(){
    if(!('serviceWorker'in navigator)||!('PushManager'in window)||!('Notification'in window))throw new Error('Thiết bị hoặc trình duyệt này chưa hỗ trợ thông báo Web Push.');
    if(isiOS()&&!installed())throw new Error('Trên iPhone, hãy Chia sẻ → Thêm vào Màn hình chính, rồi mở CDS từ biểu tượng vừa tạo.');
    const permission=await Notification.requestPermission();if(permission!=='granted')throw new Error('Bạn chưa cho phép CDS gửi thông báo.');
    const status=state.status||await api({},'GET');if(!status.publicKey)throw new Error('Máy chủ chưa tạo được khóa Web Push.');
    let subscription=await state.registration.pushManager.getSubscription();
    if(!subscription)subscription=await state.registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64(status.publicKey)});
    await api({action:'subscribe',subscription:subscription.toJSON()});await refresh();toast('Đã bật thông báo. CDS sẽ gửi thử ngay.');await api({action:'test'});
  }
  async function unsubscribe(){const sub=await state.registration.pushManager.getSubscription();if(sub){await api({action:'unsubscribe',endpoint:sub.endpoint});await sub.unsubscribe()}await refresh();toast('Đã tắt thông báo trên thiết bị này.')}
  async function install(){if(state.deferredInstall){state.deferredInstall.prompt();await state.deferredInstall.userChoice;state.deferredInstall=null;renderPanel();return}if(isiOS())toast('Bấm Chia sẻ trong Safari → Thêm vào Màn hình chính.');else toast('Mở menu trình duyệt → Cài đặt ứng dụng hoặc Thêm vào màn hình chính.')}
  window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();state.deferredInstall=event;renderPanel()});
  document.addEventListener('DOMContentLoaded',async()=>{
    if(!('serviceWorker'in navigator))return;
    try{state.registration=await navigator.serviceWorker.register('/sw.js',{scope:'/'});await navigator.serviceWorker.ready;await refresh()}catch(error){console.error(error)}
    $('#pushSetup')?.addEventListener('click',async event=>{const button=event.target.closest('button');if(!button)return;button.disabled=true;try{if(button.matches('[data-pwa-install]'))await install();if(button.matches('[data-push-enable]'))await subscribe();if(button.matches('[data-push-test]')){const result=await api({action:'test'});toast(result.message)}if(button.matches('[data-push-disable]'))await unsubscribe()}catch(error){toast(error.message,true)}finally{button.disabled=false}});
    document.querySelectorAll('[data-notification-id]').forEach(link=>link.addEventListener('click',()=>api({action:'mark_read',id:link.dataset.notificationId}).catch(()=>null)));
    $('[data-mark-all-read]')?.addEventListener('click',async()=>{await api({action:'mark_read',all:true});await refresh();location.reload()});
  });
})();
