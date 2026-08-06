<?php
/** Trình chỉnh sửa từng thành phần trên mẫu thẻ học sinh. */
if (defined('STUDENT_CARD_ELEMENT_EDITOR')) return;
define('STUDENT_CARD_ELEMENT_EDITOR', true);

function student_card_element_editor_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl_student_cards\.php$~', $path) || stripos($html, '</body>') === false) return $html;
    $addon = <<<'HTML'
<style id="studentCardElementEditorStyle">
#scElementEditor .form-label{margin-bottom:.15rem}.sc-editor-selected{outline:2px solid #0d6efd!important;outline-offset:2px;cursor:move!important;resize:both!important;overflow:hidden!important}.sc-editor-target{cursor:pointer}.sc-editor-target:hover{outline:1px dashed rgba(13,110,253,.65);outline-offset:1px}.sc-editor-hint{font-size:.78rem;color:#64748b}.sc-editor-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}.sc-editor-actions{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}@media(max-width:480px){.sc-editor-grid{grid-template-columns:1fr}}
@media print{#scElementEditor{display:none!important}.sc-editor-target,.sc-editor-selected{outline:0!important;resize:none!important;cursor:default!important}}
</style>
<script id="studentCardElementEditorScript">
(function(){
  if(document.getElementById('scElementEditor')) return;
  const preview=document.getElementById('samplePreview');
  const designCol=document.querySelector('#designStep .col-xl-4');
  if(!preview||!designCol) return;

  const ITEMS={
    logo:{label:'Logo trường',face:'front',selector:'.logo',text:false},
    agency:{label:'Dòng Sở GD&ĐT',face:'front',selector:'.agency',text:true},
    school:{label:'Tên trường',face:'front',selector:'.school-name',text:true},
    title:{label:'Tiêu đề THẺ HỌC SINH',face:'front',selector:'.title',text:true},
    photo:{label:'Ảnh học sinh',face:'front',selector:'.photo',text:false},
    name:{label:'Tên học sinh',face:'front',selector:'.student-name',text:true},
    info:{label:'Lớp, năm học, niên khóa',face:'front',selector:'.student-info',text:true},
    frontQr:{label:'Mã QR mặt trước',face:'front',selector:'.front-qr',text:false},
    ruleTitle:{label:'Tiêu đề quy định',face:'back',selector:'.rule-title',text:true},
    rules:{label:'Nội dung quy định',face:'back',selector:'.rules',text:true},
    backQr:{label:'Mã QR mặt sau',face:'back',selector:'.back-qr',text:false},
    backNote:{label:'Ghi chú dưới QR',face:'back',selector:'.back-note',text:true}
  };
  const STORE='cdsStudentCardElementLayoutV2';
  let all={vertical:{},horizontal:{}}, selected='title', dragging=false, resizeObserver=null, applying=false;
  try{all=Object.assign(all,JSON.parse(localStorage.getItem(STORE)||'{}'))}catch(e){}

  const box=document.createElement('div');
  box.id='scElementEditor';box.className='panel p-3 mb-3 no-print';
  box.innerHTML=`<h6 class="fw-bold mb-1"><i class="bi bi-bounding-box-circles"></i> Chỉnh từng thành phần</h6>
  <div class="sc-editor-hint mb-2">Bấm trực tiếp vào logo, ảnh, QR hoặc chữ trên thẻ mẫu; kéo để di chuyển, kéo góc dưới bên phải để đổi kích thước.</div>
  <label class="form-label small">Thành phần đang chọn</label><select id="sceItem" class="form-select form-select-sm mb-2"></select>
  <div id="sceMissing" class="alert alert-warning py-2 small d-none">Thành phần này đang bị tắt ở phần tùy chọn mặt trước/mặt sau.</div>
  <div class="sc-editor-grid">
    <div><label class="form-label small">Vị trí X (mm)</label><input id="sceX" type="number" step="0.1" class="form-control form-control-sm"></div>
    <div><label class="form-label small">Vị trí Y (mm)</label><input id="sceY" type="number" step="0.1" class="form-control form-control-sm"></div>
    <div><label class="form-label small">Rộng (mm)</label><input id="sceW" type="number" min="2" step="0.1" class="form-control form-control-sm"></div>
    <div><label class="form-label small">Cao (mm)</label><input id="sceH" type="number" min="2" step="0.1" class="form-control form-control-sm"></div>
  </div>
  <div id="sceTextControls" class="mt-2">
    <div class="sc-editor-grid">
      <div><label class="form-label small">Cỡ chữ (pt)</label><input id="sceFont" type="number" min="4" max="40" step="0.2" class="form-control form-control-sm"></div>
      <div><label class="form-label small">Màu chữ</label><input id="sceColor" type="color" class="form-control form-control-color w-100"></div>
      <div><label class="form-label small">Độ đậm</label><select id="sceWeight" class="form-select form-select-sm"><option value="300">Mảnh</option><option value="400">Thường</option><option value="500">Vừa</option><option value="600">Đậm vừa</option><option value="700">Đậm</option><option value="800">Rất đậm</option><option value="900">Đậm nhất</option></select></div>
      <div><label class="form-label small">Căn chữ</label><select id="sceAlign" class="form-select form-select-sm"><option value="left">Trái</option><option value="center">Giữa</option><option value="right">Phải</option></select></div>
    </div>
    <label class="form-check mt-2"><input id="sceItalic" class="form-check-input" type="checkbox"><span class="form-check-label small">Chữ nghiêng</span></label>
  </div>
  <div class="sc-editor-actions mt-3"><button id="sceReset" type="button" class="btn btn-sm btn-outline-warning">Đặt lại khối này</button><button id="sceSave" type="button" class="btn btn-sm btn-success">Lưu bố cục</button></div>`;
  const panels=designCol.querySelectorAll('.panel');
  if(panels.length>1) panels[1].insertAdjacentElement('afterend',box); else designCol.appendChild(box);

  const $=id=>document.getElementById(id), itemSelect=$('sceItem');
  Object.entries(ITEMS).forEach(([key,v])=>{const o=document.createElement('option');o.value=key;o.textContent=v.label;itemSelect.appendChild(o)});
  itemSelect.value=selected;

  function type(){return document.querySelector('.type-card.active')?.dataset.type||preview.querySelector('.card-face.horizontal')?'horizontal':'vertical'}
  function dims(){return type()==='horizontal'?{w:90,h:60}:{w:54,h:86}}
  function faceFor(key,root=preview){const meta=ITEMS[key];return root.querySelector('.card-face.'+meta.face)}
  function targetFor(key,root=preview){const face=faceFor(key,root);return face?face.querySelector(ITEMS[key].selector):null}
  function config(){return all[type()]||(all[type()]={})}
  function save(){localStorage.setItem(STORE,JSON.stringify(all))}
  function pxToMm(px,axis,face){const d=dims(),r=face.getBoundingClientRect();return px/(axis==='x'?r.width:r.height)*(axis==='x'?d.w:d.h)}
  function mmToPct(mm,axis){const d=dims();return mm/(axis==='x'?d.w:d.h)*100}
  function capture(key){
    const el=targetFor(key),face=faceFor(key);if(!el||!face)return null;
    const er=el.getBoundingClientRect(),fr=face.getBoundingClientRect(),cs=getComputedStyle(el);
    const d={x:pxToMm(er.left-fr.left,'x',face),y:pxToMm(er.top-fr.top,'y',face),w:pxToMm(er.width,'x',face),h:pxToMm(er.height,'y',face)};
    if(ITEMS[key].text){d.font=parseFloat(cs.fontSize)*72/96;d.color=rgbToHex(cs.color);d.weight=parseInt(cs.fontWeight)||400;d.italic=cs.fontStyle==='italic';d.align=cs.textAlign||'center'}
    return d;
  }
  function rgbToHex(v){const m=String(v).match(/\d+/g);if(!m||m.length<3)return '#10243a';return '#'+m.slice(0,3).map(x=>(+x).toString(16).padStart(2,'0')).join('')}
  function ensure(key){const c=config();if(!c[key])c[key]=capture(key)||{x:2,y:2,w:20,h:8,font:8,color:'#10243a',weight:400,italic:false,align:'center'};return c[key]}
  function styleOne(el,key,c){
    if(!el||!c)return;el.dataset.sceKey=key;el.classList.add('sc-editor-target');
    el.style.position='absolute';el.style.left=mmToPct(c.x,'x')+'%';el.style.top=mmToPct(c.y,'y')+'%';el.style.width=mmToPct(c.w,'x')+'%';el.style.height=mmToPct(c.h,'y')+'%';el.style.margin='0';el.style.maxWidth='none';el.style.boxSizing='border-box';el.style.zIndex='5';
    if(ITEMS[key].text){el.style.fontSize=c.font+'pt';el.style.color=c.color;el.style.fontWeight=c.weight;el.style.fontStyle=c.italic?'italic':'normal';el.style.textAlign=c.align;el.style.whiteSpace=key==='title'?'nowrap':el.style.whiteSpace}
  }
  function applyRoot(root){
    const t=type(),cfg=all[t]||{};root.querySelectorAll('.card-face').forEach(face=>{
      Object.entries(ITEMS).forEach(([key,meta])=>{if(!face.classList.contains(meta.face))return;const el=face.querySelector(meta.selector);if(el&&cfg[key])styleOne(el,key,cfg[key])});
    });
  }
  function applyAll(){if(applying)return;applying=true;document.querySelectorAll('#samplePreview,.print-grid').forEach(applyRoot);bind();applying=false}
  function bind(){
    preview.querySelectorAll('.sc-editor-target').forEach(el=>{el.onpointerdown=startDrag;el.onclick=e=>{e.stopPropagation();select(el.dataset.sceKey)}});
    preview.querySelectorAll('.sc-editor-selected').forEach(el=>el.classList.remove('sc-editor-selected'));
    const el=targetFor(selected);if(el){el.classList.add('sc-editor-selected');observeResize(el)}
  }
  function select(key){selected=key;itemSelect.value=key;const el=targetFor(key);$('sceMissing').classList.toggle('d-none',!!el);$('sceTextControls').classList.toggle('d-none',!ITEMS[key].text);if(el){ensure(key);applyAll();fill()}}
  function fill(){const c=ensure(selected);$('sceX').value=c.x.toFixed(1);$('sceY').value=c.y.toFixed(1);$('sceW').value=c.w.toFixed(1);$('sceH').value=c.h.toFixed(1);if(ITEMS[selected].text){$('sceFont').value=(c.font||8).toFixed(1);$('sceColor').value=c.color||'#10243a';$('sceWeight').value=String(c.weight||400);$('sceItalic').checked=!!c.italic;$('sceAlign').value=c.align||'center'}}
  function startDrag(e){if(e.button!==0)return;const el=e.currentTarget,key=el.dataset.sceKey;select(key);e.preventDefault();dragging=true;const face=faceFor(key),c=ensure(key),r=face.getBoundingClientRect(),sx=e.clientX,sy=e.clientY,ox=c.x,oy=c.y,d=dims();el.setPointerCapture(e.pointerId);el.onpointermove=ev=>{c.x=Math.max(0,Math.min(d.w-c.w,ox+(ev.clientX-sx)/r.width*d.w));c.y=Math.max(0,Math.min(d.h-c.h,oy+(ev.clientY-sy)/r.height*d.h));styleOne(el,key,c);fill()};el.onpointerup=()=>{dragging=false;el.onpointermove=null;save();applyAll()}}
  function observeResize(el){if(resizeObserver)resizeObserver.disconnect();resizeObserver=new ResizeObserver(()=>{if(applying||dragging)return;const face=faceFor(selected);if(!face)return;const er=el.getBoundingClientRect(),c=ensure(selected);c.w=Math.max(2,pxToMm(er.width,'x',face));c.h=Math.max(2,pxToMm(er.height,'y',face));fill();save()});resizeObserver.observe(el)}
  function update(){const c=ensure(selected);c.x=+$('sceX').value||0;c.y=+$('sceY').value||0;c.w=Math.max(2,+$('sceW').value||2);c.h=Math.max(2,+$('sceH').value||2);if(ITEMS[selected].text){c.font=Math.max(4,+$('sceFont').value||8);c.color=$('sceColor').value;c.weight=+$('sceWeight').value;c.italic=$('sceItalic').checked;c.align=$('sceAlign').value}save();applyAll()}
  ['sceX','sceY','sceW','sceH','sceFont','sceColor','sceWeight','sceItalic','sceAlign'].forEach(id=>$(id)?.addEventListener('input',update));
  itemSelect.addEventListener('change',()=>select(itemSelect.value));
  $('sceSave').addEventListener('click',()=>{save();alert('Đã lưu vị trí, kích thước và kiểu chữ cho mẫu '+(type()==='vertical'?'dọc':'ngang')+'.')});
  $('sceReset').addEventListener('click',()=>{delete config()[selected];save();const el=targetFor(selected);if(el)el.removeAttribute('style');applyAll();select(selected)});

  let timer;const observer=new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(()=>{applyAll();select(selected)},80)});observer.observe(preview,{childList:true,subtree:true});
  document.querySelectorAll('.type-card').forEach(x=>x.addEventListener('click',()=>setTimeout(()=>{applyAll();select(selected)},100)));
  applyAll();select(selected);
})();
</script>
HTML;
    return preg_replace('/<\/body>/i', $addon . '</body>', $html, 1) ?? $html;
}
ob_start('student_card_element_editor_filter');
