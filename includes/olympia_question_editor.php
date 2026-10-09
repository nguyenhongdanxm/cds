<?php // Shared manual editor; rendered only in authorized question-bank views.
$editScope=(string)($q['grade_scope']??'all');$editGrades=explode(',',str_replace(' ','',$editScope));$personalEditor=isset($setId)&&$setId!=='';
?>
<details class="olympia-question-editor mt-2" style="margin-top:10px"><summary class="btn btn-outline-primary" style="cursor:pointer;display:inline-block">✏️ Sửa câu hỏi</summary>
<form method="post" style="padding:12px 0;display:grid;gap:10px">
<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="update_question">
<input type="hidden" name="<?=$personalEditor?'week':'week_id'?>" value="<?=e($weekId)?>">
<?php if($personalEditor):?><input type="hidden" name="set" value="<?=e($setId)?>"><?php endif?>
<input type="hidden" name="question_id" value="<?=e($q['id'])?>">
<label>Vòng thi <select class="form-select" name="stage_name"><?php foreach(array_unique(array_merge(['Khởi động','Vượt chướng ngại vật','Tăng tốc','Về đích'],[(string)$q['stage_name']])) as $stage):?><option value="<?=e($stage)?>" <?=$stage===$q['stage_name']?'selected':''?>><?=e($stage)?></option><?php endforeach?></select></label>
<div style="display:flex;gap:12px;flex-wrap:wrap"><label><input type="checkbox" name="all_grades" value="1" <?=$editScope==='all'?'checked':''?>> Tất cả khối</label><?php foreach(array_map('strval',range(1,12)) as $grade):?><label><input type="checkbox" name="grade_scopes[]" value="<?=$grade?>" <?=in_array($grade,$editGrades,true)?'checked':''?>> Khối <?=$grade?></label><?php endforeach?></div>
<small class="text-muted muted">Bỏ tích “Tất cả khối” để chọn khối riêng.</small>
<label>Nội dung câu hỏi<textarea class="form-control" name="question_text" rows="3" required style="width:100%"><?=e($q['question_text'])?></textarea></label>
<label>Đáp án<textarea class="form-control" name="answer_text" rows="2" style="width:100%"><?=e($q['answer_text'])?></textarea></label>
<div style="display:flex;gap:12px;flex-wrap:wrap"><label>Điểm <input class="form-control" type="number" name="points" min="0" required value="<?=e($q['points'])?>"></label><label>Thứ tự <input class="form-control" type="number" name="sort_order" min="0" required value="<?=e($q['sort_order'])?>"></label></div>
<div><button class="btn btn-primary">💾 Lưu thay đổi</button> <button type="button" class="btn btn-outline-secondary" onclick="this.closest('form').reset();this.closest('details').open=false">Hủy</button></div>
</form></details>
