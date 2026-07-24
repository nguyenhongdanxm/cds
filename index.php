<?php
require_once 'includes/auth.php';
require_once 'includes/modules.php';
$modules = get_ecosystem_modules();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hệ sinh thái số – <?= e(SCHOOL_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--bg1:#0a2a5c;--accent:#3db5ff}
*{box-sizing:border-box}
body{
  margin:0;min-height:100vh;font-family:'Segoe UI',system-ui,sans-serif;
  background:radial-gradient(ellipse at center,#124a8a 0%,var(--bg1) 55%,#061428 100%);
  color:#fff;overflow-x:hidden;
}
.topbar{
  display:flex;justify-content:space-between;align-items:center;
  padding:.85rem 1.25rem;max-width:1100px;margin:0 auto;
}
.topbar a{color:#cfe8ff;text-decoration:none;font-weight:600}
.topbar a:hover{color:#fff}
.hero{text-align:center;padding:1rem 1rem .5rem}
.hero .so{font-size:.95rem;letter-spacing:.06em;opacity:.85;text-transform:uppercase}
.hero h1{
  font-size:clamp(1.45rem,4vw,2.15rem);font-weight:800;margin:.35rem 0;
  text-shadow:0 2px 20px rgba(0,0,0,.35);
}
.hero .school{font-size:1.05rem;opacity:.92;font-weight:600}
.hero .year{font-size:.9rem;opacity:.7;margin-top:.25rem}
.eco-wrap{
  position:relative;width:min(640px,94vw);height:min(640px,94vw);
  margin:1.25rem auto 2rem;
}
.eco-center{
  position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  width:150px;height:150px;border-radius:50%;
  background:linear-gradient(145deg,#1a5fad,#0b3a7a);
  border:3px solid rgba(255,255,255,.35);
  box-shadow:0 0 0 12px rgba(61,181,255,.12),0 12px 40px rgba(0,0,0,.35);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:.75rem;z-index:2;
}
.eco-center .core-icon{font-size:2rem;margin-bottom:.2rem}
.eco-center .core-title{font-size:.78rem;font-weight:700;line-height:1.25;opacity:.95}
.eco-ring{
  position:absolute;inset:8%;border-radius:50%;
  border:1px dashed rgba(255,255,255,.22);pointer-events:none;
}
.eco-node{
  position:absolute;left:50%;top:50%;
  width:108px;margin-left:-54px;margin-top:-54px;
  text-align:center;text-decoration:none;color:#fff;
  --r:min(42vw,270px);
  transform:rotate(var(--angle)) translateY(calc(-1 * var(--r))) rotate(calc(-1 * var(--angle)));
  transition:filter .2s;z-index:3;
}
.eco-node:hover{filter:brightness(1.12);color:#fff}
.eco-node .bubble{
  width:64px;height:64px;border-radius:50%;margin:0 auto .4rem;
  display:flex;align-items:center;justify-content:center;
  background:#fff;color:var(--node-color,#0d6efd);font-size:1.55rem;
  box-shadow:0 6px 20px rgba(0,0,0,.25);border:3px solid rgba(255,255,255,.9);
  position:relative;transition:transform .2s;
}
.eco-node:hover .bubble{transform:scale(1.08)}
.eco-node .bubble .num{
  position:absolute;top:-4px;right:-4px;width:20px;height:20px;border-radius:50%;
  background:var(--node-color);color:#fff;font-size:.65rem;font-weight:700;
  display:flex;align-items:center;justify-content:center;
}
.eco-node .label{font-size:.78rem;font-weight:700;line-height:1.2;text-shadow:0 1px 6px rgba(0,0,0,.45)}
.eco-node .sub{font-size:.65rem;opacity:.8;line-height:1.2;max-width:110px;margin:0 auto}
.eco-node.soon .bubble{opacity:.55;filter:grayscale(.3)}
.eco-node.soon .badge-soon{
  display:inline-block;font-size:.6rem;background:rgba(0,0,0,.35);
  border-radius:6px;padding:.1rem .35rem;margin-top:.15rem
}
.footer-note{text-align:center;padding:0 1rem 2rem;font-size:.85rem;opacity:.7}
.legend{
  max-width:520px;margin:0 auto 1.5rem;display:flex;flex-wrap:wrap;justify-content:center;gap:.5rem 1rem;
  font-size:.8rem;opacity:.85
}
.legend span{display:inline-flex;align-items:center;gap:.3rem}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block}
.dot.live{background:#20c997}.dot.link{background:#3db5ff}.dot.soon{background:#adb5bd}
@media (max-width:520px){
  .eco-center{width:120px;height:120px}
  .eco-node{width:92px;margin-left:-46px;margin-top:-46px}
  .eco-node .bubble{width:52px;height:52px;font-size:1.25rem}
  .eco-node .label{font-size:.7rem}
}
</style>
</head>
<body>

<div class="topbar">
  <a href="<?= BASE_URL ?>"><i class="bi bi-hexagon-fill"></i> CDS</a>
  <div>
    <?php if ($user): ?>
      <span class="me-2 small opacity-75"><?= e($user['name']) ?></span>
      <a href="<?= BASE_URL ?>admin.php" class="me-2"><i class="bi bi-speedometer2"></i> Quản trị</a>
      <a href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right"></i> Thoát</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</a>
    <?php endif; ?>
  </div>
</div>

<header class="hero">
  <div class="so"><?= e(SCHOOL_SO) ?></div>
  <h1>HỆ SINH THÁI SỐ NHÀ TRƯỜNG</h1>
  <div class="school"><?= e(SCHOOL_NAME) ?></div>
  <div class="year">Năm học <?= e(SCHOOL_YEAR) ?></div>
</header>

<div class="legend">
  <span><i class="dot live"></i> Đang vận hành</span>
  <span><i class="dot link"></i> Liên kết module</span>
  <span><i class="dot soon"></i> Đang xây dựng</span>
</div>

<div class="eco-wrap" aria-label="Sơ đồ hệ sinh thái">
  <div class="eco-ring"></div>
  <div class="eco-center">
    <div class="core-icon"><i class="bi bi-database-fill-gear"></i></div>
    <div class="core-title">CƠ SỞ<br>DỮ LIỆU<br>DÙNG CHUNG</div>
  </div>

  <?php
  $n = count($modules);
  foreach ($modules as $i => $m):
    $angle = -90 + ($i * (360 / $n));
    $status = $m['status'];
    $href = '#';
    $target = '';
    $cls = 'eco-node';
    if ($status === 'soon') {
      $cls .= ' soon';
      $href = 'javascript:void(0)';
    } elseif ($status === 'link' || $status === 'live') {
      $href = $m['url'] ?: '#';
      if (!empty($m['external'])) $target = ' target="_blank" rel="noopener"';
    }
  ?>
  <a class="<?= $cls ?>" style="--angle: <?= $angle ?>deg; --node-color: <?= e($m['color']) ?>"
     href="<?= e($href) ?>"<?= $target ?>
     title="<?= e($m['title'] . ' – ' . $m['subtitle']) ?>">
    <div class="bubble">
      <i class="bi <?= e($m['icon']) ?>"></i>
      <span class="num"><?= (int)$m['num'] ?></span>
    </div>
    <div class="label"><?= e($m['title']) ?></div>
    <div class="sub"><?= e($m['subtitle']) ?></div>
    <?php if ($status === 'soon'): ?>
    <div class="badge-soon">Sắp ra mắt</div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<p class="footer-note">
  Một tài khoản · Dữ liệu liên thông · Thống kê tập trung<br>
  <span style="opacity:.85">Thiết kế cho <?= e(SCHOOL_SHORT) ?> · Module Chuyên môn: PCCM</span>
</p>

<script>
document.querySelectorAll('.eco-node.soon').forEach(function(el){
  el.addEventListener('click', function(e){
    e.preventDefault();
    var t = el.querySelector('.label');
    alert('Module "' + (t ? t.textContent : '') + '" đang được xây dựng.\nVui lòng quay lại sau.');
  });
});
</script>
</body>
</html>
