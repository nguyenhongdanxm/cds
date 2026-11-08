/* Full-class choreography. Every eligible ID gets an actor; no sampled or truncated cast. */
(function(root){'use strict';
const W=1600;
function clamp(n){return Math.max(0,Math.min(1,n));}
function smooth(n){n=clamp(n);return n*n*(3-2*n);}
function lerp(a,b,t){return a+(b-a)*t;}
function hash(s){let n=2166136261;for(const c of String(s)){n^=c.charCodeAt(0);n=Math.imul(n,16777619);}return n>>>0;}
// A fixed 16:9 stage. Staggered depth rows overlap bodies, never remove a pupil.
function layout(n){const cols=Math.min(Math.max(1,n),Math.max(3,Math.ceil(Math.sqrt(n*2)))),rows=Math.max(1,Math.ceil(n/cols)),cell=Math.min(156,1100/(cols+.5)),h=Math.min(174,cell*1.08,rows>1?232/(rows-1)+76:174),gap=rows>1?Math.min(h*.53,232/(rows-1)):0;return {cols,rows,h,w:h*1.04,labelH:n>60?20:26,labelW:Math.min(154,cell*.86),font:n>60?10:n>40?12:14,gap,cell,width:W,height:900,scale:h/136};}
function formation(p){if(p<.28)return {offset:p<.22?22*smooth((p-.1)/.12):22,travel:0,flip:false,hold:false};
 if(p<.4)return {offset:lerp(22,130,smooth((p-.28)/.12)),travel:1400*smooth((p-.28)/.12),flip:false,hold:false};
 if(p<.43)return {offset:130,travel:1400,flip:true,hold:true};
 if(p<.52)return {offset:lerp(130,-130,smooth((p-.43)/.09)),travel:lerp(1400,-1400,smooth((p-.43)/.09)),flip:true,hold:false};
 if(p<.55)return {offset:-130,travel:-1400,flip:false,hold:true};
 return {offset:lerp(-130,0,smooth((p-.55)/.09)),travel:lerp(-1400,0,smooth((p-.55)/.09)),flip:false,hold:false};
}
function plan(candidates,winner,seed=0){const cast=candidates.slice().sort((a,b)=>hash(a.id+':'+seed)-hash(b.id+':'+seed)),l=layout(cast.length),covers=[];
 covers.push({x:120,y:830,w:250,h:330,cell:0},{x:1480,y:850,w:260,h:230,cell:1},{x:290,y:868,w:230,h:145,cell:2},{x:1295,y:868,w:290,h:145,cell:3});
 const decoys=cast.filter(s=>s.id!==winner?.id).sort((a,b)=>hash(a.id+':fall:'+seed)-hash(b.id+':fall:'+seed)).slice(0,2+(seed%2)).map((s,i)=>({id:s.id,start:[.355,.465,.575][i],fallenEnd:[.377,.487,.597][i],end:[.399,.509,.619][i],recovered:[.400,.510,.620][i]}));
 const speaker=cast.find(s=>s.id!==winner?.id)||winner||cast[0],events=[
 {id:'quiet',start:.015,end:.075,text:'Suỵt… nhẹ thôi!',speakerId:speaker?.id},
 {id:'silence',start:.12,end:.19,text:'Im lặng nhé!',speakerId:speaker?.id},
 {id:'run',start:.255,end:.295,text:'Chạy đi!',speakerId:speaker?.id},
 {id:'turn',start:.402,end:.427,text:'Quay lại!',speakerId:speaker?.id},
 ...decoys.map((d,i)=>({id:'safe-'+i,start:d.recovered,end:d.recovered+.035,text:'Hú hồn!',speakerId:d.id})),
 {id:'hide',start:.66,end:.72,text:'Nấp nhanh lên!',speakerId:speaker?.id},
 {id:'trip',start:.846,end:.886,text:'Á! Vấp rồi!',speakerId:winner?.id},
 {id:'caught',start:.972,end:1.1,text:'Dạ, em chưa học bài…',speakerId:winner?.id}
 ];const cloud=cast.map((s,i)=>{const angle=i*2.399963+(seed%31)*.13,r=Math.sqrt((i+.45)/Math.max(1,cast.length));return {x:800+Math.cos(angle)*r*410,y:725+Math.sin(angle)*r*120,delay:(hash(s.id+':turn:'+seed)%100)*.00022,phase:(hash(s.id+':panic:'+seed)%628)/100};});return {cast,winnerId:winner?.id||null,seed,layout:l,covers,decoys,events,cloud,climber:cast.findIndex((s,i)=>i%2===0&&s.id!==winner?.id)};}
function dialogue(scene,p){return scene.events.filter(e=>p>=e.start&&p<e.end).at(-1)||null;}
function origin(l,index){const col=index%l.cols,row=Math.floor(index/l.cols),rowCount=Math.min(l.cols,Math.max(1,l.count-row*l.cols)),shift=row%2?.5:0;return {x:800+(col-(rowCount-1)/2+shift-.25)*l.cell,y:620+row*l.gap};}
// Individual paths in an irregular moving pack; no row/column is used after the alarm.
function panic(scene,index,p,reduced=false){const a=scene.cloud[index],l={...scene.layout,count:scene.cast.length},home=origin(l,index),q=Math.max(.28,p-a.delay),g=formation(q),u=smooth((p-.28)/.045),wave=(p-.28)*62+a.phase,energy=reduced?0:1;let x=lerp(home.x+22,a.x+g.offset,u)+energy*u*(Math.sin(wave*(.8+index%3*.18))*48+Math.cos(wave*.61+index)*23),y=lerp(home.y,a.y,u)+energy*u*(Math.sin(wave*.87+index)*25+Math.cos(wave*.5+index*2)*14);const d=scene.decoys.find(d=>d.id===scene.cast[index].id);if(d){const tail=smooth((p-(d.start-.06))/.04)*(1-smooth((p-(d.end+.01))/.035));x=lerp(x,800+g.offset+(g.flip?425:-425),tail);y=lerp(y,817,tail);}return {x:Math.max(150,Math.min(1450,x)),y:Math.max(590,Math.min(855,y)),flip:g.flip,skidding:(q>.4&&q<.414)||(q>.52&&q<.534)};}
function hunter(scene,p,reduced=false){const ds=scene.decoys.length?scene.decoys:[{id:scene.cast[0]?.id}],switches=[.425,.54],leg=p<switches[0]?0:p<switches[1]?1:2,current=Math.min(ds.length-1,leg),prev=leg>=ds.length?current:Math.max(0,current-1),idx=scene.cast.findIndex(a=>a.id===ds[current]?.id),old=scene.cast.findIndex(a=>a.id===ds[prev]?.id),b=panic(scene,Math.max(0,idx),p,reduced),a=panic(scene,Math.max(0,old),p,reduced),t=current===0?1:smooth((p-switches[leg-1])/.025),turnA=smooth((p-.395)/.04),turnB=smooth((p-.515)/.04),direction=-1+2*turnA-2*turnB,flip=direction>0,gap=175+18*Math.sin(p*93),d=scene.decoys[current];let target=b;if(d&&p>=d.start&&p<d.end){target=panic(scene,Math.max(0,idx),d.start,reduced);}return {x:lerp(a.x,target.x,t)+direction*gap,y:lerp(a.y,target.y,t)+21+Math.sin(Math.PI*turnA)*50+Math.sin(Math.PI*turnB)*50,flip,targetId:ds[current]?.id};}
function frame(scene,p,phase,time=0,reduced=false){if(phase==='done')p=1;p=clamp(p);const l={...scene.layout,count:scene.cast.length},group=formation(p),actors=[];
 scene.cast.forEach((student,index)=>{const col=index%l.cols,row=Math.floor(index/l.cols),home=origin(l,index),winner=student.id===scene.winnerId,side=index%2,cover=scene.covers[side],clock=reduced?0:time+index*117,stride=Math.floor(clock/88)%2,depth=1+row*.018;
  let x=home.x,y=home.y,pose='fear',flip=false,rotation=0,squash=1,hidden=false,hiding=false,recovered=false,fallen=false,climbing=false;
  if(phase==='idle'||phase==='sneak'){x+=group.offset;pose='sneak';y-=reduced?0:Math.abs(Math.sin(clock/220))*3;}
  else if(phase==='wake'){x+=22;}
  else if(phase==='chase'){
   const run=panic(scene,index,p,reduced);x=run.x;y=run.y;flip=run.flip;pose=run.skidding?'fear':'run';
   const d=scene.decoys.find(d=>d.id===student.id);if(d&&p>=d.start&&p<d.end){const at=panic(scene,index,d.start,reduced);flip=at.flip;x=at.x;y=at.y;const falling=smooth((p-d.start)/.009),rising=smooth((p-d.fallenEnd)/(d.end-d.fallenEnd));rotation=(flip?-1:1)*1.14*falling*(1-rising);pose=p<d.fallenEnd?'fallen':'rise';fallen=p<d.fallenEnd;x=lerp(at.x,run.x,rising);y=lerp(at.y,run.y,rising)+12*falling*(1-rising);}if(d&&p>=d.recovered&&p<d.recovered+.035){recovered=true;const leap=Math.sin(Math.PI*clamp((p-d.recovered)/.035));x+=(flip?-1:1)*leap*48;y-=leap*24;}
  }else if(['hide','stumble','catch','done'].includes(phase)){
   const escape=panic(scene,index,.64,reduced);if(winner){const u=smooth((p-.64)/.18);x=lerp(escape.x,820,u);y=lerp(escape.y,790,u);pose=p<.835?'run':p<.855?'stumble':'fallen';fallen=p>=.855;rotation=p>=.835?.95*smooth((p-.835)/.025):0;}
   else{const k=Math.floor(index/2),sideCount=Math.ceil(l.count/2),cc=Math.min(4,Math.ceil(Math.sqrt(sideCount))),rr=Math.floor(k/cc),nRows=Math.ceil(sideCount/cc),step=Math.min(88,310/(cc-.5)),targetX=(side?1160:105)+((k%cc)+(rr%2)*.5)*step,targetY=650+rr*Math.min(62,190/Math.max(1,nRows-1)),delay=(hash(student.id+scene.seed)%11)*.0007,u=smooth((p-.64-delay)/(.18-delay));x=lerp(escape.x,targetX,u);y=lerp(escape.y,targetY,u)+(reduced?0:Math.sin(Math.PI*u)*Math.sin(index*2.1)*42);flip=side===0;pose='run';hidden=p>=.82;hiding=u>.91;if(hiding)pose='fear';
    climbing=index===scene.climber; if(climbing){x=lerp(escape.x,220,u);y=lerp(escape.y,390,u);hidden=p>=.82;hiding=u>.9;}
   }
  }
  if(pose==='run'&&!reduced){y-=Math.abs(Math.sin(clock/75))*7;rotation+=Math.sin(clock/75)*.035;squash=1+Math.sin(clock/75)*.025;}
  const hideScale=hidden||hiding?.78:1,w=l.w*depth*hideScale,h=l.h*depth*hideScale;
  actors.push({student,index,col,row,x,y,w,h,labelW:hidden?Math.min(l.labelW,74):l.labelW,labelH:hidden?26:l.labelH,font:hidden?Math.min(12,l.font):l.font,pose,stride,flip,rotation,squash,winner,hidden,hiding,cover,recovered,fallen,climbing});
 });
 const target=actors.find(a=>a.winner),scale=Math.max(.85,Math.min(1.15,l.scale)),front=880;let dog={x:280,y:front,w:250*scale,h:210*scale,pose:'sleep',stride:Math.floor(time/85)%2,contact:false,flip:false};
 if(phase==='wake')dog.pose='bark';
 if(phase==='chase'&&scene.cast.length){const hunt=hunter(scene,p,reduced);dog={...dog,...hunt,pose:'run',angry:true};}
 if(['hide','stumble','catch','done'].includes(phase)&&target){const scaleGap=(160-15*smooth((p-.97)/.03))*scale,t=smooth((p-.82)/.15),at=hunter(scene,.64,reduced),follow=smooth((p-.64)/.055),gap=p<.82?175:lerp(175,scaleGap,t);dog.x=lerp(at.x,target.x-gap,follow);dog.y=lerp(at.y,target.y+21*(1-t),follow);dog.pose=p<.97?'run':p<.99?'bark':'happy';dog.angry=p<.99;dog.flip=false;dog.contact=p>=.97;dog.contactDistance=scaleGap;}
 const line=dialogue(scene,p);return {actors,dog,covers:scene.covers,layout:l,dialogue:line,contact:!!target&&dog.contact,hidden:actors.filter(a=>a.hidden).length,contactDistance:dog.contactDistance||160*scale,props:debris(scene,p)};
}
// Launch once from a runner's feet, tumble, bounce and settle. Deterministic under pause/resume.
function debris(scene,p){const out=[];if(p<.29)return out;const l={...scene.layout,count:scene.cast.length},count=Math.min(15,Math.max(5,Math.ceil(l.count/3)));for(let i=0;i<count;i++){const index=hash('prop:'+i+scene.seed)%Math.max(1,l.count),a=origin(l,index),start=.29+i*.019,u=clamp((p-start)/.12);if(p<start)continue;const direction=i%2?1:-1,x=lerp(a.x+formation(start).offset,180+(hash('land:'+i+scene.seed)%1240),u),floor=820+(i%3)*22,y=lerp(a.y-12,floor,u)-Math.sin(Math.PI*u)*(90+i%4*20)-(u>.8?Math.abs(Math.sin((u-.8)*Math.PI*10))*12*(1-u)*5:0);out.push({kind:i===1?1:i%7===4?2:i%7===5?3:0,x,y,rotation:direction*u*Math.PI*(2+i%3),size:i===1?66:i%7===5?40:46,airborne:u<1});}return out;}
function intersects(a,b,gap=0){return a.x<b.x+b.w+gap&&a.x+a.w+gap>b.x&&a.y<b.y+b.h+gap&&a.y+a.h+gap>b.y;}
function body(a){return {x:a.x-a.w/2,y:a.y-a.h,w:a.w,h:a.h};}
function labels(actors,dog,height=900){return actors.map(a=>{const w=a.labelW||130,h=a.labelH||26;return {x:Math.max(4,Math.min(1596-w,a.x-w/2)),y:Math.max(310,a.y-a.h-h-3),w,h,actor:a};});}
const api={layout,plan,frame,dialogue,formation,debris,origin,panic,hunter,clamp,smooth,lerp,intersects,body,labels};root.CDSMucMotion=api;if(typeof module==='object'&&module.exports)module.exports=api;
})(typeof globalThis!=='undefined'?globalThis:this);
