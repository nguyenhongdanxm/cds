<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'CDS';
$base = defined('BASE_URL') ? BASE_URL : '/';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chớp Nốt – <?= htmlspecialchars($school) ?></title>
<style>
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:"Segoe UI",system-ui,sans-serif;background:#f4efe6;color:#1a1714}
.app{min-height:100dvh;display:flex;flex-direction:column}
.top{display:flex;align-items:center;gap:10px;padding:10px 14px;background:#2f5d50;color:#f4efe6}
.top a,.top button{color:#f4efe6;background:transparent;border:0;font-weight:700;cursor:pointer;text-decoration:none}
.wrap{max-width:980px;margin:0 auto;padding:18px;flex:1;width:100%}
h1{margin:0 0 8px;font-size:32px}
.muted{color:#6b645c}
.grid{display:grid;gap:10px}
.decks{grid-template-columns:repeat(auto-fill,minmax(160px,1fr))}
.cards{grid-template-columns:repeat(auto-fill,minmax(110px,1fr))}
.tile{border:1px solid #e4dcd0;background:#fffcf7;border-radius:14px;padding:12px;text-align:left;cursor:pointer}
.tile.on{border-color:#2f5d50}
.tile.off{opacity:.4}
.row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
@media(max-width:700px){.row{grid-template-columns:1fr}}
select,input,textarea{width:100%;border:1px solid #e4dcd0;border-radius:10px;padding:8px;margin-top:4px}
.btn{border:0;border-radius:12px;padding:11px 16px;font-weight:800;cursor:pointer;background:#2f5d50;color:#f4efe6}
.btn.ghost{background:#fffcf7;color:#1a1714;border:1px solid #e4dcd0}
.stage{display:grid;place-items:center;padding:12px}
.board{width:min(920px,100%);background:#fffcf7;border:1px solid #e4dcd0;border-radius:20px;overflow:hidden}
.cue{min-height:42vh;display:grid;place-items:center;padding:12px}
.cue svg,.cue img{max-height:42vh;max-width:100%}
.ans{border-top:1px solid #e4dcd0;padding:16px 20px}
.ans b{font-size:clamp(28px,7vw,56px)}
.foot{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;padding:14px}
.credit{text-align:center;font-size:11px;opacity:.7;padding:8px}
.blue{background:#2c4a6e;color:#fff}.red{background:#8a3a32;color:#fff}
</style>
</head>
<body>
<div class="app">
  <div class="top">
    <b>Chớp Nốt</b>
    <span id="prog"></span>
    <span style="margin-left:auto"></span>
    <button type="button" id="fullBtn">Toàn màn hình</button>
    <a href="<?= htmlspecialchars($base) ?>hoclieu.php?tab=games">Thoát</a>
  </div>
  <div class="wrap" id="setup">
    <h1>Chớp Nốt</h1>
    <p class="muted">Chọn dạng nhận biết, bật/tắt thẻ, rồi chiếu. Học sinh giơ tay đọc đáp án.</p>
    <h3>Dạng nhận biết</h3>
    <div class="grid decks" id="decks"></div>
    <h3>Thẻ trong dạng này</h3>
    <div class="grid cards" id="cards"></div>
    <div id="customBox" hidden>
      <h3>Thêm thẻ giáo viên</h3>
      <div class="row">
        <label>Đáp án<input id="cAns" placeholder="Đàn bầu"></label>
        <label>Gợi ý<input id="cHint" placeholder="Nhạc cụ dây"></label>
        <label>Ảnh<input id="cImg" type="file" accept="image/*"></label>
      </div>
      <p><button class="btn" type="button" id="addBtn">Lưu thẻ</button></p>
    </div>
    <div class="row">
      <label>Tốc độ<select id="speed"><option value="manual">Giáo viên bấm hiện</option><option value="3">3 giây</option><option value="5">5 giây</option><option value="8">8 giây</option></select></label>
      <label>Hai đội<select id="team"><option value="0">Tắt</option><option value="1">Bật</option></select></label>
      <label>Âm thanh nốt<select id="sound"><option value="1">Bật</option><option value="0">Tắt</option></select></label>
    </div>
    <p><button class="btn" type="button" id="startBtn">Bắt đầu chiếu</button></p>
  </div>
  <div class="stage" id="play" hidden>
    <div class="board">
      <div class="cue" id="cue"></div>
      <div class="ans" id="ans"></div>
    </div>
  </div>
  <div class="foot" id="foot" hidden></div>
  <div class="credit">Hệ Sinh Thái Quản lý Nhà Trường - Thiết kế bởi thầy giáo Nguyễn Hồng Dân -</div>
</div>
<script>
const META=[
  {id:'mnemonic',title:'Hình gợi nhớ nốt',prompt:'Đọc tên nốt'},
  {id:'staff',title:'Nốt trên khuông',prompt:'Đọc nốt trên khuông'},
  {id:'duration',title:'Trường độ',prompt:'Đọc giá trị trường độ'},
  {id:'instrument',title:'Nhạc cụ',prompt:'Đọc tên nhạc cụ'},
  {id:'symbol',title:'Ký hiệu nhạc',prompt:'Đọc tên ký hiệu'},
  {id:'custom',title:'Thẻ tự thêm',prompt:'Đọc đáp án'}
];
const BUILTIN={
  mnemonic:[
    {id:'do',answer:'Đô',hint:'Đô la',kind:'pic',pic:'do',hz:261.63},
    {id:'re',answer:'Rê',hint:'Củ rễ',kind:'pic',pic:'re',hz:293.66},
    {id:'mi',answer:'Mi',hint:'Mì tôm',kind:'pic',pic:'mi',hz:329.63},
    {id:'fa',answer:'Fa',hint:'Pha lê',kind:'pic',pic:'fa',hz:349.23},
    {id:'sol',answer:'Sol',hint:'Sổ tay',kind:'pic',pic:'sol',hz:392},
    {id:'la',answer:'La',hint:'Lá cây',kind:'pic',pic:'la',hz:440},
    {id:'si',answer:'Si',hint:'Sữa tươi',kind:'pic',pic:'si',hz:493.88}
  ],
  staff:[
    {id:'c4',answer:'Đô',hint:'Đô (C) dưới khuông',kind:'staff',y:130,hz:261.63},
    {id:'d4',answer:'Rê',hint:'Rê (D)',kind:'staff',y:121,hz:293.66},
    {id:'e4',answer:'Mi',hint:'Mi (E) dòng 1',kind:'staff',y:112,hz:329.63},
    {id:'f4',answer:'Fa',hint:'Fa (F) khe 1',kind:'staff',y:103,hz:349.23},
    {id:'g4',answer:'Sol',hint:'Sol (G) dòng 2',kind:'staff',y:94,hz:392},
    {id:'a4',answer:'La',hint:'La (A) khe 2',kind:'staff',y:85,hz:440},
    {id:'b4',answer:'Si',hint:'Si (B) dòng 3',kind:'staff',y:76,hz:493.88},
    {id:'c5',answer:'Đô',hint:'Đô (C) khe 3',kind:'staff',y:67,hz:523.25}
  ],
  duration:[
    {id:'whole',answer:'Nốt tròn',hint:'4 phách',kind:'dur',d:'whole'},
    {id:'half',answer:'Nốt trắng',hint:'2 phách',kind:'dur',d:'half'},
    {id:'quarter',answer:'Nốt đen',hint:'1 phách',kind:'dur',d:'quarter'},
    {id:'eighth',answer:'Nốt móc đơn',hint:'1/2 phách',kind:'dur',d:'eighth'},
    {id:'sixteenth',answer:'Nốt móc kép',hint:'1/4 phách',kind:'dur',d:'sixteenth'},
    {id:'rest',answer:'Dấu lặng đen',hint:'lặng 1 phách',kind:'dur',d:'rest'}
  ],
  instrument:[
    {id:'piano',answer:'Piano',hint:'Dương cầm',kind:'pic',pic:'piano'},
    {id:'organ',answer:'Organ',hint:'Đàn organ',kind:'pic',pic:'organ'},
    {id:'guitar',answer:'Guitar',hint:'Đàn ghi-ta',kind:'pic',pic:'guitar'},
    {id:'violin',answer:'Violin',hint:'Vĩ cầm',kind:'pic',pic:'violin'},
    {id:'flute',answer:'Sáo tây',hint:'Flute',kind:'pic',pic:'flute'},
    {id:'sao',answer:'Sáo trúc',hint:'Nhạc cụ dân tộc',kind:'pic',pic:'sao'},
    {id:'tranh',answer:'Đàn tranh',hint:'Nhạc cụ dân tộc',kind:'pic',pic:'tranh'},
    {id:'drum',answer:'Trống',hint:'Nhạc cụ gõ',kind:'pic',pic:'drum'}
  ],
  symbol:[
    {id:'sharp',answer:'Dấu thăng',hint:'Nâng nửa cung',kind:'sym',s:'sharp'},
    {id:'flat',answer:'Dấu giáng',hint:'Hạ nửa cung',kind:'sym',s:'flat'},
    {id:'nat',answer:'Dấu hoàn nguyên',hint:'Hủy thăng/giáng',kind:'sym',s:'nat'},
    {id:'rep',answer:'Dấu nhắc lại',hint:'Chơi lại đoạn',kind:'sym',s:'rep'},
    {id:'fer',answer:'Fermata',hint:'Kéo dài',kind:'sym',s:'fer'},
    {id:'p',answer:'Piano (p)',hint:'Nhỏ, nhẹ',kind:'sym',s:'p'},
    {id:'f',answer:'Forte (f)',hint:'To, mạnh',kind:'sym',s:'f'},
    {id:'cre',answer:'Crescendo',hint:'Từ nhỏ đến to',kind:'sym',s:'cre'}
  ]
};
const PICS={
  do:'<svg viewBox="0 0 200 160"><rect x="30" y="40" width="120" height="70" rx="6" fill="#2f6b45"/><rect x="40" y="50" width="100" height="50" rx="4" fill="#3f8a58"/><circle cx="90" cy="75" r="16" fill="#c9a227"/></svg>',
  re:'<svg viewBox="0 0 200 160"><path d="M100 20 C70 70 40 90 50 140 C80 120 90 90 100 70 C110 90 120 120 150 140 C160 90 130 70 100 20" fill="#6b4423"/></svg>',
  mi:'<svg viewBox="0 0 200 160"><rect x="60" y="50" width="80" height="80" rx="8" fill="#c45c26"/><ellipse cx="100" cy="50" rx="42" ry="12" fill="#a3461b"/><path d="M80 40 q20 -30 40 0" fill="none" stroke="#bbb" stroke-width="6"/></svg>',
  fa:'<svg viewBox="0 0 200 160"><polygon points="100,20 160,80 100,140 40,80" fill="#9fd4e6" stroke="#2f5d50" stroke-width="4"/><polygon points="100,40 140,80 100,120 60,80" fill="#dff4fb"/></svg>',
  sol:'<svg viewBox="0 0 200 160"><rect x="45" y="30" width="110" height="100" rx="6" fill="#d7c4a3"/><rect x="55" y="45" width="90" height="70" fill="#fff"/><line x1="65" y1="60" x2="135" y2="60" stroke="#bbb"/><line x1="65" y1="75" x2="135" y2="75" stroke="#bbb"/></svg>',
  la:'<svg viewBox="0 0 200 160"><path d="M100 20 C40 60 40 110 100 145 C160 110 160 60 100 20" fill="#3d8a4a"/><line x1="100" y1="40" x2="100" y2="130" stroke="#2c5e34"/></svg>',
  si:'<svg viewBox="0 0 200 160"><rect x="70" y="40" width="60" height="90" rx="8" fill="#dbe7f0"/><rect x="74" y="70" width="52" height="56" fill="#f7fbfc"/><ellipse cx="100" cy="40" rx="28" ry="10" fill="#cfd8e0"/></svg>',
  piano:'<svg viewBox="0 0 220 140"><rect x="20" y="50" width="180" height="60" rx="6" fill="#222"/><rect x="30" y="58" width="160" height="28" fill="#fff"/><rect x="48" y="58" width="12" height="18" fill="#111"/><rect x="78" y="58" width="12" height="18" fill="#111"/><rect x="108" y="58" width="12" height="18" fill="#111"/></svg>',
  organ:'<svg viewBox="0 0 220 140"><rect x="30" y="70" width="160" height="40" fill="#3a2a1a"/><rect x="50" y="20" width="14" height="50" fill="#ddd"/><rect x="80" y="30" width="14" height="40" fill="#ddd"/><rect x="110" y="18" width="14" height="52" fill="#ddd"/><rect x="140" y="28" width="14" height="42" fill="#ddd"/></svg>',
  guitar:'<svg viewBox="0 0 220 140"><circle cx="80" cy="80" r="40" fill="#b56a2b"/><circle cx="80" cy="80" r="14" fill="#222"/><rect x="118" y="72" width="80" height="12" fill="#d9b48a"/></svg>',
  violin:'<svg viewBox="0 0 220 140"><ellipse cx="80" cy="85" rx="28" ry="40" fill="#8a4b1f"/><rect x="74" y="20" width="12" height="40" fill="#c48a4a"/><line x1="140" y1="20" x2="180" y2="120" stroke="#333" stroke-width="4"/></svg>',
  flute:'<svg viewBox="0 0 220 140"><rect x="20" y="64" width="180" height="12" rx="6" fill="#cfd8dc"/><circle cx="70" cy="70" r="4" fill="#333"/><circle cx="100" cy="70" r="4" fill="#333"/><circle cx="130" cy="70" r="4" fill="#333"/></svg>',
  sao:'<svg viewBox="0 0 220 140"><rect x="30" y="64" width="160" height="14" rx="7" fill="#6b8f3c"/><circle cx="80" cy="71" r="4" fill="#2a3d14"/><circle cx="110" cy="71" r="4" fill="#2a3d14"/><circle cx="140" cy="71" r="4" fill="#2a3d14"/></svg>',
  tranh:'<svg viewBox="0 0 220 140"><polygon points="30,100 190,70 190,90 30,120" fill="#c48a4a"/><line x1="50" y1="108" x2="180" y2="82" stroke="#eee"/><line x1="50" y1="100" x2="180" y2="76" stroke="#eee"/></svg>',
  drum:'<svg viewBox="0 0 220 140"><ellipse cx="110" cy="50" rx="50" ry="16" fill="#eee"/><rect x="60" y="50" width="100" height="50" fill="#b03a2e"/><ellipse cx="110" cy="100" rx="50" ry="16" fill="#8a2e24"/></svg>'
};
function staffSvg(y){
  const ledger=y>120?'<line x1="118" x2="172" y1="'+y+'" y2="'+y+'" stroke="#1a1714" stroke-width="2"/>':'';
  return '<svg viewBox="0 0 280 180">'+
    '<path d="M38 40 c0 50 28 68 28 92" fill="none" stroke="#1a1714" stroke-width="5"/>'+
    [40,58,76,94,112].map(l=>'<line x1="70" x2="260" y1="'+l+'" y2="'+l+'" stroke="#1a1714" stroke-width="2"/>').join('')+
    ledger+'<ellipse cx="145" cy="'+y+'" rx="16" ry="11" fill="#1a1714"/>'+
    '<line x1="159" x2="159" y1="'+y+'" y2="'+(y-52)+'" stroke="#1a1714" stroke-width="4"/></svg>';
}
function durSvg(d){
  if(d==='rest') return '<svg viewBox="0 0 200 180"><path d="M96 42 h22 l-18 28 c18 4 18 28-6 36 c22 2 18 30-10 38" fill="none" stroke="#1a1714" stroke-width="8"/></svg>';
  const filled=d!=='whole'&&d!=='half';
  const stem=d!=='whole';
  const f1=d==='eighth'||d==='sixteenth';
  const f2=d==='sixteenth';
  return '<svg viewBox="0 0 200 180"><ellipse cx="88" cy="118" rx="22" ry="15" fill="'+(filled?'#1a1714':'none')+'" stroke="#1a1714" stroke-width="6"/>'+
    (stem?'<line x1="108" x2="108" y1="112" y2="38" stroke="#1a1714" stroke-width="6"/>':'')+
    (f1?'<path d="M108 38 c28 8 36 28 8 42" fill="none" stroke="#1a1714" stroke-width="6"/>':'')+
    (f2?'<path d="M108 54 c28 8 36 28 8 42" fill="none" stroke="#1a1714" stroke-width="6"/>':'')+'</svg>';
}
function symSvg(s){
  const m={
    sharp:'<g stroke="#1a1714" stroke-width="8" fill="none"><line x1="86" y1="28" x2="86" y2="132"/><line x1="122" y1="22" x2="122" y2="126"/><line x1="62" y1="62" x2="148" y2="48"/><line x1="62" y1="102" x2="148" y2="88"/></g>',
    flat:'<g stroke="#1a1714" stroke-width="8" fill="none"><line x1="92" y1="24" x2="92" y2="128"/><path d="M92 128 c48-18 48-70 0-52"/></g>',
    nat:'<g stroke="#1a1714" stroke-width="8" fill="none"><line x1="88" y1="28" x2="88" y2="108"/><line x1="124" y1="52" x2="124" y2="132"/><line x1="88" y1="72" x2="124" y2="58"/><line x1="88" y1="108" x2="124" y2="94"/></g>',
    rep:'<g fill="#1a1714"><rect x="128" y="28" width="10" height="104"/><rect x="144" y="28" width="22" height="104"/><circle cx="108" cy="62" r="8"/><circle cx="108" cy="98" r="8"/></g>',
    fer:'<g fill="#1a1714"><path d="M40 88c20-48 120-48 140 0" fill="none" stroke="#1a1714" stroke-width="8"/><circle cx="110" cy="100" r="10"/></g>',
    p:'<text x="110" y="104" text-anchor="middle" font-size="92" font-style="italic" fill="#1a1714">p</text>',
    f:'<text x="110" y="108" text-anchor="middle" font-size="92" font-style="italic" fill="#1a1714">f</text>',
    cre:'<path d="M36 80 L184 48 L184 112 Z" fill="none" stroke="#1a1714" stroke-width="8"/>'
  };
  return '<svg viewBox="0 0 220 160">'+(m[s]||'')+'</svg>';
}
function cueHtml(c){
  if(c.image) return '<img src="'+c.image+'" alt="">';
  if(c.kind==='staff') return staffSvg(c.y);
  if(c.kind==='dur') return durSvg(c.d);
  if(c.kind==='sym') return symSvg(c.s);
  if(c.kind==='pic') return PICS[c.pic]||c.answer;
  return '<div style="font-size:42px;font-weight:800">'+(c.hint||'?')+'</div>';
}
const KEY='cds-chopnot-v1';
let saved=Object.assign({deck:'mnemonic',off:{},speed:'manual',team:false,sound:true,custom:[]}, JSON.parse(localStorage.getItem(KEY)||'{}'));
let deck=[], i=0, revealed=false, blue=0, red=0, t=null, ctx=null;
function persist(){ localStorage.setItem(KEY, JSON.stringify(saved)); }
function source(){ return saved.deck==='custom' ? saved.custom : BUILTIN[saved.deck]; }
function selected(){ return source().filter(c=>!saved.off[c.id]); }
function renderSetup(){
  document.getElementById('setup').hidden=false;
  document.getElementById('play').hidden=true;
  document.getElementById('foot').hidden=true;
  document.getElementById('decks').innerHTML=META.map(d=>'<button class="tile '+(saved.deck===d.id?'on':'')+'" data-d="'+d.id+'"><b>'+d.title+'</b><div class="muted">'+d.prompt+'</div></button>').join('');
  document.getElementById('cards').innerHTML=source().map(c=>'<button class="tile '+(saved.off[c.id]?'off':'on')+'" data-c="'+c.id+'"><b>'+c.answer+'</b><div class="muted">'+c.hint+'</div></button>').join('')||'<p class="muted">Chưa có thẻ.</p>';
  document.getElementById('customBox').hidden=saved.deck!=='custom';
  document.getElementById('speed').value=saved.speed;
  document.getElementById('team').value=saved.team?'1':'0';
  document.getElementById('sound').value=saved.sound?'1':'0';
}
function playTone(hz){
  if(!saved.sound||!hz) return;
  ctx=ctx||new (window.AudioContext||window.webkitAudioContext)();
  if(ctx.state==='suspended') ctx.resume();
  const o=ctx.createOscillator(), g=ctx.createGain(), now=ctx.currentTime;
  o.frequency.value=hz; o.type='sine'; g.gain.value=0.12; o.connect(g); g.connect(ctx.destination);
  o.start(); g.gain.exponentialRampToValueAtTime(0.0001, now+0.6); o.stop(now+0.62);
}
function showCard(){
  const c=deck[i];
  document.getElementById('cue').innerHTML=cueHtml(c);
  document.getElementById('ans').innerHTML=revealed?('<b>'+c.answer+'</b><div class="muted">'+c.hint+'</div>'):('<div class="muted">'+META.find(m=>m.id===saved.deck).prompt+'</div>');
  document.getElementById('prog').textContent=(i+1)+'/'+deck.length;
  const foot=document.getElementById('foot');
  foot.hidden=false;
  if(!revealed) foot.innerHTML='<button class="btn" id="rev">Hiện đáp án</button>';
  else{
    foot.innerHTML=(saved.team?'<button class="btn blue" id="b1">Điểm Xanh '+blue+'</button><button class="btn red" id="b2">Điểm Đỏ '+red+'</button>':'')+
      '<button class="btn" id="nx">Thẻ tiếp</button>';
  }
}
function start(){
  deck=selected().sort(()=>Math.random()-0.5);
  if(!deck.length){ alert('Chọn ít nhất 1 thẻ.'); return; }
  i=0; revealed=false; blue=red=0;
  document.getElementById('setup').hidden=true;
  document.getElementById('play').hidden=false;
  showCard(); schedule();
}
function schedule(){
  clearTimeout(t);
  if(saved.speed==='manual'||revealed) return;
  t=setTimeout(reveal, Number(saved.speed)*1000);
}
function reveal(){
  if(revealed) return;
  revealed=true; playTone(deck[i].hz); showCard();
}
function next(){
  if(i>=deck.length-1){ alert('Hết vòng'+(saved.team?('\\nXanh '+blue+' · Đỏ '+red):'')); renderSetup(); return; }
  i++; revealed=false; showCard(); schedule();
}
document.getElementById('decks').onclick=e=>{
  const b=e.target.closest('[data-d]'); if(!b) return; saved.deck=b.dataset.d; persist(); renderSetup();
};
document.getElementById('cards').onclick=e=>{
  const b=e.target.closest('[data-c]'); if(!b) return; saved.off[b.dataset.c]=!saved.off[b.dataset.c]; persist(); renderSetup();
};
document.getElementById('speed').onchange=e=>{ saved.speed=e.target.value; persist(); };
document.getElementById('team').onchange=e=>{ saved.team=e.target.value==='1'; persist(); };
document.getElementById('sound').onchange=e=>{ saved.sound=e.target.value==='1'; persist(); };
document.getElementById('startBtn').onclick=start;
document.getElementById('fullBtn').onclick=()=>{ if(!document.fullscreenElement) document.documentElement.requestFullscreen(); else document.exitFullscreen(); };
document.getElementById('foot').onclick=e=>{
  if(e.target.id==='rev') reveal();
  if(e.target.id==='nx') next();
  if(e.target.id==='b1'){ blue++; showCard(); }
  if(e.target.id==='b2'){ red++; showCard(); }
};
document.getElementById('addBtn').onclick=()=>{
  const answer=document.getElementById('cAns').value.trim();
  if(!answer) return;
  const file=document.getElementById('cImg').files[0];
  const push=img=>{
    saved.custom.push({id:'c'+Date.now(),answer,hint:document.getElementById('cHint').value.trim()||'Thẻ tự thêm',kind:'img',image:img});
    persist(); document.getElementById('cAns').value=''; document.getElementById('cHint').value=''; renderSetup();
  };
  if(!file){ push(''); return; }
  const r=new FileReader(); r.onload=()=>push(r.result); r.readAsDataURL(file);
};
document.addEventListener('keydown',e=>{
  if(e.target.matches('input,textarea,select')) return;
  if(e.code==='Space'||e.code==='Enter'){ e.preventDefault(); if(document.getElementById('play').hidden) start(); else if(revealed) next(); else reveal(); }
});
renderSetup();
</script>
</body>
</html>
