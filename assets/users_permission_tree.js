(function(){
'use strict';
function ready(fn){if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fn);else fn();}
function el(tag,cls,txt){var n=document.createElement(tag);if(cls)n.className=cls;if(txt!==undefined)n.textContent=txt;return n;}
function text(v){return String(v||'').trim();}
function setToggle(btn,open){if(!btn)return;btn.textContent=open?'−':'+';btn.setAttribute('aria-expanded',open?'true':'false');}
function injectStyle(){
 if(document.getElementById('usersPermissionTreeStyle'))return;
 var s=el('style');s.id='usersPermissionTreeStyle';s.textContent=`
 .perm-group-card{border:0!important;background:transparent!important;overflow:visible!important;margin-bottom:.7rem!important}
 .perm-group-card>.card-header{background:#1f4e79!important;color:#fff!important;border-radius:9px!important;display:flex;align-items:center;gap:.6rem;padding:.62rem .8rem!important;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.08)}
 .perm-group-card>.card-header .perm-tree-title{color:#fff!important;font-weight:700!important}
 .perm-group-card>.card-header .perm-tree-count{color:#dbeafe!important;font-size:.78rem}
 .perm-group-card>.card-header .perm-tree-toggle{background:#fff!important;color:#1f4e79!important;border-color:#d9e5ef!important}
 .perm-group-card>.card-header .perm-group-parent{margin-left:.15rem;accent-color:#fff}
 .perm-group-card>.table-responsive{display:none!important;overflow:visible!important;background:transparent!important;padding:.45rem 0 0 1.25rem;position:relative}
 .perm-group-card.open>.table-responsive{display:block!important}
 .perm-group-card>.table-responsive:before{content:'';position:absolute;left:.58rem;top:0;bottom:.55rem;border-left:2px dotted #a9bac9}
 .perm-module-groups{display:grid;gap:.42rem}
 .perm-subtree{position:relative;margin-left:.15rem;border:1px solid #dbe5ef;border-radius:8px;background:#fff;overflow:visible}
 .perm-subtree:before{content:'';position:absolute;left:-.82rem;top:20px;width:.82rem;border-top:2px dotted #a9bac9}
 .perm-subtree-head{min-height:40px;display:flex;align-items:center;gap:.5rem;padding:.38rem .55rem;background:#eef5fb;color:#20384d;cursor:pointer;border-radius:7px}
 .perm-subtree.open>.perm-subtree-head{border-radius:7px 7px 0 0;border-bottom:1px solid #dbe5ef;background:#e6f0f8}
 .perm-subtree-title{font-weight:700;flex:1;color:#20384d}
 .perm-subtree-count{font-size:.75rem;color:#667788}
 .perm-subtree-toggle{width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #b9c9d6;border-radius:4px;background:#fff;color:#1f4e79;font-weight:700}
 .perm-subtree-master{margin:0}
 .perm-subtree-body{display:none;position:relative;padding:.22rem 0 .3rem 1.15rem}
 .perm-subtree.open>.perm-subtree-body{display:block}
 .perm-subtree-body:before{content:'';position:absolute;left:.55rem;top:0;bottom:.45rem;border-left:1px dotted #b8c5d0}
 .perm-subtree-table{width:100%;border-collapse:collapse}
 .perm-subtree-table tr{position:relative}
 .perm-subtree-table tr:before{content:'';position:absolute;left:-.6rem;top:50%;width:.6rem;border-top:1px dotted #b8c5d0}
 .perm-subtree-table td{padding:.42rem .5rem;border-bottom:1px solid #edf1f4;background:#fff;vertical-align:middle}
 .perm-subtree-table tr:last-child td{border-bottom:0}
 .perm-subtree-table td:last-child{width:190px}
 .perm-subtree-table .badge{display:none!important}
 .perm-subtree-table .text-muted.small{font-size:.72rem!important}
 .permission-modal .nav-tabs{display:none!important}
 .permission-modal .tab-content{border:0!important;padding:0!important}
 .permission-modal .tab-pane{display:block!important;opacity:1!important;margin:0 0 .6rem!important;border:1px solid #dbe5ef;border-radius:9px;overflow:hidden;background:#fff}
 .permission-modal .tab-pane>.d-flex:first-child{display:none!important}
 .permission-modal .perm-modal-tree-head{display:flex;align-items:center;gap:.6rem;padding:.6rem .75rem;background:#1f4e79;color:#fff;cursor:pointer}
 .permission-modal .perm-modal-tree-head .perm-tree-title{color:#fff!important}
 .permission-modal .perm-modal-tree-head .perm-tree-count{color:#dbeafe!important}
 .permission-modal .tab-pane>.feature-row{display:none}
 .permission-modal .tab-pane.open>.feature-row{display:flex}
 .perm-tree-toggle{width:25px;height:25px;border:1px solid #c8d5df;border-radius:5px;background:#fff;color:#1f4e79;display:inline-flex;align-items:center;justify-content:center;font-weight:700;line-height:1;flex:0 0 25px}
 .perm-tree-title{font-weight:700;flex:1;color:#243746}.perm-tree-count{font-size:.76rem;color:#6c757d}.perm-tree-master{margin:0}
 .perm-editor-tree{max-height:430px!important;padding:.5rem!important}.perm-editor-module{border:1px solid #e2e8f0;border-radius:9px;margin-bottom:.45rem;overflow:hidden;background:#fff}.perm-editor-module-head{display:flex;align-items:center;gap:.55rem;padding:.5rem .6rem;background:#eef5fb;cursor:pointer}.perm-editor-module-body{display:none;padding:.35rem .55rem .55rem}.perm-editor-module.open>.perm-editor-module-body{display:block}
 @media(max-width:767px){.perm-subtree-table td:last-child{width:145px}.perm-group-card>.table-responsive{padding-left:.9rem}.perm-subtree-title,.perm-tree-title{font-size:.92rem}}
 `;document.head.appendChild(s);
}
function selectForRow(row){return row.querySelector('select[name^="access["]');}
function syncCheck(box,rows){var sels=rows.map(selectForRow).filter(Boolean),on=sels.filter(function(s){return s.value!=='none';}).length;box.checked=sels.length>0&&on===sels.length;box.indeterminate=on>0&&on<sels.length;}
function enhanceGroupPermissions(){
 var form=document.querySelector('input[name="action"][value="save_group"]')?.closest('form');if(!form)return;
 Array.from(form.querySelectorAll('.card-body > .card.border.mb-3')).forEach(function(card){
  if(card.dataset.treeV2)return;card.dataset.treeV2='1';card.classList.add('perm-group-card');
  var header=card.querySelector(':scope > .card-header'),tableWrap=card.querySelector(':scope > .table-responsive');if(!header||!tableWrap)return;
  var moduleLabel=text(header.textContent),rows=Array.from(tableWrap.querySelectorAll('tbody tr'));
  header.textContent='';var toggle=el('button','perm-tree-toggle','+');toggle.type='button';var title=el('span','perm-tree-title',moduleLabel);var count=el('span','perm-tree-count',rows.length+' chức năng');var master=el('input','form-check-input perm-group-parent');master.type='checkbox';master.title='Chọn toàn bộ chức năng trong module';header.append(toggle,title,count,master);
  var groups={};rows.forEach(function(row){var badge=row.querySelector('td:first-child .badge');var g=text(badge?.textContent)||'Chung';(groups[g]||(groups[g]=[])).push(row);});
  var holder=el('div','perm-module-groups');tableWrap.innerHTML='';tableWrap.appendChild(holder);
  Object.keys(groups).forEach(function(groupLabel){var groupRows=groups[groupLabel],sub=el('section','perm-subtree'),sh=el('div','perm-subtree-head'),st=el('button','perm-subtree-toggle','+');st.type='button';var sm=el('input','form-check-input perm-subtree-master');sm.type='checkbox';sm.title='Chọn toàn bộ chức năng trong nhánh';var sl=el('span','perm-subtree-title',groupLabel);var sc=el('span','perm-subtree-count',groupRows.length+' mục');sh.append(st,sm,sl,sc);var body=el('div','perm-subtree-body'),tbl=el('table','perm-subtree-table'),tb=document.createElement('tbody');groupRows.forEach(function(r){tb.appendChild(r);});tbl.appendChild(tb);body.appendChild(tbl);sub.append(sh,body);holder.appendChild(sub);
   function openSub(v){sub.classList.toggle('open',v);setToggle(st,v);}sh.addEventListener('click',function(e){if(e.target===sm)return;openSub(!sub.classList.contains('open'));});sm.addEventListener('change',function(){groupRows.forEach(function(r){var s=selectForRow(r);if(s)s.value=sm.checked?'view':'none';});syncCheck(sm,groupRows);syncCheck(master,rows);});groupRows.forEach(function(r){selectForRow(r)?.addEventListener('change',function(){syncCheck(sm,groupRows);syncCheck(master,rows);});});syncCheck(sm,groupRows);
  });
  function openModule(v){card.classList.toggle('open',v);setToggle(toggle,v);}header.addEventListener('click',function(e){if(e.target===master)return;openModule(!card.classList.contains('open'));});master.addEventListener('change',function(){rows.forEach(function(r){var s=selectForRow(r);if(s)s.value=master.checked?'view':'none';});syncCheck(master,rows);holder.querySelectorAll('.perm-subtree').forEach(function(sub){var b=sub.querySelector('.perm-subtree-master'),rs=Array.from(sub.querySelectorAll('tbody tr'));syncCheck(b,rs);});});syncCheck(master,rows);openModule(false);
 });
}
function enhanceBulkModal(){var modal=document.getElementById('permissionModal');if(!modal)return;Array.from(modal.querySelectorAll('.tab-pane')).forEach(function(pane){if(pane.dataset.treeV2)return;pane.dataset.treeV2='1';var title=text(pane.querySelector(':scope > .d-flex:first-child strong')?.textContent)||pane.id.replace('permTab_',''),rows=Array.from(pane.querySelectorAll(':scope > .feature-row')),head=el('div','perm-modal-tree-head'),toggle=el('button','perm-tree-toggle','+');toggle.type='button';var master=el('input','form-check-input perm-tree-master');master.type='checkbox';var ttl=el('span','perm-tree-title',title);head.append(toggle,master,ttl,el('span','perm-tree-count',rows.length+' chức năng'));pane.insertBefore(head,pane.firstChild);head.addEventListener('click',function(e){if(e.target===master)return;var v=!pane.classList.contains('open');pane.classList.toggle('open',v);setToggle(toggle,v);});master.addEventListener('change',function(){rows.forEach(function(r){var a=r.querySelector('.feature-apply');if(a)a.checked=master.checked;});});});}
function enhanceUserEditor(){var title=Array.from(document.querySelectorAll('#userEditor h6')).find(function(h){return h.textContent.includes('Quyền cá nhân');});if(!title)return;var box=title.parentElement.querySelector('.perm-box[style*="max-height:360px"]');if(!box||box.dataset.treeV2)return;box.dataset.treeV2='1';box.classList.add('perm-editor-tree');var nodes=Array.from(box.children),mods=[],cur=null;nodes.forEach(function(n){if(n.matches('.small.fw-semibold.text-secondary')){cur={label:text(n.textContent),heading:n,rows:[]};mods.push(cur);}else if(cur)cur.rows.push(n);});mods.forEach(function(mod){var wrap=el('div','perm-editor-module'),head=el('div','perm-editor-module-head'),tog=el('button','perm-tree-toggle','+');tog.type='button';head.append(tog,el('span','perm-tree-title',mod.label),el('span','perm-tree-count',mod.rows.length+' chức năng'));var body=el('div','perm-editor-module-body');mod.rows.forEach(function(r){body.appendChild(r);});mod.heading.replaceWith(wrap);wrap.append(head,body);head.addEventListener('click',function(){var v=!wrap.classList.contains('open');wrap.classList.toggle('open',v);setToggle(tog,v);});});}
ready(function(){injectStyle();enhanceGroupPermissions();enhanceBulkModal();enhanceUserEditor();});
})();
