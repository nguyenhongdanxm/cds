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
  background-color:#061830;
  background-image:
    radial-gradient(ellipse 90% 70% at 50% 42%, #0e4d9e 0%, #0a356e 38%, #071e3d 68%, #040e1c 100%),
    radial-gradient(circle at 15% 20%, rgba(50,140,255,.12) 0%, transparent 40%),
    radial-gradient(circle at 85% 75%, rgba(30,100,200,.1) 0%, transparent 35%);
}

/* Top bar */
.topbar{
  display:flex;justify-content:space-between;align-items:center;
  padding:.65rem 1.15rem;flex-shrink:0;z-index:30;
}
.clock{
  font-size:clamp(.78rem,1.8vw,.92rem);
  font-weight:600;
  color:rgba(255,255,255,.88);
  letter-spacing:.02em;
  font-variant-numeric:tabular-nums;
  display:flex;align-items:center;gap:.45rem;
}
.clock i{opacity:.7;font-size:.95em}
.auth-btn{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.42rem .95rem;border-radius:999px;
  background:rgba(255,255,255,.1);
  border:1px solid rgba(255,255,255,.32);
  color:#fff;font-weight:600;font-size:.86rem;
  text-decoration:none;backdrop-filter:blur(10px);
  transition:background .2s,transform .15s,box-shadow .2s;
}
.auth-btn:hover{
  background:rgba(255,255,255,.2);
  box-shadow:0 4px 16px rgba(61,181,255,.28);
  color:#fff;transform:translateY(-1px);
}
.auth-btn.admin{background:rgba(32,201,151,.22);border-color:rgba(32,201,151,.45)}

/* Stage */
.stage{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:.25rem 0;min-height:0;
}
.eco{
  --size: min(94vw, min(78vh, 700px));
  position:relative;
  width:var(--size);
  height:var(--size);
}

/* Rings */
.eco-ring{
  position:absolute;inset:16%;
  border-radius:50%;
  border:1.5px solid rgba(130,195,255,.32);
  box-shadow:
    0 0 50px rgba(50,150,255,.07) inset,
    0 0 30px rgba(50,150,255,.05);
  pointer-events:none;
}
.eco-ring-outer{
  position:absolute;inset:4%;
  border-radius:50%;
  border:1px dashed rgba(255,255,255,.1);
  pointer-events:none;
}
.eco-ring-mid{
  position:absolute;inset:28%;
  border-radius:50%;
  border:1px solid rgba(100,180,255,.12);
  pointer-events:none;
}

