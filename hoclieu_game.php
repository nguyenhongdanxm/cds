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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{margin:0;height:100%;overflow:hidden;font-family:"Segoe UI",system-ui,sans-serif;color:#fff}
body{background:radial-gradient(circle at 20% 10%,#3a1a6b 0%,transparent 42%),radial-gradient(circle at 90% 80%,#123a7a 0%,transparent 40%),linear-gradient(160deg,#07061a,#140b2e 45%,#07111f)}
.app{height:100%;display:grid;grid-template-rows:auto 1fr;padding:10px 14px 12px;gap:8px}
.top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.brand{font-weight:800;font-size:clamp(16px,2.2vw,26px)}
.brand small{display:block;font-weight:600;opacity:.7;font-size:.7em}
.back{color:#ffd98a;text-decoration:none;font-weight:700}
.modes{display:flex;gap:6px;flex-wrap:wrap}
.modes button{border:0;border-radius:999px;padding:8px 12px;font-weight:800;cursor:pointer;background:rgba(255,255,255,.1);color:#fff}
.modes button.active{background:linear-gradient(90deg,#ffd36a,#ff8a3d);color:#3a1a00}
.stage{display:grid;grid-template-columns:minmax(280px,1fr) 300px;gap:12px;min-height:0}
@media(max-width:860px){.stage{grid-template-columns:1fr}}
.arena{position:relative;display:flex;align-items:center;justify-content:center;min-height:0}
.glow{position:absolute;width:min(70vmin,540px);height:min(70vmin,540px);border-radius:50%;background:radial-gradient(circle,#ffd36a55,transparent 68%);filter:blur(8px);animation:pulse 1.8s ease-in-out infinite}
.arena.spinning .glow{background:radial-gradient(circle,#ffde7aaa,transparent 62%);animation:pulse .35s ease-in-out infinite}
.arena.spinning .wheel-wrap{filter:drop-shadow(0 0 28px #ffd36a88)}
.arena.spinning .pointer{animation:nudge .08s linear infinite}
@keyframes pulse{50%{transform:scale(1.06);opacity:.7}}
@keyframes nudge{50%{transform:translateX(-50%) scaleY(1.15)}}
.wheel-wrap{position:relative;width:min(70vmin,540px);height:min(70vmin,540px)}
#fx{position:absolute;inset:-18%;width:136%;height:136%;left:-18%;top:-18%;pointer-events:none;z-index:3}
canvas#wheel{width:100%;height:100%;filter:drop-shadow(0 16px 28px #0008)}
.pointer{position:absolute;left:50%;top:-6px;transform:translateX(-50%);border-left:16px solid transparent;border-right:16px solid transparent;border-top:30px solid #ffe38a;filter:drop-shadow(0 6px 8px #0008);z-index:4}
.hub{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:86px;height:86px;border-radius:50%;border:6px solid #fff;background:radial-gradient(circle at 30% 30%,#fff,#ffd36a 45%,#c2410c);box-shadow:0 0 0 10px #f59e0b55;z-index:5;display:grid;place-items:center;font-weight:900;color:#7c2d12}
.spin{position:absolute;left:50%;bottom:6px;transform:translateX(-50%);z-index:6;border:0;border-radius:999px;padding:12px 28px;font-weight:900;font-size:18px;color:#3a1a00;background:linear-gradient(180deg,#ffe38a,#f59e0b);box-shadow:0 10px 22px #f59e0b66;cursor:pointer}
.spin:disabled{opacity:.55}
.panel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:12px;overflow:auto}
.panel h2{margin:0 0 8px;font-size:15px}
select,input{width:100%;border:0;border-radius:10px;padding:8px 10px}
.item-row{display:flex;gap:6px;margin-bottom:6px}
.item-row button,.add{border:0;border-radius:10px;padding:8px;cursor:pointer}
.item-row button{background:#e11d48;color:#fff}
.add{width:100%;margin-top:6px;background:#fff;color:#111;font-weight:800}
.hint{opacity:.75;font-size:12px;margin-top:8px}
.overlay{position:fixed;inset:0;display:none;place-items:center;background:rgba(5,4,16,.72);z-index:20}
.overlay.show{display:grid}
.win{width:min(92vw,560px);text-align:center;background:linear-gradient(180deg,#fff7d6,#ffd36a);color:#7c2d12;border-radius:28px;padding:28px 20px;box-shadow:0 20px 80px #f59e0b88;animation:pop .35s cubic-bezier(.2,1.4,.3,1)}
@keyframes pop{from{transform:scale(.4);opacity:0}to{transform:scale(1);opacity:1}}
.win .tag{font-size:14px;font-weight:800;opacity:.75}
.win .big{font-size:clamp(28px,6vw,52px);font-weight:900;line-height:1.15;margin:8px 0 16px}
.win button{border:0;border-radius:999px;padding:10px 18px;font-weight:800;background:#7c2d12;color:#fff;cursor:pointer}
.confetti{position:fixed;inset:0;pointer-events:none;z-index:19}
</style>
</head>
<body>
<div class="app">
  <div class="top">
    <div class="brand">Vòng quay may mắn<small><?= htmlspecialchars($school) ?></small></div>
    <div class="modes">
      <button type="button" class="active" data-mode="student">Gọi học sinh</button>
      <button type="button" data-mode="prize">Phần thưởng</button>
      <button type="button" data-mode="task">Nhiệm vụ</button>
    </div>
    <a class="back" href="<?= htmlspecialchars($base) ?>hoclieu.php?tab=games">Về học liệu</a>
  </div>
  <div class="stage">
    <div class="arena" id="arena">
      <div class="glow"></div>
      <div class="wheel-wrap">
        <canvas id="fx" width="900" height="900"></canvas>
        <div class="pointer"></div>
        <canvas id="wheel" width="720" height="720"></canvas>
        <div class="hub">GO</div>
        <button class="spin" id="spinBtn" type="button">QUAY</button>
      </div>
    </div>
    <aside class="panel">
      <div id="box-student">
        <h2>Chọn lớp lấy tên học sinh</h2>
        <select id="classSelect"><option value="">Chọn lớp</option><?php foreach ($classNames as $name): ?><option><?= htmlspecialchars($name) ?></option><?php endforeach; ?></select>
        <p class="hint" id="studentHint">Tên lấy từ danh sách lớp trong CSDL.</p>
      </div>
      <div id="box-prize" hidden>
        <h2>Phần thưởng giáo viên cài</h2>
        <div id="prizeItems"></div>
        <button class="add" id="addPrize" type="button">Thêm phần thưởng</button>
      </div>
      <div id="box-task" hidden>
        <h2>Câu hỏi / nhiệm vụ</h2>
        <div id="taskItems"></div>
        <button class="add" id="addTask" type="button">Thêm nhiệm vụ</button>
      </div>
    </aside>
  </div>
</div>
<canvas class="confetti" id="confetti"></canvas>
<div class="overlay" id="overlay">
  <div class="win">
    <div class="tag" id="winTag">Kết quả</div>
    <div class="big" id="winText">—</div>
    <button type="button" id="closeWin">Quay tiếp</button>
  </div>
</div>
<script>
const studentsByClass = <?= json_encode($studentsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const canvas=document.getElementById('wheel'), ctx=canvas.getContext('2d');
const colors=['#ef4444','#f59e0b','#10b981','#3b82f6','#a855f7','#f97316','#14b8a6','#ec4899','#84cc16','#06b6d4'];
let mode='student';
let prizes=JSON.parse(localStorage.getItem('cds_wheel_prizes')||'null')||['+1 điểm','Hát 1 bài','May mắn','Chọn bạn','Khen trước lớp','Ngôi sao vàng'];
let tasks=JSON.parse(localStorage.getItem('cds_wheel_tasks')||'null')||['Nêu ý chính','Đặt câu hỏi','Tóm tắt 30 giây','Viết ví dụ','Giải thích từ khó','Mời bạn trả lời'];
let items=[], angle=0, spinning=false, speed=0, audioCtx=null, lastTick=-1, musicTimer=null, sparks=[];
const fx=document.getElementById('fx'), fxctx=fx.getContext('2d');
function ensureAudio(){ if(!audioCtx) audioCtx=new (window.AudioContext||window.webkitAudioContext)(); if(audioCtx.state==='suspended') audioCtx.resume(); }
function beep(freq,dur,type='square',gain=0.05){ if(!audioCtx) return; const o=audioCtx.createOscillator(), g=audioCtx.createGain(); o.type=type; o.frequency.value=freq; g.gain.value=gain; o.connect(g); g.connect(audioCtx.destination); o.start(); g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime+dur); o.stop(audioCtx.currentTime+dur); }
function tickHit(){
  const rate=Math.min(1, speed/0.45);
  beep(980+rate*520, 0.035+rate*0.02, 'square', 0.045+rate*0.03);
  beep(180, 0.02, 'triangle', 0.03);
}
function playBar(step){
  if(!audioCtx) return;
  const t=audioCtx.currentTime;
  const kick=audioCtx.createOscillator(), kg=audioCtx.createGain();
  kick.frequency.setValueAtTime(140,t); kick.frequency.exponentialRampToValueAtTime(40,t+0.12);
  kg.gain.setValueAtTime(0.12,t); kg.gain.exponentialRampToValueAtTime(0.0001,t+0.14);
  kick.connect(kg); kg.connect(audioCtx.destination); kick.start(t); kick.stop(t+0.15);
  if(step%2===1){
    const n=audioCtx.createBuffer(1, 2200, audioCtx.sampleRate); const d=n.getChannelData(0);
    for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1)*Math.pow(1-i/d.length,4);
    const src=audioCtx.createBufferSource(), g=audioCtx.createGain(); src.buffer=n; g.gain.value=0.07; src.connect(g); g.connect(audioCtx.destination); src.start();
  }
  const notes=[261.6,329.6,392,523.3,392,329.6,349.2,440];
  const f=notes[step%notes.length];
  beep(f,.16,'triangle',0.05); beep(f*2,.08,'square',0.015);
}
function startMusic(){ stopMusic(); let step=0; playBar(step); musicTimer=setInterval(()=>{ if(!spinning){ stopMusic(); return; } playBar(++step); }, 220); }
function stopMusic(){ if(musicTimer){ clearInterval(musicTimer); musicTimer=null; } }
function fanfare(){ stopMusic(); [523,659,784,1046,784,1318].forEach((f,i)=>setTimeout(()=>beep(f,.2,'triangle',.09), i*80)); }
function emitSparks(){
  const n=Math.max(items.length,1);
  const burst=6+Math.floor(Math.min(12, speed*28));
  for(let i=0;i<burst;i++){
    const a=-Math.PI/2+(Math.random()-.5)*0.35;
    sparks.push({x:450+Math.cos(a)*310,y:80+Math.sin(a)*20,vx:(Math.random()-.5)*6,vy:2+Math.random()*6,life:1,c:colors[winnerIndex()%colors.length]});
  }
}
function drawFx(){
  fxctx.clearRect(0,0,900,900);
  if(spinning){
    fxctx.save(); fxctx.translate(450,450);
    fxctx.strokeStyle='rgba(255,211,106,'+(0.25+speed)+')'; fxctx.lineWidth=10;
    fxctx.beginPath(); fxctx.arc(0,0,360+Math.sin(Date.now()/80)*8,0,Math.PI*2); fxctx.stroke();
    for(let i=0;i<18;i++){
      const a=Date.now()/180+i; fxctx.fillStyle='rgba(255,255,255,.35)';
      fxctx.beginPath(); fxctx.arc(Math.cos(a)*370, Math.sin(a)*370, 3+speed*8, 0, Math.PI*2); fxctx.fill();
    }
    fxctx.restore();
  }
  sparks=sparks.filter(s=>s.life>0);
  sparks.forEach(s=>{ s.x+=s.vx; s.y+=s.vy; s.life-=0.04; fxctx.globalAlpha=s.life; fxctx.fillStyle=s.c; fxctx.fillRect(s.x,s.y,5,5); });
  fxctx.globalAlpha=1;
}
function currentItems(){ if(mode==='student') return (studentsByClass[document.getElementById('classSelect').value]||[]).slice(); return mode==='prize'?prizes.slice():tasks.slice(); }
function renderEditor(id,list,key){ const box=document.getElementById(id); box.innerHTML=''; list.forEach((text,i)=>{ const row=document.createElement('div'); row.className='item-row'; row.innerHTML='<input value="'+String(text).replace(/"/g,'&quot;')+'"><button type="button">Xóa</button>'; row.querySelector('input').oninput=e=>{list[i]=e.target.value;localStorage.setItem(key,JSON.stringify(list));draw();}; row.querySelector('button').onclick=()=>{if(list.length<3)return;list.splice(i,1);localStorage.setItem(key,JSON.stringify(list));renderEditor(id,list,key);draw();}; box.appendChild(row); }); }
function draw(){ items=currentItems(); const cx=360,cy=360,r=330,n=Math.max(items.length,1),arc=Math.PI*2/n; ctx.clearRect(0,0,720,720); ctx.save(); ctx.translate(cx,cy); ctx.rotate(angle); for(let i=0;i<n;i++){ ctx.beginPath(); ctx.moveTo(0,0); ctx.fillStyle=colors[i%colors.length]; ctx.arc(0,0,r,i*arc,(i+1)*arc); ctx.fill(); ctx.strokeStyle='rgba(255,255,255,.35)'; ctx.lineWidth=2; ctx.stroke(); ctx.save(); ctx.rotate(i*arc+arc/2); ctx.fillStyle='#fff'; ctx.font='bold 20px Segoe UI'; ctx.textAlign='right'; ctx.shadowColor='#0008'; ctx.shadowBlur=6; ctx.fillText(String(items[i]||'...').slice(0,18), r-24, 7); ctx.restore(); } ctx.beginPath(); ctx.lineWidth=18; ctx.strokeStyle='#fff7'; ctx.arc(0,0,r+2,0,Math.PI*2); ctx.stroke(); ctx.restore(); }
function winnerIndex(){ const n=Math.max(items.length,1), arc=Math.PI*2/n; const a=((Math.PI*1.5-angle)%(Math.PI*2)+Math.PI*2)%(Math.PI*2); return Math.floor(a/arc)%n; }
function burst(){ const c=document.getElementById('confetti'), x=c.getContext('2d'); c.width=innerWidth; c.height=innerHeight; const bits=Array.from({length:90},()=>({x:innerWidth/2,y:innerHeight/2,vx:(Math.random()-.5)*14,vy:Math.random()*-12-4,s:4+Math.random()*7,c:colors[Math.floor(Math.random()*colors.length)],a:1})); let t=0; (function step(){ x.clearRect(0,0,c.width,c.height); bits.forEach(b=>{b.x+=b.vx;b.y+=b.vy;b.vy+=.25;b.a-=.012;x.globalAlpha=Math.max(b.a,0);x.fillStyle=b.c;x.fillRect(b.x,b.y,b.s,b.s);}); if(++t<90) requestAnimationFrame(step); else x.clearRect(0,0,c.width,c.height); })(); }
function showWin(text){ document.getElementById('winTag').textContent=mode==='student'?'Mời lên bảng':(mode==='prize'?'Phần thưởng':'Nhiệm vụ'); document.getElementById('winText').textContent=text; document.getElementById('overlay').classList.add('show'); fanfare(); burst(); }
function tick(){ if(!spinning) return; angle+=speed; speed*=0.988; const idx=winnerIndex(); if(idx!==lastTick){ lastTick=idx; tickHit(); emitSparks(); } if(speed<0.0025){ spinning=false; stopMusic(); document.getElementById('arena').classList.remove('spinning'); document.getElementById('spinBtn').disabled=false; showWin(items[idx]||'Chưa có dữ liệu'); } draw(); drawFx(); requestAnimationFrame(tick); }
document.getElementById('spinBtn').onclick=function(){ ensureAudio(); items=currentItems(); if(spinning) return; if(items.length<2){ showWin(mode==='student'?'Hãy chọn lớp có từ 2 học sinh.':'Cần ít nhất 2 ô.'); return; } spinning=true; this.disabled=true; speed=0.48+Math.random()*0.22; lastTick=-1; document.getElementById('arena').classList.add('spinning'); startMusic(); requestAnimationFrame(tick); };
document.getElementById('classSelect').onchange=function(){ const n=(studentsByClass[this.value]||[]).length; document.getElementById('studentHint').textContent=this.value?('Lớp '+this.value+' · '+n+' học sinh'):'Tên lấy từ danh sách lớp trong CSDL.'; draw(); };
document.querySelectorAll('.modes button').forEach(btn=>btn.onclick=()=>{ mode=btn.dataset.mode; document.querySelectorAll('.modes button').forEach(x=>x.classList.toggle('active',x===btn)); ['student','prize','task'].forEach(m=>document.getElementById('box-'+m).hidden=m!==mode); draw(); });
document.getElementById('addPrize').onclick=()=>{prizes.push('Phần thưởng mới');localStorage.setItem('cds_wheel_prizes',JSON.stringify(prizes));renderEditor('prizeItems',prizes,'cds_wheel_prizes');draw();};
document.getElementById('addTask').onclick=()=>{tasks.push('Nhiệm vụ mới');localStorage.setItem('cds_wheel_tasks',JSON.stringify(tasks));renderEditor('taskItems',tasks,'cds_wheel_tasks');draw();};
document.getElementById('closeWin').onclick=()=>document.getElementById('overlay').classList.remove('show');
renderEditor('prizeItems',prizes,'cds_wheel_prizes');
renderEditor('taskItems',tasks,'cds_wheel_tasks');
draw();
</script>
</body>
</html>
