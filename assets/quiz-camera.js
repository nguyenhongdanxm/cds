(function(root){'use strict';
function mainScore(label){const s=String(label).toLowerCase();if(/front|user|facetime|trước/.test(s))return -100;if(/ultra|wide|tele|macro|depth|0[.,]5|siêu rộng/.test(s))return -20;return /back|rear|environment|sau/.test(s)?(/main|primary|chính|camera 0|back camera$/.test(s)?20:10):0;}
async function tune(track){const caps=track.getCapabilities?.()||{};for(const key of ['focusMode','exposureMode','whiteBalanceMode'])if(caps[key]?.includes('continuous')){try{await track.applyConstraints({advanced:[{[key]:'continuous'}]})}catch(e){}}return caps;}
async function open(deviceId=''){
 const md=root.navigator.mediaDevices;let stream;const video={...(deviceId?{deviceId:{exact:deviceId}}:{facingMode:{ideal:'environment'}}),width:{ideal:2560},height:{ideal:1440},frameRate:{ideal:30,max:60}};
 try{stream=await md.getUserMedia({video,audio:false})}catch(error){if(!['OverconstrainedError','NotFoundError'].includes(error.name))throw error;stream=await md.getUserMedia({video:deviceId?{deviceId:{exact:deviceId}}:{facingMode:{ideal:'environment'}},audio:false});}
 let devices=[];try{devices=(await md.enumerateDevices()).filter(d=>d.kind==='videoinput');}catch(e){}
 const track=stream.getVideoTracks()[0],currentId=track.getSettings?.().deviceId,current=devices.find(d=>d.deviceId===currentId),best=devices.slice().sort((a,b)=>mainScore(b.label)-mainScore(a.label))[0];
 // Release the lens before switching: phones may allow only one capture stream.
 if(!deviceId&&best&&mainScore(best.label)>0&&mainScore(best.label)>mainScore(current?.label)&&best.deviceId!==currentId){stream.getTracks().forEach(t=>t.stop());try{stream=await md.getUserMedia({video:{...video,deviceId:{exact:best.deviceId}},audio:false});}catch(e){if(e.name==='NotAllowedError')throw e;stream=await md.getUserMedia({video:currentId?{...video,deviceId:{exact:currentId}}:video,audio:false});}}
 return {stream,devices};
}
root.QuizCamera={open,tune,mainScore};})(window);

