(function(){
  'use strict';

  function parseDateFromPage(){
    var input=document.querySelector('form input[name="date"]');
    if(input&&/^\d{4}-\d{2}-\d{2}$/.test(input.value)) return input.value;
    var d=(window.ntMealDayData&&window.ntMealDayData.date)||'';
    var m=String(d).match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    return m ? (m[3]+'-'+m[2]+'-'+m[1]) : '';
  }

  function linesForText(ctx,text,maxWidth){
    var words=String(text||'').split(/\s+/).filter(Boolean),lines=[],line='';
    words.forEach(function(word){var test=line?line+' '+word:word;if(line&&ctx.measureText(test).width>maxWidth){lines.push(line);line=word;}else line=test;});
    if(line)lines.push(line);return lines.length?lines:[''];
  }
  function absentText(meal){
    var names=(meal&&meal.absent_names)||[];
    if(meal&&meal.state==='off')return 'Nghỉ';
    if(!meal||meal.reported===false)return 'Chưa báo';
    return names.length?names.join(', '):'—';
  }

  function buildCanvas(data){
    var canvas=document.createElement('canvas'),ctx=canvas.getContext('2d'),W=1500,margin=55,tableX=55,tableW=1390;
    var cols=[{w:95,label:'LỚP'},{w:105,label:'SĨ SỐ'},{w:100,label:'SL ĂN'},{w:285,label:'VẮNG BỮA SÁNG'},{w:100,label:'SL ĂN'},{w:285,label:'VẮNG BỮA TRƯA'},{w:100,label:'SL ĂN'},{w:320,label:'VẮNG BỮA TỐI'}];
    var rows=data.rows||[];ctx.font='20px Arial';
    var rowHeights=rows.map(function(r){var max=1;[['sang',285],['trua',285],['toi',320]].forEach(function(a){max=Math.max(max,linesForText(ctx,absentText((r.meals||{})[a[0]]),a[1]-20).length);});return Math.max(58,24+max*24);});
    var headerH=185,groupH=50,subH=50,totalH=62,footerH=55,H=headerH+groupH+subH+rowHeights.reduce(function(a,b){return a+b;},0)+totalH+footerH+30;
    canvas.width=W;canvas.height=H;ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);
    ctx.textAlign='center';ctx.fillStyle='#475569';ctx.font='22px Arial';ctx.fillText(data.school||'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN',W/2,42);
    ctx.fillStyle='#0f3f67';ctx.font='bold 34px Arial';ctx.fillText('BẢNG SỐ LƯỢNG ĂN THEO LỚP',W/2,88);
    ctx.fillStyle='#334155';ctx.font='22px Arial';ctx.fillText('Ngày '+(data.date_label||''),W/2,124);ctx.strokeStyle='#0f3f67';ctx.lineWidth=3;ctx.beginPath();ctx.moveTo(250,145);ctx.lineTo(1250,145);ctx.stroke();
    var y=headerH,starts=[],x=tableX;cols.forEach(function(c){starts.push(x);x+=c.w;});
    ctx.fillStyle='#dbeafe';ctx.fillRect(tableX,y,200,groupH);ctx.fillStyle='#fff7d6';ctx.fillRect(tableX+200,y,385,groupH);ctx.fillStyle='#dcfce7';ctx.fillRect(tableX+585,y,385,groupH);ctx.fillStyle='#e0e7ff';ctx.fillRect(tableX+970,y,420,groupH);ctx.strokeStyle='#64748b';ctx.lineWidth=1;ctx.strokeRect(tableX,y,tableW,groupH);[200,585,970].forEach(function(o){ctx.beginPath();ctx.moveTo(tableX+o,y);ctx.lineTo(tableX+o,y+groupH);ctx.stroke();});ctx.font='bold 22px Arial';ctx.fillStyle='#0f172a';ctx.fillText('THÔNG TIN LỚP',tableX+100,y+32);ctx.fillText('BỮA SÁNG',tableX+392.5,y+32);ctx.fillText('BỮA TRƯA',tableX+777.5,y+32);ctx.fillText('BỮA TỐI',tableX+1180,y+32);y+=groupH;
    ctx.fillStyle='#eef4fb';ctx.fillRect(tableX,y,tableW,subH);ctx.font='bold 18px Arial';cols.forEach(function(c,i){ctx.strokeRect(starts[i],y,c.w,subH);ctx.fillText(c.label,starts[i]+c.w/2,y+31);});y+=subH;
    rows.forEach(function(r,ri){var h=rowHeights[ri];ctx.fillStyle=ri%2?'#f8fafc':'#fff';ctx.fillRect(tableX,y,tableW,h);cols.forEach(function(c,i){ctx.strokeStyle='#94a3b8';ctx.strokeRect(starts[i],y,c.w,h);});ctx.fillStyle='#0f172a';ctx.font='bold 20px Arial';ctx.textAlign='center';ctx.fillText(String(r.class||''),starts[0]+47.5,y+h/2+7);ctx.font='20px Arial';ctx.fillText(String(r.students||0),starts[1]+52.5,y+h/2+7);['sang','trua','toi'].forEach(function(meal,idx){var m=(r.meals||{})[meal]||{},eat=m.state==='off'?0:(m.reported===false?'—':m.eat),ec=2+idx*2,ac=ec+1;ctx.font='bold 21px Arial';ctx.fillStyle='#0f3f67';ctx.fillText(String(eat==null?'—':eat),starts[ec]+cols[ec].w/2,y+h/2+7);ctx.font='18px Arial';ctx.fillStyle=m.reported===false?'#b45309':'#334155';ctx.textAlign='left';var ls=linesForText(ctx,absentText(m),cols[ac].w-20),lh=23,yy=y+(h-ls.length*lh)/2+18;ls.forEach(function(line){ctx.fillText(line,starts[ac]+10,yy);yy+=lh;});ctx.textAlign='center';});y+=h;});
    ctx.fillStyle='#e2e8f0';ctx.fillRect(tableX,y,tableW,totalH);cols.forEach(function(c,i){ctx.strokeStyle='#64748b';ctx.strokeRect(starts[i],y,c.w,totalH);});ctx.fillStyle='#0f172a';ctx.font='bold 21px Arial';ctx.textAlign='center';ctx.fillText('TỔNG',tableX+47.5,y+39);ctx.fillText(String((data.totals||{}).students||0),starts[1]+52.5,y+39);ctx.fillStyle='#0f3f67';ctx.fillText(String((data.totals||{}).sang||0),starts[2]+50,y+39);ctx.fillText(String((data.totals||{}).trua||0),starts[4]+50,y+39);ctx.fillText(String((data.totals||{}).toi||0),starts[6]+50,y+39);y+=totalH;
    ctx.textAlign='left';ctx.fillStyle='#64748b';ctx.font='16px Arial';ctx.fillText('Ghi chú: “Chưa báo” = lớp chưa gửi báo ăn của bữa đó; “Nghỉ” = bữa ăn được đặt trạng thái nghỉ.',margin,y+34);ctx.textAlign='right';ctx.fillText('Xuất lúc '+new Date().toLocaleString('vi-VN'),W-margin,y+34);return canvas;
  }

  function showQuantity(data,date){
    var canvas=buildCanvas(data);if(typeof mealDayExport!=='undefined'){mealDayExport.type='quantity';mealDayExport.canvas=canvas;mealDayExport.filename='so-luong-an-theo-lop-'+date+'.png';}
    var title=document.getElementById('mealDayExportTitle'),preview=document.getElementById('mealDayExportPreview'),modal=document.getElementById('mealDayExportModal');if(title)title.innerHTML='<i class="bi bi-table me-2"></i>Ảnh số lượng ăn theo lớp';if(preview)preview.src=canvas.toDataURL('image/png');if(modal&&window.bootstrap)bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  window.openMealQuantityExport=function(){
    var date=parseDateFromPage();if(!date){alert('Không xác định được ngày cần xuất.');return;}
    fetch((window.BASE_URL||'/')+'noitru_meal_quantity_data.php?date='+encodeURIComponent(date),{credentials:'same-origin',cache:'no-store'})
      .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
      .then(function(data){if(!data||!data.ok)throw new Error((data&&data.message)||'Không lấy được dữ liệu');showQuantity(data,date);})
      .catch(function(err){alert('Không tạo được ảnh số lượng: '+err.message);});
  };
  document.addEventListener('DOMContentLoaded',function(){var summaryBtn=document.querySelector('button[onclick="openMealDayExport(\'summary\')"]');if(!summaryBtn)return;var li=summaryBtn.closest('li');if(!li||document.getElementById('mealQuantityExportItem'))return;var newLi=document.createElement('li');newLi.id='mealQuantityExportItem';newLi.innerHTML='<button class="dropdown-item" type="button" onclick="openMealQuantityExport()"><i class="bi bi-table text-primary me-2"></i>Ảnh số lượng</button>';li.insertAdjacentElement('afterend',newLi);});
})();
