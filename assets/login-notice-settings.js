(function () {
  const form = document.getElementById('lnSettingsForm');
  const textarea = document.getElementById('lnBody');
  const status = document.getElementById('lnEditorStatus');
  const preview = document.getElementById('lnPreview');
  const css = new URL('assets/login-notice.css?v=20261119-1', window.location.href).href;
  const esc = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function content() { return window.tinymce && tinymce.get('lnBody') && tinymce.get('lnBody').initialized ? tinymce.get('lnBody').getContent() : esc(textarea.value).replace(/\n/g, '<br>'); }
  function render() {
    // Sandboxed frame isolates both editor markup and its links from the settings page.
    const theme = ['blue', 'green', 'amber', 'violet'].includes(form.elements.theme.value) ? form.elements.theme.value : 'blue';
    preview.srcdoc = '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="' + esc(css) + '"><style>body{margin:0;padding:20px 8px;background:#edf3f9}article.cds-ln-dialog{display:block;width:100%;max-height:none;margin:auto;box-sizing:border-box;border-radius:18px}.cds-ln-body{max-height:300px}</style><article class="cds-ln-dialog cds-ln-' + theme + '"><div class="cds-ln-hero"><span class="cds-ln-icon">' + esc(form.elements.icon.value) + '</span><span class="cds-ln-kicker">THÔNG BÁO NHÀ TRƯỜNG</span><h2>' + esc(form.elements.title.value || 'Tiêu đề thông báo') + '</h2></div><div class="cds-ln-body">' + (content() || '<p>Nội dung thông báo sẽ hiển thị tại đây.</p>') + '</div><footer class="cds-ln-footer"><small>Đóng để không hiện lại thông báo này.</small><button class="cds-ln-close" type="button">Đã hiểu · Đóng</button></footer></article></html>';
  }
  if (window.tinymce) {
    tinymce.init({
      selector: '#lnBody', height: 360, menubar: 'edit insert format table', promotion: false,
      plugins: 'lists link charmap table searchreplace wordcount',
      toolbar: 'undo redo | blocks fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link table charmap | removeformat',
      toolbar_mode: 'sliding', paste_data_images: false,
      valid_elements: 'p[style],div[style],span[style],br,strong,b,em,i,u,s,sub,sup,h2[style],h3[style],blockquote,ul,ol,li,a[href],table,thead,tbody,tr,td[colspan|rowspan|style],th[colspan|rowspan|style]',
      content_style: 'body{font:16px/1.6 Arial,sans-serif;color:#21334d}',
      setup: function (editor) {
        editor.on('init', function () { status.textContent = 'Bộ soạn thảo đã sẵn sàng.'; render(); });
        editor.on('input change undo redo', render);
      }
    }).catch(function () { status.textContent = 'Chưa tải được bộ soạn thảo. Có thể nhập văn bản và lưu, hoặc tải lại trang để dùng định dạng.'; });
  } else status.textContent = 'Chưa tải được bộ soạn thảo. Có thể nhập văn bản và lưu, hoặc tải lại trang để dùng định dạng.';
  form.addEventListener('input', render);
  document.querySelectorAll('[data-ln-emoji]').forEach(button => button.addEventListener('click', function () {
    const editor = window.tinymce && tinymce.get('lnBody');
    if (editor && editor.initialized) editor.insertContent(esc(button.dataset.lnEmoji));
    else { textarea.setRangeText(button.dataset.lnEmoji, textarea.selectionStart, textarea.selectionEnd, 'end'); textarea.focus(); }
    render();
  }));
  form.addEventListener('submit', function () {
    if (window.tinymce && tinymce.get('lnBody') && tinymce.get('lnBody').initialized) tinymce.get('lnBody').save();
    document.getElementById('lnSave').disabled = true;
  });
  render();
})();
