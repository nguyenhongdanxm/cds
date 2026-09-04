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
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vòng quay trên lớp – <?= htmlspecialchars($school) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{margin:0;min-height:100vh;background:radial-gradient(circle at top,#1d4f7a 0%,#0b1c2e 58%,#071018 100%);color:#fff;font-family:"Segoe UI",system-ui,sans-serif}
.wrap{max-width:1200px;margin:0 auto;padding:22px 16px 48px}
.topbar{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:18px}
.topbar a{color:#d7e7f7;text-decoration:none;font-weight:700}
h1{margin:0 0 6px;font-size:clamp(1.5rem,3vw,2.2rem)}
.sub{opacity:.8}
.modes{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.modes button{border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;border-radius:999px;padding:8px 14px;font-weight:700}
.modes button.active{background:#f4c15d;color:#10243a;border-color:#f4c15d}
.stage{display:grid;grid-template-columns:minmax(280px,1fr) 360px;gap:22px}
@media(max-width:960px){.stage{grid-template-columns:1fr}}
.wheel-box{position:relative;display:flex;flex-direction:column;align-items:center}
canvas{max-width:min(92vw,560px);height:auto;filter:drop-shadow(0 18px 40px rgba(0,0,0,.35))}
.pointer{position:absolute;top:18px;left:50%;transform:translateX(-50%);border-left:16px solid transparent;border-right:16px solid transparent;border-top:28px solid #ffd978;z-index:3}
.spin-btn{margin-top:16px;border:0;border-radius:999px;padding:14px 34px;font-weight:800;color:#10243a;background:linear-gradient(180deg,#ffe38a,#f0b429)}
.panel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:18px}
.item-row{display:flex;gap:8px;margin-bottom:8px}
.item-row input{flex:1;border:0;border-radius:10px;padding:8px 10px;color:#10243a}
.item-row button{border:0;border-radius:10px;background:#e85d4c;color:#fff;padding:8px 10px}
.result{min-height:76px;margin-top:14px;border-radius:14px;background:rgba(255,255,255,.1);padding:14px;text-align:center;font-size:1.2rem;font-weight:800}
.hint{opacity:.75;font-size:.88rem;margin-top:10px}
.mode-box{display:none}.mode-box.show{display:block}
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div>
      <div class="sub">Học liệu và thi · trò chơi trên lớp</div>
      <h1><i class="bi bi-trophy"></i> Vòng quay trên lớp</h1>
    </div>
    <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>hoclieu.php?tab=games"><i class="bi bi-arrow-left"></i> Về học liệu</a>
  </div>
  <div class="modes">
    <button type="button" class="active" data-mode="student">Dạng 1 · Gọi học sinh lên bảng</button>
    <button type="button" data-mode="prize">Dạng 2 · Phần thưởng</button>
    <button type="button" data-mode="task">Dạng 3 · Câu hỏi / nhiệm vụ</button>
  </div>
  <div class="stage">
    <div class="wheel-box">
      <div class="pointer"></div>
      <canvas id="wheel" width="640" height="640"></canvas>
      <button class="spin-btn" id="spinBtn" type="button">QUAY NGAY</button>
      <div class="result" id="result">Chọn dạng quay, rồi nhấn QUAY NGAY</div>
    </div>
    <div class="panel">
      <div class="mode-box show" id="box-student">
        <h2>Chọn lớp để lấy tên học sinh</h2>
        <select class="form-select mb-3" id="classSelect">
          <option value="">Chọn lớp</option>
          <?php foreach ($classNames as $name): ?><option><?= htmlspecialchars($name) ?></option><?php endforeach; ?>
        </select>
        <p class="hint" id="studentHint">Tên học sinh lấy từ danh sách lớp trong CSDL. Quay trúng ai thì em đó lên bảng.</p>
      </div>
      <div class="mode-box" id="box-prize">
        <h2>Phần thưởng do giáo viên cài</h2>
        <div id="prizeItems"></div>
        <button class="btn btn-light w-100 mt-2" type="button" id="addPrize">Thêm phần thưởng</button>
      </div>
      <div class="mode-box" id="box-task">
        <h2>Câu hỏi hoặc nhiệm vụ trên lớp</h2>
        <div id="taskItems"></div>
        <button class="btn btn-light w-100 mt-2" type="button" id="addTask">Thêm câu hỏi / nhiệm vụ</button>
      </div>
      <p class="hint">Dạng 1 lấy tên theo lớp đã chọn. Dạng 2 và 3 lưu trên trình duyệt máy này.</p>
    </div>
  </div>
</div>
<script>
const studentsByClass = <?= json_encode($studentsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const canvas = document.getElementById('wheel');
const ctx = canvas.getContext('2d');
const colors = ['#e85d4c','#f4c15d','#2aa7a0','#4c8fe8','#c46be0','#f28b3c','#3ecf8e','#ef6aa7'];
let mode = 'student';
let prizes = JSON.parse(localStorage.getItem('cds_wheel_prizes') || 'null') || ['+1 điểm chuyên cần','Hát 1 bài','Chúc may mắn','Chọn bạn quay hộ','Khen trước lớp','Cộng 1 ngôi sao'];
let tasks = JSON.parse(localStorage.getItem('cds_wheel_tasks') || 'null') || ['Nêu 1 ý chính của bài','Đặt 1 câu hỏi cho bạn','Tóm tắt bài trong 30 giây','Viết 1 ví dụ lên bảng','Giải thích 1 từ khó','Chọn bạn trả lời hộ'];
let items = [], angle = 0, spinning = false, speed = 0;

function currentItems(){
  if (mode === 'student') {
    const cls = document.getElementById('classSelect').value;
    return (studentsByClass[cls] || []).slice();
  }
  return mode === 'prize' ? prizes.slice() : tasks.slice();
}
function renderEditor(id, list, storeKey){
  const box = document.getElementById(id);
  box.innerHTML = '';
  list.forEach((text, i) => {
    const row = document.createElement('div');
    row.className = 'item-row';
    row.innerHTML = '<input value="'+String(text).replace(/"/g,'&quot;')+'"><button type="button">Xóa</button>';
    row.querySelector('input').addEventListener('input', e => {
      list[i] = e.target.value;
      localStorage.setItem(storeKey, JSON.stringify(list));
      refreshWheel();
    });
    row.querySelector('button').addEventListener('click', () => {
      if (list.length < 3) return;
      list.splice(i, 1);
      localStorage.setItem(storeKey, JSON.stringify(list));
      renderEditor(id, list, storeKey);
      refreshWheel();
    });
    box.appendChild(row);
  });
}
function draw(){
  items = currentItems();
  const cx = canvas.width/2, cy = canvas.height/2, r = 290, n = Math.max(items.length, 1), arc = Math.PI*2/n;
  ctx.clearRect(0,0,canvas.width,canvas.height);
  ctx.save(); ctx.translate(cx,cy); ctx.rotate(angle);
  for (let i=0;i<n;i++){
    ctx.beginPath(); ctx.moveTo(0,0); ctx.fillStyle = colors[i%colors.length];
    ctx.arc(0,0,r,i*arc,(i+1)*arc); ctx.fill();
    ctx.save(); ctx.rotate(i*arc+arc/2); ctx.fillStyle='#fff'; ctx.font='bold 16px Segoe UI'; ctx.textAlign='right';
    ctx.fillText(String(items[i]||'Chưa có dữ liệu').slice(0,22), r-18, 6); ctx.restore();
  }
  ctx.beginPath(); ctx.fillStyle='#fff'; ctx.arc(0,0,42,0,Math.PI*2); ctx.fill();
  ctx.fillStyle='#10243a'; ctx.font='bold 16px Segoe UI'; ctx.textAlign='center'; ctx.fillText('GO',0,6);
  ctx.restore();
}
function refreshWheel(){ items = currentItems(); draw(); }
function tick(){
  if (!spinning) return;
  angle += speed; speed *= 0.985;
  if (speed < 0.002) {
    spinning = false;
    document.getElementById('spinBtn').disabled = false;
    const n = Math.max(items.length,1), arc = Math.PI*2/n;
    const a = ((Math.PI*1.5 - angle) % (Math.PI*2) + Math.PI*2) % (Math.PI*2);
    const i = Math.floor(a/arc) % n;
    const label = items[i] || 'Chưa có dữ liệu';
    const prefix = mode==='student' ? 'Mời lên bảng: ' : (mode==='prize' ? 'Phần thưởng: ' : 'Nhiệm vụ: ');
    document.getElementById('result').textContent = prefix + label;
  }
  draw();
  requestAnimationFrame(tick);
}
document.getElementById('spinBtn').addEventListener('click', function(){
  items = currentItems();
  if (spinning) return;
  if (items.length < 2) {
    document.getElementById('result').textContent = mode==='student' ? 'Hãy chọn lớp có từ 2 học sinh trở lên.' : 'Cần ít nhất 2 ô để quay.';
    return;
  }
  spinning = true;
  this.disabled = true;
  speed = 0.35 + Math.random()*0.25;
  document.getElementById('result').textContent = 'Đang quay...';
  requestAnimationFrame(tick);
});
document.getElementById('classSelect').addEventListener('change', function(){
  const n = (studentsByClass[this.value] || []).length;
  document.getElementById('studentHint').textContent = this.value ? ('Lớp ' + this.value + ' có ' + n + ' học sinh trong CSDL.') : 'Tên học sinh lấy từ danh sách lớp trong CSDL.';
  refreshWheel();
});
document.querySelectorAll('.modes button').forEach(btn => {
  btn.addEventListener('click', () => {
    mode = btn.dataset.mode;
    document.querySelectorAll('.modes button').forEach(x => x.classList.toggle('active', x === btn));
    document.querySelectorAll('.mode-box').forEach(x => x.classList.toggle('show', x.id === 'box-' + mode));
    refreshWheel();
  });
});
document.getElementById('addPrize').addEventListener('click', () => {
  prizes.push('Phần thưởng mới');
  localStorage.setItem('cds_wheel_prizes', JSON.stringify(prizes));
  renderEditor('prizeItems', prizes, 'cds_wheel_prizes');
  refreshWheel();
});
document.getElementById('addTask').addEventListener('click', () => {
  tasks.push('Nhiệm vụ mới');
  localStorage.setItem('cds_wheel_tasks', JSON.stringify(tasks));
  renderEditor('taskItems', tasks, 'cds_wheel_tasks');
  refreshWheel();
});
renderEditor('prizeItems', prizes, 'cds_wheel_prizes');
renderEditor('taskItems', tasks, 'cds_wheel_tasks');
refreshWheel();
</script>
</body>
</html>
