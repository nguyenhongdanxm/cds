(function(root){'use strict';
function randomIndex(n){if(!Number.isInteger(n)||n<1||n>4294967296)throw Error('Danh sách không hợp lệ.');const cap=Math.floor(4294967296/n)*n,a=new Uint32Array(1);let value;do{root.crypto.getRandomValues(a);value=a[0];}while(value>=cap);return value%n;}
function shuffle(items){const out=items.slice();for(let i=out.length-1;i>0;i--){const j=randomIndex(i+1);[out[i],out[j]]=[out[j],out[i]];}return out;}
function pool(students,selected,called,noRepeat){return students.filter(s=>selected.has(s.id)&&(!noRepeat||!called.has(s.id)));}
function plan(available,limit=8){if(!Number.isInteger(limit)||limit<1||limit>8)throw Error('Số ô đích không hợp lệ.');if(!available.length)throw Error('Không còn học sinh để chọn.');const winner=available[randomIndex(available.length)];const candidates=shuffle([winner,...shuffle(available.filter(s=>s.id!==winner.id)).slice(0,limit-1)]),slot=candidates.findIndex(s=>s.id===winner.id);let col=3;const nodes=[{x:450,y:26}];for(let i=0;i<9;i++){col=col===0?1:col===6?5:col+(randomIndex(2)?1:-1);nodes.push({x:126+col*108,y:78+i*44});}const x=60+(slot+.5)*780/candidates.length;nodes.push({x,y:456});nodes.push({x,y:520});return {winner,candidates,slot,nodes};}
// Two underground branches and a final concealed distribution pipe.
const hidden=new Set([3,4,7,9]),weights=[.55,.65,.65,.95,1.1,.65,.65,1.05,.65,1.65,.6];
function position(plan,progress){const total=weights.reduce((a,b)=>a+b,0),time=Math.max(0,Math.min(1,progress))*total;let consumed=0,index=0;for(;index<weights.length-1;index++){if(time<=consumed+weights[index])break;consumed+=weights[index];}const t=Math.max(0,Math.min(1,(time-consumed)/weights[index])),a=plan.nodes[index],b=plan.nodes[index+1],smooth=t*t*(3-2*t);return {x:a.x+(b.x-a.x)*smooth,y:a.y+(b.y-a.y)*t-Math.sin(Math.PI*t)*6,hidden:hidden.has(index),index,t};}
root.CDSBallEngine={randomIndex,shuffle,pool,plan,position,hidden};
})(typeof window==='undefined'?globalThis:window);
