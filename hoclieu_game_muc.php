<?php
require_once __DIR__.'/includes/auth.php';
require_login();
header('Cache-Control: no-store, private');
if (!can_perm_level('hl.xem','view') && (current_user()['role'] ?? '') !== 'admin') { http_response_code(403); exit('Không có quyền xem học liệu.'); }
require_once __DIR__.'/includes/csdl_store.php';
$classMap=[]; $rosters=[];
foreach (csdl_classes_all() as $c) {
    if (!is_array($c) || (array_key_exists('active',$c) && empty($c['active']))) continue;
    $name=trim((string)($c['name']??''));
    if ($name!=='') { $classMap[(string)($c['id']??'')]=$name; $rosters[$name]=[]; }
}
foreach (csdl_students_all() as $index=>$s) {
    if (!is_array($s) || (array_key_exists('active',$s) && empty($s['active']))) continue;
    $name=trim((string)($s['name']??$s['ho_ten']??''));
    $class=trim((string)($s['class_name']??$s['class']??$s['lop']??''));
    if ($class==='' && !empty($s['class_id'])) $class=$classMap[(string)$s['class_id']]??'';
    if ($name==='' || $class==='') continue;
    $id=trim((string)($s['id']??$s['student_id']??$s['code']??''));
    if ($id==='') $id='row_'.$index;
    $rosters[$class][]=['id'=>$id,'name'=>$name,'gender'=>(string)($s['gender']??$s['gioi_tinh']??'')];
}
uksort($rosters,'csdl_compare_class_names');
foreach ($rosters as &$students) usort($students,static function($a,$b){return csdl_compare_person_names($a['name'],$b['name']);});
unset($students);
$gameUser=current_user() ?? [];
$gameUserKey=hash('sha256',(string)($gameUser['id'] ?? $gameUser['username'] ?? 'user'));
$base=defined('BASE_URL')?BASE_URL:'/';
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mực ơi đừng dậy! · CDS</title><link rel="stylesheet" href="<?=e($base)?>assets/cds-muc.css?v=20261108-5"></head>
<body data-user-key="<?=e($gameUserKey)?>">
<header class="muc-header"><a href="<?=e($base)?>hoclieu.php?tab=games" class="back">← Trò chơi</a><div><h1>Mực ơi đừng dậy!</h1><p>Đi nhẹ, nói khẽ… ai sẽ được Mực gọi tên?</p></div><button id="fullscreen" type="button">⛶ Toàn màn hình</button></header>
<main class="muc-layout"><section class="arena" aria-label="Sân chơi Mực ơi đừng dậy">
<div class="stage" id="stage"><canvas id="scene" width="1600" height="900" aria-label="Hoạt hình học sinh rón rén qua chó Mực rồi chạy trốn. Kết quả được thông báo bằng văn bản bên dưới."></canvas>
<div class="scene-top"><span class="class-badge" id="classBadge">Sân trường thân quen</span><div class="scene-controls"><button id="sound" type="button" aria-pressed="true" aria-label="Bật tắt âm thanh">♫ Âm thanh</button><button id="rosterNames" type="button" aria-controls="nameWall" aria-expanded="false" hidden>Đủ họ tên</button><button id="pause" type="button" disabled>Tạm dừng</button><button id="end" type="button" disabled>Kết thúc lượt</button><button id="exitFullscreen" type="button" hidden>⛶ Thoát</button></div></div>
<div class="scene-caption" id="caption" hidden>Suỵt… Mực đang ngủ!</div>
<div class="intro" id="intro"><span class="eyebrow">TRÒ CHƠI GỌI TÊN HỌC SINH</span><h2>Mực ơi<br><em>đừng dậy!</em></h2><p>Chọn lớp, cùng rón rén đi qua Mực.<br>Ai sẽ được gọi tên trong cuộc rượt đuổi?</p><button id="startScene" type="button" disabled>▶ Bắt đầu chơi</button><small id="assetStatus" role="status">Đang chuẩn bị sân trường…</small></div>
<button id="startFloating" class="floating-start" type="button" hidden>▶ Bắt đầu chơi</button>
<div class="pause-overlay" id="pauseOverlay" hidden><strong>Suỵt… nghỉ một chút!</strong><button id="resume" type="button">▶ Tiếp tục</button><button id="stopPaused" type="button">Kết thúc lượt này</button></div>
<div class="result" id="result" hidden><span>MỰC ĐÃ CHỌN</span><h2 id="winnerName"></h2><p id="winnerClass"></p><p id="remaining"></p><div><button id="next" type="button">▶ Lượt tiếp theo</button><button id="closeResult" class="secondary" type="button">Xem sân trường</button></div></div>
<div class="scene-bottom"><span id="sceneCount">Chọn lớp để các bạn vào sân</span><span>CDS · Sân trường vui vẻ</span></div></div>
<div class="playbar"><button class="primary" id="start" type="button" disabled>▶ Bắt đầu chơi</button><button id="showResult" type="button" hidden>Xem kết quả</button><p id="status" role="status" aria-live="polite">Chọn lớp trong danh sách học sinh để bắt đầu.</p></div>
<details class="name-wall" id="nameWall" open><summary>Đủ họ tên học sinh trên sân (<span id="castCount">0</span>)</summary><div id="castNames" class="cast-names"></div></details>
<div class="history-wrap"><strong>Đã được gọi</strong><div id="history" class="history"></div></div>
</section>
<aside class="settings"><details id="rosterPanel" open><summary>Danh sách học sinh <span id="selectedCount">0</span></summary><div class="settings-body"><label class="field">Chọn lớp<select id="classSelect"><option value="">— Chọn lớp —</option><?php foreach($rosters as $name=>$students):?><option value="<?=e($name)?>"><?=e($name)?> · <?=count($students)?> học sinh</option><?php endforeach;?></select></label><div class="options"><label><input id="noRepeat" type="checkbox" checked> Không gọi lại em đã được chọn</label><label class="field">Thời gian mỗi lượt<select id="duration"><option value="24">Nhanh · 24 giây</option><option value="32" selected>Vui nhộn · 32 giây</option><option value="44">Hồi hộp · 44 giây</option></select></label></div><button id="testBark" class="test-bark" type="button">🔊 Thử tiếng chó</button><p class="help" id="audioStatus" role="status">Âm thanh phát sau khi bấm Bắt đầu hoặc Thử tiếng chó.</p><label class="voice-option"><input id="voiceOn" type="checkbox" checked> Lời thoại tiếng Việt</label><button id="testVoice" class="test-bark" type="button">🗣 Thử lời thoại</button><p class="help" id="voiceStatus" role="status">Đang kiểm tra giọng đọc tiếng Việt…</p><div class="list-tools"><button id="selectAll" type="button">Chọn tất cả</button><button id="selectNone" type="button">Bỏ chọn</button><button id="reset" type="button">↻ Vòng mới</button></div><label class="field">Tìm học sinh<input id="search" type="search" placeholder="Nhập tên hoặc số thứ tự" autocomplete="off"></label><div id="students" class="student-list"></div><p class="help">Bỏ chọn học sinh vắng. Mỗi bạn được chọn có cơ hội như nhau. Hiện đủ nhân vật và họ tên học sinh được tích chọn; sân chơi giữ khung 16:9 với đội hình xếp tầng trước sau. Mực thức giấc và rượt đuổi tự động; thầy cô không cần điều khiển nhân vật.</p><p class="help">Lịch sử được lưu riêng theo tài khoản và lớp trên trình duyệt này. Kết thúc giữa lượt sẽ không tính là đã gọi.</p></div></details></aside></main>
<script id="rosterData" type="application/json"><?=json_encode($rosters,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="<?=e($base)?>assets/cds-muc-engine.js?v=20261108-5"></script><script src="<?=e($base)?>assets/cds-muc-motion.js?v=20261108-5"></script><script src="<?=e($base)?>assets/cds-muc-voice.js?v=20261108-5"></script><script src="<?=e($base)?>assets/cds-muc-audio.js?v=20261108-5"></script><script src="<?=e($base)?>assets/cds-muc-scene.js?v=20261108-5"></script><script src="<?=e($base)?>assets/cds-muc.js?v=20261108-5"></script><?php require __DIR__.'/includes/game_credit.php';?></body></html>
