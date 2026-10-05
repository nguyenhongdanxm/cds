/* Báo cáo ảnh được tạo tại trình duyệt từ dữ liệu đúng bộ lọc và quyền hiện tại. */
(() => {
  'use strict';
  const button = document.getElementById('healthExportImage');
  const source = document.getElementById('healthReportData');
  const preview = document.getElementById('healthReportPreview');
  if (!button || !source || !preview) return;
  const formatDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value || '') ? value.split('-').reverse().join('/') : value || '';
  function wrap(ctx, text, width) {
    const result = [];
    String(text || '—').split('\n').forEach(paragraph => {
      let line = '';
      for (const word of paragraph.split(/\s+/)) {
        const next = line ? line + ' ' + word : word;
        if (ctx.measureText(next).width > width && line) { result.push(line); line = word; }
        else line = next;
        // Break unspaced text so it cannot overlap adjacent cells.
        while (ctx.measureText(line).width > width && line.length > 1) {
          let cut = line.length - 1;
          while (cut > 1 && ctx.measureText(line.slice(0, cut)).width > width) cut--;
          result.push(line.slice(0, cut)); line = line.slice(cut);
        }
      }
      result.push(line);
    });
    return result;
  }
  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      if (document.fonts?.ready) await document.fonts.ready;
      const data = JSON.parse(source.textContent);
      const records = data.rows || [];
      preview.replaceChildren(); preview.hidden = false;
      const intro = document.createElement('p');
      intro.textContent = 'Báo cáo theo bộ lọc hiện tại. Chọn “Tải ảnh” dưới từng trang để lưu PNG.';
      preview.appendChild(intro);
      const columns = [60, 160, 270, 290, 380, 260];
      const canvas = document.createElement('canvas'); canvas.width = 1500;
      const ctx = canvas.getContext('2d');
      ctx.font = '20px Arial';
      const rows = records.map((r, index) => {
        const meds = (r.medicines || []).map(m => `${m.name || ''} · ${m.quantity || 0} ${m.unit || ''}`).join('\n');
        const values = [index + 1, formatDate(r.date), `${r.student_name || ''}\n${r.class_name || ''}`, r.diagnosis,
          [data.labels?.[r.type] || r.type, meds, r.treatment].filter(Boolean).join('\n'), r.note];
        const lines = values.map((v, i) => wrap(ctx, v, columns[i] - 24));
        return { lines, height: Math.max(74, Math.max(...lines.map(x => x.length)) * 28 + 26) };
      });
      const pages = []; let page = [], height = 0;
      for (const row of rows) {
        if (page.length && (height + row.height > 1800 || page.length >= 20)) { pages.push(page); page = []; height = 0; }
        page.push(row); height += row.height;
      }
      if (page.length || !pages.length) pages.push(page);
      pages.forEach((items, index) => {
        canvas.height = 370 + Math.max(90, items.reduce((n, r) => n + r.height, 0));
        ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, 1500, canvas.height);
        const gradient = ctx.createLinearGradient(0, 0, 1500, 190);
        gradient.addColorStop(0, '#123451'); gradient.addColorStop(1, '#0d9488');
        ctx.fillStyle = gradient; ctx.fillRect(0, 0, 1500, 190);
        ctx.fillStyle = '#b9f5e7'; ctx.font = 'bold 19px Arial'; ctx.fillText('CHĂM SÓC SỨC KHỎE HỌC SINH', 40, 43);
        ctx.fillStyle = '#ffffff'; ctx.font = 'bold 36px Arial';
        ctx.fillText('BÁO CÁO ' + (data.range === 'week' ? 'TUẦN' : data.range === 'day' ? 'NGÀY' : 'THÁNG'), 40, 96);
        ctx.font = '23px Arial'; ctx.fillText(`${formatDate(data.from)} – ${formatDate(data.to)}`, 40, 142);
        ctx.textAlign = 'right'; ctx.font = '20px Arial'; ctx.fillText(`Trang ${index + 1}/${pages.length}`, 1460, 142); ctx.textAlign = 'left';
        const metrics = [ ['LƯỢT CHĂM SÓC', records.length], ['HỌC SINH', new Set(records.map(r => r.student_id || r.student_name)).size],
          ['PHÁT THUỐC', records.filter(r => ['medicine','thuoc'].includes(r.type)).length], ['VÀO VIỆN', records.filter(r => r.type === 'hospital').length] ];
        metrics.forEach(([label, value], i) => {
          const x = 40 + i * 358; ctx.fillStyle = '#edf8f7'; ctx.fillRect(x, 210, 340, 76);
          ctx.fillStyle = '#0f766e'; ctx.font = 'bold 28px Arial'; ctx.fillText(String(value), x + 16, 241);
          ctx.fillStyle = '#456277'; ctx.font = '16px Arial'; ctx.fillText(label, x + 16, 267);
        });
        const headers = ['STT', 'NGÀY', 'HỌC SINH / LỚP', 'CHẨN ĐOÁN', 'XỬ LÝ / THUỐC', 'GHI CHÚ'];
        ctx.fillStyle = '#173b53'; ctx.fillRect(40, 310, 1420, 48);
        let x = 40; ctx.fillStyle = '#ffffff'; ctx.font = 'bold 17px Arial';
        headers.forEach((h, i) => { ctx.fillText(h, x + 12, 340); x += columns[i]; });
        let y = 358;
        if (!items.length) {ctx.fillStyle = '#475569';ctx.font = '22px Arial';ctx.fillText('Không có hồ sơ trong kỳ và bộ lọc đã chọn.', 64, y + 48);}
        items.forEach((row, rowIndex) => {
          ctx.fillStyle = rowIndex % 2 ? '#f1f7fa' : '#ffffff'; ctx.fillRect(40, y, 1420, row.height);
          ctx.fillStyle = '#233b4e'; ctx.font = '20px Arial'; x = 40;
          row.lines.forEach((lines, i) => { lines.forEach((line, j) => ctx.fillText(line, x + 12, y + 28 + j * 28)); x += columns[i]; });
          ctx.strokeStyle = '#dce6eb'; ctx.beginPath(); ctx.moveTo(40, y + row.height); ctx.lineTo(1460, y + row.height); ctx.stroke(); y += row.height;
        });
        const url = canvas.toDataURL('image/png');
        const image = document.createElement('img'); image.src = url; image.alt = `Báo cáo sức khỏe trang ${index + 1}`;
        const link = document.createElement('a'); link.href = url; link.download = `bao-cao-suc-khoe-${data.range}-${data.from}-${index + 1}.png`; link.textContent = `Tải ảnh — trang ${index + 1}`;
        preview.append(image, link);
      });
      preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
      preview.hidden = false; preview.textContent = 'Không tạo được báo cáo ảnh. Hãy tải lại trang và thử lại.'; console.error(error);
    } finally { button.disabled = false; }
  });
})();