/* Center core */
.eco-core{
  position:absolute;left:50%;top:50%;
  transform:translate(-50%,-50%);
  width:34%;height:34%;
  z-index:5;
  display:flex;align-items:center;justify-content:center;
}
.core-glow{
  position:absolute;inset:-18%;
  border-radius:50%;
  background:radial-gradient(circle,rgba(80,180,255,.4) 0%, rgba(40,120,220,.12) 45%, transparent 70%);
  animation:pulseGlow 4s ease-in-out infinite;
  pointer-events:none;
}
@keyframes pulseGlow{
  0%,100%{opacity:.5;transform:scale(.96)}
  50%{opacity:1;transform:scale(1.06)}
}
.logo-wrap{
  position:relative;z-index:2;
  width:78%;height:78%;
  border-radius:50%;
  background:radial-gradient(circle at 38% 32%,#ffffff 0%,#dceeff 100%);
  box-shadow:
    0 0 0 3px rgba(255,255,255,.55),
    0 0 0 9px rgba(70,160,255,.22),
    0 12px 40px rgba(0,0,0,.4);
  overflow:hidden;
  animation:logoFloat 5.5s ease-in-out infinite;
  display:flex;align-items:center;justify-content:center;
}
@keyframes logoFloat{
  0%,100%{transform:translateY(0)}
  50%{transform:translateY(-5px)}
}
.logo-wrap img{
  width:94%;height:94%;object-fit:contain;border-radius:50%;
}
.logo-fallback{
  font-size:clamp(1.2rem,3.5vw,1.9rem);font-weight:800;color:#c41e3a;
  text-align:center;line-height:1.1;
}

/* Orbit text around logo */
.orbit-svg{
  position:absolute;left:50%;top:50%;
  width:118%;height:118%;
  transform:translate(-50%,-50%);
  pointer-events:none;overflow:visible;z-index:1;
}
.orbit-svg text{
  fill:rgba(255,255,255,.94);
  font-size:8.5px;
  font-weight:800;
  letter-spacing:1.2px;
  text-transform:uppercase;
}
.orbit-spin{
  transform-origin:100px 100px;
  animation:orbitSpin 40s linear infinite;
}
@keyframes orbitSpin{to{transform:rotate(360deg)}}

/*
 * Module nodes – bán kính theo --size của .eco
 */
.eco-node{
  position:absolute;
  left:50%;top:50%;
  width:100px;
  margin-left:-50px;
  margin-top:-50px;
  --r: calc(var(--size) * 0.40);
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
  width:62px;height:62px;border-radius:50%;
  margin:0 auto .4rem;
  display:flex;align-items:center;justify-content:center;
  background:linear-gradient(145deg,#ffffff 0%,#eef5ff 100%);
  color:var(--node-color,#0d6efd);
  font-size:1.55rem;
  box-shadow:
    0 8px 24px rgba(0,0,0,.3),
    0 0 0 3px rgba(255,255,255,.9);
  position:relative;
  transition:transform .2s,box-shadow .2s;
}
.eco-node:hover .bubble{
  transform:scale(1.1);
  box-shadow:0 10px 28px rgba(0,0,0,.35),0 0 0 3px #fff,0 0 16px var(--node-color);
}
.eco-node .bubble .num{
  position:absolute;top:-4px;right:-4px;
  width:22px;height:22px;border-radius:50%;
  background:var(--node-color);color:#fff;
  font-size:.65rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 6px rgba(0,0,0,.3);
  border:2px solid #fff;
}
.eco-node .label{
  font-size:clamp(.68rem,1.6vw,.82rem);
  font-weight:700;line-height:1.2;
  text-shadow:0 2px 10px rgba(0,0,0,.6);
  max-width:108px;margin:0 auto;
}
.eco-node .sub{
  font-size:clamp(.58rem,1.3vw,.68rem);
  opacity:.78;line-height:1.2;
  max-width:112px;margin:.12rem auto 0;
  text-shadow:0 1px 6px rgba(0,0,0,.5);
}
.eco-node.soon .bubble{
  opacity:.55;filter:grayscale(.4);
}
.eco-node.soon .badge-soon{
  display:inline-block;font-size:.55rem;
  background:rgba(0,0,0,.45);border-radius:5px;
  padding:.1rem .35rem;margin-top:.15rem;
  letter-spacing:.02em;
}

@media (max-width:560px){
  .eco{--size:min(96vw, min(70vh, 420px))}
  .eco-node{width:78px;margin-left:-39px;margin-top:-39px;--r:calc(var(--size)*0.38)}
  .eco-node .bubble{width:48px;height:48px;font-size:1.2rem}
  .eco-node .bubble .num{width:18px;height:18px;font-size:.58rem}
  .eco-node .sub{display:none}
  .eco-node .label{font-size:.62rem;max-width:78px}
  .eco-core{width:36%;height:36%}
  .orbit-svg text{font-size:7px;letter-spacing:.9px}
}

@media (min-width:900px){
  .eco-node{width:120px;margin-left:-60px;margin-top:-60px}
  .eco-node .bubble{width:70px;height:70px;font-size:1.7rem}
  .eco-node .label{font-size:.88rem;max-width:120px}
  .orbit-svg text{font-size:9px;letter-spacing:1.4px}
}

/* Footer */
.site-footer{
  flex-shrink:0;
  text-align:center;
  padding:.8rem 1.2rem 1rem;
  font-size:clamp(.7rem,1.5vw,.84rem);
  line-height:1.45;
  color:rgba(255,255,255,.7);
  border-top:1px solid rgba(255,255,255,.07);
  background:rgba(0,0,0,.2);
}
.site-footer .line2{
  margin-top:.18rem;
  font-size:clamp(.66rem,1.4vw,.78rem);
  opacity:.88;
}
.site-footer .line2 strong{font-weight:600;color:rgba(255,255,255,.92)}
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
            <!-- Chu vi ~ 2π·82 ≈ 515: textLength để chữ kín vòng, 2 nửa + ký tự ngăn -->
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
