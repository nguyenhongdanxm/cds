<?php
/**
 * Trung tâm khởi tạo một bản CDS mới.
 *
 * Trang này chỉ đọc trạng thái và dẫn tới các chức năng đã có. Không chạy
 * migration, không nhập dữ liệu và không ghi đè bất kỳ tệp vận hành nào.
 */
define('CDS_SKIP_DRIVE_ACTION_REGISTRATION', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/database_migrations.php';
require_admin();

function setup_rows(string $path): array
{
    $rows = load_json($path, []);
    return is_array($rows) ? $rows : [];
}

function setup_count_rows(string $path): int
{
    return count(setup_rows($path));
}

function setup_status(string $state): array
{
    $catalog = [
        'done' => ['Hoàn thành', 'success', 'bi-check-circle-fill'],
        'warning' => ['Cần kiểm tra', 'warning', 'bi-exclamation-triangle-fill'],
        'pending' => ['Chưa thực hiện', 'secondary', 'bi-circle'],
        'optional' => ['Tùy chọn', 'info', 'bi-info-circle-fill'],
    ];
    return $catalog[$state] ?? $catalog['pending'];
}

$instancePath = function_exists('cds_instance_config_path') ? cds_instance_config_path() : '';
$instanceReady = $instancePath !== '' && is_file($instancePath) && is_readable($instancePath);

$dbStatus = cds_db_status();
$migrationPending = null;
if (!empty($dbStatus['connected'])) {
    try {
        $migrationPending = count(cds_db_migration_status()['pending']);
    } catch (Throwable $e) {
        $dbStatus['error'] = $e->getMessage();
    }
}

$years = setup_rows(DATA_PATH . '/school_years.json');
$teachersCount = setup_count_rows(DATA_PATH . '/teachers.json');
$classesCount = setup_count_rows(DATA_PATH . '/classes.json');
$studentsCount = setup_count_rows(DATA_PATH . '/students.json');
$users = setup_rows(USERS_FILE);
$staffUsers = array_values(array_filter($users, static function ($row) {
    return is_array($row) && ($row['role'] ?? '') !== 'admin';
}));
$defaultAdminPassword = false;
foreach ($users as $account) {
    if (!is_array($account) || ($account['role'] ?? '') !== 'admin') continue;
    $hash = (string)($account['password_hash'] ?? '');
    if ($hash !== '' && password_verify(DEFAULT_ADMIN_PASS, $hash)) {
        $defaultAdminPassword = true;
        break;
    }
}

$currentYear = '';
foreach ($years as $year) {
    if (is_array($year) && !empty($year['is_current'])) {
        $currentYear = (string)($year['label'] ?? $year['id'] ?? '');
        break;
    }
}

$cmPath = defined('PCCM_DATA_PATH') && trim((string)PCCM_DATA_PATH) !== ''
    ? rtrim((string)PCCM_DATA_PATH, '/\\')
    : (__DIR__ . '/chuyenmon/data');
$cmSubjects = setup_count_rows($cmPath . '/subjects.json');
$cmGroups = setup_count_rows($cmPath . '/groups.json');
$cmRoles = setup_count_rows($cmPath . '/roles.json');
$cmReady = $cmSubjects > 0 && $cmGroups > 0;
$chuyenmonEnabled = function_exists('school_module_enabled')
    ? school_module_enabled('chuyenmon')
    : !empty(cds_school_config('modules.chuyenmon', true));

$drive = cds_drive_settings();
$driveEnabled = !empty($drive['enabled']);
$driveFolders = 0;
foreach ((array)($drive['types'] ?? []) as $type) {
    if (is_array($type) && trim((string)($type['folder_id'] ?? '')) !== '') $driveFolders++;
}

$steps = [
    [
        'number' => 1,
        'title' => 'Cấu hình trường',
        'description' => 'Nhận diện trường, cấp học, module, MySQL, Drive và thư mục triển khai.',
        'state' => $instanceReady ? 'done' : 'pending',
        'detail' => $instanceReady ? 'Đã có bộ cấu hình riêng' : 'Chưa có instance.json riêng',
        'url' => BASE_URL . 'instance_settings.php',
        'action' => $instanceReady ? 'Kiểm tra cấu hình' : 'Mở cấu hình',
    ],
    [
        'number' => 2,
        'title' => 'Nền MySQL',
        'description' => 'Kiểm tra kết nối và chỉ chạy các migration còn thiếu tại trang quản trị MySQL.',
        'state' => !empty($dbStatus['connected']) && $migrationPending === 0 ? 'done' : 'warning',
        'detail' => !empty($dbStatus['connected'])
            ? ($migrationPending === null ? 'Không đọc được trạng thái migration' : ($migrationPending . ' migration đang chờ'))
            : 'Chưa kết nối được MySQL',
        'url' => BASE_URL . 'database_admin.php',
        'action' => 'Mở quản trị MySQL',
    ],
    [
        'number' => 3,
        'title' => 'Năm học hiện hành',
        'description' => 'Tạo năm học, khai báo ngày bắt đầu/kết thúc và đặt làm năm học hiện hành.',
        'state' => $years && $currentYear !== '' ? 'done' : 'pending',
        'detail' => $currentYear !== '' ? $currentYear : (count($years) . ' năm học, chưa chọn hiện hành'),
        'url' => BASE_URL . 'csdl.php?tab=years',
        'action' => 'Quản lý năm học',
    ],
    [
        'number' => 4,
        'title' => 'Giáo viên, cán bộ, nhân viên',
        'description' => 'Tải mẫu CSV, điền đúng cột rồi chọn Nhập & gộp. Dữ liệu đã có không bị xóa hàng loạt.',
        'state' => $teachersCount > 0 ? 'done' : 'pending',
        'detail' => number_format($teachersCount, 0, ',', '.') . ' người',
        'url' => BASE_URL . 'csdl.php?tab=teachers',
        'action' => 'Nạp giáo viên',
    ],
    [
        'number' => 5,
        'title' => 'Lớp học',
        'description' => 'Nạp lớp sau giáo viên để có thể đối chiếu giáo viên chủ nhiệm.',
        'state' => $classesCount > 0 ? 'done' : 'pending',
        'detail' => number_format($classesCount, 0, ',', '.') . ' lớp',
        'url' => BASE_URL . 'csdl.php?tab=classes',
        'action' => 'Nạp lớp',
    ],
    [
        'number' => 6,
        'title' => 'Học sinh',
        'description' => 'Nạp học sinh sau khi đã có lớp; mã lớp trong tệp nhập phải khớp lớp đã tạo.',
        'state' => $studentsCount > 0 ? 'done' : 'pending',
        'detail' => number_format($studentsCount, 0, ',', '.') . ' học sinh',
        'url' => BASE_URL . 'csdl.php?tab=students',
        'action' => 'Nạp học sinh',
    ],
    [
        'number' => 7,
        'title' => 'Danh mục Chuyên môn',
        'description' => 'Khai báo tổ chuyên môn, môn học, số tiết và nhiệm vụ kiêm nhiệm trước khi phân công.',
        'state' => !$chuyenmonEnabled ? 'optional' : ($cmReady ? 'done' : 'pending'),
        'detail' => !$chuyenmonEnabled
            ? 'Module Chuyên môn đang tắt'
            : ($cmGroups . ' tổ · ' . $cmSubjects . ' môn · ' . $cmRoles . ' nhiệm vụ'),
        'url' => BASE_URL . 'chuyenmon/monhoc.php',
        'action' => 'Mở Chuyên môn',
    ],
    [
        'number' => 8,
        'title' => 'Tài khoản và phân quyền',
        'description' => 'Load tài khoản từ CSDL giáo viên, kiểm tra nhóm quyền và đổi mật khẩu quản trị mặc định.',
        'state' => count($staffUsers) > 0 && !$defaultAdminPassword ? 'done' : 'warning',
        'detail' => count($staffUsers) . ' tài khoản nhân sự' . ($defaultAdminPassword ? ' · còn mật khẩu quản trị mặc định' : ''),
        'url' => BASE_URL . 'users.php',
        'action' => 'Tạo và phân quyền',
    ],
    [
        'number' => 9,
        'title' => 'Google Drive',
        'description' => 'Tùy chọn: kết nối tài khoản và kiểm tra quyền ghi của từng thư mục lưu trữ.',
        'state' => $driveEnabled && $driveFolders > 0 ? 'done' : 'optional',
        'detail' => $driveEnabled ? ($driveFolders . ' loại đã có thư mục') : 'Chưa bật — không cản trở CDS vận hành',
        'url' => BASE_URL . 'admin.php?view=drive',
        'action' => 'Kiểm tra Drive',
    ],
];

$requiredSteps = array_values(array_filter($steps, static fn($step) => $step['state'] !== 'optional'));
$completedSteps = count(array_filter($requiredSteps, static fn($step) => $step['state'] === 'done'));
$ready = $requiredSteps && $completedSteps === count($requiredSteps);
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Khởi tạo và nạp dữ liệu – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f4f7fb;color:#18324a}.setup-hero{border:0;border-radius:18px;background:linear-gradient(135deg,#123f67,#1f78aa);color:#fff;box-shadow:0 12px 32px rgba(22,72,111,.18)}.setup-card{border:0;border-radius:16px;box-shadow:0 4px 18px rgba(20,50,75,.07)}.step-number{width:2.5rem;height:2.5rem;border-radius:50%;display:inline-grid;place-items:center;background:#eaf3fa;color:#176a9b;font-weight:800;flex:0 0 auto}.step-row{display:grid;grid-template-columns:auto 1fr auto;gap:1rem;align-items:center;padding:1.1rem 0;border-bottom:1px solid #e9eef2}.step-row:last-child{border-bottom:0}.step-copy p{margin:.25rem 0;color:#66788a;font-size:.92rem}.step-copy small{color:#40576b}.safe-note{border-left:5px solid #198754}@media(max-width:767px){.step-row{grid-template-columns:auto 1fr}.step-action{grid-column:1/-1}.step-action .btn{width:100%}}
</style>
</head>
<body>
<?php
$nav_title = 'Khởi tạo dữ liệu';
$nav_icon = 'bi-list-check';
$nav_color = '#176a9b';
$nav_module = 'admin';
include __DIR__ . '/includes/nav_top.php';
?>
<main class="container pb-5" style="max-width:1100px">
  <section class="setup-hero p-4 p-lg-5 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <span class="badge text-bg-light text-primary mb-2">TRUNG TÂM THIẾT LẬP</span>
        <h2 class="mb-2">Khởi tạo và nạp dữ liệu</h2>
        <p class="mb-0 opacity-75">Thực hiện tuần tự từ trên xuống dưới khi triển khai CDS cho một đơn vị mới.</p>
      </div>
      <div class="text-lg-end">
        <div class="fs-2 fw-bold"><?= $completedSteps ?>/<?= count($requiredSteps) ?></div>
        <div class="opacity-75"><?= $ready ? 'Đã đủ các bước bắt buộc' : 'Bước bắt buộc hoàn thành' ?></div>
      </div>
    </div>
  </section>

  <div class="alert alert-success safe-note shadow-sm mb-4">
    <strong><i class="bi bi-shield-check"></i> Chế độ an toàn:</strong>
    trang này chỉ đọc trạng thái. Không tự chạy migration, không tự nạp và không thay đổi dữ liệu đang vận hành.
  </div>

  <?php if (!$ready): ?>
    <div class="alert alert-warning"><strong>Chưa nên bàn giao vận hành.</strong> Hãy xử lý các bước “Chưa thực hiện” hoặc “Cần kiểm tra” bên dưới.</div>
  <?php else: ?>
    <div class="alert alert-success"><strong>Sẵn sàng vận hành cơ bản.</strong> Các bước bắt buộc đã có dữ liệu; tiếp tục kiểm tra nghiệp vụ riêng của từng module được bật.</div>
  <?php endif; ?>

  <section class="card setup-card mb-4"><div class="card-body px-3 px-md-4 py-2">
    <?php foreach ($steps as $step): [$statusLabel, $statusColor, $statusIcon] = setup_status($step['state']); ?>
      <div class="step-row">
        <span class="step-number"><?= (int)$step['number'] ?></span>
        <div class="step-copy">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <h5 class="mb-0"><?= e($step['title']) ?></h5>
            <span class="badge text-bg-<?= e($statusColor) ?>"><i class="bi <?= e($statusIcon) ?>"></i> <?= e($statusLabel) ?></span>
          </div>
          <p><?= e($step['description']) ?></p>
          <small><strong>Hiện có:</strong> <?= e($step['detail']) ?></small>
        </div>
        <div class="step-action"><a class="btn btn-outline-primary" href="<?= e($step['url']) ?>"><?= e($step['action']) ?> <i class="bi bi-arrow-right"></i></a></div>
      </div>
    <?php endforeach; ?>
  </div></section>

  <section class="card setup-card mb-4"><div class="card-body p-4">
    <h5><i class="bi bi-file-earmark-spreadsheet text-success"></i> Cách nạp CSV an toàn</h5>
    <ol class="mb-0 ps-3">
      <li class="mb-2">Mở đúng mục dữ liệu và bấm <strong>Tải mẫu CSV</strong>.</li>
      <li class="mb-2">Giữ nguyên tên cột; nhập lần lượt <strong>Giáo viên → Lớp → Học sinh</strong>.</li>
      <li class="mb-2">Kiểm tra mã lớp, số điện thoại và các cột bắt buộc trước khi bấm <strong>Nhập & gộp</strong>.</li>
      <li>Sau mỗi lần nhập, đối chiếu số lượng hiển thị trên trang này rồi mới chuyển sang bước kế tiếp.</li>
    </ol>
  </div></section>

  <div class="d-flex flex-wrap justify-content-between gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL . 'admin.php') ?>"><i class="bi bi-arrow-left"></i> Quay lại quản trị</a>
    <a class="btn btn-primary" href="<?= e(BASE_URL . 'instance_settings.php') ?>"><i class="bi bi-sliders"></i> Cấu hình trường</a>
  </div>
</main>
</body>
</html>
