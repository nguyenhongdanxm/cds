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
function qp_csv(string $value): string {
    if (preg_match('/^[=+@\-\t\r]/u',$value)) $value="'".$value;
    return '"'.str_replace('"','""',$value).'"';
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
                $q = ['text'=>trim((string)($_POST['text'] ?? '')),'choices'=>[],'key'=>(string)($_POST['key'] ?? ''),'image'=>trim((string)($_POST['image']??'')),'explanation'=>trim((string)($_POST['explanation']??''))];
                foreach (['A','B','C','D'] as $letter) $q['choices'][$letter] = trim((string)($_POST['choice_' . $letter] ?? ''));
                if ($q['text'] === '' || mb_strlen($q['text']) > 2000 || !in_array($q['key'], ['A','B','C','D'], true)) throw new RuntimeException('Câu hỏi hoặc đáp án đúng chưa hợp lệ.');
                foreach ($q['choices'] as $choice) if ($choice === '' || mb_strlen($choice) > 500) throw new RuntimeException('Nhập đủ bốn lựa chọn A–D.');
                if ($q['image']!=='' && (mb_strlen($q['image'])>1000 || !preg_match('~^https://[^\s]+$~i',$q['image']))) throw new RuntimeException('Ảnh câu hỏi cần là liên kết HTTPS hợp lệ.');
                if (mb_strlen($q['explanation'])>2000) throw new RuntimeException('Giải thích đáp án quá dài.');
                if ($index !== false && $index >= 0 && isset($questions[$index])) $questions[$index] = $q;
                elseif (count($questions) < 100) $questions[] = $q;
                else throw new RuntimeException('Mỗi bộ tối đa 100 câu.');
            }
            $st = qp_db()->prepare('UPDATE cds_quiz_sets SET questions_json=?,updated_at=NOW() WHERE id=?');
            $st->execute([json_encode($questions, JSON_UNESCAPED_UNICODE),$setId]); qp_go($setId);
        }
        if ($action === 'import_questions') {
            $lines = preg_split('/\r\n|\r|\n/',trim((string)($_POST['bulk']??'')));
            if (!$lines || count($lines)>100) throw new RuntimeException('Mỗi lần nhập tối đa 100 dòng.');
            $questions = qp_questions($set);
            $incoming=[];
            foreach ($lines as $n=>$line) {
                if (trim($line)==='') continue;
                $parts=array_map('trim',explode('|',$line));
                if (count($parts)!==6 || !in_array(strtoupper($parts[5]),['A','B','C','D'],true)) throw new RuntimeException('Dòng '.($n+1).' chưa đúng 6 cột: câu hỏi | A | B | C | D | đáp án đúng.');
                if (mb_strlen($parts[0])>2000 || $parts[0]==='') throw new RuntimeException('Câu hỏi dòng '.($n+1).' không hợp lệ.');
                for ($j=1;$j<=4;$j++) if ($parts[$j]==='' || mb_strlen($parts[$j])>500) throw new RuntimeException('Lựa chọn dòng '.($n+1).' không hợp lệ.');
                $incoming[]=['text'=>$parts[0],'choices'=>['A'=>$parts[1],'B'=>$parts[2],'C'=>$parts[3],'D'=>$parts[4]],'key'=>strtoupper($parts[5])];
            }
            if (!$incoming || count($questions)+count($incoming)>100) throw new RuntimeException('Bộ câu hỏi tối đa 100 câu; chưa nhập dòng nào.');
            $st=qp_db()->prepare('UPDATE cds_quiz_sets SET questions_json=?,updated_at=NOW() WHERE id=?');
            $st->execute([json_encode(array_merge($questions,$incoming),JSON_UNESCAPED_UNICODE),$setId]);qp_go($setId);
        }
        if ($action === 'move_question') {
            $questions=qp_questions($set);
            $index=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
            $direction=(string)($_POST['direction']??'');
            $other=$index+($direction==='up'?-1:($direction==='down'?1:0));
            if ($index===false || !isset($questions[$index],$questions[$other]) || $other===$index) throw new RuntimeException('Không thể đổi thứ tự câu hỏi.');
            [$questions[$index],$questions[$other]]=[$questions[$other],$questions[$index]];
            $st=qp_db()->prepare('UPDATE cds_quiz_sets SET questions_json=?,updated_at=NOW() WHERE id=?');
            $st->execute([json_encode($questions,JSON_UNESCAPED_UNICODE),$setId]);qp_go($setId);
        }
        if ($action === 'open_session') {
            $classId = (string)($_POST['class_id'] ?? '');
            $mode = (string)($_POST['mode'] ?? 'computer');
            if (!in_array($mode,['computer','paper'],true)) throw new RuntimeException('Chế độ chơi không hợp lệ.');
            if (!isset($classMap[$classId]) || !qp_questions($set)) throw new RuntimeException('Chọn lớp và tạo ít nhất một câu hỏi.');
            $roster=[];
            foreach (csdl_students_all() as $student) if (!empty($student['active']) && (string)($student['class_id']??'')===$classId) $roster[(string)$student['id']] = (string)($student['name']??'');
            if (!$roster) throw new RuntimeException('Lớp chưa có học sinh đang học.');
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $code = (string)random_int(10000000, 99999999);
                try {
                    $st = qp_db()->prepare("INSERT INTO cds_quiz_sessions(code,set_id,class_id,owner_id,questions_json,roster_json,mode,status,created_at,expires_at) VALUES(?,?,?,?,?,?,?,'open',NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY))");
                    $st->execute([$code,$setId,$classId,qp_owner(),json_encode(qp_questions($set),JSON_UNESCAPED_UNICODE),json_encode($roster,JSON_UNESCAPED_UNICODE),$mode]);
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
$reportSession = null;
if ($set) {
    $st = qp_db()->prepare('SELECT * FROM cds_quiz_sessions WHERE set_id=? ORDER BY created_at DESC LIMIT 20');
    $st->execute([$setId]); $sessions = $st->fetchAll();
    $viewCode = (string)($_GET['report'] ?? '');
    foreach ($sessions as $session) if ($session['code'] === $viewCode) {
        $reportSession = $session;
        $reportQuestions = json_decode((string)$session['questions_json'],true) ?: [];
        $st = qp_db()->prepare('SELECT student_id,question_index,answer FROM cds_quiz_answers WHERE session_code=?');
        $st->execute([$viewCode]); $answers = $st->fetchAll(); break;
    }
}
$questions = $set ? qp_questions($set) : [];
$reportStudents=[];
$reportGrid=[];
if ($reportSession) {
    $reportStudents=json_decode((string)($reportSession['roster_json']??''),true) ?: [];
    if (!$reportStudents) foreach (csdl_students_all() as $student) if ((string)($student['class_id']??'')===(string)$reportSession['class_id']) $reportStudents[(string)$student['id']] = (string)($student['name']??'');
    foreach ($answers as $a) $reportGrid[(string)$a['student_id']][(int)$a['question_index']] = (string)$a['answer'];
    if (($_GET['export']??'')==='csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ket-qua-quiz-'.$viewCode.'.csv"');
        echo "\xEF\xBB\xBF";
        $head=['Học sinh','Số đúng','Số câu đã trả lời','Tổng câu'];
        foreach ($reportQuestions as $i=>$q) { $head[]='Câu '.($i+1).' (chọn)';$head[]='Câu '.($i+1).' (đúng/sai)'; }
        echo implode(',',array_map('qp_csv',$head))."\r\n";
        foreach ($reportStudents as $id=>$name) {
            $correct=0;$answered=0;$tail=[];
            foreach ($reportQuestions as $i=>$q) { $a=$reportGrid[$id][$i]??'';if ($a!=='') {$answered++;if ($a===($q['key']??''))$correct++;}$tail[]=$a;$tail[]=$a===''?'Chưa trả lời':($a===($q['key']??'')?'Đúng':'Sai'); }
            echo implode(',',array_map('qp_csv',array_merge([$name,(string)$correct,(string)$answered,(string)count($reportQuestions)],$tail)))."\r\n";
        }
        exit;
    }
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bộ câu hỏi · Trò chơi A–D</title>
<style>body{margin:0;background:#edf3f9;color:#183153;font:16px system-ui,Arial}header{background:#123460;color:white;padding:18px max(18px,calc((100vw - 1100px)/2))}header a{color:white}main{max-width:1100px;margin:24px auto;padding:0 16px}.card{background:white;padding:20px;border-radius:15px;box-shadow:0 4px 18px #152c4618;margin:14px 0}h1{font-size:28px;margin:8px 0}h2{font-size:20px}input,select,textarea{font:inherit;padding:10px;border:1px solid #aebed0;border-radius:8px;max-width:100%}input[type=text],textarea{width:100%;box-sizing:border-box}textarea{min-height:70px}label{font-weight:700;display:block;margin:12px 0 5px}button,.button{border:0;background:#126bb0;color:#fff;padding:10px 15px;border-radius:9px;cursor:pointer;text-decoration:none;display:inline-block;margin:4px 4px 4px 0}.secondary{background:#435e7b}.danger{background:#a93636}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}.muted{color:#607086}.notice{background:#fff2cd;padding:12px;border-radius:8px}.question{border-top:1px solid #d7e2ec;padding:12px 0}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #d9e2ec;text-align:left}</style></head><body>
<header><a href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><h1>Bộ câu hỏi A–D · Chơi trên máy tính hoặc thẻ giấy</h1></header><main><p><a class="button secondary" href="<?=BASE_URL?>hoclieu_quiz_cards.php">Quản lý và in thẻ trả lời A–D</a></p>
<?php if ($message): ?><p class="notice"><?=e($message)?></p><?php endif; ?>
<div class="card"><h2>Tạo bộ câu hỏi</h2><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="create"><label>Tên bộ câu hỏi</label><input type="text" name="title" maxlength="255" required placeholder="Ví dụ: Ôn tập Vật lý 10 – Chương 1"><button>Tạo bộ mới</button></form></div>
<div class="card"><h2>Bộ câu hỏi của tôi</h2><?php if (!$sets): ?><p>Chưa có bộ câu hỏi.</p><?php endif; ?><?php foreach ($sets as $row): ?><p><a href="?set=<?=e(rawurlencode((string)$row['id']))?>"><?=e((string)$row['title'])?></a> <span class="muted">· cập nhật <?=e((string)$row['updated_at'])?></span></p><?php endforeach; ?></div>
<?php if ($set): ?>
<div class="card"><h2><?=e((string)$set['title'])?></h2><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="rename"><input type="text" name="title" value="<?=e((string)$set['title'])?>" maxlength="255" required><button class="secondary">Đổi tên</button></form><p class="muted"><?=count($questions)?> câu trắc nghiệm. Cùng một bộ được dùng cho hai cách chơi.</p></div>
<div class="card"><h2>Thêm câu hỏi A–D</h2><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="save_question"><input type="hidden" name="index" value="-1"><label>Câu hỏi</label><textarea name="text" maxlength="2000" required></textarea><label>Liên kết ảnh HTTPS (nếu có)</label><input type="url" name="image" placeholder="https://..."><div class="grid"><?php foreach (['A','B','C','D'] as $letter): ?><div><label>Đáp án <?=$letter?></label><input type="text" name="choice_<?=$letter?>" maxlength="500" required></div><?php endforeach; ?></div><label>Đáp án đúng</label><select name="key"><?php foreach (['A','B','C','D'] as $letter): ?><option><?=$letter?></option><?php endforeach; ?></select><label>Giải thích đáp án (nếu có)</label><textarea name="explanation" maxlength="2000"></textarea><button>Lưu câu hỏi</button></form></div>
<div class="card"><details><summary><strong>Nhập nhanh nhiều câu hỏi</strong></summary><p>Mỗi dòng: <code>Câu hỏi | Lựa chọn A | Lựa chọn B | Lựa chọn C | Lựa chọn D | Đáp án đúng</code>. Ví dụ: <code>2 + 2 = ? | 3 | 4 | 5 | 6 | B</code>. Kiểm tra lại dấu | trong nội dung trước khi nhập.</p><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="import_questions"><textarea name="bulk" required style="min-height:160px" placeholder="Dán mỗi câu hỏi trên một dòng"></textarea><button>Thêm các câu hợp lệ</button></form></details></div>
<div class="card"><h2>Danh sách câu hỏi</h2><?php foreach ($questions as $i=>$q): ?><div class="question"><strong>Câu <?=$i+1?>. <?=e((string)$q['text'])?></strong><p class="muted">Đúng: <?=e((string)$q['key'])?></p><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="move_question"><input type="hidden" name="index" value="<?=$i?>"><button name="direction" value="up" class="secondary" <?=$i===0?'disabled':''?>>↑</button><button name="direction" value="down" class="secondary" <?=$i===count($questions)-1?'disabled':''?>>↓</button></form><details><summary>Sửa câu này</summary><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="save_question"><input type="hidden" name="index" value="<?=$i?>"><label>Câu hỏi</label><textarea name="text" required><?=e((string)$q['text'])?></textarea><label>Liên kết ảnh HTTPS</label><input type="url" name="image" value="<?=e((string)($q['image']??''))?>"><div class="grid"><?php foreach (['A','B','C','D'] as $letter): ?><div><label><?=$letter?></label><input type="text" name="choice_<?=$letter?>" value="<?=e((string)($q['choices'][$letter]??''))?>" required></div><?php endforeach; ?></div><label>Đáp án đúng</label><select name="key"><?php foreach (['A','B','C','D'] as $letter): ?><option <?=($q['key']===$letter)?'selected':''?>><?=$letter?></option><?php endforeach; ?></select><label>Giải thích đáp án</label><textarea name="explanation"><?=e((string)($q['explanation']??''))?></textarea><button>Lưu chỉnh sửa</button></form><form method="post" onsubmit="return confirm('Xóa câu hỏi này?')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="index" value="<?=$i?>"><button class="danger">Xóa câu</button></form></details></div><?php endforeach; ?></div>
<div class="card"><h2>Mở lượt chơi</h2><p>Chọn máy tính để học sinh trả lời trên thiết bị, hoặc thẻ giấy để giáo viên quét. Lượt chơi mở trong 24 giờ. Đây là chế độ luyện tập, chưa dùng để kiểm tra định danh.</p><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="open_session"><select name="class_id" required><option value="">Chọn lớp</option><?php foreach ($classes as $c): ?><option value="<?=e((string)$c['id'])?>"><?=e((string)$c['name'])?></option><?php endforeach; ?></select><select name="mode"><option value="computer">Chơi trên máy tính</option><option value="paper">Chơi bằng thẻ giấy</option></select><button>Tạo lượt chơi</button></form></div>
<div class="card"><h2>Các lượt chơi</h2><p>Địa chỉ học sinh trên máy tính: <strong><?=e(BASE_URL)?>hoclieu_game_quiz_join.php</strong></p><div style="overflow:auto"><table><tr><th>Mã / chế độ</th><th>Lớp</th><th>Trạng thái</th><th>Báo cáo</th></tr><?php foreach ($sessions as $s): ?><tr><td><strong><?=e((string)$s['code'])?></strong><br><?=($s['mode']==='paper'?'Thẻ giấy':'Máy tính')?></td><td><?=e($classMap[(string)$s['class_id']]??(string)$s['class_id'])?></td><td><?=e((string)$s['status'])?></td><td><?php if ($s['mode']==='paper'): ?><a class="button" href="<?=BASE_URL?>hoclieu_game_quiz_screen.php?code=<?=e((string)$s['code'])?>" target="_blank" rel="noopener">Màn hình chiếu</a><a class="button" href="<?=BASE_URL?>hoclieu_game_qr_trial.php?code=<?=e((string)$s['code'])?>">Máy quét</a><?php endif; ?><a href="?set=<?=e(rawurlencode($setId))?>&amp;report=<?=e((string)$s['code'])?>">Xem kết quả</a><?php if ($s['status']==='open'): ?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="close_session"><input type="hidden" name="code" value="<?=e((string)$s['code'])?>"><button class="secondary">Đóng</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div></div>
<?php if ($reportSession): ?>
<div class="card"><h2>Kết quả mã <?=e($viewCode)?></h2><p><?=($reportSession['mode']==='paper'?'Thẻ giấy':'Máy tính')?> · <?=count($reportQuestions)?> câu · <?=count($reportStudents)?> học sinh trong lớp</p><a class="button secondary" href="?set=<?=e(rawurlencode($setId))?>&amp;report=<?=e($viewCode)?>&amp;export=csv">Xuất CSV chi tiết</a>
<div style="overflow:auto"><table><thead><tr><th>Học sinh</th><th>Số đúng</th><th>Đã trả lời</th><?php foreach ($reportQuestions as $i=>$q): ?><th title="<?=e((string)$q['text'])?>">C<?=$i+1?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($reportStudents as $id=>$name): $correct=0;$answered=0;foreach ($reportQuestions as $i=>$q) { $a=$reportGrid[$id][$i]??'';if ($a!=='') {$answered++;if ($a===($q['key']??''))$correct++;} } ?>
<tr><td><?=e($name)?></td><td><?=$correct?> / <?=count($reportQuestions)?></td><td><?=$answered?></td><?php foreach ($reportQuestions as $i=>$q): $a=$reportGrid[$id][$i]??''; ?><td style="background:<?=$a===''?'#f1f4f7':($a===($q['key']??'')?'#e3f6e7':'#ffe9e5')?>"><?=e($a?:'—')?></td><?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table></div><p class="muted">Xanh: đúng · Đỏ: sai · Xám: chưa trả lời. Di chuột lên tiêu đề C1, C2… để xem câu hỏi.</p>
<h3>Thống kê từng câu</h3><div style="overflow:auto"><table><thead><tr><th>Câu</th><th>Đáp án</th><th>Đúng</th><th>Sai</th><th>Chưa trả lời</th><th>Tỉ lệ đúng</th></tr></thead><tbody>
<?php foreach ($reportQuestions as $i=>$q): $right=0;$wrong=0;foreach ($reportStudents as $id=>$name) { $a=$reportGrid[$id][$i]??'';if ($a!=='') { if ($a===($q['key']??''))$right++;else $wrong++; } }$missing=count($reportStudents)-$right-$wrong; ?>
<tr><td>C<?=$i+1?>. <?=e((string)$q['text'])?></td><td><?=e((string)($q['key']??''))?></td><td><?=$right?></td><td><?=$wrong?></td><td><?=$missing?></td><td><?=($right+$wrong)>0?round(100*$right/($right+$wrong),1):0?>% (trong số đã trả lời)</td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
<?php endif; ?></main></body></html>
