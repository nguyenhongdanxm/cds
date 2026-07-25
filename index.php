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
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
  color:#fff;
  min-height:100vh;
  display:flex;flex-direction:column;
  overflow-x:hidden;
  background-color:#041530;
  background-image:
    radial-gradient(ellipse 100% 80% at 50% 40%, #0a5cbf 0%, #074a9e 28%, #05306e 55%, #031a3d 78%, #020d22 100%),
    linear-gradient(rgba(40,120,220,.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(40,120,220,.06) 1px, transparent 1px);
  background-size: auto, 48px 48px, 48px 48px;
  background-position: center, center, center;
}
body::before{
  content:'';
  position:fixed;inset:0;pointer-events:none;z-index:0;
  background:
    radial-gradient(circle at 12% 18%, rgba(0,180,255,.14) 0%, transparent 32%),
    radial-gradient(circle at 88% 78%, rgba(80,140,255,.12) 0%, transparent 36%),
    radial-gradient(circle at 50% 50%, transparent 40%, rgba(0,20,50,.35) 100%);
}
body::after{
  content:'';
  position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.18;
  background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M30 0 L60 15 L60 45 L30 60 L0 45 L0 15 Z' fill='none' stroke='%234a9eff' stroke-width='0.6'/%3E%3C/svg%3E");
  background-size:72px 72px;
}

.topbar,.stage,.site-footer{position:relative;z-index:2}

.topbar{
  display:flex;justify-content:space-between;align-items:center;
  padding:.7rem 1.25rem;flex-shrink:0;
}
.clock{
  font-size:clamp(.78rem,1.8vw,.92rem);
  font-weight:600;
  color:rgba(255,255,255,.9);
  letter-spacing:.02em;
  font-variant-numeric:tabular-nums;
  display:flex;align-items:center;gap:.45rem;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.18);
  padding:.35rem .75rem;border-radius:999px;
  backdrop-filter:blur(8px);
}
.clock i{opacity:.75}
.auth-btn{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.45rem 1rem;border-radius:999px;
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.35);
  color:#fff;font-weight:600;font-size:.88rem;
  text-decoration:none;backdrop-filter:blur(10px);
  transition:background .2s,transform .15s,box-shadow .2s;
}
.auth-btn:hover{
  background:rgba(255,255,255,.22);
  box-shadow:0 4px 18px rgba(61,181,255,.35);
  color:#fff;transform:translateY(-1px);
}
.auth-btn.admin{background:rgba(32,201,151,.25);border-color:rgba(32,201,151,.5)}

.stage{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:.5rem 0 0;min-height:0;
}
.eco{
  --size: min(96vw, min(82vh, 760px));
  position:relative;
  width:var(--size);
  height:var(--size);
}

.eco-ring{
  position:absolute;inset:18%;
  border-radius:50%;
  border:2px solid rgba(140,200,255,.4);
  box-shadow:
    0 0 40px rgba(50,150,255,.12) inset,
    0 0 24px rgba(80,160,255,.15);
  pointer-events:none;
}
.eco-ring-outer{
  position:absolute;inset:6%;
  border-radius:50%;
  border:1px dashed rgba(255,255,255,.14);
  pointer-events:none;
}
.eco-ring-mid{
  position:absolute;inset:30%;
  border-radius:50%;
  border:1px solid rgba(100,180,255,.16);
  pointer-events:none;
}
.eco-ring-glow{
  position:absolute;inset:17%;
  border-radius:50%;
  pointer-events:none;
  background:radial-gradient(circle, transparent 62%, rgba(60,150,255,.08) 78%, transparent 88%);
}

