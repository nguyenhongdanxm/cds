/* Recorded CC0 bark + phase-locked original Web Audio score. All assets are local. */
(function(root){'use strict';
class MucAudio{
 constructor(options={}){this.enabled=true;this.ctx=null;this.master=null;this.nodes=new Map();this.phase='';this.beat=-1;this.barkWindows=[];this.elapsed=0;this.warning=options.onWarning||(()=>{});this.warned=false;this.buffer=null;this.decoding=null;this.bytes=null;
  const url=options.url||new URL('muc/dog-bark.mp3',document.currentScript.src).href;
  this.download=typeof fetch==='function'?fetch(url).then(r=>{if(!r.ok)throw Error('bark');return r.arrayBuffer();}).then(b=>{this.bytes=b;return b;}).catch(()=>null):Promise.resolve(null);
 }
 warn(text){if(!this.warned){this.warned=true;this.warning(text);}}
 async unlock(){if(!this.enabled)return false;try{const C=root.AudioContext||root.webkitAudioContext;if(!this.ctx){if(!C)throw Error('unsupported');this.ctx=new C();this.master=this.ctx.createGain();this.master.gain.value=.65;this.master.connect(this.ctx.destination);}await this.ctx.resume();if(!this.buffer&&!this.decoding){this.decoding=this.download.then(bytes=>bytes?this.ctx.decodeAudioData(bytes.slice(0)):null).then(buffer=>{this.buffer=buffer;if(!buffer)this.warn('Chưa tải được tiếng chó. Hãy tải lại trang rồi bấm Thử tiếng chó.');}).catch(()=>this.warn('Thiết bị chưa giải mã được tiếng chó.'));}return this.ctx.state==='running';}catch(e){this.warn('Trình duyệt chưa phát được âm thanh. Hãy kiểm tra âm lượng hoặc thử trình duyệt khác.');return false;}}
 track(node,cleanup=()=>{}){this.nodes.set(node,cleanup);node.onended=()=>{this.nodes.delete(node);try{node.disconnect();cleanup();}catch(e){}};return node;}
 stopNodes(){for(const [node,cleanup] of this.nodes){try{node.stop();node.disconnect();cleanup();}catch(e){}}this.nodes.clear();this.barkWindows=[];}
 begin(){this.stopNodes();this.phase='';this.beat=-1;this.elapsed=0;return this.unlock();}
 pause(){if(this.ctx)this.ctx.suspend().catch(()=>{});}
 resume(){return this.unlock();}
 stop(){this.stopNodes();this.phase='';this.beat=-1;}
 setEnabled(value){this.enabled=value;this.stop();if(value)return this.unlock();this.pause();return Promise.resolve(false);}
 tone(freq,length=.15,volume=.035,type='triangle',delay=0){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const c=this.ctx,o=c.createOscillator(),g=c.createGain(),t=c.currentTime+delay;this.track(o,()=>g.disconnect());o.type=type;o.frequency.setValueAtTime(freq,t);g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(Math.max(.0002,volume),t+.008);g.gain.exponentialRampToValueAtTime(.0001,t+length);o.connect(g);g.connect(this.master);o.start(t);o.stop(t+length+.01);}
 noise(length=.055,volume=.025,frequency=1200,delay=0){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const c=this.ctx,buffer=c.createBuffer(1,Math.ceil(c.sampleRate*length),c.sampleRate),data=buffer.getChannelData(0);for(let i=0;i<data.length;i++)data[i]=(Math.random()*2-1)*(1-i/data.length);const source=c.createBufferSource(),filter=c.createBiquadFilter(),gain=c.createGain();this.track(source,()=>{filter.disconnect();gain.disconnect();});source.buffer=buffer;filter.type='lowpass';filter.frequency.value=frequency;gain.gain.value=volume;source.connect(filter);filter.connect(gain);gain.connect(this.master);source.start(c.currentTime+delay);}
 bark(delay=0){if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;const at=this.elapsed+delay*1000;this.barkWindows.push([at,at+410]);if(this.buffer){const source=this.ctx.createBufferSource(),g=this.ctx.createGain();this.track(source,()=>g.disconnect());source.buffer=this.buffer;g.gain.value=.85;source.connect(g);g.connect(this.master);source.start(this.ctx.currentTime+delay);}else{this.tone(130,.13,.075,'sawtooth',delay);this.noise(.16,.08,1100,delay+.015);}}
 isBarking(elapsed){this.barkWindows=this.barkWindows.filter(w=>w[1]>=elapsed-100);return this.barkWindows.some(w=>elapsed>=w[0]&&elapsed<=w[1]);}
 enter(phase){this.stopNodes();this.phase=phase;this.beat=-1;if(phase==='wake'){this.bark(.04);this.bark(.38);this.bark(.8);}if(phase==='chase'){this.bark();this.bark(.38);}if(phase==='catch')this.bark();}
 update(phase,elapsed,total){this.elapsed=elapsed;if(!this.enabled||!this.ctx||this.ctx.state!=='running')return;if(this.phase!==phase)this.enter(phase);
 const starts={fear:0,sneak:.14,wake:.36,chase:.46,hide:.76,catch:.9},tempo={fear:510,sneak:390,chase:145,hide:230};if(!tempo[phase])return;
 const local=Math.max(0,elapsed-(starts[phase]||0)*total),beat=Math.floor(local/tempo[phase]);if(beat===this.beat)return;this.beat=beat;
 if(phase==='fear'){if(beat%2===0){this.tone(70,.12,.085,'sine');this.tone(62,.1,.05,'sine',.19);}if(beat%4===0){this.tone(220,.65,.018,'triangle');this.tone(261.63,.65,.013,'sine');}}
 if(phase==='sneak'){const notes=[220,0,261.63,0,246.94,0,196,0];if(notes[beat%8])this.tone(notes[beat%8],.09,.032,'triangle');this.noise(.035,.023,650);}
 if(phase==='chase'){const melody=[329.63,392,440,392,329.63,293.66,261.63,293.66];this.tone(melody[beat%8],.16,.033,'triangle');this.noise(.045,.028,900);if(beat%2===0)this.tone(82.4,.13,.065,'sine');if(beat%12===8)this.bark();}
 if(phase==='hide'){if(beat%2===0)this.tone(73.4,.12,.055,'sine');if(beat%8===0)this.bark();}
 }
 win(){this.stop();if(!this.enabled)return;[392,493.88,587.33,783.99].forEach((n,i)=>this.tone(n,.3,.045,'triangle',i*.12));this.noise(.05,.04,700);}
 async test(){if(!await this.unlock())return false;await this.decoding;this.elapsed=0;this.stopNodes();this.bark();this.bark(.48);return !!this.buffer;}
}
root.CDSMucAudio=MucAudio;if(typeof module==='object'&&module.exports)module.exports=MucAudio;
})(typeof window!=='undefined'?window:globalThis);
