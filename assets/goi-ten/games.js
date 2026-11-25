
/* Adapted from the user-provided Gọi Tên Vui page: egg and UFO only. */
/* ================= shared helpers ================= */
const $=id=>document.getElementById(id);
const cv=$('game'),ctx=cv.getContext('2d'),W=1000,H=600;
const rnd=(a,b)=>a+Math.random()*(b-a);
const pick=a=>a[Math.random()*a.length|0];
const shuffle=a=>{for(let i=a.length-1;i>0;i--){const j=Math.random()*(i+1)|0;[a[i],a[j]]=[a[j],a[i]]}return a};
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const esc=s=>s.replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
const PAL=['#3c6fd1','#2f8f7a','#d9472b','#8a4fc4','#e08a1e','#d6457f','#1f9bb0','#6b8e23'];
const FONT="'Be Vietnam Pro',system-ui,sans-serif",DFONT="'Baloo 2','Trebuchet MS',system-ui,sans-serif";
let storageClass='manual';const storagePrefix='cds-call-'+document.body.dataset.user+'-'+document.body.dataset.game+'-';
const storageKey=k=>storagePrefix+(k==='selectedClass'?'settings':storageClass)+'-'+k;
function load(k,d){try{const v=localStorage.getItem(storageKey(k));return v===null?d:JSON.parse(v)}catch(e){return d}}
function save(k,v){try{localStorage.setItem(storageKey(k),JSON.stringify(v))}catch(e){}}
const IMG={};for(const k in ASSETS){const im=new Image();im.onload=()=>{IMG[k]=im};im.src=ASSETS[k]}
const scaleFor=n=>(n>34?.58:n>24?.68:n>14?.82:1)*1.16;

let audio=null,master=null,silentEl=null;
function silentWav(){const n=4000,b=new Uint8Array(44+n),v=new DataView(b.buffer),w=(o,t)=>{for(let i=0;i<t.length;i++)b[o+i]=t.charCodeAt(i)};
  w(0,'RIFF');v.setUint32(4,36+n,true);w(8,'WAVE');w(12,'fmt ');v.setUint32(16,16,true);v.setUint16(20,1,true);v.setUint16(22,1,true);v.setUint32(24,8000,true);v.setUint32(28,8000,true);v.setUint16(32,1,true);v.setUint16(34,8,true);w(36,'data');v.setUint32(40,n,true);b.fill(128,44);
  let t='';for(let i=0;i<b.length;i++)t+=String.fromCharCode(b[i]);return 'data:audio/wav;base64,'+btoa(t)}
function ensureAudio(){try{if(!audio){const AC=window.AudioContext||window.webkitAudioContext;if(!AC)return null;audio=new AC();
    const comp=audio.createDynamicsCompressor();master=audio.createGain();master.gain.value=1;master.connect(comp);comp.connect(audio.destination)}
  if(audio.state!=='running'&&audio.resume)audio.resume().catch(()=>{})}catch(e){}return audio}
function unlockAudio(){if(!$('sound').checked)return;window.CDSCallingAudio?.unlock();ensureAudio();try{if(navigator.audioSession)navigator.audioSession.type='playback'}catch(e){}
  try{if(!silentEl){silentEl=new Audio(silentWav());silentEl.loop=true;silentEl.setAttribute('playsinline','')}if(silentEl.paused){const pr=silentEl.play();if(pr&&pr.catch)pr.catch(()=>{})}}catch(e){}}
['pointerdown','touchend','keydown','click'].forEach(ev=>document.addEventListener(ev,unlockAudio,{passive:true,capture:true}));
function snd(f1,f2,d,type='square',v=.08,delay=0){
  if(!$('sound').checked||document.hidden)return;const a=ensureAudio();if(!a||!master)return;
  try{const t=a.currentTime+delay+.01,o=a.createOscillator(),g=a.createGain(),vol=Math.min(.9,v*3.5)*(window.CDSCallingAudio?.effectsVolume??1);o.type=type;
    o.frequency.setValueAtTime(f1,t);o.frequency.exponentialRampToValueAtTime(Math.max(20,f2),t+d);
    g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(vol,t+.008);g.gain.exponentialRampToValueAtTime(.0001,t+d);
    o.connect(g);g.connect(master);o.start(t);o.stop(t+d+.05)}catch(e){}
}
const cluck=()=>{if(window.CDSCallingAudio?.effect('cluck'))return;snd(900,500,.09);snd(1100,600,.12,'square',.08,.11)};
const fanfare=()=>[523,659,784,1047].forEach((f,i)=>snd(f,f*1.01,.28,'triangle',.12,i*.12));

function rr(x,y,w,h,r){r=Math.min(r,w/2,h/2);ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath()}
function ell(x,y,a,b,rot=0){ctx.beginPath();ctx.ellipse(x,y,Math.max(.1,a),Math.max(.1,b),rot,0,Math.PI*2)}
function fitText(t,maxW){if(ctx.measureText(t).width<=maxW)return t;while(t.length>1&&ctx.measureText(t+'…').width>maxW)t=t.slice(0,-1);return t+'…'}
function label(x,y,text,s,hi,maxW=160){
  const fs=Math.max(10,13*s);ctx.font=`700 ${fs}px ${FONT}`;const tx=fitText(text,maxW);
  const w=ctx.measureText(tx).width+12*Math.max(.7,s),h=fs+6;
  ctx.fillStyle=hi?'#f2b632':'rgba(255,255,255,.93)';rr(x-w/2,y,w,h,h/2);ctx.fill();
  ctx.fillStyle='#2d2416';ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(tx,x,y+h/2+.5);
}
function sky(top,bot,y0=0,y1=H){const g=ctx.createLinearGradient(0,y0,0,y1);g.addColorStop(0,top);g.addColorStop(1,bot);ctx.fillStyle=g;ctx.fillRect(0,y0,W,y1-y0)}
function cloud(x,y,s=1){ctx.fillStyle='rgba(255,255,255,.95)';ctx.beginPath();ctx.arc(x,y,22*s,0,7);ctx.arc(x+26*s,y-10*s,28*s,0,7);ctx.arc(x+56*s,y,22*s,0,7);ctx.rect(x,y,56*s,22*s);ctx.fill()}
function tree(x,y,s=1,c='#4d9140'){ctx.fillStyle='#7a5432';ctx.fillRect(x-6*s,y,12*s,45*s);ctx.fillStyle=c;ctx.beginPath();ctx.arc(x,y,30*s,0,7);ctx.arc(x-20*s,y+10*s,22*s,0,7);ctx.arc(x+20*s,y+10*s,22*s,0,7);ctx.fill()}
function eggShape(x,y,s){ctx.fillStyle='#fffaf0';ctx.strokeStyle='#d8c9a8';ctx.lineWidth=1.5;ell(x,y,8*s,10*s);ctx.fill();ctx.stroke()}
function cake(x,y,s){
  ctx.fillStyle='#f6c9a0';ctx.beginPath();ctx.moveTo(x-11*s,y+6*s);ctx.lineTo(x+11*s,y+6*s);ctx.lineTo(x+11*s,y-4*s);ctx.lineTo(x-11*s,y-4*s);ctx.fill();
  ctx.fillStyle='#f48fb1';ctx.fillRect(x-11*s,y-8*s,22*s,5*s);ctx.fillStyle='#fff';ctx.fillRect(x-11*s,y-1*s,22*s,2*s);
  ctx.fillStyle='#e53935';ctx.beginPath();ctx.arc(x,y-10*s,3.2*s,0,7);ctx.fill();
}

