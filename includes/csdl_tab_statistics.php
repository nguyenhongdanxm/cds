<?php
/**
 * Thống kê cơ cấu giáo viên và học sinh từ nguồn chuẩn CSDL.
 * Biến đầu vào: $teachers, $classes, $students, $stats.
 */

$statStatus = $_GET['stat_status'] ?? 'active';
if (!in_array($statStatus, ['active', 'inactive', 'all'], true)) $statStatus = 'active';

$filterStatus = static function (array $rows) use ($statStatus): array {
    if ($statStatus === 'all') return array_values($rows);
    $wantActive = $statStatus === 'active';
    return array_values(array_filter($rows, static function ($row) use ($wantActive) {
        return !empty($row['active']) === $wantActive;
    }));
};

$statTeachers = $filterStatus($teachers);
$statStudents = $filterStatus($students);
$classById = [];
foreach ($classes as $classRow) $classById[(string)($classRow['id'] ?? '')] = $classRow;

$cleanLabel = static function ($value, string $empty = 'Chưa cập nhật'): string {
    $value = preg_replace('/\s+/u', ' ', trim((string)$value));
    return $value !== '' ? $value : $empty;
};

$genderLabel = static function ($value): string {
    $raw = trim((string)$value);
    $key = mb_strtolower($raw, 'UTF-8');
    if ($key === 'nam' || $key === 'm' || $key === 'male') return 'Nam';
    if ($key === 'nữ' || $key === 'nu' || $key === 'f' || $key === 'female') return 'Nữ';
    return $raw !== '' ? 'Khác' : 'Chưa cập nhật';
};

$aggregate = static function (array $rows, callable $labelResolver) use ($genderLabel): array {
    $out = [];
    foreach ($rows as $row) {
        $label = trim((string)$labelResolver($row));
        if ($label === '') $label = 'Chưa cập nhật';
        $key = mb_strtolower($label, 'UTF-8');
        if (!isset($out[$key])) $out[$key] = ['label' => $label, 'total' => 0, 'Nam' => 0, 'Nữ' => 0, 'Khác' => 0, 'Chưa cập nhật' => 0];
        $gender = $genderLabel($row['gender'] ?? '');
        $out[$key]['total']++;
        $out[$key][$gender]++;
    }
    $out = array_values($out);
    usort($out, static function ($a, $b) {
        if ($a['label'] === 'Chưa cập nhật' && $b['label'] === 'Chưa cập nhật') return 0;
        if ($a['label'] === 'Chưa cập nhật') return 1;
        if ($b['label'] === 'Chưa cập nhật') return -1;
        return $b['total'] <=> $a['total'] ?: strnatcasecmp($a['label'], $b['label']);
    });
    return $out;
};

$genderSummary = static function (array $rows) use ($genderLabel): array {
    $out = ['Nam' => 0, 'Nữ' => 0, 'Khác' => 0, 'Chưa cập nhật' => 0];
    foreach ($rows as $row) $out[$genderLabel($row['gender'] ?? '')]++;
    return $out;
};

$teacherGender = $genderSummary($statTeachers);
$studentGender = $genderSummary($statStudents);

$teacherLevels = $aggregate($statTeachers, static function ($row) use ($cleanLabel) {
    $raw = $cleanLabel($row['teaching_level'] ?? '');
    if ($raw === 'Chưa cập nhật') return $raw;
    $key = mb_strtolower($raw, 'UTF-8');
    $hasThcs = strpos($key, 'thcs') !== false || strpos($key, 'cấp 2') !== false;
    $hasThpt = strpos($key, 'thpt') !== false || strpos($key, 'cấp 3') !== false;
    if ($hasThcs && $hasThpt) return 'THCS & THPT';
    if ($hasThcs) return 'THCS';
    if ($hasThpt) return 'THPT';
    return $raw;
});

$studentLevels = $aggregate($statStudents, static function ($row) use ($classById, $cleanLabel) {
    $class = $classById[(string)($row['class_id'] ?? '')] ?? [];
    $level = $cleanLabel($class['level'] ?? '');
    if ($level !== 'Chưa cập nhật') return mb_strtoupper($level, 'UTF-8');
    $grade = (int)($class['grade'] ?? 0);
    if ($grade >= 6 && $grade <= 9) return 'THCS';
    if ($grade >= 10 && $grade <= 12) return 'THPT';
    return 'Chưa cập nhật';
});

