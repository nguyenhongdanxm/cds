(function () {
  'use strict';

  var overlay;
  var bar;
  var percent;
  var title;
  var detail;
  var timer;

  function createOverlay() {
    if (overlay) return overlay;
    overlay = document.createElement('div');
    overlay.className = 'cds-action-progress';
    overlay.id = 'cdsActionProgress';
    overlay.hidden = true;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.setAttribute('aria-label', 'Đang xử lý yêu cầu');
    overlay.innerHTML =
      '<div class="cds-action-progress__card">' +
        '<div class="cds-action-progress__head">' +
          '<span class="cds-action-progress__spinner" aria-hidden="true"></span>' +
          '<div><p class="cds-action-progress__title">Đang xử lý</p>' +
          '<p class="cds-action-progress__detail">Vui lòng giữ nguyên trang cho đến khi hoàn tất.</p></div>' +
        '</div>' +
        '<div class="cds-action-progress__track" aria-hidden="true"><div class="cds-action-progress__bar"></div></div>' +
        '<div class="cds-action-progress__meta"><span>Hệ thống đang thực hiện yêu cầu</span><span class="cds-action-progress__percent">5%</span></div>' +
      '</div>';
    document.body.appendChild(overlay);
    bar = overlay.querySelector('.cds-action-progress__bar');
    percent = overlay.querySelector('.cds-action-progress__percent');
    title = overlay.querySelector('.cds-action-progress__title');
    detail = overlay.querySelector('.cds-action-progress__detail');
    return overlay;
  }

  function actionInfo(form, submitter) {
    var fileSelected = Array.prototype.some.call(
      form.querySelectorAll('input[type="file"]'),
      function (input) { return input.files && input.files.length > 0; }
    );
    if (fileSelected) {
      return { title: 'Đang tải tệp', detail: 'Tệp đang được tải lên và kiểm tra. Vui lòng không đóng trang.' };
    }

    var words = ((submitter && (submitter.textContent || submitter.value)) || '') + ' ' + (form.action || '');
    if (/xử\s*lý|đề|xuất|tạo|in\b|đồng\s*bộ|nhập|gộp|tính/i.test(words)) {
      return { title: 'Đang xử lý dữ liệu', detail: 'Hệ thống đang thực hiện yêu cầu. Vui lòng giữ nguyên trang.' };
    }
    return { title: 'Đang lưu dữ liệu', detail: 'Thay đổi đang được ghi nhận an toàn vào hệ thống.' };
  }

  function showProgress(info) {
    createOverlay();
    window.clearInterval(timer);
    title.textContent = info.title;
    detail.textContent = info.detail;
    var value = 5;
    bar.style.width = value + '%';
    percent.textContent = value + '%';
    overlay.hidden = false;
    document.documentElement.setAttribute('aria-busy', 'true');

    timer = window.setInterval(function () {
      if (value >= 92) return;
      var step = value < 45 ? 7 : (value < 75 ? 3 : 1);
      value = Math.min(92, value + step);
      bar.style.width = value + '%';
      percent.textContent = value + '%';
    }, 420);
  }

  function hideProgress() {
    window.clearInterval(timer);
    if (overlay) overlay.hidden = true;
    document.documentElement.removeAttribute('aria-busy');
  }

  function hasOwnProgress(form) {
    if (form.matches('[data-cds-progress="off"], .cds-no-global-progress')) return true;
    if (form.closest('[data-cds-progress="off"], .cds-no-global-progress')) return true;
    return Boolean(
      document.querySelector('#progressOverlay, #ntxUploadOverlay, .ntx-upload-overlay') ||
      form.closest('#progressOverlay, #ntxUploadOverlay, .ntx-upload-overlay') ||
      form.querySelector('[data-progress], progress, .progress-bar')
    );
  }

  function isEligible(event, form) {
    if (event.defaultPrevented || !form || !document.body.contains(form)) return false;
    if ((form.method || 'get').toLowerCase() !== 'post') return false;
    if ((form.target || '').toLowerCase() === '_blank') return false;
    if (hasOwnProgress(form)) return false;
    if (typeof form.checkValidity === 'function' && !form.checkValidity()) return false;
    return true;
  }

  function successMessage() {
    var success = document.querySelector('.alert-success, [data-cds-success]');
    if (!success) return;
    var message = (success.textContent || '').replace(/\s+/g, ' ').trim();
    if (!message) return;

    if (!success.querySelector('.bi-check-circle-fill, .cds-success-icon')) {
      var icon = document.createElement('span');
      icon.className = 'cds-success-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = '✓ ';
      success.insertBefore(icon, success.firstChild);
    }

    var pending = null;
    try { pending = JSON.parse(sessionStorage.getItem('cdsPendingAction') || 'null'); } catch (ignore) {}
    if (!pending || Date.now() - pending.time > 120000) return;
    sessionStorage.removeItem('cdsPendingAction');

    var toast = document.createElement('div');
    toast.className = 'cds-result-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML = '<span class="cds-result-toast__icon" aria-hidden="true">✓</span>' +
      '<span class="cds-result-toast__text"><strong>Hoàn tất 100%</strong></span>';
    toast.querySelector('.cds-result-toast__text').appendChild(document.createTextNode(message));
    document.body.appendChild(toast);
    window.setTimeout(function () { toast.remove(); }, 4200);
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !isEligible(event, form)) return;
    var submitter = event.submitter || document.activeElement;
    try {
      sessionStorage.setItem('cdsPendingAction', JSON.stringify({ time: Date.now() }));
    } catch (ignore) {}
    showProgress(actionInfo(form, submitter));
  });

  window.addEventListener('pageshow', hideProgress);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', successMessage);
  } else {
    successMessage();
  }
})();
