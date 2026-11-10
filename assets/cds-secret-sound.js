/* Kenney CC0 samples hosted with the game; see secret-audio/LICENSES.md. */
(function(){'use strict';
const base=new URL('secret-audio/',document.currentScript?.src||location.href),files={click:'click.mp3',open:'open.mp3',right:'correct.mp3',wrong:'wrong.mp3',win:'win.mp3',loss:'loss.mp3',sparkle:'sparkle.mp3',tick:'tick.mp3'},buffers=new Map(),sources=new Set();let context=null,enabled=false,loading=null,lastClick=0;
function load(){if(loading)return loading;loading=Promise.allSettled(Object.entries(files).map(async([id,file])=>{try{const r=await fetch(new URL(file,base));if(!r.ok)return;const data=await r.arrayBuffer(),buffer=await context.decodeAudioData(data);buffers.set(id,buffer);}catch{}}));return loading;}
async function enable(ctx){context=ctx;enabled=true;load();}
function stop(){for(const s of sources){try{s.stop();}catch{}}sources.clear();}
function disable(){enabled=false;stop();}
const mapping={click:['click',1,.35],open:['open',1,.5],right:['right',1,.55],wrong:['wrong',1,.35],win:['win',1,.5],time:['tick',1,.3],reveal:['sparkle',1.3,.4],bonus:['sparkle',1,.55],double:['right',1.2,.55],lucky:['win',1.25,.5],loss:['loss',1,.5],half:['loss',.8,.45],swap:['open',.8,.6],steal:['sparkle',.85,.55],party:['win',1.12,.5],rocket:['sparkle',1.4,.5],again:['right',1.3,.5]};
function play(kind){if(!enabled||!context||context.state==='suspended')return false;const [id,rate,volume]=mapping[kind]||mapping.right,buffer=buffers.get(id);if(!buffer)return false;if(kind==='click'){const now=Date.now();if(now-lastClick<70)return true;lastClick=now;}try{const s=context.createBufferSource(),g=context.createGain();s.buffer=buffer;s.playbackRate.value=rate;g.gain.value=volume;s.connect(g);g.connect(context.destination);s.onended=()=>sources.delete(s);sources.add(s);s.start();return true;}catch{return false;}}
window.SecretSound={enable,disable,play,stop};
})();
