<?php $gdpPreview=$GLOBALS['gdp_excel_preview']??null; ?>
<details class="gdp-panel" id="gdpExcelImport" <?=($gdpPreview||isset($GLOBALS['gdp_excel_error']))?'open':''?>>
<summary class="fw-semibold">Nhập Excel <span class="small fw-normal text-muted">· tải mẫu, kiểm tra và xem trước khi lưu</span></summary>
<div class="mt-2">
<?php if(isset($GLOBALS['gdp_excel_error'])): ?><div class="alert alert-danger py-2" role="alert"><?=e($GLOBALS['gdp_excel_error'])?></div><?php endif; ?>
<p class="small mb-2">Mỗi dòng giao một dải tiết cho một giáo viên tại một lớp. Mỗi lớp phải đủ tiết 1–35, không trùng. Mã giáo viên lấy từ cột Mã trong CSDL, giữ nguyên số 0 đầu.</p>
<form action="<?=e(BASE_URL)?>them.php?program=1" method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center" id="gdpExcelUpload">
<input type="hidden" name="action" value="program_import_preview"><input type="hidden" name="csrf" value="<?=e($_SESSION['gdp_csrf'])?>"><input type="hidden" name="version_id" value="<?=e($gdpVersion)?>"><input type="hidden" name="MAX_FILE_SIZE" value="5242880">
<a class="btn btn-sm btn-outline-primary" href="<?=e(BASE_URL)?>program_assignment_template.php">Tải mẫu Excel</a>
<select class="form-select form-select-sm" style="width:auto;max-width:100%" name="import_subject" aria-label="Môn GDĐP nhập Excel"><?php foreach($gdpSubjects as$s): ?><option value="<?=e($s)?>" <?=($_POST['import_subject']??'')===$s?'selected':''?>><?=e($s)?></option><?php endforeach; ?></select>
<input type="file" class="form-control form-control-sm" style="width:260px;max-width:100%" name="gdp_excel" accept=".xlsx" aria-label="File phân công GDĐP" required>
<button class="btn btn-sm btn-primary" type="submit">Kiểm tra và xem trước</button>
</form>
<?php if($gdpPreview): ?>
<div class="mt-3" id="gdpExcelPreview">
<?php if($gdpPreview['error_count']): ?><div class="alert alert-danger py-2" role="alert"><strong>Có <?=$gdpPreview['error_count']?> lỗi, chưa lưu dữ liệu.</strong><ul class="mb-0"><?php foreach($gdpPreview['errors']as$error): ?><li><?=e($error)?></li><?php endforeach; ?></ul></div>
<?php else: ?><div class="alert alert-success py-2">Hợp lệ: <?=count($gdpPreview['rows'])?> dải tiết, <?=count($gdpPreview['configs'])?> lớp, mỗi lớp đủ 35 tiết.</div><?php endif; ?>
<?php if($gdpPreview['rows']): ?><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Dòng Excel</th><th>Mã giáo viên</th><th>Giáo viên</th><th>Lớp</th><th>Từ tiết</th><th>Đến tiết</th><th>Thay đổi</th></tr></thead><tbody>
<?php foreach($gdpPreview['rows']as$r): ?><tr><td><?=$r['row']?></td><td><?=e($r['code'])?></td><td><?=e($r['teacher'])?></td><td><?=e($r['class'])?></td><td><?=e($r['from'])?></td><td><?=e($r['to'])?></td><td><?=isset($gdpConfigs[$r['class']])?'Thay phân công GDĐP của lớp':'Thêm cấu hình GDĐP'?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php if(!$gdpPreview['error_count']): ?>
<p class="small">Sẽ thay toàn bộ dải tiết GDĐP của các lớp trong file. Các lớp khác giữ nguyên. TKB đã công bố và sổ đã ký giữ nguyên.</p>
<form action="<?=e(BASE_URL)?>them.php?program=1" method="post" id="gdpExcelCommit" onsubmit="return confirm('Lưu các dải tiết đã xem trước cho các lớp trong file?')">
<input type="hidden" name="action" value="program_import_commit"><input type="hidden" name="csrf" value="<?=e($_SESSION['gdp_csrf'])?>"><input type="hidden" name="version_id" value="<?=e($gdpVersion)?>"><input type="hidden" name="preview_token" value="<?=e($gdpPreview['token'])?>">
<button class="btn btn-sm btn-success" type="submit">Xác nhận nhập <?=count($gdpPreview['configs'])?> lớp</button>
</form><?php endif; ?>
</div><?php endif; ?>
<details class="small mt-2"><summary>Cách điền mẫu</summary><p class="mt-1 mb-0">Trang “Phan cong GDDP” là dữ liệu nhập; “Giao vien” cung cấp mã và tên để sao chép. Có thể bỏ trống Họ tên nếu đã nhập đúng mã. Thêm dòng khi chia dải tiết cho nhiều giáo viên. Xóa hẳn dòng của lớp không cần cập nhật. Từ tiết và Đến tiết là thứ tự tiết chương trình, không phải tuần học. Chỉ nhận giá trị, không nhận công thức.</p></details>
</div></details>
