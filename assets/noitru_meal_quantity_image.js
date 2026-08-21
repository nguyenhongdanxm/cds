(function(){
  'use strict';

  function parseDateFromPage(){
    var input=document.querySelector('form input[name="date"]');
    if(input&&/^\d{4}-\d{2}-\d{2}$/.test(input.value)) return input.value;
    var d=(window.ntMealDayData&&window.ntMealDayData.date)||'';
    var m=String(d).match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    return m ? (m[3]+'-'+m[2]+'-'+m[1]) : '';
  }

  function roundRect(ctx,x,y,w,h,r,fill,stroke){
    ctx.beginPath();
    if(ctx.roundRect) ctx.roundRect(x,y,w,h,r); else ctx.rect(x,y,w,h);
    if(fill){ctx.fillStyle=fill;ctx.fill();}
    if(stroke){ctx.strokeStyle=stroke;ctx.lineWidth=1;ctx.stroke();}
  }

  function linesForText(ctx,text,maxWidth){
    var words=String(text||'').split(/\s+/).filter(Boolean),lines=[],line='';
    words.forEach(function(word){
      var test=line ? line+' '+word : word;
      if(line && ctx.measureText(test).width>maxWidth){lines.push(line);line=word;} else line=test;
    });
    if(line) lines.push(line);
    return lines.length?lines:[''];
  }

  function absentText(meal){
    var names=(meal&&meal.absent_names)||[];
    if(meal&&meal.state==='off') return 'Nghỉ';
    if(!meal||meal.reported===false) return 'Chưa báo';
    return names.length ? names.join(', ') : '—';
  }

  function buildCanvas(data){
    var canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');
    var W=1500, margin=55, tableX=55, tableW=1390;
    var cols=[
      {k:'class',w:95,label:'LỚP'},
      {k:'students',w:105,label:'SĨ SỐ'},
      {k:'sangEat',w:100,label:'SL ĂN'},
      {k:'sangAbs',w:285,label:'VẮNG BỮA SÁNG'},
      {k:'truaEat',w:100,label:'SL ĂN'},
      {k:'truaAbs',w:285,label:'VẮNG BỮA TRƯA'},
      {k:'toiEat',w:100,label:'SL ĂN'},
      {k:'toiAbs',w:320,label:'VẮNG BỮA TỐI'}
    ];
    var rows=data.rows||[];
    ctx.font='20px Arial';
    var rowHeights=rows.map(function(r){
      var maxLines=1;
      [['sang',285],['trua',285],['toi',320]].forEach(function(x){
        var text=absentText((r.meals||{})[x[0]]);
        maxLines=Math.max(maxLines,linesForText(ctx,text,x[1]-20).length);
      });
      return Math.max(58,24+maxLines*24);
    });
    var headerH=185, groupH=50, subH=50, totalH=62, footerH=55;
    var H=headerH+groupH+subH+rowHeights.reduce(function(a,b){return a+b;},0)+totalH+footerH+30;
    canvas.width=W;canvas.height=H;
    ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);

    ctx.textAlign='center';ctx.fillStyle='#475569';ctx.font='22px Arial';
    ctx.fillText(data.school||'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN',W/2,42);
    ctx.fillStyle='#0f3f67';ctx.font='bold 34px Arial';
    ctx.fillText('BẢNG SỐ LƯỢNG ĂN THEO LỚP',W/2,88);
    ctx.fillStyle='#334155';ctx.font='22px Arial';
    ctx.fillText('Ngày '+(data.date_label||''),W/2,124);
    ctx.strokeStyle='#0f3f67';ctx.lineWidth=3;ctx.beginPath();ctx.moveTo(250,145);ctx.lineTo(1250,145);ctx.stroke();

    var y=headerH;
    var starts=[],x=tableX; cols.forEach(function(c){starts.push(x);x+=c.w;});

    ctx.fillStyle='#dbeafe';ctx.fillRect(tableX,y,200,groupH);
    ctx.fillStyle='#fff7d6';ctx.fillRect(tableX+200,y,385,groupH);
    ctx.fillStyle='#dcfce7';ctx.fillRect(tableX+585,y,385,groupH);
    ctx.fillStyle='#e0e7ff';ctx.fillRect(tableX+970,y,420,groupH);
    ctx.strokeStyle='#64748b';ctx.lineWidth=1;
    ctx.strokeRect(tableX,y,tableW,groupH);
    [200,585,970].forEach(function(off){ctx.beginPath();ctx.moveTo(tableX+off,y);ctx.lineTo(tableX+off,y+groupH);ctx.stroke();});
    ctx.font='bold 22px Arial';ctx.fillStyle='#0f172a';
    ctx.fillText('THÔNG TIN LỚP',tableX+100,y+32);
    ctx.fillText('BỮA SÁNG',tableX+392.5,y+32);
    ctx.fillText('BỮA TRƯA',tableX+777.5,y+32);
    ctx.fillText('BỮA TỐI',tableX+1180,y+32);
    y+=groupH;

    ctx.fillStyle='#eef4fb';ctx.fillRect(tableX,y,tableW,subH);
    ctx.font='bold 18px Arial';ctx.fillStyle='#0f172a';
    cols.forEach(function(c,i){ctx.strokeRect(starts[i],y,c.w,subH);ctx.fillText(c.label,starts[i]+c.w/2,y+31);});
    y+=subH;

    rows.forEach(function(r,ri){
      var h=rowHeights[ri];
      ctx.fillStyle=ri%2===0?'#ffffff':'#f8fafc';ctx.fillRect(tableX,y,tableW,h);
      cols.forEach(function(c,i){ctx.strokeStyle='#94a3b8';ctx.strokeRect(starts[i],y,c.w,h);});
      ctx.fillStyle='#0f172a';ctx.font='bold 20px Arial';ctx.textAlign='center';
      ctx.fillText(String(r.class||''),starts[0]+cols[0].w/2,y+h/2+7);
      ctx.font='20px Arial';ctx.fillText(String(r.students||0),starts[1]+cols[1].w/2,y+h/2+7);
      ['sang','trua','toi'].forEach(function(meal,idx){
        var m=(r.meals||{})[meal]||{},eat=(m.state==='off'?0:(m.reported===false?'—':m.eat));
        var eatCol=2+idx*2,absCol=eatCol+1;
        ctx.font='bold 21px Arial';ctx.fillStyle='#0f3f67';ctx.fillText(String(eat===null?'—':eat),starts[eatCol]+cols[eatCol].w/2,y+h/2+7);
        ctx.font='18px Arial';ctx.fillStyle=m.reported===false?'#b45309':'#334155';ctx.textAlign='left';
        var lines=linesForText(ctx,absentText(m),cols[absCol].w-20),lineH=23,yy=y+(h-lines.length*lineH)/2+18;
        lines.forEach(function(line){ctx.fillText(line,starts[absCol]+10,yy);yy+=lineH;});
        ctx.textAlign='center';
      });
      y+=h;
    });

    ctx.fillStyle='#e2e8f0';ctx.fillRect(tableX,y,tableW,totalH);
    cols.forEach(function(c,i){ctx.strokeStyle='#64748b';ctx.strokeRect(starts[i],y,c.w,totalH);});
    ctx.fillStyle='#0f172a';ctx.font='bold 21px Arial';ctx.textAlign='center';ctx.fillText('TỔNG',tableX+47.5,y+39);
    ctx.fillText(String((data.totals||{}).students||0),starts[1]+cols[1].w/2,y+39);
    ctx.fillStyle='#0f3f67';
    ctx.fillText(String((data.totals||{}).sang||0),starts[2]+cols[2].w/2,y+39);
    ctx.fillText(String((data.totals||{}).trua||0),starts[4]+cols[4].w/2,y+39);
    ctx.fillText(String((data.totals||{}).toi||0),starts[6]+cols[6].w/2,y+39);
    y+=totalH;

    ctx.textAlign='left';ctx.fillStyle='#64748b';ctx.font='16px Arial';ctx.fillText('Ghi chú: “Chưa báo” = lớp chưa gửi báo ăn của bữa đó; “Nghỉ” = bữa ăn được đặt trạng thái nghỉ.',margin,y+34);
    ctx.textAlign='right';ctx.fillText('Xuất lúc '+new Date().toLocaleString('vi-VN'),W-margin,y+34);
    return canvas;
  }

  window.openMealQuantityExport=function(){
    var date=parseDateFromPage();
    if(!date){alert('Không xác định được ngày cần xuất.');return;}
    fetch((window.BASE_URL||'')+'noitru_meal_quantity_data.php?date='+encodeURIComponent(date),{credentials:'same-origin'})
      .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
      .then(function(data){
        if(!data||!data.ok)throw new Error((data&&data.message)||'Không lấy được dữ liệu');
        var canvas=buildCanvas(data);
        if(typeof mealDayExport!=='undefined'){
          mealDayExport.type='quantity';
          mealDayExport.canvas=canvas;
          mealDayExport.filename='so-luong-an-theo-lop-'+date+'.png';
        }
        var title=document.getElementById('mealDayExportTitle'),preview=document.getElementById('mealDayExportPreview'),modal=document.getElementById('mealDayExportModal');
        if(title)title.innerHTML='<i class="bi bi-table me-2"></i>Ảnh số lượng ăn theo lớp';
        if(preview)preview.src=canvas.toDataURL('image/png');
        if(modal&&window.bootstrap)bootstrap.Modal.getOrCreateInstance(modal).show();
      })
      .catch(function(err){alert('Không tạo được ảnh số lượng: '+err.message);});
  };

  document.addEventListener('DOMContentLoaded',function(){
    var summaryBtn=document.querySelector('button[onclick="openMealDayExport(\'summary\')"]');
    if(!summaryBtn)return;
    var li=summaryBtn.closest('li');
    if(!li||document.getElementById('mealQuantityExportItem'))return;
    var newLi=document.createElement('li');newLi.id='mealQuantityExportItem';
    newLi.innerHTML='<button class="dropdown-item" type="button" onclick="openMealQuantityExport()"><i class="bi bi-table text-primary me-2"></i>Ảnh số lượng</button>';
    li.insertAdjacentElement('afterend',newLi);
  });
})();
