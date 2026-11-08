/* Full-class choreography. Every eligible ID gets an actor; no sampled or truncated cast. */
(function(root){'use strict';
const W=1600;
function clamp(n){return Math.max(0,Math.min(1,n));}
function smooth(n){n=clamp(n);return n*n*(3-2*n);}
function lerp(a,b,t){return a+(b-a)*t;}
function hash(s){let n=2166136261;for(const c of String(s)){n^=c.charCodeAt(0);n=Math.imul(n,16777619);}return n>>>0;}
function layout(n){const cols=n<=8?4:n<=24?6:8,rows=Math.max(1,Math.ceil(n/cols)),h=n<=8?120:n<=24?104:88,labelH=n<=24?38:44,gap=h+labelH+24,cell=1000/cols;return {cols,rows,h,w:h*1.04,labelH,labelW:cell-10,font:n<=8?16:n<=24?14:13,gap,cell,width:W,height:Math.max(900,610+(rows-1)*gap+220),scale:h/136};}
function formation(p){if(p<.28)return {offset:p<.22?22*smooth((p-.1)/.12):22,travel:0,flip:false,hold:false};
 if(p<.4)return {offset:lerp(22,130,smooth((p-.28)/.12)),travel:1400*smooth((p-.28)/.12),flip:false,hold:false};
 if(p<.43)return {offset:130,travel:1400,flip:true,hold:true};
 if(p<.52)return {offset:lerp(130,-130,smooth((p-.43)/.09)),travel:lerp(1400,-1400,smooth((p-.43)/.09)),flip:true,hold:false};
 if(p<.55)return {offset:-130,travel:-1400,flip:false,hold:true};
 return {offset:lerp(-130,0,smooth((p-.55)/.09)),travel:lerp(-1400,0,smooth((p-.55)/.09)),flip:false,hold:false};
}
function plan(candidates,winner,seed=0){const cast=candidates.slice().sort((a,b)=>hash(a.id+':'+seed)-hash(b.id+':'+seed)),l=layout(cast.length),covers=[];
 for(let row=0;row<l.rows;row++){const y=610+row*l.gap+15;covers.push({x:100,y,w:175,h:180,cell:row%2?2:0},{x:1500,y,w:175,h:180,cell:row%2?3:1});}
 const decoys=cast.filter(s=>s.id!==winner?.id).sort((a,b)=>hash(a.id+':fall:'+seed)-hash(b.id+':fall:'+seed)).slice(0,1+(seed%2)).map((s,i)=>({id:s.id,start:i?.514:.394,fallenEnd:i?.538:.418,end:i?.55:.43,recovered:i?.552:.432}));
 const speaker=cast.find(s=>s.id!==winner?.id)||winner||cast[0],events=[
 {id:'quiet',start:.015,end:.075,text:'Suỵt… nhẹ thôi!',speakerId:speaker?.id},
 {id:'silence',start:.12,end:.19,text:'Im lặng nhé!',speakerId:speaker?.id},
 {id:'run',start:.255,end:.295,text:'Chạy đi!',speakerId:speaker?.id},
 {id:'turn',start:.402,end:.427,text:'Quay lại!',speakerId:speaker?.id},
 ...decoys.map((d,i)=>({id:'safe-'+i,start:d.recovered,end:d.recovered+.035,text:'Hú hồn!',speakerId:d.id})),
 {id:'hide',start:.66,end:.72,text:'Nấp nhanh lên!',speakerId:speaker?.id},
 {id:'trip',start:.846,end:.886,text:'Á! Vấp rồi!',speakerId:winner?.id},
 {id:'caught',start:.972,end:1.1,text:'Dạ, em chưa học bài…',speakerId:winner?.id}
 ];return {cast,winnerId:winner?.id||null,seed,layout:l,covers,decoys,events};}
function dialogue(scene,p){return scene.events.filter(e=>p>=e.start&&p<e.end).at(-1)||null;}
function frame(scene,p,phase,time=0,reduced=false){if(phase==='done')p=1;p=clamp(p);const l=scene.layout,group=formation(p),actors=[],winnerIndex=scene.cast.findIndex(s=>s.id===scene.winnerId),winnerCol=winnerIndex%l.cols,winnerRow=Math.floor(winnerIndex/l.cols);
 scene.cast.forEach((student,index)=>{const col=index%l.cols,row=Math.floor(index/l.cols),origin={x:310+(col+.5)*l.cell,y:610+row*l.gap},winner=student.id===scene.winnerId,side=col<l.cols/2?0:1,cover=scene.covers[row*2+side],clock=reduced?0:time+index*117,stride=Math.floor(clock/105)%2;
  let x=origin.x,y=origin.y,pose='fear',flip=false,rotation=0,squash=1,hidden=false,hiding=false,recovered=false,fallen=false;
  if(phase==='sneak'){x+=group.offset;pose='sneak';y-=reduced?0:Math.abs(Math.sin(clock/220))*3;}
  else if(phase==='wake'){x+=22;}
  else if(phase==='chase'){
   x+=group.offset;flip=group.flip;pose=group.hold?'fear':'run';
   const d=scene.decoys.find(d=>d.id===student.id);if(d&&p>=d.start&&p<d.end){const fallFlip=formation(d.start).flip;flip=fallFlip;x=origin.x+formation(d.start).offset;const falling=smooth((p-d.start)/.008),rising=smooth((p-d.fallenEnd)/(d.end-d.fallenEnd));rotation=(fallFlip?-1:1)*.95*falling*(1-rising);pose=p<d.fallenEnd?'fallen':'rise';fallen=p<d.fallenEnd;y+=8*falling*(1-rising);}if(d&&p>=d.recovered&&p<d.recovered+.045)recovered=true;
  }else if(['hide','stumble','catch','done'].includes(phase)){
   if(winner){x+=65*smooth((p-.64)/.18);y+=10*smooth((p-.82)/.035);pose=p<.835?'run':p<.855?'stumble':'fallen';fallen=p>=.855;rotation=p>=.835?.95*smooth((p-.835)/.025):0;}
   else{const u=smooth((p-.64)/.18),maxTravel=side?1500-(310+(l.cols/2+.5)*l.cell):(310+(l.cols/2-.5)*l.cell)-100,travel=Math.max(0,maxTravel)*u,distance=Math.abs(origin.x-cover.x),t=clamp(travel/Math.max(1,distance));x=lerp(origin.x,cover.x,t);y=lerp(origin.y,cover.y-5,t);flip=side===0;pose='run';hidden=t>=1;hiding=t>.88;if(hiding)pose='fear';
    // Pass the exposed pupil in another depth lane, then duck behind cover.
    const crossesWinner=row===winnerRow&&side===(winnerCol<l.cols/2?0:1)&&((side===0&&col>winnerCol)||(side===1&&col<winnerCol));if(crossesWinner&&!hiding)y-=Math.sin(Math.PI*t)*l.h*.7;
   }
  }
  if(pose==='run'&&!reduced){y-=Math.abs(Math.sin(clock/85))*4;rotation+=Math.sin(clock/85)*.025;squash=1+Math.sin(clock/85)*.02;}
  actors.push({student,index,col,row,x,y,w:l.w,h:l.h,labelW:l.labelW,labelH:l.labelH,font:l.font,pose,stride,flip,rotation,squash,winner,hidden,hiding,cover,recovered,fallen});
 });
 const target=actors.find(a=>a.winner),scale=l.scale,front=l.height-25;let dog={x:180,y:front,w:230*scale,h:205*scale,pose:'sleep',stride:Math.floor(time/100)%2,contact:false,flip:false};
 if(phase==='wake')dog.pose='bark';
 if(phase==='chase'){
  dog.pose='run';if(p<.4){dog.x=lerp(180,420,smooth((p-.28)/.12));}
  else if(p<.44){dog.x=lerp(420,1450,smooth((p-.4)/.04));}
  else if(p<.52){dog.x=lerp(1450,1250,smooth((p-.44)/.08));dog.flip=true;}
  else if(p<.56){dog.x=lerp(1250,170,smooth((p-.52)/.04));dog.flip=true;}
  else dog.x=lerp(170,430,smooth((p-.56)/.08));
 }
 if(['hide','stumble','catch','done'].includes(phase)&&target){const distance=(160-15*smooth((p-.97)/.03))*scale,t=smooth((p-.82)/.15);dog.x=lerp(430,target.x-distance,clamp(t*1.7));dog.y=lerp(front,target.y,t*t);dog.pose=p<.82?'bark':p<.97?'run':p<.99?'bark':'happy';dog.flip=p<.94&&target.x-distance<430;dog.contact=p>=.97;dog.contactDistance=distance;}
 const line=dialogue(scene,p);return {actors,dog,covers:scene.covers,layout:l,dialogue:line,contact:!!target&&dog.contact,hidden:actors.filter(a=>a.hidden).length,contactDistance:dog.contactDistance||160*scale};
}
function intersects(a,b,gap=0){return a.x<b.x+b.w+gap&&a.x+a.w+gap>b.x&&a.y<b.y+b.h+gap&&a.y+a.h+gap>b.y;}
function body(a){return {x:a.x-a.w/2,y:a.y-a.h,w:a.w,h:a.h};}
function labels(actors,dog,height=900){const visible=actors.filter(a=>!a.hidden&&!a.hiding),bodies=visible.map(body),placed=[];if(dog)bodies.push(body(dog));for(const a of visible){const w=a.labelW||190,h=a.labelH||36,boxes=[];for(const dy of [0,-h-7,h+7])for(const dx of [0,-45,45])boxes.push({x:a.x-w/2+dx,y:a.y-a.h-h-8+dy,w,h,actor:a});const box=boxes.find(b=>b.x>=5&&b.x+b.w<=1595&&b.y>=370&&b.y+b.h<height-10&&!placed.some(c=>intersects(b,c,3))&&!bodies.some(c=>intersects(b,c,2)));if(box)placed.push(box);}return placed;}
const api={layout,plan,frame,dialogue,formation,clamp,smooth,lerp,intersects,body,labels};root.CDSMucMotion=api;if(typeof module==='object'&&module.exports)module.exports=api;
})(typeof globalThis!=='undefined'?globalThis:this);
