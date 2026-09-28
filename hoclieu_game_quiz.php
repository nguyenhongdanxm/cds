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
            $st = qp_db()->prepare('INSERT INTO cds_quiz_sets(id,owner_id,title,questions_json,is_public,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())');
            $st->execute([$id,qp_owner(),$title,'[]',($_POST['public']??'')==='1'?1:0]); qp_go($id);
        }
        $set = qp_set($setId, $action !== 'clone_set');
        if (!$set) throw new RuntimeException('Không tìm thấy bộ câu hỏi hoặc không có quyền sửa.');
        if ($action === 'visibility') {
            $public=($_POST['public']??'')==='1'?1:0;
            qp_db()->prepare('UPDATE cds_quiz_sets SET is_public=?,updated_at=NOW() WHERE id=?')->execute([$public,$setId]); qp_go($setId);
        }
        if ($action === 'clone_set') {
            if (empty($set['is_public']) && !qp_admin() && $set['owner_id']!==qp_owner()) throw new RuntimeException('Bộ câu hỏi không công khai.');
            $id='qs_'.bin2hex(random_bytes(12));
            qp_db()->prepare('INSERT INTO cds_quiz_sets(id,owner_id,title,questions_json,is_public,created_at,updated_at) VALUES(?,?,?,?,0,NOW(),NOW())')->execute([$id,qp_owner(),$set['title'].' · Bản sao',$set['questions_json']]); qp_go($id);
        }
        if ($action === 'rename') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 255) throw new RuntimeException('Tên bộ câu hỏi không hợp lệ.');
            $st = qp_db()->prepare('UPDATE cds_quiz_sets SET title=?,updated_at=NOW() WHERE id=?');
            $st->execute([$title,$setId]); qp_go($setId);
        }
        if ($action === 'save_question' || $action === 'delete_question' || $action === 'duplicate_question') {
            $questions = qp_questions($set);
            $index = filter_var($_POST['index'] ?? '-1', FILTER_VALIDATE_INT);
            if ($action === 'delete_question' || $action === 'duplicate_question') {
                if ($index === false || !isset($questions[$index])) throw new RuntimeException('Câu hỏi không tồn tại.');
                if ($action === 'delete_question') array_splice($questions, $index, 1);
                elseif (count($questions)<100) array_splice($questions,$index+1,0,[$questions[$index]]);
                else throw new RuntimeException('Mỗi bộ tối đa 100 câu.');
            } else {
                $type=(string)($_POST['type']??'single');
                if (!in_array($type,['single','paper_logic','multi','match','fill','order'],true)) throw new RuntimeException('Loại câu hỏi không hợp lệ.');
                $q = ['type'=>$type,'text'=>trim((string)($_POST['text'] ?? '')),'choices'=>[],'key'=>(string)($_POST['key'] ?? ''),'image'=>trim((string)($_POST['image']??'')),'explanation'=>trim((string)($_POST['explanation']??'')),'video'=>trim((string)($_POST['video']??'')),'audio'=>trim((string)($_POST['audio']??''))];
                foreach (['A','B','C','D'] as $letter) $q['choices'][$letter] = trim((string)($_POST['choice_' . $letter] ?? ''));
                if ($q['text'] === '' || mb_strlen($q['text']) > 2000) throw new RuntimeException('Câu hỏi không hợp lệ.');
                if ($type==='fill') {
                    $q['fill_answers']=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)($_POST['fill_answers']??''))),static fn($v)=>$v!==''));
                    if (!$q['fill_answers'] || count($q['fill_answers'])>10) throw new RuntimeException('Nhập ít nhất một đáp án chấp nhận cho câu điền từ.');
                    foreach ($q['fill_answers'] as $item) if (mb_strlen($item)>200) throw new RuntimeException('Đáp án điền từ quá dài.');
                    $q['choices']=[];$q['key']='';
                } elseif ($type==='order') {
                    $q['steps']=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)($_POST['steps']??''))),static fn($v)=>$v!==''));
                    if (count($q['steps'])<2 || count($q['steps'])>8 || count(array_unique($q['steps']))!==count($q['steps'])) throw new RuntimeException('Cần 2–8 bước sắp xếp khác nhau.');
                    foreach ($q['steps'] as $item) if (mb_strlen($item)>200) throw new RuntimeException('Nội dung bước quá dài.');
                    $q['choices']=[];$q['key']='';
                } elseif ($type==='match') {
                    $q['pairs']=[];
                    foreach (preg_split('/\r\n|\r|\n/',trim((string)($_POST['pairs']??''))) as $line) {
                        $parts=array_map('trim',explode('|',$line));
                        if (count($parts)!==2 || $parts[0]==='' || $parts[1]==='' || mb_strlen($parts[0])>200 || mb_strlen($parts[1])>200) throw new RuntimeException('Mỗi cặp nối cần dạng Vế trái | Vế phải.');
                        $q['pairs'][]=$parts;
                    }
                    if (count($q['pairs'])<2 || count($q['pairs'])>8 || count(array_unique(array_column($q['pairs'],1)))!==count($q['pairs'])) throw new RuntimeException('Cần 2–8 cặp nối và các vế phải khác nhau.');
                    $q['choices']=[];$q['key']='';
                } else {
                    if ($type==='paper_logic') { $q['choices']=['A'=>'Chỉ A đúng','B'=>'Chỉ B đúng','C'=>'Cả hai đều đúng','D'=>'Cả hai đều sai']; $q['key']=(string)($_POST['logic_key']??''); }
                    foreach ($q['choices'] as $choice) if ($choice === '' || mb_strlen($choice) > 500) throw new RuntimeException('Nhập đủ bốn lựa chọn A–D.');
                    if ($type==='multi') {
                        $q['keys']=array_values(array_intersect(['A','B','C','D'],(array)($_POST['keys']??[])));
                        if (count($q['keys'])<2) throw new RuntimeException('Chọn ít nhất hai phương án đúng.');
                        $q['key']='';
                    } elseif (!in_array($q['key'],['A','B','C','D'],true)) throw new RuntimeException('Chọn đáp án đúng.');
                }
                if (!empty($_FILES['image_file']['name'])) {
                    $file=$_FILES['image_file'];
                    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>5*1024*1024) throw new RuntimeException('Ảnh cần nhỏ hơn 5 MB.');
                    $info=getimagesize((string)$file['tmp_name']);$mime=$info['mime']??'';
                    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                    if (!isset($extensions[$mime]) || ($info[0]??0)<10 || ($info[1]??0)<10) throw new RuntimeException('Chỉ nhận ảnh JPG, PNG hoặc WebP hợp lệ.');
                    $dir=DATA_PATH.'/quiz_media';if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Không tạo được thư mục ảnh.');
                    $fileName=bin2hex(random_bytes(16)).'.'.$extensions[$mime];
                    if (!move_uploaded_file((string)$file['tmp_name'],$dir.'/'.$fileName)) throw new RuntimeException('Không lưu được ảnh.');
                    $q['image']=BASE_URL.'hoclieu_game_quiz_media.php?f='.$fileName;
                }
                if ($q['image']!=='' && (mb_strlen($q['image'])>1000 || !preg_match('~^https?://[^\s]+$~i',$q['image']))) throw new RuntimeException('Ảnh câu hỏi cần là liên kết hợp lệ.');
                if ($q['video']!=='' && !preg_match('~^https://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{11})(?:[&?][^\s]*)?$~i',$q['video'],$videoMatch)) throw new RuntimeException('Video cần là liên kết YouTube hợp lệ.');
                if ($q['video']!=='') $q['video']='https://www.youtube-nocookie.com/embed/'.$videoMatch[1];
                if ($q['audio']!=='' && (mb_strlen($q['audio'])>1000 || !preg_match('~^https://[^\s]+$~i',$q['audio']))) throw new RuntimeException('Âm thanh cần liên kết HTTPS.');
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
            if ($mode==='paper') foreach (qp_questions($set) as $q) if (!in_array(qp_question_type($q),['single','paper_logic'],true)) throw new RuntimeException('Chơi bằng thẻ giấy chỉ hỗ trợ câu A–D một đáp án hoặc Hai mệnh đề. Hãy dùng bộ câu hỏi phù hợp.');
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
function qp_editor(string $csrf,string $setId,int $index,array $q=[]): void {
    $type=qp_question_type($q);$choices=(array)($q['choices']??[]);
    $types=['single'=>['◉','Một đáp án','Máy · thẻ giấy'],'multi'=>['☑','Nhiều đáp án','Máy tính'],'paper_logic'=>['◇','Hai mệnh đề','Máy · thẻ giấy'],'match'=>['⇄','Nối cặp','Máy tính'],'fill'=>['✎','Điền đáp án','Máy tính'],'order'=>['↕','Sắp xếp','Máy tính']];
    ?>
    <form method="post" enctype="multipart/form-data" class="editor-form" data-editor>
      <input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="save_question"><input type="hidden" name="index" value="<?=$index?>">
      <div class="editor-heading"><div><span class="eyebrow">TRÌNH SOẠN CÂU HỎI</span><h3><?=$index<0?'Tạo câu hỏi mới':'Chỉnh sửa câu '.($index+1)?></h3></div><span class="pill">Tự động phù hợp chế độ chơi</span></div>
      <div class="type-grid" role="group" aria-label="Loại câu hỏi"><?php foreach($types as $name=>$meta): ?><label class="type-tile"><input type="radio" name="type" value="<?=e($name)?>" <?=$type===$name?'checked':''?>><span class="type-icon"><?=e($meta[0])?></span><strong><?=e($meta[1])?></strong><small><?=e($meta[2])?></small></label><?php endforeach; ?></div>
      <label for="prompt-<?=$index?>">Nội dung câu hỏi</label><textarea id="prompt-<?=$index?>" name="text" maxlength="2000" rows="3" required placeholder="Nhập câu hỏi rõ ràng, dễ đọc trên màn hình lớn…"><?=e((string)($q['text']??''))?></textarea>
      <div data-for="single multi" class="answer-grid"><?php foreach(['A','B','C','D'] as $letter): ?><div class="answer-row answer-<?=$letter?>"><span><?=$letter?></span><input type="text" name="choice_<?=$letter?>" maxlength="500" value="<?=e((string)($choices[$letter]??''))?>" placeholder="Phương án <?=$letter?>"><label class="correct-radio" data-for="single"><input type="radio" name="key" value="<?=$letter?>" <?=(($q['key']??'A')===$letter)?'checked':''?>> Đúng</label><label class="correct-check" data-for="multi"><input type="checkbox" name="keys[]" value="<?=$letter?>" <?=in_array($letter,(array)($q['keys']??[]),true)?'checked':''?>> Đúng</label></div><?php endforeach; ?></div>
      <div data-for="paper_logic" class="specific"><p class="hint">Ghi hai mệnh đề A và B trong nội dung câu hỏi. Học sinh chọn một trong bốn kết luận sau.</p><div class="logic-grid"><?php foreach(['A'=>'Chỉ A đúng','B'=>'Chỉ B đúng','C'=>'Cả hai đều đúng','D'=>'Cả hai đều sai'] as $letter=>$label): ?><label><input type="radio" name="logic_key" value="<?=$letter?>" <?=(($q['key']??'A')===$letter)?'checked':''?>> <b><?=$letter?></b> <?=$label?></label><?php endforeach; ?></div></div>
      <div data-for="match" class="specific"><label>Các cặp nối <small>mỗi dòng: Vế trái | Vế phải</small></label><textarea name="pairs" rows="5" placeholder="Việt Nam | Hà Nội&#10;Nhật Bản | Tokyo"><?=e(implode("\n",array_map(static fn($p)=>implode(' | ',(array)$p),(array)($q['pairs']??[]))))?></textarea><p class="hint">Từ 2 đến 8 cặp, các vế phải khác nhau.</p></div>
      <div data-for="fill" class="specific"><label>Đáp án chấp nhận <small>mỗi dòng một cách viết</small></label><textarea name="fill_answers" rows="4" placeholder="Hà Nội&#10;Thành phố Hà Nội"><?=e(implode("\n",(array)($q['fill_answers']??[])))?></textarea><p class="hint">Không phân biệt chữ hoa và khoảng trắng thừa.</p></div>
      <div data-for="order" class="specific"><label>Thứ tự đúng <small>mỗi dòng một bước</small></label><textarea name="steps" rows="5" placeholder="Bước đầu tiên&#10;Bước tiếp theo&#10;Bước cuối cùng"><?=e(implode("\n",(array)($q['steps']??[])))?></textarea><p class="hint">Từ 2 đến 8 bước. Học sinh sẽ nhận các bước đã đảo thứ tự.</p></div>
      <details class="media-panel" <?=!empty($q['image'])||!empty($q['video'])||!empty($q['audio'])?'open':''?>><summary>＋ Chèn ảnh, video, âm thanh và giải thích</summary><div class="media-grid"><div><label>Ảnh minh họa từ máy <small>JPG, PNG, WebP · tối đa 5 MB</small></label><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"><p class="hint">Hoặc dán URL ảnh:</p><input type="url" name="image" value="<?=e((string)($q['image']??''))?>" placeholder="https://…"><?php if(!empty($q['image'])): ?><img class="image-preview" src="<?=e((string)$q['image'])?>" alt="Ảnh câu hỏi hiện tại"><?php endif; ?></div><div><label>Video YouTube</label><input type="url" name="video" value="<?=e((string)($q['video']??''))?>" placeholder="https://www.youtube.com/watch?v=…"><label>Liên kết âm thanh HTTPS</label><input type="url" name="audio" value="<?=e((string)($q['audio']??''))?>" placeholder="https://…/audio.mp3"></div></div><label>Giải thích khi công bố đáp án</label><textarea name="explanation" maxlength="2000" rows="3" placeholder="Giải thích ngắn gọn vì sao đây là đáp án đúng…"><?=e((string)($q['explanation']??''))?></textarea></details>
      <div class="editor-footer"><span class="hint">Câu A–D và Hai mệnh đề dùng được với thẻ giấy.</span><button type="submit" class="btn primary"><?=$index<0?'＋ Thêm vào bộ câu hỏi':'Lưu chỉnh sửa'?></button></div>
    </form>
    <?php
}
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
            foreach ($reportQuestions as $i=>$q) { $a=$reportGrid[$id][$i]??'';if ($a!=='') {$answered++;if (qp_answer_correct($q,$a))$correct++;}$tail[]=qp_answer_label($a);$tail[]=$a===''?'Chưa trả lời':(qp_answer_correct($q,$a)?'Đúng':'Sai'); }
            echo implode(',',array_map('qp_csv',array_merge([$name,(string)$correct,(string)$answered,(string)count($reportQuestions)],$tail)))."\r\n";
        }
        exit;
    }
}
?>

