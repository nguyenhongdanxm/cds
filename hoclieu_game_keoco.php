<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'CDS';
$base = defined('BASE_URL') ? BASE_URL : '/';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Kéo co kiến thức – <?= htmlspecialchars($school) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
<style>
*{box-sizing:border-box}
html,body{margin:0;height:100%;overflow:hidden;font-family:"Segoe UI",system-ui,sans-serif;background:#1b2230;color:#fff}
.app{height:100%;display:grid;grid-template-rows:56px 1fr 26px}
.top{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#121826}
.top h1{margin:0;font-size:18px;color:#ffd54a;white-space:nowrap}
.score{border-radius:999px;padding:6px 12px;font-weight:800;display:flex;align-items:center;gap:8px}
.blue{background:#1e3a8a}.red{background:#7f1d1d}
.timer{margin:0 auto;background:#0b1220;border-radius:14px;padding:6px 16px;font-size:22px;font-weight:900}
.btn{border:0;border-radius:10px;padding:7px 10px;font-weight:700;cursor:pointer;background:#334155;color:#fff}
.play{display:grid;grid-template-columns:1fr minmax(280px,42vw) 1fr;gap:10px;padding:10px;min-height:0}
.panel{border-radius:18px;padding:14px;display:grid;grid-template-rows:1fr auto;min-height:0}
.panel.b{background:#1d4ed8}.panel.r{background:#b91c1c}
.q{font-size:clamp(16px,2.1vw,28px);font-weight:800;text-align:center;display:grid;place-items:center;padding:8px}
.opts{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.opts button{min-height:78px;border:0;border-radius:16px;background:#fff;color:#111;font-weight:800;font-size:clamp(15px,1.7vw,22px);cursor:pointer}
.opts button.ok{background:#86efac}.opts button.bad{background:#fecaca}
.mid{background:#f4f6fb;border-radius:18px;position:relative;overflow:hidden;display:grid;place-items:center}
.field{width:100%;height:100%;position:relative}
.line{position:absolute;left:50%;top:8%;bottom:8%;border-left:3px dashed #22c55e;transform:translateX(-50%)}
.ropebox{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:92%;height:160px;display:flex;align-items:center;justify-content:center;transition:transform .45s cubic-bezier(.2,.8,.2,1)}
svg{width:100%;height:160px}
.setup{padding:18px;overflow:auto}
.setup .box{max-width:980px;margin:0 auto;background:#0f172a;border-radius:16px;padding:16px}
label{font-size:13px;opacity:.8}
input,textarea,select{width:100%;border:0;border-radius:10px;padding:8px;margin:4px 0 10px}
textarea{min-height:180px;font-family:Consolas,monospace}
.row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.credit{display:flex;align-items:center;justify-content:center;font-size:11px;opacity:.7;background:#0005}
.overlay{position:fixed;inset:0;display:none;place-items:center;background:#0008;z-index:9}
.overlay.show{display:grid}
.card{background:#fff;color:#111;border-radius:18px;padding:22px;text-align:center;min-width:280px}
</style>
</head>
<body>
<div class="app">
  <div class="top">
    <h1>Kéo Co Kiến Thức</h1>
    <div class="score blue" id="sBlue">Đội Xanh 0</div>
    <div class="timer" id="timer">02:00</div>
    <div class="score red" id="sRed">0 Đội Đỏ</div>
    <button class="btn" id="muteBtn" type="button">Tắt âm</button>
    <button class="btn" id="fullBtn" type="button">Toàn màn hình</button>
    <button class="btn" id="exitBtn" type="button">Thoát</button>
  </div>

  <div class="setup" id="setup">
    <div class="box">
      <h2 style="margin-top:0">Tạo trận kéo co</h2>
      <div class="row">
        <div><label>Đội xanh</label><input id="nBlue" value="Đội Xanh"></div>
        <div><label>Đội đỏ</label><input id="nRed" value="Đội Đỏ"></div>
        <div><label>Thời gian (phút)</label><input id="mins" type="number" min="1" value="3"></div>
      </div>
      <div class="row">
        <div><label>Cách chơi</label>
          <select id="mode">
            <option value="split">Mỗi đội một câu riêng</option>
            <option value="shared">Hai đội chung một câu</option>
          </select>
        </div>
        <div><label>Cách biệt để thắng</label><input id="winBy" type="number" min="2" value="5"></div>
        <div><label>Hiện lại câu đã dùng</label>
          <select id="loopQ"><option value="1">Có</option><option value="0">Không</option></select>
        </div>
      </div>
      <label>Ngân hàng câu hỏi (mỗi dòng: câu | A | B | C | D | đáp án 1-4)</label>
      <textarea id="bank">Thủ đô của Việt Nam là thành phố nào? | Hà Nội | Huế | TP. Hồ Chí Minh | Đà Nẵng | 1
Sông nào dài nhất Việt Nam? | Hồng | Mekong | Đà | Đồng Nai | 2
Phương trình 2x + 6 = 0 có nghiệm là? | x = 6 | x = 3 | x = -3 | x = -6 | 3
1 giờ có bao nhiêu phút? | 30 | 60 | 90 | 100 | 2
Hình nào có 3 cạnh? | Vuông | Tròn | Tam giác | Chữ nhật | 3
Năm 1945 gắn với sự kiện nào? | Đổi mới | Cách mạng tháng Tám | Điện Biên Phủ | Thống nhất | 2
Kết quả 7 x 8 là? | 54 | 56 | 64 | 48 | 2
Hành tinh chúng ta đang sống tên gì? | Sao Hỏa | Mặt Trăng | Trái Đất | Sao Kim | 3</textarea>
      <button class="btn" id="startBtn" type="button" style="background:#f59e0b;color:#111">Bắt đầu thi đấu</button>
    </div>
  </div>

  <div class="play" id="play" hidden>
    <section class="panel b">
      <div class="q" id="qBlue"></div>
      <div class="opts" id="oBlue"></div>
    </section>
    <section class="mid">
      <div class="field">
        <div class="line"></div>
        <div class="ropebox" id="rope">
          <svg viewBox="0 0 640 160" aria-hidden="true">
            <line x1="40" y1="80" x2="600" y2="80" stroke="#6b3f1f" stroke-width="10" stroke-linecap="round"/>
            <circle cx="320" cy="80" r="10" fill="#dc2626"/>
            <g fill="#1d4ed8">
              <ellipse cx="90" cy="118" rx="18" ry="8" fill="#1e293b" opacity=".2"/>
              <rect x="70" y="70" width="28" height="40" rx="8"/>
              <circle cx="84" cy="58" r="12" fill="#f8d7b0"/>
              <rect x="118" y="72" width="26" height="38" rx="8"/>
              <circle cx="131" cy="60" r="12" fill="#f8d7b0"/>
              <rect x="164" y="68" width="28" height="42" rx="8"/>
              <circle cx="178" cy="56" r="12" fill="#f8d7b0"/>
            </g>
            <g fill="#b91c1c">
              <rect x="430" y="68" width="28" height="42" rx="8"/>
              <circle cx="444" cy="56" r="12" fill="#f8d7b0"/>
              <rect x="476" y="72" width="26" height="38" rx="8"/>
              <circle cx="489" cy="60" r="12" fill="#f8d7b0"/>
              <rect x="520" y="70" width="28" height="40" rx="8"/>
              <circle cx="534" cy="58" r="12" fill="#f8d7b0"/>
            </g>
          </svg>
        </div>
      </div>
    </section>
    <section class="panel r">
      <div class="q" id="qRed"></div>
      <div class="opts" id="oRed"></div>
    </section>
  </div>
  <div class="credit">Hệ Sinh Thái Quản lý Nhà Trường - Thiết kế bởi thầy giáo Nguyễn Hồng Dân -</div>
</div>
<div class="overlay" id="overlay"><div class="card"><h2 id="winTitle">Kết thúc</h2><p id="winText"></p><button class="btn" id="again" type="button">Chơi lại</button></div></div>
<script>
const DEFAULT = document.getElementById('bank').value;
let bank=[], blue=0, red=0, tLeft=120, tick=null, mute=false, mode='split', winBy=5, loopQ=true;
let usedB=[], usedR=[], curB=null, curR=null, lockB=false, lockR=false, names={blue:'Đội Xanh',red:'Đội Đỏ'};
let audioCtx=null;
function sound(ok){
  if(mute) return;
  if(!audioCtx) audioCtx=new (window.AudioContext||window.webkitAudioContext)();
  if(audioCtx.state==='suspended') audioCtx.resume();
  const o=audioCtx.createOscillator(), g=audioCtx.createGain();
  o.type='triangle'; o.frequency.value=ok?660:180;
  g.gain.value=0.08; o.connect(g); g.connect(audioCtx.destination);
  o.start(); g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime+0.18); o.stop(audioCtx.currentTime+0.2);
}
function parseBank(text){
  return text.split(/\n+/).map(line=>{
    const p=line.split('|').map(s=>s.trim()).filter((s,i,a)=>i<a.length);
    if(p.length<6) return null;
    return {q:p[0], opts:p.slice(1,5), ans:Math.max(1,Math.min(4,parseInt(p[5],10)||1))-1};
  }).filter(Boolean);
}
function pick(side){
  const used=side==='b'?usedB:usedR;
  let pool=bank.map((_,i)=>i).filter(i=>!used.includes(i));
  if(!pool.length){ if(!loopQ) return null; if(side==='b') usedB=[]; else usedR=[]; pool=bank.map((_,i)=>i); }
  const i=pool[Math.floor(Math.random()*pool.length)];
  if(side==='b') usedB.push(i); else usedR.push(i);
  return bank[i];
}
function renderMath(el){
  if(window.renderMathInElement) renderMathInElement(el,{delimiters:[{left:'$',right:'$',display:false},{left:'\\(',right:'\\)',display:false}]});
}
function drawSide(side, item){
  const q=document.getElementById(side==='b'?'qBlue':'qRed');
  const box=document.getElementById(side==='b'?'oBlue':'oRed');
  if(!item){ q.textContent='Hết câu hỏi'; box.innerHTML=''; return; }
  q.textContent=item.q; renderMath(q);
  box.innerHTML='';
  item.opts.forEach((opt,idx)=>{
    const b=document.createElement('button');
    b.textContent=opt;
    b.onclick=()=>answer(side, idx, item, b, box);
    box.appendChild(b); renderMath(b);
  });
}
function shiftRope(){
  const diff=blue-red;
  document.getElementById('rope').style.transform='translate(calc(-50% + '+(diff*18)+'px), -50%)';
  document.getElementById('sBlue').textContent=names.blue+' '+blue;
  document.getElementById('sRed').textContent=red+' '+names.red;
  if(Math.abs(diff)>=winBy) endGame(diff>0?names.blue:names.red);
}
function nextShared(){
  const item=pick('b'); curB=curR=item; lockB=lockR=false;
  drawSide('b', item); drawSide('r', item);
}
function answer(side, idx, item, btn, box){
  if((side==='b'&&lockB)||(side==='r'&&lockR)||!item) return;
  const ok=idx===item.ans;
  sound(ok);
  [...box.children].forEach((el,i)=>{ el.disabled=true; if(i===item.ans) el.classList.add('ok'); });
  if(!ok) btn.classList.add('bad');
  if(ok){ if(side==='b') blue++; else red++; }
  shiftRope();
  if(document.getElementById('overlay').classList.contains('show')) return;
  if(mode==='shared'){
    lockB=lockR=true;
    setTimeout(nextShared, 700);
  } else {
    if(side==='b'){ lockB=true; setTimeout(()=>{ curB=pick('b'); lockB=false; drawSide('b',curB); }, 650); }
    else { lockR=true; setTimeout(()=>{ curR=pick('r'); lockR=false; drawSide('r',curR); }, 650); }
  }
}
function fmt(s){ return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); }
function endGame(winner){
  clearInterval(tick);
  document.getElementById('winTitle').textContent=winner? (winner+' thắng!') : 'Hết giờ';
  document.getElementById('winText').textContent=names.blue+' '+blue+'  –  '+red+' '+names.red;
  document.getElementById('overlay').classList.add('show');
}
function start(){
  bank=parseBank(document.getElementById('bank').value||DEFAULT);
  if(bank.length<4){ alert('Cần ít nhất 4 câu hỏi.'); return; }
  names.blue=document.getElementById('nBlue').value||'Đội Xanh';
  names.red=document.getElementById('nRed').value||'Đội Đỏ';
  mode=document.getElementById('mode').value;
  winBy=Math.max(2, parseInt(document.getElementById('winBy').value,10)||5);
  loopQ=document.getElementById('loopQ').value==='1';
  blue=red=0; usedB=[]; usedR=[]; tLeft=Math.max(1,parseInt(document.getElementById('mins').value,10)||3)*60;
  document.getElementById('setup').hidden=true;
  document.getElementById('play').hidden=false;
  document.getElementById('timer').textContent=fmt(tLeft);
  shiftRope();
  if(mode==='shared') nextShared();
  else { curB=pick('b'); curR=pick('r'); drawSide('b',curB); drawSide('r',curR); }
  clearInterval(tick);
  tick=setInterval(()=>{ tLeft--; document.getElementById('timer').textContent=fmt(Math.max(0,tLeft)); if(tLeft<=0) endGame(blue===red?null:(blue>red?names.blue:names.red)); },1000);
}
document.getElementById('startBtn').onclick=start;
document.getElementById('muteBtn').onclick=function(){ mute=!mute; this.textContent=mute?'Bật âm':'Tắt âm'; };
document.getElementById('fullBtn').onclick=()=>{ if(!document.fullscreenElement) document.documentElement.requestFullscreen(); else document.exitFullscreen(); };
document.getElementById('exitBtn').onclick=()=>{ clearInterval(tick); location.href='<?= htmlspecialchars($base) ?>hoclieu.php?tab=games'; };
document.getElementById('again').onclick=()=>{ document.getElementById('overlay').classList.remove('show'); document.getElementById('play').hidden=true; document.getElementById('setup').hidden=false; };
</script>
</body>
</html>