$teacherEthnicities = $aggregate($statTeachers, static function ($row) use ($cleanLabel) {
    $value = $cleanLabel($row['ethnicity'] ?? '');
    return mb_strtolower($value, 'UTF-8') === 'kinh' ? 'Kinh' : $value;
});
$studentEthnicities = $aggregate($statStudents, static function ($row) use ($cleanLabel) {
    $value = $cleanLabel($row['ethnicity'] ?? '');
    return mb_strtolower($value, 'UTF-8') === 'kinh' ? 'Kinh' : $value;
});

$teacherTeams = $aggregate($statTeachers, static function ($row) use ($cleanLabel) {
    return $cleanLabel($row['to_chuyen_mon'] ?? ($row['pccm_group'] ?? ''), 'Chưa xếp tổ');
});
$studentGrades = $aggregate($statStudents, static function ($row) use ($classById) {
    $class = $classById[(string)($row['class_id'] ?? '')] ?? [];
    $grade = (int)($class['grade'] ?? 0);
    return $grade > 0 ? 'Khối ' . $grade : 'Chưa xếp khối';
});
$studentClasses = $aggregate($statStudents, static function ($row) use ($classById) {
    return trim((string)($classById[(string)($row['class_id'] ?? '')]['name'] ?? '')) ?: 'Chưa xếp lớp';
});
$studentBoarding = $aggregate($statStudents, static function ($row) {
    return !empty($row['boarder']) ? 'Học sinh nội trú' : 'Không nội trú';
});

$percent = static function (int $value, int $total): string {
    return $total > 0 ? number_format($value * 100 / $total, 1, ',', '.') . '%' : '0%';
};
$maxTotal = static function (array $rows): int {
    $values = array_column($rows, 'total');
    return $values ? max(1, max($values)) : 1;
};
$statusLabel = ['active' => 'Đang học / đang công tác', 'inactive' => 'Đã nghỉ / ngừng hoạt động', 'all' => 'Tất cả hồ sơ'][$statStatus];
?>

<div class="card card-soft mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h5 class="mb-1"><i class="bi bi-bar-chart-line text-primary"></i> Thống kê giáo viên và học sinh</h5>
        <div class="stat-note">Số liệu trực tiếp từ CSDL; thống kê học sinh tuân theo phạm vi lớp được phân quyền.</div>
      </div>
      <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= e($statusLabel) ?></span>
    </div>
    <form method="get" class="stat-filter">
      <input type="hidden" name="tab" value="statistics">
      <div>
        <label class="form-label small mb-1">Trạng thái hồ sơ</label>
        <select class="form-select" name="stat_status">
          <option value="active" <?= $statStatus === 'active' ? 'selected' : '' ?>>Đang học / đang công tác</option>
          <option value="inactive" <?= $statStatus === 'inactive' ? 'selected' : '' ?>>Đã nghỉ / ngừng hoạt động</option>
          <option value="all" <?= $statStatus === 'all' ? 'selected' : '' ?>>Tất cả hồ sơ</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Áp dụng</button>
    </form>
  </div>
</div>

<?php foreach ([
    ['title' => 'Giáo viên / CBGVNV', 'icon' => 'bi-person-badge', 'rows' => $statTeachers, 'gender' => $teacherGender],
    ['title' => 'Học sinh', 'icon' => 'bi-mortarboard', 'rows' => $statStudents, 'gender' => $studentGender],
] as $summary): $summaryTotal = count($summary['rows']); ?>
<section class="mb-4">
  <div class="stat-section-title">
    <h5><i class="bi <?= e($summary['icon']) ?> text-primary"></i> <?= e($summary['title']) ?></h5>
    <span class="stat-note">Tỷ lệ tính trên <?= number_format($summaryTotal, 0, ',', '.') ?> hồ sơ</span>
  </div>
  <div class="row g-2">
    <div class="col-6 col-lg-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-people"></i></span><strong><?= number_format($summaryTotal, 0, ',', '.') ?></strong><small>Tổng số</small></div></div>
    <div class="col-6 col-lg-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-gender-male"></i></span><strong><?= number_format($summary['gender']['Nam'], 0, ',', '.') ?></strong><small>Nam · <?= $percent($summary['gender']['Nam'], $summaryTotal) ?></small></div></div>
    <div class="col-6 col-lg-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-gender-female"></i></span><strong><?= number_format($summary['gender']['Nữ'], 0, ',', '.') ?></strong><small>Nữ · <?= $percent($summary['gender']['Nữ'], $summaryTotal) ?></small></div></div>
    <div class="col-6 col-lg-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-question-circle"></i></span><strong><?= number_format($summary['gender']['Khác'] + $summary['gender']['Chưa cập nhật'], 0, ',', '.') ?></strong><small>Khác / chưa cập nhật</small></div></div>
  </div>
