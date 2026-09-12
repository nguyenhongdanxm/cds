(function(){
  function rounded(ctx,x,y,w,h,r,fill,stroke){ctx.beginPath();ctx.roundRect(x,y,w,h,r);if(fill){ctx.fillStyle=fill;ctx.fill()}if(stroke){ctx.strokeStyle=stroke;ctx.stroke()}}
  function fit(ctx,text,max){text=String(text||'');if(ctx.measureText(text).width<=max)return text;while(text.length>2&&ctx.measureText(text+'…').width>max)text=text.slice(0,-1);return text+'…'}
  function exportImage(){
    var d=window.TD_ROOM_EXPORT;if(!d||!Array.isArray(d.rows))return;document.querySelectorAll('.stats-note').forEach(function(input,i){if(d.rows[i])d.rows[i].note=input.value.trim()});
    var W=1240,H=1754,canvas=document.createElement('canvas');canvas.width=W;canvas.height=H;var c=canvas.getContext('2d');
    var g=c.createLinearGradient(0,0,W,H);g.addColorStop(0,'#fffaf0');g.addColorStop(1,'#f3f8ff');c.fillStyle=g;c.fillRect(0,0,W,H);
    rounded(c,45,38,W-90,H-76,30,'#ffffff','#ead8a8');
    c.textAlign='center';c.fillStyle='#805800';c.font='600 24px "Segoe UI",Arial';c.fillText(String(d.department||'').toUpperCase(),W/2,92);
    c.fillStyle='#172033';c.font='800 27px "Segoe UI",Arial';c.fillText(String(d.school||'').toUpperCase(),W/2,132);
    c.fillStyle='#a56f00';c.font='900 43px "Segoe UI",Arial';c.fillText('KẾT QUẢ XẾP LOẠI PHÒNG NỘI TRÚ',W/2,205);
    c.fillStyle='#526071';c.font='600 24px "Segoe UI",Arial';c.fillText(d.period||'',W/2,246);
    c.strokeStyle='#e5b83c';c.lineWidth=3;c.beginPath();c.moveTo(110,274);c.lineTo(W-110,274);c.stroke();
    var rows=d.rows, top=310,bottom=1645,headerH=56,rowH=Math.min(58,Math.max(28,Math.floor((bottom-top-headerH)/Math.max(rows.length,1))));
    var x=[75,155,275,410,570,745,920,1138],heads=['Hạng','Phòng','Ngày','Điểm trừ','Tổng điểm','Điểm TB','Xếp loại','Ghi chú'];
    rounded(c,70,top,W-140,headerH,12,'#805800');c.fillStyle='#fff';c.font='700 19px "Segoe UI",Arial';c.textBaseline='middle';
    heads.forEach(function(h,i){c.textAlign=i===7?'left':'center';var left=i===0?70:x[i-1],right=x[i];c.fillText(h,i===7?left+10:(left+right)/2,top+headerH/2)});
    var y=top+headerH;
    rows.forEach(function(r,i){
      c.fillStyle=i%2?'#fffaf0':'#f7faff';c.fillRect(70,y,W-140,rowH);
      c.strokeStyle='#e5eaf0';c.lineWidth=1;c.beginPath();c.moveTo(70,y+rowH);c.lineTo(W-70,y+rowH);c.stroke();
      c.textBaseline='middle';c.fillStyle='#172033';c.font=(rowH<38?'600 15px':'600 18px')+' "Segoe UI",Arial';
      var vals=[r.rank||'—',r.room,r.days||'—',r.deduction,r.total,r.average,r.rating];
      vals.forEach(function(v,j){var left=j===0?70:x[j-1],right=x[j];c.textAlign='center';if(j===0&&Number(r.rank)===1){c.fillStyle='#a56f00';c.font='800 '+(rowH<38?'16':'20')+'px "Segoe UI",Arial'}else{c.fillStyle=j===6?(r.color||'#16794a'):'#172033';c.font=(j===6?'700 ':'600 ')+(rowH<38?'15':'18')+'px "Segoe UI",Arial'}c.fillText(fit(c,v,right-left-12),(left+right)/2,y+rowH/2)});
      c.textAlign='left';c.fillStyle='#526071';c.font=(rowH<38?'500 13px':'500 16px')+' "Segoe UI",Arial';c.fillText(fit(c,r.note||'',W-80-x[6]),x[6]+10,y+rowH/2);y+=rowH;
    });
    c.textAlign='left';c.fillStyle='#64748b';c.font='500 18px "Segoe UI",Arial';c.fillText('Điểm trung bình = tổng điểm các ngày được tính ÷ số ngày được tính',75,1690);
    c.textAlign='right';c.fillText('Xuất từ Hệ sinh thái CDS · '+new Date().toLocaleDateString('vi-VN'),W-75,1690);
    var a=document.createElement('a');a.download='Xep-loai-phong-noi-tru-'+String(d.key||'').replace(/[^0-9A-Za-z_-]/g,'-')+'.png';a.href=canvas.toDataURL('image/png',1);a.click();
  }
  var button=document.getElementById('tdRoomExportImage');if(button)button.addEventListener('click',exportImage);
})();