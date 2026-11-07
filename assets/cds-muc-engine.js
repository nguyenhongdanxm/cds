/* Uniform selection and class-local round state. No server writes. */
(function(root){'use strict';
function randomIndex(length,cryptoSource){
 if(!Number.isSafeInteger(length)||length<1||length>0x100000000)throw new Error('Danh sách không hợp lệ.');
 const crypto=cryptoSource||root.crypto;
 if(!crypto||!crypto.getRandomValues)throw new Error('Trình duyệt chưa hỗ trợ chọn ngẫu nhiên an toàn.');
 const values=new Uint32Array(1),limit=Math.floor(0x100000000/length)*length;
 do{crypto.getRandomValues(values);}while(values[0]>=limit);
 return values[0]%length;
}
function normalize(rows){const seen=new Set();return (Array.isArray(rows)?rows:[]).filter(s=>{if(!s||typeof s.id!=='string'||typeof s.name!=='string'||!s.id||!s.name.trim()||seen.has(s.id))return false;seen.add(s.id);return true;}).map(s=>({id:s.id,name:s.name,gender:typeof s.gender==='string'?s.gender:''}));}
function pool(students,selected,called,noRepeat){return students.filter(s=>selected.has(s.id)&&(!noRepeat||!called.has(s.id)));}
function restore(students,data){const ids=new Set(students.map(s=>s.id));data=data&&typeof data==='object'?data:{};return {selected:new Set(Array.isArray(data.selected)?data.selected.filter(id=>ids.has(id)):ids),called:new Set(Array.isArray(data.called)?data.called.filter(id=>ids.has(id)):[]),history:(Array.isArray(data.history)?data.history:[]).filter(h=>h&&ids.has(h.id)&&Number.isFinite(h.time)).slice(-200),noRepeat:data.noRepeat!==false};}
function phase(progress){return progress<.34?'sneak':progress<.44?'wake':progress<.89?'chase':'catch';}
class Game{
 constructor(rows,state){this.students=normalize(rows);Object.assign(this,restore(this.students,state));this.round=null;}
 available(){return pool(this.students,this.selected,this.called,this.noRepeat);}
 begin(crypto){if(this.round)return null;const candidates=this.available();if(!candidates.length)return null;this.round={winner:candidates[randomIndex(candidates.length,crypto)],candidates};return this.round;}
 cancel(){this.round=null;}
 finish(){if(!this.round)return null;const winner=this.round.winner;this.called.add(winner.id);this.history.push({id:winner.id,time:Date.now()});this.history=this.history.slice(-200);this.round=null;return winner;}
 snapshot(){return {selected:[...this.selected],called:[...this.called],history:this.history,noRepeat:this.noRepeat};}
}
const api={randomIndex,normalize,pool,restore,phase,Game};root.CDSMucEngine=api;if(typeof module==='object'&&module.exports)module.exports=api;
})(typeof globalThis!=='undefined'?globalThis:this);
