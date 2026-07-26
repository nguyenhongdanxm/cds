/* CSDL bulk select / export / delete */
function csdlGetChecked(entity) {
  return Array.from(document.querySelectorAll('.row-chk-' + entity + ':checked')).map(function (el) {
    return el.value;
  });
}
function csdlUpdateCount(entity) {
  var n = csdlGetChecked(entity).length;
  var el = document.getElementById('bulkCount-' + entity);
  if (el) el.textContent = n + ' đã chọn';
}
function csdlToggleAll(entity, on) {
  document.querySelectorAll('.row-chk-' + entity).forEach(function (el) {
    el.checked = on;
  });
  csdlUpdateCount(entity);
}
function csdlExportSelected(entity) {
  var ids = csdlGetChecked(entity);
  if (!ids.length) {
    alert('Hãy chọn ít nhất một dòng.');
    return;
  }
  var form = document.createElement('form');
  form.method = 'GET';
  form.action = (window.CSDL_BASE || '') + 'csdl_export.php';
  form.target = '_blank';
  function add(name, val) {
    var i = document.createElement('input');
    i.type = 'hidden';
    i.name = name;
    i.value = val;
    form.appendChild(i);
  }
  add('entity', entity);
  add('mode', 'export');
  ids.forEach(function (id) {
    add('ids[]', id);
  });
  document.body.appendChild(form);
  form.submit();
  form.remove();
}
function csdlDeleteSelected(entity) {
  var ids = csdlGetChecked(entity);
  if (!ids.length) {
    alert('Hãy chọn ít nhất một dòng.');
    return;
  }
  if (!confirm('Xóa ' + ids.length + ' mục đã chọn? Thao tác không hoàn tác.')) return;
  var box = document.getElementById('bulkIds-' + entity);
  var form = document.getElementById('bulkForm-' + entity);
  if (!box || !form) return;
  box.innerHTML = '';
  ids.forEach(function (id) {
    var i = document.createElement('input');
    i.type = 'hidden';
    i.name = 'ids[]';
    i.value = id;
    box.appendChild(i);
  });
  form.submit();
}
document.addEventListener('change', function (e) {
  if (e.target && e.target.classList && e.target.classList.contains('row-chk')) {
    var m = e.target.className.match(/row-chk-(\w+)/);
    if (m) csdlUpdateCount(m[1]);
  }
});
