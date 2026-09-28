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
<style>*{box-sizing:border-box}body{font:16px system-ui,Arial;margin:0;background:#eef4fa;color:#183153}header{background:#123460;color:#fff;padding:20px max(16px,calc((100vw - 1000px)/2))}header a{color:#fff}main{max-width:1000px;margin:22px auto;padding:0 16px}.panel{background:#fff;border-radius:14px;padding:20px;margin-bottom:16px;box-shadow:0 5px 20px #142c4515}select,button{font:inherit;padding:10px;border-radius:9px}button{border:0;background:#126bb0;color:#fff;cursor:pointer}button:disabled{opacity:.5}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.quiz-card{aspect-ratio:1;border:2px solid #14283e;background:#fff;display:grid;grid-template-rows:15% 70% 15%;text-align:center;break-inside:avoid}.quiz-card .middle{display:grid;grid-template-columns:15% 70% 15%;align-items:center}.quiz-card .letter{font-size:28px;font-weight:900}.quiz-card .code{display:flex;align-items:center;justify-content:center}.quiz-card .code img,.quiz-card .code canvas{width:100%;height:100%;object-fit:contain}.quiz-card small{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:2px 5px}.muted{color:#607086}@media(max-width:600px){.cards{grid-template-columns:repeat(2,1fr)}.quiz-card .letter{font-size:20px}}@media print{body{background:#fff}header,.no-print{display:none!important}main{max-width:none;margin:0;padding:0}.panel{box-shadow:none;padding:0;margin:0}.cards{grid-template-columns:repeat(3,1fr);gap:5mm}.quiz-card{width:58mm;height:58mm}}</style>
<style>
.sheet{display:contents}
.quiz-card{grid-template-rows:17% 60% 23%;min-width:0;border-color:#064a8a}
.quiz-card .letter{color:#064a8a}
.quiz-card>.letter:first-child{align-self:start}
.quiz-card .middle{grid-template-columns:17% 66% 17%;min-height:0}
.quiz-card .code{min-width:0;min-height:0}
.quiz-card .code{position:relative}
.quiz-card .orientation-mark{position:absolute;top:-13%;left:46%;width:8%;height:8%;border-radius:50%;background:#d61f35;box-shadow:0 0 0 3px #fff;z-index:1}
.card-bottom{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;min-width:0;min-height:0;padding:0 6px 4px}
.student-label{display:block;width:100%;color:#bd1e30;font-size:16px;font-weight:850;line-height:1.15;overflow-wrap:anywhere;text-align:center}
@media(max-width:600px){.student-label{font-size:12px}.card-bottom{padding:0 3px 2px;gap:0}}
@page{size:A4 portrait;margin:10mm}
@media print{
  html,body{width:190mm;margin:0;padding:0;print-color-adjust:exact;-webkit-print-color-adjust:exact}
  .cards{display:block;width:190mm;margin:0;padding:0}
  .sheet{display:flex;flex-direction:column;align-items:center;gap:8mm;width:190mm;height:275mm;padding:4mm 0;break-after:page;page-break-after:always}
  .sheet:last-child{break-after:auto;page-break-after:auto}
  .quiz-card{flex:none;width:128mm;height:128mm;aspect-ratio:auto;border:0.6mm solid #064a8a;break-inside:avoid;page-break-inside:avoid}
  .quiz-card .letter{font-size:11mm;line-height:1}
  .card-bottom{gap:1mm;padding:0 5mm 3mm}
  .student-label{font-size:5mm;line-height:1.12}
}
</style></head><body>
<header><a href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><h1>Quản lý thẻ trả lời A–D</h1></header><main>
<div class="panel no-print"><p>Mỗi học sinh dùng một mã riêng ổn định lấy từ CSDL. In một lần và dùng lại cho các bộ câu hỏi trắc nghiệm. Mã này không chứa CCCD hay thông tin phụ huynh.</p><form method="get"><label for="class">Chọn lớp: </label><select id="class" name="class" onchange="this.form.submit()"><option value="">Chọn lớp</option><?php foreach ($classes as $class): ?><option value="<?=e((string)$class['id'])?>" <?=($chosen && $chosen['id']===$class['id'])?'selected':''?>><?=e((string)$class['name'])?></option><?php endforeach; ?></select></form></div>
<?php if ($chosen): ?><div class="panel"><div class="no-print"><h2><?=e((string)$chosen['name'])?> · <?=count($students)?> học sinh</h2><p class="muted">Học sinh xoay thẻ để chữ A, B, C hoặc D nằm trên cùng. Mỗi trang A4 in 2 thẻ; chọn A4 dọc, tỷ lệ 100% và tắt đầu trang/chân trang của trình duyệt.</p><button id="print" disabled>In thẻ lớp này</button><p id="status"></p></div><div id="cards" class="cards"></div></div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script><script>
const students=<?=$json?:'[]'?>,className=<?=json_encode((string)($chosen['name']??''),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,cards=document.getElementById('cards');
if(!window.QRCode)document.getElementById('status').textContent='Không tải được thư viện tạo QR. Kiểm tra kết nối mạng trước khi in.';
else {students.forEach((s,i)=>{let sheet;if(i%2===0){sheet=document.createElement('div');sheet.className='sheet';cards.appendChild(sheet)}else sheet=cards.lastElementChild;let el=document.createElement('div');el.className='quiz-card';el.innerHTML='<div class="letter">A</div><div class="middle"><span class="letter">D</span><div class="code"><span class="orientation-mark"></span></div><span class="letter">B</span></div><div class="card-bottom"><span class="letter">C</span><span class="student-label"></span></div>';el.querySelector('.student-label').textContent=s.name+' · Lớp '+className;sheet.appendChild(el);new QRCode(el.querySelector('.code'),{text:'CDSQ1:'+s.id,width:400,height:400,correctLevel:QRCode.CorrectLevel.L})});document.getElementById('status').textContent='Đã tạo '+students.length+' thẻ · '+Math.ceil(students.length/2)+' trang A4.';document.getElementById('print').disabled=false;document.getElementById('print').onclick=()=>window.print()}
</script><?php endif; ?></main></body></html>
