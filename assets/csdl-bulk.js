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

/* Tab Lớp/khối: đổi Định mức thành Sĩ số và đếm học sinh đang học theo lớp. */
async function csdlRenderCurrentClassSizes() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('tab') !== 'classes') return;

  var table = document.querySelector('table.table-full');
  if (!table) return;

  var headers = Array.from(table.querySelectorAll('thead th'));
  var sizeIndex = headers.findIndex(function (th) {
    return th.textContent.trim().toLowerCase() === 'định mức';
  });
  if (sizeIndex < 0) return;

  headers[sizeIndex].textContent = 'Sĩ số';
  headers[sizeIndex].title = 'Số học sinh đang học hiện tại của lớp';

  try {
    var url = (window.CSDL_BASE || '') + 'csdl.php?tab=students&status=active';
    var response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok) throw new Error('HTTP ' + response.status);

    var html = await response.text();
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var studentTable = doc.querySelector('table.table-full');
    if (!studentTable) throw new Error('Không đọc được bảng học sinh');

    var studentHeaders = Array.from(studentTable.querySelectorAll('thead th'));
    var classIndex = studentHeaders.findIndex(function (th) {
      return th.textContent.trim().toLowerCase() === 'lớp';
    });
    if (classIndex < 0) throw new Error('Không tìm thấy cột lớp');

    var counts = Object.create(null);
    studentTable.querySelectorAll('tbody tr').forEach(function (tr) {
      var cells = tr.querySelectorAll('td');
      if (!cells.length || !cells[classIndex]) return;
      var className = cells[classIndex].textContent.trim();
      if (!className || className === '—') return;
      counts[className] = (counts[className] || 0) + 1;
    });

    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var cells = tr.querySelectorAll('td');
      if (!cells.length || !cells[2] || !cells[sizeIndex]) return;
      var className = cells[2].textContent.trim();
      cells[sizeIndex].textContent = String(counts[className] || 0);
      cells[sizeIndex].title = 'Số học sinh đang học hiện tại';
      cells[sizeIndex].style.fontWeight = '700';
    });
  } catch (err) {
    console.error('Không thể tải sĩ số lớp:', err);
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var cells = tr.querySelectorAll('td');
      if (cells[sizeIndex]) {
        cells[sizeIndex].textContent = '—';
        cells[sizeIndex].title = 'Không tải được sĩ số hiện tại';
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', csdlRenderCurrentClassSizes);
