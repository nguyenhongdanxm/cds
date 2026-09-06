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
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đường đua vịt – <?= htmlspecialchars($school) ?></title>
<style>
:root{--ink:#13354c;--navy:#07314a;--aqua:#35c8df;--yellow:#ffd452;--coral:#ff785a;--leaf:#52a96c}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,Segoe UI,system-ui,sans-serif;color:var(--ink)}
body{background:linear-gradient(180deg,#75d4e6 0 20%,#e8fbf7 20%);overflow-x:hidden}
.top{min-height:74px;padding:12px clamp(14px,4vw,48px);display:flex;align-items:center;gap:16px;background:#06334d;color:#fff;box-shadow:0 3px 14px #00314755;position:relative;z-index:5}
.brand{font-weight:900;font-size:clamp(18px,2.3vw,25px);letter-spacing:.03em;white-space:nowrap}.brand span{color:var(--yellow)}
.controls{margin-left:auto;display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.controls select,.btn{border:0;border-radius:12px;min-height:40px;padding:0 13px;font:700 14px inherit;cursor:pointer}
.controls select{background:#fff;color:var(--ink);min-width:155px}.btn{background:#ffffff24;color:#fff;border:1px solid #ffffff55}.btn:hover{transform:translateY(-1px);background:#ffffff38}.btn.active{background:var(--yellow);color:#633c00;border-color:var(--yellow)}.back{color:#d8f7ff;text-decoration:none;font-weight:700}
.page{max-width:1300px;margin:auto;padding:clamp(16px,3vw,34px) clamp(12px,3vw,28px) 22px}.intro{display:flex;align-items:end;justify-content:space-between;gap:15px;margin-bottom:14px}.intro h1{margin:0;color:#083b55;font-size:clamp(25px,4vw,42px);letter-spacing:.02em}.intro p{margin:4px 0 0;color:#286070;font-weight:600}.timer{font-variant-numeric:tabular-nums;background:#fff;border:4px solid var(--yellow);border-radius:18px;padding:6px 18px;text-align:center;box-shadow:0 5px 0 #dcae2c;color:#0b4963;font-size:clamp(28px,5vw,48px);font-weight:950;line-height:1}.timer small{display:block;color:#5d8090;font-size:10px;letter-spacing:.12em;margin-top:4px}
.stadium{position:relative;overflow:hidden;min-height:530px;border:7px solid #fff;border-radius:28px;background:linear-gradient(#8cdef0 0 16%,#c1f0e8 16% 21%,#209ec1 21% 100%);box-shadow:0 14px 35px #174e623b}
.sky{height:105px;background:linear-gradient(115deg,#63cbe4,#b9f5fa);position:relative}.cloud{position:absolute;background:#fff9;border-radius:99px;width:82px;height:25px;top:28px;left:13%;box-shadow:32px -12px 0 7px #fff9,65px 1px #fff9}.cloud:last-child{left:auto;right:18%;top:50px;transform:scale(.65)}
.stands{height:55px;background:repeating-linear-gradient(90deg,#e75d55 0 13px,#f8d264 13px 26px,#5dbf87 26px 39px,#4d8fd1 39px 52px);border-top:6px solid #fff;border-bottom:6px solid #095c75;position:relative}.stands:after{content:"★  ★  ★  ★  ★  ★  ★  ★  ★  ★  ★";position:absolute;inset:11px 0 auto;text-align:center;word-spacing:40px;color:#fff;font-size:16px;text-shadow:0 1px 2px #244}
.pond{position:relative;min-height:360px;padding:16px 48px 17px;background:repeating-linear-gradient(0deg,#3ab6d0 0 4px,#29a9c6 4px 8px)}.finish{position:absolute;top:0;bottom:0;right:9%;width:34px;background:repeating-conic-gradient(#fff 0 25%,#214d69 0 50%) 0/17px 17px;border-left:3px solid #fff;border-right:3px solid #fff;opacity:.96;z-index:1}.finish b{position:absolute;top:8px;left:50%;transform:translateX(-50%) rotate(90deg);white-space:nowrap;background:#083b55;color:#fff;border-radius:999px;padding:4px 9px;font-size:10px;letter-spacing:.1em}
.lanes{position:relative;z-index:2;display:grid;gap:7px}.lane{height:46px;position:relative;border-top:2px dashed #dffcff88;border-bottom:2px dashed #14789399;background:#2aaeca90;border-radius:10px}.lane.no-racer{opacity:.4}.name{position:absolute;left:6px;top:50%;transform:translateY(-50%);z-index:3;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#ffffffdd;border:2px solid #07627b;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:900;color:#124458;box-shadow:0 2px 0 #07566d33}.duck{position:absolute;left:clamp(125px,17%,175px);top:4px;width:55px;height:35px;transform:translateX(0);max-width:none;transition:transform .12s linear;filter:drop-shadow(0 3px 1px #075a7370)}.duck .body{position:absolute;inset:8px 3px 0 3px;border-radius:56% 45% 50% 48%;background:radial-gradient(circle at 36% 23%,#fff6 0 8%,transparent 9%),linear-gradient(145deg,#ffe965,#ffc52d 64%,#f3a800)}.duck .head{position:absolute;right:0;top:0;width:25px;height:24px;border-radius:50%;background:#ffd53c}.duck .head:before{content:"";position:absolute;right:3px;top:7px;width:4px;height:4px;background:#17364c;border-radius:50%}.duck .beak{position:absolute;right:-9px;top:12px;width:14px;height:9px;border-radius:2px 8px 8px 2px;background:#f77a42}.duck .wing{position:absolute;left:13px;top:15px;width:23px;height:16px;border-radius:50%;background:#f2ab12;transform:rotate(-18deg)}.duck .cap{position:absolute;right:4px;top:-4px;width:17px;height:7px;border-radius:10px 10px 2px 2px;background:#ef5f5b}.duck.winner{filter:drop-shadow(0 0 8px #fff500) drop-shadow(0 3px 1px #075a7370)}
.lily{position:absolute;width:52px;height:25px;border-radius:100% 0 100% 0;background:#56ae68;opacity:.9}.lily:after{content:"";position:absolute;left:20px;top:5px;width:12px;height:12px;border-radius:50%;background:#ff8bb4}.lily.one{left:4%;bottom:15px}.lily.two{right:3%;bottom:30px;transform:scale(.7)}.note{position:absolute;bottom:10px;left:50%;transform:translateX(-50%);z-index:4;background:#073c55e8;color:#fff;border-radius:999px;padding:8px 18px;font-size:13px;font-weight:800;white-space:nowrap;box-shadow:0 3px 12px #003a4d77}
.empty{display:grid;place-items:center;min-height:330px;text-align:center;color:#eaffff;font-weight:800;font-size:18px;padding:32px}.empty span{display:block;font-size:44px;margin-bottom:8px}.lower{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-top:15px;flex-wrap:wrap}.status{font-size:14px;font-weight:700;color:#24586a}.start{background:linear-gradient(135deg,#ff8b57,#ed5356);border:0;border-radius:16px;color:#fff;padding:13px 29px;font-size:17px;font-weight:950;letter-spacing:.06em;cursor:pointer;box-shadow:0 5px 0 #b63842}.start:hover{transform:translateY(-2px)}.start:disabled{opacity:.55;cursor:not-allowed;transform:none}.winners{background:#fff;border-radius:16px;padding:10px 14px;box-shadow:0 5px 16px #1f657222;display:flex;gap:8px;align-items:center;max-width:100%}.winners strong{white-space:nowrap}.winner-list{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#50717c}
.overlay{display:none;position:fixed;inset:0;z-index:20;place-items:center;background:#02293dcc;padding:18px}.overlay.show{display:grid}.winner-card{position:relative;overflow:hidden;width:min(520px,94vw);padding:34px 24px 28px;text-align:center;color:#0b415b;background:linear-gradient(145deg,#fff,#fff4c4);border:6px solid #fff;border-radius:28px;box-shadow:0 18px 55px #0008;animation:arrive .42s cubic-bezier(.2,1.5,.4,1)}@keyframes arrive{from{opacity:0;transform:scale(.55) rotate(-5deg)}}.cup{font-size:58px}.winner-card h2{font-size:17px;letter-spacing:.14em;margin:4px 0;color:#c76128}.winner-card .winner-name{font-size:clamp(29px,7vw,52px);font-weight:950;margin:8px 0 22px}.winner-card button{border:0;border-radius:999px;padding:11px 20px;background:#0b4a65;color:#fff;font-weight:900;cursor:pointer}.confetti{pointer-events:none;position:fixed;inset:0;z-index:21;overflow:hidden}.piece{position:absolute;width:10px;height:16px;animation:fall 2.4s linear forwards}@keyframes fall{to{transform:translate(var(--x),110vh) rotate(760deg);opacity:0}}
@media(max-width:650px){.top{align-items:flex-start}.brand{padding-top:8px}.controls{margin-left:0}.back{display:none}.stadium{min-height:475px}.pond{padding:14px 18px}.finish{right:4%;width:28px}.duck{left:112px}.name{max-width:105px;font-size:11px}.note{font-size:11px;max-width:90%;white-space:normal;text-align:center}.intro{align-items:flex-start}.timer{padding:5px 10px}}
</style>
</head>
<body>
<header class="top"><div class="brand">ĐƯỜNG ĐUA <span>VỊT VUI NHỘN</span></div><div class="controls">
  <select id="classSelect"><option value="">Chọn lớp để bắt đầu</option><?php foreach ($classNames as $name): ?><option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?></select>
  <button class="btn" id="shuffle" type="button">↻ Xáo vị trí</button>
  <button class="btn" id="removeWinners" type="button">Loại người thắng: Tắt</button>
  <button class="btn" id="reset" type="button">↺ Làm mới</button>
  <a class="back" href="<?= htmlspecialchars($base) ?>hoclieu.php?tab=games">← Học liệu</a>
</div></header>
<main class="page">
 <div class="intro"><div><h1>Sẵn sàng về đích!</h1><p>Chọn lớp, xáo các làn đua và cổ vũ cho những chú vịt.</p></div><div class="timer"><span id="timer">00.00</span><small>THỜI GIAN</small></div></div>
 <section class="stadium"><div class="sky"><i class="cloud"></i><i class="cloud"></i></div><div class="stands"></div><div class="pond"><div class="finish"><b>VỀ ĐÍCH</b></div><div id="lanes" class="lanes"><div class="empty"><div><span>🦆</span>Hãy chọn một lớp để mở đường đua.</div></div></div><i class="lily one"></i><i class="lily two"></i><div class="note" id="note">Mỗi chú vịt mang tên một học sinh</div></div></section>
 <div class="lower"><div class="winners"><strong>🏆 Đã thắng:</strong><span class="winner-list" id="winnerList">Chưa có lượt đua nào</span></div><div class="status" id="status">Chọn lớp để nạp danh sách học sinh.</div><button id="start" class="start" type="button" disabled>BẮT ĐẦU ĐUA!</button></div>
</main>
<div class="overlay" id="overlay"><div class="winner-card"><div class="cup">🏆</div><h2>NGƯỜI VỀ ĐÍCH ĐẦU TIÊN</h2><div class="winner-name" id="winnerName"></div><button id="raceAgain" type="button">Đua lượt tiếp theo</button></div></div><div class="confetti" id="confetti"></div>
<script>
const studentsByClass = <?= json_encode($studentsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const $ = id => document.getElementById(id);
let racers=[], winners=[], removeWinners=false, racing=false, startedAt=0, raf=0;
const classSelect=$('classSelect'), lanes=$('lanes'), start=$('start'), timer=$('timer');
function shuffle(items){ for(let i=items.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[items[i],items[j]]=[items[j],items[i]]} return items }
function savedWinners(){try{return JSON.parse(localStorage.getItem('cds_duck_winners_'+classSelect.value)||'[]')}catch(e){return[]}}
function saveWinners(){localStorage.setItem('cds_duck_winners_'+classSelect.value,JSON.stringify(winners))}
function render(){
  const source=(studentsByClass[classSelect.value]||[]).slice();
  const available=removeWinners ? source.filter(name=>!winners.includes(name)) : source;
  racers=shuffle(available);
  if(!classSelect.value){lanes.innerHTML='<div class="empty"><div><span>🦆</span>Hãy chọn một lớp để mở đường đua.</div></div>';start.disabled=true;return}
  if(!racers.length){lanes.innerHTML='<div class="empty"><div><span>🏁</span>Tất cả học sinh của lớp này đã thắng. Nhấn “Làm mới” để đua lại.</div></div>';start.disabled=true;return}
  lanes.innerHTML=racers.map((name,i)=>`<div class="lane"><span class="name">${escapeHtml(name)}</span><div class="duck" data-name="${escapeAttr(name)}"><i class="body"></i><i class="wing"></i><i class="head"></i><i class="beak"></i><i class="cap" style="background:${['#ef5f5b','#476fc0','#50a66d','#a663ae'][i%4]}"></i></div></div>`).join('');
  start.disabled=racers.length<2;
  $('status').textContent=racers.length<2?'Cần ít nhất 2 học sinh để đua.':`${racers.length} chú vịt đã vào làn xuất phát.`;
}
function escapeHtml(value){const el=document.createElement('span');el.textContent=value;return el.innerHTML}
function escapeAttr(value){return escapeHtml(value).replace(/"/g,'&quot;')}
function showWinners(){ $('winnerList').textContent=winners.length?winners.join(' · '):'Chưa có lượt đua nào'; }
function loadClass(){winners=savedWinners();timer.textContent='00.00';showWinners();render()}
function animate(now){
 const elapsed=now-startedAt, seconds=elapsed/1000; timer.textContent=seconds.toFixed(2).padStart(5,'0');
 let complete=true;
 document.querySelectorAll('.duck').forEach(duck=>{const duration=+duck.dataset.duration;const progress=Math.min(1,elapsed/duration), lane=duck.closest('.lane');const distance=Math.max(0,lane.clientWidth-duck.offsetLeft-duck.offsetWidth-28);duck.style.transform=`translateX(${progress*distance}px)`;if(progress<1)complete=false});
 if(!complete){raf=requestAnimationFrame(animate);return}
 finishRace();
}
function finishRace(){
 racing=false; start.disabled=false;
 const duck=[...document.querySelectorAll('.duck')].sort((a,b)=>+a.dataset.duration- +b.dataset.duration)[0];
 duck.classList.add('winner');const name=duck.dataset.name;
 if(!winners.includes(name)){winners.push(name);saveWinners();showWinners()}
 $('winnerName').textContent=name;$('overlay').classList.add('show');$('status').textContent=`${name} đã cán đích đầu tiên!`;confetti();
}
function begin(){
 if(racing||racers.length<2)return;racing=true;start.disabled=true;$('status').textContent='Xuất phát! Cổ vũ thật lớn nào!';
 document.querySelectorAll('.duck').forEach((duck,i)=>{duck.dataset.duration=3200+Math.random()*2500+i*12;duck.style.transform='translateX(0)';duck.classList.remove('winner')});
 startedAt=performance.now();raf=requestAnimationFrame(animate);
}
function confetti(){const box=$('confetti'), colors=['#ffd452','#ff785a','#35c8df','#7ecf74','#b078d1'];box.innerHTML='';for(let i=0;i<90;i++){const p=document.createElement('i');p.className='piece';p.style.left=Math.random()*100+'vw';p.style.top=(-10-Math.random()*35)+'px';p.style.background=colors[i%colors.length];p.style.setProperty('--x',(-120+Math.random()*240)+'px');p.style.animationDelay=Math.random()*.35+'s';box.appendChild(p)}setTimeout(()=>box.innerHTML='',3000)}
classSelect.addEventListener('change',loadClass);
$('shuffle').onclick=()=>{if(!racing&&classSelect.value){render();$('status').textContent='Đã xáo lại các làn đua.'}};
$('removeWinners').onclick=function(){if(racing)return;removeWinners=!removeWinners;this.classList.toggle('active',removeWinners);this.textContent='Loại người thắng: '+(removeWinners?'Bật':'Tắt');render()};
$('reset').onclick=()=>{if(racing)return;winners=[];saveWinners();showWinners();timer.textContent='00.00';render();$('status').textContent='Đã khôi phục tất cả học sinh.'};
start.onclick=begin;$('raceAgain').onclick=()=>{$('overlay').classList.remove('show');timer.textContent='00.00';render()};
</script>
</body>
</html>
