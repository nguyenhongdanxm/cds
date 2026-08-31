<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database_migrations.php';
require_once __DIR__ . '/includes/database_core_import.php';
require_once __DIR__ . '/includes/database_shadow.php';
require_once __DIR__ . '/includes/database_read_verify.php';
require_once __DIR__ . '/includes/database_sql_read.php';
require_once __DIR__ . '/includes/database_sql_write.php';
require_admin();

if (empty($_SESSION['cds_db_csrf'])) {
    $_SESSION['cds_db_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['cds_db_csrf'], $token)) {
        flash('Phiên thao tác không hợp lệ. Vui lòng thử lại.', 'danger');
        header('Location: ' . BASE_URL . 'database_admin.php');
        exit;
    }

    if (($_POST['action'] ?? '') === 'run_migrations') {
        try {
            $completed = cds_db_run_pending_migrations();
            if ($completed) {
                flash('Đã cài đặt nền MySQL thành công: ' . count($completed) . ' bản nâng cấp.');
            } else {
                flash('Cơ sở dữ liệu đã ở phiên bản mới nhất.');
            }
        } catch (Throwable $e) {
            flash('Không thể cài đặt nền MySQL: ' . $e->getMessage(), 'danger');
        }
    }

    if (($_POST['action'] ?? '') === 'import_core_snapshot') {
        try {
            $result = cds_core_import_snapshot(current_user());
            $counts = $result['counts'];
            if (!cds_read_verify_mark_snapshot_match($counts)) {
                throw new RuntimeException('Đã cập nhật MySQL nhưng chưa xác nhận được trạng thái kiểm chứng.');
            }
            if (!cds_shadow_pending_clear()) {
                throw new RuntimeException('MySQL đã đồng bộ nhưng chưa xóa được dấu đồng bộ đang chờ.');
            }
            flash(
                'Đã nhập bản sao JSON vào MySQL: '
                . $counts['teachers'] . ' giáo viên, '
                . $counts['classes'] . ' lớp, '
                . $counts['students'] . ' học sinh. '
                . 'CDS vẫn tiếp tục sử dụng JSON.',
                'success'
            );
        } catch (Throwable $e) {
            flash('Không thể nhập dữ liệu lõi: ' . $e->getMessage(), 'danger');
        }
    }

    if (($_POST['action'] ?? '') === 'set_core_shadow_write') {
        try {
            $enabled = ($_POST['enabled'] ?? '') === '1';
            cds_shadow_write_set($enabled, current_user());
            flash(
                $enabled
                    ? 'Đã bật ghi song song JSON → MySQL.'
                    : 'Đã tắt ghi song song; website tiếp tục dùng JSON.',
                'success'
            );
        } catch (Throwable $e) {
            flash('Không thể đổi chế độ ghi song song: ' . $e->getMessage(), 'danger');
        }
    }

    if (($_POST['action'] ?? '') === 'set_core_read_verify') {
        try {
            $enabled = ($_POST['enabled'] ?? '') === '1';
            cds_read_verify_set($enabled, current_user());
            flash(
                $enabled
                    ? 'Đã bật kiểm chứng đọc MySQL.'
                    : 'Đã tắt kiểm chứng đọc MySQL.',
                'success'
            );
        } catch (Throwable $e) {
            flash('Không thể đổi chế độ kiểm chứng đọc: ' . $e->getMessage(), 'danger');
        }
    }

    if (($_POST['action'] ?? '') === 'set_core_sql_read') {
        try {
            $enabled = ($_POST['enabled'] ?? '') === '1';
            cds_core_sql_read_set($enabled, current_user());
            flash(
                $enabled
                    ? 'Đã bật đọc SQL an toàn cho dữ liệu lõi. JSON vẫn được giữ và sẽ tự dùng lại nếu SQL có vấn đề.'
                    : 'Đã tắt đọc SQL; website sử dụng JSON như trước.',
                'success'
            );
        } catch (Throwable $e) {
            flash('Không thể đổi nguồn đọc dữ liệu lõi: ' . $e->getMessage(), 'danger');
        }
    }

    if (($_POST['action'] ?? '') === 'set_core_sql_primary_write') {
        try {
            $enabled = ($_POST['enabled'] ?? '') === '1';
            cds_core_sql_write_set($enabled, current_user());
            flash($enabled
                ? 'Đã bật thí điểm ghi MySQL trước cho giáo viên, lớp và học sinh.'
                : 'Đã tắt thí điểm ghi MySQL trước; hệ thống trở lại JSON-first.', 'success');
        } catch (Throwable $e) {
            flash('Không thể đổi chế độ ghi MySQL trước: ' . $e->getMessage(), 'danger');
        }
    }

    if (($_POST['action'] ?? '') === 'set_core_sql_primary_year_write') {
        try {
            $enabled = ($_POST['enabled'] ?? '') === '1';
            cds_core_sql_year_write_set($enabled, current_user());
            flash($enabled
                ? 'Đã bật thí điểm ghi MySQL trước cho năm học.'
                : 'Đã tắt thí điểm năm học; năm học trở lại JSON-first.', 'success');
        } catch (Throwable $e) {
            flash('Không thể đổi chế độ ghi năm học: ' . $e->getMessage(), 'danger');
        }
    }

    if (($_POST['action'] ?? '') === 'restore_core_json_backup') {
        try {
            $result = cds_core_sql_restore_json_backup();
            flash('Đã phục hồi bản dự phòng JSON từ MySQL: ' . (int)$result['count'] . ' bản ghi.', 'success');
        } catch (Throwable $e) {
            flash('Không thể phục hồi bản dự phòng JSON: ' . $e->getMessage(), 'danger');
        }
    }

    header('Location: ' . BASE_URL . 'database_admin.php');
    exit;
}

