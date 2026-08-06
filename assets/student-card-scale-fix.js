document.addEventListener('DOMContentLoaded', function () {
  var preview = document.getElementById('preview');
  if (!preview) return;

  function applyScale() {
    preview.querySelectorAll('.student-card .sc-item').forEach(function (item) {
      if (item.dataset.printScale === 'done') return;
      ['left', 'top', 'width', 'height', 'fontSize'].forEach(function (property) {
        var value = parseFloat(item.style[property]);
        if (Number.isFinite(value)) item.style[property] = (value * 3.78) + 'px';
      });
      item.dataset.printScale = 'done';
    });
  }

  applyScale();
  new MutationObserver(applyScale).observe(preview, { childList: true, subtree: true });
});
