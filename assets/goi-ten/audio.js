/* Local CC0 voices and CC BY music for Egg; original procedural space score for UFO. */
(()=>{'use strict';
const script=document.currentScript,root=new URL('audio/',script.src),game=document.body.dataset.game;
const sound=document.getElementById('sound'),musicControl=document.getElementById('musicVolume'),fxControl=document.getElementById('effectsVolume');
const key='cds-calling-audio-'+document.body.dataset.user;
let settings={music:.3,effects:.55};try{settings={...settings,...JSON.parse(localStorage.getItem(key)||'{}')}}catch{}
const level=v=>Number.isFinite(+v)?Math.max(0,Math.min(1,+v)):0;
settings.music=level(settings.music);settings.effects=level(settings.effects);
let ctx=null,musicGain=null,fxGain=null,enabled=sound.checked,running=false,suspended=false,phase='',music=null,musicName='',sequence=null,step=0,lastCluck=-Infinity,lastReaction=-Infinity,lastPhrase='',nextReaction=0,tension=0,henSpoken=false;
const voices=new Set(),nodes=new Set(),preloaded=new Map();
function warmVoices(){for(const name of ['oi_oi','oai','chay_mau','so_qua','thot_tim','cuu_voi','thoat_roi','henLine'])if(!preloaded.has(name)){const a=new Audio(audioSource(name));a.preload='auto';a.load?.();preloaded.set(name,a)}}
const files={cluck:'ga_cuc_tac.mp3',angry:'ga_me_tuc_gian.mp3',alarm:'ga_keu_hoang_hot.mp3',shout:'la_het_khi_chay.mp3',scream:'la_hoat_hinh.mp3',sneak:'nhac_ron_ren_30s.mp3',chase:'nhac_ruot_duoi_30s.mp3',oi_oi:'reaction_oi_oi.mp3',oai:'reaction_oai.mp3',chay_mau:'reaction_chay_mau.mp3',so_qua:'reaction_so_qua.mp3',thot_tim:'reaction_thot_tim.mp3',cuu_voi:'reaction_cuu_voi.mp3',thoat_roi:'reaction_thoat_roi.mp3',henLine:'hen_chase_line.mp3'};
function audioSource(name){const u=new URL(files[name],root);if(name==='henLine'||files[name].startsWith('reaction_'))u.searchParams.set('v',new URL(script.src).searchParams.get('v')||'6');return u.href}
function save(){try{localStorage.setItem(key,JSON.stringify(settings))}catch{}}
function context(){if(!ctx){const AC=window.AudioContext||window.webkitAudioContext;if(!AC)return null;try{ctx=new AC();const limiter=ctx.createDynamicsCompressor();limiter.threshold.value=-18;limiter.ratio.value=6;musicGain=ctx.createGain();fxGain=ctx.createGain();musicGain.connect(limiter);fxGain.connect(limiter);limiter.connect(ctx.destination);gains()}catch{return null}}return ctx}
function musicLevel(){const duck=[...voices].some(a=>a.reaction)?.35:1;return Math.min(1,settings.music*(game==='ufo'?1.45:1.2))*duck}
function gains(){if(musicGain)musicGain.gain.value=musicLevel();if(fxGain)fxGain.gain.value=settings.effects;if(music)music.volume=musicLevel();for(const a of voices)a.volume=settings.effects}
function canPlay(){return enabled&&!suspended&&!document.hidden}
function tone(from,to,duration=.4,type='sine',volume=.12,channel='effects',delay=0){
 if(!canPlay())return;const ac=context();if(!ac||ac.state!=='running')return;
 try{const o=ac.createOscillator(),g=ac.createGain(),t=ac.currentTime+delay;o.type=type;o.frequency.setValueAtTime(from,t);o.frequency.exponentialRampToValueAtTime(Math.max(20,to),t+duration);g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(volume,t+.025);g.gain.exponentialRampToValueAtTime(.0001,t+duration);o.connect(g);g.connect(channel==='music'?musicGain:fxGain);nodes.add(o);o.onended=()=>{nodes.delete(o);o.disconnect();g.disconnect()};o.start(t);o.stop(t+duration+.03)}catch{}
}
function playFile(name,reaction=false){
 if(!canPlay()||voices.size>=4)return false;
 const a=new Audio(audioSource(name));a.preload='none';a.reaction=reaction;a.volume=settings.effects;a.setAttribute('playsinline','');voices.add(a);
 gains();const clean=()=>{voices.delete(a);gains()};a.onended=clean;a.onerror=clean;
 try{const p=a.play();if(p?.catch)p.catch(clean)}catch{clean()}return true;
}
function stopMusic(){if(music){music.pause();music.currentTime=0;music=null}musicName='';if(sequence!==null){clearInterval(sequence);sequence=null}for(const n of [...nodes]){try{n.stop()}catch{}}}
function stopVoices(keepSpeech=false){for(const a of [...voices]){if(keepSpeech&&a.reaction)continue;a.pause();a.currentTime=0;voices.delete(a)}gains()}
function musicFile(name){if(!canPlay()||musicName===name)return;stopMusic();musicName=name;music=new Audio(audioSource(name));music.loop=true;music.preload='none';music.volume=musicLevel();music.setAttribute('playsinline','');const a=music;try{a.play()?.catch(()=>{if(music===a){music=null;musicName=''}})}catch{music=null;musicName=''}}
function spaceMusic(){if(!canPlay()||sequence!==null)return;stopMusic();musicName='space';step=0;
 const notes=[130.81,196,155.56,246.94,146.83,207.65,164.81,233.08];
 const tick=()=>{if(!canPlay()||!running)return;const beam=phase==='beam',boost=1+tension*.45,f=notes[step%notes.length]*(beam?2:1);tone(f,f,beam?.12:.22,'triangle',.15*boost,'music');if(step%4===0){tone(100,38,.18,'sine',.28*boost,'music');tone(65.41,65.41,.6,'triangle',.12,'music')}if(step%2===1)tone(1800,750,.045,'sine',.035,'music');if(beam&&step%3===0)tone(310,1200,.38,'sine',.055,'music');step++};tick();sequence=setInterval(tick,155);
}
function applyMusic(){if(!running||!canPlay())return;if(game==='egg'){const track=['sleep','sneak'].includes(phase)?'sneak':'chase';musicFile(track);if(track==='chase'&&sequence===null){step=0;sequence=setInterval(()=>{if(!canPlay()||!running)return;if(step%2===0)tone(95,42,.16,'sine',.14*(1+tension*.4),'music');else tone(1050,500,.04,'triangle',.025,'music');step++},240)}}else spaceMusic()}
function unlock(){if(!enabled||document.hidden)return;warmVoices();const ac=context();if(ac?.state==='suspended')ac.resume()?.catch(()=>{});if(suspended){suspended=false;applyMusic()}}
function effect(name){if(!canPlay())return false;
 if(name==='cluck'){if([...voices].some(a=>a.reaction))return true;const now=performance.now();if(now-lastCluck<900)return true;lastCluck=now;return playFile('cluck')}
 if(['angry','alarm','shout','scream'].includes(name))return playFile(name);
 if(name==='arrival'){tone(90,420,1.1,'triangle',.14);tone(620,180,.8,'sine',.1,'effects',.15)}
 else if(name==='beam'){tone(80,1200,1.6,'sine',.2);tone(160,1700,1.25,'triangle',.07);for(let i=0;i<5;i++)tone(300+i*90,950+i*150,.24,'sine',.09,'effects',i*.18)}
 else if(name==='drop'){tone(950,130,.65,'sine',.17);if(!react('relief'))playFile('scream')}
 else if(name==='success'){[523.25,659.25,783.99,1046.5].forEach((f,i)=>tone(f,f,.32,'sine',.16,'effects',i*.1));if(game==='ufo')tone(240,1600,1,'triangle',.1)}
 return true;
}
function react(kind='fear'){
 if(!running||!canPlay())return false;const now=performance.now();if(now-lastReaction<2600||[...voices].some(a=>a.reaction))return false;
 const options=(kind==='relief'?['thot_tim','thoat_roi','oi_oi']:['oi_oi','oai','chay_mau','so_qua','cuu_voi']).filter(k=>k!==lastPhrase),name=options[Math.floor(Math.random()*options.length)];
 if(!playFile(name,true))return false;lastReaction=now;lastPhrase=name;nextReaction=now+4500+Math.random()*2500;return true;
}
function sync(next,progress=0){tension=Math.max(0,Math.min(1,Number(progress)||0));if(!running)return;if(next===phase){if(canPlay()&&performance.now()>nextReaction&&['chase','fly','beam'].includes(next))react();return;}phase=next;applyMusic();if(!canPlay())return;
 if(game==='egg'){
  if(next==='wake')effect('angry');
  else if(next==='chase'){if(!henSpoken){henSpoken=true;stopVoices();playFile('henLine',true);lastReaction=performance.now();lastPhrase='henLine';nextReaction=lastReaction+8500}else if(![...voices].some(a=>a.reaction))effect('alarm');}
  else if(next==='fake')react('relief');
 }else{
  if(next==='alarm'){effect('arrival');react()}
  else if(next==='beam'){effect('beam');react()}
  else if(next==='drop')effect('drop');
 }
}
function start(){stop();running=true;suspended=false;phase='';tension=0;henSpoken=false;lastReaction=-Infinity;nextReaction=performance.now()+4500;unlock()}
function stop(){running=false;stopMusic();stopVoices();phase=''}
function finish(){const speaking=[...voices].some(a=>a.reaction);running=false;stopMusic();stopVoices(true);phase='';if(!speaking)effect('success')}
function setEnabled(value){enabled=!!value;if(!enabled){stopMusic();stopVoices();if(ctx?.state==='running')ctx.suspend()?.catch(()=>{})}else{unlock();applyMusic()}}
function test(){stop();suspended=false;unlock();if(game==='egg'){running=true;phase='wake';effect('cluck');musicFile('sneak');react()}else{running=true;phase='beam';spaceMusic();effect('arrival');effect('beam');react()}testTimer=setTimeout(stop,3500)}
let testTimer=null;const stopOriginal=stop; // cancel preview timers when a round starts or a new preview is requested
function clearPreview(){if(testTimer!==null){clearTimeout(testTimer);testTimer=null}}
for(const [el,name]of[[musicControl,'music'],[fxControl,'effects']])if(el){el.value=Math.round(settings[name]*100);el.addEventListener('input',()=>{settings[name]=level(el.value/100);gains();save()})}
document.addEventListener('visibilitychange',()=>{if(document.hidden){suspended=true;stopMusic();stopVoices();if(ctx?.state==='running')ctx.suspend()?.catch(()=>{})}});
window.addEventListener('pagehide',()=>{clearPreview();stop()});
window.CDSCallingAudio={unlock,effect,sync,start(){clearPreview();start()},stop(){clearPreview();stopOriginal()},finish,setEnabled,test(){clearPreview();test()},get effectsVolume(){return settings.effects}};
})();
