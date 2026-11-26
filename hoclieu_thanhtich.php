<?php
require_once __DIR__.'/includes/auth.php';
require_login();
require_once __DIR__.'/includes/csdl_store.php';
require_once __DIR__.'/includes/achievement_board.php';
header('Cache-Control: private, no-store');
$abUser=current_user();
if(!ab_feature($abUser,'view')){http_response_code(403);exit('Bạn chưa được cấp quyền Bảng thành tích. Quản trị viên cấp quyền tại Tài khoản và quyền → Học liệu và thi → Bảng thành tích.');}
function ab_context(): array {
    $ctx=['classes'=>[],'students'=>[],'subjects'=>[],'years'=>[],'users'=>[]];$classIds=[];
    foreach(csdl_classes_all() as $c){if(!is_array($c)||(isset($c['active'])&&!$c['active']))continue;$name=trim((string)($c['name']??''));if($name!==''){$ctx['classes'][$name]=$name;$classIds[(string)($c['id']??'')]=$name;}}
    uksort($ctx['classes'],'csdl_compare_class_names');
    foreach(csdl_students_all() as $s){if(!is_array($s)||(isset($s['active'])&&!$s['active']))continue;$id=(string)($s['id']??'');$class=trim((string)($s['class_name']??$s['class']??$s['lop']??''));if($class==='')$class=$classIds[(string)($s['class_id']??'')]??'';if($id!==''&&isset($ctx['classes'][$class]))$ctx['students'][$id]=['id'=>$id,'name'=>(string)($s['name']??$s['ho_ten']??''),'class'=>$class];}
    $subjects=load_json(CSDL_SUBJECTS,[]);
    if(!$subjects&&defined('PCCM_DATA_PATH')&&PCCM_DATA_PATH!=='')$subjects=load_json(PCCM_DATA_PATH.'/subjects.json',[]);
    foreach($subjects as $s){$name=is_string($s)?$s:(string)($s['name']??$s['subject']??'');if(trim($name)!=='')$ctx['subjects'][]=trim($name);}
    if(!$ctx['subjects'])$ctx['subjects']=['Toán học','Ngữ văn','Ngoại ngữ','Vật lí','Hóa học','Sinh học','Lịch sử','Địa lí','KHTN','LS&ĐL','GDCD','GDKT&PL','Tin học','Công nghệ','GDTC','GDQP&AN','Nghệ thuật','GDĐP','HĐTN-HN'];
    $ctx['subjects']=array_values(array_unique($ctx['subjects']));
    foreach(csdl_years_all() as $y)if(!empty($y['id']))$ctx['years'][(string)$y['id']]=['id'=>(string)$y['id'],'label'=>(string)($y['label']??$y['name']??$y['id']),'start'=>(string)($y['start']??''),'end'=>(string)($y['end']??''),'current'=>!empty($y['is_current'])];
    foreach(load_json(USERS_FILE,[]) as $u)if(is_array($u)&&!empty($u['id'])&&($u['role']??'')!=='viewer'&&(!array_key_exists('active',$u)||!empty($u['active'])))$ctx['users'][(string)$u['id']]=['id'=>(string)$u['id'],'name'=>(string)($u['name']??$u['username']??$u['id'])];
    return $ctx;
}
function ab_boot(array $s,array $u,array $ctx): array {
    $pairs=[];foreach($ctx['classes'] as $class)foreach($ctx['subjects'] as $subject)if(ab_scope_allowed($s,$u,$class,$subject))$pairs[]=['class'=>$class,'subject'=>$subject,'edit'=>ab_scope_allowed($s,$u,$class,$subject,'edit')];
    return ['csrf'=>ab_csrf(),'admin'=>ab_admin($u),'actor'=>ab_actor($u),'today'=>ab_now()->format('Y-m-d'),'pairs'=>$pairs,'years'=>array_values($ctx['years']),'users'=>ab_admin($u)?array_values($ctx['users']):[],'grants'=>ab_admin($u)?$s['grants']:[],'classes'=>ab_admin($u)?array_values($ctx['classes']):[],'subjects'=>ab_admin($u)?$ctx['subjects']:[]];
}
try{
    $ctx=ab_context();$s=ab_load();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        header('Content-Type: application/json; charset=utf-8');
        $input=json_decode((string)file_get_contents('php://input'),true);
        if(!is_array($input))throw new RuntimeException('Dữ liệu gửi lên không hợp lệ.');
        ab_save($input,$abUser,$ctx);echo json_encode(['ok'=>true],JSON_UNESCAPED_UNICODE);exit;
    }
    $api=is_string($_GET['api']??null)?$_GET['api']:'';
    if($api==='boot'){header('Content-Type: application/json; charset=utf-8');echo json_encode(ab_boot($s,$abUser,$ctx),JSON_UNESCAPED_UNICODE);exit;}
    if(in_array($api,['data','csv'],true)){
        $class=ab_text($_GET,'class',100);$subject=ab_text($_GET,'subject',150);$year=ab_text($_GET,'year',100);
        if(!isset($ctx['classes'][$class])||!in_array($subject,$ctx['subjects'],true)||!isset($ctx['years'][$year])||!ab_scope_allowed($s,$abUser,$class,$subject))throw new RuntimeException('Không có quyền xem lớp, môn hoặc năm học này.');
        [$from,$to]=ab_range($_GET);$key=ab_key($year,$class,$subject);$s=ab_load($key);if(!ab_scope_allowed($s,$abUser,$class,$subject))throw new RuntimeException('Quyền lớp–môn đã thay đổi. Hãy tải lại trang.');$students=array_filter($ctx['students'],fn($r)=>$r['class']===$class);$report=ab_report($s,$students,$key,$from,$to);
        foreach($report['history'] as &$event)$event['can_undo']=empty($event['voided_at'])&&ab_scope_allowed($s,$abUser,$class,$subject,'edit')&&(ab_admin($abUser)||$event['by']===ab_actor($abUser)||ab_feature($abUser,'delete'));unset($event);
        if($api==='csv'){
            header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="bang-thanh-tich.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Hạng','Học sinh','Lớp','Môn','Năm học','Từ ngày','Đến ngày','Điểm thưởng','Điểm phạt','Tổng điểm'],',','"','');
            foreach($report['scores'] as $r){$cells=[$r['rank'],$r['name'],$class,$subject,$ctx['years'][$year]['label'],$from,$to,$r['reward'],$r['penalty'],$r['total']];foreach($cells as &$v)if(is_string($v)&&preg_match('/^[=+\-@\t\r]/',$v))$v="'".$v;unset($v);fputcsv($out,$cells,',','"','');}fclose($out);exit;
        }
        header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'from'=>$from,'to'=>$to,'edit'=>ab_scope_allowed($s,$abUser,$class,$subject,'edit'),'criteria'=>ab_criteria($s,$key),'report'=>$report],JSON_UNESCAPED_UNICODE);exit;
    }
    if($api!=='')throw new RuntimeException('Yêu cầu không hợp lệ.');
    $boot=ab_boot($s,$abUser,$ctx);
}catch(Throwable $e){http_response_code(400);if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'||isset($_GET['api'])){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Không thể tải Bảng thành tích.'],JSON_UNESCAPED_UNICODE);}else echo '<!doctype html><meta charset="utf-8"><p>'.e($e instanceof RuntimeException?$e->getMessage():'Không thể tải Bảng thành tích.').'</p>';exit;}
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#5165dc"><title>Bảng thành tích · CDS</title><link rel="stylesheet" href="<?=e(BASE_URL)?>assets/achievement-board.css?v=1"></head><body>
<header class="ab-header"><a href="<?=e(BASE_URL)?>hoclieu.php?tab=games">← Tiện ích</a><div class="ab-brand"><span>🏆</span><div><h1>Bảng thành tích</h1><p>Mỗi nỗ lực nhỏ, một bước tiến lớn</p></div></div><button id="abSound" class="ab-button ab-light" type="button" aria-pressed="false">🔇 Âm thanh</button></header>
<main class="ab-shell"><section class="ab-filters" aria-label="Chọn bảng và kỳ thống kê"><label>Năm học<select id="abYear"></select></label><label>Lớp học<select id="abClass"></select></label><label>Môn học<select id="abSubject"></select></label><label>Thống kê<select id="abPeriod"><option value="week">Theo tuần</option><option value="month">Theo tháng</option><option value="range">Theo giai đoạn</option></select></label><label id="abAnchorLabel">Ngày trong kỳ<input type="date" id="abAnchor" value="<?=e($boot['today'])?>"></label><label class="ab-range" hidden>Từ ngày<input type="date" id="abFrom" value="<?=e($boot['today'])?>"></label><label class="ab-range" hidden>Đến ngày<input type="date" id="abTo" value="<?=e($boot['today'])?>"></label><button id="abRefresh" class="ab-button" type="button">Xem bảng</button></section>
<div id="abError" class="ab-error" role="alert" hidden></div><section class="ab-hero"><div><span class="ab-eyebrow">CÙNG NHAU TIẾN BỘ</span><h2 id="abBoardTitle">Chào mừng đến lớp học tích cực!</h2><p id="abRangeText">Chọn lớp và môn để bắt đầu.</p></div><div class="ab-hero-art" aria-hidden="true"><span>🌟</span><b>🏆</b><span>✨</span></div></section>
<div id="abNoScope" class="ab-empty" hidden><span>🗝️</span><h2>Chưa có bảng được phân công</h2><p>Quản trị viên cấp quyền Bảng thành tích trong Tài khoản và quyền, rồi chọn lớp–môn ở thẻ Phân quyền bên dưới.</p></div>
<div class="ab-stats" id="abStats"><article><span>Học sinh</span><b id="abCount">0</b><i>👥</i></article><article><span>Điểm thưởng</span><b id="abReward">0</b><i>⭐</i></article><article><span>Điểm phạt</span><b id="abPenalty">0</b><i>🎯</i></article><article><span>Tổng điểm</span><b id="abTotal">0</b><i>🚀</i></article></div>
<nav class="ab-tabs" aria-label="Chế độ xem"><button data-tab="classroom" class="active" type="button">🌈 Lớp học</button><button data-tab="ranking" type="button">🏅 Bảng xếp hạng</button><button data-tab="history" type="button">🕒 Lịch sử điểm</button><button data-tab="criteria" type="button">✨ Tiêu chí</button><?php if($boot['admin']):?><button data-tab="settings" type="button">🔑 Phân quyền</button><?php endif;?></nav>
<section id="ab-classroom" class="ab-panel"><div class="ab-toolbar"><label class="ab-search">🔎 <input id="abSearch" type="search" placeholder="Tìm học sinh…" aria-label="Tìm học sinh"></label><div><button id="abSelectAll" class="ab-button ab-quiet" type="button">Chọn cả lớp</button><button id="abClear" class="ab-button ab-quiet" type="button">Bỏ chọn</button><button id="abAward" class="ab-button" type="button">✨ Ghi điểm <span id="abSelected">0</span></button></div></div><p class="ab-help">Chọn thẻ học sinh để ghi điểm. Có thể chọn nhiều bạn cùng lúc.</p><div id="abStudents" class="ab-students"></div></section>
<section id="ab-ranking" class="ab-panel" hidden><div class="ab-toolbar"><h2>Những ngôi sao của lớp</h2><button id="abExport" class="ab-button ab-quiet" type="button">↓ Tải bảng điểm CSV</button></div><div id="abPodium" class="ab-podium"></div><div class="ab-table-scroll"><table><thead><tr><th>Hạng</th><th>Học sinh</th><th>Thưởng</th><th>Phạt</th><th>Tổng</th></tr></thead><tbody id="abRanking"></tbody></table></div><h3>Nhịp tiến bộ theo ngày</h3><div id="abTrend" class="ab-trend"></div></section>
<section id="ab-history" class="ab-panel" hidden><h2>Nhật ký ghi điểm</h2><p class="ab-help">Hoàn tác giữ lại lịch sử và loại lượt điểm đó khỏi thống kê.</p><div id="abHistory" class="ab-history"></div></section>
<section id="ab-criteria" class="ab-panel" hidden><h2>Tiêu chí của lớp–môn</h2><div id="abCriteriaList" class="ab-skills"></div><form id="abCriterionForm" class="ab-form"><h3>Thêm tiêu chí</h3><label>Tên tiêu chí<input name="name" maxlength="60" required placeholder="Ví dụ: Chuẩn bị bài tốt"></label><label>Biểu tượng<input name="icon" maxlength="8" value="🌟" required></label><label>Điểm (+ thưởng / − phạt)<input name="points" type="number" min="-100" max="100" value="1" required></label><button class="ab-button">Lưu tiêu chí</button></form></section>
<?php if($boot['admin']):?><section id="ab-settings" class="ab-panel" hidden><h2>Phân quyền lớp–môn</h2><p class="ab-help">Cấp quyền <strong>Bảng thành tích</strong> mức Xem/Sửa/Xóa tại <a href="<?=e(BASE_URL)?>users.php">Tài khoản và quyền</a>. Sau đó gán lớp–môn dưới đây. Quyền Xóa cho phép hoàn tác điểm của giáo viên khác; quyền Sửa chỉ hoàn tác lượt điểm của chính mình.</p><form id="abGrantForm" class="ab-form"><label>Tài khoản<select name="user_id" id="abGrantUser" required></select></label><label>Lớp<select name="class" id="abGrantClass" required></select></label><label>Môn<select name="subject" id="abGrantSubject" required></select></label><button class="ab-button">Gán lớp–môn</button></form><div id="abGrants" class="ab-history"></div></section><?php endif;?>
</main>
<dialog id="abAwardDialog" aria-labelledby="abAwardTitle"><form method="dialog" class="ab-dialog-head"><h2 id="abAwardTitle">Ghi nhận một bước tiến ✨</h2><button class="ab-close" aria-label="Đóng">×</button></form><p id="abAwardNames"></p><div class="ab-award-options"><label>Ngày ghi điểm<input type="date" id="abAwardDate" value="<?=e($boot['today'])?>" max="<?=e($boot['today'])?>"></label><label>Ghi chú<input id="abAwardNote" maxlength="300" placeholder="Không bắt buộc"></label></div><div id="abAwardSkills" class="ab-skills"></div><p class="ab-help">Chọn tiêu chí để ghi điểm cho những học sinh đã chọn.</p></dialog>
<div id="abToast" role="status" aria-live="polite"></div><div id="abConfetti" aria-hidden="true"></div>
<script type="application/json" id="abBoot"><?=json_encode($boot,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="<?=e(BASE_URL)?>assets/achievement-board.js?v=1" defer></script></body></html>