/* kid figure shared by several games */
function mkKid(p,i,x,y,s){return{p,v:((p.i!=null?p.i:i)%4),x,y,vx:0,vy:0,dir:Math.random()<.5?-1:1,ph:rnd(0,6),s,hair:Math.random()<.5?0:1,shorts:PAL[i%PAL.length],hold:null,pose:'idle',hi:false,spd:rnd(150,205),wan:rnd(0,6)}}
function drawKidSprite(k,t,im){
  const s=k.s,mv=Math.hypot(k.vx,k.vy)>5,scared=k.pose==='scared'||k.pose==='run',Hh=92*s,w=Hh*im.width/im.height;
  const bob=mv?Math.abs(Math.sin(t*14+k.ph))*6*s:(scared?Math.abs(Math.sin(t*22+k.ph))*3*s:Math.abs(Math.sin(t*2+k.ph))*1.2*s);
  ctx.fillStyle='rgba(60,40,10,.22)';ell(k.x,k.y+2,20*s,5.5*s);ctx.fill();
  if(k.shirt){ctx.fillStyle=k.shirt;ell(k.x,k.y+2,22*s,7*s);ctx.fill()}
  if(k.hi){ctx.fillStyle='rgba(242,182,50,.55)';ell(k.x,k.y,40*s,14*s);ctx.fill()}
  ctx.save();ctx.translate(k.x,k.y-bob);if(scared&&k.dir<0)ctx.scale(-1,1);if(mv)ctx.rotate(Math.sin(t*14+k.ph)*.05);
  ctx.drawImage(im,-w/2,-Hh,w,Hh);ctx.restore();
  drawEmotion(k.x,k.y-Hh-bob-(k.hold==='egg'?36:12)*s,k.hi?'surprise':scared?'fear':k.hold==='egg'?'mischief':'happy',s,t+k.ph);
  if(k.hold==='egg')eggShape(k.x,k.y-Hh-12*s-bob,s*1.1);
  if(k.hold==='cake')cake(k.x+k.dir*18*s,k.y-Hh*.42-bob,s*1.1);
}
function drawEmotion(x,y,mood,s,t){
  ctx.save();ctx.translate(x,y);const bounce=reduce?0:Math.sin(t*5)*2*s;ctx.translate(0,bounce);ctx.font=`800 ${18*s}px ${FONT}`;ctx.textAlign='center';ctx.textBaseline='middle';
  const text={fear:'Ối!',surprise:'?!',mischief:'Hí hí',happy:'♪',angry:'!!',sleep:'Zzz'}[mood];ctx.fillStyle=mood==='angry'||mood==='fear'?'#c8361b':'#3564a8';
  if(mood==='fear'){ctx.fillStyle='#4faeff';ell(23*s,5*s,3*s,6*s,-.4);ctx.fill();ctx.fillStyle='#c8361b'}
  if(mood!=='happy'||Math.sin(t*.8)>.7){ctx.strokeStyle='rgba(255,255,255,.95)';ctx.lineWidth=4*s;ctx.strokeText(text,0,0);ctx.fillText(text,0,0)}ctx.restore();
}
function sceneMotion(kind,t,state){
 if(reduce)return;ctx.save();
 if(kind==='egg'){
  for(let i=0;i<5;i++){const x=((i*241+t*8)%1200)-100;ctx.globalAlpha=.32;cloud(x,45+(i%3)*24,.45+(i%2)*.2)}ctx.globalAlpha=1;
  ctx.strokeStyle='#5a9d4a';ctx.lineWidth=2;for(let i=0;i<34;i++){const x=i*31,y=570+(i%3)*9,w=Math.sin(t*3+i)*5;ctx.beginPath();ctx.moveTo(x,y);ctx.quadraticCurveTo(x+w,y-12,x+w*1.5,y-20);ctx.stroke()}
  for(let i=0;i<8;i++){const x=(t*27+i*157)%W,y=170+Math.sin(t*2+i)*26+(i%3)*30;ctx.fillStyle=['#ffc85b','#fff1a2','#ffa4bb'][i%3];ell(x,y,5,2,Math.sin(t*10+i));ctx.fill()}
 }else{
  for(let i=0;i<28;i++){ctx.globalAlpha=.25+.5*Math.abs(Math.sin(t*2+i));ctx.fillStyle='#fff6be';const x=(i*137+31)%W,y=24+(i*67)%200;ctx.fillRect(x,y,i%4===0?3:2,2)}ctx.globalAlpha=1;
  for(let i=0;i<5;i++){ctx.fillStyle=`rgba(159,100,255,${.04+.04*Math.sin(t+i)})`;ell(100+i*210,90+Math.sin(t+i)*25,100,50);ctx.fill()}
  if(state?.ph==='fly'){ctx.strokeStyle='rgba(159,224,255,.5)';ctx.lineWidth=2;for(let i=0;i<4;i++){ctx.beginPath();ctx.moveTo(state.u.x-85-i*15,state.u.y+i*7);ctx.lineTo(state.u.x-120-i*15,state.u.y+i*7);ctx.stroke()}}
 }ctx.restore();
}
function drawKid(k,t){
  if(!k.vector){const im=IMG[(k.pose==='scared'||k.pose==='run')?'ks'+k.v:'kh'+k.v];if(im){drawKidSprite(k,t,im);return}}
  const s=k.s,x=k.x,y=k.y,mv=Math.hypot(k.vx,k.vy)>5,sw=mv?Math.sin(t*14+k.ph)*7*s:0;
  const scared=k.pose==='scared'||k.pose==='run';
  ctx.save();
  ctx.fillStyle='rgba(60,40,10,.22)';ell(x,y+2,16*s,5*s);ctx.fill();
  if(k.hi){ctx.fillStyle='rgba(242,182,50,.5)';ell(x,y,36*s,13*s);ctx.fill()}
  ctx.strokeStyle='#f1c9a5';ctx.lineWidth=5*s;ctx.lineCap='round';
  ctx.beginPath();ctx.moveTo(x-5*s,y-18*s);ctx.lineTo(x-5*s+sw,y);ctx.moveTo(x+5*s,y-18*s);ctx.lineTo(x+5*s-sw,y);ctx.stroke();
  ctx.fillStyle='#2b2b2b';ell(x-5*s+sw,y,4*s,2.5*s);ctx.fill();ell(x+5*s-sw,y,4*s,2.5*s);ctx.fill();
  ctx.fillStyle=k.shorts;rr(x-11*s,y-24*s,22*s,10*s,3*s);ctx.fill();
  ctx.fillStyle=k.shirt||'#fff';ctx.strokeStyle='#cfd6de';ctx.lineWidth=1.5;rr(x-12*s,y-44*s,24*s,22*s,6*s);ctx.fill();ctx.stroke();
  ctx.strokeStyle='#f1c9a5';ctx.lineWidth=4.5*s;ctx.beginPath();
  const wave=Math.sin(t*16+k.ph)*5*s;
  if(k.hold==='egg'){ctx.moveTo(x-11*s,y-40*s);ctx.lineTo(x-8*s,y-70*s);ctx.moveTo(x+11*s,y-40*s);ctx.lineTo(x+8*s,y-70*s)}
  else if(k.hold==='cake'){ctx.moveTo(x-11*s,y-40*s);ctx.lineTo(x+k.dir*8*s,y-32*s);ctx.moveTo(x+11*s,y-40*s);ctx.lineTo(x+k.dir*16*s,y-32*s)}
  else if(scared){ctx.moveTo(x-11*s,y-40*s);ctx.lineTo(x-20*s,y-58*s-wave);ctx.moveTo(x+11*s,y-40*s);ctx.lineTo(x+20*s,y-58*s+wave)}
  else{ctx.moveTo(x-11*s,y-40*s);ctx.lineTo(x-14*s-sw*.4,y-26*s);ctx.moveTo(x+11*s,y-40*s);ctx.lineTo(x+14*s+sw*.4,y-26*s)}
  ctx.stroke();
  const hy=y-58*s;
  ctx.fillStyle='#f6d3b3';ctx.beginPath();ctx.arc(x,hy,15*s,0,7);ctx.fill();
  ctx.fillStyle='#2a1d14';ctx.beginPath();ctx.arc(x,hy-3*s,15.5*s,Math.PI*1.02,Math.PI*1.98);ctx.fill();
  ell(x-k.dir*6*s,hy-10*s,11*s,6*s,-k.dir*.3);ctx.fill();
  if(k.hair){ctx.beginPath();ctx.arc(x-15*s,hy-2*s,6*s,0,7);ctx.arc(x+15*s,hy-2*s,6*s,0,7);ctx.fill();ctx.fillStyle='#e0402a';ctx.fillRect(x-17*s,hy-9*s,5*s,4*s);ctx.fillRect(x+12*s,hy-9*s,5*s,4*s)}
  const fx=x+k.dir*3*s;ctx.fillStyle='#2a1d14';
  if(scared){ctx.beginPath();ctx.arc(fx-5*s,hy+1*s,2.6*s,0,7);ctx.arc(fx+5*s,hy+1*s,2.6*s,0,7);ctx.fill();ctx.fillStyle='#8b2b1d';ell(fx,hy+8*s,3*s,4*s);ctx.fill()}
  else{ctx.beginPath();ctx.arc(fx-5*s,hy+1*s,1.8*s,0,7);ctx.arc(fx+5*s,hy+1*s,1.8*s,0,7);ctx.fill();ctx.strokeStyle='#8b2b1d';ctx.lineWidth=1.6*s;ctx.beginPath();ctx.arc(fx,hy+5*s,3.5*s,.2,Math.PI-.2);ctx.stroke()}
  ctx.fillStyle='rgba(240,120,110,.35)';ctx.beginPath();ctx.arc(fx-9*s,hy+5*s,2.5*s,0,7);ctx.arc(fx+9*s,hy+5*s,2.5*s,0,7);ctx.fill();
  if(k.hold==='egg')eggShape(x,y-80*s,s*1.1);
  if(k.hold==='cake')cake(x+k.dir*13*s,y-36*s,s);
  ctx.restore();
}
const kidLabel=k=>label(k.x,k.y+6,k.p.name,Math.max(.75,k.s),k.hi,130);
const sortDraw=list=>list.sort((a,b)=>a.y-b.y).forEach(e=>e.f());

