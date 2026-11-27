(()=>{'use strict';
class AchievementAudio{
 constructor(base){this.base=base;this.effects=false;this.music=false;this.sfxVolume=.7;this.musicVolume=.35;this.current=null;this.bg=null;this.duckTimer=null;this.onState=()=>{};try{const saved=JSON.parse(localStorage.getItem('ab-audio-volume-v1')||'{}');for(const [k,v] of Object.entries(saved))if(['sfxVolume','musicVolume'].includes(k)&&Number.isFinite(v))this[k]=Math.min(1,Math.max(0,v));}catch{}document.addEventListener('visibilitychange',()=>{if(document.hidden){this.bg?.pause();this.current?.pause();this.current=null;clearTimeout(this.duckTimer);this.restore();}else if(this.music)this.startMusic()});window.addEventListener('pagehide',()=>{this.bg?.pause();this.current?.pause()});}
 path(name){return this.base+'assets/achievement/audio/'+name+'.mp3';}
 setEffects(on){this.effects=!!on;if(!on){this.current?.pause();clearTimeout(this.duckTimer);this.restore();}this.onState();}
 async setMusic(on){this.music=!!on;if(!on)this.bg?.pause();else await this.startMusic();this.onState();}
 async startMusic(){if(!this.music||document.hidden)return;try{if(!this.bg){this.bg=new Audio(this.path('carefree'));this.bg.loop=true;this.bg.preload='none';}this.bg.volume=this.musicVolume;await this.bg.play();}catch{this.music=false;this.onState('Trình duyệt chưa cho phát nhạc. Hãy bấm bật nhạc một lần nữa.');}}
 setVolume(kind,value){const n=Number(value);if(!Number.isFinite(n))return;this[kind==='music'?'musicVolume':'sfxVolume']=Math.min(1,Math.max(0,n));if(this.bg)this.bg.volume=this.musicVolume;try{localStorage.setItem('ab-audio-volume-v1',JSON.stringify({sfxVolume:this.sfxVolume,musicVolume:this.musicVolume}))}catch{}}
 restore(){if(this.bg)this.bg.volume=this.musicVolume;}
 async play(kind){if(!this.effects||document.hidden)return;this.current?.pause();clearTimeout(this.duckTimer);const name=kind==='big'?'celebrate':kind==='tap'?'tap':'reward';const a=new Audio(this.path(name));this.current=a;a.volume=this.sfxVolume*(kind==='gentle' ? .65 : 1);a.playbackRate=kind==='gentle' ? .85 : 1;if(this.bg)this.bg.volume=this.musicVolume*.25;const release=()=>{if(this.current===a){this.current=null;this.restore();}};a.addEventListener('ended',release,{once:true});a.addEventListener('error',release,{once:true});try{await a.play();this.duckTimer=setTimeout(release,4000);}catch{release();}}
}
window.AchievementAudio=AchievementAudio;
})();
