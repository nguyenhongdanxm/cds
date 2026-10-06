<?php
require_once __DIR__.'/includes/auth.php';
require_login();
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
    $rosters[$class][]=['id'=>$id,'name'=>$name];
}
uksort($rosters,'csdl_compare_class_names');
foreach ($rosters as &$students) usort($students,static function($a,$b){return csdl_compare_person_names($a['name'],$b['name']);});
unset($students);
$base=defined('BASE_URL')?BASE_URL:'/';
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Thả bóng gọi tên · CDS</title><link rel="stylesheet" href="<?=e($base)?>assets/cds-ball.css?v=20261006-2"></head><body>
<header class="top"><a href="<?=e($base)?>hoclieu.php?tab=games" class="back">← Học liệu</a><div class="brand"><span aria-hidden="true"><svg width="34" height="34" viewBox="0 0 40 40"><circle cx="20" cy="20" r="17" fill="#17233f" stroke="#bbccff" stroke-width="2"/><circle cx="20" cy="19" r="8" fill="#ffdf93"/><text x="20" y="24" text-anchor="middle" fill="#24324d" font-size="14" font-family="system-ui" font-weight="900">8</text><path d="M9 13q2-5 7-6" stroke="white" fill="none" stroke-width="2" stroke-linecap="round" opacity=".7"/></svg></span><div><h1>Thả bóng gọi tên</h1><small>Một quả bóng · Những bất ngờ</small></div></div><button id="fullscreen" type="button">⛶ Trình chiếu</button></header>
<main class="layout"><section class="arena" aria-label="Mê cung thả bóng"><div class="stage-top"><span class="badge" id="classBadge">Chọn lớp để bắt đầu</span><span id="status" role="status" aria-live="polite">Ai sẽ là người tiếp theo?</span></div><div class="board"><canvas id="maze" width="900" height="550" aria-label="Quả bóng chạy qua các ngã rẽ và đường ống"></canvas><div class="countdown" id="countdown" hidden></div><div class="winner" id="winner" hidden><div class="winner-spark" aria-hidden="true">✦ ✧ ✦</div><strong id="winnerName"></strong></div><div class="slots" id="slots"></div></div><div class="playbar"><button id="drop" class="drop" type="button" disabled>🎱 Thả bóng!</button><button id="sound" type="button" aria-pressed="true">🔊 Âm thanh</button><button id="next" type="button" hidden>↻ Lượt tiếp</button></div><div class="history-wrap"><span>Đã gọi</span><div id="history" class="history"></div></div><p id="message" class="message" role="status"></p></section>
<aside class="settings"><details id="rosterPanel" open><summary>🎒 Lớp & học sinh <span id="selectedCount">0</span></summary><div class="settings-content"><label class="field">Chọn lớp<select id="classSelect"><option value="">— Chọn lớp —</option><?php foreach($rosters as $name=>$students):?><option value="<?=e($name)?>"><?=e($name)?> · <?=count($students)?> HS</option><?php endforeach;?></select></label><div class="options"><label><input id="noRepeat" type="checkbox" checked> Không gọi lại em đã được chọn</label><label class="field">Nhịp thả bóng<select id="speed"><option value="8">⚡ Nhanh · 8 giây</option><option value="12" selected>✨ Vừa · 12 giây</option><option value="17">🎬 Hồi hộp · 17 giây</option></select></label></div><div class="list-tools"><button id="selectAll" type="button">Tích tất cả</button><button id="selectNone" type="button">Bỏ tích</button><button id="resetCalled" type="button">↻ Cho phép gọi lại</button></div><label class="search-label">Tìm học sinh<input id="search" type="search" placeholder="Tên hoặc số thứ tự…" autocomplete="off"></label><div id="students" class="student-list"></div><p class="help">Bỏ tích học sinh vắng. Mỗi em được tích chọn có cơ hội như nhau. Tên ở các ô đích cuộn và đổi chỗ khi bóng chạy, rồi ổn định ở chặng cuối.</p></div></details></aside></main>
<button id="exitFullscreen" class="exit-fullscreen" type="button" hidden>⛶ Thoát</button><canvas id="confetti" aria-hidden="true"></canvas>
<script id="rosterData" type="application/json"><?=json_encode($rosters,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="<?=e($base)?>assets/cds-ball-engine.js?v=20261006-2"></script><script src="<?=e($base)?>assets/cds-ball.js?v=20261006-2"></script><?php require __DIR__.'/includes/game_credit.php';?></body></html>
