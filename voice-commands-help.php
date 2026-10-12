<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/modules.php';
require_once __DIR__ . '/includes/voice_command_catalog.php';

$commands = [[
    'name'=>'Trang chủ',
    'category'=>'Chung',
    'keywords'=>['tổng quan','màn hình chính','home'],
    'note'=>'Trang tổng quan CDS.',
]];
foreach (get_ecosystem_modules() as $module) {
    if (($module['status'] ?? '') === 'soon') continue;
    $commands[] = [
        'name'=>(string)$module['title'],
        'category'=>(string)$module['title'],
        'keywords'=>cds_voice_module_keywords()[$module['id'] ?? ''] ?? [],
        'note'=>($module['status'] ?? '') === 'link'
            ? 'Mở trang liên kết; quyền truy cập phụ thuộc trang đích.'
            : 'Chỉ mở từ điều hướng giọng nói nếu tài khoản có quyền module.',
    ];
}
foreach (cds_voice_quick_action_catalog() as $name=>$action) {
    $commands[] = [
        'name'=>$name,
        'category'=>$action['category'],
        'keywords'=>$action['keywords'],
        'note'=>$action['note'],
    ];
}
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
    .back{display:inline-flex;margin-top:16px;color:#245fc4;font-weight:700;text-decoration:none}
    @media(max-width:600px){.tools{flex-direction:column}.tools select{width:100%}.hero{border-radius:18px}}
  </style>
</head>
<body>
<main>
  <header class="hero">
    <h1>Câu lệnh giọng nói</h1>
    <p>Tìm cách gọi module hoặc thao tác rồi bấm vào một dòng để thử. Ví dụ: “mở nội trú”, “chuyển đến thư viện”, “đi tới văn bản”.</p>
  </header>
  <div class="tools">
    <input type="search" id="commandSearch" placeholder="Tìm module hoặc từ khóa…" aria-label="Tìm câu lệnh">
    <select id="commandCategory" aria-label="Lọc theo nhóm">
      <option value="">Tất cả nhóm</option>
      <?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?>
    </select>
  </div>
  <p class="hint">Câu lệnh gồm “mở”, “chuyển đến”, “đi tới” hoặc “xem” kèm tên mục/từ khóa. Các mục có phân quyền chỉ xuất hiện trong điều hướng thực tế khi tài khoản được cấp quyền. Tính năng nhận dạng cần trình duyệt hỗ trợ và quyền micro.</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Câu lệnh tiếng Việt</th><th>Từ khóa / đồng nghĩa</th><th>Mục đích</th><th>Ghi chú</th></tr></thead>
      <tbody id="commandRows">
      <?php foreach ($commands as $command):
          $phrase = 'mở ' . $command['name'];
          $searchText = implode(' ', [$phrase, 'chuyển đến ' . $command['name'], $command['name'], implode(' ', $command['keywords']), $command['category']]);
      ?>
        <tr tabindex="0" data-command="<?= e($phrase) ?>" data-category="<?= e($command['category']) ?>" data-search="<?= e($searchText) ?>">
          <td><strong><?= e($phrase) ?></strong><button type="button" class="try">Thử câu lệnh</button></td>
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
    console.info('CDS voice command test:',phrase);
    status.textContent='Đã thử câu lệnh: “'+phrase+'”. Để điều hướng, hãy dùng nút micro trên trang chủ.';
  }
  search.addEventListener('input',filter);category.addEventListener('change',filter);
  rows.forEach(function(row){
    row.addEventListener('click',function(){test(row);});
    row.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();test(row);}});
  });
})();
</script>
</body>
</html>