<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hỏi đáp với QR code · Soạn câu hỏi</title>
<style>
:root{--ink:#17264d;--sub:#61708e;--line:#dfe5f1;--violet:#5846d8;--blue:#3268ec;--surface:#fff}*{box-sizing:border-box}body{margin:0;color:var(--ink);background:#f5f6fc;font:16px system-ui,-apple-system,Segoe UI,sans-serif}button,input,select,textarea{font:inherit}button{cursor:pointer}a{color:#4e45c8;text-decoration:none}a:hover{text-decoration:underline}.shell{max-width:1460px;margin:auto;padding:0 24px}.topbar{background:#fff;border-bottom:1px solid var(--line)}.topbar .shell{min-height:66px;display:flex;align-items:center;gap:22px}.brand{font-size:20px;font-weight:900;color:#352f9b}.top-link{font-weight:700;color:#647293}.topbar .spacer{flex:1}.small-link{font-size:14px}.hero{background:radial-gradient(circle at 84% 20%,#9867f4 0,transparent 38%),linear-gradient(115deg,#24266f,#5845c9 62%,#855bdc);color:#fff;padding:35px 0 47px}.hero h1{margin:7px 0;font-size:clamp(29px,3vw,43px);letter-spacing:-.035em}.hero p{margin:0;color:#e9e5ff}.eyebrow{font-size:11px;font-weight:900;letter-spacing:.15em;color:#6c58dd}.hero .eyebrow{color:#e4d6ff}.workspace{display:grid;grid-template-columns:275px minmax(0,1fr);gap:22px;align-items:start;margin-top:-25px;padding-bottom:60px}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 12px 30px #2f397110}.sidebar{position:sticky;top:14px;padding:20px}.sidebar h2{font-size:17px;margin:0 0 14px}.stack{display:grid;gap:9px}.set-link{display:block;border:1px solid #e7e9f4;border-radius:12px;padding:12px;color:var(--ink);font-weight:750;overflow-wrap:anywhere}.set-link:hover,.set-link.active{border-color:#ad9ffd;background:#f3f0ff;text-decoration:none}.set-link small{display:block;color:var(--sub);font-weight:500;margin-top:4px}.sidebar details{margin-top:16px}.sidebar summary,.media-panel summary{cursor:pointer;font-weight:800;color:#5146b6}.sidebar form{margin-top:10px}.content{min-width:0}.content>.panel{padding:26px;margin-bottom:20px}.notice{background:#fff0d9;border:1px solid #f1c576;padding:16px;border-radius:12px;margin:0 0 20px}.section-title{display:flex;justify-content:space-between;gap:15px;align-items:center;flex-wrap:wrap;margin-bottom:14px}.section-title h2{margin:0;font-size:23px}.muted,.hint{color:var(--sub)}.hint{font-size:13px;margin:7px 0}.pill{background:#eeeafb;color:#5145af;border-radius:30px;padding:6px 11px;font-size:12px;font-weight:800}.stats{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0}.stat{background:#f2f3fc;padding:12px 18px;border-radius:12px;min-width:120px}.stat b{display:block;font-size:22px}.stat small{color:var(--sub)}label{display:block;font-weight:750;margin:13px 0 6px}label small{font-weight:500;color:var(--sub)}input[type=text],input[type=url],input[type=search],textarea,select{width:100%;min-width:0;border:1px solid #cbd3e4;border-radius:10px;background:#fff;padding:11px 12px;color:var(--ink);outline-color:#7567eb}textarea{resize:vertical}input[type=file]{max-width:100%;font-size:13px}.btn{border:0;border-radius:10px;padding:10px 15px;font-weight:800;display:inline-flex;justify-content:center;align-items:center;gap:6px;background:#eaeefe;color:#3d47a5;text-decoration:none}.btn:hover{text-decoration:none;filter:brightness(.96)}.btn.primary{background:#6250dc;color:white}.btn.danger{background:#fff0f0;color:#bd394b}.btn.tiny{padding:7px 10px;font-size:13px}.row{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px}.editor-heading{display:flex;align-items:center;justify-content:space-between;gap:12px}.editor-heading h3{font-size:21px;margin:3px 0 15px}.type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:10px 0 24px}.type-tile{display:grid;grid-template-columns:34px 1fr;grid-template-rows:auto auto;align-items:center;gap:1px 9px;border:2px solid #e8e9f2;border-radius:13px;padding:11px;margin:0;cursor:pointer}.type-tile input{position:absolute;opacity:0}.type-tile:has(input:checked){border-color:#7161eb;background:#f2efff;box-shadow:0 2px 10px #6456da22}.type-tile:focus-within{outline:2px solid #7161eb}.type-icon{grid-row:span 2;display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#e7e4ff;color:#5041c8;font-size:19px}.type-tile strong{font-size:14px}.type-tile small{color:var(--sub);font-size:11px}.answer-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0}.answer-row{display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #e0e3f0;border-radius:13px}.answer-row>span{flex:none;width:37px;height:37px;display:grid;place-items:center;border-radius:10px;font-weight:900;color:#fff}.answer-A>span{background:#f0a734}.answer-B>span{background:#496af1}.answer-C>span{background:#d7609c}.answer-D>span{background:#2ca99a}.answer-row input[type=text]{border:0;flex:1;padding:5px;min-width:0}.answer-row label{flex:none;white-space:nowrap;font-size:12px;margin:0;color:#4454aa}.specific{background:#f8f9fe;padding:16px;border-radius:13px;margin:15px 0}.specific label:first-child{margin-top:0}.logic-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.logic-grid label{border:1px solid #dce1ee;padding:11px;border-radius:10px;background:#fff;margin:0}.logic-grid b{color:#6250dc}.media-panel{border:1px solid var(--line);border-radius:13px;padding:16px;margin:19px 0}.media-grid{display:grid;grid-template-columns:1fr 1fr;gap:17px}.image-preview{display:block;max-width:100%;max-height:150px;object-fit:contain;margin:10px 0;border-radius:9px}.editor-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:18px}.question-card{border:1px solid #e2e5f0;border-radius:14px;padding:16px;margin:11px 0}.question-top{display:flex;gap:12px;align-items:flex-start}.number{background:#ece9ff;color:#5643bf;font-weight:900;border-radius:9px;padding:8px 11px;white-space:nowrap}.question-body{flex:1;min-width:0}.question-body h3{margin:2px 0 7px;font-size:16px;overflow-wrap:anywhere}.question-body p{margin:5px 0}.question-tools{display:flex;gap:5px;flex-wrap:wrap;margin-top:12px}.question-tools form{display:inline-flex;gap:5px}.question-card details{border-top:1px solid var(--line);margin-top:15px;padding-top:13px}.question-card summary{cursor:pointer;color:#5146b6;font-weight:800}.mini-choices{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin:10px 0}.mini-choices span{background:#f6f7fb;padding:7px;border-radius:7px;font-size:13px}.empty{padding:28px;text-align:center;background:#f7f7fe;border-radius:13px;color:#53627d}.session-table{overflow:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{padding:12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{color:#63718b}details>summary{list-style-position:inside}.section-anchor{scroll-margin-top:18px}[hidden]{display:none!important}@media(max-width:900px){.workspace{grid-template-columns:1fr}.sidebar{position:static}.type-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.shell{padding:0 13px}.content>.panel{padding:16px}.answer-grid,.media-grid,.two-col,.logic-grid{grid-template-columns:1fr}.type-grid{grid-template-columns:repeat(2,1fr)}.topbar .shell{gap:10px}.small-link{display:none}}
</style></head><body>
<nav class="topbar"><div class="shell"><a class="brand" href="<?=BASE_URL?>hoclieu.php?tab=games">◉ Hỏi đáp với QR code</a><a class="top-link" href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><span class="spacer"></span><a class="small-link" href="<?=BASE_URL?>hoclieu_quiz_cards.php">In thẻ trả lời A–D ↗</a></div></nav>
<div class="hero"><div class="shell"><span class="eyebrow">HỌC LIỆU &amp; THI · TRÒ CHƠI</span><h1>Biến câu hỏi thành giờ học thú vị</h1><p>Tạo bộ câu hỏi đẹp mắt, cho học sinh chơi trên máy hoặc quét thẻ giấy bằng điện thoại.</p></div></div>
<div class="shell workspace"><aside class="panel sidebar"><h2>Thư viện của bạn</h2><input type="search" id="set-search" placeholder="Tìm bộ câu hỏi…" aria-label="Tìm bộ câu hỏi"><div class="stack" id="set-list" style="margin-top:14px"><?php if (!$sets): ?><p class="muted">Chưa có bộ câu hỏi.</p><?php endif; ?><?php foreach($sets as $row): ?><div class="set-item" data-title="<?=e(mb_strtolower((string)$row['title']))?>"><?php if (qp_admin() || $row['owner_id']===qp_owner()): ?><a class="set-link <?=$setId===$row['id']?'active':''?>" href="?set=<?=e(rawurlencode((string)$row['id']))?>"><?=e((string)$row['title'])?><small><?=!empty($row['is_public'])?'🌐 Công khai':'🔒 Riêng tư'?></small></a><?php else: ?><div class="set-link"><?=e((string)$row['title'])?><small>🌐 Công khai</small></div><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="clone_set"><button class="btn tiny">Tạo bản sao</button></form><?php endif; ?></div><?php endforeach; ?></div><details <?=$set?'':'open'?>><summary>＋ Tạo bộ câu hỏi</summary><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="create"><label>Tên bộ câu hỏi</label><input type="text" name="title" maxlength="255" required placeholder="Ví dụ: Ôn tập Vật lý 10"><label>Chia sẻ</label><select name="public"><option value="0">Riêng tư</option><option value="1">Công khai cho giáo viên</option></select><button class="btn primary" style="margin-top:13px">Tạo bộ mới</button></form></details><p class="hint">Bộ công khai cho giáo viên khác tạo bản sao. Câu hỏi của bạn vẫn thuộc bộ gốc.</p></aside>
<main class="content"><?php if($message): ?><div class="notice" role="alert"><?=e($message)?></div><?php endif; ?>
<?php if (!$set): ?><section class="panel"><span class="eyebrow">BẮT ĐẦU</span><h2>Chọn một bộ hoặc tạo bộ câu hỏi mới</h2><p class="muted">Soạn sáu dạng câu hỏi, đính kèm ảnh, video và âm thanh. Chế độ thẻ giấy dùng câu A–D một đáp án hoặc Hai mệnh đề.</p><div class="stats"><div class="stat"><b>6</b><small>dạng câu hỏi</small></div><div class="stat"><b>2</b><small>cách chơi</small></div></div></section>
<?php else: ?>
<section class="panel"><div class="section-title"><div><span class="eyebrow">BỘ CÂU HỎI</span><h2><?=e((string)$set['title'])?></h2></div><a class="btn primary" href="#new-question">＋ Thêm câu hỏi</a></div><div class="stats"><div class="stat"><b><?=count($questions)?></b><small>câu hỏi</small></div><div class="stat"><b><?=count($sessions)?></b><small>lượt chơi</small></div><div class="stat"><b><?=!empty($set['is_public'])?'🌐':'🔒'?></b><small><?=!empty($set['is_public'])?'Công khai':'Riêng tư'?></small></div></div><details><summary>Thiết lập bộ câu hỏi</summary><div class="two-col"><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="rename"><label>Tên bộ</label><input type="text" name="title" value="<?=e((string)$set['title'])?>" maxlength="255" required><button class="btn" style="margin-top:10px">Lưu tên</button></form><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="visibility"><label>Quyền xem</label><select name="public"><option value="0" <?=empty($set['is_public'])?'selected':''?>>Riêng tư</option><option value="1" <?=!empty($set['is_public'])?'selected':''?>>Công khai cho giáo viên</option></select><button class="btn" style="margin-top:10px">Lưu chia sẻ</button></form></div></details></section>
<section class="panel section-anchor" id="new-question"><?php qp_editor($csrf,$setId,-1); ?></section>
<section class="panel" id="question-list"><div class="section-title"><h2>Danh sách câu hỏi</h2><span class="pill"><?=count($questions)?> / 100 câu</span></div><?php if (!$questions): ?><p class="empty">✨ Bộ câu hỏi còn trống. Chọn một loại ở trên để tạo câu đầu tiên.</p><?php endif; ?><?php $typeNames=['single'=>'Một đáp án','multi'=>'Nhiều đáp án','paper_logic'=>'Hai mệnh đề','match'=>'Nối cặp','fill'=>'Điền đáp án','order'=>'Sắp xếp']; foreach($questions as $i=>$q): ?><article class="question-card"><div class="question-top"><span class="number"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><div class="question-body"><h3><?=e((string)$q['text'])?></h3><p class="hint"><?=e($typeNames[qp_question_type($q)]??'Một đáp án')?> · Đúng: <?=e(qp_correct_label($q))?></p><?php if(!empty($q['image'])): ?><img class="image-preview" src="<?=e((string)$q['image'])?>" alt="Ảnh minh họa"><?php endif; ?><?php if(in_array(qp_question_type($q),['single','multi','paper_logic'],true)): ?><div class="mini-choices"><?php foreach(['A','B','C','D'] as $letter): ?><span><b><?=$letter?>.</b> <?=e((string)($q['choices'][$letter]??''))?></span><?php endforeach; ?></div><?php endif; ?><div class="question-tools"><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="move_question"><input type="hidden" name="index" value="<?=$i?>"><button class="btn tiny" name="direction" value="up" <?=$i===0?'disabled':''?> title="Đưa lên">↑</button><button class="btn tiny" name="direction" value="down" <?=$i===count($questions)-1?'disabled':''?> title="Đưa xuống">↓</button></form><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="duplicate_question"><input type="hidden" name="index" value="<?=$i?>"><button class="btn tiny">⧉ Nhân bản</button></form><button class="btn tiny" type="button" data-edit="edit-<?=$i?>">✎ Chỉnh sửa</button><form method="post" onsubmit="return confirm('Xóa câu hỏi này?')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="index" value="<?=$i?>"><button class="btn tiny danger">Xóa</button></form></div></div></div><details id="edit-<?=$i?>"><summary>Sửa nội dung câu hỏi</summary><?php qp_editor($csrf,$setId,$i,$q); ?></details></article><?php endforeach; ?></section>
<section class="panel"><details><summary><strong>Nhập nhanh nhiều câu A–D</strong></summary><p class="hint">Mỗi dòng: Câu hỏi | A | B | C | D | Đáp án đúng. Ví dụ: 2 + 2 = ? | 3 | 4 | 5 | 6 | B</p><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="import_questions"><textarea name="bulk" rows="6" required placeholder="Dán mỗi câu trên một dòng"></textarea><button class="btn primary">Nhập câu hỏi</button></form></details></section>
<section class="panel"><div class="section-title"><h2>Mở lượt chơi</h2></div><p class="muted">Chơi trên máy hỗ trợ cả sáu dạng. Chơi bằng thẻ giấy hỗ trợ Một đáp án và Hai mệnh đề. Lượt chơi mở trong 24 giờ.</p><form method="post" class="two-col"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="open_session"><div><label>Lớp</label><select name="class_id" required><option value="">Chọn lớp</option><?php foreach($classes as $c): ?><option value="<?=e((string)$c['id'])?>"><?=e((string)$c['name'])?></option><?php endforeach; ?></select></div><div><label>Cách chơi</label><select name="mode"><option value="computer">Trên máy tính</option><option value="paper">Quét thẻ giấy</option></select></div><button class="btn primary" <?=!$questions?'disabled':''?>>Tạo lượt chơi →</button></form></section>
<section class="panel"><h2>Các lượt chơi</h2><p class="hint">Học sinh vào <?=e(BASE_URL)?>hoclieu_game_quiz_join.php và nhập mã lượt chơi.</p><div class="session-table"><table><thead><tr><th>Mã / cách chơi</th><th>Lớp</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach($sessions as $s): ?><tr><td><b><?=e((string)$s['code'])?></b><br><?=($s['mode']==='paper'?'Thẻ giấy':'Máy tính')?></td><td><?=e($classMap[(string)$s['class_id']]??(string)$s['class_id'])?></td><td><?=e((string)$s['status'])?></td><td><div class="row"><?php if($s['mode']==='paper'): ?><a class="btn tiny" href="<?=BASE_URL?>hoclieu_game_quiz_screen.php?code=<?=e((string)$s['code'])?>" target="_blank" rel="noopener">Màn hình chiếu</a><a class="btn tiny" href="<?=BASE_URL?>hoclieu_game_qr_trial.php?code=<?=e((string)$s['code'])?>">Máy quét</a><?php endif; ?><a class="btn tiny" href="?set=<?=e(rawurlencode($setId))?>&amp;report=<?=e((string)$s['code'])?>">Kết quả</a><?php if($s['status']==='open'): ?><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="close_session"><input type="hidden" name="code" value="<?=e((string)$s['code'])?>"><button class="btn tiny">Đóng</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if ($reportSession): ?>
<div class="card"><h2>Kết quả mã <?=e($viewCode)?></h2><p><?=($reportSession['mode']==='paper'?'Thẻ giấy':'Máy tính')?> · <?=count($reportQuestions)?> câu · <?=count($reportStudents)?> học sinh trong lớp</p><a class="button secondary" href="?set=<?=e(rawurlencode($setId))?>&amp;report=<?=e($viewCode)?>&amp;export=csv">Xuất CSV chi tiết</a>
<div style="overflow:auto"><table><thead><tr><th>Học sinh</th><th>Số đúng</th><th>Đã trả lời</th><?php foreach ($reportQuestions as $i=>$q): ?><th title="<?=e((string)$q['text'])?>">C<?=$i+1?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($reportStudents as $id=>$name): $correct=0;$answered=0;foreach ($reportQuestions as $i=>$q) { $a=$reportGrid[$id][$i]??'';if ($a!=='') {$answered++;if (qp_answer_correct($q,$a))$correct++;} } ?>
<tr><td><?=e($name)?></td><td><?=$correct?> / <?=count($reportQuestions)?></td><td><?=$answered?></td><?php foreach ($reportQuestions as $i=>$q): $a=$reportGrid[$id][$i]??''; ?><td style="background:<?=$a===''?'#f1f4f7':(qp_answer_correct($q,$a)?'#e3f6e7':'#ffe9e5')?>"><?=e($a!==''?qp_answer_label($a):'—')?></td><?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table></div><p class="muted">Xanh: đúng · Đỏ: sai · Xám: chưa trả lời. Di chuột lên tiêu đề C1, C2… để xem câu hỏi.</p>
<h3>Thống kê từng câu</h3><div style="overflow:auto"><table><thead><tr><th>Câu</th><th>Đáp án</th><th>Đúng</th><th>Sai</th><th>Chưa trả lời</th><th>Tỉ lệ đúng</th></tr></thead><tbody>
<?php foreach ($reportQuestions as $i=>$q): $right=0;$wrong=0;foreach ($reportStudents as $id=>$name) { $a=$reportGrid[$id][$i]??'';if ($a!=='') { if (qp_answer_correct($q,$a))$right++;else $wrong++; } }$missing=count($reportStudents)-$right-$wrong; ?>
<tr><td>C<?=$i+1?>. <?=e((string)$q['text'])?></td><td><?=e(qp_correct_label($q))?></td><td><?=$right?></td><td><?=$wrong?></td><td><?=$missing?></td><td><?=($right+$wrong)>0?round(100*$right/($right+$wrong),1):0?>% (trong số đã trả lời)</td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php endif; ?><?php endif; ?></main></div><script>
const search=document.getElementById('set-search');if(search)search.addEventListener('input',()=>document.querySelectorAll('.set-item').forEach(el=>el.hidden=!el.dataset.title.includes(search.value.trim().toLocaleLowerCase('vi'))));
document.querySelectorAll('[data-edit]').forEach(b=>b.addEventListener('click',()=>{let d=document.getElementById(b.dataset.edit);d.open=true;d.scrollIntoView({behavior:'smooth',block:'start'});d.querySelector('textarea[name=text]').focus({preventScroll:true})}));
document.querySelectorAll('[data-editor]').forEach(form=>{const sync=()=>{const type=form.querySelector('input[name=type]:checked').value;form.querySelectorAll('[data-for]').forEach(el=>el.hidden=!el.dataset.for.split(' ').includes(type));form.querySelectorAll('[name^=choice_]').forEach(el=>el.required=type==='single'||type==='multi');form.querySelector('[name=pairs]').required=type==='match';form.querySelector('[name=fill_answers]').required=type==='fill';form.querySelector('[name=steps]').required=type==='order'};form.querySelectorAll('input[name=type]').forEach(el=>el.addEventListener('change',sync));sync()});
</script><?php require __DIR__ . '/includes/game_credit.php'; ?></body></html>
