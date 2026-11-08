/* Shared scene geometry: characters follow continuous routes to opaque hiding places. */
(function(root){'use strict';
const MAX_CAST=8,CHILD_W=142,CHILD_H=136,DOG_W=230,CONTACT=160;
const covers=[
 {x:160,y:660,w:225,h:255,cell:0}, {x:650,y:585,w:155,h:170,cell:1},
 {x:970,y:585,w:155,h:170,cell:1}, {x:1460,y:675,w:175,h:235,cell:5},
 {x:400,y:585,w:180,h:175,cell:2}, {x:650,y:585,w:155,h:170,cell:1},
 {x:1245,y:585,w:185,h:240,cell:4}, {x:1480,y:875,w:210,h:180,cell:3}
];
function clamp(n){return Math.max(0,Math.min(1,n));}function smooth(n){n=clamp(n);return n*n*(3-2*n);}function lerp(a,b,t){return a+(b-a)*t;}
function hash(s){let n=2166136261;for(const c of String(s)){n^=c.charCodeAt(0);n=Math.imul(n,16777619);}return n>>>0;}
function plan(candidates,winner,seed=0){const all=candidates.slice().sort((a,b)=>hash(a.id+':'+seed)-hash(b.id+':'+seed)),cast=all.slice(0,MAX_CAST);if(winner&&!cast.some(s=>s.id===winner.id))cast[cast.length-1]=winner;cast.sort((a,b)=>hash(a.id+':order:'+seed)-hash(b.id+':order:'+seed));const winnerIndex=cast.findIndex(s=>s.id===winner?.id),obstacles=[{x:47,y:645,w:396,h:360}];if(winnerIndex>=0){const x=460+(winnerIndex%4)*240,y=winnerIndex<4?680:867;obstacles.push({x:x-155,y:y-155,w:380,h:310});}
 const paths=cast.map((s,i)=>{const start={x:482+i%4*240,y:i<4?680:867},cover=covers[i],end={x:cover.x,y:cover.y-5};if(s.id===winner?.id)return [start,end];const approach={x:end.x,y:Math.min(end.y,500)};const path=route(start,approach,obstacles);path.push(end);return path;});const starts=[.46,.46,.46,.46,.65,.715,.76,.65];if(winnerIndex===0){starts[4]=.715;starts[5]=.65;}return {cast,winnerId:winner?.id||null,seed,paths,starts};}
function crosses(a,b,r){
 let lo=0,hi=1;for(const axis of ['x','y']){const d=b[axis]-a[axis],min=r[axis],max=min+(axis==='x'?r.w:r.h);if(Math.abs(d)<1e-8){if(a[axis]<=min||a[axis]>=max)return false;}else{let t0=(min-a[axis])/d,t1=(max-a[axis])/d;if(t0>t1)[t0,t1]=[t1,t0];lo=Math.max(lo,t0);hi=Math.min(hi,t1);if(lo>=hi)return false;}}return hi>0&&lo<1;
}
function route(start,end,obstacles){
 const nodes=[start,end];for(const r of obstacles)for(const x of [r.x-6,r.x+r.w+6])for(const y of [r.y-6,r.y+r.h+6])if(x>=55&&x<=1545&&y>=400&&y<=885)nodes.push({x,y});
 const dist=nodes.map(()=>Infinity),prev=nodes.map(()=>-1),done=new Set();dist[0]=0;
 for(let n=0;n<nodes.length;n++){let u=-1;for(let i=0;i<nodes.length;i++)if(!done.has(i)&&(u<0||dist[i]<dist[u]))u=i;if(u<0||!Number.isFinite(dist[u]))break;if(u===1)break;done.add(u);for(let v=0;v<nodes.length;v++){if(done.has(v)||v===u||obstacles.some(r=>crosses(nodes[u],nodes[v],r)))continue;const d=dist[u]+Math.hypot(nodes[v].x-nodes[u].x,nodes[v].y-nodes[u].y);if(d<dist[v]){dist[v]=d;prev[v]=u;}}}
 if(prev[1]<0)return [start,end];const path=[];for(let n=1;n>=0;n=prev[n]){path.unshift(nodes[n]);if(n===0)break;}return path;
}
function along(path,t){let lengths=[],length=0;for(let i=1;i<path.length;i++){lengths.push(Math.hypot(path[i].x-path[i-1].x,path[i].y-path[i-1].y));length+=lengths.at(-1);}let d=clamp(t)*length;for(let i=0;i<lengths.length;i++){if(d<=lengths[i]||i===lengths.length-1){const f=lengths[i]?d/lengths[i]:0;return {x:lerp(path[i].x,path[i+1].x,f),y:lerp(path[i].y,path[i+1].y,f)};}d-=lengths[i];}return path[0];}
function actor(student,index,p,phase,time,winnerId,reduced,paths,starts){const col=index%4,row=Math.floor(index/4),origin={x:460+col*240,y:row?867:680},cover=covers[index],winner=student.id===winnerId;
 let x=origin.x,y=origin.y,pose='fear',hidden=false,hiding=false,flip=false,rotation=0,squash=1;
 const clock=reduced?0:time+index*117,step=Math.sin(clock/85),stride=Math.floor(clock/105)%2;
 if(phase==='sneak'){const t=smooth((p-.14)/.22);x+=t*22;pose='sneak';rotation=reduced?0:Math.sin(clock/220)*.025;y-=reduced?0:Math.abs(Math.sin(clock/220))*3;}
 else if(phase==='wake'||phase==='idle'||phase==='fear'){x+=phase==='wake'?22:0;pose='fear';x+=reduced?0:Math.sin(clock/40)*(phase==='wake'?2.5:1);}
 else if(['chase','hide','catch','done'].includes(phase)){
  if(winner){const t=smooth((p-.46)/.29);x=origin.x+22+t*48;y=origin.y;
   const home=smooth((p-.88)/.065);x=lerp(x,1000,home);y=lerp(y,820,home);pose=p<.79?'run':p<.95?'stumble':'fear';
   if(p<.79&&!reduced){y-=Math.abs(step)*7;rotation=step*.025;squash=1+Math.sin(clock/85)*.025;}
   if(p>=.79&&p<.89&&!reduced){rotation=Math.sin((p-.79)*45)*.13;y-=Math.max(0,Math.sin((p-.79)*40))*6;}
  }else{
   // Back row leaves first. Front row then passes through the vacated space.
   const start=starts[index],end=start+(row?.08:.18),t=smooth((p-start)/(end-start));
   const point=along(paths[index],t);x=point.x;y=point.y;pose=p<start?'fear':'run';flip=cover.x<origin.x;
   if(t>0&&t<1&&!reduced){y-=Math.abs(step)*7;rotation=step*.025;squash=1+Math.sin(clock/85)*.02;}
   hiding=t>.78;hidden=t>=1; if(hiding)pose='fear';
  }
 }
 return {student,index,x,y,w:CHILD_W,h:CHILD_H,pose,stride,flip,rotation,squash,winner,hidden,hiding,cover};
}
function frame(scene,p,phase,time=0,reduced=false){p=clamp(p);if(phase==='done')p=1;
 const actors=scene.cast.map((s,i)=>actor(s,i,p,phase,time,scene.winnerId,reduced,scene.paths,scene.starts));const target=actors.find(a=>a.winner);
 let dog={x:245,y:850,w:DOG_W,h:205,pose:'sleep',stride:Math.floor(time/100)%2,contact:false};
 if(phase==='wake')dog.pose=p<.4?'alert':'bark';
 if(['chase','hide','catch','done'].includes(phase)&&target){const t=smooth((p-.66)/.29);const distance=CONTACT-15*smooth((p-.95)/.05);dog.x=lerp(245,target.x-distance,t);dog.y=lerp(850,target.y,t);dog.pose=p<.66?'bark':p<.95?'run':p<.975?'bark':'happy';dog.contact=p>=.95;dog.contactDistance=distance;}
 return {actors,dog,covers,contact:!!target&&dog.contact,hidden:actors.filter(a=>a.hidden).length,contactDistance:dog.contactDistance||CONTACT};
}
function intersects(a,b,gap=0){return a.x<b.x+b.w+gap&&a.x+a.w+gap>b.x&&a.y<b.y+b.h+gap&&a.y+a.h+gap>b.y;}
function body(a){return {x:a.x-a.w/2,y:a.y-a.h,w:a.w,h:a.h};}
function labels(actors,dog){const visible=actors.filter(a=>!a.hidden&&!a.hiding),bodies=visible.map(body),placed=[];if(dog)bodies.push(body(dog));
 for(const a of visible.slice().sort((a,b)=>Number(b.winner)-Number(a.winner)||a.index-b.index)){
  const w=190,h=36,candidates=[];for(const dy of [0,-48,48])for(const dx of [0,-70,70,-140,140])candidates.push({x:a.x-w/2+dx,y:a.y-a.h-h-8+dy,w,h,actor:a});
  const chosen=candidates.find(box=>box.x>=12&&box.x+box.w<=1588&&box.y>=270&&box.y+box.h<=885&&!intersects(box,{x:430,y:295,w:740,h:80},5)&&!placed.some(b=>intersects(box,b,6))&&!bodies.some(b=>intersects(box,b,3)));
  if(chosen)placed.push(chosen);
 }
 return placed;
}
const api={MAX_CAST,CONTACT,covers,clamp,smooth,lerp,plan,frame,labels,body,intersects};root.CDSMucMotion=api;if(typeof module==='object'&&module.exports)module.exports=api;
})(typeof globalThis!=='undefined'?globalThis:this);