.eco-core{
  position:absolute;left:50%;top:50%;
  transform:translate(-50%,-50%);
  width:36%;height:36%;
  z-index:5;
  display:flex;align-items:center;justify-content:center;
}
.core-glow{
  position:absolute;inset:-22%;
  border-radius:50%;
  background:radial-gradient(circle,rgba(80,180,255,.45) 0%, rgba(40,120,220,.15) 45%, transparent 70%);
  animation:pulseGlow 4s ease-in-out infinite;
  pointer-events:none;
}
@keyframes pulseGlow{
  0%,100%{opacity:.55;transform:scale(.96)}
  50%{opacity:1;transform:scale(1.08)}
}
.logo-wrap{
  position:relative;z-index:2;
  width:80%;height:80%;
  border-radius:50%;
  background:radial-gradient(circle at 38% 30%,#ffffff 0%,#e8f3ff 55%, #cfe4ff 100%);
  box-shadow:
    0 0 0 4px rgba(255,255,255,.65),
    0 0 0 10px rgba(70,160,255,.28),
    0 14px 42px rgba(0,0,0,.45),
    0 0 50px rgba(80,170,255,.35);
  overflow:hidden;
  animation:logoFloat 5.5s ease-in-out infinite;
  display:flex;align-items:center;justify-content:center;
}
@keyframes logoFloat{
  0%,100%{transform:translateY(0)}
  50%{transform:translateY(-6px)}
}
.logo-wrap img{
  width:94%;height:94%;object-fit:contain;border-radius:50%;
}
.logo-fallback{
  font-size:clamp(1.2rem,3.5vw,1.9rem);font-weight:800;color:#c41e3a;
  text-align:center;line-height:1.1;
}

.orbit-svg{
  position:absolute;left:50%;top:50%;
  width:122%;height:122%;
  transform:translate(-50%,-50%);
  pointer-events:none;overflow:visible;z-index:1;
}
.orbit-svg text{
  fill:rgba(255,255,255,.95);
  font-size:8.2px;
  font-weight:800;
  letter-spacing:1.1px;
  text-transform:uppercase;
}
.orbit-spin{
  transform-origin:100px 100px;
  animation:orbitSpin 42s linear infinite;
}
@keyframes orbitSpin{to{transform:rotate(360deg)}}

.eco-node{
  position:absolute;
  left:50%;top:50%;
  width:128px;
  margin-left:-64px;
  margin-top:-64px;
  --r: calc(var(--size) * 0.42);
  transform:
    rotate(var(--angle))
    translateY(calc(-1 * var(--r)))
    rotate(calc(-1 * var(--angle)));
  text-align:center;
  text-decoration:none;
  color:#fff;
  z-index:6;
  transition:filter .2s;
}
.eco-node:hover{filter:brightness(1.12);color:#fff}
.eco-node .bubble{
  width:74px;height:74px;border-radius:50%;
  margin:0 auto .5rem;
  display:flex;align-items:center;justify-content:center;
  background:linear-gradient(160deg,#ffffff 0%,#f0f7ff 45%, #e3efff 100%);
  color:var(--node-color,#0d6efd);
  font-size:1.85rem;
  box-shadow:
    0 10px 28px rgba(0,0,0,.32),
    0 0 0 4px rgba(255,255,255,.95),
    0 0 18px rgba(80,160,255,.2);
  position:relative;
  transition:transform .22s,box-shadow .22s;
}
.eco-node:hover .bubble{
  transform:scale(1.12);
  box-shadow:
    0 14px 32px rgba(0,0,0,.38),
    0 0 0 4px #fff,
    0 0 22px var(--node-color);
}
.eco-node .bubble .num{
  position:absolute;top:-5px;right:-5px;
  min-width:24px;height:24px;padding:0 5px;border-radius:999px;
  background:var(--node-color);color:#fff;
  font-size:.7rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 8px rgba(0,0,0,.35);
  border:2px solid #fff;
}
.eco-node .label{
  font-size:clamp(.78rem,1.85vw,.95rem);
  font-weight:800;line-height:1.25;
  text-shadow:0 2px 12px rgba(0,0,0,.75), 0 0 20px rgba(0,40,100,.4);
  max-width:130px;margin:0 auto;
  letter-spacing:.02em;
  text-transform:uppercase;
}
.eco-node .sub{
  font-size:clamp(.65rem,1.45vw,.78rem);
  opacity:.88;line-height:1.25;
  max-width:128px;margin:.2rem auto 0;
  font-weight:500;
  text-shadow:0 1px 8px rgba(0,0,0,.65);
}
.eco-node.soon .bubble{
  opacity:.62;filter:grayscale(.35);
}
.eco-node.soon .badge-soon{
  display:inline-block;font-size:.58rem;
  background:rgba(0,0,0,.5);border-radius:6px;
  padding:.15rem .4rem;margin-top:.2rem;
  letter-spacing:.03em;font-weight:600;
}

@media (max-width:560px){
  .eco{--size:min(98vw, min(72vh, 440px))}
  .eco-node{width:88px;margin-left:-44px;margin-top:-44px;--r:calc(var(--size)*0.40)}
  .eco-node .bubble{width:54px;height:54px;font-size:1.35rem}
  .eco-node .bubble .num{min-width:18px;height:18px;font-size:.58rem}
  .eco-node .sub{display:none}
  .eco-node .label{font-size:.68rem;max-width:86px}
  .eco-core{width:38%;height:38%}
  .orbit-svg text{font-size:6.8px;letter-spacing:.8px}
}

@media (min-width:900px){
  .eco-node{width:150px;margin-left:-75px;margin-top:-75px}
  .eco-node .bubble{width:84px;height:84px;font-size:2.1rem}
  .eco-node .label{font-size:1.02rem;max-width:148px}
  .eco-node .sub{font-size:.82rem;max-width:148px}
  .orbit-svg text{font-size:9px;letter-spacing:1.3px}
}

.site-footer{
  flex-shrink:0;
  text-align:center;
  padding:.85rem 1.2rem 1.05rem;
  font-size:clamp(.7rem,1.5vw,.86rem);
  line-height:1.45;
  color:rgba(255,255,255,.75);
  border-top:1px solid rgba(255,255,255,.1);
  background:rgba(0,10,30,.45);
  backdrop-filter:blur(8px);
}
.site-footer .line2{
  margin-top:.2rem;
  font-size:clamp(.66rem,1.4vw,.8rem);
  opacity:.9;
}
.site-footer .line2 strong{font-weight:700;color:rgba(255,255,255,.95)}
</style>
</head>
<body>

<div class="topbar">
  <div class="clock" id="liveClock" title="Giờ hệ thống">
    <i class="bi bi-clock"></i>
    <span id="clockText">—</span>
  </div>
  <div>
    <?php if ($user): ?>
      <a class="auth-btn admin" href="<?= BASE_URL ?>admin.php">
        <i class="bi bi-speedometer2"></i> Quản trị
      </a>
      <a class="auth-btn" href="<?= BASE_URL ?>logout.php" style="margin-left:.35rem">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    <?php else: ?>
      <a class="auth-btn" href="<?= BASE_URL ?>login.php">
        <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="stage">
  <div class="eco" id="ecoStage" aria-label="Sơ đồ hệ sinh thái">
    <div class="eco-ring-outer"></div>
    <div class="eco-ring-glow"></div>
    <div class="eco-ring"></div>
    <div class="eco-ring-mid"></div>

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
