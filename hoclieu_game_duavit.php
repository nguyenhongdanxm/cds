<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$base = defined('BASE_URL') ? BASE_URL : '/';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đua vịt gắn tên học sinh</title>
<style>
html,body{margin:0;height:100%;font-family:system-ui,sans-serif;background:#101827}.bar{height:52px;display:flex;align-items:center;gap:14px;padding:0 16px;background:#172554;color:#fff}.bar b{font-size:17px}.bar a{margin-left:auto;color:#fff;text-decoration:none;padding:7px 11px;border:1px solid #ffffff66;border-radius:8px}iframe{border:0;width:100%;height:calc(100% - 52px);background:#fff}
</style>
</head>
<body>
<div class="bar"><b>Đua vịt gắn tên học sinh</b><a href="<?=htmlspecialchars($base)?>hoclieu.php?tab=games">← Học liệu</a></div>
<iframe src="https://giaoducsangtao.my.canva.site/duavit" title="Đua vịt gắn tên học sinh" allow="fullscreen; autoplay"></iframe>
</body>
</html>