</section>
<?php endforeach; ?>

<?php
$statTables = [
    ['title' => 'Giáo viên theo cấp học', 'icon' => 'bi-diagram-3', 'rows' => $teacherLevels, 'base' => count($statTeachers)],
    ['title' => 'Học sinh theo cấp học', 'icon' => 'bi-building', 'rows' => $studentLevels, 'base' => count($statStudents)],
    ['title' => 'Dân tộc giáo viên', 'icon' => 'bi-people', 'rows' => $teacherEthnicities, 'base' => count($statTeachers)],
    ['title' => 'Dân tộc học sinh', 'icon' => 'bi-people-fill', 'rows' => $studentEthnicities, 'base' => count($statStudents)],
    ['title' => 'Giáo viên theo tổ chuyên môn', 'icon' => 'bi-person-workspace', 'rows' => $teacherTeams, 'base' => count($statTeachers)],
    ['title' => 'Học sinh theo khối', 'icon' => 'bi-layers', 'rows' => $studentGrades, 'base' => count($statStudents)],
    ['title' => 'Học sinh theo lớp', 'icon' => 'bi-grid-3x3-gap', 'rows' => $studentClasses, 'base' => count($statStudents)],
    ['title' => 'Tình trạng nội trú', 'icon' => 'bi-building-check', 'rows' => $studentBoarding, 'base' => count($statStudents)],
];
?>
<div class="row g-3">
<?php foreach ($statTables as $table): $barMax = $maxTotal($table['rows']); ?>
  <div class="col-12 col-xl-6">
    <div class="card card-soft h-100">
      <div class="card-body">
        <div class="stat-section-title">
          <h5><i class="bi <?= e($table['icon']) ?> text-primary"></i> <?= e($table['title']) ?></h5>
          <span class="badge text-bg-light border"><?= count($table['rows']) ?> nhóm</span>
        </div>
        <?php if (!$table['rows']): ?>
          <div class="text-muted small py-3 text-center">Chưa có dữ liệu.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm stat-table mb-0">
            <thead><tr><th>Nhóm</th><th>Tổng</th><th>Nam</th><th>Nữ</th><th>Khác/thiếu</th><th>Tỷ lệ</th><th>Cơ cấu</th></tr></thead>
            <tbody>
            <?php foreach ($table['rows'] as $row): ?>
              <tr>
                <td class="stat-label"><?= e($row['label']) ?></td>
                <td><strong><?= number_format($row['total'], 0, ',', '.') ?></strong></td>
                <td><?= number_format($row['Nam'], 0, ',', '.') ?></td>
                <td><?= number_format($row['Nữ'], 0, ',', '.') ?></td>
                <td><?= number_format($row['Khác'] + $row['Chưa cập nhật'], 0, ',', '.') ?></td>
                <td><?= $percent($row['total'], $table['base']) ?></td>
                <td><div class="stat-bar" title="<?= $percent($row['total'], $table['base']) ?>"><span style="width:<?= number_format($row['total'] * 100 / $barMax, 2, '.', '') ?>%"></span></div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="alert alert-light border mt-3 mb-0 small text-muted">
  <i class="bi bi-info-circle"></i>
  Mục <strong>Chưa cập nhật</strong> giúp phát hiện hồ sơ còn thiếu giới tính, dân tộc, cấp học hoặc lớp. Hãy bổ sung tại tab Giáo viên/Học sinh để thống kê chính xác hơn.
</div>
