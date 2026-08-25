(function(){
  function rr(ctx,x,y,w,h,r,fill){ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();if(fill)ctx.fill();}
  function roomFor(row){
    var sid=row.querySelector('[name="sid[]"]');
    var id=sid?sid.value:'';
    return (window.NT_ATT_ROOM_MAP&&window.NT_ATT_ROOM_MAP[id])?String(window.NT_ATT_ROOM_MAP[id]):'';
  }
  function rowDataSafe(row){return typeof window.rowData==='function'?window.rowData(row):{excuse:{value:'KP'},reason:{value:''}};}
  window.drawReport=function(){
    var canvas=document.getElementById('reportCanvas');if(!canvas)return;
    var ctx=canvas.getContext('2d'),rows=[...document.querySelectorAll('.att-person')],abs=rows.filter(r=>r.classList.contains('absent'));
    var W=1120,two=abs.length>5,cols=two?2:1,perCol=two?Math.ceil(abs.length/2):abs.length,rowH=58,listRows=Math.max(1,perCol),listH=Math.max(110,listRows*rowH+54),H=500+listH;
    canvas.width=W;canvas.height=H;ctx.fillStyle='#f4f8fb';ctx.fillRect(0,0,W,H);ctx.fillStyle='#fff';ctx.fillRect(34,28,W-68,H-56);ctx.fillStyle='#0f5f86';ctx.fillRect(34,28,W-68,12);
    ctx.textAlign='center';ctx.fillStyle='#64748b';ctx.font='24px Arial';ctx.fillText(window.NT_ATT_REPORT_SCHOOL||'',W/2,82);ctx.fillStyle='#075985';ctx.font='bold 38px Arial';ctx.fillText('BÁO CÁO ĐIỂM DANH '+(window.NT_ATT_REPORT_SHIFT||''),W/2,130);ctx.fillStyle='#475569';ctx.font='22px Arial';ctx.fillText(window.NT_ATT_REPORT_DATE||'',W/2,166);
    ctx.strokeStyle='#d8e3ea';ctx.beginPath();ctx.moveTo(86,192);ctx.lineTo(W-86,192);ctx.stroke();
    var present=rows.length-abs.length,rate=rows.length?Math.round(present*100/rows.length):100,stats=[['TỔNG SỐ',rows.length,'#0f172a','#f1f5f9'],['CÓ MẶT',present,'#15803d','#ecfdf3'],['VẮNG',abs.length,'#dc2626','#fff1f2'],['TỶ LỆ',rate+'%','#0284c7','#eff8ff']];
    stats.forEach(function(s,i){var x=74+i*250;ctx.fillStyle=s[3];rr(ctx,x,218,222,112,16,true);ctx.fillStyle=s[2];ctx.font='bold 34px Arial';ctx.fillText(s[1],x+111,264);ctx.font='bold 16px Arial';ctx.fillText(s[0],x+111,298);});
    ctx.textAlign='left';ctx.fillStyle='#0f172a';ctx.font='bold 22px Arial';ctx.fillText('DANH SÁCH HỌC SINH VẮNG ('+abs.length+')',74,378);ctx.fillStyle='#94a3b8';ctx.font='15px Arial';ctx.fillText(abs.length?'Lớp · Họ tên · Phòng · Loại vắng · Lý do':'Không có học sinh vắng trong buổi điểm danh này',74,405);
    var startY=446,gap=28,colW=two?((W-148-gap)/2):(W-148),leftX=74;
    if(!abs.length){ctx.fillStyle='#15803d';ctx.font='bold 22px Arial';ctx.fillText('✓ Tất cả học sinh có mặt',74,startY+28);} else {
      abs.forEach(function(r,i){var col=two?(i>=perCol?1:0):0,idx=two?(i%perCol):i,x=leftX+col*(colW+gap),top=startY+idx*rowH,d=rowDataSafe(r),room=roomFor(r),roomText=room?' (Phòng '+room+')':'',num=i+1;
        ctx.fillStyle=idx%2===0?'#fff7f7':'#ffffff';ctx.fillRect(x-6,top-28,colW+12,rowH-4);
        ctx.fillStyle='#b91c1c';ctx.font='bold 17px Arial';var name=num+'. '+(r.dataset.class||'')+' · '+(r.dataset.name||'')+roomText;var maxName=colW-34;while(ctx.measureText(name).width>maxName&&name.length>8)name=name.slice(0,-2)+'…';ctx.fillText(name,x+8,top);
        ctx.fillStyle='#475569';ctx.font='15px Arial';var detail=(d.excuse.value||'KP')+(d.reason.value?' — '+d.reason.value:'');var dx=x+8,dy=top+22;while(ctx.measureText(detail).width>maxName&&detail.length>5)detail=detail.slice(0,-2)+'…';ctx.fillText(detail,dx,dy);
      });
    }
    var footerY=H-88;ctx.strokeStyle='#d8e3ea';ctx.beginPath();ctx.moveTo(74,footerY-22);ctx.lineTo(W-74,footerY-22);ctx.stroke();var reporterSelect=document.querySelector('[name="reporter"]'),reporterName=reporterSelect?reporterSelect.value:(window.NT_ATT_REPORT_REPORTER||'');ctx.fillStyle='#475569';ctx.font='16px Arial';ctx.textAlign='left';ctx.fillText('Người báo cáo: '+reporterName,74,footerY+4);ctx.fillText('Thời điểm xuất: '+new Date().toLocaleString('vi-VN',{hour:'2-digit',minute:'2-digit',day:'2-digit',month:'2-digit',year:'numeric'}),74,footerY+32);ctx.textAlign='right';ctx.fillStyle='#64748b';ctx.font='bold 14px Arial';ctx.fillText('HỆ SINH THÁI QUẢN LÝ NHÀ TRƯỜNG - Quản lý nội trú',W-74,footerY+32);
  };
})();
