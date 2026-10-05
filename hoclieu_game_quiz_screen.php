<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
require_login();qp_schema();
$code=trim((string)($_GET['code']??$_POST['code']??''));
$session=qp_session($code);
if (!$session || !in_array($session['mode'],['paper','computer'],true) || (!qp_admin() && $session['owner_id']!==qp_owner())) { http_response_code(403); exit('Không có quyền xem lượt chơi giấy.'); }
// Chỉ đưa lượt chưa chơi về màn chào; giữ nguyên lượt đã có câu trả lời.
if ($_SERVER['REQUEST_METHOD']==='GET' && empty($_GET['api']) && $session['status']==='open' && $session['phase']==='question' && (int)$session['current_index']===0) {
    qp_db()->prepare("UPDATE cds_quiz_sessions SET phase='welcome',show_correct=0,show_graph=0 WHERE code=? AND phase='question' AND current_index=0 AND NOT EXISTS (SELECT 1 FROM cds_quiz_answers WHERE session_code=?)")->execute([$code,$code]);
    $session=qp_session($code);
}
require_once __DIR__.'/includes/csdl_store.php';
$classMap=array_column(csdl_classes_all(),'name','id');
$classIds=json_decode((string)($session['class_ids_json']??''),true) ?: [$session['class_id']];
$classLabel=implode(' + ',array_map(static fn($id)=>$classMap[(string)$id]??(string)$id,$classIds));
$quizSet=qp_set((string)$session['set_id']);
$subject=(string)($quizSet['category']??'');$subjectIcon='🎯';
foreach(['toán'=>'📐','vật lý'=>'⚛️','hóa'=>'🧪','sinh'=>'🧬','ngữ văn'=>'📖','lịch sử'=>'🏛️','địa'=>'🌏','tiếng anh'=>'🌐','ngoại ngữ'=>'🌐','tin'=>'💻','công nghệ'=>'⚙️','giáo dục công dân'=>'⚖️','kinh tế'=>'⚖️','thể'=>'🏅','âm nhạc'=>'🎵','mỹ thuật'=>'🎨','mĩ thuật'=>'🎨','trải nghiệm'=>'🧭','khoa học'=>'🔬'] as $keyword=>$icon){if(mb_stripos($subject,$keyword,0,'UTF-8')!==false){$subjectIcon=$icon;break;}}
$audioSettings=qp_audio_settings();$computer=$session['mode']==='computer';
$questions=json_decode((string)$session['questions_json'],true) ?: [];
$roster=json_decode((string)($session['roster_json']??''),true) ?: [];
if (empty($_SESSION['qp_screen_csrf'])) $_SESSION['qp_screen_csrf']=bin2hex(random_bytes(24));
$csrf=(string)$_SESSION['qp_screen_csrf'];
// Giải phóng khóa phiên để màn chiếu và điện thoại không chặn nhau.
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new RuntimeException('Phiên không hợp lệ.');
        if ($session['status']!=='open') throw new RuntimeException('Lượt chơi đã đóng.');
        $action=(string)($_POST['action']??'move');
        if ($action==='activity') {
            // Chỉ thao tác thật mới cập nhật thời điểm hoạt động.
        } elseif ($action==='start') {
            qp_db()->prepare("UPDATE cds_quiz_sessions SET phase=?,show_correct=0,show_graph=0 WHERE code=? AND status='open' AND phase='welcome'")->execute([$computer?'question':'scanning',$code]);
        } elseif ($action==='move') {
            if($session['phase']==='welcome')throw new RuntimeException('Hãy bấm Bắt đầu trước khi chuyển câu.');
            $index=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
            if ($index===false || $index<0 || $index>=count($questions)) throw new RuntimeException('Câu hỏi không hợp lệ.');
            $st=qp_db()->prepare("UPDATE cds_quiz_sessions SET current_index=?,phase='question',show_correct=0,show_graph=0 WHERE code=? AND status='open'");
            $st->execute([$index,$code]);
        } elseif ($action==='phase') {
            if($session['phase']==='welcome')throw new RuntimeException('Hãy bấm Bắt đầu trước khi điều khiển câu hỏi.');
            $phase=(string)($_POST['phase']??'');
            if (!in_array($phase,['question','scanning','results'],true)) throw new RuntimeException('Trạng thái không hợp lệ.');
            $st=qp_db()->prepare("UPDATE cds_quiz_sessions SET phase=? WHERE code=? AND status='open'");
            $st->execute([$phase,$code]);
        } elseif ($action==='finish') {
            if (!$questions || (int)$session['current_index']!==count($questions)-1 || $session['phase']!=='results' || empty($session['show_correct'])) throw new RuntimeException('Hãy dừng quét và công bố kết quả câu cuối trước khi kết thúc.');
            qp_db()->prepare("UPDATE cds_quiz_sessions SET phase='finished',show_correct=1,current_index=? WHERE code=? AND status='open'")->execute([$computer?count($questions):(int)$session['current_index'],$code]);
        } elseif (in_array($action,['show_correct','show_graph','show_names'],true)) {
            $value=($_POST['value']??'')==='1'?1:0;
            $resetGraph=$action==='show_correct'&&$value===1?',show_graph=0':'';
            $st=qp_db()->prepare("UPDATE cds_quiz_sessions SET $action=?$resetGraph WHERE code=? AND status='open'");
            $st->execute([$value,$code]);
        } elseif ($action==='clear') {
            $fresh=qp_session($code);
            if (!$fresh || $fresh['status']!=='open') throw new RuntimeException('Lượt chơi đã đóng.');
            $st=qp_db()->prepare('DELETE FROM cds_quiz_answers WHERE session_code=? AND question_index=?');
            $st->execute([$code,(int)$fresh['current_index']]);
            qp_db()->prepare("UPDATE cds_quiz_sessions SET show_correct=0,show_graph=0,phase='scanning' WHERE code=?")->execute([$code]);
        } else throw new RuntimeException('Thao tác không hợp lệ.');
        qp_touch($code);echo json_encode(['ok'=>true]);exit;
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
    echo json_encode(['ok'=>true,'index'=>$index,'total'=>count($questions),'answered'=>count($responses),'students'=>count($roster),'status'=>$session['status'],'phase'=>$session['phase'],'computer'=>$computer,'show_correct'=>(bool)$session['show_correct'],'show_graph'=>(bool)$session['show_graph'],'show_names'=>(bool)$session['show_names'],'graph'=>$graph,'responses'=>$responses,'roster'=>$names,'question'=>$questions[$index]??null],JSON_UNESCAPED_UNICODE);exit;
}
if (($_GET['api']??'')==='ranking') {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    $session=qp_session($code);
    if (!$session || ($session['phase']!=='finished' && !($session['phase']==='results' && !empty($session['show_correct']) && !empty($session['show_graph'])))) { http_response_code(409);echo json_encode(['ok'=>false]);exit; }
    $scores=[];foreach ($roster as $id=>$name) $scores[(string)$id]=['name'=>(string)$name,'score'=>0,'answered'=>0];
    $st=qp_db()->prepare('SELECT student_id,question_index,answer FROM cds_quiz_answers WHERE session_code=? AND question_index<=?');$st->execute([$code,$session['phase']==='finished'?count($questions)-1:(int)$session['current_index']]);
    foreach ($st as $row) { $id=(string)$row['student_id'];$index=(int)$row['question_index'];if (!isset($scores[$id],$questions[$index])) continue;$scores[$id]['answered']++;if (qp_answer_correct($questions[$index],(string)$row['answer'])) $scores[$id]['score']++; }
    $ranking=array_values($scores);usort($ranking,static fn($a,$b)=>($b['score']<=>$a['score']) ?: ($b['answered']<=>$a['answered']) ?: strcmp($a['name'],$b['name']));
    echo json_encode(['ok'=>true,'total'=>count($questions),'ranking'=>$ranking],JSON_UNESCAPED_UNICODE);exit;
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Màn hình câu hỏi · <?=$code?></title>
<style>*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 50% 25%,#174e94,#061633 65%);color:#fff;font:20px system-ui,Arial;min-height:100vh}header{display:flex;justify-content:space-between;align-items:center;padding:18px 30px;background:#061633a8;border-bottom:3px solid #ffd530}a{color:#d8eaff}main{max-width:1200px;margin:auto;padding:3vh 25px;text-align:center}.number{color:#ffd530;font-weight:900;letter-spacing:.08em}.question{font-size:clamp(28px,4vw,56px);font-weight:850;line-height:1.25;margin:20px auto 25px}.choices{display:grid;grid-template-columns:1fr 1fr;gap:16px}.choice{background:#ffffff17;border:2px solid #ffffff55;border-radius:16px;padding:22px;text-align:left;font-size:clamp(19px,2.3vw,31px);min-height:90px}.choice b{color:#ffd530}.choice.right{border-color:#54d789;background:#125d44}.tools{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:24px}button{border:0;border-radius:11px;background:#ffd530;color:#122440;padding:10px 16px;font:inherit;font-weight:800;cursor:pointer}button:disabled{opacity:.4}.secondary{background:#d8eaff}.muted{color:#bed0e5;font-size:17px}.hide{display:none!important}.roster{display:flex;flex-wrap:wrap;gap:7px;justify-content:center;margin:22px auto}.roster span{font-size:15px;padding:6px 10px;border-radius:20px;background:#ffffff22;color:#c7d5ea}.roster span.done{background:#2eaa71;color:#fff}.graph{max-width:680px;margin:25px auto;text-align:left}.graph-row{display:flex;gap:10px;align-items:center;margin:9px 0}.graph-row b{width:30px}.graph-row .bar{height:28px;border-radius:7px;background:#46a5e3;min-width:2px}.graph-row strong{min-width:24px}@media(max-width:650px){.choices{grid-template-columns:1fr}header{padding:12px}.choice{min-height:auto}}body{background:#090b16;color:#fff}.prompt-stage{display:flex;gap:18px;align-items:stretch}.prompt-stage .question{flex:1}.prompt-stage img:not(.hide){width:min(38%,420px);max-height:35vh;object-fit:contain;background:#fff;padding:12px;border-radius:18px}@media(max-width:700px){.prompt-stage{flex-direction:column}.prompt-stage img:not(.hide){width:100%}}header{background:#090b16;border-color:#f4c54a}.number{font-size:19px}.question{background:linear-gradient(125deg,#47115f,#320d47);border:1px solid #a75ab9;border-radius:24px;padding:clamp(24px,4vw,50px);min-height:120px;display:flex;align-items:center;justify-content:center;text-wrap:balance;box-shadow:0 20px 55px #0005}.choices{margin-top:22px}.choice{position:relative;min-height:130px;display:flex;align-items:center;border:0;border-radius:18px;padding:25px 30px;font-weight:700;box-shadow:inset 0 -8px #0003,0 10px 25px #0004}.choice:nth-child(1){background:#a9a900}.choice:nth-child(2){background:#8b46cf}.choice:nth-child(3){background:#e86812}.choice:nth-child(4){background:#169f96}.choice b{display:grid;place-items:center;min-width:56px;height:56px;margin-right:18px;background:#0005;color:white;border-radius:12px}.choice.right{outline:6px solid #44e5a0;background:#0e8657}.tools{background:#0b0b10;padding:15px;border-radius:16px}.tools button{font-size:16px}.graph{background:#ffffff12;padding:18px;border-radius:18px}.roster{max-height:18vh;overflow:auto}@media(min-width:1000px){main{max-width:1500px}.choices{grid-template-columns:repeat(4,1fr)}.choice{min-height:220px;flex-direction:column;align-items:flex-start;justify-content:space-between}.choice b{margin-right:0}}@media(max-width:650px){.choice{min-height:80px}}
.published .prompt-stage,.published .choices,.published #video,.published #audio,.published #roster,.published #graph{display:none!important}.published .score-board{min-height:55vh}.score-board{margin:25px auto;padding:22px;border-radius:22px;background:linear-gradient(125deg,#123960,#29204e);border:2px solid #ffd76a;text-align:left;box-shadow:0 18px 55px #0008}.score-board h2{color:#ffd76a;margin:0 0 12px;text-align:center}.score-counts{font-weight:900;text-align:center;font-size:clamp(19px,2.8vw,32px);margin-bottom:14px}.score-groups{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.score-group{padding:15px;border-radius:14px;background:#ffffff18}.score-group h3{margin:0 0 10px}.score-group.correct{border:2px solid #3fdb9c}.score-group.incorrect{border:2px solid #ff9980}.score-group.missing{border:2px solid #93aaca}.score-group span{display:inline-block;margin:3px;padding:6px 10px;border-radius:16px;background:#ffffff20;font-size:16px}@media(max-width:700px){.score-groups{grid-template-columns:1fr}}
.roster span.correct{background:#168651;color:#fff;border:2px solid #4cffaf}.roster span.wrong{background:#a9313b;color:#fff;border:2px solid #ff9b9b}.choice.wrong{outline:4px solid #ff7378;background:#932f3e}.finished .tools button:not(#fullscreen){display:none}.finished .tools{background:transparent}.finished .prompt-stage,.finished .choices,.finished #video,.finished #audio,.finished #roster,.finished #graph,.finished #scoreBoard,.finished #explanation{display:none!important}.ranking-board{position:relative;overflow:hidden;margin:20px auto;padding:30px;border-radius:24px;background:linear-gradient(135deg,#152968,#442679 60%,#7d3e91);border:2px solid #ffd86a;box-shadow:0 25px 60px #0008}.ranking-board h1{color:#ffe18a;font-size:clamp(29px,4vw,52px);margin:10px}.ranking-board>p{color:#dbe5ff}.podium{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:end;margin:26px auto;max-width:930px}.podium-card{padding:22px 14px;border-radius:20px;background:#ffffff22;border:2px solid #ffffff55;min-height:175px}.podium-card.first{min-height:230px;background:#f6bb3829;border-color:#ffd45f}.podium-card .medal{display:block;font-size:55px}.podium-card strong{display:block;font-size:clamp(18px,2vw,27px);margin:8px 0}.podium-card small{font-size:16px;color:#ffeba9}.ranking-list{max-width:780px;margin:20px auto;text-align:left}.ranking-row{display:flex;justify-content:space-between;gap:12px;padding:11px 15px;border-radius:10px;background:#ffffff16;margin:6px 0}.confetti{position:absolute;top:-20px;width:9px;height:16px;pointer-events:none;animation:confetti-fall 4s linear infinite}@keyframes confetti-fall{to{transform:translateY(75vh) rotate(740deg);opacity:0}}@media(max-width:700px){.podium{grid-template-columns:1fr}.podium-card,.podium-card.first{min-height:0}}
.welcome-board{margin:3vh auto;padding:clamp(25px,5vw,70px);max-width:1200px;border:1px solid #b49bff88;border-radius:32px;background:radial-gradient(circle at 10% 10%,#ad6aef55,transparent 45%),linear-gradient(135deg,#1c235b,#4e2075);box-shadow:0 25px 80px #0006}.welcome-icon{font-size:80px}.welcome-eyebrow{color:#ffe388;letter-spacing:.18em;font-weight:900}.welcome-board h1{font-size:clamp(32px,5vw,68px);line-height:1.2;margin:20px 0}.welcome-tags{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}.welcome-tags span{background:#ffffff20;border:1px solid #ffffff44;padding:12px 18px;border-radius:40px;font-weight:800}.waiting{color:#e1d7fb;line-height:1.5}.welcome #number,.welcome .prompt-stage,.welcome #choices,.welcome #roster,.welcome #graph,.welcome #scoreBoard,.welcome #rankingBoard,.welcome #explanation,.welcome #video,.welcome #audio{display:none!important}.welcome .tools button:not(#enableMusic):not(#fullscreen){display:none}.welcome-board:not(.hide){animation:welcomeIn .5s ease-out}@keyframes welcomeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}@media(max-width:650px){.welcome-tags span{font-size:14px;padding:9px 12px}}
body #roster,body .tools{display:none!important}#status{font-size:15px}#startGame{display:none!important}.screen-actions{position:fixed;right:12px;top:12px;z-index:10;opacity:1;transition:opacity .2s}.screen-actions:hover,.screen-actions:focus-within{opacity:1}.screen-actions button{font-size:13px;padding:8px}body.welcome .welcome-board{margin-top:6vh}
.answer-board{margin:22px auto;padding:24px;border-radius:20px;background:#125949;border:2px solid #65efb8;text-align:left;white-space:pre-wrap;font-size:clamp(20px,2.5vw,32px)}.answer-board h2{margin:0 0 12px;color:#b9ffe3}.answer-board #explanation{font-size:clamp(18px,2vw,26px);line-height:1.5}.welcome #answerBoard,.finished #answerBoard{display:none!important}
body{background:radial-gradient(ellipse at 10% 0%,#173e6f 0,transparent 50%),radial-gradient(ellipse at 95% 70%,#402665 0,transparent 55%),#0a142e;background-attachment:fixed}main{max-width:1320px;margin:auto;padding:24px 32px 70px}.subject-pill{display:inline-flex;align-items:center;gap:10px;padding:10px 22px;border:1px solid #76bdfa55;border-radius:30px;background:#193a62;color:#c9e9ff;font-weight:800;font-size:20px;margin-bottom:18px}.number{color:#91cfff;font-weight:900;letter-spacing:.13em}.prompt-stage{padding:26px 32px;margin:15px auto 24px;border-radius:24px;background:linear-gradient(135deg,#173653,#232a58);border:1px solid #80b7e34d;box-shadow:0 15px 40px #0003}.question{line-height:1.35;white-space:pre-wrap}.choices{gap:16px}.choice{border-radius:18px;text-align:left;padding:22px;border:1px solid #ffffff35;box-shadow:0 8px 20px #0002}.choice:nth-child(1){background:#254b87}.choice:nth-child(2){background:#663c79}.choice:nth-child(3){background:#226957}.choice:nth-child(4){background:#885328}.choice b{display:inline-block;margin-right:10px;color:#fff0b3}.choice.right{background:#116c48!important;border:3px solid #6cffb4;outline:none}.choice.wrong{outline:none;opacity:.5;background:#273346}.answer-board{box-shadow:0 16px 40px #0003;background:linear-gradient(125deg,#125c50,#18395d)}.screen-actions button{background:#173653e8;border:1px solid #ffffff55;color:#fff}.welcome .subject-pill,.finished .subject-pill,.published .subject-pill{display:none}.published #answerBoard{display:none}.answer-only #choices,.answer-only #graph,.answer-only #counter,.answer-only #status{display:none!important}.score-board{max-width:1200px}.finished #counter{display:none}@media(max-width:700px){main{padding:18px 14px 65px}.prompt-stage{padding:20px}.subject-pill{font-size:16px}.choice{padding:16px}}

html,body{height:100%;overflow:hidden}header{height:58px;padding:10px 22px;font-size:16px}main{height:calc(100dvh - 96px);max-width:1400px;padding:12px 22px;overflow:hidden}#fitContent{transform-origin:top left;display:flow-root}.welcome-board{margin:0 auto;padding:22px 30px}.welcome-icon{font-size:clamp(35px,6vh,65px)}.welcome-board h1{font-size:clamp(28px,4.7vh,52px);margin:12px 0}.welcome-tags span{padding:8px 15px}.welcome-board .waiting{margin:12px}.join-card{display:flex;align-items:center;justify-content:center;gap:24px;margin:18px auto 0;padding:16px 22px;border-radius:20px;background:#ffffff16;width:fit-content;max-width:100%;text-align:left}.join-card #joinQr{background:#fff;padding:10px;border-radius:12px;flex-shrink:0}.join-card strong{font-size:24px;color:#ffe59b}.join-card p{margin:10px 0}.join-card a{font-size:14px;overflow-wrap:anywhere}.join-card small{display:block;margin-top:10px;color:#b7d9f4}.prompt-stage{padding:18px 24px;margin:10px auto 16px}.question{font-size:clamp(24px,4.5vh,48px);margin:8px auto}.choice{padding:16px;font-size:clamp(18px,3vh,28px);min-height:0}.subject-pill{margin:0 0 10px;padding:7px 16px;font-size:17px}.answer-board{margin:14px auto;padding:18px;font-size:clamp(20px,3.3vh,30px)}.answer-board h2{font-size:25px}.answer-board #explanation{margin:12px 0 0}.score-board{margin:0 auto;padding:16px}.published .score-board{min-height:0}.score-board h2{font-size:26px;margin-bottom:8px}.score-counts{font-size:24px;margin-bottom:10px}.score-group{padding:10px;max-height:23vh;overflow:auto}.score-group h3{font-size:18px;margin-bottom:6px}.score-group span{font-size:14px;padding:4px 8px;margin:2px}.live-leaders{margin-top:12px;padding-top:10px;border-top:1px solid #ffffff35}.live-leaders h3{margin:0;text-align:center;color:#ffe59b;font-size:24px}.live-leaders .podium{margin:12px auto 0;gap:12px;align-items:stretch}.live-leaders .podium-card,.live-leaders .podium-card.first{min-height:0;padding:12px}.live-leaders .medal{font-size:42px}.live-leaders strong{font-size:22px;margin:5px 0}.podium-card.second{border-color:#d5e8ff;background:#a6c6ed20}.podium-card.third{border-color:#e5a77a;background:#d0854628}.live-leaders #leaderStatus{font-size:14px;margin:8px 0 0;text-align:center;color:#cbdfff}.ranking-board{margin:0 auto;padding:18px}.ranking-board h1{font-size:36px}.ranking-board .podium{margin:14px auto}.ranking-board .podium-card{min-height:0;padding:16px}.ranking-board .podium-card.first{min-height:0}.ranking-list{max-height:24vh;overflow:auto;margin:12px auto}.ranking-row{padding:7px 12px;font-size:17px}#status{margin:8px 0}.published #number,.finished #number{display:none}@media(max-width:700px){.join-card{gap:12px;padding:12px}.join-card strong{font-size:18px}.live-leaders .podium,.ranking-board .podium{grid-template-columns:repeat(3,minmax(0,1fr))}.live-leaders strong{font-size:16px}.score-groups{grid-template-columns:repeat(3,minmax(0,1fr))}.score-group span{font-size:12px}}
</style></head><body class="<?=($session['phase']==='welcome'?'welcome':'')?>">
<header><div><a href="<?=BASE_URL?>hoclieu_game_quiz.php?set=<?=e(rawurlencode((string)$session['set_id']))?>">← Quản lý</a> · <?=$computer?'Chơi trên máy':'Thẻ giấy'?> <b><?=e($code)?></b></div><span id="counter">Đang tải…</span></header>
<main><div id="fitContent"><section id="welcomeBoard" class="welcome-board"><div class="welcome-icon"><?=e($subjectIcon)?></div><p class="welcome-eyebrow">HỎI NHANH · ĐÁP GỌN</p><h1><?=e((string)($quizSet['title']??'Cùng khám phá kiến thức'))?></h1><div class="welcome-tags"><span>📚 <?=e((string)($quizSet['category']??'Kiến thức tổng hợp'))?></span><span>🎓 <?=e(($quizSet['grade_scope']??'')==='all'?'Tất cả khối':(!empty($quizSet['grade_scope'])?'Khối '.$quizSet['grade_scope']:'Chưa chọn khối'))?></span><span>🏫 Lớp <?=e($classLabel)?></span><span>✨ <?=count($questions)?> câu hỏi</span></div><p><?=e((string)($quizSet['intro']??''))?></p><p class="waiting">Sẵn sàng chinh phục kiến thức? Chờ giáo viên bấm Bắt đầu.</p><?php if($computer): ?><div class="join-card"><div id="joinQr" aria-label="Mã QR vào lượt chơi"></div><div><strong>📱 Quét mã để vào chơi</strong><p>Mã lượt: <b><?=e($code)?></b></p><a id="joinLink" href="<?=e(BASE_URL.'vaochoi.php?code='.rawurlencode($code))?>"><?=e(BASE_URL.'vaochoi.php?code='.rawurlencode($code))?></a><small id="qrStatus">Đang tạo mã QR…</small></div></div><?php endif; ?><button type="button" id="startGame">▶ Bắt đầu</button></section><div class="subject-pill"><?=e($subjectIcon)?> <?=e($subject!==''?$subject:'Khám phá tri thức')?></div><div class="number" id="number"></div><div class="prompt-stage"><div class="question" id="question"></div><img id="image" alt="Hình minh họa câu hỏi" class="hide" style="max-width:100%;max-height:35vh;border-radius:12px;margin-bottom:20px"></div><iframe id="video" class="hide" title="Video câu hỏi" allowfullscreen style="display:block;width:min(100%,720px);aspect-ratio:16/9;border:0;border-radius:14px;margin:15px auto"></iframe><audio id="audio" class="hide" controls style="width:min(100%,600px);margin:12px auto"></audio><div class="choices" id="choices"></div><div class="roster" id="roster"></div><div class="graph hide" id="graph"></div><section class="score-board hide" id="scoreBoard"><h2>✨ Công bố kết quả</h2><div class="score-counts" id="scoreCounts"></div><div class="score-groups" id="scoreGroups"></div><section class="live-leaders"><h3>🎉 Tốp 3 hiện tại · Chúc mừng các em!</h3><div class="podium" id="livePodium"></div><p id="leaderStatus" role="status"></p></section></section><section class="ranking-board hide" id="rankingBoard" aria-live="polite"><h1>🎉 Chúc mừng các em!</h1><p>Hoàn thành lượt chơi · Vinh danh ba vị trí cao nhất</p><div class="podium" id="podium"></div><div class="ranking-list" id="rankingList"></div></section><section class="answer-board hide" id="answerBoard"><h2>✅ Đáp án đúng</h2><div id="answerText"></div><p id="explanation" class="hide"></p></section><div class="tools"><button class="secondary" id="prev">← Câu trước</button><button id="scan">Bắt đầu quét</button><button class="secondary" id="results">Dừng quét</button><button class="secondary" id="reveal">Hiện đáp án</button><button class="secondary" id="showGraph">Hiện biểu đồ</button><button class="secondary" id="showNames">Ẩn danh sách</button><button id="next">Câu tiếp →</button><button id="finish" class="secondary">🏆 Kết thúc & xếp hạng</button><button class="secondary" id="clear">Xóa đáp án câu này</button><button class="secondary" id="enableMusic">🔊 Bật âm thanh</button><button class="secondary" id="fullscreen">Toàn màn hình</button></div><p class="muted" id="status">Mở máy quét trên điện thoại cùng mã lượt chơi.</p></div></main>
<div class="screen-actions"><button id="screenSound" class="secondary">🔊 Âm thanh</button><button id="screenFull" class="secondary">⛶ Toàn màn hình</button></div>
<?php if($computer):?><script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script><?php endif;?><script>
const code=<?=json_encode($code,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,csrf=<?=json_encode($csrf,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,$=id=>document.getElementById(id);

const musicSettings=<?=json_encode($audioSettings,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
let musicEnabled=true,musicBlocked=false,musicState='',backgroundMusic=new Audio(),effectMusic=new Audio();
backgroundMusic.loop=true;backgroundMusic.volume=.25;effectMusic.volume=.7;
function musicEffect(key){effectMusic.pause();effectMusic.onended=null;const url=musicSettings[key];if(!musicEnabled||!url)return;effectMusic.src=url;effectMusic.currentTime=0;effectMusic.play().catch(e=>{musicBlocked=e.name==='NotAllowedError';$('screenSound').textContent=musicBlocked?'🔊 Bấm để bật nhạc':'⚠ Kiểm tra tệp nhạc hiệu ứng';});}
function musicLoop(key){if(musicBlocked)return;const url=musicSettings[key]||'';if(backgroundMusic.dataset?.key===key)return;backgroundMusic.pause();backgroundMusic.setAttribute('data-key',key);if(musicEnabled&&url){backgroundMusic.src=url;backgroundMusic.play().catch(e=>{musicBlocked=e.name==='NotAllowedError';backgroundMusic.removeAttribute('data-key');$('screenSound').textContent=e.name==='NotAllowedError'?'🔊 Bấm để bật nhạc':'⚠ Nhạc không tải được';});}}
function unlockMusic(){musicEnabled=musicBlocked?true:!musicEnabled;musicBlocked=false;$('enableMusic').textContent=musicEnabled?'🔇 Tắt âm thanh':'🔊 Bật âm thanh';$('screenSound').textContent=$('enableMusic').textContent;backgroundMusic.removeAttribute('data-key');if(musicEnabled)musicLoop(state?.phase==='welcome'?'welcome':'background');else{backgroundMusic.pause();effectMusic.pause()}}
function updateMusic(s,old){if(!musicEnabled)return;if(!old||s.phase==='welcome'){musicLoop(s.phase==='welcome'?'welcome':'background');return}if(old.phase==='welcome'&&s.phase!=='welcome')musicEffect('start');else if(s.index!==old.index)musicEffect('start');else if(!old.show_correct&&s.show_correct){musicEffect('answer');if(s.show_graph){if(musicSettings.answer)effectMusic.onended=()=>musicEffect('score');else musicEffect('score')}}else if(!old.show_graph&&s.show_graph)musicEffect('score');else if(old.phase==='scanning'&&s.phase==='results')musicEffect('timeout');if(s.phase==='finished'){backgroundMusic.pause();if(old.phase!=='finished')musicEffect('score')}else musicLoop(s.phase==='welcome'?'welcome':'background');musicState=s.phase;}

function answerCorrect(q,answer){if(!q||!answer)return false;const normalize=v=>String(v).trim().replace(/\s+/g,' ').toLocaleLowerCase('vi');if(q.type==='fill')return(q.fill_answers||[]).some(v=>normalize(v)===normalize(answer));if(['multi','match','order'].includes(q.type)){let chosen;try{chosen=JSON.parse(answer)}catch(e){return false}if(!Array.isArray(chosen))return false;const expected=q.type==='multi'?(q.keys||[]):q.type==='match'?(q.pairs||[]).map(p=>p[1]):(q.steps||[]);if(q.type==='multi')return chosen.length===new Set(chosen).size&&JSON.stringify([...chosen].sort())===JSON.stringify([...expected].sort());return JSON.stringify(chosen)===JSON.stringify(expected);}return answer===q.key;}
let state=null,rankingShown=false;
function fitScreen(){const main=document.querySelector('main'),content=$('fitContent');if(!main||!content)return;content.style.transform='none';const width=Math.max(1,main.clientWidth-44),height=Math.max(1,main.clientHeight-24);content.style.width=width+'px';const scale=Math.min(1,height/Math.max(1,content.scrollHeight));content.style.transform='scale('+scale+')';content.style.marginLeft=(width*(1-scale)/2)+'px';}
window.addEventListener('resize',fitScreen);document.fonts?.ready.then(fitScreen);
const qr=$('joinQr');if(qr){if(window.QRCode){new QRCode(qr,{text:$('joinLink').href,width:180,height:180,correctLevel:QRCode.CorrectLevel.M});$('qrStatus').textContent='Quét bằng camera điện thoại';}else $('qrStatus').textContent='Không tải được QR; mở đường dẫn bên trên để vào chơi.';requestAnimationFrame(fitScreen);}
let leadersKey='',leadersBusy=false,leadersAt=0;
async function showLiveLeaders(s){const key=s.index+':'+JSON.stringify(s.responses);if(leadersBusy||(key===leadersKey&&Date.now()-leadersAt<5000))return;leadersBusy=true;try{const response=await fetch(location.pathname+'?code='+encodeURIComponent(code)+'&api=ranking',{cache:'no-store'}),data=await response.json();if(!response.ok||!data.ok)throw Error('Chưa tải được tốp 3');if(!state||state.index!==s.index||state.phase!=='results'||!state.show_graph)return;const board=$('livePodium');board.replaceChildren();for(const position of [1,0,2]){const person=data.ranking[position];if(!person)continue;const card=document.createElement('div'),medal=document.createElement('span'),name=document.createElement('strong'),score=document.createElement('small');card.className='podium-card '+['first','second','third'][position];medal.className='medal';medal.textContent=['🥇','🥈','🥉'][position];name.textContent=person.name;score.textContent='Hạng '+(position+1)+' · '+person.score+' câu đúng';card.append(medal,name,score);board.appendChild(card);}leadersKey=key;leadersAt=Date.now();$('leaderStatus').textContent=data.ranking.length?'Tổng điểm từ đầu lượt đến câu đang công bố':'Chưa có học sinh trong lượt chơi';requestAnimationFrame(fitScreen);}catch(e){$('leaderStatus').textContent=e.message;}finally{leadersBusy=false;}}

async function showRanking(){if(rankingShown)return;try{const response=await fetch(location.pathname+'?code='+encodeURIComponent(code)+'&api=ranking',{cache:'no-store'}),data=await response.json();if(!data.ok)throw Error('Chưa có bảng xếp hạng');rankingShown=true;const podium=$('podium'),list=$('rankingList'),top=data.ranking.slice(0,3);podium.replaceChildren();list.replaceChildren();for(const position of [1,0,2]){const person=top[position];if(!person)continue;const card=document.createElement('div'),medal=document.createElement('span'),name=document.createElement('strong'),score=document.createElement('small');card.className='podium-card '+(position===0?'first':'');medal.className='medal';medal.textContent=['🥇','🥈','🥉'][position];name.textContent=person.name;score.textContent=person.score+' / '+data.total+' điểm';card.append(medal,name,score);podium.appendChild(card)}data.ranking.forEach((person,i)=>{let row=document.createElement('div'),name=document.createElement('span'),score=document.createElement('strong');row.className='ranking-row';name.textContent=(i+1)+'. '+person.name;score.textContent=person.score+' / '+data.total+' điểm';row.append(name,score);list.appendChild(row)});const board=$('rankingBoard');for(let i=0;i<36;i++){const bit=document.createElement('span');bit.className='confetti';bit.style.left=(Math.random()*100)+'%';bit.style.background=['#ffd86a','#6beac2','#fc86c6','#a1c9ff'][i%4];bit.style.animationDelay=(Math.random()*4)+'s';board.appendChild(bit)}requestAnimationFrame(fitScreen)}catch(e){$('status').textContent='Chưa tải được bảng xếp hạng: '+e.message}}
async function refresh(){
  try{
    const r=await fetch(location.pathname+'?code='+encodeURIComponent(code)+'&api=state',{cache:'no-store'}),s=await r.json();
    if(!s.ok)throw Error('Lượt chơi không còn mở');
    updateMusic(s,state);state=s;if(s.status!=='open'&&s.phase!=='finished'){document.body.classList.add('welcome');$('welcomeBoard').classList.remove('hide');$('welcomeBoard').querySelector('.waiting').textContent='Lượt chơi đã đóng. Hãy tạo lượt mới ở trang quản lý.';$('status').textContent='Đã kết thúc lượt chơi';return;}document.body.classList.toggle("welcome",s.phase==="welcome");$('welcomeBoard').classList.toggle("hide",s.phase!=="welcome");$('startGame').disabled=s.status!=='open';
    $('number').textContent='CÂU '+(s.index+1)+' / '+s.total;
    $('question').textContent=s.question?.text||'Chưa có câu hỏi';
    $('image').classList.toggle('hide',!s.question?.image);
    if(s.question?.image)$('image').src=s.question.image;
    for(const media of ['video','audio']){const el=$(media),url=s.question?.[media]||'';el.classList.toggle('hide',!url);if(el.dataset.url!==url){el.dataset.url=url;el.src=url;}}
    $('choices').replaceChildren();
    for(const letter of ['A','B','C','D']){
      let el=document.createElement('div');el.className='choice'+(s.show_correct?(s.question?.key===letter?' right':' wrong'):'');
      let b=document.createElement('b');b.textContent=letter+'. ';
      el.append(b,document.createTextNode(s.question?.choices?.[letter]||''));$('choices').appendChild(el);
    }
    $('roster').classList.toggle('hide',!s.show_names);
    $('roster').replaceChildren();
    if(s.show_names)for(const person of s.roster||[]){
      let el=document.createElement('span'),answer=s.responses?.[person.id]||'';el.className=answer?(answerCorrect(s.question,answer)?'correct':'wrong'):'';
      el.textContent=person.name+(answer?' · '+answer+(answerCorrect(s.question,answer)?' ✓':' ✕'):' · Chưa quét');$('roster').appendChild(el);
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
    const published=s.phase==='results'&&s.show_correct&&s.show_graph;
    const finished=s.phase==='finished';document.body.classList.toggle('answer-only',s.show_correct&&!published&&!finished&&s.phase!=='welcome');document.body.classList.toggle('finished',finished);$('rankingBoard').classList.toggle('hide',!finished);if(finished)showRanking();else{rankingShown=false;$('rankingBoard').querySelectorAll('.confetti').forEach(el=>el.remove())}document.body.classList.toggle('published',published);
    $('scoreBoard').classList.toggle('hide',!published);
    if(published){
      showLiveLeaders(s);
      const groups={correct:[],incorrect:[],missing:[]};
      for(const person of s.roster||[]){const answer=s.responses?.[person.id]||'';groups[!answer?'missing':answerCorrect(s.question,answer)?'correct':'incorrect'].push({name:person.name,answer})}
      $('scoreCounts').textContent=groups.correct.length+' đúng · '+groups.incorrect.length+' sai · '+groups.missing.length+' chưa trả lời';
      $('scoreGroups').replaceChildren();
      for(const [kind,title] of [['correct','✓ Đúng'],['incorrect','✕ Sai'],['missing','— Chưa trả lời']]){
        const section=document.createElement('div'),h=document.createElement('h3');section.className='score-group '+kind;h.textContent=title+' ('+groups[kind].length+')';section.appendChild(h);
        for(const person of groups[kind]){const tag=document.createElement('span');tag.textContent=person.name+(person.answer?' · '+person.answer:'');section.appendChild(tag)}$('scoreGroups').appendChild(section);
      }
    }
    $('answerBoard').classList.toggle('hide',!s.show_correct||published||finished||s.phase==='welcome');
    const q=s.question||{},type=q.type||'single';
    $('answerText').textContent=type==='multi'?(q.keys||[]).map(k=>k+' · '+(q.choices?.[k]||'')).join('\n'):type==='fill'?(q.fill_answers||[]).join(' / '):type==='match'?(q.pairs||[]).map(p=>p.join(' → ')).join('\n'):type==='order'?(q.steps||[]).join(' → '):(q.key||'')+' · '+(q.choices?.[q.key]||'');
    $('explanation').classList.toggle('hide',!s.show_correct||!s.question?.explanation);
    $('explanation').textContent=s.question?.explanation||'';
    $('counter').textContent=s.answered+' / '+s.students+' đã trả lời';
    $('status').textContent=s.status!=='open'?'Lượt chơi đã đóng.':s.phase==='scanning'?'Đang quét thẻ · học sinh giữ đáp án ở cạnh trên.':s.phase==='results'?'Đã dừng quét · kiểm tra kết quả rồi chuyển câu.':'Sẵn sàng cho câu hỏi này.';
    $('prev').disabled=s.status!=='open'||s.index<=0;
    $('next').disabled=s.status!=='open'||s.index>=s.total-1;$('finish').disabled=s.status!=='open'||s.index!==s.total-1||s.phase!=='results'||!s.show_correct;
    $('scan').disabled=s.status!=='open'||s.phase==='scanning';$('scan').textContent=s.phase==='welcome'?'Bắt đầu':'Bắt đầu quét';
    $('results').disabled=s.status!=='open'||s.phase!=='scanning';
    $('reveal').textContent=s.show_correct?'Ẩn đáp án':'Hiện đáp án';
    $('showGraph').textContent=s.show_graph?'Ẩn biểu đồ':'Hiện biểu đồ';
    $('showNames').textContent=s.show_names?'Ẩn danh sách':'Hiện danh sách';requestAnimationFrame(fitScreen);
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
$('next').onclick=()=>state&&control('move',{index:String(state.index+1)});$('finish').onclick=()=>{if(state&&confirm('Kết thúc lượt chơi và công bố bảng xếp hạng?'))control('finish')};
$('scan').onclick=()=>state?.phase==='welcome'?control('start'):control('phase',{phase:'scanning'});
$('results').onclick=()=>control('phase',{phase:'results'});
$('reveal').onclick=()=>state&&control('show_correct',{value:state.show_correct?'0':'1'});
$('showGraph').onclick=()=>state&&control('show_graph',{value:state.show_graph?'0':'1'});
$('showNames').onclick=()=>state&&control('show_names',{value:state.show_names?'0':'1'});
$('clear').onclick=()=>{if(confirm('Xóa toàn bộ đáp án của câu hiện tại để quét lại?'))control('clear')};
$('fullscreen').onclick=()=>document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen();
$('screenSound').textContent='🔇 Tắt âm thanh';$('screenSound').onclick=unlockMusic;
document.addEventListener('pointerdown',event=>{if(event.target.closest?.('#screenSound,#enableMusic'))return;if(musicEnabled&&musicBlocked){musicBlocked=false;backgroundMusic.removeAttribute('data-key');musicLoop(state?.phase==='welcome'?'welcome':'background');}}, {capture:true});$('screenFull').onclick=()=>document.documentElement.requestFullscreen?.();
$('startGame').onclick=()=>{if(!musicEnabled)unlockMusic();control('start')};$('enableMusic').onclick=unlockMusic;
refresh();setInterval(refresh,1200);
</script><?php require __DIR__ . '/includes/game_credit.php'; ?></body></html>
