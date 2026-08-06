<?php
/** Điều khiển dịch chuyển chính xác từng khối thẻ bằng thông số và phím mũi tên. */
if (defined('STUDENT_CARD_NUDGE_CONTROLS')) return;
define('STUDENT_CARD_NUDGE_CONTROLS', true);

function student_card_nudge_controls_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl_student_cards\.php$~', $path) || stripos($html, '</body>') === false) return $html;
    $addon = <<<'HTML'
<style id="studentCardNudgeStyle">
.sce-nudge-wrap{margin-top:.65rem;padding:.65rem;border:1px solid #dbe4ed;border-radius:.65rem;background:#f8fafc}
.sce-nudge-grid{display:grid;grid-template-columns:42px 42px 42px;grid-template-rows:38px 38px 38px;gap:.25rem;justify-content:center;align-items:center}
.sce-nudge-grid button{font-size:1rem;font-weight:800;padding:0}
.sce-nudge-grid .up{grid-column:2;grid-row:1}.sce-nudge-grid .left{grid-column:1;grid-row:2}.sce-nudge-grid .center{grid-column:2;grid-row:2;font-size:.72rem;color:#64748b;text-align:center}.sce-nudge-grid .right{grid-column:3;grid-row:2}.sce-nudge-grid .down{grid-column:2;grid-row:3}
.sce-nudge-step{max-width:110px}
</style>
<script id="studentCardNudgeScript">
(function(){
  function init(){
    const panel=document.getElementById('scElementEditor');
    const x=document.getElementById('sceX'),y=document.getElementById('sceY');
    if(!panel||!x||!y||document.getElementById('sceNudgeWrap'))return false;
    const actions=panel.querySelector('.sce-actions');
    const wrap=document.createElement('div');
    wrap.id='sceNudgeWrap';wrap.className='sce-nudge-wrap';
    wrap.innerHTML=`<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <div><strong class="small">Dịch chuyển chính xác</strong><div class="small text-muted">Theo đơn vị mm</div></div>
      <label class="small mb-0">Bước dịch
        <select id="sceNudgeStep" class="form-select form-select-sm sce-nudge-step">
          <option value="0.1">0,1 mm</option><option value="0.2">0,2 mm</option><option value="0.5" selected>0,5 mm</option><option value="1">1 mm</option><option value="2">2 mm</option>
        </select>
      </label>
    </div>
    <div class="sce-nudge-grid" aria-label="Điều khiển dịch chuyển khối">
      <button type="button" class="btn btn-outline-primary up" data-dx="0" data-dy="-1" title="Dịch lên">↑</button>
      <button type="button" class="btn btn-outline-primary left" data-dx="-1" data-dy="0" title="Dịch trái">←</button>
      <span class="center">X / Y</span>
      <button type="button" class="btn btn-outline-primary right" data-dx="1" data-dy="0" title="Dịch phải">→</button>
      <button type="button" class="btn btn-outline-primary down" data-dx="0" data-dy="1" title="Dịch xuống">↓</button>
    </div>
    <div class="small text-muted mt-2">Có thể nhập trực tiếp X/Y. Khi đang chọn khối, dùng phím mũi tên; giữ Shift để dịch nhanh gấp 5 lần.</div>`;
    if(actions)actions.insertAdjacentElement('beforebegin',wrap);else panel.appendChild(wrap);

    function number(input){const v=parseFloat(String(input.value).replace(',','.'));return Number.isFinite(v)?v:0}
    function decimals(step){return step<0.1?2:1}
    function commit(input,value,step){
      input.value=Math.max(0,value).toFixed(decimals(step));
      input.dispatchEvent(new Event('input',{bubbles:true}));
      input.dispatchEvent(new Event('change',{bubbles:true}));
    }
    function move(dx,dy,multiplier){
      const step=parseFloat(document.getElementById('sceNudgeStep')?.value||'0.5')*(multiplier||1);
      if(dx)commit(x,number(x)+dx*step,step);
      if(dy)commit(y,number(y)+dy*step,step);
    }
    wrap.addEventListener('click',e=>{
      const b=e.target.closest('[data-dx]');if(!b)return;
      move(parseFloat(b.dataset.dx||0),parseFloat(b.dataset.dy||0),1);
    });
    [x,y].forEach(input=>{
      input.addEventListener('keydown',e=>{
        if(!['ArrowUp','ArrowDown'].includes(e.key))return;
        e.preventDefault();
        const step=parseFloat(input.step||'0.1')*(e.shiftKey?5:1);
        commit(input,number(input)+(e.key==='ArrowUp'?step:-step),step);
      });
    });
    document.addEventListener('keydown',e=>{
      const tag=(e.target&&e.target.tagName)||'';
      if(['INPUT','TEXTAREA','SELECT'].includes(tag))return;
      if(!['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key))return;
      if(document.getElementById('designStep')?.classList.contains('hidden'))return;
      e.preventDefault();
      const map={ArrowUp:[0,-1],ArrowDown:[0,1],ArrowLeft:[-1,0],ArrowRight:[1,0]};
      move(map[e.key][0],map[e.key][1],e.shiftKey?5:1);
    });
    return true;
  }
  if(!init()){
    let tries=0;const timer=setInterval(()=>{if(init()||++tries>40)clearInterval(timer)},100);
  }
})();
</script>
HTML;
    return preg_replace('/<\/body>/i', $addon . '</body>', $html, 1) ?? $html;
}
ob_start('student_card_nudge_controls_filter');
