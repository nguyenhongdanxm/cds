/* Một ảnh tổng hợp tuần, có thể lưu hoặc chia sẻ trực tiếp trên điện thoại. */
(() => {
  'use strict';
  const button = document.getElementById('healthExportImage');
  const source = document.getElementById('healthReportData');
  const preview = document.getElementById('healthReportPreview');
  if (!button || !source || !preview) return;
  let previousUrl = null;
  const dialog = document.getElementById('healthReportDialog');
  document.getElementById('healthReportClose')?.addEventListener('click', () => dialog?.close());
  const date = value => String(value || '').split('-').reverse().join('/');
  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      if (document.fonts?.ready) await document.fonts.ready;
      const data = JSON.parse(source.textContent), classes = data.classes || [];
      const canvas = document.createElement('canvas');
      // Même cadre, en-tête, cartes pastel et pied de page que drawReport() du pointage.
      const W = 1080, rowH = 48, H = 638 + Math.max(1, classes.length) * rowH;
      canvas.width = W; canvas.height = H;
      const ctx = canvas.getContext('2d');
      function fit(text, width, size, min, bold = '') {
        do {ctx.font = `${bold} ${size}px Arial`; if(ctx.measureText(String(text)).width <= width) break; size--;} while(size >= min);
      }
      function roundRect(x, y, w, h, r) {
        ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);
        ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();ctx.fill();
      }
      ctx.fillStyle = '#f4f8fb'; ctx.fillRect(0,0,W,H);
      ctx.fillStyle = '#ffffff'; ctx.fillRect(36,30,W-72,H-60);
      ctx.fillStyle = '#0f5f86'; ctx.fillRect(36,30,W-72,12);
      ctx.textAlign = 'center';ctx.fillStyle = '#64748b';fit(data.school || 'Trường',880,24,16);ctx.fillText(data.school || 'Trường',W/2,82,880);
      ctx.fillStyle = '#075985';fit('BÁO CÁO SỨC KHỎE TUẦN',880,38,28,'bold');ctx.fillText('BÁO CÁO SỨC KHỎE TUẦN',W/2,128);
      ctx.fillStyle = '#475569';ctx.font = '22px Arial';ctx.fillText(`Từ ${date(data.from)} đến ${date(data.to)}`,W/2,164);
      ctx.strokeStyle = '#d8e3ea';ctx.beginPath();ctx.moveTo(90,190);ctx.lineTo(W-90,190);ctx.stroke();
      const stats = [['LƯỢT CHĂM SÓC',data.total,'#0f172a','#f1f5f9'],['HỌC SINH',data.students,'#15803d','#ecfdf3'],
        ['PHÁT THUỐC',data.types?.medicine,'#0284c7','#eff8ff'],['VÀO VIỆN',data.types?.hospital,'#dc2626','#fff1f2']];
      stats.forEach((s,i) => {const x=78+i*238;ctx.fillStyle=s[3];roundRect(x,216,210,112,16);
        ctx.fillStyle=s[2];ctx.font='bold 34px Arial';ctx.fillText(String(s[1]||0),x+105,262);ctx.font='bold 16px Arial';ctx.fillText(s[0],x+105,296);});
      ctx.textAlign='left';ctx.fillStyle='#475569';ctx.font='20px Arial';
      ctx.fillText(`Theo dõi tại phòng y tế: ${data.types?.first_aid||0} lượt`,78,368);
      ctx.fillText(`Gia đình đón về: ${data.types?.family_pickup||0} lượt`,600,368);
      ctx.fillStyle='#0f172a';ctx.font='bold 22px Arial';ctx.fillText('TỔNG HỢP THEO LỚP',78,416);
      ctx.fillStyle='#94a3b8';ctx.font='15px Arial';ctx.fillText('Số học sinh được tính một lần trong tuần; số lượt tính từng hồ sơ.',78,444);
      const xs=[90,390,625,870];ctx.fillStyle='#eff8ff';ctx.fillRect(68,466,W-136,44);
      ctx.fillStyle='#075985';ctx.font='bold 19px Arial';['Lớp','Lượt chăm sóc','Học sinh','Vào viện'].forEach((s,i)=>ctx.fillText(s,xs[i],494));
      classes.forEach((row,i)=>{const top=510+i*rowH;ctx.fillStyle=i%2===0?'#f4f8fb':'#ffffff';ctx.fillRect(68,top,W-136,rowH-2);
        ctx.fillStyle='#334155';ctx.font='20px Arial';[row.class,row.visits,row.students,row.hospital].forEach((s,j)=>ctx.fillText(String(s??0),xs[j],top+31,j===0?270:190));});
      if(!classes.length){ctx.fillStyle='#15803d';ctx.font='bold 20px Arial';ctx.fillText('Không có hồ sơ chăm sóc sức khỏe trong tuần.',90,542);}
      const footerY=H-92;ctx.strokeStyle='#d8e3ea';ctx.beginPath();ctx.moveTo(78,footerY-24);ctx.lineTo(W-78,footerY-24);ctx.stroke();
      ctx.fillStyle='#475569';ctx.textAlign='left';ctx.font='16px Arial';ctx.fillText('Người báo cáo: '+(data.reporter||''),78,footerY+5,850);
      ctx.fillText('Thời điểm xuất: '+new Date().toLocaleString('vi-VN',{hour:'2-digit',minute:'2-digit',day:'2-digit',month:'2-digit',year:'numeric'}),78,footerY+34);
      ctx.textAlign='right';ctx.fillStyle='#94a3b8';ctx.font='14px Arial';ctx.fillText('Hệ thống CDS · Quản lý nội trú',W-78,footerY+34);
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
      if (!blob) throw new Error('PNG generation failed');
      if (previousUrl) URL.revokeObjectURL(previousUrl);
      previousUrl = URL.createObjectURL(blob);
      const name = `suc-khoe-tuan-${data.from}.png`;
      const image = document.createElement('img'); image.src = previousUrl; image.alt = 'Ảnh tổng hợp sức khỏe học sinh theo tuần';
      const download = document.createElement('a'); download.href = previousUrl; download.download = name; download.className = 'btn btn-outline-secondary'; download.innerHTML = '<i class="bi bi-download"></i> Tải ảnh';
      const share = document.createElement('button'); share.type = 'button'; share.className = 'btn btn-info text-white'; share.innerHTML = '<i class="bi bi-share"></i> Chia sẻ';
      const hint = document.createElement('p'); hint.textContent = 'Báo cáo tổng hợp cả tuần theo ngày đã chọn.';
      const file = typeof File === 'function' ? new File([blob], name, {type: 'image/png'}) : null;
      share.addEventListener('click', async () => {
        // File đã tạo sẵn: mở bảng chia sẻ ngay trong thao tác chạm của người dùng.
        if (!file || !navigator.share || !navigator.canShare?.({files: [file]})) {
          hint.textContent = 'Thiết bị chưa hỗ trợ chia sẻ trực tiếp. Chọn Tải ảnh hoặc nhấn giữ ảnh để lưu và gửi.';
          download.click(); return;
        }
        try { await navigator.share({files: [file], title: 'Báo cáo sức khỏe tuần'}); }
        catch (error) {if (error.name !== 'AbortError') hint.textContent = 'Chưa chia sẻ được. Bạn có thể tải ảnh để gửi.';}
      });
      const actions = document.createElement('div'); actions.className = 'health-report-actions'; actions.append(download, share);
      preview.replaceChildren(image, hint, actions); preview.hidden = false;
      if (dialog && !dialog.open) dialog.showModal();
    } catch (error) {
      preview.hidden = false; preview.textContent = 'Không tạo được ảnh. Hãy tải lại trang và thử lại.'; console.error(error);
    } finally {button.disabled = false;}
  });
})();