/* effects + speech bubble, shared */
let fx=[],bub=null;
function addFx(o){fx.push(Object.assign({vx:0,vy:0,g:0,r:0,vr:0,life:1,max:1,c:'#fff',shape:'circle',sz:4},o))}
function burst(x,y,n,cols,sp=220,shape='rect'){for(let i=0;i<n;i++){const a=rnd(0,7),v=rnd(sp*.3,sp);const l=rnd(.6,1.3);addFx({x,y,vx:Math.cos(a)*v,vy:Math.sin(a)*v-60,g:420,r:rnd(0,6),vr:rnd(-8,8),life:l,max:l,c:pick(cols),shape,sz:rnd(3,6)})}}
function feathers(x,y,n){for(let i=0;i<n;i++){const l=rnd(1,1.8);addFx({x,y,vx:rnd(-160,160),vy:rnd(-220,-40),g:200,r:rnd(0,6),vr:rnd(-6,6),life:l,max:1.8,c:Math.random()<.5?'#fff6e6':'#c99350',shape:'feather'})}}
function dust(x,y){addFx({x:x+rnd(-8,8),y:y+rnd(-4,4),vx:rnd(-20,20),vy:rnd(-30,-5),life:.6,max:.6,c:'rgba(205,175,120,.7)',shape:'dust',sz:rnd(5,10)})}
function confetti(){if(reduce)return;for(let i=0;i<110;i++){const l=rnd(2,3.2);addFx({x:rnd(0,W),y:rnd(-200,-10),vx:rnd(-60,60),vy:rnd(120,260),g:30,r:rnd(0,6),vr:rnd(-6,6),life:l,max:l,c:pick(['#e2573b','#f2b632','#2f8f7a','#5aa9e6','#c86bd6']),shape:'rect',sz:rnd(4,7)})}}
function stepFx(dt){for(const p of fx){p.life-=dt;p.vy+=p.g*dt;p.x+=p.vx*dt;p.y+=p.vy*dt;p.r+=p.vr*dt;if(p.shape==='feather')p.vx*=.98}fx=fx.filter(p=>p.life>0)}
function drawFx(){for(const p of fx){ctx.globalAlpha=clamp(p.life/p.max*1.5,0,1);ctx.fillStyle=p.c;
  if(p.shape==='rect'){ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.r);ctx.fillRect(-p.sz,-p.sz/2,p.sz*2,p.sz);ctx.restore()}
  else if(p.shape==='feather'){ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.r);ell(0,0,9,3.5);ctx.fill();ctx.restore()}
  else if(p.shape==='dust'){ctx.beginPath();ctx.arc(p.x,p.y,p.sz*(1.6-p.life/p.max),0,7);ctx.fill()}
  else{ctx.beginPath();ctx.arc(p.x,p.y,p.sz,0,7);ctx.fill()}}ctx.globalAlpha=1}
function say(text,x,y,dur=1.4,col='#c8361b'){bub={text,x,y,until:clock+dur,col}}
function drawBubble(){
  if(!bub||clock>bub.until)return;ctx.font=`700 18px ${FONT}`;
  const w=ctx.measureText(bub.text).width+28,x=clamp(bub.x-w/2,10,W-w-10),y=clamp(bub.y,8,H-50);
  ctx.fillStyle='#fff';ctx.strokeStyle='#2d2416';ctx.lineWidth=2.5;rr(x,y,w,38,14);ctx.fill();ctx.stroke();
  ctx.fillStyle=bub.col;ctx.textAlign='left';ctx.textBaseline='middle';ctx.fillText(bub.text,x+14,y+20);
}

