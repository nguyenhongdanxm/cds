(function () {
  'use strict';
  function prepare() {
    document.querySelectorAll('[draggable="true"]').forEach(function (element) { element.draggable = false; });
    document.querySelectorAll('form').forEach(function (form) {
      if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
      // Acknowledge only this account's login announcement; business forms remain disabled.
      var action = new URL(form.getAttribute('action') || window.location.href, window.location.href);
      if (form.id === 'cdsLnForm' && action.origin === window.location.origin && /\/login_notice\.php$/.test(action.pathname)) return;
      form.addEventListener('submit', function (event) { event.preventDefault(); }, true);
      form.querySelectorAll('input, select, textarea, button').forEach(function (field) {
        field.disabled = true;
        field.title = 'Tài khoản trải nghiệm chỉ được xem';
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', prepare);
  else prepare();
})();

