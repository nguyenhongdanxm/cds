<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
require_login();
if (!qp_admin() && !can_perm_level('hl.xem', 'view')) { http_response_code(403); exit('Không có quyền xem trò chơi.'); }
try { qp_schema(); } catch (Throwable $e) { http_response_code(500); exit('Không thể mở dữ liệu trò chơi: ' . e($e->getMessage())); }
if (empty($_SESSION['qp_csrf'])) $_SESSION['qp_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['qp_csrf'];
$message = '';
function qp_go(string $set = ''): void {
    header('Location: ' . BASE_URL . 'hoclieu_game_quiz.php' . ($set !== '' ? '?set=' . rawurlencode($set) : ''));
    exit;
}
$classes = array_values(array_filter(csdl_classes_all(), static fn($c) => !empty($c['active']) && (!function_exists('can_class') || can_class((string)($c['name'] ?? '')))));
csdl_sort_classes($classes);
$classMap = []; foreach ($classes as $c) $classMap[(string)$c['id']] = (string)$c['name'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Phiên biểu mẫu không hợp lệ.'); }
    $action = (string)($_POST['action'] ?? '');
    $setId = (string)($_POST['set_id'] ?? '');
    try {
        if ($action === 'create') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 255) throw new RuntimeException('Tên bộ câu hỏi không hợp lệ.');
            $id = 'qs_' . bin2hex(random_bytes(12));
            $st = qp_db()->prepare('INSERT INTO cds_quiz_sets(id,owner_id,title,questions_json,created_at,updated_at) VALUES(?,?,?, ?,NOW(),NOW())');
            $st->execute([$id,qp_owner(),$title,'[]']); qp_go($id);
        }
        $set = qp_set($setId, true);
        if (!$set) throw new RuntimeException('Không tìm thấy bộ câu hỏi hoặc không có quyền sửa.');
        if ($action === 'rename') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 255) throw new RuntimeException('Tên bộ câu hỏi không hợp lệ.');
            $st = qp_db()->prepare('UPDATE cds_quiz_sets SET title=?,updated_at=NOW() WHERE id=?');
            $st->execute([$title,$setId]); qp_go($setId);
        }
        if ($action === 'save_question' || $action === 'delete_question') {
            $questions = qp_questions($set);
            $index = filter_var($_POST['index'] ?? '-1', FILTER_VALIDATE_INT);
            if ($action === 'delete_question') {
                if ($index === false || !isset($questions[$index])) throw new RuntimeException('Câu hỏi không tồn tại.');
                array_splice($questions, $index, 1);
            } else {
                $q = ['text'=>trim((string)($_POST['text'] ?? '')),'choices'=>[],'key'=>(string)($_POST['key'] ?? '')];
                foreach (['A','B','C','D'] as $letter) $q['choices'][$letter] = trim((string)($_POST['choice_' . $letter] ?? ''));
                if ($q['text'] === '' || mb_strlen($q['text']) > 2000 || !in_array($q['key'], ['A','B','C','D'], true)) throw new RuntimeException('Câu hỏi hoặc đáp án đúng chưa hợp lệ.');
                foreach ($q['choices'] as $choice) if ($choice === '' || mb_strlen($choice) > 500) throw new RuntimeException('Nhập đủ bốn lựa chọn A–D.');
                if ($index !== false && $index >= 0 && isset($questions[$index])) $questions[$index] = $q;
                elseif (count($questions) < 100) $questions[] = $q;
                else throw new RuntimeException('Mỗi bộ tối đa 100 câu.');
            }
            $st = qp_db()->prepare('UPDATE cds_quiz_sets SET questions_json=?,updated_at=NOW() WHERE id=?');
            $st->execute([json_encode($questions, JSON_UNESCAPED_UNICODE),$setId]); qp_go($setId);
        }
        if ($action === 'open_session') {
            $classId = (string)($_POST['class_id'] ?? '');
            if (!isset($classMap[$classId]) || !qp_questions($set)) throw new RuntimeException('Chọn lớp và tạo ít nhất một câu hỏi.');
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $code = (string)random_int(10000000, 99999999);
                try {
                    $st = qp_db()->prepare("INSERT INTO cds_quiz_sessions(code,set_id,class_id,owner_id,questions_json,status,created_at,expires_at) VALUES(?,?,?,?,?,'open',NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY))");
                    $st->execute([$code,$setId,$classId,qp_owner(),json_encode(qp_questions($set),JSON_UNESCAPED_UNICODE)]);
                    qp_go($setId);
                } catch (PDOException $e) { if ($e->getCode() !== '23000' || $attempt === 2) throw $e; }
            }
        }
        if ($action === 'close_session') {
            $code = (string)($_POST['code'] ?? '');
            $st = qp_db()->prepare("UPDATE cds_quiz_sessions SET status='closed' WHERE code=? AND set_id=? AND owner_id=?");
            $st->execute([$code,$setId,qp_owner()]); qp_go($setId);
        }
    } catch (Throwable $e) { $message = $e->getMessage(); }
}
$setId = (string)($_GET['set'] ?? '');
$set = $setId !== '' ? qp_set($setId, true) : null;
$sets = qp_sets();
$sessions = [];
$answers = [];
if ($set) {
    $st = qp_db()->prepare('SELECT * FROM cds_quiz_sessions WHERE set_id=? ORDER BY created_at DESC LIMIT 20');
    $st->execute([$setId]); $sessions = $st->fetchAll();
    $viewCode = (string)($_GET['report'] ?? '');
    foreach ($sessions as $session) if ($session['code'] === $viewCode) {
        $reportQuestions = json_decode((string)$session['questions_json'],true) ?: [];
        $st = qp_db()->prepare('SELECT student_id,question_index,answer FROM cds_quiz_answers WHERE session_code=?');
        $st->execute([$viewCode]); $answers = $st->fetchAll(); break;
    }
}
$questions = $set ? qp_questions($set) : [];
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bộ câu hỏi · Trò chơi A–D</title>
<style>body{margin:0;background:#edf3f9;color:#183153;font:16px system-ui,Arial}header{background:#123460;color:white;padding:18px max(18px,calc((100vw - 1100px)/2))}header a{color:white}main{max-width:1100px;margin:24px auto;padding:0 16px}.card{background:white;padding:20px;border-radius:15px;box-shadow:0 4px 18px #152c4618;margin:14px 0}h1{font-size:28px;margin:8px 0}h2{font-size:20px}input,select,textarea{font:inherit;padding:10px;border:1px solid #aebed0;border-radius:8px;max-width:100%}input[type=text],textarea{width:100%;box-sizing:border-box}textarea{min-height:70px}label{font-weight:700;display:block;margin:12px 0 5px}button,.button{border:0;background:#126bb0;color:#fff;padding:10px 15px;border-radius:9px;cursor:pointer;text-decoration:none;display:inline-block;margin:4px 4px 4px 0}.secondary{background:#435e7b}.danger{background:#a93636}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}.muted{color:#607086}.notice{background:#fff2cd;padding:12px;border-radius:8px}.question{border-top:1px solid #d7e2ec;padding:12px 0}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #d9e2ec;text-align:left}</style></head><body>
<header><a href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><h1>Bộ câu hỏi A–D · Chơi trên máy tính hoặc thẻ giấy</h1></header><main>
<?php if ($message): ?><p class="notice"><?=e($message)?></p><?php endif; ?>
<div class="card"><h2>Tạo bộ câu hỏi</h2><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="create"><label>Tên bộ câu hỏi</label><input type="text" name="title" maxlength="255" required placeholder="Ví dụ: Ôn tập Vật lý 10 – Chương 1"><button>Tạo bộ mới</button></form></div>
<div class="card"><h2>Bộ câu hỏi của tôi</h2><?php if (!$sets): ?><p>Chưa có bộ câu hỏi.</p><?php endif; ?><?php foreach ($sets as $row): ?><p><a href="?set=<?=e(rawurlencode((string)$row['id']))?>"><?=e((string)$row['title'])?></a> <span class="muted">· cập nhật <?=e((string)$row['updated_at'])?></span></p><?php endforeach; ?></div>
<?php if ($set): ?>
<div class="card"><h2><?=e((string)$set['title'])?></h2><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="rename"><input type="text" name="title" value="<?=e((string)$set['title'])?>" maxlength="255" required><button class="secondary">Đổi tên</button></form><p class="muted"><?=count($questions)?> câu trắc nghiệm. Cùng một bộ được dùng cho hai cách chơi.</p></div>
<div class="card"><h2>Thêm câu hỏi A–D</h2><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="save_question"><input type="hidden" name="index" value="-1"><label>Câu hỏi</label><textarea name="text" maxlength="2000" required></textarea><div class="grid"><?php foreach (['A','B','C','D'] as $letter): ?><div><label>Đáp án <?=$letter?></label><input type="text" name="choice_<?=$letter?>" maxlength="500" required></div><?php endforeach; ?></div><label>Đáp án đúng</label><select name="key"><?php foreach (['A','B','C','D'] as $letter): ?><option><?=$letter?></option><?php endforeach; ?></select><button>Lưu câu hỏi</button></form></div>
<div class="card"><h2>Danh sách câu hỏi</h2><?php foreach ($questions as $i=>$q): ?><div class="question"><strong>Câu <?=$i+1?>. <?=e((string)$q['text'])?></strong><p class="muted">Đúng: <?=e((string)$q['key'])?></p><details><summary>Sửa câu này</summary><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="save_question"><input type="hidden" name="index" value="<?=$i?>"><label>Câu hỏi</label><textarea name="text" required><?=e((string)$q['text'])?></textarea><div class="grid"><?php foreach (['A','B','C','D'] as $letter): ?><div><label><?=$letter?></label><input type="text" name="choice_<?=$letter?>" value="<?=e((string)($q['choices'][$letter]??''))?>" required></div><?php endforeach; ?></div><label>Đáp án đúng</label><select name="key"><?php foreach (['A','B','C','D'] as $letter): ?><option <?=($q['key']===$letter)?'selected':''?>><?=$letter?></option><?php endforeach; ?></select><button>Lưu chỉnh sửa</button></form><form method="post" onsubmit="return confirm('Xóa câu hỏi này?')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="index" value="<?=$i?>"><button class="danger">Xóa câu</button></form></details></div><?php endforeach; ?></div>
<div class="card"><h2>Mở lượt chơi trên máy tính</h2><p>Học sinh vào bằng mã 8 chữ số và chọn tên trong lớp. Lượt chơi mở trong 24 giờ; đây là chế độ luyện tập, chưa dùng để kiểm tra định danh.</p><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="open_session"><select name="class_id" required><option value="">Chọn lớp</option><?php foreach ($classes as $c): ?><option value="<?=e((string)$c['id'])?>"><?=e((string)$c['name'])?></option><?php endforeach; ?></select><button>Tạo mã vào chơi</button></form><p><a class="button secondary" href="<?=BASE_URL?>hoclieu_game_qr_trial.php?set=<?=e(rawurlencode($setId))?>">Chơi bằng thẻ giấy với bộ này</a></p></div>
<div class="card"><h2>Các lượt chơi trên máy tính</h2><p>Địa chỉ học sinh: <strong><?=e(BASE_URL)?>hoclieu_game_quiz_join.php</strong></p><table><tr><th>Mã</th><th>Lớp</th><th>Trạng thái</th><th>Báo cáo</th></tr><?php foreach ($sessions as $s): ?><tr><td><strong><?=e((string)$s['code'])?></strong></td><td><?=e($classMap[(string)$s['class_id']]??(string)$s['class_id'])?></td><td><?=e((string)$s['status'])?></td><td><a href="?set=<?=e(rawurlencode($setId))?>&amp;report=<?=e((string)$s['code'])?>">Xem</a><?php if ($s['status']==='open'): ?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="close_session"><input type="hidden" name="code" value="<?=e((string)$s['code'])?>"><button class="secondary">Đóng</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<?php if ($answers): $studentMap=[]; foreach (csdl_students_all() as $student) $studentMap[(string)$student['id']] = (string)($student['name']??''); ?><div class="card"><h2>Kết quả mã <?=e($viewCode)?></h2><table><tr><th>Học sinh</th><th>Câu</th><th>Đáp án</th><th>Kết quả</th></tr><?php foreach ($answers as $a): $q=$reportQuestions[(int)$a['question_index']]??null; ?><tr><td><?=e($studentMap[(string)$a['student_id']]??(string)$a['student_id'])?></td><td><?=(int)$a['question_index']+1?></td><td><?=e((string)$a['answer'])?></td><td><?=$q && $a['answer']===$q['key']?'Đúng':'Sai'?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php endif; ?></main></body></html>