/* ================= games ================= */
const G={};
/* ---------- 1. Egg thief ---------- */
const NEST=ASSETS.bg_egg?{x:124,y:405}:{x:215,y:372};
function farm(){
  sky('#8fcdf0','#d4eefa',0,285);cloud(160,70);cloud(520,50,.9);cloud(820,90);
  ctx.fillStyle='#8cc56a';ctx.beginPath();ctx.moveTo(0,250);ctx.quadraticCurveTo(250,170,520,240);ctx.quadraticCurveTo(780,160,1000,230);ctx.lineTo(1000,290);ctx.lineTo(0,290);ctx.fill();
  [[430,230],[600,222],[760,236],[930,215]].forEach(([x,y],i)=>tree(x,y,1,i%2?'#3f7f35':'#4d9140'));
  sky('#e7cf9b','#d8b97e',285,H);
  ctx.fillStyle='rgba(120,90,40,.12)';for(let i=0;i<40;i++){ell((i*137)%W,310+(i*71)%280,6,2);ctx.fill()}
  ctx.fillStyle='#c79a63';ctx.fillRect(330,262,W-330,8);ctx.fillRect(330,282,W-330,8);
  for(let x=340;x<W;x+=46){ctx.fillStyle='#b5854f';rr(x,248,12,52,3);ctx.fill()}
  ctx.fillStyle='#a8683a';ctx.fillRect(40,190,250,140);
  ctx.strokeStyle='#8a522a';ctx.lineWidth=2;for(let y=205;y<330;y+=16){ctx.beginPath();ctx.moveTo(40,y);ctx.lineTo(290,y);ctx.stroke()}
  ctx.fillStyle='#8b3a2a';ctx.beginPath();ctx.moveTo(22,198);ctx.lineTo(165,118);ctx.lineTo(308,198);ctx.fill();
  ctx.fillStyle='#3d2616';rr(140,250,56,80,26);ctx.fill();
  ctx.fillStyle='#f6e7c8';rr(64,226,48,38,4);ctx.fill();ctx.strokeStyle='#8a522a';ctx.lineWidth=3;ctx.beginPath();ctx.moveTo(88,226);ctx.lineTo(88,264);ctx.moveTo(64,245);ctx.lineTo(112,245);ctx.stroke();
  ctx.fillStyle='#b98450';ctx.beginPath();ctx.moveTo(148,330);ctx.lineTo(188,330);ctx.lineTo(200,372);ctx.lineTo(136,372);ctx.fill();
}
function nest(n){
  ctx.fillStyle='#c79a43';ell(NEST.x,NEST.y+10,62,20);ctx.fill();
  ctx.strokeStyle='#a87a2c';ctx.lineWidth=2;for(let i=0;i<14;i++){const a=i/14*Math.PI*2;ctx.beginPath();ctx.moveTo(NEST.x+Math.cos(a)*40,NEST.y+10+Math.sin(a)*12);ctx.lineTo(NEST.x+Math.cos(a+.6)*62,NEST.y+10+Math.sin(a+.6)*20);ctx.stroke()}
  const sp=[[-30,0],[-14,4],[2,1],[18,4],[32,0],[-20,-6],[10,-6]];for(let i=0;i<n;i++)eggShape(NEST.x+sp[i][0],NEST.y+sp[i][1],1);
}
function hen(h,t,ph){
  {const sleep=ph==='sleep'||ph==='sneak',im=IMG[sleep?'hen_sleep':'hen_angry'];
  if(im){const sz=h.size,Hh=(sleep?78:92)*sz,w=Hh*im.width/im.height,sh=h.angry?Math.sin(t*50)*h.shake:0,bob=ph==='chase'?Math.abs(Math.sin(t*20))*6:0;
    ctx.fillStyle='rgba(60,40,10,.22)';ell(h.x,h.y+4,w*.38,9*sz);ctx.fill();
    ctx.save();ctx.translate(h.x+sh,h.y-bob);ctx.scale(h.dir,1);if(ph==='chase')ctx.rotate(Math.sin(t*20)*.06);ctx.drawImage(im,-w/2,-Hh,w,Hh);ctx.restore();drawEmotion(h.x,h.y-Hh-18,sleep?'sleep':ph==='caught'?'mischief':'angry',1,t);
    if(sleep){ctx.fillStyle='#4a6fa5';ctx.font=`800 24px ${DFONT}`;ctx.textAlign='left';const z=(t*1.2)%1,z2=(z+.5)%1;
      ctx.globalAlpha=1-z;ctx.fillText('z',h.x+w*.35,h.y-Hh-z*30);ctx.globalAlpha=1-z2;ctx.fillText('Z',h.x+w*.45,h.y-Hh-10-z2*30);ctx.globalAlpha=1}
    return}}
  const sz=h.size,x=h.x+(h.angry?Math.sin(t*50)*h.shake:0),y=h.y,sleep=ph==='sleep'||ph==='sneak';
  ctx.save();ctx.translate(x,y);ctx.scale(h.dir*sz,sz);
  ctx.fillStyle='rgba(60,40,10,.22)';ell(0,4,34,8);ctx.fill();
  const st=ph==='chase'?Math.sin(t*20)*6:0;
  if(!sleep){ctx.strokeStyle='#e8961e';ctx.lineWidth=4;ctx.beginPath();ctx.moveTo(-6,-14);ctx.lineTo(-6+st,2);ctx.moveTo(8,-14);ctx.lineTo(8-st,2);ctx.stroke()}
  ctx.fillStyle=h.angry?'#7a2a14':'#8a5a2a';ctx.beginPath();ctx.moveTo(-26,-34);ctx.quadraticCurveTo(-52,-70,-30,-74);ctx.quadraticCurveTo(-40,-50,-14,-40);ctx.fill();
  ctx.fillStyle=h.angry?'#ffe2cf':'#fff6e6';ell(0,-30,34,24,-.1);ctx.fill();
  ctx.fillStyle=h.angry?'#d9682e':'#c99350';ctx.save();ctx.translate(-4,-32);ctx.rotate(-.2-(h.angry?Math.sin(t*30)*.5:0));ell(-4,0,18,11);ctx.fill();ctx.restore();
  ctx.fillStyle=h.angry?'#ffe2cf':'#fff6e6';ctx.beginPath();ctx.arc(24,-58,15,0,7);ctx.fill();
  ctx.fillStyle='#e2312a';ctx.beginPath();ctx.arc(18,-74,6,0,7);ctx.arc(25,-77,7,0,7);ctx.arc(32,-73,6,0,7);ctx.fill();ell(36,-48,4,7);ctx.fill();
  ctx.fillStyle='#f0a32a';ctx.beginPath();ctx.moveTo(37,-62);ctx.lineTo(50,-57);ctx.lineTo(37,-53);ctx.fill();
  ctx.strokeStyle='#2a1d14';ctx.fillStyle='#2a1d14';ctx.lineWidth=2.5;
  if(sleep){ctx.beginPath();ctx.moveTo(23,-61);ctx.quadraticCurveTo(28,-57,33,-61);ctx.stroke()}
  else{ctx.beginPath();ctx.arc(28,-61,3.4,0,7);ctx.fill();ctx.beginPath();ctx.moveTo(21,-70);ctx.lineTo(34,-65);ctx.stroke()}
  ctx.restore();
  if(sleep){ctx.fillStyle='#4a6fa5';ctx.font=`800 22px ${DFONT}`;ctx.textAlign='left';const z=(t*1.2)%1,z2=(z+.5)%1;
    ctx.globalAlpha=1-z;ctx.fillText('z',h.x+34,h.y-90-z*30);ctx.globalAlpha=1-z2;ctx.fillText('Z',h.x+46,h.y-100-z2*30);ctx.globalAlpha=1}
}
G.egg={
  setup(P){const s=scaleFor(P.length);const kids=P.map((p,i)=>mkKid(p,i,rnd(560,960),rnd(330,560),s));
    return{kids,h:{x:NEST.x,y:NEST.y-8,dir:1,size:1,shake:0,angry:false,tgt:null},ph:'sleep',pt:0,eggs:7,win:pick(kids)}},
  start(S){S.ph='sneak';S.pt=0;const n=S.kids.length;
    S.kids.forEach((k,i)=>{const a=-1.25+2.5*(n===1?.5:i/(n-1))+rnd(-.08,.08),r=rnd(95,150)+(i%3)*18*k.s;k.tx=NEST.x+Math.cos(a)*r+30;k.ty=clamp(NEST.y+Math.sin(a)*r*.75,318,575)});},
  update(S,dt,t){
    S.pt+=dt;const h=S.h;
    const mv=k=>{k.x=clamp(k.x+k.vx*dt,25,975);k.y=clamp(k.y+k.vy*dt,318,580);if(Math.abs(k.vx)>3)k.dir=k.vx>0?1:-1};
    const flee=(fin,skip)=>{for(const k of S.kids){if(k===skip)continue;const ex=k.x-h.x,ey=k.y-h.y,ed=Math.hypot(ex,ey)||1;
      let ax=Math.cos(t*1.7+k.ph)*60,ay=Math.sin(t*2.1+k.ph)*60;const fear=ed<300?(320-ed)*4:0;ax+=ex/ed*fear;ay+=ey/ed*fear;
      if(k.x<70)ax+=300;if(k.x>930)ax-=300;if(k.y<335)ay+=300;if(k.y>565)ay-=300;
      k.vx+=ax*dt*2;k.vy+=ay*dt*2;if(k.dash>0)k.dash-=dt;
      const lim=k.spd*(k.dash>0?1.9:1)*(fin&&k===S.win?.55:1),v=Math.hypot(k.vx,k.vy);if(v>lim){k.vx=k.vx/v*lim;k.vy=k.vy/v*lim}
      k.pose='run';mv(k);if(Math.random()<.08)dust(k.x,k.y)}};
    if(S.ph==='sleep'){for(const k of S.kids){k.wan+=dt*.7;k.vx=Math.cos(k.wan+k.ph)*14;k.vy=Math.sin(k.wan*1.3+k.ph)*8;mv(k)}}
    else if(S.ph==='sneak'){let all=true;
      for(const k of S.kids){const dx=k.tx-k.x,dy=k.ty-k.y,d=Math.hypot(dx,dy);
        if(d>4){all=false;const sp=Math.min(d/dt,95);k.vx=dx/d*sp;k.vy=dy/d*sp}else{k.vx=k.vy=0;if(!k.hold&&Math.random()<dt*3){k.hold='egg';S.eggs=Math.max(0,S.eggs-1);snd(1300,1500,.05,'sine',.05)}}mv(k)}
      if((all&&S.kids.every(k=>k.hold)&&S.pt>2)||S.pt>Math.min(4,S.D*.2)){S.kids.forEach(k=>k.hold='egg');S.eggs=0;S.ph='wake';S.pt=0;h.angry=true;h.shake=4;
        say('Cục tác! Ai dám trộm trứng của ta?!',h.x+40,h.y-150,1.8);cluck();setTimeout(cluck,350);feathers(h.x,h.y-40,14)}}
    else if(S.ph==='wake'){h.size=Math.min(1.45,1+S.pt*.6);S.kids.forEach(k=>{k.vx=k.vy=0;k.dir=k.x<h.x?1:-1;k.pose='scared'});
      if(S.pt>1.6){S.ph='chase';S.pt=0;h.shake=2;S.next=0;h.stun=0;
        S.kids.forEach(k=>{const a=Math.atan2(k.y-h.y,k.x-h.x)+rnd(-.4,.4);k.vx=Math.cos(a)*k.spd;k.vy=Math.sin(a)*k.spd;k.pose='run';if(Math.random()<.5)k.hold=null})}}
    else if(S.ph==='chase'){
      const fin=S.el>S.D-1.4;
      if(fin)h.tgt=S.win;
      else if(!h.tgt||S.pt>S.next){const o=S.kids.length>1?S.kids.filter(k=>k!==h.tgt):S.kids;h.tgt=pick(o);S.next=S.pt+rnd(2,3);
        if(Math.random()<.5)cluck();if(Math.random()<.35)say(pick(['Đứng lại!','Trả trứng đây!','Cục tác tác!']),h.x,h.y-150,1)}
      const tg=h.tgt,dx=tg.x-h.x,dy=tg.y-h.y,d=Math.hypot(dx,dy)||1;
      if(h.stun>0)h.stun-=dt;else{const sp=(fin?340:265)*dt;h.x=clamp(h.x+dx/d*Math.min(sp,d),40,960);h.y=clamp(h.y+dy/d*Math.min(sp,d),320,578);if(Math.abs(dx)>2)h.dir=dx>0?1:-1}
      if(Math.random()<.5)dust(h.x,h.y);if(Math.random()<.05)feathers(h.x,h.y-40,2);
      flee(fin,null);
      if(fin&&((d<27*h.size&&S.el>=S.D-.4)||S.el>S.D+.6)){if(d>=27*h.size){h.x=tg.x-30;h.y=tg.y}
        S.ph='caught';S.pt=0;h.shake=0;h.dir=S.win.x>h.x?1:-1;S.win.vx=S.win.vy=0;S.win.hold=null;S.win.hi=true;S.win.pose='scared';
        say('Lần này bắt được thật rồi!',h.x,h.y-150,99);feathers(S.win.x,S.win.y-50,26);cluck();finish(S.win.p)}
      else if(!fin&&h.stun<=0&&d<27*h.size&&S.el<S.D-2.8){S.ph='fake';S.pt=0;S.fk=tg;tg.vx=tg.vy=0;tg.pose='scared';
        say('Bắt được rồi nhé!',h.x,h.y-150,.9);cluck();feathers(h.x,h.y-40,8)}}
    else if(S.ph==='fake'){const k=S.fk;h.x+=((k.x-24*h.dir)-h.x)*dt*8;h.y+=((k.y-2)-h.y)*dt*8;k.vx=k.vy=0;flee(false,k);k.pose='scared';
      if(S.pt>.9){const a=Math.atan2(k.y-h.y,k.x-h.x)+rnd(-.5,.5);k.vx=Math.cos(a)*k.spd*1.9;k.vy=Math.sin(a)*k.spd*1.9;k.dash=.6;k.pose='run';
        say(pick(['Ối! Tuột mất rồi!','Chạy thoát rồi!','Hụt rồi!']),h.x,h.y-150,1,'#1f6fa8');snd(700,200,.3,'sawtooth',.08);h.stun=.5;S.ph='chase';S.pt=0;S.next=-1}}
    else if(S.ph==='caught'){S.kids.forEach(k=>{k.vx*=.9;k.vy*=.9;if(k!==S.win)mv(k)});h.x+=((S.win.x-24*h.dir)-h.x)*dt*6;h.y+=((S.win.y-2)-h.y)*dt*6;if(Math.random()<.06)feathers(S.win.x,S.win.y-50,1)}
  },
  draw(S,t){if(IMG.bg_egg)ctx.drawImage(IMG.bg_egg,0,0,W,H);else{farm();nest(S.eggs)}sceneMotion('egg',t,S);sortDraw([...S.kids.map(k=>({y:k.y,f:()=>drawKid(k,t)})),{y:S.h.y+(S.ph==='caught'?6:0),f:()=>hen(S.h,t,S.ph)}]);S.kids.forEach(kidLabel)}
};

