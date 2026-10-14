<?php /* Sơ đồ chỉ tải khi người dùng mở cửa sổ. */ ?>
<button type="button" id="ntMapOpen" class="nt-map-open"><i class="bi bi-map-fill" aria-hidden="true"></i><span>Sơ đồ khu nội trú học sinh<small>Nhấn để xem và phóng to</small></span><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i></button>
<dialog id="ntMapDialog" class="nt-map-dialog" aria-labelledby="ntMapTitle">
 <header class="nt-map-head"><strong id="ntMapTitle"><i class="bi bi-map"></i> Sơ đồ khu nội trú học sinh</strong><button type="button" id="ntMapClose" aria-label="Đóng sơ đồ">✕</button></header>
 <div class="nt-map-tools"><button type="button" id="ntMapMinus" aria-label="Thu nhỏ">−</button><output id="ntMapScale" aria-live="polite">100%</output><button type="button" id="ntMapPlus" aria-label="Phóng to">+</button><button type="button" id="ntMapFit">Vừa khung</button><small>Kéo để di chuyển · Cuộn chuột hoặc chụm hai ngón để thu phóng</small></div>
 <div id="ntMapViewport" class="nt-map-viewport" tabindex="0" aria-label="Sơ đồ; dùng phím cộng, trừ để thu phóng và phím mũi tên để di chuyển">
  <img id="ntMapImage" data-src="<?= e(BASE_URL . 'assets/noitru-campus-map.svg') ?>" alt="Sơ đồ khu nội trú học sinh: nhà A học sinh nam, nhà B học sinh nữ, các phòng và cổng ra vào" draggable="false">
  <span id="ntMapMessage">Đang tải sơ đồ…</span>
 </div>
