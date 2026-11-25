/* Local CC0 voices and CC BY music for Egg; original procedural space score for UFO. */
(()=>{'use strict';
const script=document.currentScript,root=new URL('audio/',script.src),game=document.body.dataset.game;
const sound=document.getElementById('sound'),musicControl=document.getElementById('musicVolume'),fxControl=document.getElementById('effectsVolume');
const key='cds-calling-audio-'+document.body.dataset.user;
let settings={music:.2,effects:.55};try{settings={...settings,...JSON.parse(localStorage.getItem(key)||'{}')}}catch{}
const level=v=>Number.isFinite(+v)?Math.max(0,Math.min(1,+v)):0;
settings.music=level(settings.music);settings.effects=level(settings.effects);
let ctx=null,musicGain=null,fxGain=null,enabled=sound.checked,running=false,suspended=false,phase='',music=null,musicName='',sequence=null,step=0,lastCluck=-Infinity;
const voices=new Set(),nodes=new Set();
const files={cluck:'ga_cuc_tac.mp3',angry:'ga_me_tuc_gian.mp3',alarm:'ga_keu_hoang_hot.mp3',shout:'la_het_khi_chay.mp3',scream:'la_hoat_hinh.mp3',sneak:'nhac_ron_ren_30s.mp3',chase:'nhac_ruot_duoi_30s.mp3'};
function save(){try{localStorage.setItem(key,JSON.stringify(settings))}catch{}}
function context(){if(!ctx){const AC=window.AudioContext||window.webkitAudioContext;if(!AC)return null;try{ctx=new AC();const limiter=ctx.createDynamicsCompressor();limiter.threshold.value=-18;limiter.ratio.value=6;musicGain=ctx.createGain();fxGain=ctx.createGain();musicGain.connect(limiter);fxGain.connect(limiter);limiter.connect(ctx.destination);gains()}catch{return null}}return ctx}
function gains(){if(musicGain)musicGain.gain.value=settings.music;if(fxGain)fxGain.gain.value=settings.effects;if(music)music.volume=settings.music;for(const a of voices)a.volume=settings.effects}
function canPlay(){return enabled&&!suspended&&!document.hidden}
function tone(from,to,duration=.4,type='sine',volume=.12,channel='effects',delay=0){
 if(!canPlay())return;const ac=context();if(!ac||ac.state!=='running')return;
 try{const o=ac.createOscillator(),g=ac.createGain(),t=ac.currentTime+delay;o.type=type;o.frequency.setValueAtTime(from,t);o.frequency.exponentialRampToValueAtTime(Math.max(20,to),t+duration);g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(volume,t+.025);g.gain.exponentialRampToValueAtTime(.0001,t+duration);o.connect(g);g.connect(channel==='music'?musicGain:fxGain);nodes.add(o);o.onended=()=>{nodes.delete(o);o.disconnect();g.disconnect()};o.start(t);o.stop(t+duration+.03)}catch{}
}
function playFile(name){
 if(!canPlay()||voices.size>=4)return false;
 const a=new Audio(new URL(files[name],root).href);a.preload='none';a.volume=settings.effects;a.setAttribute('playsinline','');voices.add(a);
 const clean=()=>voices.delete(a);a.onended=clean;a.onerror=clean;
 try{const p=a.play();if(p?.catch)p.catch(clean)}catch{clean()}return true;
}
function stopMusic(){if(music){music.pause();music.currentTime=0;music=null}musicName='';if(sequence!==null){clearInterval(sequence);sequence=null}for(const n of [...nodes]){try{n.stop()}catch{}}}
function stopVoices(){for(const a of voices){a.pause();a.currentTime=0}voices.clear()}
function musicFile(name){if(!canPlay()||musicName===name)return;stopMusic();musicName=name;music=new Audio(new URL(files[name],root).href);music.loop=true;music.preload='none';music.volume=settings.music;music.setAttribute('playsinline','');const a=music;try{a.play()?.catch(()=>{if(music===a){music=null;musicName=''}})}catch{music=null;musicName=''}}
function spaceMusic(){if(!canPlay()||sequence!==null)return;stopMusic();musicName='space';step=0;
 const notes=[130.81,196,261.63,392,164.81,246.94,329.63,493.88];
 const tick=()=>{if(!canPlay()||!running)return;const beam=phase==='beam';tone(notes[step%notes.length],notes[step%notes.length],beam?.13:.32,'sine',.11,'music');if(step%8===0)tone(65.41,65.41,1.4,'triangle',.1,'music');step++};tick();sequence=setInterval(tick,220);
}
function applyMusic(){if(!running||!canPlay())return;if(game==='egg')musicFile(['sleep','sneak'].includes(phase)?'sneak':'chase');else spaceMusic()}
function unlock(){if(!enabled||document.hidden)return;const ac=context();if(ac?.state==='suspended')ac.resume()?.catch(()=>{});if(suspended){suspended=false;applyMusic()}}
function effect(name){if(!canPlay())return false;
 if(name==='cluck'){const now=performance.now();if(now-lastCluck<900)return true;lastCluck=now;return playFile('cluck')}
 if(['angry','alarm','shout','scream'].includes(name))return playFile(name);
 if(name==='arrival'){tone(90,420,1.1,'triangle',.14);tone(620,180,.8,'sine',.1,'effects',.15)}
 else if(name==='beam'){tone(150,1100,1.25,'sine',.14);tone(310,1800,1.1,'triangle',.045)}
 else if(name==='drop'){tone(950,130,.5,'sine',.15);playFile('scream')}
 else if(name==='success'){[523.25,659.25,783.99,1046.5].forEach((f,i)=>tone(f,f,.32,'sine',.16,'effects',i*.1));if(game==='ufo')tone(240,1600,1,'triangle',.1)}
 return true;
}
function sync(next){if(!running||next===phase)return;phase=next;applyMusic();if(!canPlay())return;
 if(game==='egg'){
  if(next==='wake'){effect('angry');effect('shout')}
  else if(next==='chase')effect('alarm');
  else if(next==='fake')effect('scream');
 }else{
  if(next==='alarm')effect('arrival');
  else if(next==='beam')effect('beam');
  else if(next==='drop')effect('drop');
 }
}
function start(){stop();running=true;suspended=false;phase='';unlock()}
function stop(){running=false;stopMusic();stopVoices();phase=''}
function finish(){stop();effect('success')}
function setEnabled(value){enabled=!!value;if(!enabled){stopMusic();stopVoices();if(ctx?.state==='running')ctx.suspend()?.catch(()=>{})}else{unlock();applyMusic()}}
function test(){stop();suspended=false;unlock();if(game==='egg'){effect('cluck');musicFile('sneak')}else{running=true;phase='beam';spaceMusic();effect('arrival');effect('beam')}testTimer=setTimeout(stop,3500)}
let testTimer=null;const stopOriginal=stop; // cancel preview timers when a round starts or a new preview is requested
function clearPreview(){if(testTimer!==null){clearTimeout(testTimer);testTimer=null}}
for(const [el,name]of[[musicControl,'music'],[fxControl,'effects']])if(el){el.value=Math.round(settings[name]*100);el.addEventListener('input',()=>{settings[name]=level(el.value/100);gains();save()})}
document.addEventListener('visibilitychange',()=>{if(document.hidden){suspended=true;stopMusic();stopVoices();if(ctx?.state==='running')ctx.suspend()?.catch(()=>{})}});
window.addEventListener('pagehide',()=>{clearPreview();stop()});
window.CDSCallingAudio={unlock,effect,sync,start(){clearPreview();start()},stop(){clearPreview();stopOriginal()},finish,setEnabled,test(){clearPreview();test()},get effectsVolume(){return settings.effects}};
})();
