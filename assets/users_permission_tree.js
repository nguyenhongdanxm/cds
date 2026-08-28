(function(){
'use strict';

function ready(fn){if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fn);else fn();}
function el(tag,cls,html){var n=document.createElement(tag);if(cls)n.className=cls;if(html!==undefined)n.innerHTML=html;return n;}
function safeText(v){return String(v||'').trim();}

function injectStyle(){
  if(document.getElementById('usersPermissionTreeStyle'))return;
  var s=el('style');s.id='usersPermissionTreeStyle';s.textContent=`
  .perm-tree{border:1px solid #dbe5ef;border-radius:12px;background:#fff;overflow:hidden}
  .perm-tree+.perm-tree{margin-top:.65rem}
  .perm-tree-head{display:flex;align-items:center;gap:.65rem;padding:.7rem .85rem;background:#f8fafc;cursor:pointer;user-select:none;border-bottom:1px solid transparent}
  .perm-tree.open>.perm-tree-head{border-bottom-color:#e7edf3;background:#eef5fb}
  .perm-tree-toggle{width:26px;height:26px;border:1px solid #b8c6d4;border-radius:5px;background:#fff;color:#285b86;display:inline-flex;align-items:center;justify-content:center;font-weight:700;line-height:1;flex:0 0 26px}
  .perm-tree-title{font-weight:700;flex:1;color:#243746}
  .perm-tree-count{font-size:.76rem;color:#6c757d}
  .perm-tree-body{display:none;padding:.35rem .65rem .65rem}
  .perm-tree.open>.perm-tree-body{display:block}
  .perm-tree-child{position:relative;margin-left:1rem;padding-left:1.15rem}
  .perm-tree-child:before{content:'';position:absolute;left:.1rem;top:0;bottom:0;border-left:1px dotted #aebdca}
  .perm-tree-child>.feature-row,.perm-tree-child>.perm-tree-leaf{position:relative}
  .perm-tree-child>.feature-row:before,.perm-tree-child>.perm-tree-leaf:before{content:'';position:absolute;left:-1.05rem;top:50%;width:.85rem;border-top:1px dotted #aebdca}
  .perm-tree-leaf{display:flex;align-items:center;gap:.65rem;padding:.48rem .45rem;border-bottom:1px solid #f0f3f6}
  .perm-tree-leaf:last-child{border-bottom:0}
  .perm-tree-leaf .leaf-label{flex:1;min-width:0}
  .perm-tree-leaf .leaf-code{font-size:.72rem;color:#8493a1}
  .perm-tree-master{margin:0;flex:0 0 auto}
  .perm-tree .feature-row{margin:0!important;padding:.55rem .35rem!important}
  .perm-tree .feature-row:last-child{border-bottom:0!important}
  .perm-tree-module-tools{display:flex;flex-wrap:wrap;gap:.25rem;align-items:center}
  .perm-tree-group{margin:.35rem 0;border-left:3px solid #d6e4f0;border-radius:8px;background:#fbfdff}
  .perm-tree-group-head{display:flex;align-items:center;gap:.5rem;padding:.4rem .55rem;font-weight:600;color:#496172;cursor:pointer}
  .perm-tree-group-body{padding:0 .35rem .3rem .7rem}
  .permission-modal .perm-tree-stack{display:grid;gap:.6rem}
  .permission-modal .nav-tabs{display:none!important}
  .permission-modal .tab-content{border:0!important;padding:0!important}
  .permission-modal .tab-pane{display:block!important;opacity:1!important;margin:0 0 .65rem!important}
  .permission-modal .tab-pane>.d-flex:first-child{display:none!important}
  .permission-modal .tab-pane.perm-pane-ready{border:1px solid #dbe5ef;border-radius:12px;overflow:hidden;background:#fff}
  .permission-modal .tab-pane.perm-pane-ready>.perm-modal-tree-head{display:flex;align-items:center;gap:.6rem;padding:.65rem .8rem;background:#f8fafc;cursor:pointer}
  .permission-modal .tab-pane.perm-pane-ready>.feature-row{display:none}
  .permission-modal .tab-pane.perm-pane-ready.open>.feature-row{display:flex}
  .permission-modal .tab-pane.perm-pane-ready.open>.perm-modal-tree-head{background:#eef5fb;border-bottom:1px solid #e2e8f0}
  .perm-group-card>.card-header{cursor:pointer;display:flex;align-items:center;gap:.6rem}
  .perm-group-card>.table-responsive{display:none}
  .perm-group-card.open>.table-responsive{display:block}
  .perm-group-card .perm-group-parent{margin-left:auto}
  .perm-editor-tree{max-height:430px!important;padding:.5rem!important}
  .perm-editor-module{border:1px solid #e2e8f0;border-radius:9px;margin-bottom:.45rem;overflow:hidden;background:#fff}
  .perm-editor-module-head{display:flex;align-items:center;gap:.55rem;padding:.5rem .6rem;background:#f8fafc;cursor:pointer}
  .perm-editor-module-body{display:none;padding:.35rem .55rem .55rem}
  .perm-editor-module.open>.perm-editor-module-body{display:block}
  @media(max-width:767px){.perm-tree-module-tools{display:none}.permission-modal .feature-row .col-lg-6:last-child{padding-left:1.6rem}.perm-tree-title{font-size:.92rem}}
  `;document.head.appendChild(s);
}

function setToggle(button,open){if(!button)return;button.textContent=open?'−':'+';button.setAttribute('aria-expanded',open?'true':'false');}

function enhanceGroupPermissions(){
  var form=document.querySelector('form input[name="action"][value="save_group"]')?.closest('form');
  if(!form)return;
  var cards=Array.from(form.querySelectorAll('.card-body > .card.border.mb-3'));
  cards.forEach(function(card,index){
    if(card.dataset.treeReady)return;card.dataset.treeReady='1';card.classList.add('perm-group-card');
    var header=card.querySelector(':scope > .card-header');var table=card.querySelector(':scope > .table-responsive');if(!header||!table)return;
    var label=safeText(header.textContent);header.textContent='';
    var toggle=el('button','perm-tree-toggle','+');toggle.type='button';
    var title=el('span','perm-tree-title');title.textContent=label;
    var rows=Array.from(table.querySelectorAll('tbody tr'));
    var master=el('input','form-check-input perm-group-parent');master.type='checkbox';master.title='Chọn toàn bộ chức năng trong nhánh';
    header.append(toggle,title,el('span','perm-tree-count',rows.length+' chức năng'),master);
    function open(v){card.classList.toggle('open',v);setToggle(toggle,v);}
    header.addEventListener('click',function(e){if(e.target===master)return;open(!card.classList.contains('open'));});
    master.addEventListener('change',function(){rows.forEach(function(r){var sel=r.querySelector('select[name^="access["]');if(sel)sel.value=master.checked?'view':'none';});syncMaster();});
    function syncMaster(){var vals=rows.map(function(r){return r.querySelector('select[name^="access["]')?.value||'none';});var on=vals.filter(function(v){return v!=='none';}).length;master.checked=on===vals.length&&vals.length>0;master.indeterminate=on>0&&on<vals.length;}
    rows.forEach(function(r){r.querySelector('select[name^="access["]')?.addEventListener('change',syncMaster);});syncMaster();
    open(index===0);
  });
}

function enhanceBulkModal(){
  var modal=document.getElementById('permissionModal');if(!modal)return;
  var panes=Array.from(modal.querySelectorAll('.tab-pane'));
  panes.forEach(function(pane,index){
    if(pane.dataset.treeReady)return;pane.dataset.treeReady='1';pane.classList.add('perm-pane-ready');
    var oldTitle=pane.querySelector(':scope > .d-flex:first-child strong');
    var title=safeText(oldTitle?.textContent)||pane.id.replace('permTab_','');
    var rows=Array.from(pane.querySelectorAll(':scope > .feature-row'));
    var head=el('div','perm-modal-tree-head');
    var toggle=el('button','perm-tree-toggle',index===0?'−':'+');toggle.type='button';
    var master=el('input','form-check-input perm-tree-master');master.type='checkbox';master.title='Tích áp dụng toàn bộ chức năng trong nhánh';
    var ttl=el('span','perm-tree-title');ttl.textContent=title;
    head.append(toggle,master,ttl,el('span','perm-tree-count',rows.length+' chức năng'));
    pane.insertBefore(head,pane.firstChild);pane.classList.toggle('open',index===0);
    head.addEventListener('click',function(e){if(e.target===master)return;var v=!pane.classList.contains('open');pane.classList.toggle('open',v);setToggle(toggle,v);});
    master.addEventListener('change',function(){rows.forEach(function(row){var a=row.querySelector('.feature-apply');if(a)a.checked=master.checked;});sync();});
    function sync(){var boxes=rows.map(function(r){return r.querySelector('.feature-apply');}).filter(Boolean);var checked=boxes.filter(function(b){return b.checked;}).length;master.checked=boxes.length>0&&checked===boxes.length;master.indeterminate=checked>0&&checked<boxes.length;}
    rows.forEach(function(r){r.querySelector('.feature-apply')?.addEventListener('change',sync);});sync();
  });
}

function enhanceUserEditor(){
  var title=Array.from(document.querySelectorAll('#userEditor h6')).find(function(h){return h.textContent.includes('Quyền cá nhân');});
  if(!title)return;
  var box=title.parentElement.querySelector('.perm-box[style*="max-height:360px"]');if(!box||box.dataset.treeReady)return;box.dataset.treeReady='1';box.classList.add('perm-editor-tree');
  var nodes=Array.from(box.children);var modules=[];var current=null;
  nodes.forEach(function(n){
    if(n.matches('.small.fw-semibold.text-secondary')){current={label:safeText(n.textContent),heading:n,rows:[]};modules.push(current);}else if(current)current.rows.push(n);
  });
  modules.forEach(function(mod,index){
    var wrap=el('div','perm-editor-module'+(index===0?' open':''));
    var head=el('div','perm-editor-module-head');var toggle=el('button','perm-tree-toggle',index===0?'−':'+');toggle.type='button';var ttl=el('span','perm-tree-title');ttl.textContent=mod.label;
    head.append(toggle,ttl,el('span','perm-tree-count',mod.rows.length+' chức năng'));
    var body=el('div','perm-editor-module-body');mod.rows.forEach(function(r){body.appendChild(r);});
    mod.heading.replaceWith(wrap);wrap.append(head,body);
    head.addEventListener('click',function(){var v=!wrap.classList.contains('open');wrap.classList.toggle('open',v);setToggle(toggle,v);});
  });
}

ready(function(){injectStyle();enhanceGroupPermissions();enhanceBulkModal();enhanceUserEditor();});
})();
