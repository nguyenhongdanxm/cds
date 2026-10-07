(function(root){'use strict';
function randomIndex(n){if(!Number.isInteger(n)||n<1||n>4294967296)throw Error('Danh sách không hợp lệ.');const cap=Math.floor(4294967296/n)*n,a=new Uint32Array(1);let value;do{root.crypto.getRandomValues(a);value=a[0];}while(value>=cap);return value%n;}
function shuffle(items){const out=items.slice();for(let i=out.length-1;i>0;i--){const j=randomIndex(i+1);[out[i],out[j]]=[out[j],out[i]];}return out;}
function pool(students,selected,called,noRepeat){return students.filter(s=>selected.has(s.id)&&(!noRepeat||!called.has(s.id)));}
const ballRadius=18,pegRadius=7,pegs=[];
for(let row=0;row<8;row++)for(let x=90+(row%2?36:0);x<=810;x+=72)pegs.push({x,y:96+row*47,row,id:pegs.length});
// Simulate gravity, circular peg contacts and solid compartment walls. The
// student is chosen independently, then assigned to the physical landing bin:
// central bins therefore never give any student an advantage.
function simulate(count){const dt=1/120,width=780/count;let x=425+randomIndex(51),y=34,vx=randomIndex(121)-60,vy=0,index=0,lastHit=-1,bin=-1;const frames=[{x,y,index,lastHit}];for(let step=0;step<2400;step++){vy+=245*dt;x+=vx*dt;y+=vy*dt;vx*=.999;
if(x<60+ballRadius){x=60+ballRadius;vx=Math.abs(vx)*.7;}if(x>840-ballRadius){x=840-ballRadius;vx=-Math.abs(vx)*.7;}
if(y<30){y=30;vy=Math.abs(vy)*.5;}
if(y<460)for(const peg of pegs){if(Math.abs(peg.y-y)>ballRadius+pegRadius||Math.abs(peg.x-x)>ballRadius+pegRadius)continue;const dx=x-peg.x,dy=y-peg.y,d=Math.hypot(dx,dy),contact=ballRadius+pegRadius;if(d>=contact)continue;const nx=d>1e-7?dx/d:0,ny=d>1e-7?dy/d:-1;x=peg.x+nx*(contact+.02);y=peg.y+ny*(contact+.02);const incoming=vx*nx+vy*ny;if(incoming<0){vx-=1.76*incoming*nx;vy-=1.76*incoming*ny;vx+=(randomIndex(21)-10)*.9;if(Math.abs(vx)<8)vx+=(randomIndex(2)?1:-1)*13;index++;lastHit=peg.id;}}
// Rounded divider tips are real obstacles, so a near-edge ball bounces
// into a compartment rather than jumping sideways when it enters a bin.
if(y>=459)for(let i=0;i<=count;i++){const wall=60+i*width,dx=x-wall,dy=Math.min(0,y-476),d=Math.hypot(dx,dy);if(d>=ballRadius+3)continue;const nx=d>1e-7?dx/d:(vx>=0?-1:1),ny=d>1e-7?dy/d:0;x=wall+nx*(ballRadius+3+.02);if(y<476)y=476+ny*(ballRadius+3+.02);const incoming=vx*nx+vy*ny;if(incoming<0){vx-=1.5*incoming*nx;vy-=1.5*incoming*ny;}}
if(y>=493&&bin<0)bin=Math.max(0,Math.min(count-1,Math.floor((x-60)/width)));
if(y>=520){y=520;frames.push({x,y,index,lastHit});return index>=5?{frames,slot:bin}:null;}frames.push({x,y,index,lastHit});}
throw Error('Chưa tạo được đường rơi. Hãy thử lại.');}
function motionFor(count){for(let attempt=0;attempt<20;attempt++){const motion=simulate(count);if(motion)return motion;}throw Error('Chưa tạo được lượt rơi. Hãy thử lại.');}
function plan(available,limit=6){if(!Number.isInteger(limit)||limit<1||limit>8)throw Error('Số ô đích không hợp lệ.');if(!available.length)throw Error('Không còn học sinh để chọn.');const winner=available[randomIndex(available.length)],candidates=shuffle([winner,...shuffle(available.filter(s=>s.id!==winner.id)).slice(0,limit-1)]),binCount=candidates.length,motion=motionFor(binCount),winnerIndex=candidates.findIndex(s=>s.id===winner.id);[candidates[winnerIndex],candidates[motion.slot]]=[candidates[motion.slot],candidates[winnerIndex]];return {winner,candidates,slot:motion.slot,binCount,winnerCell:motion.slot,frames:motion.frames};}
function position(plan,progress){const f=Math.max(0,Math.min(1,progress))*(plan.frames.length-1),i=Math.floor(f),a=plan.frames[i],b=plan.frames[Math.min(i+1,plan.frames.length-1)],t=f-i;return {x:a.x+(b.x-a.x)*t,y:a.y+(b.y-a.y)*t,index:a.index,lastHit:a.lastHit,hidden:false};}
function namesFrame(available,count){if(count<1||count>available.length)throw Error('Số tên không hợp lệ.');return shuffle(available).slice(0,count);}
root.CDSBallEngine={randomIndex,shuffle,pool,plan,position,namesFrame,pegs,ballRadius,pegRadius};
})(typeof window==='undefined'?globalThis:window);
