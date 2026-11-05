(()=>{'use strict';
const roots=[...document.querySelectorAll('[data-up-root]')];
const norm=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').toLowerCase().trim();
function refresh(root){
 const query=norm(root.querySelector('input[data-up-search]')?.value),module=root.querySelector('select[data-up-module]')?.value||'',state=root.querySelector('select[data-up-state]')?.value||'';
 const filtering=!!(query||module||state);root.classList.toggle('up-filtering',filtering);let count=0;
 root.querySelectorAll('[data-up-feature]').forEach(row=>{
  let match=(!query||norm(row.dataset.upSearch).includes(query))&&(!module||row.dataset.upModule===module);
  if(state==='group')match=match&&(row.querySelector('select')?.value!=='none');
  if(state==='person')match=match&&(row.querySelector('.override-select')?.value!=='inherit');
  if(state==='popup')match=match&&!!row.querySelector('.feature-apply')?.checked;
  row.hidden=!match;if(match)count++;
 });
 root.querySelectorAll('[data-up-module-panel]').forEach(panel=>{
  const show=[...panel.querySelectorAll('[data-up-feature]')].some(row=>!row.hidden);panel.hidden=!show;
  if(panel.tagName==='DETAILS'){
   if(filtering){if(!panel.hasAttribute('data-up-was-open'))panel.dataset.upWasOpen=panel.open?'1':'0';panel.open=show;}
   else if(panel.hasAttribute('data-up-was-open')){panel.open=panel.dataset.upWasOpen==='1';delete panel.dataset.upWasOpen;}
  }
 });
 const total=root.querySelectorAll('[data-up-feature]').length,display=root.querySelector('[data-up-count]');if(display)display.textContent=count+'/'+total+' chức năng';
 const pending=root.querySelector('[data-up-pending]');if(pending){const checked=root.querySelectorAll('.feature-apply:checked').length;pending.textContent='Chọn áp dụng '+checked+'/'+total+' chức năng. Mục không chọn được giữ nguyên.';}
}
function snapshot(){
 const root=document.querySelector('[data-up-root="person"]');if(!root)return;const id=document.getElementById('f_id')?.value||'',access=typeof USER_ACCESS==='object'?USER_ACCESS[id]||{}:{},labels={none:'Không quyền',view:'Xem',edit:'Sửa',delete:'Xóa'};
 root.querySelectorAll('[data-up-effective]').forEach(node=>{node.textContent=id?'Mức đang có: '+(labels[access[node.dataset.upEffective]||'none']||'Không quyền'):'Chưa lưu tài khoản';});
 const note=root.querySelector('[data-up-person-snapshot]');if(note)note.textContent=id?'Mức đang có là kết quả đã lưu trước khi chỉnh. Quyền cá nhân có thể ghi đè quyền nhóm; thay đổi chỉ có hiệu lực sau khi lưu.':'Tài khoản mới: chưa có kết quả phân quyền đã lưu.';
}
roots.forEach(root=>{
 root.addEventListener('input',()=>refresh(root));root.addEventListener('change',()=>refresh(root));
 root.addEventListener('click',event=>{if(event.target.closest('[data-up-clear]')){root.querySelectorAll('input[data-up-search],select[data-up-module],select[data-up-state]').forEach(control=>control.value='');}queueMicrotask(()=>refresh(root));});
 root.querySelector('input[data-up-search]')?.addEventListener('keydown',event=>{if(event.key==='Enter')event.preventDefault();});refresh(root);
});
document.getElementById('permissionModal')?.addEventListener('show.bs.modal',()=>{const root=document.getElementById('permissionPopupForm');if(root)refresh(root);});
for(const name of ['editUser','resetForm']){const previous=window[name];if(typeof previous==='function')window[name]=function(...args){const result=previous.apply(this,args);snapshot();const root=document.querySelector('[data-up-root="person"]');if(root)refresh(root);return result;};}
const focus=document.getElementById('upMatrixModule');focus?.addEventListener('change',()=>{const key=focus.value;document.querySelectorAll('[data-up-column]').forEach(cell=>cell.hidden=!!key&&cell.dataset.upColumn!==key);document.querySelector('.permission-matrix')?.classList.toggle('up-matrix-focused',!!key);});
snapshot();
})();