/* ---------- 10. UFO ---------- */
const UY0=395,UY1=ASSETS.bg_ufo?478:535;
const SHOPS=['TIỆM CAFE','NHÀ SÁCH','TIỆM BÁNH','ANH NGỮ','SIÊU THỊ'];
const BLD=[...Array(9)].map((_,i)=>({x:i*116-10,w:104,h:150+((i*53)%70),c:['#e9f3ff','#ffe9d6','#fde2ef','#fff4c9','#e2f5ec'][i%5],roof:['#3d7fd6','#e0703a','#d6457f','#e3a51c','#3aa57a'][i%5]}));
function street(t){
  const g=ctx.createLinearGradient(0,0,0,340);g.addColorStop(0,'#b48cf2');g.addColorStop(.6,'#f6a8c8');g.addColorStop(1,'#ffd1a6');ctx.fillStyle=g;ctx.fillRect(0,0,W,340);
  ctx.fillStyle='rgba(255,240,200,.35)';ctx.beginPath();ctx.arc(860,90,58,0,7);ctx.fill();ctx.fillStyle='#fff3c4';ctx.beginPath();ctx.arc(860,90,38,0,7);ctx.fill();
  cloud(((t*10)%1200)-150,55,.9);cloud(420,36,.8);cloud(640,78,.7);
  BLD.forEach((b,i)=>{const top=340-b.h;ctx.fillStyle=b.c;ctx.fillRect(b.x,top,b.w,b.h);ctx.fillStyle=b.roof;ctx.fillRect(b.x-4,top-8,b.w+8,10);
    ctx.fillStyle='#5aa7e6';for(let y=top+14;y<285;y+=30)for(let x=b.x+12;x<b.x+b.w-14;x+=24)ctx.fillRect(x,y,14,18);
    ctx.fillStyle='#2d2d3a';ctx.fillRect(b.x+6,296,b.w-12,14);ctx.fillStyle='#fff';ctx.font=`700 9px ${FONT}`;ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(SHOPS[i%5],b.x+b.w/2,303.5);
    for(let k=0;k<8;k++){ctx.fillStyle=k%2?'#fff':b.roof;ctx.fillRect(b.x+6+k*(b.w-12)/8,310,(b.w-12)/8,10)}
    ctx.fillStyle='#3a3a48';ctx.fillRect(b.x+108,262,4,80);ctx.fillRect(b.x+104,258,12,6);
    const tx=b.x+52;ctx.fillStyle='#8a5a3a';ctx.fillRect(tx-3,322,6,22);ctx.fillStyle='#f48fb8';ctx.beginPath();ctx.arc(tx,312,15,0,7);ctx.arc(tx-12,320,11,0,7);ctx.arc(tx+12,320,11,0,7);ctx.fill()});
  ctx.fillStyle='#ebe8f1';ctx.fillRect(0,340,W,200);ctx.strokeStyle='rgba(120,110,150,.15)';ctx.lineWidth=1.5;
  for(let y=370;y<540;y+=34){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(W,y);ctx.stroke()}
  ctx.fillStyle='#c9c4d6';ctx.fillRect(0,538,W,10);ctx.fillStyle='#3d4150';ctx.fillRect(0,548,W,52);
  ctx.fillStyle='#f2c230';for(let x=0;x<W;x+=60)ctx.fillRect(x,573,32,4);
  ctx.fillStyle='#fff';for(let y=552;y<598;y+=9)ctx.fillRect(60,y,70,5);
}
function busDraw(x){if(IMG.bus){const w=150,h=w*IMG.bus.height/IMG.bus.width;ctx.drawImage(IMG.bus,x,588-h,w,h);return}const y=554;ctx.fillStyle='#f5c02a';rr(x,y,116,30,7);ctx.fill();ctx.fillStyle='#e0a514';ctx.fillRect(x,y+20,116,4);
  ctx.fillStyle='#4f8fd6';for(let i=0;i<5;i++)ctx.fillRect(x+8+i*20,y+5,15,11);ctx.fillStyle='#fff7c2';ctx.fillRect(x+110,y+18,6,5);
  ctx.fillStyle='#222';ctx.beginPath();ctx.arc(x+24,y+30,6,0,7);ctx.arc(x+92,y+30,6,0,7);ctx.fill();
  ctx.fillStyle='rgba(255,247,194,.25)';ctx.beginPath();ctx.moveTo(x+116,y+18);ctx.lineTo(x+190,y+8);ctx.lineTo(x+190,y+34);ctx.fill()}
