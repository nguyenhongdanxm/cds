document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('healthQrDialog');
  if (!dialog) return;
  const el = id => document.getElementById(id);
  let scanner, running = false, busy = false, session = 0, torch = false;
  let controlsQueue = Promise.resolve();
  const status = message => { el('healthQrStatus').textContent = message; };
  const createScanner = () => new Html5Qrcode('healthQrReader', {formatsToSupport:[Html5QrcodeSupportedFormats.QR_CODE], experimentalFeatures:{useBarCodeDetectorIfSupported:true}});
  async function tuneCamera(ticket) {
    if (!running || ticket !== session) return;
    let caps = {};
    try { caps = scanner.getRunningTrackCapabilities(); } catch (_) {}
    for (const key of ['focusMode', 'exposureMode', 'whiteBalanceMode']) {
      if (Array.isArray(caps[key]) && caps[key].includes('continuous')) {
        try { await scanner.applyVideoConstraints({advanced:[{[key]:'continuous'}]}); } catch (_) {}
        if (!running || ticket !== session) return;
      }
    }
    const zoom = el('healthQrZoom');
    el('healthQrZoomArea').hidden = !(caps.zoom && caps.zoom.max > caps.zoom.min);
    if (caps.zoom && caps.zoom.max > caps.zoom.min) {
      zoom.min = caps.zoom.min; zoom.max = Math.max(caps.zoom.min, Math.min(caps.zoom.max, 6));
      zoom.step = caps.zoom.step || 0.1;
      const desired = Math.min(Number(zoom.max), Math.max(Number(zoom.min), 1.75));
      const step = Number(zoom.step);
      const initialZoom = Math.min(Number(zoom.max), Math.max(Number(zoom.min), Number((Number(zoom.min) + Math.round((desired - Number(zoom.min)) / step) * step).toFixed(3))));
      try { await scanner.applyVideoConstraints({advanced:[{zoom:initialZoom}]}); } catch (_) {}
      if (!running || ticket !== session) return;
      const settings = scanner.getRunningTrackSettings();
      zoom.value = Math.min(Number(zoom.max), Math.max(Number(zoom.min), settings.zoom ?? caps.zoom.min));
      el('healthQrZoomValue').textContent = Number(zoom.value).toFixed(1)+'×';
    }
    torch = false; el('healthQrTorch').hidden = !caps.torch;
    el('healthQrTorch').textContent = 'Bật đèn';
    el('healthQrTorch').setAttribute('aria-pressed','false');

  }
  function adjust(constraints, done) {
    const ticket = session;
    controlsQueue = controlsQueue.then(async () => {
      if (!running || ticket !== session) return;
      try { await scanner.applyVideoConstraints({advanced:[constraints]}); if (running && ticket === session) done(); }
      catch (_) { if (running && ticket === session) status('Camera không hỗ trợ điều chỉnh này. Hãy giữ thẻ đủ xa để camera lấy nét.'); }
    });
  }
  async function stop() {
    ['healthQrZoomArea','healthQrTorch'].forEach(id => { el(id).hidden = true; });
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
      scanner ||= createScanner();
      status('Đang mở camera…');
      const camera = {facingMode:{ideal:'environment'}};
      const onScan = text => { if (!busy) recognize(text,ticket); };
      // Scan the entire frame: a small shaded box used to discard codes near its edges.
      try {
        await scanner.start(camera, {fps:15, videoConstraints:{...camera,width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}}}, onScan, () => {});
      } catch (error) {
        if (ticket !== session || !dialog.open) throw error;
        await scanner.start(camera, {fps:12}, onScan, () => {});
      }
      running = true;
      if (ticket !== session || !dialog.open) await stop();
      else { await tuneCamera(ticket); if (ticket === session && dialog.open) status('Giữ thẻ đủ xa để camera lấy nét. Có thể zoom hoặc bật đèn nếu cần.'); }
    } catch (error) { if (ticket === session && dialog.open) status(error.name === 'Error' ? error.message : 'Không mở được camera. Hãy cấp quyền camera, hoặc chọn ảnh mã QR bên dưới.'); }
    finally { busy = false; }
  }
  el('healthQrOpen').addEventListener('click', () => { dialog.showModal(); start(); });
  el('healthQrStart').addEventListener('click', () => start());
  el('healthQrZoom').addEventListener('input', event => {
    const zoom = Number(event.target.value);
    adjust({zoom}, () => { el('healthQrZoomValue').textContent = zoom.toFixed(1)+'×'; });
  });
  el('healthQrTorch').addEventListener('click', () => {
    const next = !torch;
    adjust({torch:next}, () => { torch = next; el('healthQrTorch').textContent = torch ? 'Tắt đèn' : 'Bật đèn'; el('healthQrTorch').setAttribute('aria-pressed',String(torch)); });
  });
  el('healthQrClose').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { ++session; stop(); });
  el('healthQrFile').addEventListener('change', async event => {
    const file = event.target.files[0]; event.target.value = '';
    if (!file || busy) return;
    const ticket = ++session; busy = true; el('healthQrResult').hidden = true;
    try {
      await stop();
      if (typeof Html5Qrcode === 'undefined') throw new Error('Chưa tải được bộ quét QR. Vui lòng tải lại trang.');
      scanner ||= createScanner();
      const text = await scanner.scanFile(file, true);
      busy = false;
      await recognize(text,ticket);
    } catch (_) { if (ticket === session && dialog.open) status('Không đọc được QR trong ảnh. Hãy chọn ảnh rõ toàn bộ mã QR trên thẻ.'); }
    finally { busy = false; }
  });
  window.addEventListener('pagehide', () => { ++session; stop(); });
});
