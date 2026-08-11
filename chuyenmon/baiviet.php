<?php
$page_title = 'Bài viết chỉ tiêu chuyên môn';
require_once 'includes/functions.php';
require_once 'includes/cm_docs.php';
require_login();

$id = trim((string)($_GET['id'] ?? ''));
$article = null;
foreach (cm_docs_all() as $row) {
    if (($row['id'] ?? '') === $id && ($row['section'] ?? '') === 'kh_chitieu') {
        $article = $row;
        break;
    }
}

function safe_article_html($html) {
    $html = trim((string)$html);
    if ($html === '') return '<span class="text-muted">Bài viết chưa có nội dung.</span>';
    if ($html === strip_tags($html)) return nl2br(e($html));
    $html = strip_tags($html, '<p><br><div><strong><b><em><i><u><h2><h3><ul><ol><li><blockquote><a>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/isu', '', $html);
    $html = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/iu', 'href="#"', $html);
    return $html;
}

if (!$article) {
    http_response_code(404);
    $page_title = 'Không tìm thấy bài viết';
}

require_once 'includes/header.php';
?>
<style>
.article-shell{max-width:900px;margin:0 auto}.article-card{border:0;border-radius:18px;box-shadow:0 8px 30px rgba(15,23,42,.09);overflow:hidden}.article-head{padding:1.5rem;background:linear-gradient(135deg,#1f4e79,#2e6da4);color:#fff}.article-head h1{font-size:clamp(1.45rem,4vw,2rem);margin:.35rem 0}.article-meta{font-size:.86rem;opacity:.85}.article-body{padding:1.5rem;font-size:1rem;line-height:1.75}.article-content{white-space:pre-wrap}.article-docs{padding:1rem;border-radius:12px;background:#f4f8fc}.article-docs a{margin:.25rem .35rem .25rem 0}
</style>
<div class="article-shell">
  <a class="btn btn-sm btn-outline-secondary mb-3" href="<?= BASE_URL ?>kehoach.php?tab=chitieu"><i class="bi bi-arrow-left"></i> Danh sách chỉ tiêu</a>
  <?php if (!$article): ?>
    <div class="alert alert-warning">Bài viết không tồn tại hoặc đã bị xóa.</div>
  <?php else: ?>
    <article class="article-card bg-white">
      <header class="article-head">
        <div class="small text-uppercase">Chỉ tiêu chuyên môn</div>
        <h1><?= e($article['title'] ?? '') ?></h1>
        <div class="article-meta"><i class="bi bi-calendar3"></i> <?= e(!empty($article['date']) ? date('d/m/Y', strtotime($article['date'])) : '') ?><?php if (!empty($article['by'])): ?> · <i class="bi bi-person"></i> <?= e($article['by']) ?><?php endif; ?></div>
      </header>
      <div class="article-body">
        <div class="article-content"><?= safe_article_html($article['content'] ?? '') ?></div>
        <div class="article-docs mt-4">
          <strong class="d-block mb-2"><i class="bi bi-paperclip"></i> Văn bản liên quan</strong>
          <?php if (!empty($article['link'])): ?><a class="btn btn-outline-primary" href="<?= e($article['link']) ?>" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i> Mở liên kết văn bản</a><?php endif; ?>
          <?php if (!empty($article['file_path'])): ?><a class="btn btn-outline-success" href="<?= e(cds_storage_file_url($article['file_path'])) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-arrow-down"></i> Mở file đính kèm</a><?php endif; ?>
          <?php if (empty($article['link']) && empty($article['file_path'])): ?><span class="text-muted">Chưa có văn bản đính kèm.</span><?php endif; ?>
        </div>
      </div>
    </article>
  <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
