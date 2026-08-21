<?php
/**
 * Panel nhập · xuất · mẫu CSV cho 1 entity.
 * Biến cần có: $io_entity = teachers|classes|students
 */
if (empty($io_entity) || !in_array($io_entity, ['teachers', 'classes', 'students'], true)) return;
$canIoImport = !empty($canCsdlEdit);
$canIoExport = !empty($canCsdlExport);
if (!$canIoImport && !$canIoExport) return;
require_once __DIR__ . '/csdl_schema.php';
$schema = csdl_schema_entity($io_entity);
$titles = [
    'teachers' => 'Giáo viên / cán bộ / NV',
    'classes' => 'Lớp / khối',
    'students' => 'Học sinh',
];
$title = $titles[$io_entity];
$groups = [];
foreach ($schema as $key => $meta) {
    $g = $meta['group'] ?? 'Khác';
    if (!isset($groups[$g])) $groups[$g] = [];
    $groups[$g][$key] = $meta['label'];
}

$filteredParams = ['entity'=>$io_entity];
foreach (['q','grade','class','status'] as $filterKey) {
    if (isset($_GET[$filterKey]) && trim((string)$_GET[$filterKey]) !== '') $filteredParams[$filterKey] = trim((string)$_GET[$filterKey]);
}
$filteredExcelUrl = BASE_URL . 'csdl_export_filtered_excel.php?' . http_build_query($filteredParams);
$filteredMultiUrl = BASE_URL . 'csdl_export_filtered_excel.php?' . http_build_query(array_merge($filteredParams,['split'=>1]));
?>
<div class="card card-soft mb-4 border-primary border-opacity-25">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h5 class="mb-1"><i class="bi bi-arrow-left-right text-primary"></i> Nhập / Xuất — <?= e($title) ?></h5>
        <p class="small text-muted mb-0">CSDL là <strong>nguồn chuẩn</strong>. Các nút Excel bên phải xuất đúng dữ liệu theo bộ lọc đang dùng trên trang.</p>
      </div>
      <?php if ($canIoExport): ?><div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success btn-sm" href="<?= e($filteredExcelUrl) ?>"><i class="bi bi-file-earmark-excel"></i> Excel kết quả lọc</a>
        <a class="btn btn-outline-success btn-sm" href="<?= e($filteredMultiUrl) ?>" title="Tạo sheet Tổng hợp và thêm một sheet riêng cho từng trường thông tin"><i class="bi bi-files"></i> Excel nhiều sheet</a>
      </div><?php endif; ?>
    </div>
    <?php if ($canIoExport): ?><div class="alert alert-light border py-2 px-3 small mb-3">
      <i class="bi bi-info-circle text-primary"></i>
      <strong>Excel kết quả lọc</strong> giữ nguyên đúng các bản ghi đang lọc. <strong>Excel nhiều sheet</strong> có thêm một sheet cho từng trường thông tin để tra cứu riêng, ngoài sheet Tổng hợp.
    </div><?php endif; ?>
    <div class="row g-3">
      <?php if ($canIoExport): ?><div class="col-md-4">
        <div class="border rounded-3 p-3 h-100 bg-light">
          <div class="fw-bold mb-2"><i class="bi bi-download"></i> Mẫu nhập</div>
          <a class="btn btn-outline-secondary w-100" href="<?= BASE_URL ?>csdl_export.php?entity=<?= urlencode($io_entity) ?>&mode=template">Tải mẫu CSV</a>
        </div>
      </div><?php endif; ?>
      <?php if ($canIoImport): ?><div class="col-md-4">
        <div class="border rounded-3 p-3 h-100 bg-light">
          <div class="fw-bold mb-2"><i class="bi bi-upload text-success"></i> Nhập CSV</div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="io_import">
            <input type="hidden" name="entity" value="<?= e($io_entity) ?>">
            <input type="file" name="csv" class="form-control form-control-sm mb-2" accept=".csv,text/csv" required>
            <button class="btn btn-success w-100 btn-sm" type="submit">Nhập & gộp</button>
          </form>
        </div>
      </div><?php endif; ?>
      <?php if ($canIoExport): ?><div class="col-md-4">
        <div class="border rounded-3 p-3 h-100 bg-light">
          <div class="fw-bold mb-2"><i class="bi bi-file-earmark-spreadsheet text-primary"></i> Xuất CSV chọn cột</div>
          <form method="get" action="<?= BASE_URL ?>csdl_export.php" id="exp-<?= e($io_entity) ?>">
            <input type="hidden" name="entity" value="<?= e($io_entity) ?>">
            <input type="hidden" name="mode" value="export">
            <div class="small" style="max-height:140px;overflow:auto">
              <?php foreach ($groups as $gName => $fields): ?>
                <div class="text-muted fw-semibold mt-1"><?= e($gName) ?></div>
                <?php foreach ($fields as $key => $label): ?>
                  <label class="d-block"><input type="checkbox" name="fields[]" value="<?= e($key) ?>" checked> <?= e($label) ?></label>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
            <button class="btn btn-primary w-100 btn-sm mt-2" type="submit">Xuất CSV</button>
          </form>
          <button type="button" class="btn btn-link btn-sm p-0 mt-1" onclick="document.querySelectorAll('#exp-<?= e($io_entity) ?> input[type=checkbox]').forEach(c=>c.checked=true)">Chọn tất cả</button>
          ·
          <button type="button" class="btn btn-link btn-sm p-0 mt-1" onclick="document.querySelectorAll('#exp-<?= e($io_entity) ?> input[type=checkbox]').forEach(c=>c.checked=false)">Bỏ chọn</button>
        </div>
      </div><?php endif; ?>
    </div>
  </div>
</div>
