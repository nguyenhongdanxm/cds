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
$gameUser=current_user()??[];
$gameUserKey=hash('sha256',(string)($gameUser['id']??$gameUser['username']??'user'));
$game=($_GET['game']??'egg')==='ufo'?'ufo':'egg';
$title=$game==='ufo'?'UFO – Gọi tên học sinh':'Trộm trứng – Gọi tên học sinh';
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · CDS</title><link rel="stylesheet" href="<?=e(BASE_URL)?>assets/goi-ten/games.css?v=1"></head><body data-game="<?=e($game)?>" data-user="<?=e($gameUserKey)?>">
<div class="wrap">
  <header>
    <a class="back" href="<?=e(BASE_URL)?>hoclieu.php?tab=games">← Tiện ích gọi tên</a><h1><?=e($title)?></h1>
    <p class="sub">Chọn lớp, kiểm tra danh sách học sinh và bắt đầu gọi tên.</p>
  </header>

  <div class="picker" id="picker" role="group" aria-label="Chọn trò chơi"></div>

  <section class="card">
    <div class="stagewrap" id="stagewrap">
      <canvas id="game" width="1000" height="600" aria-label="Màn hình trò chơi"></canvas>
      <div class="banner" id="banner" hidden><div class="box"><div class="lbl" id="bLbl"></div><div class="nm" id="bName"></div><div class="stamp" id="bStamp" hidden>NHẦM RỒI!</div><div class="ch" id="bSub" hidden></div><div class="tm" id="bTimer" hidden></div></div></div>
    </div>
    <div class="bar">
      <div class="count" id="count">—</div>
      <button class="go" id="go">Bắt đầu!</button>
    </div>
    <div class="opts">
      <label><input type="checkbox" id="noRepeat" checked> Bạn đã được gọi thì nghỉ lượt sau</label>
      <label><input type="checkbox" id="mix"> Trộn vị trí mỗi lượt</label>
      <label><input type="checkbox" id="twist"> Lật kèo: tưởng bắt được rồi nhưng cuối cùng là người khác</label>
      <label><input type="checkbox" id="sound" checked> Âm thanh</label>
      <button type="button" class="small" id="testSound">🔊 Thử âm thanh</button>
      <button type="button" class="small" id="fs" hidden>Toàn màn hình</button>
    </div>
    <div class="opts"><label>Nhạc nền <input id="musicVolume" type="range" min="0" max="100" step="1" value="20" aria-label="Âm lượng nhạc nền"></label><label>Hiệu ứng <input id="effectsVolume" type="range" min="0" max="100" step="1" value="55" aria-label="Âm lượng hiệu ứng"></label></div>
    <details class="audio-credits"><summary>Nguồn âm thanh</summary><p>Tiếng gà và giọng người: Breviceps, tonsil5 (Freesound), Joseph SARDIN (BigSoundBank), CC0 1.0. Nhạc Trộm trứng: Sneaky Snitch và Monkeys Spinning Monkeys — Kevin MacLeod (incompetech.com), <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>. Đã cắt 30 giây, chuẩn hóa mức âm và thêm fade. <a href="https://incompetech.com/music/royalty-free/index.html?isrc=USUAN1100772" target="_blank" rel="noopener">Nguồn Sneaky Snitch</a> · <a href="https://incompetech.com/music/royalty-free/index.html?isrc=USUAN1400011" target="_blank" rel="noopener">Nguồn Monkeys Spinning Monkeys</a>. Nhạc không gian và tiếng tia hút UFO: âm thanh tổng hợp được tạo cho CDS.</p></details>
    <div class="timeset">
      <div class="trow">
        <span class="tl">Thời gian chọn <output id="durOut">20 giây</output></span>
        <input type="range" id="durRange" min="10" max="30" step="1" value="20" aria-label="Thời gian chọn mỗi lượt">
        <span class="hint">Trong lúc chờ sẽ có vài lần "chọn hụt rồi nhả ra". Người được gọi thật chỉ lộ diện ở khoảng 2 giây cuối.</span>
      </div>
      <p class="hint">Không nghe tiếng? Bấm "Thử âm thanh", tăng âm lượng máy, và trên iPhone/iPad hãy gạt tắt chế độ im lặng.</p>
    </div>
  </section>

  <section class="card" id="participants">
    <label class="roster-label">Chọn lớp<select id="classSelect"><option value="">— Nhập danh sách riêng —</option><?php foreach($rosters as $class=>$students):?><option value="<?=e($class)?>"><?=e($class)?> · <?=count($students)?> học sinh</option><?php endforeach;?></select></label><p class="hint" id="excludeHint"></p>
    <h2 class="step">Người chơi <small id="total"></small></h2>
    <div class="modes" role="radiogroup" aria-label="Cách nhập người chơi">
      <label><input type="radio" name="mode" id="modeNum" value="num"> Dùng số thứ tự</label>
      <label><input type="radio" name="mode" id="modeName" value="name"> Dùng tên</label>
    </div>
    <div id="numBox" class="numbox">
      <div class="numhead"><span>Số học sinh</span><output id="numOut">20</output></div>
      <input type="range" id="numRange" min="2" max="50" value="20" aria-label="Số học sinh">
      <div class="numstep"><button type="button" class="small" id="numMinus">− 1</button><span class="hint">Kéo để chọn từ 2 đến 50 bạn, mỗi bạn mang một số.</span><button type="button" class="small" id="numPlus">+ 1</button></div>
    </div>
    <div id="nameBox" class="namebox">
      <form class="addrow" id="addForm">
        <input id="newName" type="text" placeholder="Gõ một tên rồi bấm Thêm" autocomplete="off" aria-label="Tên học sinh">
        <button type="submit" class="add">Thêm</button>
      </form>
      <textarea id="list" spellcheck="false" aria-label="Danh sách tên, mỗi dòng một tên"></textarea>
      <div class="bar">
        <p class="hint">Mỗi dòng một tên, có thể dán cả danh sách lớp. Xóa dòng học sinh vắng trước khi bắt đầu.</p>
        <button type="button" class="small" id="clearList">Xoá hết tên</button>
      </div>
    </div>
  </section>

  <section class="card">
    <h2 class="step">Đã được gọi <button class="small" id="reset">Làm lại từ đầu</button></h2>
    <ol id="history"><li class="empty">Chưa có ai.</li></ol>
  </section>
</div>




<script id="rosterData" type="application/json"><?=json_encode($rosters,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="<?=e(BASE_URL)?>assets/goi-ten/assets.js?v=1"></script><script src="<?=e(BASE_URL)?>assets/goi-ten/audio.js?v=2"></script><script src="<?=e(BASE_URL)?>assets/goi-ten/games.js?v=2"></script></body></html>
