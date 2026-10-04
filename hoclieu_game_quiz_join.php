<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
if (empty($_SESSION['qp_student_csrf'])) $_SESSION['qp_student_csrf']=bin2hex(random_bytes(24));
$csrf=(string)$_SESSION['qp_student_csrf'];
$code=trim((string)($_REQUEST['code']??''));
$session=preg_match('/^[0-9]{8}$/',$code)?qp_session($code):null;
if ($session && ($session['mode']??'')!=='computer') $session=null;
$set=$session?qp_set((string)$session['set_id']):null;
$questions=$session?(json_decode((string)$session['questions_json'],true)?:[]):[];
$students=$session?(json_decode((string)($session['roster_json']??''),true)?:[]):[];
if ($session && !$students) foreach (csdl_students_all() as $student) if (!empty($student['active']) && (string)($student['class_id']??'')===(string)$session['class_id']) $students[(string)$student['id']]=(string)($student['name']??$student['full_name']??'');
$studentId=(string)($_SESSION['qp_join'][$code]??'');
if (!isset($students[$studentId])) $studentId='';
$pace=(string)($session['pace_mode']??'self');
if (!in_array($pace,['timed','teacher','self'],true)) $pace='self';
$seconds=max(10,min(300,(int)($session['seconds_per_question']??20)));
if (($_GET['api']??'')==='state') {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    if (!$session || $studentId==='') {http_response_code(403);echo json_encode(['ok'=>false]);exit;}
    $fresh=qp_session($code);
    echo json_encode(['ok'=>true,'index'=>(int)($fresh['current_index']??0),'status'=>(string)($fresh['status']??'closed'),'phase'=>(string)($fresh['phase']??'question')]);exit;
}
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST' && $session) {
    if (!hash_equals($csrf,(string)($_POST['csrf']??''))) {http_response_code(403);exit('Phiên không hợp lệ.');}
    $action=(string)($_POST['action']??'');if($session['status']==='open' && in_array($action,['join','next','answer'],true))qp_touch($code);
    if ($action==='leave') {unset($_SESSION['qp_join'][$code]);$studentId='';}
    elseif ($session['status']!=='open' || (qp_session($code)['status']??'closed')!=='open') $error='Lượt chơi đã đóng.';
    elseif ($action==='join') {
        $id=(string)($_POST['student_id']??'');
        if (!isset($students[$id])) $error='Chọn học sinh trong lớp.';
        else {$_SESSION['qp_join'][$code]=$id;$studentId=$id;}
    } elseif ($studentId!=='') {
        if ($action==='next' && $pace==='timed') {
            $progress=qp_student_progress($code,$studentId);$i=(int)$progress['question_index'];
            if ($i<count($questions)) {
                $st=qp_db()->prepare('SELECT 1 FROM cds_quiz_answers WHERE session_code=? AND student_id=? AND question_index=?');$st->execute([$code,$studentId,$i]);
                $seconds=qp_question_seconds($questions[$i]??[],$seconds);$elapsed=(int)$progress['server_epoch']-(int)$progress['started_epoch'];
                if (!$st->fetchColumn() && $elapsed<$seconds) $error='Hãy trả lời hoặc chờ hết thời gian câu này.';
                else {$st=qp_db()->prepare('UPDATE cds_quiz_progress SET question_index=question_index+1,started_at=NOW() WHERE session_code=? AND student_id=? AND question_index=?');$st->execute([$code,$studentId,$i]);header('Location: '.BASE_URL.'hoclieu_game_quiz_join.php?code='.rawurlencode($code));exit;}
            }
        } elseif ($action==='answer') {
            $i=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);$answer=(string)($_POST['answer']??'');
            if ($i===false || !isset($questions[$i]) || !in_array($answer,['A','B','C','D'],true)) $error='Câu trả lời không hợp lệ.';
            elseif ($pace==='teacher' && (($session['phase']??'')==='welcome')) $error='Chờ giáo viên bắt đầu.';
            elseif ($pace==='teacher' && $i!==(int)(qp_session($code)['current_index']??-1)) $error='Giáo viên đã chuyển câu. Trang sẽ cập nhật câu mới.';
            elseif ($pace==='timed') {
                $progress=qp_student_progress($code,$studentId);$seconds=qp_question_seconds($questions[$i]??[],$seconds);
                if ($i!==(int)$progress['question_index'] || (int)$progress['server_epoch']-(int)$progress['started_epoch']>$seconds) $error='Câu này đã hết giờ hoặc đã chuyển. Hãy xem trạng thái mới.';
            }
            if ($error==='') {
                $st=qp_db()->prepare('INSERT IGNORE INTO cds_quiz_answers(session_code,student_id,question_index,answer,answered_at) VALUES(?,?,?,?,NOW())');$st->execute([$code,$studentId,$i,$answer]);
                header('Location: '.BASE_URL.'hoclieu_game_quiz_join.php?code='.rawurlencode($code).($pace==='self'?'&q='.($i+1):''));exit;
            }
        }
    }
}
$progress=($session && $studentId!=='' && $pace==='timed')?qp_student_progress($code,$studentId):[];
$index=$pace==='teacher'?min(count($questions),max(0,(int)($session['current_index']??0))):($pace==='timed'?(int)($progress['question_index']??0):max(0,(int)($_GET['q']??0)));
$index=min($index,count($questions));
$seconds=qp_question_seconds($questions[$index]??[],$seconds);
$remaining=$pace==='timed'?max(0,$seconds-((int)($progress['server_epoch']??0)-(int)($progress['started_epoch']??0))):0;
$past=[];
if ($session && $studentId!=='') {$st=qp_db()->prepare('SELECT question_index,answer FROM cds_quiz_answers WHERE session_code=? AND student_id=?');$st->execute([$code,$studentId]);foreach ($st as $row) $past[(int)$row['question_index']]=(string)$row['answer'];}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Hỏi đáp với QR code · Vào chơi</title>
<style>*{box-sizing:border-box}body{font:16px system-ui,Arial;margin:0;min-height:100vh;background:radial-gradient(circle at 15% 5%,#174d91,#071c40 60%);color:#fff}main{max-width:760px;margin:auto;padding:clamp(14px,3vw,28px);padding-bottom:calc(26px + env(safe-area-inset-bottom))}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}h1{font-size:clamp(23px,5vw,34px);margin:8px 0}.identity{color:#d6e7fb;font-weight:700}.card{background:#123366;border:1px solid #4480bf;border-radius:20px;padding:clamp(17px,4vw,28px);margin:18px 0;box-shadow:0 16px 36px #03152e66}h2{font-size:clamp(22px,4.3vw,31px);line-height:1.35}input,select{font:inherit;padding:13px;border-radius:10px;border:0;max-width:100%;box-sizing:border-box}.button,button{border:0;border-radius:12px;padding:13px 17px;background:#ffd32d;color:#122440;font:inherit;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;min-height:48px}.choice{display:flex;width:100%;text-align:left;gap:12px;align-items:center;background:#e8f3ff;margin:10px 0;color:#122440;font-weight:700;min-height:64px}.choice b{background:#1766a8;color:#fff;border-radius:9px;min-width:38px;height:38px;display:grid;place-items:center}.choice:focus,.choice:hover{background:#b5dcff}.muted{color:#bed0e7}.error{background:#9b2634;padding:12px;border-radius:9px}.pill{border-radius:999px;background:#ffffff24;padding:7px 12px;font-weight:800}.timer{font-variant-numeric:tabular-nums;color:#ffd32d;font-size:22px;font-weight:900}.timer.expired{color:#ffa2a2}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.actions>*{flex:1}.secondary{background:#d8eaff}.question-image{max-width:100%;max-height:38vh;border-radius:12px}.progress{height:8px;background:#ffffff30;border-radius:9px;overflow:hidden}.progress i{display:block;height:100%;background:#ffd32d}@media(max-width:560px){main{padding:12px 12px calc(16px + env(safe-area-inset-bottom))}.card{margin:12px 0;border-radius:16px}.choice{font-size:16px;padding:10px;min-height:62px}.top .button{width:100%}.actions{position:sticky;bottom:0;background:#0a2857;padding:10px;border-radius:14px;z-index:4}.actions>*{width:100%}select,input[name=code]{width:100%}}</style></head><body><main>
<div class="top"><h1>🎯 Hỏi đáp với QR code</h1><?php if ($studentId!==''): ?><span class="pill"><?=e($students[$studentId])?></span><?php endif; ?></div>
<?php if ($error): ?><p class="error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if (!$session || !$set): ?><div class="card"><h2>Nhập mã vào chơi</h2><form method="get"><input name="code" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="Mã 8 chữ số" required><button>Vào chơi</button></form><?php if ($code!==''): ?><p>Mã không tồn tại hoặc đã hết hạn.</p><?php endif; ?></div>
<?php elseif ($studentId===''): ?><div class="card"><h2><?=e((string)$set['title'])?></h2><p>Chọn đúng tên của em.</p><form method="post"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="join"><select name="student_id" required><option value="">Chọn tên học sinh</option><?php foreach ($students as $id=>$name): ?><option value="<?=e($id)?>"><?=e($name)?></option><?php endforeach; ?></select><button>Bắt đầu</button></form></div>
<?php else: ?><p class="identity"><?=e((string)$set['title'])?> · Mã <?=e($code)?> · <?=$pace==='teacher'?'Giáo viên chuyển câu':($pace==='timed'?'Tự chuyển câu theo thời gian':'Luyện tập')?></p><form method="post"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="leave"><button class="secondary">Đổi người chơi</button></form>
<?php if ($session['status']!=='open'): ?><div class="card"><h2>Lượt chơi đã đóng</h2></div>
<?php elseif ($pace==='teacher' && ($session['phase']??'')==='welcome'): ?><div class="card"><h2>🎯 Sẵn sàng tham gia!</h2><p>Chờ giáo viên bấm Bắt đầu để hiện câu hỏi.</p></div>
<?php elseif ($index>=count($questions)): $correct=0;foreach ($questions as $i=>$q) if (($past[$i]??'')===($q['key']??'')) $correct++; $complete=count($past)>=count($questions); ?><div class="card"><h2><?=$complete?'Hoàn thành':'Đã kết thúc lượt chơi'?></h2><p>Đã trả lời <?=count($past)?>/<?=count($questions)?> câu<?php if ($complete): ?>; đúng <strong><?=$correct?>/<?=count($questions)?></strong><?php endif; ?>.</p><?php if ($pace==='self'): ?><a class="button" href="?code=<?=e($code)?>&amp;q=0">Xem lại câu hỏi</a><?php endif; ?></div>
<?php else: $q=$questions[$index];$answered=isset($past[$index]); ?><div class="card"><div class="top"><span class="pill">Câu <?=$index+1?> / <?=count($questions)?></span><?php if ($pace==='timed'): ?><span id="timer" class="timer" data-left="<?=$remaining?>">⏱ <?=$remaining?> giây</span><?php endif; ?></div><div class="progress"><i style="width:<?=count($questions)?round(($index+1)*100/count($questions)):0?>%"></i></div><h2><?=e((string)$q['text'])?></h2><?php if (!empty($q['image'])): ?><img class="question-image" src="<?=e((string)$q['image'])?>" alt="Hình minh họa câu hỏi"><?php endif; ?>
<?php if (!$answered && ($pace!=='timed'||$remaining>0)): ?><form method="post" id="answerForm"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="answer"><input type="hidden" name="index" value="<?=$index?>"><?php foreach (['A','B','C','D'] as $letter): ?><button class="choice" name="answer" value="<?=$letter?>"><b><?=$letter?></b><span><?=e((string)($q['choices'][$letter]??''))?></span></button><?php endforeach; ?></form><?php elseif ($answered): ?><p>Đã ghi nhận đáp án <strong><?=e($past[$index])?></strong>.</p><?php else: ?><p class="error">Đã hết thời gian trả lời câu này.</p><?php endif; ?>
<div class="actions"><?php if ($pace==='timed'): ?><form method="post"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="next"><button id="nextAfterTimer" <?=(!$answered && $remaining>0)?'disabled':''?>><?=($answered||$remaining<=0)?'Câu tiếp →':'Trả lời hoặc chờ hết giờ để sang câu tiếp'?></button></form><?php elseif ($pace==='teacher'): ?><p class="muted">Đang chờ giáo viên chuyển câu. Máy sẽ tự cập nhật.</p><?php else: ?><a class="button secondary" href="?code=<?=e($code)?>&amp;q=<?=max(0,$index-1)?>">← Câu trước</a><a class="button" href="?code=<?=e($code)?>&amp;q=<?=$index+1?>">Câu tiếp →</a><?php endif; ?></div></div><?php endif; ?>
<?php endif; ?></main>
<?php if ($session && $studentId!=='' && $pace==='teacher'): ?><script>(function(){let index=<?=$index?>,phase=<?=json_encode((string)($session['phase']??'question'))?>,active=true;async function check(){if(!active||document.hidden)return;try{const r=await fetch(location.pathname+'?code='+encodeURIComponent(<?=json_encode($code)?>)+'&api=state',{cache:'no-store'}),s=await r.json();if(s.ok&&(s.index!==index||s.status!=='open'||s.phase!==phase))location.reload()}catch(e){}}setInterval(check,3500);document.addEventListener('visibilitychange',check)})()</script><?php endif; ?>
<?php if ($session && $studentId!=='' && $pace==='timed' && $index<count($questions)): ?><script>(function(){const timer=document.getElementById('timer');if(!timer)return;const end=Date.now()+Number(timer.dataset.left)*1000;function tick(){const left=Math.max(0,Math.ceil((end-Date.now())/1000));timer.textContent='⏱ '+left+' giây';timer.classList.toggle('expired',left===0);if(left===0){const form=document.getElementById('answerForm');if(form)form.querySelectorAll('button').forEach(b=>b.disabled=true);const next=document.getElementById('nextAfterTimer');if(next){next.disabled=false;next.textContent='Hết giờ · sang câu tiếp'}}}setInterval(tick,250);tick()})()</script><?php endif; ?></body></html>