function ufoDraw(u,t,rider){if(IMG.ufo){const w=180,h=w*IMG.ufo.height/IMG.ufo.width,y=u.y+Math.sin(t*4)*2;
    ctx.save();ctx.translate(u.x,y);ctx.rotate(u.bank||0);ctx.drawImage(IMG.ufo,-w/2,-h*.55,w,h);ctx.restore();
    if(rider){ctx.save();ctx.translate(u.x,y-h*.2);ctx.fillStyle='#f6d3b3';ctx.beginPath();ctx.arc(0,0,13,0,7);ctx.fill();ctx.fillStyle='#2a1d14';ctx.beginPath();ctx.arc(0,-2,13.5,Math.PI*1.02,Math.PI*1.98);ctx.fill();
      ctx.beginPath();ctx.arc(-4.5,1,2,0,7);ctx.arc(4.5,1,2,0,7);ctx.fill();ctx.fillStyle='#8b2b1d';ell(0,7,2.5,3.2);ctx.fill();ctx.fillStyle='rgba(170,225,255,.35)';ctx.beginPath();ctx.arc(0,0,20,0,7);ctx.fill();ctx.restore()}
    return}ctx.save();ctx.translate(u.x,u.y+Math.sin(t*4)*2);
  if(rider){ctx.fillStyle='#f6d3b3';ctx.beginPath();ctx.arc(0,-18,11,0,7);ctx.fill();ctx.fillStyle='#2a1d14';ctx.beginPath();ctx.arc(0,-20,11.5,Math.PI*1.02,Math.PI*1.98);ctx.fill();
    ctx.beginPath();ctx.arc(-4,-17,1.8,0,7);ctx.arc(4,-17,1.8,0,7);ctx.fill();ctx.fillStyle='#8b2b1d';ell(0,-11,2.2,3);ctx.fill();ctx.fillStyle=rider.shirt||'#fff';ctx.fillRect(-9,-7,18,6)}
  ctx.fillStyle='rgba(170,225,255,.55)';ctx.strokeStyle='rgba(255,255,255,.85)';ctx.lineWidth=2;ctx.beginPath();ctx.arc(0,-4,30,Math.PI,0);ctx.closePath();ctx.fill();ctx.stroke();
  ctx.fillStyle='rgba(255,255,255,.5)';ell(-12,-20,5,9,-.5);ctx.fill();
  ctx.fillStyle='#3b3f63';ell(0,4,82,20);ctx.fill();ctx.fillStyle='#565c8f';ell(0,-1,76,11);ctx.fill();
  ctx.strokeStyle='#59e1ff';ctx.lineWidth=3;ell(0,6,80,17);ctx.stroke();
  for(let i=0;i<7;i++){ctx.fillStyle=((i+Math.floor(t*6))%2)?'#c69bff':'#59e1ff';ctx.beginPath();ctx.arc(-54+i*18,8,4.5,0,7);ctx.fill()}
  ctx.restore()}
G.ufo={
  setup(P){const s=scaleFor(P.length);const kids=P.map((p,i)=>{const k=mkKid(p,i,rnd(40,960),rnd(UY0,UY1),s);k.lift=0;k.shr=1;k.tx=k.x;k.ty=k.y;k.shoutT=0;return k});
    return{kids,u:{x:260,y:105,beam:0,bank:0,routeX:180,routeY:70,detour:0},bus:{x:rnd(-200,800)},ph:'idle',pt:0,win:pick(kids)}},
  start(S){S.ph='alarm';S.pt=0;S.final=false;S.cur=null;
    say('UFO xuất hiện!',420,14,1.5,'#6b2fb0');snd(300,900,.6,'sine',.08);snd(900,300,.6,'sine',.08,.6);
    shuffle(S.kids.slice()).slice(0,6).forEach(k=>{k.shout=pick(['UFO kìa!','Chạy thôi!','Cứu với!','Ối!']);k.shoutT=2})},
  next(S){let o=S.kids.filter(k=>k!==S.win&&k!==S.cur);if(!o.length)o=S.kids.filter(k=>k!==S.win);if(S.el>S.D-4.4||!o.length){S.final=true;return S.win}return pick(o)},
  update(S,dt,t){S.pt+=dt;const u=S.u;S.bus.x+=170*dt;if(S.bus.x>1100)S.bus.x=-220;
    const mv=k=>{k.x=clamp(k.x+k.vx*dt,30,970);k.y=clamp(k.y+k.vy*dt,UY0-5,UY1+1);if(Math.abs(k.vx)>3)k.dir=k.vx>0?1:-1};
    const tg=S.cur;
    for(const k of S.kids){if(k.shoutT>0)k.shoutT-=dt;if(k.captured)continue;
      if(S.ph==='idle'){k.wan+=dt*.7;k.vx=Math.cos(k.wan+k.ph)*14;k.vy=Math.sin(k.wan*1.3+k.ph)*8;mv(k);continue}
      if(k===tg&&(S.ph==='beam'||S.ph==='drop'||(S.ph==='fly'&&Math.abs(u.x-k.x)<160))){k.vx=k.vy=0;k.pose='scared';continue}
      if(S.ph==='done'){k.vx*=.9;k.vy*=.9;k.pose='idle';mv(k);continue}
      k.pose='run';if(Math.hypot(k.tx-k.x,k.ty-k.y)<10||Math.random()<dt*.5){k.tx=rnd(40,960);k.ty=rnd(UY0,UY1)}
      const dx=k.tx-k.x,dy=k.ty-k.y,d=Math.hypot(dx,dy)||1;k.vx=dx/d*k.spd*.7;k.vy=dy/d*k.spd*.7;mv(k);if(Math.random()<.03)dust(k.x,k.y)}
    if(S.ph==='idle'){u.x=500+Math.sin(t*.65)*390;u.y=145+Math.sin(t*1.3)*90;u.bank=reduce?0:Math.cos(t*.65)*.12;return}
    if(S.ph==='alarm'){u.x=500+Math.sin(t*2)*340;u.y=115+Math.sin(t*3)*65;u.bank=reduce?0:Math.cos(t*2)*.2;if(S.pt>1.5){S.ph='fly';S.pt=0;S.cur=G.ufo.next(S);u.detour=.65;u.routeX=clamp(1000-S.cur.x+rnd(-100,100),95,905);u.routeY=rnd(55,245)}}
    else if(S.ph==='fly'){const detour=!S.final&&u.detour>0;if(detour)u.detour-=dt;const dx=(detour?u.routeX:tg.x)-u.x,dy=(detour?u.routeY:150)-u.y,d=Math.hypot(dx,dy)||1,spd=S.final?clamp(d/Math.max(.3,(S.D-2.2)-S.el),60,1100):650,sp=spd*dt;u.x=clamp(u.x+dx/d*Math.min(sp,d),30,970);u.y=clamp(u.y+dy/d*Math.min(sp,d),50,270);u.bank=reduce?0:clamp(dx/700,-.22,.22);
      if(!detour&&Math.abs(tg.x-u.x)<4&&Math.abs(150-u.y)<4){S.ph='beam';S.pt=0;snd(200,600,1,'sine',.08);const top=tg.y-(u.y+25);if(S.final)S.liftSp=top/Math.max(.8,S.D-S.el);else S.liftMax=rnd(40,top*.8)}}
    else if(S.ph==='beam'){u.bank*=Math.max(0,1-dt*8);u.beam=Math.min(1,u.beam+dt*3);const top=tg.y-(u.y+25);
      if(!S.final){tg.lift=Math.min(S.liftMax,S.pt*S.liftMax/1.1);tg.shr=1-.45*(tg.lift/top);
        if(S.pt>1.5){S.ph='drop';S.pt=0;say(pick(['Phù! Thoát rồi!','Hụt rồi nhé!','Tuột mất rồi!']),tg.x,tg.y-170,1.1,'#6b2fb0');snd(600,150,.4,'sawtooth',.07)}}
      else{tg.lift=Math.min(top,tg.lift+dt*S.liftSp);tg.shr=1-.6*(tg.lift/top);
        if(tg.lift>=top){tg.captured=true;u.beam=0;S.ph='done';S.pt=0;tg.hi=true;burst(u.x,u.y,24,['#59e1ff','#c69bff','#fff'],220);finish(tg.p)}}}
    else if(S.ph==='drop'){const top=tg.y-(u.y+25);u.beam=Math.max(0,u.beam-dt*3);tg.lift=Math.max(0,tg.lift-dt*300);tg.shr=1-.45*(tg.lift/top);
      if(tg.lift===0&&S.pt>.7){tg.shr=1;S.cur=G.ufo.next(S);S.ph='fly';S.pt=0;u.detour=.65;u.routeX=clamp(1000-S.cur.x+rnd(-100,100),95,905);u.routeY=rnd(55,245)}}
    else if(S.ph==='done'){u.x+=(500-u.x)*dt*1.5;u.y+=(150-u.y)*dt*1.5}
  },
  draw(S,t){if(IMG.bg_ufo)ctx.drawImage(IMG.bg_ufo,0,0,W,H);else street(t);sceneMotion('ufo',t,S);busDraw(S.bus.x);const u=S.u,tg=S.cur;
    if(u.beam>0&&tg){const gy=tg.y+6,g=ctx.createLinearGradient(0,u.y,0,gy);g.addColorStop(0,`rgba(200,150,255,${.6*u.beam})`);g.addColorStop(1,`rgba(200,150,255,${.18*u.beam})`);
      ctx.fillStyle=g;ctx.beginPath();const by=u.y+(IMG.ufo?42:12);ctx.moveTo(u.x-28,by);ctx.lineTo(u.x+28,by);ctx.lineTo(u.x+70,gy);ctx.lineTo(u.x-70,gy);ctx.fill();ctx.fillStyle=`rgba(235,215,255,${.6*u.beam})`;ell(u.x,gy,70,12);ctx.fill()}
    const vis=S.kids.filter(k=>!k.captured);
    sortDraw(vis.map(k=>({y:k.y,f:()=>{if(k.lift>0||k.shr<1){const oy=k.y,os=k.s;k.y=oy-k.lift;k.s=os*k.shr;drawKid(k,t);k.y=oy;k.s=os}else drawKid(k,t)}})));
    vis.forEach(k=>label(k.x,k.y-k.lift+6,k.p.name,Math.max(.75,k.s*k.shr),k.hi,130));
    vis.forEach(k=>{if(k.shoutT>0){const fs=11*Math.max(.8,k.s);ctx.font=`700 ${fs}px ${FONT}`;const w=ctx.measureText(k.shout).width+10,y=k.y-k.lift-92*k.s;
      ctx.fillStyle='#fff';rr(k.x-w/2,y,w,fs+6,6);ctx.fill();ctx.fillStyle='#c8361b';ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(k.shout,k.x,y+(fs+6)/2)}});
    ufoDraw(u,t,S.kids.find(k=>k.captured))}
};

