document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('healthQrDialog');
  if (!dialog) return;
  const el = id => document.getElementById(id);
  let scanner, running = false, busy = false, session = 0;
  const status = message => { el('healthQrStatus').textContent = message; };
  async function stop() {
    if (scanner && running) { running = false; try { await scanner.stop(); } catch (_) {} }
  }
  async function recognize(text, ticket) {
    if (busy || ticket !== session || !dialog.open) return;
    busy = true;
    await stop();
    status('Đang nhận diện học sinh…');
    try {
      const url = new URL(location.href);
      url.search = ''; url.hash = '';
      url.searchParams.set('tab', 'health'); url.searchParams.set('health_qr', text);
      const response = await fetch(url, {credentials:'same-origin', headers:{Accept:'application/json'}});
      const data = await response.json();
      if (ticket !== session || !dialog.open) return;
      if (!response.ok || !data.student) throw new Error(data.error || 'Không nhận diện được thẻ học sinh.');
      const student = data.student;
      el('healthQrName').textContent = student.name;
      el('healthQrClass').textContent = [student.class_name, student.code].filter(Boolean).join(' · ');
      const link = view => {
        const target = new URL(location.href); target.search = ''; target.hash = '';
        target.searchParams.set('tab','health'); target.searchParams.set('health_view',view);
        target.searchParams.set('student_id',student.id);
        if (view === 'history') target.searchParams.set('range','all');
        return target.href;
      };
      el('healthQrRecord').href = link('record');
      el('healthQrRecord').hidden = !data.can_record;
      el('healthQrHistory').href = link('history');
      el('healthQrResult').hidden = false;
      status('Đã nhận diện học sinh. Chọn thao tác bên dưới.');
    } catch (error) {
      if (ticket === session && dialog.open) status(error instanceof SyntaxError ? 'Phiên đăng nhập đã hết hạn hoặc máy chủ chưa phản hồi. Vui lòng tải lại trang.' : error.message);
    } finally { busy = false; }
  }
  async function start() {
    if (busy || running) return;
    const ticket = ++session;
    busy = true; el('healthQrResult').hidden = true;
    try {
      if (typeof Html5Qrcode === 'undefined') throw new Error('Chưa tải được bộ quét QR. Vui lòng kiểm tra kết nối và tải lại trang.');
      if (!window.isSecureContext) throw new Error('Camera cần mở trang bằng HTTPS. Bạn có thể chọn ảnh mã QR bên dưới.');
      scanner ||= new Html5Qrcode('healthQrReader', {formatsToSupport:[Html5QrcodeSupportedFormats.QR_CODE]});
      status('Đang mở camera…');
      await scanner.start({facingMode:'environment'}, {fps:10, qrbox:(w,h)=>({width:Math.min(w,h,280)*0.8,height:Math.min(w,h,280)*0.8})}, text => { if (!busy) recognize(text,ticket); }, () => {});
      running = true;
      if (ticket !== session || !dialog.open) await stop();
      else status('Đưa mã QR trên thẻ học sinh vào khung camera.');
    } catch (error) { if (ticket === session && dialog.open) status(error.name === 'Error' ? error.message : 'Không mở được camera. Hãy cấp quyền camera, hoặc chọn ảnh mã QR bên dưới.'); }
    finally { busy = false; }
  }
  el('healthQrOpen').addEventListener('click', () => { dialog.showModal(); start(); });
  el('healthQrStart').addEventListener('click', start);
  el('healthQrClose').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { ++session; stop(); });
  el('healthQrFile').addEventListener('change', async event => {
    const file = event.target.files[0]; event.target.value = '';
    if (!file || busy) return;
    const ticket = ++session; busy = true; el('healthQrResult').hidden = true;
    try {
      await stop();
      if (typeof Html5Qrcode === 'undefined') throw new Error('Chưa tải được bộ quét QR. Vui lòng tải lại trang.');
      scanner ||= new Html5Qrcode('healthQrReader', {formatsToSupport:[Html5QrcodeSupportedFormats.QR_CODE]});
      const text = await scanner.scanFile(file, true);
      busy = false;
      await recognize(text,ticket);
    } catch (_) { if (ticket === session && dialog.open) status('Không đọc được QR trong ảnh. Hãy chọn ảnh rõ toàn bộ mã QR trên thẻ.'); }
    finally { busy = false; }
  });
  window.addEventListener('pagehide', () => { ++session; stop(); });
});
