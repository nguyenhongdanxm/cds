<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
try { qp_schema(); } catch (Throwable $e) { http_response_code(500); exit('Không thể mở trò chơi.'); }
if (empty($_SESSION['qp_student_csrf'])) $_SESSION['qp_student_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['qp_student_csrf'];
$code = trim((string)($_REQUEST['code'] ?? ''));
$session = preg_match('/^[0-9]{8}$/', $code) ? qp_session($code) : null;
$set = $session ? qp_set((string)$session['set_id']) : null;
$questions = $session ? (json_decode((string)$session['questions_json'],true) ?: []) : [];
$students = [];
if ($session) foreach (csdl_students_all() as $student) {
    if (!empty($student['active']) && (string)($student['class_id']??'') === (string)$session['class_id']) {
        $students[(string)$student['id']] = (string)($student['name']??$student['full_name']??'');
    }
}
$studentId = (string)($_SESSION['qp_join'][$code]??'');
if (!isset($students[$studentId])) $studentId = '';
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && $session) {
    if (!hash_equals($csrf,(string)($_POST['csrf']??''))) { http_response_code(403); exit('Phiên không hợp lệ.'); }
    if ($session['status']!=='open') $error = 'Lượt chơi đã đóng.';
    elseif (($_POST['action']??'')==='join') {
        $id = (string)($_POST['student_id']??'');
        if (!isset($students[$id])) $error = 'Chọn học sinh trong lớp.';
        else { $_SESSION['qp_join'][$code]=$id; $studentId=$id; }
    } elseif (($_POST['action']??'')==='answer' && $studentId!=='') {
        $i = filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
        $answer = (string)($_POST['answer']??'');
        if ($i===false || !isset($questions[$i]) || !in_array($answer,['A','B','C','D'],true)) $error='Câu trả lời không hợp lệ.';
        else {
            $st=qp_db()->prepare('INSERT INTO cds_quiz_answers(session_code,student_id,question_index,answer,answered_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE answer=VALUES(answer),answered_at=NOW()');
            $st->execute([$code,$studentId,$i,$answer]);
            header('Location: '.BASE_URL.'hoclieu_game_quiz_join.php?code='.rawurlencode($code).'&q='.($i+1));exit;
        }
    }
}
$index=max(0,(int)($_GET['q']??0));
$index=min($index,count($questions));
$past=[];
if ($session && $studentId!=='') {
    $st=qp_db()->prepare('SELECT question_index,answer FROM cds_quiz_answers WHERE session_code=? AND student_id=?');
    $st->execute([$code,$studentId]);
    foreach ($st as $row) $past[(int)$row['question_index']] = (string)$row['answer'];
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vào chơi · Câu hỏi A–D</title>
<style>body{font:17px system-ui,Arial;margin:0;background:#071c40;color:#fff}main{max-width:720px;margin:30px auto;padding:18px}.card{background:#123366;border:1px solid #4480bf;border-radius:18px;padding:24px;margin-bottom:16px}h1{font-size:28px}input,select{font:inherit;padding:12px;border-radius:9px;border:0;max-width:100%;box-sizing:border-box}button,.button{border:0;border-radius:10px;padding:13px 17px;background:#ffd32d;color:#122440;font-weight:800;font:inherit;cursor:pointer;display:inline-block;text-decoration:none;margin:5px}.choice{display:block;width:100%;text-align:left;background:#e8f3ff;margin:10px 0;color:#122440}.choice:focus,.choice:hover{background:#b5dcff}.muted{color:#bed0e7}a{color:#fff}.error{background:#9b2634;padding:12px;border-radius:9px}</style></head><body><main><h1>🎯 Trò chơi A–D</h1>
<?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif; ?>
<?php if (!$session || !$set): ?><div class="card"><h2>Nhập mã vào chơi</h2><form method="get"><input name="code" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="Mã 8 chữ số" required><button>Vào chơi</button></form><?php if ($code!==''): ?><p>Mã không tồn tại hoặc đã hết hạn.</p><?php endif; ?></div>
<?php elseif ($studentId===''): ?><div class="card"><h2><?=e((string)$set['title'])?></h2><p>Chọn đúng tên của em. Đây là chế độ luyện tập, giáo viên có thể kiểm tra lại danh tính trước khi tính điểm.</p><form method="post"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="join"><select name="student_id" required><option value="">Chọn tên học sinh</option><?php foreach ($students as $id=>$name): ?><option value="<?=e($id)?>"><?=e($name)?></option><?php endforeach; ?></select><button>Bắt đầu</button></form></div>
<?php else: ?><p><?=e((string)$set['title'])?> · <?=e($students[$studentId])?> · Mã <?=e($code)?></p>
<?php if ($session['status']!=='open'): ?><div class="card"><h2>Lượt chơi đã đóng</h2></div>
<?php elseif ($index>=count($questions)): ?><div class="card"><h2>Đã đến cuối bộ câu hỏi</h2><p>Em đã trả lời <?=count($past)?>/<?=count($questions)?> câu.</p><a class="button" href="?code=<?=e($code)?>&amp;q=0">Xem lại câu hỏi</a></div>
<?php else: $q=$questions[$index]; ?><div class="card"><p class="muted">Câu <?=$index+1?> / <?=count($questions)?></p><h2><?=e((string)$q['text'])?></h2><form method="post"><input type="hidden" name="code" value="<?=e($code)?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="answer"><input type="hidden" name="index" value="<?=$index?>"><?php foreach (['A','B','C','D'] as $letter): ?><button class="choice" name="answer" value="<?=$letter?>"><?=$letter?>. <?=e((string)($q['choices'][$letter]??''))?></button><?php endforeach; ?></form><p class="muted">Đã chọn: <?=e($past[$index]??'Chưa trả lời')?></p><a href="?code=<?=e($code)?>&amp;q=<?=max(0,$index-1)?>">← Câu trước</a> · <a href="?code=<?=e($code)?>&amp;q=<?=$index+1?>">Câu tiếp →</a></div><?php endif; ?>
<?php endif; ?></main></body></html>
