<?php
/** Native manual editor. Reads existing records; legacy save/delete endpoints remain unchanged. */
$nativeManualRows=get_manual_assignments();$nativeManualEdit=cds_can_feature('cm.pccm','edit');$nativeManualGroups=[];
foreach($nativeManualRows as$entry)$nativeManualGroups[$entry['teacher']][]=$entry;
?>
<section id="cdsManualAssignments" class="pccm-native-manual" data-native-manual="1">
<?php if($nativeManualEdit): ?>
<form method="post" action="<?=e(BASE_URL)?>them.php" class="pccm-manual-grid">
<input type="hidden" name="cds_manual_action" value="save"><input type="hidden" name="manual_return_page" value="them">
<div class="pccm-manual-teacher-field"><label class="form-label">Giáo viên</label><input name="manual_teacher" class="form-control form-control-sm" list="cdsTeacherNames" required placeholder="Chọn giáo viên"><datalist id="cdsTeacherNames"><?php foreach($teachers as$name): ?><option value="<?=e($name)?>"></option><?php endforeach; ?></datalist></div>
<div><label class="form-label">Môn / nội dung</label><input name="manual_subject" class="form-control form-control-sm" list="pccmManualSubjects" required maxlength="150"><datalist id="pccmManualSubjects"><?php foreach($subjects as$subject): ?><option value="<?=e($subject)?>"></option><?php endforeach; ?></datalist></div>
<div><label class="form-label">Lớp</label><input name="manual_class" class="form-control form-control-sm" list="cdsClassNames" required><datalist id="cdsClassNames"><?php foreach($classes as$class): ?><option value="<?=e($class)?>"></option><?php endforeach; ?></datalist></div>
<div><label class="form-label">Số tiết</label><input name="manual_periods" class="form-control form-control-sm" type="number" min="0.1" step="0.1" required></div>
<div><label class="form-label">Ghi chú</label><input name="manual_note" class="form-control form-control-sm" maxlength="200"></div>
<button type="submit" class="btn btn-primary btn-sm">Thêm</button>
</form>
<?php else: ?><p class="small text-muted">Bạn có quyền xem phân công thủ công.</p><?php endif; ?>
<details class="mt-3"><summary class="small">Quản lý <?=count($nativeManualRows)?> phân công thủ công đã có</summary>
<?php foreach($nativeManualGroups as$name=>$entries): ?>
<div class="pccm-manual-owner border-top py-2" data-manual-teacher="<?=e($name)?>"><strong class="small"><?=e($name)?></strong><div class="d-flex flex-wrap gap-2 mt-1">
<?php foreach($entries as$entry): ?><div class="pccm-manual-entry"><span><?=e($entry['subject'])?> · <?=e($entry['class'])?> · <?=e($entry['periods'])?> tiết</span>
<?php if($nativeManualEdit): ?><form method="post" action="<?=e(BASE_URL)?>them.php" class="d-inline"><input type="hidden" name="cds_manual_action" value="delete"><input type="hidden" name="manual_return_page" value="them"><input type="hidden" name="manual_id" value="<?=e($entry['id'])?>"><button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Xóa phân công <?=e($entry['subject'].' '.$entry['class'])?>"><i class="bi bi-trash"></i></button></form><?php endif; ?></div><?php endforeach; ?>
</div></div><?php endforeach; ?>
<?php if(!$nativeManualRows): ?><p class="small text-muted mt-2">Chưa có phân công thủ công.</p><?php endif; ?>
</details>
</section>
