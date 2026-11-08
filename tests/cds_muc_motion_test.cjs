'use strict';
const assert=require('node:assert/strict'),M=require('../assets/cds-muc-motion.js'),E=require('../assets/cds-muc-engine.js');
for(let n=1;n<=8;n++){
 const rows=Array.from({length:n},(_,i)=>({id:String(i),name:'Nguyễn Học Sinh '+i}));
 for(const winner of rows){const scene=M.plan(rows,winner,10);let previous=null;
  for(let i=0;i<=1000;i++){
   const p=i/1000,f=M.frame(scene,p,E.phase(p),p*16000);
   for(const a of f.actors){assert.ok(Number.isFinite(a.x)&&Number.isFinite(a.y));assert.ok(a.x>=40&&a.x<=1550&&a.y>=400&&a.y<=885,'Routes stay inside the schoolyard');
    if(previous&&!a.hidden&&!previous[a.index].hidden)assert.ok(Math.hypot(a.x-previous[a.index].x,a.y-previous[a.index].y)<38,'Continuous motion, no teleport');}
   const visible=f.actors.filter(a=>!a.hidden&&!a.hiding);
   for(let a=0;a<visible.length;a++)for(let b=a+1;b<visible.length;b++)assert.ok(!M.intersects(M.body(visible[a]),M.body(visible[b])),'Visible pupils do not overlap');
   const labels=M.labels(f.actors,f.dog);for(let a=0;a<labels.length;a++){for(let b=a+1;b<labels.length;b++)assert.ok(!M.intersects(labels[a],labels[b]),'Name labels do not overlap');assert.ok(!M.intersects(labels[a],{x:430,y:295,w:740,h:80}),'Names avoid speech bubble');assert.ok(!M.intersects(labels[a],M.body(f.dog)),'Names do not cover the dog');for(const body of visible.map(M.body))assert.ok(!M.intersects(labels[a],body),'Labels do not cover faces or bodies');}
   const target=f.actors.find(a=>a.winner);if(p<.95){assert.equal(f.contact,false);assert.ok(Math.hypot(f.dog.x-target.x,f.dog.y-target.y)>=M.CONTACT-1,'No early contact');}else{assert.equal(f.contact,true);assert.ok(Math.abs(target.x-f.dog.x-f.contactDistance)<.001&&Math.abs(target.y-f.dog.y)<.001,'Catch is actual muzzle/body contact');}
   if(p>=.9){assert.equal(f.hidden,n-1,'All escaping pupils reach cover');assert.equal(target.hidden,false,'Only the slow pupil remains exposed');}
   previous=f.actors;
  }
 }
}
const many=Array.from({length:45},(_,i)=>({id:'id'+i,name:'Học sinh '+i}));for(const winner of many){const s=M.plan(many,winner,31);assert.equal(s.cast.length,8);assert.ok(s.cast.some(a=>a.id===winner.id));assert.equal(new Set(s.cast.map(a=>a.id)).size,8);}
const idle=M.frame(M.plan(many,null),0,'idle',0);assert.ok(idle.actors.every(a=>a.pose==='fear'),'Opening pupils are frightened');assert.equal(M.labels(idle.actors).length,idle.actors.length,'Every opening name is visible');
console.log('PASS Mực motion: scared opening, all cast sizes/winners, continuous routes, pupil/label separation, cover arrivals and exact capture contact');