/* ================= engine ================= */
const GAMES=[{id:'egg',ico:'🥚',name:'Trộm trứng',desc:'Gà mẹ rượt đuổi'},{id:'ufo',ico:'🛸',name:'UFO',desc:'Đĩa bay gọi tên'}];
const WINLBL={egg:'Gà mẹ bắt được',ufo:'UFO đã chọn'};
const cur=document.body.dataset.game==='ufo'?'ufo':'egg';
let mode='name',numCount=load('numCount',20),called=load('called',[]);
let S=null,ephase='idle',cd=0,clock=0,lastT=performance.now();
const listEl=$('list'),goEl=$('go'),histEl=$('history');
listEl.value=load('names','');
const names=()=>{if(mode==='num')return Array.from({length:numCount},(_,i)=>String(i+1));const used=new Set(),original=rosters[classSelect.value]||[];return listEl.value.split('\n').map(s=>s.trim()).filter(Boolean).map((s,i)=>{const index=original.findIndex((p,j)=>p.name===s&&!used.has(j));if(index>=0)used.add(index);return (index>=0?index+1:i+1)+'. '+s;});};
const pool=()=>$('noRepeat').checked?names().filter(n=>!called.includes(n)):names();
const busy=()=>ephase==='play'||ephase==='count'||ephase==='twist';

let durSet=clamp(+load('dur',20)||20,10,30);
function setup(){window.CDSCallingAudio?.stop();const P=pool().map((name,i)=>({name,i}));if($('mix').checked)shuffle(P);fx=[];bub=null;S=P.length?G[cur].setup(P):null;if(S){S.el=0;S.D=durSet}ephase='idle';$('banner').hidden=true;updateUI()}
function start(){
  if(busy())return;unlockAudio();if(ephase==='done')setup();if(!S)return;window.CDSCallingAudio?.start();
  S.el=0;S.D=rnd(durSet-1.5,durSet-.5)-($('twist').checked?3.5:0);S.nextTick=.4;
  if(G[cur].countdown){ephase='count';cd=0}else{ephase='play';G[cur].start(S)}
  goEl.disabled=true;setRosterLocked(true);
}
function finish(p,sub){
  if(ephase!=='play')return;
  const others=pool().filter(n=>n!==p.name);
  if($('twist').checked&&others.length){ephase='twist';twistRun(p,sub,pick(others));return}
  ephase='done';setRosterLocked(false);showWinner(p.name,sub,cur==='flash'?500:350);
}
function showWinner(name,sub,delay){window.CDSCallingAudio?.finish();
  $('bLbl').textContent=WINLBL[cur];$('bName').textContent=name;$('bName').className='nm';$('bStamp').hidden=true;$('bSub').hidden=!sub;$('bSub').textContent=sub?'🎯 '+sub:'';
  setTimeout(()=>{if(ephase==='done'){$('banner').hidden=false;if(!window.CDSCallingAudio)fanfare();confetti()}},delay);
  called.push(name);save('called',called);renderHistory();updateCount();
  goEl.disabled=false;goEl.textContent=pool().length?'Chơi lượt mới!':'Hết người rồi';if(!pool().length)goEl.disabled=true;
}
function twistRun(p,sub,real){
  const nm=$('bName'),st=$('bStamp');
  $('bLbl').textContent=WINLBL[cur];nm.textContent=p.name;nm.className='nm';st.hidden=true;$('bSub').hidden=true;
  setTimeout(()=>{$('banner').hidden=false;fanfare()},300);
  setTimeout(()=>{st.hidden=false;nm.className='nm wrong';snd(300,120,.5,'sawtooth',.14);say('Ơ… khoan đã!',380,20,1.5,'#c8361b')},1700);
  const all=names();let i=0;const steps=14;
  const roll=()=>{if(i<steps){nm.className='nm rolling';nm.textContent=pick(all);snd(700+i*25,700+i*25,.05,'square',.07);i++;setTimeout(roll,60+i*i*2.2)}
    else{ephase='done';setRosterLocked(false);showWinner(real,sub,0);$('bLbl').textContent='Lật kèo! Người được gọi thật là';nm.className='nm pop'}};
  setTimeout(()=>{st.hidden=true;$('bLbl').textContent='Không phải bạn này! Người thật sự là…';roll()},2800);
}
function frame(now){
  const dt=clamp((now-lastT)/1000,0,.05);lastT=now;clock+=dt;const t=now/1000;
  ctx.setTransform(cv.width/W,0,0,cv.height/H,0,0);
  if(!S){ctx.fillStyle='#2d2416';ctx.fillRect(0,0,W,H);ctx.fillStyle='#fff';ctx.font=`700 26px ${FONT}`;ctx.textAlign='center';ctx.textBaseline='middle';
    ctx.fillText(names().length?'Mọi bạn đều đã được gọi. Bấm "Làm lại từ đầu".':'Thêm người chơi ở bên dưới để bắt đầu',W/2,H/2);requestAnimationFrame(frame);return}
  if(ephase==='count'||ephase==='play'||ephase==='twist'){S.el+=dt;if(ephase==='play'&&S.el>=S.nextTick){const k=clamp(S.el/S.D,0,1);snd(1000-k*300,650,.05,'triangle',.04);S.nextTick=S.el+(.6-.46*k)}}
  if(ephase==='count'){const before=Math.floor(cd/.8);cd+=dt;const after=Math.floor(cd/.8);if(cd===dt||after!==before)snd(after>=3?880:440,after>=3?880:440,.2,'square',.1);
    if(cd>=2.6){ephase='play';G[cur].start(S)}}
  G[cur].update(S,dt,t);if(busy())window.CDSCallingAudio?.sync(S.ph);stepFx(dt);
  G[cur].draw(S,t);drawFx();drawBubble();drawClock(t);
  if(ephase==='count'){const n=3-Math.floor(cd/.8),txt=n>0?String(n):'Đi!';ctx.font=`800 ${150}px ${DFONT}`;ctx.textAlign='center';ctx.textBaseline='middle';ctx.lineWidth=10;ctx.strokeStyle='rgba(0,0,0,.4)';ctx.strokeText(txt,W/2,H/2);ctx.fillStyle='#fff';ctx.fillText(txt,W/2,H/2)}
  if(ephase==='idle'){ctx.font=`700 20px ${FONT}`;const tx='Bấm vào đây hoặc nút Bắt đầu!',w=ctx.measureText(tx).width+30;ctx.fillStyle='rgba(20,20,20,.6)';rr(W/2-w/2,H-50,w,36,18);ctx.fill();ctx.fillStyle='#fff';ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(tx,W/2,H-31)}
  requestAnimationFrame(frame);
}
function drawClock(t){
  if(ephase==='idle')return;
  const left=ephase==='count'?durSet:Math.max(0,Math.ceil(durSet-S.el)),low=ephase==='play'&&left<=5;
  const sc=low?1+.08*Math.abs(Math.sin(t*6)):1,w=128,h=58,x=14,y=14;
  ctx.save();ctx.translate(x+w/2,y+h/2);ctx.scale(sc,sc);
  ctx.fillStyle='rgba(0,0,0,.25)';rr(-w/2+3,-h/2+4,w,h,16);ctx.fill();
  ctx.fillStyle=low?'#c8361b':'rgba(255,253,247,.95)';ctx.strokeStyle=low?'#fff':'#2d2416';ctx.lineWidth=3;rr(-w/2,-h/2,w,h,16);ctx.fill();ctx.stroke();
  ctx.strokeStyle=low?'#fff':'#2d2416';ctx.lineWidth=3;ctx.beginPath();ctx.arc(-34,2,15,0,7);ctx.stroke();ctx.fillStyle=ctx.strokeStyle;ctx.fillRect(-38,-18,8,5);
  const a=-Math.PI/2+(ephase==='play'?(S.el/durSet)*Math.PI*2:0);ctx.lineWidth=2.5;ctx.beginPath();ctx.moveTo(-34,2);ctx.lineTo(-34+Math.cos(a)*10,2+Math.sin(a)*10);ctx.stroke();
  ctx.fillStyle=low?'#fff':'#2d2416';ctx.font=`800 34px ${DFONT}`;ctx.textAlign='left';ctx.textBaseline='middle';ctx.fillText(String(left).padStart(2,'0'),-12,4);
  ctx.font=`700 12px ${FONT}`;ctx.fillText('giây',30,10);
  ctx.restore();
}
function resize(){const r=cv.getBoundingClientRect(),dpr=Math.min(2,window.devicePixelRatio||1);cv.width=Math.round(r.width*dpr);cv.height=Math.round(r.width*.6*dpr)}

