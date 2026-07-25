<?php
require_once 'includes/auth.php';
require_once 'includes/modules.php';
$modules = get_ecosystem_modules();
$user = current_user();
$logoPath = BASE_URL . 'assets/logo.png';
$logoExists = file_exists(__DIR__ . '/assets/logo.png');
$nModules = count($modules);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hệ sinh thái số – <?= e(SCHOOL_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
  color:#fff;
  min-height:100vh;
  display:flex;flex-direction:column;
  overflow-x:hidden;
  background-color:#031428;
  background-image:
    radial-gradient(ellipse 110% 85% at 50% 42%, #0b62c9 0%, #0850a8 25%, #053a7a 50%, #032450 72%, #020f24 100%);
}
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background:
    radial-gradient(circle at 18% 22%, rgba(0,190,255,.16) 0%, transparent 34%),
    radial-gradient(circle at 82% 75%, rgba(70,130,255,.14) 0%, transparent 38%);
}
body::after{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.2;
  background-image:url("data:image/svg+xml,%3Csvg width='70' height='70' viewBox='0 0 70 70' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M35 2 L68 18 L68 52 L35 68 L2 52 L2 18 Z' fill='none' stroke='%2350a0ff' stroke-width='0.5'/%3E%3C/svg%3E");
  background-size:80px 80px;
}
.topbar,.stage,.site-footer{position:relative;z-index:2}

