(function () {
  'use strict';
  var panel = document.getElementById('sideAi');
  if (!panel) return;
  if (panel.dataset.open === '1') document.querySelector('[data-side="ai"]')?.click();
  var form = document.getElementById('editorAiCommandForm');
  document.getElementById('editorAiCheck')?.addEventListener('click', function () {
    document.getElementById('editorAiCommand').value = 'Kiểm tra lỗi TKB';
    form.requestSubmit();
  });
  [form, document.getElementById('editorAiApplyForm')].forEach(function (item) {
    item?.addEventListener('submit', function () {
      item.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
      document.getElementById('editorAiProgress').textContent = item === form
        ? 'Đang hiểu yêu cầu và kiểm tra lịch. Chưa áp dụng thay đổi…'
        : 'Đang kiểm tra lại và lưu bản nháp…';
    });
  });
})();
