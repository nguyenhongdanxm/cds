<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/modules.php';
require_once __DIR__ . '/includes/voice_command_catalog.php';
require_login();
$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
if (!function_exists('cds_voice_csrf_token')) {
    function cds_voice_csrf_token(): string {
        if (empty($_SESSION['cds_voice_csrf'])) $_SESSION['cds_voice_csrf'] = bin2hex(random_bytes(24));
        return (string)$_SESSION['cds_voice_csrf'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_voice_commands'])) {
    if (!$isAdmin) {
        http_response_code(403);
        exit('Chỉ quản trị hệ thống được thay đổi bộ lệnh.');
    }
    if (!hash_equals(cds_voice_csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Phiên làm việc không hợp lệ.');
    }
    try {
        if (!cds_voice_save_commands((array)($_POST['commands'] ?? []))) {
            error_log('CDS voice command configuration could not be saved.');
            flash('Không lưu được bộ lệnh. Vui lòng thử lại.', 'danger');
        } else {
            flash('Đã lưu bộ lệnh giọng nói.', 'success');
        }
    } catch (Throwable $error) {
        flash($error->getMessage(), 'danger');
    }
    header('Location: ' . BASE_URL . 'voice-commands-help.php');
    exit;
}
$commands = cds_voice_commands_for_user((array)$user);
$modulesForGuide = [];
foreach (get_ecosystem_modules() as $module) {
    if (($module['status'] ?? '') === 'soon') continue;
    if ($isAdmin || ($module['status'] ?? '') === 'link' || can_module((string)($module['id'] ?? ''), 'view')) {
        $modulesForGuide[] = $module;
    }
}
$voiceTargets = array_map(static function (array $command): array {
    return [
        'name'=>(string)$command['name'],
        'url'=>(string)$command['url'],
        'keywords'=>is_array($command['keywords'] ?? null) ? $command['keywords'] : [],
        'category'=>(string)($command['category'] ?? ''),
    ];
}, $commands);
$adminCommands = $isAdmin ? cds_voice_load_commands() : [];
$categories = array_values(array_unique(array_column($commands, 'category')));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Câu lệnh giọng nói – CDS</title>
  <style>
    :root{font-family:"Be Vietnam Pro",Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#19324f;background:#f3f7fc}
    *{box-sizing:border-box}body{margin:0;padding:clamp(14px,4vw,40px)}
    main{max-width:1100px;margin:auto}.hero{padding:clamp(22px,5vw,42px);border-radius:24px;color:#fff;background:linear-gradient(120deg,#173b68,#2563eb 58%,#12aaa6);box-shadow:0 18px 42px #143f702b}
    h1{margin:0 0 8px;font-size:clamp(25px,4vw,38px)}.hero p{margin:0;line-height:1.6;color:#e8f3ff}
    .tools{display:flex;gap:10px;margin:18px 0}.tools input,.tools select{min-width:0;padding:12px 14px;border:1px solid #d4e0ed;border-radius:12px;background:#fff;color:inherit;font:inherit}
    .tools input{flex:1}.tools select{width:min(230px,42%)}
    .hint{margin:0 0 14px;color:#536b84;font-size:14px}.table-wrap{overflow:auto;border:1px solid #dce6f0;border-radius:16px;background:#fff;box-shadow:0 8px 24px #1b3a5b0f}
    table{width:100%;border-collapse:collapse;min-width:720px}th,td{padding:14px 16px;border-bottom:1px solid #edf1f6;text-align:left;vertical-align:top}
    th{position:sticky;top:0;background:#f7faff;color:#476482;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
    tbody tr{cursor:pointer}tbody tr:hover,tbody tr:focus{background:#f2f8ff;outline:none}tbody tr:last-child td{border-bottom:0}
    td strong{display:block;color:#183c68}.try{display:inline-flex;margin-top:7px;padding:6px 10px;border:0;border-radius:9px;background:#eaf2ff;color:#245fc4;font:inherit;font-size:12px;font-weight:700;cursor:pointer}
    .note{color:#5f7489;font-size:13px}.status{min-height:24px;margin:10px 2px;color:#176a55;font-size:14px}
    .admin-tools{margin:24px 0;padding:20px;border:1px solid #dce6f0;border-radius:16px;background:#fff}.admin-row{display:grid;grid-template-columns:1.1fr 1.5fr 1fr 1.2fr 1fr 1.2fr .7fr .8fr auto;gap:8px;align-items:start;padding:10px 0;border-bottom:1px solid #edf1f6}.admin-row input,.admin-row textarea,.admin-row select{width:100%;min-width:0;padding:8px;border:1px solid #d4e0ed;border-radius:8px;font:inherit;font-size:13px}.admin-row textarea{min-height:58px;resize:vertical}.admin-head{font-size:11px;font-weight:700;color:#536b84}.admin-actions{display:flex;gap:8px;margin-top:12px}.admin-actions button{padding:9px 13px;border:0;border-radius:9px;background:#eaf2ff;color:#245fc4;font:inherit;font-weight:700;cursor:pointer}.admin-actions .save{background:#166534;color:white}.row-tools{display:grid;gap:6px;font-size:12px}.examples{display:block;margin-top:5px;color:#637a92;font-size:12px}
    .module-index{margin-top:22px;padding:18px 20px;border:1px solid #dce6f0;border-radius:16px;background:#fff}.module-index ul{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px 24px;padding-left:20px;color:#536b84}
    .back{display:inline-flex;margin-top:16px;color:#245fc4;font-weight:700;text-decoration:none}
    @media(max-width:900px){.admin-row{grid-template-columns:1fr 1fr}.admin-head{display:none}}
    @media(max-width:600px){.tools{flex-direction:column}.tools select{width:100%}.hero{border-radius:18px}}
  </style>
</head>
<body>
<main>
  <header class="hero">
    <h1>Câu lệnh giọng nói</h1>
    <p>Tìm cách gọi module hoặc thao tác rồi bấm vào một dòng để thử. Ví dụ: “mở nội trú”, “chuyển đến thư viện”, “đi tới văn bản”.</p>
  </header>
  <?php if(function_exists('show_flash')) show_flash(); ?>
  <?php if($isAdmin): ?>
  <form class="admin-tools" method="post" id="voiceCommandEditor">
    <h2>Cài đặt bộ lệnh</h2>
    <p class="hint">Chỉ quản trị được thay đổi. URL phải là đường dẫn nội bộ; từ khóa nhập mỗi dòng một cụm. Lệnh thuộc module và thao tác nhanh vẫn tuân theo quyền hiện có.</p>
    <input type="hidden" name="csrf" value="<?= e(cds_voice_csrf_token()) ?>">
    <input type="hidden" name="save_voice_commands" value="1">
    <div class="admin-row admin-head"><span>Tên hiển thị</span><span>URL nội bộ</span><span>Từ khóa / đồng nghĩa</span><span>Nhóm</span><span>Ghi chú</span><span>Hiển thị cho</span><span>Bật</span><span>Xóa</span><span></span></div>
    <div id="adminCommandRows">
      <?php foreach($adminCommands as $i=>$command): ?>
      <div class="admin-row">
        <input type="hidden" name="commands[<?= (int)$i ?>][id]" value="<?= e($command['id']??'') ?>">
        <input name="commands[<?= (int)$i ?>][name]" maxlength="80" required value="<?= e($command['name']??'') ?>" aria-label="Tên hiển thị">
        <input name="commands[<?= (int)$i ?>][url]" maxlength="500" value="<?= e($command['url']??'') ?>" aria-label="URL nội bộ">
        <textarea name="commands[<?= (int)$i ?>][keywords]" aria-label="Từ khóa, mỗi dòng một cụm"><?= e(implode("\n",(array)($command['keywords']??[]))) ?></textarea>
        <input name="commands[<?= (int)$i ?>][category]" maxlength="40" required value="<?= e($command['category']??'') ?>" aria-label="Nhóm">
        <input name="commands[<?= (int)$i ?>][note]" maxlength="300" value="<?= e($command['note']??'') ?>" aria-label="Ghi chú">
        <select name="commands[<?= (int)$i ?>][show_to]" aria-label="Hiển thị cho"><option value="all" <?= ($command['show_to']??'all')!=='admin'?'selected':'' ?>>Tất cả*</option><option value="admin" <?= ($command['show_to']??'')==='admin'?'selected':'' ?>>Chỉ quản trị</option></select>
        <input type="checkbox" name="commands[<?= (int)$i ?>][enabled]" value="1" <?= !empty($command['enabled'])?'checked':'' ?> aria-label="Bật câu lệnh">
        <label class="row-tools"><input type="checkbox" name="commands[<?= (int)$i ?>][remove]" value="1"> Xóa</label>
        <span></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="admin-actions"><button type="button" id="addVoiceCommand">Thêm lệnh</button><button class="save" type="submit">Lưu cấu hình</button></div>
    <small>* Lệnh thuộc module/chức năng chỉ hiện cho người có quyền tương ứng; lệnh tùy chỉnh không nhận diện được module có thể giới hạn chỉ quản trị.</small>
  </form>
  <?php endif; ?>
  <section class="module-index">
    <h2>Menu và module</h2>
    <p class="hint">Các mục dưới đây phản ánh menu người dùng được phép truy cập. Lệnh giọng nói chỉ điều hướng tới đích nội bộ đang bật trong bảng trên.</p>
    <ul><?php foreach($modulesForGuide as $module): ?><li><strong><?= e($module['title']??'') ?></strong> — có thể gọi “mở …”, “chuyển đến …” hoặc “đi tới …”<?= ($module['status']??'')==='link'?' (liên kết ngoài không được điều hướng bằng giọng nói).':'' ?></li><?php endforeach; ?></ul>
  </section>
  <div class="tools">
    <input type="search" id="commandSearch" placeholder="Tìm module hoặc từ khóa…" aria-label="Tìm câu lệnh">
    <select id="commandCategory" aria-label="Lọc theo nhóm">
      <option value="">Tất cả nhóm</option>
      <?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?>
    </select>
  </div>
  <p class="hint">Có thể nói “mở …”, “chuyển đến …” hoặc “đi tới …” kèm tên mục/từ khóa. Danh sách đã lọc theo quyền của tài khoản; nhận dạng giọng nói cần trình duyệt hỗ trợ và quyền micro.</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Câu lệnh tiếng Việt</th><th>Từ khóa / đồng nghĩa</th><th>Mục đích</th><th>Ghi chú</th></tr></thead>
      <tbody id="commandRows">
      <?php foreach ($commands as $command):
          $phrase = 'mở ' . $command['name'];
          $searchText = implode(' ', [$phrase, 'chuyển đến ' . $command['name'], $command['name'], implode(' ', $command['keywords']), $command['category']]);
      ?>
        <tr tabindex="0" data-command="<?= e($phrase) ?>" data-category="<?= e($command['category']) ?>" data-search="<?= e($searchText) ?>">
          <td><strong><?= e($phrase) ?></strong><span class="examples">“mở …” · “chuyển đến …” · “đi tới …”</span><button type="button" class="try">Thử câu lệnh</button></td>
          <td><?= e(implode(' / ', $command['keywords']) ?: $command['name']) ?></td>
          <td><?= e($command['category'] === 'Chung' ? 'Mở trang tổng quan' : $command['name']) ?></td>
          <td class="note"><?= e($command['note']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="status" id="commandStatus" aria-live="polite"></p>
  <a class="back" href="<?= e(BASE_URL) ?>admin.php">Quay lại CDS</a>
</main>
<script>
(function(){
  var search=document.getElementById('commandSearch'),category=document.getElementById('commandCategory');
  var rows=Array.from(document.querySelectorAll('#commandRows tr')),status=document.getElementById('commandStatus');
  function norm(value){
    return String(value||'').toLocaleLowerCase('vi').replace(/đ/g,'d').normalize('NFD')
      .replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ').trim();
  }
  function filter(){
    var query=norm(search.value),group=category.value;
    rows.forEach(function(row){
      row.hidden=(!norm(row.dataset.search).includes(query))||(group&&row.dataset.category!==group);
    });
  }
  function test(row){
    var phrase=row.dataset.command;
    var matches=window.CDSVoiceNavigation?window.CDSVoiceNavigation.match(phrase):[];
    status.textContent=matches.length?'Sẽ mở: '+matches[0].name:'Không tìm thấy câu lệnh phù hợp.';
  }
  search.addEventListener('input',filter);category.addEventListener('change',filter);
  rows.forEach(function(row){
    row.addEventListener('click',function(){test(row);});
    row.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();test(row);}});
  });
  var add=document.getElementById('addVoiceCommand'),editorRows=document.getElementById('adminCommandRows');
  if(add&&editorRows)add.addEventListener('click',function(){
    var i=editorRows.children.length,row=document.createElement('div');row.className='admin-row';
    row.innerHTML='<input type="hidden" name="commands['+i+'][id]" value=""><input name="commands['+i+'][name]" maxlength="80" required aria-label="Tên hiển thị"><input name="commands['+i+'][url]" maxlength="500" required aria-label="URL nội bộ"><textarea name="commands['+i+'][keywords]" aria-label="Từ khóa, mỗi dòng một cụm"></textarea><input name="commands['+i+'][category]" maxlength="40" required aria-label="Nhóm"><input name="commands['+i+'][note]" maxlength="300" aria-label="Ghi chú"><select name="commands['+i+'][show_to]" aria-label="Hiển thị cho"><option value="all">Tất cả*</option><option value="admin">Chỉ quản trị</option></select><input type="checkbox" name="commands['+i+'][enabled]" value="1" checked aria-label="Bật câu lệnh"><label class="row-tools"><input type="checkbox" name="commands['+i+'][remove]" value="1"> Xóa</label><span></span>';
    editorRows.appendChild(row);
  });
})();
</script>
<script type="application/json" id="voiceNavTargets"><?= json_encode($voiceTargets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
<script src="<?= e(BASE_URL) ?>assets/voice-nav.js?v=20261012-3"></script>
</body>
</html>
