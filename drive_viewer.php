<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = trim((string)($_GET['id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{10,}$/', $id)) {
    http_response_code(404);
    exit('File không hợp lệ.');
}

$back = trim((string)($_GET['back'] ?? ''));
if ($back === '' || preg_match('~^(?:https?:)?//~i', $back)) $back = 'admin.php';
$title = trim((string)($_GET['title'] ?? 'Tài liệu Google Drive')) ?: 'Tài liệu Google Drive';
$direct = 'https://drive.google.com/file/d/' . rawurlencode($id) . '/view';
$preview = 'https://drive.google.com/file/d/' . rawurlencode($id) . '/preview';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box}html,body{margin:0;height:100%;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#fff}.viewer{height:100%;display:grid;grid-template-rows:auto 1fr}.viewer-bar{display:flex;align-items:center;gap:.75rem;padding:.7rem .85rem;background:#fff;color:#172033;border-bottom:1px solid #dbe4ee;box-shadow:0 2px 10px #0002;z-index:5}.viewer-bar strong{min-width:0;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.viewer-bar a,.viewer-bar button{border:1px solid #cbd5e1;background:#fff;color:#1e293b;border-radius:10px;min-height:40px;padding:.55rem .8rem;font:inherit;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer}.viewer-bar .close-btn{background:#0f4c81;color:#fff;border-color:#0f4c81}.viewer-frame{width:100%;height:100%;border:0;background:#fff}@media(max-width:600px){.viewer-bar{padding:.55rem}.viewer-bar strong{font-size:.9rem}.viewer-bar a,.viewer-bar button{padding:.5rem .65rem}.viewer-bar .label{display:none}}
</style>
</head>
<body>
<div class="viewer">
  <header class="viewer-bar">
    <button type="button" class="close-btn" onclick="closeViewer()"><i class="bi bi-x-lg"></i><span class="label">Đóng</span></button>
    <strong><?= e($title) ?></strong>
    <a href="<?= e($direct) ?>" target="_blank" rel="noopener"><i class="bi bi-google"></i><span class="label">Mở trên Drive</span></a>
  </header>
  <iframe class="viewer-frame" src="<?= e($preview) ?>" allow="autoplay" referrerpolicy="no-referrer"></iframe>
</div>
<script>
function closeViewer(){
  if(history.length>1){ history.back(); return; }
  location.href=<?= json_encode($back, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
}
</script>
</body>
</html>
