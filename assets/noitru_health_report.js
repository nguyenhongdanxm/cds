/* Một ảnh tổng hợp tuần, có thể lưu hoặc chia sẻ trực tiếp trên điện thoại. */
(() => {
  'use strict';
  const button = document.getElementById('healthExportImage');
  const source = document.getElementById('healthReportData');
  const preview = document.getElementById('healthReportPreview');
  if (!button || !source || !preview) return;
  let previousUrl = null;
  const date = value => String(value || '').split('-').reverse().join('/');
  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      if (document.fonts?.ready) await document.fonts.ready;
      const data = JSON.parse(source.textContent), classes = data.classes || [];
      const canvas = document.createElement('canvas');
      canvas.width = 1080; canvas.height = 730 + Math.max(1, classes.length) * 64;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#f3f8fb'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      const gradient = ctx.createLinearGradient(0, 0, 1080, 190);
      gradient.addColorStop(0, '#123451'); gradient.addColorStop(1, '#0d9488');
      ctx.fillStyle = gradient; ctx.fillRect(0, 0, 1080, 190);
      ctx.fillStyle = '#c3f5e8'; ctx.font = 'bold 23px Arial'; ctx.fillText('CHĂM SÓC SỨC KHỎE HỌC SINH', 44, 48);
      ctx.fillStyle = '#ffffff'; ctx.font = 'bold 43px Arial'; ctx.fillText('TỔNG HỢP TUẦN', 44, 107);
      ctx.font = '27px Arial'; ctx.fillText(`${date(data.from)} – ${date(data.to)}`, 44, 153);
      function card(x, y, w, number, label, color) {
        ctx.fillStyle = '#ffffff'; ctx.fillRect(x, y, w, 108);
        ctx.fillStyle = color; ctx.font = 'bold 43px Arial'; ctx.fillText(String(number || 0), x + 22, y + 50);
        ctx.fillStyle = '#456277'; ctx.font = '24px Arial'; ctx.fillText(label, x + 22, y + 87);
      }
      card(40, 215, 490, data.total, 'Lượt chăm sóc', '#0f766e');
      card(550, 215, 490, data.students, 'Học sinh được chăm sóc', '#1d4ed8');
      const metrics = [['medicine', 'Phát thuốc', '#0f766e'], ['first_aid', 'Theo dõi tại phòng y tế', '#b45309'], ['hospital', 'Vào viện', '#be123c'], ['family_pickup', 'Gia đình đón về', '#6d28d9']];
      metrics.forEach(([key, label, color], i) => card(40 + (i % 2) * 510, 343 + Math.floor(i / 2) * 122, 490, data.types?.[key], label, color));
      ctx.fillStyle = '#173b53'; ctx.font = 'bold 27px Arial'; ctx.fillText('TỔNG HỢP THEO LỚP', 44, 624);
      const xs = [60, 365, 620, 895];
      ctx.fillStyle = '#173b53'; ctx.fillRect(40, 646, 1000, 58);
      ctx.fillStyle = '#ffffff'; ctx.font = 'bold 24px Arial';
      ['Lớp', 'Lượt', 'Học sinh', 'Vào viện'].forEach((text, i) => ctx.fillText(text, xs[i], 684));
      classes.forEach((row, i) => {
        const y = 704 + i * 64;
        ctx.fillStyle = i % 2 ? '#e8f2f6' : '#ffffff'; ctx.fillRect(40, y, 1000, 64);
        ctx.fillStyle = '#233b4e'; ctx.font = '26px Arial';
        [row.class, row.visits, row.students, row.hospital].forEach((value, j) => ctx.fillText(String(value ?? 0), xs[j], y + 41, j === 0 ? 280 : 200));
      });
      if (!classes.length) {ctx.fillStyle = '#456277';ctx.font = '25px Arial';ctx.fillText('Không có hồ sơ chăm sóc sức khỏe trong tuần.', 60, 745);}
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
      if (!blob) throw new Error('PNG generation failed');
      if (previousUrl) URL.revokeObjectURL(previousUrl);
      previousUrl = URL.createObjectURL(blob);
      const name = `suc-khoe-tuan-${data.from}.png`;
      const image = document.createElement('img'); image.src = previousUrl; image.alt = 'Ảnh tổng hợp sức khỏe học sinh theo tuần';
      const download = document.createElement('a'); download.href = previousUrl; download.download = name; download.textContent = 'Tải ảnh';
      const share = document.createElement('button'); share.type = 'button'; share.className = 'btn btn-primary'; share.textContent = 'Chia sẻ ảnh';
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
      preview.replaceChildren(hint, share, download, image); preview.hidden = false;
      preview.scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (error) {
      preview.hidden = false; preview.textContent = 'Không tạo được ảnh. Hãy tải lại trang và thử lại.'; console.error(error);
    } finally {button.disabled = false;}
  });
})();
