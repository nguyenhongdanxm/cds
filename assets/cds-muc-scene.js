/* Original illustrated assets; animation is rendered locally at a 1600×900 logical size. */
(function(){'use strict';
const script=document.currentScript,base=new URL('muc/',script.src),images={},W=1600,H=900;
let loaded=false;
const ready=Promise.all(['school','street','sprites'].map(key=>new Promise((resolve,reject)=>{const im=new Image();im.onload=()=>{images[key]=im;resolve();};im.onerror=()=>reject(new Error('Không tải được hình '+key));im.src=new URL(key+'.webp',base).href;}))).then(()=>{loaded=true;});
function roundRect(ctx,x,y,w,h,r){ctx.beginPath();ctx.roundRect(x,y,w,h,r);}
function shadow(ctx,x,y,w){ctx.fillStyle='#27311b2b';ctx.beginPath();ctx.ellipse(x,y,w,.16*w,0,0,Math.PI*2);ctx.fill();}
function sprite(ctx,row,col,x,y,size,rotation=0,flip=false){const atlas=images.sprites,cw=atlas.width/4,ch=atlas.height/3;ctx.save();ctx.translate(x,y);ctx.rotate(rotation);if(flip)ctx.scale(-1,1);ctx.drawImage(atlas,col*cw,row*ch,cw,ch,-size/2,-size,size,size);ctx.restore();}
function label(ctx,name,x,y,w=155,accent=false){ctx.font='800 17px "Segoe UI",system-ui,sans-serif';const words=name.trim().split(/\s+/);let lines=[name];if(ctx.measureText(name).width>w-18){let split=Math.ceil(words.length/2);lines=[words.slice(0,split).join(' '),words.slice(split).join(' ')];}const height=lines.length*20+10;ctx.fillStyle=accent?'#fff0bc':'#fffdf2';ctx.strokeStyle=accent?'#e58a23':'#443b2c';ctx.lineWidth=accent?3:1.5;roundRect(ctx,x-w/2,y-height,w,height,12);ctx.fill();ctx.stroke();ctx.fillStyle='#332c23';ctx.textAlign='center';ctx.textBaseline='middle';lines.forEach((line,i)=>ctx.fillText(line,x,y-height+15+i*20,w-14));}
function bubble(ctx,text,x,y){ctx.font='900 25px "Segoe UI",system-ui,sans-serif';const w=Math.min(450,ctx.measureText(text).width+42),h=57;ctx.fillStyle='#fffef3';ctx.strokeStyle='#382f25';ctx.lineWidth=4;roundRect(ctx,x-w/2,y-h,w,h,18);ctx.fill();ctx.stroke();ctx.beginPath();ctx.moveTo(x-10,y-1);ctx.lineTo(x+2,y+17);ctx.lineTo(x+18,y-1);ctx.fill();ctx.stroke();ctx.fillStyle='#302820';ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(text,x,y-h/2,w-20);}
function dust(ctx,x,y,time){for(let i=0;i<6;i++){const t=(time/360+i/6)%1;ctx.globalAlpha=(1-t)*.6;ctx.fillStyle='#f4e6c2';ctx.beginPath();ctx.arc(x-t*100,y-5-t*25,5+t*13,0,Math.PI*2);ctx.fill();}ctx.globalAlpha=1;}
function confetti(ctx,time){const colors=['#ff6652','#ffc740','#55c6ba','#a689e7','#74bf65'];for(let i=0;i<85;i++){const x=((i*193+Math.sin(i)*73)%W+W)%W,y=((time*.12+i*97)%(H+80))-80;ctx.save();ctx.translate(x,y);ctx.rotate(time*.002+i);ctx.fillStyle=colors[i%5];ctx.fillRect(-4,-7,8,14);ctx.restore();}}
function buildCast(candidates,winner){let shown=candidates.slice(0,24);if(winner&&!shown.some(s=>s.id===winner.id)){shown[shown.length-1]=winner;}return shown;}
function render(ctx,time,state){ctx.clearRect(0,0,W,H);if(!loaded){ctx.fillStyle='#8fc8e5';ctx.fillRect(0,0,W,H);ctx.fillStyle='#fff';ctx.font='bold 30px system-ui';ctx.textAlign='center';ctx.fillText('Đang chuẩn bị sân trường…',W/2,H/2);return;}
 const phase=state.phase||'idle',reduced=state.reduced,moveTime=reduced?0:time,progress=state.progress||0,chase=phase==='chase',caught=phase==='catch'||phase==='done',catchT=phase==='done'||reduced?1:Math.max(0,Math.min(1,(progress-.89)/.11));
 if(chase&&!reduced){const scroll=((progress-.44)*1800)%W;ctx.drawImage(images.street,-scroll,0,W,H);ctx.drawImage(images.street,W-scroll,0,W,H);}else ctx.drawImage(chase?images.street:images.school,0,0,W,H);
 const candidates=state.candidates||[],cast=buildCast(candidates,state.winner),n=cast.length;
 if(phase==='intro'||!n){shadow(ctx,1170,805,110);sprite(ctx,0,0,1170,815,330,Math.sin(moveTime/1000)*.012);ctx.font='900 65px system-ui';ctx.fillStyle='#c9d4ff';ctx.strokeStyle='#fff';ctx.lineWidth=5;ctx.strokeText('Z',1270,610);ctx.fillText('Z',1270,610);return;}
 const cols=Math.min(8,Math.max(4,Math.ceil(n/3))),rows=Math.ceil(n/cols),size=n>16?147:175,spacingX=Math.min(155,1000/cols),spacingY=rows>2?87:103,startX=480;
 let winnerPosition=null;
 cast.forEach((s,i)=>{const row=Math.floor(i/cols),col=i%cols,seed=[...s.id].reduce((v,c)=>v+c.charCodeAt(0),0),gender=(s.gender||'').trim().toLocaleLowerCase('vi'),girl=['nữ','nu','female','f'].includes(gender)?true:['nam','male','m'].includes(gender)?false:seed%2===0;let x=startX+col*spacingX+(row%2)*33,y=H-75-(rows-1-row)*spacingY;const step=Math.sin(moveTime/(chase?85:180)+i*1.7);let pose=phase==='wake'?3:chase?1:0;
  if(phase==='sneak'){x+=Math.sin(progress*3)*65;y+=step*2;}
  if(chase){const p=Math.min(1,(progress-.44)/.45);x+=Math.sin(p*11+i)*35;x-=p*60;y+=Math.abs(step)*-12;}
  if(caught){if(s.id===state.winner?.id){x+=(750-x)*catchT;y+=(830-y)*catchT;pose=catchT>.7?2:1;winnerPosition={x,y,girl,pose};}else{const targetX=i%2===0?80+i*9:1500-i*7;const targetY=640+(i%3)*70;const t=phase==='done'?1:Math.min(1,(progress-.89)/.05);x+=(targetX-x)*t;y+=(targetY-y)*t;pose=3;ctx.globalAlpha=.5*(1-catchT);}}
  if(caught&&s.id===state.winner?.id){ctx.globalAlpha=1;return;}
  shadow(ctx,x,y,size*.26);sprite(ctx,girl?2:1,pose,x,y,size,chase?step*.075:step*.018);label(ctx,s.name,x,y-size+5,Math.min(152,spacingX+5));ctx.globalAlpha=1;
 });
 let dogX=280,dogY=850,dogPose=0,dogSize=280;
 if(phase==='wake')dogPose=1;
 if(chase){const p=(progress-.44)/.45;dogX=210+p*260;dogPose=2;dogY=820+Math.abs(Math.sin(moveTime/90))*-12;dust(ctx,dogX-95,dogY-15,moveTime);}
 if(caught){dogX=470+500*catchT;dogY=820+22*catchT;dogPose=catchT>.7?3:2;dogSize=280-35*catchT;}
 shadow(ctx,dogX,dogY,dogSize*.3);sprite(ctx,0,dogPose,dogX,dogY,dogSize,chase?Math.sin(moveTime/90)*.055:0);
 if(phase==='idle'||phase==='sneak'){ctx.save();ctx.font='900 51px system-ui';ctx.fillStyle='#bfcfff';ctx.strokeStyle='#fff';ctx.lineWidth=5;const zY=dogY-180+Math.sin(moveTime/700)*8;ctx.strokeText('Z',dogX+60,zY);ctx.fillText('Z',dogX+60,zY);ctx.font='900 32px system-ui';ctx.fillText('z',dogX+94,zY-38);ctx.restore();if(n)bubble(ctx,'Suỵt… nhẹ thôi!',650,470);}
 if(phase==='wake')bubble(ctx,'Grừ… ai đấy?',dogX+35,565);
 if(chase)bubble(ctx,Math.floor(progress*20)%2?'Chạy thôi!':'Mực đuổi kìa!',940,490);
 if(caught){if(winnerPosition){shadow(ctx,winnerPosition.x,winnerPosition.y,55);sprite(ctx,winnerPosition.girl?2:1,winnerPosition.pose,winnerPosition.x,winnerPosition.y,180+30*catchT,reduced?0:Math.sin(time/160)*.04);label(ctx,state.winner.name,winnerPosition.x,winnerPosition.y-210,250,true);}confetti(ctx,moveTime);bubble(ctx,'Bắt được rồi! Hê hê…',980,575);}
 if(phase==='wake'){ctx.fillStyle='#faeeb9';ctx.font='900 70px system-ui';ctx.fillText('!',dogX+125,600);}
}
window.CDSMucScene={ready,render,buildCast};
})();
