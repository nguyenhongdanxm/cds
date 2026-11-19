(function () {
  const dialog = document.getElementById('cdsLoginNotice');
  if (!dialog) return;
  const form = document.getElementById('cdsLnForm');
  const error = form.querySelector('.cds-ln-error');
  const buttons = form.querySelectorAll('button');
  const gate = form.elements.context.value === 'gate';
  let busy = false;
  function finish(next) {
    if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
    if (gate && next) window.location.assign(next);
  }
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (busy) return;
    busy = true; error.hidden = true;
    buttons.forEach(button => { button.disabled = true; });
    try {
      const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: new FormData(form) });
      const result = await response.json();
      if (response.status === 409 || response.status === 403) {
        error.textContent = result.message || 'Phiên làm việc đã thay đổi.';
        const reload = document.createElement('button'); reload.type = 'button'; reload.textContent = 'Tải lại trang'; reload.onclick = () => window.location.reload();
        error.append(' ', reload); error.hidden = false; return;
      }
      if (!response.ok || !result.ok) throw new Error(result.message || 'Chưa lưu được trạng thái đóng. Vui lòng thử lại.');
      finish(result.next);
    } catch (e) {
      error.textContent = 'Chưa ghi nhận được việc đóng thông báo. Kiểm tra kết nối rồi bấm Đóng để thử lại.';
      error.hidden = false;
    } finally {
      busy = false; buttons.forEach(button => { button.disabled = false; });
    }
  });
  dialog.addEventListener('cancel', function (event) { event.preventDefault(); form.requestSubmit(); });
  if (typeof dialog.showModal === 'function') dialog.showModal();
  else dialog.setAttribute('open', '');
  const expires = Number(dialog.dataset.expires) * 1000;
  if (expires) {
    const check = function () {
      if (Date.now() >= expires && !busy) {
        // Expiration hides the dialog without falsely recording a manual dismissal.
        if (gate) window.location.reload(); else if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
      }
    };
    check(); setInterval(check, 15000);
  }
})();
