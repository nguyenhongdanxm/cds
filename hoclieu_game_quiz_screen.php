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
        $action=(string)($_POST['action']??'move');
        if ($action==='move') {
            $index=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
            if ($index===false || $index<0 || $index>=count($questions)) throw new RuntimeException('Câu hỏi không hợp lệ.');
            $st=qp_db()->prepare("UPDATE cds_quiz_sessions SET current_index=?,phase='question',show_correct=0,show_graph=0 WHERE code=? AND status='open'");
            $st->execute([$index,$code]);
        } elseif ($action==='phase') {
            $phase=(string)($_POST['phase']??'');
            if (!in_array($phase,['question','scanning','results'],true)) throw new RuntimeException('Trạng thái không hợp lệ.');
            $st=qp_db()->prepare("UPDATE cds_quiz_sessions SET phase=? WHERE code=? AND status='open'");
            $st->execute([$phase,$code]);
        } elseif (in_array($action,['show_correct','show_graph','show_names'],true)) {
            $value=($_POST['value']??'')==='1'?1:0;
            $st=qp_db()->prepare("UPDATE cds_quiz_sessions SET $action=? WHERE code=? AND status='open'");
            $st->execute([$value,$code]);
        } elseif ($action==='clear') {
            $fresh=qp_session($code);
            if (!$fresh || $fresh['status']!=='open') throw new RuntimeException('Lượt chơi đã đóng.');
            $st=qp_db()->prepare('DELETE FROM cds_quiz_answers WHERE session_code=? AND question_index=?');
            $st->execute([$code,(int)$fresh['current_index']]);
            qp_db()->prepare("UPDATE cds_quiz_sessions SET show_correct=0,show_graph=0,phase='scanning' WHERE code=?")->execute([$code]);
        } else throw new RuntimeException('Thao tác không hợp lệ.');
        echo json_encode(['ok'=>true]);exit;
    } catch (Throwable $e) { http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit; }
}
if (($_GET['api']??'')==='state') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $session=qp_session($code);
    if (!$session) { http_response_code(404);echo json_encode(['ok'=>false]);exit; }
    $index=min(max(0,(int)$session['current_index']),max(0,count($questions)-1));
    $st=qp_db()->prepare('SELECT student_id,answer FROM cds_quiz_answers WHERE session_code=? AND question_index=?');
    $st->execute([$code,$index]);
    $responses=[];$graph=['A'=>0,'B'=>0,'C'=>0,'D'=>0];
    foreach ($st as $row) {
        $id=(string)$row['student_id'];$answer=(string)$row['answer'];
        if (!array_key_exists($id,$roster)) continue;
        $responses[$id]=$answer;if (isset($graph[$answer]))$graph[$answer]++;
    }
    $names=[];foreach ($roster as $id=>$name)$names[]=['id'=>(string)$id,'name'=>(string)$name,'answered'=>isset($responses[(string)$id])];
    echo json_encode(['ok'=>true,'index'=>$index,'total'=>count($questions),'answered'=>count($responses),'students'=>count($roster),'status'=>$session['status'],'phase'=>$session['phase'],'show_correct'=>(bool)$session['show_correct'],'show_graph'=>(bool)$session['show_graph'],'show_names'=>(bool)$session['show_names'],'graph'=>$graph,'responses'=>$responses,'roster'=>$names,'question'=>$questions[$index]??null],JSON_UNESCAPED_UNICODE);exit;
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Màn hình câu hỏi · <?=$code?></title>
<style>*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 50% 25%,#174e94,#061633 65%);color:#fff;font:20px system-ui,Arial;min-height:100vh}header{display:flex;justify-content:space-between;align-items:center;padding:18px 30px;background:#061633a8;border-bottom:3px solid #ffd530}a{color:#d8eaff}main{max-width:1200px;margin:auto;padding:3vh 25px;text-align:center}.number{color:#ffd530;font-weight:900;letter-spacing:.08em}.question{font-size:clamp(28px,4vw,56px);font-weight:850;line-height:1.25;margin:20px auto 25px}.choices{display:grid;grid-template-columns:1fr 1fr;gap:16px}.choice{background:#ffffff17;border:2px solid #ffffff55;border-radius:16px;padding:22px;text-align:left;font-size:clamp(19px,2.3vw,31px);min-height:90px}.choice b{color:#ffd530}.choice.right{border-color:#54d789;background:#125d44}.tools{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:24px}button{border:0;border-radius:11px;background:#ffd530;color:#122440;padding:10px 16px;font:inherit;font-weight:800;cursor:pointer}button:disabled{opacity:.4}.secondary{background:#d8eaff}.muted{color:#bed0e5;font-size:17px}.hide{display:none!important}.roster{display:flex;flex-wrap:wrap;gap:7px;justify-content:center;margin:22px auto}.roster span{font-size:15px;padding:6px 10px;border-radius:20px;background:#ffffff22;color:#c7d5ea}.roster span.done{background:#2eaa71;color:#fff}.graph{max-width:680px;margin:25px auto;text-align:left}.graph-row{display:flex;gap:10px;align-items:center;margin:9px 0}.graph-row b{width:30px}.graph-row .bar{height:28px;border-radius:7px;background:#46a5e3;min-width:2px}.graph-row strong{min-width:24px}@media(max-width:650px){.choices{grid-template-columns:1fr}header{padding:12px}.choice{min-height:auto}}body{background:#090b16;color:#fff}.prompt-stage{display:flex;gap:18px;align-items:stretch}.prompt-stage .question{flex:1}.prompt-stage img:not(.hide){width:min(38%,420px);max-height:35vh;object-fit:contain;background:#fff;padding:12px;border-radius:18px}@media(max-width:700px){.prompt-stage{flex-direction:column}.prompt-stage img:not(.hide){width:100%}}header{background:#090b16;border-color:#f4c54a}.number{font-size:19px}.question{background:linear-gradient(125deg,#47115f,#320d47);border:1px solid #a75ab9;border-radius:24px;padding:clamp(24px,4vw,50px);min-height:120px;display:flex;align-items:center;justify-content:center;text-wrap:balance;box-shadow:0 20px 55px #0005}.choices{margin-top:22px}.choice{position:relative;min-height:130px;display:flex;align-items:center;border:0;border-radius:18px;padding:25px 30px;font-weight:700;box-shadow:inset 0 -8px #0003,0 10px 25px #0004}.choice:nth-child(1){background:#a9a900}.choice:nth-child(2){background:#8b46cf}.choice:nth-child(3){background:#e86812}.choice:nth-child(4){background:#169f96}.choice b{display:grid;place-items:center;min-width:56px;height:56px;margin-right:18px;background:#0005;color:white;border-radius:12px}.choice.right{outline:6px solid #44e5a0;background:#0e8657}.tools{background:#0b0b10;padding:15px;border-radius:16px}.tools button{font-size:16px}.graph{background:#ffffff12;padding:18px;border-radius:18px}.roster{max-height:18vh;overflow:auto}@media(min-width:1000px){main{max-width:1500px}.choices{grid-template-columns:repeat(4,1fr)}.choice{min-height:220px;flex-direction:column;align-items:flex-start;justify-content:space-between}.choice b{margin-right:0}}@media(max-width:650px){.choice{min-height:80px}}
.published .prompt-stage,.published .choices,.published #video,.published #audio,.published #roster,.published #graph,.published #explanation{display:none!important}.published .score-board{min-height:55vh}.score-board{margin:25px auto;padding:22px;border-radius:22px;background:linear-gradient(125deg,#123960,#29204e);border:2px solid #ffd76a;text-align:left;box-shadow:0 18px 55px #0008}.score-board h2{color:#ffd76a;margin:0 0 12px;text-align:center}.score-counts{font-weight:900;text-align:center;font-size:clamp(19px,2.8vw,32px);margin-bottom:14px}.score-groups{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.score-group{padding:15px;border-radius:14px;background:#ffffff18}.score-group h3{margin:0 0 10px}.score-group.correct{border:2px solid #3fdb9c}.score-group.incorrect{border:2px solid #ff9980}.score-group.missing{border:2px solid #93aaca}.score-group span{display:inline-block;margin:3px;padding:6px 10px;border-radius:16px;background:#ffffff20;font-size:16px}@media(max-width:700px){.score-groups{grid-template-columns:1fr}}
</style></head><body>
<header><div><a href="<?=BASE_URL?>hoclieu_game_quiz.php?set=<?=e(rawurlencode((string)$session['set_id']))?>">← Quản lý</a> · Thẻ giấy <b><?=e($code)?></b></div><span id="counter">Đang tải…</span></header>
<main><div class="number" id="number"></div><div class="prompt-stage"><div class="question" id="question"></div><img id="image" alt="Hình minh họa câu hỏi" class="hide" style="max-width:100%;max-height:35vh;border-radius:12px;margin-bottom:20px"></div><iframe id="video" class="hide" title="Video câu hỏi" allowfullscreen style="display:block;width:min(100%,720px);aspect-ratio:16/9;border:0;border-radius:14px;margin:15px auto"></iframe><audio id="audio" class="hide" controls style="width:min(100%,600px);margin:12px auto"></audio><div class="choices" id="choices"></div><div class="roster" id="roster"></div><div class="graph hide" id="graph"></div><section class="score-board hide" id="scoreBoard"><h2>✨ Công bố kết quả</h2><div class="score-counts" id="scoreCounts"></div><div class="score-groups" id="scoreGroups"></div></section><p class="muted hide" id="explanation"></p><div class="tools"><button class="secondary" id="prev">← Câu trước</button><button id="scan">Bắt đầu quét</button><button class="secondary" id="results">Dừng quét</button><button class="secondary" id="reveal">Hiện đáp án</button><button class="secondary" id="showGraph">Hiện biểu đồ</button><button class="secondary" id="showNames">Ẩn danh sách</button><button id="next">Câu tiếp →</button><button class="secondary" id="clear">Xóa đáp án câu này</button><button class="secondary" id="fullscreen">Toàn màn hình</button></div><p class="muted" id="status">Mở máy quét trên điện thoại cùng mã lượt chơi.</p></main>
<script>
const code=<?=json_encode($code,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,csrf=<?=json_encode($csrf,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,$=id=>document.getElementById(id);
let state=null;
async function refresh(){
  try{
    const r=await fetch(location.pathname+'?code='+encodeURIComponent(code)+'&api=state',{cache:'no-store'}),s=await r.json();
    if(!s.ok)throw Error('Lượt chơi không còn mở');
    state=s;
    $('number').textContent='CÂU '+(s.index+1)+' / '+s.total;
    $('question').textContent=s.question?.text||'Chưa có câu hỏi';
    $('image').classList.toggle('hide',!s.question?.image);
    if(s.question?.image)$('image').src=s.question.image;
    for(const media of ['video','audio']){const el=$(media),url=s.question?.[media]||'';el.classList.toggle('hide',!url);if(el.dataset.url!==url){el.dataset.url=url;el.src=url;}}
    $('choices').replaceChildren();
    for(const letter of ['A','B','C','D']){
      let el=document.createElement('div');el.className='choice'+(s.show_correct&&s.question?.key===letter?' right':'');
      let b=document.createElement('b');b.textContent=letter+'. ';
      el.append(b,document.createTextNode(s.question?.choices?.[letter]||''));$('choices').appendChild(el);
    }
    $('roster').classList.toggle('hide',!s.show_names);
    $('roster').replaceChildren();
    if(s.show_names)for(const person of s.roster||[]){
      let el=document.createElement('span');el.className=person.answered?'done':'';
      el.textContent=person.name+(person.answered?' ✓':'');$('roster').appendChild(el);
    }
    $('graph').classList.toggle('hide',!s.show_graph);
    $('graph').replaceChildren();
    if(s.show_graph)for(const letter of ['A','B','C','D']){
      let row=document.createElement('div');row.className='graph-row';
      let label=document.createElement('b');label.textContent=letter;
      let bar=document.createElement('div');bar.className='bar';bar.style.width=(Math.max(2,100*(s.graph?.[letter]||0)/Math.max(1,s.students)))+'%';
      let count=document.createElement('strong');count.textContent=String(s.graph?.[letter]||0);
      row.append(label,bar,count);$('graph').appendChild(row);
    }
    const published=s.phase==='results'&&s.show_correct;
    document.body.classList.toggle('published',published);
    $('scoreBoard').classList.toggle('hide',!published);
    if(published){
      const groups={correct:[],incorrect:[],missing:[]};
      for(const person of s.roster||[]){const answer=s.responses?.[person.id]||'';groups[!answer?'missing':answer===s.question?.key?'correct':'incorrect'].push({name:person.name,answer})}
      $('scoreCounts').textContent=groups.correct.length+' đúng · '+groups.incorrect.length+' sai · '+groups.missing.length+' chưa trả lời';
      $('scoreGroups').replaceChildren();
      for(const [kind,title] of [['correct','✓ Đúng'],['incorrect','✕ Sai'],['missing','— Chưa trả lời']]){
        const section=document.createElement('div'),h=document.createElement('h3');section.className='score-group '+kind;h.textContent=title+' ('+groups[kind].length+')';section.appendChild(h);
        for(const person of groups[kind]){const tag=document.createElement('span');tag.textContent=person.name+(person.answer?' · '+person.answer:'');section.appendChild(tag)}$('scoreGroups').appendChild(section);
      }
    }
    $('explanation').classList.toggle('hide',!s.show_correct||!s.question?.explanation);
    $('explanation').textContent=s.question?.explanation||'';
    $('counter').textContent=s.answered+' / '+s.students+' đã trả lời';
    $('status').textContent=s.status!=='open'?'Lượt chơi đã đóng.':s.phase==='scanning'?'Đang quét thẻ · học sinh giữ đáp án ở cạnh trên.':s.phase==='results'?'Đã dừng quét · kiểm tra kết quả rồi chuyển câu.':'Sẵn sàng cho câu hỏi này.';
    $('prev').disabled=s.status!=='open'||s.index<=0;
    $('next').disabled=s.status!=='open'||s.index>=s.total-1;
    $('scan').disabled=s.status!=='open'||s.phase==='scanning';
    $('results').disabled=s.status!=='open'||s.phase!=='scanning';
    $('reveal').textContent=s.show_correct?'Ẩn đáp án':'Hiện đáp án';
    $('showGraph').textContent=s.show_graph?'Ẩn biểu đồ':'Hiện biểu đồ';
    $('showNames').textContent=s.show_names?'Ẩn danh sách':'Hiện danh sách';
  }catch(e){$('status').textContent='Không cập nhật được: '+e.message}
}
async function control(action,values={}){
  try{
    let body=new URLSearchParams({code,csrf,action,...values});
    let r=await fetch(location.pathname+'?code='+encodeURIComponent(code),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    let result=await r.json();if(!r.ok||!result.ok)throw Error(result.message||'Không thực hiện được');
    await refresh();
  }catch(e){$('status').textContent=e.message}
}
$('prev').onclick=()=>state&&control('move',{index:String(state.index-1)});
$('next').onclick=()=>state&&control('move',{index:String(state.index+1)});
$('scan').onclick=()=>control('phase',{phase:'scanning'});
$('results').onclick=()=>control('phase',{phase:'results'});
$('reveal').onclick=()=>state&&control('show_correct',{value:state.show_correct?'0':'1'});
$('showGraph').onclick=()=>state&&control('show_graph',{value:state.show_graph?'0':'1'});
$('showNames').onclick=()=>state&&control('show_names',{value:state.show_names?'0':'1'});
$('clear').onclick=()=>{if(confirm('Xóa toàn bộ đáp án của câu hiện tại để quét lại?'))control('clear')};
$('fullscreen').onclick=()=>document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen();
refresh();setInterval(refresh,1500);
</script></body></html>
