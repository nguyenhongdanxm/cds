/* Recorded CC0 bark + phase-locked original Web Audio score. All assets are local. */
(function(root){'use strict';
class MucAudio{
 constructor(options={}){this.enabled=true;this.ctx=null;this.master=null;this.nodes=new Map();this.phase='';this.beat=-1;this.barkWindows=[];this.elapsed=0;this.warning=options.onWarning||(()=>{});this.warned=false;this.buffer=null;this.decoding=null;this.bytes=null;this.falls=new Set();this.stepBeat=-1;this.cries=new Set();const Voice=root.CDSMucVoice;this.voice=Voice?new Voice({onStatus:options.onVoiceStatus,onSpeaking:value=>{if(this.master)this.master.gain.value=value?.2:.65;}}):{cancel(){},reset(){},setEnabled(){},update(){},test(){return false;}};
  const url=options.url||new URL('muc/dog-bark.mp3',document.currentScript.src).href;
  this.download=typeof fetch==='function'?fetch(url).then(r=>{if(!r.ok)throw Error('bark');return r.arrayBuffer();}).then(b=>{this.bytes=b;return b;}).catch(()=>null):Promise.resolve(null);
 }
 warn(text){if(!this.warned){this.warned=true;this.warning(text);}}
 async unlock(){if(!this.enabled)return false;try{const C=root.AudioContext||root.webkitAudioContext;if(!this.ctx){if(!C)throw Error('unsupported');this.ctx=new C();this.master=this.ctx.createGain();this.master.gain.value=.65;if(this.ctx.createDynamicsCompressor){this.compressor=this.ctx.createDynamicsCompressor();this.compressor.threshold.value=-16;this.compressor.knee.value=18;this.compressor.ratio.value=3;this.compressor.attack.value=.008;this.compressor.release.value=.18;this.master.connect(this.compressor);this.compressor.connect(this.ctx.destination);}else this.master.connect(this.ctx.destination);}await this.ctx.resume();if(!this.buffer&&!this.decoding){this.decoding=this.download.then(bytes=>bytes?this.ctx.decodeAudioData(bytes.slice(0)):null).then(buffer=>{this.buffer=buffer;if(!buffer)this.warn('Chưa tải được tiếng chó. Hãy tải lại trang rồi bấm Thử tiếng chó.');}).catch(()=>this.warn('Thiết bị chưa giải mã được tiếng chó.'));}return this.ctx.state==='running';}catch(e){this.warn('Trình duyệt chưa phát được âm thanh. Hãy kiểm tra âm lượng hoặc thử trình duyệt khác.');return false;}}
 track(node,cleanup=()=>{}){this.nodes.set(node,cleanup);node.onended=()=>{this.nodes.delete(node);try{node.disconnect();cleanup();}catch(e){}};return node;}
 stopNodes(){for(const [node,cleanup] of this.nodes){try{node.stop();node.disconnect();cleanup();}catch(e){}}this.nodes.clear();this.barkWindows=[];}
 begin(){this.stopNodes();this.voice.reset();this.falls.clear();this.cries.clear();this.stepBeat=-1;this.phase='';this.beat=-1;this.elapsed=0;return this.unlock();}
 pause(){this.voice.cancel();if(this.ctx)this.ctx.suspend().catch(()=>{});}
 resume(){return this.unlock();}
 stop(){this.stopNodes();this.voice.cancel();this.phase='';this.beat=-1;}
 setEnabled(value){this.enabled=value;this.stop();if(value)return this.unlock();this.pause();return Promise.resolve(false);}
 tone(freq,length=.15,volume=.035,type='triangle',delay=0){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const c=this.ctx,o=c.createOscillator(),g=c.createGain(),t=c.currentTime+delay;this.track(o,()=>g.disconnect());o.type=type;o.frequency.setValueAtTime(freq,t);g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(Math.max(.0002,volume),t+.008);g.gain.exponentialRampToValueAtTime(.0001,t+length);o.connect(g);g.connect(this.master);o.start(t);o.stop(t+length+.01);}
 bass(freq,length=.25){this.tone(freq,length,.12,'sine');this.tone(freq*2,length*.8,.025,'triangle');}
 kick(){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const c=this.ctx,o=c.createOscillator(),g=c.createGain(),t=c.currentTime;this.track(o,()=>g.disconnect());o.type='sine';o.frequency.setValueAtTime(125,t);o.frequency.exponentialRampToValueAtTime(42,t+.17);g.gain.setValueAtTime(.23,t);g.gain.exponentialRampToValueAtTime(.0001,t+.24);o.connect(g);g.connect(this.master);o.start(t);o.stop(t+.25);}
 noise(length=.055,volume=.025,frequency=1200,delay=0){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const c=this.ctx,buffer=c.createBuffer(1,Math.ceil(c.sampleRate*length),c.sampleRate),data=buffer.getChannelData(0);for(let i=0;i<data.length;i++)data[i]=(Math.random()*2-1)*(1-i/data.length);const source=c.createBufferSource(),filter=c.createBiquadFilter(),gain=c.createGain();this.track(source,()=>{filter.disconnect();gain.disconnect();});source.buffer=buffer;filter.type='lowpass';filter.frequency.value=frequency;gain.gain.value=volume;source.connect(filter);filter.connect(gain);gain.connect(this.master);source.start(c.currentTime+delay);}
 footstep(delay=0){this.tone(92,.075,.052,'sine',delay);this.tone(57,.065,.035,'triangle',delay+.008);this.noise(.035,.035,420,delay);}
 scream(){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const c=this.ctx,o=c.createOscillator(),t=c.currentTime;o.type='sawtooth';o.frequency.setValueAtTime(410,t);o.frequency.exponentialRampToValueAtTime(690,t+.08);o.frequency.exponentialRampToValueAtTime(370,t+.4);const filters=[],gains=[];for(const [freq,volume] of [[850,.026],[1250,.018],[2800,.008]]){const f=c.createBiquadFilter(),g=c.createGain();f.type='bandpass';f.frequency.value=freq;f.Q.value=6;g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(volume,t+.03);g.gain.exponentialRampToValueAtTime(.0001,t+.43);o.connect(f);f.connect(g);g.connect(this.master);filters.push(f);gains.push(g);}this.track(o,()=>{filters.forEach(f=>f.disconnect());gains.forEach(g=>g.disconnect());});o.start(t);o.stop(t+.44);}
 bark(delay=0){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const at=this.elapsed+delay*1000;this.barkWindows.push([at,at+410]);if(this.buffer){const source=this.ctx.createBufferSource(),g=this.ctx.createGain();this.track(source,()=>g.disconnect());source.buffer=this.buffer;g.gain.value=.85;source.connect(g);g.connect(this.master);source.start(this.ctx.currentTime+delay);}else{this.tone(130,.13,.075,'sawtooth',delay);this.noise(.16,.08,1100,delay+.015);}}
 isBarking(elapsed){this.barkWindows=this.barkWindows.filter(w=>w[1]>=elapsed-100);return this.barkWindows.some(w=>elapsed>=w[0]&&elapsed<=w[1]);}
 enter(phase){this.stopNodes();this.phase=phase;this.beat=-1;if(phase==='wake'){this.bark(.04);this.bark(.38);this.bark(.8);}if(phase==='chase'){this.bark();this.bark(.38);}if(phase==='catch')this.bark();}
 update(phase,elapsed,total,story={}){this.elapsed=elapsed;if(!this.enabled)return;this.voice.update(story.dialogue);if(!this.ctx||this.ctx.state!=='running')return;if(this.phase!==phase)this.enter(phase);for(const id of story.fallenIds||[]){if(!this.falls.has(id)){this.falls.add(id);this.noise(.09,.06,650);this.tone(95,.13,.06,'sine');}}
 const starts={fear:0,sneak:.1,wake:.22,chase:.28,hide:.64,stumble:.82,catch:.94},tempo={fear:510,sneak:390,chase:170,hide:160,stumble:290};if(!tempo[phase])return;
 if(phase==='chase'||phase==='hide'){const step=Math.floor(elapsed/125);if(step!==this.stepBeat){this.stepBeat=step;this.footstep();this.footstep(.055);}const cry=Math.floor(Math.max(0,elapsed-.28*total)/1250);if(!this.cries.has(cry)){this.cries.add(cry);this.scream();}}
 const local=Math.max(0,elapsed-(starts[phase]||0)*total),beat=Math.floor(local/tempo[phase]);if(beat===this.beat)return;this.beat=beat;
 if(phase==='fear'){if(beat%2===0){this.tone(70,.12,.085,'sine');this.tone(62,.1,.05,'sine',.19);}if(beat%4===0){this.tone(220,.65,.018,'triangle');this.tone(261.63,.65,.013,'sine');}}
 if(phase==='sneak'){const notes=[220,0,261.63,0,246.94,0,196,0];if(notes[beat%8])this.tone(notes[beat%8],.09,.032,'triangle');this.noise(.035,.023,650);}
 if(phase==='chase'||phase==='hide'){
  // Original D-minor chase score: sub bass, pitched kick, snare, hats and urgent ostinato.
  const bass=[73.42,73.42,65.41,65.41,58.27,58.27,69.30,65.41],ostinato=[293.66,349.23,440,349.23,293.66,261.63,233.08,261.63];
  if(beat%2===0){this.kick();this.bass(bass[Math.floor(beat/2)%8],.3);}if(beat%4===2){this.noise(.12,.075,2300);this.tone(180,.07,.025,'triangle');}
  this.noise(.025,.016,6500);this.tone(ostinato[beat%8],.1,.025,'sawtooth');if(beat%4===0){this.tone(146.83,.5,.018,'triangle');this.tone(174.61,.5,.012,'triangle');}
  if(beat>=5&&beat%5===0)this.bark();
 }
 if(phase==='stumble'){if(beat%2===0){this.kick();this.bass(55,.24);}if(beat%4===0)this.bark();}

 }
 win(){this.stopNodes();this.phase='';this.beat=-1;if(!this.enabled)return;this.tone(65.41,.32,.055,'sine');this.tone(73.42,.32,.035,'triangle',.18);}
 async test(){if(!await this.unlock())return false;await this.decoding;this.elapsed=0;this.stopNodes();this.bark();this.bark(.48);return !!this.buffer;}
}
root.CDSMucAudio=MucAudio;if(typeof module==='object'&&module.exports)module.exports=MucAudio;
})(typeof window!=='undefined'?window:globalThis);
