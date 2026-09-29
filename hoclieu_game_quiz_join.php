<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
if (empty($_SESSION['qp_student_csrf'])) $_SESSION['qp_student_csrf'] = bin2hex(random_bytes(24));
$csrf=(string)$_SESSION['qp_student_csrf'];
$code=trim((string)($_REQUEST['code']??''));
$session=preg_match('/^[0-9]{8}$/',$code)?qp_session($code):null;
if ($session && ($session['mode']??'')!=='computer') $session=null;
if (($_GET['api']??'')==='visibility') {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    echo json_encode(['revealed'=>$session && (!empty($session['show_correct']) || $session['phase']==='finished'),'closed'=>$session && $session['status']!=='open']);exit;
}
$set=$session?qp_set((string)$session['set_id']):null;
$questions=$session?(json_decode((string)$session['questions_json'],true)?:[]):[];
$students=$session?(json_decode((string)($session['roster_json']??''),true)?:[]):[];
if ($session && !$students) foreach (csdl_students_all() as $student) {
    if (!empty($student['active']) && (string)($student['class_id']??'')===(string)$session['class_id'])
        $students[(string)$student['id']]=(string)($student['name']??$student['full_name']??'');
}
$groups=$session?qp_group_players($code):[];
$players=$students+$groups;
$playerId=(string)($_SESSION['qp_join'][$code]??'');
if (!isset($players[$playerId])) $playerId='';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST' && $session) {
    if (!hash_equals($csrf,(string)($_POST['csrf']??''))) { http_response_code(403);exit('Phiên không hợp lệ.'); }
    $action=(string)($_POST['action']??'');
    if ($action==='leave') { unset($_SESSION['qp_join'][$code]);$playerId=''; }
    elseif ($session['status']!=='open' || $session['phase']==='finished') $error='Lượt chơi đã kết thúc.';
    elseif ($action==='join') {
        if (($_POST['identity']??'student')==='group') {
            $name=trim(preg_replace('/\s+/u',' ',(string)($_POST['group_name']??'')));
            if (mb_strlen($name)<2 || mb_strlen($name)>60 || preg_match('/[\p{C}<>]/u',$name)) $error='Tên nhóm cần từ 2 đến 60 ký tự và không có ký tự đặc biệt.';
            else {
                $st=qp_db()->prepare('SELECT group_id FROM cds_quiz_groups WHERE session_code=? AND name=?');$st->execute([$code,$name]);
                $id=(string)($st->fetchColumn()?:'');
                if ($id==='') {
                    if (count($groups)>=40) $error='Lượt chơi đã đủ 40 nhóm.';
                    else {
                        $id='grp_'.bin2hex(random_bytes(12));
                        try { qp_db()->prepare('INSERT INTO cds_quiz_groups(session_code,group_id,name,created_at) VALUES(?,?,?,NOW())')->execute([$code,$id,$name]); }
                        catch (PDOException $e) {
                            if ($e->getCode()!=='23000') throw $e;
                            $st->execute([$code,$name]);$id=(string)($st->fetchColumn()?:'');
                            if ($id==='') $error='Không tạo được nhóm, vui lòng thử lại.';
                        }
                    }
                }
                if ($error==='' && $id!=='') { $groups[$id]=$name;$players[$id]=$name;$_SESSION['qp_join'][$code]=$id;$playerId=$id; }
            }
        } elseif (($_POST['identity']??'student')==='student') {
            $id=(string)($_POST['student_id']??'');
            if (!isset($students[$id])) $error='Hãy chọn đúng tên học sinh.';
            else { $_SESSION['qp_join'][$code]=$id;$playerId=$id; }
        } else $error='Cách tham gia không hợp lệ.';
    } elseif ($action==='answer' && $playerId!=='') {
        $i=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
        $q=$i!==false?($questions[$i]??null):null;
        $type=$q?qp_question_type($q):'';
        $answer=(string)($_POST['answer']??'');
        if ($type==='multi') {
            $chosen=array_values(array_unique(array_intersect(['A','B','C','D'],(array)($_POST['answers']??[]))));sort($chosen);
            $answer=json_encode($chosen);$valid=count($chosen)>0;
        } elseif ($type==='fill') { $answer=trim((string)($_POST['fill_answer']??''));$valid=$answer!=='' && mb_strlen($answer)<=200; }
        elseif ($type==='order') {
            $chosen=(array)($_POST['steps']??[]);$steps=(array)($q['steps']??[]);
            $valid=count($chosen)===count($steps) && count(array_unique($chosen))===count($steps) && !array_diff($chosen,$steps);
            $answer=json_encode(array_values($chosen),JSON_UNESCAPED_UNICODE);
        } elseif ($type==='match') {
            $chosen=(array)($_POST['matches']??[]);$rights=array_column((array)($q['pairs']??[]),1);
            $valid=count($chosen)===count($rights) && count(array_unique($chosen))===count($rights) && !array_diff($chosen,$rights);
            $answer=json_encode(array_values($chosen),JSON_UNESCAPED_UNICODE);
        } else $valid=in_array($answer,['A','B','C','D'],true);
        if ($i===false || !$q || !$valid) $error='Câu trả lời chưa hợp lệ, em hãy kiểm tra lại.';
        else {
            qp_db()->prepare('INSERT IGNORE INTO cds_quiz_answers(session_code,student_id,question_index,answer,answered_at) VALUES(?,?,?,?,NOW())')->execute([$code,$playerId,$i,$answer]);
            header('Location: '.BASE_URL.'vaochoi.php?code='.rawurlencode($code).'&q='.($i+1));exit;
        }
    }
}
$index=max(0,(int)($_GET['q']??0));$index=min($index,count($questions));
$past=[];
if ($session && $playerId!=='') {
    $st=qp_db()->prepare('SELECT question_index,answer FROM cds_quiz_answers WHERE session_code=? AND student_id=?');$st->execute([$code,$playerId]);
    foreach ($st as $row) $past[(int)$row['question_index']]=(string)$row['answer'];
}
$revealed=$session && (!empty($session['show_correct']) || $session['phase']==='finished');
$encouragement=['Bình tĩnh đọc kỹ câu hỏi, em làm được! 🌟','Mỗi câu trả lời là một bước tiến mới! 🚀','Cả nhóm cùng trao đổi và chọn phương án nhé! 🤝','Thử sức hết mình, điều thú vị đang chờ phía trước! ✨','Sắp về đích rồi, tiếp tục cố gắng! 🎯'];
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vào chơi · Hỏi Nhanh - Đáp Gọn</title>
<style>
:root{--ink:#182a52;--sub:#61708d;--line:#dce4f4;--purple:#6651d7;--blue:#2068ca}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 85% 5%,#dcd1ff,transparent 35%),radial-gradient(circle at 8% 80%,#d9f2ff,transparent 35%),#f4f6ff;color:var(--ink);font:16px system-ui,-apple-system,Segoe UI,sans-serif}button,input,select{font:inherit}a{color:#4534ac;text-decoration:none}a:hover{text-decoration:underline}.top{background:#fff;border-bottom:1px solid var(--line)}.top-inner{max-width:1140px;margin:auto;padding:15px 22px;display:flex;align-items:center;gap:15px}.logo{font-size:20px;font-weight:900;color:#3d319a}.top-note{margin-left:auto;color:#6c7795;font-size:13px;font-weight:750}.page{max-width:1140px;margin:0 auto;padding:32px 22px 70px}.intro{margin-bottom:22px}.intro h1{font-size:clamp(29px,3.5vw,43px);line-height:1.15;margin:8px 0;color:#272969}.intro p{margin:0;color:var(--sub)}.eyebrow{font-size:11px;font-weight:900;letter-spacing:.15em;color:#7059da}.panel{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 16px 40px #29346a12;padding:28px;margin:16px 0}.panel h2{margin:0 0 10px;font-size:clamp(21px,2.5vw,28px)}.muted{color:var(--sub)}.hero-panel{display:grid;grid-template-columns:1fr 330px;gap:25px;align-items:center;padding:36px;background:linear-gradient(135deg,#fff,#f3f0ff)}.hero-panel .illustration{display:grid;place-items:center;min-height:210px;border-radius:20px;background:radial-gradient(circle,#b0eafc,#7469e8);font-size:85px;box-shadow:inset 0 0 35px #fff8}.join-form{display:flex;gap:10px;max-width:525px;margin-top:24px}.join-form input{font-size:23px;letter-spacing:.18em;font-weight:850;text-align:center;flex:1;min-width:0}.field,select,input[type=text],input[inputmode=numeric]{border:1px solid #c7d2e8;border-radius:12px;padding:12px 14px;background:#fff;color:var(--ink);outline-color:#7967df}.btn,button{border:0;border-radius:12px;background:#6351d6;color:#fff;padding:12px 18px;font-weight:850;cursor:pointer;display:inline-flex;justify-content:center;align-items:center;text-decoration:none}.btn:hover,button:hover{filter:brightness(.96);text-decoration:none}.btn.secondary,button.secondary{background:#e9edff;color:#3c4096}.btn.tiny,button.tiny{padding:9px 12px;font-size:13px}.notice{padding:13px 16px;background:#fff5d9;border:1px solid #f3d47e;border-radius:12px;color:#685124;margin:14px 0}.error{background:#fff0f0;border-color:#edaaa9;color:#9b2834}.identity-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0}.identity-option{display:flex;align-items:center;gap:12px;padding:17px;border:2px solid #dce2f2;border-radius:15px;cursor:pointer}.identity-option:has(input:checked){border-color:#7563dc;background:#f5f2ff}.identity-option input{width:19px;height:19px;accent-color:#6651d7}.identity-option strong,.identity-option small{display:block}.identity-option small{margin-top:2px;color:var(--sub);font-weight:500}.identity-field select,.identity-field input{width:100%;margin:8px 0 12px}.identity-field label{display:block;font-weight:800}.identity-field[hidden]{display:none}.play-header{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}.badge{padding:8px 12px;border-radius:30px;background:#e8e5ff;color:#5141aa;font-size:13px;font-weight:850}.progress-track{height:10px;background:#e9edfa;border-radius:10px;overflow:hidden;margin:15px 0}.progress-track span{display:block;height:100%;background:linear-gradient(90deg,#4fb8e6,#7653dd);border-radius:10px}.motivation{display:flex;align-items:center;gap:10px;background:#eaf8f5;color:#176c63;border-radius:13px;padding:12px 15px;font-weight:750;margin:15px 0}.question-layout{display:grid;grid-template-columns:minmax(0,1fr) 230px;gap:18px;align-items:start}.question-card{background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 14px 34px #29346a12;padding:25px}.question-card h2{font-size:clamp(22px,3vw,33px);line-height:1.35;margin:8px 0 20px}.question-card img{max-width:100%;max-height:320px;border-radius:12px}.side-card{padding:19px;border-radius:18px;background:#25296b;color:#fff}.side-card p{color:#d5dcff;font-size:14px}.side-card strong{font-size:26px}.side-card .small{font-size:13px}.answers{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:18px 0}.answer-tile{display:flex;align-items:center;gap:12px;width:100%;min-height:78px;text-align:left;background:#f5f7ff;color:var(--ink);border:2px solid #e1e6f5;border-radius:15px;padding:12px 15px;font-weight:750}.answer-tile:hover,.answer-tile:focus{background:#eef0ff;border-color:#8c7bea}.answer-tile:nth-child(4n+1) .letter{background:#e7ab35}.answer-tile:nth-child(4n+2) .letter{background:#5d76e5}.answer-tile:nth-child(4n+3) .letter{background:#d26a9d}.answer-tile:nth-child(4n+4) .letter{background:#26a89b}.letter{flex:none;display:grid;place-items:center;width:38px;height:38px;border-radius:10px;color:#fff;font-weight:900}.answer-tile input{accent-color:#6651d7}.answer-tile:has(input:checked){background:#eeeaff;border-color:#7563dc}.answer-tile .label-text{flex:1}.question-card select,.question-card input[type=text]{width:100%;margin:7px 0 14px}.select-row{display:block;margin:12px 0;padding:13px;border:1px solid var(--line);border-radius:12px}.submit-row{display:flex;justify-content:flex-end;gap:10px;margin:16px 0}.nav-row{display:flex;gap:10px;justify-content:space-between;margin-top:16px}.saved-answer{padding:18px;border-radius:14px;background:#e8f8ef;color:#166847;font-weight:800}.result-list{display:grid;gap:9px;margin-top:18px}.result-row{padding:12px 14px;border-radius:12px;background:#f5f7fe}.result-row.ok{background:#e6f8ef}.result-row.bad{background:#fff0ee}.completion{font-size:17px;line-height:1.55}.completion strong{font-size:24px;color:#4f39b5}@media(max-width:780px){.hero-panel,.question-layout{grid-template-columns:1fr}.hero-panel .illustration{display:none}.side-card{order:-1}.answers{grid-template-columns:1fr}}@media(max-width:560px){.page{padding:20px 14px 55px}.panel,.question-card{padding:18px}.identity-grid{grid-template-columns:1fr}.join-form{flex-direction:column}.top-note{display:none}.play-header{align-items:flex-start}.answers{gap:9px}}
</style></head><body><header class="top"><div class="top-inner"><a class="logo" href="<?=BASE_URL?>vaochoi.php">🎯 Hỏi Nhanh - Đáp Gọn</a><span class="top-note">Học vui · Nghĩ nhanh · Cùng tiến bộ</span></div></header><main class="page">
<div class="intro"><span class="eyebrow">TRÒ CHƠI TƯƠNG TÁC</span><h1>Hỏi Nhanh - Đáp Gọn</h1><p>Vào chơi cùng lớp, cùng nhóm và khám phá điều thú vị trong mỗi câu hỏi.</p></div>
<?php if ($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif; ?>
<?php if (!$session || !$set): ?>
<section class="panel hero-panel"><div><span class="eyebrow">BƯỚC 1 · VÀO CHƠI</span><h2>Nhập mã từ giáo viên</h2><p class="muted">Mã gồm 8 chữ số. Em có thể nhập trên máy tính, điện thoại hoặc mở đường dẫn giáo viên gửi.</p><form class="join-form" method="get" action="<?=BASE_URL?>vaochoi.php"><input class="field" name="code" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="0000 0000" aria-label="Mã lượt chơi 8 chữ số" required><button>Vào chơi →</button></form><?php if($code!==''): ?><p class="notice error">Mã chưa đúng, đã hết hạn hoặc lượt này không chơi trên máy.</p><?php endif; ?></div><div class="illustration" aria-hidden="true">🎮</div></section>
<?php elseif ($playerId===''): ?>
<section class="panel"><span class="eyebrow">BƯỚC 2 · CHỌN NGƯỜI CHƠI</span><h2><?=e((string)$set['title'])?></h2><p class="muted">Chọn tên em trong danh sách hoặc nhập tên nhóm để cả nhóm cùng tham gia.</p><form method="post" id="identity-form"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="join"><div class="identity-grid"><label class="identity-option"><input type="radio" name="identity" value="student" checked><span><strong>👤 Chơi cá nhân</strong><small>Chọn tên học sinh</small></span></label><label class="identity-option"><input type="radio" name="identity" value="group"><span><strong>👥 Chơi theo nhóm</strong><small>Tự nhập tên nhóm</small></span></label></div><div class="identity-field" data-identity="student"><label for="student-name">Tên của em</label><select id="student-name" name="student_id" required><option value="">Chọn tên học sinh</option><?php foreach($students as $id=>$name): ?><option value="<?=e((string)$id)?>"><?=e((string)$name)?></option><?php endforeach; ?></select></div><div class="identity-field" data-identity="group" hidden><label for="group-name">Tên nhóm</label><input id="group-name" class="field" name="group_name" maxlength="60" placeholder="Ví dụ: Nhóm Sao Sáng"><p class="muted">Nếu nhóm đã có, nhập đúng tên để tiếp tục lượt chơi của nhóm.</p></div><button>Bắt đầu chơi →</button></form></section>
<?php else: ?>
<section class="panel"><div class="play-header"><div><span class="eyebrow">ĐANG THAM GIA · MÃ <?=e($code)?></span><h2><?=e((string)$set['title'])?></h2><span class="badge"><?=str_starts_with($playerId,'grp_')?'👥 Nhóm ':'👤 '?><?=e((string)$players[$playerId])?></span></div><form method="post"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="leave"><button class="secondary tiny">Đổi người chơi</button></form></div><div class="progress-track" role="progressbar" aria-valuenow="<?=count($past)?>" aria-valuemin="0" aria-valuemax="<?=count($questions)?>"><span style="width:<?=count($questions)?round(100*count($past)/count($questions)):0?>%"></span></div><p class="muted">Đã trả lời <?=count($past)?> / <?=count($questions)?> câu hỏi</p></section>
<?php if ($session['status']!=='open' && !$revealed): ?><div class="notice">Lượt chơi đã đóng. Hãy chờ hướng dẫn từ giáo viên.</div><?php endif; ?>
<?php if ($index>=count($questions)): $complete=count($past)>=count($questions); ?>
<section class="panel completion"><span class="eyebrow">VỀ ĐÍCH</span><h2><?=$complete?'🎉 Hoàn thành lượt chơi!':'🌟 Em đã xem hết câu hỏi'?></h2><p><?=$complete?'Tuyệt vời! Câu trả lời đã được ghi nhận.':'Một vài câu chưa có đáp án. Em có thể quay lại để hoàn thành.'?></p><?php if($revealed): $correct=0;foreach($questions as $i=>$question) if(isset($past[$i])&&qp_answer_correct($question,$past[$i]))$correct++; ?><p>Giáo viên đã công bố: em trả lời đúng <strong><?=$correct?> / <?=count($questions)?></strong> câu.</p><div class="result-list"><?php foreach($questions as $i=>$question): $answer=$past[$i]??'';$ok=$answer!==''&&qp_answer_correct($question,$answer); ?><div class="result-row <?=$answer===''?'':($ok?'ok':'bad')?>"><b>Câu <?=$i+1?>:</b> <?=e((string)$question['text'])?><br>Em trả lời: <?=e($answer!==''?qp_answer_label($answer):'—')?> · Đáp án đúng: <?=e(qp_correct_label($question))?><?php if(!empty($question['explanation'])): ?><p class="muted"><?=nl2br(e((string)$question['explanation']))?></p><?php endif; ?></div><?php endforeach; ?></div><?php else: ?><div class="motivation">🔒 Đáp án và điểm sẽ hiện sau khi giáo viên công bố hoặc kết thúc lượt chơi.</div><?php endif; ?><a class="btn secondary" href="?code=<?=e($code)?>&amp;q=0">← Xem lại câu hỏi</a></section>
<?php else: $q=$questions[$index]; ?>
<div class="question-layout"><article class="question-card"><span class="eyebrow">CÂU <?=($index+1)?> / <?=count($questions)?></span><h2><?=e((string)$q['text'])?></h2><?php if(!empty($q['image'])): ?><img src="<?=e((string)$q['image'])?>" alt="Ảnh minh họa câu hỏi"><?php endif; ?><?php if(!empty($q['video'])): ?><iframe src="<?=e((string)$q['video'])?>" title="Video câu hỏi" loading="lazy" allowfullscreen style="width:100%;aspect-ratio:16/9;border:0;border-radius:12px"></iframe><?php endif; ?><?php if(!empty($q['audio'])): ?><audio controls src="<?=e((string)$q['audio'])?>" style="width:100%"></audio><?php endif; ?>
<?php if(!isset($past[$index]) && $session['status']==='open'): ?><form method="post"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="answer"><input type="hidden" name="index" value="<?=$index?>">
<?php if(qp_question_type($q)==='fill'): ?><label for="fill-answer">Nhập đáp án của em</label><input id="fill-answer" class="field" name="fill_answer" required maxlength="200" autocomplete="off"><div class="submit-row"><button>Gửi đáp án →</button></div>
<?php elseif(qp_question_type($q)==='order'): $steps=(array)($q['steps']??[]);$shuffled=$steps;shuffle($shuffled);foreach($steps as $n=>$step): ?><label class="select-row">Vị trí <?=$n+1?><select name="steps[]" required><option value="">Chọn nội dung</option><?php foreach($shuffled as $item): ?><option value="<?=e((string)$item)?>"><?=e((string)$item)?></option><?php endforeach; ?></select></label><?php endforeach; ?><div class="submit-row"><button>Gửi đáp án →</button></div>
<?php elseif(qp_question_type($q)==='match'): $pairs=(array)($q['pairs']??[]);$rights=array_column($pairs,1);shuffle($rights);foreach($pairs as $pair): ?><label class="select-row"><?=e((string)$pair[0])?> → <select name="matches[]" required><option value="">Chọn cặp</option><?php foreach($rights as $right): ?><option value="<?=e((string)$right)?>"><?=e((string)$right)?></option><?php endforeach; ?></select></label><?php endforeach; ?><div class="submit-row"><button>Gửi đáp án →</button></div>
<?php elseif(qp_question_type($q)==='multi'): ?><div class="answers"><?php foreach(['A','B','C','D'] as $letter): ?><label class="answer-tile"><input type="checkbox" name="answers[]" value="<?=$letter?>"><span class="letter"><?=$letter?></span><span class="label-text"><?=e((string)($q['choices'][$letter]??''))?></span></label><?php endforeach; ?></div><div class="submit-row"><button>Gửi đáp án →</button></div>
<?php else: ?><div class="answers"><?php foreach(['A','B','C','D'] as $letter): ?><button class="answer-tile" name="answer" value="<?=$letter?>"><span class="letter"><?=$letter?></span><span class="label-text"><?=e((string)($q['choices'][$letter]??''))?></span></button><?php endforeach; ?></div><?php endif; ?></form>
<?php else: ?><div class="saved-answer"><?php if(isset($past[$index])): ?>✓ Đã ghi nhận: <?=e(qp_answer_label($past[$index]))?>. <?=e($encouragement[$index%count($encouragement)])?><?php else: ?>Lượt chơi đã kết thúc; em chưa trả lời câu này.<?php endif; ?></div><?php if($revealed): ?><div class="motivation">💡 Đáp án đúng: <?=e(qp_correct_label($q))?><?php if(!empty($q['explanation'])): ?> · <?=e((string)$q['explanation'])?><?php endif; ?></div><?php endif; ?><?php endif; ?>
<div class="nav-row"><a class="btn secondary tiny" href="?code=<?=e($code)?>&amp;q=<?=max(0,$index-1)?>">← Câu trước</a><a class="btn secondary tiny" href="?code=<?=e($code)?>&amp;q=<?=$index+1?>">Câu tiếp →</a></div></article><aside class="side-card"><span class="eyebrow" style="color:#a8ffea">CỔ VŨ EM</span><p><strong>✨</strong></p><p><?=e($encouragement[$index%count($encouragement)])?></p><p class="small">🔒 Đáp án đúng chỉ hiện khi giáo viên công bố hoặc kết thúc lượt chơi.</p></aside></div>
<?php endif; ?>
<?php endif; ?>
</main><script>const revealCode=<?=json_encode($session && $playerId!=='' && !$revealed?$code:'',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;if(revealCode)setInterval(async()=>{try{const response=await fetch(<?=json_encode(BASE_URL.'vaochoi.php',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>+'?code='+encodeURIComponent(revealCode)+'&api=visibility',{cache:'no-store'}),state=await response.json();if(state.revealed)location.reload()}catch(e){}},12000);const identity=document.getElementById('identity-form');if(identity){const sync=()=>{const mode=identity.querySelector('input[name="identity"]:checked').value;identity.querySelectorAll('[data-identity]').forEach(field=>{const active=field.dataset.identity===mode;field.hidden=!active;field.querySelectorAll('input,select').forEach(input=>input.required=active)})};identity.querySelectorAll('input[name="identity"]').forEach(input=>input.addEventListener('change',sync));sync()}</script><?php require __DIR__ . '/includes/game_credit.php'; ?></body></html>
