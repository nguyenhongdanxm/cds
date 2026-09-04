<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!function_exists('csdl_students_all')) {
    $csdl = __DIR__ . '/includes/csdl_store.php';
    if (is_file($csdl)) require_once $csdl;
}
$classes = function_exists('csdl_classes_all') ? csdl_classes_all() : [];
$classNames = [];
foreach ($classes as $c) {
    $name = trim((string)($c['name'] ?? ''));
    if ($name !== '' && (!array_key_exists('active', $c) || !empty($c['active']))) $classNames[] = $name;
}
sort($classNames, SORT_NATURAL);
$studentsByClass = [];
if (function_exists('csdl_students_all')) {
    $classMap = [];
    foreach ($classes as $c) $classMap[(string)($c['id'] ?? '')] = trim((string)($c['name'] ?? ''));
    foreach (csdl_students_all() as $stu) {
        if (!is_array($stu)) continue;
        if (array_key_exists('active', $stu) && empty($stu['active'])) continue;
        $name = trim((string)($stu['name'] ?? $stu['ho_ten'] ?? ''));
        if ($name === '') continue;
        $cls = trim((string)($stu['class_name'] ?? $stu['class'] ?? $stu['lop'] ?? ''));
        if ($cls === '' && !empty($stu['class_id'])) $cls = $classMap[(string)$stu['class_id']] ?? '';
        if ($cls === '') continue;
        $studentsByClass[$cls][] = $name;
    }
    foreach ($studentsByClass as &$names) {
        $names = array_values(array_unique($names));
        sort($names, SORT_NATURAL);
    }
    unset($names);
}
$school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'CDS';
$base = defined('BASE_URL') ? BASE_URL : '/';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Vòng quay may mắn – <?= htmlspecialchars($school) ?></title>
<style>
*{box-sizing:border-box}
html,body{margin:0;height:100%;overflow:hidden;font-family:"Segoe UI",system-ui,sans-serif;color:#fff}
body{background:radial-gradient(circle at 50% 8%,#263b73 0,#0b0720 52%,#05030f 100%);letter-spacing:.01em}
.app{height:100%;display:grid;grid-template-rows:auto 1fr 28px;min-height:0}
.credit{height:28px;display:flex;align-items:center;justify-content:center;font-size:11px;opacity:.72;letter-spacing:.2px;background:#0006}
.bar{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#120a2ccc;backdrop-filter:blur(8px);z-index:8}
.bar b{white-space:nowrap;font-size:clamp(17px,2.2vw,26px);letter-spacing:.04em;text-shadow:0 2px 8px #0008}
.bar a{color:#ffd98a;text-decoration:none;font-weight:700;margin-left:auto}
.modes button,.opt,.bar select{border:0;border-radius:999px;padding:7px 11px;font-weight:800;cursor:pointer}
.modes button{background:#ffffff1a;color:#fff}
.modes button.on{background:linear-gradient(90deg,#ffd36a,#ff8a3d);color:#3a1a00}
.bar select{max-width:140px}
.opt{background:#ffffff14;color:#fff}
.opt.on{background:#22c55e;color:#052e16}
.stage{position:relative;min-height:0;display:grid;place-items:center;background:radial-gradient(circle,#1e3a6d55 0,#08051a33 58%,transparent 72%);overflow:hidden}.show-title{position:absolute;top:3px;left:50%;transform:translateX(-50%);font-size:clamp(18px,3vw,34px);font-weight:950;letter-spacing:.08em;color:#ffe38a;text-shadow:0 0 10px #ff9f1c,0 3px 0 #7c2d12;z-index:4;white-space:nowrap}.show-title span{font-size:.48em;color:#fff;letter-spacing:.18em;margin-left:10px}.live-badge{position:absolute;top:18px;right:18px;border:1px solid #ffdc8a;border-radius:999px;padding:6px 12px;color:#ffe38a;font-size:11px;font-weight:900;letter-spacing:.12em;z-index:4}.live-badge.live{background:#dc2626;color:#fff;border-color:#fecaca;box-shadow:0 0 16px #ef4444}.credit{height:32px;font-size:11px}
canvas{display:block;filter:drop-shadow(0 0 18px #ffd36a88)}
#wheel{width:min(96vmin,100vw,100vh - 58px);height:min(96vmin,100vw,100vh - 58px);max-width:100%;max-height:calc(100vh - 58px)}
.center{position:absolute;left:50%;top:50%;z-index:12;transform:translate(-50%,-50%);width:min(18vmin,120px);height:min(18vmin,120px);border:6px solid #fff;border-radius:50%;background:radial-gradient(circle at 30% 28%,#fff,#ffd36a 46%,#c2410c);box-shadow:0 0 0 8px #f59e0b55,0 0 28px #ffd36a99,0 8px 24px #0008;font-weight:900;font-size:clamp(16px,3.2vmin,28px);color:#7c2d12;cursor:pointer;z-index:6}
.center:disabled{opacity:.6}
.pointer{position:absolute;left:50%;top:calc(50% - min(48vmin,50vh - 32px) + 38px);transform:translate(-50%,-8px);width:0;height:0;border-left:14px solid transparent;border-right:14px solid transparent;border-top:26px solid #ffe38a;filter:drop-shadow(0 4px 6px #0008);z-index:7;pointer-events:none}
.drawer{position:absolute;right:10px;top:10px;width:min(280px,86vw);background:#120a2ce8;border:1px solid #ffffff22;border-radius:16px;padding:12px;display:none;z-index:9}
.drawer.show{display:block}
.drawer h3{margin:0 0 8px;font-size:16px;color:#ffe9a8;text-shadow:0 1px 4px #000}
.item-row{display:flex;gap:6px;margin-bottom:6px}
.item-row input{flex:1;border:0;border-radius:8px;padding:7px}
.item-row button,.add{border:0;border-radius:8px;padding:7px;cursor:pointer}
.item-row button{background:#e11d48;color:#fff}
.add{width:100%;background:#fff;color:#111;font-weight:800}
.overlay{position:fixed;inset:0;display:none;place-items:center;background:#050410cc;z-index:20}
.overlay.show{display:grid}
.win{width:min(92vw,560px);text-align:center;background:linear-gradient(180deg,#fff7d6,#ffd36a);color:#7c2d12;border-radius:28px;padding:28px 18px;animation:pop .35s cubic-bezier(.2,1.4,.3,1)}
@keyframes pop{from{transform:scale(.45);opacity:0}}
.win .big{font-size:clamp(28px,7vw,56px);font-weight:900;margin:8px 0 14px}
.win button{border:0;border-radius:999px;padding:10px 18px;font-weight:800;background:#7c2d12;color:#fff}
.confetti{position:fixed;inset:0;pointer-events:none;z-index:19}
</style>
</head>
<body>
<div class="app">
  <div class="bar">
    <b>Vòng quay</b>
    <div class="modes">
      <button class="on" data-mode="student">Học sinh</button>
      <button data-mode="prize">Phần thưởng</button>
      <button data-mode="task">Nhiệm vụ</button>
    </div>
    <select id="classSelect"><option value="">Chọn lớp</option><?php foreach ($classNames as $name): ?><option><?= htmlspecialchars($name) ?></option><?php endforeach; ?></select>
    <button class="opt" id="optHide" type="button">Giữ ô đã quay</button>
    <button class="opt" id="optReset" type="button">Hiện lại tất cả</button>
    <button class="opt on" id="optMusic" type="button">Nhạc</button>
    <button class="opt" id="optEdit" type="button">Sửa nội dung</button>
    <a href="<?= htmlspecialchars($base) ?>hoclieu.php?tab=games">Học liệu</a>
  </div>
  <div class="stage" id="stage"><div class="show-title">VÒNG QUAY MAY MẮN <span>★ GAMESHOW ★</span></div><div class="live-badge" id="liveBadge">SẴN SÀNG</div>
    <canvas id="wheel" width="900" height="900"></canvas>
    <button class="center" id="spinBtn" type="button">QUAY</button>
    <div class="drawer" id="drawer">
      <div id="box-prize"><h3>Phần thưởng</h3><div id="prizeItems"></div><button class="add" id="addPrize" type="button">Thêm ô quay</button><button class="add" id="presetPrize" type="button">Nạp bộ phần thưởng mẫu</button></div>
      <div id="box-task" hidden><h3>Nhiệm vụ</h3><div id="taskItems"></div><button class="add" id="addTask" type="button">Thêm ô quay</button><button class="add" id="presetTask" type="button">Nạp bộ nhiệm vụ mẫu</button></div>
    </div>
  </div>
  <div class="credit"><span id="resultTicker">Chọn lớp hoặc nội dung rồi nhấn QUAY</span> · Hệ Sinh Thái Quản lý Nhà Trường - Thiết kế bởi thầy giáo Nguyễn Hồng Dân -</div>
</div>
<canvas class="confetti" id="confetti"></canvas>
<div class="overlay" id="overlay"><div class="win"><div id="winTag">Kết quả</div><div class="big" id="winText">—</div><button type="button" id="closeWin">Quay tiếp</button></div></div>
<script>
const studentsByClass = <?= json_encode($studentsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const canvas=document.getElementById('wheel'), ctx=canvas.getContext('2d');
const colors=['#ef4444','#f59e0b','#22c55e','#3b82f6','#a855f7','#f97316','#14b8a6','#ec4899','#84cc16','#06b6d4'];
let mode='student', hideUsed=false, musicOn=true, editing=false;
let prizes=JSON.parse(localStorage.getItem('cds_wheel_prizes')||'null')||['+1 điểm','Hát 1 bài','May mắn','Chọn bạn','Khen trước lớp','Ngôi sao vàng'];
const prizePreset=['+2 điểm','Được chọn bài hát','Tràng pháo tay','Huy hiệu chăm học','Quyền chọn bạn cùng nhóm','Quà bất ngờ','Miễn một câu hỏi','Ngôi sao tuần này'];
let tasks=JSON.parse(localStorage.getItem('cds_wheel_tasks')||'null')||['Nêu ý chính','Đặt câu hỏi','Tóm tắt 30 giây','Viết ví dụ','Giải thích từ khó','Mời bạn trả lời'];
const taskPreset=['Đọc và giải thích một câu','Nêu một ví dụ thực tế','Tóm tắt bài trong 30 giây','Đặt câu hỏi cho cả lớp','Vẽ sơ đồ tư duy nhanh','Giải một câu vận dụng','Chia sẻ điều em nhớ nhất','Mời một bạn cùng trả lời'];
let used={}, items=[], angle=0, spinning=false, speed=0, audioCtx=null, musicTimer=null, lastTick=-1, sparks=[];

function sourceItems(){
  if(mode==='student') return (studentsByClass[document.getElementById('classSelect').value]||[]).slice();
  return mode==='prize'?prizes.slice():tasks.slice();
}
function currentItems(){
  const all=sourceItems();
  return hideUsed ? all.filter(n=>!used[n]) : all;
}
function renderEditor(id,list,key){
  const box=document.getElementById(id); box.innerHTML='';
  list.forEach((text,i)=>{
    const row=document.createElement('div'); row.className='item-row';
    row.innerHTML='<input value="'+String(text).replace(/"/g,'&quot;')+'"><button type="button">Xóa</button>';
    row.querySelector('input').oninput=e=>{list[i]=e.target.value;localStorage.setItem(key,JSON.stringify(list));draw();};
    row.querySelector('button').onclick=()=>{if(list.length<3)return;list.splice(i,1);localStorage.setItem(key,JSON.stringify(list));renderEditor(id,list,key);draw();};
    box.appendChild(row);
  });
}
function sizeCanvas(){
  const box=document.getElementById('stage').getBoundingClientRect();
  const s=Math.floor(Math.max(280, Math.min(box.width, box.height)-8));
  canvas.style.width=s+'px'; canvas.style.height=s+'px';
}
function draw(){
  items=currentItems();
  const W=canvas.width, cx=W/2, r=W*0.46, n=Math.max(items.length,1), arc=Math.PI*2/n;
  ctx.clearRect(0,0,W,W);
  
  ctx.save(); ctx.translate(cx,cx); ctx.rotate(angle);
  for(let i=0;i<n;i++){
    ctx.beginPath(); ctx.moveTo(0,0); ctx.fillStyle=colors[i%colors.length];
    ctx.arc(0,0,r,i*arc,(i+1)*arc); ctx.fill();
    ctx.strokeStyle='#fff6'; ctx.lineWidth=2; ctx.stroke();
    ctx.save(); ctx.rotate(i*arc+arc/2); ctx.fillStyle='#fff';
    ctx.font='bold '+Math.max(11, Math.min(18, 520/n))+'px Segoe UI';
    ctx.textAlign='right'; ctx.shadowColor='#0008'; ctx.shadowBlur=4;
    ctx.fillText(String(items[i]||'...').slice(0,16), r-18, 5);
    ctx.beginPath(); ctx.fillStyle='#fff'; ctx.arc(r-2,0,4.5,0,Math.PI*2); ctx.fill();
    ctx.restore();
  }
  ctx.beginPath(); ctx.lineWidth=14; ctx.strokeStyle='#ffffffcc'; ctx.arc(0,0,r+4,0,Math.PI*2); ctx.stroke();
  if(spinning){
    ctx.rotate(-angle);
    const glow=ctx.createRadialGradient(0,0,r*0.7,0,0,r+28);
    glow.addColorStop(0,'rgba(255,211,106,0)'); glow.addColorStop(1,'rgba(255,211,106,.35)');
    ctx.fillStyle=glow; ctx.beginPath(); ctx.arc(0,0,r+28,0,Math.PI*2); ctx.fill();
    for(let i=0;i<16;i++){
      const a=Date.now()/160+i*(Math.PI*2/16);
      ctx.fillStyle='rgba(255,255,255,.55)';
      ctx.beginPath(); ctx.arc(Math.cos(a)*(r+18), Math.sin(a)*(r+18), 3.2, 0, Math.PI*2); ctx.fill();
    }
  }
  sparks=sparks.filter(s=>s.life>0);
  sparks.forEach(s=>{
    s.x+=s.vx; s.y+=s.vy; s.life-=0.05;
    ctx.globalAlpha=Math.max(s.life,0); ctx.fillStyle='#ffe38a';
    ctx.fillRect(s.x,s.y,4,4);
  });
  ctx.globalAlpha=1; ctx.restore();
  ctx.beginPath(); ctx.fillStyle='#ffe38a';
  ctx.moveTo(cx, 18); ctx.lineTo(cx-16, 48); ctx.lineTo(cx+16, 48); ctx.closePath(); ctx.fill();
}
function winnerIndex(){
  const n=Math.max(items.length,1), arc=Math.PI*2/n;
  const a=((Math.PI*1.5-angle)%(Math.PI*2)+Math.PI*2)%(Math.PI*2);
  return Math.floor(a/arc)%n;
}
function ensureAudio(){ if(!audioCtx) audioCtx=new (window.AudioContext||window.webkitAudioContext)(); if(audioCtx.state==='suspended') audioCtx.resume(); }
function tone(freq,dur,type,gain){
  if(!audioCtx) return;
  const o=audioCtx.createOscillator(), g=audioCtx.createGain();
  o.type=type; o.frequency.setValueAtTime(freq,audioCtx.currentTime);
  g.gain.setValueAtTime(gain,audioCtx.currentTime);
  g.gain.exponentialRampToValueAtTime(0.0001,audioCtx.currentTime+dur);
  o.connect(g); g.connect(audioCtx.destination); o.start(); o.stop(audioCtx.currentTime+dur);
}
function pegClick(){
  if(!audioCtx) return;
  const t=audioCtx.currentTime, rate=Math.min(1,speed/0.5);
  const buf=audioCtx.createBuffer(1, 900, audioCtx.sampleRate), d=buf.getChannelData(0);
  for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1)*Math.pow(1-i/d.length,7);
  const src=audioCtx.createBufferSource(), g=audioCtx.createGain();
  src.buffer=buf; g.gain.value=0.16+rate*0.08; src.connect(g); g.connect(audioCtx.destination); src.start();
  tone(2100+rate*700, 0.045, 'square', 0.09+rate*0.05);
  tone(320, 0.03, 'triangle', 0.05);
  tone(900+Math.min(700,speed*900),0.055,'sine',0.035);
}
function playShowBar(step){
  if(!audioCtx||!musicOn) return;
  const t=audioCtx.currentTime;
  const kick=audioCtx.createOscillator(), kg=audioCtx.createGain();
  kick.frequency.setValueAtTime(180,t); kick.frequency.exponentialRampToValueAtTime(48,t+0.09);
  kg.gain.setValueAtTime(0.16,t); kg.gain.exponentialRampToValueAtTime(0.0001,t+0.11);
  kick.connect(kg); kg.connect(audioCtx.destination); kick.start(t); kick.stop(t+0.12);
  const hat=audioCtx.createBuffer(1,700,audioCtx.sampleRate), hd=hat.getChannelData(0);
  for(let i=0;i<hd.length;i++) hd[i]=(Math.random()*2-1)*Math.pow(1-i/hd.length,4);
  const hs=audioCtx.createBufferSource(), hg=audioCtx.createGain(); hs.buffer=hat; hg.gain.value=0.05; hs.connect(hg); hg.connect(audioCtx.destination); hs.start();
  if(step%2){
    const n=audioCtx.createBuffer(1,1400,audioCtx.sampleRate), d=n.getChannelData(0);
    for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1)*Math.pow(1-i/d.length,2.4);
    const s=audioCtx.createBufferSource(), g=audioCtx.createGain(); s.buffer=n; g.gain.value=0.1; s.connect(g); g.connect(audioCtx.destination); s.start();
  }
  const runs=[392,523.3,659.3,783.9,1046.5,783.9,659.3,523.3,440,587.3,739.9,880,1174.7,880,739.9,587.3];
  tone(runs[step%runs.length], .12, 'triangle', 0.07);
  tone(runs[step%runs.length]*2, .07, 'square', 0.025);
  if(step%4===0){ tone(261.6,.2,'triangle',0.04); tone(329.6,.2,'triangle',0.03); tone(392,.2,'triangle',0.03); }
}
function startMusic(){ stopMusic(); if(!musicOn) return; let step=0; playShowBar(step); musicTimer=setInterval(()=>{ if(!spinning){stopMusic();return;} playShowBar(++step); }, 135); }
function stopMusic(){ if(musicTimer){ clearInterval(musicTimer); musicTimer=null; } }
function fanfare(){ stopMusic(); [523,659,784,1046,784,1318,1046].forEach((f,i)=>setTimeout(()=>tone(f,.18,'triangle',.1), i*85)); }
function burst(){
  const c=document.getElementById('confetti'), x=c.getContext('2d');
  c.width=innerWidth; c.height=innerHeight;
  const bits=Array.from({length:110},()=>({x:innerWidth/2,y:innerHeight/2,vx:(Math.random()-.5)*16,vy:Math.random()*-14-3,s:5+Math.random()*7,c:colors[Math.floor(Math.random()*colors.length)],a:1}));
  let t=0; (function step(){ x.clearRect(0,0,c.width,c.height); bits.forEach(b=>{b.x+=b.vx;b.y+=b.vy;b.vy+=.28;b.a-=.012;x.globalAlpha=Math.max(b.a,0);x.fillStyle=b.c;x.fillRect(b.x,b.y,b.s,b.s);}); if(++t<90) requestAnimationFrame(step); })();
}
function showWin(text){
  if(hideUsed && text) used[text]=1;
  document.getElementById('winTag').textContent=mode==='student'?'Mời lên bảng':(mode==='prize'?'Phần thưởng':'Nhiệm vụ');
  document.getElementById('winText').textContent=text;
  document.getElementById('overlay').classList.add('show');
  fanfare(); burst();
}
function tick(){
  if(!spinning) return;
  angle+=speed; speed*=0.987;
  const idx=winnerIndex();
  if(idx!==lastTick){
    lastTick=idx; pegClick();
    const W=canvas.width, r=W*0.46;
    sparks.push({x:0,y:-r-6,vx:(Math.random()-.5)*3,vy:1+Math.random()*2,life:1});
  }
  if(speed<0.0024){
    spinning=false; stopMusic();
    document.getElementById('spinBtn').disabled=false;
    showWin(items[idx]||'Chưa có dữ liệu');
  }
  draw(); requestAnimationFrame(tick);
}
document.getElementById('spinBtn').onclick=function(){
  ensureAudio(); items=currentItems();
  if(spinning) return;
  if(items.length<2){ showWin(mode==='student'?'Chọn lớp hoặc bật lại Lặp lại tên.':'Cần ít nhất 2 ô.'); return; }
  spinning=true; this.disabled=true; speed=0.5+Math.random()*0.18; lastTick=-1; document.getElementById('liveBadge').classList.add('live');document.getElementById('liveBadge').textContent='ĐANG QUAY'; startMusic(); requestAnimationFrame(tick);
};
document.querySelectorAll('.modes button').forEach(btn=>btn.onclick=()=>{
  mode=btn.dataset.mode;
  document.querySelectorAll('.modes button').forEach(x=>x.classList.toggle('on',x===btn));
  document.getElementById('box-prize').hidden=mode!=='prize';
  document.getElementById('box-task').hidden=mode!=='task';
  draw();
});
document.getElementById('classSelect').onchange=draw;
document.getElementById('optHide').onclick=function(){ hideUsed=!hideUsed; this.classList.toggle('on',hideUsed); this.textContent=hideUsed?'Ẩn ô đã quay':'Giữ ô đã quay'; draw(); };
document.getElementById('optReset').onclick=function(){ used={}; draw(); };
document.getElementById('optMusic').onclick=function(){ musicOn=!musicOn; this.classList.toggle('on',musicOn); if(!musicOn) stopMusic(); };
document.getElementById('optEdit').onclick=function(){ editing=!editing; this.classList.toggle('on',editing); document.getElementById('drawer').classList.toggle('show',editing); };
document.getElementById('addPrize').onclick=()=>{prizes.push('Phần thưởng mới');localStorage.setItem('cds_wheel_prizes',JSON.stringify(prizes));renderEditor('prizeItems',prizes,'cds_wheel_prizes');draw();};
document.getElementById('addTask').onclick=()=>{tasks.push('Nhiệm vụ mới');localStorage.setItem('cds_wheel_tasks',JSON.stringify(tasks));renderEditor('taskItems',tasks,'cds_wheel_tasks');draw();};
document.getElementById('presetPrize').onclick=()=>{prizes=prizePreset.slice();localStorage.setItem('cds_wheel_prizes',JSON.stringify(prizes));renderEditor('prizeItems',prizes,'cds_wheel_prizes');draw();};
document.getElementById('presetTask').onclick=()=>{tasks=taskPreset.slice();localStorage.setItem('cds_wheel_tasks',JSON.stringify(tasks));renderEditor('taskItems',tasks,'cds_wheel_tasks');draw();};
document.getElementById('closeWin').onclick=()=>{document.getElementById('overlay').classList.remove('show'); draw();};
window.addEventListener('resize',()=>{sizeCanvas(); draw();});
renderEditor('prizeItems',prizes,'cds_wheel_prizes');
renderEditor('taskItems',tasks,'cds_wheel_tasks');
sizeCanvas(); draw();
</script>
</body>
</html>
