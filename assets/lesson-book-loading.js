(function(){
'use strict';
const box=document.getElementById('lbPageLoading'),text=document.getElementById('lbPageLoadingText');if(!box||!text)return;
let timer=null,pendingForm=null,repeatTimer=null;
function reset(){clearTimeout(timer);clearTimeout(repeatTimer);timer=null;repeatTimer=null;pendingForm=null;box.hidden=true;document.querySelector('main.lb-shell')?.removeAttribute('aria-busy');}
function show(message){clearTimeout(timer);text.textContent=message;box.hidden=false;document.querySelector('main.lb-shell')?.setAttribute('aria-busy','true');timer=setTimeout(()=>{text.textContent='Máy chủ đang xử lý dữ liệu. Vui lòng chờ…';},15000);}
function pageUrl(value){try{const url=new URL(value||location.href,location.href);return url.origin===location.origin&&/\/sodaubai\.php$/i.test(url.pathname)&&!url.searchParams.has('ajax')?url:null;}catch(e){return null;}}
document.addEventListener('submit',function(event){
 const form=event.target;if(!(form instanceof HTMLFormElement)||(form.method||'get').toLowerCase()!=='get'||(form.target&&form.target!=='_self')||!pageUrl(form.action))return;
 if(pendingForm===form){event.preventDefault();return;}
 if(event.defaultPrevented)return;pendingForm=form;clearTimeout(repeatTimer);repeatTimer=setTimeout(()=>{pendingForm=null;},1000);show('Đang tải nội dung đã chọn…');
 queueMicrotask(()=>{if(event.defaultPrevented)reset();});
});
document.addEventListener('click',function(event){
 if(event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
 const link=event.target.closest?.('a[href]');if(!link||link.hasAttribute('download')||(link.target&&link.target!=='_self'))return;
 const url=pageUrl(link.href);if(!url||(url.pathname===location.pathname&&url.search===location.search&&url.hash))return;
 if(event.defaultPrevented)return;show('Đang mở mục đã chọn…');queueMicrotask(()=>{if(event.defaultPrevented)reset();});
});
window.addEventListener('pageshow',reset);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',reset,{once:true});else reset();
})();