/* ================= UI ================= */
function updateCount(){const all=names(),left=pool();$('total').textContent=all.length+' bạn';$('count').textContent=$('noRepeat').checked?`Còn ${left.length}/${all.length} bạn chưa được gọi`:`${all.length} bạn tham gia`}
function renderHistory(){histEl.innerHTML=called.length?called.slice().reverse().map(n=>`<li>${esc(n)}</li>`).join(''):'<li class="empty">Chưa có ai.</li>';
  if(called.length){histEl.setAttribute('reversed','');histEl.setAttribute('start',called.length)}else histEl.removeAttribute('reversed')}
function updateUI(){updateCount();renderHistory();goEl.textContent=pool().length?'Bắt đầu!':'Hết người rồi';goEl.disabled=!pool().length}
function applyMode(){$('modeNum').checked=mode==='num';$('modeName').checked=mode==='name';$('numBox').hidden=mode!=='num';$('nameBox').hidden=mode!=='name';$('numRange').value=numCount;$('numOut').textContent=numCount}
function setNum(v){if(busy()){applyMode();return}numCount=clamp(v,2,50);save('numCount',numCount);applyMode();setup()}

function setRosterLocked(locked){document.querySelectorAll('#participants input,#participants textarea,#participants button,#participants select').forEach(el=>el.disabled=locked);$('noRepeat').disabled=locked;$('mix').disabled=locked;$('twist').disabled=locked;$('durRange').disabled=locked;$('testSound').disabled=locked;}
const rosters=JSON.parse($('rosterData').textContent),classSelect=$('classSelect');
function useClass(){if(busy())return;const key=classSelect.value;storageClass=key||'manual';called=load('called',[]);listEl.value=key?(rosters[key]||[]).map(s=>s.name).join('\n'):load('names','');save('names',listEl.value);setup();}
classSelect.addEventListener('change',useClass);
$('excludeHint').textContent='Có thể xóa dòng học sinh vắng trước khi bắt đầu. Lịch sử lưu riêng theo lớp và tài khoản trên trình duyệt này.';
const remembered=load('selectedClass','');if(Object.hasOwn(rosters,remembered))classSelect.value=remembered;
classSelect.addEventListener('change',()=>save('selectedClass',classSelect.value));useClass();
goEl.onclick=start;
cv.addEventListener('click',()=>{if(!busy())start()});
document.addEventListener('keydown',e=>{if(e.code==='Space'&&!/TEXTAREA|INPUT|BUTTON/.test(e.target.tagName)){e.preventDefault();start()}});
$('numRange').addEventListener('input',e=>setNum(+e.target.value));
$('numMinus').onclick=()=>setNum(numCount-1);$('numPlus').onclick=()=>setNum(numCount+1);
document.querySelectorAll('input[name=mode]').forEach(r=>r.onchange=()=>{if(busy()){applyMode();return}mode=r.value;save('mode',mode);called=[];save('called',called);applyMode();setup()});
listEl.addEventListener('input',()=>{save('names',listEl.value);if(!busy())setup()});
$('noRepeat').onchange=()=>{if(!busy())setup()};
$('addForm').addEventListener('submit',e=>{e.preventDefault();const inp=$('newName'),v=inp.value.trim();if(!v)return;
  listEl.value=(listEl.value.trim()?listEl.value.replace(/\s+$/,'')+'\n':'')+v;inp.value='';inp.focus();save('names',listEl.value);if(!busy())setup()});
$('clearList').onclick=()=>{if(busy())return;listEl.value='';save('names','');called=[];save('called',called);setup();$('newName').focus()};
$('reset').onclick=()=>{if(busy())return;called=[];save('called',called);setup()};
if(document.fullscreenEnabled){$('fs').hidden=false;$('fs').onclick=()=>{try{document.fullscreenElement?document.exitFullscreen():$('stagewrap').requestFullscreen().catch(()=>{})}catch(e){}}}
document.addEventListener('fullscreenchange',()=>setTimeout(resize,50));
addEventListener('resize',resize);
$('twist').checked=!!load('twist',false);$('twist').onchange=()=>save('twist',$('twist').checked);
$('mix').checked=!!load('mix',false);$('mix').onchange=()=>{save('mix',$('mix').checked);if(!busy())setup()};
const durTxt=()=>{$('durOut').textContent=durSet+' giây'};$('durRange').value=durSet;durTxt();
$('durRange').addEventListener('input',e=>{durSet=+e.target.value;save('dur',durSet);durTxt()});
$('testSound').onclick=()=>{unlockAudio();if(!$('sound').checked){$('sound').checked=true;unlockAudio()}if(window.CDSCallingAudio){window.CDSCallingAudio.setEnabled(true);window.CDSCallingAudio.test();}else{fanfare();cluck()}};
$('sound').addEventListener('change',()=>{window.CDSCallingAudio?.setEnabled($('sound').checked);if($('sound').checked)unlockAudio();else{if(silentEl)silentEl.pause();if(audio?.state==='running')audio.suspend().catch(()=>{})}});
applyMode();resize();setup();requestAnimationFrame(frame);

document.addEventListener('visibilitychange',()=>{if(document.hidden){if(silentEl)silentEl.pause();if(audio?.state==='running')audio.suspend().catch(()=>{})}});
window.addEventListener('pagehide',()=>{if(silentEl)silentEl.pause();if(audio)audio.close().catch(()=>{})});
