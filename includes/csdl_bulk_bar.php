<?php
/**
 * Thanh thao tác hàng loạt.
 * Cần: $bulk_entity = teachers|classes|students
 */
if (empty($bulk_entity)) return;
$canBulkExport = !empty($canCsdlExport);
$canBulkDelete = !empty($canDeleteCurrent);
if (!$canBulkExport && !$canBulkDelete) return;
$labels = ['teachers' => 'giáo viên', 'classes' => 'lớp', 'students' => 'học sinh'];
$lab = $labels[$bulk_entity] ?? 'mục';
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-2 bulk-bar" data-entity="<?= e($bulk_entity) ?>">
  <div class="form-check mb-0">
    <input class="form-check-input" type="checkbox" id="chkAll-<?= e($bulk_entity) ?>" onclick="csdlToggleAll('<?= e($bulk_entity) ?>', this.checked)">
    <label class="form-check-label small" for="chkAll-<?= e($bulk_entity) ?>">Chọn tất cả</label>
  </div>
  <span class="text-muted small" id="bulkCount-<?= e($bulk_entity) ?>">0 đã chọn</span>
  <?php if ($canBulkExport): ?><button type="button" class="btn btn-sm btn-outline-primary" onclick="csdlExportSelected('<?= e($bulk_entity) ?>')">
    <i class="bi bi-download"></i> Xuất đã chọn
  </button><?php endif; ?>
  <?php if ($canBulkDelete): ?><button type="button" class="btn btn-sm btn-outline-danger" onclick="csdlDeleteSelected('<?= e($bulk_entity) ?>')">
    <i class="bi bi-trash"></i> Xóa đã chọn
  </button><?php endif; ?>
</div>
<form method="post" id="bulkForm-<?= e($bulk_entity) ?>" class="d-none">
  <input type="hidden" name="action" value="bulk_delete">
  <input type="hidden" name="entity" value="<?= e($bulk_entity) ?>">
  <div id="bulkIds-<?= e($bulk_entity) ?>"></div>
</form>
