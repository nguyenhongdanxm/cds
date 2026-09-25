<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_login();
if (!can_perm_level('hl.xem', 'view') && (current_user()['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Bạn không có quyền xem trò chơi.');
}
$classes = array_values(array_filter(csdl_classes_all(), static function ($class) {
    return !empty($class['active']) && (!function_exists('can_class') || can_class((string)($class['name'] ?? '')));
}));
csdl_sort_classes($classes);
$classId = trim((string)($_GET['class'] ?? ''));
$chosen = null;
foreach ($classes as $class) {
    if ((string)$class['id'] === $classId) { $chosen = $class; break; }
}
$students = [];
if ($chosen) {
    foreach (csdl_students_all() as $student) {
        if (!empty($student['active']) && (string)($student['class_id'] ?? '') === $classId) {
            $students[] = ['id' => (string)$student['id'], 'name' => (string)($student['name'] ?? $student['full_name'] ?? '')];
        }
    }
}
$studentJson = json_encode($students, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trả lời bằng thẻ QR · Bản thử</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef3f9;color:#183153;font:16px system-ui,Arial,sans-serif}header{background:#123460;color:#fff;padding:18px max(18px,calc((100vw - 1140px)/2))}header a{color:#fff}main{max-width:1140px;margin:24px auto;padding:0 16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:16px}.panel{background:#fff;border-radius:16px;padding:20px;box-shadow:0 4px 20px #18315316;margin-bottom:16px}h1{margin:6px 0;font-size:26px}h2{margin:0 0 14px;font-size:20px}label{display:block;font-weight:650;margin:10px 0 5px}select,input,textarea,button{font:inherit}select,input,textarea{width:100%;padding:10px;border:1px solid #aabbd0;border-radius:8px}textarea{min-height:70px}button,.action{border:0;background:#126bb0;color:#fff;border-radius:9px;padding:11px 15px;cursor:pointer;text-decoration:none;display:inline-block;margin:4px 4px 4px 0}button:disabled{opacity:.5;cursor:not-allowed}.secondary{background:#47617f}.danger{background:#a93636}.answer-buttons button{min-width:55px;background:#e7f0fc;color:#14325a;border:2px solid transparent;font-weight:800}.answer-buttons button.active{border-color:#126bb0;background:#cce6ff}.muted{color:#56677c}.notice{background:#fff6d8;border-left:4px solid #e5a300;padding:10px;border-radius:6px}.camera{width:100%;background:#07172c;aspect-ratio:4/3;object-fit:contain;border-radius:10px}.results{width:100%;border-collapse:collapse}.results td,.results th{padding:9px;border-bottom:1px solid #d9e2ec;text-align:left}.results tr.ok{background:#e4f7e9}.results tr.bad{background:#ffefeb}.cards{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.quiz-card{width:100%;aspect-ratio:1;border:2px solid #14283e;background:#fff;display:grid;grid-template-rows:15% 70% 15%;text-align:center;page-break-inside:avoid;break-inside:avoid}.quiz-card .middle{display:grid;grid-template-columns:15% 70% 15%;align-items:center}.quiz-card .letter{font-size:27px;font-weight:900}.quiz-card .code{display:flex;align-items:center;justify-content:center}.quiz-card .code img,.quiz-card .code canvas{width:100%;height:100%;object-fit:contain}.quiz-card small{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:2px 5px}.status{font-weight:700;min-height:24px}.hidden{display:none!important}@media(max-width:600px){.cards{grid-template-columns:1fr 1fr}.quiz-card .letter{font-size:20px}}@media print{body{background:#fff}header,.no-print,.panel:not(.print-panel){display:none!important}main{max-width:none;margin:0;padding:0}.print-panel{box-shadow:none;padding:0;margin:0}.cards{grid-template-columns:repeat(3,1fr);gap:5mm}.quiz-card{width:58mm;height:58mm}h2{display:none}}
</style></head><body>
<header><a href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><h1>Trả lời bằng thẻ QR <small>· Bản thử</small></h1></header>
<main><div class="panel no-print"><form method="get"><label for="class">Lớp chơi</label><select id="class" name="class" onchange="this.form.submit()"><option value="">Chọn lớp</option><?php foreach ($classes as $class): ?><option value="<?=e((string)$class['id'])?>" <?=($chosen && $class['id']===$chosen['id'])?'selected':''?>><?=e((string)$class['name'])?></option><?php endforeach; ?></select></form></div>
<?php if ($chosen): ?>
<p class="notice no-print">Thử trong một lớp với thẻ in bên dưới. Dữ liệu câu hỏi và kết quả chỉ lưu trong trình duyệt đang dùng; không ghi điểm Olympia. Mỗi em xoay thẻ sao cho đáp án A, B, C hoặc D nằm trên cùng.</p>
<div class="grid no-print"><section class="panel"><h2>1. Câu hỏi</h2><label for="question">Nội dung</label><textarea id="question" placeholder="Nhập câu hỏi để hiển thị khi chơi"></textarea><label>Đáp án đúng</label><div class="answer-buttons" id="keys"></div><button id="newQuestion">Mở câu hỏi mới</button><button id="clearQuestion" class="secondary">Xóa lượt quét của câu này</button><p id="current" class="status"></p></section>
<section class="panel"><h2>2. Quét bằng điện thoại</h2><video class="camera" id="video" playsinline muted autoplay></video><canvas id="frame" class="hidden"></canvas><p id="scanStatus" class="status">Chọn lớp và mở câu hỏi để bắt đầu.</p><button id="startScan">Bật camera</button><button id="stopScan" class="secondary">Tắt camera</button><p class="muted">Giữ mã hướng về camera, đủ sáng và cách điện thoại khoảng 0,5–2 m. Lia máy lần lượt qua các nhóm học sinh. Nếu chưa đọc được, có thể ghi tay ở bảng kết quả.</p></section></div>
<section class="panel no-print"><h2>3. Kết quả câu hiện tại · <span id="count">0</span>/<?=count($students)?></h2><button id="exportCsv" class="secondary">Xuất CSV kết quả</button><table class="results"><thead><tr><th>Học sinh</th><th>Đáp án</th><th>Đúng/sai</th><th>Sửa thủ công</th></tr></thead><tbody id="resultRows"></tbody></table></section>
<section class="panel print-panel"><div class="no-print"><h2>Thẻ trả lời lớp <?=e((string)$chosen['name'])?></h2><p>Mã này chỉ chứa ID nội bộ; không chứa CCCD, số điện thoại hay thông tin phụ huynh. Dùng thẻ riêng cho trò chơi, không thay QR xác minh thẻ học sinh.</p><button onclick="window.print()">In thẻ A–D</button></div><div id="cards" class="cards"></div></section>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script><script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const students=<?=$studentJson?:'[]'?>, classId=<?=json_encode($classId,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const byId=new Map(students.map(s=>[s.id,s]));
const $=id=>document.getElementById(id), escapeHtml=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const storageKey='cds-qr-trial-v1:'+classId;
let state;try{state=JSON.parse(sessionStorage.getItem(storageKey)||'{}')}catch(e){state={}}
if(!state||typeof state!=='object')state={};
state.questions=Array.isArray(state.questions)?state.questions:[];
state.index=Number.isInteger(state.index)?state.index:-1;
let cameraStream=null,scanning=false,busy=false,lastSeen=new Map();
const current=()=>state.questions[state.index]||null;
function save(){sessionStorage.setItem(storageKey,JSON.stringify(state))}
function render(){let q=current();$('question').value=q?.text||'';$('current').textContent=q?'Câu '+(state.index+1)+' · '+(q.text||'Chưa nhập nội dung'):'Chưa mở câu hỏi';$('keys').innerHTML=['A','B','C','D'].map(a=>'<button type="button" data-key="'+a+'" class="'+(q?.key===a?'active':'')+'">'+a+'</button>').join('');let count=0;$('resultRows').innerHTML=students.map(s=>{let a=q?.answers?.[s.id]||'',status=a?(a===q.key?'Đúng':'Sai'):'Chưa trả lời';if(a)count++;return '<tr class="'+(a?(a===q.key?'ok':'bad'):'')+'"><td>'+escapeHtml(s.name)+'</td><td>'+escapeHtml(a||'—')+'</td><td>'+status+'</td><td><select class="manual" data-id="'+escapeHtml(s.id)+'"><option value="">—</option>'+['A','B','C','D'].map(x=>'<option value="'+x+'" '+(a===x?'selected':'')+'>'+x+'</option>').join('')+'</select></td></tr>'}).join('');$('count').textContent=count}
$('keys').onclick=e=>{let a=e.target.dataset.key,q=current();if(!q||!a)return;q.key=a;save();render()};
$('question').oninput=e=>{let q=current();if(q){q.text=e.target.value;save();$('current').textContent='Câu '+(state.index+1)+' · '+(q.text||'Chưa nhập nội dung')}};
$('newQuestion').onclick=()=>{stopCamera();state.questions.push({text:'',key:'A',answers:{}});state.index=state.questions.length-1;save();render();$('question').focus()};
$('clearQuestion').onclick=()=>{let q=current();if(q&&confirm('Xóa tất cả câu trả lời của câu hiện tại?')){q.answers={};save();render()}};
$('resultRows').onchange=e=>{if(!e.target.matches('.manual'))return;let q=current();if(!q)return;let id=e.target.dataset.id;if(!byId.has(id))return;if(e.target.value)q.answers[id]=e.target.value;else delete q.answers[id];save();render()};
function csvCell(v){return '"'+String(v??'').replace(/"/g,'""')+'"'}
$('exportCsv').onclick=()=>{let rows=[['Câu','Nội dung','Đáp án đúng','Lớp','Học sinh','Đáp án','Kết quả']];state.questions.forEach((q,i)=>students.forEach(s=>{let a=q.answers?.[s.id]||'';rows.push([i+1,q.text,q.key,<?=json_encode((string)$chosen['name'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,s.name,a,a?(a===q.key?'Đúng':'Sai'):'Chưa trả lời'])}));let csv='\uFEFF'+rows.map(r=>r.map(csvCell).join(',')).join('\r\n'),url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'})),link=document.createElement('a');link.href=url;link.download='tra-loi-the-'+classId+'.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000)};
const cards=$('cards');students.forEach(s=>{let el=document.createElement('div');el.className='quiz-card';el.innerHTML='<div class="letter">A</div><div class="middle"><span class="letter">D</span><div class="code"></div><span class="letter">B</span></div><div><span class="letter">C</span><br><small></small></div>';el.querySelector('small').textContent=s.name;cards.appendChild(el);if(window.QRCode)new QRCode(el.querySelector('.code'),{text:'CDSQ1:'+s.id,width:180,height:180,correctLevel:QRCode.CorrectLevel.L})});
function answerFromLocation(loc){let p=loc.topLeftCorner,r=loc.topRightCorner;if(!p||!r)return null;let dx=r.x-p.x,dy=r.y-p.y;if(Math.hypot(dx,dy)<20)return null;let angle=Math.atan2(dy,dx)*180/Math.PI; if(angle>=-45&&angle<45)return 'A';if(angle>=45&&angle<135)return 'D';if(angle>=-135&&angle< -45)return 'B';return 'C'}
function scanFrame(){if(!scanning||busy)return;busy=true;try{let v=$('video');if(v.readyState<2)return;let w=Math.min(1000,v.videoWidth),h=Math.round(v.videoHeight*w/v.videoWidth);if(!w||!h)return;let canvas=$('frame');canvas.width=w;canvas.height=h;let ctx=canvas.getContext('2d',{willReadFrequently:true});ctx.drawImage(v,0,0,w,h);let data=ctx.getImageData(0,0,w,h),found=0,q=current();if(!q)return;for(let n=0;n<12;n++){let code=window.jsQR?.(data.data,w,h,{inversionAttempts:'dontInvert'});if(!code)break;let id=code.data.startsWith('CDSQ1:')?code.data.slice(6):'';let answer=answerFromLocation(code.location);if(byId.has(id)&&answer){found++;let prior=lastSeen.get(id),now=Date.now();if(prior?.answer===answer&&now-prior.time<2500&&prior.count>=1){if(!q.answers[id]){q.answers[id]=answer;save();render();$('scanStatus').textContent=byId.get(id).name+' → '+answer+' · Đã ghi nhận'}}else lastSeen.set(id,{answer,time:now,count:1});lastSeen.set(id,{answer,time:now,count:1})}let corners=[code.location.topLeftCorner,code.location.topRightCorner,code.location.bottomRightCorner,code.location.bottomLeftCorner].filter(Boolean);if(corners.length<4)break;ctx.beginPath();ctx.moveTo(corners[0].x,corners[0].y);corners.slice(1).forEach(p=>ctx.lineTo(p.x,p.y));ctx.closePath();ctx.fillStyle='#000';ctx.fill();data=ctx.getImageData(0,0,w,h)}if(found&&!$('scanStatus').textContent.includes('Đã ghi nhận'))$('scanStatus').textContent='Đang nhận diện '+found+' thẻ…'}catch(e){$('scanStatus').textContent='Lỗi quét: '+e.message}finally{busy=false}}
let interval=null;async function startCamera(){if(!current()){$('scanStatus').textContent='Hãy mở câu hỏi mới trước.';return}if(!window.jsQR){$('scanStatus').textContent='Không tải được thư viện quét mã. Kiểm tra kết nối mạng.';return}if(!navigator.mediaDevices?.getUserMedia){$('scanStatus').textContent='Trình duyệt cần HTTPS và quyền camera.';return}try{cameraStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280}},audio:false});$('video').srcObject=cameraStream;await $('video').play();scanning=true;lastSeen.clear();interval=setInterval(scanFrame,300);$('scanStatus').textContent='Camera đang quét. Giơ mặt mã về phía điện thoại.'}catch(e){$('scanStatus').textContent='Không mở được camera: '+e.message}}
function stopCamera(){scanning=false;if(interval)clearInterval(interval);interval=null;cameraStream?.getTracks().forEach(t=>t.stop());cameraStream=null;$('video').srcObject=null}
$('startScan').onclick=startCamera;$('stopScan').onclick=stopCamera;window.addEventListener('pagehide',stopCamera);render();
</script><?php endif; ?></main></body></html>
