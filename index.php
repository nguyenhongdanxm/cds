<?php
require_once 'includes/auth.php';
require_once 'includes/modules.php';
$modules = get_ecosystem_modules();
$user = current_user();
$logoPath = BASE_URL . 'assets/logo.png';
$logoExists = file_exists(__DIR__ . '/assets/logo.png');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hệ sinh thái số – <?= e(SCHOOL_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{
  --bg-deep:#041428;
  --bg-mid:#0a3a7a;
  --ring:#4db8ff;
  --text:#fff;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
  color:var(--text);
  background:
    radial-gradient(ellipse 80% 60% at 50% 40%, #0d4a9a 0%, #0a2a5c 45%, var(--bg-deep) 100%);
  min-height:100vh;
  display:flex;flex-direction:column;
  overflow-x:hidden;
}

/* ---- Top bar ---- */
.topbar{
  display:flex;justify-content:space-between;align-items:center;
  padding:.7rem 1.2rem;flex-shrink:0;z-index:20;
}
.brand{
  font-weight:700;font-size:.95rem;letter-spacing:.04em;
  color:rgba(255,255,255,.85);text-decoration:none;
  display:flex;align-items:center;gap:.4rem;
}
.brand:hover{color:#fff}
.auth-btn{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.45rem 1rem;border-radius:999px;
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.35);
  color:#fff;font-weight:600;font-size:.88rem;
  text-decoration:none;backdrop-filter:blur(8px);
  transition:background .2s,transform .15s,box-shadow .2s;
}
.auth-btn:hover{
  background:rgba(255,255,255,.22);
  box-shadow:0 4px 18px rgba(61,181,255,.25);
  color:#fff;transform:translateY(-1px);
}
.auth-btn.admin{background:rgba(32,201,151,.25);border-color:rgba(32,201,151,.5)}

/* ---- Header ---- */
.hero{
  text-align:center;padding:.4rem 1rem 0;flex-shrink:0;
}
.hero .so{
  font-size:clamp(.72rem,1.8vw,.9rem);
  letter-spacing:.12em;text-transform:uppercase;
  opacity:.8;font-weight:600;
}
.hero h1{
  font-size:clamp(1.15rem,3.5vw,1.85rem);
  font-weight:800;margin:.25rem 0;
  text-shadow:0 2px 16px rgba(0,0,0,.35);
  line-height:1.25;
}
.hero .year{font-size:clamp(.75rem,1.6vw,.88rem);opacity:.65;margin-top:.15rem}

/* ---- Ecosystem stage (responsive square) ---- */
.stage{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:.5rem 0 0;min-height:0;
}
.eco{
  position:relative;
  width:min(92vw, min(92vh, 680px));
  aspect-ratio:1;
  max-height:min(72vh, 680px);
}

/* Connecting ring */
.eco-ring{
  position:absolute;inset:18%;
  border-radius:50%;
  border:1.5px solid rgba(120,190,255,.35);
  box-shadow:0 0 40px rgba(61,181,255,.08) inset;
  pointer-events:none;
}
.eco-ring-outer{
  position:absolute;inset:6%;
  border-radius:50%;
  border:1px dashed rgba(255,255,255,.12);
  pointer-events:none;
}

/* ---- Center: logo + orbiting text ---- */
.eco-core{
  position:absolute;left:50%;top:50%;
  transform:translate(-50%,-50%);
  width:38%;height:38%;
  z-index:5;
  display:flex;align-items:center;justify-content:center;
}
.core-glow{
  position:absolute;inset:-12%;
  border-radius:50%;
  background:radial-gradient(circle,rgba(77,184,255,.35) 0%, transparent 70%);
  animation:pulseGlow 3.5s ease-in-out infinite;
  pointer-events:none;
}
@keyframes pulseGlow{
  0%,100%{opacity:.55;transform:scale(1)}
  50%{opacity:1;transform:scale(1.08)}
}
.logo-wrap{
  position:relative;width:72%;height:72%;
  border-radius:50%;
  background:radial-gradient(circle at 40% 35%,#fff 0%,#e8f4ff 100%);
  box-shadow:
    0 0 0 3px rgba(255,255,255,.5),
    0 0 0 8px rgba(61,181,255,.2),
    0 10px 36px rgba(0,0,0,.35);
  overflow:hidden;
  animation:logoFloat 5s ease-in-out infinite;
  display:flex;align-items:center;justify-content:center;
}
@keyframes logoFloat{
  0%,100%{transform:translateY(0)}
  50%{transform:translateY(-6px)}
}
.logo-wrap img{
  width:92%;height:92%;object-fit:contain;
  border-radius:50%;
}
.logo-fallback{
  font-size:clamp(1.4rem,4vw,2.2rem);font-weight:800;color:#c41e3a;
  text-align:center;line-height:1.1;padding:.3rem;
}

/* SVG text circling the logo */
.orbit-svg{
  position:absolute;left:50%;top:50%;
  width:108%;height:108%;
  transform:translate(-50%,-50%);
  pointer-events:none;overflow:visible;
}
.orbit-svg text{
  fill:rgba(255,255,255,.92);
  font-size:11.5px;
  font-weight:700;
  letter-spacing:2.5px;
  text-transform:uppercase;
}
.orbit-spin{
  transform-origin:center;
  animation:orbitSpin 28s linear infinite;
}
@keyframes orbitSpin{to{transform:rotate(360deg)}}

/* ---- Module nodes ---- */
.eco-node{
  position:absolute;left:50%;top:50%;
  width:88px;margin-left:-44px;margin-top:-44px;
  --r:42%;
  transform:rotate(var(--angle)) translateY(calc(-1 * var(--r))) rotate(calc(-1 * var(--angle)));
  text-align:center;text-decoration:none;color:#fff;
  z-index:6;transition:filter .2s;
}
.eco-node:hover{filter:brightness(1.15);color:#fff}
.eco-node .bubble{
  width:58px;height:58px;border-radius:50%;margin:0 auto .35rem;
  display:flex;align-items:center;justify-content:center;
  background:#fff;color:var(--node-color,#0d6efd);
  font-size:1.4rem;
  box-shadow:0 6px 22px rgba(0,0,0,.28),0 0 0 2px rgba(255,255,255,.85);
  position:relative;transition:transform .2s;
}
.eco-node:hover .bubble{transform:scale(1.1)}
.eco-node .bubble .num{
  position:absolute;top:-5px;right:-5px;
  width:20px;height:20px;border-radius:50%;
  background:var(--node-color);color:#fff;
  font-size:.62rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 6px rgba(0,0,0,.25);
}
.eco-node .label{
  font-size:clamp(.62rem,1.5vw,.78rem);
  font-weight:700;line-height:1.15;
  text-shadow:0 1px 8px rgba(0,0,0,.55);
  max-width:100px;margin:0 auto;
}
.eco-node .sub{
  font-size:clamp(.55rem,1.2vw,.65rem);
  opacity:.75;line-height:1.15;max-width:105px;margin:.1rem auto 0;
}
.eco-node.soon .bubble{opacity:.5;filter:grayscale(.35)}
.eco-node.soon .badge-soon{
  display:inline-block;font-size:.55rem;
  background:rgba(0,0,0,.4);border-radius:5px;
  padding:.08rem .3rem;margin-top:.12rem;
}

/* Side caption style like reference – show short code above label on larger screens */
@media (min-width:700px){
  .eco-node{width:110px;margin-left:-55px}
  .eco-node .bubble{width:64px;height:64px;font-size:1.5rem}
}

@media (max-width:480px){
  .eco-node{width:72px;margin-left:-36px;margin-top:-36px}
  .eco-node .bubble{width:46px;height:46px;font-size:1.1rem}
  .eco-node .sub{display:none}
  .eco-node .label{font-size:.6rem}
  .logo-wrap{width:78%;height:78%}
  .orbit-svg text{font-size:9px;letter-spacing:1.5px}
}

/* ---- Footer ---- */
.site-footer{
  flex-shrink:0;
  text-align:center;
  padding:.85rem 1.25rem 1.1rem;
  font-size:clamp(.72rem,1.6vw,.85rem);
  line-height:1.45;
  color:rgba(255,255,255,.72);
  border-top:1px solid rgba(255,255,255,.08);
  background:rgba(0,0,0,.15);
}
.site-footer .line1{font-weight:500}
.site-footer .line2{
  margin-top:.2rem;opacity:.85;
  font-size:clamp(.68rem,1.5vw,.8rem);
}
.site-footer .line2 strong{font-weight:600;color:rgba(255,255,255,.9)}
</style>
</head>
<body>

<div class="topbar">
  <a class="brand" href="<?= BASE_URL ?>">
    <i class="bi bi-hexagon-fill"></i> CDS · Xín Mần
  </a>
  <div>
    <?php if ($user): ?>
      <a class="auth-btn admin" href="<?= BASE_URL ?>admin.php">
        <i class="bi bi-speedometer2"></i> Quản trị
      </a>
      <a class="auth-btn" href="<?= BASE_URL ?>logout.php" style="margin-left:.4rem">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    <?php else: ?>
      <a class="auth-btn" href="<?= BASE_URL ?>login.php">
        <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
      </a>
    <?php endif; ?>
  </div>
</div>

<header class="hero">
  <div class="so"><?= e(SCHOOL_SO) ?></div>
  <h1>HỆ SINH THÁI QUẢN LÝ TRƯỜNG HỌC</h1>
  <div class="year">Năm học <?= e(SCHOOL_YEAR) ?></div>
</header>

<div class="stage">
  <div class="eco" aria-label="Sơ đồ hệ sinh thái">
    <div class="eco-ring-outer"></div>
    <div class="eco-ring"></div>

    <!-- Tâm: logo + chữ vòng -->
    <div class="eco-core">
      <div class="core-glow"></div>

      <svg class="orbit-svg" viewBox="0 0 200 200">
        <defs>
          <path id="orbitPath" d="M100,100 m-78,0 a78,78 0 1,1 156,0 a78,78 0 1,1 -156,0" fill="none"/>
        </defs>
        <g class="orbit-spin">
          <text>
            <textPath href="#orbitPath" startOffset="0%">
              HỆ SINH THÁI QUẢN LÝ TRƯỜNG HỌC · XÍN MẦN · TUYÊN QUANG ·&nbsp;
            </textPath>
          </text>
        </g>
      </svg>

      <div class="logo-wrap">
        <?php if ($logoExists): ?>
          <img src="<?= e($logoPath) ?>" alt="Logo <?= e(SCHOOL_NAME) ?>">
        <?php else: ?>
          <div class="logo-fallback">XÍN<br>MẦN</div>
        <?php endif; ?>
      </div>
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
    <a class="<?= $cls ?>"
       style="--angle:<?= $angle ?>deg;--node-color:<?= e($m['color']) ?>"
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
</div>

<footer class="site-footer">
  <div class="line1">Dự án chuyển đổi số trong quản lý giáo dục của trường PTDTNT THCS&THPT Xín Mần</div>
  <div class="line2">Thiết kế và xây dựng bởi thầy giáo <strong>Nguyễn Hồng Dân</strong></div>
</footer>

<script>
document.querySelectorAll('.eco-node.soon').forEach(function(el){
  el.addEventListener('click', function(e){
    e.preventDefault();
    var t = el.querySelector('.label');
    alert('Module "' + (t ? t.textContent.trim() : '') + '" đang được xây dựng.\nVui lòng quay lại sau.');
  });
});
</script>
</body>
</html>
