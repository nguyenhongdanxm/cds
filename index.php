<?php
require_once 'includes/auth.php';
require_once 'includes/modules.php';
$modules = get_ecosystem_modules();
$user = current_user();
$logoPath = BASE_URL . 'assets/logo.png';
$logoExists = file_exists(__DIR__ . '/assets/logo.png');
$nModules = count($modules);
$coreHref = $user
    ? BASE_URL . 'admin.php'
    : BASE_URL . 'login.php?next=' . urlencode(BASE_URL . 'admin.php');
$coreLabel = $user ? 'Mở trang quản trị' : 'Đăng nhập để vào hệ thống';
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
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.14;
  background-image:url("data:image/svg+xml,%3Csvg width='70' height='70' viewBox='0 0 70 70' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M35 2 L68 18 L68 52 L35 68 L2 52 L2 18 Z' fill='none' stroke='%2350a0ff' stroke-width='0.5'/%3E%3C/svg%3E");
  background-size:80px 80px;
}

.binary-rain{
  position:fixed;inset:0;z-index:0;
  pointer-events:none;overflow:hidden;
  opacity:.11;
}
.binary-col{
  position:absolute;top:0;
  font-family:'Courier New',Consolas,monospace;
  font-size:12px;line-height:1.15;font-weight:600;
  color:rgba(150,210,255,.9);
  white-space:pre;
  text-shadow:0 0 6px rgba(80,160,255,.3);
  animation:binaryFall linear infinite;
  will-change:transform;
}
@keyframes binaryFall{
  0%{transform:translateY(-120%)}
  100%{transform:translateY(120vh)}
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
  border:1px solid rgba(140,200,255,.06);transform:translate(-50%,-50%);
  opacity:.35;
}
.orbit-track.t1{width:42%;height:42%;border-style:dashed;border-color:rgba(160,210,255,.08)}
.orbit-track.t2{width:62%;height:62%;border-color:rgba(100,180,255,.05)}
.orbit-track.t3{width:36%;height:36%;border-color:rgba(120,190,255,.05)}
.orbit-track.t4{width:74%;height:74%;border:1px dashed rgba(255,255,255,.04)}

