/* Optional Vietnamese device speech. Only fixed dialogue is spoken, never roster names. */
(function(root){'use strict';
class MucVoice{
 constructor(options={}){this.enabled=true;this.synth=root.speechSynthesis;this.Utterance=root.SpeechSynthesisUtterance;this.onStatus=options.onStatus||(()=>{});this.onSpeaking=options.onSpeaking||(()=>{});this.seen=new Set();this.epoch=0;this.voice=null;this.refresh=()=>{const voices=this.synth?.getVoices?.()||[];this.voice=voices.filter(v=>/^vi(?:-|_)/i.test(v.lang)||v.lang==='vi').sort((a,b)=>Number(b.localService)-Number(a.localService))[0]||null;this.onStatus(this.voice?'Giọng Việt: '+this.voice.name:'Thiết bị chưa có giọng đọc tiếng Việt; câu thoại vẫn hiện bằng chữ.');};this.synth?.addEventListener?.('voiceschanged',this.refresh);this.refresh();}
 cancel(){this.epoch++;try{this.synth?.cancel();}catch(e){}this.onSpeaking(false);}
 reset(){this.cancel();this.seen.clear();this.refresh();}
 setEnabled(value){this.enabled=value;if(!value)this.cancel();else this.refresh();}
 speak(text){if(!this.enabled||!this.voice||!this.Utterance||!this.synth)return false;this.cancel();const epoch=++this.epoch;try{const u=new this.Utterance(text.replace(/…/g,', '));u.lang='vi-VN';u.voice=this.voice;u.rate=1.08;u.pitch=1.08;u.volume=1;u.onstart=()=>{if(epoch===this.epoch)this.onSpeaking(true);};const end=()=>{if(epoch===this.epoch)this.onSpeaking(false);};u.onend=end;u.onerror=()=>{end();if(epoch===this.epoch)this.onStatus('Giọng đọc chưa phát được. Câu thoại vẫn hiện trên sân chơi.');};this.current=u;this.synth.speak(u);return true;}catch(e){this.onSpeaking(false);return false;}}
 update(line){if(!line||this.seen.has(line.id))return;this.seen.add(line.id);this.speak(line.text);}
 test(){this.refresh();return this.speak('Suỵt, nhẹ thôi! Im lặng nhé! Chạy đi!');}
}
root.CDSMucVoice=MucVoice;if(typeof module==='object'&&module.exports)module.exports=MucVoice;
})(typeof window!=='undefined'?window:globalThis);