$dbStatus = cds_db_status();
$migrationStatus = null;
$corePreview = null;
$mysqlCounts = null;
$coreComparison = null;
$shadowWriteReady = false;
$shadowWriteEnabled = false;
$readVerifyReady = false;
$readVerifyEnabled = false;
$readVerifyStatus = array();
$sqlReadReady = false;
$sqlReadEnabled = false;
$sqlWriteReady = false;
$sqlWriteEnabled = false;
$sqlWriteReadiness = array('ready' => false, 'reason' => 'Chưa cài đặt bản nâng cấp.');
$yearWriteReady = false;
$yearWriteEnabled = false;
$yearWriteReadiness = array('ready' => false, 'reason' => 'Chưa cài đặt bản nâng cấp.');
$sqlReadStatus = array(
    'configured' => false,
    'ready' => false,
    'effective' => false,
    'reason_code' => '',
    'reason' => '',
    'entities' => array(),
);
$coreTablesReady = false;
if ($dbStatus['connected']) {
    try {
        $migrationStatus = cds_db_migration_status();
        $coreTablesReady = !isset(
            $migrationStatus['pending']['20260730_002_core_school_data']
        );
        $corePreview = cds_core_preview();
        if ($coreTablesReady) {
            $mysqlCounts = cds_core_mysql_counts();
            $coreComparison = cds_core_compare_snapshot($corePreview['data']);
            $shadowWriteReady = !isset(
                $migrationStatus['pending']['20260731_003_shadow_write_settings']
            );
            if ($shadowWriteReady) {
                $shadowWriteEnabled = cds_shadow_write_enabled();
            }
            $readVerifyReady = !isset(
                $migrationStatus['pending']['20260731_004_read_verification']
            );
            if ($readVerifyReady) {
                $readVerifyEnabled = cds_read_verify_enabled();
                $readVerifyStatus = cds_read_verify_status();
            }
            $sqlReadReady = !isset(
                $migrationStatus['pending']['20260830_005_safe_core_sql_read']
            );
            if ($sqlReadReady) {
                $sqlReadStatus = cds_core_sql_read_status();
                $sqlReadEnabled = !empty($sqlReadStatus['configured']);
            }
            $sqlWriteReady = !isset($migrationStatus['pending']['20260831_006_pilot_core_sql_primary_write']);
            if ($sqlWriteReady) {
                $sqlWriteEnabled = cds_core_sql_write_enabled();
                $sqlWriteReadiness = cds_core_sql_write_readiness();
            }
            $yearWriteReady = !isset($migrationStatus['pending']['20260831_007_pilot_school_year_sql_write']);
            if ($yearWriteReady) {
                $yearWriteEnabled = cds_core_sql_year_write_enabled();
                $yearWriteReadiness = cds_core_sql_year_write_readiness();
            }
        }
    } catch (Throwable $e) {
        $dbStatus['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MySQL CDS – <?= e(SCHOOL_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f0f4f8}
.status-card{background:#fff;border:0;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.status-dot{width:.75rem;height:.75rem;border-radius:50%;display:inline-block}
.check-row{display:flex;justify-content:space-between;gap:1rem;padding:.7rem 0;border-bottom:1px solid #edf0f2}
.check-row:last-child{border-bottom:0}
code{word-break:break-all}
</style>
</head>
<body>
<?php
$nav_title = 'MySQL CDS';
$nav_icon = 'bi-database-check';
$nav_color = '#1F4E79';
$nav_module = 'admin';
include __DIR__ . '/includes/nav_top.php';
?>
<main class="container pb-5">
  <?php show_flash(); ?>

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-database-check text-primary"></i> Trạng thái MySQL</h3>
      <p class="text-muted mb-0">Chỉ quản trị hệ thống được truy cập trang này.</p>
    </div>
    <a href="<?= e(BASE_URL . 'admin.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left"></i> Quay lại quản trị
    </a>
  </div>

  <div class="alert <?= !empty($sqlReadStatus['configured']) && empty($sqlReadStatus['effective'])
      ? 'alert-warning' : 'alert-info' ?>">
    <strong>Chế độ an toàn:</strong>
    <?php if (!empty($sqlReadStatus['effective'])): ?>
      CDS đang đọc dữ liệu lõi từ MySQL; JSON vẫn được ghi song song và giữ làm bản dự phòng.
    <?php elseif (!empty($sqlReadStatus['configured'])): ?>
      CDS đã tự quay về đọc JSON vì <?= e($sqlReadStatus['reason']) ?>
      Chế độ SQL sẽ tự hoạt động lại khi các điều kiện an toàn được khôi phục.
    <?php else: ?>
      CDS đang đọc và ghi dữ liệu JSON. MySQL chưa được chọn làm nguồn đọc dữ liệu lõi.
    <?php endif; ?>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <section class="status-card p-3 h-100">
        <h5 class="mb-2">Kiểm tra kết nối</h5>
        <div class="check-row">
          <span>Tệp cấu hình riêng</span>
          <strong class="<?= $dbStatus['config_exists'] ? 'text-success' : 'text-danger' ?>">
            <?= $dbStatus['config_exists'] ? 'Đã nhận' : 'Chưa nhận' ?>
          </strong>
        </div>
        <div class="check-row">
          <span>Extension pdo_mysql</span>
          <strong class="<?= $dbStatus['pdo_mysql'] ? 'text-success' : 'text-danger' ?>">
            <?= $dbStatus['pdo_mysql'] ? 'Sẵn sàng' : 'Chưa bật' ?>
          </strong>
        </div>
        <div class="check-row">
          <span>Kết nối MariaDB</span>
          <strong class="<?= $dbStatus['connected'] ? 'text-success' : 'text-danger' ?>">
            <?= $dbStatus['connected'] ? 'Thành công' : 'Thất bại' ?>
          </strong>
        </div>
        <?php if ($dbStatus['connected']): ?>
          <div class="check-row"><span>Database</span><strong><?= e($dbStatus['database']) ?></strong></div>
          <div class="check-row"><span>Phiên bản máy chủ</span><strong><?= e($dbStatus['server_version']) ?></strong></div>
        <?php elseif ($dbStatus['error']): ?>
          <div class="alert alert-danger mt-3 mb-0"><?= e($dbStatus['error']) ?></div>
        <?php endif; ?>
      </section>
    </div>

    <div class="col-lg-6">
      <section class="status-card p-3 h-100">
        <h5 class="mb-2">Phiên bản cấu trúc</h5>
        <?php if ($migrationStatus): ?>
          <div class="check-row">
            <span>Đã cài đặt</span>
            <strong><?= count($migrationStatus['applied']) ?></strong>
          </div>
          <div class="check-row">
            <span>Đang chờ</span>
            <strong class="<?= $migrationStatus['pending'] ? 'text-warning' : 'text-success' ?>">
              <?= count($migrationStatus['pending']) ?>
            </strong>
          </div>

          <?php if ($migrationStatus['pending']): ?>
            <div class="mt-3 small text-muted">
              <?php foreach ($migrationStatus['pending'] as $id => $migration): ?>
                <div><code><?= e($id) ?></code> — <?= e($migration['description']) ?></div>
              <?php endforeach; ?>
            </div>
            <form method="post" class="mt-3" onsubmit="return confirm('Cài đặt các bảng nền MySQL? Dữ liệu JSON hiện tại không bị thay đổi.');">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
              <input type="hidden" name="action" value="run_migrations">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-database-add"></i> Cài đặt nền MySQL
              </button>
            </form>
          <?php else: ?>
            <div class="alert alert-success mt-3 mb-0">
              <i class="bi bi-check-circle"></i> Cấu trúc nền đã ở phiên bản mới nhất.
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert-warning mb-0">Cần kết nối thành công trước khi quản lý phiên bản.</div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <?php if ($corePreview): ?>
  <section class="status-card p-3 mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <h5 class="mb-1"><i class="bi bi-people"></i> Bản sao dữ liệu lõi</h5>
        <p class="text-muted small mb-0">
          Kiểm tra JSON trước khi sao chép giáo viên, lớp và học sinh sang MySQL.
        </p>
      </div>
      <span class="badge <?= $corePreview['can_import'] ? 'text-bg-success' : 'text-bg-danger' ?>">
        <?= $corePreview['can_import'] ? 'Có thể nhập' : 'Cần xử lý mâu thuẫn' ?>
      </span>
    </div>

    <div class="row g-2 mt-2">
      <?php
      $coreLabels = array(
          'years' => 'Năm học',
          'teachers' => 'Giáo viên',
          'classes' => 'Lớp',
          'students' => 'Học sinh',
      );
      foreach ($coreLabels as $key => $label):
          $jsonCount = (int)$corePreview['counts'][$key];
          $mysqlCount = $mysqlCounts !== null ? (int)$mysqlCounts[$key] : null;
      ?>
      <div class="col-6 col-lg-3">
        <div class="border rounded-3 p-2 h-100">
          <div class="small text-muted"><?= e($label) ?></div>
          <div class="fw-bold">JSON: <?= $jsonCount ?></div>
          <div class="small <?= $mysqlCount === $jsonCount ? 'text-success' : 'text-muted' ?>">
            MySQL: <?= $mysqlCount === null ? 'Chưa có bảng' : $mysqlCount ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($coreComparison): ?>
      <div class="alert <?= $coreComparison['is_match'] ? 'alert-success' : 'alert-warning' ?> mt-3 mb-2">
        <strong>
          <i class="bi <?= $coreComparison['is_match'] ? 'bi-shield-check' : 'bi-exclamation-triangle' ?>"></i>
          <?= $coreComparison['is_match']
              ? 'Đối chiếu chi tiết: toàn bộ ID và nội dung bản ghi đang khớp.'
              : 'Đối chiếu chi tiết: bản sao MySQL đã khác dữ liệu JSON hiện tại.' ?>
        </strong>
        <?php if (!$coreComparison['is_match']): ?>
          <ul class="mb-0 mt-1">
            <?php foreach ($coreLabels as $key => $label):
                $comparison = $coreComparison['types'][$key];
                if ($comparison['is_match']) {
                    continue;
                }
            ?>
              <li>
                <?= e($label) ?>:
                thiếu <?= count($comparison['missing']) ?>,
                thừa <?= count($comparison['extra']) ?>,
                thay đổi <?= count($comparison['changed']) ?>.
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($corePreview['errors']): ?>
      <div class="alert alert-danger mt-3 mb-2">
        <strong>Mâu thuẫn bắt buộc xử lý:</strong>
        <ul class="mb-0 mt-1">
          <?php foreach (array_slice($corePreview['errors'], 0, 20) as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($corePreview['warnings']): ?>
      <div class="alert alert-warning mt-3 mb-2">
        <strong>Cảnh báo cần kiểm tra:</strong>
        <ul class="mb-0 mt-1">
          <?php foreach (array_slice($corePreview['warnings'], 0, 20) as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!$coreTablesReady): ?>
      <div class="alert alert-info mt-3 mb-0">
        Cài đặt bản nâng cấp cấu trúc đang chờ trước khi nhập dữ liệu.
      </div>
    <?php elseif ($corePreview['can_import']): ?>
      <form method="post" class="mt-3"
            onsubmit="return confirm('Sao chép ảnh chụp dữ liệu JSON hiện tại vào MySQL? Website vẫn tiếp tục dùng JSON sau thao tác này.');">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
        <input type="hidden" name="action" value="import_core_snapshot">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-database-up"></i>
          <?= $mysqlCounts && array_sum($mysqlCounts) > 0
              ? 'Cập nhật bản sao JSON vào MySQL'
              : 'Nhập bản sao JSON vào MySQL' ?>
        </button>
      </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($coreComparison && $coreComparison['is_match'] && $shadowWriteReady): ?>
  <section class="status-card p-3 mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1"><i class="bi bi-arrow-left-right"></i> Ghi song song dữ liệu lõi</h5>
        <p class="text-muted small mb-0">
          JSON vẫn là nguồn chính; mỗi lần lưu chỉ cập nhật đúng bản ghi MySQL tương ứng.
          Nhập hoặc xóa hàng loạt chỉ đồng bộ MySQL một lần khi hoàn tất để tránh chậm hệ thống.
          Thay đổi năm học tiếp tục dùng ảnh chụp đầy đủ để giữ đúng liên kết lớp và học sinh.
        </p>
      </div>
      <span class="badge <?= $shadowWriteEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
        <?= $shadowWriteEnabled ? 'Đang bật' : 'Đang tắt' ?>
      </span>
    </div>
    <form method="post" class="mt-3"
          onsubmit="return confirm('<?= $shadowWriteEnabled
              ? 'Tắt ghi song song MySQL?'
              : 'Bật ghi song song JSON sang MySQL? JSON vẫn là nguồn chính.' ?>');">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
      <input type="hidden" name="action" value="set_core_shadow_write">
      <input type="hidden" name="enabled" value="<?= $shadowWriteEnabled ? '0' : '1' ?>">
      <button type="submit" class="btn <?= $shadowWriteEnabled ? 'btn-outline-secondary' : 'btn-primary' ?>">
        <i class="bi <?= $shadowWriteEnabled ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
        <?= $shadowWriteEnabled ? 'Tắt ghi song song' : 'Bật ghi song song' ?>
      </button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($coreComparison && $readVerifyReady): ?>
  <section class="status-card p-3 mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1"><i class="bi bi-shield-check"></i> Kiểm chứng đọc MySQL</h5>
        <p class="text-muted small mb-0">
          <?= !empty($sqlReadStatus['effective'])
              ? 'CDS đang đọc dữ liệu lõi từ MySQL; kết quả đối chiếu được giữ để bảo vệ cơ chế tự quay về JSON.'
              : 'CDS đang đọc JSON; MySQL được đối chiếu để xác nhận đủ điều kiện đọc SQL an toàn.' ?>
        </p>
      </div>
      <span class="badge <?= $readVerifyEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
        <?= $readVerifyEnabled ? 'Đang bật' : 'Đang tắt' ?>
      </span>
    </div>

    <?php if ($readVerifyEnabled): ?>
      <div class="row g-2 mt-2">
        <?php foreach ($coreLabels as $key => $label):
            $verify = $readVerifyStatus[$key] ?? null;
            $matched = $verify && $verify['verify_status'] === 'match';
        ?>
          <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-2 h-100">
              <div class="small text-muted"><?= e($label) ?></div>
              <div class="fw-bold <?= $matched ? 'text-success' : 'text-warning' ?>">
                <?= !$verify ? 'Chưa kiểm tra' : ($matched ? 'Đang khớp' : 'Có sai lệch') ?>
              </div>
              <?php if ($verify): ?>
                <div class="small text-muted">
                  JSON <?= (int)$verify['json_count'] ?> · MySQL <?= (int)$verify['mysql_count'] ?>
                </div>
                <div class="small text-muted"><?= e((string)$verify['checked_at']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="alert alert-info mt-3 mb-0">
        Nếu MySQL lỗi hoặc có sai lệch, website tự sử dụng JSON và tiếp tục hoạt động.
      </div>
    <?php endif; ?>

    <form method="post" class="mt-3"
          onsubmit="return confirm('<?= $readVerifyEnabled
              ? 'Tắt kiểm chứng đọc MySQL?'
              : 'Bật đọc MySQL để đối chiếu âm thầm? Giao diện vẫn dùng JSON.' ?>');">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
      <input type="hidden" name="action" value="set_core_read_verify">
      <input type="hidden" name="enabled" value="<?= $readVerifyEnabled ? '0' : '1' ?>">
      <button type="submit" class="btn <?= $readVerifyEnabled ? 'btn-outline-secondary' : 'btn-primary' ?>">
        <i class="bi <?= $readVerifyEnabled ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
        <?= $readVerifyEnabled ? 'Tắt kiểm chứng đọc' : 'Bật kiểm chứng đọc' ?>
      </button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($sqlReadReady && $coreTablesReady): ?>
  <section class="status-card p-3 mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1"><i class="bi bi-database-check"></i> Đọc SQL an toàn – dữ liệu lõi</h5>
        <p class="text-muted small mb-0">
          Áp dụng cho năm học, giáo viên, lớp và học sinh. JSON tiếp tục được lưu làm bản dự phòng.
        </p>
      </div>
      <?php
      $sqlReadBadgeClass = !empty($sqlReadStatus['effective'])
          ? 'text-bg-success'
          : ($sqlReadEnabled ? 'text-bg-warning' : 'text-bg-secondary');
      $sqlReadBadgeText = !empty($sqlReadStatus['effective'])
          ? 'Đang đọc SQL'
          : ($sqlReadEnabled ? 'Đã tự về JSON' : 'Đang đọc JSON');
      ?>
      <span class="badge <?= $sqlReadBadgeClass ?>">
        <?= $sqlReadBadgeText ?>
      </span>
    </div>
    <div class="alert alert-info mt-3 mb-0">
      Khi SQL mất kết nối, ghi song song bị tắt hoặc dữ liệu không còn khớp,
      hệ thống tự động quay về JSON mà không cần quản trị can thiệp.
    </div>
    <?php if ($sqlReadEnabled && empty($sqlReadStatus['effective'])): ?>
      <div class="alert alert-warning mt-2 mb-0">
        <strong>Đang dùng JSON dự phòng:</strong> <?= e($sqlReadStatus['reason']) ?>
        <?php if (!empty($sqlReadStatus['entities'])): ?>
          <ul class="mb-0 mt-1">
            <?php foreach ($sqlReadStatus['entities'] as $entityType => $message): ?>
              <li><?= e($coreLabels[$entityType] ?? $entityType) ?>: <?= e($message) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php elseif (!$shadowWriteEnabled): ?>
      <div class="alert alert-warning mt-2 mb-0">
        Cần bật <strong>Ghi song song dữ liệu lõi</strong> trước khi có thể bật đọc SQL.
      </div>
    <?php endif; ?>
    <form method="post" class="mt-3"
          onsubmit="return confirm('<?= $sqlReadEnabled
              ? 'Tắt đọc SQL và quay về JSON?'
              : 'Bật đọc SQL cho dữ liệu lõi? JSON vẫn được giữ làm dự phòng.' ?>');">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
      <input type="hidden" name="action" value="set_core_sql_read">
      <input type="hidden" name="enabled" value="<?= $sqlReadEnabled ? '0' : '1' ?>">
      <button type="submit" class="btn <?= $sqlReadEnabled ? 'btn-outline-secondary' : 'btn-primary' ?>"
              <?= !$sqlReadEnabled && empty($sqlReadStatus['ready']) ? 'disabled' : '' ?>>
        <i class="bi <?= $sqlReadEnabled ? 'bi-arrow-counterclockwise' : 'bi-database-check' ?>"></i>
        <?= $sqlReadEnabled ? 'Quay về đọc JSON' : 'Bật đọc SQL an toàn' ?>
      </button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($sqlWriteReady && $coreTablesReady): ?>
  <section class="status-card p-3 mt-3 border border-warning-subtle">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1"><i class="bi bi-database-gear"></i> Thí điểm ghi MySQL trước</h5>
        <p class="text-muted small mb-0">
          Chỉ áp dụng cho giáo viên, lớp và học sinh. MySQL được ghi trong transaction trước;
          JSON tiếp tục được cập nhật nguyên tử làm bản dự phòng. Năm học và thao tác hàng loạt
          vẫn giữ JSON-first rồi đồng bộ MySQL một lần khi hoàn tất.
        </p>
      </div>
      <span class="badge <?= $sqlWriteEnabled ? 'text-bg-warning' : 'text-bg-secondary' ?>">
        <?= $sqlWriteEnabled ? 'Đang thí điểm' : 'Mặc định tắt' ?>
      </span>
    </div>
    <?php if (!$sqlWriteEnabled && empty($sqlWriteReadiness['ready'])): ?>
      <div class="alert alert-warning mt-3 mb-0"><?= e($sqlWriteReadiness['reason']) ?></div>
    <?php endif; ?>
    <?php if (cds_core_sql_backup_pending_status()): ?>
      <div class="alert alert-danger mt-3 mb-0">
        Có bản dự phòng JSON chưa hoàn tất. Không bật hoặc tiếp tục thí điểm cho tới khi phục hồi.
      </div>
      <form method="post" class="mt-2"
            onsubmit="return confirm('Phục hồi toàn bộ tệp JSON của nhóm đang chờ từ raw_json trong MySQL?');">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
        <input type="hidden" name="action" value="restore_core_json_backup">
        <button type="submit" class="btn btn-danger btn-sm">Phục hồi JSON từ MySQL</button>
      </form>
    <?php endif; ?>
    <form method="post" class="mt-3"
          onsubmit="return confirm('<?= $sqlWriteEnabled
              ? 'Tắt ghi MySQL trước và quay lại JSON-first?'
              : 'Bật chế độ thí điểm ghi MySQL trước? Chỉ thực hiện sau khi đã sao lưu và kiểm thử.' ?>');">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
      <input type="hidden" name="action" value="set_core_sql_primary_write">
      <input type="hidden" name="enabled" value="<?= $sqlWriteEnabled ? '0' : '1' ?>">
      <button type="submit" class="btn <?= $sqlWriteEnabled ? 'btn-outline-secondary' : 'btn-warning' ?>"
              <?= !$sqlWriteEnabled && empty($sqlWriteReadiness['ready']) ? 'disabled' : '' ?>>
        <?= $sqlWriteEnabled ? 'Tắt thí điểm' : 'Bật thí điểm có kiểm soát' ?>
      </button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($yearWriteReady && $coreTablesReady): ?>
  <section class="status-card p-3 mt-3 border border-primary-subtle">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1"><i class="bi bi-calendar2-check"></i> Giai đoạn 2A – ghi năm học vào MySQL trước</h5>
        <p class="text-muted small mb-0">
          Đổi năm hiện hành, sửa lịch tuần và thêm/xóa năm học được thực hiện trong transaction;
          liên kết năm của lớp, học sinh được cập nhật cùng lúc. JSON vẫn là bản dự phòng.
          Nhập và xóa hàng loạt tiếp tục dùng cơ chế JSON-first an toàn.
        </p>
      </div>
      <span class="badge <?= $yearWriteEnabled ? 'text-bg-primary' : 'text-bg-secondary' ?>">
        <?= $yearWriteEnabled ? 'Đang thí điểm 2A' : 'Mặc định tắt' ?>
      </span>
    </div>
    <?php if (!$yearWriteEnabled && empty($yearWriteReadiness['ready'])): ?>
      <div class="alert alert-warning mt-3 mb-0"><?= e($yearWriteReadiness['reason']) ?></div>
    <?php endif; ?>
    <form method="post" class="mt-3"
          onsubmit="return confirm('<?= $yearWriteEnabled
              ? 'Tắt thí điểm ghi năm học vào MySQL trước?'
              : 'Bật giai đoạn 2A? Chỉ kiểm thử sau khi toàn bộ dữ liệu đang khớp.' ?>');">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cds_db_csrf']) ?>">
      <input type="hidden" name="action" value="set_core_sql_primary_year_write">
      <input type="hidden" name="enabled" value="<?= $yearWriteEnabled ? '0' : '1' ?>">
      <button type="submit" class="btn <?= $yearWriteEnabled ? 'btn-outline-secondary' : 'btn-primary' ?>"
              <?= !$yearWriteEnabled && empty($yearWriteReadiness['ready']) ? 'disabled' : '' ?>>
        <?= $yearWriteEnabled ? 'Tắt thí điểm 2A' : 'Bật thí điểm 2A có kiểm soát' ?>
      </button>
    </form>
  </section>
  <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