.topbar{
  display:flex;justify-content:space-between;align-items:center;
  padding:.7rem 1.25rem;flex-shrink:0;
}
.clock{
  font-size:clamp(.78rem,1.8vw,.92rem);font-weight:600;
  color:rgba(255,255,255,.92);
  display:flex;align-items:center;gap:.45rem;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);
  padding:.35rem .75rem;border-radius:999px;backdrop-filter:blur(8px);
}
.auth-btn{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.45rem 1rem;border-radius:999px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.35);
  color:#fff;font-weight:600;font-size:.88rem;text-decoration:none;
  backdrop-filter:blur(10px);transition:.2s;
}
.auth-btn:hover{background:rgba(255,255,255,.22);color:#fff;transform:translateY(-1px)}
.auth-btn.admin{background:rgba(32,201,151,.25);border-color:rgba(32,201,151,.5)}

.stage{flex:1;display:flex;align-items:center;justify-content:center;min-height:0;padding:.4rem 0}
.eco{
  --size: min(96vw, min(84vh, 780px));
  position:relative;width:var(--size);height:var(--size);
}

/* —— Hệ mặt trời: các quỹ đạo —— */
.orbit-track{
  position:absolute;left:50%;top:50%;
  border-radius:50%;pointer-events:none;
  border:1px solid rgba(140,200,255,.22);
  transform:translate(-50%,-50%);
}
.orbit-track.t1{width:72%;height:72%;border-style:dashed;border-color:rgba(160,210,255,.35);
  animation:spinSlow 80s linear infinite}
.orbit-track.t2{width:84%;height:84%;border-color:rgba(100,180,255,.28);
  box-shadow:0 0 30px rgba(60,150,255,.08) inset}
.orbit-track.t3{width:58%;height:58%;border-color:rgba(120,190,255,.2)}
.orbit-track.t4{
  width:96%;height:96%;
  border:1px dashed rgba(255,255,255,.1);
  animation:spinSlow 120s linear infinite reverse;
}
@keyframes spinSlow{to{transform:translate(-50%,-50%) rotate(360deg)}}

/* Hạt sáng chạy trên quỹ đạo */
.planet-dot{
  position:absolute;left:50%;top:50%;width:8px;height:8px;margin:-4px;
  border-radius:50%;pointer-events:none;z-index:3;
  background:radial-gradient(circle,#fff 0%,#6ec8ff 40%,transparent 70%);
  box-shadow:0 0 12px #5eb8ff,0 0 24px rgba(80,180,255,.6);
  --pr: calc(var(--size) * 0.42);
  animation:planetOrbit var(--dur,28s) linear infinite;
  animation-delay: var(--delay,0s);
  offset-path: none;
  transform: rotate(var(--a0,0deg)) translateY(calc(-1 * var(--pr))) rotate(0deg);
}
@keyframes planetOrbit{
  to{transform: rotate(calc(var(--a0,0deg) + 360deg)) translateY(calc(-1 * var(--pr)))}
}

/* Tia sáng từ tâm */
.sun-rays{
  position:absolute;left:50%;top:50%;width:70%;height:70%;
  transform:translate(-50%,-50%);pointer-events:none;z-index:1;opacity:.35;
  background:conic-gradient(from 0deg,
    transparent 0deg, rgba(100,180,255,.15) 8deg, transparent 16deg,
    transparent 40deg, rgba(100,180,255,.12) 48deg, transparent 56deg,
    transparent 80deg, rgba(100,180,255,.14) 88deg, transparent 96deg,
    transparent 120deg, rgba(100,180,255,.1) 128deg, transparent 136deg,
    transparent 160deg, rgba(100,180,255,.13) 168deg, transparent 176deg,
    transparent 200deg, rgba(100,180,255,.11) 208deg, transparent 216deg,
    transparent 240deg, rgba(100,180,255,.14) 248deg, transparent 256deg,
    transparent 280deg, rgba(100,180,255,.1) 288deg, transparent 296deg,
    transparent 320deg, rgba(100,180,255,.12) 328deg, transparent 336deg,
    transparent 360deg);
  animation:spinSlow 60s linear infinite;
  border-radius:50%;
}

.eco-core{
  position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  width:34%;height:34%;z-index:5;
  display:flex;align-items:center;justify-content:center;
}
.core-glow{
  position:absolute;inset:-28%;border-radius:50%;pointer-events:none;
  background:radial-gradient(circle,rgba(100,190,255,.55) 0%, rgba(40,120,220,.2) 42%, transparent 70%);
  animation:pulseGlow 3.8s ease-in-out infinite;
}
@keyframes pulseGlow{
  0%,100%{opacity:.5;transform:scale(.94)}
  50%{opacity:1;transform:scale(1.1)}
}
.logo-wrap{
  position:relative;z-index:2;width:82%;height:82%;border-radius:50%;
  background:radial-gradient(circle at 38% 28%,#fff 0%,#eaf4ff 50%,#cde4ff 100%);
  box-shadow:
    0 0 0 4px rgba(255,255,255,.7),
    0 0 0 11px rgba(70,160,255,.3),
    0 0 60px rgba(80,170,255,.45),
    0 16px 40px rgba(0,0,0,.4);
  overflow:hidden;display:flex;align-items:center;justify-content:center;
  animation:logoFloat 5s ease-in-out infinite;
}
@keyframes logoFloat{
  0%,100%{transform:translateY(0)}
  50%{transform:translateY(-5px)}
}
.logo-wrap img{width:94%;height:94%;object-fit:contain;border-radius:50%}
.logo-fallback{font-size:clamp(1.2rem,3.5vw,1.9rem);font-weight:800;color:#c41e3a;text-align:center;line-height:1.1}

.orbit-svg{
  position:absolute;left:50%;top:50%;width:124%;height:124%;
  transform:translate(-50%,-50%);pointer-events:none;overflow:visible;z-index:1;
}
.orbit-svg text{
  fill:rgba(255,255,255,.95);font-size:8px;font-weight:800;letter-spacing:1px;
}
.orbit-spin{transform-origin:100px 100px;animation:orbitSpin 42s linear infinite}
@keyframes orbitSpin{to{transform:rotate(360deg)}}

/* —— Vệ tinh: icon + chữ bên cạnh —— */
.eco-node{
  position:absolute;left:50%;top:50%;
  width:240px;margin-left:-120px;margin-top:-42px;
  --r: calc(var(--size) * 0.42);
  transform:
    rotate(var(--angle))
    translateY(calc(-1 * var(--r)))
    rotate(calc(-1 * var(--angle)));
  text-decoration:none;color:#fff;z-index:6;
  transition:filter .2s;
}
.eco-node:hover{filter:brightness(1.12);color:#fff}

.eco-node .node-inner{
  display:flex;align-items:center;gap:.7rem;
  animation:satFloat 4.5s ease-in-out infinite;
  animation-delay:var(--delay,0s);
}
@keyframes satFloat{
  0%,100%{transform:translateY(0)}
  50%{transform:translateY(-4px)}
}

/* Phải: icon | chữ */
.eco-node.side-right .node-inner{flex-direction:row;text-align:left;justify-content:flex-start}
/* Trái: chữ | icon */
.eco-node.side-left .node-inner{flex-direction:row-reverse;text-align:right;justify-content:flex-start}
/* Trên/dưới: chữ dưới icon, căn giữa */
.eco-node.side-center{
  width:130px;margin-left:-65px;margin-top:-70px;
}
.eco-node.side-center .node-inner{
  flex-direction:column;text-align:center;gap:.4rem;
}

.eco-node .bubble{
  flex-shrink:0;
  width:72px;height:72px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  background:linear-gradient(160deg,#fff 0%,#f0f7ff 50%,#e2efff 100%);
  color:var(--node-color,#0d6efd);
  font-size:1.8rem;
  box-shadow:
    0 10px 28px rgba(0,0,0,.35),
    0 0 0 4px rgba(255,255,255,.95),
    0 0 20px rgba(80,170,255,.35);
  position:relative;
  transition:transform .22s,box-shadow .22s;
}
.eco-node:hover .bubble{
  transform:scale(1.1);
  box-shadow:0 14px 32px rgba(0,0,0,.4),0 0 0 4px #fff,0 0 28px var(--node-color);
}
.eco-node .bubble .num{
  position:absolute;top:-5px;right:-5px;
  min-width:24px;height:24px;padding:0 5px;border-radius:999px;
  background:var(--node-color);color:#fff;font-size:.68rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35);
}
.eco-node .text-block{min-width:0;max-width:150px}
.eco-node .label{
  font-size:clamp(.8rem,1.9vw,1rem);
  font-weight:800;line-height:1.25;
  text-shadow:0 2px 12px rgba(0,0,0,.8);
  letter-spacing:.02em;text-transform:uppercase;
}
.eco-node .sub{
  font-size:clamp(.68rem,1.5vw,.8rem);
  opacity:.9;line-height:1.25;margin-top:.15rem;font-weight:500;
  text-shadow:0 1px 8px rgba(0,0,0,.7);
}
.eco-node.soon .bubble{opacity:.6;filter:grayscale(.35)}
.eco-node.soon .badge-soon{
  display:inline-block;font-size:.55rem;background:rgba(0,0,0,.5);
  border-radius:5px;padding:.12rem .35rem;margin-top:.15rem;font-weight:600;
}

@media (max-width:600px){
  .eco{--size:min(100vw, min(70vh, 420px))}
  .eco-node{width:150px;margin-left:-75px;margin-top:-32px;--r:calc(var(--size)*0.40)}
  .eco-node.side-center{width:90px;margin-left:-45px;margin-top:-55px}
  .eco-node .bubble{width:52px;height:52px;font-size:1.3rem}
  .eco-node .text-block{max-width:90px}
  .eco-node .label{font-size:.68rem}
  .eco-node .sub{display:none}
  .eco-core{width:36%;height:36%}
  .planet-dot{display:none}
}
@media (min-width:960px){
  .eco-node{width:280px;margin-left:-140px}
  .eco-node .bubble{width:82px;height:82px;font-size:2.05rem}
  .eco-node .text-block{max-width:175px}
  .eco-node .label{font-size:1.05rem}
  .eco-node .sub{font-size:.84rem}
}

.site-footer{
  flex-shrink:0;text-align:center;
  padding:.85rem 1.2rem 1.05rem;
  font-size:clamp(.7rem,1.5vw,.86rem);line-height:1.45;
  color:rgba(255,255,255,.75);
  border-top:1px solid rgba(255,255,255,.1);
  background:rgba(0,10,30,.5);backdrop-filter:blur(8px);
}
.site-footer .line2{margin-top:.2rem;font-size:clamp(.66rem,1.4vw,.8rem)}
.site-footer .line2 strong{font-weight:700;color:#fff}
</style>
</head>
<body>

<div class="topbar">
  <div class="clock" id="liveClock">
    <i class="bi bi-clock"></i>
    <span id="clockText">—</span>
  </div>
  <div>
    <?php if ($user): ?>
      <a class="auth-btn admin" href="<?= BASE_URL ?>admin.php"><i class="bi bi-speedometer2"></i> Quản trị</a>
      <a class="auth-btn" href="<?= BASE_URL ?>logout.php" style="margin-left:.35rem"><i class="bi bi-box-arrow-right"></i></a>
    <?php else: ?>
      <a class="auth-btn" href="<?= BASE_URL ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</a>
    <?php endif; ?>
  </div>
</div>

<div class="stage">
  <div class="eco" id="ecoStage">
    <div class="orbit-track t4"></div>
    <div class="orbit-track t2"></div>
    <div class="orbit-track t1"></div>
    <div class="orbit-track t3"></div>
    <div class="sun-rays"></div>

    <!-- Hạt sáng quỹ đạo -->
    <div class="planet-dot" style="--a0:20deg;--dur:26s;--delay:0s"></div>
    <div class="planet-dot" style="--a0:140deg;--dur:34s;--delay:-5s;--pr:calc(var(--size)*0.36)"></div>
    <div class="planet-dot" style="--a0:250deg;--dur:22s;--delay:-10s;--pr:calc(var(--size)*0.30)"></div>

    <div class="eco-core">
      <div class="core-glow"></div>
      <svg class="orbit-svg" viewBox="0 0 200 200">
        <defs>
          <path id="orbitPath" d="M100,100 m-82,0 a82,82 0 1,1 164,0 a82,82 0 1,1 -164,0" fill="none"/>
        </defs>
        <g class="orbit-spin">
          <text>
            <textPath href="#orbitPath" startOffset="0%" textLength="515" lengthAdjust="spacing">
              🌐  DỰ ÁN CHUYỂN ĐỔI SỐ  ⚛  HỆ SINH THÁI QUẢN LÝ NHÀ TRƯỜNG  
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
    foreach ($modules as $i => $m):
      $angle = -90 + ($i * (360 / $nModules));
      $sin = sin(deg2rad($angle));
      $cos = cos(deg2rad($angle));
      // CSS: angle 0 = top, 90 = right → sin>0 = nửa phải
      if (abs($sin) < 0.35) {
        $side = 'side-center'; // gần đỉnh / đáy
      } elseif ($sin > 0) {
        $side = 'side-right';
      } else {
        $side = 'side-left';
      }
      $status = $m['status'];
      $href = '#';
      $target = '';
      $cls = 'eco-node ' . $side;
      if ($status === 'soon') {
        $cls .= ' soon';
        $href = 'javascript:void(0)';
      } elseif ($status === 'link' || $status === 'live') {
        $href = $m['url'] ?: '#';
        if (!empty($m['external'])) $target = ' target="_blank" rel="noopener"';
      }
      $delay = ($i * 0.35) . 's';
    ?>
    <a class="<?= $cls ?>"
       style="--angle:<?= $angle ?>deg;--node-color:<?= e($m['color']) ?>;--delay:<?= $delay ?>"
       href="<?= e($href) ?>"<?= $target ?>
       title="<?= e($m['title'] . ' – ' . $m['subtitle']) ?>">
      <div class="node-inner">
        <div class="bubble">
          <i class="bi <?= e($m['icon']) ?>"></i>
          <span class="num"><?= (int)$m['num'] ?></span>
        </div>
        <div class="text-block">
          <div class="label"><?= e($m['title']) ?></div>
          <div class="sub"><?= e($m['subtitle']) ?></div>
          <?php if ($status === 'soon'): ?>
            <div class="badge-soon">Sắp ra mắt</div>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<footer class="site-footer">
  <div class="line1">Dự án chuyển đổi số trong quản lý giáo dục của trường PTDTNT THCS&THPT Xín Mần</div>
  <div class="line2">Thiết kế và xây dựng bởi thầy giáo <strong>Nguyễn Hồng Dân</strong></div>
</footer>

<script>
(function(){
  var days = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
  function pad(n){ return n < 10 ? '0'+n : ''+n; }
  function tick(){
    var d = new Date();
    var text = days[d.getDay()] + ', '
      + pad(d.getDate()) + '/' + pad(d.getMonth()+1) + '/' + d.getFullYear()
      + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    var el = document.getElementById('clockText');
    if (el) el.textContent = text;
  }
  tick();
  setInterval(tick, 1000);
  document.querySelectorAll('.eco-node.soon').forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      var t = el.querySelector('.label');
      alert('Module "' + (t ? t.textContent.trim() : '') + '" đang được xây dựng.\nVui lòng quay lại sau.');
    });
  });
})();
</script>
</body>
</html>
