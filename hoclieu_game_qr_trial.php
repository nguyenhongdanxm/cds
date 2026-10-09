<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/quiz_paper_store.php';
require_login();qp_schema();
$classes = array_values(array_filter(csdl_classes_all(), static fn($class) => !empty($class['active'])));
csdl_sort_classes($classes);
$code = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));
$paperSession = null;
if ($code !== '') {
    try {
        $candidate = qp_session($code);
        if ($candidate && $candidate['mode'] === 'paper' && ($candidate['owner_id'] === qp_owner() || qp_admin())) $paperSession = $candidate;
    } catch (Throwable $e) { $paperSession = null; }
    if (!$paperSession) { http_response_code(403); exit('Lượt chơi giấy không tồn tại hoặc bạn không có quyền mở.'); }
}
$classId = $paperSession ? (string)$paperSession['class_id'] : trim((string)($_GET['class'] ?? ''));
$chosen = null;
foreach ($classes as $class) {
    if ((string)$class['id'] === $classId) { $chosen = $class; break; }
}
$students = [];
$studentClasses=[];
$classNames=array_column($classes,'name','id');
$sessionClassIds=$paperSession ? (json_decode((string)($paperSession['class_ids_json']??''),true) ?: [$classId]) : [$classId];
$sessionClassLabel=implode(' + ',array_map(static fn($id)=>$classNames[(string)$id]??(string)$id,$sessionClassIds));
if ($paperSession) foreach (csdl_students_all() as $student) $studentClasses[(string)$student['id']]=(string)($student['class_id']??'');
if ($paperSession && !empty($paperSession['roster_json'])) {
    $roster = json_decode((string)$paperSession['roster_json'], true) ?: [];
    foreach ($roster as $id=>$name) $students[] = ['id'=>(string)$id,'name'=>(string)$name,'class_name'=>$classNames[$studentClasses[(string)$id]??'']??''];
}
if ($chosen && !$students) {
    foreach (csdl_students_all() as $student) {
        if (!empty($student['active']) && (string)($student['class_id'] ?? '') === $classId) {
            $students[] = ['id' => (string)$student['id'], 'name' => (string)($student['name'] ?? $student['full_name'] ?? ''),'class_name'=>(string)$chosen['name']];
        }
    }
}
$studentJson = json_encode($students, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$setId = $paperSession ? (string)$paperSession['set_id'] : trim((string)($_GET['set'] ?? ''));
$quizSet = null;
if ($setId !== '') {
    try { $quizSet = qp_set($setId); if ($quizSet && !qp_can_play_set($quizSet)) $quizSet = null; } catch (Throwable $e) { $quizSet = null; }
}
if (!$paperSession && !$quizSet && !qp_admin() && !can_perm_level('hl.xem', 'view')) {
    http_response_code(403); exit('Hãy chọn một bộ câu hỏi công khai để chơi.');
}
$bank = $paperSession ? (json_decode((string)$paperSession['questions_json'],true) ?: []) : ($quizSet ? qp_questions($quizSet) : []);
$bankJson = json_encode($bank, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if (empty($_SESSION['qp_paper_csrf'])) $_SESSION['qp_paper_csrf']=bin2hex(random_bytes(24));
$paperCsrf=(string)$_SESSION['qp_paper_csrf'];
if (empty($_SESSION['qp_screen_csrf'])) $_SESSION['qp_screen_csrf']=bin2hex(random_bytes(24));
$controlCsrf=(string)$_SESSION['qp_screen_csrf'];
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if ($_SERVER['REQUEST_METHOD']==='POST' && $paperSession) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!hash_equals($paperCsrf,(string)($_POST['csrf']??''))) throw new RuntimeException('Phiên không hợp lệ.');
        if ($paperSession['status']!=='open') throw new RuntimeException('Lượt chơi đã đóng.');
        $id=(string)($_POST['student_id']??'');
        $validIds=array_column($students,'id');
        $index=filter_var($_POST['index']??'',FILTER_VALIDATE_INT);
        $answer=(string)($_POST['answer']??'');
        if (!in_array($id,$validIds,true) || $index===false || !isset($bank[$index]) || ($answer!==''&&!in_array($answer,['A','B','C','D'],true))) throw new RuntimeException('Dữ liệu câu trả lời không hợp lệ.');
        $fresh=qp_session($code);
        if (!$fresh || $fresh['status']!=='open' || (int)$fresh['current_index']!==$index) throw new RuntimeException('Đã chuyển câu hỏi; vui lòng chờ máy quét đồng bộ.');
        if ($fresh['phase']!=='scanning') throw new RuntimeException('Câu hỏi chưa ở trạng thái quét.');
        if ($answer==='') {
            $st=qp_db()->prepare('DELETE FROM cds_quiz_answers WHERE session_code=? AND student_id=? AND question_index=?');
            $st->execute([$code,$id,$index]);
        } else {
            $st=qp_db()->prepare('INSERT INTO cds_quiz_answers(session_code,student_id,question_index,answer,answered_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE answer=VALUES(answer),answered_at=NOW()');
            $st->execute([$code,$id,$index,$answer]);
        }
        qp_touch($code);echo json_encode(['ok'=>true]);exit;
    } catch (Throwable $e) { http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit; }
}
$savedAnswers=[];
if ($paperSession) {
    $st=qp_db()->prepare('SELECT student_id,question_index,answer FROM cds_quiz_answers WHERE session_code=?');
    $st->execute([$code]);
    foreach ($st as $row) $savedAnswers[(int)$row['question_index']][(string)$row['student_id']]=(string)$row['answer'];
}
$savedJson=json_encode($savedAnswers,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trả lời bằng thẻ QR · Bản thử</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef3f9;color:#183153;font:16px system-ui,Arial,sans-serif}header{background:#123460;color:#fff;padding:18px max(18px,calc((100vw - 1140px)/2))}header a{color:#fff}main{max-width:1140px;margin:24px auto;padding:0 16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:16px}.panel{background:#fff;border-radius:16px;padding:20px;box-shadow:0 4px 20px #18315316;margin-bottom:16px}h1{margin:6px 0;font-size:26px}h2{margin:0 0 14px;font-size:20px}label{display:block;font-weight:650;margin:10px 0 5px}select,input,textarea,button{font:inherit}select,input,textarea{width:100%;padding:10px;border:1px solid #aabbd0;border-radius:8px}textarea{min-height:70px}button,.action{border:0;background:#126bb0;color:#fff;border-radius:9px;padding:11px 15px;cursor:pointer;text-decoration:none;display:inline-block;margin:4px 4px 4px 0}button:disabled{opacity:.5;cursor:not-allowed}.secondary{background:#47617f}.danger{background:#a93636}.answer-buttons button{min-width:55px;background:#e7f0fc;color:#14325a;border:2px solid transparent;font-weight:800}.answer-buttons button.active{border-color:#126bb0;background:#cce6ff}.muted{color:#56677c}.notice{background:#fff6d8;border-left:4px solid #e5a300;padding:10px;border-radius:6px}.camera{width:100%;height:100%;background:#07172c;object-fit:cover}.camera-stage{height:min(62vh,620px);position:relative;overflow:hidden;border-radius:16px;background:#07172c}.camera-tools{position:absolute;left:8px;right:8px;top:8px;display:flex;gap:8px;align-items:center;justify-content:space-between;background:#0009;color:#fff;border-radius:12px;padding:8px}.camera-tools button{margin:0;padding:8px}.camera-tools label{margin:0;font-size:13px}.camera-tools input{width:115px;vertical-align:middle}.detected-layer{position:absolute;inset:0;pointer-events:none}.detected-layer span{position:absolute;transform:translate(-50%,-50%);background:#092718e9;color:#fff;border:2px solid #27dc91;border-radius:10px;padding:5px 8px;font-size:13px;font-weight:800;white-space:nowrap;box-shadow:0 4px 12px #0008}.camera-feed{position:absolute;bottom:8px;left:8px;right:8px;display:flex;flex-wrap:wrap;gap:6px;pointer-events:none}.camera-feed span{background:#04281ee8;border:2px solid #22c984;color:#fff;border-radius:9px;padding:7px 10px;font-weight:750}.camera-stage.scanner-full{position:fixed;z-index:9999;inset:0;width:100vw;height:100dvh;border-radius:0}.scanner-full .camera-tools{top:env(safe-area-inset-top);padding:12px}.scanner-full .camera-feed{bottom:calc(85px + env(safe-area-inset-bottom))}.scanner-full .camera{object-fit:cover}.camera-bottom{display:none}.scanner-full .camera-bottom{display:flex;position:absolute;bottom:env(safe-area-inset-bottom);left:0;right:0;gap:8px;background:#000c;padding:10px}.camera-bottom button{flex:1;margin:0}.camera-stage:not(.scanner-full) .camera-tools{flex-wrap:wrap}.results{width:100%;border-collapse:collapse}.results td,.results th{padding:9px;border-bottom:1px solid #d9e2ec;text-align:left}.results tr.ok{background:#e4f7e9}.results tr.bad{background:#ffefeb}.phone-controls{display:flex;flex-wrap:wrap;gap:4px}.phone-controls button{flex:1;min-width:125px}.scan-roster{display:flex;flex-wrap:wrap;gap:5px;margin:12px 0}.scan-roster span{font-size:13px;border-radius:15px;background:#e8edf3;padding:5px 8px}.scan-roster span.done{background:#a6e6c2;color:#064827}.scan-graph{display:flex;gap:8px;margin:10px 0}.scan-graph span{padding:5px 8px;border-radius:8px;background:#e4ecf5}.cards{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.quiz-card{width:100%;aspect-ratio:1;border:2px solid #14283e;background:#fff;display:grid;grid-template-rows:15% 70% 15%;text-align:center;page-break-inside:avoid;break-inside:avoid}.quiz-card .middle{display:grid;grid-template-columns:15% 70% 15%;align-items:center}.quiz-card .letter{font-size:27px;font-weight:900}.quiz-card .code{display:flex;align-items:center;justify-content:center}.quiz-card .code img,.quiz-card .code canvas{width:100%;height:100%;object-fit:contain}.quiz-card small{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:2px 5px}.status{font-weight:700;min-height:24px}.hidden{display:none!important}@media(max-width:600px){.cards{grid-template-columns:1fr 1fr}.quiz-card .letter{font-size:20px}}@media print{body{background:#fff}header,.no-print,.panel:not(.print-panel){display:none!important}main{max-width:none;margin:0;padding:0}.print-panel{box-shadow:none;padding:0;margin:0}.cards{grid-template-columns:repeat(3,1fr);gap:5mm}.quiz-card{width:58mm;height:58mm}h2{display:none}}
.quiz-card>.letter:first-child{align-self:center}

.mobile-dock,.mobile-results,.mobile-missing-toggle,.mobile-missing{display:none}
@media(max-width:700px){
 body.mobile-quiz{background:#061326;color:#fff}
 .mobile-quiz header,.mobile-quiz main>.panel,.mobile-quiz main>.notice,.mobile-quiz .grid>section:first-child,.mobile-quiz main>.grid~section{display:none!important}
 .mobile-quiz main,.mobile-quiz .grid,.mobile-quiz .grid>section{display:block;max-width:none;margin:0;padding:0;background:none;box-shadow:none}
 .mobile-quiz .grid>section>h2,.mobile-quiz .grid>section>.phone-controls,.mobile-quiz .grid>section>.scan-graph,.mobile-quiz .grid>section>.scan-roster,.mobile-quiz .grid>section>p.muted,.mobile-quiz .grid>section>#scanStatus{display:none}
 .mobile-quiz .camera-stage,.mobile-quiz .camera-stage.scanner-full{position:fixed;inset:0;width:100vw;height:100dvh;border-radius:0;background:radial-gradient(circle,#143c68,#061326 75%)}
 .mobile-quiz .camera{object-fit:cover}
 .mobile-quiz .camera-tools{top:env(safe-area-inset-top);left:10px;right:10px;background:#061326d9;border:1px solid #6fbaf466;border-radius:16px;padding:9px 12px;z-index:2}
 .mobile-quiz .camera-tools #cameraExpand{display:none}
 .mobile-quiz .camera-tools label{display:flex;align-items:center;gap:5px;color:#d3ecff}
 .mobile-quiz .camera-tools input{width:75px;accent-color:#2ed6be}
 .mobile-quiz .mobile-missing-toggle{display:block;position:absolute;z-index:4;top:calc(65px + env(safe-area-inset-top));right:10px;margin:0;padding:8px 12px;border:1px solid #ffd76a;border-radius:20px;background:#142849e8;color:#ffd76a;font-size:13px;font-weight:850}
 .mobile-quiz .mobile-missing.open{display:block;position:absolute;z-index:4;top:calc(109px + env(safe-area-inset-top));left:10px;right:10px;max-height:44vh;overflow:auto;padding:14px;border:1px solid #ffd76a88;border-radius:17px;background:#102845f2;color:#fff;box-shadow:0 15px 35px #0009}
 .mobile-missing h3{margin:0 0 5px;color:#ffd76a;font-size:17px}.mobile-missing p{margin:0 0 10px;color:#bdd4e7;font-size:12px}.mobile-missing .missing-names{display:flex;flex-wrap:wrap;gap:6px}.mobile-missing .missing-names span{padding:6px 9px;border-radius:15px;background:#ffffff1b;border:1px solid #ffffff45;font-size:13px}.mobile-missing .all-done{color:#68ebb2;font-weight:850}
 .mobile-quiz .camera-feed{bottom:calc(180px + env(safe-area-inset-bottom));z-index:2}
 .mobile-quiz .camera-bottom{display:none}
 .mobile-quiz .mobile-dock{display:block;position:absolute;z-index:3;bottom:0;left:0;right:0;padding:14px 12px calc(12px + env(safe-area-inset-bottom));background:linear-gradient(transparent,#061326 18%);color:#fff}
 .mobile-dock .mobile-question{margin:0 0 8px;font-size:14px;font-weight:750;max-height:38px;overflow:hidden}
 .mobile-dock .mobile-status{min-height:20px;margin:0 0 9px;color:#a8e9f9;font-size:12px}
 .mobile-dock .mobile-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}
 .mobile-dock button{margin:0;min-width:0;padding:10px 4px;font-size:12px;font-weight:800;background:#123d69;color:#fff;border:1px solid #5badd3}
 .mobile-dock #mobileStart{background:linear-gradient(135deg,#1baad9,#117bbf)}
 .mobile-dock #mobilePublish{background:linear-gradient(135deg,#ffcf48,#f07b39);color:#24152c;border:0}
 .mobile-quiz .mobile-results.open{display:flex;flex-direction:column;position:fixed;inset:0;z-index:10001;background:linear-gradient(155deg,#123960,#14113e 75%);padding:calc(18px + env(safe-area-inset-top)) 16px calc(18px + env(safe-area-inset-bottom));overflow:auto;color:#fff}
 .mobile-results h2{margin:0 0 8px;color:#ffd76a;font-size:24px}.mobile-results .score-summary{font-weight:850;font-size:19px;margin:8px 0 16px}
 .mobile-results .score-columns{display:grid;gap:12px}.mobile-results .score-group{background:#ffffff18;border:1px solid #ffffff35;border-radius:16px;padding:12px}.score-group h3{margin:0 0 8px}.score-group span{display:inline-block;margin:3px;padding:6px 9px;border-radius:15px;background:#ffffff23;font-size:13px}.score-group.correct{border-color:#37d99a}.score-group.incorrect{border-color:#ff9680}.score-group.missing{border-color:#a6b9d6}
 .mobile-results button{position:sticky;bottom:0;margin-top:16px;background:#ffd76a;color:#162747;font-weight:900}
}

.detected-layer span.scan-wrong,.camera-feed span.scan-wrong{background:#9e2636e8;border-color:#ff8e9b;color:#fff}.detected-layer span.scan-correct,.camera-feed span.scan-correct{background:#087449e8;border-color:#56f4b1;color:#fff}@media(max-width:700px){.mobile-quiz .mobile-missing-toggle{padding:10px 13px;background:#142849f2;box-shadow:0 4px 16px #0008}.mobile-dock #mobileFinish:not([hidden]){display:block;grid-column:1/-1;background:#6946b9;color:#fff;border-color:#cbb6ff}}
.scanner-back{color:#fff;font-size:12px;white-space:nowrap;text-decoration:none;padding:6px;border:1px solid #8ebde866;border-radius:9px}.camera-tools{flex-wrap:wrap}#cameraPermission{position:fixed;inset:18% 5% auto;z-index:20000;background:#122d50;color:#fff;padding:22px;border:1px solid #8acbff;border-radius:18px;box-shadow:0 15px 80px #000b;max-height:70dvh;overflow:auto}#cameraPermission p{font-size:14px;line-height:1.5}@media(max-width:700px){.mobile-quiz .mobile-missing-toggle{top:calc(128px + env(safe-area-inset-top))}.mobile-quiz .mobile-missing.open{top:calc(176px + env(safe-area-inset-top))}}</style></head><body class="<?=$chosen?'mobile-quiz':''?>">
<header><a href="<?=BASE_URL?>hoclieu.php?tab=games">← Trò chơi</a><h1>Trả lời bằng thẻ QR <small>· Bản thử</small></h1></header>
<main><div class="panel no-print"><?php if ($paperSession): ?><strong>Lượt thẻ giấy <?=e($code)?> · Lớp <?=e($sessionClassLabel)?></strong> · <a href="<?=BASE_URL?>hoclieu_game_quiz_screen.php?code=<?=e($code)?>" target="_blank" rel="noopener">Mở màn hình chiếu</a><?php else: ?><form method="get"><?php if ($quizSet): ?><input type="hidden" name="set" value="<?=e($setId)?>"><?php endif; ?><label for="class">Lớp chơi</label><select id="class" name="class" onchange="this.form.submit()"><option value="">Chọn lớp</option><?php foreach ($classes as $class): ?><option value="<?=e((string)$class['id'])?>" <?=($chosen && $class['id']===$chosen['id'])?'selected':''?>><?=e((string)$class['name'])?></option><?php endforeach; ?></select></form><?php endif; ?></div>
<?php if ($chosen): ?>
<p class="notice no-print"><?=$paperSession?'Đáp án quét được lưu vào báo cáo của lượt chơi này.':'Chế độ thử độc lập lưu kết quả trong trình duyệt.'?> Mỗi em xoay thẻ sao cho đáp án A, B, C hoặc D nằm trên cùng. Máy quét xác định hướng bằng ba ô vuông ở góc mã QR; thẻ đã in trước đây vẫn dùng được. Không ghi điểm Olympia.</p>
<div class="grid no-print"><section class="panel"><h2>1. Câu hỏi<?php if ($quizSet): ?> · <?=e((string)$quizSet['title'])?><?php endif; ?></h2><label for="question">Nội dung</label><textarea id="question" placeholder="Nhập câu hỏi để hiển thị khi chơi" <?=$quizSet?'readonly':''?>></textarea><img id="questionImage" class="hidden" alt="Hình minh họa câu hỏi" style="max-width:100%;max-height:220px"><div id="choices"></div><label>Đáp án đúng</label><div class="answer-buttons" id="keys"></div><button id="newQuestion" <?=$quizSet?'class="hidden"':''?>>Mở câu hỏi mới</button><?php if ($quizSet && !$paperSession): ?><button id="nextQuestion">Câu tiếp theo</button><?php endif; ?><button id="clearQuestion" class="secondary">Xóa lượt quét của câu này</button><p id="current" class="status"></p></section>
<section class="panel"><h2>2. Điều khiển và quét bằng điện thoại</h2><div class="camera-stage" id="cameraStage"><video class="camera" id="video" playsinline muted autoplay></video><div class="camera-tools"><a class="scanner-back" href="<?=e(BASE_URL)?>hoclieu_game_quiz.php?set=<?=e(rawurlencode($setId))?>&amp;view=play">← Quay lại</a><strong id="cameraCount">0 đã quét</strong><label>Zoom <input id="cameraZoom" type="range" min="1" max="4" step="0.1" value="1"> <span id="zoomValue">1×</span></label><select id="cameraDevice" aria-label="Chọn camera" style="width:125px;max-width:36%;font-size:12px;padding:6px"><option value="">Camera sau chính</option></select><button type="button" id="cameraExpand" class="secondary">⛶ Toàn màn hình</button></div><button type="button" class="mobile-missing-toggle" id="mobileMissingToggle" aria-expanded="false" aria-controls="mobileMissing">👥 Xem học sinh chưa quét · 0</button><div class="mobile-missing" id="mobileMissing"><h3>Học sinh chưa quét</h3><p>Danh sách của câu hiện tại · camera vẫn tiếp tục quét.</p><div class="missing-names" id="mobileMissingNames"></div></div><div class="detected-layer" id="detectedLayer"></div><div class="camera-feed" id="cameraFeed"></div><div class="camera-bottom"><button type="button" id="cameraFinish" class="secondary">Dừng & xem kết quả</button><button type="button" id="cameraClose" class="secondary">Thu nhỏ</button></div><div class="mobile-dock"><p class="mobile-question" id="mobileQuestion">Sẵn sàng quét thẻ</p><p class="mobile-status" id="mobileStatus">Chạm Bật quét để mở camera.</p><div class="mobile-actions"><button type="button" id="mobilePrev">← Trước</button><button type="button" id="mobileStart">▶ Bắt đầu · Camera</button><button type="button" id="mobileNext">Tiếp →</button><button type="button" id="mobilePreview">💡 Đáp án</button><button type="button" id="mobilePublish">📊 Công bố</button><button type="button" id="mobileFinish" hidden>🏆 Kết thúc & xếp hạng</button></div></div></div><div id="answerPreview" class="hidden" style="position:fixed;inset:15% 5% auto;z-index:120;background:#fff;color:#123;padding:22px;border-radius:18px;box-shadow:0 15px 70px #0009;max-height:65vh;overflow:auto"><h3>👁 Đáp án trên màn chiếu</h3><p id="previewAnswer"></p><p id="previewExplanation"></p><button type="button" id="closePreview">Đóng</button></div><div id="cameraPermission" class="hidden" role="dialog" aria-modal="true" aria-labelledby="cameraPermissionTitle"><h3 id="cameraPermissionTitle">📷 Cho phép camera</h3><p>Chạm biểu tượng cài đặt hoặc ổ khoá cạnh địa chỉ trang → Quyền của trang → Camera → Cho phép. Trên iPhone: menu aA / cài đặt trang web → Camera → Cho phép.</p><p>Sau đó bấm Thử lại. Nếu trình duyệt vẫn chặn, tải lại trang sau khi đổi quyền. Mở bằng Safari hoặc Chrome nếu đang dùng trình duyệt trong ứng dụng.</p><button type="button" id="retryCamera">📷 Thử lại · Xin quyền camera</button><button type="button" id="closeCameraPermission" class="secondary">Đóng</button></div><canvas id="frame" class="hidden"></canvas><p id="scanStatus" class="status">Chọn lớp và mở câu hỏi để bắt đầu.</p><div class="phone-controls"><button id="startScan">Bật camera · Quét</button><button id="stopScan" class="secondary">Dừng quét</button><?php if ($paperSession): ?><button id="prevRemote" class="secondary">← Câu trước</button><button id="nextRemote">Câu tiếp →</button><button id="revealRemote" class="secondary">Hiện đáp án</button><button id="graphRemote" class="secondary">Hiện biểu đồ</button><?php endif; ?></div><div id="scanGraph" class="scan-graph"></div><div id="scanRoster" class="scan-roster"></div><p class="muted">Giữ mã hướng về camera, đủ sáng và lia máy qua các nhóm học sinh. Tên đổi màu khi đã quét. Có thể sửa thủ công ở bảng dưới.</p></section></div>
<section class="panel no-print"><h2>3. Kết quả câu hiện tại · <span id="count">0</span>/<?=count($students)?></h2><button id="exportCsv" class="secondary">Xuất CSV kết quả</button><table class="results"><thead><tr><th>Học sinh</th><th>Đáp án</th><th>Đúng/sai</th><th>Sửa thủ công</th></tr></thead><tbody id="resultRows"></tbody></table></section>
<div class="mobile-results" id="mobileResults" role="dialog" aria-modal="true" aria-label="Kết quả câu hỏi"><h2>✨ Kết quả câu hỏi</h2><p id="mobileScoreSummary" class="score-summary"></p><div id="mobileScoreColumns" class="score-columns"></div><button type="button" id="mobileResultsClose">← Quay lại máy quét</button></div>
<section class="panel print-panel"><div class="no-print"><h2>Thẻ trả lời lớp <?=e($sessionClassLabel)?></h2><p>Mã này chỉ chứa ID nội bộ; không chứa CCCD, số điện thoại hay thông tin phụ huynh. Dùng thẻ riêng cho trò chơi, không thay QR xác minh thẻ học sinh.</p><button id="printCards" disabled>In thẻ A–D</button><p id="printStatus" class="status"></p></div><div id="cards" class="cards"></div></section>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script><script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="<?=e(BASE_URL)?>assets/quiz-camera.js?v=20261009-1"></script><script>
const students=<?=$studentJson?:'[]'?>, classId=<?=json_encode($classId,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,bank=<?=$bankJson?:'[]'?>,setId=<?=json_encode($quizSet?$setId:'',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,paperCode=<?=json_encode($paperSession?$code:'',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,paperCsrf=<?=json_encode($paperCsrf,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,controlCsrf=<?=json_encode($controlCsrf,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,savedAnswers=<?=$savedJson?:'{}'?>,screenApi=<?=json_encode(BASE_URL.'hoclieu_game_quiz_screen.php',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const byId=new Map(students.map(s=>[s.id,s]));
const $=id=>document.getElementById(id), escapeHtml=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const storageKey='cds-qr-trial-v2:'+classId+':'+(paperCode||setId);
let state;try{state=JSON.parse(sessionStorage.getItem(storageKey)||'{}')}catch(e){state={}}
if(!state||typeof state!=='object')state={};
state.questions=Array.isArray(state.questions)?state.questions:[];
state.index=Number.isInteger(state.index)?state.index:-1;
if(paperCode||setId){state.questions=bank.map((q,i)=>({...q,answers:paperCode?(savedAnswers[i]||{}):(state.questions[i]?.answers||{})}));if(state.index<0&&bank.length)state.index=0}
if(paperCode)state.index=<?=($paperSession?(int)$paperSession['current_index']:0)?>;
let cameraStarting=false,cameraStream=null,scanning=false,busy=false,lastSeen=new Map(),paperOpen=true,phase=<?=json_encode((string)($paperSession['phase']??'question'))?>,showCorrect=false,showGraph=false,pending=new Set(),digitalZoom=1,hardwareZoom=false,feedTimer=null;
const current=()=>state.questions[state.index]||null;
function save(){sessionStorage.setItem(storageKey,JSON.stringify(state))}
function mobileStatus(message){$('scanStatus').textContent=message;$('mobileStatus').textContent=message}
function renderMobileMissing(){
 const q=current(),missing=q?students.filter(student=>!q.answers?.[student.id]):students;
 $('mobileMissingToggle').textContent='👥 Xem học sinh chưa quét · '+missing.length;
 const list=$('mobileMissingNames');list.replaceChildren();
 if(!q){const message=document.createElement('span');message.textContent='Chưa mở câu hỏi.';list.appendChild(message);return}
 if(!missing.length){const message=document.createElement('div');message.className='all-done';message.textContent='✓ Tất cả học sinh đã được ghi nhận';list.appendChild(message);return}
 for(const student of missing){const name=document.createElement('span');name.textContent=student.name;list.appendChild(name)}
}
function renderMobileResults(){
 const q=current(),groups={correct:[],incorrect:[],missing:[]};
 for(const student of students){const answer=q?.answers?.[student.id]||'';groups[!answer?'missing':answer===q.key?'correct':'incorrect'].push({name:student.name,answer})}
 $('mobileQuestion').textContent=q?'Câu '+(state.index+1)+' / '+state.questions.length+' · '+(q.text||'Chưa có nội dung'):'Chưa mở câu hỏi';
 $('mobilePrev').disabled=!paperCode||phase==='welcome'||state.index<=0;$('mobileNext').disabled=!paperCode||phase==='welcome'||state.index>=state.questions.length-1;$('mobileFinish').hidden=!paperCode||state.index!==state.questions.length-1||phase==='finished';$('mobileFinish').disabled=phase!=='results'||!showCorrect;
 $('mobileScoreSummary').textContent=groups.correct.length+' đúng · '+groups.incorrect.length+' sai · '+groups.missing.length+' chưa trả lời';
 const columns=$('mobileScoreColumns');columns.replaceChildren();
 for(const [kind,title] of [['correct','✓ Đúng'],['incorrect','✕ Sai'],['missing','— Chưa trả lời']]){
  const section=document.createElement('section'),heading=document.createElement('h3');section.className='score-group '+kind;heading.textContent=title+' ('+groups[kind].length+')';section.appendChild(heading);
  for(const person of groups[kind]){const tag=document.createElement('span');tag.textContent=person.name+(person.answer?' · '+person.answer:'');section.appendChild(tag)}columns.appendChild(section)
 }
 renderMobileMissing();
}
async function persistAnswer(id,answer,index){if(!paperCode)return true;let body=new URLSearchParams({code:paperCode,csrf:paperCsrf,student_id:id,index:String(index),answer});try{let response=await fetch(location.pathname+'?code='+encodeURIComponent(paperCode),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});let result=await response.json();if(!response.ok||!result.ok)throw Error(result.message||'Không lưu được');return true}catch(e){$('scanStatus').textContent='Chưa lưu lên máy chủ: '+e.message;await syncQuestion();return false}}
async function control(action,values={}){let body=new URLSearchParams({csrf:controlCsrf,action,...values});let response=await fetch(screenApi+'?code='+encodeURIComponent(paperCode),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});let result=await response.json();if(!response.ok||!result.ok)throw Error(result.message||'Không điều khiển được lượt chơi');return result}
function render(){let q=current();$('question').value=q?.text||'';$('questionImage').classList.toggle('hidden',!q?.image);if(q?.image)$('questionImage').src=q.image;$('choices').innerHTML=q?.choices?['A','B','C','D'].map(a=>'<p><b>'+a+'.</b> '+escapeHtml(q.choices[a]||'')+'</p>').join(''):'';$('current').textContent=q?'Câu '+(state.index+1)+' / '+state.questions.length+' · '+(q.text||'Chưa nhập nội dung'):'Chưa mở câu hỏi';$('keys').innerHTML=['A','B','C','D'].map(a=>'<button type="button" data-key="'+a+'" class="'+(q?.key===a?'active':'')+'" '+(setId?'disabled':'')+'>'+a+'</button>').join('');let count=0;$('resultRows').innerHTML=students.map(s=>{let a=q?.answers?.[s.id]||'',status=a?(a===q.key?'Đúng':'Sai'):'Chưa trả lời';if(a)count++;return '<tr class="'+(a?(a===q.key?'ok':'bad'):'')+'"><td>'+escapeHtml(s.name)+'</td><td>'+escapeHtml(a||'—')+'</td><td>'+status+'</td><td><select class="manual" data-id="'+escapeHtml(s.id)+'"><option value="">—</option>'+['A','B','C','D'].map(x=>'<option value="'+x+'" '+(a===x?'selected':'')+'>'+x+'</option>').join('')+'</select></td></tr>'}).join('');$('count').textContent=count;renderMobileResults()}
$('keys').onclick=e=>{let a=e.target.dataset.key,q=current();if(!q||!a)return;q.key=a;save();render()};
$('question').oninput=e=>{let q=current();if(q){q.text=e.target.value;save();$('current').textContent='Câu '+(state.index+1)+' · '+(q.text||'Chưa nhập nội dung')}};
$('newQuestion').onclick=()=>{stopCamera();state.questions.push({text:'',key:'A',answers:{}});state.index=state.questions.length-1;save();render();$('question').focus()};
if(setId&&!paperCode)$('nextQuestion').onclick=()=>{if(state.index<state.questions.length-1){stopCamera();state.index++;save();render()}else $('scanStatus').textContent='Đã hết bộ câu hỏi.'};
async function syncQuestion(){
 if(!paperCode||cameraStarting)return;
 try{
  let response=await fetch(screenApi+'?code='+encodeURIComponent(paperCode)+'&api=state',{cache:'no-store'}),data=await response.json();
  if(!data.ok)throw Error('Không đọc được lượt chơi');if(cameraStarting)return;
  paperOpen=data.status==='open';phase=data.phase;showCorrect=data.show_correct;showGraph=data.show_graph;if(phase==='welcome'){$('mobileQuestion').textContent='🎯 Sẵn sàng · bấm Bắt đầu để mở câu hỏi và camera';$('mobileStart').textContent='▶ Bắt đầu · Camera';}
  if(!paperOpen){stopCamera();renderMobileResults();$('mobileStart').disabled=true;$('mobilePublish').disabled=true;$('mobilePreview').disabled=true;$('scanStatus').textContent=phase==='finished'?'Đã kết thúc · bảng xếp hạng đang hiển thị trên màn chiếu.':'Lượt chơi đã đóng.';return}
  if(phase!=='scanning'&&scanning&&!cameraStarting)stopCamera();
  let changed=state.index!==data.index;state.index=data.index;
  let q=current(),answers=data.responses||{};
  if(q&&JSON.stringify(q.answers)!==JSON.stringify(answers)){q.answers=answers;changed=true}
  if(changed){lastSeen.clear();save();render()}else renderMobileResults()
  $('startScan').textContent=phase==='welcome'?'▶ Bắt đầu · Mở camera':phase==='scanning'?'Bật camera · Quét':'Mở lượt quét · Bật camera';
  $('revealRemote').textContent=showCorrect?'Ẩn đáp án':'Hiện đáp án';
  $('graphRemote').textContent=showGraph?'Ẩn biểu đồ':'Hiện biểu đồ';
  $('stopScan').disabled=phase!=='scanning';
  $('scanRoster').replaceChildren(...(data.roster||[]).map(student=>{let badge=document.createElement('span');badge.textContent=student.name+(student.answered?' ✓':'');badge.className=student.answered?'done':'';return badge}));
  $('cameraCount').textContent=data.answered+' / '+data.students+' đã quét';
  $('scanGraph').replaceChildren(...['A','B','C','D'].map(letter=>{let badge=document.createElement('span');badge.textContent=letter+': '+(data.graph?.[letter]||0);return badge}));
  if(phase==='welcome'){$('mobileQuestion').textContent='🎯 Sẵn sàng · bấm Bắt đầu để mở câu hỏi và camera';$('mobileStart').textContent='▶ Bắt đầu · Camera';}
  if(!scanning)$('scanStatus').textContent=phase==='finished'?'Đã kết thúc · xem bảng xếp hạng trên màn chiếu.':phase==='scanning'?'Đang mở lượt quét · chạm Bật camera để nhận thẻ.':phase==='results'?'Đã dừng quét · '+data.answered+'/'+data.students+' học sinh đã trả lời.':'Câu '+(data.index+1)+' sẵn sàng.';
 }catch(e){$('scanStatus').textContent='Mất kết nối màn hình chiếu: '+e.message}
}
$('clearQuestion').onclick=async()=>{let q=current();if(!q||!confirm('Xóa tất cả câu trả lời của câu hiện tại?'))return;
 if(paperCode){try{await control('clear');await syncQuestion()}catch(e){$('scanStatus').textContent=e.message}}
 else{q.answers={};save();render()}
};
$('resultRows').onchange=async e=>{if(!e.target.matches('.manual'))return;let q=current();if(!q)return;let id=e.target.dataset.id;if(!byId.has(id))return;
 if(paperCode&&phase!=='scanning'){$('scanStatus').textContent='Hãy mở lượt quét trước khi sửa đáp án.';await syncQuestion();return}
 let value=e.target.value,index=state.index;
 if(paperCode){if(!await persistAnswer(id,value,index))return}
 if(value)q.answers[id]=value;else delete q.answers[id];save();render()
};
function csvCell(v){return '"'+String(v??'').replace(/"/g,'""')+'"'}
$('exportCsv').onclick=()=>{let rows=[['Câu','Nội dung','Đáp án đúng','Lớp','Học sinh','Đáp án','Kết quả']];state.questions.forEach((q,i)=>students.forEach(s=>{let a=q.answers?.[s.id]||'';rows.push([i+1,q.text,q.key,s.class_name||<?=json_encode((string)($chosen['name']??''),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,s.name,a,a?(a===q.key?'Đúng':'Sai'):'Chưa trả lời'])}));let csv='\uFEFF'+rows.map(r=>r.map(csvCell).join(',')).join('\r\n'),url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'})),link=document.createElement('a');link.href=url;link.download='tra-loi-the-'+classId+'.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000)};
const cards=$('cards');students.forEach(s=>{let el=document.createElement('div');el.className='quiz-card';el.innerHTML='<div class="letter">A</div><div class="middle"><span class="letter">D</span><div class="code"></div><span class="letter">B</span></div><div><span class="letter">C</span><br><small></small></div>';el.querySelector('small').textContent=s.name;cards.appendChild(el);if(window.QRCode){new QRCode(el.querySelector('.code'),{text:'CDSQ1:'+s.id,width:480,height:480,correctLevel:QRCode.CorrectLevel.L})}});$('printStatus').textContent=window.QRCode?'Đã tạo '+students.length+' thẻ. In ở tỷ lệ 100%, không chọn vừa trang.':'Không tải được thư viện tạo QR; kiểm tra kết nối mạng trước khi in.';$('printCards').disabled=!window.QRCode;$('printCards').onclick=()=>window.print();
// jsQR labels the three QR finder patterns in the code's own orientation.
// Its top edge therefore turns with the printed A side, regardless of the
// screen-space corner order supplied by BarcodeDetector.
function answerFromFinder(loc){
 const p=loc?.topLeftFinderPattern,r=loc?.topRightFinderPattern;
 if(!p||!r)return null;
 const dx=r.x-p.x,dy=r.y-p.y;
 if(Math.hypot(dx,dy)<10)return null;
 const angle=Math.atan2(dy,dx)*180/Math.PI;
 if(angle>=-45&&angle<45)return 'A';
 if(angle>=45&&angle<135)return 'D';
 if(angle>=-135&&angle< -45)return 'B';
 return 'C';
}
function nativeCodeOrientation(ctx,loc,id,w,h){
 if(!id||!window.jsQR)return null;
 const corners=[loc.topLeftCorner,loc.topRightCorner,loc.bottomRightCorner,loc.bottomLeftCorner];
 if(corners.some(p=>!p))return null;
 const side=Math.max(Math.hypot(corners[0].x-corners[1].x,corners[0].y-corners[1].y),Math.hypot(corners[1].x-corners[2].x,corners[1].y-corners[2].y));
 const margin=Math.ceil(side*0.2);
 const x=Math.max(0,Math.floor(Math.min(...corners.map(p=>p.x))-margin)),y=Math.max(0,Math.floor(Math.min(...corners.map(p=>p.y))-margin));
 const right=Math.min(w,Math.ceil(Math.max(...corners.map(p=>p.x))+margin)),bottom=Math.min(h,Math.ceil(Math.max(...corners.map(p=>p.y))+margin));
 if(right<=x||bottom<=y)return null;
 let crop=ctx.getImageData(x,y,right-x,bottom-y);
 if(side<100){const enlarged=document.createElement('canvas');enlarged.width=crop.width*2;enlarged.height=crop.height*2;const ec=enlarged.getContext('2d',{willReadFrequently:true});ec.imageSmoothingEnabled=false;ec.drawImage(ctx.canvas,x,y,crop.width,crop.height,0,0,enlarged.width,enlarged.height);crop=ec.getImageData(0,0,enlarged.width,enlarged.height);}
 const match=window.jsQR(crop.data,crop.width,crop.height,{inversionAttempts:'dontInvert'});
 return match?.data==='CDSQ1:'+id?answerFromFinder(match.location):null;
}
let detector=null,scanHandle=0,scanPending=false,scanTicks=0,scanLast=0,scanGeneration=0;
function recordScan(id,answer,location,w,h,markers){
 if(!byId.has(id)||!answer)return;
 let center=location?.topLeftCorner,bottom=location?.bottomRightCorner;
 if(center&&bottom)markers.push({name:byId.get(id).name,answer,correct:answer===current()?.key,x:Math.min(90,Math.max(10,(center.x+bottom.x)/2/w*100)),y:Math.min(87,Math.max(15,(center.y+bottom.y)/2/h*100))});
 let prior=lastSeen.get(id),now=Date.now();
 if(prior?.answer===answer&&now-prior.time<2500){
  let q=current();if(!q||q.answers[id]===answer||pending.has(id))return;
  pending.add(id);let index=state.index;
  persistAnswer(id,answer,index).then(ok=>{
   pending.delete(id);if(!ok||state.index!==index)return;
   q.answers[id]=answer;save();render();let badge=document.createElement('span');badge.textContent=(answer===q.key?'✓ ':'✕ ')+answer+' · '+byId.get(id).name;badge.className=answer===q.key?'scan-correct':'scan-wrong';$('cameraFeed').prepend(badge);while($('cameraFeed').children.length>5)$('cameraFeed').lastChild.remove();$('cameraCount').textContent=Object.keys(q.answers).length+' / '+students.length+' đã quét';$('scanStatus').textContent=byId.get(id).name+' → '+answer+' · Đã ghi nhận';
  });
 }else lastSeen.set(id,{answer,time:now});
}
function maskCode(data,w,h,corners){
 if(corners.length<4)return;
 const x1=Math.max(0,Math.floor(Math.min(...corners.map(p=>p.x))-12)),x2=Math.min(w,Math.ceil(Math.max(...corners.map(p=>p.x))+12));
 const y1=Math.max(0,Math.floor(Math.min(...corners.map(p=>p.y))-12)),y2=Math.min(h,Math.ceil(Math.max(...corners.map(p=>p.y))+12));
 for(let y=y1;y<y2;y++)for(let x=x1;x<x2;x++){let i=(y*w+x)*4;data[i]=data[i+1]=data[i+2]=255}
}
function fallbackCodes(ctx,w,h,markers){
 let data=ctx.getImageData(0,0,w,h),found=0,start=performance.now();
 for(let n=0;n<Math.min(5,students.length);n++){
  if(n>0&&performance.now()-start>45)break;
  let code=window.jsQR?.(data.data,w,h,{inversionAttempts:'dontInvert'});if(!code)break;
  found++;let id=code.data.startsWith('CDSQ1:')?code.data.slice(6):'';
  recordScan(id,answerFromFinder(code.location),code.location,w,h,markers);
  let corners=[code.location.topLeftCorner,code.location.topRightCorner,code.location.bottomRightCorner,code.location.bottomLeftCorner].filter(Boolean);
  if(corners.length<4)break;maskCode(data.data,w,h,corners);
 }
 return found;
}
async function scanFrame(generation){
 if(!scanning||generation!==scanGeneration||(paperCode&&phase!=='scanning'))return;
 const now=performance.now();if(scanPending||now-scanLast<50){scanHandle=requestAnimationFrame(()=>scanFrame(generation));return}
 scanPending=true;scanLast=now;
 try{
  let v=$('video');if(v.readyState<2||!current())return;
  const tile=!detector&&scanTicks++%5!==0,limit=detector?2560:1280;
  let w=Math.min(limit,v.videoWidth),h=Math.round(v.videoHeight*w/v.videoWidth);if(!w||!h)return;
  let canvas=$('frame');if(canvas.width!==w||canvas.height!==h){canvas.width=w;canvas.height=h}
  let ctx=canvas.getContext('2d',{willReadFrequently:!detector}),crop=hardwareZoom?1:digitalZoom,cw=v.videoWidth/crop,ch=v.videoHeight/crop;
  let sx=(v.videoWidth-cw)/2,sy=(v.videoHeight-ch)/2;
  if(tile){const region=(scanTicks-1)%5-1;const col=Math.max(0,region)%2,row=Math.floor(Math.max(0,region)/2);sx+=col*cw*0.4;sy+=row*ch*0.4;cw*=0.6;ch*=0.6;}
  ctx.drawImage(v,sx,sy,cw,ch,0,0,w,h);
  let markers=[];
  if(detector){
   try{let codes=await detector.detect(canvas);for(let code of codes){let corners=code.cornerPoints||[];if(corners.length<4)continue;let loc={topLeftCorner:corners[0],topRightCorner:corners[1],bottomRightCorner:corners[2],bottomLeftCorner:corners[3]};let id=code.rawValue?.startsWith('CDSQ1:')?code.rawValue.slice(6):'';recordScan(id,nativeCodeOrientation(ctx,loc,id,w,h),loc,w,h,markers)}}
   catch(e){detector=null;fallbackCodes(ctx,w,h,markers)}
  }else fallbackCodes(ctx,w,h,markers);
  if(tile)markers.forEach(m=>{m.x=((sx+(m.x/100)*cw)/v.videoWidth)*100;m.y=((sy+(m.y/100)*ch)/v.videoHeight)*100;});
  if(generation===scanGeneration)$('detectedLayer').replaceChildren(...markers.map(marker=>{let el=document.createElement('span');el.textContent=(marker.correct?'✓ ':'✕ ')+marker.answer+' · '+marker.name;el.className=marker.correct?'scan-correct':'scan-wrong';el.style.left=marker.x+'%';el.style.top=marker.y+'%';return el}));
 }catch(e){$('scanStatus').textContent='Lỗi quét: '+e.message}
 finally{scanPending=false;if(scanning&&generation===scanGeneration)scanHandle=requestAnimationFrame(()=>scanFrame(generation))}
}
async function startCamera(targetIndex=null){
 if(cameraStarting||scanning)return;cameraStarting=true;
 if(!paperOpen||phase==='finished'){cameraStarting=false;mobileStatus('Lượt chơi đã kết thúc hoặc đã đóng.');return}
 if(!current()){cameraStarting=false;mobileStatus('Hãy mở câu hỏi mới trước.');return}
 if('BarcodeDetector' in window){try{detector=new BarcodeDetector({formats:['qr_code']})}catch(e){detector=null}}
 if(!window.jsQR){cameraStarting=false;mobileStatus('Không tải được thư viện quét mã. Kiểm tra kết nối mạng.');return}
 if(!navigator.mediaDevices?.getUserMedia){cameraStarting=false;mobileStatus('Camera cần HTTPS. Hãy mở trang bằng Safari hoặc Chrome và cho phép camera.');return}
 try{
  $('cameraPermission').classList.add('hidden');
  mobileStatus('Đang mở camera · hãy cho phép sử dụng camera khi trình duyệt hỏi.');
  const openingControl=(async()=>{if(paperCode){if(targetIndex!==null){await control('move',{index:String(targetIndex)});state.index=targetIndex;showCorrect=false;showGraph=false;$('answerPreview').classList.add('hidden');render();phase='question';}if(phase==='welcome')await control('start');else if(phase!=='scanning')await control('phase',{phase:'scanning'});phase='scanning';}})();openingControl.catch(()=>{});
  const opened=await window.QuizCamera.open($('cameraDevice').value);cameraStream=opened.stream;const activeId=cameraStream.getVideoTracks()[0].getSettings?.().deviceId;const choices=opened.devices.map((d,i)=>{const option=document.createElement('option');option.value=d.deviceId;option.textContent=d.label||'Camera '+(i+1);return option;});if(choices.length){$('cameraDevice').replaceChildren(...choices);$('cameraDevice').value=activeId||'';}
  await openingControl;

  let track=cameraStream.getVideoTracks()[0],caps=await window.QuizCamera.tune(track),zoom=caps?.zoom;hardwareZoom=!!zoom;digitalZoom=1;$('video').style.transform='';$('cameraZoom').disabled=!zoom;if(zoom){$('cameraZoom').min=String(zoom.min);$('cameraZoom').max=String(zoom.max);$('cameraZoom').step=String(zoom.step||0.1);$('cameraZoom').value=String(track.getSettings?.().zoom||Math.max(zoom.min,Math.min(zoom.max,1)))}else{$('cameraZoom').value='1';$('zoomValue').textContent='Thiết bị chưa hỗ trợ zoom camera';}

  $('video').srcObject=cameraStream;await $('video').play();scanning=true;lastSeen.clear();scanGeneration++;scanPending=false;if(hardwareZoom)$('zoomValue').textContent=Number($('cameraZoom').value).toFixed(1)+'×';$('cameraStage').classList.add('scanner-full');$('cameraExpand').textContent='Thu nhỏ';scanHandle=requestAnimationFrame(()=>scanFrame(scanGeneration));
  mobileStatus('Camera đang quét · giữ chữ đáp án ở cạnh trên.');$('mobileStart').textContent='Dừng quét';
 }catch(e){stopCamera();if(e.name==='NotAllowedError'||e.name==='SecurityError')$('cameraPermission').classList.remove('hidden');const reason=e.name==='NotAllowedError'?'Chưa được cấp quyền camera. Mở cài đặt quyền của trang và cho phép Camera, rồi bấm Bắt đầu lại.':e.name==='NotReadableError'?'Camera đang được ứng dụng khác sử dụng. Đóng ứng dụng đó rồi thử lại.':e.message;mobileStatus('Camera: '+reason+' · kiểm tra màn chiếu và bấm mở camera lại nếu cần.')}finally{cameraStarting=false}
}
function stopCamera(){$('mobileStart').textContent='▶ Bắt đầu · Camera';scanning=false;scanGeneration++;cancelAnimationFrame(scanHandle);scanHandle=0;$('cameraStage').classList.remove('scanner-full');cameraStream?.getTracks().forEach(t=>t.stop());cameraStream=null;$('video').srcObject=null;detector=null}
$('retryCamera').onclick=()=>startCamera();$('closeCameraPermission').onclick=()=>$('cameraPermission').classList.add('hidden');$('cameraDevice').onchange=async()=>{if(cameraStarting)return;stopCamera();await startCamera();};$('startScan').onclick=()=>startCamera();
$('cameraExpand').onclick=()=>{$('cameraStage').classList.toggle('scanner-full');$('cameraExpand').textContent=$('cameraStage').classList.contains('scanner-full')?'Thu nhỏ':'⛶ Toàn màn hình'};
$('cameraClose').onclick=()=>{$('cameraStage').classList.remove('scanner-full');$('cameraExpand').textContent='⛶ Toàn màn hình'};
$('cameraFinish').onclick=()=>$('stopScan').click();
let zoomBusy=false,zoomRequested=null;
async function setCameraZoom(value){if(!hardwareZoom||!cameraStream)return;const input=$('cameraZoom');zoomRequested=Math.max(Number(input.min),Math.min(Number(input.max),value));if(zoomBusy)return;zoomBusy=true;try{while(zoomRequested!==null){const next=zoomRequested;zoomRequested=null;await cameraStream.getVideoTracks()[0].applyConstraints({advanced:[{zoom:next}]});input.value=String(next);$('zoomValue').textContent=next.toFixed(1)+'×'}}catch(error){mobileStatus('Camera không áp dụng được mức zoom này.')}finally{zoomBusy=false}}
$('cameraZoom').oninput=e=>setCameraZoom(Number(e.target.value));
let pinchDistance=0,pinchZoom=1;
$('video').style.touchAction='none';
$('video').addEventListener('touchstart',e=>{if(e.touches.length===2&&hardwareZoom){e.preventDefault();pinchDistance=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY);pinchZoom=Number($('cameraZoom').value)}},{passive:false});
$('video').addEventListener('touchmove',e=>{if(e.touches.length===2&&pinchDistance&&hardwareZoom){e.preventDefault();const distance=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY);setCameraZoom(pinchZoom*distance/pinchDistance)}},{passive:false});
$('video').addEventListener('touchend',()=>{pinchDistance=0});

$('stopScan').onclick=async()=>{stopCamera();if(paperCode){try{await control('phase',{phase:'results'});await syncQuestion()}catch(e){$('scanStatus').textContent=e.message}}else $('scanStatus').textContent='Đã dừng quét.'};
if(paperCode){
 const move=async offset=>{if(cameraStarting)return;const index=state.index+offset;if(index<0||index>=state.questions.length)return;stopCamera();$('mobileResults').classList.remove('open');await startCamera(index);await syncQuestion()};
 $('prevRemote').onclick=()=>move(-1);$('nextRemote').onclick=()=>move(1);
 $('revealRemote').onclick=async()=>{try{await control('show_correct',{value:showCorrect?'0':'1'});await syncQuestion()}catch(e){$('scanStatus').textContent=e.message}};
 $('graphRemote').onclick=async()=>{try{await control('show_graph',{value:showGraph?'0':'1'});await syncQuestion()}catch(e){$('scanStatus').textContent=e.message}};
 syncQuestion();setInterval(syncQuestion,2500);
}
$('mobilePreview').onclick=async()=>{const q=current();if(!q||phase==='welcome'){mobileStatus('Hãy bắt đầu trước khi xem đáp án.');return}const key=q.key||'';$('previewAnswer').textContent=key+' · '+(q.choices?.[key]||'');$('previewExplanation').textContent=q.explanation?'Giải thích: '+q.explanation:'';$('answerPreview').classList.remove('hidden');if(paperCode){try{await control('show_correct',{value:'1'});await syncQuestion();mobileStatus('Đã hiện đáp án và giải thích trên màn chiếu.')}catch(e){mobileStatus(e.message)}}};$('closePreview').onclick=()=>$('answerPreview').classList.add('hidden');
$('mobileStart').onclick=()=>scanning?$('stopScan').click():startCamera();
$('mobileMissingToggle').onclick=()=>{const open=$('mobileMissing').classList.toggle('open');$('mobileMissingToggle').setAttribute('aria-expanded',String(open));$('mobileMissingToggle').textContent=(open?'✕ Đóng danh sách':'👥 Xem học sinh chưa quét')+' · '+(current()?students.filter(student=>!current().answers?.[student.id]).length:students.length)};
$('mobilePrev').onclick=()=>paperCode&&$('prevRemote').click();
$('mobileNext').onclick=()=>paperCode&&$('nextRemote').click();
$('mobileFinish').onclick=async()=>{if(!paperCode||!confirm('Kết thúc lượt chơi và công bố bảng xếp hạng trên màn chiếu?'))return;try{stopCamera();await control('finish');await syncQuestion();mobileStatus('🎉 Đã công bố bảng xếp hạng trên màn chiếu.')}catch(e){mobileStatus(e.message)}};
$('mobilePublish').onclick=async()=>{
 if(!current()){mobileStatus('Chưa có câu hỏi để công bố.');return}
 stopCamera();
 try{
  if(paperCode){await control('phase',{phase:'results'});await control('show_correct',{value:'1'});await control('show_graph',{value:'1'});await syncQuestion()}
  renderMobileResults();$('mobileResults').classList.add('open');
 }catch(e){mobileStatus('Chưa công bố được: '+e.message)}
};
$('mobileResultsClose').onclick=()=>{$('mobileResults').classList.remove('open');mobileStatus('Kết quả đã công bố · có thể chuyển câu tiếp theo.')};
let lastInteraction=0;
document.addEventListener('pointerdown',()=>{if(!paperCode||!paperOpen||phase==='finished'||Date.now()-lastInteraction<60000)return;lastInteraction=Date.now();control('activity').catch(error=>mobileStatus(error.message));});
window.addEventListener('pagehide',stopCamera);render();
</script><?php endif; ?></main><?php require __DIR__ . '/includes/game_credit.php'; ?></body></html>



