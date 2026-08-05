<?php
/**
 * Bộ lọc lịch trực theo một người.
 *
 * noitru_duty_view.php cũ lọc trực tiếp từng dòng nên làm mất tên những
 * người trực cùng. Tiện ích này giữ nguyên toàn bộ ca trực ở phía máy chủ,
 * rồi chỉ ẩn các ngày không có người được chọn ở phía giao diện.
 */

if (!defined('DATA_PATH') || !function_exists('current_user')) return;

$script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($script !== 'noitru.php' || ($_GET['tab'] ?? '') !== 'duty' || ($_GET['section'] ?? 'calendar') !== 'calendar') return;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;

$selectedTeacherId = trim((string)($_GET['teacher'] ?? ''));
if ($selectedTeacherId === '') return;

require_once __DIR__ . '/csdl_store.php';
$selectedTeacherName = '';
foreach (csdl_teachers_all() as $teacher) {
    if ((string)($teacher['id'] ?? '') !== $selectedTeacherId) continue;
    $selectedTeacherName = trim((string)($teacher['name'] ?? ''));
    break;
}
if ($selectedTeacherName === '') return;

// Để giao diện gốc dựng đủ tên tất cả người trực cùng trong từng ngày.
unset($_GET['teacher']);

ob_start(function ($html) use ($selectedTeacherId, $selectedTeacherName) {
    $teacherIdJson = json_encode($selectedTeacherId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $teacherNameJson = json_encode($selectedTeacherName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $script = <<<'HTML'
<script>
(function () {
  var selectedId = __TEACHER_ID__;
  var selectedName = __TEACHER_NAME__;
  var normalize = function (value) {
    return String(value || '').normalize('NFC').trim().toLocaleLowerCase('vi');
  };
  var target = normalize(selectedName);
  var select = document.querySelector('select[name="teacher"]');
  if (select) select.value = selectedId;

  var calendar = document.querySelector('.duty-calendar');
  var visible = 0;
  if (calendar) {
    calendar.querySelectorAll('.duty-calendar-blank').forEach(function (blank) { blank.remove(); });
    calendar.querySelectorAll('.duty-day').forEach(function (day) {
      var names = Array.from(day.querySelectorAll('.duty-day-person span')).map(function (node) {
        return normalize(node.textContent);
      });
      var matched = names.indexOf(target) !== -1;
      day.hidden = !matched;
      if (matched) visible++;
    });
  }

  document.querySelectorAll('.duty-month-nav a').forEach(function (link) {
    try {
      var url = new URL(link.href, window.location.href);
      url.searchParams.set('teacher', selectedId);
      link.href = url.toString();
    } catch (error) {}
  });

  var todayLink = document.querySelector('.duty-toolbar-actions a.btn-outline-info');
  if (todayLink) {
    try {
      var todayUrl = new URL(todayLink.href, window.location.href);
      todayUrl.searchParams.set('teacher', selectedId);
      todayLink.href = todayUrl.toString();
    } catch (error) {}
  }

  if (calendar && visible === 0) {
    var message = document.createElement('div');
    message.className = 'alert alert-info mb-0';
    message.style.gridColumn = '1 / -1';
    message.innerHTML = '<i class="bi bi-info-circle me-1"></i><strong></strong> không có lịch trực trong tháng này.';
    message.querySelector('strong').textContent = selectedName;
    calendar.appendChild(message);
  }
})();
</script>
HTML;
    $script = str_replace(['__TEACHER_ID__', '__TEACHER_NAME__'], [$teacherIdJson, $teacherNameJson], $script);
    if (stripos($html, '</body>') !== false) return preg_replace('/<\/body>/i', $script . '</body>', $html, 1);
    return $html . $script;
});
