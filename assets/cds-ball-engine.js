(function(root){'use strict';
function randomIndex(n){if(!Number.isInteger(n)||n<1||n>4294967296)throw Error('Danh sách không hợp lệ.');const cap=Math.floor(4294967296/n)*n,a=new Uint32Array(1);let value;do{root.crypto.getRandomValues(a);value=a[0];}while(value>=cap);return value%n;}
function shuffle(items){const out=items.slice();for(let i=out.length-1;i>0;i--){const j=randomIndex(i+1);[out[i],out[j]]=[out[j],out[i]];}return out;}
function pool(students,selected,called,noRepeat){return students.filter(s=>selected.has(s.id)&&(!noRepeat||!called.has(s.id)));}
function junction(row,col){return {x:126+col*108+Math.sin(row*1.71+col*.93)*18,y:78+row*44+Math.cos(row*.91+col*1.21)*7};}
function plan(available,limit=8){if(!Number.isInteger(limit)||limit<1||limit>8)throw Error('Số ô đích không hợp lệ.');if(!available.length)throw Error('Không còn học sinh để chọn.');const winner=available[randomIndex(available.length)];const candidates=shuffle([winner,...shuffle(available.filter(s=>s.id!==winner.id)).slice(0,limit-1)]),slot=candidates.findIndex(s=>s.id===winner.id);let col=3;const nodes=[{x:450,y:26}];for(let i=0;i<9;i++){col=col===0?1:col===6?5:col+(randomIndex(2)?1:-1);nodes.push(junction(i,col));}const x=60+(slot+.5)*780/candidates.length;nodes.push({x,y:456});nodes.push({x,y:520});return {winner,candidates,slot,nodes};}
// Two underground branches and a final concealed distribution pipe.
const hidden=new Set([3,4,7,9]),weights=[.55,.65,.65,.95,1.1,.65,.65,1.05,.65,1.65,.6];
// Every rail uses the same cubic geometry for drawing and ball movement.
function curve(a,b,index){if(index>=9)return [a,{x:a.x,y:a.y+(b.y-a.y)/3},{x:b.x,y:a.y+2*(b.y-a.y)/3},b];const direction=b.x>=a.x?1:-1,style=(index+Math.round(a.x/108))%3;if(style===0)return [a,{x:a.x+direction*170,y:a.y-32},{x:b.x-direction*165,y:b.y+38},b];if(style===1)return [a,{x:a.x-direction*45,y:a.y+80},{x:b.x+direction*45,y:b.y-75},b];return [a,{x:a.x+direction*178,y:a.y+80},{x:b.x-direction*174,y:b.y-80},b];}
function point(points,t){const [a,b,c,d]=points,u=1-t;return {x:u*u*u*a.x+3*u*u*t*b.x+3*u*t*t*c.x+t*t*t*d.x,y:u*u*u*a.y+3*u*u*t*b.y+3*u*t*t*c.y+t*t*t*d.y};}
function position(plan,progress){if(progress>=1)return {...plan.nodes[11],hidden:false,index:10,t:1};const total=weights.reduce((a,b)=>a+b,0),time=Math.max(0,Math.min(1,progress))*total;let consumed=0,index=0;for(;index<weights.length-1;index++){if(time<=consumed+weights[index])break;consumed+=weights[index];}const t=Math.max(0,Math.min(1,(time-consumed)/weights[index])),p=point(curve(plan.nodes[index],plan.nodes[index+1],index),t);return {...p,hidden:hidden.has(index),index,t};}
function namesFrame(available,count){if(count<1||count>available.length)throw Error('Số tên không hợp lệ.');return shuffle(available).slice(0,count);}
root.CDSBallEngine={randomIndex,shuffle,pool,plan,position,hidden,curve,point,namesFrame,junction};
})(typeof window==='undefined'?globalThis:window);
