<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_module('thidua', 'view');
register_shutdown_function(function(){require __DIR__.'/includes/module_switcher.php';});

$id = trim((string)($_GET['id'] ?? ''));
$data = load_json(DATA_PATH . '/thidua.json', ['records'=>[]]);
$article = null;
foreach (($data['records'] ?? []) as $row) {
    if (($row['id'] ?? '') === $id && ($row['type'] ?? '') === 'student_profile') {
        $article = $row;
        break;
    }
}
if (!$article) http_response_code(404);
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($article['title'] ?? 'Bài viết hồ sơ thi đua') ?> – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f5f7fa;color:#172033;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}.article-shell{max-width:920px;margin:2rem auto;padding:0 1rem}.article-card{border:1px solid #eadca5;border-radius:20px;background:#fff;box-shadow:0 10px 34px rgba(15,23,42,.08);overflow:hidden}.article-head{padding:1.6rem;background:linear-gradient(135deg,#704d00,#b78000);color:#fff}.article-head h1{font-size:clamp(1.5rem,4vw,2.15rem);margin:.35rem 0}.article-meta{font-size:.86rem;opacity:.85}.article-body{padding:1.6rem;line-height:1.75}.article-content{white-space:pre-wrap}.article-docs{padding:1rem;border:1px solid #f0d778;border-radius:13px;background:#fffbee}
</style>
</head>
<body>
<main class="article-shell">
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= BASE_URL ?>thidua.php?section=student_profile"><i class="bi bi-arrow-left"></i> Danh sách hồ sơ thi đua</a>
<?php if (!$article): ?>
<div class="alert alert-warning">Bài viết không tồn tại hoặc đã bị xóa.</div>
<?php else: ?>
<article class="article-card">
<header class="article-head">
<div class="small text-uppercase">Hồ sơ thi đua học sinh</div>
<h1><?= e($article['title'] ?? '') ?></h1>
<div class="article-meta"><i class="bi bi-person"></i> <?= e($article['person_name'] ?? '') ?><?php if (!empty($article['class_name'])): ?> · Lớp <?= e($article['class_name']) ?><?php endif; ?> · <i class="bi bi-calendar3"></i> <?= e(date('d/m/Y', strtotime($article['date'] ?? 'now'))) ?></div>
</header>
<div class="article-body">
<?php if (!empty($article['category'])): ?><span class="badge text-bg-warning mb-3"><?= e($article['category']) ?></span><?php endif; ?>
<div class="article-content"><?= nl2br(e($article['note'] ?? '')) ?: '<span class="text-muted">Bài viết chưa có nội dung.</span>' ?></div>
<div class="article-docs mt-4"><strong class="d-block mb-2"><i class="bi bi-paperclip"></i> Văn bản liên quan</strong><?php if (!empty($article['link'])): ?><a class="btn btn-outline-primary" href="<?= e($article['link']) ?>" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i> Mở liên kết văn bản</a><?php else: ?><span class="text-muted">Chưa có liên kết văn bản.</span><?php endif; ?></div>
</div>
</article>
<?php endif; ?>
</main>
</body>
</html>
