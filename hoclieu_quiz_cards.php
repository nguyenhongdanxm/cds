<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
require_login();
if (!qp_admin() && !can_perm_level('hl.xem','view')) { http_response_code(403); exit('Không có quyền xem thẻ trả lời.'); }
$classes=array_values(array_filter(csdl_classes_all(),static fn($c)=>!empty($c['active']) && (!function_exists('can_class') || can_class((string)($c['name']??'')))));
csdl_sort_classes($classes);
$classId=(string)($_GET['class']??'');$chosen=null;
foreach ($classes as $class) if ((string)$class['id']===$classId) { $chosen=$class;break; }
$students=[];
if ($chosen) foreach (csdl_students_all() as $student) if (!empty($student['active']) && (string)($student['class_id']??'')===$classId) $students[]=['id'=>(string)$student['id'],'name'=>(string)($student['name']??'')];
$json=json_encode($students,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản lý thẻ trả lời A–D</title>
<style>*{box-sizing:border-box}body{font:16px system-ui,Arial;margin:0;background:#eef4fa;color:#183153}header{background:#123460;color:#fff;padding:20px max(16px,calc((100vw - 1000px)/2))}header a{color:#fff}main{max-width:1000px;margin:22px auto;padding:0 16px}.panel{background:#fff;border-radius:14px;padding:20px;margin-bottom:16px;box-shadow:0 5px 20px #142c4515}select,button{font:inherit;padding:10px;border-radius:9px}button{border:0;background:#126bb0;color:#fff;cursor:pointer}button:disabled{opacity:.5}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.quiz-card{aspect-ratio:1;border:2px solid #14283e;background:#fff;display:grid;grid-template-rows:15% 70% 15%;text-align:center;break-inside:avoid}.quiz-card .middle{display:grid;grid-template-columns:15% 70% 15%;align-items:center}.quiz-card .letter{font-size:28px;font-weight:900}.quiz-card .code{display:flex;align-items:center;justify-content:center}.quiz-card .code img,.quiz-card .code canvas{width:100%;height:100%;object-fit:contain}.quiz-card small{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:2px 5px}.muted{color:#607086}@media(max-width:600px){.cards{grid-template-columns:repeat(2,1fr)}.quiz-card .letter{font-size:20px}}@media print{body{background:#fff}header,.no-print{display:none!important}main{max-width:none;margin:0;padding:0}.panel{box-shadow:none;padding:0;margin:0}.cards{grid-template-columns:repeat(3,1fr);gap:5mm}.quiz-card{width:58mm;height:58mm}}</style></head><body>
<header><a href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><h1>Quản lý thẻ trả lời A–D</h1></header><main>
<div class="panel no-print"><p>Mỗi học sinh dùng một mã riêng ổn định lấy từ CSDL. In một lần và dùng lại cho các bộ câu hỏi trắc nghiệm. Mã này không chứa CCCD hay thông tin phụ huynh.</p><form method="get"><label for="class">Chọn lớp: </label><select id="class" name="class" onchange="this.form.submit()"><option value="">Chọn lớp</option><?php foreach ($classes as $class): ?><option value="<?=e((string)$class['id'])?>" <?=($chosen && $chosen['id']===$class['id'])?'selected':''?>><?=e((string)$class['name'])?></option><?php endforeach; ?></select></form></div>
<?php if ($chosen): ?><div class="panel"><div class="no-print"><h2><?=e((string)$chosen['name'])?> · <?=count($students)?> học sinh</h2><p class="muted">Học sinh xoay thẻ để chữ A, B, C hoặc D nằm trên cùng. In ở tỷ lệ 100%, không chọn “vừa trang”.</p><button id="print" disabled>In thẻ lớp này</button><p id="status"></p></div><div id="cards" class="cards"></div></div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script><script>
const students=<?=$json?:'[]'?>,cards=document.getElementById('cards');
if(!window.QRCode)document.getElementById('status').textContent='Không tải được thư viện tạo QR. Kiểm tra kết nối mạng trước khi in.';
else {students.forEach(s=>{let el=document.createElement('div');el.className='quiz-card';el.innerHTML='<div class="letter">A</div><div class="middle"><span class="letter">D</span><div class="code"></div><span class="letter">B</span></div><div><span class="letter">C</span><br><small></small></div>';el.querySelector('small').textContent=s.name;cards.appendChild(el);new QRCode(el.querySelector('.code'),{text:'CDSQ1:'+s.id,width:180,height:180,correctLevel:QRCode.CorrectLevel.L})});document.getElementById('status').textContent='Đã tạo '+students.length+' thẻ.';document.getElementById('print').disabled=false;document.getElementById('print').onclick=()=>window.print()}
</script><?php endif; ?></main></body></html>