</dialog>
<style>
.nt-map-open{width:100%;margin-top:18px;border:1px solid #edbad2;border-radius:16px;padding:18px;display:flex;gap:14px;align-items:center;text-align:left;color:#941953;background:linear-gradient(120deg,#fff0f7,#f1f7ff);font:inherit;font-weight:700;cursor:pointer}.nt-map-open>i:first-child{font-size:28px}.nt-map-open span{flex:1}.nt-map-open small{display:block;font-weight:400;font-size:12px;margin-top:4px;color:#64748b}.nt-map-open:hover{border-color:#ac2265;box-shadow:0 6px 18px #94195315}
.nt-map-dialog{width:min(1200px,96vw);height:min(850px,92dvh);max-width:96vw;max-height:92dvh;margin:auto;padding:0;border:0;border-radius:18px;overflow:hidden;background:#f1f5f9;color:#17243b;box-shadow:0 24px 90px #0006}.nt-map-dialog[open]{display:flex;flex-direction:column}.nt-map-dialog::backdrop{background:#0f172ac4;backdrop-filter:blur(4px)}.nt-map-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;background:#fff;border-bottom:1px solid #e2e8f0}.nt-map-head strong{font-size:16px}.nt-map-head button,.nt-map-tools button{border:1px solid #d4dce8;border-radius:9px;background:#fff;color:#17243b;min-width:40px;min-height:40px;cursor:pointer;font:inherit}.nt-map-head button:hover,.nt-map-tools button:hover{background:#fce7f3}.nt-map-tools{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:8px 14px;background:#fff}.nt-map-tools output{min-width:54px;text-align:center;font-size:13px}.nt-map-tools small{color:#64748b;font-size:12px;margin-left:auto}.nt-map-viewport{position:relative;flex:1;min-height:0;overflow:hidden;touch-action:none;cursor:grab;background:radial-gradient(ellipse at center,#fff,#dfe7f1);outline-offset:-3px}.nt-map-viewport:active{cursor:grabbing}.nt-map-viewport img{position:absolute;left:0;top:0;max-width:none!important;max-height:none!important;transform-origin:0 0;user-select:none;will-change:transform}.nt-map-viewport>span{position:absolute;inset:0;display:grid;place-items:center;color:#64748b;pointer-events:none}.nt-map-viewport>span[hidden]{display:none}@media(max-width:600px){.nt-map-dialog{width:96vw;height:90dvh}.nt-map-head{padding:10px}.nt-map-head strong{font-size:14px}.nt-map-tools{padding:8px}.nt-map-tools small{width:100%;margin:0;font-size:11px}.nt-map-open{padding:14px;font-size:14px}}
</style>
<script>
(()=>{
 const open=document.getElementById('ntMapOpen'),dialog=document.getElementById('ntMapDialog'),viewport=document.getElementById('ntMapViewport'),img=document.getElementById('ntMapImage'),output=document.getElementById('ntMapScale'),message=document.getElementById('ntMapMessage');
 let scale=1,fitScale=1,x=0,y=0,ready=false,previousOverflow='',points=new Map(),gesture=null;
 const bounds=()=>{const w=img.naturalWidth*scale,h=img.naturalHeight*scale;x=w<=viewport.clientWidth?(viewport.clientWidth-w)/2:Math.max(viewport.clientWidth-w,Math.min(0,x));y=h<=viewport.clientHeight?(viewport.clientHeight-h)/2:Math.max(viewport.clientHeight-h,Math.min(0,y));};
 const draw=()=>{if(!ready)return;bounds();img.style.transform='translate('+x+'px,'+y+'px) scale('+scale+')';output.value=Math.round(scale/fitScale*100)+'%';};
 const fit=()=>{if(!ready||!dialog.open)return;fitScale=Math.min(viewport.clientWidth/img.naturalWidth,viewport.clientHeight/img.naturalHeight);scale=fitScale;x=0;y=0;draw();};
 const zoom=(factor,px=viewport.clientWidth/2,py=viewport.clientHeight/2)=>{if(!ready)return;const next=Math.max(fitScale,Math.min(fitScale*6,scale*factor)),ratio=next/scale;x=px-(px-x)*ratio;y=py-(py-y)*ratio;scale=next;draw();};
 const local=e=>{const r=viewport.getBoundingClientRect();return{x:e.clientX-r.left,y:e.clientY-r.top};};
 const snapshot=()=>{const p=[...points.values()];gesture=p.length>=2?{distance:Math.hypot(p[1].x-p[0].x,p[1].y-p[0].y),cx:(p[0].x+p[1].x)/2,cy:(p[0].y+p[1].y)/2}:p.length?{x:p[0].x,y:p[0].y}:null;};
 open.addEventListener('click',()=>{if(dialog.open)return;previousOverflow=document.body.style.overflow;dialog.showModal();document.body.style.overflow='hidden';if(!img.getAttribute('src'))img.src=img.dataset.src;requestAnimationFrame(()=>{fit();viewport.focus();});});
 document.getElementById('ntMapClose').addEventListener('click',()=>dialog.close());
 dialog.addEventListener('close',()=>{document.body.style.overflow=previousOverflow;points.clear();gesture=null;open.focus();});
 dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});
 img.addEventListener('load',()=>{ready=true;message.hidden=true;fit();});
 img.addEventListener('error',()=>{ready=false;message.hidden=false;message.textContent='Không tải được sơ đồ. Hãy tải lại trang để thử lại.';});
 document.getElementById('ntMapPlus').addEventListener('click',()=>zoom(1.25));
 document.getElementById('ntMapMinus').addEventListener('click',()=>zoom(1/1.25));
 document.getElementById('ntMapFit').addEventListener('click',fit);
 viewport.addEventListener('wheel',e=>{e.preventDefault();const p=local(e);zoom(Math.exp(-e.deltaY*.002),p.x,p.y);},{passive:false});
 viewport.addEventListener('pointerdown',e=>{if(e.pointerType==='mouse'&&e.button!==0)return;viewport.setPointerCapture(e.pointerId);points.set(e.pointerId,local(e));snapshot();});
 viewport.addEventListener('pointermove',e=>{if(!points.has(e.pointerId))return;points.set(e.pointerId,local(e));const p=[...points.values()];if(p.length>=2&&gesture?.distance){const d=Math.hypot(p[1].x-p[0].x,p[1].y-p[0].y),cx=(p[0].x+p[1].x)/2,cy=(p[0].y+p[1].y)/2;zoom(d/Math.max(gesture.distance,1),gesture.cx,gesture.cy);x+=cx-gesture.cx;y+=cy-gesture.cy;}else if(p.length===1&&gesture){x+=p[0].x-gesture.x;y+=p[0].y-gesture.y;}draw();snapshot();});
 const release=e=>{points.delete(e.pointerId);snapshot();};['pointerup','pointercancel','lostpointercapture'].forEach(type=>viewport.addEventListener(type,release));
 viewport.addEventListener('keydown',e=>{if(['+','=','-','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','0'].includes(e.key)){e.preventDefault();if(e.key==='+'||e.key==='=')zoom(1.25);else if(e.key==='-')zoom(1/1.25);else if(e.key==='0')fit();else{x+=e.key==='ArrowLeft'?40:e.key==='ArrowRight'?-40:0;y+=e.key==='ArrowUp'?40:e.key==='ArrowDown'?-40:0;draw();}}});
 new ResizeObserver(()=>{if(dialog.open)fit();}).observe(viewport);
})();
</script>
