(function(){
'use strict';
const app=document.getElementById('academic-drive');if(!app||!app.dataset.root)return;
const byId=id=>document.getElementById(id),items=byId('acd-items'),status=byId('acd-status'),more=byId('acd-more'),preview=byId('acd-preview'),frame=byId('acd-frame');
let folder=app.dataset.root,cursor='',generation=0,controller=null,busy=false;
function node(tag,text,cls){const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;}
function safeUrl(value){try{const u=new URL(value);return u.protocol==='https:'&&['drive.google.com','docs.google.com'].includes(u.hostname)&&!u.username&&!u.password&&!u.port?u.href:'';}catch(_){return '';}}
function sourceLink(url,text){const a=node('a',text);a.href=safeUrl(url)||'https://drive.google.com/';a.target='_blank';a.rel='noopener noreferrer';return a;}
function closePreview(){preview.close();frame.removeAttribute('src');}
byId('acd-close').addEventListener('click',closePreview);preview.addEventListener('close',()=>frame.removeAttribute('src'));
function view(item){const url=safeUrl(item.preview_url);if(!url)return;byId('acd-preview-title').textContent=item.name;byId('acd-preview-source').href=safeUrl(item.open_url);frame.src=url;preview.showModal();}
function openFolder(id){folder=id;byId('acd-query').value='';load(false);}
function renderItem(item){const tr=node('tr'),name=node('td'),date=node('td'),source=node('td');const icon=node('i',undefined,item.folder?'bi bi-folder-fill text-warning me-2':'bi bi-file-earmark-text text-primary me-2');icon.setAttribute('aria-hidden','true');name.append(icon);
if(item.folder||item.preview_url){const button=node('button',item.name,'btn btn-link p-0');button.type='button';button.addEventListener('click',()=>item.folder?openFolder(item.id):view(item));name.append(button);}else name.append(sourceLink(item.open_url,item.name));
if(item.shortcut)name.append(node('small',' (Lối tắt trên Drive)','text-muted'));
const d=new Date(item.modified);date.textContent=Number.isNaN(d.getTime())?'—':d.toLocaleString('vi-VN');source.append(sourceLink(item.open_url,'Mở trên Drive'));tr.append(name,date,source);items.append(tr);}
async function load(append){const run=++generation;if(controller)controller.abort();controller=new AbortController();busy=true;more.disabled=true;app.setAttribute('aria-busy','true');status.className='text-muted';status.textContent='Đang đọc Google Drive…';if(!append){cursor='';items.replaceChildren();more.hidden=true;}
try{const url=new URL('hoso.php',document.baseURI);url.search=new URLSearchParams({api:'list',folder,sort:byId('acd-sort').value,q:byId('acd-query').value.trim(),cursor:append?cursor:''}).toString();const response=await fetch(url,{credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});let data;try{data=await response.json();}catch(_){throw new Error('Phiên đăng nhập đã hết hoặc máy chủ chưa trả về dữ liệu. Hãy tải lại trang.');}if(run!==generation)return;if(!response.ok||!data.ok)throw new Error(data.message||'Không đọc được thư mục.');
const breadcrumbs=byId('acd-breadcrumbs');breadcrumbs.replaceChildren();data.breadcrumbs.forEach((b,i)=>{if(i)breadcrumbs.append(node('span','/'));const button=node('button',b.name);button.type='button';button.addEventListener('click',()=>openFolder(b.id));if(i===data.breadcrumbs.length-1)button.setAttribute('aria-current','page');breadcrumbs.append(button);});
data.items.forEach(renderItem);cursor=data.next_cursor||'';more.hidden=!cursor;byId('acd-source').href=safeUrl(data.source_url);status.textContent=data.incomplete?'Drive chưa trả đủ kết quả. Hãy làm mới hoặc thu hẹp tìm kiếm.':items.children.length?'Đã hiển thị '+items.children.length+' mục.':'Thư mục trống hoặc không có tài liệu phù hợp.';
}catch(error){if(run!==generation||error.name==='AbortError')return;status.className='text-danger';status.textContent=error.message;more.hidden=!cursor;}finally{if(run===generation){busy=false;more.disabled=false;app.removeAttribute('aria-busy');}}}
byId('acd-search').addEventListener('submit',e=>{e.preventDefault();load(false);});byId('acd-sort').addEventListener('change',()=>load(false));byId('acd-refresh').addEventListener('click',()=>load(false));more.addEventListener('click',()=>{if(!busy)load(true);});load(false);
})();
