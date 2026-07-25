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
  color:#fff;min-height:100dvh;height:100dvh;
  display:flex;flex-direction:column;overflow:hidden;
  background-color:#031428;
  background-image:radial-gradient(ellipse 110% 85% at 50% 42%, #0b62c9 0%, #0850a8 25%, #053a7a 50%, #032450 72%, #020f24 100%);
}
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background:
    radial-gradient(circle at 18% 22%, rgba(0,190,255,.16) 0%, transparent 34%),
    radial-gradient(circle at 82% 75%, rgba(70,130,255,.14) 0%, transparent 38%);
}
body::after{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.18;
  background-image:url("data:image/svg+xml,%3Csvg width='70' height='70' viewBox='0 0 70 70' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M35 2 L68 18 L68 52 L35 68 L2 52 L2 18 Z' fill='none' stroke='%2350a0ff' stroke-width='0.5'/%3E%3C/svg%3E");
  background-size:80px 80px;
}
.topbar,.hero,.stage,.site-footer{position:relative;z-index:2}

.topbar{
  display:flex;justify-content:space-between;align-items:center;
  padding:clamp(.2rem,.5vh,.4rem) .9rem;flex-shrink:0;
}
.clock{
  font-size:clamp(.65rem,1.4vw,.82rem);font-weight:600;color:rgba(255,255,255,.92);
  display:flex;align-items:center;gap:.3rem;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);
  padding:.2rem .5rem;border-radius:999px;backdrop-filter:blur(8px);
}
.auth-btn{
  display:inline-flex;align-items:center;gap:.3rem;
  padding:.28rem .7rem;border-radius:999px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.35);
  color:#fff;font-weight:600;font-size:.78rem;text-decoration:none;backdrop-filter:blur(10px);transition:.2s;
}
.auth-btn:hover{background:rgba(255,255,255,.22);color:#fff}
.auth-btn.admin{background:rgba(32,201,151,.25);border-color:rgba(32,201,151,.5)}

.hero{text-align:center;padding:0 .75rem 0;flex-shrink:0}
.hero .line1{
  font-size:clamp(.62rem,1.8vw,.85rem);font-weight:700;letter-spacing:.05em;
  color:rgba(255,255,255,.88);text-shadow:0 1px 8px rgba(0,0,0,.4);
}
.hero .line2{
  margin-top:clamp(.05rem,.25vh,.15rem);
  font-size:clamp(.9rem,3vw,1.55rem);font-weight:800;letter-spacing:.03em;line-height:1.15;
  background:linear-gradient(90deg,#fff 0%,#b8dcff 45%,#fff 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent;
  filter:drop-shadow(0 2px 10px rgba(0,40,100,.45));
}

.stage{
  flex:1 1 auto;min-height:0;
  display:flex;align-items:center;justify-content:center;padding:0;
}
.eco{
  --size: min(98vw, calc(100dvh - 5.8rem), 760px);
  position:relative;width:var(--size);height:var(--size);
}

.orbit-track{
  position:absolute;left:50%;top:50%;border-radius:50%;pointer-events:none;
  border:1px solid rgba(140,200,255,.22);transform:translate(-50%,-50%);
}
.orbit-track.t1{width:42%;height:42%;border-style:dashed;border-color:rgba(160,210,255,.4);animation:spinOrbit 90s linear infinite}
.orbit-track.t2{width:70%;height:70%;border-color:rgba(100,180,255,.28)}
.orbit-track.t3{width:36%;height:36%;border-color:rgba(120,190,255,.22)}
.orbit-track.t4{width:82%;height:82%;border:1px dashed rgba(255,255,255,.1);animation:spinOrbit 130s linear infinite reverse}
@keyframes spinOrbit{to{transform:translate(-50%,-50%) rotate(360deg)}}

.sun-rays{
  position:absolute;left:50%;top:50%;width:40%;height:40%;
  transform:translate(-50%,-50%);pointer-events:none;z-index:1;opacity:.24;border-radius:50%;
  background:conic-gradient(from 0deg,
    transparent 0deg,rgba(100,180,255,.14) 6deg, transparent 14deg,
    transparent 45deg, rgba(100,180,255,.12) 52deg, transparent 60deg,
    transparent 90deg, rgba(100,180,255,.13) 97deg, transparent 105deg,
    transparent 135deg, rgba(100,180,255,.1) 142deg, transparent 150deg,
    transparent 180deg, rgba(100,180,255,.14) 187deg, transparent 195deg,
    transparent 225deg, rgba(100,180,255,.11) 232deg, transparent 240deg,
    transparent 270deg, rgba(100,180,255,.13) 277deg, transparent 285deg,
    transparent 315deg, rgba(100,180,255,.1) 322deg, transparent 330deg, transparent 360deg);
  animation:spinOrbit 70s linear infinite;
}

.planet-dot{
  position:absolute;left:50%;top:50%;width:5px;height:5px;margin:-2.5px;
  border-radius:50%;pointer-events:none;z-index:3;
  background:radial-gradient(circle,#fff 0%,#6ec8ff 45%,transparent 70%);
  box-shadow:0 0 8px #5eb8ff;
  --pr: calc(var(--size) * 0.36);
  animation:planetOrbit var(--dur,28s) linear infinite;
  transform:rotate(var(--a0,0deg)) translateY(calc(-1 * var(--pr)));
}
@keyframes planetOrbit{
  to{transform:rotate(calc(var(--a0,0deg) + 360deg)) translateY(calc(-1 * var(--pr)))}
}

/* Logo giữa */
.eco-core{
  position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  width:32%;height:32%;z-index:5;
  display:flex;align-items:center;justify-content:center;
}
.core-glow{
  position:absolute;inset:-16%;border-radius:50%;pointer-events:none;
  background:radial-gradient(circle,rgba(100,190,255,.48) 0%, rgba(40,120,220,.16) 42%, transparent 70%);
  animation:pulseGlow 3.8s ease-in-out infinite;
}
@keyframes pulseGlow{0%,100%{opacity:.5;transform:scale(.94)}50%{opacity:1;transform:scale(1.06)}}
.logo-wrap{
  position:relative;z-index:2;width:90%;height:90%;border-radius:50%;
  background:radial-gradient(circle at 38% 28%,#fff 0%,#eaf4ff 50%,#cde4ff 100%);
  box-shadow:0 0 0 3px rgba(255,255,255,.7),0 0 0 8px rgba(70,160,255,.28),0 0 32px rgba(80,170,255,.4),0 10px 28px rgba(0,0,0,.4);
  overflow:hidden;display:flex;align-items:center;justify-content:center;
  animation:logoFloat 5s ease-in-out infinite;
}
@keyframes logoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
.logo-wrap img{width:94%;height:94%;object-fit:contain;border-radius:50%}
.logo-fallback{font-size:clamp(1rem,3vw,1.6rem);font-weight:800;color:#c41e3a;text-align:center;line-height:1.1}

/* Chữ vòng logo giữa: nhỏ + sát */
.orbit-svg{
  position:absolute;left:0;top:0;width:100%;height:100%;
  pointer-events:none;overflow:visible;z-index:4;
}
.orbit-svg text{fill:#fff;font-size:5px;font-weight:800;letter-spacing:.2px}
.orbit-spin{transform-origin:100px 100px;animation:orbitSpin 48s linear infinite}
@keyframes orbitSpin{to{transform:rotate(360deg)}}

/*
 * Logo vệ tinh
 * .sat = flex row: luôn [icon|chữ] hoặc [chữ|icon]
 * Tâm icon nằm đúng điểm quỹ đạo → chữ chỉ nằm ngoài mép icon
 */
.eco-node{
  position:absolute;left:50%;top:50%;width:0;height:0;
  --r: calc(var(--size) * 0.41);
  --b: 33px;
  transform:rotate(var(--angle)) translateY(calc(-1 * var(--r))) rotate(calc(-1 * var(--angle)));
  text-decoration:none;color:#fff;z-index:6;
}
.eco-node:hover,.eco-node:focus-within{filter:brightness(1.08);color:#fff}

.eco-node .sat{
  position:absolute;
  top:0;left:0;
  display:flex;
  align-items:center;
  gap:12px;
  animation:satFloat 4.5s ease-in-out infinite;
  animation-delay:var(--delay,0s);
}
/* Phải: [bubble][chữ] — dịch trái nửa bubble để tâm bubble = gốc */
.eco-node.side-right .sat{
  flex-direction:row;
  transform: translate(calc(-1 * var(--b)), -50%);
}
/* Trái: [chữ][bubble] — dịch để tâm bubble = gốc */
.eco-node.side-left .sat{
  flex-direction:row;
  transform: translate(calc(-100% + var(--b)), -50%);
}

@keyframes satFloat{0%,100%{transform:translate(calc(-1 * var(--b)), -50%) translateY(0)}
  50%{transform:translate(calc(-1 * var(--b)), -50%) translateY(-2px)}}
/* float riêng từng bên — override bằng animation trên .sat sẽ conflict; dùng filter trên bubble thay */
.eco-node .sat{animation:none}
.eco-node .bubble{
  animation:satBob 4.5s ease-in-out infinite;
  animation-delay:var(--delay,0s);
}
@keyframes satBob{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}

.eco-node .bubble{
  flex-shrink:0;
  width:calc(var(--b) * 2);height:calc(var(--b) * 2);
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  background:linear-gradient(160deg,#fff 0%,#f0f7ff 50%,#e2efff 100%);
  color:var(--node-color,#0d6efd);
  font-size:1.5rem;
  box-shadow:0 8px 20px rgba(0,0,0,.35),0 0 0 3px rgba(255,255,255,.95),0 0 14px rgba(80,170,255,.28);
  position:relative;
  transition:transform .2s,box-shadow .2s;
}
.eco-node:hover .bubble,.eco-node.show-label .bubble{
  transform:scale(1.08);
  box-shadow:0 10px 24px rgba(0,0,0,.4),0 0 0 3px #fff,0 0 18px var(--node-color);
}

.eco-node .bubble .num{
  position:absolute;top:-4px;right:-4px;
  min-width:20px;height:20px;padding:0 4px;border-radius:999px;
  background:var(--node-color);color:#fff;font-size:.58rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  border:2px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,.3);
}

.eco-node .text-block{
  flex-shrink:0;
  max-width:min(22vw, 230px);
}
.eco-node.side-right .text-block{text-align:left}
.eco-node.side-left .text-block{text-align:right}

.eco-node .label{
  font-size:clamp(.78rem,1.7vw,1rem);
  font-weight:900;line-height:1.15;
  text-shadow:0 2px 12px rgba(0,0,0,.85);
  letter-spacing:.03em;text-transform:uppercase;
  white-space:nowrap;
}
.eco-node .sub{
  font-size:clamp(.54rem,1.1vw,.68rem);
  font-weight:700;line-height:1.2;margin-top:.1rem;
  opacity:.92;letter-spacing:.015em;
  text-transform:uppercase;
  text-shadow:0 1px 8px rgba(0,0,0,.75);
  white-space:nowrap;
}
.eco-node.soon .bubble{opacity:.58;filter:grayscale(.35)}
.eco-node.soon .badge-soon{
  display:inline-block;font-size:.48rem;background:rgba(0,0,0,.5);
  border-radius:4px;padding:.06rem .28rem;margin-top:.08rem;font-weight:600;
  white-space:nowrap;
}

@media (min-width:960px){
  .eco{--size:min(94vw, calc(100dvh - 5.5rem), 780px)}
  .eco-node{--b:36px;--r:calc(var(--size)*0.41)}
  .eco-node .bubble{font-size:1.7rem}
  .eco-node .text-block{max-width:min(24vw, 250px)}
  .eco-node .label{font-size:1.02rem}
  .eco-node .sub{font-size:.72rem}
}

@media (max-width:768px){
  body{overflow-x:hidden;overflow-y:auto;height:auto;min-height:100dvh}
  .eco{--size:min(98vw, calc(100dvh - 8rem), 460px)}
  .eco-node{--b:26px;--r:calc(var(--size)*0.39)}
  .eco-node .bubble{font-size:1.2rem}
  .eco-node .sat{gap:8px}
  .eco-core{width:34%;height:34%}
  .planet-dot{display:none}
  .eco-node .text-block{
    opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s;
    background:rgba(4,24,55,.92);border:1px solid rgba(255,255,255,.22);
    border-radius:10px;padding:.4rem .5rem;
    backdrop-filter:blur(8px);box-shadow:0 8px 22px rgba(0,0,0,.35);
    max-width:min(70vw, 280px);
  }
  .eco-node:hover .text-block,
  .eco-node:focus-within .text-block,
  .eco-node.show-label .text-block{opacity:1;visibility:visible}
  .eco-node .label{font-size:.7rem}
  .eco-node .sub{font-size:.52rem}
}

@media (max-width:400px){
  .eco-node{--b:22px}
  .eco-node .bubble{font-size:1.05rem}
  .hero .line2{font-size:clamp(.88rem,4vw,1.1rem)}
}

.site-footer{
  flex-shrink:0;text-align:center;
  padding:clamp(.25rem,.6vh,.5rem) 1rem;
  font-size:clamp(.58rem,1.2vw,.75rem);line-height:1.3;color:rgba(255,255,255,.7);
  border-top:1px solid rgba(255,255,255,.08);background:rgba(0,10,30,.45);
}
.site-footer .line2{margin-top:.06rem}
.site-footer .line2 strong{font-weight:700;color:#fff}
</style>
</head>
<body>

<div class="topbar">
  <div class="clock" id="liveClock"><i class="bi bi-clock"></i> <span id="clockText">—</span></div>
  <div>
    <?php if ($user): ?>
      <a class="auth-btn admin" href="<?= BASE_URL ?>admin.php"><i class="bi bi-speedometer2"></i> Quản trị</a>
      <a class="auth-btn" href="<?= BASE_URL ?>logout.php" style="margin-left:.3rem"><i class="bi bi-box-arrow-right"></i></a>
    <?php else: ?>
      <a class="auth-btn" href="<?= BASE_URL ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</a>
    <?php endif; ?>
  </div>
</div>

<header class="hero">
  <div class="line1">TRƯỜNG PTDTNT THCS&THPT XÍN MẦN</div>
  <div class="line2">HỆ SINH THÁI QUẢN LÝ NHÀ TRƯỜNG</div>
</header>

<div class="stage">
  <div class="eco" id="ecoStage">
    <div class="orbit-track t4"></div>
    <div class="orbit-track t2"></div>
    <div class="orbit-track t1"></div>
    <div class="orbit-track t3"></div>
    <div class="sun-rays"></div>
    <div class="planet-dot" style="--a0:30deg;--dur:26s"></div>
    <div class="planet-dot" style="--a0:150deg;--dur:34s;--pr:calc(var(--size)*0.30)"></div>
    <div class="planet-dot" style="--a0:260deg;--dur:22s;--pr:calc(var(--size)*0.24)"></div>

    <svg class="orbit-svg" viewBox="0 0 200 200" aria-hidden="true">
      <defs>
        <path id="orbitPath" d="M100,100 m-36,0 a36,36 0 1,1 72,0 a36,36 0 1,1 -72,0" fill="none"/>
      </defs>
      <g class="orbit-spin">
        <text>
          <textPath href="#orbitPath" startOffset="0%" textLength="226" lengthAdjust="spacing">
 *  CHUYỂN ĐỔI SỐ TRONG GIÁO DỤC  *  HỆ SINH THÁI QUẢN LÝ NHÀ TRƯỜNG  * 
          </textPath>
        </text>
      </g>
    </svg>

    <div class="eco-core">
      <div class="core-glow"></div>
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
      $angle = 22.5 + ($i * (360 / max($nModules, 1)));
      $sin = sin(deg2rad($angle));
      $side = ($sin >= 0) ? 'side-right' : 'side-left';
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
      $delay = number_format($i * 0.28, 2) . 's';
    ?>
    <a class="<?= $cls ?>"
       style="--angle:<?= $angle ?>deg;--node-color:<?= e($m['color']) ?>;--delay:<?= $delay ?>"
       href="<?= e($href) ?>"<?= $target ?>
       title="<?= e($m['title'] . ' – ' . $m['subtitle']) ?>">
      <div class="sat">
        <?php if ($side === 'side-left'): ?>
          <div class="text-block">
            <div class="label"><?= e($m['title']) ?></div>
            <div class="sub"><?= e($m['subtitle']) ?></div>
            <?php if ($status === 'soon'): ?><div class="badge-soon">Sắp ra mắt</div><?php endif; ?>
          </div>
          <div class="bubble">
            <i class="bi <?= e($m['icon']) ?>"></i>
            <span class="num"><?= (int)$m['num'] ?></span>
          </div>
        <?php else: ?>
          <div class="bubble">
            <i class="bi <?= e($m['icon']) ?>"></i>
            <span class="num"><?= (int)$m['num'] ?></span>
          </div>
          <div class="text-block">
            <div class="label"><?= e($m['title']) ?></div>
            <div class="sub"><?= e($m['subtitle']) ?></div>
            <?php if ($status === 'soon'): ?><div class="badge-soon">Sắp ra mắt</div><?php endif; ?>
          </div>
        <?php endif; ?>
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
    var el = document.getElementById('clockText');
    if (!el) return;
    el.textContent = days[d.getDay()] + ', '
      + pad(d.getDate()) + '/' + pad(d.getMonth()+1) + '/' + d.getFullYear()
      + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }
  tick(); setInterval(tick, 1000);

  var nodes = document.querySelectorAll('.eco-node');
  nodes.forEach(function(el){
    el.addEventListener('click', function(e){
      if (window.matchMedia('(max-width:768px)').matches) {
        var open = el.classList.contains('show-label');
        nodes.forEach(function(n){ n.classList.remove('show-label'); });
        if (!open) {
          el.classList.add('show-label');
          if (el.classList.contains('soon')) e.preventDefault();
        }
      } else if (el.classList.contains('soon')) {
        e.preventDefault();
        var t = el.querySelector('.label');
        alert('Module "' + (t ? t.textContent.trim() : '') + '" đang được xây dựng.\nVui lòng quay lại sau.');
      }
    });
  });
  document.addEventListener('click', function(e){
    if (!e.target.closest('.eco-node')) {
      nodes.forEach(function(n){ n.classList.remove('show-label'); });
    }
  });
})();
</script>
</body>
</html>
