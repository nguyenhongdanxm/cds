<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
require_once __DIR__ . '/includes/quiz_ai.php';
require_login();
if (!qp_admin() && !can_perm_level('hl.xem', 'view')) { http_response_code(403); exit('Không có quyền xem trò chơi.'); }
try { qp_schema(); } catch (Throwable $e) { http_response_code(500); exit('Không thể mở dữ liệu trò chơi: ' . e($e->getMessage())); }
if (empty($_SESSION['qp_csrf'])) $_SESSION['qp_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['qp_csrf'];
$message = '';
$quizSubjects=['Toán','Ngữ văn','Vật lí','Hóa học','Sinh học','Khoa học tự nhiên','Lịch sử','Địa lí','Lịch sử và Địa lí','Tiếng Anh','Tin học','Công nghệ','Giáo dục công dân','Giáo dục kinh tế và pháp luật','Giáo dục thể chất','Âm nhạc','Mĩ thuật','Hoạt động trải nghiệm, hướng nghiệp','Giáo dục địa phương','Kiến thức tổng hợp'];
$quizGrades=['all'=>'Tất cả khối'];foreach(range(6,12) as $g)$quizGrades[(string)$g]='Khối '.$g;
function qp_classification_fields(array $subjects,array $grades,array $current=[]):void {
 $subject=(string)($current['category']??'');$grade=(string)($current['grade_scope']??'');
 if($subject!==''&&!in_array($subject,$subjects,true))$subjects[]=$subject;
 ?><div class="two-col"><div><label>Môn</label><select name="category" required><option value="">Chọn môn</option><?php foreach($subjects as $v):?><option value="<?=e($v)?>" <?=$subject===$v?'selected':''?>><?=e($v)?></option><?php endforeach?></select></div><div><label>Khối</label><select name="grade_scope" required><option value="">Chọn khối</option><?php foreach($grades as $v=>$label):?><option value="<?=e((string)$v)?>" <?=$grade===(string)$v?'selected':''?>><?=e($label)?></option><?php endforeach?></select></div></div><?php
}

function qp_go(string $set = '', string $view = ''): void {
    $params=[]; if ($set!=='') $params['set']=$set; if ($view!=='') $params['view']=$view;
    header('Location: ' . BASE_URL . 'hoclieu_game_quiz.php' . ($params ? '?'.http_build_query($params) : ''));
    exit;
}
function qp_csv(string $value): string {
    if (preg_match('/^[=+@\-\t\r]/u',$value)) $value="'".$value;
    return '"'.str_replace('"','""',$value).'"';
}
$classes = array_values(array_filter(csdl_classes_all(), static fn($c) => !empty($c['active'])));
csdl_sort_classes($classes);
$classMap = []; foreach ($classes as $c) $classMap[(string)$c['id']] = (string)$c['name'];
if (($_SERVER['REQUEST_METHOD']??'')==='POST'&&($_POST['action']??'')==='ai_generate') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if(!hash_equals($csrf,(string)($_POST['csrf']??''))||(!qp_admin()&&!can_perm('ai.dayhoc'))) {http_response_code(403);throw new RuntimeException('Không có quyền dùng AI hoặc phiên biểu mẫu đã hết hạn.');}
        $aiSetId=trim((string)($_POST['set_id']??''));$aiExisting=[];
        if($aiSetId!==''){$aiSet=qp_set($aiSetId,true);if(!$aiSet){http_response_code(403);throw new RuntimeException('Không có quyền sửa bộ câu hỏi này.');}$aiExisting=qp_questions($aiSet);}
        $drafts=trim((string)($_POST['drafts']??''));if($drafts!=='')$aiExisting=array_merge($aiExisting,qp_ai_validate($drafts,$aiExisting));
        if(microtime(true)-(float)($_SESSION['qp_ai_last_request']??0)<2){http_response_code(429);throw new RuntimeException('Vui lòng chờ vài giây rồi thử lại.');}
        $_SESSION['qp_ai_last_request']=microtime(true);
        require_once __DIR__.'/includes/ai_service.php';
        $result=qp_ai_generate($_POST,$aiExisting);if(empty($result['ok']))http_response_code(502);
        require_once __DIR__.'/includes/audit.php';cds_audit_log(empty($result['ok'])?'quiz_ai_failed':'quiz_ai_generated','hoclieu',['set_id'=>$aiSetId,'count'=>count($result['questions']??[]),'provider'=>$result['provider']??'','model'=>$result['model']??'','usage'=>$result['usage']??[]]);
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e instanceof InvalidArgumentException||http_response_code()===403||http_response_code()===429?$e->getMessage():'Không tạo được câu hỏi. Vui lòng thử lại.'],JSON_UNESCAPED_UNICODE);error_log('[Quiz AI] '.$e->getMessage());}
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Phiên biểu mẫu không hợp lệ.'); }
    $action = (string)($_POST['action'] ?? '');
    $setId = (string)($_POST['set_id'] ?? '');
    try {
        if($action==='save_audio'){
            if(!qp_admin())throw new RuntimeException('Chỉ quản trị được cài âm thanh chung.');
            $settings=[];foreach(['welcome','start','background','timeout','answer','score'] as $key){
                $url=trim((string)($_POST['music_'.$key]??''));
                if($url!==''&&(strlen($url)>1500||(!preg_match('~^https://[^\\s]+$~i',$url)&&!str_starts_with($url,BASE_URL.'uploads/quiz_audio/'))))throw new RuntimeException('Nhạc cần là liên kết HTTPS hợp lệ.');
                $file=$_FILES['music_file_'.$key]??null;
                if($file && (int)$file['error']!==UPLOAD_ERR_NO_FILE){
                    if((int)$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Tải nhạc không thành công; kiểm tra giới hạn tải lên của hosting.');
                    if((int)$file['size']>15*1024*1024)throw new RuntimeException('Mỗi tệp nhạc tối đa 15 MB.');
                    $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
                    $types=['audio/mpeg'=>'mp3','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/mp4'=>'m4a','video/mp4'=>'m4a'];
                    if(!isset($types[$mime]))throw new RuntimeException('Chỉ nhận tệp MP3, OGG, WAV hoặc M4A.');
                    $dir=__DIR__.'/uploads/quiz_audio';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Không tạo được thư mục nhạc.');
                    $name=bin2hex(random_bytes(16)).'.'.$types[$mime];
                    if(!move_uploaded_file((string)$file['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Không lưu được nhạc.');
                    $url=BASE_URL.'uploads/quiz_audio/'.$name;
                }
                $settings[$key]=$url;
            }
            qp_db()->prepare('INSERT INTO cds_quiz_audio_settings(id,settings_json,updated_at) VALUES(1,?,NOW()) ON DUPLICATE KEY UPDATE settings_json=VALUES(settings_json),updated_at=NOW()')->execute([json_encode($settings,JSON_UNESCAPED_UNICODE)]);
            qp_go();
        }
        if ($action === 'create') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 255) throw new RuntimeException('Tên bộ câu hỏi không hợp lệ.');
            $category=trim((string)($_POST['category']??''));$intro=trim((string)($_POST['intro']??''));$grade=trim((string)($_POST['grade_scope']??''));
            if(!array_key_exists($grade,$quizGrades)||(!in_array($category,$quizSubjects,true)&&$category!==(string)($set['category']??'')))throw new RuntimeException('Vui lòng chọn môn và khối hợp lệ.');
            if ($category==='' || $intro==='' || mb_strlen($category)>100 || mb_strlen($intro)>500) throw new RuntimeException('Nhập thể loại và giới thiệu ngắn cho bộ câu hỏi.');
            $id = 'qs_' . bin2hex(random_bytes(12));
            $st = qp_db()->prepare('INSERT INTO cds_quiz_sets(id,owner_id,title,category,grade_scope,intro,questions_json,is_public,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())');
            $aiQuestions=trim((string)($_POST['ai_questions']??''));$initialQuestions=$aiQuestions!==''?qp_ai_validate($aiQuestions):[];
            $st->execute([$id,qp_owner(),$title,$category,$grade,$intro,json_encode($initialQuestions,JSON_UNESCAPED_UNICODE),($_POST['public']??'')==='1'?1:0]); qp_go($id,'edit');
        }
        $set = qp_set($setId, !in_array($action,['clone_set','open_session','close_session','reveal_session'],true));
        if ($set && !qp_admin() && $set['owner_id']!==qp_owner() && empty($set['is_public'])) $set=null;
        if (!$set) throw new RuntimeException('Không tìm thấy bộ câu hỏi hoặc không có quyền sửa.');
        if ($action === 'visibility') {
            $public=($_POST['public']??'')==='1'?1:0;
            qp_db()->prepare('UPDATE cds_quiz_sets SET is_public=?,updated_at=NOW() WHERE id=?')->execute([$public,$setId]); qp_go($setId);
        }
        if ($action === 'clone_set') {
            if (empty($set['is_public']) && !qp_admin() && $set['owner_id']!==qp_owner()) throw new RuntimeException('Bộ câu hỏi không công khai.');
            $id='qs_'.bin2hex(random_bytes(12));
            qp_db()->prepare('INSERT INTO cds_quiz_sets(id,owner_id,title,category,grade_scope,intro,questions_json,is_public,created_at,updated_at) VALUES(?,?,?,?,?,?,?,0,NOW(),NOW())')->execute([$id,qp_owner(),$set['title'].' · Bản sao',$set['category']??'',$set['grade_scope']??'',$set['intro']??'',$set['questions_json']]); qp_go($id,'edit');
        }
        if ($action === 'save_set') {
            $title = trim((string)($_POST['title'] ?? ''));
            $category=trim((string)($_POST['category']??''));$intro=trim((string)($_POST['intro']??''));$grade=trim((string)($_POST['grade_scope']??''));
            if(!array_key_exists($grade,$quizGrades)||(!in_array($category,$quizSubjects,true)&&$category!==(string)($set['category']??'')))throw new RuntimeException('Vui lòng chọn môn và khối hợp lệ.');
            if ($title==='' || $category==='' || $intro==='' || mb_strlen($title)>255 || mb_strlen($category)>100 || mb_strlen($intro)>500) throw new RuntimeException('Thông tin bộ câu hỏi không hợp lệ.');
            qp_db()->prepare('UPDATE cds_quiz_sets SET title=?,category=?,grade_scope=?,intro=?,is_public=?,updated_at=NOW() WHERE id=?')->execute([$title,$category,$grade,$intro,($_POST['public']??'')==='1'?1:0,$setId]); qp_go($setId,'settings');
        }
        if ($action === 'delete_set') {
            $pdo=qp_db();$pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM cds_quiz_progress WHERE session_code IN (SELECT code FROM cds_quiz_sessions WHERE set_id=?)')->execute([$setId]);
                $pdo->prepare('DELETE FROM cds_quiz_answers WHERE session_code IN (SELECT code FROM cds_quiz_sessions WHERE set_id=?)')->execute([$setId]);
                $pdo->prepare('DELETE FROM cds_quiz_groups WHERE session_code IN (SELECT code FROM cds_quiz_sessions WHERE set_id=?)')->execute([$setId]);
                $pdo->prepare('DELETE FROM cds_quiz_sessions WHERE set_id=?')->execute([$setId]);
                $pdo->prepare('DELETE FROM cds_quiz_sets WHERE id=?')->execute([$setId]);
                $pdo->commit();
            } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
            qp_go();
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
                $q = ['seconds'=>max(10,min(300,(int)($_POST['seconds']??20))),'type'=>$type,'text'=>trim((string)($_POST['text'] ?? '')),'choices'=>[],'key'=>(string)($_POST['key'] ?? ''),'image'=>trim((string)($_POST['image']??'')),'explanation'=>trim((string)($_POST['explanation']??'')),'video'=>trim((string)($_POST['video']??'')),'audio'=>trim((string)($_POST['audio']??''))];
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
            $st->execute([json_encode($questions, JSON_UNESCAPED_UNICODE),$setId]); qp_go($setId,'edit');
        }
        if ($action === 'import_ai_questions') {
            $pdo=qp_db();$pdo->beginTransaction();
            try{
                $locked=$pdo->prepare('SELECT * FROM cds_quiz_sets WHERE id=? FOR UPDATE');$locked->execute([$setId]);$current=$locked->fetch();
                if(!$current||(!qp_admin()&&$current['owner_id']!==qp_owner()))throw new RuntimeException('Không có quyền sửa bộ câu hỏi.');
                $existing=qp_questions($current);$incoming=qp_ai_validate(trim((string)($_POST['ai_questions']??'')),$existing);
                $st=$pdo->prepare('UPDATE cds_quiz_sets SET questions_json=?,updated_at=NOW() WHERE id=?');
                $st->execute([json_encode(array_merge($existing,$incoming),JSON_UNESCAPED_UNICODE),$setId]);$pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            qp_go($setId,'edit');
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
            $st->execute([json_encode(array_merge($questions,$incoming),JSON_UNESCAPED_UNICODE),$setId]);qp_go($setId,'edit');
        }
        if ($action === 'move_question') {
            $questions=qp_questions($set);
            $index=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
            $direction=(string)($_POST['direction']??'');
            $other=$index+($direction==='up'?-1:($direction==='down'?1:0));
            if ($index===false || !isset($questions[$index],$questions[$other]) || $other===$index) throw new RuntimeException('Không thể đổi thứ tự câu hỏi.');
            [$questions[$index],$questions[$other]]=[$questions[$other],$questions[$index]];
            $st=qp_db()->prepare('UPDATE cds_quiz_sets SET questions_json=?,updated_at=NOW() WHERE id=?');
            $st->execute([json_encode($questions,JSON_UNESCAPED_UNICODE),$setId]);qp_go($setId,'edit');
        }
        if ($action === 'open_session') {
            $classIds = array_values(array_unique(array_map('strval',(array)($_POST['class_ids'] ?? []))));
            $mode = (string)($_POST['mode'] ?? 'computer');
            if (!in_array($mode,['computer','paper'],true)) throw new RuntimeException('Chế độ chơi không hợp lệ.');
            $pace=$mode==='paper'?'teacher':(string)($_POST['pace_mode']??'timed');
            if (!in_array($pace,['timed','teacher'],true)) throw new RuntimeException('Cách chuyển câu không hợp lệ.');
            $seconds=max(10,min(300,(int)($_POST['seconds_per_question']??20)));
            if (count($classIds)<1 || count($classIds)>2 || array_diff($classIds,array_keys($classMap)) || !qp_questions($set)) throw new RuntimeException('Chọn một hoặc hai lớp và tạo ít nhất một câu hỏi.');
            if ($mode==='paper') foreach (qp_questions($set) as $q) if (!in_array(qp_question_type($q),['single','paper_logic'],true)) throw new RuntimeException('Chơi bằng thẻ giấy chỉ hỗ trợ câu A–D một đáp án hoặc Hai mệnh đề. Hãy dùng bộ câu hỏi phù hợp.');
            $roster=[];
            foreach (csdl_students_all() as $student) if (!empty($student['active']) && in_array((string)($student['class_id']??''),$classIds,true)) {
                $name=(string)($student['name']??$student['full_name']??'');
                $roster[(string)$student['id']] = $name.(count($classIds)===2?' · '.$classMap[(string)$student['class_id']]:'');
            }
            if (!$roster) throw new RuntimeException('Các lớp đã chọn chưa có học sinh đang học.');
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $code = (string)random_int(10000000, 99999999);
                try {
                    $st = qp_db()->prepare("INSERT INTO cds_quiz_sessions(code,set_id,class_id,class_ids_json,owner_id,questions_json,roster_json,mode,pace_mode,seconds_per_question,status,phase,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,'open','welcome',NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY))");
                    $st->execute([$code,$setId,$classIds[0],json_encode($classIds),qp_owner(),json_encode(qp_questions($set),JSON_UNESCAPED_UNICODE),json_encode($roster,JSON_UNESCAPED_UNICODE),$mode,$pace,$seconds]);
                    qp_go($setId,'play');
                } catch (PDOException $e) { if ($e->getCode() !== '23000' || $attempt === 2) throw $e; }
            }
        }
        if ($action === 'reveal_session') {
            $code=(string)($_POST['code']??'');
            $st=qp_db()->prepare(qp_admin() ? "UPDATE cds_quiz_sessions SET show_correct=1,last_activity_at=NOW() WHERE code=? AND set_id=? AND mode='computer' AND status='open'" : "UPDATE cds_quiz_sessions SET show_correct=1,last_activity_at=NOW() WHERE code=? AND set_id=? AND owner_id=? AND mode='computer' AND status='open'");
            $st->execute(qp_admin()?[$code,$setId]:[$code,$setId,qp_owner()]);qp_go($setId,'play');
        }
        if ($action === 'close_session') {
            $code = (string)($_POST['code'] ?? '');
            $st = qp_db()->prepare(qp_admin() ? "UPDATE cds_quiz_sessions SET status='closed',phase=IF(mode='computer','finished',phase),show_correct=IF(mode='computer',1,show_correct) WHERE code=? AND set_id=?" : "UPDATE cds_quiz_sessions SET status='closed',phase=IF(mode='computer','finished',phase),show_correct=IF(mode='computer',1,show_correct) WHERE code=? AND set_id=? AND owner_id=?");
            $st->execute(qp_admin()?[$code,$setId]:[$code,$setId,qp_owner()]); qp_go($setId);
        }
    } catch (Throwable $e) { $message = $e->getMessage(); }
}
$setId = (string)($_GET['set'] ?? ($_POST['set_id'] ?? ''));
$actionView=['create'=>'create','save_set'=>'settings','delete_set'=>'settings','save_question'=>'edit','delete_question'=>'edit','duplicate_question'=>'edit','move_question'=>'edit','import_questions'=>'edit','import_ai_questions'=>'edit'];
$view=(string)($_GET['view']??($_POST['view']??($actionView[$_POST['action']??'']??'play')));
if (!in_array($view,['play','edit','settings','create'],true)) $view='play';
$set = $setId !== '' ? qp_set($setId) : null;
if ($set && !qp_admin() && $set['owner_id']!==qp_owner() && empty($set['is_public'])) $set=null;
$canEditSet=$set && (qp_admin() || $set['owner_id']===qp_owner());
if ($set && !$canEditSet && in_array($view,['edit','settings'],true)) $view='play';
$sets = qp_sets();
$sessions = [];
$answers = [];
$reportSession = null;
if ($set) {
    $st = qp_db()->prepare(qp_admin() ? 'SELECT * FROM cds_quiz_sessions WHERE set_id=? ORDER BY created_at DESC LIMIT 20' : 'SELECT * FROM cds_quiz_sessions WHERE set_id=? AND owner_id=? ORDER BY created_at DESC LIMIT 20');
    $st->execute(qp_admin()?[$setId]:[$setId,qp_owner()]); $sessions = $st->fetchAll();
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
      <label>Thời gian suy nghĩ khi chơi trên máy (giây)</label><input type="number" name="seconds" min="10" max="300" value="<?=qp_question_seconds($q)?>" required><p class="hint">Mặc định 20 giây/câu. Chơi bằng thẻ giấy do người điều khiển quyết định khi dừng.</p>
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
    if ($reportSession['mode']==='computer') $reportStudents=array_merge($reportStudents,qp_group_players($viewCode));
    foreach ($answers as $a) $reportGrid[(string)$a['student_id']][(int)$a['question_index']] = (string)$a['answer'];
    if (($_GET['export']??'')==='xlsx') {require_once __DIR__.'/includes/quiz_result_excel.php';qp_result_excel($viewCode,$reportStudents,$reportQuestions,$reportGrid);exit;}
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

<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hỏi Nhanh - Đáp Gọn · Thư viện câu hỏi</title>
<style>
:root{--ink:#17264d;--sub:#61708e;--line:#dfe5f1;--violet:#5846d8;--blue:#3268ec;--surface:#fff}*{box-sizing:border-box}body{margin:0;color:var(--ink);background:#f5f6fc;font:16px system-ui,-apple-system,Segoe UI,sans-serif}button,input,select,textarea{font:inherit}button{cursor:pointer}a{color:#4e45c8;text-decoration:none}a:hover{text-decoration:underline}.shell{max-width:1460px;margin:auto;padding:0 24px}.topbar{background:#fff;border-bottom:1px solid var(--line)}.topbar .shell{min-height:66px;display:flex;align-items:center;gap:22px}.brand{font-size:20px;font-weight:900;color:#352f9b}.top-link{font-weight:700;color:#647293}.topbar .spacer{flex:1}.small-link{font-size:14px}.hero{background:radial-gradient(circle at 84% 20%,#9867f4 0,transparent 38%),linear-gradient(115deg,#24266f,#5845c9 62%,#855bdc);color:#fff;padding:35px 0 47px}.hero h1{margin:7px 0;font-size:clamp(29px,3vw,43px);letter-spacing:-.035em}.hero p{margin:0;color:#e9e5ff}.eyebrow{font-size:11px;font-weight:900;letter-spacing:.15em;color:#6c58dd}.hero .eyebrow{color:#e4d6ff}.workspace{display:grid;grid-template-columns:275px minmax(0,1fr);gap:22px;align-items:start;margin-top:-25px;padding-bottom:60px}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 12px 30px #2f397110}.sidebar{position:sticky;top:14px;padding:20px}.sidebar h2{font-size:17px;margin:0 0 14px}.stack{display:grid;gap:9px}.set-link{display:block;border:1px solid #e7e9f4;border-radius:12px;padding:12px;color:var(--ink);font-weight:750;overflow-wrap:anywhere}.set-link:hover,.set-link.active{border-color:#ad9ffd;background:#f3f0ff;text-decoration:none}.set-link small{display:block;color:var(--sub);font-weight:500;margin-top:4px}.sidebar details{margin-top:16px}.sidebar summary,.media-panel summary{cursor:pointer;font-weight:800;color:#5146b6}.sidebar form{margin-top:10px}.content{min-width:0}.content>.panel{padding:26px;margin-bottom:20px}.notice{background:#fff0d9;border:1px solid #f1c576;padding:16px;border-radius:12px;margin:0 0 20px}.section-title{display:flex;justify-content:space-between;gap:15px;align-items:center;flex-wrap:wrap;margin-bottom:14px}.section-title h2{margin:0;font-size:23px}.muted,.hint{color:var(--sub)}.hint{font-size:13px;margin:7px 0}.pill{background:#eeeafb;color:#5145af;border-radius:30px;padding:6px 11px;font-size:12px;font-weight:800}.stats{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0}.stat{background:#f2f3fc;padding:12px 18px;border-radius:12px;min-width:120px}.stat b{display:block;font-size:22px}.stat small{color:var(--sub)}label{display:block;font-weight:750;margin:13px 0 6px}label small{font-weight:500;color:var(--sub)}input[type=text],input[type=url],input[type=search],textarea,select{width:100%;min-width:0;border:1px solid #cbd3e4;border-radius:10px;background:#fff;padding:11px 12px;color:var(--ink);outline-color:#7567eb}textarea{resize:vertical}input[type=file]{max-width:100%;font-size:13px}.btn{border:0;border-radius:10px;padding:10px 15px;font-weight:800;display:inline-flex;justify-content:center;align-items:center;gap:6px;background:#eaeefe;color:#3d47a5;text-decoration:none}.btn:hover{text-decoration:none;filter:brightness(.96)}.btn.primary{background:#6250dc;color:white}.btn.danger{background:#fff0f0;color:#bd394b}.btn.tiny{padding:7px 10px;font-size:13px}.row{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px}.editor-heading{display:flex;align-items:center;justify-content:space-between;gap:12px}.editor-heading h3{font-size:21px;margin:3px 0 15px}.type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:10px 0 24px}.type-tile{display:grid;grid-template-columns:34px 1fr;grid-template-rows:auto auto;align-items:center;gap:1px 9px;border:2px solid #e8e9f2;border-radius:13px;padding:11px;margin:0;cursor:pointer}.type-tile input{position:absolute;opacity:0}.type-tile:has(input:checked){border-color:#7161eb;background:#f2efff;box-shadow:0 2px 10px #6456da22}.type-tile:focus-within{outline:2px solid #7161eb}.type-icon{grid-row:span 2;display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#e7e4ff;color:#5041c8;font-size:19px}.type-tile strong{font-size:14px}.type-tile small{color:var(--sub);font-size:11px}.answer-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0}.answer-row{display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #e0e3f0;border-radius:13px}.answer-row>span{flex:none;width:37px;height:37px;display:grid;place-items:center;border-radius:10px;font-weight:900;color:#fff}.answer-A>span{background:#f0a734}.answer-B>span{background:#496af1}.answer-C>span{background:#d7609c}.answer-D>span{background:#2ca99a}.answer-row input[type=text]{border:0;flex:1;padding:5px;min-width:0}.answer-row label{flex:none;white-space:nowrap;font-size:12px;margin:0;color:#4454aa}.specific{background:#f8f9fe;padding:16px;border-radius:13px;margin:15px 0}.specific label:first-child{margin-top:0}.logic-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.logic-grid label{border:1px solid #dce1ee;padding:11px;border-radius:10px;background:#fff;margin:0}.logic-grid b{color:#6250dc}.media-panel{border:1px solid var(--line);border-radius:13px;padding:16px;margin:19px 0}.media-grid{display:grid;grid-template-columns:1fr 1fr;gap:17px}.image-preview{display:block;max-width:100%;max-height:150px;object-fit:contain;margin:10px 0;border-radius:9px}.editor-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:18px}.question-card{border:1px solid #e2e5f0;border-radius:14px;padding:16px;margin:11px 0}.question-top{display:flex;gap:12px;align-items:flex-start}.number{background:#ece9ff;color:#5643bf;font-weight:900;border-radius:9px;padding:8px 11px;white-space:nowrap}.question-body{flex:1;min-width:0}.question-body h3{margin:2px 0 7px;font-size:16px;overflow-wrap:anywhere}.question-body p{margin:5px 0}.question-tools{display:flex;gap:5px;flex-wrap:wrap;margin-top:12px}.question-tools form{display:inline-flex;gap:5px}.question-card details{border-top:1px solid var(--line);margin-top:15px;padding-top:13px}.question-card summary{cursor:pointer;color:#5146b6;font-weight:800}.mini-choices{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin:10px 0}.mini-choices span{background:#f6f7fb;padding:7px;border-radius:7px;font-size:13px}.empty{padding:28px;text-align:center;background:#f7f7fe;border-radius:13px;color:#53627d}.session-table{overflow:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{padding:12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{color:#63718b}details>summary{list-style-position:inside}.section-anchor{scroll-margin-top:18px}[hidden]{display:none!important}@media(max-width:900px){.workspace{grid-template-columns:1fr}.sidebar{position:static}.type-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.shell{padding:0 13px}.content>.panel{padding:16px}.answer-grid,.media-grid,.two-col,.logic-grid{grid-template-columns:1fr}.type-grid{grid-template-columns:repeat(2,1fr)}.topbar .shell{gap:10px}.small-link{display:none}}
.workspace{grid-template-columns:minmax(0,1fr);gap:18px}.library-panel,.create-panel{padding:25px}.library-panel .section-title h2{font-size:29px}.library-panel .section-title p{margin:8px 0 0}.library-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px;margin-top:18px}.library-card{display:flex;flex-direction:column;align-items:flex-start;min-width:0;padding:19px;border:1px solid #e1e5f4;border-radius:17px;background:linear-gradient(155deg,#f7f6ff,#fff 60%)}.library-card .pill{align-self:flex-end;margin-top:-40px}.library-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#e8e4ff;color:#5948c8;font-size:26px}.library-card h3{margin:18px 0 6px;overflow-wrap:anywhere}.library-card p{margin:0 0 12px;min-height:43px;line-height:1.45}.library-meta{font-size:13px;font-weight:750;color:#5c638b;margin-bottom:17px}.library-card .row{margin-top:auto}.create-panel{scroll-margin-top:18px}.create-actions,.play-actions{display:flex;align-items:end;gap:15px;flex-wrap:wrap}.create-actions>div,.play-actions>div{min-width:240px;flex:1}.create-actions .btn,.play-actions .btn{min-height:46px}.library-back{margin:0 0 3px}.content>.panel{margin-bottom:18px}.set-overview h2{font-size:27px}.play-panel{border-color:#c9c0f9!important;background:linear-gradient(155deg,#f7f5ff,#fff 50%)}.play-panel h2{margin:0 0 6px}.play-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px;margin:18px 0}.play-option{position:relative;display:flex;flex-direction:column;align-items:flex-start;gap:7px;margin:0;padding:18px;border:2px solid #dfe3f2;border-radius:16px;background:#fff;cursor:pointer}.play-option:has(input:checked){border-color:#6e5ce4;box-shadow:0 7px 22px #6554d126;background:#f8f6ff}.play-option:has(input:disabled){opacity:.55;cursor:not-allowed}.play-option input{position:absolute;right:17px;top:18px;width:20px;height:20px;accent-color:#6250dc}.play-icon{font-size:28px}.play-option strong{font-size:18px}.play-option small{line-height:1.5;color:var(--sub);max-width:50ch}.play-actions{margin-bottom:16px}.section-summary{cursor:pointer;font-size:19px;font-weight:850;color:#3d358e}.set-editor{padding-top:16px}.set-editor>form+form{margin-top:18px;padding-top:15px;border-top:1px solid var(--line)}@media(max-width:900px){.library-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.library-grid,.play-grid{grid-template-columns:1fr}.library-panel,.create-panel{padding:17px}.library-panel .section-title h2{font-size:25px}.play-option{padding:15px}}

.workflow-tabs{display:flex;flex-wrap:wrap;gap:10px;grid-column:1/-1;margin:0 0 5px}.workflow-tab{display:inline-flex;align-items:center;gap:7px;border-radius:13px;padding:12px 17px;background:#fff;border:1px solid #dce2f5;color:#46547f;font-weight:800;box-shadow:0 5px 16px #2f39710b}.workflow-tab.active{background:#5040ba;color:#fff;border-color:#5040ba}.workflow-tab:hover{text-decoration:none;border-color:#7968dd}.library-panel,.create-panel{grid-column:1/-1}.library-card:nth-child(4n+2){background:linear-gradient(155deg,#edfbff,#fff 60%)}.library-card:nth-child(4n+3){background:linear-gradient(155deg,#fff5e9,#fff 60%)}.library-card:nth-child(4n+4){background:linear-gradient(155deg,#effbf3,#fff 60%)}.library-card:nth-child(4n+2) .library-icon{background:#d6f4fb;color:#09849d}.library-card:nth-child(4n+3) .library-icon{background:#ffebd1;color:#b86412}.library-card:nth-child(4n+4) .library-icon{background:#dbf4e2;color:#13824d}.class-picker{padding:17px;background:#fff;border-radius:15px;border:1px solid #e1def6;margin-bottom:16px}.class-picker>strong{font-size:17px}.class-options{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;max-height:235px;overflow:auto}.class-option{display:flex;align-items:center;gap:9px;margin:0;padding:11px;border:1px solid #dce2f1;border-radius:10px;cursor:pointer}.class-option:has(input:checked){background:#eae7ff;border-color:#745de0;color:#3b30a0}.class-option input{accent-color:#6250dc}.set-overview{border-top:4px solid #7762df!important}@media(max-width:900px){.class-options{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.workflow-tab{flex:1;justify-content:center;padding:10px;font-size:13px}.class-options{grid-template-columns:repeat(2,minmax(0,1fr))}}

.workspace{grid-template-columns:250px minmax(0,1fr);gap:20px;align-items:start}.work-sidebar{position:sticky;top:16px;padding:15px;min-width:0}.sidebar-heading{display:flex;gap:11px;align-items:center;padding:10px 8px 16px;border-bottom:1px solid #e7e9f3}.sidebar-heading>div{min-width:0}.sidebar-heading small{display:block;font-size:10px;letter-spacing:.09em;font-weight:900;color:#7a83a2}.sidebar-heading strong{display:block;line-height:1.25;overflow-wrap:anywhere;margin-top:4px;color:#252d69}.sidebar-mark{display:grid;place-items:center;flex:none;width:39px;height:39px;background:#ece8ff;border-radius:12px;font-size:21px}.sidebar-menu{display:grid;gap:6px;margin:15px 0}.sidebar-item{display:flex;gap:10px;align-items:center;padding:12px;border:1px solid transparent;border-radius:12px;color:#414b72}.sidebar-item>span:first-child{font-size:22px;line-height:1}.sidebar-item b,.sidebar-item small{display:block}.sidebar-item b{font-size:14px}.sidebar-item small{color:#7c87a4;font-size:11px;margin-top:3px}.sidebar-item.active{background:#eeeaff;border-color:#c9befa;color:#3d2da5}.sidebar-item.active small{color:#665ca7}.sidebar-item:hover{background:#f4f3ff;text-decoration:none}.sidebar-return{display:block;border-top:1px solid #e7e9f3;padding:16px 9px 4px;font-size:13px;font-weight:800}.content{min-width:0}.content>.panel{margin-bottom:18px}.screen-heading{display:flex;align-items:center;gap:17px;border-top:4px solid #7762df!important}.screen-heading h2{font-size:27px;margin:4px 0}.screen-heading p{margin:0}.screen-icon{display:grid;place-items:center;width:55px;height:55px;flex:none;background:#eeeaff;border-radius:15px;font-size:27px}.settings-panel{max-width:900px}.library-panel,.create-panel{width:100%}@media(max-width:900px){.workspace{grid-template-columns:1fr}.work-sidebar{position:static}.sidebar-heading{padding-bottom:10px}.sidebar-menu{display:flex;overflow-x:auto;gap:7px}.sidebar-item{flex:1;min-width:155px}.sidebar-item small{display:none}.sidebar-return{padding:10px}.screen-heading h2{font-size:23px}}

.library-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:start}.library-card{padding:14px;min-height:0;border-radius:14px}.library-card h3{font-size:17px;line-height:1.3;margin:10px 0 4px}.library-card p{min-height:0;max-height:40px;overflow:hidden;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;font-size:13px;line-height:1.45;margin:0 0 7px}.library-card .pill{font-size:11px;padding:5px 8px;margin-top:-36px}.library-icon{width:35px;height:35px;font-size:19px}.library-meta{margin:0 0 10px;font-size:12px}.library-card .row{margin-top:0}.library-card .btn{padding:7px 12px;font-size:13px}.session-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}.session-card{position:relative;min-width:0;padding:18px;border:1px solid #dce3f4;border-radius:17px;background:linear-gradient(145deg,#f8f7ff,#fff 65%)}.session-head,.session-main,.session-links{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.session-head{justify-content:space-between;margin-bottom:15px}.session-mode,.session-status{padding:6px 10px;border-radius:20px;font-size:12px;font-weight:850;background:#e9e6ff;color:#4a3ca8}.session-status{background:#edf0f5;color:#607089}.session-status.is-open{background:#dcf6e7;color:#087543}.session-main{gap:24px;margin-bottom:14px}.session-main>div{min-width:110px}.session-main small,.session-main strong{display:block}.session-main small{font-size:10px;font-weight:850;letter-spacing:.09em;color:#7e89a7}.session-main strong{font-size:15px;margin-top:3px}.session-main .session-code{font-size:23px;letter-spacing:.07em;color:#4535a2}.session-links{border-top:1px solid #e3e7f3;padding-top:13px}.session-links a{font-size:13px;font-weight:800;padding:8px 10px;border-radius:9px;background:#e9edff}.session-url{display:grid;gap:3px;margin-top:12px;font-size:11px;overflow-wrap:anywhere}.session-url span{color:#7b86a0;font-weight:800}.session-url a{color:#4c45a4;overflow-wrap:anywhere}.session-close{margin-top:12px}.sessions-panel .hint strong{color:#4434a8}@media(max-width:1250px){.library-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:800px){.library-grid,.session-list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.library-grid,.session-list{grid-template-columns:1fr}.session-card{padding:15px}}
.student-entry{display:flex;flex-wrap:wrap;gap:6px 12px;align-items:center;padding:12px 14px;border-radius:12px;background:#eff6ff;color:#314676;font-weight:750}.student-entry a{overflow-wrap:anywhere;color:#3d35a4;text-decoration:underline}.session-links{margin-top:4px}#open-play-form{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px;align-items:start}.play-grid{grid-column:1/-1;gap:12px!important}.play-option{padding:15px!important;min-height:120px!important}.play-icon{font-size:32px!important;display:block;margin-bottom:6px}.mode-computer{background:#edf5ff!important;border-color:#79b7ed!important}.mode-paper{background:#fff5e6!important;border-color:#eeb56b!important}.mode-computer:has(input:checked){box-shadow:0 0 0 2px #2684ca}.mode-paper:has(input:checked){box-shadow:0 0 0 2px #da8524}#qpPace{background:#f4f7ff;padding:14px;border-radius:12px}#qpPace label{display:block;margin:8px 0}#qpSeconds{width:100px;padding:8px;border:1px solid #bdcbe5;border-radius:8px}.class-picker{padding:12px!important}.class-options{grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:6px!important}.class-options label{padding:8px!important}.play-grid~.hint{grid-column:1/-1}.session-card.is-paper{background:#fff7ec;border-color:#edc38a}.session-card.is-computer{background:#edf6ff;border-color:#a3c9eb}.session-card.is-closed{background:#eef0f3!important;filter:grayscale(1);color:#74808d}.session-card.is-closed a{color:#6e7782;background:#dfe3e8}.session-card.is-closed .session-code{color:#707985}.hero{padding:20px!important}.hero-stats{margin-top:12px!important}.sessions-panel{padding:20px!important}@media(max-width:700px){#open-play-form{grid-template-columns:1fr}.play-grid{grid-template-columns:1fr 1fr!important}.play-option small{font-size:12px}.class-options{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
.qp-ai-panel{border-top:4px solid #0891b2!important;background:linear-gradient(140deg,#effcff,#fff 55%)}.qp-ai-options{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:12px}.qp-ai-draft{margin-top:15px;padding:16px;border:1px solid #c9dfec;border-radius:13px;background:#fff}.qp-ai-draft label{display:block;margin:9px 0 5px}.qp-ai-draft input[type=checkbox]{width:auto}.qp-ai-draft textarea{width:100%}#qp-ai-status{font-size:13px;color:#435773}#qp-ai-preview{max-height:680px;overflow:auto}#qp-ai-save-area{margin-top:14px}@media(max-width:700px){.qp-ai-options{grid-template-columns:1fr 1fr}}

/* Compact authoring workspace; preserve comfortable touch targets. */
.shell{max-width:1600px;padding:0 18px}.workspace{grid-template-columns:205px minmax(0,1fr);gap:14px;padding-bottom:30px}.work-sidebar{padding:10px;top:10px}.sidebar-heading{padding:6px 4px 10px;gap:8px}.sidebar-heading strong{font-size:14px}.sidebar-heading small{font-size:9px;letter-spacing:.04em}.sidebar-mark{width:32px;height:32px;font-size:18px}.sidebar-menu{gap:4px;margin:9px 0}.sidebar-item{padding:9px 8px;gap:8px;min-width:0}.sidebar-item b{font-size:13px}.sidebar-item small{font-size:10px}.sidebar-return{padding:10px 4px 4px;font-size:12px}.content>.panel{padding:16px;margin-bottom:12px}.section-title{gap:10px;margin-bottom:10px}.section-title h2,.screen-heading h2{font-size:22px}.section-title p{margin:5px 0;font-size:14px}.screen-heading{gap:12px}.screen-icon{width:42px;height:42px;font-size:23px}.hero h1{font-size:30px}.hero p{font-size:14px}.stats{gap:8px;margin:10px 0 0}.stat{min-width:0;padding:8px 12px;display:flex;align-items:center;gap:8px}.stat b{font-size:18px}.stat small{font-size:12px}.btn{padding:8px 12px;font-size:13px;min-height:36px;white-space:nowrap}.btn.tiny{font-size:12px;padding:7px 9px}.row{gap:6px}.play-panel>h2{font-size:22px}.play-panel>p{font-size:14px;margin:5px 0 10px}#open-play-form{gap:10px}.play-grid{margin:4px 0 2px;gap:10px!important}.play-option{display:grid;grid-template-columns:52px minmax(0,1fr);gap:3px 9px;align-items:center;padding:11px 38px 11px 12px!important;min-height:0!important;border-radius:12px}.play-icon{grid-row:span 2;font-size:26px!important;margin:0!important;white-space:nowrap}.play-option strong{font-size:16px;line-height:1.3}.play-option small{font-size:12px;line-height:1.35;max-width:none}.play-option input{right:10px;top:12px;width:18px;height:18px}#qpPace{padding:10px 12px}#qpPace label{font-size:13px;margin:5px 0}.class-picker{margin-bottom:0;padding:10px!important;min-width:0}.class-picker>strong{font-size:14px}.class-picker .hint{margin:4px 0 7px;font-size:12px}.class-options{grid-template-columns:repeat(auto-fit,minmax(66px,1fr))!important;max-height:none;gap:5px!important}.class-options label{padding:6px 8px!important;gap:6px;min-height:36px;font-size:13px;white-space:nowrap}.class-option input{width:16px;height:16px;flex:none;margin:0}input[type=text],input[type=url],input[type=search],select,textarea{padding:8px 10px;font-size:14px}.play-actions{grid-column:1/-1;margin:0}.play-actions .btn{min-height:38px}.play-panel>.hint{font-size:12px;line-height:1.45;margin:10px 0 0}.sessions-panel{padding:16px!important}.session-list{gap:10px}.session-card{padding:12px;min-width:0}.session-head{gap:7px;flex-wrap:wrap}.session-mode,.session-status{font-size:12px}.session-main{gap:12px;margin:10px 0}.session-main .session-code{font-size:20px}.session-main strong{font-size:14px}.session-links{display:flex;flex-wrap:wrap;gap:6px;padding-top:9px}.session-links a{padding:7px 8px;font-size:12px;white-space:nowrap}.session-close{margin-top:8px}.student-entry{padding:9px 11px;font-size:13px;gap:5px 9px}.student-entry a{min-width:0;overflow-wrap:anywhere}.question-card{padding:12px}.two-col{gap:10px}.type-grid{gap:7px;margin-bottom:15px}.media-panel{padding:12px;margin:12px 0}.qp-ai-options{gap:8px}
@media(max-width:1100px){.workspace{grid-template-columns:185px minmax(0,1fr)}.play-option{padding-right:28px!important}.play-option strong{font-size:14px}.play-option small{font-size:11px}.session-list{grid-template-columns:1fr 1fr}}
@media(max-width:900px){.workspace{grid-template-columns:1fr;gap:10px}.work-sidebar{position:static;padding:8px 10px}.sidebar-heading{padding:2px 0 8px}.sidebar-menu{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;margin:7px 0;overflow:visible}.sidebar-item{min-width:0;padding:8px 6px;justify-content:center}.sidebar-item>span:first-child{font-size:18px}.sidebar-item b{font-size:12px}.sidebar-item small{display:none}.sidebar-return{padding:6px 0 0}.content>.panel{padding:14px}.hero{padding:14px!important}.hero h1{font-size:27px}}
@media(max-width:600px){.shell{padding:0 10px}.topbar .shell{gap:10px;flex-wrap:wrap;padding-top:8px;padding-bottom:8px}.brand{font-size:16px}.top-link{font-size:12px}.sidebar-item{gap:5px}.sidebar-item b{font-size:11px}.play-grid{grid-template-columns:1fr 1fr!important}#open-play-form{grid-template-columns:1fr}.play-option{grid-template-columns:1fr;padding:10px 25px 10px 10px!important;gap:5px}.play-icon{grid-row:auto;font-size:24px!important}.play-option strong{font-size:13px}.play-option small{font-size:11px}.class-options{grid-template-columns:repeat(4,minmax(0,1fr))!important}.class-options label{min-height:40px;padding:7px 5px!important;justify-content:center}.class-option input{width:17px;height:17px}.session-list{grid-template-columns:1fr}.session-links a,.btn{min-height:40px}.section-title h2{font-size:20px}.stat{padding:7px 9px}.stats{gap:5px}.student-entry{font-size:12px}.two-col,.media-grid{grid-template-columns:1fr}.qp-ai-options{grid-template-columns:repeat(2,minmax(0,1fr))}.play-actions .btn{width:100%}}
@media(max-width:360px){.class-options{grid-template-columns:repeat(3,minmax(0,1fr))!important}.sidebar-item>span:first-child{font-size:15px}.play-option small{font-size:10px}}
.field-help{display:block;color:#64748b;font-size:12px;font-weight:400;line-height:1.4;margin:5px 0 8px}.author-choice [aria-pressed="true"]{background:#6250dc;color:#fff}.play-icon{max-width:100%;font-size:24px!important}
</style></head><body>
<nav class="topbar"><div class="shell"><a class="brand" href="<?=BASE_URL?>hoclieu_game_quiz.php">◉ Hỏi Nhanh - Đáp Gọn</a><a class="top-link" href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><span class="spacer"></span><a class="small-link" href="<?=BASE_URL?>hoclieu_quiz_cards.php">In thẻ trả lời A–D ↗</a></div></nav>
<div class="hero"><div class="shell"><span class="eyebrow">HỌC LIỆU &amp; THI · TRÒ CHƠI</span><h1>Hỏi Nhanh - Đáp Gọn</h1><p>Hỏi đáp kiến thức linh hoạt bằng máy và bảng.</p></div></div>
<?php if(qp_admin()):$audioSettings=qp_audio_settings();?><div class="shell"><details class="panel" style="padding:18px;margin:18px 0"><summary class="section-summary">🎵 Âm thanh chung cho trò chơi</summary><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_audio"><p class="hint">Tải nhạc từ máy (MP3/OGG/WAV/M4A, tối đa 15 MB mỗi tệp) hoặc dán liên kết HTTPS. Tệp tải lên được ưu tiên. Áp dụng chung cho tất cả bộ câu hỏi.</p><div class="two-col"><?php foreach(['welcome'=>'Nhạc chào mừng','start'=>'Nhạc bắt đầu','background'=>'Nhạc nền','timeout'=>'Nhạc kết thúc thời gian','answer'=>'Nhạc công bố đáp án','score'=>'Nhạc công bố điểm'] as $key=>$label):?><div><label><?=e($label)?></label><input type="url" name="music_<?=e($key)?>" value="<?=e((string)($audioSettings[$key]??''))?>" placeholder="https://.../nhac.mp3"><input type="file" name="music_file_<?=e($key)?>" accept="audio/mpeg,audio/ogg,audio/wav,audio/mp4,.mp3,.ogg,.wav,.m4a"><small>Chọn tệp để thay nhạc hiện tại</small><?php if(!empty($audioSettings[$key])):?><audio controls preload="none" style="width:100%;margin-top:8px" src="<?=e($audioSettings[$key])?>"></audio><?php endif?></div><?php endforeach?></div><button class="btn primary" style="margin-top:12px">Lưu âm thanh chung</button></form></details></div><?php endif?><?php require __DIR__.'/includes/quiz_user_guide.php'; ?>
<?php if($message): ?><div class="shell"><div class="notice" role="alert"><?=e($message)?></div></div><?php endif; ?>
<div class="shell workspace">
<aside class="panel work-sidebar"><div class="sidebar-heading"><span class="sidebar-mark">🎯</span><div><small>KHÔNG GIAN LÀM VIỆC</small><strong><?=e($set?(string)$set['title']:'Hỏi Nhanh - Đáp Gọn')?></strong></div></div>
<?php if (!$set): ?>
<a class="sidebar-item" href="#quiz-help"><span>📖</span><span><b>Hướng dẫn sử dụng</b><small>Soạn bộ · mẫu ChatGPT · cách chơi</small></span></a><nav class="sidebar-menu" aria-label="Thư viện và tạo bộ"><a class="sidebar-item <?=$view!=='create'?'active':''?>" href="<?=BASE_URL?>hoclieu_game_quiz.php"><span>📚</span><span><b>Thư viện câu hỏi</b><small>Chọn bộ để bắt đầu</small></span></a><a class="sidebar-item <?=$view==='create'?'active':''?>" href="?view=create"><span>✨</span><span><b>Tạo bộ câu hỏi</b><small>Tên, môn, khối, giới thiệu</small></span></a></nav>
<?php else: ?>
<nav class="sidebar-menu" aria-label="Các màn hình của bộ câu hỏi"><a class="sidebar-item <?=$view==='play'?'active':''?>" href="?set=<?=e(rawurlencode($setId))?>&amp;view=play"><span>🎮</span><span><b>Chọn cách chơi</b><small>Chọn lớp và mở lượt</small></span></a><?php if($canEditSet): ?><a class="sidebar-item <?=$view==='edit'?'active':''?>" href="?set=<?=e(rawurlencode($setId))?>&amp;view=edit"><span>✏️</span><span><b>Soạn câu hỏi</b><small>Sáu dạng và nhập nhanh</small></span></a><a class="sidebar-item <?=$view==='settings'?'active':''?>" href="?set=<?=e(rawurlencode($setId))?>&amp;view=settings"><span>⚙️</span><span><b>Thông tin bộ</b><small>Chia sẻ, sửa và xóa</small></span></a><?php endif; ?></nav><a class="sidebar-return" href="<?=BASE_URL?>hoclieu_game_quiz.php">← Về thư viện câu hỏi</a>
<?php endif; ?>
</aside>
<main class="content">
<?php if (!$set): ?>
<?php if ($view!=='create'): ?>
<section class="panel library-panel"><div class="section-title"><div><span class="eyebrow">BƯỚC 1 · THƯ VIỆN</span><h2>Thư viện câu hỏi</h2><p class="muted">Chọn bộ câu hỏi để xem cách chơi hoặc tạo bộ mới cho lớp.</p></div><a class="btn primary" href="?view=create">✨ Tạo bộ câu hỏi mới</a></div>
<input type="search" id="set-search" placeholder="Tìm theo tên bộ câu hỏi…" aria-label="Tìm bộ câu hỏi">
<div class="two-col" style="margin-top:12px"><div><label>Lọc theo môn</label><select id="subject-filter"><option value="">Tất cả môn</option><?php $filterSubjects=$quizSubjects;foreach($sets as $entry){$v=(string)($entry['category']??'');if($v!==''&&!in_array($v,$filterSubjects,true))$filterSubjects[]=$v;}foreach($filterSubjects as $v):?><option value="<?=e($v)?>"><?=e($v)?></option><?php endforeach?></select></div><div><label>Lọc theo khối</label><select id="grade-filter"><option value="">Tất cả</option><?php foreach($quizGrades as $v=>$label):?><option value="<?=e((string)$v)?>"><?=e($label)?></option><?php endforeach?><option value="unclassified">Chưa chọn khối</option></select></div></div><p id="filter-empty" class="hint" hidden>Không có bộ câu hỏi phù hợp.</p><div class="library-grid" id="set-list"><?php if (!$sets): ?><p class="empty">Chưa có bộ câu hỏi. Hãy tạo bộ đầu tiên ở bên dưới.</p><?php endif; ?>
<?php foreach($sets as $row): $owned=qp_admin() || $row['owner_id']===qp_owner();$count=count(json_decode((string)$row['questions_json'],true)?:[]); ?>
<article class="library-card set-item" data-subject="<?=e((string)($row['category']??''))?>" data-grade="<?=e((string)($row['grade_scope']??''))?>" data-title="<?=e(mb_strtolower((string)$row['title'].' '.($row['category']??'')))?>">
<span class="library-icon" aria-hidden="true">◉</span><span class="pill"><?=!empty($row['is_public'])?'🌐 Công khai':'🔒 Riêng tư'?></span>
<h3><?=e((string)$row['title'])?></h3><p class="muted"><?=e((string)($row['intro']??''))?></p><div class="library-meta"><?=e((string)($row['category']?:'Chưa phân loại'))?> · <?=e($quizGrades[(string)($row['grade_scope']??'')]??'Chưa chọn khối')?> · <?=$count?> câu hỏi</div>
<div class="row"><a class="btn primary" href="?set=<?=e(rawurlencode((string)$row['id']))?>">Chọn →</a><?php if($owned):?><form method="post" onsubmit="return confirm('Xóa bộ câu hỏi này và toàn bộ lượt chơi, kết quả liên quan? Không thể hoàn tác.')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="delete_set"><button class="btn danger" type="submit">🗑 Xóa</button></form><?php endif?><?php if(!$owned): ?><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e((string)$row['id'])?>"><input type="hidden" name="action" value="clone_set"><button class="btn">Tạo bản sao để sửa</button></form><?php endif; ?></div>
</article><?php endforeach; ?></div></section>
<?php else: ?>
<section class="panel author-choice"><div class="section-title"><h2>Chọn cách tạo câu hỏi</h2></div><div class="row"><button type="button" class="btn" data-author-mode="ai">✨ Tạo câu hỏi bằng AI</button><button type="button" class="btn" data-author-mode="manual">✍️ Tạo câu hỏi tuỳ chọn</button></div><p class="hint">Chọn một cách để mở giao diện tương ứng. Đổi cách không làm mất nội dung đang soạn.</p></section><?php qp_ai_panel($csrf); ?>
<section class="panel create-panel" id="create-set" hidden><div class="section-title"><div><span class="eyebrow">TẠO NỘI DUNG</span><h2>Tạo bộ câu hỏi mới</h2></div></div><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="create"><input type="hidden" name="ai_questions" id="qp-ai-create-questions"><div class="two-col"><div><label>Tên bộ câu hỏi</label><input type="text" name="title" maxlength="255" required placeholder="Ví dụ: Ôn tập Vật lý 10"></div></div><?php qp_classification_fields($quizSubjects,$quizGrades); ?><label>Giới thiệu ngắn</label><textarea name="intro" maxlength="500" rows="2" required placeholder="Nội dung, đối tượng hoặc mục tiêu của bộ câu hỏi"></textarea><div class="create-actions"><div><label>Chia sẻ</label><select name="public"><option value="0">Riêng tư</option><option value="1">Công khai cho giáo viên</option></select></div><button class="btn primary">Tạo bộ câu hỏi →</button></div></form><p class="hint">Bộ công khai cho người khác chơi hoặc tạo bản sao. Chỉ người tạo và quản trị được sửa, xóa bộ gốc.</p></section>
<?php endif; ?>
<?php endif; ?>
<?php if ($set): ?>
<?php if ($view==='play'): ?>
<section class="panel set-overview"><div class="section-title"><div><span class="eyebrow">CHỌN CÁCH CHƠI</span><h2><?=e((string)$set['title'])?></h2><p class="muted"><?=e((string)($set['intro']??''))?></p></div><div class="row"><span class="pill"><?=!empty($set['is_public'])?'🌐 Công khai':'🔒 Riêng tư'?></span><?php if($canEditSet): ?><a class="btn" href="?set=<?=e(rawurlencode($setId))?>&amp;view=edit#new-question">✏️ Soạn câu hỏi</a><?php endif; ?></div></div>
<div class="stats"><div class="stat"><b><?=count($questions)?></b><small>câu hỏi</small></div><div class="stat"><b><?=count($sessions)?></b><small>lượt chơi của bạn</small></div><div class="stat"><b><?=e((string)($set['category']?:'Chưa phân loại'))?></b><small>môn</small></div></div></section>
<?php endif; ?>
<?php if ($view==='play'): ?>
<section class="panel play-panel"><h2>Chọn chế độ chơi</h2><p class="muted">Chọn cách học sinh trả lời, sau đó chọn lớp để mở lượt chơi.</p>
<form method="post" id="open-play-form"><input type="hidden" name="view" value="play"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="open_session">
<div class="play-grid"><label class="play-option mode-computer"><input type="radio" name="mode" value="computer" checked><span class="play-icon">💻</span><strong>Chơi trên máy</strong><small>Điện thoại / máy tính · 6 dạng câu hỏi.</small></label><label class="play-option mode-paper"><input type="radio" name="mode" value="paper" <?=$questions&&!array_filter($questions,static fn($q)=>!in_array(qp_question_type($q),['single','paper_logic'],true))?'':'disabled'?>><span class="play-icon">📄📷</span><strong>Quét thẻ giấy A–D</strong><small>Quét bằng điện thoại · A–D / Hai mệnh đề.</small></label></div><div id="qpPace"><label>Chuyển câu</label><select name="pace_mode" id="qpPaceMode"><option value="timed">Theo thời gian · HS tự chuyển</option><option value="teacher">Theo giáo viên · GV chuyển</option></select><label id="qpSecondsLabel">Thời gian mặc định (giây)</label><input id="qpSeconds" type="number" name="seconds_per_question" min="10" max="300" value="20"></div>
<div class="class-picker"><strong>🏫 Chọn lớp · tối đa 2</strong><p class="hint">Hai lớp dùng chung mã chơi.</p><div class="class-options"><?php foreach($classes as $c): ?><label class="class-option"><input type="checkbox" name="class_ids[]" value="<?=e((string)$c['id'])?>"><span><?=e((string)$c['name'])?></span></label><?php endforeach; ?></div><?php if(!$classes): ?><p class="notice">Chưa có lớp đang hoạt động trong CSDL.</p><?php endif; ?></div><div class="play-actions"><button class="btn primary" <?=!$questions||!$classes?'disabled':''?>>Tạo lượt chơi →</button></div>
</form><p class="hint">Lưu ý: chế độ thẻ giấy cần in thẻ A–D cho học sinh, mở màn hình chiếu và dùng máy quét của giáo viên. <a href="<?=BASE_URL?>hoclieu_quiz_cards.php">Quản lý và in thẻ ↗</a> Lượt tự đóng sau 30 phút không có thao tác hoặc câu trả lời; tối đa 24 giờ.</p>
<?php if(!$questions): ?><p class="notice">Bộ này chưa có câu hỏi. Người tạo cần thêm câu hỏi trước khi mở lượt chơi.</p><?php endif; ?></section>
<?php endif; ?>
<?php if ($canEditSet && $view==='settings'): ?>
<section class="panel screen-heading"><span class="screen-icon">⚙️</span><div><span class="eyebrow">THÔNG TIN BỘ</span><h2>Sửa thông tin và chia sẻ</h2><p class="muted">Quản lý tên, thể loại, mô tả và quyền xem của bộ câu hỏi.</p></div></section>
<section class="panel settings-panel"><div class="set-editor"><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="save_set"><div class="two-col"><div><label>Tên bộ</label><input type="text" name="title" value="<?=e((string)$set['title'])?>" maxlength="255" required></div></div><?php qp_classification_fields($quizSubjects,$quizGrades,$set); ?><label>Giới thiệu</label><textarea name="intro" rows="2" maxlength="500" required><?=e((string)($set['intro']??''))?></textarea><label>Chia sẻ</label><select name="public"><option value="0" <?=empty($set['is_public'])?'selected':''?>>Riêng tư</option><option value="1" <?=!empty($set['is_public'])?'selected':''?>>Công khai cho giáo viên</option></select><button class="btn primary" style="margin-top:12px">Lưu thông tin</button></form>
<form method="post" onsubmit="return confirm('Xóa bộ câu hỏi này và toàn bộ câu hỏi, lượt chơi, kết quả liên quan? Không thể hoàn tác.')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="delete_set"><button class="btn danger">Xóa bộ câu hỏi</button></form></div></section>
<?php endif; ?>
<?php if ($canEditSet && $view==='edit'): ?>
<section class="panel screen-heading"><span class="screen-icon">✏️</span><div><span class="eyebrow">BIÊN SOẠN</span><h2>Soạn câu hỏi</h2><p class="muted"><?=e((string)$set['title'])?> · <?=count($questions)?> câu hỏi. Chọn dạng, thêm nội dung rồi lưu vào bộ.</p></div></section>
<section class="panel author-choice"><div class="section-title"><h2>Chọn cách tạo câu hỏi</h2></div><div class="row"><button type="button" class="btn" data-author-mode="ai">✨ Tạo câu hỏi bằng AI</button><button type="button" class="btn" data-author-mode="manual">✍️ Tạo câu hỏi tuỳ chọn</button></div><p class="hint">Chọn một cách để mở giao diện tương ứng. Đổi cách không làm mất nội dung đang soạn.</p></section><?php qp_ai_panel($csrf,$setId,count($questions),$set); ?>
<section class="panel section-anchor" id="new-question" hidden><details <?=$questions?'':'open'?>><summary class="section-summary">＋ Thêm câu hỏi mới · chọn 1 trong 6 dạng</summary><div class="set-editor"><?php qp_editor($csrf,$setId,-1); ?></div></details></section>
<section class="panel" id="question-list"><div class="section-title"><h2>Danh sách câu hỏi</h2><span class="pill"><?=count($questions)?> / 100 câu</span></div><?php if (!$questions): ?><p class="empty">✨ Bộ câu hỏi còn trống. Chọn một loại ở trên để tạo câu đầu tiên.</p><?php endif; ?><?php $typeNames=['single'=>'Một đáp án','multi'=>'Nhiều đáp án','paper_logic'=>'Hai mệnh đề','match'=>'Nối cặp','fill'=>'Điền đáp án','order'=>'Sắp xếp']; foreach($questions as $i=>$q): ?><article class="question-card"><div class="question-top"><span class="number"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><div class="question-body"><h3><?=e((string)$q['text'])?></h3><p class="hint"><?=e($typeNames[qp_question_type($q)]??'Một đáp án')?> · Đúng: <?=e(qp_correct_label($q))?></p><?php if(!empty($q['image'])): ?><img class="image-preview" src="<?=e((string)$q['image'])?>" alt="Ảnh minh họa"><?php endif; ?><?php if(in_array(qp_question_type($q),['single','multi','paper_logic'],true)): ?><div class="mini-choices"><?php foreach(['A','B','C','D'] as $letter): ?><span><b><?=$letter?>.</b> <?=e((string)($q['choices'][$letter]??''))?></span><?php endforeach; ?></div><?php endif; ?><div class="question-tools"><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="move_question"><input type="hidden" name="index" value="<?=$i?>"><button class="btn tiny" name="direction" value="up" <?=$i===0?'disabled':''?> title="Đưa lên">↑</button><button class="btn tiny" name="direction" value="down" <?=$i===count($questions)-1?'disabled':''?> title="Đưa xuống">↓</button></form><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="duplicate_question"><input type="hidden" name="index" value="<?=$i?>"><button class="btn tiny">⧉ Nhân bản</button></form><button class="btn tiny" type="button" data-edit="edit-<?=$i?>">✎ Chỉnh sửa</button><form method="post" onsubmit="return confirm('Xóa câu hỏi này?')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="index" value="<?=$i?>"><button class="btn tiny danger">Xóa</button></form></div></div></div><details id="edit-<?=$i?>"><summary>Sửa nội dung câu hỏi</summary><?php qp_editor($csrf,$setId,$i,$q); ?></details></article><?php endforeach; ?></section>
<section class="panel" id="manual-bulk" hidden><details><summary><strong>Nhập nhanh nhiều câu A–D</strong></summary><p class="hint">Mỗi dòng: Câu hỏi | A | B | C | D | Đáp án đúng. Ví dụ: 2 + 2 = ? | 3 | 4 | 5 | 6 | B</p><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="import_questions"><textarea name="bulk" rows="6" required placeholder="Dán mỗi câu trên một dòng"></textarea><button class="btn primary">Nhập câu hỏi</button></form></details></section>
<?php endif; ?>
<?php if ($view==='play'): ?>
<section class="panel sessions-panel"><div class="section-title"><div><span class="eyebrow">QUẢN LÝ LƯỢT CHƠI</span><h2>🎟️ Các lượt chơi</h2></div><span class="pill"><?=count($sessions)?> lượt gần đây</span></div>
<p class="student-entry"><span>💻 Học sinh chơi trên máy truy cập và nhập mã:</span> <a href="https://cds.noitruxinman.edu.vn/vaochoi.php" target="_blank" rel="noopener">https://cds.noitruxinman.edu.vn/vaochoi.php ↗</a></p>
<?php if (!$sessions): ?><p class="empty">Chưa có lượt chơi. Chọn chế độ và lớp ở trên để tạo lượt đầu tiên.</p><?php endif; ?>
<div class="session-list"><?php foreach($sessions as $s): $sessionClasses=json_decode((string)($s['class_ids_json']??''),true)?:[$s['class_id']];$sessionLabel=implode(' + ',array_map(static fn($id)=>$classMap[(string)$id]??(string)$id,$sessionClasses));$studentUrl=BASE_URL.'vaochoi.php?code='.rawurlencode((string)$s['code']);$screenUrl=BASE_URL.'chieu.php?code='.rawurlencode((string)$s['code']);$scanUrl=BASE_URL.'quet.php?code='.rawurlencode((string)$s['code']); ?>
<article class="session-card <?=($s['status']!=='open'||$s['phase']==='finished'?'is-closed':($s['mode']==='paper'?'is-paper':'is-computer'))?>"><div class="session-head"><span class="session-mode"><?=($s['mode']==='paper'?'📄📷 Thẻ giấy A–D':'💻 Chơi trên máy')?></span><span class="session-status <?=($s['status']==='open' && $s['phase']!=='finished'?'is-open':'')?>"><?=($s['phase']==='finished'?'🏁 Đã kết thúc':($s['status']==='open'?'● Đang mở':'● Đã đóng'))?></span></div><div class="session-main"><div><small>MÃ LƯỢT CHƠI</small><strong class="session-code"><?=e((string)$s['code'])?></strong></div><div><small>LỚP THAM GIA</small><strong>🏫 <?=e($sessionLabel)?></strong></div></div>
<div class="session-links"><?php if($s['mode']==='paper'): ?><a href="<?=e($screenUrl)?>" target="_blank" rel="noopener">📺 Màn chiếu ↗</a><a href="<?=e($scanUrl)?>" target="_blank" rel="noopener">📷 Quét thẻ ↗</a><?php else: ?><a href="<?=e($screenUrl)?>" target="_blank" rel="noopener">📺 Màn chiếu ↗</a><a href="<?=e($screenUrl.'&control=1')?>" target="_blank" rel="noopener">🎛 Điều khiển ↗</a><a href="<?=e($studentUrl)?>" target="_blank" rel="noopener">🎮 Vào chơi ↗</a><?php endif; ?><a href="?set=<?=e(rawurlencode($setId))?>&amp;view=play&amp;report=<?=e((string)$s['code'])?>">📊 Kết quả ↗</a></div>

<?php if($s['mode']==='computer' && $s['status']==='open' && empty($s['show_correct'])): ?><form method="post" class="session-close" onsubmit="return confirm('Công bố đáp án cho tất cả học sinh trong lượt chơi?')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="reveal_session"><input type="hidden" name="code" value="<?=e((string)$s['code'])?>"><button class="btn tiny primary">👁 Công bố đáp án</button></form><?php endif; ?>
<?php if($s['status']==='open'): ?><form method="post" class="session-close" onsubmit="return confirm('Kết thúc lượt chơi này? Học sinh sẽ không thể gửi đáp án tiếp.')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="set_id" value="<?=e($setId)?>"><input type="hidden" name="action" value="close_session"><input type="hidden" name="code" value="<?=e((string)$s['code'])?>"><button class="btn tiny danger"><?=($s['mode']==='computer'?'🏁 Kết thúc chơi':'Đóng lượt chơi')?></button></form><?php endif; ?></article>
<?php endforeach; ?></div></section>
<?php if ($reportSession): ?>
<div class="card"><h2>Kết quả mã <?=e($viewCode)?></h2><p><?=($reportSession['mode']==='paper'?'Thẻ giấy':'Máy tính')?> · <?=count($reportQuestions)?> câu · <?=count($reportStudents)?> học sinh trong lớp</p><a class="button secondary" href="?set=<?=e(rawurlencode($setId))?>&amp;report=<?=e($viewCode)?>&amp;export=csv">Xuất CSV</a> <a class="btn primary" href="?set=<?=e(rawurlencode($setId))?>&amp;report=<?=e(rawurlencode($viewCode))?>&amp;export=xlsx">📊 Xuất Excel có màu</a>
<div style="overflow:auto"><table><thead><tr><th>Học sinh</th><th>Số đúng</th><th>Đã trả lời</th><?php foreach ($reportQuestions as $i=>$q): ?><th title="<?=e((string)$q['text'])?>">C<?=$i+1?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($reportStudents as $id=>$name): $correct=0;$answered=0;foreach ($reportQuestions as $i=>$q) { $a=$reportGrid[$id][$i]??'';if ($a!=='') {$answered++;if (qp_answer_correct($q,$a))$correct++;} } ?>
<tr><td><?=e($name)?></td><td><?=$correct?> / <?=count($reportQuestions)?></td><td><?=$answered?></td><?php foreach ($reportQuestions as $i=>$q): $a=$reportGrid[$id][$i]??''; ?><td style="background:<?=$a===''?'#f1f4f7':(qp_answer_correct($q,$a)?'#e3f6e7':'#ffe9e5')?>"><?=e($a!==''?qp_answer_label($a):'—')?></td><?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table></div><p class="muted">Xanh: đúng · Đỏ: sai · Xám: chưa trả lời. Di chuột lên tiêu đề C1, C2… để xem câu hỏi.</p>
<h3>Thống kê từng câu</h3><div style="overflow:auto"><table><thead><tr><th>Câu</th><th>Đáp án</th><th>Đúng</th><th>Sai</th><th>Chưa trả lời</th><th>Tỉ lệ đúng</th></tr></thead><tbody>
<?php foreach ($reportQuestions as $i=>$q): $right=0;$wrong=0;foreach ($reportStudents as $id=>$name) { $a=$reportGrid[$id][$i]??'';if ($a!=='') { if (qp_answer_correct($q,$a))$right++;else $wrong++; } }$missing=count($reportStudents)-$right-$wrong; ?>
<tr><td>C<?=$i+1?>. <?=e((string)$q['text'])?></td><td><?=e(qp_correct_label($q))?></td><td><?=$right?></td><td><?=$wrong?></td><td><?=$missing?></td><td><?=($right+$wrong)>0?round(100*$right/($right+$wrong),1):0?>% (trong số đã trả lời)</td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php endif; ?><?php endif; ?><?php endif; ?></main></div><script>
const search=document.getElementById('set-search'),subjectFilter=document.getElementById('subject-filter'),gradeFilter=document.getElementById('grade-filter');if(search){function filterLibrary(){const term=search.value.trim().toLocaleLowerCase('vi'),subject=subjectFilter.value,grade=gradeFilter.value;let visible=0;document.querySelectorAll('.set-item').forEach(el=>{el.hidden=!el.dataset.title.includes(term)||(subject!==''&&el.dataset.subject!==subject)||(grade!==''&&(grade==='unclassified'?el.dataset.grade!=='':el.dataset.grade!==grade));if(!el.hidden)visible++});document.getElementById('filter-empty').hidden=visible>0}search.addEventListener('input',filterLibrary);subjectFilter.addEventListener('change',filterLibrary);gradeFilter.addEventListener('change',filterLibrary);filterLibrary()}
document.querySelectorAll('.class-options').forEach(picker=>picker.addEventListener('change',event=>{const selected=picker.querySelectorAll('input:checked');if(selected.length>2){event.target.checked=false;alert('Chỉ chọn tối đa 2 lớp trong một lượt chơi.')}}));
document.getElementById('open-play-form')?.addEventListener('submit',event=>{if(!event.currentTarget.querySelector('input[name="class_ids[]"]:checked')){event.preventDefault();alert('Hãy chọn ít nhất một lớp để chơi.')}});
document.querySelectorAll('[data-edit]').forEach(b=>b.addEventListener('click',()=>{let d=document.getElementById(b.dataset.edit);d.open=true;d.scrollIntoView({behavior:'smooth',block:'start'});d.querySelector('textarea[name=text]').focus({preventScroll:true})}));
document.querySelectorAll('[data-editor]').forEach(form=>{const sync=()=>{const type=form.querySelector('input[name=type]:checked').value;form.querySelectorAll('[data-for]').forEach(el=>el.hidden=!el.dataset.for.split(' ').includes(type));form.querySelectorAll('[name^=choice_]').forEach(el=>el.required=type==='single'||type==='multi');form.querySelector('[name=pairs]').required=type==='match';form.querySelector('[name=fill_answers]').required=type==='fill';form.querySelector('[name=steps]').required=type==='order'};form.querySelectorAll('input[name=type]').forEach(el=>el.addEventListener('change',sync));sync()});

const chooser=document.querySelector('.author-choice');if(chooser){const ai=document.getElementById('qp-ai-panel'),manual=document.getElementById('new-question')||document.getElementById('create-set'),bulk=document.getElementById('manual-bulk');if(ai)ai.hidden=true;else chooser.querySelector('[data-author-mode="ai"]').disabled=true;function selectAuthor(mode){if(ai)ai.hidden=mode!=='ai';if(manual)manual.hidden=mode!=='manual';if(bulk)bulk.hidden=mode!=='manual';chooser.querySelectorAll('[data-author-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.authorMode===mode)));}chooser.querySelectorAll('[data-author-mode]').forEach(b=>b.addEventListener('click',()=>selectAuthor(b.dataset.authorMode)));if(location.hash==='#new-question')selectAuthor('manual');}
const helpTexts={title:'Tên ngắn để nhận biết bộ câu hỏi.',category:'Chọn môn hoặc nhóm kiến thức của bộ.',grade_scope:'Chọn khối phù hợp với học sinh.',intro:'Nêu nội dung và mục tiêu của bộ câu hỏi.',public:'Riêng tư: chỉ thầy quản lý. Công khai: giáo viên khác có thể chơi hoặc sao chép.',text:'Viết rõ yêu cầu; tránh câu hỏi có nhiều cách hiểu.',seconds:'Thời gian trả lời trên máy, từ 10 đến 300 giây.',explanation:'Giải thích vì sao đáp án đúng; hiển thị khi công bố đáp án.',fill_answers:'Mỗi dòng một đáp án được chấp nhận.',steps:'Mỗi dòng một bước, theo trình tự đúng.',pairs:'Mỗi dòng một cặp theo mẫu trong ô nhập.',bulk:'Mỗi dòng một câu; dùng dấu | để ngăn các cột.'};document.querySelectorAll('#create-set input,#create-set select,#create-set textarea,#new-question input,#new-question textarea,#manual-bulk textarea').forEach(input=>{const text=helpTexts[input.name];if(!text||input.type==='hidden')return;const hint=document.createElement('small');hint.className='field-help';hint.textContent=text;input.insertAdjacentElement('afterend',hint);});

const playForm=document.getElementById('open-play-form');if(playForm){const pace=playForm.querySelector('[name=pace_mode]'),seconds=playForm.querySelector('[name=seconds_per_question]');function syncPace(){const computer=playForm.querySelector('[name=mode]:checked')?.value==='computer',timed=pace.value==='timed';document.getElementById('qpPace').hidden=!computer;seconds.hidden=!timed;seconds.disabled=!computer||!timed;document.getElementById('qpSecondsLabel').hidden=!timed}playForm.querySelectorAll('[name=mode]').forEach(el=>el.addEventListener('change',syncPace));pace.addEventListener('change',syncPace);syncPace()}
</script><?php require __DIR__ . '/includes/game_credit.php'; ?><script src="<?=e(BASE_URL)?>assets/quiz-ai.js?v=20261005-2"></script></body></html>
