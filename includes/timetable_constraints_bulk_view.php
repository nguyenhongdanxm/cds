<?php
// Included inside the summary panel, after the existing overview.
$bulkItems=tlc_bulk_items($data);
?>
<div class="mt-3 border-top pt-3"><h5>Tích chọn xóa ràng buộc</h5><p class="small text-muted">Chỉ xóa các mục được tích trong phiên bản tuần hiện tại. TKB đã công bố giữ nguyên.</p>
<form method="post" action="/tkb_xep.php" class="constraint-bulk-form">
<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="constraints_bulk_delete"><input type="hidden" name="bulk_origin" value="summary"><input type="hidden" name="confirm_bulk" value="1">
<label class="me-3"><input type="checkbox" data-bulk-all> Chọn tất cả danh sách</label>
<button class="btn btn-outline-danger btn-sm" data-bulk-submit disabled>Xóa đã chọn (0)</button>
<div class="table-responsive mt-2" style="max-height:420px"><table class="table table-sm"><thead><tr><th>Chọn</th><th>Loại</th><th>Nội dung</th></tr></thead><tbody>
<?php foreach($bulkItems as$item):?><tr><td><input type="checkbox" name="selected_constraints[]" value="<?=e($item['token'])?>"></td><td><?=e($item['title'])?></td><td><?=e($item['label'])?></td></tr><?php endforeach;?>
</tbody></table></div></form></div>