.sat-link{
  position:absolute;left:50%;top:50%;
  width:68%;height:68%;
  transform:translate(-50%,-50%);
  border-radius:50%;
  pointer-events:none;z-index:2;
}
.sat-link::before{
  content:'';position:absolute;inset:0;border-radius:50%;
  border:1.5px solid rgba(140,200,255,.22);
  box-shadow:0 0 12px rgba(80,160,255,.12),inset 0 0 12px rgba(80,160,255,.06);
}
.sat-link .trail{
  position:absolute;inset:-2px;border-radius:50%;
  background:conic-gradient(
    from 0deg,
    transparent 0deg,
    rgba(160,210,255,.05) 20deg,
    rgba(180,220,255,.45) 40deg,
    rgba(255,255,255,.55) 48deg,
    rgba(180,220,255,.35) 58deg,
    transparent 85deg,
    transparent 140deg,
    rgba(140,200,255,.08) 160deg,
    rgba(160,210,255,.3) 175deg,
    rgba(200,230,255,.4) 185deg,
    transparent 210deg,
    transparent 360deg
  );
  -webkit-mask:radial-gradient(farthest-side, transparent calc(50% - 2px), #000 calc(50% - 1px), #000 calc(50% + 1px), transparent calc(50% + 2.5px));
  mask:radial-gradient(farthest-side, transparent calc(50% - 2px), #000 calc(50% - 1px), #000 calc(50% + 1px), transparent calc(50% + 2.5px));
  animation:trailSpin 28s linear infinite;
  opacity:.9;
}
.sat-link .trail.t2{
  animation-duration:42s;animation-direction:reverse;opacity:.55;filter:blur(.5px);
}
@keyframes trailSpin{to{transform:rotate(360deg)}}

.unlock-layer{
  position:absolute;left:50%;top:50%;
  width:48%;height:48%;
  transform:translate(-50%,-50%);
  pointer-events:none;z-index:3;
}
.unlock-layer .gear-ring{position:absolute;inset:0;animation:spinReverse 56s linear infinite}
.unlock-layer .globe-ring{position:absolute;inset:0;animation:spinReverse 72s linear infinite}
@keyframes spinReverse{to{transform:rotate(-360deg)}}
.unlock-layer .gear-ring svg{
  width:100%;height:100%;opacity:.28;
  filter:drop-shadow(0 0 6px rgba(100,180,255,.25));
}
.unlock-layer .globe-dot{
  position:absolute;left:50%;top:50%;
  width:22px;height:22px;margin:-11px;
  display:flex;align-items:center;justify-content:center;
  color:rgba(180,220,255,.55);font-size:1rem;
  filter:drop-shadow(0 0 4px rgba(80,160,255,.35));
  transform:rotate(var(--a)) translateY(calc(-1 * var(--gr))) rotate(calc(-1 * var(--a)));
  --gr:46%;
}
.unlock-layer .globe-dot i{font-size:1.05rem}

.sun-rays{
  position:absolute;left:50%;top:50%;width:40%;height:40%;
  transform:translate(-50%,-50%);pointer-events:none;z-index:1;opacity:.18;border-radius:50%;
  background:conic-gradient(from 0deg,
    transparent 0deg,rgba(100,180,255,.12) 6deg, transparent 14deg,
    transparent 45deg,rgba(100,180,255,.1) 52deg, transparent 60deg,
    transparent 90deg,rgba(100,180,255,.11) 97deg, transparent 105deg,
    transparent 135deg,rgba(100,180,255,.08) 142deg, transparent 150deg,
    transparent 180deg,rgba(100,180,255,.12) 187deg, transparent 195deg,
    transparent 225deg,rgba(100,180,255,.09) 232deg, transparent 240deg,
    transparent 270deg,rgba(100,180,255,.11) 277deg, transparent 285deg,
    transparent 315deg,rgba(100,180,255,.08) 322deg, transparent 330deg, transparent 360deg);
  animation:spinOrbit 70s linear infinite;
}
@keyframes spinOrbit{to{transform:translate(-50%,-50%) rotate(360deg)}}

.planet-dot{
  position:absolute;left:50%;top:50%;width:5px;height:5px;margin:-2.5px;
  border-radius:50%;pointer-events:none;z-index:3;
  background:radial-gradient(circle,#fff 0%,#6ec8ff 45%,transparent 70%);
  box-shadow:0 0 8px #5eb8ff;opacity:.7;
  --pr:calc(var(--size)*0.32);
  animation:planetOrbit var(--dur,28s) linear infinite;
  transform:rotate(var(--a0,0deg)) translateY(calc(-1 * var(--pr)));
}
@keyframes planetOrbit{
  to{transform:rotate(calc(var(--a0,0deg)+360deg)) translateY(calc(-1 * var(--pr)))}
}

.eco-core{
  position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  width:34%;height:34%;z-index:5;
  display:flex;align-items:center;justify-content:center;
}
.core-click-rays{
  position:absolute;left:50%;top:50%;width:210%;height:210%;
  transform:translate(-50%,-50%) scale(.25) rotate(0deg);
  pointer-events:none;z-index:0;opacity:0;border-radius:50%;
  background:repeating-conic-gradient(
    from 0deg,
    rgba(255,255,255,.95) 0deg 2deg,
    rgba(70,190,255,.72) 2deg 5deg,
    transparent 5deg 15deg
  );
  -webkit-mask:radial-gradient(circle,transparent 0 24%,#000 32% 58%,transparent 76%);
  mask:radial-gradient(circle,transparent 0 24%,#000 32% 58%,transparent 76%);
  filter:drop-shadow(0 0 10px #63c9ff) drop-shadow(0 0 24px rgba(62,157,255,.9));
}
.eco-core.core-activating .core-click-rays{animation:coreBurst .68s ease-out both}
@keyframes coreBurst{
  0%{opacity:0;transform:translate(-50%,-50%) scale(.25) rotate(0deg)}
  28%{opacity:1}
  100%{opacity:0;transform:translate(-50%,-50%) scale(1.15) rotate(16deg)}
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
  cursor:pointer;text-decoration:none;
}
.logo-wrap.is-authenticated{
  box-shadow:0 0 0 3px #fff,0 0 0 9px rgba(89,205,255,.48),0 0 38px rgba(105,215,255,.86),0 0 74px rgba(35,130,255,.52),0 10px 28px rgba(0,0,0,.4);
}
.logo-wrap:hover,.logo-wrap:focus-visible{
  filter:brightness(1.08);outline:none;
  box-shadow:0 0 0 4px #fff,0 0 0 11px rgba(88,205,255,.48),0 0 45px rgba(100,220,255,.9),0 0 84px rgba(40,135,255,.58),0 12px 30px rgba(0,0,0,.42);
}
.eco-core.core-activating .logo-wrap{
  animation:none;transform:scale(1.08);
  filter:brightness(1.18) saturate(1.12);
}
@keyframes logoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
.logo-wrap img{width:94%;height:94%;object-fit:contain;border-radius:50%}
.logo-fallback{font-size:clamp(1rem,3vw,1.6rem);font-weight:800;color:#c41e3a;text-align:center;line-height:1.1}

.orbit-svg{
  position:absolute;left:0;top:0;width:100%;height:100%;
  pointer-events:none;overflow:visible;z-index:4;
}
.orbit-svg text{fill:#fff;font-size:5px;font-weight:800;letter-spacing:.2px}
.orbit-spin{transform-origin:100px 100px;animation:orbitSpin 48s linear infinite}
@keyframes orbitSpin{to{transform:rotate(360deg)}}

/* —— Vệ tinh —— */
.eco-node{
  position:absolute;left:50%;top:50%;width:0;height:0;
  --r:calc(var(--size)*0.34);
  --b:32px;
  --gap:14px;
  transform:rotate(var(--angle)) translateY(calc(-1 * var(--r))) rotate(calc(-1 * var(--angle)));
  text-decoration:none;color:#fff;z-index:6;
}
.eco-node:hover,.eco-node:focus-within{color:#fff}

.eco-node .bubble{
  position:absolute;z-index:2;
  left:calc(-1 * var(--b));
  top:calc(-1 * var(--b));
  width:calc(var(--b)*2);
  height:calc(var(--b)*2);
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  background:radial-gradient(circle at 32% 28%, #fff 0%, #f5faff 45%, #e3f0ff 100%);
  color:var(--node-color,#0d6efd);
  font-size:1.45rem;
  border:2.5px solid rgba(255,255,255,.95);
  box-shadow:
    0 4px 0 rgba(0,40,100,.12),
    0 10px 24px rgba(0,0,0,.32),
    0 0 0 1px rgba(255,255,255,.5),
    0 0 18px rgba(80,170,255,.3),
    inset 0 2px 10px rgba(255,255,255,.85),
    inset 0 -3px 8px rgba(0,60,140,.06);
  transition:transform .3s cubic-bezier(.34,1.4,.64,1), box-shadow .3s ease, filter .3s ease;
  animation:satBob 4.5s ease-in-out infinite;
  animation-delay:var(--delay,0s);
}
.eco-node .bubble i{
  filter:drop-shadow(0 1px 2px rgba(0,40,100,.15));
  transition:transform .3s cubic-bezier(.34,1.4,.64,1);
}
@keyframes satBob{
  0%,100%{transform:translateY(0)}
  50%{transform:translateY(-2px)}
}
.eco-node:hover .bubble,
.eco-node.show-label .bubble{
  animation:none;
  transform:translateY(-9px) scale(1.14);
  filter:brightness(1.06);
  box-shadow:
    0 8px 0 rgba(0,40,100,.1),
    0 18px 36px rgba(0,0,0,.35),
    0 0 0 3px #fff,
    0 0 22px var(--node-color),
    0 0 48px var(--node-color),
    0 0 72px rgba(80,170,255,.35),
    inset 0 2px 12px rgba(255,255,255,.95);
}
.eco-node:hover .bubble i{transform:scale(1.08)}
.eco-node .bubble .num{
  position:absolute;top:-5px;right:-5px;
  min-width:22px;height:22px;padding:0 5px;border-radius:999px;
  background:var(--node-color);
  color:#fff;font-size:.58rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  border:2px solid #fff;
  box-shadow:0 2px 8px rgba(0,0,0,.3);
  transition:transform .3s cubic-bezier(.34,1.4,.64,1);
}
.eco-node:hover .bubble .num{transform:scale(1.12)}

/*
 * Chữ: neo CHẮC từ mép bubble ra ngoài
 *  Phải: left = +b+gap, translateY(-50%)
 *  Trái: left = -b-gap, translate(-100%, -50%)  ← mép phải chữ = mép trái bubble
 */
.eco-node .text-block{
  position:absolute;
  z-index:1;
  top:0;
  width:max-content;
  max-width:min(24vw, 260px);
  pointer-events:none;
  white-space:nowrap;
}
.eco-node.side-right .text-block{
  left:calc(var(--b) + var(--gap));
  transform:translateY(-50%);
  text-align:left;
}
.eco-node.side-left .text-block{
  left:calc(-1 * var(--b) - var(--gap));
  transform:translate(-100%, -50%);
  text-align:right;
}
.eco-node.side-right:hover .text-block,
.eco-node.side-right.show-label .text-block{
  transform:translateY(calc(-50% - 6px));
}
.eco-node.side-left:hover .text-block,
.eco-node.side-left.show-label .text-block{
  transform:translate(-100%, calc(-50% - 6px));
}

.eco-node .label{
  font-size:clamp(.78rem,1.7vw,1rem);
  font-weight:900;line-height:1.15;
  text-shadow:0 2px 12px rgba(0,0,0,.85);
  letter-spacing:.03em;text-transform:uppercase;
}
.eco-node .sub{
  font-size:clamp(.54rem,1.1vw,.68rem);
  font-weight:700;line-height:1.2;margin-top:.1rem;
  opacity:.92;letter-spacing:.015em;
  text-transform:uppercase;
  text-shadow:0 1px 8px rgba(0,0,0,.75);
}
.eco-node.soon .bubble{opacity:.78;filter:grayscale(.2)}
.eco-node.soon:hover .bubble{opacity:1;filter:grayscale(0)}
.eco-node.soon .badge-soon{
  display:inline-block;font-size:.48rem;background:rgba(0,0,0,.55);
  border-radius:4px;padding:.06rem .28rem;margin-top:.08rem;font-weight:600;
}

@media (min-width:960px){
  .eco{--size:min(94vw, calc(100dvh - 5.5rem), 780px)}
  .eco-node{--b:35px;--r:calc(var(--size)*0.34);--gap:16px}
  .eco-node .bubble{font-size:1.65rem}
  .eco-node .text-block{max-width:min(26vw, 280px)}
  .eco-node .label{font-size:1.02rem}
  .eco-node .sub{font-size:.72rem}
  .unlock-layer{width:46%;height:46%}
  .sat-link{width:68%;height:68%}
}

@media (max-width:768px){
  body{overflow-x:hidden;overflow-y:auto;height:auto;min-height:100dvh}
  .eco{--size:min(98vw, calc(100dvh - 8rem), 460px)}
  .eco-node{--b:26px;--r:calc(var(--size)*0.34);--gap:10px}
  .eco-node .bubble{font-size:1.2rem}
  .eco-core{width:36%;height:36%}
  .planet-dot{display:none}
  .unlock-layer{width:50%;height:50%;opacity:.85}
  .sat-link{width:68%;height:68%}
  .binary-rain{opacity:.08}
  .eco-node .text-block{
    opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s;z-index:8;
    background:rgba(4,24,55,.92);border:1px solid rgba(255,255,255,.22);
    border-radius:10px;padding:.4rem .5rem;
    backdrop-filter:blur(8px);box-shadow:0 8px 22px rgba(0,0,0,.35);
    max-width:min(70vw, 280px);
    white-space:normal;
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

<div class="binary-rain" id="binaryRain" aria-hidden="true"></div>

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
    <div class="planet-dot" style="--a0:150deg;--dur:34s;--pr:calc(var(--size)*0.28)"></div>
    <div class="planet-dot" style="--a0:260deg;--dur:22s;--pr:calc(var(--size)*0.22)"></div>

    <div class="sat-link" aria-hidden="true">
      <div class="trail"></div>
      <div class="trail t2"></div>
    </div>

    <div class="unlock-layer" aria-hidden="true">
      <div class="gear-ring">
        <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="100" cy="100" r="78" stroke="rgba(160,210,255,0.55)" stroke-width="1.2" stroke-dasharray="6 4.5"/>
          <circle cx="100" cy="100" r="70" stroke="rgba(140,200,255,0.35)" stroke-width="0.8"/>
          <circle cx="100" cy="100" r="62" stroke="rgba(120,180,255,0.25)" stroke-width="0.6" stroke-dasharray="2 3"/>
          <g stroke="rgba(180,220,255,0.5)" stroke-width="2.2" stroke-linecap="round">
            <line x1="100" y1="18" x2="100" y2="28"/><line x1="100" y1="172" x2="100" y2="182"/>
            <line x1="18" y1="100" x2="28" y2="100"/><line x1="172" y1="100" x2="182" y2="100"/>
            <line x1="41.5" y1="41.5" x2="48.5" y2="48.5"/><line x1="151.5" y1="151.5" x2="158.5" y2="158.5"/>
            <line x1="158.5" y1="41.5" x2="151.5" y2="48.5"/><line x1="48.5" y1="151.5" x2="41.5" y2="158.5"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(15 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(30 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(45 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(60 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(75 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(105 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(120 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(135 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(150 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(165 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(195 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(210 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(225 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(240 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(255 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(285 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(300 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(315 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(330 100 100)"/>
            <line x1="100" y1="22" x2="100" y2="30" transform="rotate(345 100 100)"/>
          </g>
        </svg>
      </div>
      <div class="globe-ring">
        <div class="globe-dot" style="--a:0deg"><i class="bi bi-globe2"></i></div>
        <div class="globe-dot" style="--a:90deg"><i class="bi bi-globe-americas"></i></div>
        <div class="globe-dot" style="--a:180deg"><i class="bi bi-globe-asia-australia"></i></div>
        <div class="globe-dot" style="--a:270deg"><i class="bi bi-globe-europe-africa"></i></div>
      </div>
    </div>

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
      <div class="core-click-rays" aria-hidden="true"></div>
      <a class="logo-wrap core-link<?= $user ? ' is-authenticated' : '' ?>"
         href="<?= e($coreHref) ?>"
         data-authenticated="<?= $user ? '1' : '0' ?>"
         aria-label="<?= e($coreLabel) ?>"
         title="<?= e($coreLabel) ?>">
        <?php if ($logoExists): ?>
          <img src="<?= e($logoPath) ?>" alt="Logo <?= e(SCHOOL_NAME) ?>">
        <?php else: ?>
          <div class="logo-fallback">XÍN<br>MẦN</div>
        <?php endif; ?>
      </a>
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

  (function binaryRain(){
    var host = document.getElementById('binaryRain');
    if (!host) return;
    var cols = Math.min(28, Math.max(12, Math.floor(window.innerWidth / 48)));
    var html = '';
    for (var i = 0; i < cols; i++) {
      var len = 18 + Math.floor(Math.random() * 28);
      var bits = '';
      for (var j = 0; j < len; j++) bits += (Math.random() > 0.5 ? '1' : '0') + '\n';
      var left = (i / cols) * 100 + (Math.random() * 2);
      var dur = 14 + Math.random() * 18;
      var delay = -Math.random() * dur;
      html += '<div class="binary-col" style="left:'+left+'%;animation-duration:'+dur+'s;animation-delay:'+delay+'s">'+bits+'</div>';
    }
    host.innerHTML = html;
  })();

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

  var coreLink = document.querySelector('.core-link');
  if (coreLink) {
    coreLink.addEventListener('click', function(e){
      if (coreLink.getAttribute('data-authenticated') !== '1') return;
      e.preventDefault();
      var core = coreLink.closest('.eco-core');
      if (core && core.classList.contains('core-activating')) return;
      if (core) core.classList.add('core-activating');
      var destination = coreLink.href;
      window.setTimeout(function(){ window.location.href = destination; }, 560);
    });
  }
})();
</script>
</body>
</html>
