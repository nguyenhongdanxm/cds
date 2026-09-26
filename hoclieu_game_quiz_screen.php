<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
require_login();
$code=trim((string)($_GET['code']??$_POST['code']??''));
$session=qp_session($code);
if (!$session || $session['mode']!=='paper' || (!qp_admin() && $session['owner_id']!==qp_owner())) { http_response_code(403); exit('Không có quyền xem lượt chơi giấy.'); }
$questions=json_decode((string)$session['questions_json'],true) ?: [];
$roster=json_decode((string)($session['roster_json']??''),true) ?: [];
if (empty($_SESSION['qp_screen_csrf'])) $_SESSION['qp_screen_csrf']=bin2hex(random_bytes(24));
$csrf=(string)$_SESSION['qp_screen_csrf'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new RuntimeException('Phiên không hợp lệ.');
        if ($session['status']!=='open') throw new RuntimeException('Lượt chơi đã đóng.');
        $index=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
        if ($index===false || $index<0 || $index>=count($questions)) throw new RuntimeException('Câu hỏi không hợp lệ.');
        $st=qp_db()->prepare("UPDATE cds_quiz_sessions SET current_index=? WHERE code=? AND status='open'");
        $st->execute([$index,$code]);echo json_encode(['ok'=>true,'index'=>$index]);exit;
    } catch (Throwable $e) { http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit; }
}
if (($_GET['api']??'')==='state') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $session=qp_session($code);
    if (!$session) { http_response_code(404);echo json_encode(['ok'=>false]);exit; }
    $index=min(max(0,(int)$session['current_index']),max(0,count($questions)-1));
    $st=qp_db()->prepare('SELECT COUNT(*) FROM cds_quiz_answers WHERE session_code=? AND question_index=?');
    $st->execute([$code,$index]);
    echo json_encode(['ok'=>true,'index'=>$index,'total'=>count($questions),'answered'=>(int)$st->fetchColumn(),'students'=>count($roster),'status'=>$session['status'],'question'=>$questions[$index]??null],JSON_UNESCAPED_UNICODE);exit;
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Màn hình câu hỏi · <?=$code?></title>
<style>*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 50% 25%,#174e94,#061633 65%);color:#fff;font:20px system-ui,Arial;min-height:100vh}header{display:flex;justify-content:space-between;align-items:center;padding:18px 30px;background:#061633a8;border-bottom:3px solid #ffd530}a{color:#d8eaff}main{max-width:1200px;margin:auto;padding:4vh 25px;text-align:center}.number{color:#ffd530;font-weight:900;letter-spacing:.08em}.question{font-size:clamp(28px,4vw,56px);font-weight:850;line-height:1.25;margin:25px auto 35px}.choices{display:grid;grid-template-columns:1fr 1fr;gap:16px}.choice{background:#ffffff17;border:2px solid #ffffff55;border-radius:16px;padding:22px;text-align:left;font-size:clamp(19px,2.3vw,31px);min-height:100px}.choice b{color:#ffd530}.choice.right{border-color:#54d789;background:#125d44}.tools{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:30px}button{border:0;border-radius:11px;background:#ffd530;color:#122440;padding:12px 20px;font:inherit;font-weight:800;cursor:pointer}button:disabled{opacity:.4}.secondary{background:#d8eaff}.muted{color:#bed0e5;font-size:17px}.hide{display:none}@media(max-width:650px){.choices{grid-template-columns:1fr}header{padding:12px}.choice{min-height:auto}}</style></head><body>
<header><div><a href="<?=BASE_URL?>hoclieu_game_quiz.php?set=<?=e(rawurlencode((string)$session['set_id']))?>">← Quản lý</a> · Thẻ giấy <b><?=e($code)?></b></div><span id="counter">Đang tải…</span></header>
<main><div class="number" id="number"></div><div class="question" id="question"></div><img id="image" alt="Hình minh họa câu hỏi" class="hide" style="max-width:100%;max-height:35vh;border-radius:12px;margin-bottom:20px"><div class="choices" id="choices"></div><p class="muted hide" id="explanation"></p><div class="tools"><button class="secondary" id="prev">← Câu trước</button><button id="next">Câu tiếp →</button><button class="secondary" id="reveal">Hiện đáp án</button><button class="secondary" id="fullscreen">Toàn màn hình</button></div><p class="muted" id="status">Điện thoại của giáo viên mở máy quét cùng mã lượt chơi; khi chuyển câu tại đây, máy quét sẽ tự theo câu mới.</p></main>
<script>
const code=<?=json_encode($code,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,csrf=<?=json_encode($csrf,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,$=id=>document.getElementById(id);
let state=null,revealed=false;
async function refresh(){try{const r=await fetch(location.pathname+'?code='+encodeURIComponent(code)+'&api=state',{cache:'no-store'}),s=await r.json();if(!s.ok)throw Error('Lượt chơi không còn mở');if(!state||state.index!==s.index)revealed=false;state=s;$('number').textContent='CÂU '+(s.index+1)+' / '+s.total;$('question').textContent=s.question?.text||'Chưa có câu hỏi';$('image').classList.toggle('hide',!s.question?.image);if(s.question?.image)$('image').src=s.question.image;$('explanation').classList.toggle('hide',!revealed||!s.question?.explanation);$('explanation').textContent=s.question?.explanation||'';$('choices').innerHTML='';for(const letter of ['A','B','C','D']){let el=document.createElement('div');el.className='choice'+(revealed&&s.question?.key===letter?' right':'');let b=document.createElement('b');b.textContent=letter+'. ';el.appendChild(b);el.append(document.createTextNode(s.question?.choices?.[letter]||''));$('choices').appendChild(el)}$('counter').textContent=s.answered+' / '+s.students+' đã trả lời';$('status').textContent=s.status==='open'?'Học sinh xoay thẻ để đáp án ở cạnh trên, giáo viên quét bằng điện thoại.':'Lượt chơi đã đóng.';$('prev').disabled=s.status!=='open'||s.index<=0;$('next').disabled=s.status!=='open'||s.index>=s.total-1;$('reveal').textContent=revealed?'Ẩn đáp án':'Hiện đáp án'}catch(e){$('status').textContent='Không cập nhật được: '+e.message}}
async function move(i){if(!state)return;let body=new URLSearchParams({code,csrf,index:String(i)});let r=await fetch(location.pathname+'?code='+encodeURIComponent(code),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body}),result=await r.json();if(!result.ok){$('status').textContent=result.message||'Không chuyển được câu';return}await refresh()}
$('prev').onclick=()=>move(state.index-1);$('next').onclick=()=>move(state.index+1);$('reveal').onclick=()=>{revealed=!revealed;refresh()};$('fullscreen').onclick=()=>document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen();refresh();setInterval(refresh,2500);
</script></body></html>
