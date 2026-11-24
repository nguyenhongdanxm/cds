/** Normalize teacher signatures before submitting; the server validates and normalizes again. */
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('input[type=file][name=teacher_signature]').forEach(input=>{
  const form=input.closest('form');if(!form)return;
  form.addEventListener('submit',async event=>{
   const file=input.files&&input.files[0];if(!file||form.dataset.signatureReady==='1')return;
   event.preventDefault();
   let message=form.querySelector('[data-signature-status]');if(!message){message=document.createElement('div');message.dataset.signatureStatus='1';message.className='form-text';input.after(message);}
   const buttons=[...form.querySelectorAll('[type=submit],button:not([type])')];buttons.forEach(b=>b.disabled=true);message.textContent='Đang chuẩn hóa chữ ký…';
   try{
    if(file.size>5*1024*1024)throw Error('Ảnh chữ ký tối đa 5 MB.');
    const bitmap=await createImageBitmap(file),w=bitmap.width,h=bitmap.height;
    if(w>6000||h>6000||w*h>20000000){bitmap.close();throw Error('Ảnh tối đa 6000 px và 20 triệu điểm ảnh.');}
    const source=document.createElement('canvas');source.width=w;source.height=h;const ctx=source.getContext('2d',{willReadFrequently:true});ctx.drawImage(bitmap,0,0);bitmap.close();
    const pixels=ctx.getImageData(0,0,w,h).data;let minX=w,minY=h,maxX=-1,maxY=-1;
    for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4;if(pixels[i+3]>15&&Math.min(pixels[i],pixels[i+1],pixels[i+2])<238){minX=Math.min(minX,x);maxX=Math.max(maxX,x);minY=Math.min(minY,y);maxY=Math.max(maxY,y);}}
    if(maxX<0)throw Error('Không nhận thấy nét chữ ký trong ảnh.');
    const canvas=document.createElement('canvas');canvas.width=480;canvas.height=160;const cw=maxX-minX+1,ch=maxY-minY+1,scale=Math.min(440/cw,120/ch),nw=Math.max(1,Math.round(cw*scale)),nh=Math.max(1,Math.round(ch*scale));canvas.getContext('2d').drawImage(source,minX,minY,cw,ch,Math.round((480-nw)/2),Math.round((160-nh)/2),nw,nh);
    const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!blob)throw Error('Không tạo được ảnh PNG.');
    const transfer=new DataTransfer();transfer.items.add(new File([blob],'chu-ky.png',{type:'image/png'}));input.files=transfer.files;form.dataset.signatureReady='1';buttons.forEach(b=>b.disabled=false);message.textContent='Đang lưu chữ ký…';HTMLFormElement.prototype.submit.call(form);
   }catch(error){buttons.forEach(b=>b.disabled=false);message.textContent=error.message||'Không xử lý được ảnh chữ ký.';}
  });
 });
});
