<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!function_exists('csdl_students_all')) {
    $store = __DIR__ . '/includes/csdl_store.php';
    if (is_file($store)) require_once $store;
}
$classes = function_exists('csdl_classes_all') ? csdl_classes_all() : [];
$classMap = [];
$classNames = [];
foreach ($classes as $class) {
    if (!is_array($class) || (array_key_exists('active', $class) && empty($class['active']))) continue;
    $id = (string)($class['id'] ?? '');
    $name = trim((string)($class['name'] ?? ''));
    if ($name === '') continue;
    $classMap[$id] = $name;
    $classNames[] = $name;
}
$studentsByClass = [];
if (function_exists('csdl_students_all')) {
    foreach (csdl_students_all() as $student) {
        if (!is_array($student) || (array_key_exists('active', $student) && empty($student['active']))) continue;
        $name = trim((string)($student['name'] ?? $student['ho_ten'] ?? ''));
        $class = trim((string)($student['class_name'] ?? $student['class'] ?? $student['lop'] ?? ''));
        if ($class === '' && !empty($student['class_id'])) $class = $classMap[(string)$student['class_id']] ?? '';
        if ($name !== '' && $class !== '') $studentsByClass[$class][] = $name;
    }
    foreach ($studentsByClass as &$names) {
        $names = array_values(array_unique($names));
        sort($names, SORT_NATURAL);
    }
    unset($names);
}
sort($classNames, SORT_NATURAL);
$base = defined('BASE_URL') ? BASE_URL : '/';
$school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'CDS';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Quay số gọi tên – <?= htmlspecialchars($school) ?></title>
<style>
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:"Segoe UI",system-ui,sans-serif;color:#fff}body{background:#3b0b62;overflow-x:hidden}
body:before{content:"";position:fixed;inset:0;background:repeating-conic-gradient(from 0deg at 50% 46%,#ec489922 0 10deg,#fbbf2426 10deg 20deg);animation:turn 38s linear infinite;pointer-events:none}@keyframes turn{to{transform:rotate(360deg) scale(1.15)}}
.top{position:relative;z-index:5;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:11px 18px;background:#21053be8;border-bottom:2px solid #fbbf24}.brand{font-size:clamp(18px,2.4vw,29px);font-weight:1000;color:#ffe88a;text-shadow:0 3px #9a3412}.controls{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap}.controls select,.controls button,.controls a{border:1px solid #ffffff38;border-radius:11px;padding:9px 12px;background:#ffffff16;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.controls select{background:#fff;color:#1f2937;min-width:150px}.controls button.on{background:#22c55e;color:#052e16}
.page{position:relative;z-index:2;min-height:calc(100vh - 64px);display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:18px;padding:18px;max-width:1500px;margin:auto}.show{min-width:0;display:flex;flex-direction:column;align-items:center}.title{text-align:center;margin:2px 0 12px}.title small{display:block;color:#fbcfe8;font-weight:900;letter-spacing:.22em}.title h1{margin:2px;font-size:clamp(30px,5vw,66px);line-height:1;color:#fff5b8;text-shadow:0 4px #be185d,0 0 24px #facc15}
.machine{position:relative;width:min(900px,96%);padding:42px 46px 32px;border:10px solid #fbbf24;border-radius:42px;background:linear-gradient(150deg,#f43f5e,#be185d 60%,#831843);box-shadow:0 13px 0 #7c2d12,0 25px 55px #0008,inset 0 0 0 5px #fff4}.bulbs{position:absolute;inset:9px;border:4px dotted #fff;border-radius:25px;pointer-events:none;animation:bulb 1s steps(2) infinite}@keyframes bulb{50%{filter:drop-shadow(0 0 9px #fff);opacity:.5}}
.reels{display:flex;justify-content:center;gap:clamp(8px,2vw,24px);padding:24px;border:7px solid #7c2d12;border-radius:26px;background:linear-gradient(#fff,#e5e7eb);box-shadow:inset 0 6px 18px #0003}.reel{position:relative;width:clamp(86px,15vw,170px);height:clamp(125px,21vw,215px);display:grid;place-items:center;overflow:hidden;border:5px solid #f59e0b;border-radius:18px;background:linear-gradient(#fff7ed,#fff,#fde68a);color:#311047;font-size:clamp(70px,13vw,148px);font-weight:1000;font-variant-numeric:tabular-nums;text-shadow:0 4px #fbbf24;box-shadow:inset 0 0 22px #0002}.reel:before,.reel:after{content:"";position:absolute;left:0;right:0;height:22%;z-index:2}.reel:before{top:0;background:linear-gradient(#0003,transparent)}.reel:after{bottom:0;background:linear-gradient(transparent,#0003)}.reel.rolling span{animation:jitter .075s linear infinite}@keyframes jitter{50%{transform:translateY(-12%) scaleY(.82);filter:blur(2px)}}.reel.lock{animation:lock .32s ease}@keyframes lock{50%{transform:scale(1.09);box-shadow:0 0 30px #fff}}
.result{min-height:112px;margin:24px auto 5px;padding:15px 22px;border-radius:22px;background:#fff;color:#311047;text-align:center;box-shadow:0 8px 0 #7c2d12;width:min(690px,100%)}.result .number{font-weight:950;color:#be185d}.result .name{display:block;font-size:clamp(25px,4vw,46px);font-weight:1000;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.result .hint{color:#64748b;font-weight:700}
.spin{margin-top:22px;border:0;border-radius:999px;padding:16px 55px;background:linear-gradient(#fde047,#f59e0b);color:#4a1d00;font-size:clamp(22px,3vw,34px);font-weight:1000;cursor:pointer;box-shadow:0 9px 0 #b45309,0 15px 30px #0005;transition:.15s}.spin:hover{transform:translateY(-2px)}.spin:active{transform:translateY(6px);box-shadow:0 3px 0 #b45309}.spin:disabled{filter:grayscale(1);opacity:.6}.lever{position:absolute;right:-50px;top:36%;width:25px;height:135px;background:#fbbf24;border:5px solid #7c2d12;border-radius:20px;transform-origin:bottom}.lever:before{content:"";position:absolute;width:58px;height:58px;border-radius:50%;background:#ef4444;border:6px solid #fff;left:50%;top:-35px;transform:translateX(-50%);box-shadow:0 5px #991b1b}.machine.spinning .lever{animation:lever .65s ease}@keyframes lever{45%{transform:rotate(38deg)}}
.side{background:#21053bcc;border:1px solid #ffffff25;border-radius:22px;padding:15px;min-height:0;box-shadow:0 16px 35px #0004}.side h2{font-size:19px;margin:0 0 5px;color:#ffe88a}.side p{margin:0 0 12px;color:#e9d5ff;font-size:13px}.search{width:100%;border:0;border-radius:10px;padding:10px;margin-bottom:10px}.student-list{max-height:calc(100vh - 240px);overflow:auto;display:grid;gap:6px}.student{display:grid;grid-template-columns:42px 1fr;align-items:center;gap:8px;background:#ffffff12;border-radius:10px;padding:7px}.student b{display:grid;place-items:center;height:34px;border-radius:8px;background:#fbbf24;color:#4a1d00}.student.used{opacity:.42;text-decoration:line-through}.history{margin-top:15px;width:min(900px,96%);display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;color:#fdf2f8}.history span{background:#ffffff16;border-radius:999px;padding:6px 10px;font-weight:700}
.confetti{position:fixed;inset:0;z-index:20;pointer-events:none}.flash{animation:flash .45s ease 2}@keyframes flash{50%{filter:brightness(1.8)}}
@media(max-width:850px){.page{grid-template-columns:1fr}.side{order:2}.student-list{max-height:260px}.lever{display:none}.machine{padding:34px 22px 26px}.top{justify-content:center}.controls{margin:auto;justify-content:center}}
</style>
</head>
<body>
<header class="top"><div class="brand">🎲 QUAY SỐ GỌI TÊN</div><div class="controls">
<select id="classSelect"><option value="">Chọn lớp</option><?php foreach ($classNames as $name): ?><option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?></select>
<button id="exclude" type="button">Loại người đã trúng: Tắt</button><button id="sound" class="on" type="button">Âm thanh: Bật</button><button id="reset" type="button">Làm mới</button><a href="<?= htmlspecialchars($base) ?>hoclieu.php?tab=games">← Học liệu</a>
</div></header>
<main class="page">
<section class="show"><div class="title"><small>TRÒ CHƠI TRÊN LỚP</small><h1>QUAY SỐ GỌI TÊN</h1></div>
<div class="machine" id="machine"><div class="bulbs"></div><div class="lever"></div><div class="reels" id="reels"><div class="reel"><span>0</span></div><div class="reel"><span>0</span></div></div>
<div class="result" id="result"><span class="name">Chọn lớp để bắt đầu</span><span class="hint">Mỗi học sinh được gắn một số theo danh sách lớp</span></div></div>
<button class="spin" id="spin" type="button" disabled>QUAY SỐ!</button><div class="history" id="history"></div></section>
<aside class="side"><h2>Danh sách số của lớp</h2><p>Số được gắn ổn định theo thứ tự tên học sinh.</p><input class="search" id="search" type="search" placeholder="Tìm tên hoặc số..."><div class="student-list" id="studentList"><div class="student"><span>Chưa chọn lớp.</span></div></div></aside>
</main><canvas class="confetti" id="confetti"></canvas>
<script>
const studentsByClass=<?= json_encode($studentsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const classSelect=document.getElementById('classSelect'),reels=document.getElementById('reels'),spin=document.getElementById('spin'),result=document.getElementById('result'),machine=document.getElementById('machine');
let students=[],used=[],history=[],spinning=false,excludeUsed=false,soundOn=true,audioContext=null;
function pad(n){return String(n).padStart(Math.max(2,String(Math.max(students.length,1)).length),'0')}
function escapeHtml(v){const e=document.createElement('div');e.textContent=v;return e.innerHTML}
function makeReels(){const count=Math.max(2,String(Math.max(students.length,1)).length);reels.innerHTML='';for(let i=0;i<count;i++){const d=document.createElement('div');d.className='reel';d.innerHTML='<span>0</span>';reels.appendChild(d)}}
function renderList(){const q=document.getElementById('search').value.trim().toLocaleLowerCase('vi');document.getElementById('studentList').innerHTML=students.map((name,i)=>({name:name,no:pad(i+1),used:used.includes(i)})).filter(x=>!q||x.name.toLocaleLowerCase('vi').includes(q)||x.no.includes(q)).map(x=>'<div class="student '+(x.used?'used':'')+'"><b>'+x.no+'</b><span>'+escapeHtml(x.name)+'</span></div>').join('')||'<div class="student"><span>Không tìm thấy.</span></div>'}
function renderHistory(){document.getElementById('history').innerHTML=history.length?'<b>Đã gọi:</b>'+history.slice(-8).reverse().map(x=>'<span>'+x.no+' · '+escapeHtml(x.name)+'</span>').join(''):''}
function selectClass(){students=(studentsByClass[classSelect.value]||[]).slice();used=[];history=[];makeReels();renderList();renderHistory();spin.disabled=students.length<2;result.innerHTML=students.length?'<span class="name">'+students.length+' học sinh đã sẵn sàng</span><span class="hint">Nhấn QUAY SỐ để chọn ngẫu nhiên</span>':'<span class="name">Lớp chưa có đủ học sinh</span>'}
function pool(){const all=students.map((_,i)=>i);return excludeUsed?all.filter(i=>!used.includes(i)):all}
function beep(freq,duration,volume){if(!soundOn)return;try{audioContext=audioContext||new(window.AudioContext||window.webkitAudioContext)();const o=audioContext.createOscillator(),g=audioContext.createGain();o.type='square';o.frequency.value=freq;g.gain.setValueAtTime(volume,audioContext.currentTime);g.gain.exponentialRampToValueAtTime(.001,audioContext.currentTime+duration);o.connect(g).connect(audioContext.destination);o.start();o.stop(audioContext.currentTime+duration)}catch(e){}}
function burst(){const c=document.getElementById('confetti'),x=c.getContext('2d');c.width=innerWidth;c.height=innerHeight;const colors=['#facc15','#fb7185','#22d3ee','#a78bfa','#4ade80'],bits=Array.from({length:130},()=>({x:innerWidth/2,y:innerHeight*.46,vx:(Math.random()-.5)*18,vy:-4-Math.random()*12,s:4+Math.random()*8,c:colors[Math.floor(Math.random()*colors.length)],a:1}));let f=0;(function go(){x.clearRect(0,0,c.width,c.height);bits.forEach(b=>{b.x+=b.vx;b.y+=b.vy;b.vy+=.3;b.a-=.011;x.globalAlpha=Math.max(0,b.a);x.fillStyle=b.c;x.fillRect(b.x,b.y,b.s,b.s)});if(++f<100)requestAnimationFrame(go);else x.clearRect(0,0,c.width,c.height)})()}
function finish(index){const no=pad(index+1),name=students[index];used.push(index);history.push({no:no,name:name});result.innerHTML='<span class="number">SỐ '+no+'</span><span class="name">'+escapeHtml(name)+'</span><span class="hint">Học sinh được chọn ngẫu nhiên</span>';machine.classList.remove('spinning');machine.classList.add('flash');setTimeout(()=>machine.classList.remove('flash'),1000);spinning=false;spin.disabled=excludeUsed&&pool().length===0;renderList();renderHistory();[523,659,784,1047].forEach((f,i)=>setTimeout(()=>beep(f,.18,.1),i*100));burst()}
function roll(){if(spinning)return;const available=pool();if(!available.length){result.innerHTML='<span class="name">Đã gọi hết danh sách</span><span class="hint">Nhấn Làm mới hoặc tắt chế độ loại người đã trúng</span>';return}spinning=true;spin.disabled=true;machine.classList.add('spinning');result.innerHTML='<span class="name">Đang quay...</span><span class="hint">Các trống số đang tìm người may mắn</span>';const selected=available[Math.floor(Math.random()*available.length)],target=pad(selected+1),els=[...reels.querySelectorAll('.reel')];els.forEach(e=>e.classList.add('rolling'));const timer=setInterval(()=>{els.forEach(e=>{if(e.classList.contains('rolling'))e.querySelector('span').textContent=Math.floor(Math.random()*10)});beep(180+Math.random()*160,.035,.025)},70);els.forEach((e,i)=>setTimeout(()=>{e.classList.remove('rolling');e.classList.add('lock');e.querySelector('span').textContent=target[i];setTimeout(()=>e.classList.remove('lock'),350);if(i===els.length-1){clearInterval(timer);setTimeout(()=>finish(selected),430)}},1050+i*520))}
classSelect.onchange=selectClass;spin.onclick=roll;document.getElementById('search').oninput=renderList;
document.getElementById('exclude').onclick=function(){excludeUsed=!excludeUsed;this.classList.toggle('on',excludeUsed);this.textContent='Loại người đã trúng: '+(excludeUsed?'Bật':'Tắt');spin.disabled=students.length<2||(excludeUsed&&pool().length===0)};
document.getElementById('sound').onclick=function(){soundOn=!soundOn;this.classList.toggle('on',soundOn);this.textContent='Âm thanh: '+(soundOn?'Bật':'Tắt')};
document.getElementById('reset').onclick=()=>{used=[];history=[];renderList();renderHistory();spin.disabled=students.length<2;result.innerHTML='<span class="name">Đã làm mới lượt chơi</span><span class="hint">Tất cả học sinh có thể được chọn lại</span>'};
</script>
</body></html>
